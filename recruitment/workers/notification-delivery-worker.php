<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/feature.php';
require_once dirname(__DIR__).'/includes/database.php';
require_once dirname(__DIR__).'/model/RecruitmentSecurity.php';
if (!recruitment_candidate_identity_enabled() || !recruitment_mutations_enabled()) { fwrite(STDERR,"Recruitment notification delivery is source-locked until qualified.\n"); exit(3); }
$provider=trim((string)getenv('TAASCOR_RECRUITMENT_MAIL_PROVIDER'));
$from=filter_var((string)getenv('TAASCOR_RECRUITMENT_MAIL_FROM'),FILTER_VALIDATE_EMAIL);
$base=rtrim((string)getenv('TAASCOR_RECRUITMENT_PUBLIC_BASE_URL'),'/');
if ($provider!=='native_mail' || !$from || !preg_match('#^https://#',$base)) { fwrite(STDERR,"Approved native_mail configuration, sender, and HTTPS base URL are required.\n"); exit(3); }
$db=recruitment_database(); $key=recruitment_data_key();
$db->beginTransaction();
$rows=$db->query("SELECT o.*,c.email_ciphertext,p.enabled_flag AS preference_enabled FROM recruitment_notification_outbox o JOIN recruitment_candidates c ON c.candidate_id=o.candidate_id LEFT JOIN recruitment_candidate_notification_preferences p ON p.candidate_id=o.candidate_id AND p.channel='email' AND p.message_type=o.message_type WHERE o.delivery_status IN ('pending','retry') AND o.available_at<=UTC_TIMESTAMP() AND o.attempt_count<5 ORDER BY o.notification_id LIMIT 20 FOR UPDATE SKIP LOCKED")->fetchAll(PDO::FETCH_ASSOC)?:[];
$ids=array_column($rows,'notification_id');
if ($ids) { $db->exec("UPDATE recruitment_notification_outbox SET delivery_status='processing',claimed_at=UTC_TIMESTAMP() WHERE notification_id IN (".implode(',',array_map('intval',$ids)).")"); }
$db->commit();
foreach ($rows as $row) {
    $attempt=(int)$row['attempt_count']+1; $outcome='failed'; $error='send_failed'; $providerRef=null;
    if ($row['preference_enabled'] !== null && (int)$row['preference_enabled'] === 0 && in_array((string)$row['message_type'],['interview_invitation','onboarding_action'],true)) {
        $db->prepare("UPDATE recruitment_notification_outbox SET delivery_status='suppressed',attempt_count=:attempt,last_error_code='candidate_preference' WHERE notification_id=:id")->execute(['attempt'=>$attempt,'id'=>$row['notification_id']]);
        $db->prepare("INSERT INTO recruitment_notification_attempts (notification_id,provider_name,attempt_number,outcome,error_code) VALUES (:id,:provider,:attempt,'suppressed','candidate_preference')")->execute(['id'=>$row['notification_id'],'provider'=>$provider,'attempt'=>$attempt]);
        continue;
    }
    try {
        $payload=json_decode((string)$row['payload_json'],true,512,JSON_THROW_ON_ERROR);
        $email=RecruitmentSecurity::decrypt((string)$row['email_ciphertext'],$key);
        [$subject,$body]=render_recruitment_message((string)$row['message_type'],$payload,$key,$base);
        $headers=['From: TAASCOR Recruitment <'.$from.'>','Content-Type: text/plain; charset=UTF-8','X-Recruitment-Notification: '.(int)$row['notification_id']];
        if (!mail($email,$subject,$body,implode("\r\n",$headers))) throw new RuntimeException('mail_rejected');
        $outcome='sent'; $error=null; $providerRef='native-mail:'.hash('sha256',$row['notification_id'].'|'.microtime(true));
    } catch (Throwable $e) { $error=substr(preg_replace('/[^A-Za-z0-9_.-]/','_',strtolower($e->getMessage()))?:'delivery_error',0,80); }
    $db->beginTransaction();
    $db->prepare('INSERT INTO recruitment_notification_attempts (notification_id,provider_name,provider_reference,attempt_number,outcome,error_code) VALUES (:id,:provider,:ref,:attempt,:outcome,:error)')->execute(['id'=>$row['notification_id'],'provider'=>$provider,'ref'=>$providerRef,'attempt'=>$attempt,'outcome'=>$outcome,'error'=>$error]);
    if ($outcome==='sent') $db->prepare("UPDATE recruitment_notification_outbox SET delivery_status='sent',attempt_count=:attempt,sent_at=UTC_TIMESTAMP(),last_error_code=NULL WHERE notification_id=:id")->execute(['attempt'=>$attempt,'id'=>$row['notification_id']]);
    else { $status=$attempt>=5?'dead_letter':'retry'; $delay=min(3600,60*(2**($attempt-1))); $db->prepare("UPDATE recruitment_notification_outbox SET delivery_status=:status,attempt_count=:attempt,available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL :delay SECOND),last_error_code=:error WHERE notification_id=:id")->execute(['status'=>$status,'attempt'=>$attempt,'delay'=>$delay,'error'=>$error,'id'=>$row['notification_id']]); }
    $db->commit();
}
fwrite(STDOUT,'Processed '.count($rows)." notification(s).\n");

function render_recruitment_message(string $type,array $payload,string $key,string $base): array {
    return match($type) {
        'verify_email'=>['Verify your TAASCOR candidate account',"Verify your email:\n{$base}/recruitment/candidate/verify.php?token=".rawurlencode(RecruitmentSecurity::decrypt((string)$payload['token_ciphertext'],$key))."\n\nIf you did not create this account, ignore this message."],
        'password_reset'=>['Reset your TAASCOR candidate password',"Reset your password:\n{$base}/recruitment/candidate/reset.php?token=".rawurlencode(RecruitmentSecurity::decrypt((string)$payload['token_ciphertext'],$key))."\n\nThis link expires after 30 minutes."],
        'interview_invitation'=>['TAASCOR interview invitation',"An interview is scheduled for {$payload['starts_at_utc']} UTC ({$payload['timezone_name']}). Sign in to confirm or request rescheduling:\n{$base}/recruitment/candidate/interviews.php"],
        'offer_available'=>['Your TAASCOR offer is available',"A new offer is ready in your secure candidate account. Sign in to review the exact version and expiry:\n{$base}/recruitment/candidate/offers.php"],
        'onboarding_action'=>['TAASCOR onboarding action required',"A secure onboarding task is ready. Sign in to view its purpose, visibility, and due date:\n{$base}/recruitment/candidate/onboarding.php"],
        'candidate_message'=>['New message in your TAASCOR candidate account',"A new message is available from the recruitment team. Sign in to read it securely:\n{$base}/recruitment/candidate/messages.php"],
        default=>throw new InvalidArgumentException('Unsupported notification type.'),
    };
}
