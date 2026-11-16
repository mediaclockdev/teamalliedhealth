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
  $rows[] = [$LABELS[$k] ?? $k, $val($b[$k])];
}
$message = (isset($b['message']) && !$skip('message', $b['message'])) ? $val($b['message']) : '';

$text = "$service enquiry\n\n";
foreach ($rows as [$l, $v]) $text .= "$l: $v\n";
if ($message !== '') $text .= "\nMessage:\n$message\n";

$nameCell = '<span style="font-size:24px;font-weight:700;color:#fff;letter-spacing:.2px">Team Allied Health</span>';
$site = rtrim($cfg['site_url'] ?? '', '/');
$logo = $site
  ? '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="padding-right:14px;vertical-align:middle"><img src="' . esc($site) . '/assets/logo-email.png" alt="" width="88" height="88" style="display:block;border:3px solid #fff;width:82px;height:82px;border-radius:50%"></td><td style="vertical-align:middle">' . $nameCell . '</td></tr></table>'
  : $nameCell;

$tr = '';
foreach ($rows as [$l, $v]) {
  $tr .= '<tr><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;width:38%;font-weight:700;color:#475569;vertical-align:top">' . esc($l)
       . '</td><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;vertical-align:top">' . esc($v) . '</td></tr>';
}
$msgBlock = $message === '' ? '' :
  '<div style="margin-top:16px;padding:14px 16px;background:#f0fdf4;border-left:4px solid #047857;border-radius:6px"><div style="font-weight:700;color:#475569;margin-bottom:6px">Message</div><div style="white-space:pre-wrap">' . esc($message) . '</div></div>';

$html = '<!doctype html><html><body style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">'
  . '<tr><td style="background:#004719;padding:20px 24px">' . $logo . '</td></tr>'
  . '<tr><td style="padding:24px 24px 8px"><h2 style="margin:0;font-size:20px;color:#004719">New ' . esc($service) . ' enquiry</h2></td></tr>'
  . '<tr><td style="padding:8px 24px 24px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $tr . '</table>' . $msgBlock . '</td></tr>'
  . '<tr><td style="padding:14px 24px;background:#f8fafc;font-size:12px;color:#64748b">Sent from the website form. Reply to this email to respond to the enquirer.</td></tr>'
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
