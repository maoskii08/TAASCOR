let employee_id = null;
let employee_id_array = [];
let empTbl = null;
let fileName = "Employee_Management";
let access_level = $('#access_level').val();
let dtrIdentityPrefill = null;
let dtrIdentityPrefillOpened = false;
let dataIssueHandoff = null;
let dataIssueHandoffHandled = false;
let employeeWorkspaceEmbed = new URLSearchParams(window.location.search).get('embed') === '1';
let file_data = 
        {
            "columns" : ['Employee Ident','Old Employee Ident', 'Payroll Employee ID', 'Full Name', 'Last Name', 'First Name', 'Middle Name', 'Hire Date', 
                        'Separation Date', 'Present Address', 'Permanent Address', 'Contact Number', 'Email Address', 'Birthday', 'Birth Place', 'Gender', 'Civil Status', 
                        'Nationality', 'Emergency Person', 'Emergency Contact Number', 'Client Date', 'Position', 'Client', 'Branch', 'Client Location', 'Department', 'TIN', 'SSS', 
                        'PHILHEALTH', 'PAG-IBIG', 'Bank Account Number', 'Daily Salary', 'Bank Name', 'Insurance', 'Annual Leaves', 'Employee Type', 'Pay Type'
                        ]
            ,"date" : ['Hire Date', 'Separation Date', 'Birthday', 'Client Date']
        };

function isBlank(value) {
    return value == null || String(value).trim() === '';
}

function validateRequiredFields(fields) {
    let missingFields = fields
        .filter(field => isBlank(field.value))
        .map(field => field.label);

    if (missingFields.length === 0) {
        return true;
    }

    Swal.fire({
        icon: 'info',
        title: 'Complete the Required Fields',
        html: 'Please complete the following fields before saving:<br><br>' +
            missingFields.map(field => '&bull; ' + field).join('<br>') +
            '<br><br><small>Examples: Contact Number: 09171234567 &nbsp;|&nbsp; SSS: 12-3456789-0 &nbsp;|&nbsp; Date: 2026-06-30</small>'
    });

    return false;
}

function escapeHtml(value) {
    return $('<div>').text(value).html();
}

function getRequestErrorMessage(response) {
    let message = response && response.error ? response.error : '';

    if (!message && response && response.responseJSON) {
        message = response.responseJSON.error || '';
    }

    if (!message && response && response.responseText) {
        try {
            message = JSON.parse(response.responseText).error || '';
        } catch (e) {}
    }

    return 'Please refresh the page and try again. If the issue continues, contact your administrator.' +
        (message ? '<br><br><small>Details: ' + escapeHtml(message) + '</small>' : '');
}

function finishEmployeeWorkspace(actionName, employeeId) {
    if (employeeWorkspaceEmbed && window.parent !== window) {
        window.parent.postMessage({
            source: 'taascor-employee-workspace',
            type: 'saved',
            action: actionName,
            employeeId: String(employeeId || '')
        }, window.location.origin);
        return;
    }
    window.location.reload();
}

function openWorkspaceTermination() {
    employee_id = String($('#edit-employee-ident').val() || employee_id || '');
    $('#term-employee-id').val(employee_id);
    $('#editEmployeeModal').modal('hide');
    $('#terminateModal').modal('show');
}

function removeWorkspaceEmployee() {
    const employeeId = String($('#edit-employee-ident').val() || employee_id || '');
    if (!employeeId) {
        return;
    }
    Swal.fire({
        title: 'Remove this employee from active HRIS?',
        html: 'The employee record will be retained with today’s removal date and can be restored from Terminated Employees.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remove employee',
        cancelButtonText: 'Cancel'
    }).then(function (result) {
        if (!result.value) {
            return;
        }
        let formdata = new FormData();
        formdata.append('request', 'delete-employee');
        formdata.append('employee_id_array', employeeId);
        $.ajax({
            url: 'controller/EmployeeController.php',
            type: 'POST',
            data: formdata,
            dataType: 'json',
            contentType: false,
            processData: false
        }).done(function (response) {
            if (response.success !== 1) {
                Swal.fire({
                    icon: 'error',
                    title: 'Unable to Remove Employee',
                    html: getRequestErrorMessage(response)
                });
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Employee Removed From Active HRIS'
            }).then(function () {
                finishEmployeeWorkspace('remove', employeeId);
            });
        }).fail(function (response) {
            Swal.fire({
                icon: 'error',
                title: 'Unable to Remove Employee',
                html: getRequestErrorMessage(response)
            });
        });
    });
}

$(document).on('click', '.modal [data-bs-dismiss="modal"]', function () {
    if (employeeWorkspaceEmbed && window.parent !== window) {
        window.parent.postMessage({
            source: 'taascor-employee-workspace',
            type: 'close'
        }, window.location.origin);
    }
});

document.getElementById('saveBtn').addEventListener("click", saveChanges);
document.getElementById('addBtn').addEventListener("click", addEmployee);
document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getEmployeeList);
document.getElementById('terminateBtn').addEventListener("click", terminateEmployee);
if (document.getElementById('workspaceTerminateBtn')) {
    document.getElementById('workspaceTerminateBtn').addEventListener('click', openWorkspaceTermination);
}
if (document.getElementById('workspaceRemoveBtn')) {
    document.getElementById('workspaceRemoveBtn').addEventListener('click', removeWorkspaceEmployee);
}

$( document ).ready(function() {
    $.ajaxPrefilter(function(options, originalOptions, xhr) {
        let csrfToken = $('#csrf_token').val();
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-Token', csrfToken);
        }
    });

    if(access_level != "4"){
        document.getElementById("addEmployeeBtn").style.display = "block";
    }
    importExcel();
    prepareDataIssueHandoff();
    prepareDtrIdentityPrefill();
    getClientFilter();
    getDepartmentFilter();
});

