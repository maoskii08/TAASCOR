'use strict';
const accountingPath = $('#url_page').val();
const API = `/${accountingPath}/accounting-finance/controller/AccountingController.php`;
const fmt = v => parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});

function dtMake(id, data, cols) {
    const key = id + '_dt';
    if (window[key]) { window[key].destroy(); $('#'+id).empty(); }
    window[key] = $('#'+id).DataTable({
        data, columns: cols, responsive: true, pageLength: 25,
        dom: 'Bfrtip', buttons: ['excelHtml5','csvHtml5'],
        order: [[0,'asc']]
    });
}

function loadFilters() {
    $.post(API, {request:'get-client-filter'}, r => {
        ['#rem_client','#ann_client','#emp_client'].forEach(s => {
            $(s).find('option:not(:first)').remove();
            (r.data||[]).forEach(c => $(s).append(`<option value="${c.client_name}">${c.client_name}</option>`));
        });
        $('.select2').select2({theme:'bootstrap-5', width:'100%'});
    }, 'json');

    $.post(API, {request:'get-year-filter'}, r => {
        ['#ann_year','#emp_year'].forEach(s => {
            $(s).find('option:not(:first)').remove();
            (r.data||[]).forEach(y => $(s).append(`<option value="${y.yr}">${y.yr}</option>`));
        });
    }, 'json');
}

$('#rem_client').on('change', function(){
    $.post(API, {request:'get-pay-day-filter', client:$(this).val()}, r => {
        $('#rem_payday').find('option:not(:first)').remove();
        (r.data||[]).forEach(d =>
            $('#rem_payday').append(`<option value="${d.pay_day}" data-cutoff="${d.cut_off}">${d.pay_day} (${d.cut_off})</option>`)
        );
    }, 'json');
});

$('#btnRemSearch').on('click', function(){
    const sel = $('#rem_payday option:selected');
    $.post(API, {request:'get-remittance-summary', client:$('#rem_client').val(), pay_day:sel.val(), cut_off:sel.data('cutoff')||''}, r => {
        dtMake('remTable', r.data||[], [
            {title:'Client',              data:'client_name'},
            {title:'Cut-off',             data:'cut_off'},
            {title:'Pay Day',             data:'pay_day'},
            {title:'HC',                  data:'headcount',        className:'text-end'},
            {title:'SSS (Total)',          data:'grand_sss',        render:fmt, className:'text-end fw-bold'},
            {title:'EE SSS',              data:'ee_sss',           render:fmt, className:'text-end'},
            {title:'ER SSS',              data:'er_sss',           render:fmt, className:'text-end'},
            {title:'ER MPF',              data:'er_sss_mpf',       render:fmt, className:'text-end'},
            {title:'ER EC',               data:'er_sss_ec',        render:fmt, className:'text-end'},
            {title:'PhilHealth (Total)',   data:'grand_philhealth', render:fmt, className:'text-end fw-bold'},
            {title:'EE PH',               data:'ee_philhealth',    render:fmt, className:'text-end'},
            {title:'ER PH',               data:'er_philhealth',    render:fmt, className:'text-end'},
            {title:'Pag-IBIG (Total)',     data:'grand_pagibig',    render:fmt, className:'text-end fw-bold'},
            {title:'EE Pag-IBIG',         data:'ee_pagibig',       render:fmt, className:'text-end'},
            {title:'ER Pag-IBIG',         data:'er_pagibig',       render:fmt, className:'text-end'},
            {title:'Withholding Tax',      data:'grand_tax',        render:fmt, className:'text-end fw-bold'},
            {title:'Net Pay',             data:'total_net_pay',    render:fmt, className:'text-end'}
        ]);
    }, 'json');
});

$('#btnAnnSearch').on('click', function(){
    $.post(API, {request:'get-annual-summary', client:$('#ann_client').val(), year:$('#ann_year').val()}, r => {
        dtMake('annTable', r.data||[], [
            {title:'Client',          data:'client_name'},
            {title:'Year',            data:'year'},
            {title:'Headcount',       data:'headcount',         className:'text-end'},
            {title:'Total Gross',     data:'total_gross',       render:fmt, className:'text-end'},
            {title:'Total Taxable',   data:'total_taxable',     render:fmt, className:'text-end'},
            {title:'Total Tax',       data:'total_tax',         render:fmt, className:'text-end fw-bold'},
            {title:'Total SSS',       data:'grand_sss',         render:fmt, className:'text-end'},
            {title:'Total PhilHealth',data:'grand_philhealth',  render:fmt, className:'text-end'},
            {title:'Total Pag-IBIG',  data:'grand_pagibig',     render:fmt, className:'text-end'},
            {title:'Total Net Pay',   data:'total_net_pay',     render:fmt, className:'text-end'},
            {title:'13th Month',      data:'total_13th_month',  render:fmt, className:'text-end'}
        ]);
    }, 'json');
});

$('#btnEmpSearch').on('click', function(){
    $.post(API, {request:'get-employee-annual', client:$('#emp_client').val(), year:$('#emp_year').val()}, r => {
        dtMake('empTable', r.data||[], [
            {title:'ID',           data:'employee_id'},
            {title:'Employee',     data:'employee_name'},
            {title:'TIN',          data:'tin_number'},
            {title:'Client',       data:'client_name'},
            {title:'Year',         data:'year'},
            {title:'Gross',        data:'total_gross',       render:fmt, className:'text-end'},
            {title:'Taxable',      data:'total_taxable',     render:fmt, className:'text-end'},
            {title:'Tax',          data:'total_tax',         render:fmt, className:'text-end fw-bold'},
            {title:'SSS',          data:'total_sss',         render:fmt, className:'text-end'},
            {title:'PhilHealth',   data:'total_philhealth',  render:fmt, className:'text-end'},
            {title:'Pag-IBIG',     data:'total_pagibig',     render:fmt, className:'text-end'},
            {title:'Net Pay',      data:'total_net',         render:fmt, className:'text-end'},
            {title:'13th Month',   data:'total_13th',        render:fmt, className:'text-end'}
        ]);
    }, 'json');
});

$(document).ready(function(){
    $('.select2').select2({theme:'bootstrap-5', width:'100%'});
    loadFilters();
});
