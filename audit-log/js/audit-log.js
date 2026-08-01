'use strict';
const auditPath = $('#url_page').val();
const API = `/${auditPath}/audit-log/controller/AuditLogController.php`;
let logDT = null, sumDT = null, payrollEvidenceDT = null, dtrEvidenceDT = null;
let dtrEvidencePage = 1;
let dtrEvidenceHasMore = false;
let dtrEvidenceRequest = null;

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Set default date range — last 30 days
const today = new Date();
const d30   = new Date(); d30.setDate(d30.getDate() - 30);
$('#f_date_to').val(today.toISOString().slice(0,10));
$('#f_date_from').val(d30.toISOString().slice(0,10));

function loadActionTypes() {
    $.post(API, {request:'get-action-types'}, r => {
        (r.data||[]).forEach(a =>
            $('#f_action').append(`<option value="${escapeHtml(a.log_action)}">${escapeHtml(a.log_action)}</option>`)
        );
    }, 'json');
}

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

function payrollEvidenceFilters() {
    return {
        request: 'get-payroll-adjustment-audit-events',
        event_uid: String($('#pa_event_uid').val() || '').trim().toUpperCase(),
        client_name: String($('#pa_client_name').val() || '').trim(),
        pay_day: $('#pa_pay_day').val(),
        adjustment_kind: $('#pa_kind').val()
    };
}

