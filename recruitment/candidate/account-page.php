<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/public_candidate_origin.php';
recruitment_redirect_candidate_to_public_origin();
require_once __DIR__ . '/../includes/candidate_runtime.php';
require_once __DIR__ . '/../includes/candidate_csrf.php';

$mode = isset($candidatePageMode) ? (string)$candidatePageMode : 'login';
$allowedModes = ['login', 'register', 'recover', 'reset', 'verify'];
if (!in_array($mode, $allowedModes, true)) {
    http_response_code(404);
    exit;
}

$ready = recruitment_candidate_runtime_ready();
$flash = recruitment_candidate_take_flash();
$token = trim((string)($_GET['token'] ?? ''));
$page = [
    'login' => ['title' => 'Welcome back', 'intro' => 'Sign in to review your applications and required actions.'],
    'register' => ['title' => 'Create your candidate account', 'intro' => 'Use one email address to manage every TAASCOR application.'],
    'recover' => ['title' => 'Recover your account', 'intro' => 'We will send instructions when the account is eligible to continue.'],
    'reset' => ['title' => 'Choose a new password', 'intro' => 'Use a strong password that you do not use on another service.'],
    'verify' => ['title' => 'Confirm your email', 'intro' => 'Confirming your email protects your application and account history.'],
][$mode];

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <title><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?> | TAASCOR Careers</title>
  <link rel="icon" href="../../assets/img/favicon/favicon.ico">
  <link rel="stylesheet" href="../assets/candidate.css?v=20260910-1">
</head>
<body class="candidate-page">
  <a class="candidate-skip" href="#candidate-form">Skip to account form</a>
  <main class="candidate-shell">
    <section class="candidate-brand" aria-labelledby="candidate-brand-title">
      <a class="candidate-logo" href="../../" aria-label="TAASCOR HRIS home">
        <img src="../../assets/img/svg/logo-wo-visio.svg" alt="TAASCOR Management and General Services Corporation">
      </a>
      <div class="candidate-brand-copy">
        <p class="candidate-kicker">TAASCOR Careers</p>
        <h2 id="candidate-brand-title">Your application stays in your control.</h2>
        <p>Return to one place for application updates, interviews, offers, and onboarding tasks.</p>
      </div>
      <div class="candidate-trust" aria-label="Account protection summary">
        <strong>Candidate account protection</strong>
        <span>Separate from employee and staff access</span>
      </div>
    </section>

    <section class="candidate-account" aria-labelledby="candidate-title">
      <div class="candidate-account-inner" id="candidate-form">
        <?php if (!$ready): ?>
          <div class="candidate-notice candidate-notice-warning" role="status">
            <strong>Candidate accounts are not open yet.</strong>
            <span>This preview does not collect or submit personal information.</span>
          </div>
        <?php elseif ($flash): ?>
          <div class="candidate-notice candidate-notice-<?= htmlspecialchars((string)($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>" role="status">
            <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <header class="candidate-heading">
          <h1 id="candidate-title"><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
          <p><?= htmlspecialchars($page['intro'], ENT_QUOTES, 'UTF-8') ?></p>
        </header>

        <form action="actions/account.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(recruitment_candidate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
          <?php if (in_array($mode, ['reset', 'verify'], true)): ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>
          <fieldset <?= $ready ? '' : 'disabled' ?>>
            <?php if (in_array($mode, ['login', 'register', 'recover'], true)): ?>
              <div class="candidate-field">
                <label for="candidate-email">Email address</label>
                <input id="candidate-email" name="email" type="email" inputmode="email" autocomplete="email" required aria-describedby="candidate-email-help">
                <small id="candidate-email-help">Use the email address connected to your applications.</small>
              </div>
            <?php endif; ?>

            <?php if (in_array($mode, ['login', 'register', 'reset'], true)): ?>
              <div class="candidate-field">
                <label for="candidate-password"><?= $mode === 'reset' ? 'New password' : 'Password' ?></label>
                <div class="candidate-password-control">
                  <input id="candidate-password" name="password" type="password" autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>" required <?= $mode === 'login' ? '' : 'minlength="12" aria-describedby="candidate-password-help"' ?>>
                  <button type="button" class="candidate-show-password" data-password-toggle="candidate-password" aria-pressed="false">Show</button>
                </div>
                <?php if ($mode !== 'login'): ?>
                  <small id="candidate-password-help">At least 12 characters with upper and lowercase letters, a number, and a symbol.</small>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (in_array($mode, ['register', 'reset'], true)): ?>
              <div class="candidate-field">
                <label for="candidate-password-confirm">Confirm password</label>
                <input id="candidate-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="12">
              </div>
            <?php endif; ?>

            <?php if ($mode === 'register'): ?>
              <label class="candidate-consent" for="candidate-privacy">
                <input id="candidate-privacy" name="privacy_acknowledged" type="checkbox" value="1" required>
                <span>I have read the <a href="privacy.php">candidate privacy notice</a> and understand how my information will be used.</span>
              </label>
            <?php endif; ?>

            <?php if ($mode === 'verify' && $token === ''): ?>
              <p class="candidate-inline-error" role="alert">The verification link is incomplete.</p>
            <?php endif; ?>

            <button class="candidate-primary" type="submit" <?= in_array($mode, ['verify', 'reset'], true) && $token === '' ? 'disabled' : '' ?>>
              <?= htmlspecialchars([
                  'login' => 'Sign in',
                  'register' => 'Create account',
                  'recover' => 'Send recovery instructions',
                  'reset' => 'Save new password',
                  'verify' => 'Confirm email',
              ][$mode], ENT_QUOTES, 'UTF-8') ?>
            </button>
          </fieldset>
        </form>

        <nav class="candidate-account-links" aria-label="Candidate account options">
          <?php if ($mode === 'login'): ?>
            <a href="register.php">Create an account</a>
            <a href="recover.php">Forgot your password?</a>
          <?php else: ?>
            <a href="index.php">Return to sign in</a>
          <?php endif; ?>
        </nav>
        <p class="candidate-support">TAASCOR will never ask for payment to process a job application.</p>
      </div>
    </section>
  </main>
  <script src="../assets/candidate.js?v=20260910-1" defer></script>
<?php require_once __DIR__.'/../includes/guide_component.php'; recruitment_guide_render('candidate.'.($mode==='login'?'sign-in':$mode)); ?>
</body>
</html>
