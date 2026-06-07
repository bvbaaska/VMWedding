<?php
require_once __DIR__ . '/config.php';

// ─── Хранилище (JSON Lines, без БД — работает на любом PHP) ───
function store_guest($name, $attendance, $comment) {
    $row = [
        'name'       => $name,
        'attendance' => $attendance,
        'comment'    => $comment,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents(STORE_FILE, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
                      FILE_APPEND | LOCK_EX);
    return $row;
}

function read_guests() {
    if (!file_exists(STORE_FILE)) return [];
    $out = [];
    foreach (file(STORE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $r = json_decode($line, true);
        if ($r) $out[] = $r;
    }
    return $out;
}

// ─── Telegram API ───
function tg($method, $params) {
    $ch = curl_init(API . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function tg_send($chat_id, $text, $markup = null) {
    $p = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($markup) $p['reply_markup'] = json_encode($markup);
    return tg('sendMessage', $p);
}

function main_menu() {
    return ['inline_keyboard' => [
        [['text' => '📥 Скачать список гостей (Excel)', 'callback_data' => 'export']],
        [['text' => '📊 Статистика', 'callback_data' => 'stats']],
    ]];
}

function notify_admins($row) {
    global $ADMIN_CHAT_IDS, $ATTENDANCE_RU;
    $att = $ATTENDANCE_RU[$row['attendance']] ?? $row['attendance'];
    $text = "💌 <b>Новая заявка с сайта!</b>\n\n"
          . "<b>ФИО:</b> " . htmlspecialchars($row['name']) . "\n"
          . "<b>Придёт:</b> $att\n"
          . "<b>Комментарий:</b> " . (htmlspecialchars($row['comment']) ?: '—') . "\n\n"
          . "<i>" . $row['created_at'] . "</i>";
    foreach ($ADMIN_CHAT_IDS as $cid) tg_send($cid, $text, main_menu());
}

// ─── Генерация настоящего .xlsx (без сторонних библиотек) ───
function build_xlsx($headers, $rows) {
    $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $colName = function ($n) { $s=''; while($n>0){ $m=($n-1)%26; $s=chr(65+$m).$s; $n=intval(($n-$m)/26);} return $s; };

    $sheetRows = '';
    $all = array_merge([$headers], $rows);
    foreach ($all as $r => $cells) {
        $rn = $r + 1;
        $sheetRows .= "<row r=\"$rn\">";
        foreach (array_values($cells) as $c => $val) {
            $ref = $colName($c + 1) . $rn;
            $sheetRows .= "<c r=\"$ref\" t=\"inlineStr\"><is><t xml:space=\"preserve\">"
                        . $esc((string)$val) . "</t></is></c>";
        }
        $sheetRows .= "</row>";
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Гости" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    return $tmp; // путь к файлу
}

function send_excel($chat_id) {
    global $ATTENDANCE_RU;
    $guests = read_guests();
    if (!$guests) { tg_send($chat_id, 'Пока нет ни одной заявки 🤷'); return; }

    $headers = ['№', 'ФИО', 'Сможете прийти', 'Комментарий', 'Дата отправки'];
    $rows = [];
    foreach ($guests as $i => $g) {
        $rows[] = [
            $i + 1,
            $g['name'],
            $ATTENDANCE_RU[$g['attendance']] ?? $g['attendance'],
            $g['comment'] ?? '',
            $g['created_at'],
        ];
    }
    $path = build_xlsx($headers, $rows);
    $fname = 'guests_' . date('Ymd_Hi') . '.xlsx';

    tg('sendDocument', [
        'chat_id'  => $chat_id,
        'caption'  => 'Список гостей · всего заявок: ' . count($guests),
        'document' => new CURLFile($path, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $fname),
    ]);
    @unlink($path);
}

function send_stats($chat_id) {
    $g = read_guests();
    $yes = $maybe = $no = 0;
    foreach ($g as $r) {
        if ($r['attendance'] === 'yes') $yes++;
        elseif ($r['attendance'] === 'maybe') $maybe++;
        elseif ($r['attendance'] === 'no') $no++;
    }
    tg_send($chat_id,
        "📊 <b>Статистика заявок</b>\n\n"
        . "Всего: <b>" . count($g) . "</b>\n"
        . "✅ Придут: <b>$yes</b>\n"
        . "🤔 Не уверены: <b>$maybe</b>\n"
        . "❌ Не смогут: <b>$no</b>",
        main_menu());
}
