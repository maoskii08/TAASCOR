var dtrEngineLookups = {
    clients: [],
    locations: [],
    source_types: [],
    file_types: [],
    canonical_fields: []
};
var dtrAdapterApprovalProfiles = [];
var dtrIdentityExceptions = [];
var dtrIdentityNotificationTimer = null;
var dtrSmartResolutionRows = [];
var dtrSmartResolutionSafeCount = 0;
var dtrCurrentPayrollImportRunId = 0;
var dtrRequestedBatchAutoLoaded = false;

$(document).ready(function () {
    resetTemplateForm();
    bindDtrEngineEvents();
    applyDtrEngineRoleCapabilities();
    loadLookups();
    loadTemplates();
    loadBatches();
    loadAdapterApprovalWorkflow();
    loadAdapterPreviewWorkflow();
    loadPayrollBasisPreview();
    loadIdentityExceptions();
    loadIdentityNotifications(true);
    dtrIdentityNotificationTimer = window.setInterval(function () {
        loadIdentityNotifications(false);
    }, 30000);
});

function bindDtrEngineEvents() {
    $('#newTemplateBtn, #resetTemplateBtn').on('click', function () {
        resetTemplateForm();
    });

    $('#addMappingBtn').on('click', function () {
        addMappingRow();
    });

    $('#mappingTable').on('click', '.remove-mapping-row', function () {
        $(this).closest('tr').remove();
        if ($('#mappingTable tbody tr').length === 0) {
            addMappingRow();
        }
    });

    $('#templatesTable').on('click', '.edit-template', function () {
        loadTemplateForEdit($(this).data('id'));
    });

    $('#templatesTable').on('click', '.deactivate-template', function () {
        deactivateTemplate($(this).data('id'));
    });

    $('#templateForm').on('submit', function (event) {
        event.preventDefault();
        saveTemplate();
    });

    $('#syntheticUploadForm').on('submit', function (event) {
        event.preventDefault();
        uploadSyntheticPreview();
    });

    $('#fujiSummaryUploadForm').on('submit', function (event) {
        event.preventDefault();
        uploadFujiSummary();
    });

    $('#analyzeSmartCohortBtn').on('click', function () {
        loadSmartEmployeeResolution();
    });

    $('#smartBatchFilter').on('change', function () {
        var batchId = Number($(this).val() || 0);
        if (batchId > 0) {
            $('#identityBatchFilter').val(String(batchId));
            loadSmartEmployeeResolution(batchId);
            loadIdentityExceptions(batchId);
        }
    });

    $('#smartResolutionFilter').on('change', function () {
        renderSmartResolutionRows();
    });

    $('#approveSmartCohortBtn').on('click', function () {
        approveSmartEmployeeCohort();
    });

    $('#createPayrollImportRunBtn').on('click', function () {
        createPayrollImportRun();
    });

    $('#clearSyntheticBtn').on('click', function () {
        clearSyntheticBatches();
    });

    $('#profileRealSamplesBtn').on('click', function () {
        loadRealSampleProfile();
    });

    $('#runRealSampleAdaptersBtn').on('click', function () {
        runRealSampleAdapters();
    });

    $('#clearRealSampleAdaptersBtn').on('click', function () {
        clearRealSampleAdapters();
    });

    $('#refreshNormalizationBtn').on('click', function () {
        loadNormalizationPreview();
    });

    $('#refreshTimekeepingBtn').on('click', function () {
        loadTimekeepingPreview();
    });

    $('#refreshAdapterApprovalBtn').on('click', function () {
        loadAdapterApprovalWorkflow();
    });

    $('#refreshPreviewWorkflowBtn').on('click', function () {
        loadAdapterPreviewWorkflow();
    });

    $('#runPayrollBasisPreviewBtn').on('click', function () {
        runPayrollBasisPreview();
    });

    $('#refreshPayrollBasisPreviewBtn').on('click', function () {
        loadPayrollBasisPreview();
    });

    $('#adapterApprovalTable').on('click', '.edit-adapter-approval', function () {
        populateAdapterApprovalForm(String($(this).data('key')));
    });

    $('#adapterApprovalForm').on('submit', function (event) {
        event.preventDefault();
        saveAdapterApproval();
    });

    $('#refreshIdentityExceptionsBtn').on('click', function () {
        loadIdentityExceptions();
        loadIdentityNotifications(true);
    });

    $('#identityBatchFilter').on('change', function () {
        $('#smartBatchFilter').val(String($(this).val() || 0));
        loadIdentityExceptions();
    });

    $('#syncIdentityBatchBtn').on('click', function () {
        syncSelectedIdentityBatch();
    });

    $('#identityExceptionsTable').on('click', '.resolve-identity-exception', function () {
        openIdentityResolution(Number($(this).data('id')));
    });

    $('#identityExceptionSearch').on('input', function () {
        renderIdentityExceptions(dtrIdentityExceptions);
    });

    $('#identityExceptionTypeFilter').on('change', function () {
        renderIdentityExceptions(dtrIdentityExceptions);
    });

    $('#mapIdentityExceptionBtn').on('click', function () {
        submitIdentityResolution('map_existing');
    });

    $('#excludeIdentityExceptionBtn').on('click', function () {
        submitIdentityResolution('exclude');
    });

    $('#identityNotificationList').on('click', '.identity-notification-item', function (event) {
        event.preventDefault();
        openIdentityNotification(Number($(this).data('id')), String($(this).data('target') || ''));
    });

    $('#enableBrowserNotificationsBtn').on('click', function () {
        enableIdentityBrowserNotifications();
    });
}

function loadLookups() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'lookups' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load DTR Format Engine lookups.');
                return;
            }
            dtrEngineLookups = response;
            renderLookupOptions();
            rebuildMappingCanonicalOptions();
        },
        error: function () {
            showDtrEngineError('Unable to load DTR Format Engine lookups.');
        }
    });
}

function renderLookupOptions() {
    $('#clientId').html('<option value="">Any client</option>' + dtrEngineLookups.clients.map(function (client) {
        return '<option value="' + escapeHtml(client.client_id) + '">' + escapeHtml(client.client_name) + '</option>';
    }).join(''));

    $('#locationId').html('<option value="">Any site</option>' + dtrEngineLookups.locations.map(function (location) {
        return '<option value="' + escapeHtml(location.location_id) + '">' + escapeHtml(location.location_name) + '</option>';
    }).join(''));

    $('#sourceType').html(dtrEngineLookups.source_types.map(function (type) {
        return '<option value="' + escapeHtml(type) + '">' + escapeHtml(type) + '</option>';
    }).join(''));

    $('#fileType').html(dtrEngineLookups.file_types.map(function (type) {
        return '<option value="' + escapeHtml(type) + '">' + escapeHtml(type) + '</option>';
    }).join(''));
}

function rebuildMappingCanonicalOptions() {
    $('#mappingTable tbody tr').each(function () {
        var selected = $(this).find('.mapping-canonical').val();
        $(this).find('.mapping-canonical').html(canonicalOptions(selected));
    });
}

function loadTemplates() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'list-templates' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load DTR templates.');
                return;
            }
            renderTemplates(response.data || []);
        },
        error: function () {
            showDtrEngineError('Unable to load DTR templates.');
        }
    });
}

function renderTemplates(templates) {
    $('#templateCount').text(templates.length);
    renderSyntheticTemplateOptions(templates);

    if (!templates.length) {
        $('#templatesTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No templates configured.</td></tr>');
        return;
    }

    $('#templatesTable tbody').html(templates.map(function (template) {
        var statusBadge = Number(template.is_active) === 1 ? 'bg-label-success' : 'bg-label-secondary';
        var statusText = Number(template.is_active) === 1 ? 'Active' : 'Inactive';
        var clientSite = [template.client_name || 'Any client', template.location_name || 'Any site'].join(' / ');
        var updated = template.updated_at || template.created_at || '';
        var deactivateButton = Number(template.is_active) === 1
            ? '<button type="button" class="btn btn-sm btn-outline-warning deactivate-template" data-id="' + escapeHtml(template.id) + '">Deactivate</button>'
            : '';

        return '<tr>'
            + '<td>' + escapeHtml(template.template_name) + '</td>'
            + '<td>' + escapeHtml(clientSite) + '</td>'
            + '<td>' + escapeHtml(template.source_type) + ' / ' + escapeHtml(template.file_type) + '</td>'
            + '<td class="text-end">' + escapeHtml(template.field_count) + '</td>'
            + '<td><span class="badge ' + statusBadge + '">' + statusText + '</span></td>'
            + '<td>' + escapeHtml(updated) + '</td>'
            + '<td class="text-nowrap">'
            + '<button type="button" class="btn btn-sm btn-outline-primary edit-template me-1" data-id="' + escapeHtml(template.id) + '">Edit</button>'
            + deactivateButton
            + '</td>'
            + '</tr>';
    }).join(''));
}

