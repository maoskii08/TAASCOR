let fileName = "DTR";
let access_level = $('#access_level').val();
let payrollDetails = [];
let bankData = [];
let pnbTotalAmount = 0;
let pnbTotalCount = 0;
let pnbPayDay = '';

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('payslipBtn').addEventListener("click", downloadPayslip);
document.getElementById('postBtn').addEventListener("click", postPayroll);
document.getElementById('filterBtn').addEventListener("click", getPayrollSummary);

$( document ).ready(function() {
    getClientFilter();
});

$("#client").change(function() {
    let client_selected = $(this).val();
    if(client_selected != null){
        getPayDay(client_selected);
    }    
});

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#payType').val(null).trigger('change');
    $('#bankName').val(null).trigger('change');
    $('#clientLocation').val(null).trigger('change');
    $('#tblDiv').hide();
    $('#tblDiv2').hide();
    payrollDetails = [];
}

function downloadPayslip() {
    if(payrollDetails.length > 0){
        let client_name = payrollDetails[0][0];
        let cut_off = payrollDetails[0][1];
        let pay_day = payrollDetails[0][2];
        let pay_type = payrollDetails[0][5];
        let bank_name = payrollDetails[0][6];
        let location = payrollDetails[0][7];
        let url = "payslip2.php?cn="+client_name+"&co="+cut_off+"&pd="+pay_day+"&pt="+pay_type+"&bn="+bank_name+"&cl="+location;
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

    let client_name = payrollDetails[0][0];
    let cut_off = payrollDetails[0][1];
    let pay_day = payrollDetails[0][2];
    let url = "payslip2.php?cn="+client_name+"&co="+cut_off+"&pd="+pay_day+"&ei="+employee_id;
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

            getPayType();
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
                console.log(response.data2)
                console.log(response.columns2)
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


