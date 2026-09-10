<?php

declare(strict_types=1);

require_once __DIR__ . '/RecruitmentPolicy.php';
require_once __DIR__ . '/RecruitmentSecurity.php';
require_once __DIR__ . '/RecruitmentContentPolicy.php';
require_once __DIR__ . '/RecruitmentStaffAccessService.php';
require_once __DIR__ . '/RecruitmentNotificationOutbox.php';

final class RecruitmentOfferOnboardingService
{
    public function __construct(
        private PDO $db,
        private string $dataKey,
        private RecruitmentStaffAccessService $access,
        private RecruitmentNotificationOutbox $outbox
    ) {
        if (strlen($dataKey) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
    }

    public function prepareOffer(string $applicationPublicId, array $terms, ?string $expiresAt, string $actor): string
    {
        $this->access->assertCapability($actor, 'offer.prepare');
        $normalizedTerms = $this->validatedOfferTerms($terms);
        $expiry = $expiresAt === null || trim($expiresAt) === ''
            ? null
            : RecruitmentContentPolicy::utcDateTime($expiresAt, 'offer expiry');
        if ($expiry !== null && strtotime($expiry . ' UTC') <= time()) {
            throw new InvalidArgumentException('Offer expiry must be in the future.');
        }
        $publicId = self::uuidV4();

        return $this->transaction(function () use ($applicationPublicId, $normalizedTerms, $expiry, $actor, $publicId): string {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null || (string)$application['current_status'] !== 'conditional_offer') {
                throw new DomainException('The application must reach conditional offer before preparation.');
            }
            $existing = $this->db->prepare('SELECT offer_id FROM recruitment_offers WHERE application_id = :application_id LIMIT 1 FOR UPDATE');
            $existing->execute(['application_id' => $application['application_id']]);
            if ($existing->fetchColumn()) {
                throw new DomainException('This application already has an offer record.');
            }
            $this->db->prepare(
                "INSERT INTO recruitment_offers
                    (public_id, application_id, status, current_version, prepared_by_username, expires_at)
                 VALUES (:public_id, :application_id, 'draft', 1, :actor, :expires_at)"
            )->execute([
                'public_id' => $publicId,
                'application_id' => $application['application_id'],
                'actor' => $actor,
                'expires_at' => $expiry,
            ]);
            $offerId = (int)$this->db->lastInsertId();
            $this->insertOfferVersion($offerId, 1, $normalizedTerms, $actor);
            $this->audit('offer_prepared', $actor, 'offer', $publicId, 'candidate_selection', [
                'application_public_id' => $applicationPublicId,
                'version' => 1,
                'expires_at' => $expiry,
            ]);
            return $publicId;
        });
    }

