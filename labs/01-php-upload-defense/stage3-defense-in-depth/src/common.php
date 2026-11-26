<?php
declare(strict_types=1);

const STORAGE_DIR = '/var/lab-storage/uploads';
const MAX_UPLOAD_BYTES = 2097152; // 2 MB
const STORED_FILE_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(png|jpg|jpeg|gif)$/';

function current_lang(): string
{
    $raw = $_GET['lang'] ?? 'ja';
    return ($raw === 'en') ? 'en' : 'ja';
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return array<int, array{name: string, size: int, mtime: int}>
 */
function list_stored_images(): array
{
    if (!is_dir(STORAGE_DIR)) {
        return [];
    }
    $entries = scandir(STORAGE_DIR);
    if ($entries === false) {
        return [];
    }
    $files = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
            continue;
        }
        if (preg_match(STORED_FILE_PATTERN, $entry) !== 1) {
            continue;
        }
        $fullPath = STORAGE_DIR . '/' . $entry;
        if (is_file($fullPath) && !is_link($fullPath)) {
            $files[] = [
                'name' => $entry,
                'size' => (int) (@filesize($fullPath) ?: 0),
                'mtime' => (int) (@filemtime($fullPath) ?: 0),
            ];
        }
    }
    usort($files, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    return $files;
}

