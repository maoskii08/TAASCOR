let fileName = "DTR";
let access_level = $('#access_level').val();
let payrollDetails = [];
let employeeID = null;
let validPayDates = false;
let file_data = 
        {
            "columns" : ['Payroll Employee ID', 'Employee ID','Employee Full Name','Daily Salary','Days Worked', 'Absent', 'Lates (Min)','Undertime','Vacation Leave','Sick Leave',
                        'Overtime', 'Night Differential', 'Night Differential OT', 'Regular Holiday','Regular Holiday OT', 'Regular Holiday Night Diff', 'Regular Holiday ND OT',
                        'Special Holiday', 'Special Holiday OT', 'Special Holiday Night Diff', 'Special Holiday ND OT', 'Rest Day', 'Rest Day OT', 'Rest Day Night Diff', 'Rest Day ND OT',
                        'Rest Day - Regular Holiday','Rest Day - Regular Holiday OT', 'Rest Day - Regular Holiday Night Diff', 'Rest Day - Regular Holiday ND OT', 'Rest Day - Special Holiday', 
                        'Rest Day - Special Holiday OT','Rest Day - Special Holiday Night Diff', 'Rest Day - Special Holiday ND OT',]
            ,"date" : []
        };

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getDTRList);
document.getElementById('saveChanges').addEventListener("click", saveChanges);

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

$("#payDay").change(function() {
    let pay_day = $(this).val();
    if(pay_day == "m"){
        $("#m-start-date").val('');
        $("#m-end-date").val('');
        $("#m-pay-date").val('');
        $("#dateModal").modal("show");
    }    
});

$("#proceedBtn").click(function() {
    let client = $("#client").val();
    let m_start_date = $("#m-start-date").val();
    let m_end_date = $("#m-end-date").val();
    let m_pay_date = $("#m-pay-date").val();

    let cut_start = new Date(m_start_date);
    let cut_end = new Date(m_end_date);
    let pay_date = new Date(m_pay_date);

    if (cut_end < cut_start || pay_date < cut_end) {
        swal.fire({
            icon: 'info',   
            title: 'Invalid Sequence of Dates!',       
            text: 'Please make sure the sequence of the start to end to pay date is correct'
        });

        validPayDates = false;

        return false;
    }

    let cut_start_day = cut_start.getDate();
    let cut_end_day = cut_end.getDate();
    let pay_day = pay_date.getDate();
    let cut_off = cut_start_day + '-' + cut_end_day;

    let diffInDays = (cut_end - cut_start) / (1000 * 60 * 60 * 24);

    if(diffInDays >= 30){
        swal.fire({
            icon: 'info',   
            title: 'Dates Interval',       
            text: 'The dates selected is more than 30 days'
        });

        $('#tblDiv').hide();
        $('#tblDiv2').hide();
        payrollDetails = [];

        return false;
    }

    if(diffInDays <= 7){
        cut_off = "Weekly";
    }

    let formdata = new FormData();
    formdata.append("request", "validate-manual-dates");
    formdata.append("client", client);
    formdata.append("cut_off", cut_off);
    formdata.append("pay_day", pay_day);

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#saveBtn').html('Validating... <i class="fa fa-spinner fa-spin"></i>');
            $('#saveBtn').attr('disabled',true);
        },
        success: function (response) { 
            if(!response.exists){
                swal.fire({
                    icon: 'warning',   
                    title: 'Incorrect Cut Off and Pay Dates',                 
                    text: "Please ensure that the dates you entered are aligned with the existing cut off and pay date"               
                })
        
                validPayDates = false;
                return false;
            }

            $('#saveBtn').html('Proceed');
            $('#saveBtn').attr('disabled',false);
            $("#dateModal").modal("hide");

            validPayDates = true;

            swal.fire({
                icon: 'success',   
                title: 'Dates are set. Click the Filter Button'       
            });
        }
    });
});

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#clientLocation').val(null).trigger('change');
    $('#tblDiv').hide();
    $('#tblDiv2').hide();
    payrollDetails = [];
    // getClientFilter();
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