function loadPayrollEvidence() {
    $('#payrollEvidenceState').removeClass('alert-danger alert-success').addClass('alert-secondary').text('Loading payroll change evidence…');
    $.post(API, payrollEvidenceFilters(), function (response) {
        if (Number(response.success) !== 1) {
            if (payrollEvidenceDT) {
                payrollEvidenceDT.clear().draw();
            }
            $('#payrollEvidenceState').removeClass('alert-secondary alert-success').addClass('alert-danger')
                .text(response.error || 'Payroll change evidence could not be loaded.');
            return;
        }
        if (payrollEvidenceDT) {
            payrollEvidenceDT.destroy();
            $('#payrollEvidenceTable').empty();
        }
        payrollEvidenceDT = $('#payrollEvidenceTable').DataTable({
            data: response.data || [],
            columns: [
                {title: 'Created', data: 'created_at'},
                {title: 'Event ID', data: 'event_uid', render: function (value) {
                    const id = escapeHtml(value);
                    return `<button type="button" class="btn btn-sm btn-link p-0 js-payroll-evidence-detail" data-event-uid="${id}">${id}</button>`;
                }},
                {title: 'Client', data: 'client_name'},
                {title: 'Pay Day', data: 'pay_day'},
                {title: 'Kind', data: 'adjustment_kind', render: function (value) {
                    return `<span class="badge bg-label-primary text-capitalize">${escapeHtml(value)}</span>`;
                }},
                {title: 'Operation', data: 'operation'},
                {title: 'Rows', data: 'row_count', className: 'text-end'},
                {title: 'Actor', data: 'actor'}
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            dom: 'Bfrtip',
            buttons: ['excelHtml5', 'csvHtml5'],
            responsive: true
        });
        const total = Number(response.total || 0);
        $('#payrollEvidenceState').removeClass('alert-secondary alert-danger').addClass('alert-success')
            .text(total === 1 ? '1 payroll change audit event found.' : `${total} payroll change audit events found.`);
    }, 'json').fail(function (xhr) {
        const payload = xhr.responseJSON || {};
        $('#payrollEvidenceState').removeClass('alert-secondary alert-success').addClass('alert-danger')
            .text(payload.error || 'Payroll change evidence could not be loaded.');
    });
}

function renderPayrollEvidenceDetail(event) {
    const rows = Array.isArray(event.rows) ? event.rows : [];
    const rowHtml = rows.map(function (row) {
        return `<tr><td>${escapeHtml(row.source_row_number)}</td><td>${escapeHtml(row.adjustment_id)}</td><td>${escapeHtml(row.employee_id)}</td><td class="text-end">${escapeHtml(row.amount)}</td><td>${escapeHtml(row.type)}</td></tr>`;
    }).join('');
    const hashClass = event.hash_valid ? 'bg-success' : 'bg-danger';
    return `
      <div class="row g-3 mb-4">
        <div class="col-md-4"><small class="text-muted d-block">Event ID</small><strong>${escapeHtml(event.event_uid)}</strong></div>
        <div class="col-md-4"><small class="text-muted d-block">Client / Pay Day</small><strong>${escapeHtml(event.client_name)} · ${escapeHtml(event.pay_day)}</strong></div>
        <div class="col-md-4"><small class="text-muted d-block">Actor / Created</small><strong>${escapeHtml(event.actor)} · ${escapeHtml(event.created_at)}</strong></div>
        <div class="col-md-4"><small class="text-muted d-block">Operation</small><strong>${escapeHtml(event.operation)}</strong></div>
        <div class="col-md-4"><small class="text-muted d-block">Exact scope</small><strong>${escapeHtml(event.period_start)} to ${escapeHtml(event.period_end)} · ${escapeHtml(event.cut_off)}</strong></div>
        <div class="col-md-4"><small class="text-muted d-block">Evidence integrity</small><span class="badge ${hashClass}">${event.hash_valid ? 'SHA-256 verified' : 'HASH MISMATCH'}</span></div>
        <div class="col-md-6"><small class="text-muted d-block">Business reason</small><strong>${escapeHtml(event.change_reason)}</strong></div>
        <div class="col-md-6"><small class="text-muted d-block">Approval / evidence reference</small><strong>${escapeHtml(event.evidence_reference)}</strong></div>
      </div>
      <div class="table-responsive"><table class="table table-sm table-bordered">
        <thead><tr><th>Source row</th><th>Adjustment ID</th><th>Employee ID</th><th>Amount</th><th>Type</th></tr></thead>
        <tbody>${rowHtml}</tbody>
      </table></div>`;
}

function openPayrollEvidence(eventUid) {
    const uid = String(eventUid || '').trim().toUpperCase();
    if (!/^[A-F0-9]{24}$/.test(uid)) {
        $('#payrollEvidenceState').removeClass('alert-secondary alert-success').addClass('alert-danger').text('The audit event identifier is invalid.');
        return;
    }
    $('#payrollEvidenceDetail').empty();
    $('#payrollEvidenceDetailState').show().removeClass('alert-danger alert-success').addClass('alert-secondary').text('Loading and verifying payroll evidence…');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('payrollEvidenceModal')).show();
    $.post(API, {request: 'get-payroll-adjustment-audit-event', event_uid: uid}, function (response) {
        if (Number(response.success) !== 1) {
            $('#payrollEvidenceDetailState').removeClass('alert-secondary alert-success').addClass('alert-danger').text(response.error || 'Payroll evidence could not be loaded.');
            return;
        }
        $('#payrollEvidenceDetailState').hide();
        $('#payrollEvidenceDetail').html(renderPayrollEvidenceDetail(response.data || {}));
    }, 'json').fail(function (xhr) {
        const payload = xhr.responseJSON || {};
        $('#payrollEvidenceDetailState').removeClass('alert-secondary alert-success').addClass('alert-danger').text(payload.error || 'Payroll evidence could not be loaded.');
    });
}

$('#btnPayrollEvidenceSearch').on('click', loadPayrollEvidence);
$('#tabPayrollEvidenceLink').on('shown.bs.tab', function () {
    if (!payrollEvidenceDT) {
        loadPayrollEvidence();
    }
});
$('#payrollEvidenceTable').on('click', '.js-payroll-evidence-detail', function () {
    openPayrollEvidence($(this).data('event-uid'));
});

function dtrEvidenceFilters() {
    return {
        request: 'get-dtr-mutation-audit-events',
        event_uid: String($('#dtr_event_uid').val() || '').trim().toUpperCase(),
        client_name: String($('#dtr_client_name').val() || '').trim(),
        pay_day: $('#dtr_pay_day').val(),
        operation: $('#dtr_operation').val(),
        employee_id: String($('#dtr_employee_id').val() || '').trim(),
        scope_kind: $('#dtr_scope_kind').val(),
        page: dtrEvidencePage,
        page_size: Number($('#dtr_page_size').val() || 25)
    };
}

function dtrOperationLabel(value) {
    return ({
        EDIT: 'Manual edit',
        BENEFIT: 'Benefit removal',
        DELETE_EMPLOYEE: 'Employee deletion',
        DELETE_BULK: 'Scoped bulk deletion'
    })[String(value || '').toUpperCase()] || String(value || '');
}

