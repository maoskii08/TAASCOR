<?php
require_once('../includes/session_security.php');
taascor_start_secure_session();
require_once('../includes/csrf.php');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — TAASCOR HRIS</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico"/>
  <link rel="stylesheet" href="vendors/bootstrap-4.6.0/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="../assets/css/login.css"/>
</head>
<body>
<div class="container" style="max-width:460px;margin:80px auto;padding:20px;">
  <div style="text-align:center;margin-bottom:32px;">
    <img src="../assets/img/svg/logo.svg" class="img-fluid" width="260px" alt="TAASCOR HRIS">
    <h1 style="margin-top:16px;color:#1a237e;font-size:2rem;">Forgot Password</h1>
    <p class="text-muted small">Enter your username. If an email is on file, we'll send a reset link.</p>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div id="alertBox" style="display:none" class="alert mb-3"></div>

      <div id="formSection">
        <div class="mb-3">
          <label class="form-label" for="usernameInput">Username</label>
          <input type="text" id="usernameInput" class="form-control" autocomplete="username" autofocus>
        </div>
        <button id="btnSend" class="btn btn-primary w-100">
          Send Reset Link
        </button>
      </div>

      <div class="text-center mt-3">
        <a href="./" class="small text-muted">Back to Login</a>
      </div>
    </div>
  </div>
</div>

<script src="vendors/bootstrap-core/jquery-3.5.1.js"></script>
<script>
var csrfToken = <?= json_encode(csrf_token()) ?>;
$('#btnSend').on('click', function () {
    var username = $('#usernameInput').val().trim();
    if (!username) { showAlert('danger', 'Please enter your username.'); return; }

    $('#btnSend').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Sending...');

    $.post('controller/ForgotPasswordController.php',
        { request: 'forgot-password', username: username, csrf_token: csrfToken },
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