function renderSyntheticTemplateOptions(templates) {
    var options = '<option value="">Select template</option>';
    templates.forEach(function (template) {
        if (Number(template.is_active) === 1) {
            options += '<option value="' + escapeHtml(template.id) + '">' + escapeHtml(template.template_name) + '</option>';
        }
    });
    $('#syntheticTemplateId').html(options);
}

function loadBatches() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'list-batches' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load upload batches.');
                return;
            }
            renderBatches(response.data || []);
        },
        error: function () {
            showDtrEngineError('Unable to load upload batches.');
        }
    });
}

function renderBatches(batches) {
    $('#batchCount').text(batches.length);

    var selectedBatch = String($('#identityBatchFilter').val() || '0');
    var selectedSmartBatch = String($('#smartBatchFilter').val() || selectedBatch || '0');
    var requestedBatchRaw = new URLSearchParams(window.location.search).get('identity_batch');
    var requestedBatch = /^\d+$/.test(String(requestedBatchRaw || '')) ? String(Number(requestedBatchRaw)) : '';
    var batchOptions = '<option value="0">All staged batches</option>' + batches.map(function (batch) {
        return '<option value="' + escapeHtml(batch.id) + '">' + escapeHtml(batch.batch_uid + ' - ' + batch.original_filename) + '</option>';
    }).join('');
    $('#identityBatchFilter').html(batchOptions);
    $('#smartBatchFilter').html(batchOptions.replace('All staged batches', 'Select a staged batch'));
    if (requestedBatch && $('#identityBatchFilter option[value="' + requestedBatch + '"]').length) {
        $('#identityBatchFilter').val(requestedBatch);
    } else if ($('#identityBatchFilter option[value="' + selectedBatch.replace(/"/g, '') + '"]').length) {
        $('#identityBatchFilter').val(selectedBatch);
    }
    var finalIdentityBatch = String($('#identityBatchFilter').val() || '0');
    if (requestedBatch && $('#smartBatchFilter option[value="' + requestedBatch + '"]').length) {
        $('#smartBatchFilter').val(requestedBatch);
    } else if ($('#smartBatchFilter option[value="' + selectedSmartBatch.replace(/"/g, '') + '"]').length) {
        $('#smartBatchFilter').val(selectedSmartBatch);
    } else if ($('#smartBatchFilter option[value="' + finalIdentityBatch.replace(/"/g, '') + '"]').length) {
        $('#smartBatchFilter').val(finalIdentityBatch);
    }

    if (requestedBatch && !dtrRequestedBatchAutoLoaded
        && $('#smartBatchFilter option[value="' + requestedBatch + '"]').length) {
        dtrRequestedBatchAutoLoaded = true;
        loadSmartEmployeeResolution(Number(requestedBatch));
        loadIdentityExceptions(Number(requestedBatch));
    }

    if (!batches.length) {
        $('#batchesTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No staged upload batches.</td></tr>');
        return;
    }

    $('#batchesTable tbody').html(batches.map(function (batch) {
        return '<tr>'
            + '<td>' + escapeHtml(batch.batch_uid) + '</td>'
            + '<td>' + escapeHtml(batch.template_name || 'Unassigned') + '</td>'
            + '<td>' + escapeHtml(batch.original_filename) + '</td>'
            + '<td class="text-end">' + escapeHtml(batch.row_count) + '</td>'
            + '<td>' + escapeHtml(batch.validation_status) + ' (' + escapeHtml(batch.error_count) + ')</td>'
            + '<td>' + escapeHtml(batch.processing_status) + '</td>'
            + '<td>' + escapeHtml(batch.uploaded_at) + '</td>'
            + '</tr>';
    }).join(''));
    loadNormalizationPreview();
}

function loadTemplateForEdit(id) {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'get-template', id: id },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load the selected template.');
                return;
            }
            populateTemplateForm(response.data);
        },
        error: function () {
            showDtrEngineError('Unable to load the selected template.');
        }
    });
}

function populateTemplateForm(template) {
    $('#templateId').val(template.id || 0);
    $('#templateName').val(template.template_name || '');
    $('#clientId').val(template.client_id || '');
    $('#locationId').val(template.location_id || '');
    $('#sourceType').val(template.source_type || '');
    $('#fileType').val(template.file_type || 'xlsx');
    $('#dateFormat').val(template.date_format || '');
    $('#timeFormat').val(template.time_format || '');
    $('#employeeIdentifierField').val(template.employee_identifier_field || '');
    $('#isActive').prop('checked', Number(template.is_active) === 1);
    $('#expectedHeaders').val(formatExpectedHeaders(template.expected_headers));

    $('#mappingTable tbody').empty();
    (template.fields || []).forEach(function (field) {
        addMappingRow(field);
    });
    if ($('#mappingTable tbody tr').length === 0) {
        addMappingRow();
    }
}

function saveTemplate() {
    var fields = collectMappingRows();
    var formData = $('#templateForm').serializeArray();
    var payload = {};
    formData.forEach(function (item) {
        payload[item.name] = item.value;
    });
    payload.request = 'save-template';
    payload.fields_json = JSON.stringify(fields);
    payload.csrf_token = $('#csrf_token').val();
    if ($('#isActive').is(':checked')) {
        payload.is_active = '1';
    }

    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: payload,
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to save DTR template.');
                return;
            }
            $('#templateId').val(response.id);
            $('#dtrEngineAlert').hide();
            loadTemplates();
        },
        error: function () {
            showDtrEngineError('Unable to save DTR template.');
        }
    });
}

function deactivateTemplate(id) {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'deactivate-template',
            id: id,
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to deactivate DTR template.');
                return;
            }
            loadTemplates();
            resetTemplateForm();
        },
        error: function () {
            showDtrEngineError('Unable to deactivate DTR template.');
        }
    });
}

function resetTemplateForm() {
    $('#templateForm')[0].reset();
    $('#templateId').val('0');
    $('#dateFormat').val('Y-m-d');
    $('#timeFormat').val('H:i');
    $('#isActive').prop('checked', true);
    $('#mappingTable tbody').empty();
    addMappingRow({
        source_header: 'Employee ID',
        canonical_field: 'employee_id',
        data_type: 'text',
        is_required: 1,
        sort_order: 1
    });
}

function uploadSyntheticPreview() {
    var form = $('#syntheticUploadForm')[0];
    var formData = new FormData(form);
    formData.append('request', 'upload-synthetic');
    formData.append('csrf_token', $('#csrf_token').val());

    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (!response || response.success !== 1) {
                renderPreviewRows([]);
                $('#syntheticSummary').hide();
                showDtrEngineError(response && response.error ? response.error : 'Unable to preview the synthetic DTR file.');
                return;
            }
            $('#dtrEngineAlert').hide();
            renderSyntheticSummary(response.summary || {});
            renderPreviewRows(response.rows || []);
            loadBatches();
            loadNormalizationPreview();
            loadTimekeepingPreview();
            loadIdentityExceptions(response.summary && response.summary.batch_id ? response.summary.batch_id : 0);
            loadIdentityNotifications(false);
        },
        error: function () {
            renderPreviewRows([]);
            $('#syntheticSummary').hide();
            showDtrEngineError('Unable to preview the synthetic DTR file.');
        }
    });
}

