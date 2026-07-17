

$( document ).ready(function() {
    getIncompleteDetails();
});



function getIncompleteDetails() {    
    var table = `<table id="incompleteTbl" class="dt-complex-header table table-bordered table-sm nowrap" style="width:100%">
                    <thead>
                      <tr>
                        <th>Employee Ident</th>
                        <th>Full Name</th>
                        <th>Branch Name</th>
                        <th>Client Name</th>
                        <th>Client Location</th>
                        <th>Philhealth</th>
                        <th>Cleaned Philhealth</th>
                      </tr>
                    </thead>
                </table>`;

        let formdata = new FormData();
        formdata.append("request", "get-incomplete-list");

        $.ajax({
            url: 'controller/DataController.php',
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
    
                $('#incompleteTbl').DataTable().destroy();
                $('#incompleteTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: false,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
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
                                filename: 'Duplicate Philhealth',
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