function prepareDataIssueHandoff() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('from_data_issue') !== '1') {
        return;
    }

    const employeeId = String(params.get('employee_id') || '').trim();
    let actionName = String(params.get('action') || 'edit').trim().toLowerCase();
    if (!['edit', 'terminate', 'delete'].includes(actionName)) {
        actionName = 'edit';
    }
    if (!employeeId) {
        return;
    }

    dataIssueHandoff = {
        employeeId: employeeId,
        action: actionName,
        returnTo: String(params.get('return_to') || '').trim()
    };
    $('#employee').val(employeeId);
}

function clearDataIssueHandoffUrl() {
    const url = new URL(window.location.href);
    ['from_data_issue', 'employee_id', 'action', 'return_to'].forEach(function (key) {
        url.searchParams.delete(key);
    });
    window.history.replaceState(
        {},
        document.title,
        url.pathname + (url.search ? url.search : '') + url.hash
    );
}

function showDataIssueHandoffError(title, message) {
    Swal.fire({
        icon: 'info',
        title: title,
        html: escapeHtml(message)
    });
}

function handleDataIssueHandoff(tableApi) {
    if (!dataIssueHandoff || dataIssueHandoffHandled) {
        return;
    }
    dataIssueHandoffHandled = true;

    const employeeId = dataIssueHandoff.employeeId;
    const actionName = dataIssueHandoff.action;
    const updateButton = $('#emp_mgmnt_tbl #updateBtn').filter(function () {
        return String($(this).val()) === employeeId;
    }).first();

    clearDataIssueHandoffUrl();

    if (!updateButton.length) {
        showDataIssueHandoffError(
            'Employee Unavailable',
            'Employee ' + employeeId + ' is not available in your Employee Management access scope.'
        );
        return;
    }

    if (actionName === 'edit') {
        updateButton.trigger('click');
        return;
    }

    if (actionName === 'terminate') {
        const terminateButton = updateButton.closest('tr').find('#deleteBtn').first();
        if (!terminateButton.length) {
            showDataIssueHandoffError(
                'Action Unavailable',
                'You do not have permission to terminate this employee.'
            );
            return;
        }
        terminateButton.trigger('click');
        return;
    }

    if (String(access_level) !== '1') {
        showDataIssueHandoffError(
            'Admin Access Required',
            'Only an Admin can remove an employee from active HRIS records.'
        );
        return;
    }

    tableApi.row(updateButton.closest('tr')).select();
    bulkDelete();
}

function prepareDtrIdentityPrefill() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('from_identity_exception') !== '1') {
        return;
    }
    dtrIdentityPrefill = {
        sourceEmployeeId: String(params.get('source_employee_id') || '').trim(),
        sourceEmployeeName: String(params.get('source_employee_name') || '').trim(),
        clientId: String(params.get('client_id') || '').trim(),
        clientName: String(params.get('client_name') || '').trim()
    };
    const nameParts = dtrIdentityPrefill.sourceEmployeeName.split(',');
    const lastName = String(nameParts[0] || '').trim();
    const firstName = String(nameParts.slice(1).join(',') || '').trim();
    $('#add-full-name').val(dtrIdentityPrefill.sourceEmployeeName);
    $('#add-last-name').val(lastName);
    $('#add-first-name').val(firstName);
    $('#add-payroll-employee-ident').val(dtrIdentityPrefill.sourceEmployeeId);
    $('#identity-prefill-alert')
        .removeClass('d-none')
        .html(
            '<strong>DTR identity handoff</strong><br>'
            + 'The source name, source employee ID, and client were prefilled from an unresolved DTR exception. '
            + 'Review every required HRIS field before saving; this page does not auto-create an employee.'
        );
    if (!dtrIdentityPrefillOpened) {
        dtrIdentityPrefillOpened = true;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addEmployeeModal')).show();
    }
}

function applyDtrIdentityClientPrefill() {
    if (!dtrIdentityPrefill || !dtrIdentityPrefill.clientName) {
        return;
    }
    const hasClient = $('#add-client option').filter(function () {
        return String($(this).val()) === dtrIdentityPrefill.clientName;
    }).length > 0;
    if (hasClient) {
        $('#add-client').val(dtrIdentityPrefill.clientName).trigger('change');
    }
}

$("#branch").change(function() {
    let branch_selected = $(this).val();
    if(branch_selected != null){
        getClientBranch(branch_selected);
    }    
});

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#client-location-filter').val(null).trigger('change');
    $('#branch').val(null).trigger('change');
    $('#employee').val(null);
    getClientFilter();
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
        maxInAGroup: 100,
        serverColumnNames: file_data['columns'],
        importTypeSelector: "#dataType",
        fileChooserSelector: "#fileUploader",
        clientSelector: "#import-client",
        outputSelector: "#tableOutput",
        extraData: {
            importID: 0,
            cmd: "batch_upload",
            obj
        }
    });
}

