let fileName = "Additional";
let payrollDetails = [];
let employeeArray = [];
let file_data = {
    columns: ['Employee ID', 'Employee Full Name', 'Amount', 'Type of Addition'],
    date: []
};

$(document).ready(function () {
    // custom-footer.php still loads legacy SweetAlert after this module. Restore
    // the SweetAlert2 API once all page scripts are ready.
    if (window.Swal && typeof window.Swal.fire === 'function') {
        window.swal = window.Swal;
    }

    importExcel();
    getClientFilter();

    $('#clearBtn').on('click', clearFilter);
    $('#filterBtn').on('click', getAdditionalList);
    $('#addBtn').on('click', individualAdditional);
    $('#client').on('change', handleClientChange);
    $('#add-employee-id').on('change', updateSelectedEmployeeName);
});

function payrollAlert(options) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
        return window.Swal.fire(options);
    }
    window.alert(options.text || options.title || 'Payroll request failed.');
    return Promise.resolve({isConfirmed: true});
}

function responseError(response, fallback) {
    const payload = response && (response.responseJSON || response);
    if (payload && typeof payload.error === 'string' && payload.error.trim() !== '') {
        return payload.error;
    }
    if (payload && payload.error && typeof payload.error.message === 'string') {
        return payload.error.message;
    }
    return fallback || 'The payroll request could not be completed.';
}

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
}

function adjustmentAuditHtml(response, summary) {
    const eventId = String(response && response.audit_event_id || '').trim().toUpperCase();
    if (!/^[A-F0-9]{24}$/.test(eventId)) {
        return escapeHtml(summary) + '<br><strong>Audit evidence unavailable:</strong> no valid event identifier was returned.';
    }
    const link = '../audit-log/?adjustment_event=' + encodeURIComponent(eventId);
    return escapeHtml(summary)
        + '<br><a class="btn btn-sm btn-outline-primary mt-3" href="' + link + '">Review audit event '
        + escapeHtml(eventId) + '</a>';
}

function resetEmployeePicker() {
    employeeArray = [];
    $('#add-employee-id')
        .empty()
        .append(new Option('Select employee', '', true, true))
        .val(null)
        .trigger('change');
    $('#add-employee-name').val('');
}

function populateEmployeePicker(rows) {
    const employees = new Map();
    rows.forEach(function (row) {
        const employeeId = String(row[0] == null ? '' : row[0]).trim();
        if (employeeId !== '' && !employees.has(employeeId)) {
            employees.set(employeeId, {
                id: employeeId,
                name: String(row[1] == null ? '' : row[1]).trim()
            });
        }
    });

    employeeArray = Array.from(employees.values());
    const picker = $('#add-employee-id');
    picker.empty().append(new Option('Select employee', '', true, true));
    employeeArray.forEach(function (employee) {
        picker.append(new Option(
            employee.id + ' — ' + employee.name,
            employee.id,
            false,
            false
        ));
    });
    picker.val(null).trigger('change');
}

function updateSelectedEmployeeName() {
    const employeeId = String($('#add-employee-id').val() || '');
    const employee = employeeArray.find(function (entry) {
        return entry.id === employeeId;
    });
    $('#add-employee-name').val(employee ? employee.name : '');
}

function handleClientChange() {
    const client = $('#client').val();
    $('#payDay').empty().append(new Option('Select pay day', '', true, true)).val(null).trigger('change');
    $('#branch').empty().append(new Option('All branches', '', true, true)).val('').trigger('change');
    $('#clientLocation').empty().append(new Option('All locations', '', true, true)).val('').trigger('change');
    $('#tblDiv').hide();
    payrollDetails = [];
    resetEmployeePicker();

    if (client) {
        getPayDay(client);
        getBranch(client);
        getClientLocation(client);
    }
}

function clearFilter() {
    $('#client').val(null).trigger('change');
    $('#payDay').val(null).trigger('change');
    $('#branch').val('').trigger('change');
    $('#clientLocation').val('').trigger('change');
    $('#table_container').empty();
    $('#tblDiv').hide();
    payrollDetails = [];
    resetEmployeePicker();
}

