<?php
// ─── Настройки ───
// Токен бота от @BotFather:
define('BOT_TOKEN', 'ВСТАВЬТЕ_ТОКЕН_БОТА');

// ID чатов админов (узнать у @userinfobot). Можно несколько.
$ADMIN_CHAT_IDS = ['ВАШ_CHAT_ID'];

// Секрет для защиты webhook-URL (придумайте любую строку без пробелов).
define('WEBHOOK_SECRET', 'change-me-secret-123');

// Файл-хранилище заявок (рядом со скриптами).
define('STORE_FILE', __DIR__ . '/guests.jsonl');

// Разрешённый источник (домен сайта) для CORS.
define('ALLOW_ORIGIN', 'https://vmwed.ru');

define('API', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

$ATTENDANCE_RU = [
    'yes'   => '✅ Да, смогу',
    'maybe' => '🤔 Пока не уверен(а)',
    'no'    => '❌ К сожалению, не смогу',
];
