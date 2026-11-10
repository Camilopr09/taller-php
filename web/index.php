<?php
declare(strict_types=1);

/**
 * Workshop – EC2 Practice
 * Página de diagnóstico para una instancia EC2 con Apache + PHP.
 * Archivo único, sin dependencias externas.
 *
 * >>> CAMBIA ESTE VALOR POR TU NOMBRE ANTES DE GRABAR EL VIDEO <<<
 */
const STUDENT_NAME = '[Tu nombre aquí]';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/* ------------------------------------------------------------------ */
/* Utilidades                                                          */
/* ------------------------------------------------------------------ */

function h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function server_str(string $key): ?string
{
    $v = $_SERVER[$key] ?? null;
    return (is_string($v) && $v !== '') ? $v : null;
}

function read_file(string $path): ?string
{
    $r = @file_get_contents($path);
    return ($r === false || $r === '') ? null : $r;
}

function fmt_bytes(float $b): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) {
        $b /= 1024;
        $i++;
    }
    return number_format($b, $i === 0 ? 0 : 1, '.', '') . ' ' . $u[$i];
}

function fmt_uptime(float $s): string
{
    $s = (int)$s;
    $d = intdiv($s, 86400);
    $hh = intdiv($s % 86400, 3600);
    $m = intdiv($s % 3600, 60);
    return ($d > 0 ? $d . 'd ' : '') . $hh . 'h ' . $m . 'm';
}

/* ------------------------------------------------------------------ */
/* Parámetros (lista blanca)                                           */
/* ------------------------------------------------------------------ */

