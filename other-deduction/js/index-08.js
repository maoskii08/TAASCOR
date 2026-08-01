let fileName = "Deduction";
let access_level = $('#access_level').val();
let payrollDetails = [];
let employeeArray = [];
let buttonArray = [];
let file_data = 
        {
            "columns" : ['Employee ID','Employee Full Name','Amount','Type of Deduction']
            ,"date" : []
        };

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getDeductionList);
document.getElementById('addBtn').addEventListener("click", individualDeduction);

$( document ).ready(function() {
    // custom-footer loads legacy SweetAlert after SweetAlert2. Preserve the
    // existing module API while guaranteeing that .fire is SweetAlert2.
    if (window.Swal && typeof window.Swal.fire === 'function') {
        window.swal = window.Swal;
    }
    importExcel();
    getClientFilter();
});

$("#client").change(function() {
    let client_selected = $(this).val();
    resetPayrollSelection();
    if(client_selected){
        getPayDay(client_selected);
        getBranch(client_selected);
        getClientLocation(client_selected);
    }
});

$("#add-employee-id").change(function() {
    const selected = $(this).find(':selected');
    $("#add-employee-name").val(selected.data('employee-name') || '');
});

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#branch').val(null).trigger('change');
    $('#clientLocation').val(null).trigger('change');
    $('#tblDiv').hide();
    $('#table_container').empty();
    payrollDetails = [];
    employeeArray = [];
    populateEmployeeSelect([]);
}

function resetPayrollSelection() {
    $('#payDay').empty().append('<option value="">Select Pay Day</option>').val(null).trigger('change');
    $('#branch').empty().append('<option value="">All branches</option>').val('').trigger('change');
    $('#clientLocation').empty().append('<option value="">All locations</option>').val('').trigger('change');
    $('#tblDiv').hide();
    $('#table_container').empty();
    payrollDetails = [];
    employeeArray = [];
    populateEmployeeSelect([]);
}

function responseMessage(response, fallback) {
    if (response && response.error) {
        return typeof response.error === 'string'
            ? response.error
            : (response.error.message || fallback);
    }
    if (response && response.message) {
        return response.message;
    }
    if (response && response.responseJSON) {
        return responseMessage(response.responseJSON, fallback);
    }
    return fallback;
}

function showPayrollAlert(icon, title, text) {
    return Swal.fire({ icon, title, text });
}

function escapeHtml(value) {
    return $('<div>').text(String(value == null ? '' : value)).html();
}

function adjustmentAuditHtml(response, summary) {
    const eventId = String(response && response.audit_event_id || '').trim().toUpperCase();
    if (!/^[A-F0-9]{24}$/.test(eventId)) {
        return escapeHtml(summary) + '<br><strong>Audit evidence unavailable:</strong> no valid event identifier was returned.';
    }
    const link = '../audit-log/?adjustment_event=' + encodeURIComponent(eventId);
    return escapeHtml(summary)
        + '<br><a class="btn btn-sm btn-outline-primary mt-3" href="' + link + '">Review audit event '
        + escapeHtml(eventId) + '</a>';
}

function setAddBusy(isBusy) {
    $('#addBtn')
        .prop('disabled', isBusy)
        .html(isBusy ? 'Saving... <i class="fa fa-spinner fa-spin"></i>' : 'Add');
}

function resetAddForm() {
    $('#add-employee-id').val(null).trigger('change');
    $('#add-employee-name').val('');
    $('#add-amount').val('');
    $('#add-type-of-deduction').val('');
    $('#add-change-reason').val('');
    $('#add-evidence-reference').val('');
}

function populateEmployeeSelect(rows) {
    const employeeSelect = $('#add-employee-id');
    const employees = new Map();
    rows.forEach((row) => {
        const employeeId = Number(row[0]);
        if (Number.isInteger(employeeId) && employeeId > 0 && !employees.has(employeeId)) {
            employees.set(employeeId, String(row[1] || ''));
        }
    });
    employeeSelect.empty().append('<option value="">Select an employee from this DTR</option>');
    Array.from(employees.entries())
        .sort((a, b) => a[1].localeCompare(b[1]))
        .forEach(([employeeId, employeeName]) => {
            const option = new Option(`${employeeName} (${employeeId})`, employeeId, false, false);
            $(option).attr('data-employee-name', employeeName);
            employeeSelect.append(option);
        });
    employeeSelect.val(null).trigger('change');
}

