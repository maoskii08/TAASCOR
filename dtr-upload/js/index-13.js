let fileName = "DTR";
let access_level = $('#access_level').val();
let payrollDetails = [];
let employeeID = null;
let validPayDates = false;
let file_data = 
        {
            "columns" : ['Payroll Employee ID', 'Employee ID','Employee Full Name','Daily Salary','Days Worked', 'Absent', 'Lates (Min)','Undertime','Vacation Leave','Sick Leave',
                        'Overtime', 'Night Differential', 'Night Differential OT', 'Regular Holiday','Regular Holiday OT', 'Regular Holiday Night Diff', 'Regular Holiday ND OT',
                        'Special Holiday', 'Special Holiday OT', 'Special Holiday Night Diff', 'Special Holiday ND OT', 'Rest Day', 'Rest Day OT', 'Rest Day Night Diff', 'Rest Day ND OT',
                        'Rest Day - Regular Holiday','Rest Day - Regular Holiday OT', 'Rest Day - Regular Holiday Night Diff', 'Rest Day - Regular Holiday ND OT', 'Rest Day - Special Holiday', 
                        'Rest Day - Special Holiday OT','Rest Day - Special Holiday Night Diff', 'Rest Day - Special Holiday ND OT',]
            ,"date" : []
        };

document.getElementById('clearBtn').addEventListener("click", clearFilter);
document.getElementById('filterBtn').addEventListener("click", getDTRList);
document.getElementById('saveChanges').addEventListener("click", saveChanges);

$( document ).ready(function() {
    getClientFilter();
});

$("#client").change(function() {
    let client_selected = $(this).val();
    if(client_selected != null){
        getPayDay(client_selected);
    }    
});

$("#payDay").change(function() {
    let pay_day = $(this).val();
    if(pay_day == "m"){
        $("#m-start-date").val('');
        $("#m-end-date").val('');
        $("#m-pay-date").val('');
        $("#dateModal").modal("show");
    }    
});

$("#proceedBtn").click(function() {
    let client = $("#client").val();
    let m_start_date = $("#m-start-date").val();
    let m_end_date = $("#m-end-date").val();
    let m_pay_date = $("#m-pay-date").val();

    let cut_start = new Date(m_start_date);
    let cut_end = new Date(m_end_date);
    let pay_date = new Date(m_pay_date);

    if (cut_end < cut_start || pay_date < cut_end) {
        swal.fire({
            icon: 'info',   
            title: 'Invalid Sequence of Dates!',       
            text: 'Please make sure the sequence of the start to end to pay date is correct'
        });

        validPayDates = false;

        return false;
    }

    let cut_start_day = cut_start.getDate();
    let cut_end_day = cut_end.getDate();
    let pay_day = pay_date.getDate();
    let cut_off = cut_start_day + '-' + cut_end_day;

    let diffInDays = (cut_end - cut_start) / (1000 * 60 * 60 * 24);

    if(diffInDays >= 30){
        swal.fire({
            icon: 'info',   
            title: 'Dates Interval',       
            text: 'The dates selected is more than 30 days'
        });

        $('#tblDiv').hide();
        $('#tblDiv2').hide();
        payrollDetails = [];

        return false;
    }

    if(diffInDays <= 7){
        cut_off = "Weekly";
    }

    let formdata = new FormData();
    formdata.append("request", "validate-manual-dates");
    formdata.append("client", client);
    formdata.append("cut_off", cut_off);
    formdata.append("pay_day", m_pay_date);

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#proceedBtn').html('Validating... <i class="fa fa-spinner fa-spin"></i>');
            $('#proceedBtn').attr('disabled',true);
        },
        success: function (response) { 
            if(!response.exists){
                swal.fire({
                    icon: 'warning',   
                    title: 'Incorrect Cut Off and Pay Dates',                 
                    text: "Please ensure that the dates you entered are aligned with the existing cut off and pay date"               
                })
        
                validPayDates = false;
                return false;
            }

            $("#dateModal").modal("hide");

            validPayDates = true;

            swal.fire({
                icon: 'success',   
                title: 'Dates are set. Click the Filter Button'       
            });
        },
        complete: function () {
            $('#proceedBtn').html('Proceed');
            $('#proceedBtn').attr('disabled',false);
        }
    });
});

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#clientLocation').val(null).trigger('change');
    $('#tblDiv').hide();
    $('#tblDiv2').hide();
    payrollDetails = [];
    // getClientFilter();
}