function uploadFujiSummary() {
    var form = $('#fujiSummaryUploadForm')[0];
    var formData = new FormData(form);
    formData.append('request', 'upload-fuji-summary');
    formData.append('csrf_token', $('#csrf_token').val());
    $('#stageFujiSummaryBtn').prop('disabled', true).text('Staging...');
    $('#fujiSummaryUploadStatus').removeClass('alert-danger alert-success').addClass('alert-info')
        .text('Reading the complete Fuji summary and building the HRIS resolution queue...').show();

    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (!response || response.success !== 1 || !response.identity_gate || response.identity_gate.success !== 1) {
                $('#fujiSummaryUploadStatus').removeClass('alert-info alert-success').addClass('alert-danger')
                    .text(response && response.error ? response.error : 'Unable to stage the Fuji workbook.');
                return;
            }
            var summary = response.summary || {};
            var gate = response.identity_gate || {};
            $('#fujiSummaryUploadStatus').removeClass('alert-info alert-danger').addClass('alert-success')
                .text('Batch ' + String(gate.batch_uid || response.batch_id)
                    + ' staged ' + String(summary.row_count || 0)
                    + ' employees. Payroll gate: ' + String(gate.gate_status || 'blocked')
                    + ' (' + String(gate.open_p0_count || 0) + ' unresolved).');
            loadBatches();
            loadIdentityExceptions(response.batch_id);
            $('#smartBatchFilter, #identityBatchFilter').val(String(response.batch_id));
            loadSmartEmployeeResolution(response.batch_id);
            loadIdentityNotifications(false);
            window.location.hash = 'smart-employee-resolution';
        },
        error: function () {
            $('#fujiSummaryUploadStatus').removeClass('alert-info alert-success').addClass('alert-danger')
                .text('Unable to stage the Fuji workbook.');
        },
        complete: function () {
            $('#stageFujiSummaryBtn').prop('disabled', false).text('Stage');
        }
    });
}

function renderSyntheticSummary(summary) {
    $('#syntheticRows').text(summary.row_count || 0);
    $('#syntheticValidRows').text(summary.valid_rows || 0);
    $('#syntheticErrorRows').text(summary.error_rows || 0);
    $('#syntheticBatch').text(summary.batch_id || '-');
    $('#syntheticSummary').show();
}

function renderPreviewRows(rows) {
    if (!rows.length) {
        $('#previewTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No synthetic preview loaded.</td></tr>');
        return;
    }

    $('#previewTable tbody').html(rows.map(function (row) {
        var status = row.validation_status === 'valid' ? 'Valid' : 'Needs Review';
        var badge = row.validation_status === 'valid' ? 'bg-label-success' : 'bg-label-warning';
        var errors = (row.errors || []).join(', ');
        return '<tr>'
            + '<td class="text-end">' + escapeHtml(row.row_number) + '</td>'
            + '<td>' + escapeHtml(row.employee_identifier) + '</td>'
            + '<td>' + escapeHtml(row.work_date) + '</td>'
            + '<td>' + escapeHtml(row.time_in) + '</td>'
            + '<td>' + escapeHtml(row.time_out) + '</td>'
            + '<td><span class="badge ' + badge + '">' + status + '</span></td>'
            + '<td>' + escapeHtml(errors || 'clear') + '</td>'
            + '</tr>';
    }).join(''));
}

function clearSyntheticBatches() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'clear-synthetic-batches',
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to clear synthetic staging batches.');
                return;
            }
            $('#dtrEngineAlert').hide();
            $('#syntheticSummary').hide();
            renderPreviewRows([]);
            loadBatches();
            loadNormalizationPreview();
            loadTimekeepingPreview();
        },
        error: function () {
            showDtrEngineError('Unable to clear synthetic staging batches.');
        }
    });
}

function loadRealSampleProfile() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'real-sample-profile' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load real sample profiles.');
                return;
            }
            $('#dtrEngineAlert').hide();
            renderRealSampleProfiles(response.data || []);
        },
        error: function () {
            showDtrEngineError('Unable to load real sample profiles.');
        }
    });
}

function runRealSampleAdapters() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'run-real-sample-adapters',
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to run real sample adapters.');
                return;
            }
            $('#dtrEngineAlert').hide();
            renderRealSampleAdapterResults(response.results || []);
            loadTemplates();
            loadBatches();
            loadNormalizationPreview();
            loadTimekeepingPreview();
            loadAdapterApprovalWorkflow();
            loadAdapterPreviewWorkflow();
            loadPayrollBasisPreview();
        },
        error: function () {
            showDtrEngineError('Unable to run real sample adapters.');
        }
    });
}

function clearRealSampleAdapters() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'clear-real-sample-adapters',
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to clear real sample adapter staging.');
                return;
            }
            $('#dtrEngineAlert').hide();
            renderRealSampleAdapterResults([]);
            loadBatches();
            loadNormalizationPreview();
            loadTimekeepingPreview();
            loadPayrollBasisPreview();
        },
        error: function () {
            showDtrEngineError('Unable to clear real sample adapter staging.');
        }
    });
}

function renderRealSampleProfiles(profiles) {
    if (!profiles.length) {
        $('#realSampleProfileTable tbody').html('<tr><td colspan="5" class="text-center text-muted">No real sample profile loaded.</td></tr>');
        return;
    }

    $('#realSampleProfileTable tbody').html(profiles.map(function (profile) {
        return '<tr>'
            + '<td>' + escapeHtml(profile.filename) + '</td>'
            + '<td>' + escapeHtml(profile.sheet) + '</td>'
            + '<td>' + escapeHtml(profile.header_rows) + '</td>'
            + '<td>' + escapeHtml(profile.employee_identifier) + '</td>'
            + '<td>' + escapeHtml(profile.mode) + '</td>'
            + '</tr>';
    }).join(''));
}

function renderRealSampleAdapterResults(results) {
    if (!results.length) {
        $('#realSampleAdapterTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No real sample adapter run loaded.</td></tr>');
        return;
    }

    $('#realSampleAdapterTable tbody').html(results.map(function (result) {
        var diagnostics = (result.adapter_diagnostics || result.profile_safety_warnings || []).join(', ') || 'clear';
        return '<tr>'
            + '<td>' + escapeHtml(result.filename) + '</td>'
            + '<td class="text-end">' + escapeHtml(result.batch_id) + '</td>'
            + '<td class="text-end">' + escapeHtml(result.rows) + '</td>'
            + '<td class="text-end">' + escapeHtml(result.valid_rows) + '</td>'
            + '<td class="text-end">' + escapeHtml(result.error_rows) + '</td>'
            + '<td class="text-end">' + escapeHtml(result.preview_hours) + '</td>'
            + '<td>' + escapeHtml(diagnostics) + '</td>'
            + '</tr>';
    }).join(''));
}

function loadAdapterPreviewWorkflow() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'adapter-preview-workflow' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load adapter preview workflow.');
                return;
            }
            renderAdapterPreviewWorkflow(response);
        },
        error: function () {
            showDtrEngineError('Unable to load adapter preview workflow.');
        }
    });
}

