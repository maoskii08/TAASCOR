<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_guard.php';
auth_require_role([1, 2]);
require_once __DIR__ . '/includes/feature.php';
require dirname(__DIR__) . '/config/page-permissions.php';

$visiblePageIds = $pages[(string)auth_level()] ?? [];

$summary = [
    'pending_requisitions' => null,
    'approved_requisitions' => null,
    'published_jobs' => null,
    'active_applications' => null,
    'ready_for_conversion' => null,
];
$workspaceMessage = 'Foundation mode is active. Recruitment data and mutations remain disabled.';

if (recruitment_staff_workspace_enabled()) {
    try {
        require_once dirname(__DIR__) . '/config/db_connect.php';
        require_once __DIR__ . '/model/RecruitmentRepository.php';
        $summary = (new RecruitmentRepository($pdoConn))->staffSummary();
        $workspaceMessage = 'The read-only recruitment workspace is connected.';
    } catch (Throwable $error) {
        error_log('Recruitment workspace summary failed: ' . $error->getMessage());
        $workspaceMessage = 'The recruitment workspace could not load. No data was changed.';
    }
}

function recruitment_metric_value(?int $value): string
{
    return $value === null ? 'Not active' : number_format($value);
}
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recruitment | TAASCOR HRIS</title>
  <meta name="description" content="Governed recruitment and onboarding workspace for authorized TAASCOR staff." />
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />
  <link rel="stylesheet" href="../assets/js/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/js/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="assets/recruitment.css?v=20260909a" />
