<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';
auth_require_role([1, 2]);
require_once dirname(__DIR__) . '/includes/feature.php';
require dirname(__DIR__, 2) . '/config/page-permissions.php';

header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$viewKey = isset($recruitmentStaffView) ? (string)$recruitmentStaffView : 'requisitions';
$views = [
    'requisitions' => [
        'title' => 'Requisition queue',
        'eyebrow' => 'Plan and approve',
        'intro' => 'Turn an accountable hiring request into an approved opening before any role reaches a candidate.',
        'empty_title' => 'No requisitions are available',
        'empty_body' => 'Foundation mode keeps the queue disconnected. When qualified, approved requests will appear here with owner, headcount, target date, and decision evidence.',
        'columns' => ['Request', 'Owner', 'Headcount', 'Target', 'Status'],
    ],
    'jobs' => [
        'title' => 'Job publication',
        'eyebrow' => 'Publish with control',
        'intro' => 'Prepare candidate-safe role content and publish only from an approved requisition with clear dates and provenance.',
        'empty_title' => 'No jobs are available',
        'empty_body' => 'The public job projection remains offline. Qualified openings will show version, channel, publication window, and current state here.',
        'columns' => ['Role', 'Requisition', 'Location', 'Window', 'Status'],
    ],
    'pipeline' => [
        'title' => 'Candidate pipeline',
        'eyebrow' => 'Assess with clarity',
        'intro' => 'Keep each application connected to its role, owner, current stage, candidate-safe message, and complete decision history.',
        'empty_title' => 'No applications are available',
        'empty_body' => 'Candidate collection is disabled. Once qualified, only assigned and authorized applications will be visible in this workspace.',
        'columns' => ['Application ref', 'Role', 'Stage', 'Stage age', 'Updated'],
    ],
    'exceptions' => [
        'title' => 'Exception desk',
        'eyebrow' => 'Resolve what needs attention',
        'intro' => 'Bring overdue work, failed delivery, missing evidence, and blocked handoffs into one owned resolution queue.',
        'empty_title' => 'No exceptions are available',
        'empty_body' => 'Operational exception monitoring activates only with the qualified workflow. No placeholder incidents or fabricated counts are shown.',
        'columns' => ['Exception', 'Record', 'Owner', 'Age', 'Priority'],
    ],
    'interviews' => ['title'=>'Interview desk','eyebrow'=>'Schedule and assess','intro'=>'Coordinate candidate-safe scheduling, panel participation, and submitted structured scorecards.','empty_title'=>'No interviews are available','empty_body'=>'Scheduled interviews appear here with timezone and response state.','columns'=>['Interview','Role','Starts','Timezone','Status']],
    'offers' => ['title'=>'Offer center','eyebrow'=>'Prepare and approve','intro'=>'Create immutable offer versions, separate preparation from approval, and retain delivery evidence.','empty_title'=>'No offers are available','empty_body'=>'Offer records appear only after an authorized conditional decision.','columns'=>['Offer','Role','Version','Expiry','Status']],
    'onboarding' => ['title'=>'Onboarding cases','eyebrow'=>'Guide every requirement','intro'=>'Run approved templates, dependencies, due dates, review decisions, and readiness checks.','empty_title'=>'No onboarding cases are available','empty_body'=>'Accepted offers create governed onboarding cases here.','columns'=>['Case','Role','Owner','Target','Status']],
    'conversions' => ['title'=>'Employee conversion','eyebrow'=>'Activate with control','intro'=>'Review minimum employee payloads, duplicate evidence, maker-checker decisions, and reconciliation.','empty_title'=>'No conversions are available','empty_body'=>'Only independently approved readiness cases enter this queue.','columns'=>['Request','Case','Duplicate check','Reviewer','Status']],
    'reports' => ['title'=>'Operations snapshot','eyebrow'=>'Measure the workflow','intro'=>'Use system-derived workload totals without inventing performance targets or candidate outcomes.','empty_title'=>'No operational data is available','empty_body'=>'Metrics appear after the qualified workflow begins processing synthetic or approved records.','columns'=>['Open requisitions','Approved jobs','Active applications','Ready to convert','Snapshot']],
];

if (!array_key_exists($viewKey, $views)) {
    http_response_code(404);
    $viewKey = 'requisitions';
}

$view = $views[$viewKey];
$visiblePageIds = $pages[(string)auth_level()] ?? [];
$rows = [];
$workspaceMessage = 'Foundation mode is active. Staff data and actions remain disconnected.';

if (recruitment_staff_workspace_enabled()) {
    try {
        require_once dirname(__DIR__, 2) . '/config/db_connect.php';
        require_once dirname(__DIR__) . '/model/RecruitmentRepository.php';
        $repository = new RecruitmentRepository($pdoConn);
        $rows = match ($viewKey) {
            'requisitions' => $repository->requisitionQueue(),
            'jobs' => $repository->jobQueue(),
            'pipeline' => $repository->applicationPipeline(),
            'exceptions' => $repository->exceptionQueue(),
            'interviews' => $repository->interviewQueue(),
            'offers' => $repository->offerQueue(),
            'onboarding' => $repository->onboardingQueue(),
            'conversions' => $repository->conversionQueue(),
            'reports' => $repository->reportRows(),
        };
        $workspaceMessage = 'The authorized read-only queue is connected.';
    } catch (Throwable $error) {
        error_log('Recruitment staff queue failed: ' . $error->getMessage());
        $workspaceMessage = 'This queue could not load. No data was changed.';
    }
}

