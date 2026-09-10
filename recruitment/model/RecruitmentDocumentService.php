<?php

declare(strict_types=1);

final class RecruitmentDocumentService
{
    public function __construct(private PDO $db, private string $dataKey, private string $privateRoot)
    {
        $documentRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!RecruitmentDocumentPolicy::privateRootIsSafe($privateRoot, $documentRoot)) {
            throw new RuntimeException('Recruitment document storage must be configured outside the web root.');
        }
    }

    public function request(string $applicationPublicId, array $input, string $actor): string
    {
        $purpose = RecruitmentContentPolicy::requiredText($input['purpose_code'] ?? '', 'Purpose', 3, 80);
        $classification = RecruitmentContentPolicy::oneOf($input['classification'] ?? '', 'classification', ['standard', 'confidential', 'restricted', 'highly_restricted']);
        $dueAt = RecruitmentContentPolicy::utcDateTime($input['due_at'] ?? '', 'due date');
        $this->db->beginTransaction();
        try {
            $application = $this->lockApplication($applicationPublicId);
            if (!in_array((string)$application['current_status'], ['requirements', 'conditional_offer', 'offer_accepted', 'onboarding'], true)) {
                throw new DomainException('Documents may be requested only at an approved requirements stage.');
            }
            $publicId = self::uuidV4();
            $stmt = $this->db->prepare("INSERT INTO recruitment_document_requests
                (public_id, application_id, purpose_code, classification, request_status, due_at, requested_by_username, requested_at)
                VALUES (:public_id, :application_id, :purpose, :classification, 'open', :due_at, :actor, UTC_TIMESTAMP())");
            $stmt->execute(['public_id'=>$publicId,'application_id'=>$application['application_id'],'purpose'=>$purpose,'classification'=>$classification,'due_at'=>$dueAt,'actor'=>$actor]);
            $this->audit('document_requested', $actor, 'document_request', $publicId, ['purpose'=>$purpose,'classification'=>$classification]);
            $this->db->commit();
            return $publicId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function upload(int $candidateId, string $requestPublicId, array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new InvalidArgumentException('Select a file that completed uploading.');
        }
        $tmp = (string)$file['tmp_name'];
        $size = (int)filesize($tmp);
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $name = basename((string)($file['name'] ?? 'document'));
        $errors = RecruitmentDocumentPolicy::validateUpload($mime, $size, $name);
        if ($errors !== []) throw new InvalidArgumentException(implode(' ', $errors));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $validExtensions = ['application/pdf'=>['pdf'], 'image/jpeg'=>['jpg','jpeg'], 'image/png'=>['png']];
        if (!in_array($extension, $validExtensions[$mime] ?? [], true)) {
            throw new InvalidArgumentException('The file extension does not match its content.');
        }

        $request = $this->candidateRequest($candidateId, $requestPublicId, true);
        if ((string)$request['request_status'] !== 'open') throw new DomainException('This document request is not open.');
        $quota = $this->db->prepare('SELECT COALESCE(SUM(byte_size),0) FROM recruitment_documents WHERE candidate_id = :candidate_id AND deleted_at IS NULL');
        $quota->execute(['candidate_id'=>$candidateId]);
        if ((int)$quota->fetchColumn() + $size > 26_214_400) throw new DomainException('Your secure document storage quota has been reached.');

        $key = RecruitmentDocumentPolicy::storageKey();
        $target = rtrim($this->privateRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Secure storage could not be prepared.');
        if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('The upload could not be moved into quarantine.');
        @chmod($target, 0600);
        try {
            $publicId = self::uuidV4();
            $stmt = $this->db->prepare("INSERT INTO recruitment_documents
                (public_id, request_id, candidate_id, storage_key, original_name_ciphertext, content_sha256, media_type, byte_size, scan_status, review_status)
                VALUES (:public_id,:request_id,:candidate_id,:storage_key,:name,:sha,:mime,:size,'quarantined','not_available')");
            $stmt->execute(['public_id'=>$publicId,'request_id'=>$request['request_id'],'candidate_id'=>$candidateId,'storage_key'=>$key,'name'=>RecruitmentSecurity::encrypt($name,$this->dataKey),'sha'=>hash_file('sha256',$target),'mime'=>$mime,'size'=>$size]);
            $documentId = (int)$this->db->lastInsertId();
            $this->event($documentId, 'uploaded_to_quarantine', 'candidate', (string)$candidateId, ['byte_size'=>$size,'media_type'=>$mime]);
            return $publicId;
        } catch (Throwable $e) {
            @unlink($target);
            throw $e;
        }
    }

    public function recordScan(string $documentPublicId, string $result, string $scanner, ?string $code = null): void
    {
        $result = RecruitmentContentPolicy::oneOf($result, 'scan result', ['clean','infected','scan_failed']);
        $document = $this->document($documentPublicId, true);
        if ((string)$document['scan_status'] !== 'quarantined' && (string)$document['scan_status'] !== 'scan_failed') throw new DomainException('This document is not awaiting a scan.');
        $review = $result === 'clean' ? 'pending' : 'not_available';
        $stmt = $this->db->prepare('UPDATE recruitment_documents SET scan_status=:scan, review_status=:review, scanned_at=UTC_TIMESTAMP() WHERE document_id=:id AND deleted_at IS NULL');
        $stmt->execute(['scan'=>$result,'review'=>$review,'id'=>$document['document_id']]);
        $this->event((int)$document['document_id'], 'malware_scan_completed', 'scanner', $scanner, ['result'=>$result,'code'=>$code]);
    }

    public function review(string $documentPublicId, string $decision, string $reason, string $actor): void
    {
        $decision = RecruitmentContentPolicy::oneOf($decision, 'decision', ['approved','rejected']);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Review reason', 3, 500);
        $document = $this->document($documentPublicId, true);
        if ((string)$document['scan_status'] !== 'clean' || (string)$document['review_status'] !== 'pending') throw new DomainException('Only clean, pending documents can be reviewed.');
        $stmt = $this->db->prepare('UPDATE recruitment_documents SET review_status=:decision, reviewed_at=UTC_TIMESTAMP(), reviewed_by_username=:actor WHERE document_id=:id AND scan_status=\'clean\' AND review_status=\'pending\'');
        $stmt->execute(['decision'=>$decision,'actor'=>$actor,'id'=>$document['document_id']]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('The document changed before review completed.');
        $this->db->prepare('INSERT INTO recruitment_document_reviews (document_id,decision,reason,reviewer_username) VALUES (:id,:decision,:reason,:actor)')->execute(['id'=>$document['document_id'],'decision'=>$decision,'reason'=>$reason,'actor'=>$actor]);
        $this->event((int)$document['document_id'], 'review_completed', 'staff', $actor, ['decision'=>$decision]);
    }

    public function releaseToCandidate(int $candidateId, string $documentPublicId): array
    {
        $document = $this->document($documentPublicId, false);
        if ((int)$document['candidate_id'] !== $candidateId || !RecruitmentDocumentPolicy::canRelease((string)$document['scan_status'], (string)$document['review_status'])) throw new DomainException('This document is not available.');
        return $this->releasedFile($document, 'candidate', (string)$candidateId);
    }

    public function releaseToStaff(string $documentPublicId, string $actor): array
    {
        return $this->releasedFile($this->document($documentPublicId, false), 'staff', $actor);
    }

    private function releasedFile(array $document, string $actorType, string $actor): array
    {
        if (!RecruitmentDocumentPolicy::canRelease((string)$document['scan_status'], (string)$document['review_status'])) throw new DomainException('This document is not approved for release.');
        $path = rtrim($this->privateRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$document['storage_key']);
        if (!is_file($path) || !hash_equals((string)$document['content_sha256'], hash_file('sha256',$path))) throw new RuntimeException('Document integrity verification failed.');
        $this->event((int)$document['document_id'], 'released', $actorType, $actor, []);
        return ['path'=>$path,'name'=>RecruitmentSecurity::decrypt((string)$document['original_name_ciphertext'],$this->dataKey),'media_type'=>(string)$document['media_type'],'byte_size'=>(int)$document['byte_size']];
    }

    private function candidateRequest(int $candidateId, string $publicId, bool $lock): array
    {
        $sql = 'SELECT dr.* FROM recruitment_document_requests dr JOIN recruitment_applications a ON a.application_id=dr.application_id WHERE dr.public_id=:public_id AND a.candidate_id=:candidate_id LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $stmt=$this->db->prepare($sql); $stmt->execute(['public_id'=>$publicId,'candidate_id'=>$candidateId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC); if (!$row) throw new DomainException('Document request not found.'); return $row;
    }

    private function document(string $publicId, bool $lock): array
    {
        $stmt=$this->db->prepare('SELECT * FROM recruitment_documents WHERE public_id=:public_id AND deleted_at IS NULL LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['public_id'=>$publicId]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if (!$row) throw new DomainException('Document not found.'); return $row;
    }

    private function lockApplication(string $publicId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM recruitment_applications WHERE public_id=:public_id LIMIT 1 FOR UPDATE'); $stmt->execute(['public_id'=>$publicId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC); if (!$row) throw new DomainException('Application not found.'); return $row;
    }

    private function event(int $documentId,string $type,string $actorType,string $actor,array $metadata): void
    { $this->db->prepare('INSERT INTO recruitment_document_events (document_id,event_type,actor_type,actor_reference,metadata_json) VALUES (:id,:type,:actor_type,:actor,:metadata)')->execute(['id'=>$documentId,'type'=>$type,'actor_type'=>$actorType,'actor'=>$actor,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]); }
    private function audit(string $type,string $actor,string $entityType,string $entityId,array $metadata): void
    { $this->db->prepare('INSERT INTO recruitment_audit_events (event_type,actor_type,actor_reference,subject_type,subject_reference,reason_code,metadata_json) VALUES (:type,\'staff\',:actor,:entity_type,:entity_id,\'approved_stage_request\',:metadata)')->execute(['type'=>$type,'actor'=>$actor,'entity_type'=>$entityType,'entity_id'=>$entityId,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]); }
    private static function uuidV4(): string { $b=random_bytes(16); $b[6]=chr((ord($b[6])&0x0f)|0x40); $b[8]=chr((ord($b[8])&0x3f)|0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4)); }
}
