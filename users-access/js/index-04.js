
let id = null;
let is_active = null;

document.getElementById('saveBtn').addEventListener("click", saveChanges);
document.getElementById('openCreateUserBtn').addEventListener("click", openCreateUser);
document.getElementById('createUserBtn').addEventListener("click", createUser);

$( document ).ready(function() {
    getClientLocation();
});

$("#user-role").change(function() {
    let role = $(this).val();
    if(role && Number(role) >= 4){
        $("#client_container").show();
    }else{
        $("#client_location").val(null).trigger("change");
        $("#client_container").hide();
    }
});

$("#signup-role").change(function() {
    let role = $(this).val();
    if (role && Number(role) >= 4) {
        $("#signup-client-container").removeClass("d-none");
    } else {
        $("#signup-client-location").val(null).trigger("change");
        $("#signup-client-container").addClass("d-none");
    }
});

$(document).on("click", ".password-toggle", function() {
    const input = document.getElementById($(this).data("target"));
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    $(this).attr("aria-pressed", show ? "true" : "false")
        .attr("aria-label", show ? "Hide password" : "Show password")
        .find("i").toggleClass("bx-show", !show).toggleClass("bx-hide", show);
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
                var option2 = new Option(option.client_name, option.client_id, false, false);
                $('#signup-client-location').append(option2);
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

    $('#user-name').val(user_name);
    $('#full-name').val(full_name);
    $('#email').val(email);
    $('#user-role').val(access_level).trigger('change');
    if(Number(access_level) >= 4){
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
    let client_location = $('#client_location').val() || [];

    if (!full_name.trim() || !email.trim() || !user_role) {
        swal.fire({ icon: 'error', title: 'Complete all required user fields.' });
        return;
    }
    if (Number(user_role) >= 4 && client_location.length === 0) {
        swal.fire({ icon: 'error', title: 'Assign at least one client to Coordinator and C&B users.' });
        return;
    }

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

function openCreateUser() {
    $('#create-user-alert').addClass('d-none').text('');
    $('#signup-firstname, #signup-lastname, #signup-username, #signup-email, #signup-password, #signup-confirm-password').val('');
    $('#signup-role').val('').trigger('change');
    $('#signup-client-location').val(null).trigger('change');
    $('#createUserModal').modal('show');
}

function createUser() {
    const firstname = $('#signup-firstname').val().trim();
    const lastname = $('#signup-lastname').val().trim();
    const username = $('#signup-username').val().trim();
    const email = $('#signup-email').val().trim();
    const accessLevel = $('#signup-role').val();
    const clients = $('#signup-client-location').val() || [];
    const password = $('#signup-password').val();
    const confirmPassword = $('#signup-confirm-password').val();
    const alertBox = $('#create-user-alert');

    function fail(message) {
        alertBox.removeClass('d-none').text(message);
    }

    alertBox.addClass('d-none').text('');
    if (!firstname || !lastname || !username || !email || !accessLevel || !password || !confirmPassword) {
        fail('Complete all required fields.');
        return;
    }
    if (!/^[A-Za-z0-9._-]{3,64}$/.test(username)) {
        fail('Username must be 3 to 64 characters and use only letters, numbers, dots, underscores, or hyphens.');
        return;
    }
    if (Number(accessLevel) >= 4 && clients.length === 0) {
        fail('Assign at least one client to Coordinator and C&B users.');
        return;
    }
    if (password !== confirmPassword) {
        fail('Passwords do not match.');
        return;
    }
    if (password.length < 12 || !/[a-z]/.test(password) || !/[A-Z]/.test(password)
        || !/[0-9]/.test(password) || !/[^A-Za-z0-9]/.test(password)) {
        fail('Use at least 12 characters with uppercase, lowercase, number, and symbol.');
        return;
    }

    const formdata = new FormData();
    formdata.append('request', 'sign-up-user');
    formdata.append('firstname', firstname);
    formdata.append('lastname', lastname);
    formdata.append('username', username);
    formdata.append('email', email);
    formdata.append('access_level', accessLevel);
    formdata.append('client_location', clients.join(','));
    formdata.append('password', password);
    formdata.append('confirm_password', confirmPassword);

    $.ajax({
        url: '../login/controller/SignUpController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function() {
            $('#createUserBtn').prop('disabled', true)
                .html('Creating... <i class="fa fa-spinner fa-spin"></i>');
        }
    }).done(function(response) {
        if (response.success == 1) {
            $('#createUserModal').modal('hide');
            swal.fire({
                icon: 'success',
                title: 'Pending account created',
                text: response.message || 'Review the account in the user list, then activate it.'
            }).then(function() {
                getUserList();
            });
        } else {
            fail(response.error || 'The account could not be created.');
        }
    }).fail(function(xhr) {
        const response = xhr.responseJSON || {};
        fail(response.error || 'The account could not be created.');
    }).always(function() {
        $('#createUserBtn').prop('disabled', false)
            .html('<i class="bx bx-user-plus me-1"></i>Create Pending Account');
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
