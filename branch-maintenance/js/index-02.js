
let id = null;

document.getElementById('saveBtn').addEventListener("click", saveChanges);
document.getElementById('addBtn').addEventListener("click", addBranch);


$( document ).ready(function() {
    getBranchList();
});



$(document).on("click","#branchTbl #updateBtn",function() {
    var row = $(this).closest('tr');

    id = $(this).val();
    var branch_name = row.find('td:eq(1)').text();

    $('#edit-branch-name').val(branch_name);
    $('#editModal').modal("show");

});


function addBranch(){
    let branch_name = $('#add-branch-name').val();

    if(branch_name.trim() == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "add-branch");
    formdata.append("branch_name", branch_name);

    $.ajax({
        url: 'controller/BranchController.php',
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
                    title: 'Successfully Added Branch!'       
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

function saveChanges(){
    let branch_name = $('#edit-branch-name').val();

    if(branch_name.trim() == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "update-branch");
    formdata.append("id", id);
    formdata.append("branch_name", branch_name);

    $.ajax({
        url: 'controller/BranchController.php',
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
                swal.fire({
                    icon: 'success',   
                    title: 'Successfully Saved Changes!'       
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
            formdata.append("request", 'delete-branch');
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


function getBranchList() {    
    var table = `<table id="branchTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Action</th>
                        <th>Branch Name</th>
                      </tr>
                    </thead>
                </table>`;

        let formdata = new FormData();
        formdata.append("request", "get-branch-list");

        $.ajax({
            url: 'controller/BranchController.php',
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
    
                $('#branchTbl').DataTable().destroy();
                $('#branchTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: false,
                    paging: true,
                    searching: true,
                    ordering: false,
                    info: true,
                    scrollX: true,
                    // layout: {
                    // topStart: 'buttons',
                    // }
                });
            }
            
        });
}


