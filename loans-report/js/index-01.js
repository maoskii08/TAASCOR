let loan_type = null;
let loan_date = '';
document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getLoanList);

$( document ).ready(function() {
    getLoanType();
});

function clearFilter() {
    $('#loanDate').val('');
    $('#loanType').val(null).trigger('change');
    loan_type = null;
    loan_date = '';
    $('#tblDiv').hide();
}

function downloadReport() {
    let url = "report.php?lt="+loan_type+"&ld="+loan_date+"-01";
    window.open(url, '_blank')
}

function getLoanType(){
    
    let formdata = new FormData();
    formdata.append("request", "get-loan-type");

    $.ajax({
        url: 'controller/LoanController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#loanType').attr('disabled',true);
            $('#loanType').empty();
        },
        success: function (response) { 
            $('#loanType').append(`<option value="" disabled selected>Select Loan Type</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(option.loan_type, option.loan_type, false, false);
                $('#loanType').append(dataOption);
            });

            $('#loanType').trigger('change'); 
            $('#loanType').attr('disabled',false);
        }
    });
}


function getLoanList() {    
    loan_date = $('#loanDate').val();
    loan_type = $('#loanType').val();

    if(loan_date == '' || loan_type == null){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please select loan type and date'
        });

        $('#tblDiv').hide();

        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "get-loan-list");
    formdata.append("loan_date", loan_date);
    formdata.append("loan_type", loan_type);

    $.ajax({
        url: 'controller/LoanController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        beforeSend: function(xhr) {
            $("#tblDiv").show();
            $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
        },
        success: function(response){
            if(response.success == 1){
                var table = `<table id="loanTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Employee Full Name</th>
                        <th>Date Awarded</th>
                        <th>1st Cut-Off</th>
                        <th>2nd Cut-off</th>
                        <th>Collected This Month</th>
                      </tr>
                    </thead>
                </table>`;

                $('#table_container').html('');
                $('#table_container').html(table);

                $('#loanTbl').DataTable().destroy();
                $('#loanTbl').DataTable({
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
                        // { 
                        //     extend: 'excel', 
                        //     title: null,
                        //     className: 'btn btn-sm btn-outline-secondary',
                        //     text: '<i class="bx bx-download"></i> Download',
                        //     filename: "Loans",
                        //     exportOptions: {
                        //         format: {
                        //             header: function (data, column) {
                        //                 return data; 
                        //             }
                        //         }
                        //     }
                        // },
                        {
                            text: '<i class="bx bx-download"></i> Download Report',
                            className: 'btn btn-sm btn-outline-primary',
                            action: function (e, dt, node, config) {
                                downloadReport();      
                            }
                        }
                    ]
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