function importExcel() {
    const aliases = {};
    file_data.columns.forEach(function (column) {
        const normalized = column.toLowerCase();
        aliases[normalized] = [column, normalized];
    });

    new ExcelImport({
        maxWorkbookRows: 1000,
        maxWorkbookBytes: 1048576,
        serverColumnNames: file_data.columns,
        importTypeSelector: '#dataType',
        fileChooserSelector: '#fileUploader',
        outputSelector: '#tableOutput',
        extraData: {
            importID: 0,
            cmd: 'batch_upload',
            obj: aliases
        }
    });
}

function resetAddForm() {
    $('#add-employee-id').val(null).trigger('change');
    $('#add-employee-name').val('');
    $('#add-amount').val('');
    $('#add-type-of-addition').val('');
    $('#add-change-reason').val('');
    $('#add-evidence-reference').val('');
}

function individualAdditional() {
    if (payrollDetails.length !== 1) {
        payrollAlert({
            icon: 'info',
            title: 'Select a payroll population',
            text: 'Filter a client and pay day before adding an adjustment.'
        });
        return;
    }

    const employeeId = String($('#add-employee-id').val() || '').trim();
    const amount = String($('#add-amount').val() || '').trim();
    const typeOfAddition = String($('#add-type-of-addition').val() || '').trim();
    const changeReason = String($('#add-change-reason').val() || '').trim();
    const evidenceReference = String($('#add-evidence-reference').val() || '').trim();

    if (
        employeeId === ''
        || !/^\d{1,8}(?:\.\d{1,2})?$/.test(amount)
        || Number(amount) <= 0
        || Number(amount) > 99999999.99
        || typeOfAddition.length < 2
        || changeReason.length < 5
        || evidenceReference.length < 3
    ) {
        payrollAlert({
            icon: 'info',
            title: 'Complete the required fields',
            text: 'Choose an employee, enter a positive amount with up to two decimals, and provide the type, business reason, and approval or evidence reference.'
        });
        return;
    }

    const scope = payrollDetails[0];
    const formData = new FormData();
    formData.append('request', 'add-individual');
    formData.append('employee_id', employeeId);
    formData.append('amount', amount);
    formData.append('type_of_addition', typeOfAddition);
    formData.append('change_reason', changeReason);
    formData.append('evidence_reference', evidenceReference);
    formData.append('client_name', scope[0]);
    formData.append('cut_off', scope[1]);
    formData.append('pay_day', scope[2]);
    formData.append('start_date', scope[3]);
    formData.append('end_date', scope[4]);

    $.ajax({
        url: 'controller/AdditionalController.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#addBtn').html('Saving... <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>').prop('disabled', true);
        }
    })
        .done(function (response) {
            if (Number(response.success) !== 1) {
                payrollAlert({
                    icon: 'error',
                    title: 'Addition not saved',
                    text: responseError(response)
                });
                return;
            }
            $('#addModal').modal('hide');
            resetAddForm();
            payrollAlert({
                icon: 'success',
                title: 'Payroll addition saved',
                html: adjustmentAuditHtml(response, 'The addition, payroll recalculation, and exact audit evidence committed together.')
            }).then(getAdditionalList);
        })
        .fail(function (xhr) {
            payrollAlert({
                icon: 'error',
                title: 'Addition not saved',
                text: responseError(xhr)
            });
        })
        .always(function () {
            $('#addBtn').text('Add').prop('disabled', false);
        });
}

