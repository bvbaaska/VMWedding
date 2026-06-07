<?php
// Telegram webhook: команды и кнопки бота
require_once __DIR__ . '/lib.php';

// Защита: Telegram должен передать наш секрет в заголовке
$hdr = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($hdr !== WEBHOOK_SECRET) { http_response_code(403); exit; }

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

// обычные сообщения / команды
if (isset($update['message'])) {
    $chat = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
    if ($text === '/start' || $text === '/menu') {
        tg_send($chat,
            "Привет! 👋\nЯ собираю заявки гостей со свадебного сайта <b>vmwed.ru</b>.\n\nВыберите действие:",
            main_menu());
    } else {
        tg_send($chat, "Откройте меню командой /menu", main_menu());
    }
}

// нажатия инлайн-кнопок
if (isset($update['callback_query'])) {
    $cq   = $update['callback_query'];
    $chat = $cq['message']['chat']['id'];
    tg('answerCallbackQuery', ['callback_query_id' => $cq['id']]);
    if ($cq['data'] === 'export') send_excel($chat);
    elseif ($cq['data'] === 'stats') send_stats($chat);
}

http_response_code(200);
echo 'ok';
