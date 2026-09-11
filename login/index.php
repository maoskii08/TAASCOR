<?php
    require_once('../includes/session_security.php');
    taascor_start_secure_session();
    $loginError = $_SESSION['error'] ?? null;
    unset($_SESSION['error']);
    require_once('../includes/csrf.php');

    $nextPath = (string)($_GET['next'] ?? '');
    if (
        $nextPath === ''
        || !str_starts_with($nextPath, '/')
        || str_starts_with($nextPath, '//')
        || preg_match('/[\r\n]/', $nextPath)
    ) {
        $nextPath = '';
    }
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Login</title>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

  <link rel="stylesheet" href="vendors/bootstrap-4.6.0/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../assets/css/login.css" />
</head>

<body>
  <div class="container">
    <div class="form-box login">
      <form action="controller/LoginController.php" method="post" class="login">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="next_path" value="<?php echo htmlspecialchars($nextPath, ENT_QUOTES, 'UTF-8'); ?>">
        <img src="../assets/img/svg/logo.svg" class="img-fluid" width="380" alt="TAASCOR HRIS">
        <h1 class="mb-5">Login</h1>
        <div class="input-box mt-5">
          <label class="field-label" for="loginUsername">Username</label>
          <input id="loginUsername" type="text" name="user_name" autocomplete="username" required>
        </div>
        <div class="input-box">
          <label class="field-label" for="loginPassword">Password</label>
          <input id="loginPassword" type="password" name="user_pass" autocomplete="current-password" required>
        </div>
        <div class="d-flex align-items-center mb-3" style="gap:.5rem;">
          <input class="form-check-input mt-0" type="checkbox" id="showLoginPassword">
          <label class="form-check-label small" for="showLoginPassword">Show password</label>
        </div>
        <button type="submit" class="btn">Login</button>
        <div class="forgot-link">
          <a href="forgot-password.php">Forgot Password?</a>
          <br>
          <?php 
              if($loginError === null){
                  echo '<span></span>';
              } else{
                  echo '<span class="text-danger font-weight-bold mt-3 mb-2">'. htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') . '</span>';
              }
              session_write_close();
          ?>
        </div>
      </form>
    </div>

    <div class="toggle-box">
      <div class="toggle-panel toggle-left">
        <h2 class="greet">Hello, Welcome!</h2>
        <p>Need an account?</p>
        <p>Contact your HRIS administrator.</p>
      </div>
    </div>
  </div>


  </div>
  <script>
    document.getElementById('showLoginPassword').addEventListener('change', function () {
      const password = document.getElementById('loginPassword');
      password.type = this.checked ? 'text' : 'password';
    });
  </script>
</body>

</html>
