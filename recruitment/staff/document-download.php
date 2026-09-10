<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/includes/auth_guard.php'; auth_require_role([1,2]);
require_once dirname(__DIR__).'/includes/staff_runtime.php';
header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff');
if(!recruitment_staff_mutations_ready()){http_response_code(503);exit('Document access remains source-locked.');}
try{$services=recruitment_staff_services();$services['access']->assertCapability(auth_user(),'document.review');$file=recruitment_staff_document_service($services['db'])->releaseToStaff((string)($_GET['id']??''),auth_user());header('Content-Type: '.$file['media_type']);header('Content-Length: '.(string)$file['byte_size']);header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['name']));readfile($file['path']);}catch(Throwable $e){error_log('Staff document release failed: '.$e->getMessage());http_response_code(404);echo 'Document unavailable.';}
