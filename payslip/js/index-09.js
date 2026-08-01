let fileName = "DTR";
let access_level = $('#access_level').val();
let payrollDetails = [];
let bankData = [];
let pnbTotalAmount = 0;
let pnbTotalCount = 0;
let pnbPayDay = '';
let payslipLayout = 'default';
let payrollReleaseGate = null;
let payrollRunId = null;
let payrollLocked = false;

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('payslipBtn').addEventListener("click", downloadPayslip);
document.getElementById('postBtn').addEventListener("click", postPayroll);
document.getElementById('filterBtn').addEventListener("click", getPayrollSummary);
document.getElementById('payrollRun').addEventListener("change", function () {
    payrollRunId = Number($(this).val() || 0) || null;
    payrollReleaseGate = null;
    $('#postBtn')
        .prop('disabled', true)
        .attr('title', 'The selected payroll run must be verified again.');
    renderPayrollReleaseGateStatus(null, false);
    if (payrollRunId) {
        getPayrollSummary();
    }
});

$( document ).ready(function() {
    getClientFilter();
});

$("#client").change(function() {
    let client_selected = $(this).val();
    if(client_selected){
        getPayDay(client_selected);
    }    
});

function parsePayslipRequestError(xhr, fallbackMessage) {
    let payload = xhr && xhr.responseJSON ? xhr.responseJSON : null;
    if (!payload && xhr && xhr.responseText) {
        try {
            payload = JSON.parse(xhr.responseText);
        } catch (error) {
            payload = null;
        }
    }

    let status = Number(xhr && xhr.status ? xhr.status : 0);
    let code = payload && (payload.error_code || payload.code)
        ? String(payload.error_code || payload.code)
        : (status === 401 ? 'ACCESS_REQUIRED' : (status === 403 ? 'REQUEST_REFRESH_REQUIRED' : 'CONNECTION_FAILED'));

    return {
        error_code: code,
        error: payload && (payload.error || payload.message)
            ? String(payload.error || payload.message)
            : fallbackMessage
    };
}

function clearPayslipClientLoadError() {
    $('#payslipClientLoadState')
        .hide()
        .empty()
        .removeAttr('data-hris-actionable data-hris-error-code');
    $('#client').removeAttr('aria-invalid aria-describedby');
}

function focusPayslipClientFilter() {
    let visibleSelection = $('#client').next('.select2').find('.select2-selection').get(0);
    let target = visibleSelection || document.getElementById('client');
    if (target && typeof target.focus === 'function') {
        target.focus();
    }
}

function showPayslipClientLoadError(input) {
    let error = input && input.error
        ? input
        : {error_code: 'CONNECTION_FAILED', error: 'Unable to load the client list.'};
    let $client = $('#client');
    let host = document.getElementById('payslipClientLoadState');

    $client
        .prop('disabled', false)
        .empty()
        .append(new Option('Client list unavailable - retry below', '', true, true))
        .attr('aria-invalid', 'true')
        .attr('aria-describedby', 'payslipClientLoadState')
        .trigger('change.select2');

    if (host && window.HrisActionableErrors) {
        window.HrisActionableErrors.render(host, error, {
            title: 'The Payslip client list could not be loaded',
            resolution: 'Retry the client list. If it still fails, keep the error reference and contact the application administrator.',
            secondaryLabel: 'Retry client list',
            onSecondary: function () {
                clearPayslipClientLoadError();
                getClientFilter();
            }
        });
    } else if (host) {
        host.className = 'alert alert-danger';
        host.textContent = error.error;
        host.style.display = '';
    }

    if (host && typeof host.scrollIntoView === 'function') {
        host.scrollIntoView({block: 'nearest'});
    }
    focusPayslipClientFilter();
}

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#payType').val(null).trigger('change');
    $('#bankName').val(null).trigger('change');
    $('#clientLocation').val(null).trigger('change');
    $('#tblDiv').hide();
    $('#tblDiv2').hide();
    payrollDetails = [];
    payslipLayout = 'default';
    payrollReleaseGate = null;
    payrollRunId = null;
    payrollLocked = false;
    $('#payrollRun').html('<option value="">Select the approved sealed run</option>');
    $('#smartRunSelectorRow').hide();
    $('#payrollReleaseGateStatus').hide().empty();
    $('#postBtn')
        .prop('disabled', true)
        .attr('title', 'Generate payroll and pass the release controls before posting.');
}

