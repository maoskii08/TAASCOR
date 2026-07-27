let employee_id = null;
let access_level = $('#access_level').val();

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getEmployeeList);

$( document ).ready(function() {
    getClientFilter();
});

$("#branch").change(function() {
    let branch_selected = $(this).val();
    if(branch_selected != null){
        getClientBranch(branch_selected);
    }    
});

function clearFilter() {
    $('#client').val('').trigger('change');
    $('#branch').val('').trigger('change');
    $('#employee').val('');

    getClientFilter();
}


$(document).on("click","#emp_mgmnt_tbl #restoreBtn",function() {
    let employee = $(this).val();
    
    Swal.fire({
        title: 'Are you sure you want to restore this employee - '+employee+'?', 
        html: 'Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        
        /* Read more about isConfirmed, isDenied below */
        if (result.value) {

            let formdata = new FormData();
            formdata.append("request", 'restore-employee');
            formdata.append("employee", employee);
            
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
                            title: 'Successfully Restored Employee!'   
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
});


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

            getBranchFilter();
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
            $('#branch').empty();
        },
        success: function (response) { 
            $('#branch').append(`<option value="" disabled selected>Select Branch</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.branch_name, option.branch_id, false, false);
                $('#branch').append(option1);
            });

            $('#branch').trigger('change'); 
            $('#branch').attr('disabled',false);


            getEmployeeList();
        }
    });
}



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
                        ${actionCol}
                        <th>Employee Ident</th>
                        <th>Record Status</th>
                        <th>Terminated / Removed On</th>
                        <th>Old Employee Ident</th>
                        <th>Payroll Employee ID</th>
                        <th>Full Name</th>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Hire Date</th>
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
    
                $('#emp_mgmnt_tbl').DataTable().destroy();
                $('#emp_mgmnt_tbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: false,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    scrollX: true                    
                });
            }
            
        });
}