$(document).on("click","#dtrTbl .js-remove-benefits",function() {
    if (!payrollDetails.length) {
        Swal.fire({
            icon: 'error',
            title: 'Payroll scope unavailable',
            text: 'Select a client and pay date again before changing government benefits.'
        });
        return;
    }
    employeeID = $(this).val();
    let client_name = payrollDetails[0][0];
    let cut_off = payrollDetails[0][1];
    let pay_day = payrollDetails[0][2];
    let phrase = 'REMOVE BENEFITS ' + employeeID + ' ' + client_name + ' ' + pay_day + ' ' + cut_off;

    Swal.fire({
        title: 'Review government-benefits removal',
        html:
            '<div class="text-start mb-3">'
            + '<div><strong>Employee:</strong> ' + dtrEscapeHtml(employeeID) + '</div>'
            + '<div><strong>Client:</strong> ' + dtrEscapeHtml(client_name) + '</div>'
            + '<div><strong>Pay date:</strong> ' + dtrEscapeHtml(formatDate(pay_day)) + '</div>'
            + '<div><strong>Cutoff:</strong> ' + dtrEscapeHtml(cut_off) + '</div>'
            + '</div>'
            + '<label class="form-label d-block text-start">Business reason</label>'
            + '<textarea id="dtr-benefits-reason" class="form-control mb-3" maxlength="500" '
            + 'placeholder="Explain the approved payroll correction"></textarea>'
            + '<label class="form-label d-block text-start">Evidence reference</label>'
            + '<input id="dtr-benefits-evidence" class="form-control mb-3" maxlength="500" '
            + 'placeholder="Approval ID, ticket, source file, or controlled record">'
            + '<label class="form-label d-block text-start">Type <code>'
            + dtrEscapeHtml(phrase) + '</code> to confirm</label>'
            + '<input id="dtr-benefits-confirmation" class="form-control" autocomplete="off">',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remove and audit benefits',
        confirmButtonColor: '#dc3545',
        focusConfirm: false,
        preConfirm: function () {
            let reason = String($('#dtr-benefits-reason').val() || '').trim();
            let evidence = String($('#dtr-benefits-evidence').val() || '').trim();
            let confirmation = String($('#dtr-benefits-confirmation').val() || '').trim();
            if (reason.length < 10) {
                Swal.showValidationMessage('Enter a business reason of at least 10 characters.');
                return false;
            }
            if (evidence.length < 3) {
                Swal.showValidationMessage('Enter a traceable evidence reference.');
                return false;
            }
            if (confirmation !== phrase) {
                Swal.showValidationMessage('The typed confirmation does not match the employee payroll scope.');
                return false;
            }
            return { reason: reason, evidence: evidence, confirmation: confirmation };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            let formdata = new FormData();
            formdata.append("request", 'remove-govt-benefits');
            formdata.append("employee_ident", employeeID);
            formdata.append("client_name", client_name);
            formdata.append("pay_day", pay_day);
            formdata.append("cut_off", cut_off);
            formdata.append("change_reason", result.value.reason);
            formdata.append("change_evidence", result.value.evidence);
            formdata.append("benefits_confirmation", result.value.confirmation);
            
            $.ajax({
                url: 'controller/DTRController.php',
                type: 'POST',
                data: formdata,
                dataType: 'json',
                processing: true, 
                contentType: false,
                processData: false
                })
                .done(function (response) {
                    let auditEvidenceLink = dtrAuditEvidenceLink(response.audit_event);

                    if(response.success == 1 && response.audit_recorded && auditEvidenceLink){

                        swal.fire({
                            icon: 'success',   
                            title: 'Government benefits removed and audited',
                            html: dtrAuditedSuccessHtml(
                                'The exact employee payroll scope was changed and the evidence was recorded.',
                                response.audit_event
                            )
                        }).then(function () {
                            getDTRList();
                        });

                    }else{

                        swal.fire({
                            icon: 'error',   
                            title: 'Government-benefits change rolled back',
                            text: response.error || 'The change could not be completed and audited.'
                        });
                    }
                
                })
                .fail(function (response) {
                    swal.fire({
                        icon: 'error',   
                        title: 'Government-benefits change not completed',
                        text: response.responseJSON && response.responseJSON.error
                            ? response.responseJSON.error
                            : 'The request failed. No completion was confirmed.'
                    });
                });
        } 
    })
});