function downloadPayslip() {
    if(payrollDetails.length > 0){
        if (payrollReleaseGate && payrollReleaseGate.mode === 'smart_run'
            && !payrollPreviewRunIsSelected(payrollReleaseGate)) {
            swal.fire({
                icon: 'warning',
                title: 'Payslip preview is blocked',
                text: 'Select and verify the exact governed payroll run before opening its payslips.'
            });
            return false;
        }
        let client_name = payrollDetails[0][0];
        let cut_off = payrollDetails[0][1];
        let pay_day = payrollDetails[0][2];
        let pay_type = payrollDetails[0][5];
        let bank_name = payrollDetails[0][6];
        let location = payrollDetails[0][7];
        let params = new URLSearchParams({
            cn: client_name,
            co: cut_off,
            pd: pay_day,
            pt: pay_type,
            bn: bank_name,
            cl: location,
            layout: payslipLayout
        });
        if (payrollRunId) {
            params.set('run_id', String(payrollRunId));
        }
        let url;
        if (payrollLocked && payrollRunId && payrollReleaseGate && payrollReleaseGate.mode === 'smart_run') {
            url = "payslip-sealed.php?run_id=" + encodeURIComponent(String(payrollRunId));
        } else {
            if (payrollReleaseGate
                && (payrollReleaseGate.mode === 'smart_run'
                    || payrollReleaseGate.mode === 'legacy_preview')) {
                params.set('preview', '1');
            }
            url = "payslip2.php?" + params.toString();
        }
        window.open(url, '_blank')
    }else{
        swal.fire({
            icon: 'info',   
            title: 'Generate Payroll!',       
            text: 'Please generate payroll first'
        });
        return false;
    }
}


function postPayroll(){
    let client = $("#client").val();
    let pay_day = $("#payDay").val();

    if(client == null || pay_day == null){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please select client and pay day'
        });

        $('#tblDiv').hide();
        $('#tblDiv2').hide();
        payrollDetails = [];

        return false;
    }

    if(!payrollReleaseGateIsReady(payrollReleaseGate)){
        swal.fire({
            icon: 'warning',
            title: 'Payroll release is blocked',
            text: payrollReleaseGateMessage(payrollReleaseGate)
        });
        return false;
    }

    Swal.fire({
        title: 'POST PAYROLL?', 
        html: client + ' - ' + formatDate(pay_day) + '. Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        if (result.value) {
            let formdata = new FormData();
            formdata.append("request", "post-payroll");
            formdata.append("client", client);
            formdata.append("pay_day", pay_day);
            if (payrollRunId) {
                formdata.append("run_id", String(payrollRunId));
            }

            $.ajax({
                url: 'controller/PayslipController.php',
                type: 'POST',
                data: formdata,
                dataType: 'json',
                processing: true, 
                contentType: false,
                processData: false,
                beforeSend: function( xhr ) {
                },
                success: function (response) { 
                    if(response.success == 1){
                        payrollLocked = true;
                        payrollReleaseGate = response.release_gate || payrollReleaseGate;
                        $('#postBtn').prop('disabled', true).attr('title', 'Payroll is posted and locked.');
                        swal.fire({
                            icon: 'success',   
                            title: 'Successfully Post Payroll!',  
                            html: 'The payroll for '+ client + ' - ' + formatDate(pay_day) + ' is now locked.'            
                        });

                    }else{
                        swal.fire({
                            icon: 'error',   
                            title: 'Something went wrong!',                 
                            text: "Error Message: " + response.error + ""               
                        }).then(function (result) {
                            window.location.reload()
                        });
                    }
                },
                error: function(response) { // if error occured
                    swal.fire({
                        icon: 'error',   
                        title: 'Something went wrong!',                 
                        text: "Error Message: " + response.error + ""               
                    }).then(function (result) {
                        window.location.reload()
                    });
                }
            });
        } 
    });
}