</head>
<body class="recruitment-page">
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require dirname(__DIR__) . '/includes/nav-bar.php'; ?>
      <div class="home">
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)" aria-label="Open navigation">
              <span class="recruitment-menu-label" aria-hidden="true">Menu</span>
            </a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <nav aria-label="Breadcrumb">
                <ol class="breadcrumb mb-0">
                  <li class="breadcrumb-item">HR</li>
                  <li class="breadcrumb-item active" aria-current="page">Recruitment</li>
                </ol>
              </nav>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item">
                <span class="recruitment-stage-badge"><?php echo htmlspecialchars(ucfirst(TAASCOR_RECRUITMENT_RELEASE_STAGE)); ?> mode</span>
              </li>
              <li class="nav-item ms-2">
                <a class="recruitment-logout" href="../login/logout.php">Log out</a>
              </li>
            </ul>
          </div>
        </nav>

        <main class="content-wrapper">
          <div class="container-xxl recruitment-shell flex-grow-1 container-p-y">
            <section class="recruitment-hero p-4 p-lg-5 mb-4" aria-labelledby="recruitment-title">
              <div class="d-flex flex-column flex-xl-row align-items-xl-end justify-content-between gap-4">
                <div>
                  <p class="recruitment-kicker">Recruitment and onboarding</p>
                  <h1 class="recruitment-title" id="recruitment-title">One hiring record, from request to first day.</h1>
                  <p class="recruitment-lede">A governed workspace for requisitions, candidates, decisions, onboarding readiness, and controlled employee conversion inside TAASCOR HRIS.</p>
                </div>
                <div class="recruitment-callout rounded-3 p-3" role="status">
                  <strong class="d-block mb-1">Safe foundation</strong>
                  <span><?php echo htmlspecialchars($workspaceMessage); ?></span>
                </div>
              </div>
            </section>

            <dl class="recruitment-metric-grid mb-4" aria-label="Recruitment workspace summary">
              <div class="recruitment-metric"><dt>Pending requisitions</dt><dd><?php echo htmlspecialchars(recruitment_metric_value($summary['pending_requisitions'])); ?></dd></div>
              <div class="recruitment-metric"><dt>Approved requisitions</dt><dd><?php echo htmlspecialchars(recruitment_metric_value($summary['approved_requisitions'])); ?></dd></div>
              <div class="recruitment-metric"><dt>Published jobs</dt><dd><?php echo htmlspecialchars(recruitment_metric_value($summary['published_jobs'])); ?></dd></div>
              <div class="recruitment-metric"><dt>Active applications</dt><dd><?php echo htmlspecialchars(recruitment_metric_value($summary['active_applications'])); ?></dd></div>
              <div class="recruitment-metric"><dt>Ready for conversion</dt><dd><?php echo htmlspecialchars(recruitment_metric_value($summary['ready_for_conversion'])); ?></dd></div>
            </dl>

            <nav class="recruitment-quicklinks mb-4" aria-label="Recruitment operations preview">
              <a href="staff/requisitions.php"><span>01</span>Requisitions</a>
              <a href="staff/jobs.php"><span>02</span>Job publication</a>
              <a href="staff/pipeline.php"><span>03</span>Candidate pipeline</a>
              <a href="staff/exceptions.php"><span>04</span>Exception desk</a>
            </nav>

            <section class="recruitment-panel p-4 mb-4" aria-labelledby="workflow-title">
              <div class="mb-4">
                <h2 id="workflow-title" class="mb-2">Connected hiring lifecycle</h2>
                <p class="mb-0">Each stage carries its owner, decision evidence, candidate message, and audit history forward.</p>
              </div>
              <ol class="recruitment-flow">
                <li><span class="recruitment-flow-index">01</span><strong>Requisition</strong><span>Request, business need, headcount, owner, and approval.</span></li>
                <li><span class="recruitment-flow-index">02</span><strong>Publish</strong><span>Approved role details, dates, worksite, and official channels.</span></li>
                <li><span class="recruitment-flow-index">03</span><strong>Assess</strong><span>Applications, objective screening, interviews, and exceptions.</span></li>
                <li><span class="recruitment-flow-index">04</span><strong>Decide</strong><span>Human decision, reason boundary, offer approval, and response.</span></li>
                <li><span class="recruitment-flow-index">05</span><strong>Onboard</strong><span>Applicable tasks, secure requirements, due dates, and readiness.</span></li>
                <li><span class="recruitment-flow-index">06</span><strong>Convert</strong><span>Independent review, duplicate prevention, and employee activation.</span></li>
              </ol>
            </section>

            <div class="row g-4">
              <div class="col-lg-7">
                <section class="recruitment-panel p-4" aria-labelledby="current-slice-title">
                  <h2 id="current-slice-title" class="mb-3">Current implementation slice</h2>
                  <ul class="mb-0 ps-3">
                    <li class="mb-2">Additive recruitment schema separated from payroll and DTR tables.</li>
                    <li class="mb-2">Candidate-specific session and credential boundary.</li>
                    <li class="mb-2">Server-enforced requisition, job, and application state transitions.</li>
                    <li class="mb-2">Read-only public jobs feed for the TAASCOR website.</li>
                    <li class="mb-2">Source-locked staff queues for requisitions, jobs, applications, and exceptions.</li>
                    <li>Fail-closed source and environment controls for every recruitment capability.</li>
                  </ul>
                </section>
              </div>
              <div class="col-lg-5">
                <aside class="recruitment-panel p-4" aria-labelledby="release-boundary-title">
                  <h2 id="release-boundary-title" class="mb-3">Release boundary</h2>
                  <p>No real candidate data, job publication, document collection, hiring decision, or employee conversion is enabled by this foundation.</p>
                  <a class="recruitment-link" href="../role-guide/">Review current HRIS role access</a>
                </aside>
              </div>
            </div>
          </div>
          <div class="content-backdrop fade"></div>
        </main>
        <div class="layout-overlay layout-menu-toggle"></div>
      </div>
    </div>
  </div>
  <script>
    window.taascorRecruitmentVisiblePages = <?php echo json_encode(array_values($visiblePageIds), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="assets/recruitment.js?v=20260909a"></script>
<?php require_once __DIR__.'/includes/guide_component.php'; recruitment_guide_render('overview'); ?>
</body>
</html>
