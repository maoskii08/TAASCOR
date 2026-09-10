<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/feature.php';require_once dirname(__DIR__).'/includes/database.php';require_once dirname(__DIR__).'/model/RecruitmentNotificationOutbox.php';
if(!recruitment_mutations_enabled()){fwrite(STDERR,"Recruitment maintenance is source-locked until qualified.\n");exit(3);}
$db=recruitment_database();$db->beginTransaction();
try{
    $jobs=$db->query("SELECT job_id,public_id,slug,title,content_version,publication_status,closes_at FROM recruitment_jobs WHERE publication_status IN ('published','paused') AND closes_at IS NOT NULL AND closes_at<=UTC_TIMESTAMP() FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC)?:[];
    $jobUpdate=$db->prepare("UPDATE recruitment_jobs SET publication_status='expired' WHERE job_id=:id AND publication_status IN ('published','paused')");
    $jobEvent=$db->prepare("INSERT INTO recruitment_job_publication_events (job_id,event_type,channel,content_version,content_sha256,actor_username,reason,snapshot_json,occurred_at) VALUES (:id,'expired','taascor_website',:version,:hash,'system:maintenance','publication_window_elapsed',:snapshot,UTC_TIMESTAMP())");
    foreach($jobs as $job){$snapshot=json_encode(['public_id'=>$job['public_id'],'slug'=>$job['slug'],'title'=>$job['title'],'from_status'=>$job['publication_status'],'to_status'=>'expired','closes_at'=>$job['closes_at']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$jobUpdate->execute(['id'=>$job['job_id']]);$jobEvent->execute(['id'=>$job['job_id'],'version'=>$job['content_version'],'hash'=>hash('sha256',$snapshot),'snapshot'=>$snapshot]);}
    $offers=$db->exec("UPDATE recruitment_offers SET status='expired',responded_at=UTC_TIMESTAMP() WHERE status='delivered' AND expires_at IS NOT NULL AND expires_at<=UTC_TIMESTAMP()");
    $db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
$outbox=new RecruitmentNotificationOutbox($db);
$reminders=$db->query("SELECT a.candidate_id,oi.public_id,oi.due_at FROM recruitment_onboarding_items oi JOIN recruitment_onboarding_cases oc ON oc.onboarding_case_id=oi.onboarding_case_id JOIN recruitment_applications a ON a.application_id=oc.application_id WHERE oc.status IN ('in_progress','blocked') AND oi.status IN ('pending','in_progress','changes_requested') AND oi.due_at BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR)")->fetchAll(PDO::FETCH_ASSOC)?:[];
foreach($reminders as $item){$outbox->enqueue((int)$item['candidate_id'],'onboarding_action',['item_public_id'=>$item['public_id'],'due_at'=>$item['due_at']],$item['public_id'].'|'.gmdate('Y-m-d'));}
fwrite(STDOUT,'Expired '.count($jobs).' job(s), '.$offers.' offer(s), and queued '.count($reminders)." reminder(s).\n");