$(document).on("click","#dtrTbl #empPayslip",function() {
    let employee_id = $(this).val();

    if (payrollReleaseGate && payrollReleaseGate.mode === 'smart_run'
        && !payrollPreviewRunIsSelected(payrollReleaseGate)) {
        swal.fire({
            icon: 'warning',
            title: 'Payslip preview is blocked',
            text: 'Select and verify the exact governed payroll run before opening this payslip.'
        });
        return false;
    }

    let client_name = payrollDetails[0][0];
    let cut_off = payrollDetails[0][1];
    let pay_day = payrollDetails[0][2];
    let params = new URLSearchParams({
        cn: client_name,
        co: cut_off,
        pd: pay_day,
        ei: employee_id,
        layout: payslipLayout
    });
    if (payrollRunId) {
        params.set('run_id', String(payrollRunId));
    }
    let url;
    if (payrollLocked && payrollRunId && payrollReleaseGate && payrollReleaseGate.mode === 'smart_run') {
        url = "payslip-sealed.php?run_id=" + encodeURIComponent(String(payrollRunId))
            + "&employee_id=" + encodeURIComponent(String(employee_id));
    } else {
        if (payrollReleaseGate
            && (payrollReleaseGate.mode === 'smart_run'
                || payrollReleaseGate.mode === 'legacy_preview')) {
            params.set('preview', '1');
        }
        url = "payslip2.php?" + params.toString();
    }
    window.open(url, '_blank')
});

function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/PayslipController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            clearPayslipClientLoadError();
            $('#client')
                .prop('disabled', true)
                .empty()
                .append(new Option('Loading clients...', '', true, true))
                .trigger('change.select2');
        },
        success: function (response) {
            if (!response || Number(response.success) !== 1 || !Array.isArray(response.data)) {
                showPayslipClientLoadError(response || {
                    error_code: 'CONNECTION_FAILED',
                    error: 'The client list response was incomplete.'
                });
                return;
            }

            clearPayslipClientLoadError();
            $('#client').empty().append(`<option value="" disabled selected>Select Client</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(option1);
            });

            $('#client').prop('disabled', false).trigger('change.select2');

            getPayType();
        },
        error: function (xhr) {
            showPayslipClientLoadError(parsePayslipRequestError(
                xhr,
                'Unable to load the client list. Check your connection and retry.'
            ));
        }
    });
}


function getPayType(){

    let formdata = new FormData();
    formdata.append("request", "get-pay-type");

    $.ajax({
        url: 'controller/PayslipController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#payType').attr('disabled',true);
            $('#payType').empty();
        },
        success: function (response) { 
            $('#payType').append(`<option value="" disabled selected>Select Pay Type</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.pay_type, option.pay_type, false, false);
                $('#payType').append(option1);
            });

            $('#payType').trigger('change'); 
            $('#payType').attr('disabled',false);

            getBankName();
        }
    });
}

function getBankName(){

    let formdata = new FormData();
    formdata.append("request", "get-bank-name");

    $.ajax({
        url: 'controller/PayslipController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#bankName').attr('disabled',true);
            $('#bankName').empty();
        },
        success: function (response) { 
            $('#bankName').append(`<option value="" disabled selected>Select Bank Name</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.bank_name, option.bank_name, false, false);
                $('#bankName').append(option1);
            });

            $('#bankName').trigger('change'); 
            $('#bankName').attr('disabled',false);
        }
    });
}


function getPayDay(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-pay-day");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/PayslipController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#payDay').attr('disabled',true);
            $('#payDay').empty();
        },
        success: function (response) { 
            $('#payDay').append(`<option value="" disabled selected>Select Pay Day</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(formatDate(option.pay_date), option.pay_date, false, false);
                $(dataOption).attr("data-sd", option.start_date).attr("data-ed", option.end_date).attr("data-co", option.cut_off);
                $('#payDay').append(dataOption);
            });

            $('#payDay').trigger('change'); 
            $('#payDay').attr('disabled',false);

            getBranch(client_selected);
        }
    });
}

function getBranch(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-branch");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/PayslipController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#branch').attr('disabled',true);
            $('#branch').empty();
        },
        success: function (response) { 
            $('#branch').append(`<option value="" disabled selected>Select Branch</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(option.branch_name, option.branch_id, false, false);
                $('#branch').append(dataOption);
            });

            $('#branch').trigger('change'); 
            $('#branch').attr('disabled',false);

            getClientLocation(client_selected);
        }
    });
}

function getClientLocation(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-client-location");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/PayslipController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#clientLocation').attr('disabled',true);
            $('#clientLocation').empty();
        },
        success: function (response) { 
            $('#clientLocation').append(`<option value="" disabled selected>Select Client Location</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(option.location_name, option.location_id, false, false);
                $('#clientLocation').append(dataOption);
            });

            $('#clientLocation').trigger('change'); 
            $('#clientLocation').attr('disabled',false);
        }
    });
}