function renderAdapterPreviewWorkflow(workflow) {
    var intakeRows = 0;
    var intakeData = (((workflow.sample_intake || {}).data) || []);
    intakeData.forEach(function (batch) {
        intakeRows += Number(batch.staged_rows || batch.row_count || 0);
    });

    var normalization = ((workflow.normalization_preview || {}).summary) || {};
    var timekeeping = ((workflow.timekeeping_preview || {}).summary) || {};
    var approvals = (((workflow.adapter_approval_status || {}).data) || []);
    var readyProfiles = approvals.filter(function (profile) {
        return profile.payroll_handoff_ready === true;
    }).length;

    $('#previewWorkflowRows').text(intakeRows);
    $('#previewWorkflowNormalized').text(normalization.normalization_ready || 0);
    $('#previewWorkflowTimekeeping').text(timekeeping.payroll_preview_eligible_rows || 0);
    $('#previewWorkflowHandoff').text(readyProfiles > 0 ? 'Review Required' : 'Blocked');

    var steps = [
        ['Sample intake', intakeRows > 0 ? 'Loaded' : 'Not loaded', intakeRows + ' staged local preview rows'],
        ['Workbook profile', (((workflow.workbook_profile || {}).data) || []).length ? 'Loaded' : 'Not loaded', (((workflow.workbook_profile || {}).data) || []).length + ' configured sample profiles'],
        ['Validation summary', intakeData.length ? 'Loaded' : 'Not loaded', intakeData.map(function (batch) { return batch.original_filename + ': ' + batch.valid_rows + ' valid / ' + batch.error_count + ' issues'; }).join(' | ') || 'No sample batches'],
        ['Normalization preview', normalization.normalization_ready > 0 ? 'Loaded' : 'Review', (normalization.normalization_ready || 0) + ' ready / ' + (normalization.blocked_validation || 0) + ' blocked'],
        ['Timekeeping preview', timekeeping.total_staged_rows > 0 ? 'Loaded' : 'Review', (timekeeping.payroll_preview_eligible_rows || 0) + ' preview eligible / ' + (timekeeping.excluded_rows || 0) + ' excluded'],
        ['Issue breakdown', Object.keys(timekeeping.issue_count_by_type || {}).length > 0 ? 'Review' : 'Clear', Object.keys(timekeeping.issue_count_by_type || {}).join(', ') || 'No current issue breakdown'],
        ['Adapter approval status', 'Loaded', approvals.map(function (profile) { return profile.key + ': ' + profile.approval_status + ' / ' + profile.approval_scope; }).join(' | ')],
        ['Payroll handoff', 'Blocked', 'No profile is payroll-handoff-ready; explicit payroll approval is missing']
    ];

    $('#previewWorkflowTable tbody').html(steps.map(function (step) {
        var badge = step[1] === 'Loaded' || step[1] === 'Clear' ? 'bg-label-success' : 'bg-label-warning';
        if (step[1] === 'Blocked') {
            badge = 'bg-label-danger';
        }
        return '<tr>'
            + '<td>' + escapeHtml(step[0]) + '</td>'
            + '<td><span class="badge ' + badge + '">' + escapeHtml(step[1]) + '</span></td>'
            + '<td>' + escapeHtml(step[2]) + '</td>'
            + '</tr>';
    }).join(''));
}

function runPayrollBasisPreview() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'run-payroll-basis-preview',
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to build the payroll basis preview.');
                return;
            }
            $('#dtrEngineAlert').hide();
            loadPayrollBasisPreview();
        },
        error: function () {
            showDtrEngineError('Unable to build the payroll basis preview.');
        }
    });
}

function loadPayrollBasisPreview() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'payroll-basis-preview' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load the payroll basis preview.');
                return;
            }
            renderPayrollBasisPreview(response.summary || {}, response.headers || [], response.rows || []);
        },
        error: function () {
            showDtrEngineError('Unable to load the payroll basis preview.');
        }
    });
}

function renderPayrollBasisPreview(summary, headers, rows) {
    $('#payrollBasisHeaders').text(summary.headers || 0);
    $('#payrollBasisRows').text(summary.rows || 0);
    $('#payrollBasisHours').text(summary.total_preview_worked_hours || 0);
    $('#payrollBasisHandoff').text(summary.payroll_handoff_status || 'blocked');
    renderPayrollBasisHeaders(headers);
    renderPayrollBasisRows(rows);
}

function renderPayrollBasisHeaders(headers) {
    if (!headers.length) {
        $('#payrollBasisHeaderTable tbody').html('<tr><td colspan="6" class="text-center text-muted">No payroll basis preview loaded.</td></tr>');
        return;
    }

    $('#payrollBasisHeaderTable tbody').html(headers.map(function (header) {
        var clientSite = (header.client_name_snapshot || '') + ' / ' + (header.location_name_snapshot || '');
        var period = (header.pay_period_start || '') + ' - ' + (header.pay_period_end || '');
        return '<tr>'
            + '<td>' + escapeHtml(header.profile_name || header.profile_key) + '</td>'
            + '<td>' + escapeHtml(clientSite) + '</td>'
            + '<td>' + escapeHtml(period) + '</td>'
            + '<td class="text-end">' + escapeHtml(header.row_count) + '</td>'
            + '<td class="text-end">' + escapeHtml(header.total_preview_worked_hours) + '</td>'
            + '<td>' + escapeHtml(header.basis_status + ' / ' + header.approval_status + ' / ' + header.payroll_handoff_status) + '</td>'
            + '</tr>';
    }).join(''));
}

function renderPayrollBasisRows(rows) {
    if (!rows.length) {
        $('#payrollBasisRowTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No payroll basis preview rows loaded.</td></tr>');
        return;
    }

    $('#payrollBasisRowTable tbody').html(rows.map(function (row) {
        var clientSite = (row.client_name_snapshot || '') + ' / ' + (row.location_name_snapshot || '');
        var source = (row.source_batch_uid || '') + ' #' + (row.source_row_number || '');
        return '<tr>'
            + '<td>' + escapeHtml(row.profile_key) + '</td>'
            + '<td>' + escapeHtml(row.employee_name_snapshot || row.employee_id) + '</td>'
            + '<td>' + escapeHtml(clientSite) + '</td>'
            + '<td>' + escapeHtml(row.work_date || row.pay_period_start) + '</td>'
            + '<td class="text-end">' + escapeHtml(row.worked_hours_preview) + '</td>'
            + '<td>' + escapeHtml(row.eligibility_status + ' / ' + row.payroll_handoff_status) + '</td>'
            + '<td>' + escapeHtml(source) + '</td>'
            + '</tr>';
    }).join(''));
}

function loadAdapterApprovalWorkflow() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'adapter-approval-workflow' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load adapter approval workflow.');
                return;
            }
            dtrAdapterApprovalProfiles = response.data || [];
            renderAdapterApprovalWorkflow(dtrAdapterApprovalProfiles);
            if (!$('#approvalProfileKey').val() && dtrAdapterApprovalProfiles.length) {
                populateAdapterApprovalForm(dtrAdapterApprovalProfiles[0].key);
            }
        },
        error: function () {
            showDtrEngineError('Unable to load adapter approval workflow.');
        }
    });
}

function renderAdapterApprovalWorkflow(profiles) {
    if (!profiles.length) {
        $('#adapterApprovalTable tbody').html('<tr><td colspan="8" class="text-center text-muted">No adapter approval workflow loaded.</td></tr>');
        return;
    }

    $('#adapterApprovalTable tbody').html(profiles.map(function (profile) {
        var ready = profile.payroll_handoff_ready === true;
        var handoffBadge = ready ? 'bg-label-success' : 'bg-label-danger';
        var handoffText = ready ? 'Ready' : 'Blocked';
        var flags = ((profile.preview_flags || {}).flags || []).join(', ') || 'clear';
        var gaps = (profile.mapping_gaps || []).join(', ') || 'none';
        return '<tr>'
            + '<td>' + escapeHtml(profile.profile_name) + '</td>'
            + '<td>' + escapeHtml(profile.sample_file) + '</td>'
            + '<td>' + escapeHtml(profile.decision_status || profile.approval_status) + '</td>'
            + '<td>' + escapeHtml(profile.approval_scope || 'blocked') + '</td>'
            + '<td>' + escapeHtml(flags) + '</td>'
            + '<td><span class="badge ' + handoffBadge + '">' + handoffText + '</span></td>'
            + '<td>' + escapeHtml(gaps) + '</td>'
            + '<td><button type="button" class="btn btn-sm btn-outline-primary edit-adapter-approval" data-key="' + escapeHtml(profile.key) + '">Review</button></td>'
            + '</tr>';
    }).join(''));
}

function populateAdapterApprovalForm(key) {
    var profile = findAdapterApprovalProfile(key);
    if (!profile) {
        return;
    }

    $('#approvalProfileKey').val(profile.key);
    $('#approvalProfileName').val(profile.profile_name);
    $('#approvalStatus').val(profile.approval_status || 'draft');
    $('#approvalScope').val(profile.approval_scope || 'blocked');
    $('#riskAccepted').prop('checked', profile.risk_accepted === true);
    $('#employeeMatchingApproved').prop('checked', checklistPassed(profile, 'employee_matching_approved'));
    $('#statusDictionaryApproved').prop('checked', checklistPassed(profile, 'status_dictionary_approved'));
    $('#clientSiteBindingApproved').prop('checked', checklistPassed(profile, 'client_site_binding_approved'));
    $('#payPeriodExtractionApproved').prop('checked', checklistPassed(profile, 'pay_period_extraction_approved'));
    $('#suspiciousPreviewReviewed').prop('checked', checklistPassed(profile, 'suspicious_preview_reviewed'));
    $('#ownerApprovalCaptured').prop('checked', checklistPassed(profile, 'owner_approval_captured'));
    $('#approvalNotes').val(profile.approval_notes || '');
    $('#mappingGaps').val((profile.mapping_gaps || []).join("\n"));

    var gate = profile.approval_gate || {};
    $('#approvalGateStatus').val(gate.profile_review_complete ? 'Profile review complete' : 'Profile blocked: ' + ((gate.profile_blocking_items || []).join(', ') || 'approval required'));
    $('#payrollHandoffBlocked').val(profile.payroll_handoff_ready ? 'Ready' : 'Blocked: preview/staging approval is not payroll approval');
    renderApprovalChecklist(profile.checklist || []);
}

