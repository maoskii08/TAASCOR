<?php
declare(strict_types=1);
final class RecruitmentStaffCaseService
{
    public function __construct(private PDO $db,private string $dataKey,private RecruitmentStaffAccessService $access,private RecruitmentNotificationOutbox $outbox){}

    public function detail(string $applicationPublicId,string $actor): array
    {
        $this->access->assertCapability($actor,'application.view');
        $statement=$this->db->prepare('SELECT a.application_id,a.public_id,a.current_status,a.candidate_summary_ciphertext,a.submitted_at,a.updated_at,c.candidate_id,c.public_id AS candidate_public_id,c.email_ciphertext,j.title,j.public_id AS job_public_id FROM recruitment_applications a JOIN recruitment_candidates c ON c.candidate_id=a.candidate_id JOIN recruitment_jobs j ON j.job_id=a.job_id WHERE a.public_id=:public_id LIMIT 1');
        $statement->execute(['public_id'=>trim($applicationPublicId)]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new DomainException('Application not found.');
        $row['email']=RecruitmentSecurity::decrypt((string)$row['email_ciphertext'],$this->dataKey);
        $row['candidate_summary']=$row['candidate_summary_ciphertext']?json_decode(RecruitmentSecurity::decrypt((string)$row['candidate_summary_ciphertext'],$this->dataKey),true,512,JSON_THROW_ON_ERROR):[];
        unset($row['email_ciphertext'],$row['candidate_summary_ciphertext']);
        $queries=[
            'timeline'=>'SELECT from_status,to_status,candidate_message,reason_code,changed_by_type,changed_by_reference,changed_at FROM recruitment_application_events WHERE application_id=:id ORDER BY changed_at',
            'assignments'=>'SELECT assignee_username,assignment_role,assigned_by_username,assigned_at,unassigned_at FROM recruitment_application_assignments WHERE application_id=:id ORDER BY assigned_at DESC',
            'interviews'=>'SELECT public_id,interview_type,starts_at_utc,ends_at_utc,timezone_name,location_type,status,confirmed_at,completed_at FROM recruitment_interviews WHERE application_id=:id ORDER BY starts_at_utc',
            'offers'=>'SELECT public_id,status,current_version,expires_at,delivered_at,responded_at FROM recruitment_offers WHERE application_id=:id ORDER BY offer_id DESC',
            'onboarding'=>'SELECT public_id,status,owner_username,target_start_date,readiness_approved_at,updated_at FROM recruitment_onboarding_cases WHERE application_id=:id ORDER BY onboarding_case_id DESC',
            'documents'=>'SELECT dr.public_id,dr.purpose_code,dr.classification,dr.request_status,dr.due_at,d.public_id AS document_public_id,d.scan_status,d.review_status FROM recruitment_document_requests dr LEFT JOIN recruitment_documents d ON d.request_id=dr.request_id AND d.deleted_at IS NULL WHERE dr.application_id=:id ORDER BY dr.request_id DESC',
        ];
        foreach($queries as $key=>$sql){$q=$this->db->prepare($sql);$q->execute(['id'=>$row['application_id']]);$row[$key]=$q->fetchAll(PDO::FETCH_ASSOC)?:[];}
        $this->audit('candidate_pii_viewed',$actor,'application',$applicationPublicId,'authorized_case_review',['fields'=>['email','candidate_summary']]);
        return $row;
    }

    public function sendCandidateMessage(string $applicationPublicId,string $subject,string $body,string $actor): string
    {
        $this->access->assertCapability($actor,'application.manage');
        $subject=RecruitmentContentPolicy::requiredText($subject,'Subject',3,190); $body=RecruitmentContentPolicy::requiredText($body,'Message',10,4000);
        $q=$this->db->prepare('SELECT application_id,candidate_id FROM recruitment_applications WHERE public_id=:public_id LIMIT 1');$q->execute(['public_id'=>trim($applicationPublicId)]);$application=$q->fetch(PDO::FETCH_ASSOC);if(!$application)throw new DomainException('Application not found.');
        $publicId=self::uuidV4();
        $this->db->beginTransaction();
        try{$this->db->prepare("INSERT INTO recruitment_candidate_messages (public_id,candidate_id,application_id,direction,message_type,subject_ciphertext,body_ciphertext,sender_type,sender_reference) VALUES (:public_id,:candidate_id,:application_id,'outbound','staff_message',:subject,:body,'staff',:actor)")->execute(['public_id'=>$publicId,'candidate_id'=>$application['candidate_id'],'application_id'=>$application['application_id'],'subject'=>RecruitmentSecurity::encrypt($subject,$this->dataKey),'body'=>RecruitmentSecurity::encrypt($body,$this->dataKey),'actor'=>$actor]);$this->outbox->enqueue((int)$application['candidate_id'],'candidate_message',['message_public_id'=>$publicId],$publicId);$this->audit('candidate_message_sent',$actor,'candidate_message',$publicId,'candidate_communication',['application_public_id'=>$applicationPublicId]);$this->db->commit();return $publicId;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function audit(string $event,string $actor,string $subjectType,string $subjectReference,string $reason,array $metadata):void{$this->db->prepare("INSERT INTO recruitment_audit_events (event_type,actor_type,actor_reference,subject_type,subject_reference,reason_code,metadata_json) VALUES (:event,'staff',:actor,:subject_type,:subject_reference,:reason,:metadata)")->execute(['event'=>$event,'actor'=>$actor,'subject_type'=>$subjectType,'subject_reference'=>$subjectReference,'reason'=>$reason,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);}
    private static function uuidV4():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}
}
