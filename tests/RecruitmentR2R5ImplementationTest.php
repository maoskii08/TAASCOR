<?php
declare(strict_types=1);
require_once __DIR__.'/../recruitment/model/RecruitmentPolicy.php';
require_once __DIR__.'/../recruitment/model/RecruitmentDocumentPolicy.php';
$failures=[]; $check=static function(bool $condition,string $message)use(&$failures):void{ if(!$condition){$failures[]=$message;echo "FAIL: {$message}\n";}else echo "PASS: {$message}\n"; };
$root=realpath(__DIR__.'/..');
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
foreach(['20260910_03_recruitment_operations.sql','20260910_04_offers_onboarding.sql','20260910_05_employee_conversion.sql','20260910_06_document_governance.sql'] as $migration){$sql=$read('recruitment/migrations/'.$migration);$check(str_contains($sql,'recruitment_schema_migrations'),$migration.' is rerunnable and recorded');}
foreach(['interviews','offers','onboarding','conversions','reports'] as $route){$check(is_file($root.'/recruitment/staff/'.$route.'.php'),'staff '.$route.' route exists');}
$check(is_file($root.'/recruitment/staff/candidate.php'),'staff candidate detail route exists');
$check(is_file($root.'/recruitment/staff/admin/access.php'),'staff access administration route exists');
foreach(['dashboard','applications','application','apply','interviews','offers','onboarding','documents','messages','settings'] as $route){$check(is_file($root.'/recruitment/candidate/'.$route.'.php'),'candidate '.$route.' route exists');}
$actions=$read('recruitment/staff/actions.php');
foreach(['requisition.create','job.transition','application.transition','interview.schedule','offer.decide','onboarding.readiness.approve','document.review','conversion.execute','conversion.reconcile','access.grant'] as $action){$check(str_contains($actions,"'{$action}'"),'staff action '.$action.' is routed');}
$check(str_contains($actions,"'candidate.message'"),'staff candidate communication is routed');
$check(RecruitmentPolicy::canTransition('offer','draft','pending_approval'),'offer can enter independent approval');
$check(!RecruitmentPolicy::canTransition('offer','draft','delivered'),'offer cannot bypass approval');
$check(RecruitmentPolicy::canTransition('onboarding_case','ready_for_review','ready_for_conversion'),'onboarding readiness has an approval state');
$check(!RecruitmentPolicy::canTransition('onboarding_case','in_progress','converted'),'onboarding cannot bypass readiness');
$check(RecruitmentPolicy::canTransition('conversion','approved','executed'),'conversion can execute only after approval');
$document=$read('recruitment/model/RecruitmentDocumentService.php');
foreach(['is_uploaded_file','finfo','move_uploaded_file','hash_file','canRelease','recruitment_document_events'] as $control){$check(str_contains($document,$control),'document flow includes '.$control);}
$check(str_contains($read('recruitment/workers/document-scan-worker.php'),'escapeshellarg'),'scanner invokes its approved command with escaped paths');
$notification=$read('recruitment/model/RecruitmentNotificationDeliveryService.php');
$check(str_contains($notification,'recruitment_notification_attempts'),'notification delivery records provider attempts');
$check(str_contains($notification,"'dead_letter'"),'notification delivery has a dead-letter state');
$maintenance=$read('recruitment/workers/workflow-maintenance-worker.php');
$check(str_contains($maintenance,"publication_status='expired'") && str_contains($maintenance,"status='expired'"),'maintenance enforces job and offer expiry');
$check(str_contains($maintenance,"onboarding_action"),'maintenance queues due onboarding reminders');
$conversion=$read('recruitment/model/RecruitmentConversionService.php');
foreach(['idempotency','duplicate','makerCheckerSatisfied','reconcile'] as $control){$check(str_contains($conversion,$control),'conversion includes '.$control.' control');}
$feature=$read('recruitment/includes/feature.php');
$check(str_contains($feature,"const TAASCOR_RECRUITMENT_RELEASE_STAGE = 'foundation'"),'all implemented capabilities remain source-locked');
$check(!RecruitmentDocumentPolicy::canRelease('clean','pending'),'clean but unapproved files remain unavailable');
$check(RecruitmentDocumentPolicy::canRelease('clean','approved'),'clean approved files are releasable');
if($failures!==[]){fwrite(STDERR,"RESULT: ".count($failures)." R2-R5 implementation check(s) failed.\n");exit(1);} echo "RESULT: Recruitment R2-R5 implementation checks passed.\n";