$(document).on('click', '#dtrTbl #deleteBtn', function () {
    if (payrollDetails.length !== 1) {
        return;
    }

    const adjustmentId = String($(this).val() || '').trim();
    const employeeId = String($(this).data('employee-id') || '').trim();

    window.Swal.fire({
        title: 'Delete this payroll addition?',
        icon: 'warning',
        html:
            '<div class="text-start">' +
            '<label for="delete-change-reason" class="form-label fw-bold">Business reason</label>' +
            '<input id="delete-change-reason" class="swal2-input m-0 mb-3 w-100" maxlength="255" placeholder="Why must this be deleted?">' +
            '<label for="delete-evidence-reference" class="form-label fw-bold">Approval or evidence reference</label>' +
            '<input id="delete-evidence-reference" class="swal2-input m-0 mb-3 w-100" maxlength="255" placeholder="Ticket, approval, or source document">' +
            '<label for="delete-confirmation" class="form-label fw-bold">Type DELETE to confirm</label>' +
            '<input id="delete-confirmation" class="swal2-input m-0 w-100" autocomplete="off" placeholder="DELETE">' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: 'Delete addition',
        confirmButtonColor: '#d33',
        focusConfirm: false,
        preConfirm: function () {
            const reason = String($('#delete-change-reason').val() || '').trim();
            const evidence = String($('#delete-evidence-reference').val() || '').trim();
            const confirmation = String($('#delete-confirmation').val() || '').trim().toUpperCase();
            if (reason.length < 5) {
                window.Swal.showValidationMessage('Enter a business reason with at least five characters.');
                return false;
            }
            if (evidence.length < 3) {
                window.Swal.showValidationMessage('Enter an approval, ticket, or source-file reference.');
                return false;
            }
            if (confirmation !== 'DELETE') {
                window.Swal.showValidationMessage('Type DELETE exactly to confirm.');
                return false;
            }
            return {
                changeReason: reason,
                evidenceReference: evidence,
                confirmation: confirmation
            };
        }
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        const scope = payrollDetails[0];
        const formData = new FormData();
        formData.append('request', 'delete');
        formData.append('id', adjustmentId);
        formData.append('employee_id', employeeId);
        formData.append('client_name', scope[0]);
        formData.append('cut_off', scope[1]);
        formData.append('pay_day', scope[2]);
        formData.append('start_date', scope[3]);
        formData.append('end_date', scope[4]);
        formData.append('change_reason', result.value.changeReason);
        formData.append('evidence_reference', result.value.evidenceReference);
        formData.append('confirmation', result.value.confirmation);

        $.ajax({
            url: 'controller/AdditionalController.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false
        })
            .done(function (response) {
                if (Number(response.success) !== 1) {
                    payrollAlert({
                        icon: 'error',
                        title: 'Addition not deleted',
                        text: responseError(response)
                    });
                    return;
                }
                payrollAlert({
                    icon: 'success',
                    title: 'Payroll addition deleted',
                    html: adjustmentAuditHtml(response, 'The removal, payroll recalculation, and exact audit evidence committed together.')
                }).then(getAdditionalList);
            })
            .fail(function (xhr) {
                payrollAlert({
                    icon: 'error',
                    title: 'Addition not deleted',
                    text: responseError(xhr)
                });
            });
    });
});

function getClientFilter() {
    const formData = new FormData();
    formData.append('request', 'get-client-filter');

    $.ajax({
        url: 'controller/AdditionalController.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#client').prop('disabled', true).empty();
        }
    })
        .done(function (response) {
            const client = $('#client');
            client.append(new Option('Select client', '', true, true));
            (response.data || []).forEach(function (option) {
                client.append(new Option(option.client_name, option.client_name, false, false));
            });
            client.val(null).trigger('change').prop('disabled', false);
        })
        .fail(function (xhr) {
            $('#client').prop('disabled', false);
            payrollAlert({icon: 'error', title: 'Clients unavailable', text: responseError(xhr)});
        });
}

