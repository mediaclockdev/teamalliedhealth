<?php
// Form mailer: emails every submitted field via SMTP (PHPMailer). Settings live in mail-config.php.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/lib/phpmailer/Exception.php';
require __DIR__ . '/lib/phpmailer/PHPMailer.php';
require __DIR__ . '/lib/phpmailer/SMTP.php';

header('Content-Type: application/json');
function out($ok, $code = 200) { http_response_code($code); echo json_encode(['ok' => $ok]); exit; }
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 405);
$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) out(false, 400);
if (!empty($b['_hp'])) out(true); // honeypot: pretend success to bots

$name  = trim((string)($b['name'] ?? ''));
$email = trim((string)($b['email'] ?? ''));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 422);

// Config: prefer a copy above the web root (not downloadable); fall back to one beside this file.
$cfgFile = is_file(dirname(__DIR__) . '/mail-config.php') ? dirname(__DIR__) . '/mail-config.php' : __DIR__ . '/mail-config.php';
if (!is_file($cfgFile)) { error_log('mail-config.php missing'); out(false, 500); }
$cfg = require $cfgFile;

$LABELS = [
  'for' => 'Enquiry is for', 'name' => 'Name', 'dob' => 'Date of birth', 'parent' => 'Parent/caregiver',
  'guardian' => 'Parent/guardian', 'age' => 'Age', 'address' => 'Address', 'email' => 'Email', 'phone' => 'Phone',
  'funding' => 'Funding', 'therapy' => 'Therapy', 'location' => 'Location', 'topic' => 'Topic',
  'focus' => 'Focus', 'delivery' => 'Delivery', 'message' => 'Message', 'consent' => 'Consent',
];
$SERVICES = [
  'index' => 'Referral', 'contact' => 'Contact', 'early-intervention' => 'Early Intervention',
  'occupational-therapy' => 'Occupational Therapy', 'physiotherapy' => 'Physiotherapy', 'speech-therapy' => 'Speech Therapy',
  'psychology' => 'Psychology', 'behaviour-therapy' => 'Behaviour Therapy',
];

$page = preg_replace('/\.html$/', '', (string)($b['_page'] ?? ''));
$service = $SERVICES[$page] ?? 'Website';

$val  = fn($v) => $v === 'on' ? 'Yes' : mb_substr(trim((string)$v), 0, 5000);
$skip = fn($k, $v) => $k === '_hp' || $k === '_page' || is_array($v) || trim((string)$v) === '';

// Name, email, phone first; message last in its own block; everything else in form order.
$top  = ['name', 'email', 'phone'];
$keys = array_merge($top, array_filter(array_keys($b), fn($k) => !in_array($k, $top) && $k !== 'message'));
$rows = [];
foreach ($keys as $k) {
  if (!array_key_exists($k, $b) || $skip($k, $b[$k])) continue;
  $rows[] = [$LABELS[$k] ?? $k, $val($b[$k]), $k];
}
$message = (isset($b['message']) && !$skip('message', $b['message'])) ? $val($b['message']) : '';

$text = "$service enquiry\n\n";
foreach ($rows as [$l, $v, $_]) $text .= "$l: $v\n";
if ($message !== '') $text .= "\nMessage:\n$message\n";

$nameCell = '<span style="font-size:24px;font-weight:700;color:#fff;letter-spacing:.2px">Team Allied Health</span>';
$site = rtrim($cfg['site_url'] ?? '', '/');
$logo = $site
  ? '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="padding-right:14px;vertical-align:middle"><img src="' . esc($site) . '/assets/logo-email.png" alt="" width="88" height="88" style="display:block;border:3px solid #fff;width:82px;height:82px;border-radius:50%"></td><td style="vertical-align:middle">' . $nameCell . '</td></tr></table>'
  : $nameCell;

