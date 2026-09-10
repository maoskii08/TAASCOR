<?php
declare(strict_types=1);
require_once __DIR__.'/guide_manifest.php';
require_once __DIR__.'/feature.php';

function recruitment_guide_render(string $pageId): void
{
    $manifest=recruitment_guide_manifest();
    foreach($manifest as $id=>&$item){$item['availability']=recruitment_guide_is_live($id)?'Live':'Disconnected';}unset($item);
    if(!isset($manifest[$pageId]))$pageId='overview';
    $guide=$manifest[$pageId];$safe=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    ?>
    <link rel="stylesheet" href="<?= $safe(recruitment_guide_asset_base()) ?>assets/guide.css?v=20260910a">
    <button class="recruitment-guide-launcher" type="button" data-guide-open aria-label="Open guide for <?= $safe($guide['label']) ?>"><span aria-hidden="true">?</span><span>Guide</span></button>
    <div class="recruitment-guide-backdrop" data-guide-backdrop hidden></div>
    <section class="recruitment-guide-dialog" data-guide-dialog role="dialog" aria-modal="true" aria-labelledby="recruitment-guide-title" hidden>
      <div class="recruitment-guide-toolbar"><div><span>TAASCOR GUIDE CENTER</span><strong id="recruitment-guide-title">Recruitment and onboarding guide</strong></div><button type="button" data-guide-close aria-label="Close guide">Close</button></div>
      <div class="recruitment-guide-layout">
        <aside class="recruitment-guide-nav"><label for="recruitment-guide-search">Search every guide</label><input id="recruitment-guide-search" type="search" data-guide-search placeholder="Try offer approval or documents"><p data-guide-count></p><nav data-guide-nav aria-label="Guide articles"></nav><div class="recruitment-guide-zero" data-guide-zero hidden><strong>No guide matched</strong><span>Try a page, action, status, or outcome.</span></div><a href="<?= $safe(recruitment_guide_asset_base()) ?>guide.php">Open full Guide Center</a></aside>
        <article class="recruitment-guide-article" data-guide-article tabindex="-1"></article>
      </div>
    </section>
    <script>window.taascorRecruitmentGuides=<?= json_encode(['current'=>$pageId,'guides'=>array_values($manifest)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= $safe(recruitment_guide_asset_base()) ?>assets/guide.js?v=20260910a" defer></script>
    <?php
}

function recruitment_guide_is_live(string $pageId): bool
{
    if($pageId==='overview')return recruitment_staff_workspace_enabled();
    if(str_starts_with($pageId,'candidate.'))return recruitment_candidate_identity_enabled();
    if(str_starts_with($pageId,'staff.'))return recruitment_staff_workspace_enabled();
    return false;
}

function recruitment_guide_asset_base(): string
{
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/recruitment/index.php'));
    $position=strpos($script,'/recruitment/');
    return $position===false?'/recruitment/':substr($script,0,$position).'/recruitment/';
}
