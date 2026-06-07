<?php
// Приём заявки с формы сайта → сохранение + уведомление в Telegram
require_once __DIR__ . '/lib.php';

header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')   { http_response_code(405); echo '{"ok":false}'; exit; }

// данные: JSON или обычная форма
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$name       = trim($data['name'] ?? '');
$attendance = trim($data['attendance'] ?? '');
$comment    = trim($data['comment'] ?? '');

global $ATTENDANCE_RU;
if ($name === '' || !isset($ATTENDANCE_RU[$attendance])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Заполните ФИО и выберите вариант'], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = store_guest($name, $attendance, $comment);
notify_admins($row);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
