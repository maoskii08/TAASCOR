(function (window, document, $) {
    'use strict';

    var state = {
        filters: null,
        response: null,
        mode: 'executive',
        table: null,
        chart: null
    };
    var money = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    var wholeNumber = new Intl.NumberFormat('en-PH', {maximumFractionDigits: 0});

    $(document).ready(function () {
        state.mode = String($('#access_level').val()) === '3' ? 'payroll' : 'executive';
        $('input[name="review_mode"][value="' + state.mode + '"]').prop('checked', true);
        bindEvents();
        loadFilters();
    });

    function bindEvents() {
        $('#payrollSummaryFilters').on('submit', function (event) {
            event.preventDefault();
            loadSummary();
        });
        $('#resetPayrollFilters').on('click', function () {
            setQuickPeriod('latest');
            $('#payrollClient').val('');
            $('#payrollCutoff').val('');
            loadSummary();
        });
        $('#payrollQuickPeriod').on('change', function () {
            setQuickPeriod(this.value);
        });
        $('#payrollDateFrom, #payrollDateTo').on('change', function () {
            $('#payrollQuickPeriod').val('custom');
        });
        $('input[name="review_mode"]').on('change', function () {
            state.mode = this.value;
            renderMode();
        });
    }

    function postRequest(payload) {
        var formdata = new FormData();
        Object.keys(payload).forEach(function (key) {
            formdata.append(key, payload[key]);
        });

        return $.ajax({
            url: 'controller/PayrollController.php',
            data: formdata,
            type: 'POST',
            dataType: 'json',
            contentType: false,
            processData: false
        });
    }

    function loadFilters() {
        postRequest({request: 'get-payroll-filters'})
            .done(function (response) {
                if (!response || response.success !== 1) {
                    showError(response && response.error ? response.error : 'Unable to load report filters.');
                    return;
                }
                state.filters = response.data;
                populateSelect($('#payrollClient'), response.data.clients || []);
                populateSelect($('#payrollCutoff'), response.data.cutoffs || []);
                configureDateInputs(response.data.coverage || {});
                renderSourceNotice(response.data.coverage || {}, response.data.historical_archive || {});
                setQuickPeriod('latest');
                loadSummary();
            })
            .fail(function (xhr) {
                showError(requestError(xhr, 'Unable to load Payroll Summary filters.'));
            });
    }

    function populateSelect(select, values) {
        values.forEach(function (value) {
            select.append(new Option(value, value));
        });
    }

    function configureDateInputs(coverage) {
        ['#payrollDateFrom', '#payrollDateTo'].forEach(function (selector) {
            $(selector)
                .attr('min', coverage.min_pay_day || '')
                .attr('max', coverage.max_pay_day || '');
        });
    }

    function setQuickPeriod(period) {
        if (!state.filters || !state.filters.coverage || period === 'custom') {
            return;
        }
        var coverage = state.filters.coverage;
        var minDate = parseDate(coverage.min_pay_day);
        var maxDate = parseDate(coverage.max_pay_day);
        if (!minDate || !maxDate) {
            return;
        }
        var from = new Date(maxDate.getTime());
        var to = new Date(maxDate.getTime());

        if (period === '30' || period === '90') {
            from.setUTCDate(from.getUTCDate() - (Number(period) - 1));
            if (from < minDate) {
                from = new Date(minDate.getTime());
            }
        } else if (period === 'ytd') {
            from = new Date(Date.UTC(maxDate.getUTCFullYear(), 0, 1));
            if (from < minDate) {
                from = new Date(minDate.getTime());
            }
        } else if (period === 'all') {
            from = new Date(minDate.getTime());
        }

        $('#payrollQuickPeriod').val(period);
        $('#payrollDateFrom').val(formatInputDate(from));
        $('#payrollDateTo').val(formatInputDate(to));
    }

    function loadSummary() {
        var dateFrom = $('#payrollDateFrom').val();
        var dateTo = $('#payrollDateTo').val();
        if (!dateFrom || !dateTo) {
            showError('Choose both From and To dates before applying the report.');
            return;
        }

        setLoading(true);
        postRequest({
            request: 'get-payroll-summary',
            date_from: dateFrom,
            date_to: dateTo,
            client: $('#payrollClient').val() || '',
            cut_off: $('#payrollCutoff').val() || ''
        })
            .done(function (response) {
                if (!response || response.success !== 1) {
                    showError(response && response.error ? response.error : 'Unable to load Payroll Summary.');
                    return;
                }
                state.response = response;
                renderSourceNotice(
                    response.source.canonical || {},
                    response.source.historical_archive || {}
                );
                renderKpis(response.summary || {});
                renderReviewContext(response);
                renderMode();
            })
            .fail(function (xhr) {
                showError(requestError(xhr, 'Unable to load Payroll Summary.'));
            })
            .always(function () {
                setLoading(false);
            });
    }

    function setLoading(isLoading) {
        $('#applyPayrollFilters')
            .prop('disabled', isLoading)
            .html(
                isLoading
                    ? '<i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Loading'
                    : '<i class="bx bx-filter-alt" aria-hidden="true"></i> Apply filters'
            );
        if (isLoading) {
            $('#table_container').html(
                '<div class="payroll-empty-state">'
                + '<i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>'
                + '<strong>Loading scoped payroll</strong>'
                + '<span>Aggregating the selected client, cutoff, and pay-date range.</span>'
                + '</div>'
            );
        }
    }

    function renderMode() {
        if (!state.response) {
            return;
        }
        var isExecutive = state.mode === 'executive';
        $('.payroll-review-layout').toggleClass('is-payroll-mode', !isExecutive);
        $('#payrollChartPanel').toggle(isExecutive);
        $('#payrollTableTitle').text(isExecutive ? 'Executive payroll overview' : 'Payroll component review');
        $('#payrollTableSubtitle').text(
            isExecutive
                ? 'Client-level payroll cost and variance for the selected scope.'
                : 'Detailed earnings, deductions, and employer contributions for reconciliation.'
        );
        renderTable(state.response.data || []);
        if (isExecutive) {
            renderChart(state.response.data || []);
        } else {
            destroyChart();
        }
    }

    function renderSourceNotice(coverage, historical) {
        var min = friendlyDate(coverage.min_pay_day);
        var max = friendlyDate(coverage.max_pay_day);
        var historicalIncluded = historical.included_in_summary === true;
        $('#payrollSourceNotice')
            .toggleClass('is-warning', !historicalIncluded)
            .html(
                '<div class="payroll-source-notice-icon" aria-hidden="true"><i class="bx '
                + (historicalIncluded ? 'bx-check-shield' : 'bx-info-circle')
                + '"></i></div>'
                + '<div><strong>'
                + escapeHtml(coverage.label || 'Canonical employee-level HRIS payroll')
                + '</strong><p>'
                + 'Included coverage: '
                + escapeHtml(min || 'not available')
                + ' to '
                + escapeHtml(max || 'not available')
                + ', '
                + wholeNumber.format(Number(coverage.row_count || 0))
                + ' employee-payroll rows. '
                + (historicalIncluded
                    ? 'The validated historical archive is included.'
                    : 'The shared Google Drive archive for 2017-2026 is not included: '
                        + escapeHtml(historical.status || 'not loaded into HRIS')
                        + '.')
                + '</p></div>'
            );
    }

    function renderKpis(summary) {
        var metrics = [
            {
                label: 'Gross payroll',
                value: currency(summary.total_gross_income),
                delta: summary.gross_variance_pct,
                meta: 'Before employee deductions'
            },
            {
                label: 'Net pay',
                value: currency(summary.total_net_pay),
                delta: summary.net_variance_pct,
                meta: percent(summary.net_to_gross_pct) + ' of gross payroll'
            },
            {
                label: 'Employees',
                value: wholeNumber.format(Number(summary.employee_count || 0)),
                delta: summary.employee_variance_pct,
                meta: wholeNumber.format(Number(summary.client_count || 0))
                    + (Number(summary.client_count || 0) === 1 ? ' client in scope' : ' clients in scope')
            },
            {
                label: 'Employee deductions',
                value: currency(summary.employee_deductions),
                delta: null,
                meta: 'Tax, statutory, loans, tardy, other'
            },
            {
                label: 'Employer contributions',
                value: currency(summary.employer_contributions),
                delta: summary.employer_variance_pct,
                meta: 'Total payroll cost ' + currency(summary.total_payroll_cost)
            }
        ];
        $('#payrollKpiBand').html(metrics.map(function (metric) {
            return '<div class="payroll-kpi">'
                + '<div class="payroll-kpi-label"><span>' + escapeHtml(metric.label) + '</span>'
                + deltaBadge(metric.delta) + '</div>'
                + '<div class="payroll-kpi-value" title="' + escapeHtml(metric.value) + '">'
                + escapeHtml(metric.value) + '</div>'
                + '<div class="payroll-kpi-meta">' + escapeHtml(metric.meta) + '</div>'
                + '</div>';
        }).join(''));
    }

    function renderReviewContext(response) {
        var scope = response.scope || {};
        var source = response.source || {};
        var scopedClient = scope.client || 'All clients';
        var cutoff = scope.cut_off || 'All cutoffs';
        var period = friendlyDate(scope.date_from) + ' to ' + friendlyDate(scope.date_to);
        var historyText = source.historical_included
            ? 'Historical archive included'
            : 'Canonical HRIS only; shared-drive archive excluded';

        $('#payrollReviewContext').html(
            '<div class="payroll-review-context-list">'
            + contextItem('bx-calendar-check', 'Selected scope', period + ' · ' + scopedClient + ' · ' + cutoff)
            + contextItem('bx-git-compare', 'Comparison basis', scope.comparison_label || 'No comparison available')
            + contextItem('bx-data', 'Data lineage', historyText)
            + contextItem('bx-check-square', 'Review standard', 'Confirm population, gross-to-net movement, statutory totals, and material variance before release.')
            + '</div>'
        );
        $('#payrollChartSubtitle').text(period + ' · ' + scopedClient + ' · Top 10 by gross payroll');
    }

    function contextItem(icon, title, detail) {
        return '<div class="payroll-context-item">'
            + '<i class="bx ' + icon + '" aria-hidden="true"></i>'
            + '<div><strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(detail) + '</span></div>'
            + '</div>';
    }

    function renderTable(rows) {
        if (state.table) {
            state.table.destroy();
            state.table = null;
        }
        if (!rows.length) {
            $('#payrollResultCount').text('0 clients');
            $('#table_container').html(
                '<div class="payroll-empty-state">'
                + '<i class="bx bx-search-alt" aria-hidden="true"></i>'
                + '<strong>No canonical payroll matches this scope</strong>'
                + '<span>Try another client, cutoff, or pay-date range. The shared-drive historical archive is not searched.</span>'
                + '</div>'
            );
            return;
        }

        var columns = state.mode === 'executive' ? executiveColumns() : payrollColumns();
        $('#payrollResultCount').text(
            rows.length + (rows.length === 1 ? ' client' : ' clients')
        );
        $('#table_container').html(
            '<table id="payrollTbl" class="table table-bordered table-sm nowrap" style="width:100%">'
            + '<thead><tr>'
            + columns.map(function (column) {
                return '<th>' + escapeHtml(column.title) + '</th>';
            }).join('')
            + '</tr></thead></table>'
        );

        state.table = $('#payrollTbl').DataTable({
            data: rows,
            columns: columns,
            responsive: false,
            lengthChange: true,
            pageLength: 25,
            paging: true,
            searching: true,
            ordering: true,
            order: state.mode === 'executive' ? [[3, 'desc']] : [[0, 'asc']],
            info: true,
            scrollX: true,
            layout: {topStart: 'buttons'},
            buttons: [{
                extend: 'excel',
                title: null,
                className: 'btn btn-sm btn-outline-secondary',
                text: '<i class="bx bx-download"></i> Download scoped summary',
                filename: exportFilename(),
                exportOptions: {
                    format: {
                        header: function (data) {
                            return $('<div>').html(data).text();
                        },
                        body: function (data) {
                            return $('<div>').html(data).text();
                        }
                    }
                }
            }]
        });
    }

    function executiveColumns() {
        return [
            textColumn('Client', 'client_name', true),
            numberColumn('Employees', 'employee_count'),
            numberColumn('Payroll Runs', 'payroll_run_count'),
            moneyColumn('Gross Payroll', 'total_gross_income'),
            varianceColumn('Gross vs Prior', 'gross_variance_pct'),
            moneyColumn('Employee Deductions', 'employee_deductions'),
            moneyColumn('Net Pay', 'total_net_pay'),
            varianceColumn('Net vs Prior', 'net_variance_pct'),
            moneyColumn('Employer Contributions', 'employer_contributions'),
            moneyColumn('Total Payroll Cost', 'total_payroll_cost'),
            percentColumn('Net / Gross', 'net_to_gross_pct')
        ];
    }

    function payrollColumns() {
        return [
            textColumn('Client', 'client_name', true),
            numberColumn('Employees', 'employee_count'),
            numberColumn('Payroll Runs', 'payroll_run_count'),
            {
                title: 'Pay Date Coverage',
                data: null,
                render: function (_data, type, row) {
                    var value = friendlyDate(row.first_pay_day)
                        + (row.first_pay_day === row.last_pay_day ? '' : ' – ' + friendlyDate(row.last_pay_day));
                    return type === 'display' ? escapeHtml(value) : value;
                }
            },
            moneyColumn('Basic Pay', 'total_basic_pay'),
            moneyColumn('OT', 'total_ot'),
            moneyColumn('Leaves', 'total_leaves'),
            moneyColumn('Other Additional', 'total_other_additional'),
            moneyColumn('Gross Income', 'total_gross_income'),
            moneyColumn('Taxable Income', 'total_taxable'),
            moneyColumn('Tax', 'total_tax'),
            moneyColumn('Tardy', 'total_tardy'),
            moneyColumn('Employee SSS', 'total_employee_sss'),
            moneyColumn('Employee SSS MPF', 'total_employee_sss_mpf'),
            moneyColumn('Employee PhilHealth', 'total_employee_philhealth'),
            moneyColumn('Employee Pag-IBIG', 'total_employee_pagibig'),
            moneyColumn('Employee Loan', 'total_employee_loan'),
            moneyColumn('Other Deduction', 'total_other_deduction'),
            moneyColumn('Net Pay', 'total_net_pay'),
            moneyColumn('13th Month', 'total_13th_month'),
            moneyColumn('Employer SSS', 'total_employer_sss'),
            moneyColumn('Employer SSS MPF', 'total_employer_sss_mpf'),
            moneyColumn('Employer SSS EC', 'total_employer_sss_ec'),
            moneyColumn('Employer PhilHealth', 'total_employer_philhealth'),
            moneyColumn('Employer Pag-IBIG', 'total_employer_pagibig')
        ];
    }

    function textColumn(title, field, emphasize) {
        return {
            title: title,
            data: field,
            render: function (value, type) {
                if (type !== 'display') {
                    return value;
                }
                return emphasize
                    ? '<span class="payroll-client-name" title="' + escapeHtml(value) + '">' + escapeHtml(value) + '</span>'
                    : escapeHtml(value);
            }
        };
    }

    function numberColumn(title, field) {
        return {
            title: title,
            data: field,
            className: 'text-end',
            render: function (value, type) {
                return type === 'display' ? wholeNumber.format(Number(value || 0)) : Number(value || 0);
            }
        };
    }

    function moneyColumn(title, field) {
        return {
            title: title,
            data: field,
            className: 'text-end',
            render: function (value, type) {
                return type === 'display' ? currency(value) : Number(value || 0);
            }
        };
    }

    function varianceColumn(title, field) {
        return {
            title: title,
            data: field,
            className: 'text-end',
            render: function (value, type) {
                return type === 'display'
                    ? deltaBadge(value, true)
                    : (value == null ? -Infinity : Number(value));
            }
        };
    }

    function percentColumn(title, field) {
        return {
            title: title,
            data: field,
            className: 'text-end',
            render: function (value, type) {
                return type === 'display' ? percent(value) : Number(value || 0);
            }
        };
    }

    function renderChart(rows) {
        destroyChart();
        if (!rows.length || typeof window.ApexCharts !== 'function') {
            return;
        }
        var ranked = rows.slice().sort(function (left, right) {
            return Number(right.total_gross_income) - Number(left.total_gross_income);
        }).slice(0, 10);
        var options = {
            chart: {
                type: 'bar',
                height: Math.max(300, ranked.length * 42),
                toolbar: {show: false},
                animations: {enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches}
            },
            series: [
                {name: 'Gross Payroll', data: ranked.map(function (row) { return Number(row.total_gross_income); })},
                {name: 'Net Pay', data: ranked.map(function (row) { return Number(row.total_net_pay); })}
            ],
            colors: ['#696cff', '#36b37e'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '58%',
                    borderRadius: 4
                }
            },
            dataLabels: {enabled: false},
            xaxis: {
                categories: ranked.map(function (row) { return row.client_name; }),
                labels: {
                    formatter: function (value) {
                        return compactCurrency(value);
                    }
                }
            },
            yaxis: {
                labels: {
                    maxWidth: 180
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return currency(value);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left'
            },
            grid: {
                borderColor: '#edf0f3'
            }
        };
        state.chart = new window.ApexCharts(document.querySelector('#payrollClientChart'), options);
        state.chart.render();
    }

    function destroyChart() {
        if (state.chart) {
            state.chart.destroy();
            state.chart = null;
        }
        $('#payrollClientChart').empty();
    }

    function exportFilename() {
        var scope = state.response && state.response.scope ? state.response.scope : {};
        var client = (scope.client || 'All_Clients').replace(/[^A-Za-z0-9_-]+/g, '_');
        return [
            'Payroll_Summary',
            state.mode === 'executive' ? 'Executive' : 'Payroll_Review',
            client,
            scope.date_from || '',
            scope.date_to || ''
        ].filter(Boolean).join('_');
    }

    function showError(message) {
        $('#table_container').html(
            '<div class="payroll-empty-state payroll-error-state" role="alert">'
            + '<i class="bx bx-error-circle" aria-hidden="true"></i>'
            + '<strong>Payroll Summary could not be loaded</strong>'
            + '<span>' + escapeHtml(message) + '</span>'
            + '</div>'
        );
        $('#payrollResultCount').text('');
    }

    function requestError(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
            return xhr.responseJSON.error;
        }
        if (xhr && xhr.responseText) {
            try {
                var parsed = JSON.parse(xhr.responseText);
                return parsed.error || fallback;
            } catch (_error) {}
        }
        return fallback;
    }

    function deltaBadge(value, fullLabel) {
        if (value == null || !Number.isFinite(Number(value))) {
            return '<span class="payroll-delta is-na">No prior</span>';
        }
        var number = Number(value);
        var className = number > 0.05 ? 'is-up' : (number < -0.05 ? 'is-down' : 'is-flat');
        var icon = number > 0.05 ? 'bx-up-arrow-alt' : (number < -0.05 ? 'bx-down-arrow-alt' : 'bx-minus');
        var label = (number > 0 ? '+' : '') + number.toFixed(1) + '%';
        return '<span class="payroll-delta ' + className + '" title="Change versus prior comparison">'
            + '<i class="bx ' + icon + '" aria-hidden="true"></i>'
            + (fullLabel ? label : '<span>' + label + '</span>')
            + '</span>';
    }

    function currency(value) {
        return money.format(Number(value || 0));
    }

    function compactCurrency(value) {
        var number = Number(value || 0);
        if (Math.abs(number) >= 1000000) {
            return '₱' + (number / 1000000).toFixed(1) + 'M';
        }
        if (Math.abs(number) >= 1000) {
            return '₱' + (number / 1000).toFixed(0) + 'K';
        }
        return '₱' + number.toFixed(0);
    }

    function percent(value) {
        return value == null || !Number.isFinite(Number(value))
            ? 'Not available'
            : Number(value).toFixed(1) + '%';
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }
        var parts = value.split('-').map(Number);
        return parts.length === 3
            ? new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]))
            : null;
    }

    function formatInputDate(date) {
        return date.toISOString().slice(0, 10);
    }

    function friendlyDate(value) {
        var date = parseDate(value);
        return date
            ? new Intl.DateTimeFormat('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                timeZone: 'UTC'
            }).format(date)
            : '';
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }
}(window, document, window.jQuery));
