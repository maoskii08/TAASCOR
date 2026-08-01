<?php require_once(__DIR__ . '/../legacy-route-disabled.php'); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
  <title>Forgot Password — TAASCOR HRIS</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png"/>
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css"/>
  <link rel="stylesheet" href="../assets/vendor/css/core.css"/>
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css"/>
  <link rel="stylesheet" href="../assets/css/login.css"/>
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>
<body>
<div class="container" style="max-width:460px;margin:80px auto;padding:20px;">
  <div style="text-align:center;margin-bottom:32px;">
    <img src="../assets/img/svg/logo.svg" class="img-fluid" width="260px" alt="TAASCOR HRIS">
    <h4 style="margin-top:16px;color:#1a237e;">Forgot Password</h4>
    <p class="text-muted small">Enter your username. If an email is on file, we'll send a reset link.</p>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div id="alertBox" style="display:none" class="alert mb-3"></div>

      <div id="formSection">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" id="usernameInput" class="form-control" placeholder="Your HRIS username" autofocus>
        </div>
        <button id="btnSend" class="btn btn-primary w-100">
          <i class="bx bx-send me-1"></i> Send Reset Link
        </button>
      </div>

      <div class="text-center mt-3">
        <a href="./" class="small text-muted"><i class="bx bx-arrow-back me-1"></i>Back to Login</a>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script>
$('#btnSend').on('click', function () {
    var username = $('#usernameInput').val().trim();
    if (!username) { showAlert('danger', 'Please enter your username.'); return; }

    $('#btnSend').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Sending...');

    $.post('controller/ForgotPasswordController.php',
        { request: 'forgot-password', username: username },
        function (r) {
            if (r.success) {
                $('#formSection').hide();
                showAlert('success',
                    '<i class="bx bx-check-circle me-1"></i><strong>Sent!</strong> ' + r.message);
            } else {
                showAlert('danger', r.error || 'An error occurred.');
                $('#btnSend').prop('disabled', false).html('<i class="bx bx-send me-1"></i> Send Reset Link');
            }
        }, 'json'
    ).fail(function () {
        showAlert('danger', 'Server error. Please try again.');
        $('#btnSend').prop('disabled', false).html('<i class="bx bx-send me-1"></i> Send Reset Link');
    });
});

$('#usernameInput').on('keypress', function (e) {
    if (e.which === 13) $('#btnSend').click();
});

function showAlert(type, msg) {
    $('#alertBox').removeClass('alert-success alert-danger alert-info')
                  .addClass('alert-' + type).html(msg).show();
}
</script>
</body>
</html>
