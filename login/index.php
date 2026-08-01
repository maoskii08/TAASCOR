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
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Login</title>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />

  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/login.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- Select2 -->
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2.min.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2-bootstrap-5-theme.min.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <div class="container">
    <div class="form-box login">
      <form action="controller/LoginController.php" method="post" class="login">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="next_path" value="<?php echo htmlspecialchars($nextPath, ENT_QUOTES, 'UTF-8'); ?>">
        <img src="../assets/img/svg/logo.svg" class="img-fluid" width="380px">
        <h1 class="mb-5">Login</h1>
        <div class="input-box mt-5">
          <input type="text" name="user_name" placeholder="Username" required>
          <i class='bx bx-user'></i>
        </div>
        <div class="input-box">
          <input id="loginPassword" type="password" name="user_pass" placeholder="Password" autocomplete="current-password" required>
          <i class='bx bx-lock-alt'></i>
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
        <h1 class="greet">Hello, Welcome!</h1>
        <p>Need an account?</p>
        <p>Contact your HRIS administrator.</p>
      </div>
    </div>
  </div>


  </div>
  <!-- Core JS -->
  <!-- build:js ../assets/vendor/js/core.js -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <!-- Select2 -->
  <script src="../assets/vendor/libs/select2/select2.full.min.js"></script>
  <!--Sweetalert-->
  <script src="../assets/vendor/libs/sweetalert/sweetalert.min.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>

  <!-- endbuild -->

  <!-- Main JS -->
  <!-- <script src="../assets/js/main.js"></script> -->

  <!-- Page JS -->
  <!-- <script src="../assets/js/dashboards-analytics.js"></script> -->
  <script>
    document.getElementById('showLoginPassword').addEventListener('change', function () {
      const password = document.getElementById('loginPassword');
      password.type = this.checked ? 'text' : 'password';
    });
  </script>
</body>

</html>
