<?php

declare(strict_types=1);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$root = dirname(__DIR__);
$script = (string)file_get_contents($root . '/assets/js/tasca-chat.js');
$styles = (string)file_get_contents($root . '/assets/css/tasca-chat.css');
$footer = (string)file_get_contents($root . '/includes/custom-footer.php');
$guides = (string)file_get_contents($root . '/assets/js/hris-help-guides.js');
$brandMark = (string)file_get_contents($root . '/assets/img/svg/tasca-bot-logo.svg');
$gateway = (string)file_get_contents($root . '/tasca-ai/chat.php');
$gemini = (string)file_get_contents($root . '/includes/tasca_gemini.php');

$check(str_contains($footer, 'tasca-chat.css?v=20260801b'), 'TASCA styles load globally on authenticated pages');
$check(str_contains($footer, 'tasca-chat.js?v=20260801d'), 'TASCA Gemini script loads globally on authenticated pages');
$check(str_contains($script, "var brandMarkPath = '../assets/img/svg/tasca-bot-logo.svg?v=20260801c';"), 'TASCA uses the branded bot badge asset');
$check(substr_count($script, 'brandMarkPath') >= 4, 'launcher, header, and assistant messages share the branded bot badge');
$check(str_contains($brandMark, '<title>TASCA AI bot logo</title>'), 'branded badge has an accessible SVG title');
$check(str_contains($brandMark, 'TAASCOR hexagonal brand badge'), 'branded badge preserves the TAASCOR hexagonal identity');
$check(str_contains($brandMark, 'M935.595 464.768'), 'branded badge contains the bundled Boxicons bot glyph');
$check(str_contains($brandMark, 'translate(38 162) scale(.128 -.128)'), 'Boxicons bot glyph is optically enlarged for small-size clarity');
$check(substr_count($brandMark, 'fill="#FEDD21"') >= 3, 'bot glyph uses restrained gold intelligence details');
$check(!str_contains($script, 'tasca-chat-launcher-mark" aria-hidden="true">T</span>'), 'launcher no longer renders the circular yellow T mark');
$check(
    strpos($footer, 'hris-help-guides.js') < strpos($footer, 'tasca-chat.js'),
    'approved guide registry loads before TASCA'
);
$check(
    strpos($footer, 'hris-help.js') < strpos($footer, 'tasca-chat.js'),
    'Page Guide bridge loads before TASCA'
);

foreach (['1', '2', '3', '4', '5'] as $role) {
    $check(str_contains($script, "'{$role}'"), "TASCA allows authenticated role {$role}");
}
$check(str_contains($script, "var allowedRoles = ['1', '2', '3', '4', '5'];"), 'all five roles use one explicit availability list');
$check(str_contains($script, "document.getElementById('access_level')"), 'TASCA requires the authenticated page access marker');
$check(str_contains($script, "window.HrisHelp.open"), 'TASCA preserves access to the existing Page Guide');
$check(str_contains($script, "parts[parts.length - 1].toLowerCase() === 'index.php'"), 'route detection normalizes explicit index.php URLs');
$check(!str_contains($script, 'sessionStorage'), 'prototype does not retain messages across logout in session storage');
$check(!str_contains($script, 'localStorage'), 'prototype does not retain messages in local storage');
$check(str_contains($script, 'Gemini processes each question.'), 'visible copy discloses external AI processing');
$check(str_contains($script, 'Do not enter names, IDs, payroll values'), 'visible copy warns against sending private records');
$check(str_contains($script, "message.items.length"), 'structured guide steps render as real lists');
$check(str_contains($script, "text.textContent = message.text"), 'message text renders without HTML injection');
$check(str_contains($script, "listItem.textContent = item"), 'guide list items render without HTML injection');
$check(!str_contains($script, 'bestPayrollFaq'), 'prototype does not search role-unsafe payroll FAQs');
$check(!str_contains($script, 'currentGuide.canDo'), 'prototype does not treat guide actions as user entitlement');
$check(!str_contains($script, 'currentGuide.actions'), 'prototype does not expose action steps as role permission');
$check(str_contains($script, "accessLevel() === '4'"), 'Coordinator receives a dedicated read-only boundary');
$check(str_contains($script, 'No permission to create, upload, update, terminate, or delete'), 'Coordinator guidance blocks mutation claims');
$check(str_contains($script, "document.getElementById('tascaChatGuideAction').remove()"), 'Coordinator receives no TASCA link into action-oriented Page Guide content');
$check(str_contains($script, 'asksForPrivateRecord'), 'prototype refuses requests for sensitive records');
$check(str_contains($script, 'I cannot view or reveal employee'), 'sensitive-record refusal is explicit');
$check(str_contains($script, "mode: 'gemini-guide'"), 'chat identifies itself as Gemini guide mode');
$check(str_contains($script, 'Gemini guide mode'), 'visible Gemini mode label is present');
$check(str_contains($script, 'role="log"'), 'message history uses an accessible live log');
$check(str_contains($script, 'aria-live="polite"'), 'assistant updates are announced politely');
$check(str_contains($script, "event.key === 'Escape'"), 'chat supports keyboard dismissal');
$check(str_contains($script, "event.key === 'Enter' && !event.shiftKey"), 'composer supports Messenger-style Enter to send');
$check(str_contains($script, "ui.panel.setAttribute('aria-modal', 'true')"), 'mobile full-screen chat uses modal semantics');
$check(str_contains($script, "app.setAttribute('inert', '')"), 'mobile full-screen chat makes the application background inert');
$check(str_contains($script, 'trapFocus(ui.panel, event)'), 'mobile full-screen chat traps keyboard focus');
$check(str_contains($script, "document.addEventListener('show.bs.modal', closeChat)"), 'chat closes before application modals open');
$check(str_contains($script, 'state.abortController.abort()'), 'new chat cancels a pending Gemini request');
$check(str_contains($script, "ui.root.classList.contains('is-open') && ui.input"), 'completed hidden responses do not focus the closed composer');
$check(str_contains($script, 'tascaChatUnread'), 'closed chat reports a new assistant response with an unread badge');
$check(str_contains($script, "var aiEndpoint = '../tasca-ai/chat.php';"), 'browser calls only the same-origin TASCA AI gateway');
$check(str_contains($script, 'window.fetch(aiEndpoint'), 'Gemini requests use the same-origin gateway');
$check(str_contains($script, "'X-CSRF-Token': csrfToken()"), 'Gemini requests include the protected CSRF header');
$check(str_contains($script, "credentials: 'same-origin'"), 'Gemini requests retain the authenticated session');
$check(!str_contains($script, 'generativelanguage.googleapis.com'), 'Gemini API URL is not exposed to the browser');
$check(!str_contains($script, 'GEMINI_API_KEY'), 'Gemini API key name and value are not exposed to the browser');
$check(!str_contains($script, 'new XMLHttpRequest'), 'Gemini integration does not instantiate XMLHttpRequest');
$check(!str_contains($script, '$.ajax'), 'Gemini integration does not use jQuery AJAX');
$check(!str_contains($script, "window.addEventListener('scroll'"), 'prototype does not add a scroll listener');
$check(!str_contains($script, "window.location ="), 'TASCA never redirects the user automatically');
$check(!str_contains($script, "location.replace("), 'TASCA never replaces the current route automatically');
$check(!str_contains($script, "\u{2013}") && !str_contains($script, "\u{2014}"), 'visible TASCA copy contains no en dash or em dash');

