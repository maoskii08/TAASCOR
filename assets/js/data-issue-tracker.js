(function (window, document, $) {
    'use strict';

    var issuePages = [
        'incomplete-details',
        'sss-format',
        'duplicate-sss',
        'duplicate-philhealth',
        'duplicate-pagibig',
        'duplicate-tin',
        'duplicate-bank-account',
        'invalid-salary',
        'invalid-contact-number',
        'invalid-employee-type'
    ];

    function pageSlug() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        return parts.length ? parts[parts.length - 1].toLowerCase() : '';
    }

    if (issuePages.indexOf(pageSlug()) === -1 || !$ || !$.fn || !$.fn.dataTable) {
        return;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function accessLevel() {
        return String($('#access_level').val() || '');
    }

    function canManageEmployees() {
        return ['1', '2', '3'].indexOf(accessLevel()) !== -1;
    }

    function employeeManagementUrl(employeeId, action) {
        var params = new URLSearchParams();
        params.set('from_data_issue', '1');
        params.set('employee_id', String(employeeId));
        params.set('action', action);
        params.set('return_to', window.location.pathname + window.location.search);
        return '../employee-management/?' + params.toString();
    }

    function employeeCell(employeeId) {
        var safeId = escapeHtml(employeeId);
        if (!canManageEmployees()) {
            return '<span class="data-issue-employee-id" title="Ask an authorized HR, Payroll, or Admin user to manage this employee">'
                + safeId + '</span>';
        }

        var actions = ''
            + '<a class="data-issue-action" href="' + employeeManagementUrl(employeeId, 'edit') + '"'
            + ' aria-label="Update employee ' + safeId + '" title="Update employee"><i class="bx bx-pencil" aria-hidden="true"></i></a>'
            + '<a class="data-issue-action data-issue-action-warning" href="' + employeeManagementUrl(employeeId, 'terminate') + '"'
            + ' aria-label="Terminate employee ' + safeId + '" title="Terminate employee"><i class="bx bx-user-x" aria-hidden="true"></i></a>';

        if (accessLevel() === '1') {
            actions += '<a class="data-issue-action data-issue-action-danger" href="' + employeeManagementUrl(employeeId, 'delete') + '"'
                + ' aria-label="Delete employee ' + safeId + '" title="Delete employee"><i class="bx bx-trash" aria-hidden="true"></i></a>';
        }

        return '<div class="data-issue-employee-cell">'
            + '<a class="data-issue-employee-link" href="' + employeeManagementUrl(employeeId, 'edit') + '"'
            + ' aria-label="Open employee ' + safeId + ' in Employee Management">' + safeId + '</a>'
            + '<span class="data-issue-row-actions">' + actions + '</span></div>';
    }

    function columnIndex(api, acceptedLabels) {
        var index = -1;
        $(api.table().header()).find('th').each(function (column) {
            var label = $(this).text().replace(/\s+/g, ' ').trim().toLowerCase();
            if (acceptedLabels.indexOf(label) !== -1) {
                index = column;
                return false;
            }
        });
        return index;
    }

    function uniqueSorted(values) {
        var seen = {};
        return values.map(function (value) {
            return String(value == null ? '' : value).trim();
        }).filter(function (value) {
            if (!value || seen[value]) {
                return false;
            }
            seen[value] = true;
            return true;
        }).sort(function (left, right) {
            return left.localeCompare(right);
        });
    }

    function addOptions(select, values) {
        values.forEach(function (item) {
            var value = typeof item === 'string' ? item : item.value;
            var label = typeof item === 'string' ? item : item.label;
            select.append(new Option(label, value));
        });
    }

    function decorateRows(api) {
        api.rows({page: 'current'}).every(function () {
            var data = this.data();
            var employeeId = data && data.length ? String(data[0] == null ? '' : data[0]).trim() : '';
            var node = this.node();
            if (!employeeId || !node) {
                return;
            }
            var cell = $('td', node).get(0);
            if (cell) {
                cell.innerHTML = employeeCell(employeeId);
            }
        });
    }

    function exactColumnSearch(api, index, value) {
        if (index < 0) {
            return;
        }
        var regex = value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '';
        api.column(index).search(regex, true, false);
    }

    function updateSummary(api) {
        var visible = api.rows({search: 'applied'}).count();
        var total = api.rows().count();
        $('#dataIssueScopeSummary').text(
            visible + ' of ' + total + ' active employee exceptions shown.'
        );
    }

    function buildToolbar(api) {
        var clientIndex = columnIndex(api, ['client', 'client name']);
        var branchIndex = columnIndex(api, ['branch', 'branch name']);
        var locationIndex = columnIndex(api, ['client location']);
        var data = api.rows().data().toArray();
        var clients = clientIndex >= 0 ? uniqueSorted(data.map(function (row) {
            return row[clientIndex];
        })) : [];
        var populations = [];
        var seenPopulations = {};

        data.forEach(function (row) {
            var branch = branchIndex >= 0 ? String(row[branchIndex] || '').trim() : '';
            var location = locationIndex >= 0 ? String(row[locationIndex] || '').trim() : '';
            if (!branch && !location) {
                return;
            }
            var value = branch + '|||' + location;
            if (seenPopulations[value]) {
                return;
            }
            seenPopulations[value] = true;
            populations.push({
                value: value,
                label: [branch, location].filter(Boolean).join(' — ')
            });
        });
        populations.sort(function (left, right) {
            return left.label.localeCompare(right.label);
        });

        $('#dataIssueToolbar').remove();
        $('#table_container').before(
            '<section class="data-issue-toolbar" id="dataIssueToolbar" aria-labelledby="dataIssueScopeTitle">'
            + '<div class="data-issue-toolbar-copy"><h6 id="dataIssueScopeTitle">Review scope</h6>'
            + '<p>Filter the active employee exceptions before opening a record.</p></div>'
            + '<div class="data-issue-filter"><label for="dataIssueClientFilter">Client</label>'
            + '<select class="form-select form-select-sm" id="dataIssueClientFilter"><option value="">All clients</option></select></div>'
            + '<div class="data-issue-filter"><label for="dataIssuePopulationFilter">Population (Branch / Location)</label>'
            + '<select class="form-select form-select-sm" id="dataIssuePopulationFilter"><option value="">All populations</option></select></div>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary data-issue-clear" id="dataIssueClearFilters">'
            + '<i class="bx bx-reset" aria-hidden="true"></i> Clear filters</button>'
            + '<p class="data-issue-scope-summary" id="dataIssueScopeSummary" aria-live="polite"></p>'
            + '</section>'
        );

        addOptions($('#dataIssueClientFilter'), clients);
        addOptions($('#dataIssuePopulationFilter'), populations);

        $('#dataIssueClientFilter').on('change', function () {
            exactColumnSearch(api, clientIndex, this.value);
            api.draw();
        });

        $('#dataIssuePopulationFilter').on('change', function () {
            var selected = this.value ? this.value.split('|||') : ['', ''];
            exactColumnSearch(api, branchIndex, selected[0] || '');
            exactColumnSearch(api, locationIndex, selected[1] || '');
            api.draw();
        });

        $('#dataIssueClearFilters').on('click', function () {
            $('#dataIssueClientFilter').val('');
            $('#dataIssuePopulationFilter').val('');
            exactColumnSearch(api, clientIndex, '');
            exactColumnSearch(api, branchIndex, '');
            exactColumnSearch(api, locationIndex, '');
            api.search('');
            api.draw();
        });

        api.on('draw.dataIssueTracker', function () {
            decorateRows(api);
            updateSummary(api);
        });
        decorateRows(api);
        updateSummary(api);
    }

    $(document).on('init.dt.dataIssueTracker', function (_event, settings) {
        if (!settings || !settings.nTable || settings.nTable.id !== 'incompleteTbl') {
            return;
        }
        buildToolbar(new $.fn.dataTable.Api(settings));
    });
}(window, document, window.jQuery));