function checklistPassed(profile, field) {
    var checks = ((profile.approval_gate || {}).checks || {});
    return checks[field] === true;
}

function renderApprovalChecklist(items) {
    if (!items.length) {
        $('#approvalChecklistTable tbody').html('<tr><td colspan="3" class="text-center text-muted">No checklist loaded.</td></tr>');
        return;
    }

    $('#approvalChecklistTable tbody').html(items.map(function (item) {
        var badge = item.approved ? 'bg-label-success' : 'bg-label-warning';
        var status = item.approved ? 'Captured' : 'Pending';
        return '<tr>'
            + '<td>' + escapeHtml(item.question) + '</td>'
            + '<td>' + escapeHtml(item.required_field) + '</td>'
            + '<td><span class="badge ' + badge + '">' + status + '</span></td>'
            + '</tr>';
    }).join(''));
}

function saveAdapterApproval() {
    var payload = {
        request: 'save-adapter-approval',
        csrf_token: $('#csrf_token').val(),
        profile_key: $('#approvalProfileKey').val(),
        approval_status: $('#approvalStatus').val(),
        approval_scope: $('#approvalScope').val(),
        risk_accepted: $('#riskAccepted').is(':checked') ? '1' : '0',
        employee_matching_approved: $('#employeeMatchingApproved').is(':checked') ? '1' : '0',
        status_dictionary_approved: $('#statusDictionaryApproved').is(':checked') ? '1' : '0',
        client_site_binding_approved: $('#clientSiteBindingApproved').is(':checked') ? '1' : '0',
        pay_period_extraction_approved: $('#payPeriodExtractionApproved').is(':checked') ? '1' : '0',
        suspicious_preview_reviewed: $('#suspiciousPreviewReviewed').is(':checked') ? '1' : '0',
        owner_approval_captured: $('#ownerApprovalCaptured').is(':checked') ? '1' : '0',
        approval_notes: $('#approvalNotes').val(),
        mapping_gaps: $('#mappingGaps').val()
    };

    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: payload,
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to save adapter approval review.');
                return;
            }
            $('#dtrEngineAlert').hide();
            loadAdapterApprovalWorkflow();
        },
        error: function () {
            showDtrEngineError('Unable to save adapter approval review.');
        }
    });
}

function findAdapterApprovalProfile(key) {
    for (var i = 0; i < dtrAdapterApprovalProfiles.length; i++) {
        if (String(dtrAdapterApprovalProfiles[i].key) === String(key)) {
            return dtrAdapterApprovalProfiles[i];
        }
    }
    return null;
}

function loadNormalizationPreview() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'normalization-preview' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load normalization preview.');
                return;
            }
            renderNormalizationSummary(response.summary || {});
            renderNormalizationRows(response.rows || []);
            loadTimekeepingPreview();
        },
        error: function () {
            showDtrEngineError('Unable to load normalization preview.');
        }
    });
}

function loadTimekeepingPreview() {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'timekeeping-preview' },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load timekeeping preview.');
                return;
            }
            renderTimekeepingSummary(response.summary || {});
            renderTimekeepingRows(response.rows || []);
            renderTimekeepingIssues((response.summary || {}).issue_count_by_type || {});
        },
        error: function () {
            showDtrEngineError('Unable to load timekeeping preview.');
        }
    });
}

function renderTimekeepingSummary(summary) {
    $('#timekeepingStaged').text(summary.total_staged_rows || 0);
    $('#timekeepingEligible').text(summary.payroll_preview_eligible_rows || 0);
    $('#timekeepingExcluded').text(summary.excluded_rows || 0);
    $('#timekeepingConflicts').text(summary.conflict_rows || 0);
    $('#timekeepingHours').text(summary.total_preview_worked_hours || 0);
    $('#timekeepingIssues').text(Object.keys(summary.issue_count_by_type || {}).length);
}

function renderTimekeepingRows(rows) {
    if (!rows.length) {
        $('#timekeepingTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No timekeeping preview loaded.</td></tr>');
        return;
    }

    $('#timekeepingTable tbody').html(rows.map(function (row) {
        var eligible = row.payroll_eligibility_status === 'eligible_preview';
        var badge = eligible ? 'bg-label-success' : 'bg-label-warning';
        var label = eligible ? 'Eligible' : 'Excluded';
        var reason = (row.exclusion_reason || []).join(', ') || 'clear';
        return '<tr>'
            + '<td>' + escapeHtml(row.employee_reference) + '</td>'
            + '<td>' + escapeHtml((row.client_reference || '') + ' / ' + (row.site_reference || '')) + '</td>'
            + '<td>' + escapeHtml(row.work_date) + '</td>'
            + '<td>' + escapeHtml(row.time_in + ' - ' + row.time_out) + '</td>'
            + '<td class="text-end">' + escapeHtml(row.total_worked_hours) + '</td>'
            + '<td><span class="badge ' + badge + '">' + label + '</span></td>'
            + '<td>' + escapeHtml(reason) + '</td>'
            + '</tr>';
    }).join(''));
}

function renderTimekeepingIssues(issues) {
    var keys = Object.keys(issues);
    if (!keys.length) {
        $('#timekeepingIssuesTable tbody').html('<tr><td colspan="2" class="text-center text-muted">No issues found.</td></tr>');
        return;
    }

    $('#timekeepingIssuesTable tbody').html(keys.map(function (key) {
        return '<tr>'
            + '<td>' + escapeHtml(key) + '</td>'
            + '<td class="text-end">' + escapeHtml(issues[key]) + '</td>'
            + '</tr>';
    }).join(''));
}

function renderNormalizationSummary(summary) {
    $('#normalizationRows').text(summary.rows || 0);
    $('#normalizationReady').text(summary.normalization_ready || 0);
    $('#normalizationBlocked').text(summary.blocked_validation || 0);
    $('#normalizationConflicts').text(summary.conflict_rows || 0);
}

function renderNormalizationRows(rows) {
    if (!rows.length) {
        $('#normalizationTable tbody').html('<tr><td colspan="7" class="text-center text-muted">No normalized preview loaded.</td></tr>');
        return;
    }

    $('#normalizationTable tbody').html(rows.map(function (row) {
        var ready = row.normalization_status === 'preview_ready';
        var badge = ready ? 'bg-label-success' : 'bg-label-secondary';
        var status = ready ? 'Preview Ready' : 'Blocked';
        var source = row.source_template + ' / ' + row.source_batch + ' #' + row.source_row_number;
        var conflict = row.conflict_indicator ? (row.conflict_types || []).join(', ') : 'clear';
        return '<tr>'
            + '<td>' + escapeHtml(row.employee_reference) + '</td>'
            + '<td>' + escapeHtml((row.client_reference || '') + ' / ' + (row.site_reference || '')) + '</td>'
            + '<td>' + escapeHtml(row.work_date) + '</td>'
            + '<td>' + escapeHtml(row.time_in + ' - ' + row.time_out) + '</td>'
            + '<td>' + escapeHtml(source) + '</td>'
            + '<td><span class="badge ' + badge + '">' + status + '</span></td>'
            + '<td>' + escapeHtml(conflict) + '</td>'
            + '</tr>';
    }).join(''));
}

