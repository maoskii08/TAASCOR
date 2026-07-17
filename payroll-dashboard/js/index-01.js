let client_selected = null;

$( document ).ready(function() {
    getClientFilter();
});

$("#client").change(function() {
    client_selected = $(this).val();
    if(client_selected != null){
        $("#monthYearDiv").hide();
        $("#payrollMonth").val('').trigger('change');
        $("#payrollYear").val('').trigger('change');
        getPayDay();
    }    
});

$("#payrollMonth").change(function() {
    let payroll_month = $(this).val();
    let payroll_year = $("#payrollYear").val();
    if(payroll_month != '' && payroll_year != ''){
        getNetPay();
    }    
});


$("#payrollYear").change(function() {
    let payroll_month = $("#payrollMonth").val();
    let payroll_year = $(this).val();
    if(payroll_month != '' && payroll_year != ''){
        getNetPay();
    }    
});

function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/DashboardController.php',
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
                var options = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(options);
            });

            $('#client').trigger('change'); 
            $('#client').attr('disabled',false);
        }
    });
}


function getPayDay(){
    
    let formdata = new FormData();
    formdata.append("request", "get-pay-day");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#payrollPeriodTxt').text('');
            $('#payrollDateTxt').text('');
        },
        success: function (response) { 
            let payroll_start = formatDate(response.data[0].start_date);
            let payroll_end = formatDate(response.data[0].end_date);
            let payroll_date = formatDate(response.data[0].pay_date);
            let payrollText = payroll_start + " <i class='bx bx-right-arrow-alt'></i> " + payroll_end;
            $('#payrollPeriodTxt').html(payrollText);
            $('#payrollDateTxt').text(payroll_date);

            getNetPay();
        }
    });
}


function getNetPay(){
    let payroll_month = $("#payrollMonth").val();
    let payroll_year = $("#payrollYear").val();

    let formdata = new FormData();
    formdata.append("request", "get-net-pay");
    formdata.append("client_selected", client_selected);
    formdata.append("payroll_month", payroll_month);
    formdata.append("payroll_year", payroll_year);

    console.log(payroll_month) 

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#currentNetPay').text('');
            $('#prevNetPay').text('');
        },
        success: function (response) { 
            let current_net_pay = response.data.current_net_pay;
            let previous_net_pay = response.data.previous_net_pay;

            if (current_net_pay > previous_net_pay) {
                $("#trendingArrow").removeClass("bx-trending-down");
                $("#trendingArrow").addClass("bx-trending-up");
            }else{
                $("#trendingArrow").removeClass("bx-trending-up");
                $("#trendingArrow").addClass("bx-trending-down");
            }

            $("#currentNetPay").text(current_net_pay);
            $("#prevNetPay").text(previous_net_pay);

            $("#monthYearDiv").show();
        }
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

