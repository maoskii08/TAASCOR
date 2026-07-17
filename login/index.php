<?php
    session_start();
    session_destroy();
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
        <img src="../assets/img/svg/logo.svg" class="img-fluid" width="380px">
        <h1 class="mb-5">Login</h1>
        <div class="input-box mt-5">
          <input type="text" name="user_name" placeholder="Username" required>
          <i class='bx bx-user'></i>
        </div>
        <div class="input-box">
          <input type="password" name="user_pass" placeholder="Password" required>
          <i class='bx bx-lock-alt'></i>
        </div>
        <button type="submit" class="btn">Login</button>
        <div class="forgot-link">
          <a href="forgot-password.php">Forgot Password?</a>
          <br>
          <?php 
              if(isset($_SESSION['error']) == false){
                  echo '<span></span>';
              } else{
                  echo '<span class="text-danger font-weight-bold mt-3 mb-2">'. $_SESSION['error'] . '</span>';
              }
              unset($_SESSION['error']);
              session_write_close();
          ?>
        </div>
      </form>
    </div>

    <div class="form-box register">
      <form action="#">
        <h1>Sign Up</h1>
        <div class="input-box">
          <input id="username" type="text" placeholder="Username" required>
          <i class='bx bx-user'></i>
        </div>
        <div class="input-box">
          <input id="password" type="password" placeholder="Password" required>
          <i class='bx bx-lock-alt'></i>
        </div>
        <div class="input-box">
          <input id="firstname" type="text" placeholder="First Name" required>
          <i class='bx bx-user'></i>
        </div>
        <div class="input-box">
          <input id="lastname" type="text" placeholder="Last Name" required>
          <i class='bx bx-user'></i>
        </div>
        <div class="input-box">
          <input id="email" type="email" placeholder="Email" required>
          <i class='bx bx-envelope'></i>
        </div>
        <div class="input-box">
          <select class="form-select" id="department" data-placeholder="Role" required>
            <option></option>
            <option value="1">Admin</option>
            <option value="2">HR</option>
            <option value="3">Payroll</option>
            <option value="4">Coordinator</option>
          </select>
        </div>

        <div style="display:none" class="input-box" id="client_container">
          <select class="form-select js-example-basic-multiple" id="client_location"
            data-placeholder="Choose Client" multiple>
          </select>
        </div>

        <button id="signUpBtn" type="button" class="btn">Sign Up</button>
      </form>
    </div>

    <div class="toggle-box">
      <div class="toggle-panel toggle-left">
        <h1 class="greet">Hello, Welcome!</h1>
        <p>Don't have an account?</p>
        <button class="btn register-btn">Sign up</button>
      </div>

      <div class="toggle-panel toggle-right">
        <h1 class="greet">Welcome Back!</h1>
        <p>Already have an account?</p>
        <button class="btn login-btn">Login</button>
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
    const container = document.querySelector('.container');
    const registerBtn = document.querySelector('.register-btn');
    const loginBtn = document.querySelector('.login-btn');

    registerBtn.addEventListener('click', () => {
      container.classList.add('active');
    })

    loginBtn.addEventListener('click', () => {
      container.classList.remove('active');
    })
  </script>

  <script src="js/index-03.js"></script>
  <script>
    $('#department').select2({
      theme: "bootstrap-5",
      width: '100%',
      placeholder: 'Role'
    });

    $('#client_location').select2({
      theme: "bootstrap-5",
      width: '100%',
      placeholder: 'Clients',
      allowClear: true
    });

    // $('.signup-btn').on('click', function () {
    //   swal({
    //     title: "Thank you for signing up!",
    //     text: "Please wait for the email once your account is active.",
    //     icon: "success"
    //   });
    // });
  </script>
</body>

</html>