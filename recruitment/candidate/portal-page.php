<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/candidate_portal_runtime.php';
require_once __DIR__ . '/../includes/candidate_runtime.php';
require_once __DIR__ . '/../includes/candidate_csrf.php';
require_once __DIR__ . '/../model/RecruitmentRepository.php';

$viewKey = isset($candidatePortalView) ? (string)$candidatePortalView : 'dashboard';
$views = [
    'dashboard' => ['title' => 'Your candidate home', 'intro' => 'Applications, interviews, offers, and onboarding actions in one place.'],
    'applications' => ['title' => 'Your applications', 'intro' => 'Review every application and its candidate-safe history.'],
    'application' => ['title' => 'Application details', 'intro' => 'Review the accepted job terms, submitted information, and dated progress.'],
    'apply' => ['title' => 'Apply for this role', 'intro' => 'Share only the information needed to begin an application.'],
    'interviews' => ['title' => 'Interviews', 'intro' => 'See confirmed details, respond, or request another schedule.'],
    'offers' => ['title' => 'Offers', 'intro' => 'Review the exact approved version before recording a decision.'],
    'onboarding' => ['title' => 'Onboarding', 'intro' => 'Complete only the requirements that apply to your accepted role.'],
    'documents' => ['title' => 'Documents', 'intro' => 'Track approved document requests and their review state.'],
    'messages' => ['title' => 'Messages', 'intro' => 'Contact the recruitment team without leaving your candidate account.'],
    'settings' => ['title' => 'Privacy and settings', 'intro' => 'Manage optional notifications and submit a privacy request.'],
];
if (!isset($views[$viewKey])) {
    http_response_code(404);
    exit;
}

$ready = recruitment_candidate_portal_ready();
$context = null;
$candidateId = 0;
$portal = null;
$data = [];
$flash = recruitment_candidate_take_flash();
if ($ready) {
    try {
        $context = recruitment_candidate_portal_context();
        $candidateId = $context['candidate_id'];
        $portal = $context['portal'];
        $data = match ($viewKey) {
            'dashboard' => ['summary' => $portal->dashboardSummary($candidateId), 'applications' => array_slice($portal->applications($candidateId), 0, 3)],
            'applications' => ['applications' => $portal->applications($candidateId)],
            'application' => ['application' => $portal->application($candidateId, trim((string)($_GET['id'] ?? '')))],
            'apply' => ['job' => (new RecruitmentRepository($context['db']))->publishedJob(trim((string)($_GET['job'] ?? '')))],
            'interviews' => ['interviews' => $portal->interviews($candidateId)],
            'offers' => ['offers' => $portal->offers($candidateId)],
            'onboarding' => ['cases' => $portal->onboardingCases($candidateId)],
            'documents' => ['documents' => $portal->documents($candidateId)],
            'messages' => ['messages' => $portal->messages($candidateId), 'applications' => $portal->applications($candidateId)],
            'settings' => ['notifications' => $portal->notificationHistory($candidateId)],
        };
    } catch (DomainException) {
        header('Location: index.php');
        exit;
    } catch (Throwable $error) {
        error_log('Recruitment candidate portal render failed: ' . $error->getMessage());
        $data = ['load_error' => true];
    }
}

$safe = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = recruitment_candidate_csrf_token();
$page = $views[$viewKey];
$nav = [
    'dashboard' => 'Home',
    'applications' => 'Applications',
    'interviews' => 'Interviews',
    'offers' => 'Offers',
    'onboarding' => 'Onboarding',
    'documents' => 'Documents',
    'messages' => 'Messages',
    'settings' => 'Settings',
];

header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <title><?= $safe($page['title']) ?> | TAASCOR Careers</title>
  <link rel="icon" href="../../assets/img/favicon/favicon.ico">
  <link rel="stylesheet" href="../assets/candidate.css?v=20260910-2">