function importExcel() {
    var obj = {};

    for (let i = 0; i < file_data['columns'].length; i++) {
        var xcl = [];
        col = file_data['columns'][i].toLowerCase();
        
        xcl = [file_data['columns'][i], col] 
            
        obj[col] = xcl;
    }

    // console.log(obj)

    new ExcelImport({
        maxWorkbookRows: 1000,
        maxWorkbookBytes: 1048576,
        serverColumnNames: file_data['columns'],
        importTypeSelector: "#dataType",
        fileChooserSelector: "#fileUploader",
        outputSelector: "#tableOutput",
        extraData: {
            importID: 0,
            obj
        }
    });
}


function individualDeduction(){
    let employee_id = $('#add-employee-id').val();
    let employee_name = $('#add-employee-name').val();
    let amount = String($('#add-amount').val() || '').trim();
    let type_of_deduction = String($('#add-type-of-deduction').val() || '').trim();
    let change_reason = String($('#add-change-reason').val() || '').trim();
    let evidence_reference = String($('#add-evidence-reference').val() || '').trim();

    if (!employee_id || !/^\d{1,8}(?:\.\d{1,2})?$/.test(amount) || Number(amount) <= 0 || Number(amount) > 99999999.99) {
        showPayrollAlert('info', 'Check employee and amount', 'Select an employee and enter a positive amount up to 99,999,999.99 with no more than two decimal places.');
        return false;
    }
    if (type_of_deduction.length < 2 || type_of_deduction.length > 50 || change_reason.length < 5 || evidence_reference.length < 3) {
        showPayrollAlert('info', 'Required audit details', 'Enter a deduction type, a business reason, and an approval, ticket, or source reference.');
        return false;
    }
    if (payrollDetails.length !== 1) {
        showPayrollAlert('warning', 'Payroll scope unavailable', 'Filter a client and pay day before adding a deduction.');
        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "add-individual");
    formdata.append("employee_id", employee_id);
    formdata.append("employee_name", employee_name);
    formdata.append("amount", amount);
    formdata.append("type_of_deduction", type_of_deduction);
    formdata.append("change_reason", change_reason);
    formdata.append("evidence_reference", evidence_reference);
    for(let i=0; i < payrollDetails.length; i++) {
        formdata.append("client_name", payrollDetails[i][0]);
        formdata.append("cut_off", payrollDetails[i][1]);
        formdata.append("pay_day", payrollDetails[i][2]);
        formdata.append("start_date", payrollDetails[i][3]);
        formdata.append("end_date", payrollDetails[i][4]);
    }

    $.ajax({
        url: 'controller/DeductionController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function() {
            setAddBusy(true);
        },
        success: function (response) { 
            if(response.success == 1){
                $("#addModal").modal('hide');
                resetAddForm();
                Swal.fire({
                    icon: 'success',
                    title: 'Deduction added',
                    html: adjustmentAuditHtml(response, 'The deduction, payroll recalculation, and exact audit evidence committed together.')
                }).then(function () {
                    getDeductionList();
                });
            }else{
                showPayrollAlert('error', 'Deduction not saved', responseMessage(response, 'Review the payroll scope and try again.'));
            }
        },
        error: function(xhr) {
            showPayrollAlert('error', 'Deduction not saved', responseMessage(xhr, 'The request could not be completed.'));
        },
        complete: function() {
            setAddBusy(false);
        }
    });
}