function getPayrollSummary() {    
    let client = $("#client").val();
    let pay_type = $("#payType").val();
    let bank_name = $("#bankName").val();
    let branch = $("#branch").val();
    let client_location = $("#clientLocation").val();
    let pay_day = $("#payDay").val();
    let start_date = $("#payDay option:selected").data("sd");
    let end_date = $("#payDay option:selected").data("ed");
    let cut_off = $("#payDay option:selected").data("co");

    if(client == null || pay_day == null){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please select client and pay day'
        });

        $('#tblDiv').hide();
        $('#tblDiv2').hide();
        payrollDetails = [];

        return false;
    }
    let formdata = new FormData();
    formdata.append("request", "get-payroll-summary");
    formdata.append("client", client);
    formdata.append("pay_type", pay_type);
    formdata.append("pay_day", pay_day);
    formdata.append("start_date", start_date);
    formdata.append("end_date", end_date);
    formdata.append("cut_off", cut_off);
    formdata.append("bank_name", bank_name);
    formdata.append("client_location", client_location);
    formdata.append("branch", branch);
    if (payrollRunId) {
        formdata.append("run_id", String(payrollRunId));
    }

    $.ajax({
        url: 'controller/PayslipController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $("#tblDiv").show();
            $("#tblDiv2").show();
            $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
            $('#table_container2').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
        },
        success: function(response){
            if(response.success == 1){
                fileName = "Payregister";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }

                if(pay_type != null && pay_type != ''){
                    fileName += "_" + pay_type;
                }
                
                let buttonArray = [
                                    { 
                                        extend: 'excel', 
                                        title: null,
                                        className: 'btn btn-sm btn-outline-primary',
                                        text: '<i class="bx bx-download"></i> Download',
                                        filename: fileName,
                                        exportOptions: {
                                            columns: function (index, data, node) {
                                                // Exclude the first column (index 0)
                                                return index !== 0;
                                            },
                                            format: {
                                                header: function (data, column) {
                                                    return data; 
                                                }
                                            },
                                        }
                                    }
                                ]
                if(bank_name != null && response.locked){
                    if(bank_name == 'METROBANK'){
                        metroBankTable();
                        buttonArray.push({
                            text: 'Download Bank Transfer',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                downloadMetroBank(bankData, fileName);            
                            }
                        });
                    }else if(bank_name == 'Gcash'){
                        gcashTable();
                        buttonArray.push({
                            text: 'Download Bank Transfer',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                downloadGcash(bankData, fileName);            
                            }
                        });
                    }else if(bank_name == 'Philippine National Bank'){
                        pnbTable();
                        buttonArray.push({
                            text: 'Download Bank Transfer',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                downloadPNB(bankData, fileName, pnbPayDay, pnbTotalAmount, pnbTotalCount);            
                            }
                        });
                    }    
                }

                payslipLayout = response.payslip_layout || 'default';
                payrollLocked = Boolean(response.locked);
                payrollReleaseGate = response.release_gate || null;
                renderPayrollRunCandidates(payrollReleaseGate);
                let releaseReady = !response.locked && payrollReleaseGateIsReady(payrollReleaseGate);
                $('#postBtn').prop('disabled', !releaseReady);
                if(!releaseReady){
                    $('#postBtn').attr(
                        'title',
                        response.locked
                            ? 'Payroll is posted and locked.'
                            : payrollReleaseGateMessage(payrollReleaseGate)
                    );
                }else{
                    $('#postBtn').attr('title', 'All release controls passed for the selected authoritative run.');
                }
                renderPayrollReleaseGateStatus(payrollReleaseGate, Boolean(response.locked));
                payrollDetails= [];
                payrollDetails.push([client,cut_off,pay_day,start_date,end_date,pay_type,bank_name,client_location]);
                var table = `<table id="dtrTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                                style="width:100%">
                                <thead>
                                <tr></tr>
                                </thead>
                            </table>`;

                $('#table_container').html('');
                $('#table_container').html(table);

                $('#dtrTbl').DataTable().destroy();
                $('#dtrTbl').DataTable({
                    data: response.data,
                    columns: response.columns,
                    responsive: true,
                    lengthChange: true,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    scrollX: true,
                    layout: {
                    topStart: 'buttons',
                    },
                    dom: '<"dt-top-container"<l><"dt-center-in-div"B><f>r>t>ip>',
                    buttons: buttonArray
                });

                var table = `<div class="alert alert-primary" role="alert">
                                Client: <a class="alert-link me-3">${client}</a>
                                Cut Off: <a class="alert-link me-3">${formatDate(start_date)} to ${formatDate(end_date)}</a>
                                Pay Day: <a class="alert-link">${formatDate(pay_day)}</a>
                              </div>
                            <table id="clientTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                                style="width:100%">
                                <thead>
                                <tr></tr>
                                </thead>
                            </table>`;

                $('#table_container2').html('');
                $('#table_container2').html(table);

                fileName = "Payroll_Balancing";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }
                $('#clientTbl').DataTable().destroy();
                $('#clientTbl').DataTable({
                    data: response.data2,
                    columns: response.columns2,
                    responsive: true,
                    lengthChange: false,
                    paging: false,
                    searching: false,
                    ordering: false,
                    info: false,
                    scrollX: true,
                    layout: {
                    topStart: 'buttons',
                    },
                    buttons: [
                        { 
                            extend: 'excel', 
                            title: null,
                            className: 'btn btn-sm btn-outline-secondary',
                            text: '<i class="bx bx-download"></i> Download',
                            filename: fileName,
                            exportOptions: {
                                format: {
                                    header: function (data, column) {
                                        return data; 
                                    }
                                },
                                // columns: ':not(.notexport)'
                            },
                        }
                    ]
                });
            }else if(response.success == 2){
                swal.fire({
                    icon: 'warning',   
                    title: 'No Pay Day Set',                 
                    text: "Please go to Pay Day Maintenance and set a cutoff date"               
                }).then(function (result) {
                    window.location.reload()
                });
            }else{
                swal.fire({
                    icon: 'error',   
                    title: 'Something went wrong!',                 
                    text: "Error Message: " + response.error + ""               
                }).then(function (result) {
                    window.location.reload()
                });
            }
        },
        error: function(response) { // if error occured
            swal.fire({
                icon: 'error',   
                title: 'Something went wrong!',                 
                text: "Error Message: " + response.error + ""               
            }).then(function (result) {
                window.location.reload()
            });
        }
    });
}

