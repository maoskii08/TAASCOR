<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/candidate_portal_runtime.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (!recruitment_candidate_portal_ready()) { http_response_code(503); exit('Document access is not available.'); }
try {
    $context=recruitment_candidate_portal_context();
    $file=recruitment_candidate_document_service($context['db'])->releaseToCandidate($context['candidate_id'],(string)($_GET['id']??''));
    header('Content-Type: '.$file['media_type']);
    header('Content-Length: '.(string)$file['byte_size']);
    header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['name']));
    readfile($file['path']);
} catch (Throwable $error) { error_log('Candidate document release failed: '.$error->getMessage()); http_response_code(404); echo 'Document unavailable.'; }