function deleteDTRUpload(branch, client_location, branch_txt, client_location_txt) {
    if (!payrollDetails.length) {
        Swal.fire({
            icon: 'error',
            title: 'Payroll scope unavailable',
            text: 'Select a client and pay date again before deleting records.'
        });
        return;
    }

    let client_name = payrollDetails[0][0];
    let pay_day = payrollDetails[0][2];
    let preflightData = new FormData();
    preflightData.append("request", 'preflight-delete-dtr-upload');
    preflightData.append("client_name", client_name);
    preflightData.append("pay_day", pay_day);
    preflightData.append("branch", branch);
    preflightData.append("client_location", client_location);

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: preflightData,
        dataType: 'json',
        contentType: false,
        processData: false
    }).done(function (preflight) {
        if (preflight.success != 1 || !preflight.can_delete) {
            Swal.fire({
                icon: 'warning',
                title: 'Deletion blocked',
                text: preflight.error || 'No records can be deleted in this payroll scope.'
            });
            return;
        }

        let labels = {
            dtr_upload: 'DTR rows',
            payroll_gross_variables: 'Gross calculation rows',
            payroll_other_additional: 'Additional-pay rows',
            payroll_other_deduction: 'Deduction rows',
            payroll_summary: 'Payroll summary rows'
        };
        let countRows = Object.keys(preflight.counts || {}).map(function (key) {
            return '<tr><td class="text-start">' + (labels[key] || key) + '</td>'
                + '<td class="text-end fw-bold">' + Number(preflight.counts[key] || 0).toLocaleString() + '</td></tr>';
        }).join('');
        let reviewedScope = preflight.scope || {};
        let reviewedClient = String(reviewedScope.client || '');
        let reviewedPayDay = String(reviewedScope.pay_day || '');
        let reviewedBranch = reviewedScope.branch == null ? null : Number(reviewedScope.branch);
        let reviewedLocation = reviewedScope.client_location == null
            ? null
            : Number(reviewedScope.client_location);
        let branchLabel = reviewedBranch != null
            ? '<div><strong>Branch:</strong> ' + dtrEscapeHtml(branch_txt) + ' (#' + reviewedBranch + ')</div>'
            : '';
        let locationLabel = reviewedLocation != null
            ? '<div><strong>Location:</strong> ' + dtrEscapeHtml(client_location_txt) + '</div>'
            : '';
        let phrase = String(preflight.confirmation_phrase || '');
        let reviewToken = String(preflight.review_token || '');
        if (!reviewedClient || !reviewedPayDay || !phrase || !reviewToken) {
            Swal.fire({
                icon: 'error',
                title: 'Deletion review unavailable',
                text: 'The server did not issue a complete deletion review. Refresh and try again.'
            });
            return;
        }

        Swal.fire({
            title: 'Review records before deletion',
            icon: 'warning',
            width: 620,
            html:
                '<div class="text-start mb-3">'
                + '<div><strong>Client:</strong> ' + dtrEscapeHtml(reviewedClient) + '</div>'
                + '<div><strong>Pay date:</strong> ' + dtrEscapeHtml(formatDate(reviewedPayDay)) + '</div>'
                + branchLabel + locationLabel
                + '<div><strong>Employees:</strong> ' + Number(preflight.employee_count || 0).toLocaleString() + '</div>'
                + '</div>'
                + '<table class="table table-sm table-bordered mb-3"><tbody>' + countRows
                + '<tr class="table-danger"><td class="text-start fw-bold">Total rows</td>'
                + '<td class="text-end fw-bold">' + Number(preflight.total_rows || 0).toLocaleString() + '</td></tr>'
                + '</tbody></table>'
                + '<label class="form-label d-block text-start">Reason for deletion</label>'
                + '<textarea id="dtr-delete-reason" class="form-control mb-3" maxlength="500" '
                + 'placeholder="Explain why this payroll scope must be removed"></textarea>'
                + '<label class="form-label d-block text-start">Evidence reference</label>'
                + '<input id="dtr-delete-evidence" class="form-control mb-3" maxlength="500" '
                + 'placeholder="Approval ID, ticket, source file, or controlled record">'
                + '<label class="form-label d-block text-start">Type <code>'
                + dtrEscapeHtml(phrase) + '</code> to confirm</label>'
                + '<input id="dtr-delete-confirmation" class="form-control" autocomplete="off">',
            showCancelButton: true,
            confirmButtonText: 'Delete reviewed records',
            confirmButtonColor: '#dc3545',
            focusConfirm: false,
            preConfirm: function () {
                let reason = String($('#dtr-delete-reason').val() || '').trim();
                let evidence = String($('#dtr-delete-evidence').val() || '').trim();
                let confirmation = String($('#dtr-delete-confirmation').val() || '').trim();
                if (reason.length < 10) {
                    Swal.showValidationMessage('Enter a reason of at least 10 characters.');
                    return false;
                }
                if (evidence.length < 3) {
                    Swal.showValidationMessage('Enter a traceable evidence reference.');
                    return false;
                }
                if (confirmation !== phrase) {
                    Swal.showValidationMessage('The typed confirmation does not match the payroll scope.');
                    return false;
                }
                return { reason: reason, evidence: evidence, confirmation: confirmation };
            }
        }).then(function (result) {
            let values = result.value;
            if (!values || (!result.isConfirmed && typeof result.isConfirmed !== 'undefined')) {
                return;
            }

            let deleteData = new FormData();
            deleteData.append("request", 'delete-dtr-upload');
            deleteData.append("client_name", reviewedClient);
            deleteData.append("pay_day", reviewedPayDay);
            deleteData.append("branch", reviewedBranch == null ? 'null' : String(reviewedBranch));
            deleteData.append("client_location", reviewedLocation == null ? 'null' : String(reviewedLocation));
            deleteData.append("deletion_reason", values.reason);
            deleteData.append("deletion_evidence", values.evidence);
            deleteData.append("deletion_confirmation", values.confirmation);
            deleteData.append("review_token", reviewToken);

            $.ajax({
                url: 'controller/DTRController.php',
                type: 'POST',
                data: deleteData,
                dataType: 'json',
                contentType: false,
                processData: false,
                beforeSend: function () {
                    Swal.fire({
                        title: 'Deleting reviewed records',
                        text: 'The deletion and audit entry are being committed together.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function () {
                            if (Swal.showLoading) {
                                Swal.showLoading();
                            }
                        }
                    });
                }
            }).done(function (response) {
                let auditEvidenceLink = dtrAuditEvidenceLink(response.audit_event);
                if(response.success == 1 && response.audit_recorded && auditEvidenceLink){
                    Swal.fire({
                        icon: 'success',
                        title: 'Records deleted and audited',
                        html: dtrAuditedSuccessHtml(
                            Number(response.total_deleted || 0).toLocaleString() + ' rows were removed.',
                            response.audit_event
                        )
                    }).then(function () {
                        getDTRList();
                    });
                }else{
                    Swal.fire({
                        icon: 'error',
                        title: 'Deletion rolled back',
                        text: response.error || 'The records could not be deleted and audited.'
                    });
                }
            }).fail(function (response) {
                let message = response.responseJSON && response.responseJSON.error
                    ? response.responseJSON.error
                    : 'The deletion request failed. No completion was confirmed.';
                Swal.fire({
                    icon: 'error',
                    title: 'Deletion not completed',
                    text: message
                });
            });
        });
    }).fail(function (response) {
        let message = response.responseJSON && response.responseJSON.error
            ? response.responseJSON.error
            : 'The deletion impact could not be verified.';
        Swal.fire({
            icon: 'error',
            title: 'Preflight failed',
            text: message
        });
    });
};

function dtrEscapeHtml(value) {
    return $('<div>').text(String(value == null ? '' : value)).html();
}

function dtrAuditEvidenceUrl(eventId) {
    let normalizedEventId = String(eventId == null ? '' : eventId).trim();
    if (!/^DTRM-[A-F0-9]{32}$/.test(normalizedEventId)) {
        return '';
    }

    return '../audit-log/?dtr_event=' + encodeURIComponent(normalizedEventId);
}

function dtrAuditEvidenceLink(eventId) {
    let evidenceUrl = dtrAuditEvidenceUrl(eventId);
    if (!evidenceUrl) {
        return '';
    }

    return '<div class="mt-3">'
        + '<a class="btn btn-sm btn-outline-primary" href="' + dtrEscapeHtml(evidenceUrl) + '">'
        + '<i class="bx bx-link-external me-1" aria-hidden="true"></i>'
        + 'View DTR Change Evidence'
        + '</a>'
        + '</div>';
}

function dtrAuditedSuccessHtml(summary, eventId) {
    let evidenceLink = dtrAuditEvidenceLink(eventId);
    if (!evidenceLink) {
        return '';
    }

    return '<div>' + dtrEscapeHtml(summary) + '</div>' + evidenceLink;
}