$lang = (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'], true))
    ? $_GET['lang'] : 'es';
$format = (isset($_GET['format']) && is_string($_GET['format']) && in_array($_GET['format'], ['html', 'json'], true))
    ? $_GET['format'] : 'html';

$I18N = [
    'es' => [
        'title' => 'Workshop – EC2 Practice',
        'subtitle' => 'Aplicación web PHP desplegada en AWS EC2',
        'na' => 'No disponible',
        'not_ec2' => 'No se pudo leer la metadata de EC2 (IMDS). Esta página no parece estar ejecutándose en una instancia EC2, o el servicio de metadata está deshabilitado.',
        'badge_ec2' => 'Ejecutándose en EC2',
        'badge_local' => 'Fuera de EC2',
        'card_client' => 'Cliente',
        'card_server' => 'Servidor',
        'card_ec2' => 'Instancia EC2',
        'card_sys' => 'Extras · Sistema',
        'client_ip' => 'Dirección IP del cliente',
        'xff' => 'X-Forwarded-For (informativo)',
        'ua' => 'Navegador (User Agent)',
        'browser' => 'Navegador detectado',
        'os' => 'Sistema del cliente',
        'language' => 'Idioma del cliente',
        'protocol' => 'Protocolo HTTP',
        'method' => 'Método de la petición',
        'hostname' => 'Hostname del servidor',
        'server_ip' => 'IP del servidor',
        'datetime' => 'Fecha y hora del servidor',
        'live_clock' => 'Reloj en vivo',
        'timezone' => 'Zona horaria',
        'instance_id' => 'Instance ID',
        'az' => 'Zona de disponibilidad',
        'region' => 'Región',
        'private_ip' => 'IP privada',
        'instance_type' => 'Tipo de instancia',
        'ami' => 'AMI ID',
        'public_ip' => 'IPv4 pública',
        'public_host' => 'Hostname público',
        'mac' => 'Dirección MAC',
        'sgs' => 'Security Groups',
        'software' => 'Software del servidor',
        'php' => 'Versión de PHP',
        'sapi' => 'PHP SAPI',
        'os_server' => 'Sistema operativo',
        'kernel' => 'Kernel',
        'uptime' => 'Tiempo encendido (uptime)',
        'load' => 'Carga promedio (1/5/15 min)',
        'memory' => 'Memoria',
        'disk' => 'Disco (/)',
        'used' => 'usado',
        'of' => 'de',
        'copy' => 'Copiar',
        'copied' => '¡Copiado!',
        'json_link' => 'Ver JSON',
        'footer' => 'Workshop – EC2 Practice · Universidad Autónoma del Caribe',
        'student' => 'Estudiante',
        'toggle' => 'English',
        'toggle_lang' => 'en',
    ],
    'en' => [
        'title' => 'Workshop – EC2 Practice',
        'subtitle' => 'PHP web application deployed on AWS EC2',
        'na' => 'Not available',
        'not_ec2' => 'EC2 metadata (IMDS) could not be read. This page does not seem to be running on an EC2 instance, or the metadata service is disabled.',
        'badge_ec2' => 'Running on EC2',
        'badge_local' => 'Not on EC2',
        'card_client' => 'Client',
        'card_server' => 'Server',
        'card_ec2' => 'EC2 Instance',
        'card_sys' => 'Extras · System',
        'client_ip' => 'Client IP address',
        'xff' => 'X-Forwarded-For (informational)',
        'ua' => 'Browser (User Agent)',
        'browser' => 'Detected browser',
        'os' => 'Client OS',
        'language' => 'Client language',
        'protocol' => 'HTTP protocol',
        'method' => 'Request method',
        'hostname' => 'Server hostname',
        'server_ip' => 'Server IP address',
        'datetime' => 'Server date and time',
        'live_clock' => 'Live clock',
        'timezone' => 'Time zone',
        'instance_id' => 'Instance ID',
        'az' => 'Availability Zone',
        'region' => 'Region',
        'private_ip' => 'Private IP address',
        'instance_type' => 'Instance type',
        'ami' => 'AMI ID',
        'public_ip' => 'Public IPv4',
        'public_host' => 'Public hostname',
        'mac' => 'MAC address',
        'sgs' => 'Security Groups',
        'software' => 'Server software',
        'php' => 'PHP version',
        'sapi' => 'PHP SAPI',
        'os_server' => 'Operating system',
        'kernel' => 'Kernel',
        'uptime' => 'Uptime',
        'load' => 'Load average (1/5/15 min)',
        'memory' => 'Memory',
        'disk' => 'Disk (/)',
        'used' => 'used',
        'of' => 'of',
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'json_link' => 'View JSON',
        'footer' => 'Workshop – EC2 Practice · Universidad Autónoma del Caribe',
        'student' => 'Student',
        'toggle' => 'Español',
        'toggle_lang' => 'es',
    ],
];

function t(string $k): string
{
    global $I18N, $lang;
    return $I18N[$lang][$k] ?? $I18N['es'][$k] ?? $k;
}

/* ------------------------------------------------------------------ */
/* Metadata de EC2 (IMDSv2) con caché de ~60 s                         */
/* ------------------------------------------------------------------ */

function imds_request(string $url, string $method, array $headers, float $timeout = 1.0): ?string
{
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'timeout' => $timeout,
        'ignore_errors' => false,
    ]]);
    $old = ini_set('default_socket_timeout', (string)$timeout);
    try {
        $r = @file_get_contents($url, false, $ctx);
    } catch (\Throwable $e) {
        $r = false;
    }
    if ($old !== false) {
        ini_set('default_socket_timeout', $old);
    }
    return ($r === false) ? null : trim($r);
}

function imds_fetch(string $base): array
{
    $fail = ['ok' => false];

    // IMDSv2: token de sesión
    $token = imds_request(
        $base . '/latest/api/token',
        'PUT',
        ['X-aws-ec2-metadata-token-ttl-seconds: 21600', 'Content-Length: 0']
    );
    $hdr = [];
    if ($token !== null && preg_match('/^[\x21-\x7e]+$/', $token) === 1) {
        $hdr = ['X-aws-ec2-metadata-token: ' . $token];
    }
    // Si no hubo token, se intenta un GET estilo IMDSv1 (solo como respaldo)

    $get = static fn(string $path): ?string => (($v = imds_request($base . '/latest/meta-data/' . $path, 'GET', $hdr)) !== null && $v !== '') ? $v : null;

    $id = $get('instance-id');
    if ($id === null) {
        return $fail;
    }
    $az = $get('placement/availability-zone');
    $region = $get('placement/region');
    if ($region === null && $az !== null) {
        $region = preg_replace('/[a-z]$/', '', $az);
    }
    $sg = $get('security-groups');

    return [
        'ok' => true,
        'imds_version' => $hdr ? 'v2' : 'v1',
        'instance_id' => $id,
        'az' => $az,
        'region' => $region,
        'private_ip' => $get('local-ipv4'),
        'instance_type' => $get('instance-type'),
        'ami_id' => $get('ami-id'),
        'public_ip' => $get('public-ipv4'),
        'public_hostname' => $get('public-hostname'),
        'mac' => $get('mac'),
        'security_groups' => $sg !== null ? array_values(array_filter(array_map('trim', explode("\n", $sg)))) : null,
    ];
}

