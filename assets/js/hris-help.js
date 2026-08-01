(function (window, document) {
    'use strict';

    var registry = window.HrisHelpGuides || {};
    var guides = registry.guides || {};
    var lastFocused = null;
    var lightboxReturnFocus = null;
    var currentGuide = null;
    var currentSlug = '';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function pageSlug() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        return parts.length ? parts[parts.length - 1].toLowerCase() : 'dashboard';
    }

    function appBase() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        if (parts.length <= 1) {
            return '';
        }
        return '/' + parts.slice(0, -1).join('/');
    }

    function renderList(items) {
        return '<ul class="hris-help-list">' + (items || []).map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul>';
    }

    function renderActions(actions) {
        return (actions || []).map(function (item, index) {
            return '<details class="hris-help-action"' + (index === 0 ? ' open' : '') + '>'
                + '<summary><span>' + escapeHtml(item.title) + '</span><i class="bx bx-chevron-down" aria-hidden="true"></i></summary>'
                + '<ol>' + (item.steps || []).map(function (stepText) {
                    return '<li>' + escapeHtml(stepText) + '</li>';
                }).join('') + '</ol>'
                + '</details>';
        }).join('');
    }

    function wrapText(context, text, maxWidth) {
        var words = String(text || '').split(/\s+/);
        var lines = [];
        var line = '';
        words.forEach(function (word) {
            var test = line ? line + ' ' + word : word;
            if (context.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = test;
            }
        });
        if (line) {
            lines.push(line);
        }
        return lines;
    }

    function roundRect(context, x, y, width, height, radius) {
        var r = Math.min(radius, width / 2, height / 2);
        context.beginPath();
        context.moveTo(x + r, y);
        context.arcTo(x + width, y, x + width, y + height, r);
        context.arcTo(x + width, y + height, x, y + height, r);
        context.arcTo(x, y + height, x, y, r);
        context.arcTo(x, y, x + width, y, r);
        context.closePath();
    }

    function drawArrow(context, fromX, fromY, toX, toY) {
        var headLength = 11;
        var angle = Math.atan2(toY - fromY, toX - fromX);
        context.strokeStyle = '#8592a3';
        context.fillStyle = '#8592a3';
        context.lineWidth = 3;
        context.beginPath();
        context.moveTo(fromX, fromY);
        context.lineTo(toX, toY);
        context.stroke();
        context.beginPath();
        context.moveTo(toX, toY);
        context.lineTo(
            toX - headLength * Math.cos(angle - Math.PI / 6),
            toY - headLength * Math.sin(angle - Math.PI / 6)
        );
        context.lineTo(
            toX - headLength * Math.cos(angle + Math.PI / 6),
            toY - headLength * Math.sin(angle + Math.PI / 6)
        );
        context.closePath();
        context.fill();
    }

    function createProcessImage(flow, title) {
        var steps = flow || [];
        var canvas = document.createElement('canvas');
        var width = 1100;
        var columns = steps.length <= 4 ? 2 : 3;
        var rows = Math.ceil(steps.length / columns);
        var nodeWidth = columns === 2 ? 430 : 310;
        var nodeHeight = 145;
        var gapX = 55;
        var gapY = 65;
        var startX = (width - (columns * nodeWidth + (columns - 1) * gapX)) / 2;
        var startY = 95;
        var height = startY + rows * nodeHeight + Math.max(0, rows - 1) * gapY + 55;
        var scale = 2;
        canvas.width = width * scale;
        canvas.height = height * scale;
        var context = canvas.getContext('2d');
        context.scale(scale, scale);

        var gradient = context.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#f7f7ff');
        gradient.addColorStop(1, '#f4fbff');
        context.fillStyle = gradient;
        context.fillRect(0, 0, width, height);

        context.fillStyle = '#566a7f';
        context.font = '700 28px Arial, sans-serif';
        context.textAlign = 'center';
        context.fillText(title + ' process flow', width / 2, 48);

        var positions = steps.map(function (_item, index) {
            var row = Math.floor(index / columns);
            var colInRow = index % columns;
            var reverse = row % 2 === 1;
            var col = reverse ? columns - 1 - colInRow : colInRow;
            return {
                x: startX + col * (nodeWidth + gapX),
                y: startY + row * (nodeHeight + gapY)
            };
        });

        positions.forEach(function (position, index) {
            if (index === 0) {
                return;
            }
            var previous = positions[index - 1];
            var sameRow = previous.y === position.y;
            if (sameRow) {
                var leftToRight = position.x > previous.x;
                drawArrow(
                    context,
                    leftToRight ? previous.x + nodeWidth : previous.x,
                    previous.y + nodeHeight / 2,
                    leftToRight ? position.x - 10 : position.x + nodeWidth + 10,
                    position.y + nodeHeight / 2
                );
            } else {
                drawArrow(
                    context,
                    previous.x + nodeWidth / 2,
                    previous.y + nodeHeight,
                    position.x + nodeWidth / 2,
                    position.y - 10
                );
            }
        });

        steps.forEach(function (item, index) {
            var position = positions[index];
            context.shadowColor = 'rgba(67, 89, 113, 0.12)';
            context.shadowBlur = 14;
            context.shadowOffsetY = 5;
            roundRect(context, position.x, position.y, nodeWidth, nodeHeight, 18);
            context.fillStyle = '#ffffff';
            context.fill();
            context.shadowColor = 'transparent';
            context.strokeStyle = index === steps.length - 1 ? '#28c76f' : '#696cff';
            context.lineWidth = 3;
            context.stroke();

            context.beginPath();
            context.arc(position.x + 32, position.y + 34, 18, 0, Math.PI * 2);
            context.fillStyle = index === steps.length - 1 ? '#28c76f' : '#696cff';
            context.fill();
            context.fillStyle = '#ffffff';
            context.font = '700 17px Arial, sans-serif';
            context.textAlign = 'center';
            context.fillText(String(index + 1), position.x + 32, position.y + 40);

            context.textAlign = 'left';
            context.fillStyle = '#384551';
            context.font = '700 20px Arial, sans-serif';
            var titleLines = wrapText(context, item.title, nodeWidth - 82).slice(0, 2);
            titleLines.forEach(function (line, lineIndex) {
                context.fillText(line, position.x + 60, position.y + 31 + lineIndex * 23);
            });

            context.fillStyle = '#697a8d';
            context.font = '400 16px Arial, sans-serif';
            var detailY = position.y + (titleLines.length > 1 ? 83 : 67);
            var detailLines = wrapText(context, item.detail, nodeWidth - 38).slice(0, 3);
            detailLines.forEach(function (line, lineIndex) {
                context.fillText(line, position.x + 20, detailY + lineIndex * 21);
            });
        });

        return canvas.toDataURL('image/png');
    }

    function trapFocus(container, event) {
        var focusable = Array.prototype.slice.call(container.querySelectorAll(
            'a[href]:not([hidden]), button:not([disabled]), input:not([disabled]), summary, [tabindex]:not([tabindex="-1"])'
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

    function openProcessImage(imageSource, imageAlt, imageTitle, trigger) {
        var lightbox = document.getElementById('hrisHelpLightbox');
        var image = document.getElementById('hrisHelpLightboxImage');
        var title = document.getElementById('hrisHelpLightboxTitle');
        if (!lightbox || !image) {
            return;
        }
        lightboxReturnFocus = trigger || document.activeElement;
        image.src = imageSource;
        image.alt = imageAlt || 'Process flow diagram';
        title.textContent = imageTitle || 'Process flow';
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hris-help-lightbox-open');
        window.setTimeout(function () {
            if (lightbox.classList.contains('is-open')) {
                document.getElementById('hrisHelpLightboxClose').focus();
            }
        }, 80);
    }

    function closeProcessImage() {
        var lightbox = document.getElementById('hrisHelpLightbox');
        if (!lightbox || !lightbox.classList.contains('is-open')) {
            return;
        }
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hris-help-lightbox-open');
        if (lightboxReturnFocus && typeof lightboxReturnFocus.focus === 'function') {
            lightboxReturnFocus.focus();
        }
    }

    function renderPayrollChecklist(items) {
        return '<ol class="hris-help-payroll-checklist">' + (items || []).map(function (item, index) {
            return '<li><span aria-hidden="true">' + (index + 1) + '</span><div><strong>'
                + escapeHtml(item.title) + '</strong><p>' + escapeHtml(item.detail) + '</p></div></li>';
        }).join('') + '</ol>';
    }

    function contextualPayrollFaqs(term) {
        var support = registry.payrollHelp || {};
        var faqs = support.faqs || [];
        var normalized = String(term || '').trim().toLowerCase();
        if (normalized) {
            return faqs.filter(function (faq) {
                return [
                    faq.question,
                    faq.answer,
                    faq.keywords,
                    (faq.pages || []).join(' ')
                ].join(' ').toLowerCase().indexOf(normalized) !== -1;
            }).slice(0, 8);
        }

        var relevant = faqs.filter(function (faq) {
            return (faq.pages || []).indexOf(currentSlug) !== -1;
        });
        var remaining = faqs.filter(function (faq) {
            return relevant.indexOf(faq) === -1;
        });
        return relevant.concat(remaining).slice(0, 5);
    }

    function renderContextualPayrollFaqs(term) {
        var list = document.getElementById('hrisHelpContextFaqList');
        var result = document.getElementById('hrisHelpContextFaqResult');
        if (!list || !result) {
            return;
        }
        var matches = contextualPayrollFaqs(term);
        result.textContent = matches.length + (matches.length === 1 ? ' question shown.' : ' questions shown.');
        if (!matches.length) {
            list.innerHTML = '<div class="hris-help-context-empty"><i class="bx bx-search-alt" aria-hidden="true"></i>'
                + '<strong>No matching common question</strong><span>Try DTR, employee, deduction, payslip, release, or error.</span></div>';
            return;
        }
        list.innerHTML = matches.map(function (faq) {
            return '<details class="hris-help-context-faq"><summary><span>' + escapeHtml(faq.question)
                + '</span><i class="bx bx-chevron-down" aria-hidden="true"></i></summary>'
                + '<div><p>' + escapeHtml(faq.answer) + '</p>'
                + '<a href="' + escapeHtml(faq.url) + '">Open related page <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>'
                + '</div></details>';
        }).join('');
    }

    function renderPayrollSupport() {
        var support = registry.payrollHelp || {};
        if (!currentGuide.faq || currentSlug === 'payroll-help' || !support.checklist || !support.faqs) {
            return '';
        }
        return ''
            + '<section class="hris-help-payroll-support" aria-labelledby="hrisHelpPayrollChecklistTitle">'
            + '<div class="hris-help-support-heading"><div><h3 id="hrisHelpPayrollChecklistTitle"><i class="bx bx-list-check" aria-hidden="true"></i> Payroll cycle checklist</h3>'
            + '<p>Use this order as guidance; it does not record approval or replace the release gate.</p></div></div>'
            + renderPayrollChecklist(support.checklist)
            + '</section>'
            + '<section class="hris-help-payroll-support" aria-labelledby="hrisHelpContextFaqTitle">'
            + '<div class="hris-help-support-heading"><div><h3 id="hrisHelpContextFaqTitle"><i class="bx bx-message-rounded-dots" aria-hidden="true"></i> Common payroll questions</h3>'
            + '<p>Start with questions relevant to this page, or search across the common payroll topics.</p></div></div>'
            + '<label class="hris-help-search-label" for="hrisHelpContextFaqSearch">Search payroll help</label>'
            + '<div class="hris-help-context-search"><i class="bx bx-search" aria-hidden="true"></i>'
            + '<input type="search" id="hrisHelpContextFaqSearch" placeholder="Search DTR, employee, deduction, payslip..." autocomplete="off"></div>'
            + '<p class="hris-help-context-result" id="hrisHelpContextFaqResult" aria-live="polite"></p>'
            + '<div id="hrisHelpContextFaqList"></div>'
            + '<a class="hris-help-center-link" href="' + escapeHtml(appBase() + '/payroll-help/') + '">'
            + '<span><i class="bx bx-help-circle" aria-hidden="true"></i><strong>Open Full Payroll Help Center</strong>'
            + '<small>Browse every question, category, and the end-to-end process.</small></span>'
            + '<i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>'
            + '</section>';
    }

    function renderGuide(guide) {
        var drawerBody = document.getElementById('hrisHelpDrawerBody');
        var title = document.getElementById('hrisHelpTitle');
        var audience = document.getElementById('hrisHelpAudience');
        var diagram = createProcessImage(guide.flow || [], guide.title);
        var flowAlt = 'Process flow for ' + guide.title + ': ' + (guide.flow || []).map(function (item, index) {
            return (index + 1) + '. ' + item.title + '. ' + item.detail;
        }).join(' ');

        title.textContent = guide.title;
        audience.textContent = guide.audience || 'Authorized HRIS users';

        drawerBody.innerHTML = ''
            + '<p class="hris-help-summary">' + escapeHtml(guide.summary) + '</p>'
            + renderPayrollSupport()
            + '<section><h3><i class="bx bx-layout" aria-hidden="true"></i> What is on this page</h3>'
            + renderList(guide.whatsHere) + '</section>'
            + '<section><h3><i class="bx bx-check-circle" aria-hidden="true"></i> What you can do</h3>'
            + renderList(guide.canDo) + '</section>'
            + '<section><div class="hris-help-section-heading"><h3><i class="bx bx-git-branch" aria-hidden="true"></i> Process flow</h3>'
            + '<span class="hris-help-section-hint"><i class="bx bx-fullscreen" aria-hidden="true"></i> Click diagram to maximize</span></div>'
            + '<button type="button" class="hris-help-process-trigger" id="hrisHelpProcessTrigger" aria-label="Maximize the ' + escapeHtml(guide.title) + ' process flow">'
            + '<img class="hris-help-process-image" id="hrisHelpProcessImage" src="' + diagram + '" alt="' + escapeHtml(flowAlt) + '">'
            + '<span class="hris-help-process-overlay"><i class="bx bx-fullscreen" aria-hidden="true"></i> Maximize</span></button></section>'
            + '<section><h3><i class="bx bx-play-circle" aria-hidden="true"></i> How to complete the actions</h3>'
            + renderActions(guide.actions) + '</section>'
            + '<section class="hris-help-tips"><h3><i class="bx bx-bulb" aria-hidden="true"></i> Important reminders</h3>'
            + renderList(guide.tips) + '</section>';

        document.getElementById('hrisHelpProcessTrigger').addEventListener('click', function (event) {
            openProcessImage(diagram, flowAlt, guide.title + ' process flow', event.currentTarget);
        });
        var contextualFaqSearch = document.getElementById('hrisHelpContextFaqSearch');
        if (contextualFaqSearch) {
            contextualFaqSearch.addEventListener('input', function () {
                renderContextualPayrollFaqs(contextualFaqSearch.value);
            });
            renderContextualPayrollFaqs('');
        }
    }

    function openGuide() {
        var drawer = document.getElementById('hrisHelpDrawer');
        var overlay = document.getElementById('hrisHelpOverlay');
        lastFocused = document.activeElement;
        renderGuide(currentGuide);
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        overlay.setAttribute('aria-hidden', 'false');
        var app = document.querySelector('.layout-wrapper');
        if (app) {
            app.setAttribute('inert', '');
            app.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.add('hris-help-open');
        document.getElementById('hrisHelpClose').focus();
    }

    function closeGuide() {
        var drawer = document.getElementById('hrisHelpDrawer');
        var overlay = document.getElementById('hrisHelpOverlay');
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-hidden', 'true');
        var app = document.querySelector('.layout-wrapper');
        if (app) {
            app.removeAttribute('inert');
            app.removeAttribute('aria-hidden');
        }
        document.body.classList.remove('hris-help-open');
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function injectHelpUi() {
        currentSlug = pageSlug();
        currentGuide = guides[currentSlug] || registry.fallback;
        var wrapper = document.createElement('div');
        wrapper.innerHTML = ''
            + '<div class="hris-help-overlay" id="hrisHelpOverlay" aria-hidden="true"></div>'
            + '<aside class="hris-help-drawer" id="hrisHelpDrawer" role="dialog" aria-modal="true" aria-labelledby="hrisHelpTitle" aria-hidden="true">'
            + '<header class="hris-help-header">'
            + '<div><span class="hris-help-kicker">TAASCOR USER GUIDE</span><h2 id="hrisHelpTitle">Page Guide</h2>'
            + '<p><i class="bx bx-user-check" aria-hidden="true"></i> <span id="hrisHelpAudience"></span></p></div>'
            + '<button type="button" class="hris-help-close" id="hrisHelpClose" aria-label="Close page guide"><i class="bx bx-x" aria-hidden="true"></i></button>'
            + '</header>'
            + '<div class="hris-help-tools">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" id="hrisHelpPrint"><i class="bx bx-printer me-1" aria-hidden="true"></i>Print guide</button>'
            + '</div>'
            + '<div class="hris-help-body" id="hrisHelpDrawerBody"></div>'
            + '</aside>'
            + '<div class="hris-help-lightbox" id="hrisHelpLightbox" role="dialog" aria-modal="true" aria-labelledby="hrisHelpLightboxTitle" aria-hidden="true">'
            + '<div class="hris-help-lightbox-shell">'
            + '<header class="hris-help-lightbox-header"><div><span class="hris-help-kicker">PROCESS DIAGRAM</span><h2 id="hrisHelpLightboxTitle">Process flow</h2></div>'
            + '<button type="button" class="hris-help-close" id="hrisHelpLightboxClose" aria-label="Close process diagram"><i class="bx bx-x" aria-hidden="true"></i></button></header>'
            + '<div class="hris-help-lightbox-body"><img id="hrisHelpLightboxImage" alt=""></div>'
            + '</div></div>';
        while (wrapper.firstChild) {
            document.body.appendChild(wrapper.firstChild);
        }

        var navbarActions = document.querySelector('#layout-navbar .navbar-nav.ms-auto');
        var launcherItem = document.createElement('li');
        launcherItem.className = 'nav-item hris-help-navbar-item';
        launcherItem.innerHTML = '<button type="button" class="hris-help-launcher" id="hrisHelpLauncher" aria-label="Open guide for '
            + escapeHtml(currentGuide.title) + '" aria-controls="hrisHelpDrawer" title="Open guide">'
            + '<span aria-hidden="true">?</span></button>';
        if (navbarActions) {
            navbarActions.insertBefore(launcherItem, navbarActions.firstChild);
        } else {
            document.body.appendChild(launcherItem);
        }

        document.getElementById('hrisHelpLauncher').addEventListener('click', openGuide);
        document.getElementById('hrisHelpClose').addEventListener('click', closeGuide);
        document.getElementById('hrisHelpOverlay').addEventListener('click', closeGuide);
        document.getElementById('hrisHelpLightboxClose').addEventListener('click', closeProcessImage);
        document.getElementById('hrisHelpLightbox').addEventListener('click', function (event) {
            if (event.target === event.currentTarget) {
                closeProcessImage();
            }
        });
        document.getElementById('hrisHelpPrint').addEventListener('click', function () {
            window.print();
        });
        document.addEventListener('keydown', function (event) {
            var drawer = document.getElementById('hrisHelpDrawer');
            var lightbox = document.getElementById('hrisHelpLightbox');
            if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
                closeProcessImage();
                return;
            }
            if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeGuide();
                return;
            }
            if (event.key === 'Tab' && lightbox.classList.contains('is-open')) {
                trapFocus(lightbox, event);
                return;
            }
            if (event.key === 'Tab' && drawer.classList.contains('is-open')) {
                trapFocus(drawer, event);
            }
        });
    }

    window.HrisHelp = {
        createProcessImage: createProcessImage,
        openProcessImage: openProcessImage,
        open: openGuide
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectHelpUi);
    } else {
        injectHelpUi();
    }
}(window, document));
