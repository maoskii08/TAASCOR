let employee_id = null;
let employee_id_array = [];
let empTbl = null;
let fileName = "Employee_Management";
let access_level = $('#access_level').val();
let file_data = 
        {
            "columns" : ['Employee Ident','Old Employee Ident', 'Payroll Employee ID', 'Full Name', 'Last Name', 'First Name', 'Middle Name', 'Hire Date', 
                        'Separation Date', 'Present Address', 'Permanent Address', 'Contact Number', 'Email Address', 'Birthday', 'Birth Place', 'Gender', 'Civil Status', 
                        'Nationality', 'Emergency Person', 'Emergency Contact Number', 'Client Date', 'Position', 'Client', 'Branch', 'Client Location', 'Department', 'TIN', 'SSS', 
                        'PHILHEALTH', 'PAG-IBIG', 'Bank Account Number', 'Daily Salary', 'Bank Name', 'Insurance', 'Annual Leaves', 'Employee Type', 'Pay Type'
                        ]
            ,"date" : ['Hire Date', 'Separation Date', 'Birthday', 'Client Date']
        };

document.getElementById('saveBtn').addEventListener("click", saveChanges);
document.getElementById('addBtn').addEventListener("click", addEmployee);
document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getEmployeeList);
document.getElementById('terminateBtn').addEventListener("click", terminateEmployee);

$( document ).ready(function() {
    if(access_level != "4"){
        document.getElementById("addEmployeeBtn").style.display = "block";
    }
    importExcel();
    getClientFilter();
    getDepartmentFilter();
});

$("#branch").change(function() {
    let branch_selected = $(this).val();
    if(branch_selected != null){
        getClientBranch(branch_selected);
    }    
});

