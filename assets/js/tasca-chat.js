(function (window, document) {
    'use strict';

    var allowedRoles = ['1', '2', '3', '4', '5'];
  var brandMarkPath = '../assets/img/svg/tasca-bot-logo.svg?v=20260801c';
    var registry = window.HrisHelpGuides || {};
    var guides = registry.guides || {};
    var maxMessages = 30;
    var maxInputLength = 500;
    var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 575.98px)') : null;
    var state = {
        busy: false,
        lastFocused: null,
        lastQuestion: '',
        messages: [],
        responseTimer: null,
        mobileInert: false,
        previousAppAriaHidden: null
    };
    var ui = {};
    var currentSlug = pageSlug();
    var currentGuide = guides[currentSlug] || registry.fallback || {
        title: 'Current page',
        summary: 'Use this page to complete the task shown in the HRIS.',
        whatsHere: [],
        canDo: [],
        actions: [],
        tips: []
    };

    function pageSlug() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        if (parts.length && parts[parts.length - 1].toLowerCase() === 'index.php') {
            parts.pop();
        }
        return parts.length ? parts[parts.length - 1].toLowerCase() : 'dashboard';
    }

    function accessLevel() {
        var input = document.getElementById('access_level');
        return input ? String(input.value || '') : '';
    }

    function isAuthenticatedRole() {
        return allowedRoles.indexOf(accessLevel()) !== -1;
    }

    function nowLabel(dateValue) {
        var date = dateValue ? new Date(dateValue) : new Date();
        if (Number.isNaN(date.getTime())) {
            date = new Date();
        }
        return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
    }

    function messageId() {
        return 'tasca-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    }

    function cleanList(items) {
        if (!Array.isArray(items)) {
            return [];
        }
        return items.filter(function (item) {
            return typeof item === 'string' && item.trim() !== '';
        }).slice(0, 6).map(function (item) {
            return item.slice(0, 800);
        });
    }

    function safeMessage(raw) {
        if (!raw || (raw.role !== 'assistant' && raw.role !== 'user')) {
            return null;
        }
        if (typeof raw.text !== 'string' || raw.text.trim() === '') {
            return null;
        }
        var message = {
            id: typeof raw.id === 'string' ? raw.id : messageId(),
            role: raw.role,
            text: raw.text.slice(0, 4000),
            items: cleanList(raw.items),
            ordered: raw.ordered === true,
            source: typeof raw.source === 'string' ? raw.source.slice(0, 180) : '',
            createdAt: typeof raw.createdAt === 'string' ? raw.createdAt : new Date().toISOString(),
            error: raw.error === true
        };
        return message;
    }

    function assistantMessage(text, options) {
        var config = options || {};
        return safeMessage({
            role: 'assistant',
            text: text,
            items: config.items || [],
            ordered: config.ordered === true,
            source: config.source || '',
            error: config.error === true
        });
    }

    function welcomeMessage() {
        if (accessLevel() === '4') {
            return assistantMessage(
                'Hi, I am TASCA. I can provide a read-only overview of ' + currentGuide.title + '. Use only controls visible to your Coordinator role. I cannot view or change HRIS records.',
                {source: currentGuide.title}
            );
        }
        return assistantMessage(
            'Hi, I am TASCA. I can provide a read-only overview of ' + currentGuide.title + ' or open the existing Page Guide. Use only controls visible to your role. I cannot view or change HRIS records.',
            {source: currentGuide.title}
        );
    }

    function resetMessages() {
        if (state.responseTimer !== null) {
            window.clearTimeout(state.responseTimer);
            state.responseTimer = null;
        }
        setBusy(false);
        state.messages = [welcomeMessage()];
        state.lastQuestion = '';
        renderMessages();
        updateComposerState();
    }

    function createIcon(name) {
        var icon = document.createElement('i');
        icon.className = 'bx ' + name;
        icon.setAttribute('aria-hidden', 'true');
        return icon;
    }

    function createMessageElement(message) {
        var article = document.createElement('article');
        article.className = 'tasca-chat-message is-' + message.role + (message.error ? ' is-error' : '');
        article.dataset.messageId = message.id;

        if (message.role === 'assistant') {
            var avatar = document.createElement('img');
            avatar.className = 'tasca-chat-message-avatar';
            avatar.src = brandMarkPath;
            avatar.alt = '';
            avatar.setAttribute('aria-hidden', 'true');
            article.appendChild(avatar);
        }

        var stack = document.createElement('div');
        stack.className = 'tasca-chat-message-stack';

        var name = document.createElement('p');
        name.className = 'tasca-chat-message-name';
        name.textContent = message.role === 'assistant' ? 'TASCA' : 'You';
        stack.appendChild(name);

        var bubble = document.createElement('div');
        bubble.className = 'tasca-chat-bubble';

        var text = document.createElement('p');
        text.textContent = message.text;
        bubble.appendChild(text);

        if (message.items.length) {
            var list = document.createElement(message.ordered ? 'ol' : 'ul');
            message.items.forEach(function (item) {
                var listItem = document.createElement('li');
                listItem.textContent = item;
                list.appendChild(listItem);
            });
            bubble.appendChild(list);
        }

        if (message.source && accessLevel() !== '4') {
            var sourceButton = document.createElement('button');
            sourceButton.type = 'button';
            sourceButton.className = 'tasca-chat-source';
            sourceButton.appendChild(createIcon('bx-book-open'));
            var sourceLabel = document.createElement('span');
            sourceLabel.textContent = 'Based on: ' + message.source + ' Page Guide';
            sourceButton.appendChild(sourceLabel);
            sourceButton.addEventListener('click', openPageGuide);
            bubble.appendChild(sourceButton);
        }

        if (message.error) {
            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'tasca-chat-retry';
            retry.appendChild(createIcon('bx-refresh'));
            var retryLabel = document.createElement('span');
            retryLabel.textContent = 'Try again';
            retry.appendChild(retryLabel);
            retry.addEventListener('click', function () {
                if (state.lastQuestion) {
                    submitQuestion(state.lastQuestion, true);
                }
            });
            bubble.appendChild(retry);
        }

        stack.appendChild(bubble);

        var time = document.createElement('p');
        time.className = 'tasca-chat-time';
        time.textContent = nowLabel(message.createdAt);
        stack.appendChild(time);

        article.appendChild(stack);
        return article;
    }

    function renderMessages() {
        if (!ui.messages) {
            return;
        }
        var fragment = document.createDocumentFragment();
        state.messages.forEach(function (message) {
            fragment.appendChild(createMessageElement(message));
        });
        ui.messages.replaceChildren(fragment);
        window.requestAnimationFrame(function () {
            ui.messages.scrollTop = ui.messages.scrollHeight;
        });
    }

    function addMessage(message) {
        var cleaned = safeMessage(message);
        if (!cleaned) {
            return;
        }
        state.messages.push(cleaned);
        state.messages = state.messages.slice(-maxMessages);
        renderMessages();
    }

    function normalise(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9\s&/-]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function hasAny(value, phrases) {
        return phrases.some(function (phrase) {
            return value.indexOf(phrase) !== -1;
        });
    }

    function asksForPrivateRecord(question) {
        var requestWords = ['show', 'find', 'give', 'tell', 'look up', 'lookup', 'display', 'reveal', 'what is'];
        var sensitiveWords = ['bank account', 'contact number', 'employee id', 'pag-ibig number', 'pagibig number', 'password', 'pay amount', 'payslip amount', 'philhealth number', 'salary', 'sss number', 'tin number'];
        return hasAny(question, requestWords) && hasAny(question, sensitiveWords);
    }

    function safePageContext() {
        if (accessLevel() === '4') {
            return {
                summary: 'This is the ' + currentGuide.title + ' page. TASCA can provide a read-only overview, but only visible controls and server permissions determine what your Coordinator role may do.',
                whatsHere: [
                    'Information already visible to your role on the current page.',
                    'Search, filters, and review controls that the HRIS currently displays to you.',
                    'No permission to create, upload, update, terminate, or delete a record is granted by TASCA.'
                ]
            };
        }
        return {
            summary: currentGuide.summary,
            whatsHere: currentGuide.whatsHere || []
        };
    }

    function safeReminders() {
        if (accessLevel() === '4') {
            return [
                'Use only controls currently visible to your Coordinator role.',
                'Do not attempt to create, upload, update, terminate, or delete records through direct requests.',
                'Ask HR or Admin when a record needs a controlled lifecycle change.'
            ];
        }
        return currentGuide.tips || [
            'Confirm the page, selected records, and source evidence before taking an authorized action.',
            'TASCA guidance does not grant additional HRIS permissions.'
        ];
    }

    function buildAnswer(rawQuestion) {
        var question = normalise(rawQuestion);
        var context = safePageContext();

        if (asksForPrivateRecord(question)) {
            return assistantMessage(
                'I cannot view or reveal employee, payroll, banking, government ID, contact, credential, or client-restricted records. I can explain the approved process or open this page\'s guide.',
                {source: currentGuide.title}
            );
        }

        if (/^(hi|hello|hey|good morning|good afternoon|good evening)\b/.test(question)) {
            return assistantMessage(
                'Hi. I am ready to provide read-only help for ' + currentGuide.title + '. Ask what this page is for or request the important reminders. Use only controls visible to your role.',
                {source: currentGuide.title}
            );
        }

        if (hasAny(question, ['explain this page', 'what is this page', 'page for', 'what is here', 'current page'])) {
            return assistantMessage(context.summary, {
                items: context.whatsHere,
                source: currentGuide.title
            });
        }

        if (hasAny(question, ['what can i do', 'can i do here', 'available actions', 'help me here'])) {
            return assistantMessage('TASCA does not treat guide content as permission. This page contains the following areas; use only controls currently visible to your role:', {
                items: context.whatsHere,
                source: currentGuide.title
            });
        }

        if (hasAny(question, ['reminder', 'warning', 'safe', 'avoid', 'important'])) {
            return assistantMessage('Keep these safeguards in mind:', {
                items: safeReminders(),
                source: currentGuide.title
            });
        }

        if (hasAny(question, ['how', 'step', 'guide', 'process', 'next'])) {
            return assistantMessage(
                'This guide-mode build does not confirm permission for an action or provide cross-module instructions. Use only controls visible to your role. Open the existing Page Guide for approved process documentation.',
                {items: safeReminders(), source: currentGuide.title}
            );
        }

        return assistantMessage(
            'I can provide a read-only overview of ' + currentGuide.title + '. Try asking what this page is for, what is visible here, or which safeguards apply.',
            {
                items: context.whatsHere.slice(0, 3),
                source: currentGuide.title
            }
        );
    }

    function setBusy(isBusy) {
        state.busy = isBusy;
        if (ui.typing) {
            ui.typing.classList.toggle('is-visible', isBusy);
            ui.typing.setAttribute('aria-hidden', isBusy ? 'false' : 'true');
        }
        updateComposerState();
        if (isBusy && ui.messages) {
            window.requestAnimationFrame(function () {
                ui.messages.scrollTop = ui.messages.scrollHeight;
            });
        }
    }

    function updateComposerState() {
        if (!ui.input || !ui.send) {
            return;
        }
        ui.send.disabled = state.busy || ui.input.value.trim() === '';
        ui.input.setAttribute('aria-busy', state.busy ? 'true' : 'false');
    }

    function resizeInput() {
        if (!ui.input) {
            return;
        }
        ui.input.style.height = 'auto';
        ui.input.style.height = Math.min(ui.input.scrollHeight, 96) + 'px';
    }

    function submitQuestion(rawQuestion, isRetry) {
        var question = String(rawQuestion || '').trim().slice(0, maxInputLength);
        if (!question || state.busy) {
            return;
        }

        state.lastQuestion = question;
        if (!isRetry) {
            addMessage({role: 'user', text: question});
        }
        ui.input.value = '';
        resizeInput();
        setBusy(true);

        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        state.responseTimer = window.setTimeout(function () {
            try {
                addMessage(buildAnswer(question));
            } catch (ignored) {
                addMessage(assistantMessage(
                    'I could not prepare a local guide response. Try again or open the full Page Guide.',
                    {error: true}
                ));
            } finally {
                state.responseTimer = null;
                setBusy(false);
                if (ui.root && ui.root.classList.contains('is-open') && ui.input) {
                    ui.input.focus();
                } else if (ui.unread) {
                    ui.unread.hidden = false;
                }
            }
        }, reduceMotion ? 0 : 520);
    }

    function isMobile() {
        return Boolean(mobileQuery && mobileQuery.matches);
    }

    function setAppInert(shouldInert) {
        var app = document.querySelector('.layout-wrapper');
        if (!app) {
            return;
        }
        if (shouldInert && !state.mobileInert) {
            state.previousAppAriaHidden = app.getAttribute('aria-hidden');
            app.setAttribute('inert', '');
            app.setAttribute('aria-hidden', 'true');
            state.mobileInert = true;
            return;
        }
        if (!shouldInert && state.mobileInert) {
            app.removeAttribute('inert');
            if (state.previousAppAriaHidden === null) {
                app.removeAttribute('aria-hidden');
            } else {
                app.setAttribute('aria-hidden', state.previousAppAriaHidden);
            }
            state.previousAppAriaHidden = null;
            state.mobileInert = false;
        }
    }

    function syncPanelMode() {
        if (!ui.panel) {
            return;
        }
        var mobile = isMobile();
        ui.panel.setAttribute('role', mobile ? 'dialog' : 'complementary');
        if (mobile) {
            ui.panel.setAttribute('aria-modal', 'true');
        } else {
            ui.panel.removeAttribute('aria-modal');
        }
        setAppInert(mobile && ui.root.classList.contains('is-open'));
    }

    function trapFocus(container, event) {
        var selector = 'button:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])';
        var focusable = Array.prototype.filter.call(container.querySelectorAll(selector), function (element) {
            return element.getClientRects().length > 0;
        });
        if (!focusable.length) {
            event.preventDefault();
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

    function openChat() {
        if (!ui.root || ui.root.classList.contains('is-open')) {
            return;
        }
        state.lastFocused = document.activeElement;
        ui.root.classList.add('is-open');
        ui.launcher.setAttribute('aria-expanded', 'true');
        ui.panel.setAttribute('aria-hidden', 'false');
        if (ui.unread) {
            ui.unread.hidden = true;
        }
        document.body.classList.add('tasca-chat-open');
        syncPanelMode();
        window.setTimeout(function () {
            if (ui.input) {
                ui.input.focus();
            }
        }, 0);
    }

    function closeChat() {
        if (!ui.root || !ui.root.classList.contains('is-open')) {
            return;
        }
        ui.root.classList.remove('is-open');
        ui.launcher.setAttribute('aria-expanded', 'false');
        ui.panel.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('tasca-chat-open');
        setAppInert(false);
        if (state.lastFocused && typeof state.lastFocused.focus === 'function') {
            state.lastFocused.focus();
        } else {
            ui.launcher.focus();
        }
    }

    function toggleChat() {
        if (ui.root.classList.contains('is-open')) {
            closeChat();
        } else {
            openChat();
        }
    }

    function openPageGuide() {
        closeChat();
        if (window.HrisHelp && typeof window.HrisHelp.open === 'function') {
            window.HrisHelp.open();
        }
    }

    function injectUi() {
        if (!isAuthenticatedRole() || document.getElementById('tascaChatRoot')) {
            return;
        }

        var root = document.createElement('div');
        root.className = 'tasca-chat-root';
        root.id = 'tascaChatRoot';
        root.innerHTML = ''
            + '<button type="button" class="tasca-chat-launcher" id="tascaChatLauncher" aria-label="Open TASCA AI assistant" aria-controls="tascaChatPanel" aria-expanded="false">'
            + '<img class="tasca-chat-launcher-mark" src="' + brandMarkPath + '" alt="" aria-hidden="true">'
            + '<span class="tasca-chat-launcher-status" aria-hidden="true"></span>'
            + '<span class="tasca-chat-unread" id="tascaChatUnread" aria-label="New TASCA message" hidden>1</span>'
            + '</button>'
            + '<section class="tasca-chat-panel" id="tascaChatPanel" role="complementary" aria-label="TASCA AI assistant" aria-hidden="true">'
            + '<header class="tasca-chat-header">'
            + '<div class="tasca-chat-identity"><img class="tasca-chat-avatar" src="' + brandMarkPath + '" alt="" aria-hidden="true">'
            + '<div class="tasca-chat-identity-copy"><strong>TASCA AI</strong><span class="tasca-chat-mode">Guide mode</span></div></div>'
            + '<div class="tasca-chat-header-actions">'
            + '<button type="button" class="tasca-chat-action" id="tascaChatReset" aria-label="Start a new chat" title="Start a new chat"><i class="bx bx-refresh" aria-hidden="true"></i></button>'
            + '<button type="button" class="tasca-chat-action" id="tascaChatClose" aria-label="Minimize TASCA" title="Minimize TASCA"><i class="bx bx-minus" aria-hidden="true"></i></button>'
            + '</div></header>'
            + '<div class="tasca-chat-context"><i class="bx bx-layout" aria-hidden="true"></i><span>Current page:</span><strong id="tascaChatPage"></strong></div>'
            + '<div class="tasca-chat-messages" id="tascaChatMessages" role="log" aria-live="polite" aria-relevant="additions text"></div>'
            + '<div class="tasca-chat-typing" id="tascaChatTyping" role="status" aria-live="polite" aria-hidden="true"><span>TASCA is typing</span><span class="tasca-chat-typing-dots" aria-hidden="true"><span></span><span></span><span></span></span></div>'
            + '<div class="tasca-chat-quick-actions" id="tascaChatQuickActions" aria-label="Suggested questions">'
            + '<button type="button" class="tasca-chat-quick-action" data-tasca-question="Explain this page">Explain this page</button>'
            + '<button type="button" class="tasca-chat-quick-action" data-tasca-question="What can I do here?">What can I do here?</button>'
            + '<button type="button" class="tasca-chat-quick-action" data-tasca-question="Show the safest next steps">Safe next steps</button>'
            + '<button type="button" class="tasca-chat-quick-action" id="tascaChatGuideAction" data-tasca-action="guide">Open Page Guide</button>'
            + '</div>'
            + '<form class="tasca-chat-composer" id="tascaChatForm">'
            + '<label class="tasca-visually-hidden" for="tascaChatInput">Message TASCA</label>'
            + '<textarea class="tasca-chat-input" id="tascaChatInput" rows="1" maxlength="500" placeholder="Message TASCA..." autocomplete="off" enterkeyhint="send" aria-describedby="tascaChatPrivacy"></textarea>'
            + '<button type="submit" class="tasca-chat-send" id="tascaChatSend" aria-label="Send message" disabled><i class="bx bx-send" aria-hidden="true"></i></button>'
            + '</form>'
            + '<p class="tasca-chat-privacy" id="tascaChatPrivacy">Guide mode only. Messages remain in memory and clear when this page reloads. TASCA cannot view or change HRIS records.</p>'
            + '</section>';
        document.body.appendChild(root);

        ui.root = root;
        ui.launcher = document.getElementById('tascaChatLauncher');
        ui.panel = document.getElementById('tascaChatPanel');
        ui.messages = document.getElementById('tascaChatMessages');
        ui.typing = document.getElementById('tascaChatTyping');
        ui.unread = document.getElementById('tascaChatUnread');
        ui.input = document.getElementById('tascaChatInput');
        ui.send = document.getElementById('tascaChatSend');
        ui.form = document.getElementById('tascaChatForm');
        document.getElementById('tascaChatPage').textContent = currentGuide.title;

        state.messages = [welcomeMessage()];
        if (accessLevel() === '4') {
            document.getElementById('tascaChatGuideAction').remove();
        }
        renderMessages();
        updateComposerState();
        syncPanelMode();

        ui.launcher.addEventListener('click', toggleChat);
        document.getElementById('tascaChatClose').addEventListener('click', closeChat);
        document.getElementById('tascaChatReset').addEventListener('click', resetMessages);
        ui.form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitQuestion(ui.input.value, false);
        });
        ui.input.addEventListener('input', function () {
            resizeInput();
            updateComposerState();
        });
        ui.input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                submitQuestion(ui.input.value, false);
            }
        });
        document.getElementById('tascaChatQuickActions').addEventListener('click', function (event) {
            var button = event.target.closest('button');
            if (!button) {
                return;
            }
            if (button.dataset.tascaAction === 'guide') {
                openPageGuide();
                return;
            }
            if (button.dataset.tascaQuestion) {
                submitQuestion(button.dataset.tascaQuestion, false);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Tab' && isMobile() && ui.root.classList.contains('is-open')) {
                trapFocus(ui.panel, event);
                return;
            }
            if (event.key === 'Escape' && ui.root.classList.contains('is-open')) {
                closeChat();
            }
        });
        document.addEventListener('show.bs.modal', closeChat);
        document.addEventListener('show.bs.offcanvas', closeChat);
        if (mobileQuery && typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', syncPanelMode);
        }
    }

    window.TascaChat = {
        open: openChat,
        close: closeChat,
        reset: resetMessages,
        ask: function (question) {
            openChat();
            submitQuestion(question, false);
        },
        mode: 'guide'
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectUi);
    } else {
        injectUi();
    }
}(window, document));