$ICONS = ['name' => '👤', 'email' => '✉️', 'phone' => '📞', 'dob' => '📅', 'address' => '📍', 'funding' => '💳', 'therapy' => '💚', 'location' => '📌', 'consent' => '✅', 'for' => '🙋', 'focus' => '🎯', 'delivery' => '🏠', 'topic' => '💡', 'parent' => '👪', 'guardian' => '👪', 'age' => '🎂'];
$card = fn($l, $v, $k) => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f4;border-radius:8px"><tr>'
  . '<td width="36" style="padding:12px 0 12px 12px;font-size:18px;vertical-align:middle">' . ($ICONS[$k] ?? '📝') . '</td>'
  . '<td style="padding:10px 12px;vertical-align:middle"><div style="font-size:12px;color:#475569">' . esc($l) . '</div>'
  . '<div style="font-size:15px;font-weight:700;color:#0f172a;word-break:break-word">' . ($k === 'email' ? '<a href="mailto:' . esc($v) . '" style="color:#047857">' . esc($v) . '</a>' : esc($v)) . '</div></td></tr></table>';

$grid = '';
$pend = null; // card waiting for a partner in the 2-column grid
$cell = fn($c, $pad) => '<td width="50%" valign="top" style="padding:' . $pad . '">' . $c . '</td>';
foreach ($rows as [$l, $v, $k]) {
  if ($k === 'address') { // long values get a full-width row
    if ($pend !== null) { $grid .= '<tr><td colspan="2" style="padding:0 0 8px">' . $pend . '</td></tr>'; $pend = null; }
    $grid .= '<tr><td colspan="2" style="padding:0 0 8px">' . $card($l, $v, $k) . '</td></tr>';
  } elseif ($pend === null) $pend = $card($l, $v, $k);
  else { $grid .= '<tr>' . $cell($pend, '0 4px 8px 0') . $cell($card($l, $v, $k), '0 0 8px 4px') . '</tr>'; $pend = null; }
}
if ($pend !== null) $grid .= '<tr><td colspan="2" style="padding:0 0 8px">' . $pend . '</td></tr>';

$msgBlock = $message === '' ? '' :
  '<div style="margin-top:12px;font-size:17px;font-weight:700;color:#004719">💬 Message</div>'
  . '<div style="margin-top:8px;padding:14px 16px;background:#e8f6ec;border-left:4px solid #2e9e4f;border-radius:6px;white-space:pre-wrap">' . esc($message) . '</div>';

$html = '<!doctype html><html><body style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden">'
  . '<tr><td style="background:#0b5a2b;padding:22px 24px">' . $logo . '</td></tr>'
  . '<tr><td style="padding:20px 24px 8px"><div style="padding:14px 16px;background:#e8f6ec;border-radius:8px"><div style="font-size:19px;font-weight:700;color:#004719">New ' . esc($service) . ' Enquiry Received</div>'
  . '<div style="font-size:14px;color:#334155;margin-top:2px">A new enquiry has been submitted through the website form.</div></div></td></tr>'
  . '<tr><td style="padding:12px 24px 8px"><div style="font-size:17px;font-weight:700;color:#004719;padding-bottom:6px;margin-bottom:10px;border-bottom:1px solid #cbd5e1">👤 ' . esc($service) . ' Details</div>'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $grid . '</table>' . $msgBlock . '</td></tr>'
  . '<tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;text-align:center">Sent from the website form. Please reply to this email to respond to the enquirer.</td></tr>'
  . '</table></body></html>';

try {
  $m = new PHPMailer(true);
  $m->isSMTP();
  $m->Host = $cfg['host'];
  $m->Port = (int)$cfg['port'];
  $m->SMTPAuth = true;
  $m->Username = $cfg['user'];
  $m->Password = $cfg['pass'];
  $secure = $cfg['secure'] ?? ($m->Port === 465 ? 'ssl' : 'tls'); // 'ssl' | 'tls' | 'none' (none: local testing only)
  $m->SMTPSecure = ['ssl' => PHPMailer::ENCRYPTION_SMTPS, 'tls' => PHPMailer::ENCRYPTION_STARTTLS][$secure] ?? '';
  $m->SMTPAutoTLS = $secure !== 'none';
  $m->CharSet = 'UTF-8';
  $m->setFrom($cfg['from'] ?? $cfg['user'], 'Team Allied Health');
  $m->addAddress($cfg['to'] ?? $cfg['user']);
  $m->addReplyTo($email, str_replace(["\r", "\n"], '', $name));
  $m->Subject = "New $service enquiry – " . str_replace(["\r", "\n"], '', $name);
  $m->isHTML(true);
  $m->Body = $html;
  $m->AltBody = $text;
  $m->send();
  out(true);
} catch (Exception $e) {
  error_log('mail failed: ' . $e->getMessage());
  out(false, 500);
}