function clearFilter() {
    $('#client').val(null).trigger('change');
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
        swal.fire({
            title: "No Selected Employee",
            text: "Please select employee to delete" ,
            icon: "info"
        });

        return false;
    }
    // console.log(employee_id_array)
    Swal.fire({
        title: 'Are you sure you want to delete this employees?', 
        html: 'Click Yes to proceed.',
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

                        swal.fire({
                            icon: 'success',   
                            title: 'Successfully Deleted Employees! '        
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
                
                })
                .fail(function (response) {
                    swal.fire({
                        icon: 'error',   
                        title: 'Something went wrong!',                 
                        text: "Error Message: " + response.error + ""               
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
    
    // if(full_name.trim() == '' || last_name.trim() == '' ||
    //     first_name.trim() == '' || client_location == null ||
    //     hire_date.trim() == '' || present_address.trim() == '' ||
    //     contact_number.trim() == '' || birthday.trim() == '' ||
    //     birth_place.trim() == '' || gender.trim() == '' ||
    //     civil_status.trim() == '' || branch == null ||
    //     client == null || position == null || 
    //     tin.trim() == '' || sss.trim() == '' ||
    //     philhealth.trim() == '' || pag_ibig.trim() == '' ||
    //     daily_salary.trim() == '' || bank_account_number.trim() == '' ||
    //     annual_leaves.trim() == '' || employee_type == null
    // ){
    //     swal.fire({
    //         icon: 'info',   
    //         title: 'Required Fields!',       
    //         text: 'Please complete the required fields.'
    //     });

    //     return false;
    // }

    let cleanedSSS = sss.replace(/[^0-9]/g, '');
    if (cleanedSSS.length != 10 && sss.trim() != '') {
        swal.fire({
            icon: 'info',   
            title: 'Invalid SSS Format',       
            text: 'Please correct the SSS in 10 Digits format only.'
        });

        return false;
    } 

    // if(daily_salary < 100 || daily_salary > 10000){
    //     swal.fire({
    //         icon: 'info',   
    //         title: 'Daily Salary Adjustments',       
    //         text: 'Daily Salary is less than 100 or greater than 10,000'
    //     });

    //     return false;
    // }

    let cleanedNumber = contact_number.replace(/[^0-9]/g, '');
    if (!/^\d{11}$/.test(cleanedNumber) && contact_number.trim() != '') {
        swal.fire({
            icon: 'info',   
            title: 'Invalid Contact Number',       
            text: 'Please correct the contact number into 11 digits format only.'
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
            $("#editEmployeeModal").modal('hide');
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Saved Changes! '        
                }).then(function (result) {
                    window.location.reload()
                });

            }else if(response.success == 2){
                console.log(response.dup)
                var dupTxt = "";
                for(let i=0; i < response.dup.length; i++){
                    dupTxt += response.dup[i] + '<br>';
                }
                swal.fire({
                    icon: 'warning',   
                    title: 'Duplicates!',
                    html: dupTxt
                })

                $('#saveBtn').html('Save Changes');
                $('#saveBtn').attr('disabled',false);

                return false;
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
            $("#editEmployeeModal").modal('hide');
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

    // if(full_name.trim() == '' || last_name.trim() == '' ||
    //     first_name.trim() == '' || client_location == null ||
    //     hire_date.trim() == '' || present_address.trim() == '' ||
    //     contact_number.trim() == '' || birthday.trim() == '' ||
    //     birth_place.trim() == '' || gender.trim() == '' ||
    //     civil_status.trim() == '' || branch == null ||
    //     client == null || position == null || 
    //     tin.trim() == '' || sss.trim() == '' ||
    //     philhealth.trim() == '' || pag_ibig.trim() == '' ||
    //     daily_salary.trim() == '' || bank_account_number.trim() == '' ||
    //     annual_leaves.trim() == '' || employee_type == null
    // ){
    //     swal.fire({
    //         icon: 'info',   
    //         title: 'Required Fields!',       
    //         text: 'Please complete the required fields.'
    //     });

    //     return false;
    // }

    let cleanedSSS = sss.replace(/[^0-9]/g, '');
    if (cleanedSSS.length != 10 && sss.trim() != '') {
        swal.fire({
            icon: 'info',   
            title: 'Invalid SSS Format',       
            text: 'Please correct the SSS in 10 Digits format only.'
        });

        return false;
    } 

    // if(daily_salary < 100 || daily_salary > 10000){
    //     swal.fire({
    //         icon: 'info',   
    //         title: 'Daily Salary Adjustments',       
    //         text: 'Daily Salary is less than 100 or greater than 10,000'
    //     });

    //     return false;
    // }

    let cleanedNumber = contact_number.replace(/[^0-9]/g, '');
    if (!/^\d{11}$/.test(cleanedNumber) && contact_number.trim() != '') {
        swal.fire({
            icon: 'info',   
            title: 'Invalid Contact Number',       
            text: 'Please correct the contact number into 11 digits format only.'
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
            $("#addEmployeeModal").modal('hide');
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Added Employee! '        
                }).then(function (result) {
                    window.location.reload()
                });

            }else if(response.success == 2){
                var dupTxt = "";
                for(let i=0; i < response.dup.length; i++){
                    dupTxt += response.dup[i] + '<br>';
                }
                swal.fire({
                    icon: 'warning',   
                    title: 'Duplicates!',
                    html: dupTxt
                })

                $('#addBtn').html('Add Employee');
                $('#addBtn').attr('disabled',false);
                return false;
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
            $("#addEmployeeModal").modal('hide');
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
                swal.fire({
                    icon: 'info',   
                    title: 'Required Fields!',       
                    text: 'Please enter separation date.'
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

                        swal.fire({
                            icon: 'success',   
                            title: 'Successfully Terminated Employee! '        
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
                
                })
                .fail(function (response) {
                    swal.fire({
                        icon: 'error',   
                        title: 'Something went wrong!',                 
                        text: "Error Message: " + response.error + ""               
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
            $('#client').empty();
            $('#add-client').empty();
            $('#edit-client').empty();
        },
        success: function (response) { 
            $('#client').append(`<option value="" disabled selected>Select Client</option>`);
            $('#add-client').append(`<option value="" disabled selected>Select Client</option>`);
            $('#edit-client').append(`<option value="" disabled selected>Select Client</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                var option2 = new Option(option.client_name, option.client_name, false, false);
                var option3 = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(option1);
                $('#add-client').append(option2);
                $('#edit-client').append(option3);
            });

            $('#client').trigger('change'); 
            $('#add-client').trigger('change'); 
            $('#edit-client').trigger('change'); 
            $('#client').attr('disabled',false);
            $('#add-client').attr('disabled',false);
            $('#edit-client').attr('disabled',false);

            getBranchFilter();
        }
    });
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
            $('#add-client-location').attr('disabled',true);
            $('#add-client-location').empty();
            $('#edit-client-location').attr('disabled',true);
            $('#edit-client-location').empty();
        },
        success: function (response) { 
            $('#add-client-location').append(`<option value="" disabled selected>Select Client Location</option>`);
            $('#edit-client-location').append(`<option value="" disabled selected>Select Client Location</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.location_name, option.location_name, false, false);
                var option2 = new Option(option.location_name, option.location_name, false, false);
                $('#add-client-location').append(option1);
                $('#edit-client-location').append(option2);
            });

            $('#add-client-location').trigger('change'); 
            $('#add-client-location').attr('disabled',false);

            $('#edit-client-location').trigger('change'); 
            $('#edit-client-location').attr('disabled',false);
        }
    });
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
            $('#client').append(`<option value="" disabled selected>Select Client Location</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(option1);
            });

            $('#client').trigger('change'); 
            $('#client').attr('disabled',false);
        }
    });
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
    });
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
    });
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
    });
}


function importData() {
    $('#importModal').modal('show');
}

$("#importModal").on('hidden.bs.modal', function (e) {	
    $('#readingFileStatus').html("");
    $('#tableOutput').html("");
    $('#dataType').prop('disabled', false);
    $('#fileUploader').prop('disabled', false);
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
        let branch = $("#branch").val();
        let branchTxt = $("#branch option:selected").text();

        let formdata = new FormData();
        formdata.append("request", "get-employee-list");
        formdata.append("employee", employee);
        formdata.append("client", client);
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
                }else{
                    $('#emp_mgmnt_tbl').DataTable().destroy();
                    $('#emp_mgmnt_tbl').DataTable({
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
                }
            }
            
        });
}

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}