function renderPayrollRunCandidates(gate) {
    if (!gate || gate.mode !== 'smart_run') {
        payrollRunId = null;
        $('#smartRunSelectorRow').hide();
        return;
    }
    let candidates = Array.isArray(gate.candidates) ? gate.candidates : [];
    if (gate.authoritative_run_id) {
        payrollRunId = Number(gate.authoritative_run_id);
    }
    if (candidates.length > 0) {
        let current = payrollRunId ? String(payrollRunId) : '';
        let options = '<option value="">Select the approved sealed run</option>';
        candidates.forEach(function (run) {
            let value = String(run.id);
            let selected = value === current ? ' selected' : '';
            let label = [run.run_uid, run.status, run.release_status].filter(Boolean).join(' · ');
            options += '<option value="' + value + '"' + selected + '>' + $('<div>').text(label).html() + '</option>';
        });
        $('#payrollRun').html(options);
    }
    $('#smartRunSelectorRow').show();
}

function payrollReleaseGateIsReady(gate) {
    return Boolean(
        gate
        && gate.success == 1
        && gate.gate_status === 'ready'
        && gate.mode === 'smart_run'
        && gate.release_attempt === true
        && gate.release_actor_verified === true
        && String(gate.release_actor || '').trim() !== ''
        && Number(gate.authoritative_run_id || 0) > 0
        && Number(gate.authoritative_run_id) === Number(payrollRunId || 0)
    );
}

function payrollPreviewRunIsSelected(gate) {
    return Boolean(
        gate
        && gate.mode === 'smart_run'
        && Number(gate.authoritative_run_id || 0) > 0
        && Number(gate.authoritative_run_id) === Number(payrollRunId || 0)
    );
}

