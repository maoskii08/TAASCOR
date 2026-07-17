let fileName = "DTR";
let access_level = $('#access_level').val();
let payrollDetails = [];
let employeeArray = [];
let file_data = 
        {
            "columns" : ['Employee ID','Employee Full Name','Amount','Type of Deduction']
            ,"date" : []
        };

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getDeductionList);
document.getElementById('addBtn').addEventListener("click", individualAdditional);

$( document ).ready(function() {
    importExcel();
    getClientFilter();
});

$("#client").change(function() {
    let client_selected = $(this).val();
    if(client_selected != null){
        getPayDay(client_selected);
    }    
});

$("#add-employee-id").change(function() {
    let employee_id = $(this).val();
    if(employee_id != ''){
        let employeeMap = new Map(employeeArray.map(e => [e[0], e]));
        let employee = employeeMap.get(Number(employee_id));
        if (employee) {
            $("#add-employee-name").val(employee[1]);
        }else{
            swal.fire({
                icon: 'info',   
                title: 'Invalid Employee ID',                 
                text: "Please input the correct employee id"               
            });
        }
    }    
});

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#tblDiv').hide();
    payrollDetails = [];
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


function individualAdditional(){
    let employee_id = $('#add-employee-id').val();
    let employee_name = $('#add-employee-name').val();
    let amount = $('#add-amount').val();
    let type_of_deduction = $('#add-type-of-deduction').val();

    if(employee_id == '' || amount == '' || type_of_deduction.trim() == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the all fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "add-individual");
    formdata.append("employee_id", employee_id);
    formdata.append("employee_name", employee_name);
    formdata.append("amount", amount);
    formdata.append("type_of_deduction", type_of_deduction);
    for(let i=0; i < payrollDetails.length; i++) {
        formdata.append("client_name", payrollDetails[i][0]);
        formdata.append("cut_off", payrollDetails[i][1]);
        formdata.append("pay_day", payrollDetails[i][2]);
        formdata.append("start_date", payrollDetails[i][2]);
        formdata.append("end_date", payrollDetails[i][2]);
    }

    $.ajax({
        url: 'controller/DeductionController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#addBtn').html('Saving... <i class="fa fa-spinner fa-spin"></i>');
            $('#addBtn').attr('disabled',true);
        },
        success: function (response) { 
            $("#addModal").modal('hide');
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Added!'       
                }).then(function (result) {
                    getDeductionList();
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


$(document).on("click","#dtrTbl #deleteBtn",function() {
    let id = $(this).val();

    var row = $(this).closest('tr');
    var employee_id = row.find('td:eq(0)').text();
    
    Swal.fire({
        title: 'Are you sure you want to delete this deduction?', 
        html: 'Click Yes to proceed.',
        icon: 'question',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        
        /* Read more about isConfirmed, isDenied below */
        if (result.value) {

            let formdata = new FormData();
            formdata.append("request", 'delete');
            formdata.append("id", id);
            formdata.append("employee_id", employee_id);
            for(let i=0; i < payrollDetails.length; i++) {
                formdata.append("client_name", payrollDetails[i][0]);
                formdata.append("cut_off", payrollDetails[i][1]);
                formdata.append("pay_day", payrollDetails[i][2]);
            }
            
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

                        swal.fire({
                            icon: 'success',   
                            title: 'Successfully Deleted Deduction! '        
                        }).then(function (result) {
                            getDeductionList()
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
        url: 'controller/DeductionController.php',
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
        url: 'controller/DeductionController.php',
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
        url: 'controller/DeductionController.php',
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

function getDeductionList() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let client_location = $("#clientLocation").val();
    let start_date = $("#payDay option:selected").data("sd");
    let end_date = $("#payDay option:selected").data("ed");
    let cut_off = $("#payDay option:selected").data("co");
    let branch = $("#branch").val();

    if(client == null || pay_day == null){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please select client and pay day'
        });

        $('#tblDiv').hide();
        payrollDetails = [];

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
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $("#tblDiv").show();
            $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
        },
        success: function(response){
            if(response.success == 1){
                if(response.data.length > 0){
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
                                    // Exclude the first column (index 0)
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
                        Client: <a class="alert-link me-3">${client}</a>
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

                    $('#dtrTbl').DataTable().destroy();
                    $('#dtrTbl').DataTable({
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
                        buttons: buttonArray
                    });

                    employeeArray = response.data;
                }else{
                    swal.fire({
                        icon: 'warning',   
                        title: 'No DTR Upload',                 
                        text: "Please go to DTR Upload Page First."               
                    }).then(function (result) {
                        window.location.reload()
                    });
                }                
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

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}


