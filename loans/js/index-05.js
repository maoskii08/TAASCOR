let monthly = true;
let id = null;
document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getLoanList);
document.getElementById('empSearchBtn').addEventListener("click", searchEmployee);
document.getElementById('addLoanBtn').addEventListener("click", addLoan);
document.getElementById('editLoanBtn').addEventListener("click", updateLoan);

$( document ).ready(function() {
    getLoanType();
});

function clearFilter() {
    $('#employee').val('');
    $('#loanType').val(null).trigger('change');
    getLoanList();
}

function getLoanType(){
    
    let formdata = new FormData();
    formdata.append("request", "get-loan-type");

    $.ajax({
        url: 'controller/LoanController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#loanType').attr('disabled',true);
            $('#loanType').empty();
        },
        success: function (response) { 
            $('#loanType').append(`<option value="" disabled selected>Select Loan Type</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(option.loan_type, option.loan_type, false, false);
                $('#loanType').append(dataOption);
            });

            $('#loanType').trigger('change'); 
            $('#loanType').attr('disabled',false);
            getLoanList();
        }
    });
}

$(document).on("click","#loanTbl #updateBtn",function() {
    var row = $(this).closest('tr');

    id = $(this).val();

    var employee_id = row.find('td:eq(1)').text();
    var employee_full_name = row.find('td:eq(2)').text();
    var loan_type = row.find('td:eq(3)').text();
    var loan_date = row.find('td:eq(4)').text();
    var start_payment = row.find('td:eq(5)').text();
    var loan_amount = row.find('td:eq(6)').text();
    var interest_amount = row.find('td:eq(7)').text();
    var monthly_amortization = row.find('td:eq(8)').text();
    var beginning_payment = row.find('td:eq(9)').text();
    var system_payment = row.find('td:eq(10)').text();
    var loan_balance = row.find('td:eq(11)').text();
    var stop_payment = row.find('td:eq(12)').text();
    var reactivation_date = row.find('td:eq(13)').text();
    var remarks = row.find('td:eq(14)').text();
    var week1 = row.find('td:eq(15)').text();
    var week2 = row.find('td:eq(16)').text();
    var week3 = row.find('td:eq(17)').text();
    var week4 = row.find('td:eq(18)').text();

    let stop = '0';
    if(stop_payment == 'Yes'){
        stop = '1';
    }

    $('#edit-employee-ident').val(employee_id);
    $('#edit-full-name').val(employee_full_name);
    $('#edit-loan-type').val(loan_type).trigger('change');
    $('#edit-loan-date').val(loan_date);
    $('#edit-start-payment').val(start_payment.replace(/,/g, ''));
    $('#edit-loan-amount').val(loan_amount.replace(/,/g, ''));
    $('#edit-interest-amount').val(interest_amount.replace(/,/g, ''));
    $('#edit-beginning-payment').val(beginning_payment.replace(/,/g, ''));
    $('#edit-system-payment').val(system_payment.replace(/,/g, ''));
    $('#edit-loan-balance').val(loan_balance.replace(/,/g, ''));
    $('#edit-stop-payment').val(stop).trigger('change');
    $('#edit-reactivation-date').val(reactivation_date);
    $('#edit-remarks').val(remarks);

    if(Number(monthly_amortization.replace(/,/g, '')) > 0){
        $('#edit-monthly-amortization').val(monthly_amortization.replace(/,/g, ''));
        $('#edit-monthly-container').show();
        $('#edit-weekly-container').hide();
        monthly = true;
    }else{
        $('#edit-week1-amortization').val(week1.replace(/,/g, ''));
        $('#edit-week2-amortization').val(week2.replace(/,/g, ''));
        $('#edit-week3-amortization').val(week3.replace(/,/g, ''));
        $('#edit-week4-amortization').val(week4.replace(/,/g, ''));
        $('#edit-monthly-container').hide();
        $('#edit-weekly-container').show();
        monthly = false;
    }

    $('#editModal').modal('show');

});


function addLoan(){

    let employee_ident = $('#add-employee-ident').val();
    let employee_full_name = $('#add-full-name').val();
    let loan_type = $('#add-loan-type').val();
    let loan_date = $('#add-loan-date').val();
    let start_payment = $('#add-start-payment').val();
    let loan_amount = $('#add-loan-amount').val();
    let interest_amount = $('#add-interest-amount').val();
    let beginning_payment = $('#add-beginning-payment').val();
    let system_payment = $('#add-system-payment').val();
    let loan_balance = $('#add-loan-balance').val();
    let stop_payment = $('#add-stop-payment').val();
    let reactivation_date = $('#add-reactivation-date').val();
    let remarks = $('#add-remarks').val();
    let monthly_amortization = $('#add-monthly-amortization').val();
    let week1_amortization = $('#add-week1-amortization').val();
    let week2_amortization = $('#add-week2-amortization').val();
    let week3_amortization = $('#add-week3-amortization').val();
    let week4_amortization = $('#add-week4-amortization').val();

    if(employee_ident == '' || employee_full_name == '' ||
        loan_type == null || loan_date == '' || start_payment == '' ||
        start_payment == '' || loan_amount == '' || interest_amount == '' ||
        interest_amount == '' || beginning_payment == '' || system_payment == '' ||
        loan_balance == '' ||
        (monthly && monthly_amortization == '') || 
        (!monthly && (week1_amortization == '' && week2_amortization == '' &&
            week3_amortization == '' && week4_amortization == ''
        ))
    ){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "add-loan");
    formdata.append("employee_ident", employee_ident);
    formdata.append("employee_full_name", employee_full_name);
    formdata.append("loan_type", loan_type);
    formdata.append("loan_date", loan_date);
    formdata.append("start_payment", start_payment);
    formdata.append("loan_amount", loan_amount);
    formdata.append("interest_amount", interest_amount);
    formdata.append("beginning_payment", beginning_payment);
    formdata.append("system_payment", system_payment);
    formdata.append("loan_balance", loan_balance);
    formdata.append("stop_payment", stop_payment);
    formdata.append("reactivation_date", reactivation_date);
    formdata.append("remarks", remarks);
    formdata.append("monthly_amortization", monthly_amortization);
    formdata.append("week1_amortization", week1_amortization);
    formdata.append("week2_amortization", week2_amortization);
    formdata.append("week3_amortization", week3_amortization);
    formdata.append("week4_amortization", week4_amortization);

    $.ajax({
        url: 'controller/LoanController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#addLoanBtn').html('Adding Loan... <i class="fa fa-spinner fa-spin"></i>');
            $('#addLoanBtn').attr('disabled',true);
        },
        success: function (response) { 
            $("#addModal").modal('hide');
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Added Loan! '        
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
            $("#addModal").modal('hide');
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

function updateLoan(){

    let employee_ident = $('#edit-employee-ident').val();
    let employee_full_name = $('#edit-full-name').val();
    let loan_type = $('#edit-loan-type').val();
    let loan_date = $('#edit-loan-date').val();
    let start_payment = $('#edit-start-payment').val();
    let loan_amount = $('#edit-loan-amount').val();
    let interest_amount = $('#edit-interest-amount').val();
    let beginning_payment = $('#edit-beginning-payment').val();
    let system_payment = $('#edit-system-payment').val();
    let loan_balance = $('#edit-loan-balance').val();
    let stop_payment = $('#edit-stop-payment').val();
    let reactivation_date = $('#edit-reactivation-date').val();
    let remarks = $('#edit-remarks').val();
    let monthly_amortization = $('#edit-monthly-amortization').val();
    let week1_amortization = $('#edit-week1-amortization').val();
    let week2_amortization = $('#edit-week2-amortization').val();
    let week3_amortization = $('#edit-week3-amortization').val();
    let week4_amortization = $('#edit-week4-amortization').val();

    if(employee_ident == '' || employee_full_name == '' ||
        loan_type == null || loan_date == '' || start_payment == '' ||
        start_payment == '' || loan_amount == '' || interest_amount == '' ||
        interest_amount == '' || beginning_payment == '' || system_payment == '' ||
        loan_balance == '' ||
        (monthly && monthly_amortization == '') || 
        (!monthly && (week1_amortization == '' && week2_amortization == '' &&
            week3_amortization == '' && week4_amortization == ''
        ))
    ){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }
    let formdata = new FormData();
    formdata.append("request", "update-loan");
    formdata.append("id", id);
    formdata.append("employee_ident", employee_ident);
    formdata.append("employee_full_name", employee_full_name);
    formdata.append("loan_type", loan_type);
    formdata.append("loan_date", loan_date);
    formdata.append("start_payment", start_payment);
    formdata.append("loan_amount", loan_amount);
    formdata.append("interest_amount", interest_amount);
    formdata.append("beginning_payment", beginning_payment);
    formdata.append("system_payment", system_payment);
    formdata.append("loan_balance", loan_balance);
    formdata.append("stop_payment", stop_payment);
    formdata.append("reactivation_date", reactivation_date);
    formdata.append("remarks", remarks);
    formdata.append("monthly_amortization", monthly_amortization);
    formdata.append("week1_amortization", week1_amortization);
    formdata.append("week2_amortization", week2_amortization);
    formdata.append("week3_amortization", week3_amortization);
    formdata.append("week4_amortization", week4_amortization);

    $.ajax({
        url: 'controller/LoanController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#editLoanBtn').html('Saving... <i class="fa fa-spinner fa-spin"></i>');
            $('#editLoanBtn').attr('disabled',true);
        },
        success: function (response) { 
            $("#editModal").modal('hide');
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Updated Loan! '        
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
            $("#editLoanBtn").modal('hide');
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

function searchEmployee(){
    
    let employee_ident = $('#add-employee-ident').val();
    
    if(employee_ident.trim() == ''){
        swal.fire({
            title: 'REQUIRED!',
            text: 'Please input employee ident',
            icon: 'info'
        });
        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "search-employee");
    formdata.append("employee_ident", employee_ident);

    $.ajax({
        url: 'controller/LoanController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#empSearchBtn').html('Searching... <i class="fa fa-spinner fa-spin"></i>');
            $('#empSearchBtn').attr('disabled',true);
        },
        success: function (response) { 
            if(response.success == 1){
                for (i = 0; i < response.data.length; i++) {
                    let data = response.data[i];
                    $('#add-full-name').val(data.employee_full_name)
                }
                monthly = response.monthly
                if(monthly){
                    $("#monthly-container").show();
                    $("#weekly-container").hide();
                    $('#add-week1-amortization').val('');
                    $('#add-week2-amortization').val('');
                    $('#add-week3-amortization').val('');
                    $('#add-week4-amortization').val('');
                }else{
                    $("#monthly-container").hide();
                    $("#weekly-container").show();
                    $('#add-monthly-amortization').val('');
                }
            }else if(response.success == 2){
                swal.fire({
                    title: 'NOT FOUND!',
                    text: 'Employee information does not exist. Please contact HR Team for confirmation.',
                    icon: 'info'
                });

                $("#monthly-container").hide();
                $("#weekly-container").hide();
            }else if(response.success == 3){
                swal.fire({
                    title: 'No Pay Day!',
                    text: 'Employee Client has no pay day. Please contact Payroll Team for confirmation.',
                    icon: 'info'
                });

                $("#monthly-container").hide();
                $("#weekly-container").hide();

                $('#add-monthly-amortization').val('');
                $('#add-week1-amortization').val('');
                $('#add-week2-amortization').val('');
                $('#add-week3-amortization').val('');
                $('#add-week4-amortization').val('');
            }else{
                swal.fire({
                    icon: 'error',   
                    title: 'Something went wrong!',                 
                    text: "Error Message: " + response.error + ""               
                }).then(function (result) {
                    window.location.reload()
                });
            }

            $('#empSearchBtn').attr('disabled',false);
            $('#empSearchBtn').html('Search');
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


function getLoanList() {    
    let employee = $('#employee').val();
    let loan_type = $('#loanType').val();

    let formdata = new FormData();
    formdata.append("request", "get-loan-list");
    formdata.append("employee", employee);
    formdata.append("loan_type", loan_type);

    $.ajax({
        url: 'controller/LoanController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        beforeSend: function(xhr) {
            $("#tblDiv").show();
            $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
        },
        success: function(response){
            if(response.success == 1){
                var table = `<table id="loanTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Action</th>
                        <th>Employee ID</th>
                        <th>Employee Full Name</th>
                        <th>Loan Type</th>
                        <th>Loan Date</th>
                        <th>Start Payment Date</th>
                        <th>Loan Amount</th>
                        <th>Interest Amount</th>
                        <th>Monthly Amortization</th>
                        <th>Beginning Payment</th>
                        <th>In System Payment</th>
                        <th>Loan Running Balance</th>
                        <th>Stop Payment</th>
                        <th>Reactivation Date</th>
                        <th>Reactivation Remarks</th>
                        <th>Week 1 Amortization</th>
                        <th>Week 2 Amortization</th>
                        <th>Week 3 Amortization</th>
                        <th>Week 4 Amortization</th>
                      </tr>
                    </thead>
                </table>`;

                $('#table_container').html('');
                $('#table_container').html(table);

                $('#loanTbl').DataTable().destroy();
                $('#loanTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: false,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
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
                            filename: "Loans",
                            exportOptions: {
                                format: {
                                    header: function (data, column) {
                                        return data; 
                                    }
                                }
                            }
                        },
                        {
                            text: '<i class="bx bx-plus"></i> Add Loan',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                $('#addModal').modal('show');      
                            }
                        }
                    ]
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

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}