function dtrScopeLabel(value) {
    return ({
        EMPLOYEE: 'Employee',
        PAYROLL_SCOPE: 'Payroll scope'
    })[String(value || '').toUpperCase()] || String(value || 'Legacy event');
}

function setDtrPagingState(response) {
    const page = Number(response.page || dtrEvidencePage);
    const pageSize = Number(response.page_size || $('#dtr_page_size').val() || 25);
    const total = Number(response.total || 0);
    const returned = Number(response.returned || 0);
    const first = returned > 0 ? ((page - 1) * pageSize) + 1 : 0;
    const last = returned > 0 ? first + returned - 1 : 0;
    dtrEvidenceHasMore = Boolean(response.has_more);
    $('#btnDtrEvidencePrevious').prop('disabled', page <= 1);
    $('#btnDtrEvidenceNext').prop('disabled', !dtrEvidenceHasMore);
    $('#dtrEvidenceState').removeClass('alert-secondary alert-danger').addClass('alert-success')
        .text(total === 0
            ? 'No DTR change audit events matched the selected filters.'
            : `Showing ${first}-${last} of ${total} DTR change audit events. Page ${page}.`);
}

function loadDtrEvidence() {
    if (dtrEvidenceRequest && dtrEvidenceRequest.readyState !== 4) {
        dtrEvidenceRequest.abort();
    }
    $('#dtrEvidenceState').removeClass('alert-danger alert-success').addClass('alert-secondary')
        .text('Loading the bounded DTR evidence page…');
    $('#btnDtrEvidencePrevious, #btnDtrEvidenceNext').prop('disabled', true);
    dtrEvidenceRequest = $.post(API, dtrEvidenceFilters(), function (response) {
        if (Number(response.success) !== 1) {
            if (dtrEvidenceDT) {
                dtrEvidenceDT.clear().draw();
            }
            dtrEvidenceHasMore = false;
            $('#dtrEvidenceState').removeClass('alert-secondary alert-success').addClass('alert-danger')
                .text(response.error || 'DTR change evidence could not be loaded.');
            return;
        }
        if (dtrEvidenceDT) {
            dtrEvidenceDT.destroy();
            $('#dtrEvidenceTable').empty();
        }
        dtrEvidenceDT = $('#dtrEvidenceTable').DataTable({
            data: response.data || [],
            columns: [
                {title: 'Created', data: 'created_at', render: escapeHtml},
                {title: 'Event ID', data: 'event_uid', render: function (value) {
                    const id = escapeHtml(value);
                    return `<button type="button" class="btn btn-sm btn-link p-0 js-dtr-evidence-detail" data-event-uid="${id}">${id}</button>`;
                }},
                {title: 'Operation', data: 'operation', render: function (value) {
                    return `<span class="badge bg-label-primary">${escapeHtml(dtrOperationLabel(value))}</span>`;
                }},
                {title: 'Scope', data: 'scope_kind', render: function (value) {
                    return escapeHtml(dtrScopeLabel(value));
                }},
                {title: 'Employee ID', data: 'employee_id', className: 'text-end', render: function (value) {
                    return value == null || value === '' ? '—' : escapeHtml(value);
                }},
                {title: 'Client', data: 'client_name', render: escapeHtml},
                {title: 'Pay Day', data: 'pay_day', render: escapeHtml},
                {title: 'Cut Off', data: 'cut_off', render: function (value) {
                    return value == null || value === '' ? '—' : escapeHtml(value);
                }},
                {title: 'Actor', data: 'actor', render: escapeHtml}
            ],
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            responsive: true,
            dom: 't'
        });
        setDtrPagingState(response);
    }, 'json').fail(function (xhr, status) {
        if (status === 'abort') {
            return;
        }
        const payload = xhr.responseJSON || {};
        dtrEvidenceHasMore = false;
        $('#dtrEvidenceState').removeClass('alert-secondary alert-success').addClass('alert-danger')
            .text(payload.error || 'DTR change evidence could not be loaded.');
    });
}

function integrityBadge(valid, pendingLabel) {
    if (valid === null || typeof valid === 'undefined') {
        return `<span class="badge bg-label-secondary">${escapeHtml(pendingLabel || 'Not recorded')}</span>`;
    }
    return valid
        ? '<span class="badge bg-success">SHA-256 verified</span>'
        : '<span class="badge bg-danger">HASH MISMATCH</span>';
}

