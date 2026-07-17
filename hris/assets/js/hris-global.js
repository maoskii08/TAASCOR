/**
 * hris-global.js
 * Loaded on every authenticated page via custom-footer.php.
 * Handles: CSRF token injection, session timeout, global AJAX errors.
 */
(function ($) {
    'use strict';

    // ── CSRF: inject X-CSRF-Token header on every jQuery AJAX POST ────────
    $(document).ajaxSend(function (_event, xhr, settings) {
        var method = String(settings.type || settings.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
            var csrfToken = $('#csrf_token').val() || '';
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
            }
        }
    });

    // ── Session timeout / 401 handler ─────────────────────────────────────
    $(document).ajaxError(function (event, xhr) {
        if (xhr.status === 401) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.timeout) {
                    // Show a friendly message then redirect
                    if (typeof swal !== 'undefined') {
                        swal({
                            title: 'Session Expired',
                            text: resp.error || 'Your session has expired. Please log in again.',
                            icon: 'warning',
                            button: 'Log In'
                        }).then(function () {
                            window.location.href = (resp.redirect || '../login/');
                        });
                    } else {
                        alert(resp.error || 'Session expired. Please log in again.');
                        window.location.href = (resp.redirect || '../login/');
                    }
                }
            } catch (e) {
                // Non-JSON 401 — just redirect
                window.location.href = '../login/';
            }
        }
    });

    // ── Session idle warning (2 min before timeout) ───────────────────────
    var IDLE_WARN_MS  = (1800 - 120) * 1000; // warn at 28 min
    var warningShown  = false;
    var lastActivity  = Date.now();

    function resetTimer() { lastActivity = Date.now(); warningShown = false; }
    $(document).on('mousemove keydown click touchstart', resetTimer);

    setInterval(function () {
        var idle = Date.now() - lastActivity;
        if (idle >= IDLE_WARN_MS && !warningShown) {
            warningShown = true;
            if (typeof swal !== 'undefined') {
                swal({
                    title: 'Still there?',
                    text: 'You will be logged out in 2 minutes due to inactivity.',
                    icon: 'warning',
                    buttons: { cancel: 'Log Out Now', confirm: 'Keep Working' }
                }).then(function (keep) {
                    if (!keep) {
                        window.location.href = '../login/logout.php';
                    } else {
                        resetTimer();
                        // Ping server to reset idle timer
                        $.post('../restriction/controller/RestrictionController.php',
                            { page: 0 });
                    }
                });
            }
        }
    }, 30000); // check every 30 seconds

}(jQuery));