function bulkDelete(){
    employee_id_array = empTbl.rows({ selected: true }).data().pluck(2).toArray();

    if(employee_id_array.length == 0){
        Swal.fire({
            title: "Select an Employee",
            text: "Tick the checkbox beside at least one employee, then click Delete again." ,
            icon: "info"
        });

        return false;
    }
    // console.log(employee_id_array)
    Swal.fire({
        title: 'Remove the selected employees from active HRIS?',
        html: 'The employee records will be retained with a removal date and can be restored from Terminated Employees.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        if (result.value) {
            let formdata = new FormData();
            formdata.append("request", 'delete-employee');
            formdata.append("employee_id_array", employee_id_array);
            
            $.ajax({
                url: 'controller/EmployeeController.php',
                type: 'POST',
                data: formdata,
                dataType: 'json',
                processing: true, 
                contentType: false,
                processData: false
                })
                .done(function (response) {

                    if(response.success == 1){

                        Swal.fire({
                            icon: 'success',   
                            title: 'Employees Removed From Active HRIS'
                        }).then(function (result) {
                            finishEmployeeWorkspace('remove', employee_id_array.join(','))
                        });

                    }else{

                        Swal.fire({
                            icon: 'error',   
                            title: 'Something went wrong!',                 
                            html: getRequestErrorMessage(response)               
                        }).then(function (result) {
                            window.location.reload()
                        });
                    }
                
                })
                .fail(function (response) {
                    Swal.fire({
                        icon: 'error',   
                        title: 'Something went wrong!',                 
                        html: getRequestErrorMessage(response)               
                    }).then(function (result) {
                        window.location.reload()
                    });
                });
        } 
    })
}


$(document).on("click","#emp_mgmnt_tbl #updateBtn",function() {
    var row = $(this).closest('tr');

    employee_id = $(this).val();

    var old_employee_ident = row.find('td:eq(3)').text();
    var payroll_employee_id = row.find('td:eq(4)').text();
    var full_name = row.find('td:eq(5)').text();
    var last_name = row.find('td:eq(6)').text();
    var first_name = row.find('td:eq(7)').text();
    var middle_name = row.find('td:eq(8)').text();
    var hire_date = row.find('td:eq(9)').text();
    var separation_date = row.find('td:eq(10)').text();
    var present_address = row.find('td:eq(11)').text();
    var permanent_address = row.find('td:eq(12)').text();
    var contact_number = row.find('td:eq(13)').text();
    var email_address = row.find('td:eq(14)').text();
    var birthday = row.find('td:eq(15)').text();
    var birth_place = row.find('td:eq(16)').text();
    var gender = row.find('td:eq(17)').text();
    var civil_status = row.find('td:eq(18)').text();
    var nationality = row.find('td:eq(19)').text();
    var emergency_person = row.find('td:eq(20)').text();
    var emergency_contact_number = row.find('td:eq(21)').text();
    var client_date = row.find('td:eq(22)').text();
    var position_name = row.find('td:eq(23)').text();
    var client_name = row.find('td:eq(24)').text();
    var branch_name = row.find('td:eq(25)').text();
    var clientLocation = row.find('td:eq(26)').text();
    var department_name = row.find('td:eq(27)').text();
    var tin = row.find('td:eq(28)').text();
    var sss = row.find('td:eq(29)').text();
    var philhealth = row.find('td:eq(30)').text();
    var pag_ibig = row.find('td:eq(31)').text();
    var bank_account_number = row.find('td:eq(32)').text();
    var daily_salary = row.find('td:eq(33)').text();
    var bank_name = row.find('td:eq(34)').text();
    var insurance = row.find('td:eq(35)').text();
    var annual_leaves = row.find('td:eq(36)').text();
    var employee_type = row.find('td:eq(37)').text();
    var pay_type = row.find('td:eq(38)').text();

    let hireformattedDate = formatDateToYYYYMMDD(hire_date);
    let birthdayformattedDate = formatDateToYYYYMMDD(birthday);
    let clientformattedDate = formatDateToYYYYMMDD(client_date);

    $('#edit-employee-ident').val(employee_id);
    $('#edit-old-employee-ident').val(old_employee_ident);
    $('#edit-payroll-employee-ident').val(payroll_employee_id);
    $('#edit-full-name').val(full_name);
    $('#edit-last-name').val(last_name);
    $('#edit-first-name').val(first_name);
    $('#edit-middle-name').val(middle_name);
    $('#edit-hire-date').val(hireformattedDate);
    $('#edit-separation-date').val(separation_date);
    $('#edit-present-address').val(present_address);
    $('#edit-permanent-address').val(permanent_address);
    $('#edit-contact-number').val(contact_number);
    $('#edit-email').val(email_address);
    $('#edit-birthday').val(birthdayformattedDate);
    $('#edit-birth-place').val(birth_place);
    $('#edit-gender').val(gender);
    $('#edit-civil-status').val(civil_status);
    $('#edit-nationality').val(nationality);
    $('#edit-emergency-person').val(emergency_person);
    $('#edit-emergency-contact-number').val(emergency_contact_number);
    $('#edit-branch').val(branch_name).trigger('change');
    $('#edit-client-location').val(clientLocation).trigger('change');
    $('#edit-client').val(client_name).trigger('change');
    $('#edit-client-date').val(clientformattedDate);
    $('#edit-department').val(department_name).trigger('change');
    $('#edit-position').val(position_name).trigger('change');
    $('#edit-insurance').val(insurance);
    $('#edit-tin').val(tin);
    $('#edit-sss').val(sss);
    $('#edit-philhealth').val(philhealth);
    $('#edit-pag-ibig').val(pag_ibig);
    $('#edit-daily-salary').val(daily_salary);
    $('#edit-bank-name').val(bank_name);
    $('#edit-bank-account-number').val(bank_account_number);
    $('#edit-annual-leaves').val(annual_leaves);
    $('#edit-pay-type').val(pay_type);
    $('#edit-employee-type').val(employee_type).trigger('change');
    $('#editEmployeeModal').modal("show");

});