$(document).on("click","#dtrTbl .js-delete-record",function() {
    employeeID = $(this).val();
    let client_name = payrollDetails[0][0];
    let cut_off = payrollDetails[0][1];
    let pay_day = payrollDetails[0][2];
    let phrase = 'DELETE EMPLOYEE ' + employeeID + ' ' + client_name + ' ' + pay_day + ' ' + cut_off;

    Swal.fire({
        title: 'Review employee payroll deletion',
        html:
            '<div class="text-start mb-3">'
            + '<div><strong>Employee:</strong> ' + dtrEscapeHtml(employeeID) + '</div>'
            + '<div><strong>Client:</strong> ' + dtrEscapeHtml(client_name) + '</div>'
            + '<div><strong>Pay date:</strong> ' + dtrEscapeHtml(formatDate(pay_day)) + '</div>'
            + '<div><strong>Cutoff:</strong> ' + dtrEscapeHtml(cut_off) + '</div>'
            + '</div>'
            + '<label class="form-label d-block text-start">Reason for deletion</label>'
            + '<textarea id="dtr-employee-delete-reason" class="form-control mb-3" maxlength="500" '
            + 'placeholder="Explain why this employee payroll scope must be removed"></textarea>'
            + '<label class="form-label d-block text-start">Evidence reference</label>'
            + '<input id="dtr-employee-delete-evidence" class="form-control mb-3" maxlength="500" '
            + 'placeholder="Approval ID, ticket, source file, or controlled record">'
            + '<label class="form-label d-block text-start">Type <code>'
            + dtrEscapeHtml(phrase) + '</code> to confirm</label>'
            + '<input id="dtr-employee-delete-confirmation" class="form-control" autocomplete="off">',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete employee records',
        confirmButtonColor: '#dc3545',
        focusConfirm: false,
        preConfirm: function () {
            let reason = String($('#dtr-employee-delete-reason').val() || '').trim();
            let evidence = String($('#dtr-employee-delete-evidence').val() || '').trim();
            let confirmation = String($('#dtr-employee-delete-confirmation').val() || '').trim();
            if (reason.length < 10) {
                Swal.showValidationMessage('Enter a reason of at least 10 characters.');
                return false;
            }
            if (evidence.length < 3) {
                Swal.showValidationMessage('Enter a traceable evidence reference.');
                return false;
            }
            if (confirmation !== phrase) {
                Swal.showValidationMessage('The typed confirmation does not match the employee payroll scope.');
                return false;
            }
            return { reason: reason, evidence: evidence, confirmation: confirmation };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            let formdata = new FormData();
            formdata.append("request", 'delete-employee-dtr');
            formdata.append("employee_ident", employeeID);
            formdata.append("client_name", client_name);
            formdata.append("pay_day", pay_day);
            formdata.append("cut_off", cut_off);
            formdata.append("deletion_reason", result.value.reason);
            formdata.append("deletion_evidence", result.value.evidence);
            formdata.append("deletion_confirmation", result.value.confirmation);
            
            $.ajax({
                url: 'controller/DTRController.php',
                type: 'POST',
                data: formdata,
                dataType: 'json',
                processing: true, 
                contentType: false,
                processData: false
                })
                .done(function (response) {
                    let auditEvidenceLink = dtrAuditEvidenceLink(response.audit_event);

                    if(response.success == 1 && response.audit_recorded && auditEvidenceLink){

                        Swal.fire({
                            icon: 'success',   
                            title: 'Employee DTR deleted and audited',
                            html: dtrAuditedSuccessHtml(
                                Number(response.total_deleted || 0).toLocaleString() + ' rows were removed.',
                                response.audit_event
                            )
                        }).then(function () {
                            getDTRList()
                        });

                    }else{

                        Swal.fire({
                            icon: 'error',   
                            title: 'Employee deletion rolled back',
                            text: response.error || 'The employee payroll records could not be deleted and audited.'
                        });
                    }
                
                })
                .fail(function (response) {
                    Swal.fire({
                        icon: 'error',   
                        title: 'Employee deletion not completed',
                        text: response.responseJSON && response.responseJSON.error
                            ? response.responseJSON.error
                            : 'The request failed. No completion was confirmed.'
                    });
                });
        } 
    })
});