$(document).on("click","#dtrTbl #removeBenBtn",function() {
    employeeID = $(this).val();
    let pay_day = payrollDetails[0][2];

    Swal.fire({
        title: 'Are you sure you want to remove the govt contributions for employee - '+employeeID+'?', 
        html: 'Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        if (result.value) {
            let formdata = new FormData();
            formdata.append("request", 'remove-govt-benefits');
            formdata.append("employee_ident", employeeID);
            formdata.append("pay_day", pay_day);
            
            $.ajax({
                url: 'controller/DTRController.php',
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
                            title: 'Successfully Removed Govt Contributions! '        
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


function deleteDTRUpload(branch, client_location, branch_txt, client_location_txt) {
    let client_name = payrollDetails[0][0];
    let pay_day = payrollDetails[0][2];

    let message = '<strong>' + client_name + '<br>';
    message += formatDate(pay_day) + '</strong><br>';
    if(branch != null){
        message += '<strong>' + branch_txt + '</strong><br>';
    }
    if(client_location != null){
        message += '<strong>' + client_location_txt + '</strong><br>';
    }
    message += 'Click Yes to proceed.';

    Swal.fire({
        title: 'Are you sure you want to remove all the records?', 
        html: message,
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        if (result.value) {
            let formdata = new FormData();
            formdata.append("request", 'delete-dtr-upload');
            formdata.append("client_name", client_name);
            formdata.append("pay_day", pay_day);
            formdata.append("branch", branch);
            formdata.append("client_location", client_location);
            
            $.ajax({
                url: 'controller/DTRController.php',
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
                            title: 'Successfully Deleted DTR! '        
                        }).then(function (result) {
                            getDTRList()
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
};

$(document).on("click","#dtrTbl #deleteRecord",function() {
    employeeID = $(this).val();
    let pay_day = payrollDetails[0][2];

    Swal.fire({
        title: 'Are you sure you want to remove the DTR for employee - '+employeeID+'?', 
        html: 'Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        if (result.value) {
            let formdata = new FormData();
            formdata.append("request", 'delete-employee-dtr');
            formdata.append("employee_ident", employeeID);
            formdata.append("pay_day", pay_day);
            
            $.ajax({
                url: 'controller/DTRController.php',
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
                            title: 'Successfully Deleted DTR! '        
                        }).then(function (result) {
                            getDTRList()
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


$(document).on("click","#dtrTbl #updateBtn",function() {
    var row = $(this).closest('tr');

    employeeID = $(this).val();
    var employee_id = row.find('td:eq(2)').text();
    var employee_full_name = row.find('td:eq(3)').text();
    var daily_salary = row.find('td:eq(4)').text();
    var days_worked = row.find('td:eq(5)').text();
    var absent = row.find('td:eq(6)').text();
    var lates = row.find('td:eq(7)').text();
    var undertime = row.find('td:eq(8)').text();
    var vacation_leave = row.find('td:eq(9)').text();
    var sick_leave = row.find('td:eq(10)').text();
    var overtime = row.find('td:eq(11)').text();
    var night_diff = row.find('td:eq(12)').text();
    var night_diff_ot = row.find('td:eq(13)').text();
    var regular_holiday = row.find('td:eq(14)').text();
    var regular_holiday_ot = row.find('td:eq(15)').text();
    var regular_holiday_night_diff = row.find('td:eq(16)').text();
    var regular_holiday_nd_ot = row.find('td:eq(17)').text();
    var special_holiday = row.find('td:eq(18)').text();
    var special_holiday_ot = row.find('td:eq(19)').text();
    var special_holiday_night_diff = row.find('td:eq(20)').text();
    var special_holiday_nd_ot = row.find('td:eq(21)').text();
    var rest_day = row.find('td:eq(22)').text();
    var rest_day_ot = row.find('td:eq(23)').text();
    var rest_day_night_diff = row.find('td:eq(24)').text();
    var rest_day_nd_ot = row.find('td:eq(25)').text();
    var rd_regular_holiday = row.find('td:eq(26)').text();
    var rd_regular_holiday_ot = row.find('td:eq(27)').text();
    var rd_regular_holiday_night_diff = row.find('td:eq(28)').text();
    var rd_regular_holiday_nd_ot = row.find('td:eq(29)').text();
    var rd_special_holiday = row.find('td:eq(30)').text();
    var rd_special_holiday_ot = row.find('td:eq(31)').text();
    var rd_special_holiday_night_diff = row.find('td:eq(32)').text();
    var rd_special_holiday_nd_ot = row.find('td:eq(33)').text();
    

    $('#employee-ident').val(employee_id);
    $('#employee-full-name').val(employee_full_name);
    $('#daily-salary').val(daily_salary);
    $('#days-worked').val(days_worked);
    $('#absent').val(absent);
    $('#lates').val(lates);
    $('#undertime').val(undertime);
    $('#vacation-leave').val(vacation_leave);
    $('#sick-leave').val(sick_leave);
    $('#overtime').val(overtime);
    $('#night-diff').val(night_diff);
    $('#night-diff-ot').val(night_diff_ot);
    $('#regular-holiday').val(regular_holiday);
    $('#regular-holiday-ot').val(regular_holiday_ot);
    $('#regular-holiday-night-diff').val(regular_holiday_night_diff);
    $('#regular-holiday-nd-ot').val(regular_holiday_nd_ot);
    $('#special-holiday').val(special_holiday);
    $('#special-holiday-ot').val(special_holiday_ot);
    $('#special-holiday-night-diff').val(special_holiday_night_diff);
    $('#special-holiday-nd-ot').val(special_holiday_nd_ot);
    $('#rest-day').val(rest_day);
    $('#rest-day-ot').val(rest_day_ot);
    $('#rest-day-night-diff').val(rest_day_night_diff);
    $('#rest-day-nd-ot').val(rest_day_nd_ot);
    $('#rd-regular-holiday').val(rd_regular_holiday);
    $('#rd-regular-holiday-ot').val(rd_regular_holiday_ot);
    $('#rd-regular-holiday-night-diff').val(rd_regular_holiday_night_diff);
    $('#rd-regular-holiday-nd-ot').val(rd_regular_holiday_nd_ot);
    $('#rd-special-holiday').val(rd_special_holiday);
    $('#rd-special-holiday-ot').val(rd_special_holiday_ot);
    $('#rd-special-holiday-night-diff').val(rd_special_holiday_night_diff);
    $('#rd-special-holiday-nd-ot').val(rd_special_holiday_nd_ot);
    $('#editDTRModal').modal("show");

});


function saveChanges(){
    let daily_salary = $('#daily-salary').val();
    let days_worked = $('#days-worked').val();
    let absent = $('#absent').val();
    let lates = $('#lates').val();
    let undertime = $('#undertime').val();
    let vacation_leave = $('#vacation-leave').val();
    let sick_leave = $('#sick-leave').val();
    let overtime = $('#overtime').val();
    let night_diff = $('#night-diff').val();
    let night_diff_ot = $('#night-diff-ot').val();
    let regular_holiday = $('#regular-holiday').val();
    let regular_holiday_ot = $('#regular-holiday-ot').val();
    let regular_holiday_night_diff = $('#regular-holiday-night-diff').val();
    let special_holiday = $('#special-holiday').val();
    let special_holiday_ot = $('#special-holiday-ot').val();
    let special_holiday_night_diff = $('#special-holiday-night-diff').val();
    let rest_day = $('#rest-day').val();
    let rest_day_ot =  $('#rest-day-ot').val();
    let rest_day_night_diff =  $('#rest-day-night-diff').val();
    let rd_regular_holiday = $('#rd-regular-holiday').val();
    let rd_regular_holiday_ot = $('#rd-regular-holiday-ot').val();
    let rd_regular_holiday_night_diff = $('#rd-regular-holiday-night-diff').val();
    let rd_special_holiday = $('#rd-special-holiday').val();
    let rd_special_holiday_ot = $('#rd-special-holiday-ot').val();
    let rd_special_holiday_night_diff = $('#rd-special-holiday-night-diff').val();

    let regular_holiday_nd_ot = $('#regular-holiday-nd-ot').val();
    let special_holiday_nd_ot = $('#special-holiday-nd-ot').val();
    let rest_day_nd_ot = $('#rest-day-nd-ot').val();
    let rd_regular_holiday_nd_ot = $('#rd-regular-holiday-nd-ot').val();
    let rd_special_holiday_nd_ot = $('#rd-special-holiday-nd-ot').val();
    
    if(daily_salary == '' || days_worked == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    if(days_worked > 16){
        swal.fire({
            icon: 'info',   
            title: 'Days Worked!',       
            text: 'Worked Days is more than 16'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "update-dtr");
    formdata.append("employee_ident", employeeID);
    formdata.append("daily_salary", daily_salary);
    formdata.append("days_worked", days_worked);
    formdata.append("absent", absent);
    formdata.append("lates", lates);
    formdata.append("undertime", undertime);
    formdata.append("vacation_leave", vacation_leave);
    formdata.append("sick_leave", sick_leave);
    formdata.append("overtime", overtime);
    formdata.append("night_diff", night_diff);
    formdata.append("night_diff_ot", night_diff_ot);
    formdata.append("regular_holiday", regular_holiday);
    formdata.append("regular_holiday_ot", regular_holiday_ot);
    formdata.append("regular_holiday_night_diff", regular_holiday_night_diff);
    formdata.append("special_holiday", special_holiday);
    formdata.append("special_holiday_ot", special_holiday_ot);
    formdata.append("special_holiday_night_diff", special_holiday_night_diff);
    formdata.append("rest_day", rest_day);
    formdata.append("rest_day_ot", rest_day_ot);
    formdata.append("rest_day_night_diff", rest_day_night_diff);
    formdata.append("rd_regular_holiday", rd_regular_holiday);
    formdata.append("rd_regular_holiday_ot", rd_regular_holiday_ot);
    formdata.append("rd_regular_holiday_night_diff", rd_regular_holiday_night_diff);
    formdata.append("rd_special_holiday", rd_special_holiday);
    formdata.append("rd_special_holiday_ot", rd_special_holiday_ot);
    formdata.append("rd_special_holiday_night_diff", rd_special_holiday_night_diff);
    formdata.append("regular_holiday_nd_ot", regular_holiday_nd_ot );
    formdata.append("special_holiday_nd_ot", special_holiday_nd_ot);
    formdata.append("rest_day_nd_ot", rest_day_nd_ot);
    formdata.append("rd_regular_holiday_nd_ot", rd_regular_holiday_nd_ot);
    formdata.append("rd_special_holiday_nd_ot", rd_special_holiday_nd_ot);
    for(let i=0; i < payrollDetails.length; i++) {
        formdata.append("client_name", payrollDetails[i][0]);
        formdata.append("cut_off", payrollDetails[i][1]);
        formdata.append("pay_day", payrollDetails[i][2]);
        formdata.append("start_date", payrollDetails[i][3]);
        formdata.append("end_date", payrollDetails[i][4]);
    }

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#saveChanges').html('Saving... <i class="fa fa-spinner fa-spin"></i>');
            $('#saveChanges').attr('disabled',true);
        },
        success: function (response) { 
            $("#editDTRModal").modal('hide');
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Saved Changes! '        
                }).then(function (result) {
                    getDTRList();
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

            $('#saveChanges').html('Save Changes');
            $('#saveChanges').attr('disabled',false);
        },
        error: function(response) { // if error occured
            $("#editDTRModal").modal('hide');
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

function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/DTRController.php',
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
        url: 'controller/DTRController.php',
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
            $('#payDay').append(`<option value="m">Manual</option>`);

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
        url: 'controller/DTRController.php',
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
        url: 'controller/DTRController.php',
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

function getDTRList() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let branch = $("#branch").val();
    let branch_txt = $("#branch option:selected").text();
    let client_location = $("#clientLocation").val();
    let client_location_txt = $("#clientLocation option:selected").text();
    let start_date = $("#payDay option:selected").data("sd");
    let end_date = $("#payDay option:selected").data("ed");
    let cut_off = $("#payDay option:selected").data("co");

    let m_start_date = $("#m-start-date").val();
    let m_end_date = $("#m-end-date").val();
    let m_pay_date = $("#m-pay-date").val();

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

    if(pay_day == "m"){
        if(!validPayDates){
            swal.fire({
                icon: 'info',   
                title: 'Incorrect Manual Encode Dates',       
                text: 'Please correct the dates first'
            });
    
            $('#tblDiv').hide();
            $('#tblDiv2').hide();
            payrollDetails = [];
    
            return false;
        }

        if(m_start_date == '' || m_end_date == '' || m_pay_date == ''){
            swal.fire({
                icon: 'info',   
                title: 'Required Fields!',       
                text: 'Missing details on manual input of pay day details'
            });
    
            $('#tblDiv').hide();
            $('#tblDiv2').hide();
            payrollDetails = [];
    
            return false;
        }else{
            let cut_start = new Date(m_start_date);
            let cut_start_day = cut_start.getDate();
    
            let cut_end = new Date(m_end_date);
            let cut_end_day = cut_end.getDate();

            let diffInDays = (cut_end - cut_start) / (1000 * 60 * 60 * 24);

            start_date = m_start_date;
            end_date = m_end_date;
            pay_day = m_pay_date;
            cut_off = cut_start_day + '-' + cut_end_day;

            if(diffInDays >= 30){
                swal.fire({
                    icon: 'info',   
                    title: 'Dates Interval',       
                    text: 'The dates selected is more than 30 days'
                });
        
                $('#tblDiv').hide();
                $('#tblDiv2').hide();
                payrollDetails = [];
        
                return false;
            }

            if(diffInDays <= 7){
                cut_off = "Weekly";
            }
        }
    }

    let formdata = new FormData();
    formdata.append("request", "get-dtr-list");
    formdata.append("client", client);
    formdata.append("pay_day", pay_day);
    formdata.append("start_date", start_date);
    formdata.append("end_date", end_date);
    formdata.append("cut_off", cut_off);
    formdata.append("client_location", client_location);
    formdata.append("branch", branch);

    $.ajax({
        url: 'controller/DTRController.php',
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

                fileName = "DTR";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }


                buttonArray = [
                    {
                        text: '<i class="bx bx-trash"></i> Delete All Records',
                        className: 'btn btn-sm btn-outline-danger',
                        action: function (e, dt, node, config) {
                            deleteDTRUpload(branch,client_location,branch_txt,client_location_txt);            
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
                            columns: function (idx, data, node) {
                                let excludeColumns = [0]; 
                                return excludeColumns.indexOf(idx) === -1; 
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
                                columns: function (idx, data, node) {
                                    let excludeColumns = [0]; 
                                    return excludeColumns.indexOf(idx) === -1; 
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
                    Client: <a class="alert-link me-3">${client}</a>
                    Cut Off: <a class="alert-link me-3">${formatDate(start_date)} to ${formatDate(end_date)}</a>
                    Pay Day: <a class="alert-link">${formatDate(pay_day)}</a>
                </div>
                <table id="dtrTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th class="text-center">Action</th>
                        <th>Payroll Employee ID</th>
                        <th>Employee ID</th>
                        <th>Employee Full Name</th>
                        <th>Daily Salary</th>
                        <th>Days Worked</th>
                        <th>Absent</th>
                        <th>Lates (Min)</th>
                        <th>Undertime</th>
                        <th>Vacation Leave</th>
                        <th>Sick Leave</th>
                        <th>Overtime</th>
                        <th>Night Differential</th>
                        <th>Night Differential OT</th>
                        <th>Regular Holiday</th>
                        <th>Regular Holiday OT</th>
                        <th>Regular Holiday Night Diff</th>
                        <th>Regular Holiday ND OT</th>
                        <th>Special Holiday</th>
                        <th>Special Holiday OT</th>
                        <th>Special Holiday Night Diff</th>
                        <th>Special Holiday ND OT</th>
                        <th>Rest Day</th>
                        <th>Rest Day OT</th>
                        <th>Rest Day Night Diff</th>
                        <th>Rest Day ND OT</th>
                        <th>Rest Day - Regular Holiday</th>
                        <th>Rest Day - Regular Holiday OT</th>
                        <th>Rest Day - Regular Holiday Night Diff</th>
                        <th>Rest Day - Regular Holiday ND OT</th>
                        <th>Rest Day - Special Holiday</th>
                        <th>Rest Day - Special Holiday OT</th>
                        <th>Rest Day - Special Holiday Night Diff</th>
                        <th>Rest Day - Special Holiday ND OT</th>
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

                var table = `
                <table id="dtrTbl2" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Employee ID</th>
                        <th>Employee Full Name</th>
                        <th>Basic Pay</th>
                        <th>Lates (Min)</th>
                        <th>Undertime</th>
                        <th>Vacation Leave</th>
                        <th>Sick Leave</th>
                        <th>Overtime</th>
                        <th>Night Differential</th>
                        <th>Night Differential OT</th>
                        <th>Regular Holiday</th>
                        <th>Regular Holiday OT</th>
                        <th>Regular Holiday Night Diff</th>
                        <th>Regular Holiday ND OT</th>
                        <th>Special Holiday</th>
                        <th>Special Holiday OT</th>
                        <th>Special Holiday Night Diff</th>
                        <th>Special Holiday ND OT</th>
                        <th>Rest Day</th>
                        <th>Rest Day OT</th>
                        <th>Rest Day Night Diff</th>
                        <th>Rest Day ND OT</th>
                        <th>Rest Day - Regular Holiday</th>
                        <th>Rest Day - Regular Holiday OT</th>
                        <th>Rest Day - Regular Holiday Night Diff</th>
                        <th>Rest Day - Regular Holiday ND OT</th>
                        <th>Rest Day - Special Holiday</th>
                        <th>Rest Day - Special Holiday OT</th>
                        <th>Rest Day - Special Holiday Night Diff</th>
                        <th>Rest Day - Special Holiday ND OT</th>
                      </tr>
                    </thead>
                </table>`;

                $('#table_container2').html('');
                $('#table_container2').html(table);

                fileName = "DTR_Amounts";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }

                $('#dtrTbl2').DataTable().destroy();
                $('#dtrTbl2').DataTable({
                    data: response.data3,
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
                                }
                            }
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

function formatDateToYYYYMMDD(dateStr) {
    let parts = dateStr.split("/");
    return parts[2] + "-" + parts[0].padStart(2, '0') + "-" + parts[1].padStart(2, '0');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}