function saveChanges(){

    let employee_ident = $('#edit-employee-ident').val();
    let old_employee_ident = $('#edit-old-employee-ident').val();
    let payroll_employee_ident = $('#edit-payroll-employee-ident').val();
    let full_name = $('#edit-full-name').val();
    let last_name = $('#edit-last-name').val();
    let first_name = $('#edit-first-name').val();
    let middle_name = $('#edit-middle-name').val();
    let hire_date = $('#edit-hire-date').val();
    let present_address = $('#edit-present-address').val();
    let permanent_address = $('#edit-permanent-address').val();
    let contact_number = $('#edit-contact-number').val();
    let email_address = $('#edit-email').val();
    let birthday =  $('#edit-birthday').val();
    let birth_place = $('#edit-birth-place').val();
    let gender = $('#edit-gender').val();
    let civil_status = $('#edit-civil-status').val();
    let nationality = $('#edit-nationality').val();
    let emergency_person = $('#edit-emergency-person').val();
    let emergency_contact_number = $('#edit-emergency-contact-number').val();
    let branch = $('#edit-branch').val();
    let client = $('#edit-client').val();
    let department = $('#edit-department').val();
    let position =  $('#edit-position').val();
    let insurance = $('#edit-insurance').val();
    let tin = $('#edit-tin').val();
    let sss = $('#edit-sss').val();
    let philhealth = $('#edit-philhealth').val();
    let pag_ibig = $('#edit-pag-ibig').val();
    let daily_salary = $('#edit-daily-salary').val();
    let bank_name =  $('#edit-bank-name').val();
    let bank_account_number = $('#edit-bank-account-number').val();
    let annual_leaves = $('#edit-annual-leaves').val();
    let employee_type = $('#edit-employee-type').val();
    let client_location = $('#edit-client-location').val();
    let client_date = $('#edit-client-date').val();
    let pay_type = $('#edit-pay-type').val();
    
    if (!validateRequiredFields([
        { label: 'Employee Type', value: employee_type },
        { label: 'Full Name', value: full_name },
        { label: 'Last Name', value: last_name },
        { label: 'First Name', value: first_name },
        { label: 'Gender', value: gender },
        { label: 'Civil Status', value: civil_status },
        { label: 'Hire Date', value: hire_date },
        { label: 'Present Address', value: present_address },
        { label: 'Contact Number', value: contact_number },
        { label: 'Birthday', value: birthday },
        { label: 'Birth Place', value: birth_place },
        { label: 'Pay Type', value: pay_type },
        { label: 'Branch', value: branch },
        { label: 'Client', value: client },
        { label: 'Client Date', value: client_date },
        { label: 'Client Location', value: client_location },
        { label: 'Position', value: position },
        { label: 'TIN', value: tin },
        { label: 'SSS', value: sss },
        { label: 'PHILHEALTH', value: philhealth },
        { label: 'PAG-IBIG', value: pag_ibig },
        { label: 'Daily Salary', value: daily_salary },
        { label: 'Bank Account Number', value: bank_account_number },
        { label: 'Annual Leaves', value: annual_leaves }
    ])) {
        return false;
    }

    let cleanedSSS = sss.replace(/[^0-9]/g, '');
    if (cleanedSSS.length != 10 && sss.trim() != '') {
        Swal.fire({
            icon: 'info',   
            title: 'Invalid SSS Format',       
            html: 'Enter exactly 10 digits.<br><br><small>Example: 12-3456789-0 or 1234567890</small>'
        });

        return false;
    } 

    // if(daily_salary < 100 || daily_salary > 10000){
    //     Swal.fire({
    //         icon: 'info',   
    //         title: 'Daily Salary Adjustments',       
    //         text: 'Daily Salary is less than 100 or greater than 10,000'
    //     });

    //     return false;
    // }

    let cleanedNumber = contact_number.replace(/[^0-9]/g, '');
    if (!/^\d{11}$/.test(cleanedNumber) && contact_number.trim() != '') {
        Swal.fire({
            icon: 'info',   
            title: 'Invalid Contact Number',       
            html: 'Enter exactly 11 digits.<br><br><small>Example: 09171234567</small>'
        });
        return false;  
    }

    let formdata = new FormData();
    formdata.append("request", "update-employee");
    formdata.append("employee_ident", employee_ident);
    formdata.append("old_employee_ident", old_employee_ident);
    formdata.append("last_name", last_name);
    formdata.append("first_name", first_name);
    formdata.append("middle_name", middle_name);
    formdata.append("hire_date", hire_date);
    formdata.append("present_address", present_address);
    formdata.append("permanent_address", permanent_address);
    formdata.append("contact_number", contact_number);
    formdata.append("email_address", email_address);
    formdata.append("birthday", birthday);
    formdata.append("birth_place", birth_place);
    formdata.append("gender", gender);
    formdata.append("civil_status", civil_status);
    formdata.append("nationality", nationality);
    formdata.append("emergency_person", emergency_person);
    formdata.append("emergency_contact_number", emergency_contact_number);
    formdata.append("branch", branch);
    formdata.append("client", client);
    formdata.append("department", department);
    formdata.append("position", position);
    formdata.append("insurance", insurance);
    formdata.append("tin", tin);
    formdata.append("sss", sss);
    formdata.append("pag_ibig", pag_ibig);
    formdata.append("philhealth", philhealth);
    formdata.append("daily_salary", daily_salary);
    formdata.append("bank_name", bank_name);
    formdata.append("bank_account_number", bank_account_number);
    formdata.append("annual_leaves", annual_leaves);
    formdata.append("payroll_employee_ident", payroll_employee_ident);
    formdata.append("full_name", full_name);
    formdata.append("employee_type", employee_type);
    formdata.append("client_location", client_location);
    formdata.append("client_date", client_date);
    formdata.append("pay_type", pay_type);

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#saveBtn').html('Updating Employee... <i class="fa fa-spinner fa-spin"></i>');
            $('#saveBtn').attr('disabled',true);
        },
        success: function (response) { 
            if(response.success == 1){
                $("#editEmployeeModal").modal('hide');
                Swal.fire({
                    icon: 'success',   
                    title: 'Successfully Saved Changes! '        
                }).then(function (result) {
                    finishEmployeeWorkspace('update', employee_ident)
                });

            }else if(response.success == 2){
                var dupTxt = "";
                for(let i=0; i < response.dup.length; i++){
                    dupTxt += response.dup[i] + '<br>';
                }
                Swal.fire({
                    icon: 'warning',   
                    title: 'Duplicates!',
                    html: 'The employee record matches data already assigned to an active employee:<br><br>' + dupTxt + '<br>Please review the existing record before trying again.'
                })

                $('#saveBtn').html('Save Changes');
                $('#saveBtn').attr('disabled',false);

                return false;
            }else{
                Swal.fire({
                    icon: 'error',   
                    title: 'Something went wrong!',                 
                    html: getRequestErrorMessage(response)               
                });
                $('#saveBtn').html('Save Changes');
                $('#saveBtn').attr('disabled',false);
            }
        },
        error: function(response) { // if error occured
            Swal.fire({
                icon: 'error',   
                title: 'Something went wrong!',                 
                html: getRequestErrorMessage(response)               
            });
            $('#saveBtn').html('Save Changes');
            $('#saveBtn').attr('disabled',false);
        }
    });
}