$(document).on("click","#dtrTbl #deleteBtn",function() {
    let id = $(this).val();

    let row = $(this).closest('tr');
    if (row.hasClass('child')) {
        row = row.prev();
    }
    const tableRow = $.fn.DataTable.isDataTable('#dtrTbl')
        ? $('#dtrTbl').DataTable().row(row).data()
        : null;
    const employee_id = Number(
        $(this).data('employee-id')
        || (Array.isArray(tableRow) ? tableRow[0] : null)
        || row.find('td:eq(0)').text().trim()
    );
    if (!Number.isInteger(employee_id) || employee_id <= 0 || payrollDetails.length !== 1) {
        showPayrollAlert('error', 'Deduction not selected', 'Refresh the filtered payroll population and try again.');
        return;
    }
    
    Swal.fire({
        title: 'Delete this payroll deduction?',
        html: `
            <label for="delete-change-reason" class="form-label text-start d-block">Business reason</label>
            <input id="delete-change-reason" class="swal2-input mt-0" maxlength="255" placeholder="Why must this deduction be deleted?">
            <label for="delete-evidence-reference" class="form-label text-start d-block">Approval or evidence reference</label>
            <input id="delete-evidence-reference" class="swal2-input mt-0" maxlength="255" placeholder="Ticket, approval, or source">
            <label for="delete-confirmation" class="form-label text-start d-block">Type DELETE to confirm</label>
            <input id="delete-confirmation" class="swal2-input mt-0" autocomplete="off" placeholder="DELETE">
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete deduction',
        confirmButtonColor: '#d33',
        focusConfirm: false,
        preConfirm: () => {
            const changeReason = String($('#delete-change-reason').val() || '').trim();
            const evidenceReference = String($('#delete-evidence-reference').val() || '').trim();
            const confirmation = String($('#delete-confirmation').val() || '').trim().toUpperCase();
            if (changeReason.length < 5) {
                Swal.showValidationMessage('Enter a business reason with at least five characters.');
                return false;
            }
            if (evidenceReference.length < 3) {
                Swal.showValidationMessage('Enter an approval, ticket, or source reference.');
                return false;
            }
            if (confirmation !== 'DELETE') {
                Swal.showValidationMessage('Type DELETE exactly to confirm.');
                return false;
            }
            return {
                change_reason: changeReason,
                evidence_reference: evidenceReference,
                confirmation
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {

            let formdata = new FormData();
            formdata.append("request", 'delete');
            formdata.append("id", id);
            formdata.append("employee_id", employee_id);
            for(let i=0; i < payrollDetails.length; i++) {
                formdata.append("client_name", payrollDetails[i][0]);
                formdata.append("cut_off", payrollDetails[i][1]);
                formdata.append("pay_day", payrollDetails[i][2]);
                formdata.append("start_date", payrollDetails[i][3]);
                formdata.append("end_date", payrollDetails[i][4]);
            }
            formdata.append("change_reason", result.value.change_reason);
            formdata.append("evidence_reference", result.value.evidence_reference);
            formdata.append("confirmation", result.value.confirmation);
            
            $.ajax({
                url: 'controller/DeductionController.php',
                type: 'POST',
                data: formdata,
                dataType: 'json',
                processing: true, 
                contentType: false,
                processData: false
                })
                .done(function (response) {
                // success: function (response) {

                    if(response.success == 1){
                        Swal.fire({
                            icon: 'success',
                            title: 'Deduction deleted',
                            html: adjustmentAuditHtml(response, 'The removal, payroll recalculation, and exact audit evidence committed together.')
                        }).then(function () {
                            getDeductionList()
                        });
                    }else{
                        showPayrollAlert('error', 'Deduction not deleted', responseMessage(response, 'Review the payroll scope and try again.'));
                    }
                
                })
                .fail(function (xhr) {
                    showPayrollAlert('error', 'Deduction not deleted', responseMessage(xhr, 'The request could not be completed.'));
                });
        } 
    });
});

function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/DeductionController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function() {
            $('#client').attr('disabled',true);
            $('#client').empty();
        },
        success: function (response) { 
            $('#client').append('<option value="">Select Client</option>');

            (Array.isArray(response.data) ? response.data : []).forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(option1);
            });

            $('#client').val(null).trigger('change');
            $('#client').attr('disabled',false);
            if (response.success !== 1) {
                showPayrollAlert('error', 'Clients unavailable', responseMessage(response, 'Unable to load active payroll clients.'));
            }
        },
        error: function(xhr) {
            $('#client').attr('disabled', false);
            showPayrollAlert('error', 'Clients unavailable', responseMessage(xhr, 'Unable to load active payroll clients.'));
        }
    });
}

function getPayDay(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-pay-day");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DeductionController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function() {
            $('#payDay').attr('disabled',true);
            $('#payDay').empty();
        },
        success: function (response) { 
            $('#payDay').append('<option value="">Select Pay Day</option>');

            (Array.isArray(response.data) ? response.data : []).forEach(option => {
                var dataOption = new Option(formatDate(option.pay_date), option.pay_date, false, false);
                $(dataOption).attr("data-sd", option.start_date).attr("data-ed", option.end_date).attr("data-co", option.cut_off);
                $('#payDay').append(dataOption);
            });

            $('#payDay').val(null).trigger('change');
            $('#payDay').attr('disabled',false);
            if (response.success !== 1) {
                showPayrollAlert('error', 'Pay days unavailable', responseMessage(response, 'Unable to load payroll periods.'));
            }
        },
        error: function(xhr) {
            $('#payDay').attr('disabled', false);
            showPayrollAlert('error', 'Pay days unavailable', responseMessage(xhr, 'Unable to load payroll periods.'));
        }
    });
}

function getBranch(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-branch");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DeductionController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function() {
            $('#branch').attr('disabled',true);
            $('#branch').empty();
        },
        success: function (response) { 
            $('#branch').append('<option value="">All branches</option>');

            (Array.isArray(response.data) ? response.data : []).forEach(option => {
                var dataOption = new Option(option.branch_name, option.branch_id, false, false);
                $('#branch').append(dataOption);
            });

            $('#branch').val('').trigger('change');
            $('#branch').attr('disabled',false);
        },
        error: function(xhr) {
            $('#branch').empty().append('<option value="">All branches</option>').attr('disabled', false);
            showPayrollAlert('error', 'Branches unavailable', responseMessage(xhr, 'Unable to load branches.'));
        }
    });
}

function getClientLocation(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-client-location");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DeductionController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function() {
            $('#clientLocation').attr('disabled',true);
            $('#clientLocation').empty();
        },
        success: function (response) { 
            $('#clientLocation').append('<option value="">All locations</option>');

            (Array.isArray(response.data) ? response.data : []).forEach(option => {
                var dataOption = new Option(option.location_name, option.location_id, false, false);
                $('#clientLocation').append(dataOption);
            });

            $('#clientLocation').val('').trigger('change');
            $('#clientLocation').attr('disabled',false);
        },
        error: function(xhr) {
            $('#clientLocation').empty().append('<option value="">All locations</option>').attr('disabled', false);
            showPayrollAlert('error', 'Locations unavailable', responseMessage(xhr, 'Unable to load client locations.'));
        }
    });
}


function importData() {
    if (payrollDetails.length !== 1) {
        showPayrollAlert('warning', 'Payroll scope unavailable', 'Filter a client and pay day with a DTR population before uploading deductions.');
        return;
    }
    $('#importModal').modal('show');
}

$("#importModal").on('hidden.bs.modal', function (e) {	
    $('#readingFileStatus').html("");
    $('#tableOutput').html("");
    $('#dataType').prop('disabled', false);
    $('#fileUploader').prop('disabled', false);
    document.getElementById('fileUploader').value= null;
    $('#import-change-reason').val('');
    $('#import-evidence-reference').val('');
});

function getDeductionList() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let client_location = $("#clientLocation").val();
    let start_date = $("#payDay option:selected").data("sd");
    let end_date = $("#payDay option:selected").data("ed");
    let cut_off = $("#payDay option:selected").data("co");
    let branch = $("#branch").val();

    if(!client || !pay_day || !start_date || !end_date || !cut_off){
        showPayrollAlert('info', 'Required payroll scope', 'Select a client and pay day before reviewing deductions.');

        $('#tblDiv').hide();
        payrollDetails = [];
        employeeArray = [];
        populateEmployeeSelect([]);

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "get-deduction-list");
    formdata.append("client", client);
    formdata.append("pay_day", pay_day);
    formdata.append("start_date", start_date);
    formdata.append("end_date", end_date);
    formdata.append("cut_off", cut_off);
    formdata.append("client_location", client_location);
    formdata.append("branch", branch);

    $.ajax({
        url: 'controller/DeductionController.php',
        data: formdata,
        type: 'POST',
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $("#tblDiv").show();
            $('#table_container').html('<div class="text-center" role="status">Loading deductions <i class="fa fa-spinner fa-spin" aria-hidden="true"></i></div>');
        },
        success: function(response){
            if(response.success == 1){
                const rows = Array.isArray(response.data) ? response.data : [];
                if(rows.length > 0){
                    fileName = "Deduction";
                    if(client != null && client != ''){
                        fileName += "_" + client;
                    }

                    if(pay_day != null && pay_day != ''){
                        fileName += "_" + pay_day;
                    }
                    buttonArray = [
                        {
                            text: '<i class="bx bx-plus"></i> Add Deduction',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                resetAddForm();
                                populateEmployeeSelect(employeeArray);
                                $("#addModal").modal("show");            
                            }
                        },
                        {
                            text: '<i class="bx bx-upload"></i> Upload',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                importData();            
                            }
                        },
                        { 
                            extend: 'excel', 
                            title: null,
                            className: 'btn btn-sm btn-outline-secondary',
                            text: '<i class="bx bx-download"></i> Download',
                                filename: fileName,
                                exportOptions: {
                                    columns: function (index, data, node) {
                                        return index !== 4;
                                    },
                                format: {
                                    header: function (data, column) {
                                        return data; 
                                    }
                                }
                            }
                        }
                    ];
                    
                    if(response.locked){
                        buttonArray = [
                            { 
                                extend: 'excel', 
                                title: null,
                                className: 'btn btn-sm btn-outline-secondary',
                                text: '<i class="bx bx-download"></i> Download',
                                filename: fileName,
                                exportOptions: {
                                    columns: function (index) {
                                        return index !== 4;
                                    },
                                    format: {
                                        header: function (data, column) {
                                            return data; 
                                        }
                                    }
                                }
                            }
                        ];
                    }

                    payrollDetails= [];
                    payrollDetails.push([client,cut_off,pay_day,start_date,end_date]);
                    var table = `
                    <div class="alert alert-primary" role="alert">
                        Client: <span class="alert-link me-3">${escapeHtml(client)}</span>
                        Cut Off: <a class="alert-link me-3">${formatDate(start_date)} to ${formatDate(end_date)}</a>
                        Pay Day: <a class="alert-link">${formatDate(pay_day)}</a>
                    </div>
                    <table id="dtrTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                        style="width:100%">
                        <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Employee Full Name</th>
                            <th>Amount</th>
                            <th>Type of Deduction</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                    </table>`;

                    $('#table_container').html('');
                    $('#table_container').html(table);

                    $('#dtrTbl').DataTable({
                        data: rows,
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
                        buttons: buttonArray,
                        columnDefs: [
                            {
                                targets: [0, 1, 2, 3],
                                render: $.fn.dataTable.render.text()
                            },
                            {
                                targets: 4,
                                orderable: false,
                                searchable: false
                            }
                        ]
                    });

                    employeeArray = rows;
                    populateEmployeeSelect(employeeArray);
                }else{
                    payrollDetails = [];
                    employeeArray = [];
                    populateEmployeeSelect([]);
                    $('#table_container').html(`
                        <div class="alert alert-warning mb-0" role="alert">
                            <h5 class="alert-heading mb-1">No DTR population found</h5>
                            <p class="mb-2">Upload or reconcile the DTR for this exact client and payroll period before entering deductions.</p>
                            <a class="btn btn-sm btn-outline-warning" href="../dtr-upload/">Go to DTR Upload</a>
                        </div>
                    `);
                }                
            }else if(response.success == 2){
                payrollDetails = [];
                employeeArray = [];
                populateEmployeeSelect([]);
                $('#table_container').html(`
                    <div class="alert alert-warning mb-0" role="alert">
                        <h5 class="alert-heading mb-1">No pay day configured</h5>
                        <p class="mb-0">Configure the client cutoff and pay day before processing deductions.</p>
                    </div>
                `);
            }else{
                payrollDetails = [];
                employeeArray = [];
                populateEmployeeSelect([]);
                $('#table_container').html('<div class="alert alert-danger mb-0" role="alert">Unable to load the payroll deduction population.</div>');
                showPayrollAlert('error', 'Deductions unavailable', responseMessage(response, 'Review the selected payroll scope and try again.'));
            }
        },
        error: function(xhr) {
            payrollDetails = [];
            employeeArray = [];
            populateEmployeeSelect([]);
            $('#table_container').html('<div class="alert alert-danger mb-0" role="alert">Unable to load the payroll deduction population.</div>');
            showPayrollAlert('error', 'Deductions unavailable', responseMessage(xhr, 'The request could not be completed.'));
        }
    });
}

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}

function formatDate(dateString) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateString || ''))) {
        return String(dateString || '');
    }
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}
