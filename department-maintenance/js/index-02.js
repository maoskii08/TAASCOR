
let id = null;

document.getElementById('saveBtn').addEventListener("click", saveChanges);
document.getElementById('addBtn').addEventListener("click", addDepartment);


$( document ).ready(function() {
    getDepartmentList();
});



$(document).on("click","#departmentTbl #updateBtn",function() {
    var row = $(this).closest('tr');

    id = $(this).val();
    var department_name = row.find('td:eq(1)').text();

    $('#edit-department-name').val(department_name);
    $('#editModal').modal("show");

});


function addDepartment(){
    let department_name = $('#add-department-name').val();

    if(department_name.trim() == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "add-department");
    formdata.append("department_name", department_name);

    $.ajax({
        url: 'controller/DepartmentController.php',
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
                    title: 'Successfully Added Department!'       
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
    let department_name = $('#edit-department-name').val();

    if(department_name.trim() == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "update-department");
    formdata.append("id", id);
    formdata.append("department_name", department_name);

    $.ajax({
        url: 'controller/DepartmentController.php',
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



// $(document).on("click","#branchTbl #deleteBtn",function() {
//     id = $(this).val();
    
//     Swal.fire({
//         title: 'Are you sure you want to terminate this branch?', 
//         html: 'Click Yes to proceed.',
//         icon: 'warning',  
//         showCancelButton: true,
//         confirmButtonText: `Yes`,
//         denyButtonText: `Cancel`,
//     }).then((result) => {
//         if (result.value) {

//             let formdata = new FormData();
//             formdata.append("request", 'delete-client');
//             formdata.append("id", id);
            
//             $.ajax({
//                 url: 'controller/BranchController.php',
//                 type: 'POST',
//                 data: formdata,
//                 dataType: 'json',
//                 processing: true, 
//                 contentType: false,
//                 processData: false
//                 })
//                 .done(function (response) {

//                     if(response.success == 1){

//                         swal.fire({
//                             icon: 'success',   
//                             title: 'Successfully Deleted Branch! '        
//                         }).then(function (result) {
//                             window.location.reload()
//                         });

//                     }else{

//                         swal.fire({
//                             icon: 'error',   
//                             title: 'Something went wrong!',                 
//                             text: "Error Message: " + response.error + ""               
//                         }).then(function (result) {
//                             window.location.reload()
//                         });
//                     }
                
//                 })
//                 .fail(function (response) {
//                     swal.fire({
//                         icon: 'error',   
//                         title: 'Something went wrong!',                 
//                         text: "Error Message: " + response.error + ""               
//                     }).then(function (result) {
//                         window.location.reload()
//                     });
//                 });
//         } 
//     })
// });


function getDepartmentList() {    
    var table = `<table id="departmentTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Action</th>
                        <th>Department Name</th>
                      </tr>
                    </thead>
                </table>`;

        let formdata = new FormData();
        formdata.append("request", "get-department-list");

        $.ajax({
            url: 'controller/DepartmentController.php',
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
    
                $('#departmentTbl').DataTable().destroy();
                $('#departmentTbl').DataTable({
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