function addEmployee(){

    let old_employee_ident = $('#add-old-employee-ident').val();
    let payroll_employee_ident = $('#add-payroll-employee-ident').val();
    // let payroll_branch_code = $('#add-payroll-branch-code').val();
    let full_name = $('#add-full-name').val();
    let last_name = $('#add-last-name').val();
    let first_name = $('#add-first-name').val();
    let middle_name = $('#add-middle-name').val();
    let hire_date = $('#add-hire-date').val();
    let present_address = $('#add-present-address').val();
    let permanent_address = $('#add-permanent-address').val();
    let contact_number = $('#add-contact-number').val();
    let email_address = $('#add-email').val();
    let birthday =  $('#add-birthday').val();
    let birth_place = $('#add-birth-place').val();
    let gender = $('#add-gender').val();
    let civil_status = $('#add-civil-status').val();
    let nationality = $('#add-nationality').val();
    let emergency_person = $('#add-emergency-person').val();
    let emergency_contact_number = $('#add-emergency-contact-number').val();
    let branch = $('#add-branch').val();
    let client = $('#add-client').val();
    let department = $('#add-department').val();
    let position =  $('#add-position').val();
    let insurance = $('#add-insurance').val();
    let tin = $('#add-tin').val();
    let sss = $('#add-sss').val();
    let philhealth = $('#add-philhealth').val();
    let pag_ibig = $('#add-pag-ibig').val();
    let daily_salary = $('#add-daily-salary').val();
    let bank_name =  $('#add-bank-name').val();
    let bank_account_number = $('#add-bank-account-number').val();
    let annual_leaves = $('#add-annual-leaves').val();
    let employee_type = $('#add-employee-type').val();
    let client_location = $('#add-client-location').val();
    let pay_type = $('#add-pay-type').val();
    let client_date = $('#add-client-date').val();

    if (!validateRequiredFields([
        { label: 'Employee Type', value: employee_type },
        { label: 'Full Name', value: full_name },
        { label: 'Last Name', value: last_name },
        { label: 'First Name', value: first_name },
        { label: 'Gender', value: gender },
        { label: 'Civil Status', value: civil_status },
        { label: 'Hire Date', value: hire_date },
        { label: 'Present Address', value: present_address },
        { label: 'Contact Number', value: contact_number },
        { label: 'Birthday', value: birthday },
        { label: 'Birth Place', value: birth_place },
        { label: 'Pay Type', value: pay_type },
        { label: 'Branch', value: branch },
        { label: 'Client', value: client },
        { label: 'Client Date', value: client_date },
        { label: 'Client Location', value: client_location },
        { label: 'Position', value: position },
        { label: 'TIN', value: tin },
        { label: 'SSS', value: sss },
        { label: 'PHILHEALTH', value: philhealth },
        { label: 'PAG-IBIG', value: pag_ibig },
        { label: 'Daily Salary', value: daily_salary },
        { label: 'Bank Account Number', value: bank_account_number },
        { label: 'Annual Leaves', value: annual_leaves }
    ])) {
        return false;
    }

    let cleanedSSS = sss.replace(/[^0-9]/g, '');
    if (cleanedSSS.length != 10 && sss.trim() != '') {
        Swal.fire({
            icon: 'info',   
            title: 'Invalid SSS Format',       
            html: 'Enter exactly 10 digits.<br><br><small>Example: 12-3456789-0 or 1234567890</small>'
        });

        return false;
    } 

    // if(daily_salary < 100 || daily_salary > 10000){
    //     Swal.fire({
    //         icon: 'info',   
    //         title: 'Daily Salary Adjustments',       
    //         text: 'Daily Salary is less than 100 or greater than 10,000'
    //     });

    //     return false;
    // }

    let cleanedNumber = contact_number.replace(/[^0-9]/g, '');
    if (!/^\d{11}$/.test(cleanedNumber) && contact_number.trim() != '') {
        Swal.fire({
            icon: 'info',   
            title: 'Invalid Contact Number',       
            html: 'Enter exactly 11 digits.<br><br><small>Example: 09171234567</small>'
        });
        return false;  
    }

    let formdata = new FormData();
    formdata.append("request", "add-employee");
    formdata.append("old_employee_ident", old_employee_ident);
    formdata.append("last_name", last_name);
    formdata.append("first_name", first_name);
    formdata.append("middle_name", middle_name);
    formdata.append("hire_date", hire_date);
    formdata.append("present_address", present_address);
    formdata.append("permanent_address", permanent_address);
    formdata.append("contact_number", contact_number);
    formdata.append("email_address", email_address);
    formdata.append("birthday", birthday);
    formdata.append("birth_place", birth_place);
    formdata.append("gender", gender);
    formdata.append("civil_status", civil_status);
    formdata.append("nationality", nationality);
    formdata.append("emergency_person", emergency_person);
    formdata.append("emergency_contact_number", emergency_contact_number);
    formdata.append("branch", branch);
    formdata.append("client", client);
    formdata.append("department", department);
    formdata.append("position", position);
    formdata.append("insurance", insurance);
    formdata.append("tin", tin);
    formdata.append("sss", sss);
    formdata.append("pag_ibig", pag_ibig);
    formdata.append("philhealth", philhealth);
    formdata.append("daily_salary", daily_salary);
    formdata.append("bank_name", bank_name);
    formdata.append("bank_account_number", bank_account_number);
    formdata.append("annual_leaves", annual_leaves);
    formdata.append("payroll_employee_ident", payroll_employee_ident);
    formdata.append("full_name", full_name);
    formdata.append("employee_type", employee_type);
    formdata.append("client_location", client_location);
    formdata.append("pay_type", pay_type);
    formdata.append("client_date", client_date);

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#addBtn').html('Adding Employee... <i class="fa fa-spinner fa-spin"></i>');
            $('#addBtn').attr('disabled',true);
        },
        success: function (response) { 
            if(response.success == 1){
                $("#addEmployeeModal").modal('hide');
                Swal.fire({
                    icon: 'success',   
                    title: 'Successfully Added Employee! '        
                }).then(function (result) {
                    finishEmployeeWorkspace('create', response.employee_id || '')
                });

            }else if(response.success == 2){
                var dupTxt = "";
                for(let i=0; i < response.dup.length; i++){
                    dupTxt += response.dup[i] + '<br>';
                }
                Swal.fire({
                    icon: 'warning',   
                    title: 'Duplicates!',
                    html: 'The employee record matches data already assigned to an active employee:<br><br>' + dupTxt + '<br>Please review the existing record before trying again.'
                })

                $('#addBtn').html('Add Employee');
                $('#addBtn').attr('disabled',false);
                return false;
            }else{
                Swal.fire({
                    icon: 'error',   
                    title: 'Something went wrong!',                 
                    html: getRequestErrorMessage(response)               
                });
                $('#addBtn').html('Add Employee');
                $('#addBtn').attr('disabled',false);
            }
        },
        error: function(response) { // if error occured
            Swal.fire({
                icon: 'error',   
                title: 'Something went wrong!',                 
                html: getRequestErrorMessage(response)               
            });
            $('#addBtn').html('Add Employee');
            $('#addBtn').attr('disabled',false);
        }
    });
}