function prettyEvidence(value) {
    if (value === null || typeof value === 'undefined') {
        return 'No canonical scope payload was recorded for this legacy event.';
    }
    return JSON.stringify(value, null, 2);
}

function renderDtrEvidenceDetail(event) {
    const overallClass = event.hash_valid ? 'alert-success' : 'alert-danger';
    const overallText = event.hash_valid
        ? 'All recorded canonical evidence hashes were verified by the server.'
        : 'HASH MISMATCH: do not rely on this event for payroll release until it is investigated.';
    const scopeEvidence = event.scope_evidence_supported
        ? integrityBadge(event.scope_hash_valid, 'Not recorded')
        : '<span class="badge bg-label-warning">Legacy schema — scope hash unavailable</span>';
    const calculator = event.calculator_routine
        ? `${escapeHtml(event.calculator_routine)}<br><code class="text-break">${escapeHtml(event.calculator_hash || '')}</code>`
        : 'Not used for this operation';
    const scopeDetails = event.scope_evidence_present
        ? `<details open><summary class="fw-semibold mb-2">Exact canonical scope</summary><pre class="bg-label-secondary rounded p-3 overflow-auto">${escapeHtml(prettyEvidence(event.scope))}</pre></details>`
        : `<div class="alert alert-warning mb-0">This legacy event does not contain a separately hashed scope payload. Its before and after evidence can still be verified.</div>`;

    return `
      <div class="alert ${overallClass}" role="alert">${overallText}</div>
      <div class="row g-3 mb-4">
        <div class="col-lg-4"><small class="text-muted d-block">Event ID</small><strong>${escapeHtml(event.event_uid)}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Operation / Scope</small><strong>${escapeHtml(dtrOperationLabel(event.operation))} · ${escapeHtml(dtrScopeLabel(event.scope_kind))}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Actor / Created</small><strong>${escapeHtml(event.actor)} · ${escapeHtml(event.created_at)}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Employee ID</small><strong>${event.employee_id == null ? 'Not applicable' : escapeHtml(event.employee_id)}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Client / Pay Day</small><strong>${escapeHtml(event.client_name)} · ${escapeHtml(event.pay_day)}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Exact period</small><strong>${escapeHtml(event.period_start || '—')} to ${escapeHtml(event.period_end || '—')} · ${escapeHtml(event.cut_off || 'All cutoffs')}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Branch / Location IDs</small><strong>${escapeHtml(event.branch_id == null ? 'All' : event.branch_id)} · ${escapeHtml(event.client_location_id == null ? 'All' : event.client_location_id)}</strong></div>
        <div class="col-lg-4"><small class="text-muted d-block">Before / After integrity</small>${integrityBadge(event.before_hash_valid)} ${integrityBadge(event.after_hash_valid)}</div>
        <div class="col-lg-4"><small class="text-muted d-block">Scope integrity</small>${scopeEvidence}</div>
        <div class="col-md-6"><small class="text-muted d-block">Business reason</small><strong>${escapeHtml(event.change_reason)}</strong></div>
        <div class="col-md-6"><small class="text-muted d-block">Approval / evidence reference</small><strong>${escapeHtml(event.evidence_reference)}</strong></div>
        <div class="col-12"><small class="text-muted d-block">Verified calculator proof</small>${calculator}</div>
      </div>
      <div class="mb-4">${scopeDetails}</div>
      <div class="row g-3">
        <div class="col-lg-6"><details><summary class="fw-semibold mb-2">Canonical before evidence (${escapeHtml(event.before_payload_bytes)} bytes)</summary><pre class="bg-label-secondary rounded p-3 overflow-auto">${escapeHtml(prettyEvidence(event.before))}</pre></details></div>
        <div class="col-lg-6"><details><summary class="fw-semibold mb-2">Canonical after evidence (${escapeHtml(event.after_payload_bytes)} bytes)</summary><pre class="bg-label-secondary rounded p-3 overflow-auto">${escapeHtml(prettyEvidence(event.after))}</pre></details></div>
      </div>
      <details class="mt-4"><summary class="fw-semibold mb-2">Recorded SHA-256 hashes</summary>
        <dl class="row mb-0">
          <dt class="col-sm-2">Before</dt><dd class="col-sm-10"><code class="text-break">${escapeHtml(event.before_hash)}</code></dd>
          <dt class="col-sm-2">After</dt><dd class="col-sm-10"><code class="text-break">${escapeHtml(event.after_hash)}</code></dd>
          <dt class="col-sm-2">Scope</dt><dd class="col-sm-10"><code class="text-break">${escapeHtml(event.scope_hash || 'Not recorded')}</code></dd>
        </dl>
      </details>`;
}

