(function (window, document, $) {
    'use strict';

    var state = {
        activeKey: '',
        rows: [],
        correction: null,
        trigger: null,
        total: 0,
        truncated: false
    };

    $(document).ready(function () {
        bindDrawerEvents();
        loadPayrollDataQuality();
    });

    function loadPayrollDataQuality() {
        $('#dqAlert').hide();
        $.ajax({
            url: 'controller/DataQualityController.php',
            data: {request: 'summary'},
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (!response || response.success !== 1) {
                showDataQualityError(response && response.error ? response.error : 'Unable to load Payroll Data Quality summary.');
                return;
            }

            $('#dqCheckCount').text(Number(response.totals.checks).toLocaleString());
            $('#dqOpenFindings').text(Number(response.totals.open_findings).toLocaleString());
            $('#dqGeneratedAt').text('Generated ' + response.generated_at);

            var rows = response.data.map(function (item) {
                var badge = item.count > 0 ? 'bg-label-warning' : 'bg-label-success';
                var action = item.count > 0
                    ? '<button type="button" class="btn btn-sm btn-outline-primary dq-review-button" data-key="' + escapeHtml(item.key) + '"'
                        + ' data-label="' + escapeHtml(item.label) + '" data-severity="' + escapeHtml(item.severity) + '">'
                        + '<i class="bx bx-detail me-1" aria-hidden="true"></i>Review issues</button>'
                    : '<span class="text-muted">No action</span>';
                return '<tr>'
                    + '<td><span class="dq-finding-name">' + escapeHtml(item.label) + '</span></td>'
                    + '<td>' + severityBadge(item.severity) + '</td>'
                    + '<td class="text-end fw-semibold">' + Number(item.count).toLocaleString() + '</td>'
                    + '<td><span class="badge ' + badge + '">' + escapeHtml(item.status) + '</span></td>'
                    + '<td><span class="dq-finding-note">' + escapeHtml(item.note) + '</span></td>'
                    + '<td class="text-end">' + action + '</td>'
                    + '</tr>';
            }).join('');

            $('#dqTable tbody').html(rows || '<tr><td colspan="6" class="text-center text-muted py-5">No checks returned.</td></tr>');
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            if (xhr.status === 401 && response.redirect) {
                window.location.href = response.redirect;
                return;
            }
            showDataQualityError(response.error || 'Unable to load Payroll Data Quality summary.');
        });
    }

    function severityBadge(severity) {
        var badge = String(severity).toLowerCase() === 'high' ? 'bg-label-danger' : 'bg-label-warning';
        return '<span class="badge ' + badge + '">' + escapeHtml(severity) + '</span>';
    }

    function bindDrawerEvents() {
        $(document).on('click', '.dq-review-button', function () {
            state.trigger = this;
            openDrawer($(this).data('key'), $(this).data('label'), $(this).data('severity'));
        });

        $('#dqDrawerClose, #dqDrawerBackdrop').on('click', closeDrawer);
        $('#dqDrawerPrimaryAction').on('click', function (event) {
            if ($(this).attr('aria-disabled') === 'true') {
                event.preventDefault();
            }
        });
        $('#dqDrawerRerun').on('click', function () {
            closeDrawer();
            loadPayrollDataQuality();
        });
        $('#dqDrawerSearch').on('input', renderFilteredRows);

        $(document).on('keydown', function (event) {
            if (!$('#dqDrawer').hasClass('is-open')) {
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closeDrawer();
                return;
            }
            if (event.key === 'Tab') {
                trapDrawerFocus(event);
            }
        });
    }

    function openDrawer(key, label, severity) {
        state.activeKey = String(key || '');
        state.rows = [];
        state.correction = null;
        state.total = 0;
        state.truncated = false;
        $('#dqDrawerTitle').text(label || 'Issue details');
        $('#dqDrawerSeverity').text((severity || 'Finding') + ' severity');
        $('#dqDrawerDescription').text('Loading affected records and correction guidance.');
        $('#dqDrawerSearch').val('').prop('disabled', true);
        $('#dqDrawerCount').text('');
        $('#dqDrawerOwner').text('-');
        $('#dqDrawerPrimaryAction').hide();
        $('#dqDrawerBody').html('<div class="dq-drawer-loading"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i><span>Loading affected records...</span></div>');

        $('#dqDrawerBackdrop, #dqDrawer').prop('hidden', false);
        $('.layout-wrapper').attr('inert', '').attr('aria-hidden', 'true');
        document.body.classList.add('dq-drawer-open');
        window.requestAnimationFrame(function () {
            $('#dqDrawerBackdrop, #dqDrawer').addClass('is-open');
            $('#dqDrawerClose').trigger('focus');
        });

        $.ajax({
            url: 'controller/DataQualityController.php',
            data: {request: 'details', key: state.activeKey, limit: 100},
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (!response || response.success !== 1) {
                renderDrawerError(response && response.error ? response.error : 'Unable to load issue details.');
                return;
            }
            state.rows = response.data || [];
            state.correction = response.correction || {};
            state.total = Number(response.total || state.rows.length);
            state.truncated = Boolean(response.truncated);
            renderDrawer(response);
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            if (xhr.status === 401 && response.redirect) {
                window.location.href = response.redirect;
                return;
            }
            renderDrawerError(response.error || 'Unable to load issue details.');
        });
    }

    function renderDrawer(response) {
        var correction = state.correction;
        var guidanceClass = correction.blocked ? ' dq-drawer-guidance is-blocked' : 'dq-drawer-guidance';
        var guidanceTitle = correction.blocked ? 'Correction dependency' : 'Recommended correction';

        $('#dqDrawerDescription').text('Review the affected records before changing the owning source module.');
        $('#dqDrawerOwner').text(correction.owner || 'Payroll');
        $('#dqDrawerSearch').prop('disabled', state.rows.length === 0);

        var guidance = '<div class="' + guidanceClass + '"><strong>' + escapeHtml(guidanceTitle) + '</strong>'
            + escapeHtml(correction.guidance || '') + '</div>';
        $('#dqDrawerBody').html(guidance + '<div class="dq-record-list" id="dqRecordList"></div>');

        configurePrimaryAction(correction);
        renderFilteredRows();

    }

    function configurePrimaryAction(correction) {
        var button = $('#dqDrawerPrimaryAction');
        var accessLevel = String($('#access_level').val() || '');
        if (!correction.url) {
            button.hide();
            return;
        }
        if (correction.admin_only && accessLevel !== '1') {
            button.attr('href', '#').addClass('disabled').attr('aria-disabled', 'true')
                .find('span').text('Admin correction required');
            button.show();
            return;
        }
        button.attr('href', correction.url).removeClass('disabled').removeAttr('aria-disabled')
            .find('span').text(correction.action_label || 'Open correction workspace');
        button.show();
    }

    function renderFilteredRows() {
        var term = String($('#dqDrawerSearch').val() || '').trim().toLowerCase();
        var rows = state.rows.filter(function (row) {
            if (!term) {
                return true;
            }
            return [
                row.employee_id, row.employee_name, row.client_name, row.pay_day,
                row.cut_off, row.start_date, row.end_date, row.issue_value, row.evidence
            ].join(' ').toLowerCase().indexOf(term) !== -1;
        });

        var countText = rows.length.toLocaleString() + ' of ' + state.rows.length.toLocaleString() + ' loaded records shown.';
        if (state.truncated) {
            countText += ' ' + state.total.toLocaleString() + ' total affected; use the source workspace for the full population.';
        }
        $('#dqDrawerCount').text(countText);
        if (rows.length === 0) {
            $('#dqRecordList').html('<div class="dq-drawer-empty"><i class="bx bx-search-alt" aria-hidden="true"></i>'
                + '<strong>No loaded records match this search.</strong><span>Clear the search to return to the affected records.</span></div>');
            return;
        }

        $('#dqRecordList').html(rows.map(renderRecord).join(''));
    }

    function renderRecord(row) {
        var employeeId = row.employee_id == null ? '' : String(row.employee_id);
        var employeeActionLabel = state.activeKey === 'employee_client_mismatch' ? 'Update employee' : 'Review employee';
        var employeeStatus = String(row.employee_status || '').trim().toLowerCase();
        var action = employeeId && employeeStatus === 'active'
            ? '<a class="btn btn-sm btn-outline-primary dq-record-action" href="' + employeeManagementUrl(employeeId) + '">'
                + '<i class="bx bx-user me-1" aria-hidden="true"></i>' + employeeActionLabel + '</a>'
            : (employeeId && employeeStatus === 'terminated'
                ? '<a class="btn btn-sm btn-outline-secondary dq-record-action" href="../terminated-employees/">'
                    + '<i class="bx bx-user-x me-1" aria-hidden="true"></i>View lifecycle record</a>'
                : '');
        var period = row.start_date && row.end_date ? row.start_date + ' to ' + row.end_date : '';
        var meta = [
            row.client_name,
            row.pay_day ? 'Payday ' + row.pay_day : '',
            row.cut_off ? 'Cutoff ' + row.cut_off : '',
            period
        ].filter(Boolean).map(function (item) {
            return '<span>' + escapeHtml(item) + '</span>';
        }).join('');

        return '<article class="dq-record">'
            + '<div class="dq-record-heading"><div><strong>' + escapeHtml(row.employee_name || 'Affected record') + '</strong>'
            + (employeeId ? '<span>Employee ' + escapeHtml(employeeId)
                + (row.employee_status ? ' · ' + escapeHtml(row.employee_status) : '') + '</span>' : '') + '</div>' + action + '</div>'
            + (meta ? '<div class="dq-record-meta">' + meta + '</div>' : '')
            + '<p class="dq-record-value">' + escapeHtml(row.issue_value || 'Validation finding') + '</p>'
            + '<p class="dq-record-evidence">' + escapeHtml(row.evidence || '') + '</p>'
            + '</article>';
    }

    function employeeManagementUrl(employeeId) {
        var params = new URLSearchParams();
        params.set('from_data_issue', '1');
        params.set('employee_id', employeeId);
        params.set('action', 'edit');
        params.set('return_to', window.location.pathname + window.location.search);
        return '../employee-management/?' + params.toString();
    }

    function renderDrawerError(message) {
        $('#dqDrawerSearch').prop('disabled', true);
        $('#dqDrawerBody').html('<div class="dq-drawer-empty"><i class="bx bx-error-circle" aria-hidden="true"></i>'
            + '<strong>Issue details are unavailable.</strong><span>' + escapeHtml(message) + '</span></div>');
    }

    function closeDrawer() {
        $('#dqDrawerBackdrop, #dqDrawer').removeClass('is-open');
        $('.layout-wrapper').removeAttr('inert').removeAttr('aria-hidden');
        document.body.classList.remove('dq-drawer-open');
        window.setTimeout(function () {
            $('#dqDrawerBackdrop, #dqDrawer').prop('hidden', true);
            if (state.trigger && document.contains(state.trigger)) {
                state.trigger.focus();
            }
        }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 220);
    }

    function trapDrawerFocus(event) {
        var drawer = document.getElementById('dqDrawer');
        var focusable = Array.prototype.slice.call(drawer.querySelectorAll(
            'a[href]:not([aria-disabled="true"]), button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return element.offsetParent !== null;
        });
        if (!focusable.length) {
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function showDataQualityError(message) {
        $('#dqAlert').text(message).show();
        $('#dqTable tbody').html('<tr><td colspan="6" class="text-center text-muted py-5">No data available.</td></tr>');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}(window, document, window.jQuery));