$(document).on("click","#dtrTbl .js-dtr-update",function() {
    var row = $(this).closest('tr');

    employeeID = $(this).val();
    var employee_id = row.find('td:eq(2)').text();
    var employee_full_name = row.find('td:eq(3)').text();
    var daily_salary = row.find('td:eq(4)').text();
    var days_worked = row.find('td:eq(5)').text();
    var absent = row.find('td:eq(6)').text();
    var lates = row.find('td:eq(7)').text();
    var undertime = row.find('td:eq(8)').text();
    var vacation_leave = row.find('td:eq(9)').text();
    var sick_leave = row.find('td:eq(10)').text();
    var overtime = row.find('td:eq(11)').text();
    var night_diff = row.find('td:eq(12)').text();
    var night_diff_ot = row.find('td:eq(13)').text();
    var regular_holiday = row.find('td:eq(14)').text();
    var regular_holiday_ot = row.find('td:eq(15)').text();
    var regular_holiday_night_diff = row.find('td:eq(16)').text();
    var regular_holiday_nd_ot = row.find('td:eq(17)').text();
    var special_holiday = row.find('td:eq(18)').text();
    var special_holiday_ot = row.find('td:eq(19)').text();
    var special_holiday_night_diff = row.find('td:eq(20)').text();
    var special_holiday_nd_ot = row.find('td:eq(21)').text();
    var rest_day = row.find('td:eq(22)').text();
    var rest_day_ot = row.find('td:eq(23)').text();
    var rest_day_night_diff = row.find('td:eq(24)').text();
    var rest_day_nd_ot = row.find('td:eq(25)').text();
    var rd_regular_holiday = row.find('td:eq(26)').text();
    var rd_regular_holiday_ot = row.find('td:eq(27)').text();
    var rd_regular_holiday_night_diff = row.find('td:eq(28)').text();
    var rd_regular_holiday_nd_ot = row.find('td:eq(29)').text();
    var rd_special_holiday = row.find('td:eq(30)').text();
    var rd_special_holiday_ot = row.find('td:eq(31)').text();
    var rd_special_holiday_night_diff = row.find('td:eq(32)').text();
    var rd_special_holiday_nd_ot = row.find('td:eq(33)').text();
    

    $('#employee-ident').val(employee_id);
    $('#employee-full-name').val(employee_full_name);
    $('#daily-salary').val(daily_salary);
    $('#days-worked').val(days_worked);
    $('#absent').val(absent);
    $('#lates').val(lates);
    $('#undertime').val(undertime);
    $('#vacation-leave').val(vacation_leave);
    $('#sick-leave').val(sick_leave);
    $('#overtime').val(overtime);
    $('#night-diff').val(night_diff);
    $('#night-diff-ot').val(night_diff_ot);
    $('#regular-holiday').val(regular_holiday);
    $('#regular-holiday-ot').val(regular_holiday_ot);
    $('#regular-holiday-night-diff').val(regular_holiday_night_diff);
    $('#regular-holiday-nd-ot').val(regular_holiday_nd_ot);
    $('#special-holiday').val(special_holiday);
    $('#special-holiday-ot').val(special_holiday_ot);
    $('#special-holiday-night-diff').val(special_holiday_night_diff);
    $('#special-holiday-nd-ot').val(special_holiday_nd_ot);
    $('#rest-day').val(rest_day);
    $('#rest-day-ot').val(rest_day_ot);
    $('#rest-day-night-diff').val(rest_day_night_diff);
    $('#rest-day-nd-ot').val(rest_day_nd_ot);
    $('#rd-regular-holiday').val(rd_regular_holiday);
    $('#rd-regular-holiday-ot').val(rd_regular_holiday_ot);
    $('#rd-regular-holiday-night-diff').val(rd_regular_holiday_night_diff);
    $('#rd-regular-holiday-nd-ot').val(rd_regular_holiday_nd_ot);
    $('#rd-special-holiday').val(rd_special_holiday);
    $('#rd-special-holiday-ot').val(rd_special_holiday_ot);
    $('#rd-special-holiday-night-diff').val(rd_special_holiday_night_diff);
    $('#rd-special-holiday-nd-ot').val(rd_special_holiday_nd_ot);
    $('#dtr-change-reason').val('');
    $('#dtr-change-evidence').val('');
    $('#editDTRModal').modal("show");

});