function imds_get(): array
{
    $env = getenv('IMDS_ENDPOINT');
    $base = rtrim((is_string($env) && $env !== '') ? $env : 'http://169.254.169.254', '/');
    $cache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'workshop-ec2-imds-' . md5($base) . '.json';

    $raw = @file_get_contents($cache);
    if ($raw !== false) {
        $c = json_decode($raw, true);
        if (is_array($c) && isset($c['ts'], $c['data']) && is_int($c['ts']) && is_array($c['data'])) {
            $ttl = !empty($c['data']['ok']) ? 60 : 15; // los fallos se cachean menos
            if (time() - $c['ts'] < $ttl) {
                return $c['data'];
            }
        }
    }
    $data = imds_fetch($base);
    @file_put_contents($cache, json_encode(['ts' => time(), 'data' => $data]), LOCK_EX);
    return $data;
}

/* ------------------------------------------------------------------ */
/* Detección de navegador / SO                                         */
/* ------------------------------------------------------------------ */

function parse_browser(?string $ua): ?string
{
    if ($ua === null) {
        return null;
    }
    $map = [
        ['/Edg(?:e|A|iOS)?\/([\d.]+)/', 'Microsoft Edge'],
        ['/OPR\/([\d.]+)/', 'Opera'],
        ['/(?:Firefox|FxiOS)\/([\d.]+)/', 'Firefox'],
        ['/(?:Chrome|CriOS)\/([\d.]+)/', 'Chrome'],
        ['/Version\/([\d.]+).*Safari/', 'Safari'],
        ['/^curl\/([\d.]+)/', 'curl'],
        ['/^Wget\/([\d.]+)/', 'Wget'],
    ];
    foreach ($map as [$re, $name]) {
        if (preg_match($re, $ua, $m) === 1) {
            return $name . ' ' . $m[1];
        }
    }
    return null;
}

function parse_os(?string $ua): ?string
{
    if ($ua === null) {
        return null;
    }
    if (preg_match('/Windows NT ([\d.]+)/', $ua, $m) === 1) {
        return 'Windows ' . ($m[1] === '10.0' ? '10/11' : $m[1]);
    }
    if (preg_match('/Android ([\d.]+)/', $ua, $m) === 1) {
        return 'Android ' . $m[1];
    }
    if (preg_match('/(?:iPhone|iPad).*OS ([\d_]+)/', $ua, $m) === 1) {
        return 'iOS ' . str_replace('_', '.', $m[1]);
    }
    if (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m) === 1) {
        return 'macOS ' . str_replace('_', '.', $m[1]);
    }
    if (strpos($ua, 'CrOS') !== false) {
        return 'ChromeOS';
    }
    if (strpos($ua, 'Linux') !== false || stripos($ua, 'curl') === 0) {
        return 'Linux';
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* Recolección de datos                                                */
/* ------------------------------------------------------------------ */

$ec2 = imds_get();
$onEc2 = !empty($ec2['ok']);

$ua = server_str('HTTP_USER_AGENT');
if ($ua !== null && strlen($ua) > 1000) {
    $ua = substr($ua, 0, 1000);
}
$accept = server_str('HTTP_ACCEPT_LANGUAGE');
$clientLang = $accept !== null ? trim(explode(',', $accept)[0]) : null;
if ($clientLang !== null && strlen($clientLang) > 35) {
    $clientLang = substr($clientLang, 0, 35);
}
$xff = server_str('HTTP_X_FORWARDED_FOR');
if ($xff !== null && strlen($xff) > 300) {
    $xff = substr($xff, 0, 300);
}

$host = @gethostname();
$host = (is_string($host) && $host !== '') ? $host : null;

$serverIp = server_str('SERVER_ADDR');
if ($serverIp === null && $onEc2) {
    $serverIp = $ec2['private_ip'] ?? null;
}
if ($serverIp === null && $host !== null) {
    $g = @gethostbyname($host);
    $serverIp = ($g !== $host && $g !== '') ? $g : null;
}

$now = time();
$tzName = date_default_timezone_get();
$utcOffset = 'UTC' . date('P', $now);

$osRelease = null;
$osr = read_file('/etc/os-release');
if ($osr !== null && preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $osr, $m) === 1) {
    $osRelease = $m[1];
}