    public function reviseOffer(string $offerPublicId, array $terms, ?string $expiresAt, string $reason, string $actor): int
    {
        $this->access->assertCapability($actor, 'offer.prepare');
        $normalizedTerms = $this->validatedOfferTerms($terms);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Revision reason', 3, 500);
        $expiry = $expiresAt === null || trim($expiresAt) === ''
            ? null
            : RecruitmentContentPolicy::utcDateTime($expiresAt, 'offer expiry');
        if ($expiry !== null && strtotime($expiry . ' UTC') <= time()) {
            throw new InvalidArgumentException('Offer expiry must be in the future.');
        }

        return $this->transaction(function () use ($offerPublicId, $normalizedTerms, $expiry, $reason, $actor): int {
            $offer = $this->lockByPublicId('recruitment_offers', 'offer_id', $offerPublicId);
            if ($offer === null || !in_array((string)$offer['status'], ['draft', 'rejected'], true)) {
                throw new DomainException('Only a draft or rejected offer can be revised.');
            }
            $version = (int)$offer['current_version'] + 1;
            $this->db->prepare('UPDATE recruitment_offer_versions SET superseded_at = UTC_TIMESTAMP() WHERE offer_id = :offer_id AND superseded_at IS NULL')
                ->execute(['offer_id' => $offer['offer_id']]);
            $this->insertOfferVersion((int)$offer['offer_id'], $version, $normalizedTerms, $actor);
            $statement = $this->db->prepare(
                "UPDATE recruitment_offers
                    SET current_version = :version, status = 'draft', prepared_by_username = :actor,
                        approved_by_username = NULL, approved_at = NULL, expires_at = :expires_at
                  WHERE offer_id = :offer_id AND current_version = :expected_version"
            );
            $statement->execute([
                'version' => $version,
                'actor' => $actor,
                'expires_at' => $expiry,
                'offer_id' => $offer['offer_id'],
                'expected_version' => $offer['current_version'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The offer changed while the revision was being saved.');
            }
            $this->audit('offer_revised', $actor, 'offer', $offerPublicId, $reason, ['version' => $version]);
            return $version;
        });
    }

    public function submitOfferForApproval(string $offerPublicId, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'offer.prepare');
        $this->transitionOffer($offerPublicId, 'pending_approval', $reason, $actor, false);
    }

    public function decideOffer(string $offerPublicId, string $decision, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'offer.approve');
        $decision = RecruitmentContentPolicy::oneOf($decision, 'offer decision', ['approved', 'rejected']);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Decision reason', 3, 500);

        $this->transaction(function () use ($offerPublicId, $decision, $reason, $actor): void {
            $offer = $this->lockByPublicId('recruitment_offers', 'offer_id', $offerPublicId);
            if ($offer === null || (string)$offer['status'] !== 'pending_approval') {
                throw new DomainException('The offer is not pending approval.');
            }
            if (!RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$offer['prepared_by_username'], $actor)) {
                throw new DomainException('The offer preparer cannot approve or reject the same version.');
            }
            $version = $this->currentOfferVersion((int)$offer['offer_id'], (int)$offer['current_version']);
            $this->db->prepare(
                "UPDATE recruitment_offers
                    SET status = :decision,
                        approved_by_username = CASE WHEN :approved_decision = 'approved' THEN :actor ELSE NULL END,
                        approved_at = CASE WHEN :approved_time = 'approved' THEN UTC_TIMESTAMP() ELSE NULL END
                  WHERE offer_id = :offer_id AND status = 'pending_approval'"
            )->execute([
                'decision' => $decision,
                'approved_decision' => $decision,
                'actor' => $actor,
                'approved_time' => $decision,
                'offer_id' => $offer['offer_id'],
            ]);
            $this->db->prepare(
                'INSERT INTO recruitment_offer_approvals
                    (offer_id, offer_version_id, decision, decided_by_username, decision_reason)
                 VALUES (:offer_id, :offer_version_id, :decision, :actor, :reason)'
            )->execute([
                'offer_id' => $offer['offer_id'],
                'offer_version_id' => $version['offer_version_id'],
                'decision' => $decision,
                'actor' => $actor,
                'reason' => $reason,
            ]);
            $this->audit('offer_decided', $actor, 'offer', $offerPublicId, $reason, [
                'decision' => $decision,
                'version' => (int)$offer['current_version'],
                'content_sha256' => $version['content_sha256'],
            ]);
        });
    }

    public function deliverOffer(string $offerPublicId, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'offer.prepare');
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Delivery reason', 3, 500);
        $this->transaction(function () use ($offerPublicId, $reason, $actor): void {
            $offer = $this->lockByPublicId('recruitment_offers', 'offer_id', $offerPublicId);
            if ($offer === null || !RecruitmentPolicy::canTransition('offer', (string)$offer['status'], 'delivered')) {
                throw new DomainException('Only an approved offer can be delivered.');
            }
            if ($offer['expires_at'] !== null && strtotime((string)$offer['expires_at'] . ' UTC') <= time()) {
                throw new DomainException('The approved offer has already expired.');
            }
            $version = $this->currentOfferVersion((int)$offer['offer_id'], (int)$offer['current_version']);
            $this->db->prepare("UPDATE recruitment_offers SET status = 'delivered', delivered_at = UTC_TIMESTAMP() WHERE offer_id = :offer_id AND status = 'approved'")
                ->execute(['offer_id' => $offer['offer_id']]);
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$offer['application_id']);
            if ($application === null) {
                throw new DomainException('The offer application no longer exists.');
            }
            $this->outbox->enqueue((int)$application['candidate_id'], 'offer_available', [
                'offer_public_id' => $offerPublicId,
                'version' => (int)$offer['current_version'],
                'content_sha256' => $version['content_sha256'],
                'expires_at' => $offer['expires_at'],
            ], $offerPublicId . '|' . $offer['current_version']);
            $this->audit('offer_delivered', $actor, 'offer', $offerPublicId, $reason, [
                'version' => (int)$offer['current_version'],
                'content_sha256' => $version['content_sha256'],
            ]);
        });
    }

    public function respondToOffer(int $candidateId, string $offerPublicId, string $response, ?string $note): void
    {
        $response = RecruitmentContentPolicy::oneOf($response, 'offer response', ['accepted', 'declined']);
        $responseNote = RecruitmentContentPolicy::optionalText($note ?? '', 'Response note', 1000);
        $this->transaction(function () use ($candidateId, $offerPublicId, $response, $responseNote): void {
            $offer = $this->lockByPublicId('recruitment_offers', 'offer_id', $offerPublicId);
            if ($offer === null || !RecruitmentPolicy::canTransition('offer', (string)$offer['status'], $response)) {
                throw new DomainException('This offer is not available for a response.');
            }
            if ($offer['expires_at'] !== null && strtotime((string)$offer['expires_at'] . ' UTC') <= time()) {
                throw new DomainException('This offer has expired.');
            }
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$offer['application_id']);
            if ($application === null || (int)$application['candidate_id'] !== $candidateId) {
                throw new DomainException('Offer not found.');
            }
            $version = $this->currentOfferVersion((int)$offer['offer_id'], (int)$offer['current_version']);
            $evidence = hash('sha256', implode('|', [
                $candidateId,
                $offerPublicId,
                (string)$offer['current_version'],
                (string)$version['content_sha256'],
                $response,
            ]));
            $this->db->prepare(
                "INSERT INTO recruitment_offer_responses
                    (offer_id, offer_version_id, response, response_note_ciphertext, evidence_hash)
                 VALUES (:offer_id, :offer_version_id, :response, :note_ciphertext, :evidence_hash)"
            )->execute([
                'offer_id' => $offer['offer_id'],
                'offer_version_id' => $version['offer_version_id'],
                'response' => $response,
                'note_ciphertext' => $responseNote === null ? null : RecruitmentSecurity::encrypt($responseNote, $this->dataKey),
                'evidence_hash' => $evidence,
            ]);
            $this->db->prepare('UPDATE recruitment_offers SET status = :status, responded_at = UTC_TIMESTAMP() WHERE offer_id = :offer_id AND status = \'delivered\'')
                ->execute(['status' => $response, 'offer_id' => $offer['offer_id']]);
            $applicationStatus = $response === 'accepted' ? 'offer_accepted' : 'offer_declined';
            if (!RecruitmentPolicy::canTransition('application', (string)$application['current_status'], $applicationStatus)) {
                throw new DomainException('The application is not aligned with this offer response.');
            }
            $this->db->prepare('UPDATE recruitment_applications SET current_status = :status, closed_at = CASE WHEN :closed_status = \'offer_declined\' THEN UTC_TIMESTAMP() ELSE closed_at END WHERE application_id = :application_id AND current_status = :expected_status')
                ->execute([
                    'status' => $applicationStatus,
                    'closed_status' => $applicationStatus,
                    'application_id' => $application['application_id'],
                    'expected_status' => $application['current_status'],
                ]);
            $this->recordApplicationEvent((int)$application['application_id'], (string)$application['current_status'], $applicationStatus, $response === 'accepted' ? 'Offer accepted.' : 'Offer declined.', 'candidate', (string)$candidateId, 'offer_response');
            $this->audit('offer_candidate_response', 'candidate:' . $candidateId, 'offer', $offerPublicId, 'self_service', [
                'response' => $response,
                'version' => (int)$offer['current_version'],
                'evidence_hash' => $evidence,
            ]);
        });
    }

    public function createOnboardingTemplate(array $input, array $items, string $actor): string
    {
        $this->access->assertCapability($actor, 'onboarding.manage');
        if ($items === [] || count($items) > 50) {
            throw new InvalidArgumentException('Provide 1 to 50 onboarding template items.');
        }
        $template = [
            'template_name' => RecruitmentContentPolicy::requiredText($input['template_name'] ?? '', 'Template name', 3, 190),
            'worker_type' => RecruitmentContentPolicy::requiredText($input['worker_type'] ?? '', 'Worker type', 2, 60),
            'location_scope' => RecruitmentContentPolicy::requiredText($input['location_scope'] ?? '*', 'Location scope', 1, 190),
            'client_scope' => RecruitmentContentPolicy::requiredText($input['client_scope'] ?? '*', 'Client scope', 1, 190),
            'version_number' => RecruitmentContentPolicy::positiveInteger($input['version_number'] ?? 1, 'Template version', 100000),
        ];
        $validatedItems = [];
        $codes = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Onboarding template items are invalid.');
            }
            $code = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim((string)($item['item_code'] ?? ''))) ?? '');
            if ($code === '' || strlen($code) > 80 || isset($codes[$code])) {
                throw new InvalidArgumentException('Each onboarding item requires a unique stable code.');
            }
            $codes[$code] = true;
            $validatedItems[] = [
                'item_code' => $code,
                'title' => RecruitmentContentPolicy::requiredText($item['title'] ?? '', 'Item title', 3, 190),
                'purpose_text' => RecruitmentContentPolicy::requiredText($item['purpose_text'] ?? '', 'Item purpose', 10, 500),
                'visibility_text' => RecruitmentContentPolicy::requiredText($item['visibility_text'] ?? '', 'Visibility explanation', 10, 500),
                'item_type' => RecruitmentContentPolicy::oneOf($item['item_type'] ?? '', 'item type', ['acknowledgement', 'information', 'document', 'appointment', 'training']),
                'classification' => RecruitmentContentPolicy::oneOf($item['classification'] ?? '', 'classification', ['standard', 'confidential', 'restricted', 'highly_restricted']),
                'due_offset_days' => max(0, min((int)($item['due_offset_days'] ?? 0), 365)),
                'required_flag' => filter_var($item['required_flag'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'dependency_item_code' => RecruitmentContentPolicy::optionalText($item['dependency_item_code'] ?? '', 'Dependency code', 80),
                'display_order' => $index + 1,
            ];
        }
        foreach ($validatedItems as $item) {
            if ($item['dependency_item_code'] !== null && !isset($codes[$item['dependency_item_code']])) {
                throw new InvalidArgumentException('Every onboarding dependency must reference an item in the same template.');
            }
            if ($item['dependency_item_code'] === $item['item_code']) {
                throw new InvalidArgumentException('An onboarding item cannot depend on itself.');
            }
        }
        foreach (array_keys($codes) as $startCode) {
            $seen = [];
            $cursor = $startCode;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    throw new InvalidArgumentException('Onboarding item dependencies must not contain a cycle.');
                }
                $seen[$cursor] = true;
                $next = null;
                foreach ($validatedItems as $candidateItem) {
                    if ($candidateItem['item_code'] === $cursor) {
                        $next = $candidateItem['dependency_item_code'];
                        break;
                    }
                }
                $cursor = $next;
            }
        }
        $publicId = self::uuidV4();

        return $this->transaction(function () use ($template, $validatedItems, $publicId, $actor): string {
            $this->db->prepare(
                "INSERT INTO recruitment_onboarding_templates
                    (public_id, template_name, worker_type, location_scope, client_scope,
                     version_number, status, created_by_username)
                 VALUES
                    (:public_id, :template_name, :worker_type, :location_scope, :client_scope,
                     :version_number, 'draft', :actor)"
            )->execute(['public_id' => $publicId, 'actor' => $actor] + $template);
            $templateId = (int)$this->db->lastInsertId();
            $insert = $this->db->prepare(
                'INSERT INTO recruitment_onboarding_template_items
                    (template_id, item_code, title, purpose_text, visibility_text, item_type,
                     classification, due_offset_days, required_flag, dependency_item_code, display_order)
                 VALUES
                    (:template_id, :item_code, :title, :purpose_text, :visibility_text, :item_type,
                     :classification, :due_offset_days, :required_flag, :dependency_item_code, :display_order)'
            );
            foreach ($validatedItems as $item) {
                $insert->execute(['template_id' => $templateId] + $item);
            }
            $this->audit('onboarding_template_created', $actor, 'onboarding_template', $publicId, 'approved_process_design', [
                'version' => $template['version_number'],
                'item_count' => count($validatedItems),
            ]);
            return $publicId;
        });
    }

    public function approveOnboardingTemplate(string $templatePublicId, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'onboarding.manage');
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Approval reason', 3, 500);
        $this->transaction(function () use ($templatePublicId, $reason, $actor): void {
            $template = $this->lockByPublicId('recruitment_onboarding_templates', 'template_id', $templatePublicId);
            if ($template === null || (string)$template['status'] !== 'draft') {
                throw new DomainException('Only a draft onboarding template can be approved.');
            }
            if (!RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$template['created_by_username'], $actor)) {
                throw new DomainException('The template creator cannot approve the same template version.');
            }
            $this->db->prepare("UPDATE recruitment_onboarding_templates SET status = 'approved', approved_by_username = :actor, approved_at = UTC_TIMESTAMP() WHERE template_id = :template_id AND status = 'draft'")
                ->execute(['actor' => $actor, 'template_id' => $template['template_id']]);
            $this->audit('onboarding_template_approved', $actor, 'onboarding_template', $templatePublicId, $reason, [
                'version' => (int)$template['version_number'],
            ]);
        });
    }

    public function startOnboarding(string $offerPublicId, string $templatePublicId, string $owner, ?string $targetStartDate, string $actor): string
    {
        $this->access->assertCapability($actor, 'onboarding.manage');
        $owner = RecruitmentContentPolicy::requiredText($owner, 'Onboarding owner', 1, 190);
        $targetDate = RecruitmentContentPolicy::date($targetStartDate ?? '', 'target start date');
        $publicId = self::uuidV4();

        return $this->transaction(function () use ($offerPublicId, $templatePublicId, $owner, $targetDate, $actor, $publicId): string {
            $offer = $this->lockByPublicId('recruitment_offers', 'offer_id', $offerPublicId);
            if ($offer === null || (string)$offer['status'] !== 'accepted') {
                throw new DomainException('An accepted offer is required to start onboarding.');
            }
            $template = $this->lockByPublicId('recruitment_onboarding_templates', 'template_id', $templatePublicId);
            if ($template === null || (string)$template['status'] !== 'approved') {
                throw new DomainException('An approved onboarding template is required.');
            }
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$offer['application_id']);
            if ($application === null || (string)$application['current_status'] !== 'offer_accepted') {
                throw new DomainException('The application is not ready for onboarding.');
            }
            $this->db->prepare(
                "INSERT INTO recruitment_onboarding_cases
                    (public_id, application_id, offer_id, template_id, status, owner_username, target_start_date)
                 VALUES
                    (:public_id, :application_id, :offer_id, :template_id, 'in_progress', :owner, :target_start_date)"
            )->execute([
                'public_id' => $publicId,
                'application_id' => $application['application_id'],
                'offer_id' => $offer['offer_id'],
                'template_id' => $template['template_id'],
                'owner' => $owner,
                'target_start_date' => $targetDate,
            ]);
            $caseId = (int)$this->db->lastInsertId();
            $items = $this->db->prepare('SELECT * FROM recruitment_onboarding_template_items WHERE template_id = :template_id ORDER BY display_order');
            $items->execute(['template_id' => $template['template_id']]);
            $templateItems = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($templateItems === []) {
                throw new DomainException('The onboarding template has no approved items.');
            }
            $insert = $this->db->prepare(
                'INSERT INTO recruitment_onboarding_items
                    (public_id, onboarding_case_id, source_template_item_id, item_code, title,
                     purpose_text, visibility_text, item_type, classification, required_flag, due_at)
                 VALUES
                    (:public_id, :onboarding_case_id, :source_template_item_id, :item_code, :title,
                     :purpose_text, :visibility_text, :item_type, :classification, :required_flag, :due_at)'
            );
            foreach ($templateItems as $item) {
                $dueAt = $targetDate === null
                    ? null
                    : gmdate('Y-m-d H:i:s', strtotime($targetDate . ' 23:59:59 UTC') - ((int)$item['due_offset_days'] * 86400));
                $insert->execute([
                    'public_id' => self::uuidV4(),
                    'onboarding_case_id' => $caseId,
                    'source_template_item_id' => $item['template_item_id'],
                    'item_code' => $item['item_code'],
                    'title' => $item['title'],
                    'purpose_text' => $item['purpose_text'],
                    'visibility_text' => $item['visibility_text'],
                    'item_type' => $item['item_type'],
                    'classification' => $item['classification'],
                    'required_flag' => $item['required_flag'],
                    'due_at' => $dueAt,
                ]);
                $itemId = (int)$this->db->lastInsertId();
                $this->recordItemEvent($itemId, null, 'pending', 'staff', $actor, 'Case started from approved template.');
            }
            $this->db->prepare("UPDATE recruitment_applications SET current_status = 'onboarding' WHERE application_id = :application_id AND current_status = 'offer_accepted'")
                ->execute(['application_id' => $application['application_id']]);
            $this->recordApplicationEvent((int)$application['application_id'], 'offer_accepted', 'onboarding', 'Onboarding in progress.', 'staff', $actor, 'onboarding_started');
            $this->outbox->enqueue((int)$application['candidate_id'], 'onboarding_action', [
                'onboarding_public_id' => $publicId,
                'target_start_date' => $targetDate,
                'item_count' => count($templateItems),
            ], $publicId);
            $this->audit('onboarding_case_started', $actor, 'onboarding_case', $publicId, 'accepted_offer', [
                'template_public_id' => $templatePublicId,
                'template_version' => (int)$template['version_number'],
                'item_count' => count($templateItems),
            ]);
            return $publicId;
        });
    }

    public function updateOnboardingItem(int $candidateId, string $itemPublicId, string $toStatus, string $reason): void
    {
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Update note', 3, 500);
        if (!in_array($toStatus, ['in_progress', 'submitted'], true)) {
            throw new InvalidArgumentException('Candidates may start or submit an onboarding item.');
        }
        $this->transaction(function () use ($candidateId, $itemPublicId, $toStatus, $reason): void {
            $item = $this->lockByPublicId('recruitment_onboarding_items', 'onboarding_item_id', $itemPublicId);
            if ($item === null) {
                throw new DomainException('Onboarding item not found.');
            }
            $case = $this->lockById('recruitment_onboarding_cases', 'onboarding_case_id', (int)$item['onboarding_case_id']);
            $application = $case === null ? null : $this->lockById('recruitment_applications', 'application_id', (int)$case['application_id']);
            if ($case === null || $application === null || (int)$application['candidate_id'] !== $candidateId || !in_array((string)$case['status'], ['in_progress', 'blocked'], true)) {
                throw new DomainException('Onboarding item not found.');
            }
            $fromStatus = (string)$item['status'];
            if (!RecruitmentPolicy::canTransition('onboarding_item', $fromStatus, $toStatus)) {
                throw new DomainException('That onboarding item update is not available.');
            }
            $this->db->prepare('UPDATE recruitment_onboarding_items SET status = :status, completed_at = CASE WHEN :submitted_status = \'submitted\' THEN UTC_TIMESTAMP() ELSE completed_at END WHERE onboarding_item_id = :item_id AND status = :from_status')
                ->execute([
                    'status' => $toStatus,
                    'submitted_status' => $toStatus,
                    'item_id' => $item['onboarding_item_id'],
                    'from_status' => $fromStatus,
                ]);
            $this->recordItemEvent((int)$item['onboarding_item_id'], $fromStatus, $toStatus, 'candidate', (string)$candidateId, $reason);
            $this->audit('onboarding_item_candidate_updated', 'candidate:' . $candidateId, 'onboarding_item', $itemPublicId, 'self_service', [
                'from' => $fromStatus,
                'to' => $toStatus,
            ]);
        });
    }

    public function reviewOnboardingItem(string $itemPublicId, string $decision, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'onboarding.manage');
        $decision = RecruitmentContentPolicy::oneOf($decision, 'item decision', ['approved', 'changes_requested', 'waived', 'not_applicable']);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Review reason', 3, 500);
        $this->transaction(function () use ($itemPublicId, $decision, $reason, $actor): void {
            $item = $this->lockByPublicId('recruitment_onboarding_items', 'onboarding_item_id', $itemPublicId);
            if ($item === null || !RecruitmentPolicy::canTransition('onboarding_item', (string)$item['status'], $decision)) {
                throw new DomainException('That onboarding item decision is not available.');
            }
            if (in_array((string)$item['classification'], ['restricted', 'highly_restricted'], true)) {
                $this->access->assertCapability($actor, 'document.review');
            }
            $this->db->prepare(
                'UPDATE recruitment_onboarding_items
                    SET status = :status, reviewed_by_username = :actor, reviewed_at = UTC_TIMESTAMP(),
                        review_reason = :reason, completed_at = CASE WHEN :completed_status IN (\'approved\', \'waived\', \'not_applicable\') THEN UTC_TIMESTAMP() ELSE completed_at END
                  WHERE onboarding_item_id = :item_id AND status = :from_status'
            )->execute([
                'status' => $decision,
                'actor' => $actor,
                'reason' => $reason,
                'completed_status' => $decision,
                'item_id' => $item['onboarding_item_id'],
                'from_status' => $item['status'],
            ]);
            $this->recordItemEvent((int)$item['onboarding_item_id'], (string)$item['status'], $decision, 'staff', $actor, $reason);
            $this->audit('onboarding_item_reviewed', $actor, 'onboarding_item', $itemPublicId, 'requirement_review', [
                'decision' => $decision,
                'classification' => $item['classification'],
            ]);
        });
    }

    public function prepareReadiness(string $casePublicId, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'employee_conversion.prepare');
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Readiness reason', 3, 500);
        $this->transaction(function () use ($casePublicId, $reason, $actor): void {
            $case = $this->lockByPublicId('recruitment_onboarding_cases', 'onboarding_case_id', $casePublicId);
            if ($case === null || (string)$case['status'] !== 'in_progress') {
                throw new DomainException('The onboarding case is not ready for readiness review.');
            }
            $count = $this->db->prepare(
                "SELECT COUNT(*) FROM recruitment_onboarding_items
                  WHERE onboarding_case_id = :case_id AND required_flag = 1
                    AND status NOT IN ('approved', 'waived', 'not_applicable')"
            );
            $count->execute(['case_id' => $case['onboarding_case_id']]);
            if ((int)$count->fetchColumn() !== 0) {
                throw new DomainException('Complete or resolve every required onboarding item first.');
            }
            $update = $this->db->prepare(
                "UPDATE recruitment_onboarding_cases
                    SET status = 'ready_for_review', readiness_prepared_by_username = :actor,
                        readiness_prepared_at = UTC_TIMESTAMP()
                  WHERE onboarding_case_id = :case_id AND status = 'in_progress'"
            );
            $update->execute(['actor' => $actor, 'case_id' => $case['onboarding_case_id']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The onboarding case changed before readiness could be prepared.');
            }
            $this->audit('onboarding_readiness_prepared', $actor, 'onboarding_case', $casePublicId, $reason, []);
        });
    }

    public function approveReadiness(string $casePublicId, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'employee_conversion.approve');
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Approval reason', 3, 500);
        $this->transaction(function () use ($casePublicId, $reason, $actor): void {
            $case = $this->lockByPublicId('recruitment_onboarding_cases', 'onboarding_case_id', $casePublicId);
            if ($case === null || (string)$case['status'] !== 'ready_for_review') {
                throw new DomainException('The onboarding case is not awaiting readiness approval.');
            }
            if (!RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$case['readiness_prepared_by_username'], $actor)) {
                throw new DomainException('The readiness preparer cannot approve the same case.');
            }
            $this->db->prepare(
                "UPDATE recruitment_onboarding_cases
                    SET status = 'ready_for_conversion', readiness_approved_by_username = :actor,
                        readiness_approved_at = UTC_TIMESTAMP()
                  WHERE onboarding_case_id = :case_id AND status = 'ready_for_review'"
            )->execute(['actor' => $actor, 'case_id' => $case['onboarding_case_id']]);
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$case['application_id']);
            if ($application === null || !RecruitmentPolicy::canTransition('application', (string)$application['current_status'], 'ready_for_conversion')) {
                throw new DomainException('The application is not aligned with onboarding readiness.');
            }
            $this->db->prepare("UPDATE recruitment_applications SET current_status = 'ready_for_conversion' WHERE application_id = :application_id AND current_status = 'onboarding'")
                ->execute(['application_id' => $application['application_id']]);
            $this->recordApplicationEvent((int)$application['application_id'], 'onboarding', 'ready_for_conversion', 'Onboarding complete.', 'staff', $actor, 'readiness_approved');
            $this->audit('onboarding_readiness_approved', $actor, 'onboarding_case', $casePublicId, $reason, []);
        });
    }

    private function transitionOffer(string $offerPublicId, string $toStatus, string $reason, string $actor, bool $approval): void
    {
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Offer reason', 3, 500);
        $this->transaction(function () use ($offerPublicId, $toStatus, $reason, $actor, $approval): void {
            $offer = $this->lockByPublicId('recruitment_offers', 'offer_id', $offerPublicId);
            if ($offer === null || !RecruitmentPolicy::canTransition('offer', (string)$offer['status'], $toStatus)) {
                throw new DomainException('That offer transition is not allowed.');
            }
            if ($approval && !RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$offer['prepared_by_username'], $actor)) {
                throw new DomainException('The offer preparer cannot approve the same version.');
            }
            $this->db->prepare('UPDATE recruitment_offers SET status = :status WHERE offer_id = :offer_id AND status = :expected_status')
                ->execute(['status' => $toStatus, 'offer_id' => $offer['offer_id'], 'expected_status' => $offer['status']]);
            $this->audit('offer_status_changed', $actor, 'offer', $offerPublicId, $reason, [
                'from' => $offer['status'],
                'to' => $toStatus,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function validatedOfferTerms(array $terms): array
    {
        return [
            'position_title' => RecruitmentContentPolicy::requiredText($terms['position_title'] ?? '', 'Position title', 3, 190),
            'employment_type' => RecruitmentContentPolicy::oneOf($terms['employment_type'] ?? '', 'employment type', ['regular', 'probationary', 'fixed_term', 'project_based', 'part_time', 'internship']),
            'start_date' => RecruitmentContentPolicy::date($terms['start_date'] ?? '', 'start date', true),
            'work_location' => RecruitmentContentPolicy::requiredText($terms['work_location'] ?? '', 'Work location', 2, 190),
            'work_arrangement' => RecruitmentContentPolicy::oneOf($terms['work_arrangement'] ?? '', 'work arrangement', ['onsite', 'hybrid', 'remote']),
            'compensation_currency' => RecruitmentContentPolicy::oneOf($terms['compensation_currency'] ?? 'PHP', 'compensation currency', ['PHP']),
            'compensation_amount' => RecruitmentContentPolicy::requiredText($terms['compensation_amount'] ?? '', 'Compensation amount', 1, 80),
            'pay_frequency' => RecruitmentContentPolicy::oneOf($terms['pay_frequency'] ?? '', 'pay frequency', ['monthly', 'semi_monthly', 'biweekly', 'weekly']),
            'conditions' => RecruitmentContentPolicy::optionalText($terms['conditions'] ?? '', 'Offer conditions', 4000),
        ];
    }

    private function insertOfferVersion(int $offerId, int $version, array $terms, string $actor): int
    {
        $json = RecruitmentContentPolicy::canonicalJson($terms);
        $contentHash = hash('sha256', $json);
        $this->db->prepare(
            'INSERT INTO recruitment_offer_versions
                (offer_id, version_number, terms_ciphertext, content_sha256, prepared_by_username)
             VALUES (:offer_id, :version_number, :terms_ciphertext, :content_sha256, :actor)'
        )->execute([
            'offer_id' => $offerId,
            'version_number' => $version,
            'terms_ciphertext' => RecruitmentSecurity::encrypt($json, $this->dataKey),
            'content_sha256' => $contentHash,
            'actor' => $actor,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function currentOfferVersion(int $offerId, int $version): array
    {
        $statement = $this->db->prepare('SELECT * FROM recruitment_offer_versions WHERE offer_id = :offer_id AND version_number = :version LIMIT 1 FOR UPDATE');
        $statement->execute(['offer_id' => $offerId, 'version' => $version]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DomainException('The current immutable offer version is missing.');
        }
        return $row;
    }

    private function recordApplicationEvent(int $applicationId, ?string $from, string $to, string $message, string $actorType, string $actorReference, string $reasonCode): void
    {
        $this->db->prepare(
            'INSERT INTO recruitment_application_events
                (application_id, from_status, to_status, candidate_message, reason_code, changed_by_type, changed_by_reference)
             VALUES (:application_id, :from_status, :to_status, :candidate_message, :reason_code, :actor_type, :actor_reference)'
        )->execute([
            'application_id' => $applicationId,
            'from_status' => $from,
            'to_status' => $to,
            'candidate_message' => $message,
            'reason_code' => $reasonCode,
            'actor_type' => $actorType,
            'actor_reference' => $actorReference,
        ]);
    }

    private function recordItemEvent(int $itemId, ?string $from, string $to, string $actorType, string $actorReference, string $reason): void
    {
        $this->db->prepare(
            'INSERT INTO recruitment_onboarding_item_events
                (onboarding_item_id, from_status, to_status, actor_type, actor_reference, reason)
             VALUES (:item_id, :from_status, :to_status, :actor_type, :actor_reference, :reason)'
        )->execute([
            'item_id' => $itemId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_reference' => $actorReference,
            'reason' => $reason,
        ]);
    }

    private function audit(string $eventType, string $actor, string $subjectType, string $subjectReference, string $reasonCode, array $metadata): void
    {
        $actorType = str_starts_with($actor, 'candidate:') ? 'candidate' : 'staff';
        $actorReference = str_starts_with($actor, 'candidate:') ? substr($actor, 10) : $actor;
        $this->db->prepare(
            'INSERT INTO recruitment_audit_events
                (event_type, actor_type, actor_reference, subject_type, subject_reference, reason_code, metadata_json)
             VALUES (:event_type, :actor_type, :actor_reference, :subject_type, :subject_reference, :reason_code, :metadata_json)'
        )->execute([
            'event_type' => $eventType,
            'actor_type' => $actorType,
            'actor_reference' => $actorReference,
            'subject_type' => $subjectType,
            'subject_reference' => $subjectReference,
            'reason_code' => substr($reasonCode, 0, 80),
            'metadata_json' => RecruitmentContentPolicy::canonicalJson($metadata),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function lockByPublicId(string $table, string $primaryKey, string $publicId): ?array
    {
        $allowed = [
            'recruitment_applications' => 'application_id',
            'recruitment_offers' => 'offer_id',
            'recruitment_onboarding_templates' => 'template_id',
            'recruitment_onboarding_cases' => 'onboarding_case_id',
            'recruitment_onboarding_items' => 'onboarding_item_id',
        ];
        if (($allowed[$table] ?? null) !== $primaryKey) {
            throw new LogicException('Unsupported recruitment record lock.');
        }
        $statement = $this->db->prepare("SELECT * FROM {$table} WHERE public_id = :public_id LIMIT 1 FOR UPDATE");
        $statement->execute(['public_id' => $publicId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function lockById(string $table, string $primaryKey, int $id): ?array
    {
        $allowed = [
            'recruitment_applications' => 'application_id',
            'recruitment_onboarding_cases' => 'onboarding_case_id',
        ];
        if (($allowed[$table] ?? null) !== $primaryKey || $id <= 0) {
            throw new LogicException('Unsupported recruitment record lock.');
        }
        $statement = $this->db->prepare("SELECT * FROM {$table} WHERE {$primaryKey} = :id LIMIT 1 FOR UPDATE");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function transaction(callable $operation): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
