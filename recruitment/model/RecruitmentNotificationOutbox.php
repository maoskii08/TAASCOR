<?php

declare(strict_types=1);

final class RecruitmentNotificationOutbox
{
    private const MESSAGE_TYPES = ['verify_email', 'password_reset', 'interview_invitation', 'offer_available', 'onboarding_action', 'candidate_message'];

    public function __construct(private PDO $db)
    {
    }

    public function enqueue(int $candidateId, string $messageType, array $payload, string $idempotencySeed): int
    {
        if ($candidateId <= 0 || !in_array($messageType, self::MESSAGE_TYPES, true)) {
            throw new InvalidArgumentException('A supported candidate notification is required.');
        }
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $idempotencyKey = hash('sha256', $candidateId . '|' . $messageType . '|' . $idempotencySeed);
        $statement = $this->db->prepare(
            "INSERT INTO recruitment_notification_outbox
                (candidate_id, message_type, idempotency_key, payload_json, delivery_status, available_at)
             VALUES (:candidate_id, :message_type, :idempotency_key, :payload_json, 'pending', UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE notification_id = LAST_INSERT_ID(notification_id)"
        );
        $statement->execute([
            'candidate_id' => $candidateId,
            'message_type' => $messageType,
            'idempotency_key' => $idempotencyKey,
            'payload_json' => $payloadJson,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