$(document).on("click","#emp_mgmnt_tbl #deleteBtn",function() {
    employee_id = $(this).val();

    $('#term-employee-id').val(employee_id);
    $('#terminateModal').modal('show');
});


function terminateEmployee(){
    Swal.fire({
        title: 'Are you sure you want to terminate this employee?', 
        html: 'Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        
        /* Read more about isConfirmed, isDenied below */
        if (result.value) {
            let termination_date = $('#term-date').val();
            if(termination_date == ''){
                Swal.fire({
                    icon: 'info',   
                    title: 'Enter the Separation Date',       
                    html: 'Select the employee separation date before continuing.<br><br><small>Example: 2026-06-30</small>'
                });
                return false;
            }

            let formdata = new FormData();
            formdata.append("request", 'terminate-employee');
            formdata.append("employee", employee_id);
            formdata.append("termination_date", termination_date);
            
            $.ajax({
                url: 'controller/EmployeeController.php',
                type: 'POST',
                data: formdata,
                dataType: 'json',
                processing: true, 
                contentType: false,
                processData: false
                })
                .done(function (response) {

                    if(response.success == 1){

                        Swal.fire({
                            icon: 'success',   
                            title: 'Successfully Terminated Employee! '        
                        }).then(function (result) {
                            finishEmployeeWorkspace('terminate', employee_id)
                        });

                    }else{

                        Swal.fire({
                            icon: 'error',   
                            title: 'Something went wrong!',                 
                            html: getRequestErrorMessage(response)               
                        }).then(function (result) {
                            window.location.reload()
                        });
                    }
                
                })
                .fail(function (response) {
                    Swal.fire({
                        icon: 'error',   
                        title: 'Something went wrong!',                 
                        html: getRequestErrorMessage(response)               
                    }).then(function (result) {
                        window.location.reload()
                    });
                });
        } 
    })
}

function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#client').attr('disabled',true);
            $('#add-client').attr('disabled',true);
            $('#edit-client').attr('disabled',true);
            $('#import-client').attr('disabled',true);
            $('#client').empty();
            $('#add-client').empty();
            $('#edit-client').empty();
            $('#import-client').empty();
        },
        success: function (response) { 
            $('#client').append(`<option value="" disabled selected>Select Client</option>`);
            $('#add-client').append(`<option value="" disabled selected>Select Client</option>`);
            $('#edit-client').append(`<option value="" disabled selected>Select Client</option>`);
            $('#import-client').append(`<option value="" disabled selected>Select Client</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                var option2 = new Option(option.client_name, option.client_name, false, false);
                var option3 = new Option(option.client_name, option.client_name, false, false);
                var importOption = new Option(option.client_name, option.client_id, false, false);
                $('#client').append(option1);
                $('#add-client').append(option2);
                $('#edit-client').append(option3);
                $('#import-client').append(importOption);
            });

            $('#client').trigger('change'); 
            $('#add-client').trigger('change'); 
            $('#edit-client').trigger('change'); 
            $('#client').attr('disabled',false);
            $('#add-client').attr('disabled',false);
            $('#edit-client').attr('disabled',false);
            $('#import-client').attr('disabled',false);

            applyDtrIdentityClientPrefill();
            getBranchFilter();
        }
    }).fail(showEmployeeManagementLoadError);
}


function getClientLocation(){

    let formdata = new FormData();
    formdata.append("request", "get-client-location");

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#client-location-filter').attr('disabled',true);
            $('#client-location-filter').empty();
            $('#add-client-location').attr('disabled',true);
            $('#add-client-location').empty();
            $('#edit-client-location').attr('disabled',true);
            $('#edit-client-location').empty();
        },
        success: function (response) { 
            $('#client-location-filter').append(`<option value="" disabled selected>Select Client Location</option>`);
            $('#add-client-location').append(`<option value="" disabled selected>Select Client Location</option>`);
            $('#edit-client-location').append(`<option value="" disabled selected>Select Client Location</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.location_name, option.location_name, false, false);
                var option2 = new Option(option.location_name, option.location_name, false, false);
                var option3 = new Option(option.location_name, option.location_name, false, false);
                $('#client-location-filter').append(option1);
                $('#add-client-location').append(option2);
                $('#edit-client-location').append(option3);
            });

            $('#client-location-filter').trigger('change'); 
            $('#client-location-filter').attr('disabled',false);

            $('#add-client-location').trigger('change'); 
            $('#add-client-location').attr('disabled',false);

            $('#edit-client-location').trigger('change'); 
            $('#edit-client-location').attr('disabled',false);
        }
    }).fail(showEmployeeManagementLoadError);
}