function loadSmartEmployeeResolution(batchOverride) {
    var batchId = Number(batchOverride || $('#smartBatchFilter').val() || 0);
    if (batchId <= 0) {
        dtrSmartResolutionRows = [];
        dtrSmartResolutionSafeCount = 0;
        $('#smartSourceCount, #smartSafeCount, #smartReviewCount, #smartBlockCount, #smartCollisionCount').text('-');
        $('#approveSmartCohortBtn').prop('disabled', true);
        $('#createPayrollImportRunBtn').prop('disabled', true);
        renderPayrollImportRun(null, null);
        $('#smartResolutionTable tbody').html('<tr><td colspan="4" class="text-center text-muted">Select and analyze a staged batch.</td></tr>');
        $('#smartResolutionTableSummary').text('');
        return;
    }

    $('#smartBatchFilter, #identityBatchFilter').val(String(batchId));
    $('#analyzeSmartCohortBtn').prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span>Analyzing');
    $('#smartResolutionStatus').removeClass('alert-danger alert-success alert-warning').addClass('alert-info')
        .text('Evaluating the complete batch for one-to-one identity evidence and collisions...');
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: {
            request: 'smart-employee-resolution-preview',
            batch_id: batchId
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                dtrSmartResolutionRows = [];
                dtrSmartResolutionSafeCount = 0;
                $('#approveSmartCohortBtn').prop('disabled', true);
                $('#smartResolutionStatus').removeClass('alert-info alert-success alert-warning').addClass('alert-danger')
                    .text(response && response.error ? response.error : 'Unable to analyze the staged employee cohort.');
                return;
            }
            dtrSmartResolutionRows = response.results || [];
            var preview = response.cohort_preview || {};
            var counts = preview.classification_counts || {};
            dtrSmartResolutionSafeCount = Number(counts.auto_eligible_shadow || 0);
            $('#smartSourceCount').text(Number(preview.unique_source_count || preview.total_sources || 0));
            $('#smartSafeCount').text(dtrSmartResolutionSafeCount);
            $('#smartReviewCount').text(Number(counts.review || 0));
            $('#smartBlockCount').text(Number(counts.block || 0));
            $('#smartCollisionCount').text(Number(preview.collision_source_count || 0));
            $('#approveSmartCohortBtn').prop('disabled', dtrSmartResolutionSafeCount <= 0);
            var unresolved = Number(counts.review || 0) + Number(counts.block || 0);
            var batch = response.batch || {};
            var identityReady = batch.validation_status === 'passed' && batch.processing_status === 'identity_ready';
            $('#createPayrollImportRunBtn').prop('disabled', unresolved > 0 || !identityReady);
            $('#smartResolutionStatus')
                .removeClass('alert-info alert-danger alert-success alert-warning')
                .addClass(unresolved > 0 ? 'alert-warning' : 'alert-success')
                .text(
                    dtrSmartResolutionSafeCount + ' collision-free shadow matches are eligible for explicit owner approval. '
                    + unresolved + ' employees remain in review or hard-blocked. Engine ' + String(response.engine_version || '') + '.'
                );
            renderSmartResolutionRows();
            loadLatestPayrollImportRun(batchId);
        },
        error: function () {
            $('#smartResolutionStatus').removeClass('alert-info alert-success alert-warning').addClass('alert-danger')
                .text('Unable to analyze the staged employee cohort.');
        },
        complete: function () {
            $('#analyzeSmartCohortBtn').prop('disabled', false)
                .html('<i class="bx bx-scan me-1"></i>Analyze batch');
        }
    });
}

function renderSmartResolutionRows() {
    var filter = String($('#smartResolutionFilter').val() || 'all');
    var filtered = dtrSmartResolutionRows.filter(function (row) {
        return filter === 'all' || String(row.classification || '') === filter;
    });
    var shown = filtered.slice(0, 200);
    if (!shown.length) {
        $('#smartResolutionTable tbody').html('<tr><td colspan="4" class="text-center text-muted">No decisions match this view.</td></tr>');
        $('#smartResolutionTableSummary').text('0 decisions shown.');
        return;
    }
    $('#smartResolutionTable tbody').html(shown.map(function (row) {
        var candidate = (row.candidates || [])[0] || {};
        var sourceRows = (row.source_row_numbers || []).slice(0, 3).join(', ');
        var source = '<strong>' + escapeHtml(row.source_employee_name || 'Name unavailable') + '</strong>'
            + '<br><small class="text-muted">Source ID ' + escapeHtml(row.source_employee_id || 'missing')
            + (sourceRows ? ' · row ' + escapeHtml(sourceRows) : '') + '</small>';
        var target = candidate.employee_id
            ? '<strong>' + escapeHtml(candidate.employee_name || 'HRIS employee') + '</strong>'
                + '<br><small class="text-muted">Employee ID ' + escapeHtml(candidate.employee_id)
                + ' · score ' + escapeHtml(row.top_score || 0) + '</small>'
            : '<span class="text-muted">No eligible candidate</span>';
        var evidenceCodes = (row.explanations || []).map(function (evidence) {
            return evidence.code || evidence;
        }).slice(0, 4);
        var contradictionCodes = (row.contradictions || []).slice(0, 3);
        var evidence = evidenceCodes.concat(contradictionCodes).join(', ') || row.classification_reason || 'No evidence';
        var badge = row.classification === 'auto_eligible_shadow'
            ? 'bg-label-success'
            : (row.classification === 'review' ? 'bg-label-warning' : 'bg-label-danger');
        var label = row.classification === 'auto_eligible_shadow'
            ? 'Safe shadow'
            : (row.classification === 'review' ? 'Owner review' : 'Blocked');
        return '<tr>'
            + '<td>' + source + '</td>'
            + '<td>' + target + '</td>'
            + '<td><small>' + escapeHtml(evidence) + '</small><br><small class="text-muted">margin ' + escapeHtml(row.margin || 0) + '</small></td>'
            + '<td><span class="badge ' + badge + '">' + label + '</span><br><small>' + escapeHtml(row.classification_reason || '') + '</small></td>'
            + '</tr>';
    }).join(''));
    $('#smartResolutionTableSummary').text(
        'Showing ' + shown.length + ' of ' + filtered.length + ' decisions. Results are grouped by unique source employee.'
    );
}

function approveSmartEmployeeCohort() {
    var batchId = Number($('#smartBatchFilter').val() || 0);
    var reason = String($('#smartCohortApprovalReason').val() || '').trim();
    if (batchId <= 0) {
        showDtrEngineError('Select and analyze one staged batch.');
        return;
    }
    if (dtrSmartResolutionSafeCount <= 0) {
        showDtrEngineError('This batch has no safe shadow matches to approve.');
        return;
    }
    if (!reason) {
        showDtrEngineError('Enter the owner approval reason for this safe cohort.');
        $('#smartCohortApprovalReason').trigger('focus');
        return;
    }

    $('#approveSmartCohortBtn').prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span>Approving');
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'approve-smart-employee-cohort',
            batch_id: batchId,
            reason: reason,
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to approve the smart employee cohort.');
                return;
            }
            $('#dtrEngineAlert').hide();
            $('#smartResolutionStatus').removeClass('alert-info alert-danger alert-warning').addClass('alert-success')
                .text(String(response.message || 'Safe employee cohort approved.') + ' Remaining exceptions were kept blocked.');
            $('#smartCohortApprovalReason').val('');
            loadIdentityExceptions(batchId);
            loadIdentityNotifications(false);
            loadBatches();
            loadSmartEmployeeResolution(batchId);
        },
        error: function () {
            showDtrEngineError('Unable to approve the smart employee cohort.');
        },
        complete: function () {
            $('#approveSmartCohortBtn').html('<i class="bx bx-check-shield me-1"></i>Approve safe cohort');
        }
    });
}

function loadLatestPayrollImportRun(batchId) {
    batchId = Number(batchId || $('#smartBatchFilter').val() || 0);
    if (batchId <= 0) {
        renderPayrollImportRun(null, null);
        return;
    }
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: {
            request: 'latest-payroll-import-run',
            batch_id: batchId
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                if (response && response.error_code === 'RUN_NOT_FOUND') {
                    renderPayrollImportRun(null, null);
                    return;
                }
                $('#payrollImportRunStatus').removeClass('alert-warning alert-success').addClass('alert-danger')
                    .text(response && response.error ? response.error : 'Unable to load the guarded payroll run.');
                return;
            }
            renderPayrollImportRun(response.run || {}, response.gate || {});
        }
    });
}