function getPayDay(clientSelected) {
    const formData = new FormData();
    formData.append('request', 'get-pay-day');
    formData.append('client_selected', clientSelected);

    $.ajax({
        url: 'controller/AdditionalController.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#payDay').prop('disabled', true).empty();
        }
    })
        .done(function (response) {
            const payDay = $('#payDay');
            payDay.append(new Option('Select pay day', '', true, true));
            (response.data || []).forEach(function (option) {
                const item = new Option(formatDate(option.pay_date), option.pay_date, false, false);
                $(item)
                    .attr('data-sd', option.start_date)
                    .attr('data-ed', option.end_date)
                    .attr('data-co', option.cut_off);
                payDay.append(item);
            });
            payDay.val(null).trigger('change').prop('disabled', false);
        })
        .fail(function (xhr) {
            $('#payDay').prop('disabled', false);
            payrollAlert({icon: 'error', title: 'Pay days unavailable', text: responseError(xhr)});
        });
}

function getBranch(clientSelected) {
    const formData = new FormData();
    formData.append('request', 'get-branch');
    formData.append('client_selected', clientSelected);

    $.ajax({
        url: 'controller/AdditionalController.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#branch').prop('disabled', true).empty();
        }
    })
        .done(function (response) {
            const branch = $('#branch');
            branch.append(new Option('All branches', '', true, true));
            (response.data || []).forEach(function (option) {
                branch.append(new Option(option.branch_name, option.branch_id, false, false));
            });
            branch.val('').trigger('change').prop('disabled', false);
        })
        .fail(function (xhr) {
            $('#branch').prop('disabled', false);
            payrollAlert({icon: 'error', title: 'Branches unavailable', text: responseError(xhr)});
        });
}

function getClientLocation(clientSelected) {
    const formData = new FormData();
    formData.append('request', 'get-client-location');
    formData.append('client_selected', clientSelected);

    $.ajax({
        url: 'controller/AdditionalController.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#clientLocation').prop('disabled', true).empty();
        }
    })
        .done(function (response) {
            const location = $('#clientLocation');
            location.append(new Option('All locations', '', true, true));
            (response.data || []).forEach(function (option) {
                location.append(new Option(option.location_name, option.location_id, false, false));
            });
            location.val('').trigger('change').prop('disabled', false);
        })
        .fail(function (xhr) {
            $('#clientLocation').prop('disabled', false);
            payrollAlert({icon: 'error', title: 'Locations unavailable', text: responseError(xhr)});
        });
}

function importData() {
    if (payrollDetails.length !== 1 || employeeArray.length === 0) {
        payrollAlert({
            icon: 'info',
            title: 'No DTR population selected',
            text: 'Filter a client and pay day with uploaded DTR records before importing additions.'
        });
        return;
    }
    $('#importModal').modal('show');
}

$('#importModal').on('hidden.bs.modal', function () {
    $('#readingFileStatus').empty();
    $('#tableOutput').empty();
    $('#dataType').prop('disabled', false);
    $('#fileUploader').prop('disabled', false).val('');
    $('#import-change-reason').val('');
    $('#import-evidence-reference').val('');
});

function renderNoDtrState(client, payDay) {
    $('#table_container').html(
        '<div class="alert alert-warning mb-0" role="status">' +
        '<h5 class="alert-heading">No DTR population found</h5>' +
        '<p class="mb-2">No employees were found for <strong>' + escapeHtml(client) +
        '</strong> and pay day <strong>' + escapeHtml(formatDate(payDay)) + '</strong>.</p>' +
        '<a class="btn btn-sm btn-warning" href="../dtr-upload/">Open DTR Upload</a>' +
        '</div>'
    );
}

function renderRequestError(message) {
    $('#table_container').html(
        '<div class="alert alert-danger mb-0" role="alert">' +
        '<h5 class="alert-heading">Payroll additions could not be loaded</h5>' +
        '<p class="mb-0">' + escapeHtml(message) + '</p>' +
        '</div>'
    );
}

