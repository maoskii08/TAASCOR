<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/public_candidate_origin.php';
recruitment_redirect_candidate_to_public_origin();
require_once __DIR__ . '/../includes/feature.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <title>Candidate privacy | TAASCOR Careers</title>
  <link rel="icon" href="../../assets/img/favicon/favicon.ico">
  <link rel="stylesheet" href="../assets/candidate.css?v=20260910-1">
</head>
<body>
  <main class="candidate-legal-shell">
    <a class="candidate-logo candidate-legal-logo" href="index.php" aria-label="Return to candidate sign in">
      <img src="../../assets/img/svg/logo-wo-visio.svg" alt="TAASCOR Management and General Services Corporation">
    </a>
    <article class="candidate-legal">
      <p class="candidate-kicker">TAASCOR Careers</p>
      <h1>Candidate privacy notice</h1>
      <div class="candidate-notice candidate-notice-warning" role="status">
        <strong>The candidate privacy notice is not published yet.</strong>
        <span>Candidate registration cannot open until the approved notice, purposes, retention periods, and request channels are added here.</span>
      </div>
      <p>No candidate information is collected by this preview.</p>
      <a class="candidate-secondary" href="index.php">Return to candidate sign in</a>
    </article>
  </main>
<?php require_once __DIR__.'/../includes/guide_component.php'; recruitment_guide_render('candidate.privacy'); ?>
</body>
</html>
