<?php

declare(strict_types=1);

final class RecruitmentNotificationDeliveryService
{
    private const MAX_ATTEMPTS = 5;
    private const STALE_CLAIM_MINUTES = 15;

    private Closure $sender;

    public function __construct(
        private PDO $db,
        private string $dataKey,
        private string $publicBaseUrl,
        private string $providerName,
        callable $sender
    ) {
        if (strlen($this->dataKey) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
        if (!preg_match('#^https://#', $this->publicBaseUrl)) {
            throw new InvalidArgumentException('The recruitment public base URL must use HTTPS.');
        }
        if (trim($this->providerName) === '') {
            throw new InvalidArgumentException('A recruitment notification provider is required.');
        }
        $this->publicBaseUrl = rtrim($this->publicBaseUrl, '/');
        $this->sender = Closure::fromCallable($sender);
    }

    /** @return array{processed:int,recovered:int} */
    public function processBatch(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $recovered = $this->recoverStaleClaims();
        $rows = $this->claimDue($limit);
        foreach ($rows as $row) {
            $this->deliver($row);
        }
        return ['processed' => count($rows), 'recovered' => $recovered];
    }

    public function recoverStaleClaims(): int
    {
        $statement = $this->db->prepare(
            "UPDATE recruitment_notification_outbox
             SET delivery_status = CASE WHEN attempt_count >= :max_attempts THEN 'dead_letter' ELSE 'retry' END,
                 available_at = UTC_TIMESTAMP(), claimed_at = NULL, last_error_code = 'stale_claim_recovered'
             WHERE delivery_status = 'processing'
               AND claimed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . self::STALE_CLAIM_MINUTES . " MINUTE)"
        );
        $statement->execute(['max_attempts' => self::MAX_ATTEMPTS]);
        return $statement->rowCount();
    }

    /** @return list<array<string,mixed>> */
    private function claimDue(int $limit): array
    {
        $this->db->beginTransaction();
        try {
            $rows = $this->db->query(
                "SELECT o.*, c.email_ciphertext, p.enabled_flag AS preference_enabled
                 FROM recruitment_notification_outbox o
                 JOIN recruitment_candidates c ON c.candidate_id = o.candidate_id
                 LEFT JOIN recruitment_candidate_notification_preferences p
                   ON p.candidate_id = o.candidate_id
                  AND p.channel = 'email'
                  AND p.message_type = o.message_type
                 WHERE o.delivery_status IN ('pending', 'retry')
                   AND o.available_at <= UTC_TIMESTAMP()
                   AND o.attempt_count < " . self::MAX_ATTEMPTS . "
                 ORDER BY o.notification_id
                 LIMIT {$limit}
                 FOR UPDATE SKIP LOCKED"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $ids = array_map('intval', array_column($rows, 'notification_id'));
            if ($ids !== []) {
                $this->db->exec(
                    "UPDATE recruitment_notification_outbox
                     SET delivery_status = 'processing', claimed_at = UTC_TIMESTAMP()
                     WHERE notification_id IN (" . implode(',', $ids) . ")"
                );
            }
            $this->db->commit();
            return $rows;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $row */
    private function deliver(array $row): void
    {
        $attempt = (int)$row['attempt_count'] + 1;
        if (
            $row['preference_enabled'] !== null
            && (int)$row['preference_enabled'] === 0
            && in_array((string)$row['message_type'], ['interview_invitation', 'onboarding_action'], true)
        ) {
            $this->recordAttempt($row, $attempt, 'suppressed', 'candidate_preference', null);
            return;
        }

        $outcome = 'failed';
        $errorCode = 'send_failed';
        $providerReference = null;
        try {
            $payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            $email = RecruitmentSecurity::decrypt((string)$row['email_ciphertext'], $this->dataKey);
            [$subject, $body] = self::renderMessage(
                (string)$row['message_type'],
                $payload,
                $this->dataKey,
                $this->publicBaseUrl
            );
            $providerReference = ($this->sender)($email, $subject, $body, (int)$row['notification_id']);
            $outcome = 'sent';
            $errorCode = null;
        } catch (Throwable $error) {
            $errorCode = self::errorCode($error);
        }
        $this->recordAttempt($row, $attempt, $outcome, $errorCode, $providerReference);
    }

    /** @param array<string,mixed> $row */
    private function recordAttempt(
        array $row,
        int $attempt,
        string $outcome,
        ?string $errorCode,
        ?string $providerReference
    ): void {
        $notificationId = (int)$row['notification_id'];
        $this->db->beginTransaction();
        try {
            $attemptStatement = $this->db->prepare(
                'INSERT INTO recruitment_notification_attempts
                    (notification_id, provider_name, provider_reference, attempt_number, outcome, error_code)
                 VALUES (:id, :provider, :reference, :attempt, :outcome, :error)'
            );
            $attemptStatement->execute([
                'id' => $notificationId,
                'provider' => $this->providerName,
                'reference' => $providerReference,
                'attempt' => $attempt,
                'outcome' => $outcome,
                'error' => $errorCode,
            ]);

            if ($outcome === 'sent') {
                $status = 'sent';
                $availableAt = (string)$row['available_at'];
            } elseif ($outcome === 'suppressed') {
                $status = 'suppressed';
                $availableAt = (string)$row['available_at'];
            } else {
                $status = $attempt >= self::MAX_ATTEMPTS ? 'dead_letter' : 'retry';
                $delay = min(3600, 60 * (2 ** ($attempt - 1)));
                $availableAt = gmdate('Y-m-d H:i:s', time() + $delay);
            }

            $update = $this->db->prepare(
                "UPDATE recruitment_notification_outbox
                 SET delivery_status = :status,
                     attempt_count = :attempt,
                     available_at = :available_at,
                     claimed_at = NULL,
                     sent_at = CASE WHEN :sent_flag = 1 THEN UTC_TIMESTAMP() ELSE sent_at END,
                     last_error_code = :error
                 WHERE notification_id = :id AND delivery_status = 'processing'"
            );
            $update->execute([
                'status' => $status,
                'attempt' => $attempt,
                'available_at' => $availableAt,
                'sent_flag' => $outcome === 'sent' ? 1 : 0,
                'error' => $errorCode,
                'id' => $notificationId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The notification claim changed before delivery completed.');
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $payload @return array{0:string,1:string} */
    public static function renderMessage(string $type, array $payload, string $key, string $base): array
    {
        return match ($type) {
            'verify_email' => ['Verify your TAASCOR candidate account', "Verify your email:\n{$base}/recruitment/candidate/verify.php?token=" . rawurlencode(RecruitmentSecurity::decrypt((string)$payload['token_ciphertext'], $key)) . "\n\nIf you did not create this account, ignore this message."],
            'password_reset' => ['Reset your TAASCOR candidate password', "Reset your password:\n{$base}/recruitment/candidate/reset.php?token=" . rawurlencode(RecruitmentSecurity::decrypt((string)$payload['token_ciphertext'], $key)) . "\n\nThis link expires after 30 minutes."],
            'interview_invitation' => ['TAASCOR interview invitation', "An interview is scheduled for {$payload['starts_at_utc']} UTC ({$payload['timezone_name']}). Sign in to confirm or request rescheduling:\n{$base}/recruitment/candidate/interviews.php"],
            'offer_available' => ['Your TAASCOR offer is available', "A new offer is ready in your secure candidate account. Sign in to review the exact version and expiry:\n{$base}/recruitment/candidate/offers.php"],
            'onboarding_action' => ['TAASCOR onboarding action required', "A secure onboarding task is ready. Sign in to view its purpose, visibility, and due date:\n{$base}/recruitment/candidate/onboarding.php"],
            'candidate_message' => ['New message in your TAASCOR candidate account', "A new message is available from the recruitment team. Sign in to read it securely:\n{$base}/recruitment/candidate/messages.php"],
            default => throw new InvalidArgumentException('Unsupported notification type.'),
        };
    }

    private static function errorCode(Throwable $error): string
    {
        return substr(
            preg_replace('/[^A-Za-z0-9_.-]/', '_', strtolower($error->getMessage())) ?: 'delivery_error',
            0,
            80
        );
    }
}