function saveChanges(){
    let daily_salary = $('#daily-salary').val();
    let days_worked = $('#days-worked').val();
    let absent = $('#absent').val();
    let lates = $('#lates').val();
    let undertime = $('#undertime').val();
    let vacation_leave = $('#vacation-leave').val();
    let sick_leave = $('#sick-leave').val();
    let overtime = $('#overtime').val();
    let night_diff = $('#night-diff').val();
    let night_diff_ot = $('#night-diff-ot').val();
    let regular_holiday = $('#regular-holiday').val();
    let regular_holiday_ot = $('#regular-holiday-ot').val();
    let regular_holiday_night_diff = $('#regular-holiday-night-diff').val();
    let special_holiday = $('#special-holiday').val();
    let special_holiday_ot = $('#special-holiday-ot').val();
    let special_holiday_night_diff = $('#special-holiday-night-diff').val();
    let rest_day = $('#rest-day').val();
    let rest_day_ot =  $('#rest-day-ot').val();
    let rest_day_night_diff =  $('#rest-day-night-diff').val();
    let rd_regular_holiday = $('#rd-regular-holiday').val();
    let rd_regular_holiday_ot = $('#rd-regular-holiday-ot').val();
    let rd_regular_holiday_night_diff = $('#rd-regular-holiday-night-diff').val();
    let rd_special_holiday = $('#rd-special-holiday').val();
    let rd_special_holiday_ot = $('#rd-special-holiday-ot').val();
    let rd_special_holiday_night_diff = $('#rd-special-holiday-night-diff').val();

    let regular_holiday_nd_ot = $('#regular-holiday-nd-ot').val();
    let special_holiday_nd_ot = $('#special-holiday-nd-ot').val();
    let rest_day_nd_ot = $('#rest-day-nd-ot').val();
    let rd_regular_holiday_nd_ot = $('#rd-regular-holiday-nd-ot').val();
    let rd_special_holiday_nd_ot = $('#rd-special-holiday-nd-ot').val();
    let change_reason = String($('#dtr-change-reason').val() || '').trim();
    let change_evidence = String($('#dtr-change-evidence').val() || '').trim();
    
    if(daily_salary == '' || days_worked == ''){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please complete the required fields.'
        });

        return false;
    }
    if (change_reason.length < 10 || change_reason.length > 500) {
        swal.fire({
            icon: 'info',
            title: 'Business reason required',
            text: 'Enter a business reason between 10 and 500 characters.'
        });
        return false;
    }
    if (change_evidence.length < 3 || change_evidence.length > 500) {
        swal.fire({
            icon: 'info',
            title: 'Evidence reference required',
            text: 'Enter an approval, ticket, source file, or controlled record reference.'
        });
        return false;
    }

    const numericValues = [
        daily_salary, days_worked, absent, lates, undertime, vacation_leave, sick_leave,
        overtime, night_diff, night_diff_ot, regular_holiday, regular_holiday_ot,
        regular_holiday_night_diff, special_holiday, special_holiday_ot,
        special_holiday_night_diff, rest_day, rest_day_ot, rest_day_night_diff,
        rd_regular_holiday, rd_regular_holiday_ot, rd_regular_holiday_night_diff,
        rd_special_holiday, rd_special_holiday_ot, rd_special_holiday_night_diff,
        regular_holiday_nd_ot, special_holiday_nd_ot, rest_day_nd_ot,
        rd_regular_holiday_nd_ot, rd_special_holiday_nd_ot
    ];
    const invalidNumeric = numericValues.some(function (value) {
        return value !== '' && (!Number.isFinite(Number(value)) || Number(value) < 0);
    });
    if (invalidNumeric) {
        swal.fire({
            icon: 'info',   
            title: 'Invalid payroll value',
            text: 'DTR values must be valid non-negative numbers.'
        });
        return false;
    }

    let periodDays = null;
    if (payrollDetails.length === 1) {
        let periodStart = new Date(payrollDetails[0][3] + 'T00:00:00');
        let periodEnd = new Date(payrollDetails[0][4] + 'T00:00:00');
        if (!Number.isNaN(periodStart.getTime()) && !Number.isNaN(periodEnd.getTime())) {
            periodDays = Math.floor((periodEnd - periodStart) / 86400000) + 1;
        }
    }
    if (periodDays !== null && Number(days_worked) > periodDays) {
        swal.fire({
            icon: 'info',
            title: 'Days worked exceeds the period',
            text: 'Days worked cannot be more than the selected ' + periodDays + '-day payroll period.'
        });
        return false;
    }

    let formdata = new FormData();
    formdata.append("request", "update-dtr");
    formdata.append("employee_ident", employeeID);
    formdata.append("daily_salary", daily_salary);
    formdata.append("days_worked", days_worked);
    formdata.append("absent", absent);
    formdata.append("lates", lates);
    formdata.append("undertime", undertime);
    formdata.append("vacation_leave", vacation_leave);
    formdata.append("sick_leave", sick_leave);
    formdata.append("overtime", overtime);
    formdata.append("night_diff", night_diff);
    formdata.append("night_diff_ot", night_diff_ot);
    formdata.append("regular_holiday", regular_holiday);
    formdata.append("regular_holiday_ot", regular_holiday_ot);
    formdata.append("regular_holiday_night_diff", regular_holiday_night_diff);
    formdata.append("special_holiday", special_holiday);
    formdata.append("special_holiday_ot", special_holiday_ot);
    formdata.append("special_holiday_night_diff", special_holiday_night_diff);
    formdata.append("rest_day", rest_day);
    formdata.append("rest_day_ot", rest_day_ot);
    formdata.append("rest_day_night_diff", rest_day_night_diff);
    formdata.append("rd_regular_holiday", rd_regular_holiday);
    formdata.append("rd_regular_holiday_ot", rd_regular_holiday_ot);
    formdata.append("rd_regular_holiday_night_diff", rd_regular_holiday_night_diff);
    formdata.append("rd_special_holiday", rd_special_holiday);
    formdata.append("rd_special_holiday_ot", rd_special_holiday_ot);
    formdata.append("rd_special_holiday_night_diff", rd_special_holiday_night_diff);
    formdata.append("regular_holiday_nd_ot", regular_holiday_nd_ot );
    formdata.append("special_holiday_nd_ot", special_holiday_nd_ot);
    formdata.append("rest_day_nd_ot", rest_day_nd_ot);
    formdata.append("rd_regular_holiday_nd_ot", rd_regular_holiday_nd_ot);
    formdata.append("rd_special_holiday_nd_ot", rd_special_holiday_nd_ot);
    formdata.append("change_reason", change_reason);
    formdata.append("change_evidence", change_evidence);
    for(let i=0; i < payrollDetails.length; i++) {
        formdata.append("client_name", payrollDetails[i][0]);
        formdata.append("cut_off", payrollDetails[i][1]);
        formdata.append("pay_day", payrollDetails[i][2]);
        formdata.append("start_date", payrollDetails[i][3]);
        formdata.append("end_date", payrollDetails[i][4]);
    }

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#saveChanges').html('Saving... <i class="fa fa-spinner fa-spin"></i>');
            $('#saveChanges').attr('disabled',true);
        },
        success: function (response) { 
            let auditEvidenceLink = dtrAuditEvidenceLink(response.audit_event);
            if(response.success == 1 && response.audit_recorded && auditEvidenceLink){
                $("#editDTRModal").modal('hide');
                swal.fire({
                    icon: 'success',   
                    title: 'DTR change saved and audited',
                    html: dtrAuditedSuccessHtml(
                        'The DTR change and its audit evidence were committed together.',
                        response.audit_event
                    )
                }).then(function (result) {
                    getDTRList();
                });

            }else{
                swal.fire({
                    icon: 'error',   
                    title: 'DTR changes were not saved',
                    text: response.error || 'The DTR update failed validation.'
                });
            }
        },
        error: function(response) { // if error occured
            let message = response.responseJSON && response.responseJSON.error
                ? response.responseJSON.error
                : 'The DTR update request could not be completed.';
            swal.fire({
                icon: 'error',   
                title: 'DTR changes were not saved',
                text: message
            });
        },
        complete: function () {
            $('#saveChanges').html('Save Changes');
            $('#saveChanges').attr('disabled',false);
        }
    });
}

function getClientFilter(){

    let formdata = new FormData();
    formdata.append("request", "get-client-filter");

    $.ajax({
        url: 'controller/DTRController.php',
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
                var option1 = new Option(option.client_name, option.client_name, false, false);
                $('#client').append(option1);
            });

            $('#client').trigger('change'); 
            $('#client').attr('disabled',false);
        }
    });
}