function getClientBranch(branch_selected){

    let formdata = new FormData();
    formdata.append("request", "get-client-branch");
    formdata.append("branch_selected", branch_selected);


    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#client').attr('disabled',true);
            $('#client').empty();
        },
        success: function (response) { 
            $('#client').append(`<option value="" disabled selected>Select Client</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(option1);
            });

            $('#client').trigger('change'); 
            $('#client').attr('disabled',false);
        }
    }).fail(showEmployeeManagementLoadError);
}



function getBranchFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-branch-filter");

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#branch').attr('disabled',true);
            $('#add-branch').attr('disabled',true);
            $('#edit-branch').attr('disabled',true);
            $('#branch').empty();
            $('#add-branch').empty();
            $('#edit-branch').empty();
        },
        success: function (response) { 
            $('#branch').append(`<option value="" disabled selected>Select Branch</option>`);
            $('#add-branch').append(`<option value="" disabled selected>Select Branch</option>`);
            $('#edit-branch').append(`<option value="" disabled selected>Select Branch</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.branch_name, option.branch_id, false, false);
                var option2 = new Option(option.branch_name, option.branch_name, false, false);
                var option3 = new Option(option.branch_name, option.branch_name, false, false);
                $('#branch').append(option1);
                $('#add-branch').append(option2);
                $('#edit-branch').append(option3);
            });

            $('#branch').trigger('change'); 
            $('#add-branch').trigger('change'); 
            $('#edit-branch').trigger('change'); 
            $('#branch').attr('disabled',false);
            $('#add-branch').attr('disabled',false);
            $('#edit-branch').attr('disabled',false);


            getEmployeeList();
        }
    }).fail(showEmployeeManagementLoadError);
}

function getDepartmentFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-department-filter");

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#add-department').attr('disabled',true);
            $('#edit-department').attr('disabled',true);
            $('#add-department').empty();
            $('#edit-department').empty();
        },
        success: function (response) { 
            $('#add-department').append(`<option value="" disabled selected>Select Department</option>`);
            $('#edit-department').append(`<option value="" disabled selected>Select Department</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.department_name, option.department_name, false, false);
                var option2 = new Option(option.department_name, option.department_name, false, false);
                $('#add-department').append(option1);
                $('#edit-department').append(option2);
            });

            $('#add-department').trigger('change'); 
            $('#edit-department').trigger('change'); 
            $('#add-department').attr('disabled',false);
            $('#edit-department').attr('disabled',false);

            getPositionFilter();
        }
    }).fail(showEmployeeManagementLoadError);
}


function getPositionFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-position-filter");

    $.ajax({
        url: 'controller/EmployeeController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#add-position').attr('disabled',true);
            $('#edit-position').attr('disabled',true);
            $('#add-position').empty();
            $('#edit-position').empty();
        },
        success: function (response) { 
            $('#add-position').append(`<option value="" disabled selected>Select Position</option>`);
            $('#edit-position').append(`<option value="" disabled selected>Select Position</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.position_name, option.position_name, false, false);
                var option2 = new Option(option.position_name, option.position_name, false, false);
                $('#add-position').append(option1);
                $('#edit-position').append(option2);
            });

            $('#add-position').trigger('change'); 
            $('#edit-position').trigger('change'); 
            $('#add-position').attr('disabled',false);
            $('#edit-position').attr('disabled',false);

            getClientLocation();
        }
    }).fail(showEmployeeManagementLoadError);
}


function importData() {
    $('#importModal').modal('show');
}

$("#importModal").on('hidden.bs.modal', function (e) {	
    $('#readingFileStatus').html("");
    $('#tableOutput').html("");
    $('#dataType').prop('disabled', false);
    $('#fileUploader').prop('disabled', false);
    $('#import-client').prop('disabled', false).val('');
    document.getElementById('fileUploader').value= null;
});

