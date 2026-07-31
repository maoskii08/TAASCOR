
let id = null;

document.getElementById('saveBtn').addEventListener("click", saveChanges);
document.getElementById('addBtn').addEventListener("click", addPayDay);


$( document ).ready(function() {
    getClientFilter();
});


$("#add-cut-off").change(function() {
    let cut_off = $(this).val();
    if(cut_off == "Weekly"){
        $("#add-pay-day").val(null).trigger("change");
        $("#add-pay-container").hide();
    }else{
        $("#add-pay-container").show();
    }
});


$("#edit-cut-off").change(function() {
    let cut_off = $(this).val();
    if(cut_off == "Weekly"){
        $("#edit-pay-day").val(null).trigger("change");
        $("#edit-pay-container").hide();
    }else{
        $("#edit-pay-container").show();
    }
});


$(document).on("click","#payTbl #updateBtn",function() {
    var row = $(this).closest('tr');

    id = $(this).val();
    var client_name = row.find('td:eq(1)').text();
    var cut_off = row.find('td:eq(2)').text();
    var pay_day = row.find('td:eq(3)').text();

    $('#edit-client-name').val(client_name).trigger('change');
    $('#edit-cut-off').val(cut_off).trigger('change');
    $('#edit-pay-day').val(pay_day).trigger('change');

    $('#edit-client-name').prop('disabled', true); 

    $('#editModal').modal("show");

});


function addPayDay(){
    let client_name = $('#add-client-name').val();
    let cut_off = $('#add-cut-off').val();
    let pay_day = $('#add-pay-day').val();

    if(client_name == null || cut_off == null || (pay_day == null && cut_off != 'Weekly')){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "add-payday");
    formdata.append("client_name", client_name);
    formdata.append("cut_off", cut_off);
    formdata.append("pay_day", pay_day);

    $.ajax({
        url: 'controller/PayController.php',
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
                window.location.reload();
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

function saveChanges(){
    let client_name = $('#edit-client-name').val();
    let cut_off = $('#edit-cut-off').val();
    let pay_day = $('#edit-pay-day').val();

    if(client_name == null || cut_off == null || (pay_day == null && cut_off != 'Weekly')){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "update-payday");
    formdata.append("id", id);
    formdata.append("client_name", client_name);
    formdata.append("cut_off", cut_off);
    formdata.append("pay_day", pay_day);

    $.ajax({
        url: 'controller/PayController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#saveBtn').html('Saving... <i class="fa fa-spinner fa-spin"></i>');
            $('#saveBtn').attr('disabled',true);
        },
        success: function (response) { 
            $("#editModal").modal('hide');
            if(response.success == 1){
                window.location.reload();
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
            $("#editModal").modal('hide');
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



$(document).on("click","#branchTbl #deleteBtn",function() {
    id = $(this).val();
    
    Swal.fire({
        title: 'Are you sure you want to terminate this branch?', 
        html: 'Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        if (result.value) {

            let formdata = new FormData();
            formdata.append("request", 'delete-client');
            formdata.append("id", id);
            
            $.ajax({
                url: 'controller/BranchController.php',
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
                            title: 'Successfully Deleted Branch! '        
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


function getPayDayList() {    
    var table = `<table id="payTbl" class="dt-complex-header table table-bordered table-sm nowrap" style="width:100%">
                    <thead>
                      <tr>
                        <th>Action</th>
                        <th>Client Name</th>
                        <th>Cut Off</th>
                        <th>Pay Day</th>
                      </tr>
                    </thead>
                </table>`;

        let formdata = new FormData();
        formdata.append("request", "get-pay-list");

        $.ajax({
            url: 'controller/PayController.php',
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
    
                $('#payTbl').DataTable().destroy();
                $('#payTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: false,
                    paging: true,
                    searching: true,
                    ordering: false,
                    info: true,
                    scrollX: true,
                });
            }
            
        });
}


function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/PayController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#add-client-name').attr('disabled',true);
            $('#edit-client-name').attr('disabled',true);
            $('#add-client-name').empty();
            $('#edit-client-name').empty();
        },
        success: function (response) { 
            $('#add-client-name').append(`<option value="" disabled selected>Select Client</option>`);
            $('#edit-client-name').append(`<option value="" disabled selected>Select Client</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_name, false, false);
                var option2 = new Option(option.client_name, option.client_name, false, false);
                $('#add-client-name').append(option1);
                $('#edit-client-name').append(option2);
            });

            $('#add-client-name').trigger('change'); 
            $('#edit-client-name').trigger('change'); 
            $('#add-client-name').attr('disabled',false);
            $('#edit-client-name').attr('disabled',false);

            getPayDayList();
        }
    });
}