</head>
<body class="candidate-portal-page">
  <a class="candidate-skip" href="#candidate-content">Skip to content</a>
  <header class="candidate-portal-header">
    <a class="candidate-portal-logo" href="dashboard.php" aria-label="TAASCOR candidate home">
      <img src="../../assets/img/svg/logo-wo-visio.svg" alt="TAASCOR Management and General Services Corporation">
    </a>
    <button class="candidate-portal-menu" type="button" data-candidate-menu aria-controls="candidate-navigation" aria-expanded="false">Menu</button>
    <nav class="candidate-portal-nav" id="candidate-navigation" aria-label="Candidate account">
      <?php foreach ($nav as $key => $label): ?>
        <a href="<?= $safe($key) ?>.php"<?= $key === $viewKey || ($viewKey === 'application' && $key === 'applications') || ($viewKey === 'apply' && $key === 'applications') ? ' aria-current="page"' : '' ?>><?= $safe($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <form class="candidate-portal-logout" action="actions/account.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>">
      <input type="hidden" name="action" value="logout">
      <button type="submit"<?= $ready ? '' : ' disabled' ?>>Sign out</button>
    </form>
  </header>

  <main class="candidate-portal-main" id="candidate-content">
    <?php if (!$ready): ?>
      <div class="candidate-notice candidate-notice-warning" role="status">
        <strong>Candidate portal preview</strong>
        <span>No account or application data is connected while the foundation release is active.</span>
      </div>
    <?php elseif (isset($data['load_error'])): ?>
      <div class="candidate-notice candidate-notice-error" role="alert">This page could not load. No information was changed.</div>
    <?php elseif ($flash): ?>
      <div class="candidate-notice candidate-notice-<?= $safe($flash['type'] ?? 'info') ?>" role="status"><?= $safe($flash['message'] ?? '') ?></div>
    <?php endif; ?>

    <header class="candidate-portal-heading">
      <p class="candidate-kicker">TAASCOR candidate account</p>
      <h1><?= $safe($page['title']) ?></h1>
      <p><?= $safe($page['intro']) ?></p>
    </header>

    <?php if ($viewKey === 'dashboard'): ?>
      <?php $summary = $data['summary'] ?? ['applications' => null, 'active_applications' => null, 'application_actions' => null, 'upcoming_interviews' => null, 'onboarding_actions' => null]; ?>
      <dl class="candidate-portal-metrics" aria-label="Candidate account summary">
        <div><dt>Applications</dt><dd><?= $summary['applications'] === null ? 'Not active' : $safe($summary['applications']) ?></dd></div>
        <div><dt>Active</dt><dd><?= $summary['active_applications'] === null ? 'Not active' : $safe($summary['active_applications']) ?></dd></div>
        <div><dt>Application actions</dt><dd><?= $summary['application_actions'] === null ? 'Not active' : $safe($summary['application_actions']) ?></dd></div>
        <div><dt>Interviews</dt><dd><?= $summary['upcoming_interviews'] === null ? 'Not active' : $safe($summary['upcoming_interviews']) ?></dd></div>
        <div><dt>Onboarding actions</dt><dd><?= $summary['onboarding_actions'] === null ? 'Not active' : $safe($summary['onboarding_actions']) ?></dd></div>
      </dl>
      <section class="candidate-portal-panel" aria-labelledby="recent-applications-title">
        <div class="candidate-panel-heading"><div><p>Recent activity</p><h2 id="recent-applications-title">Your applications</h2></div><a href="applications.php">View all</a></div>
        <?php $applications = $data['applications'] ?? []; ?>
        <?php if ($applications === []): ?>
          <div class="candidate-empty"><strong>No applications to show</strong><span>Approved roles and saved applications will appear here.</span></div>
        <?php else: ?>
          <div class="candidate-record-list">
            <?php foreach ($applications as $application): ?>
              <a href="application.php?id=<?= rawurlencode((string)$application['public_id']) ?>">
                <span><strong><?= $safe($application['job_title']) ?></strong><small><?= $safe($application['location_label']) ?> · <?= $safe($application['employment_type']) ?></small></span>
                <em><?= $safe($application['candidate_status']) ?></em>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif ($viewKey === 'applications'): ?>
      <section class="candidate-portal-panel" aria-labelledby="applications-title">
        <div class="candidate-panel-heading"><div><p>Application history</p><h2 id="applications-title">All applications</h2></div></div>
        <?php $applications = $data['applications'] ?? []; ?>
        <?php if ($applications === []): ?>
          <div class="candidate-empty"><strong>No applications to show</strong><span>Your saved and submitted applications will appear here.</span></div>
        <?php else: ?>
          <div class="candidate-record-list">
            <?php foreach ($applications as $application): ?>
              <a href="application.php?id=<?= rawurlencode((string)$application['public_id']) ?>">
                <span><strong><?= $safe($application['job_title']) ?></strong><small><?= $safe($application['location_label']) ?> · Updated <?= $safe($application['updated_at']) ?></small></span>
                <em><?= $safe($application['candidate_status']) ?></em>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif ($viewKey === 'application'): ?>
      <?php $application = $data['application'] ?? null; ?>
      <?php if (!is_array($application)): ?>
        <div class="candidate-empty candidate-portal-panel"><strong>Application not found</strong><span>The application may be unavailable or outside this candidate account.</span></div>
      <?php else: ?>
        <div class="candidate-portal-grid">
          <section class="candidate-portal-panel" aria-labelledby="application-role-title">
            <div class="candidate-panel-heading"><div><p><?= $safe($application['candidate_status']) ?></p><h2 id="application-role-title"><?= $safe($application['job_title']) ?></h2></div></div>
            <dl class="candidate-detail-list">
              <div><dt>Location</dt><dd><?= $safe($application['location_label']) ?></dd></div>
              <div><dt>Work arrangement</dt><dd><?= $safe($application['work_arrangement']) ?></dd></div>
              <div><dt>Employment type</dt><dd><?= $safe($application['employment_type']) ?></dd></div>
              <div><dt>Application reference</dt><dd><?= $safe($application['public_id']) ?></dd></div>
            </dl>
            <?php if ($application['current_status'] === 'draft'): ?>
              <form class="candidate-inline-form" action="actions/portal.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="application_submit"><input type="hidden" name="application_public_id" value="<?= $safe($application['public_id']) ?>">
                <label><input type="checkbox" name="job_change_acknowledged" value="1"> I reviewed the current job terms if they changed.</label>
                <button class="candidate-primary" type="submit">Submit application</button>
              </form>
            <?php elseif (RecruitmentPolicy::canTransition('application', (string)$application['current_status'], 'withdrawn')): ?>
              <form class="candidate-inline-form" action="actions/portal.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="application_withdraw"><input type="hidden" name="application_public_id" value="<?= $safe($application['public_id']) ?>">
                <label for="withdrawal-reason">Withdrawal reason</label><textarea id="withdrawal-reason" name="reason" required minlength="3" maxlength="500"></textarea>
                <button class="candidate-secondary" type="submit">Withdraw application</button>
              </form>
            <?php endif; ?>
          </section>
          <section class="candidate-portal-panel" aria-labelledby="application-timeline-title">
            <div class="candidate-panel-heading"><div><p>Dated updates</p><h2 id="application-timeline-title">Application timeline</h2></div></div>
            <ol class="candidate-timeline">
              <?php foreach ($application['timeline'] ?? [] as $event): ?><li><strong><?= $safe($event['status']) ?></strong><span><?= $safe($event['message']) ?></span><time><?= $safe($event['at']) ?></time></li><?php endforeach; ?>
            </ol>
          </section>
        </div>
      <?php endif; ?>
    <?php elseif ($viewKey === 'apply'): ?>
      <?php $job = $data['job'] ?? null; ?>
      <section class="candidate-portal-panel candidate-application-form" aria-labelledby="apply-form-title">
        <div class="candidate-panel-heading"><div><p><?= is_array($job) ? $safe($job['location_label']) : 'Approved opportunity' ?></p><h2 id="apply-form-title"><?= is_array($job) ? $safe($job['title']) : 'Application form preview' ?></h2></div></div>
        <?php if ($ready && !is_array($job)): ?>
          <div class="candidate-empty"><strong>This role is not accepting applications</strong><span>Return to the TAASCOR Careers page for current opportunities.</span></div>
        <?php else: ?>
          <form action="actions/portal.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="application_save"><input type="hidden" name="job_public_id" value="<?= is_array($job) ? $safe($job['public_id']) : '' ?>">
            <fieldset<?= $ready ? '' : ' disabled' ?>>
              <div class="candidate-form-grid"><label>Full legal name<input name="full_name" required minlength="2" maxlength="190" autocomplete="name"></label><label>Phone number<input name="phone" required minlength="7" maxlength="40" autocomplete="tel" inputmode="tel"></label></div>
              <label>Current city or municipality<input name="current_city" required minlength="2" maxlength="190" autocomplete="address-level2"></label>
              <label>Relevant experience summary<textarea name="experience_summary" maxlength="2000" rows="6"></textarea></label>
              <label class="candidate-check"><input type="checkbox" name="eligibility_confirmed" value="1" required><span>I confirm that I am eligible to work in the Philippines.</span></label>
              <label class="candidate-check"><input type="checkbox" name="privacy_acknowledged" value="1" required><span>I have read the <a href="privacy.php">candidate privacy notice</a>.</span></label>
              <button class="candidate-primary" type="submit">Save and review</button>
            </fieldset>
          </form>
        <?php endif; ?>
      </section>
    <?php elseif ($viewKey === 'interviews'): ?>
      <div class="candidate-card-grid">
        <?php foreach ($data['interviews'] ?? [] as $interview): ?>
          <article class="candidate-portal-panel"><p class="candidate-card-label"><?= $safe($interview['status']) ?></p><h2><?= $safe($interview['job_title']) ?></h2><dl class="candidate-detail-list"><div><dt>Starts</dt><dd><?= $safe($interview['starts_at_utc']) ?> UTC</dd></div><div><dt>Timezone</dt><dd><?= $safe($interview['timezone_name']) ?></dd></div><div><dt>Format</dt><dd><?= $safe($interview['location_type']) ?></dd></div><div><dt>Location</dt><dd><?= $safe($interview['location']) ?></dd></div></dl><?php if ($interview['status'] === 'scheduled'): ?><form class="candidate-action-row" action="actions/portal.php" method="post"><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="interview_response"><input type="hidden" name="interview_public_id" value="<?= $safe($interview['public_id']) ?>"><button name="response" value="confirmed" class="candidate-primary" type="submit">Confirm</button><button name="response" value="reschedule_requested" class="candidate-secondary" type="submit">Request another time</button></form><?php endif; ?></article>
        <?php endforeach; ?>
        <?php if (($data['interviews'] ?? []) === []): ?><div class="candidate-empty candidate-portal-panel"><strong>No interviews to show</strong><span>Interview invitations and responses will appear here.</span></div><?php endif; ?>
      </div>
    <?php elseif ($viewKey === 'offers'): ?>
      <div class="candidate-card-grid">
        <?php foreach ($data['offers'] ?? [] as $offer): ?><article class="candidate-portal-panel"><p class="candidate-card-label"><?= $safe($offer['status']) ?> · Version <?= $safe($offer['current_version']) ?></p><h2><?= $safe($offer['job_title']) ?></h2><dl class="candidate-detail-list"><?php foreach ($offer['terms'] as $label => $value): ?><div><dt><?= $safe(ucwords(str_replace('_', ' ', (string)$label))) ?></dt><dd><?= $safe($value ?? 'Not specified') ?></dd></div><?php endforeach; ?></dl><?php if ($offer['status'] === 'delivered'): ?><form class="candidate-inline-form" action="actions/portal.php" method="post"><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="offer_response"><input type="hidden" name="offer_public_id" value="<?= $safe($offer['public_id']) ?>"><label for="offer-note-<?= $safe($offer['public_id']) ?>">Optional note<textarea id="offer-note-<?= $safe($offer['public_id']) ?>" name="note" maxlength="1000"></textarea></label><div class="candidate-action-row"><button name="response" value="accepted" class="candidate-primary" type="submit">Accept offer</button><button name="response" value="declined" class="candidate-secondary" type="submit">Decline offer</button></div></form><?php endif; ?></article><?php endforeach; ?>
        <?php if (($data['offers'] ?? []) === []): ?><div class="candidate-empty candidate-portal-panel"><strong>No offers to show</strong><span>Only approved and delivered offer versions appear here.</span></div><?php endif; ?>
      </div>
    <?php elseif ($viewKey === 'onboarding'): ?>
      <div class="candidate-card-grid">
        <?php foreach ($data['cases'] ?? [] as $case): ?><section class="candidate-portal-panel"><p class="candidate-card-label"><?= $safe($case['status']) ?></p><h2><?= $safe($case['job_title']) ?></h2><p>Target start: <?= $safe($case['target_start_date'] ?? 'Not set') ?></p><div class="candidate-task-list"><?php foreach ($case['items'] as $item): ?><article><div><strong><?= $safe($item['title']) ?></strong><span><?= $safe($item['purpose_text']) ?></span><small><?= $safe($item['visibility_text']) ?> · <?= $safe($item['classification']) ?></small></div><em><?= $safe($item['status']) ?></em><?php if (in_array($item['status'], ['pending', 'in_progress', 'changes_requested'], true)): ?><form action="actions/portal.php" method="post"><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="onboarding_item_update"><input type="hidden" name="item_public_id" value="<?= $safe($item['public_id']) ?>"><input type="hidden" name="reason" value="Candidate completed the requested step."><button name="status" value="<?= $item['status'] === 'pending' ? 'in_progress' : 'submitted' ?>" class="candidate-secondary" type="submit"><?= $item['status'] === 'pending' ? 'Start' : 'Submit for review' ?></button></form><?php endif; ?></article><?php endforeach; ?></div></section><?php endforeach; ?>
        <?php if (($data['cases'] ?? []) === []): ?><div class="candidate-empty candidate-portal-panel"><strong>No onboarding case to show</strong><span>Approved onboarding steps appear only after an accepted offer.</span></div><?php endif; ?>
      </div>
    <?php elseif ($viewKey === 'documents'): ?>
      <section class="candidate-portal-panel"><div class="candidate-panel-heading"><div><p>Secure requirements</p><h2>Document request history</h2></div></div><div class="candidate-task-list"><?php foreach ($data['documents'] ?? [] as $document): ?><article><div><strong><?= $safe(ucwords(str_replace('_', ' ', (string)$document['purpose_code']))) ?></strong><span><?= $safe($document['classification']) ?> · Due <?= $safe($document['due_at'] ?? 'Not set') ?></span><small>Scan: <?= $safe($document['scan_status'] ?? 'No file') ?> · Review: <?= $safe($document['review_status'] ?? 'Pending') ?></small><?php if ($document['public_id'] && RecruitmentDocumentPolicy::canRelease((string)$document['scan_status'],(string)$document['review_status'])): ?><a href="document-download.php?id=<?= $safe($document['public_id']) ?>">Download approved file</a><?php elseif (!$document['public_id'] && $document['request_status']==='open'): ?><form action="actions/portal.php" method="post" enctype="multipart/form-data"><fieldset<?= $ready ? '' : ' disabled' ?>><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="document_upload"><input type="hidden" name="request_public_id" value="<?= $safe($document['request_public_id']) ?>"><label>PDF, JPG, or PNG up to 5 MB<input type="file" name="document" accept="application/pdf,image/jpeg,image/png" required></label><button class="candidate-secondary" type="submit">Upload securely</button></fieldset></form><?php endif; ?></div><em><?= $safe($document['request_status']) ?></em></article><?php endforeach; ?><?php if (($data['documents'] ?? []) === []): ?><div class="candidate-empty"><strong>No document requests</strong><span>TAASCOR requests documents only when an approved stage and purpose require them.</span></div><?php endif; ?></div></section>
    <?php elseif ($viewKey === 'messages'): ?>
      <div class="candidate-portal-grid"><section class="candidate-portal-panel"><div class="candidate-panel-heading"><div><p>Conversation</p><h2>Message history</h2></div></div><div class="candidate-message-list"><?php foreach ($data['messages'] ?? [] as $message): ?><article><p><?= $safe($message['direction']) ?> · <?= $safe($message['sent_at']) ?></p><h3><?= $safe($message['subject']) ?></h3><div><?= nl2br($safe($message['body'])) ?></div></article><?php endforeach; ?><?php if (($data['messages'] ?? []) === []): ?><div class="candidate-empty"><strong>No messages yet</strong><span>Messages sent through your candidate account will appear here.</span></div><?php endif; ?></div></section><section class="candidate-portal-panel"><div class="candidate-panel-heading"><div><p>Recruitment support</p><h2>Send a message</h2></div></div><form class="candidate-inline-form" action="actions/portal.php" method="post"><fieldset<?= $ready ? '' : ' disabled' ?>><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="support_message"><label>Related application<select name="application_public_id"><option value="">General question</option><?php foreach ($data['applications'] ?? [] as $application): ?><option value="<?= $safe($application['public_id']) ?>"><?= $safe($application['job_title']) ?></option><?php endforeach; ?></select></label><label>Subject<input name="subject" required minlength="3" maxlength="190"></label><label>Message<textarea name="body" required minlength="10" maxlength="4000" rows="7"></textarea></label><button class="candidate-primary" type="submit">Send message</button></fieldset></form></section></div>
    <?php elseif ($viewKey === 'settings'): ?>
      <div class="candidate-portal-grid"><section class="candidate-portal-panel"><div class="candidate-panel-heading"><div><p>Optional updates</p><h2>Notification preferences</h2></div></div><p>Security, verification, offer, and legally required service messages cannot be disabled.</p><?php foreach (['interview_invitation' => 'Interview invitations', 'offer_available' => 'Offer availability', 'onboarding_action' => 'Onboarding reminders'] as $type => $label): ?><form class="candidate-preference" action="actions/portal.php" method="post"><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="notification_preference"><input type="hidden" name="channel" value="email"><input type="hidden" name="message_type" value="<?= $safe($type) ?>"><span><?= $safe($label) ?></span><button class="candidate-secondary" name="enabled" value="1" type="submit">Enable email</button><button class="candidate-text-button" name="enabled" value="0" type="submit">Disable</button></form><?php endforeach; ?></section><section class="candidate-portal-panel"><div class="candidate-panel-heading"><div><p>Your information</p><h2>Privacy request</h2></div><a href="privacy.php">Read notice</a></div><form class="candidate-inline-form" action="actions/portal.php" method="post"><input type="hidden" name="csrf_token" value="<?= $safe($csrf) ?>"><input type="hidden" name="action" value="privacy_request"><label>Request type<select name="request_type" required><option value="access">Access</option><option value="correction">Correction</option><option value="deletion">Deletion</option><option value="restriction">Restriction</option><option value="objection">Objection</option></select></label><label>Details<textarea name="details" required minlength="10" maxlength="4000" rows="7"></textarea></label><button class="candidate-primary" type="submit">Submit privacy request</button></form></section></div>
    <?php endif; ?>

    <?php if (!$ready && $viewKey !== 'dashboard'): ?><div class="candidate-empty candidate-portal-panel"><strong><?= $safe($page['title']) ?> is not connected</strong><span>This route is complete but remains source-locked until candidate privacy and security qualification.</span></div><?php endif; ?>
    <p class="candidate-portal-fraud">TAASCOR will never ask for payment to process a job application. Verify every opportunity through the official TAASCOR website.</p>
  </main>
  <script src="../assets/candidate.js?v=20260910-2" defer></script>
<?php require_once __DIR__.'/../includes/guide_component.php'; recruitment_guide_render('candidate.'.$viewKey); ?>
</body>
</html>