function getAdditionalList() {
    const client = $('#client').val();
    const payDay = $('#payDay').val();
    const selectedPayDay = $('#payDay option:selected');
    const startDate = selectedPayDay.data('sd');
    const endDate = selectedPayDay.data('ed');
    const cutOff = selectedPayDay.data('co');
    const branch = $('#branch').val() || '';
    const clientLocation = $('#clientLocation').val() || '';

    if (!client || !payDay || !startDate || !endDate || !cutOff) {
        payrollAlert({
            icon: 'info',
            title: 'Select a payroll period',
            text: 'Select both a client and pay day before filtering.'
        });
        $('#tblDiv').hide();
        payrollDetails = [];
        resetEmployeePicker();
        return;
    }

    const formData = new FormData();
    formData.append('request', 'get-additional-list');
    formData.append('client', client);
    formData.append('pay_day', payDay);
    formData.append('start_date', startDate);
    formData.append('end_date', endDate);
    formData.append('cut_off', cutOff);
    formData.append('client_location', clientLocation);
    formData.append('branch', branch);

    $.ajax({
        url: 'controller/AdditionalController.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false,
        processData: false,
        beforeSend: function () {
            $('#tblDiv').show();
            $('#table_container').html(
                '<div class="text-center" role="status">Loading payroll additions… ' +
                '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i></div>'
            );
        }
    })
        .done(function (response) {
            if (Number(response.success) === 2) {
                payrollDetails = [];
                resetEmployeePicker();
                renderRequestError('No pay day is configured for this client. Configure the pay-day maintenance record first.');
                return;
            }
            if (Number(response.success) !== 1) {
                payrollDetails = [];
                resetEmployeePicker();
                renderRequestError(responseError(response));
                return;
            }

            const rows = Array.isArray(response.data) ? response.data : [];
            if (rows.length === 0) {
                payrollDetails = [];
                resetEmployeePicker();
                renderNoDtrState(client, payDay);
                return;
            }

            payrollDetails = [[client, cutOff, payDay, startDate, endDate]];
            populateEmployeePicker(rows);
            fileName = 'Additional_' + client + '_' + payDay;

            let buttons = [
                {
                    text: '<i class="bx bx-plus" aria-hidden="true"></i> Add Additional',
                    className: 'btn btn-sm btn-outline-primary',
                    action: function () {
                        $('#addModal').modal('show');
                    }
                },
                {
                    text: '<i class="bx bx-upload" aria-hidden="true"></i> Upload',
                    className: 'btn btn-sm btn-outline-primary',
                    action: importData
                },
                {
                    extend: 'excel',
                    title: null,
                    className: 'btn btn-sm btn-outline-secondary',
                    text: '<i class="bx bx-download" aria-hidden="true"></i> Download',
                    filename: fileName,
                    exportOptions: {columns: [0, 1, 2, 3]}
                }
            ];
            if (response.locked) {
                buttons = [buttons[2]];
            }

            const table =
                '<div class="alert alert-primary" role="status">' +
                'Client: <strong class="me-3">' + escapeHtml(client) + '</strong>' +
                'Cut Off: <strong class="me-3">' + escapeHtml(formatDate(startDate)) + ' to ' + escapeHtml(formatDate(endDate)) + '</strong>' +
                'Pay Day: <strong>' + escapeHtml(formatDate(payDay)) + '</strong>' +
                (response.locked ? '<span class="badge bg-secondary ms-3">Posted and locked</span>' : '') +
                '</div>' +
                '<table id="dtrTbl" class="dt-complex-header table table-bordered table-sm nowrap" style="width:100%">' +
                '<thead><tr>' +
                '<th>Employee ID</th><th>Employee Full Name</th><th>Amount</th><th>Type of Addition</th><th>Action</th>' +
                '</tr></thead></table>';

            $('#table_container').html(table);
            $('#dtrTbl').DataTable({
                data: rows,
                responsive: true,
                lengthChange: true,
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                scrollX: true,
                layout: {topStart: 'buttons'},
                dom: '<"dt-top-container"<l><"dt-center-in-div"B><f>r>t>ip>',
                buttons: buttons
            });
        })
        .fail(function (xhr) {
            payrollDetails = [];
            resetEmployeePicker();
            renderRequestError(responseError(xhr));
        });
}

function formatDate(dateString) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dateString || ''));
    if (!match) {
        return String(dateString || '');
    }
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}
