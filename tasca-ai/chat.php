<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function tasca_chat_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    tasca_chat_json(405, ['success' => 0, 'error' => 'Method not allowed.']);
}

require_once(__DIR__ . '/../includes/auth_guard.php');
require_once(__DIR__ . '/../includes/tasca_gemini.php');

auth_require_role([1, 2, 3, 4, 5]);

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    tasca_chat_json(413, ['success' => 0, 'error' => 'The message is too large.']);
}

$raw = (string)file_get_contents('php://input');
$request = json_decode($raw, true);
if (!is_array($request)) {
    tasca_chat_json(400, ['success' => 0, 'error' => 'Invalid chat request.']);
}

$question = trim((string)($request['question'] ?? ''));
if ($question === '' || mb_strlen($question) > 500) {
    tasca_chat_json(422, ['success' => 0, 'error' => 'Enter a message of up to 500 characters.']);
}
if (tasca_ai_contains_sensitive_input($question)) {
    tasca_chat_json(422, [
        'success' => 0,
        'code' => 'sensitive_input',
        'error' => 'Do not enter employee names, IDs, contact details, payroll values, banking information, credentials, or other private records. Ask for general process guidance instead.',
    ]);
}
if (!tasca_ai_rate_limit(session_id())) {
    tasca_chat_json(429, ['success' => 0, 'error' => 'TASCA is receiving too many messages. Wait a minute and try again.']);
}

$contextInput = is_array($request['context'] ?? null) ? $request['context'] : [];
$cleanList = static function ($value): array {
    if (!is_array($value)) {
        return [];
    }
    $clean = [];
    foreach (array_slice($value, 0, 6) as $item) {
        if (is_string($item) && trim($item) !== '') {
            $clean[] = mb_substr(trim($item), 0, 300);
        }
    }
    return $clean;
};
$context = [
    'page_slug' => preg_replace('/[^a-z0-9-]/', '', strtolower((string)($contextInput['pageSlug'] ?? ''))) ?: 'unknown',
    'page_title' => mb_substr(trim((string)($contextInput['pageTitle'] ?? 'Current page')), 0, 120),
    'summary' => mb_substr(trim((string)($contextInput['summary'] ?? '')), 0, 500),
    'visible_areas' => $cleanList($contextInput['visibleAreas'] ?? []),
    'safety_reminders' => $cleanList($contextInput['safetyReminders'] ?? []),
];

try {
    $config = tasca_ai_load_config();
    if (!$config['configured']) {
        tasca_chat_json(503, ['success' => 0, 'error' => 'Gemini is not configured for this TAASCOR environment.']);
    }
    $answer = tasca_ai_generate($question, auth_level(), $context, $config);
    tasca_chat_json(200, [
        'success' => 1,
        'text' => $answer['text'],
        'source' => $context['page_title'],
        'provider' => 'Gemini AI',
        'model' => $answer['model'],
    ]);
} catch (DomainException $exception) {
    tasca_chat_json(422, ['success' => 0, 'error' => 'Gemini could not answer that request safely. Rephrase it as general HRIS process guidance.']);
} catch (Throwable $exception) {
    error_log('TASCA AI gateway error: ' . get_class($exception));
    tasca_chat_json(502, ['success' => 0, 'error' => 'TASCA AI is temporarily unavailable. Try again or open the Page Guide.']);
}
