/* ─── billing.js ─────────────────────────────────────────────────────────── */
'use strict';

const billingPath   = $('#url_page').val();
let billingTable = null;
let detailTable  = null;
let currentRow   = {};

/* ── Helpers ─────────────────────────────────────────────────────────────── */
const fmt  = v => parseFloat(v || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const fmtN = v => parseInt(v  || 0).toLocaleString('en-PH');

/* ── Populate filters ────────────────────────────────────────────────────── */
function loadClientFilter() {
    $.post(`/${billingPath}/billing/controller/BillingController.php`,
        { request: 'get-client-filter' },
        function(res) {
            if (!res.data) return;
            const $sel = $('#filter_client').empty().append('<option value="">All Clients</option>');
            res.data.forEach(r => $sel.append(`<option value="${r.client_name}">${r.client_name}</option>`));
            $sel.trigger('change');
        }, 'json'
    );
}

function loadPayDayFilter(client) {
    $.post(`/${billingPath}/billing/controller/BillingController.php`,
        { request: 'get-pay-day-filter', client: client || '' },
        function(res) {
            const $sel = $('#filter_payday').empty().append('<option value="">All Pay Days</option>');
            if (res.data) {
                res.data.forEach(r => {
                    $sel.append(`<option value="${r.pay_day}" data-cutoff="${r.cut_off}">${r.pay_day} (${r.cut_off})</option>`);
                });
            }
        }, 'json'
    );
}

/* ── Main billing summary table ──────────────────────────────────────────── */
function loadBillingTable() {
    const client  = $('#filter_client').val();
    const selOpt  = $('#filter_payday option:selected');
    const pay_day = selOpt.val();
    const cut_off = selOpt.data('cutoff') || '';

    $.post(`/${billingPath}/billing/controller/BillingController.php`,
        { request: 'get-billing-summary', client, pay_day, cut_off },
        function(res) {
            if (billingTable) { billingTable.destroy(); $('#billingTable').empty(); }

            if (!res.data || !res.data.length) {
                $('#billingTable').closest('.card-body').html(
                    '<p class="text-center text-muted py-4">No billing data found for the selected filters.</p>'
                );
                return;
            }

            // Rebuild thead
            $('#billingTable').html(`
                <thead><tr>
                    <th>Client</th><th>Cut-off</th><th>Pay Day</th><th>HC</th>
                    <th>Basic Pay</th><th>OT</th><th>Additions</th><th>Gross</th>
                    <th>Net Pay</th><th>Employer Contribs</th><th>13th Mo.</th><th>Action</th>
                </tr></thead><tbody></tbody>
            `);

            billingTable = $('#billingTable').DataTable({
                data: res.data,
                columns: [
                    { data: 'client_name' },
                    { data: 'cut_off' },
                    { data: 'pay_day' },
                    { data: 'headcount', render: fmtN, className: 'text-end' },
                    { data: 'basic_pay',           render: fmt, className: 'text-end' },
                    { data: 'total_ot',            render: fmt, className: 'text-end' },
                    { data: 'total_additional',    render: fmt, className: 'text-end' },
                    { data: 'gross_income',        render: fmt, className: 'text-end' },
                    { data: 'net_pay',             render: fmt, className: 'text-end' },
                    { data: 'total_employer_contributions', render: fmt, className: 'text-end' },
                    { data: 'annual_bonus',        render: fmt, className: 'text-end' },
                    {
                        data: null,
                        orderable: false,
                        render: (d, t, row) =>
                            `<button class="btn btn-sm btn-primary btn-detail"
                                data-client="${row.client_name}"
                                data-payday="${row.pay_day}"
                                data-cutoff="${row.cut_off}">
                                <i class="bx bx-list-ul"></i> Details
                            </button>
                            <button class="btn btn-sm btn-success btn-pdf"
                                data-client="${row.client_name}"
                                data-payday="${row.pay_day}"
                                data-cutoff="${row.cut_off}">
                                <i class="bx bx-file-pdf"></i> Invoice
                            </button>`
                    }
                ],
                order: [[2, 'desc']],
                responsive: true,
                dom: 'Bfrtip',
                buttons: ['excelHtml5', 'csvHtml5'],
                pageLength: 25
            });
        }, 'json'
    );
}

/* ── Detail modal (per-employee breakdown) ───────────────────────────────── */
function loadDetailModal(client, pay_day, cut_off) {
    $('#modalClient').text(client);
    $('#modalPayDay').text(`${pay_day} (${cut_off})`);
    currentRow = { client, pay_day, cut_off };

    $.post(`/${billingPath}/billing/controller/BillingController.php`,
        { request: 'get-billing-detail', client, pay_day, cut_off },
        function(res) {
            if (detailTable) { detailTable.destroy(); $('#detailTable').empty(); }

            $('#detailTable').html(`
                <thead><tr>
                    <th>ID</th><th>Employee</th><th>Daily Rate</th><th>Days</th>
                    <th>Basic</th><th>Gross</th><th>Tax</th>
                    <th>EE SSS</th><th>EE PH</th><th>EE Pag-IBIG</th>
                    <th>Loan</th><th>Net Pay</th>
                    <th>ER SSS</th><th>ER PH</th><th>ER Pag-IBIG</th>
                </tr></thead><tbody></tbody>
            `);

            detailTable = $('#detailTable').DataTable({
                data: res.data || [],
                columns: [
                    { data: 'employee_id' },
                    { data: 'employee_name' },
                    { data: 'daily_salary',      render: fmt, className: 'text-end' },
                    { data: 'daily_worked',      className: 'text-end' },
                    { data: 'basic_pay',         render: fmt, className: 'text-end' },
                    { data: 'gross_income',      render: fmt, className: 'text-end' },
                    { data: 'employee_tax',      render: fmt, className: 'text-end' },
                    { data: 'employee_sss',      render: fmt, className: 'text-end' },
                    { data: 'employee_philhealth', render: fmt, className: 'text-end' },
                    { data: 'employee_pagibig',  render: fmt, className: 'text-end' },
                    { data: 'employee_loan',     render: fmt, className: 'text-end' },
                    { data: 'net_pay',           render: fmt, className: 'text-end fw-bold' },
                    { data: 'employer_sss',      render: fmt, className: 'text-end' },
                    { data: 'employer_philhealth', render: fmt, className: 'text-end' },
                    { data: 'employer_pagibig',  render: fmt, className: 'text-end' },
                ],
                responsive: true,
                dom: 'Bfrtip',
                buttons: ['excelHtml5'],
                pageLength: 50,
                scrollX: true
            });

            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }, 'json'
    );
}

/* ── Events ──────────────────────────────────────────────────────────────── */
$(document).ready(function () {
    loadClientFilter();

    $('#filter_client').on('change', function () {
        loadPayDayFilter($(this).val());
    });

    $('#btnSearch').on('click', loadBillingTable);

    $('#billingTable').on('click', '.btn-detail', function () {
        loadDetailModal(
            $(this).data('client'),
            $(this).data('payday'),
            $(this).data('cutoff')
        );
    });

    $('#billingTable').on('click', '.btn-pdf', function () {
        const c = $(this).data('client');
        const p = $(this).data('payday');
        const o = $(this).data('cutoff');
        window.open(`/${billingPath}/billing/invoice.php?client=${encodeURIComponent(c)}&pay_day=${encodeURIComponent(p)}&cut_off=${encodeURIComponent(o)}`, '_blank');
    });
});