function createPayrollImportRun() {
    var batchId = Number($('#smartBatchFilter').val() || 0);
    if (batchId <= 0) {
        showDtrEngineError('Select and analyze one staged batch.');
        return;
    }
    $('#createPayrollImportRunBtn').prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span>Snapshotting');
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'create-payroll-import-run',
            batch_id: batchId,
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                $('#payrollImportRunStatus').removeClass('alert-warning alert-success').addClass('alert-danger')
                    .text(response && response.error ? response.error : 'Unable to create the guarded payroll snapshot.');
                return;
            }
            $('#dtrEngineAlert').hide();
            loadLatestPayrollImportRun(batchId);
        },
        error: function () {
            $('#payrollImportRunStatus').removeClass('alert-warning alert-success').addClass('alert-danger')
                .text('Unable to create the guarded payroll snapshot.');
        },
        complete: function () {
            $('#createPayrollImportRunBtn').html('<i class="bx bx-layer-plus me-1"></i>Create canonical snapshot');
            loadSmartEmployeeResolution(batchId);
        }
    });
}

function renderPayrollImportRun(run, gate) {
    run = run || null;
    gate = gate || {};
    if (!run || !run.id) {
        dtrCurrentPayrollImportRunId = 0;
        $('#payrollImportRunUid').text('Not created');
        $('#payrollImportRunState, #payrollImportRulesState, #payrollImportReleaseState').text('-');
        $('#payrollImportRunStatus').removeClass('alert-danger alert-success').addClass('alert-warning')
            .text('Resolve every identity first. A versioned statutory/loan ruleset and exact Fuji reconciliation are still required before approval or payslip release.');
        return;
    }
    dtrCurrentPayrollImportRunId = Number(run.id);
    $('#payrollImportRunUid').text(String(run.run_uid || run.id));
    $('#payrollImportRunState').text(String(run.status || '-'));
    $('#payrollImportRulesState').text(String(run.ruleset_status || '-'));
    var blockerCount = Number(gate.blocker_count == null ? run.release_blocker_count || 0 : gate.blocker_count);
    $('#payrollImportReleaseState').text(gate.eligible ? 'Eligible' : 'Blocked · ' + blockerCount);
    $('#payrollHandoffStatus').text(run.status === 'released' ? 'Released' : 'Guarded');
    var blockers = (gate.blockers || []).join(', ');
    $('#payrollImportRunStatus')
        .removeClass('alert-danger alert-warning alert-success')
        .addClass(gate.eligible ? 'alert-success' : 'alert-warning')
        .text(gate.eligible
            ? 'Every configured release control has passed. Final posting remains maker-checker controlled.'
            : 'Immutable run snapshot created. Release remains blocked by: ' + (blockers || 'pending controls') + '.');
}

function loadIdentityExceptions(batchOverride) {
    var batchId = Number(batchOverride || $('#identityBatchFilter').val() || 0);
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: {
            request: 'employee-identity-exceptions',
            batch_id: batchId,
            status: 'open'
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to load employee identity exceptions.');
                return;
            }
            dtrIdentityExceptions = response.data || [];
            renderIdentityExceptions(dtrIdentityExceptions);
            renderIdentityGate(response.summary || {});
        },
        error: function () {
            showDtrEngineError('Unable to load employee identity exceptions.');
        }
    });
}

function renderIdentityExceptions(rows) {
    var query = String($('#identityExceptionSearch').val() || '').trim().toLowerCase();
    var type = String($('#identityExceptionTypeFilter').val() || 'all');
    var filtered = rows.filter(function (row) {
        if (type !== 'all' && String(row.exception_code || '') !== type) {
            return false;
        }
        if (!query) {
            return true;
        }
        return [
            row.source_employee_id,
            row.source_employee_name,
            row.batch_uid,
            row.suggested_employee_id,
            row.suggested_employee_name,
            row.exception_code
        ].join(' ').toLowerCase().indexOf(query) !== -1;
    });
    var shown = filtered.slice(0, 200);
    if (!shown.length) {
        $('#identityExceptionsTable tbody').html('<tr><td colspan="6" class="text-center text-muted">No unresolved employee identity exceptions.</td></tr>');
        $('#identityExceptionTableSummary').text('0 exceptions shown.');
        return;
    }

    $('#identityExceptionsTable tbody').html(shown.map(function (row) {
        var source = escapeHtml(row.source_employee_name || 'Name unavailable')
            + (row.source_employee_id ? '<br><small class="text-muted">Source ID ' + escapeHtml(row.source_employee_id) + '</small>' : '');
        var suggestion = row.suggested_employee_id
            ? escapeHtml(row.suggested_employee_name || 'HRIS employee')
                + '<br><small class="text-muted">Employee ID ' + escapeHtml(row.suggested_employee_id) + '</small>'
            : '<span class="text-muted">No confident candidate</span>';
        var codeLabel = identityExceptionLabel(row.exception_code);
        return '<tr>'
            + '<td><strong>' + escapeHtml(row.batch_uid) + '</strong><br><small class="text-muted">Row ' + escapeHtml(row.source_row_number) + '</small></td>'
            + '<td>' + source + '</td>'
            + '<td><span class="badge bg-label-danger">' + escapeHtml(row.severity) + '</span><br><small>' + escapeHtml(codeLabel) + '</small></td>'
            + '<td>' + suggestion + '</td>'
            + '<td><span class="badge bg-label-warning">' + escapeHtml(row.status) + '</span></td>'
            + '<td class="text-nowrap">'
            + '<button type="button" class="btn btn-sm btn-primary resolve-identity-exception" data-id="' + escapeHtml(row.id) + '">Resolve</button>'
            + '</td>'
            + '</tr>';
    }).join(''));
    $('#identityExceptionTableSummary').text('Showing ' + shown.length + ' of ' + filtered.length + ' matching unresolved exceptions.');
}

function renderIdentityGate(summary) {
    var counts = summary.counts || {};
    var openCount = Number(summary.open_p0_count || 0);
    var blocked = summary.gate_status !== 'ready';
    $('#identityMissingCount').text(Number(counts.MISSING_HRIS_EMPLOYEE || 0));
    $('#identityReviewCount').text(Number(counts.EMPLOYEE_MAPPING_REVIEW || 0));
    $('#identityConflictCount').text(Number(counts.HRIS_STATUS_CONFLICT || 0));
    $('#identityGateStatus')
        .text(blocked ? 'Blocked — ' + openCount + ' P0' : 'Ready')
        .toggleClass('text-danger', blocked)
        .toggleClass('text-success', !blocked);
    if (blocked) {
        $('#identityGateAlert').text(
            'Payroll-basis finalization is blocked until all ' + openCount + ' P0 employee identity exceptions are resolved.'
        ).show();
    } else {
        $('#identityGateAlert').hide();
    }
}

function identityExceptionLabel(code) {
    var labels = {
        MISSING_HRIS_EMPLOYEE: 'Missing / no confident HRIS employee',
        EMPLOYEE_MAPPING_REVIEW: 'Employee mapping review',
        HRIS_STATUS_CONFLICT: 'HRIS status/client conflict'
    };
    return labels[code] || code || 'Employee identity exception';
}

function syncSelectedIdentityBatch() {
    var batchId = Number($('#identityBatchFilter').val() || 0);
    if (batchId <= 0) {
        showDtrEngineError('Select one staged batch before validating employee identities.');
        return;
    }
    $('#syncIdentityBatchBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Validating');
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'sync-employee-identities',
            batch_id: batchId,
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to validate employee identities.');
                return;
            }
            $('#dtrEngineAlert').hide();
            loadIdentityExceptions(batchId);
            loadIdentityNotifications(false);
            loadBatches();
            loadNormalizationPreview();
            loadTimekeepingPreview();
        },
        error: function () {
            showDtrEngineError('Unable to validate employee identities.');
        },
        complete: function () {
            $('#syncIdentityBatchBtn').prop('disabled', false).html('<i class="bx bx-shield-quarter me-1"></i>Validate selected batch');
        }
    });
}

