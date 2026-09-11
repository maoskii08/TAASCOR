<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/feature.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/model/RecruitmentSecurity.php';
require_once dirname(__DIR__) . '/model/RecruitmentNotificationDeliveryService.php';

if (!recruitment_candidate_identity_enabled() || !recruitment_mutations_enabled()) {
    fwrite(STDERR, "Recruitment notification delivery is source-locked until qualified.\n");
    exit(3);
}

$provider = trim((string)getenv('TAASCOR_RECRUITMENT_MAIL_PROVIDER'));
$from = filter_var((string)getenv('TAASCOR_RECRUITMENT_MAIL_FROM'), FILTER_VALIDATE_EMAIL);
$base = rtrim((string)getenv('TAASCOR_RECRUITMENT_PUBLIC_BASE_URL'), '/');
if ($provider !== 'native_mail' || !$from || !preg_match('#^https://#', $base)) {
    fwrite(STDERR, "Approved native_mail configuration, sender, and HTTPS base URL are required.\n");
    exit(3);
}

$sender = static function (string $email, string $subject, string $body, int $notificationId) use ($from): string {
    $headers = [
        'From: TAASCOR Recruitment <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Recruitment-Notification: ' . $notificationId,
    ];
    if (!mail($email, $subject, $body, implode("\r\n", $headers))) {
        throw new RuntimeException('mail_rejected');
    }
    return 'native-mail:' . hash('sha256', $notificationId . '|' . microtime(true));
};

$service = new RecruitmentNotificationDeliveryService(
    recruitment_database(),
    recruitment_data_key(),
    $base,
    $provider,
    $sender
);
$result = $service->processBatch();
fwrite(
    STDOUT,
    'Processed ' . $result['processed'] . ' notification(s); recovered ' . $result['recovered'] . " stale claim(s).\n"
);