$uptimeSec = null;
$up = read_file('/proc/uptime');
if ($up !== null && preg_match('/^([\d.]+)/', $up, $m) === 1) {
    $uptimeSec = (float)$m[1];
}

$load = null;
if (function_exists('sys_getloadavg')) {
    $l = @sys_getloadavg();
    if (is_array($l) && count($l) === 3) {
        $load = array_map(static fn($x) => round((float)$x, 2), $l);
    }
}

$mem = null;
$mi = read_file('/proc/meminfo');
if ($mi !== null
    && preg_match('/^MemTotal:\s+(\d+) kB/m', $mi, $a) === 1
    && preg_match('/^MemAvailable:\s+(\d+) kB/m', $mi, $b) === 1
    && (int)$a[1] > 0) {
    $total = (float)$a[1] * 1024;
    $used = $total - (float)$b[1] * 1024;
    $mem = ['total' => $total, 'used' => $used, 'pct' => (int)round($used / $total * 100)];
}

$disk = null;
$dt = @disk_total_space('/');
$df = @disk_free_space('/');
if (is_float($dt) && is_float($df) && $dt > 0) {
    $du = $dt - $df;
    $disk = ['total' => $dt, 'used' => $du, 'pct' => (int)round($du / $dt * 100)];
}

$data = [
    'client' => [
        'ip' => server_str('REMOTE_ADDR'),
        'x_forwarded_for' => $xff,
        'user_agent' => $ua,
        'browser' => parse_browser($ua),
        'os' => parse_os($ua),
        'language' => $clientLang,
        'protocol' => server_str('SERVER_PROTOCOL'),
        'method' => server_str('REQUEST_METHOD'),
    ],
    'server' => [
        'hostname' => $host,
        'ip' => $serverIp,
        'datetime' => date('Y-m-d H:i:s', $now),
        'iso8601' => date('c', $now),
        'timestamp' => $now,
        'timezone' => $tzName,
        'utc_offset' => $utcOffset,
    ],
    'instance' => [
        'on_ec2' => $onEc2,
        'instance_id' => $ec2['instance_id'] ?? null,
        'availability_zone' => $ec2['az'] ?? null,
        'region' => $ec2['region'] ?? null,
        'private_ip' => $ec2['private_ip'] ?? null,
        'instance_type' => $ec2['instance_type'] ?? null,
        'ami_id' => $ec2['ami_id'] ?? null,
        'public_ip' => $ec2['public_ip'] ?? null,
        'public_hostname' => $ec2['public_hostname'] ?? null,
        'mac' => $ec2['mac'] ?? null,
        'security_groups' => $ec2['security_groups'] ?? null,
        'imds_version' => $ec2['imds_version'] ?? null,
    ],
    'system' => [
        'software' => server_str('SERVER_SOFTWARE'),
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'os' => $osRelease,
        'kernel' => php_uname('s') . ' ' . php_uname('r'),
        'uptime_seconds' => $uptimeSec,
        'uptime' => $uptimeSec !== null ? fmt_uptime($uptimeSec) : null,
        'load_average' => $load,
        'memory' => $mem !== null ? ['total_bytes' => (int)$mem['total'], 'used_bytes' => (int)$mem['used'], 'percent' => $mem['pct']] : null,
        'disk_root' => $disk !== null ? ['total_bytes' => (int)$disk['total'], 'used_bytes' => (int)$disk['used'], 'percent' => $disk['pct']] : null,
    ],
    'student' => STUDENT_NAME,
];

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ), "\n";
    exit;
}

