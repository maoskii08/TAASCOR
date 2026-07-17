
$( document ).ready(function() {
    getPayrollSummary();
});


function getPayrollSummary() {    
    var table = `<table id="payrollTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                        <tr>
                        <th>Client</th>
                        <th>Total Basic Pay</th>
                        <th>Total OT</th>
                        <th>Total Leaves</th>
                        <th>Total Other Additional</th>
                        <th>Total Gross Income</th>
                        <th>Total Taxable</th>
                        <th>Total Tax</th>
                        <th>Total Tardy</th>
                        <th>Total Employee SSS</th>
                        <th>Total Employee SSS MPF</th>
                        <th>Total Employee Philhealth</th>
                        <th>Total Employee Pag-ibig</th>
                        <th>Total Employee Loan</th>
                        <th>Total Other Deduction</th>
                        <th>Total Net Pay</th>
                        <th>Total 13th Month</th>
                        <th>Total Employer SSS</th>
                        <th>Total Employer SSS MPF</th>
                        <th>Total Employer SSS EC</th>
                        <th>Total Employer Philhealth</th>
                        <th>Total Employer Pag-ibig</th>
                        </tr>
                    </thead>
                </table>`;

        let formdata = new FormData();
        formdata.append("request", "get-payroll-summary");

        $.ajax({
            url: 'controller/PayrollController.php',
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
    
                $('#payrollTbl').DataTable().destroy();
                $('#payrollTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: false,
                    paging: true,
                    searching: true,
                    ordering: false,
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
                            filename: "Payroll Summary",
                            exportOptions: {
                                format: {
                                    header: function (data, column) {
                                        return data; 
                                    }
                                },
                            },
                        }
                    ]
                });
            }
            
        });
}