$check(str_contains($gateway, 'auth_require_role([1, 2, 3, 4, 5])'), 'Gemini gateway requires one of the five authenticated roles');
$check(str_contains($gateway, "REQUEST_METHOD") && str_contains($gateway, "'POST'"), 'Gemini gateway accepts POST only');
$check(str_contains($gateway, 'tasca_ai_contains_sensitive_input'), 'Gemini gateway rejects likely private data before generation');
$check(str_contains($gateway, 'tasca_ai_rate_limit'), 'Gemini gateway rate-limits each authenticated session');
$check(str_contains($gemini, "'X-Goog-Api-Key: '"), 'Gemini credential is sent in a protected server-side header');
$check(str_contains($gemini, 'BLOCK_MEDIUM_AND_ABOVE'), 'Gemini uses conservative configurable safety filters');
$check(str_contains($gemini, 'You have no access to databases'), 'Gemini system instruction denies record and tool access');
$check(str_contains($gemini, "(\$part['thought'] ?? false) !== true"), 'Gemini internal thought parts are never returned to the browser');
$check(!str_contains($gemini, 'C:\\Users\\mAOskii'), 'tracked Gemini integration does not hardcode a local secret path');

$check(str_contains($styles, 'height: min(680px, calc(100dvh - 118px))'), 'desktop panel respects the dynamic viewport');
$check(str_contains($styles, '@media (max-width: 575.98px)'), 'mobile full-screen layout is defined');
$check(str_contains($styles, 'height: 100dvh'), 'mobile chat uses the dynamic viewport');
$check(str_contains($styles, 'env(safe-area-inset-bottom)'), 'mobile controls respect device safe areas');
$check(str_contains($styles, '@media (prefers-reduced-motion: reduce)'), 'chat honors reduced-motion preferences');
$check(str_contains($styles, '.tasca-chat-input:focus'), 'composer has a visible focus state');
$check(str_contains($styles, '--tasca-muted: #52657c'), 'small muted text uses the reviewed high-contrast token');
$check(str_contains($styles, 'color: #607188'), 'message timestamps use the reviewed high-contrast color');
$check(str_contains($styles, 'color: #5e7088'), 'composer placeholder uses the reviewed high-contrast color');
$check(str_contains($styles, 'min-height: 44px'), 'mobile interactive controls use a 44-pixel minimum target');
$check(str_contains($styles, 'body.hris-help-open .tasca-chat-root'), 'Page Guide state suppresses the TASCA layer');
$check(str_contains($styles, 'body.modal-open .tasca-chat-root'), 'Bootstrap modal state suppresses the TASCA layer');
$check(str_contains($styles, '.tasca-chat-message.is-error'), 'chat has a distinct error state');
$check(str_contains($styles, '.tasca-chat-typing.is-visible'), 'chat has a visible loading state');
$check(str_contains($styles, '@media print'), 'chat is excluded from printed HRIS pages');
$check(!str_contains($styles, "\u{2013}") && !str_contains($styles, "\u{2014}"), 'TASCA styles contain no en dash or em dash');

$check(str_contains($guides, 'window.HrisHelpGuides'), 'TASCA knowledge source remains the approved Page Guide registry');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: TASCA all-role Messenger-style guide prototype checks passed.\n";