/* ------------------------------------------------------------------ */
/* Render HTML                                                         */
/* ------------------------------------------------------------------ */

function na(): string
{
    return '<span class="na">' . h(t('na')) . '</span>';
}

/** Fila: etiqueta + valor (ya escapado o null). */
function row(string $label, ?string $html, bool $copy = false, bool $mono = true, ?string $raw = null): string
{
    $val = $html ?? na();
    $btn = '';
    if ($copy && $raw !== null) {
        $btn = ' <button type="button" class="copy" data-copy="' . h($raw) . '" data-ok="' . h(t('copied')) . '">' . h(t('copy')) . '</button>';
    }
    return '<div class="row"><dt>' . h($label) . '</dt><dd class="' . ($mono ? 'mono' : '') . '">' . $val . $btn . '</dd></div>';
}

function txt(?string $v): ?string
{
    return $v === null ? null : h($v);
}

function bar(int $pct, string $label): string
{
    $pct = max(0, min(100, $pct));
    $cls = $pct >= 90 ? 'bar crit' : ($pct >= 75 ? 'bar warn' : 'bar');
    return '<div class="' . $cls . '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct . '"><span style="width:' . $pct . '%"></span></div>'
        . '<div class="barlabel">' . h($label) . '</div>';
}

$c = $data['client'];
$s = $data['server'];
$i = $data['instance'];
$y = $data['system'];

$otherLang = t('toggle_lang');
$jsonUrl = '?format=json&lang=' . $lang;
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?= h(t('title')) ?></title>
<style>
:root{
  --bg:#f4f6fa; --card:#ffffff; --text:#1c2430; --muted:#5b6778; --border:#dde3ec;
  --accent:#e8710a; --accent-2:#1a6fd1; --ok:#1c8a4a; --warn:#b7791f; --crit:#c53030;
  --track:#e6ebf2; --code:#f0f3f8; --shadow:0 1px 2px rgba(20,30,50,.06),0 4px 14px rgba(20,30,50,.05);
}
@media (prefers-color-scheme:dark){
  :root{
    --bg:#0f141b; --card:#171e28; --text:#e6ebf2; --muted:#9aa8ba; --border:#2a3442;
    --accent:#ff9b3d; --accent-2:#6cb0ff; --ok:#4cc27e; --warn:#e3b341; --crit:#f2777a;
    --track:#273140; --code:#202a37; --shadow:none;
  }
}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--text);
  font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
.wrap{max-width:1100px;margin:0 auto;padding:20px 16px 40px}
header{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
h1{font-size:1.5rem;line-height:1.2;margin:0}
.sub{color:var(--muted);margin:2px 0 0;font-size:.95rem}
.tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.tools a,.badge{font-size:.85rem;padding:5px 12px;border-radius:999px;border:1px solid var(--border);
  background:var(--card);color:var(--text);text-decoration:none;white-space:nowrap}
.tools a:hover{border-color:var(--accent-2);color:var(--accent-2)}
.badge{font-weight:600}
.badge.on{color:var(--ok);border-color:var(--ok)}
.badge.off{color:var(--warn);border-color:var(--warn)}
.notice{background:var(--card);border:1px solid var(--warn);border-left-width:4px;border-radius:10px;
  padding:10px 14px;margin-bottom:16px;font-size:.92rem;color:var(--text)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));gap:16px}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 18px;
  box-shadow:var(--shadow);min-width:0}
.card h2{font-size:1.05rem;margin:0 0 10px;padding-bottom:8px;border-bottom:2px solid var(--accent);
  display:flex;align-items:center;gap:8px}
