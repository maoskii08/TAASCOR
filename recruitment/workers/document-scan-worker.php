<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/feature.php'; require_once dirname(__DIR__).'/includes/database.php';
require_once dirname(__DIR__).'/model/RecruitmentSecurity.php'; require_once dirname(__DIR__).'/model/RecruitmentContentPolicy.php'; require_once dirname(__DIR__).'/model/RecruitmentDocumentPolicy.php'; require_once dirname(__DIR__).'/model/RecruitmentDocumentService.php';
if (!recruitment_mutations_enabled()) { fwrite(STDERR,"Document scanning is source-locked until qualified.\n"); exit(3); }
$scanner=trim((string)getenv('TAASCOR_RECRUITMENT_SCANNER_COMMAND')); $root=trim((string)getenv('TAASCOR_RECRUITMENT_DOCUMENT_ROOT'));
if ($scanner==='' || !is_executable($scanner)) { fwrite(STDERR,"An executable scanner command is required.\n"); exit(3); }
$db=recruitment_database(); $service=new RecruitmentDocumentService($db,recruitment_data_key(),$root);
$rows=$db->query("SELECT public_id,storage_key FROM recruitment_documents WHERE scan_status IN ('quarantined','scan_failed') AND deleted_at IS NULL ORDER BY uploaded_at LIMIT 20")->fetchAll(PDO::FETCH_ASSOC)?:[];
foreach($rows as $row){ $path=rtrim($root,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,(string)$row['storage_key']); $cmd=escapeshellarg($scanner).' --no-summary '.escapeshellarg($path); exec($cmd,$output,$code); $result=$code===0?'clean':($code===1?'infected':'scan_failed'); $service->recordScan((string)$row['public_id'],$result,basename($scanner),'exit_'.$code); }
fwrite(STDOUT,'Scanned '.count($rows)." document(s).\n");