function getPayDay(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-pay-day");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#payDay').attr('disabled',true);
            $('#payDay').empty();
        },
        success: function (response) { 
            $('#payDay').append(`<option value="" disabled selected>Select Pay Day</option>`);
            $('#payDay').append(`<option value="m">Manual</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(formatDate(option.pay_date), option.pay_date, false, false);
                $(dataOption).attr("data-sd", option.start_date).attr("data-ed", option.end_date).attr("data-co", option.cut_off);
                $('#payDay').append(dataOption);
            });

            $('#payDay').trigger('change'); 
            $('#payDay').attr('disabled',false);

            getBranch(client_selected);
        }
    });
}

function getBranch(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-branch");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#branch').attr('disabled',true);
            $('#branch').empty();
        },
        success: function (response) { 
            $('#branch').append(`<option value="" disabled selected>Select Branch</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(option.branch_name, option.branch_id, false, false);
                $('#branch').append(dataOption);
            });

            $('#branch').trigger('change'); 
            $('#branch').attr('disabled',false);

            getClientLocation(client_selected);
        }
    });
}


function getClientLocation(client_selected){
    
    let formdata = new FormData();
    formdata.append("request", "get-client-location");
    formdata.append("client_selected", client_selected);

    $.ajax({
        url: 'controller/DTRController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $('#clientLocation').attr('disabled',true);
            $('#clientLocation').empty();
        },
        success: function (response) { 
            $('#clientLocation').append(`<option value="" disabled selected>Select Client Location</option>`);

            response.data.forEach(option => {
                var dataOption = new Option(option.location_name, option.location_id, false, false);
                $('#clientLocation').append(dataOption);
            });

            $('#clientLocation').trigger('change'); 
            $('#clientLocation').attr('disabled',false);
        }
    });
}


function importData() {
    Swal.fire({
        icon: 'info',
        title: 'Use Smart DTR Upload',
        text: 'The retired browser-batched workbook uploader is closed. Continue in DTR Format Engine for governed parsing, employee alignment, review, and reconciliation.',
        showCancelButton: true,
        confirmButtonText: 'Open DTR Format Engine',
        cancelButtonText: 'Stay here'
    }).then(function (result) {
        if (result.isConfirmed) {
            window.location.href = '../dtr-format-engine/';
        }
    });
}