.card.ec2 h2{border-color:var(--accent-2)}
.card.sys h2{border-color:var(--ok)}
dl{margin:0}
.row{display:grid;grid-template-columns:11rem minmax(0,1fr);gap:4px 12px;padding:7px 0;border-bottom:1px dashed var(--border)}
.row:last-child{border-bottom:0}
dt{color:var(--muted);font-size:.9rem}
dd{margin:0;font-size:.92rem;overflow-wrap:anywhere;word-break:break-word;min-width:0}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;font-size:.9rem}
.na{color:var(--muted);font-style:italic;font-family:inherit}
.ua{display:block;background:var(--code);padding:6px 8px;border-radius:6px;font-size:.8rem;line-height:1.4}
.tag{display:inline-block;background:var(--code);border:1px solid var(--border);border-radius:6px;padding:0 7px;margin:0 4px 4px 0;font-size:.85rem}
.clock{font-size:1.15rem;font-weight:700;color:var(--accent-2)}
.copy{font:inherit;font-size:.75rem;margin-left:6px;padding:1px 9px;border-radius:6px;cursor:pointer;
  border:1px solid var(--border);background:var(--code);color:var(--text)}
.copy:hover{border-color:var(--accent-2);color:var(--accent-2)}
.bar{height:8px;border-radius:99px;background:var(--track);overflow:hidden;margin-top:4px}
.bar span{display:block;height:100%;background:var(--ok);border-radius:99px}
.bar.warn span{background:var(--warn)}
.bar.crit span{background:var(--crit)}
.barlabel{font-size:.8rem;color:var(--muted);margin-top:3px}
footer{margin-top:22px;text-align:center;color:var(--muted);font-size:.88rem}
footer strong{color:var(--accent);font-weight:700}
@media (max-width:560px){
  .row{grid-template-columns:1fr;gap:0}
  h1{font-size:1.3rem}
  .card{padding:14px}
}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <h1><?= h(t('title')) ?></h1>
      <p class="sub"><?= h(t('subtitle')) ?></p>
    </div>
    <div class="tools">
      <span class="badge <?= $onEc2 ? 'on' : 'off' ?>"><?= h($onEc2 ? t('badge_ec2') : t('badge_local')) ?></span>
      <a href="<?= h($jsonUrl) ?>"><?= h(t('json_link')) ?></a>
      <a href="?lang=<?= h($otherLang) ?>" hreflang="<?= h($otherLang) ?>"><?= h(t('toggle')) ?></a>
    </div>
  </header>

<?php if (!$onEc2): ?>
  <div class="notice" role="note"><?= h(t('not_ec2')) ?></div>
<?php endif; ?>

  <div class="grid">
    <section class="card">
      <h2><?= h(t('card_client')) ?></h2>
      <dl>
<?php
echo row(t('client_ip'), txt($c['ip']), false);
if ($c['x_forwarded_for'] !== null) {
    echo row(t('xff'), txt($c['x_forwarded_for']));
}
echo row(t('ua'), $c['user_agent'] !== null ? '<span class="ua">' . h($c['user_agent']) . '</span>' : null);
echo row(t('browser'), txt($c['browser']), false, false);
echo row(t('os'), txt($c['os']), false, false);
echo row(t('language'), txt($c['language']));
echo row(t('protocol'), txt($c['protocol']));
echo row(t('method'), txt($c['method']));
?>
      </dl>
    </section>

    <section class="card">
      <h2><?= h(t('card_server')) ?></h2>
      <dl>
<?php
echo row(t('hostname'), txt($s['hostname']));
echo row(t('server_ip'), txt($s['ip']));
echo row(t('datetime'), h($s['datetime']) . ' <span class="na">' . h($s['utc_offset']) . '</span>');
echo row(t('live_clock'), '<span class="clock" id="clock" aria-live="off">' . h($s['datetime']) . '</span>');
echo row(t('timezone'), h($s['timezone']) . ' (' . h($s['utc_offset']) . ')');
?>
      </dl>
    </section>

    <section class="card ec2">
      <h2><?= h(t('card_ec2')) ?></h2>
      <dl>
