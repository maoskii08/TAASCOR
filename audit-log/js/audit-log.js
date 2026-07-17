'use strict';
const auditPath = $('#url_page').val();
const API = `/${auditPath}/audit-log/controller/AuditLogController.php`;
let logDT = null, sumDT = null;

// Set default date range — last 30 days
const today = new Date();
const d30   = new Date(); d30.setDate(d30.getDate() - 30);
$('#f_date_to').val(today.toISOString().slice(0,10));
$('#f_date_from').val(d30.toISOString().slice(0,10));

// Load action type filter
$.post(API, {request:'get-action-types'}, r => {
    (r.data||[]).forEach(a =>
        $('#f_action').append(`<option value="${a.log_action}">${a.log_action}</option>`)
    );
}, 'json');

// Search — button click
$('#btnSearch').on('click', searchLogs);

// Live search — fires 400 ms after user stops typing in Username field
let _usernameTimer = null;
$('#f_username').on('input', function () {
    clearTimeout(_usernameTimer);
    _usernameTimer = setTimeout(searchLogs, 400);
});

function searchLogs() {
    $.post(API, {
        request:   'get-logs',
        username:  $('#f_username').val(),
        action:    $('#f_action').val(),
        date_from: $('#f_date_from').val(),
        date_to:   $('#f_date_to').val()
    }, r => {
        if (logDT) { logDT.destroy(); $('#logTable').empty(); }
        logDT = $('#logTable').DataTable({
            data: r.data || [],
            columns: [
                {title:'#',        data:'id',                    className:'text-end'},
                {title:'Username', data:'username'},
                {title:'Action',   data:'log_action', render: a =>
                    `<span class="badge ${a==='Login'?'bg-success':'bg-primary'}">${a}</span>`},
                {title:'Date/Time', data:'inserted_date_time_ph'}
            ],
            order:[[0,'desc']], pageLength:50,
            dom:'Bfrtip', buttons:['excelHtml5','csvHtml5'], responsive:true
        });
        const total = r.total || 0;
        // mini stats strip
        $('#logTable_wrapper').prepend(
            `<div class="alert alert-info py-2 mb-3"><i class="bx bx-info-circle me-1"></i>
             <strong>${total}</strong> records found (showing latest 2,000)</div>`
        );
    }, 'json');
}

// Login summary tab
$('#tabSumLink').on('shown.bs.tab', function() {
    if (sumDT) return;
    $.post(API, {request:'get-login-summary'}, r => {
        sumDT = $('#sumTable').DataTable({
            data: r.data||[],
            columns: [
                {title:'Username',      data:'username'},
                {title:'Total Logins',  data:'total_logins',  className:'text-end'},
                {title:'Today',         data:'logins_today',  className:'text-end',
                    render: v => v>0 ? `<span class="badge bg-success">${v}</span>` : '0'},
                {title:'Last Login',    data:'last_login'},
                {title:'First Login',   data:'first_login'}
            ],
            order:[[3,'desc']], pageLength:50,
            dom:'Bfrtip', buttons:['excelHtml5'], responsive:true
        });
    }, 'json');
});

// Auto-search on load
$(document).ready(searchLogs);
