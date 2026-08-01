<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require(__DIR__ . '/../../config/db_connect.php');

$limit = max(1, min(100, (int)($argv[1] ?? 25)));
$appUrl = rtrim(trim((string)getenv('TAASCOR_APP_URL')), '/');
$mailFrom = trim((string)getenv('TAASCOR_MAIL_FROM'))
    ?: 'noreply@taascor.visiotechsolutions.com';
if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "TAASCOR_APP_URL must be configured before notification delivery.\n");
    exit(1);
}

$pdoConn->exec(
    "UPDATE notification_delivery_outbox
     SET delivery_status = 'retry',
         available_at = NOW(),
         claimed_at = NULL,
         last_error = 'Recovered stale delivery claim',
         last_error_at = NOW()
     WHERE delivery_status = 'processing'
       AND claimed_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
);

function claimEmailDelivery(PDO $db): ?array
{
    $db->beginTransaction();
    try {
        $stmt = $db->query(
            "SELECT o.*, u.employee_email
             FROM notification_delivery_outbox o
             INNER JOIN taascor_user_access u
                ON u.employee_user_name = o.recipient
               AND u.is_active = b'1'
             WHERE o.channel = 'email'
               AND o.delivery_status IN ('pending', 'retry')
               AND o.available_at <= NOW()
               AND o.attempt_count < o.max_attempts
             ORDER BY o.available_at, o.id
             LIMIT 1
             FOR UPDATE"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db->commit();
            return null;
        }
        $update = $db->prepare(
            "UPDATE notification_delivery_outbox
             SET delivery_status = 'processing',
                 attempt_count = attempt_count + 1,
                 claimed_at = NOW(),
                 last_error = NULL
             WHERE id = :id
               AND delivery_status IN ('pending', 'retry')"
        );
        $update->execute([':id' => (int)$row['id']]);
        if ($update->rowCount() !== 1) {
            $db->rollBack();
            return null;
        }
        $db->commit();
        $row['attempt_count'] = (int)$row['attempt_count'] + 1;
        return $row;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function markEmailSent(PDO $db, int $id): void
{
    $stmt = $db->prepare(
        "UPDATE notification_delivery_outbox
         SET delivery_status = 'sent', sent_at = NOW(), claimed_at = NULL,
             last_error = NULL, last_error_at = NULL
         WHERE id = :id AND delivery_status = 'processing'"
    );
    $stmt->execute([':id' => $id]);
}

function markEmailFailed(PDO $db, array $delivery, string $error): void
{
    $attempt = (int)$delivery['attempt_count'];
    $maxAttempts = (int)$delivery['max_attempts'];
    $terminal = $attempt >= $maxAttempts;
    $backoffMinutes = min(60, 2 ** max(0, $attempt - 1));
    $stmt = $db->prepare(
        "UPDATE notification_delivery_outbox
         SET delivery_status = :status,
             available_at = DATE_ADD(NOW(), INTERVAL {$backoffMinutes} MINUTE),
             claimed_at = NULL,
             last_error = :last_error,
             last_error_at = NOW()
         WHERE id = :id AND delivery_status = 'processing'"
    );
    $stmt->execute([
        ':status' => $terminal ? 'dead_letter' : 'retry',
        ':last_error' => substr($error, 0, 1000),
        ':id' => (int)$delivery['id'],
    ]);
}

$sent = 0;
$retried = 0;
for ($processed = 0; $processed < $limit; $processed++) {
    $delivery = claimEmailDelivery($pdoConn);
    if ($delivery === null) {
        break;
    }
    try {
        $email = trim((string)$delivery['employee_email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Recipient has no valid email address.');
        }
        $payload = json_decode((string)$delivery['delivery_payload'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Notification payload is invalid.');
        }
        $title = trim((string)($payload['title'] ?? 'TAASCOR HRIS payroll alert'));
        $message = trim((string)($payload['message'] ?? 'A payroll workflow needs attention.'));
        $target = ltrim((string)($payload['target_url'] ?? ''), './');
        $link = $target === '' ? $appUrl : $appUrl . '/' . $target;
        $body = "{$message}\n\nOpen TAASCOR HRIS:\n{$link}\n";
        $headers = "From: {$mailFrom}\r\nReply-To: {$mailFrom}\r\nX-Mailer: PHP/" . PHP_VERSION;
        if (!mail($email, $title, $body, $headers)) {
            throw new RuntimeException('Mail transport rejected the notification.');
        }
        markEmailSent($pdoConn, (int)$delivery['id']);
        $sent++;
    } catch (Throwable $error) {
        markEmailFailed($pdoConn, $delivery, $error->getMessage());
        $retried++;
    }
}

echo json_encode(['sent' => $sent, 'retried_or_dead_lettered' => $retried]) . PHP_EOL;