$safe = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$stageLabel = ucfirst(TAASCOR_RECRUITMENT_RELEASE_STAGE) . ' mode';
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default" data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $safe($view['title']); ?> | TAASCOR HRIS</title>
  <meta name="description" content="Authorized TAASCOR recruitment operations workspace." />
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/favicon.ico" />
  <link rel="stylesheet" href="../../assets/js/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/js/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/recruitment.css?v=20260910b" />
</head>
<body class="recruitment-page recruitment-operations-page">
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require dirname(__DIR__, 2) . '/includes/nav-bar.php'; ?>
      <div class="home">
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)" aria-label="Open navigation"><span class="recruitment-menu-label" aria-hidden="true">Menu</span></a>
          </div>
          <nav class="recruitment-breadcrumb" aria-label="Breadcrumb">
            <a href="../">Recruitment</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo $safe($view['title']); ?></span>
          </nav>
          <div class="ms-auto d-flex align-items-center gap-2">
            <span class="recruitment-stage-badge"><?php echo $safe($stageLabel); ?></span>
            <a class="recruitment-logout" href="../../login/logout.php">Log out</a>
          </div>
        </nav>

        <main class="content-wrapper">
          <div class="container-xxl recruitment-shell flex-grow-1 container-p-y">
            <header class="recruitment-operations-header">
              <div>
                <p class="recruitment-kicker"><?php echo $safe($view['eyebrow']); ?></p>
                <h1 class="recruitment-operations-title"><?php echo $safe($view['title']); ?></h1>
                <p class="recruitment-operations-intro"><?php echo $safe($view['intro']); ?></p>
              </div>
              <div class="recruitment-callout rounded-3 p-3" role="status">
                <strong class="d-block mb-1">Release boundary</strong>
                <span><?php echo $safe($workspaceMessage); ?></span>
              </div>
            </header>

            <nav class="recruitment-workspace-tabs" aria-label="Recruitment operations">
              <?php foreach ($views as $key => $item): ?>
                <a href="<?php echo $safe($key === 'requisitions' ? 'requisitions.php' : $key . '.php'); ?>"<?php echo $key === $viewKey ? ' aria-current="page"' : ''; ?>><?php echo $safe($item['title']); ?></a>
              <?php endforeach; ?>
            </nav>

            <section class="recruitment-queue" aria-labelledby="queue-heading">
              <div class="recruitment-queue-heading">
                <div>
                  <p class="recruitment-queue-label">Authorized work queue</p>
                  <h2 id="queue-heading"><?php echo $safe($view['title']); ?></h2>
                </div>
                <span class="recruitment-record-count"><?php echo $rows === [] ? 'No active records' : $safe(count($rows)) . ' active'; ?></span>
              </div>

              <?php if ($rows === []): ?>
                <div class="recruitment-empty-state">
                  <span class="recruitment-empty-mark" aria-hidden="true">T</span>
                  <div>
                    <h3><?php echo $safe($view['empty_title']); ?></h3>
                    <p><?php echo $safe($view['empty_body']); ?></p>
                  </div>
                </div>
              <?php else: ?>
                <div class="recruitment-queue-list" role="table" aria-label="<?php echo $safe($view['title']); ?> records">
                  <div class="recruitment-queue-row recruitment-queue-row-head" role="row">
                    <?php foreach ($view['columns'] as $column): ?><span role="columnheader"><?php echo $safe($column); ?></span><?php endforeach; ?>
                  </div>
                  <?php foreach ($rows as $row): ?>
                    <div class="recruitment-queue-row" role="row">
                      <?php foreach (array_slice(array_values($row), 0, 5) as $index => $value): ?><span role="cell" data-label="<?php echo $safe($view['columns'][$index] ?? 'Value'); ?>"><?php echo $safe($value); ?></span><?php endforeach; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <aside class="recruitment-next-gate" aria-label="Qualification requirements">
              <strong>Before this queue can open</strong>
              <span>Approve staff grants and assignment scope, complete MFA and PII-read audit qualification, and close privacy and provider ownership.</span>
            </aside>
          </div>
          <div class="content-backdrop fade"></div>
        </main>
        <div class="layout-overlay layout-menu-toggle"></div>
      </div>
    </div>
  </div>
  <script>window.taascorRecruitmentVisiblePages = <?php echo json_encode(array_values($visiblePageIds), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
  <script src="../assets/recruitment.js?v=20260910b"></script>
<?php require_once dirname(__DIR__).'/includes/guide_component.php'; recruitment_guide_render('staff.'.$viewKey); ?>
</body>
</html>