function openDtrEvidence(eventUid, updateUrl) {
    const uid = String(eventUid || '').trim().toUpperCase();
    if (!/^DTRM-[A-F0-9]{32}$/.test(uid)) {
        $('#dtrEvidenceState').removeClass('alert-secondary alert-success').addClass('alert-danger')
            .text('The DTR audit event identifier is invalid.');
        return;
    }
    if (updateUrl !== false && window.history && window.history.replaceState) {
        const link = new URL(window.location.href);
        link.searchParams.delete('adjustment_event');
        link.searchParams.set('dtr_event', uid);
        window.history.replaceState({}, '', link.toString());
    }
    $('#dtrEvidenceDetail').empty();
    $('#dtrEvidenceDetailState').show().removeClass('alert-danger alert-success').addClass('alert-secondary')
        .text('Loading and verifying canonical DTR evidence…');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('dtrEvidenceModal')).show();
    $.post(API, {request: 'get-dtr-mutation-audit-event', event_uid: uid}, function (response) {
        if (Number(response.success) !== 1) {
            $('#dtrEvidenceDetailState').removeClass('alert-secondary alert-success').addClass('alert-danger')
                .text(response.error || 'DTR change evidence could not be loaded.');
            return;
        }
        const event = response.data || {};
        $('#dtrEvidenceDetailState').hide();
        $('#dtrEvidenceDetail').html(renderDtrEvidenceDetail(event));
    }, 'json').fail(function (xhr) {
        const payload = xhr.responseJSON || {};
        $('#dtrEvidenceDetailState').removeClass('alert-secondary alert-success').addClass('alert-danger')
            .text(payload.error || 'DTR change evidence could not be loaded.');
    });
}

$('#btnDtrEvidenceSearch').on('click', function () {
    dtrEvidencePage = 1;
    loadDtrEvidence();
});
$('#dtr_page_size').on('change', function () {
    dtrEvidencePage = 1;
    loadDtrEvidence();
});
$('#btnDtrEvidencePrevious').on('click', function () {
    if (dtrEvidencePage > 1) {
        dtrEvidencePage -= 1;
        loadDtrEvidence();
    }
});
$('#btnDtrEvidenceNext').on('click', function () {
    if (dtrEvidenceHasMore) {
        dtrEvidencePage += 1;
        loadDtrEvidence();
    }
});
$('#tabDtrEvidenceLink').on('shown.bs.tab', function () {
    if (!dtrEvidenceDT) {
        loadDtrEvidence();
    }
});
$('#dtrEvidenceTable').on('click', '.js-dtr-evidence-detail', function () {
    openDtrEvidence($(this).data('event-uid'), true);
});

// Auto-search on load
$(document).ready(function () {
    // hris-global.js is loaded after this page script and installs the shared
    // CSRF ajaxSend hook before DOM ready. Do not issue this POST at top level.
    loadActionTypes();
    searchLogs();
    const requestedDtrEvent = String(new URLSearchParams(window.location.search).get('dtr_event') || '').trim().toUpperCase();
    if (/^DTRM-[A-F0-9]{32}$/.test(requestedDtrEvent)) {
        $('#dtr_event_uid').val(requestedDtrEvent);
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tabDtrEvidenceLink')).show();
        loadDtrEvidence();
        openDtrEvidence(requestedDtrEvent, false);
        return;
    }
    const requestedEvent = String(new URLSearchParams(window.location.search).get('adjustment_event') || '').trim().toUpperCase();
    if (/^[A-F0-9]{24}$/.test(requestedEvent)) {
        $('#pa_event_uid').val(requestedEvent);
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tabPayrollEvidenceLink')).show();
        loadPayrollEvidence();
        openPayrollEvidence(requestedEvent);
    }
});