<?php
echo row(t('instance_id'), txt($i['instance_id']), true, true, $i['instance_id']);
echo row(t('az'), txt($i['availability_zone']));
echo row(t('region'), txt($i['region']));
echo row(t('private_ip'), txt($i['private_ip']));
echo row(t('instance_type'), txt($i['instance_type']));
echo row(t('ami'), txt($i['ami_id']));
echo row(t('public_ip'), txt($i['public_ip']), true, true, $i['public_ip']);
echo row(t('public_host'), txt($i['public_hostname']));
echo row(t('mac'), txt($i['mac']));
$sgHtml = null;
if (is_array($i['security_groups']) && $i['security_groups']) {
    $sgHtml = '';
    foreach ($i['security_groups'] as $g) {
        $sgHtml .= '<span class="tag">' . h($g) . '</span>';
    }
}
echo row(t('sgs'), $sgHtml);
?>
      </dl>
    </section>

    <section class="card sys">
      <h2><?= h(t('card_sys')) ?></h2>
      <dl>
<?php
echo row(t('software'), txt($y['software']));
echo row(t('php'), h($y['php_version']) . ' <span class="na">(' . h($y['php_sapi']) . ')</span>');
echo row(t('os_server'), txt($y['os']), false, false);
echo row(t('kernel'), txt($y['kernel']));
echo row(t('uptime'), txt($y['uptime']));
echo row(t('load'), $y['load_average'] !== null ? h(implode(' / ', array_map(static fn($x) => number_format((float)$x, 2, '.', ''), $y['load_average']))) : null);
echo row(t('memory'), $mem !== null
    ? h(fmt_bytes($mem['used'])) . ' ' . h(t('of')) . ' ' . h(fmt_bytes($mem['total'])) . bar($mem['pct'], $mem['pct'] . '% ' . t('used'))
    : null);
echo row(t('disk'), $disk !== null
    ? h(fmt_bytes($disk['used'])) . ' ' . h(t('of')) . ' ' . h(fmt_bytes($disk['total'])) . bar($disk['pct'], $disk['pct'] . '% ' . t('used'))
    : null);
?>
      </dl>
    </section>
  </div>

  <footer>
    <?= h(t('footer')) ?><br>
    <?= h(t('student')) ?>: <strong><?= h(STUDENT_NAME) ?></strong>
  </footer>
</div>
<script>
(function(){
  // Reloj en vivo sembrado con la hora del servidor
  var el=document.getElementById('clock');
  if(el){
    var base=<?= (int)$now ?>*1000, off=<?= (int)date('Z', $now) ?>*1000, t0=Date.now();
    var p=function(n){return n<10?'0'+n:''+n;};
    var tick=function(){
      var d=new Date(base+off+(Date.now()-t0));
      el.textContent=d.getUTCFullYear()+'-'+p(d.getUTCMonth()+1)+'-'+p(d.getUTCDate())+' '+
        p(d.getUTCHours())+':'+p(d.getUTCMinutes())+':'+p(d.getUTCSeconds());
    };
    tick(); setInterval(tick,1000);
  }
  // Botones de copiar (con respaldo para HTTP, donde navigator.clipboard no existe)
  var fallback=function(text){
    var ta=document.createElement('textarea');
    ta.value=text; ta.style.position='fixed'; ta.style.opacity='0';
    document.body.appendChild(ta); ta.select();
    try{document.execCommand('copy');}catch(e){}
    document.body.removeChild(ta);
  };
  document.querySelectorAll('button.copy').forEach(function(b){
    b.addEventListener('click',function(){
      var text=b.getAttribute('data-copy')||'', label=b.textContent;
      if(navigator.clipboard&&window.isSecureContext){
        navigator.clipboard.writeText(text).catch(function(){fallback(text);});
      }else{fallback(text);}
      b.textContent=b.getAttribute('data-ok');
      setTimeout(function(){b.textContent=label;},1500);
    });
  });
})();
</script>
</body>
</html>
