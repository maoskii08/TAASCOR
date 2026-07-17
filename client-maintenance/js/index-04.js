'use strict';

// ── FinanceDash code list (from compiled FinanceDash bundle 2026-06-01) ────
const FD_CODES = [
    'ALL_INCLUSIVE','AUTO_88','BAICANG','BICHAIN','CAINIAO','CAVITE_LIGHT',
    'CENTRO','CLS','CONTINUUM','COXON','CREATIVE','CYA','DELTA','EURO_MED',
    'FABRIANO_SPA','FUJIFILM','GLOBALMAXX','LAZADA','LESLIE','MARINA',
    'MTC_TRANSPORT','MULTI_MIX','NEWBIE','NIKKOPLAS','OHGITANI','ORO CROWNE',
    'PASTURE_TO_PLATE','QCSI','SANTE','SCOMMERCE','SEALED_AIR',
    'SHINSEI PRINTING PHIL. INC','SIIX','SUMIDEN','SUPERFLOW','TECHNO_PRYME',
    'WCL','WCL_COLD_STORAGE','YUANSHAN',
];

let editId = null;

// Populate FD code dropdowns
function buildFdOptions(selectedCode) {
    let html = '<option value="">— None —</option>';
    FD_CODES.forEach(function(c) {
        const sel = (c === selectedCode) ? ' selected' : '';
        html += `<option value="${c}"${sel}>${c}</option>`;
    });
    return html;
}
$('#add-fd-code, #edit-fd-code').each(function() {
    $(this).html(buildFdOptions(''));
});

// ── Button events ─────────────────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click', saveChanges);
document.getElementById('addBtn').addEventListener('click', addClient);

// Edit button (delegated)
$(document).on('click', '#clientTbl .updateBtn', function () {
    editId = $(this).val();
    $('#edit-client-name').val($(this).data('name'));
    $('#edit-fd-code').html(buildFdOptions($(this).data('fd') || ''));
    $('#editModal').modal('show');
});

