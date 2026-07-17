'use strict';
const clientMgmtPath = $('#url_page').val();
const accessLevel = parseInt($('#access_level').val() || '0');
const API = `/${clientMgmtPath}/client-management/controller/ClientMgmtController.php`;
const fmt = v => parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});

let allClients = [];
let empDT = null, payDT = null;

// ── Load all clients and render cards ─────────────────────────────────────
function loadClients() {
    $.post(API, {request:'get-client-overview'}, function(r) {
        allClients = r.data || [];
        $('#clientCount').text(`${allClients.length} clients`);
        renderCards(allClients);
    }, 'json');
}

function renderCards(clients) {
    const grid = $('#clientGrid').empty();
    if (!clients.length) {
        grid.html('<div class="col-12 text-center text-muted py-5">No clients found.</div>');
        return;
    }
    clients.forEach(c => {
        const netFmt = parseFloat(c.last_net_pay||0) > 0
            ? `<div class="last-payroll">Last payroll: ${c.last_payroll} — ₱${fmt(c.last_net_pay)} net</div>`
            : `<div class="last-payroll text-warning">No payroll data yet</div>`;

        grid.append(`
            <div class="col-md-4 col-sm-6">
              <div class="card client-card h-100" data-id="${c.client_id}" data-name="${c.client_name}"
                   data-address="${c.address||''}" data-contact="${c.contact_person||''}"
                   data-phone="${c.contact_number||''}" data-email="${c.email||''}"
                   data-industry="${c.industry||''}" data-notes="${(c.notes||'').replace(/"/g,'&quot;')}">
                <div class="card-body">
                  <div class="d-flex align-items-start justify-content-between mb-2">
                    <h6 class="mb-0 fw-bold">${c.client_name}</h6>
                    <span class="badge bg-label-primary">${c.industry || 'N/A'}</span>
                  </div>
                  <div class="stat-badge">${c.active_headcount}</div>
                  <div class="text-muted small mb-2">active employees</div>
                  ${netFmt}
                  ${c.address ? `<div class="mt-2 small text-muted"><i class="bx bx-map me-1"></i>${c.address}</div>` : ''}
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center py-2">
                  <span class="small text-muted">${c.contact_person || '—'}</span>
                  <button class="btn btn-sm btn-outline-primary">View Details</button>
                </div>
              </div>
            </div>`);
    });
}

// ── Search ─────────────────────────────────────────────────────────────────
$('#clientSearch').on('input', function() {
    const q = $(this).val().toLowerCase();
    const filtered = allClients.filter(c =>
        c.client_name.toLowerCase().includes(q) ||
        (c.industry||'').toLowerCase().includes(q) ||
        (c.address||'').toLowerCase().includes(q)
    );
    renderCards(filtered);
    $('#clientCount').text(`${filtered.length} of ${allClients.length} clients`);
});

// ── Open detail modal ─────────────────────────────────────────────────────
$(document).on('click', '.client-card', function() {
    const id   = $(this).data('id');
    const name = $(this).data('name');

    $('#modalClientName').text(name);
    $('#pf_client_id').val(id);
    $('#pf_address').val($(this).data('address'));
    $('#pf_contact_person').val($(this).data('contact'));
    $('#pf_contact_number').val($(this).data('phone'));
    $('#pf_email').val($(this).data('email'));
    $('#pf_industry').val($(this).data('industry'));
    $('#pf_notes').val($(this).data('notes'));
    $('#pfMsg').text('');

    // Show save button only for admins
    if (accessLevel === 1) $('#pfAdminOnly').show(); else $('#pfAdminOnly').hide();

    new bootstrap.Modal(document.getElementById('clientModal')).show();
});

// ── Load employees tab ────────────────────────────────────────────────────
$('#tabEmpLink').on('shown.bs.tab', function() {
    const id = $('#pf_client_id').val();
    if (!id) return;
    $.post(API, {request:'get-client-employees', client_id: id}, function(r) {
        if (empDT) { empDT.destroy(); $('#empDetailTable').empty(); }
        empDT = $('#empDetailTable').DataTable({
            data: r.data||[],
            columns: [
                {title:'ID',         data:'employee_id'},
                {title:'Name',       data:'employee_name'},
                {title:'Type',       data:'employee_type'},
                {title:'Position',   data:'position_name'},
                {title:'Department', data:'department_name'},
                {title:'Branch',     data:'branch_name'},
                {title:'Location',   data:'location_name'},
                {title:'Daily Rate', data:'daily_salary', render: v => '₱'+fmt(v), className:'text-end'},
                {title:'Bank',       data:'bank_name'},
                {title:'Status',     data:'status', render: v => v==='Active'
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Terminated</span>'}
            ],
            pageLength: 25, dom:'Bfrtip', buttons:['excelHtml5'], responsive:true
        });
    }, 'json');
});

// ── Load payroll history tab ──────────────────────────────────────────────
$('#tabPayLink').on('shown.bs.tab', function() {
    const id = $('#pf_client_id').val();
    if (!id) return;
    $.post(API, {request:'get-client-payroll-history', client_id: id}, function(r) {
        if (payDT) { payDT.destroy(); $('#payrollHistTable').empty(); }
        payDT = $('#payrollHistTable').DataTable({
            data: r.data||[],
            columns: [
                {title:'Pay Day',       data:'pay_day'},
                {title:'Cut-off',       data:'cut_off'},
                {title:'HC',            data:'headcount',       className:'text-end'},
                {title:'Gross',         data:'total_gross',     render: v=>'₱'+fmt(v), className:'text-end'},
                {title:'Tax',           data:'total_tax',       render: v=>'₱'+fmt(v), className:'text-end'},
                {title:'SSS',           data:'total_sss',       render: v=>'₱'+fmt(v), className:'text-end'},
                {title:'PhilHealth',    data:'total_philhealth',render: v=>'₱'+fmt(v), className:'text-end'},
                {title:'Pag-IBIG',      data:'total_pagibig',   render: v=>'₱'+fmt(v), className:'text-end'},
                {title:'Net Pay',       data:'total_net',       render: v=>'₱'+fmt(v), className:'text-end fw-bold'},
                {title:'Status',        data:'status', render: v => v==='Locked'
                    ? '<span class="badge bg-success">Locked</span>'
                    : '<span class="badge bg-warning">Open</span>'}
            ],
            order:[[0,'desc']], pageLength:25, dom:'Bfrtip', buttons:['excelHtml5'], responsive:true
        });
    }, 'json');
});

// ── Save profile ──────────────────────────────────────────────────────────
$('#btnSaveProfile').on('click', function() {
    $.post(API, {
        request:        'update-client-profile',
        client_id:      $('#pf_client_id').val(),
        address:        $('#pf_address').val(),
        contact_person: $('#pf_contact_person').val(),
        contact_number: $('#pf_contact_number').val(),
        email:          $('#pf_email').val(),
        industry:       $('#pf_industry').val(),
        notes:          $('#pf_notes').val()
    }, function(r) {
        if (r.success) {
            $('#pfMsg').text('✅ Saved successfully').removeClass('text-danger').addClass('text-success');
            loadClients(); // refresh cards
        } else {
            $('#pfMsg').text('❌ ' + (r.error||'Save failed')).removeClass('text-success').addClass('text-danger');
        }
    }, 'json');
});

$(document).ready(loadClients);
