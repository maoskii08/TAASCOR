

document.getElementById('signUpBtn').addEventListener("click", signUpUser);

$( document ).ready(function() {
    getClientLocation()
});

$("#department").change(function() {
    let role = $(this).val();
    if(role == 4){
        $("#client_container").show();
        $('#client_location').trigger('change'); 
    }else{
        $("#client_location").val(null).trigger("change");
        $("#client_container").hide();
    }
});

function getClientLocation(){

    let formdata = new FormData();
    formdata.append("request", "get-client-location");

    $.ajax({
        url: 'controller/SignUpController.php',
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
                var option1 = new Option(option.client_location, option.client_id, false, false);
                $('#client_location').append(option1);
            });

            $('#client_location').trigger('change'); 
            $('#client_location').attr('disabled',false);
        }
    });
}
function signUpUser(){

    let username = $('#username').val();
    let password = $('#password').val();
    let firstname = $('#firstname').val();
    let lastname = $('#lastname').val();
    let email = $('#email').val();
    let access_level = $('#department').val();
    let access_description = $("#department option:selected").text();
    let client_location = $('#client_location').val();

    console.log(client_location)
    if(username.trim() == "" || password.trim() == "" || 
        firstname.trim() == "" || lastname.trim() == "" ||
        email.trim() == "" || access_level == null ||
        (client_location.length == 0 && access_level == 4)){
        swal({
            icon: 'info',   
            title: 'Required Fields',                 
            text: "Please complete the required fields"               
        });
        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "sign-up-user");
    formdata.append("username", username);
    formdata.append("password", password);
    formdata.append("firstname", firstname);
    formdata.append("lastname", lastname);
    formdata.append("email", email);
    formdata.append("access_level", access_level);
    formdata.append("access_description", access_description);
    formdata.append("client_location", client_location.join(","));

    $.ajax({
        url: 'controller/SignUpController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#signUpBtn').html('Signing Up... <i class="fa fa-spinner fa-spin"></i>');
            $('#signUpBtn').attr('disabled',true);
        },
        success: function (response) { 
            if(response.success == 1){
                swal({
                    icon: 'success',   
                    title: "Thank you for signing up!",   
                    text: "Please wait for the email once your account is activated."  
                }).then(function (result) {
                    window.location.reload()
                });

            }else if(response.success == 2){
                swal({
                    icon: 'info',   
                    title: "Username already exists",   
                    text: "Please enter a new username"  
                })
            }else{

                swal({
                    icon: 'error',   
                    title: 'Something went wrong!',                 
                    text: "Error Message: " + response.error + ""               
                }).then(function (result) {
                    window.location.reload()
                });
            }
        },
        error: function(response) { // if error occured
            swal({
                icon: 'error',   
                title: 'Something went wrong!',                 
                text: "Error Message: " + response.error + ""               
            }).then(function (result) {
                window.location.reload()
            });
        }
    });
}