function payrollReleaseGateMessage(gate) {
    if (!gate) {
        return 'Release controls could not be verified. Refresh the payroll scope and try again.';
    }
    let labels = {
        smart_run_schema_required: 'Smart payroll release controls are not installed.',
        smart_run_schema_incomplete: 'Smart payroll release controls are incomplete.',
        smart_payroll_enrollment_required: 'This client is not enrolled in the governed payroll workflow.',
        payroll_scope_empty: 'The selected client and pay date contain no payroll rows.',
        authoritative_run_selection_required: 'Select an approved, sealed payroll run.',
        authoritative_smart_run_missing: 'The selected authoritative payroll run is unavailable.',
        release_actor_missing: 'The authenticated release actor could not be verified.',
        owner_approval_missing: 'Record an authorized Payroll or Admin approval before releasing payroll.',
        smart_run_gate_check_failed: 'The release controls could not be verified.'
    };
    let blockers = Array.isArray(gate.blocking_reasons) ? gate.blocking_reasons : [];
    if (!blockers.length) {
        return gate.error || 'Identity, calculation, reconciliation, payslip, and owner approval controls must pass.';
    }
    return blockers.map(function (reason) {
        if (labels[reason]) {
            return labels[reason];
        }
        let code = String(reason).replace(/^run:[^:]+:/, '');
        if (labels[code]) {
            return labels[code];
        }
        return code.replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }).join(' ');
}

function renderPayrollReleaseGateStatus(gate, locked) {
    let ready = payrollReleaseGateIsReady(gate);
    let status = $('#payrollReleaseGateStatus');
    status
        .removeClass('alert-warning alert-success alert-secondary')
        .addClass(locked ? 'alert-secondary' : (ready ? 'alert-success' : 'alert-warning'));

    if (locked) {
        status.text('Posted and locked. Payroll data can no longer be changed for this scope.').show();
        return;
    }
    if (gate && gate.mode === 'legacy_preview' && gate.preview_allowed === true) {
        status
            .text('Posting blocked. Payslips may be generated only as UNVERIFIED LEGACY PREVIEW - NON-DISTRIBUTABLE.')
            .show();
        return;
    }
    if (ready) {
        status.text('Ready to post. The authoritative run and all release controls passed.').show();
        return;
    }
    status.text('Posting blocked: ' + payrollReleaseGateMessage(gate)).show();
}


function metroBankTable() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let cut_off = $("#payDay option:selected").data("co");

    let formdata = new FormData();
    formdata.append("request", "get-metro-bank");
    formdata.append("client", client);
    formdata.append("pay_day", pay_day);
    formdata.append("cut_off", cut_off);

    $.ajax({
        url: 'controller/PayslipController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function(response){
            fileName = "METROBANK";
            if(client != null && client != ''){
                fileName += "_" + client;
            }

            if(pay_day != null && pay_day != ''){
                fileName += "_" + pay_day;
            }

            bankData = response.data;
        },
        error: function(response) { // if error occured
            swal.fire({
                icon: 'error',   
                title: 'Something went wrong!',                 
                text: "Error Message: " + response.error + ""               
            }).then(function (result) {
                window.location.reload()
            });
        }
        
    });
}


function gcashTable() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let cut_off = $("#payDay option:selected").data("co");

        let formdata = new FormData();
        formdata.append("request", "get-gcash");
        formdata.append("client", client);
        formdata.append("pay_day", pay_day);
        formdata.append("cut_off", cut_off);

        $.ajax({
            url: 'controller/PayslipController.php',
            data: formdata,
            type: 'POST',
            contentType: false,
            processData: false,
            beforeSend: function( xhr ) {
            },
            success: function(response){
                fileName = "Gcash";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }

                bankData = response.data;
            },
            error: function(response) { // if error occured
                swal.fire({
                    icon: 'error',   
                    title: 'Something went wrong!',                 
                    text: "Error Message: " + response.error + ""               
                }).then(function (result) {
                    window.location.reload()
                });
            }
            
        });
}

function pnbTable() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let cut_off = $("#payDay option:selected").data("co");

    let formdata = new FormData();
    formdata.append("request", "get-pnb");
    formdata.append("client", client);
    formdata.append("pay_day", pay_day);
    formdata.append("cut_off", cut_off);

    $.ajax({
        url: 'controller/PayslipController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function(response){
            fileName = "PNB";
            if(client != null && client != ''){
                fileName += "_" + client;
            }

            if(pay_day != null && pay_day != ''){
                fileName += "_" + pay_day;
            }

            bankData = response.data;
            pnbTotalCount = response.data.length;
            pnbTotalAmount = response.total_amount;
            pnbPayDay = pay_day;
        },
        error: function(response) { // if error occured
            swal.fire({
                icon: 'error',   
                title: 'Something went wrong!',                 
                text: "Error Message: " + response.error + ""               
            }).then(function (result) {
                window.location.reload()
            });
        }
        
    });
}

