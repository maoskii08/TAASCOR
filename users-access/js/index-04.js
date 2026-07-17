
let id = null;
let is_active = null;

document.getElementById('saveBtn').addEventListener("click", saveChanges);

$( document ).ready(function() {
    getClientLocation();
});

$("#user-role").change(function() {
    let role = $(this).val();
    if(role == 4){
        $("#client_container").show();
    }else{
        $("#client_location").val(null).trigger("change");
        $("#client_container").hide();
    }
});


function getClientLocation(){

    let formdata = new FormData();
    formdata.append("request", "get-client-location");

    $.ajax({
        url: 'controller/UserController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#client_location').attr('disabled',true);
            $('#client_location').empty();
        },
        success: function (response) { 
            $('#client_location').append(`<option value="" disabled selected>Select Client</option>`);

            response.data.forEach(option => {
                var option1 = new Option(option.client_name, option.client_id, false, false);
                $('#client_location').append(option1);
            });

            $('#client_location').trigger('change'); 
            $('#client_location').attr('disabled',false);
        }
    });

    getUserList();
}

$(document).on("click","#userTbl #updateBtn",function() {
    var row = $(this).closest('tr');

    id = $(this).val();
    let access_level = $(this).data('al');
    let client_access = $(this).data('ci');
    is_active = $(this).data('ia');

    var user_name = row.find('td:eq(1)').text();
    var full_name = row.find('td:eq(2)').text();
    var email = row.find('td:eq(3)').text();

    console.log(client_access)

    $('#user-name').val(user_name);
    $('#full-name').val(full_name);
    $('#email').val(email);
    $('#user-role').val(access_level).trigger('change');
    if(access_level == 4){
        $('#client_location').val(String(client_access).split(",")).trigger('change');
    }

    if(is_active){
        $("#saveBtn").text("Save Changes");
    }else{
        $("#saveBtn").text("Activate Access");
    }
    

    $('#editUserModal').modal("show");

});


function saveChanges(){
    let full_name = $('#full-name').val();
    let email = $('#email').val();
    let user_role = $('#user-role').val();
    let user_role_txt = $("#user-role option:selected").text();
    let client_location = $('#client_location').val();

    let formdata = new FormData();
    formdata.append("request", "update-user");
    formdata.append("id", id);
    formdata.append("is_active", is_active);
    formdata.append("full_name", full_name);
    formdata.append("email", email);
    formdata.append("user_role", user_role);
    formdata.append("user_role_txt", user_role_txt);
    formdata.append("client", client_location.join(","));

    $.ajax({
        url: 'controller/UserController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#saveBtn').html('Processing... <i class="fa fa-spinner fa-spin"></i>');
            $('#saveBtn').attr('disabled',true);
        },
        success: function (response) { 
            $("#editUserModal").modal('hide');
            var title = "Successfully Activated Access!"
            if(is_active){
                title = "Successfully Saved Changes!"
            }
            if(response.success == 1){
                swal.fire({
                    icon: 'success',   
                    title: title       
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
            $("#editUserModal").modal('hide');
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



$(document).on("click","#userTbl #deleteBtn",function() {
    id = $(this).val();
    
    Swal.fire({
        title: 'Are you sure you want to terminate this user?', 
        html: 'Click Yes to proceed.',
        icon: 'warning',  
        showCancelButton: true,
        confirmButtonText: `Yes`,
        denyButtonText: `Cancel`,
    }).then((result) => {
        
        /* Read more about isConfirmed, isDenied below */
        if (result.value) {

            let formdata = new FormData();
            formdata.append("request", 'delete-user');
            formdata.append("id", id);
            
            $.ajax({
                url: 'controller/UserController.php',
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
                            title: 'Successfully Deleted User! '        
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


function getUserList() {    
    var table = `<table id="userTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Action</th>
                        <th>User Name</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>User Role</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                </table>`;

        let formdata = new FormData();
        formdata.append("request", "get-user-list");

        $.ajax({
            url: 'controller/UserController.php',
            data: formdata,
            type: 'POST',
            dataType: 'json',
            contentType: false,
            processData: false,
            beforeSend: function( xhr ) {
                $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
            },
            success: function(response){
                $('#table_container').html('');
                $('#table_container').html(table);

                $('#userTbl').DataTable().destroy();
                $('#userTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: true,
                    pageLength: 50,
                    lengthMenu: [[25, 50, 100, -1], ['25', '50', '100', 'All']],
                    paging: true,
                    searching: true,
                    ordering: true,
                    order: [[1, 'asc']],
                    info: true,
                    scrollX: true,
                });
            },
            error: function() {
                $('#table_container').html(`<center class="text-danger">Failed to load user list. Please refresh the page.</center>`);
            }

        });
}


