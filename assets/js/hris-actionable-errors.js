(function (window, document) {
    'use strict';

    var componentScript = document.currentScript;
    var detectedAppRoot = (function () {
        if (componentScript && componentScript.src) {
            var scriptPath = new URL(componentScript.src, window.location.origin).pathname;
            var assetMarker = '/assets/js/hris-actionable-errors.js';
            var assetIndex = scriptPath.indexOf(assetMarker);
            if (assetIndex >= 0) {
                return scriptPath.slice(0, assetIndex);
            }
        }
        var parts = window.location.pathname.split('/').filter(Boolean);
        var rootLevelModules = [
            'dtr-format-engine', 'employee-management', 'users-access', 'payroll-dashboard',
            'payroll-summary', 'payroll-data-quality', 'dtr-upload', 'other-additional',
            'other-deduction', 'payslip', 'payroll-help', 'tests'
        ];
        return parts.length && rootLevelModules.indexOf(parts[0]) === -1 ? '/' + parts[0] : '';
    })();

    var definitions = [
        {
            code: 'APPROVED_RULESET_REQUIRED',
            pattern: /no approved, effective-dated payroll ruleset|approved payroll ruleset/i,
            title: 'Payroll rules are not configured for this pay date',
            resolution: 'Configure one approved client ruleset whose effective dates include this payroll date, then retry the canonical snapshot.',
            action: 'payroll-rules',
            actionLabel: 'Configure payroll rules'
        },
        {
            code: 'RULESET_EFFECTIVE_OVERLAP',
            pattern: /more than one approved payroll ruleset|ruleset.*overlap/i,
            title: 'Payroll rule versions overlap',
            resolution: 'Review the client ruleset registry and correct the effective dates so exactly one approved version covers this pay date.',
            action: 'payroll-rules',
            actionLabel: 'Review payroll rules'
        },
        {
            code: 'RULESET_INTEGRITY_FAILED',
            pattern: /ruleset hash is invalid|ruleset integrity/i,
            title: 'The approved payroll ruleset failed its integrity check',
            resolution: 'Create a new version from the approved source. Existing immutable rule versions cannot be edited in place.',
            action: 'payroll-rules',
            actionLabel: 'Review payroll rules'
        },
        {
            code: 'RULESET_INPUT_INVALID',
            pattern: /valid client, version, effective window|rule manifest.*required/i,
            title: 'The payroll ruleset form is incomplete',
            resolution: 'Review the highlighted client, version, effective dates, and approved rule manifest before creating the version.',
            action: 'payroll-rules',
            actionLabel: 'Review ruleset fields'
        },
        {
            code: 'RULESET_EVIDENCE_REQUIRED',
            pattern: /approval evidence.*policy source|validation performed/i,
            title: 'Approval evidence is required',
            resolution: 'Identify the authorized policy source, reviewer, and validation performed so this rule version is auditable.',
            action: 'payroll-rules',
            actionLabel: 'Add approval evidence'
        },
        {
            code: 'RULESET_MANIFEST_INVALID',
            pattern: /every rule needs|manifest must be a non-empty json array/i,
            title: 'The payroll rule manifest is invalid',
            resolution: 'Correct the manifest so every entry includes a rule type, key, version, and immutable snapshot object.',
            action: 'payroll-rules',
            actionLabel: 'Correct rule manifest'
        },
        {
            code: 'EMPLOYEE_IDENTITY_BLOCKED',
            pattern: /employee identit|employee mapping|unresolved employee|confident hris/i,
            title: 'Employee identities still need review',
            resolution: 'Open Employee Identity Exceptions, correct or approve every remaining employee match, and validate the batch again.',
            action: 'identity-review',
            actionLabel: 'Resolve employee identities'
        },
        {
            code: 'STAGED_BATCH_EMPTY',
            pattern: /staged batch has no eligible rows/i,
            title: 'No validated DTR rows are available for the snapshot',
            resolution: 'Return to the staged DTR batch, resolve its row-validation failures, and analyze the batch again.',
            action: 'dtr-upload',
            actionLabel: 'Review staged DTR'
        },
        {
            code: 'BATCH_PAY_DATE_REQUIRED',
            pattern: /one unambiguous pay date/i,
            title: 'The staged DTR does not have one clear pay date',
            resolution: 'Correct the upload period so all eligible rows resolve to one pay date, then stage and analyze the DTR again.',
            action: 'dtr-upload',
            actionLabel: 'Review DTR upload'
        },
        {
            code: 'POPULATION_EXCEPTIONS',
            pattern: /population exception|payslip-only employee|dtr and expected payslip/i,
            title: 'The DTR and payslip populations do not match',
            resolution: 'Review each population exception and record the evidence-backed disposition before continuing payroll.',
            action: 'population-review',
            actionLabel: 'Resolve population issues'
        },
        {
            code: 'ADAPTER_REQUIRED',
            pattern: /no approved client adapter|adapter.*not approved|no approved format/i,
            title: 'This DTR format is not approved for the selected client',
            resolution: 'Open the governed format registry, create or approve the correct client adapter version, then stage the file again.',
            action: 'adapter-registry',
            actionLabel: 'Review DTR formats'
        },
        {
            code: 'EMPLOYEE_MASTER_DATA_REQUIRED',
            pattern: /employee master|employee record|missing employee/i,
            title: 'Employee master data needs correction',
            resolution: 'Open the employee record, correct the verified information, then return to the same payroll batch and refresh validation.',
            action: 'employee-management',
            actionLabel: 'Open Employee Management'
        },
        {
            code: 'ACCESS_REQUIRED',
            pattern: /unauthorized|permission|access is required|forbidden|log in/i,
            title: 'Your account cannot complete this action',
            resolution: 'Sign in again if your session expired. If access is still blocked, ask an administrator to review your account role and client scope.',
            action: 'user-access',
            actionLabel: 'Review user access'
        },
        {
            code: 'REQUEST_REFRESH_REQUIRED',
            pattern: /invalid request|csrf|refresh the page/i,
            title: 'This page session is no longer current',
            resolution: 'Refresh the page to obtain a new secure request token. Your uploaded batch and approved mappings remain stored.',
            action: 'refresh',
            actionLabel: 'Refresh this page'
        },
        {
            code: 'CONNECTION_FAILED',
            pattern: /unable to load|unable to reach|network|connection|timed out/i,
            title: 'The application could not load the required data',
            resolution: 'Check your connection and retry. If the error continues, keep the error reference and contact the application administrator.',
            action: 'retry',
            actionLabel: 'Retry'
        }
    ];

    function normalize(input) {
        if (input && typeof input === 'object') {
            return {
                code: String(input.error_code || input.code || '').trim(),
                message: String(input.error || input.message || '').trim()
            };
        }
        return {code: '', message: String(input || '').trim()};
    }

    function matchDefinition(error) {
        var byCode = definitions.find(function (definition) {
            return error.code !== '' && definition.code === error.code;
        });
        if (byCode) {
            return byCode;
        }
        return definitions.find(function (definition) {
            return definition.pattern.test(error.message);
        }) || {
            code: error.code || 'ACTION_FAILED',
            title: 'We could not complete this action',
            resolution: 'Review the message below, correct the owning record or configuration, and retry. Use the page guide if you need help locating the source.',
            action: 'page-guide',
            actionLabel: 'Open page guide'
        };
    }

    function appRoot() {
        return detectedAppRoot;
    }

    function actionHref(action, context) {
        var root = appRoot();
        var query = new URLSearchParams();
        if (context.clientId) {
            query.set('client_id', String(context.clientId));
        }
        if (context.payDate) {
            query.set('pay_date', String(context.payDate));
        }
        if (context.batchId) {
            query.set('batch_id', String(context.batchId));
        }
        var suffix = query.toString() ? '?' + query.toString() : '';
        var routes = {
            'payroll-rules': root + '/dtr-format-engine/' + suffix + '#payroll-rules',
            'dtr-upload': root + '/dtr-format-engine/' + suffix + '#real-dtr-upload',
            'identity-review': root + '/dtr-format-engine/' + suffix + '#employee-identity-review',
            'population-review': root + '/dtr-format-engine/' + suffix + '#payroll-population-review',
            'adapter-registry': root + '/dtr-format-engine/' + suffix + '#adapter-registry',
            'employee-management': root + '/employee-management/' + suffix,
            'user-access': root + '/users-access/' + suffix
        };
        return routes[action] || '#';
    }

    function createElement(tag, className, text) {
        var element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text) {
            element.textContent = text;
        }
        return element;
    }

    function dispatchAction(event, definition, context) {
        var actionEvent = new CustomEvent('hris:resolve-error', {
            bubbles: true,
            cancelable: true,
            detail: {
                code: definition.code,
                action: definition.action,
                context: context
            }
        });
        var handled = !document.dispatchEvent(actionEvent);
        if (handled) {
            event.preventDefault();
            return;
        }
        if (definition.action === 'refresh') {
            event.preventDefault();
            window.location.reload();
            return;
        }
        if (definition.action === 'retry') {
            event.preventDefault();
            window.location.reload();
            return;
        }
        if (definition.action === 'page-guide') {
            event.preventDefault();
            var guide = document.querySelector('.hris-help-launcher');
            if (guide) {
                guide.click();
            }
        }
    }

    function render(target, input, options) {
        var host = typeof target === 'string' ? document.querySelector(target) : target;
        if (!host) {
            return null;
        }
        var error = normalize(input);
        if (!error.message) {
            return null;
        }
        options = options || {};
        var context = options.context || {};
        var definition = matchDefinition(error);
        var code = error.code || definition.code;

        host.textContent = '';
        host.classList.add('hris-actionable-host');
        host.setAttribute('data-hris-actionable', '1');
        host.setAttribute('data-hris-error-code', code);
        host.style.display = '';

        var panel = createElement('div', 'hris-action-error');
        panel.setAttribute('role', 'alert');
        panel.setAttribute('aria-live', 'assertive');

        var icon = createElement('span', 'hris-action-error__icon');
        icon.setAttribute('aria-hidden', 'true');
        icon.appendChild(createElement('i', 'bx bx-error-circle'));

        var content = createElement('div', 'hris-action-error__content');
        content.appendChild(createElement('strong', 'hris-action-error__title', options.title || definition.title));
        content.appendChild(createElement('span', 'hris-action-error__message', error.message));
        content.appendChild(createElement('span', 'hris-action-error__resolution', options.resolution || definition.resolution));

        var actions = createElement('div', 'hris-action-error__actions');
        var action = createElement('a', 'hris-action-error__action', options.actionLabel || definition.actionLabel);
        action.href = options.href || actionHref(definition.action, context);
        action.appendChild(createElement('i', 'bx bx-right-arrow-alt'));
        action.addEventListener('click', function (event) {
            dispatchAction(event, definition, context);
            if (typeof options.onAction === 'function') {
                event.preventDefault();
                options.onAction(definition, context);
            }
        });
        actions.appendChild(action);

        if (options.secondaryLabel && typeof options.onSecondary === 'function') {
            var secondary = createElement('button', 'hris-action-error__secondary', options.secondaryLabel);
            secondary.type = 'button';
            secondary.addEventListener('click', options.onSecondary);
            actions.appendChild(secondary);
        }

        if (code && code !== 'ACTION_FAILED') {
            actions.appendChild(createElement('span', 'hris-action-error__reference', 'Reference: ' + code));
        }

        content.appendChild(actions);
        panel.appendChild(icon);
        panel.appendChild(content);
        host.appendChild(panel);
        return {code: code, definition: definition};
    }

    function enhanceElement(element) {
        if (!element || element.querySelector('.hris-action-error__content')) {
            return;
        }
        if (element.hidden || element.style.display === 'none') {
            return;
        }
        var message = String(element.textContent || '').replace(/\s+/g, ' ').trim();
        if (!message) {
            return;
        }
        render(element, {
            code: element.getAttribute('data-error-code') || '',
            error: message
        });
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('.alert-danger, .payroll-page-state--error, [data-hris-error]')
            .forEach(enhanceElement);
    }

    function observe() {
        enhanceAll(document);
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                var target = mutation.target.nodeType === 1 ? mutation.target : mutation.target.parentElement;
                if (target && target.matches && target.matches('.alert-danger, .payroll-page-state--error, [data-hris-error]')) {
                    enhanceElement(target);
                }
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    if (node.matches('.alert-danger, .payroll-page-state--error, [data-hris-error]')) {
                        enhanceElement(node);
                    }
                    enhanceAll(node);
                });
            });
        });
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'hidden']
        });
    }

    window.HrisActionableErrors = {
        definitions: definitions,
        render: render,
        enhanceAll: enhanceAll,
        observe: observe
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observe);
    } else {
        observe();
    }
})(window, document);