function generate_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function render_stage3_main(string $lang, string $flashHtml = ''): void
{
    $isEn = ($lang === 'en');
    $files = list_stored_images();

    $title = $isEn
        ? 'Stage 3: Defense-in-Depth'
        : 'Stage 3: 多層防御（アプリ層・保存層・インフラ層の分離）';

    ob_start();
    if ($flashHtml !== '') {
        echo $flashHtml;
    }
    ?>
    <section class="card stage-summary stage-safe">
      <div class="stage-badge safe"><?= $isEn ? 'STAGE 3 — DEFENSE-IN-DEPTH' : 'STAGE 3 — 多層防御' ?></div>
      <h1><?= esc($title) ?></h1>
      <?php if ($isEn): ?>
        <p>
          Independent controls break every step of <strong>Upload &rarr; Store &rarr; Serve &rarr; Execute</strong> across app, storage, and web server layers.
        </p>
        <ul class="boundary-list">
          <li><strong>App Layer:</strong> Enforces extension allowlist (<code>.png</code>, <code>.jpg</code>, <code>.jpeg</code>, <code>.gif</code>), 2 MB cap, <code>finfo</code> MIME check, and <code>getimagesize()</code> header check.</li>
          <li><strong>Storage Layer:</strong> Replaces client filename with random <code>UUIDv4</code> and stores outside <code>DocumentRoot</code> in <code>/var/lab-storage/uploads/</code>.</li>
          <li><strong>Delivery Layer:</strong> <code>/image.php?file=&lt;uuid&gt;.&lt;ext&gt;</code> validates UUID format and sets <code>X-Content-Type-Options: nosniff</code> and <code>Content-Security-Policy: default-src 'none'</code>.</li>
          <li><strong>Infra Layer:</strong> Enforces <code>AllowOverride None</code>, disables PHP on <code>/uploads/</code>, and denies direct access with HTTP <code>403</code>.</li>
        </ul>
      <?php else: ?>
        <p>
          <strong>アップロード → 保存 → 配信 → 実行</strong> の全段階を、アプリ層・保存層・インフラ層の独立した防御で遮断します。
        </p>
        <ul class="boundary-list">
          <li><strong>アプリ層:</strong> 拡張子許可リスト（<code>.png</code>・<code>.jpg</code>・<code>.jpeg</code>・<code>.gif</code>）、2 MB 上限、<code>finfo</code> と <code>getimagesize()</code> で実データを検証します。</li>
          <li><strong>保存層:</strong> 元のファイル名を破棄して <code>UUIDv4</code> に置換し、<code>DocumentRoot</code> 外の <code>/var/lab-storage/uploads/</code> に保存します。</li>
          <li><strong>配信層:</strong> <code>/image.php?file=&lt;uuid&gt;.&lt;ext&gt;</code> で形式検証し、<code>nosniff</code> と <code>Content-Security-Policy: default-src 'none'</code> を付与します。</li>
          <li><strong>インフラ層:</strong> <code>AllowOverride None</code>、PHP エンジン無効化、<code>Require all denied</code> により <code>/uploads/</code> への直接アクセスを HTTP <code>403</code> で遮断します。</li>
        </ul>
      <?php endif; ?>
    </section>

    <div class="grid-two">
      <section class="card">
        <h2><?= $isEn ? '1. Upload Image' : '1. 画像アップロード' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Try sample-valid.png (200) vs. marker-basic.php, marker-bypass.phtml, or spoofed .png (400).'
              : 'sample-valid.png（200 許可）と marker-basic.php / marker-bypass.phtml / 偽装 .png（400 拒否）を比較します。' ?>
        </p>
        <form action="/upload.php?lang=<?= esc($lang) ?>" method="post" enctype="multipart/form-data" class="upload-form">
          <label for="file-input" class="file-label">
            <?= $isEn ? 'Choose image:' : '画像ファイルを選択:' ?>
          </label>
          <input id="file-input" type="file" name="file" required>
          <button type="submit" class="btn btn-primary">
            <?= $isEn ? 'Upload' : 'アップロード' ?>
          </button>
        </form>
      </section>

      <section class="card">
        <h2><?= $isEn ? '2. Infra Guard & Reset' : '2. インフラ層ガード確認・リセット' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Confirm that pre-seeded /uploads/infra-guard-check.phtml is blocked with HTTP 403:'
              : '事前配置された /uploads/infra-guard-check.phtml が HTTP 403 で遮断されることを確認します:' ?>
        </p>
        <p>
          <a class="btn btn-outline" href="/uploads/infra-guard-check.phtml" target="_blank" rel="noopener">
            <?= $isEn ? 'Check /uploads/infra-guard-check.phtml (403)' : '/uploads/infra-guard-check.phtml の 403 遮断を確認' ?>
          </a>
        </p>
        <hr class="divider">
        <form action="/reset.php?lang=<?= esc($lang) ?>" method="post">
          <button type="submit" class="btn btn-secondary">
            <?= $isEn ? 'Reset Stage 3' : 'Stage 3 をリセット' ?>
          </button>
        </form>
      </section>
    </div>

    <section class="card">
      <h2><?= $isEn ? 'Stored Images (Served via /image.php)' : '保存済み画像一覧' ?></h2>
      <?php if ($files === []): ?>
        <p class="muted"><?= $isEn ? 'No uploaded images.' : '保存されている画像はありません。' ?></p>
      <?php else: ?>
        <table class="file-table">
          <thead>
            <tr>
              <th><?= $isEn ? 'Delivery URL' : '配信 URL' ?></th>
              <th><?= $isEn ? 'Size (bytes)' : 'バイト数' ?></th>
              <th><?= $isEn ? 'Updated (UTC)' : 'UTC 更新日時' ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $file): ?>
              <?php $url = '/image.php?file=' . rawurlencode($file['name']); ?>
              <tr>
                <td><a href="<?= esc($url) ?>" target="_blank" rel="noopener"><?= esc('/image.php?file=' . $file['name']) ?></a></td>
                <td><?= esc((string) $file['size']) ?></td>
                <td><?= esc(gmdate('Y-m-d H:i:s', $file['mtime'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
    <?php
    $content = (string) ob_get_clean();
    render_layout($lang, $title, $content);
}

function render_layout(string $lang, string $pageTitle, string $bodyContent): void
{
    $isEn = ($lang === 'en');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    ?>
<!DOCTYPE html>
<html lang="<?= esc($lang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> | Lab 01</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Segoe UI', Roboto, 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif;
      background: #f3f4f6;
      color: #111827;
      line-height: 1.6;
    }
    .safety-banner {
      background: #991b1b;
      color: #ffffff;
      padding: 0.7rem 1.25rem;
      font-weight: 700;
      text-align: center;
      border-bottom: 3px solid #ef4444;
      font-size: 0.95rem;
    }
    .topbar {
      background: #1f2937;
      color: #f9fafb;
      padding: 0.7rem 1.25rem;
    }
    .topbar-inner {
      max-width: 980px;
      margin: 0 auto;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
    }
    .stage-nav, .lang-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    .stage-link, .lang-link {
      color: #e5e7eb;
      text-decoration: none;
      padding: 0.35rem 0.7rem;
      border-radius: 6px;
      font-size: 0.88rem;
      border: 1px solid #374151;
      background: #111827;
    }
    .stage-link.active, .lang-link.active {
      background: #059669;
      border-color: #34d399;
      color: #ffffff;
      font-weight: 700;
    }
    .container {
      max-width: 980px;
      margin: 1.5rem auto 3rem;
      padding: 0 1rem;
    }
    .card {
      background: #ffffff;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 1.25rem;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }
    .stage-safe { border-left: 6px solid #059669; }
    .stage-badge {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.03em;
    }
    .stage-badge.safe { background: #d1fae5; color: #065f46; }
    h1 { margin: 0.5rem 0 0.75rem; font-size: 1.45rem; }
    h2 { margin: 0 0 0.6rem; font-size: 1.15rem; }
    .muted { color: #4b5563; font-size: 0.92rem; margin-top: 0; }
    .boundary-list { padding-left: 1.25rem; margin: 0.5rem 0 0; }
    .boundary-list li { margin-bottom: 0.35rem; }
    .grid-two {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.25rem;
    }
    .upload-form { display: flex; flex-direction: column; gap: 0.75rem; }
    .file-label { font-weight: 600; font-size: 0.92rem; }
    input[type="file"] {
      padding: 0.5rem;
      border: 1px solid #9ca3af;
      border-radius: 6px;
      background: #f9fafb;
    }
    .btn {
      display: inline-block;
      padding: 0.55rem 1rem;
      border-radius: 6px;
      border: none;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      text-decoration: none;
      text-align: center;
    }
    .btn-primary { background: #059669; color: #ffffff; }
    .btn-secondary { background: #374151; color: #ffffff; }
    .btn-outline {
      background: #f9fafb;
      color: #1f2937;
      border: 1px solid #6b7280;
      font-size: 0.88rem;
    }
    .divider { border: 0; border-top: 1px solid #e5e7eb; margin: 1rem 0; }
    .alert {
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.25rem;
      border: 1px solid transparent;
    }
    .alert-ok { background: #ecfdf5; border-color: #10b981; color: #065f46; }
    .alert-err { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
    .preview-img {
      display: block;
      margin-top: 0.75rem;
      max-width: 180px;
      border: 1px solid #9ca3af;
      border-radius: 6px;
      background: #ffffff;
      padding: 4px;
    }
    .file-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
    .file-table th, .file-table td {
      text-align: left;
      padding: 0.6rem 0.75rem;
      border-bottom: 1px solid #e5e7eb;
    }
    .file-table th { background: #f9fafb; color: #374151; }
    code {
      background: #f3f4f6;
      padding: 0.12rem 0.38rem;
      border-radius: 4px;
      font-family: Consolas, Monaco, monospace;
      font-size: 0.9em;
    }
  </style>
</head>
<body>
  <div class="safety-banner" role="alert">
    <?php if ($isEn): ?>
      WARNING: FOR LOCAL EDUCATIONAL USE ONLY - DO NOT DEPLOY PUBLICLY
    <?php else: ?>
      警告: ローカル学習専用・公開サーバへのデプロイ厳禁
    <?php endif; ?>
  </div>

  <header class="topbar">
    <div class="topbar-inner">
      <nav class="stage-nav" aria-label="Stage Switcher">
        <a class="stage-link" href="http://127.0.0.1:8081/?lang=<?= esc($lang) ?>">
          Stage 1 :8081 <?= $isEn ? 'Vulnerable' : '脆弱' ?>
        </a>
        <a class="stage-link" href="http://127.0.0.1:8082/?lang=<?= esc($lang) ?>">
          Stage 2 :8082 <?= $isEn ? 'App-Only' : '単一層防御' ?>
        </a>
        <a class="stage-link active" href="http://127.0.0.1:8083/?lang=<?= esc($lang) ?>">
          Stage 3 :8083 <?= $isEn ? 'Defense-in-Depth' : '多層防御' ?>
        </a>
      </nav>
      <nav class="lang-nav" aria-label="Language Switcher">
        <a class="lang-link <?= !$isEn ? 'active' : '' ?>" href="/?lang=ja">日本語</a>
        <a class="lang-link <?= $isEn ? 'active' : '' ?>" href="/?lang=en">English</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <?= $bodyContent ?>
  </main>
</body>
</html>
    <?php
}