// Status toggle (delegated)
$(document).on('click', '#clientTbl .toggleStatusBtn', function () {
    const id       = $(this).val();
    const current  = parseInt($(this).data('active'));
    const newState = current ? 0 : 1;
    const label    = newState ? 'activate' : 'deactivate';

    Swal.fire({
        title: `${newState ? 'Activate' : 'Deactivate'} this client?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
    }).then(function(result) {
        if (!result.value) return;
        const fd = new FormData();
        fd.append('request',   'update-status');
        fd.append('id',        id);
        fd.append('is_active', newState);
        $.ajax({
            url: 'controller/ClientController.php',
            type: 'POST', data: fd, dataType: 'json',
            contentType: false, processData: false,
            success: function(r) {
                if (r.success == 1) { getClientList(); loadAlignment(); }
                else swal.fire({ icon:'error', title:'Error', text: r.error });
            }
        });
    });
});

// Delete button (delegated)
$(document).on('click', '#clientTbl .deleteBtn', function () {
    const id = $(this).val();
    Swal.fire({
        title: 'Delete this client?',
        html: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
    }).then(function(result) {
        if (!result.value) return;
        const fd = new FormData();
        fd.append('request', 'delete-client');
        fd.append('id', id);
        $.ajax({
            url: 'controller/ClientController.php',
            type: 'POST', data: fd, dataType: 'json',
            contentType: false, processData: false,
            success: function(r) {
                if (r.success == 1) { getClientList(); loadAlignment(); }
                else swal.fire({ icon:'error', title:'Error', text: r.error });
            }
        });
    });
});

// ── Add client ────────────────────────────────────────────────────────────
function addClient() {
    const name = $('#add-client-name').val().trim();
    if (!name) {
        swal.fire({ icon:'info', title:'Required', text:'Please enter a client name.' });
        return;
    }
    const fd = new FormData();
    fd.append('request',     'add-client');
    fd.append('client_name', name);

    $.ajax({
        url: 'controller/ClientController.php',
        type: 'POST', data: fd, dataType: 'json',
        contentType: false, processData: false,
        beforeSend: function() {
            $('#addBtn').html('Saving… <i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);
        },
        success: function(r) {
            $('#addModal').modal('hide');
            if (r.success == 1) {
                swal.fire({ icon:'success', title:'Client Added!' }).then(function() {
                    getClientList(); loadAlignment();
                });
            } else {
                swal.fire({ icon:'error', title:'Error', text: r.error });
                $('#addBtn').html('Add').prop('disabled', false);
            }
        },
        error: function() {
            swal.fire({ icon:'error', title:'Request failed' });
            $('#addBtn').html('Add').prop('disabled', false);
        }
    });
}

// ── Save client edit ──────────────────────────────────────────────────────
function saveChanges() {
    const name = $('#edit-client-name').val().trim();
    if (!name) {
        swal.fire({ icon:'info', title:'Required', text:'Please enter a client name.' });
        return;
    }
    const fd = new FormData();
    fd.append('request',     'update-client');
    fd.append('id',          editId);
    fd.append('client_name', name);
    fd.append('fd_code',     $('#edit-fd-code').val() || '');

    $.ajax({
        url: 'controller/ClientController.php',
        type: 'POST', data: fd, dataType: 'json',
        contentType: false, processData: false,
        beforeSend: function() {
            $('#saveBtn').html('Saving… <i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);
        },
        success: function(r) {
            $('#editModal').modal('hide');
            if (r.success == 1) {
                swal.fire({ icon:'success', title:'Saved!' }).then(function() {
                    getClientList(); loadAlignment();
                });
            } else {
                swal.fire({ icon:'error', title:'Error', text: r.error });
                $('#saveBtn').html('Save Changes').prop('disabled', false);
            }
        },
        error: function() {
            swal.fire({ icon:'error', title:'Request failed' });
            $('#saveBtn').html('Save Changes').prop('disabled', false);
        }
    });
}

// ── Client list DataTable ─────────────────────────────────────────────────
function getClientList() {
    const tableHtml = `<table id="clientTbl"
        class="dt-complex-header table table-bordered table-sm nowrap" style="width:100%">
        <thead><tr>
            <th>Action</th>
            <th>Status</th>
            <th>Client Name</th>
            <th>FD Code</th>
            <th>Active Employees</th>
        </tr></thead></table>`;

    const fd = new FormData();
    fd.append('request', 'get-client-list');

    $.ajax({
        url: 'controller/ClientController.php',
        type: 'POST', data: fd, dataType: 'json',
        contentType: false, processData: false,
        beforeSend: function() {
            $('#table_container').html('<center>Loading… <i class="fa fa-spinner fa-spin"></i></center>');
        },
        success: function(r) {
            $('#table_container').html(tableHtml);
            $('#clientTbl').DataTable().destroy();
            $('#clientTbl').DataTable({
                data: r.data || [],
                responsive: true,
                lengthChange: true,
                pageLength: 50,
                lengthMenu: [[25,50,100,-1],['25','50','100','All']],
                searching: true,
                ordering: true,
                order: [[2,'asc']],
                info: true,
                scrollX: true,
                columnDefs: [
                    { orderable: false, targets: 0 },
                    { orderable: false, targets: 3 },
                ]
            });
        },
        error: function() {
            $('#table_container').html('<center class="text-danger">Failed to load client list. Please refresh.</center>');
        }
    });
}

// ── Alignment tab ─────────────────────────────────────────────────────────
let alignmentLoaded = false;

function loadAlignment() {
    const fd = new FormData();
    fd.append('request', 'get-alignment');

    $.ajax({
        url: 'controller/ClientController.php',
        type: 'POST', data: fd, dataType: 'json',
        contentType: false, processData: false,
        beforeSend: function() {
            $('#alignment_container').html('<center class="py-5">Loading… <i class="fa fa-spinner fa-spin"></i></center>');
        },
        success: function(r) {
            if (!r.data) { $('#alignment_container').html('<p class="text-danger">Failed to load.</p>'); return; }

            const hrisClients = r.data;

            // Split: has canonical name (in Client Master) vs. not
            const withCanonical    = hrisClients.filter(function(c) { return c.fd_canonical && c.fd_canonical.trim() !== ''; });
            const withoutCanonical = hrisClients.filter(function(c) { return !c.fd_canonical || c.fd_canonical.trim() === ''; });

            // Group by canonical name for main table
            const canonicalMap = {}; // canonical → { fd_code, fd_group, members[] }
            withCanonical.forEach(function(c) {
                const key = c.fd_canonical.trim();
                if (!canonicalMap[key]) {
                    canonicalMap[key] = { fd_code: c.fd_code || '', fd_group: c.fd_group || '', members: [] };
                }
                canonicalMap[key].members.push(c);
            });

            const totalHris        = hrisClients.length;
            const withCanonicalCnt = withCanonical.length;
            const noCanonicalCnt   = withoutCanonical.length;

            let html = `
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-4">
                    <div class="card text-center border-primary">
                        <div class="card-body py-3">
                            <h4 class="mb-0 text-primary">${totalHris}</h4>
                            <small class="text-muted">Total HRIS Clients</small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card text-center border-success">
                        <div class="card-body py-3">
                            <h4 class="mb-0 text-success">${withCanonicalCnt}</h4>
                            <small class="text-muted">Aligned to Client Master</small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card text-center border-warning">
                        <div class="card-body py-3">
                            <h4 class="mb-0 text-warning">${noCanonicalCnt}</h4>
                            <small class="text-muted">No Canonical / Not in Master</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FD Alignment table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bx bx-check-circle text-success me-1"></i>FD Alignment — HRIS Clients Mapped to Client Master</h6>
                </div>
                <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Canonical Name</th>
                            <th>FD Code</th>
                            <th>HRIS Client(s)</th>
                            <th class="text-end">Active Employees</th>
                            <th>HRIS Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

            // Sort rows by canonical name alphabetically
            Object.keys(canonicalMap).sort().forEach(function(canonical) {
                const entry   = canonicalMap[canonical];
                const members = entry.members;
                const totalEmp   = members.reduce(function(s, m) { return s + parseInt(m.active_count); }, 0);
                const allActive  = members.every(function(m) { return parseInt(m.is_active) === 1; });
                const anyInactive = members.some(function(m) { return parseInt(m.is_active) === 0; });
                const statusBadge = allActive
                    ? '<span class="badge bg-success">All Active</span>'
                    : (anyInactive ? '<span class="badge bg-warning text-dark">Has Inactive</span>' : '');
                const names = members.map(function(m) {
                    const inactBadge = parseInt(m.is_active) === 0
                        ? ' <span class="badge bg-secondary" style="font-size:9px">Inactive</span>' : '';
                    return m.client_name + inactBadge;
                }).join('<br>');

                html += `<tr>
                    <td><strong>${canonical}</strong></td>
                    <td><span class="badge bg-info text-dark">${entry.fd_code || '<span class=\'text-muted\'>—</span>'}</span></td>
                    <td>${names}</td>
                    <td class="text-end fw-bold">${totalEmp.toLocaleString()}</td>
                    <td>${statusBadge}</td>
                </tr>`;
            });

            html += `</tbody></table></div></div>`;

            // Second table: HRIS clients with no canonical name (not in client master)
            if (withoutCanonical.length > 0) {
                html += `
                <div class="card border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0 text-warning">
                            <i class="bx bx-error me-1"></i>
                            HRIS Clients Without Canonical Name / Not in Client Master
                            <span class="badge bg-warning text-dark ms-2">${withoutCanonical.length}</span>
                        </h6>
                    </div>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>HRIS Client Name</th>
                                <th>FD Code</th>
                                <th class="text-end">Active Employees</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

                withoutCanonical.forEach(function(c) {
                    const statusBadge = parseInt(c.is_active) === 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                    const fdBadge = c.fd_code
                        ? `<span class="badge bg-secondary">${c.fd_code}</span>`
                        : '<span class="text-muted">—</span>';
                    html += `<tr>
                        <td>${c.client_name}</td>
                        <td>${fdBadge}</td>
                        <td class="text-end">${parseInt(c.active_count).toLocaleString()}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-xs btn-sm btn-outline-primary editFromAlignBtn"
                                data-id="${c.client_id}"
                                data-name="${c.client_name.replace(/'/g, "&#39;")}"
                                data-fd="${c.fd_code || ''}">
                                <i class="bx bx-link me-1"></i>Map FD Code
                            </button>
                        </td>
                    </tr>`;
                });

                html += `</tbody></table></div></div>`;
            }

            $('#alignment_container').html(html);
            alignmentLoaded = true;
        },
        error: function() {
            $('#alignment_container').html('<p class="text-danger">Failed to load alignment data.</p>');
        }
    });
}

// Map FD Code button in alignment tab opens edit modal
$(document).on('click', '.editFromAlignBtn', function() {
    editId = $(this).data('id');
    $('#edit-client-name').val($(this).data('name'));
    $('#edit-fd-code').html(buildFdOptions($(this).data('fd') || ''));
    $('#editModal').modal('show');
});

// Load alignment tab on first click
$('#tab-alignment-btn').on('shown.bs.tab', function() {
    loadAlignment();
});

// ── Sync from Master ──────────────────────────────────────────────────────
$('#syncMasterBtn').on('click', function() {
    Swal.fire({
        title: 'Sync from Client Master?',
        html: 'This will update <strong>fd_code, canonical name, group, and active/inactive status</strong> for all HRIS clients based on the Google Sheet master.<br><br>Unmatched clients will not be changed.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, sync now',
        confirmButtonColor: '#27489B',
    }).then(function(result) {
        if (!result.value) return;

        $('#syncMasterBtn').html('<i class="fa fa-spinner fa-spin me-1"></i> Syncing…').prop('disabled', true);

        var fd = new FormData();
        fd.append('request', 'sync');   // not used by SyncController but keeps pattern

        $.ajax({
            url: 'controller/SyncController.php',
            type: 'POST', data: fd,
            dataType: 'json', contentType: false, processData: false,
            success: function(r) {
                $('#syncMasterBtn').html('<i class="bx bx-refresh me-1"></i> Sync from Master').prop('disabled', false);

                if (r.success != 1) {
                    swal.fire({ icon: 'error', title: 'Sync failed', text: r.error });
                    return;
                }

                var unmatchedHtml = r.unmatched_count > 0
                    ? '<br><br><strong>' + r.unmatched_count + ' unmatched</strong> (no change):<br>'
                      + r.unmatched.map(function(u){ return '• ' + u; }).join('<br>')
                    : '<br><br>✅ All clients matched.';

                swal.fire({
                    icon: 'success',
                    title: 'Sync complete',
                    html: '<strong>' + r.updated + '</strong> clients updated from ' + r.master_entries + ' master entries.'
                        + '<br>Active: <strong>' + r.active_clients + '</strong> | Inactive: <strong>' + r.inactive_clients + '</strong>'
                        + unmatchedHtml,
                }).then(function() {
                    getClientList();
                    loadAlignment();
                });
            },
            error: function() {
                $('#syncMasterBtn').html('<i class="bx bx-refresh me-1"></i> Sync from Master').prop('disabled', false);
                swal.fire({ icon: 'error', title: 'Request failed', text: 'Could not reach the sync endpoint.' });
            }
        });
    });
});

// ── Init ──────────────────────────────────────────────────────────────────
$(document).ready(function() {
    getClientList();
});
