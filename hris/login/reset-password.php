<?php
require_once(__DIR__ . '/../legacy-route-disabled.php');
// Validate token exists before rendering the form
$token = $_GET['token'] ?? '';
if (!$token) { header('Location: ./'); exit(); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
  <title>Reset Password — TAASCOR HRIS</title>
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
    <h4 style="margin-top:16px;color:#1a237e;">Set New Password</h4>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div id="alertBox" style="display:none" class="alert mb-3"></div>

      <div id="formSection">
        <!-- Token validity check will show/hide this -->
        <div id="loadingMsg" class="text-center text-muted py-3">
          <i class="bx bx-loader-alt bx-spin"></i> Validating link...
        </div>
        <div id="resetForm" style="display:none">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" id="passInput" class="form-control" placeholder="Minimum 8 characters">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" id="confirmInput" class="form-control" placeholder="Repeat new password">
          </div>
          <button id="btnReset" class="btn btn-primary w-100">
            <i class="bx bx-lock-open-alt me-1"></i> Set New Password
          </button>
        </div>
      </div>

      <div class="text-center mt-3">
        <a href="./" class="small text-muted"><i class="bx bx-arrow-back me-1"></i>Back to Login</a>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script>
var token = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';

// Validate token on load
$.post('controller/ForgotPasswordController.php',
    { request: 'validate-token', token: token },
    function (r) {
        $('#loadingMsg').hide();
        if (r.valid) {
            $('#resetForm').show();
        } else {
            showAlert('danger',
                '<i class="bx bx-error me-1"></i>This reset link is <strong>invalid or has expired</strong>. '
                + 'Please <a href="forgot-password.php">request a new one</a>.');
        }
    }, 'json'
).fail(function () {
    $('#loadingMsg').hide();
    showAlert('danger', 'Could not validate the link. Please try again.');
});

// Submit new password
$('#btnReset').on('click', function () {
    var pass    = $('#passInput').val();
    var confirm = $('#confirmInput').val();

    if (!pass || !confirm) { showAlert('danger', 'Please fill in both fields.'); return; }
    if (pass !== confirm)  { showAlert('danger', 'Passwords do not match.'); return; }
    if (pass.length < 8)   { showAlert('danger', 'Password must be at least 8 characters.'); return; }

    $('#btnReset').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Saving...');

    $.post('controller/ForgotPasswordController.php',
        { request: 'reset-password', token: token, password: pass, confirm: confirm },
        function (r) {
            if (r.success) {
                $('#formSection').hide();
                showAlert('success',
                    '<i class="bx bx-check-circle me-1"></i><strong>Password updated!</strong> '
                    + r.message + ' <a href="./">Click here to log in.</a>');
            } else {
                showAlert('danger', r.error || 'An error occurred.');
                $('#btnReset').prop('disabled', false)
                              .html('<i class="bx bx-lock-open-alt me-1"></i> Set New Password');
            }
        }, 'json'
    );
});

$('#passInput, #confirmInput').on('keypress', function (e) {
    if (e.which === 13) $('#btnReset').click();
});

function showAlert(type, msg) {
    $('#alertBox').removeClass('alert-success alert-danger')
                  .addClass('alert-' + type).html(msg).show();
}
</script>
</body>
</html>