function downloadBank() {
    let table = $('#bankTbl').DataTable();
    table.button(0).trigger();
}

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}


function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}


function downloadGcash(data, fileName) {
    let wb = XLSX.utils.book_new();

    // Headers
    let headers = [["Gcash Mobile Number", "Full Name", "Amount", "Remarks"]];

    let formattedData = data.map(item => [
        item.atm_number || "",   
        item.full_name || "",       
        item.net_pay || "0.00",     
        item.remarks || ""          
    ]);

    let finalData = headers.concat(formattedData);
    let ws = XLSX.utils.aoa_to_sheet(finalData);

    // Set column widths
    ws["!cols"] = [
        { wch: 20 },  
        { wch: 30 },  
        { wch: 15 },  
        { wch: 30 }   
    ];

    // Protect worksheet with password
    ws["!protect"] = { password: "1234" };

    XLSX.utils.book_append_sheet(wb, ws, "Bank Transfer");

    // Convert workbook to binary format
    let wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });

    function s2ab(s) {
        let buf = new ArrayBuffer(s.length);
        let view = new Uint8Array(buf);
        for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
    }

    // Trigger file download
    saveAs(new Blob([s2ab(wbout)], { type: "application/octet-stream" }), fileName + ".xlsx");
}



function downloadMetroBank(data, fileName) {
    let wb = XLSX.utils.book_new();

    // Headers
    let headers = [["Last Name", "First Name", "Middle Name", "Employee Account Number", "Amount"]];

    let formattedData = data.map(item => [
        item.last_name || "",   
        item.first_name || "",       
        item.middle_name || "",     
        item.atm_number || "",
        item.net_pay || "0.00"            
    ]);

    let finalData = headers.concat(formattedData);
    let ws = XLSX.utils.aoa_to_sheet(finalData);

    // Set column widths
    ws["!cols"] = [
        { wch: 20 },  
        { wch: 20 },  
        { wch: 20 },  
        { wch: 30 },
        { wch: 20 }   
    ];

    // Protect worksheet with password
    ws["!protect"] = { password: "1234" };

    XLSX.utils.book_append_sheet(wb, ws, "Bank Transfer");

    // Convert workbook to binary format
    let wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });

    function s2ab(s) {
        let buf = new ArrayBuffer(s.length);
        let view = new Uint8Array(buf);
        for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
    }

    // Trigger file download
    saveAs(new Blob([s2ab(wbout)], { type: "application/octet-stream" }), fileName + ".xlsx");
}


function downloadPNB(data, fileName, pnbPayDay, pnbTotalAmount, pnbTotalCount) {
    let wb = XLSX.utils.book_new();

    // Headers
    let headers = [["Employee Name", "Account Number", "Amount", "Remarks", "SOURCE ACCOUNT:","13097000403",
                        "PAYROLL DATE:", pnbPayDay, "PAYROLL TIME:", "", "TOTAL AMOUNT:", pnbTotalAmount, "TOTAL COUNT:", pnbTotalCount]];

    let formattedData = data.map(item => [
        item.full_name || "",   
        item.atm_number || "",       
        item.net_pay || "0.00",     
        item.remarks || "",
        "",
        "",
        "",
        "",
        "",
        "",
        "",
        "",
        ""          
    ]);

    let finalData = headers.concat(formattedData);
    let ws = XLSX.utils.aoa_to_sheet(finalData);

    // Set column widths
    ws["!cols"] = [
        { wch: 30 },  
        { wch: 20 },  
        { wch: 20 },  
        { wch: 30 },
        { wch: 20 },  
        { wch: 15 },  
        { wch: 15 },  
        { wch: 10 },
        { wch: 15 },  
        { wch: 10 },  
        { wch: 15 },  
        { wch: 15 },
        { wch: 15 },   
    ];

    // Protect worksheet with password
    ws["!protect"] = { password: "1234" };

    XLSX.utils.book_append_sheet(wb, ws, "Bank Transfer");

    // Convert workbook to binary format
    let wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });

    function s2ab(s) {
        let buf = new ArrayBuffer(s.length);
        let view = new Uint8Array(buf);
        for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
    }

    // Trigger file download
    saveAs(new Blob([s2ab(wbout)], { type: "application/octet-stream" }), fileName + ".xlsx");
}