function getEmployeeList() {    
    let dailySalary = "<th>Daily Salary</th>";
    let actionCol = "<th class='notexport'>Action</th>";
    if(access_level == "4"){
        dailySalary = "";
        actionCol = "";
    }
    var table = `<table id="emp_mgmnt_tbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th class='text-center notexport'></th>
                        ${actionCol}
                        <th>Employee Ident</th>
                        <th>Old Employee Ident</th>
                        <th>Payroll Employee ID</th>
                        <th>Full Name</th>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Hire Date</th>
                        <th>Separation Date</th>
                        <th>Present Address</th>
                        <th>Permanent Address</th>
                        <th>Contact Number</th>
                        <th>Email Address</th>
                        <th>Birthday</th>
                        <th>Birth Place</th>
                        <th>Gender</th>
                        <th>Civil Status</th>
                        <th>Nationality</th>
                        <th>Emergency Person</th>
                        <th>Emergency Contact Number</th>
                        <th>Client Date</th>
                        <th>Position</th>
                        <th>Client</th>
                        <th>Branch</th>
                        <th>Client Location</th>
                        <th>Department</th>
                        <th>TIN</th>
                        <th>SSS</th>
                        <th>PHILHEALTH</th>
                        <th>PAG-IBIG</th>
                        <th>Bank Account Number</th>
                        ${dailySalary}
                        <th>Bank Name</th>
                        <th>Insurance</th>
                        <th>Annual Leaves</th>
                        <th>Employee Type</th>
                        <th>Pay Type</th>
                      </tr>
                    </thead>
                </table>`;
    
        let employee = $("#employee").val();
        let client = $("#client").val();
        let clientLocation = $("#client-location-filter").val();
        let branch = $("#branch").val();
        let branchTxt = $("#branch option:selected").text();

        let formdata = new FormData();
        formdata.append("request", "get-employee-list");
        formdata.append("employee", employee);
        formdata.append("client", client);
        formdata.append("client_location", clientLocation);
        formdata.append("branch", branch);

        $.ajax({
            url: 'controller/EmployeeController.php',
            data: formdata,
            type: 'POST',
            contentType: false,
            processData: false,
            beforeSend: function( xhr ) {
                $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
            },
            success: function(response){
                $('#table_container').html('');
                $('#table_container').html(table);

                fileName = "Employee_Management";
                if(employee.trim() != ''){
                    fileName += "_" + employee;
                }

                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(clientLocation != null && clientLocation != ''){
                    fileName += "_" + clientLocation;
                }

                if(branch != null && branch != ''){
                    fileName += "_" + branchTxt;
                }

                let buttonsArray = [
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
                            columns: ':not(.notexport)'
                        },
                        customize: function (xlsx) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            var targetColumns = ["D", "E", "F", "H", "J", "L", "N", "O", "P", "Q", "U", "V", "W", "X", "Z", 
                                "AA", "AB", "AC", "AD", "AE", "AH", "AI"];
                        
                            // Apply style 17 (Light Green) ONLY to the first row (headers)
                            targetColumns.forEach(function (col) {
                                $('row[r="1"] c[r^="' + col + '"]', sheet).attr('s', '17');
                            });
                        }
                    }
                ];

                if(Number(access_level) === 2){
                    buttonsArray.unshift({
                        text: '<i class="bx bx-upload"></i> Upload',
                        className: 'btn btn-sm btn-outline-primary',
                        action: function () {
                            importData();
                        }
                    });
                }

                if(access_level == 1){

                    buttonsArray = [
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
                                format: {
                                    header: function (data, column) {
                                        return data; 
                                    }
                                },
                                columns: ':not(.notexport)'
                            },
                            customize: function (xlsx) {
                                var sheet = xlsx.xl.worksheets['sheet1.xml'];
                                var targetColumns = ["D", "E", "F", "H", "J", "L", "N", "O", "P", "Q", "U", "V", "W", "X", "Z", 
                                    "AA", "AB", "AC", "AD", "AE", "AH", "AI"];
                            
                                // Apply style 17 (Light Green) ONLY to the first row (headers)
                                targetColumns.forEach(function (col) {
                                    $('row[r="1"] c[r^="' + col + '"]', sheet).attr('s', '17');
                                });
                            }
                        },
                        {
                            text: '<i class="bx bx-trash"></i> Delete Record',
                            className: 'btn btn-sm btn-outline-danger',
                            action: function (e, dt, node, config) {
                                bulkDelete();            
                            }
                        }
                    ];
                    
                    $('#emp_mgmnt_tbl').DataTable().destroy();
                    empTbl = $('#emp_mgmnt_tbl').DataTable({
                        data: response.data,
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
                        columnDefs: [
                        {
                            orderable: false,
                            render: DataTable.render.select(),
                            targets: 0
                        }
                        ],
                            select: {
                            style: 'multi',
                        },
                        buttons: buttonsArray
                    });
                    handleDataIssueHandoff(empTbl);
                }else{
                    $('#emp_mgmnt_tbl').DataTable().destroy();
                    empTbl = $('#emp_mgmnt_tbl').DataTable({
                        data: response.data,
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
                        columnDefs: [
                            {
                                orderable: false,
                                render: DataTable.render.select(),
                                targets: 0
                            }
                        ],
                        rowCallback: function(row, data) {
                            $(row).find('td:eq(0) input[type="checkbox"]').prop('disabled', true);
                        },                
                        buttons: buttonsArray
                    });
                    handleDataIssueHandoff(empTbl);
                }
            }
            
        }).fail(showEmployeeManagementLoadError);
}

function showEmployeeManagementLoadError(xhr, textStatus, errorThrown) {
    let responseText = xhr && xhr.responseText ? xhr.responseText.trim() : "";
    let message = "Please refresh the page and try again.";

    try {
        const parsed = JSON.parse(responseText);
        message = parsed.error || message;
    } catch (e) {}

    $('#table_container').html(
        `<div class="alert alert-danger mb-0" role="alert">
            Unable to load Employee Management data.<br>
            <small>HTTP ${xhr ? xhr.status : "unknown"} (${textStatus || "unknown"})</small><br>
            <small>${$('<div>').text(message).html()}</small>
        </div>`
    );

    console.error("Employee Management request failed:", {
        status: xhr ? xhr.status : null,
        textStatus,
        errorThrown,
        responseText
    });
}

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}
