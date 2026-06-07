<?php
// Открыть ОДИН раз в браузере, чтобы привязать webhook к боту.
// Затем удалите этот файл с сервера.
require_once __DIR__ . '/lib.php';

// URL вашего webhook.php (поменяйте домен/путь при необходимости)
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$dir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$webhookUrl = $base . $dir . '/webhook.php';

$res = tg('setWebhook', [
    'url'            => $webhookUrl,
    'secret_token'   => WEBHOOK_SECRET,
    'allowed_updates'=> json_encode(['message', 'callback_query']),
]);

header('Content-Type: text/plain; charset=utf-8');
echo "Webhook URL: $webhookUrl\n\n";
echo "Ответ Telegram:\n" . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "\n\nЕсли ok:true — готово. Напишите боту /menu и удалите этот файл.";
