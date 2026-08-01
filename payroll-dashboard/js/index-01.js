(function ($) {
  'use strict';

  const endpoint = 'controller/DashboardController.php';
  const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
  const integerFormatter = new Intl.NumberFormat('en-PH', {
    maximumFractionDigits: 0
  });

  $(function () {
    bindDashboardEvents();
    loadClients();
  });

  function bindDashboardEvents() {
    $('#payrollClientFilter').on('change', function () {
      const clientId = String($(this).val() || '');
      resetPayDateFilter();
      hideDashboardContent();
      if (!clientId) {
        showPageState(
          'instruction',
          'Choose a payroll client',
          'The dashboard will then list dates found in DTR, payroll results, governed runs, or posting locks.'
        );
        return;
      }
      loadPayDates(clientId);
    });

    $('#payrollDateFilter').on('change', function () {
      const clientId = String($('#payrollClientFilter').val() || '');
      const payDate = String($(this).val() || '');
      if (!clientId || !payDate) {
        hideDashboardContent();
        return;
      }
      loadSnapshot(clientId, payDate);
    });

    $('#refreshDashboardBtn').on('click', function () {
      const clientId = String($('#payrollClientFilter').val() || '');
      const payDate = String($('#payrollDateFilter').val() || '');
      if (!clientId) {
        loadClients();
        return;
      }
      if (payDate) {
        loadSnapshot(clientId, payDate);
      } else {
        loadPayDates(clientId);
      }
    });
  }

  function loadClients() {
    const $client = $('#payrollClientFilter');
    $('#refreshDashboardBtn').prop('disabled', true);
    $client.prop('disabled', true).empty().append(new Option('Loading clients…', ''));
    resetPayDateFilter();
    hideDashboardContent();
    showPageState('loading', 'Loading payroll access', 'Checking the clients available to your role.');

    dashboardRequest('get-dashboard-filters')
      .done(function (response) {
        if (!requireSuccess(response)) {
          return;
        }
        const clients = Array.isArray(response.data) ? response.data : [];
        $client.empty().append(new Option('Select a client', ''));
        clients.forEach(function (client) {
          const suffix = Number(client.is_active) === 1 ? '' : ' · Inactive';
          $client.append(new Option(String(client.client_name || '') + suffix, String(client.client_id || '')));
        });
        $client.prop('disabled', clients.length === 0);
        $('#refreshDashboardBtn').prop('disabled', false);

        if (clients.length === 0) {
          showPageState(
            'empty',
            'No payroll clients are available',
            'Ask an administrator to confirm your payroll role and client configuration.'
          );
          return;
        }

        showPageState(
          'instruction',
          'Choose a payroll client',
          'The dashboard shows one exact pay date at a time so data from different cycles is never mixed.'
        );
        if (clients.length === 1) {
          $client.val(String(clients[0].client_id)).trigger('change');
        }
      })
      .fail(handleRequestFailure);
  }

  function loadPayDates(clientId) {
    const $date = $('#payrollDateFilter');
    $date.prop('disabled', true).empty().append(new Option('Loading pay dates…', ''));
    $('#refreshDashboardBtn').prop('disabled', true);
    showPageState('loading', 'Loading payroll cycles', 'Reviewing DTR, payroll results, run, and posting sources.');

    dashboardRequest('get-pay-date-filters', { client_id: clientId })
      .done(function (response) {
        if (!requireSuccess(response)) {
          return;
        }
        const dates = Array.isArray(response.data) ? response.data : [];
        $date.empty().append(new Option('Select a pay date', ''));
        dates.forEach(function (scope) {
          $date.append(new Option(payDateOptionLabel(scope), String(scope.pay_date || '')));
        });
        $date.prop('disabled', dates.length === 0);
        $('#refreshDashboardBtn').prop('disabled', false);

        if (dates.length === 0) {
          showPageState(
            'empty',
            'No payroll cycle was found for this client',
            'There are no DTR rows, payroll results, governed runs, or posting locks to review yet.'
          );
          return;
        }

        const defaultDate = String(response.default_pay_date || dates[0].pay_date || '');
        $date.val(defaultDate).trigger('change');
      })
      .fail(handleRequestFailure);
  }

  function loadSnapshot(clientId, payDate) {
    $('#refreshDashboardBtn').prop('disabled', true);
    $('#payrollClientFilter, #payrollDateFilter').prop('disabled', true);
    hideDashboardContent();
    showPageState('loading', 'Loading payroll evidence', 'Calculating source-backed totals and control status.');

    dashboardRequest('get-dashboard-snapshot', {
      client_id: clientId,
      pay_date: payDate
    })
      .done(function (response) {
        if (!requireSuccess(response)) {
          return;
        }
        if (response.state === 'empty') {
          showPageState(
            'empty',
            'No payroll evidence exists for this scope',
            'Choose another pay date or begin the payroll workflow for this cycle.'
          );
          return;
        }
        renderSnapshot(response);
      })
      .fail(handleRequestFailure)
      .always(function () {
        $('#payrollClientFilter, #payrollDateFilter').prop('disabled', false);
        $('#refreshDashboardBtn').prop('disabled', false);
      });
  }

  function renderSnapshot(snapshot) {
    const scope = snapshot.scope || {};
    const metrics = snapshot.metrics || {};
    const governance = snapshot.governance || {};
    const approval = governance.approval || {};
    const run = snapshot.run || null;
    const input = snapshot.input || {};
    const freshness = snapshot.freshness || {};

    hidePageState();
    $('#payrollDashboardContent').prop('hidden', false);

    $('#selectedScopeMode').text(modeLabel(scope.mode));
    $('#selectedScopeTitle').text((scope.client_name || 'Payroll client') + ' · ' + formatDate(scope.pay_date));
    $('#selectedScopePeriod').text(scopePeriodLabel(scope));
    setStatusPill($('#readinessPill'), governance.readiness_state || 'unknown');
    $('#freshnessText').text(freshnessLabel(freshness));

    $('#metricEmployeeCount').text(formatInteger(metrics.employee_count));
    $('#metricEmployeeContext').text(employeeMetricContext(metrics, run));
    $('#metricGrossIncome').text(formatMoney(metrics.gross_income));
    $('#metricNetPay').text(formatMoney(metrics.net_pay));
    $('#metricEmployeeDeductions').text(formatMoney(metrics.employee_deductions));
    $('#financialSourceLabel').text(financialSourceLabel(metrics, governance));

    $('#controlDataMode').text(modeLabel(scope.mode));
    $('#controlRunState').text(statusLabel(governance.run_state));
    $('#controlApprovalState').text(statusLabel(approval.state));
    $('#controlReleaseState').text(statusLabel(governance.release_state));
    setStatusPill(
      $('#postingPill'),
      governance.is_posted ? 'posted' : 'not_posted',
      governance.is_posted ? 'Posted' : 'Not posted'
    );

    renderRunStages(run, snapshot.release_checks || []);
    $('#approvalMaker').text(approval.maker || 'Not available');
    $('#approvalChecker').text(approval.checker || 'Not available');
    $('#approvalMakerAt').text(formatDateTime(approval.maker_at));
    $('#approvalCheckerAt').text(formatDateTime(approval.checker_at));
    $('#approvalMessage').text(approval.message || 'No approval evidence is available.');

    renderBlockers(governance.blockers || [], Number(governance.blocker_count || 0));

    $('#lineageDtrRows').text(formatInteger(input.dtr_row_count));
    $('#lineageDtrEmployees').text(formatInteger(input.dtr_employee_count));
    $('#lineagePayrollRows').text(formatInteger(input.legacy_payroll_row_count));
    $('#lineagePopulationExceptions').text(formatInteger(input.population_exception_count));
    $('#lineageRunUid').text(run ? run.run_uid : 'Not available');
    $('#lineageSourceFile').text(run && run.source_file_name ? run.source_file_name : 'Not available');
    $('#lineageRuleset').text(run ? rulesetLabel(run) : 'Not available');
    $('#lineageCanonicalRows').text(run ? formatInteger(run.canonical_row_count) : 'Not available');

    renderContracts(snapshot.contracts || []);
    $('#freshnessDetail').text(freshness.message || 'No freshness metadata is available.');

    const breakdown = metrics.deduction_breakdown || {};
    $('#deductionStatutory').text(formatMoney(breakdown.statutory_and_tax));
    $('#deductionLoans').text(formatMoney(breakdown.employee_loans));
    $('#deductionOther').text(formatMoney(breakdown.other_deductions));
    $('#additionalPay').text(formatMoney(metrics.additional_pay));
    $('#overtimePay').text(formatMoney(metrics.overtime_pay));
    $('#employerContributions').text(formatMoney(metrics.employer_contributions));
  }

  function renderRunStages(run, checks) {
    const $stages = $('#smartRunStages').empty();
    if (!run) {
      $('<div>', {
        class: 'payroll-stage',
        'data-tone': 'warning'
      })
        .append($('<span>').text('Governed run'))
        .append($('<strong>').text('Not available'))
        .appendTo($stages);
      return;
    }

    const blockingChecks = checks.filter(function (check) {
      return Boolean(check.is_blocking);
    });
    const passedChecks = blockingChecks.filter(function (check) {
      return String(check.status || '').toLowerCase() === 'passed';
    }).length;
    const checkStatus = blockingChecks.length > 0 && passedChecks === blockingChecks.length
      ? 'passed'
      : (blockingChecks.length === 0 ? 'not_recorded' : 'pending');

    [
      ['Run', run.status],
      ['Identity', run.identity_status],
      ['Rules', run.ruleset_status],
      ['Calculation', run.calculation_status],
      ['Reconciliation', run.reconciliation_status],
      ['Release checks', blockingChecks.length ? passedChecks + '/' + blockingChecks.length + ' passed' : 'Not recorded', checkStatus]
    ].forEach(function (stage) {
      $('<div>', {
        class: 'payroll-stage',
        'data-tone': statusTone(stage[2] || stage[1])
      })
        .append($('<span>').text(stage[0]))
        .append($('<strong>').text(statusLabel(stage[1])))
        .appendTo($stages);
    });
  }

  function renderBlockers(blockers, total) {
    const $list = $('#blockerList').empty();
    const $clear = $('#blockerClearState');
    $('#blockerTotal').text(formatInteger(total) + ' open');

    if (!blockers.length) {
      $clear.prop('hidden', false);
      return;
    }

    $clear.prop('hidden', true);
    blockers.forEach(function (blocker) {
      const $item = $('<div>', {
        class: 'payroll-blocker-item',
        'data-severity': String(blocker.severity || 'P1')
      });
      $('<span>', { class: 'payroll-blocker-item__severity' })
        .text(String(blocker.severity || 'P1'))
        .appendTo($item);
      $('<a>', {
        href: String(blocker.route || '../payroll-data-quality/')
      })
        .text(String(blocker.label || 'Payroll blocker'))
        .appendTo($item);
      $('<strong>')
        .text(formatInteger(blocker.count))
        .appendTo($item);
      $item.appendTo($list);
    });
  }

  function renderContracts(contracts) {
    const $list = $('#contractList').empty();
    contracts.forEach(function (contract) {
      const available = Boolean(contract.available);
      const count = Number(contract.record_count || 0);
      const hasVerification = Object.prototype.hasOwnProperty.call(contract, 'verified')
        && contract.verified !== null;
      const verified = hasVerification ? Boolean(contract.verified) : null;
      const detail = available
        ? (count > 0 ? formatInteger(count) + ' scope record' + (count === 1 ? '' : 's') : 'Available · no scope record')
        : 'Schema unavailable';
      const $item = $('<div>', {
        class: 'payroll-contract',
        'data-available': available && verified !== false ? 'true' : 'false'
      });
      $('<i>', {
        class: available && verified !== false ? 'bx bx-check-circle' : 'bx bx-error-circle',
        'aria-hidden': 'true'
      }).appendTo($item);
      $('<span>').text(String(contract.label || contract.key || 'Data contract')).appendTo($item);
      $('<small>').text(
        verified === false
          ? detail + ' · governed binding ' + statusLabel(contract.status || 'invalid')
          : detail
      ).appendTo($item);
      $item.appendTo($list);
    });
  }

  function financialSourceLabel(metrics, governance) {
    if (metrics.financials_available) {
      return metrics.financials_status === 'governed_binding_verified'
        ? 'Source: payroll_summary · governed binding verified'
        : 'Source: payroll_summary · legacy unverified';
    }
    const binding = governance.legacy_binding || {};
    if (metrics.financials_status === 'governed_binding_invalid') {
      return 'Financial source: hidden · ' + statusLabel(binding.state || 'binding invalid');
    }
    return 'Financial source: not available';
  }

  function dashboardRequest(request, payload) {
    const formData = new FormData();
    formData.append('request', request);
    Object.entries(payload || {}).forEach(function (entry) {
      formData.append(entry[0], entry[1]);
    });

    return $.ajax({
      url: endpoint,
      method: 'POST',
      data: formData,
      dataType: 'json',
      contentType: false,
      processData: false
    });
  }

  function requireSuccess(response) {
    if (!response || Number(response.success) !== 1) {
      handleRequestFailure({
        responseJSON: {
          error: response && response.error ? response.error : 'The payroll dashboard request failed.'
        }
      });
      return false;
    }
    return true;
  }

  function handleRequestFailure(xhr) {
    const response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
    const message = response && response.error
      ? response.error
      : 'The payroll dashboard could not load this data. Refresh the page or contact an administrator.';
    hideDashboardContent();
    showPageState('error', 'Payroll dashboard unavailable', message);
    const hasClients = $('#payrollClientFilter option').length > 1;
    $('#payrollClientFilter').prop('disabled', !hasClients);
    const hasDates = $('#payrollDateFilter option').length > 1;
    $('#payrollDateFilter').prop('disabled', !hasDates);
    $('#refreshDashboardBtn').prop('disabled', false);
  }

  function showPageState(type, title, detail) {
    const $state = $('#payrollDashboardState');
    $state
      .prop('hidden', false)
      .removeClass('payroll-page-state--loading payroll-page-state--error payroll-page-state--empty payroll-page-state--instruction')
      .addClass('payroll-page-state--' + type)
      .empty();
    if (type === 'loading') {
      $('<span>', {
        class: 'spinner-border spinner-border-sm',
        'aria-hidden': 'true'
      }).appendTo($state);
    } else {
      $('<i>', {
        class: type === 'error' ? 'bx bx-error-circle bx-sm' : 'bx bx-info-circle bx-sm',
        'aria-hidden': 'true'
      }).appendTo($state);
    }
    $('<div>')
      .append($('<strong>').text(title))
      .append($('<span>').text(detail))
      .appendTo($state);
  }

  function hidePageState() {
    $('#payrollDashboardState').prop('hidden', true);
  }

  function hideDashboardContent() {
    $('#payrollDashboardContent').prop('hidden', true);
  }

  function resetPayDateFilter() {
    $('#payrollDateFilter')
      .prop('disabled', true)
      .empty()
      .append(new Option('Select a client first', ''));
  }

  function payDateOptionLabel(scope) {
    const sources = Array.isArray(scope.sources) ? scope.sources : [];
    const sourceLabels = {
      smart_run: 'Governed run',
      payroll_results: 'Payroll results',
      dtr_basis: 'DTR',
      posting_lock: 'Posted'
    };
    const labels = sources.map(function (source) {
      return sourceLabels[source] || statusLabel(source);
    });
    return formatDate(scope.pay_date) + (labels.length ? ' · ' + labels.join(' + ') : '');
  }

  function scopePeriodLabel(scope) {
    const start = formatDate(scope.period_start);
    const end = formatDate(scope.period_end);
    const cutoff = scope.cut_off ? ' · Cutoff ' + scope.cut_off : '';
    if (start === 'Not available' && end === 'Not available') {
      return 'Payroll period not available' + cutoff;
    }
    return start + ' – ' + end + cutoff;
  }

  function employeeMetricContext(metrics, run) {
    if (metrics.financials_available) {
      return 'Distinct employees in payroll results';
    }
    if (run) {
      return 'From governed run; financial results unavailable';
    }
    return 'From DTR basis; financial results unavailable';
  }

  function rulesetLabel(run) {
    const key = String(run.ruleset_key || '').trim();
    const version = String(run.ruleset_version || '').trim();
    if (!key && !version) {
      return 'Not available';
    }
    return key + (version ? ' · ' + version : '');
  }

  function modeLabel(mode) {
    const labels = {
      smart_run: 'Governed smart run',
      legacy_scope: 'Legacy payroll results',
      dtr_only: 'DTR inputs only',
      lock_only: 'Posting lock only',
      empty: 'No payroll evidence'
    };
    return labels[String(mode || '')] || statusLabel(mode);
  }

  function setStatusPill($element, status, label) {
    $element
      .attr('data-tone', statusTone(status))
      .text(label || statusLabel(status));
  }

  function statusTone(status) {
    const value = String(status || '').toLowerCase();
    if (/(blocked|failed|error|collision|invalid)/.test(value)) {
      return 'danger';
    }
    if (/(pending|unverified|unavailable|not_|draft|staged|canonicalized)/.test(value)) {
      return 'warning';
    }
    if (/(passed|approved|ready|released|resolved|locked|posted|clear)/.test(value)) {
      return 'success';
    }
    return 'info';
  }

  function statusLabel(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
      return 'Not available';
    }
    return String(value)
      .replace(/_/g, ' ')
      .replace(/\b\w/g, function (character) {
        return character.toUpperCase();
      });
  }

  function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
      return 'Not available';
    }
    const number = Number(value);
    return Number.isFinite(number) ? currencyFormatter.format(number) : 'Not available';
  }

  function formatInteger(value) {
    if (value === null || value === undefined || value === '') {
      return 'Not available';
    }
    const number = Number(value);
    return Number.isFinite(number) ? integerFormatter.format(number) : 'Not available';
  }

  function formatDate(value) {
    if (!value) {
      return 'Not available';
    }
    const parts = String(value).slice(0, 10).split('-').map(Number);
    if (parts.length !== 3 || parts.some(function (part) { return !Number.isFinite(part); })) {
      return 'Not available';
    }
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    if (Number.isNaN(date.getTime())) {
      return 'Not available';
    }
    return new Intl.DateTimeFormat('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    }).format(date);
  }

  function formatDateTime(value) {
    if (!value) {
      return 'Not available';
    }
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
      return 'Not available';
    }
    return new Intl.DateTimeFormat('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    }).format(date);
  }

  function freshnessLabel(freshness) {
    const timestamp = formatDateTime(freshness.timestamp);
    if (timestamp !== 'Not available') {
      return 'Evidence timestamp: ' + timestamp;
    }
    return 'Freshness unavailable · ' + statusLabel(freshness.source);
  }
})(jQuery);
