<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$marker = 'LAB_EXECUTION_MARKER_STAGE1';
$phpVersion = PHP_VERSION;
$processUser = 'unknown';
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $pw = posix_getpwuid(posix_geteuid());
    if (is_array($pw) && isset($pw['name'])) {
        $processUser = (string) $pw['name'];
    }
} elseif (function_exists('get_current_user')) {
    $processUser = (string) get_current_user();
}
$hostName = (string) (gethostname() ?: 'unknown');
$utcTime = gmdate('c');

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Harmless Marker Output - Stage 1</title>
  <style>
    body { font-family: sans-serif; background: #fff8f8; color: #1f2937; margin: 2rem; line-height: 1.6; }
    .box { max-width: 680px; border: 2px solid #dc2626; border-radius: 8px; background: #ffffff; padding: 1.5rem; }
    .badge { display: inline-block; background: #dc2626; color: #ffffff; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: bold; font-size: 0.85rem; }
    code { background: #f3f4f6; padding: 0.15rem 0.4rem; border-radius: 4px; }
    dl { display: grid; grid-template-columns: 180px 1fr; gap: 0.5rem 1rem; margin-top: 1rem; }
    dt { font-weight: bold; color: #4b5563; }
    dd { margin: 0; font-family: monospace; }
  </style>
</head>
<body>
  <div class="box">
    <span class="badge">HARMLESS LAB MARKER</span>
    <h1><?= $esc($marker) ?></h1>
    <p>
      Server-side PHP interpretation occurred inside the upload directory.<br>
      アップロード先ディレクトリ内でサーバサイド PHP 解釈が発生しました（無害な固定情報のみを表示しています）。
    </p>
    <dl>
      <dt>Marker ID</dt>
      <dd><?= $esc($marker) ?></dd>
      <dt>PHP Version</dt>
      <dd><?= $esc($phpVersion) ?></dd>
      <dt>Process User</dt>
      <dd><?= $esc($processUser) ?></dd>
      <dt>Container Host</dt>
      <dd><?= $esc($hostName) ?></dd>
      <dt>Timestamp (UTC)</dt>
      <dd><?= $esc($utcTime) ?></dd>
    </dl>
  </div>
</body>
</html>