function getDTRList() {    
    let client = $("#client").val();
    let pay_day = $("#payDay").val();
    let branch = $("#branch").val();
    let branch_txt = $("#branch option:selected").text();
    let client_location = $("#clientLocation").val();
    let client_location_txt = $("#clientLocation option:selected").text();
    let start_date = $("#payDay option:selected").data("sd");
    let end_date = $("#payDay option:selected").data("ed");
    let cut_off = $("#payDay option:selected").data("co");

    let m_start_date = $("#m-start-date").val();
    let m_end_date = $("#m-end-date").val();
    let m_pay_date = $("#m-pay-date").val();

    if(client == null || pay_day == null){
        swal.fire({
            icon: 'info',   
            title: 'Required Fields!',       
            text: 'Please select client and pay day'
        });

        $('#tblDiv').hide();
        $('#tblDiv2').hide();
        payrollDetails = [];

        return false;
    }

    if(pay_day == "m"){
        if(!validPayDates){
            swal.fire({
                icon: 'info',   
                title: 'Incorrect Manual Encode Dates',       
                text: 'Please correct the dates first'
            });
    
            $('#tblDiv').hide();
            $('#tblDiv2').hide();
            payrollDetails = [];
    
            return false;
        }

        if(m_start_date == '' || m_end_date == '' || m_pay_date == ''){
            swal.fire({
                icon: 'info',   
                title: 'Required Fields!',       
                text: 'Missing details on manual input of pay day details'
            });
    
            $('#tblDiv').hide();
            $('#tblDiv2').hide();
            payrollDetails = [];
    
            return false;
        }else{
            let cut_start = new Date(m_start_date);
            let cut_start_day = cut_start.getDate();
    
            let cut_end = new Date(m_end_date);
            let cut_end_day = cut_end.getDate();

            let diffInDays = (cut_end - cut_start) / (1000 * 60 * 60 * 24);

            start_date = m_start_date;
            end_date = m_end_date;
            pay_day = m_pay_date;
            cut_off = cut_start_day + '-' + cut_end_day;

            if(diffInDays >= 30){
                swal.fire({
                    icon: 'info',   
                    title: 'Dates Interval',       
                    text: 'The dates selected is more than 30 days'
                });
        
                $('#tblDiv').hide();
                $('#tblDiv2').hide();
                payrollDetails = [];
        
                return false;
            }

            if(diffInDays <= 7){
                cut_off = "Weekly";
            }
        }
    }

    let formdata = new FormData();
    formdata.append("request", "get-dtr-list");
    formdata.append("client", client);
    formdata.append("pay_day", pay_day);
    formdata.append("start_date", start_date);
    formdata.append("end_date", end_date);
    formdata.append("cut_off", cut_off);
    formdata.append("client_location", client_location);
    formdata.append("branch", branch);

    $.ajax({
        url: 'controller/DTRController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $("#tblDiv").show();
            $("#tblDiv2").show();
            $('#table_container').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
            $('#table_container2').html(`<center>Loading ... <i class="fa fa-spinner fa-spin"></i></center>`);
        },
        success: function(response){
            if(response.success == 1){

                fileName = "DTR";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }


                buttonArray = [
                    {
                        text: '<i class="bx bx-trash"></i> Delete All Records',
                        className: 'btn btn-sm btn-outline-danger',
                        action: function (e, dt, node, config) {
                            deleteDTRUpload(branch,client_location,branch_txt,client_location_txt);            
                        }
                    },
                    {
                        text: '<i class="bx bx-upload"></i> Upload',
                        className: 'btn btn-sm btn-outline-primary',
                        action: function (e, dt, node, config) {
                            importData();            
                        }
                    },
                    { 
                        extend: 'excel', 
                        title: null,
                        className: 'btn btn-sm btn-outline-secondary',
                        text: '<i class="bx bx-download"></i> Download',
                        filename: fileName,
                        exportOptions: {
                            columns: function (idx, data, node) {
                                let excludeColumns = [0]; 
                                return excludeColumns.indexOf(idx) === -1; 
                            },
                            format: {
                                header: function (data, column) {
                                    return data; 
                                }
                            }
                        }
                    }
                ];
                
                if(response.locked){
                    buttonArray = [
                        { 
                            extend: 'excel', 
                            title: null,
                            className: 'btn btn-sm btn-outline-secondary',
                            text: '<i class="bx bx-download"></i> Download',
                            filename: fileName,
                            exportOptions: {
                                columns: function (idx, data, node) {
                                    let excludeColumns = [0]; 
                                    return excludeColumns.indexOf(idx) === -1; 
                                },
                                format: {
                                    header: function (data, column) {
                                        return data; 
                                    }
                                }
                            }
                        }
                    ];
                }
                payrollDetails= [];
                payrollDetails.push([client,cut_off,pay_day,start_date,end_date]);
                var table = `
                <div class="alert alert-primary" role="alert">
                    Client: <a class="alert-link me-3">${client}</a>
                    Cut Off: <a class="alert-link me-3">${formatDate(start_date)} to ${formatDate(end_date)}</a>
                    Pay Day: <a class="alert-link">${formatDate(pay_day)}</a>
                </div>
                <table id="dtrTbl" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th class="text-center">Action</th>
                        <th>Payroll Employee ID</th>
                        <th>Employee ID</th>
                        <th>Employee Full Name</th>
                        <th>Daily Salary</th>
                        <th>Days Worked</th>
                        <th>Absent</th>
                        <th>Lates (Min)</th>
                        <th>Undertime</th>
                        <th>Vacation Leave</th>
                        <th>Sick Leave</th>
                        <th>Overtime</th>
                        <th>Night Differential</th>
                        <th>Night Differential OT</th>
                        <th>Regular Holiday</th>
                        <th>Regular Holiday OT</th>
                        <th>Regular Holiday Night Diff</th>
                        <th>Regular Holiday ND OT</th>
                        <th>Special Holiday</th>
                        <th>Special Holiday OT</th>
                        <th>Special Holiday Night Diff</th>
                        <th>Special Holiday ND OT</th>
                        <th>Rest Day</th>
                        <th>Rest Day OT</th>
                        <th>Rest Day Night Diff</th>
                        <th>Rest Day ND OT</th>
                        <th>Rest Day - Regular Holiday</th>
                        <th>Rest Day - Regular Holiday OT</th>
                        <th>Rest Day - Regular Holiday Night Diff</th>
                        <th>Rest Day - Regular Holiday ND OT</th>
                        <th>Rest Day - Special Holiday</th>
                        <th>Rest Day - Special Holiday OT</th>
                        <th>Rest Day - Special Holiday Night Diff</th>
                        <th>Rest Day - Special Holiday ND OT</th>
                      </tr>
                    </thead>
                </table>`;

                $('#table_container').html('');
                $('#table_container').html(table);

                $('#dtrTbl').DataTable().destroy();
                $('#dtrTbl').DataTable({
                    data: response.data,
                    responsive: true,
                    lengthChange: true,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    scrollX: true,
                    layout: {
                    topStart: 'buttons',
                    },
                    dom: '<"dt-top-container"<l><"dt-center-in-div"B><f>r>t>ip>',
                    buttons: buttonArray
                });

                var table = `
                <table id="dtrTbl2" class="dt-complex-header table table-bordered table-sm nowrap"
                    style="width:100%">
                    <thead>
                      <tr>
                        <th>Employee ID</th>
                        <th>Employee Full Name</th>
                        <th>Basic Pay</th>
                        <th>Lates (Min)</th>
                        <th>Undertime</th>
                        <th>Vacation Leave</th>
                        <th>Sick Leave</th>
                        <th>Overtime</th>
                        <th>Night Differential</th>
                        <th>Night Differential OT</th>
                        <th>Regular Holiday</th>
                        <th>Regular Holiday OT</th>
                        <th>Regular Holiday Night Diff</th>
                        <th>Regular Holiday ND OT</th>
                        <th>Special Holiday</th>
                        <th>Special Holiday OT</th>
                        <th>Special Holiday Night Diff</th>
                        <th>Special Holiday ND OT</th>
                        <th>Rest Day</th>
                        <th>Rest Day OT</th>
                        <th>Rest Day Night Diff</th>
                        <th>Rest Day ND OT</th>
                        <th>Rest Day - Regular Holiday</th>
                        <th>Rest Day - Regular Holiday OT</th>
                        <th>Rest Day - Regular Holiday Night Diff</th>
                        <th>Rest Day - Regular Holiday ND OT</th>
                        <th>Rest Day - Special Holiday</th>
                        <th>Rest Day - Special Holiday OT</th>
                        <th>Rest Day - Special Holiday Night Diff</th>
                        <th>Rest Day - Special Holiday ND OT</th>
                      </tr>
                    </thead>
                </table>`;

                $('#table_container2').html('');
                $('#table_container2').html(table);

                fileName = "DTR_Amounts";
                if(client != null && client != ''){
                    fileName += "_" + client;
                }

                if(pay_day != null && pay_day != ''){
                    fileName += "_" + pay_day;
                }

                $('#dtrTbl2').DataTable().destroy();
                $('#dtrTbl2').DataTable({
                    data: response.data3,
                    responsive: true,
                    lengthChange: true,
                    paging: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    scrollX: true,
                    layout: {
                    topStart: 'buttons',
                    },
                    dom: '<"dt-top-container"<l><"dt-center-in-div"B><f>r>t>ip>',
                    buttons: [
                        { 
                            extend: 'excel', 
                            title: null,
                            className: 'btn btn-sm btn-outline-secondary',
                            text: '<i class="bx bx-download"></i> Download',
                            filename: fileName,
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
            }else if(response.success == 2){
                swal.fire({
                    icon: 'warning',   
                    title: 'No Pay Day Set',                 
                    text: "Please go to Pay Day Maintenance and set a cutoff date"               
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

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}
