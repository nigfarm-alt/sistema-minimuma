<?php
/* ============================================================
   ПРИЁМ АНКЕТЫ ПЕРЕД СТАРТОМ
   Кладётся рядом с anketa.html в корень сайта.
   Единственное, что нужно поменять — строка $TO ниже.
   Скрипт НИЧЕГО не сохраняет на сервере: письмо уходит на почту
   и всё. Так меньше рисков при утечке.
   ============================================================ */

$TO = 'dmitry@gromov.fit';          // ← сюда приходят анкеты
$FROM = 'anketa@gromov.fit';        // ← техническая почта отправителя, создайте ящик в панели

header('Content-Type: application/json; charset=utf-8');

function fail($msg = 'error') {
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('method');

/* ловушка для ботов: поле company скрыто, человек его не заполнит */
if (!empty($_POST['company'])) { echo json_encode(['ok' => true]); exit; }

/* простая защита от спама: не чаще одной анкеты в минуту с адреса */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$lock = sys_get_temp_dir() . '/anketa_' . md5($ip);
if (file_exists($lock) && (time() - filemtime($lock)) < 60) fail('too_fast');
@touch($lock);

$text = $_POST['text'] ?? '';
$fio  = $_POST['fio']  ?? '';
$tg   = $_POST['tg']   ?? '';

if (mb_strlen($text) < 100 || mb_strlen($text) > 20000) fail('empty');
if ($fio === '' || $tg === '') fail('empty');

/* все три галочки должны прийти */
if (empty($_POST['c1']) || empty($_POST['c2']) || empty($_POST['c3'])) fail('consent');

/* вычищаем переводы строк из всего, что попадёт в заголовки письма */
function clean($s) { return trim(preg_replace('/[\r\n]+/', ' ', mb_substr($s, 0, 200))); }

$fioClean = clean($fio);
$tgClean  = clean($tg);

$kind = ($_POST['kind'] ?? '') === 'publikaciya' ? 'Согласие на публикацию' : 'Анкета';
$subject  = $kind . ': ' . $fioClean;
$headers  = "From: Анкета сайта <{$FROM}>\r\n";
$headers .= "Reply-To: {$FROM}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

$body  = $text;
$body .= "\n----------------------------------------\n";
$body .= "Telegram: {$tgClean}\n";
$body .= "Получено: " . date('d.m.Y H:i') . "\n";
$body .= "IP: {$ip}\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$sent = @mail($TO, $encodedSubject, $body, $headers);

if ($sent) {
  echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} else {
  fail('mail');
}