function openIdentityResolution(exceptionId) {
    var exception = dtrIdentityExceptions.find(function (row) {
        return Number(row.id) === Number(exceptionId);
    });
    if (!exception) {
        showDtrEngineError('The selected employee exception is no longer available.');
        return;
    }
    $('#identityExceptionId').val(exception.id);
    $('#identityResolutionSource').html(
        '<strong>' + escapeHtml(exception.source_employee_name || 'Name unavailable') + '</strong>'
        + '<br>Source ID: ' + escapeHtml(exception.source_employee_id || 'missing')
        + '<br>' + escapeHtml(identityExceptionLabel(exception.exception_code))
    );
    $('#identityResolutionEmployeeId').val(exception.suggested_employee_id || '');
    $('#identityResolutionReason').val('');
    var candidateText = (exception.candidates || []).map(function (candidate) {
        var score = candidate.score == null ? '' : ' (' + candidate.score + '%)';
        return candidate.employee_name + ' — ID ' + candidate.employee_id + score;
    }).join(' | ');
    $('#identityResolutionSuggestion').text(candidateText || 'Enter the approved HRIS employee ID.');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('identityResolutionModal')).show();
}

function submitIdentityResolution(action) {
    var exceptionId = Number($('#identityExceptionId').val() || 0);
    var employeeId = Number($('#identityResolutionEmployeeId').val() || 0);
    var reason = String($('#identityResolutionReason').val() || '').trim();
    if (action === 'map_existing' && employeeId <= 0) {
        showDtrEngineError('Enter the approved HRIS employee ID.');
        return;
    }
    if (!reason) {
        showDtrEngineError('Enter the owner-approved reason for this identity decision.');
        return;
    }

    $('#mapIdentityExceptionBtn, #excludeIdentityExceptionBtn').prop('disabled', true);
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'resolve-employee-exception',
            exception_id: exceptionId,
            action: action,
            employee_id: employeeId,
            reason: reason,
            csrf_token: $('#csrf_token').val()
        },
        success: function (response) {
            if (!response || response.success !== 1) {
                showDtrEngineError(response && response.error ? response.error : 'Unable to resolve the employee identity exception.');
                return;
            }
            $('#dtrEngineAlert').hide();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('identityResolutionModal')).hide();
            loadIdentityExceptions(response.batch_id || 0);
            loadIdentityNotifications(false);
            loadBatches();
            loadNormalizationPreview();
            loadTimekeepingPreview();
        },
        error: function () {
            showDtrEngineError('Unable to resolve the employee identity exception.');
        },
        complete: function () {
            $('#mapIdentityExceptionBtn, #excludeIdentityExceptionBtn').prop('disabled', false);
        }
    });
}

function loadIdentityNotifications(forceRefresh) {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'GET',
        dataType: 'json',
        data: { request: 'notifications', limit: 20 },
        success: function (response) {
            if (!response || response.success !== 1) {
                return;
            }
            renderIdentityNotifications(response.data || [], Number(response.unread_count || 0));
            showDesktopIdentityNotification(response.data || [], !!forceRefresh);
        }
    });
}

function renderIdentityNotifications(rows, unreadCount) {
    $('#identityNotificationBadge').text(unreadCount).toggle(unreadCount > 0);
    if (!rows.length) {
        $('#identityNotificationList').html('<div class="px-3 py-4 text-center text-muted">No DTR employee notifications.</div>');
        return;
    }
    $('#identityNotificationList').html(rows.map(function (row) {
        var unread = !row.read_at;
        return '<a href="#" class="list-group-item list-group-item-action identity-notification-item ' + (unread ? 'bg-label-primary' : '') + '"'
            + ' data-id="' + escapeHtml(row.id) + '" data-target="' + escapeHtml(row.target_url || '') + '">'
            + '<div class="d-flex justify-content-between gap-2">'
            + '<strong class="small">' + escapeHtml(row.title) + '</strong>'
            + '<span class="badge ' + (row.status === 'open' ? 'bg-danger' : 'bg-success') + '">' + escapeHtml(row.open_count) + '</span>'
            + '</div>'
            + '<div class="small text-muted mt-1">' + escapeHtml(row.message) + '</div>'
            + '</a>';
    }).join(''));
}

function openIdentityNotification(notificationId, target) {
    $.ajax({
        url: 'controller/TemplateController.php',
        type: 'POST',
        dataType: 'json',
        data: {
            request: 'mark-notification-read',
            notification_id: notificationId,
            csrf_token: $('#csrf_token').val()
        },
        complete: function () {
            if (target) {
                window.location.href = target;
            } else {
                document.getElementById('employee-identity-review').scrollIntoView({ behavior: 'smooth' });
                loadIdentityNotifications(true);
            }
        }
    });
}

function enableIdentityBrowserNotifications() {
    if (!('Notification' in window)) {
        showDtrEngineError('This browser does not support desktop notifications.');
        return;
    }
    Notification.requestPermission().then(function (permission) {
        if (permission === 'granted') {
            $('#enableBrowserNotificationsBtn').text('Desktop alerts enabled').prop('disabled', true);
            loadIdentityNotifications(false);
        } else {
            showDtrEngineError('Desktop notification permission was not granted.');
        }
    });
}

function showDesktopIdentityNotification(rows, forceRefresh) {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !rows.length) {
        return;
    }
    var newest = rows.find(function (row) {
        return row.status === 'open' && !row.read_at;
    });
    if (!newest) {
        return;
    }
    var storageKey = 'taascorIdentityLastNotificationRevision';
    var revision = [newest.id, newest.updated_at || newest.created_at || '', newest.open_count].join('|');
    var lastRevision = window.localStorage.getItem(storageKey) || '';
    if (revision === lastRevision) {
        return;
    }
    window.localStorage.setItem(storageKey, revision);
    if (forceRefresh && lastRevision === '') {
        return;
    }
    var desktop = new Notification(newest.title, {
        body: newest.message,
        icon: '../assets/img/png/logo.png',
        tag: 'dtr-identity-' + newest.id
    });
    desktop.onclick = function () {
        window.focus();
        openIdentityNotification(Number(newest.id), String(newest.target_url || ''));
        desktop.close();
    };
}

function addMappingRow(field) {
    field = field || {};
    var row = '<tr>'
        + '<td><input type="text" class="form-control form-control-sm mapping-source" value="' + escapeHtml(field.source_header || '') + '" /></td>'
        + '<td><select class="form-select form-select-sm mapping-canonical">' + canonicalOptions(field.canonical_field || '') + '</select></td>'
        + '<td><select class="form-select form-select-sm mapping-type">'
        + option('text', 'Text', field.data_type || 'text')
        + option('date', 'Date', field.data_type || 'text')
        + option('time', 'Time', field.data_type || 'text')
        + option('number', 'Number', field.data_type || 'text')
        + '</select></td>'
        + '<td class="text-center"><input type="checkbox" class="form-check-input mapping-required" ' + (Number(field.is_required) === 1 ? 'checked' : '') + ' /></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-secondary remove-mapping-row"><i class="bx bx-x"></i></button></td>'
        + '</tr>';
    $('#mappingTable tbody').append(row);
}

function collectMappingRows() {
    var rows = [];
    $('#mappingTable tbody tr').each(function (index) {
        rows.push({
            source_header: $(this).find('.mapping-source').val(),
            canonical_field: $(this).find('.mapping-canonical').val(),
            data_type: $(this).find('.mapping-type').val(),
            is_required: $(this).find('.mapping-required').is(':checked') ? 1 : 0,
            sort_order: index + 1
        });
    });
    return rows;
}

function canonicalOptions(selected) {
    return dtrEngineLookups.canonical_fields.map(function (field) {
        return option(field, field, selected);
    }).join('');
}

function option(value, label, selected) {
    return '<option value="' + escapeHtml(value) + '" ' + (String(value) === String(selected) ? 'selected' : '') + '>' + escapeHtml(label) + '</option>';
}

function formatExpectedHeaders(value) {
    try {
        var headers = JSON.parse(value || '[]');
        if (Array.isArray(headers)) {
            return headers.join("\n");
        }
    } catch (ignored) {}
    return value || '';
}

function applyDtrEngineRoleCapabilities() {
    var canConfigure = String($('body').data('can-configure-templates')) === '1';
    if (canConfigure) {
        return;
    }
    $('#templateForm :input, #newTemplateBtn, #addMappingBtn, #clearSyntheticBtn, #runRealSampleAdaptersBtn, #clearRealSampleAdaptersBtn, #adapterApprovalForm :input')
        .prop('disabled', true);
    $('#saveTemplateBtn').text('Admin configuration only');
}

function showDtrEngineError(message) {
    $('#dtrEngineAlert').text(message).show();
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
