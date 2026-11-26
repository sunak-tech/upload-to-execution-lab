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
        ? 'Stage 3: Defense-in-Depth (App + Storage + Infra Layers)'
        : 'Stage 3: 多層防御（アプリ層・ストレージ層・インフラ層の分離）';

    ob_start();
    if ($flashHtml !== '') {
        echo $flashHtml;
    }
    ?>
    <section class="card stage-summary stage-safe">
      <div class="stage-badge safe"><?= $isEn ? 'STAGE 3 — MULTI-LAYER DEFENSE-IN-DEPTH' : 'STAGE 3 — 多層防御モデル' ?></div>
      <h1><?= esc($title) ?></h1>
      <?php if ($isEn): ?>
        <p>
          Stage 3 breaks every link of the <strong>Upload &rarr; Store &rarr; Serve &rarr; Interpret</strong> chain using independent controls across application, filesystem, and web server layers:
        </p>
        <ul class="boundary-list">
          <li><strong>App Layer Validation:</strong> Strict allowlist (<code>.png</code>, <code>.jpg</code>, <code>.jpeg</code>, <code>.gif</code>), 2 MB size cap, server-side <code>finfo(FILEINFO_MIME_TYPE)</code> magic-byte check, and <code>getimagesize()</code> structural image parsing.</li>
          <li><strong>Storage Isolation:</strong> Discards the client filename in favor of a random <code>UUIDv4</code> name and writes outside <code>DocumentRoot</code> (<code>/var/lab-storage/uploads/</code>).</li>
          <li><strong>Safe Delivery Endpoint:</strong> <code>/image.php?file=&lt;uuid&gt;.&lt;ext&gt;</code> validates the UUID pattern and emits <code>X-Content-Type-Options: nosniff</code>, <code>Content-Security-Policy: default-src 'none'</code>, and <code>X-Frame-Options: DENY</code>.</li>
          <li><strong>Apache / Infra Hardening:</strong> Enforces <code>AllowOverride None</code> (ignoring <code>.htaccess</code>), disables the PHP engine under <code>/var/www/html/uploads</code>, and blocks direct access (HTTP <code>403</code>).</li>
        </ul>
      <?php else: ?>
        <p>
          Stage 3 では <strong>Upload &rarr; Store &rarr; Serve &rarr; Interpret</strong> の各境界に対して、アプリ層・ストレージ層・インフラ層で独立した防御を重ねています。
        </p>
        <ul class="boundary-list">
          <li><strong>アプリケーション層の厳格検証:</strong> 拡張子ホワイトリスト（<code>.png</code>, <code>.jpg</code>, <code>.jpeg</code>, <code>.gif</code>）、2 MB 上限、サーバ側 <code>finfo(FILEINFO_MIME_TYPE)</code> と <code>getimagesize()</code> による実データ検証を実施します。</li>
          <li><strong>ストレージ分離:</strong> クライアント指定のファイル名を破棄して <code>UUIDv4</code> に置換し、<code>DocumentRoot</code> 外の <code>/var/lab-storage/uploads/</code> に保存します。</li>
          <li><strong>安全な配信エンドポイント:</strong> <code>/image.php?file=&lt;uuid&gt;.&lt;ext&gt;</code> で正規表現検証を行い、<code>X-Content-Type-Options: nosniff</code>・<code>Content-Security-Policy: default-src 'none'</code>・<code>X-Frame-Options: DENY</code> を付与して配信します。</li>
          <li><strong>Apache / インフラ層の防御:</strong> <code>AllowOverride None</code>（<code>.htaccess</code> 無効化）に加え、万一公開ディレクトリ内の <code>/uploads/</code> にファイルが存在しても PHP エンジン無効化と <code>Require all denied</code>（HTTP <code>403</code>）で遮断します。</li>
        </ul>
      <?php endif; ?>
    </section>

    <div class="grid-two">
      <section class="card">
        <h2><?= $isEn ? '1. Upload an Image (Protected)' : '1. ファイルをアップロード（多層防御）' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Try sample-valid.png (accepted: 200) vs. marker-basic.php, marker-bypass.phtml, or disguised PHP-in-.png (rejected: 400).'
              : 'sample-valid.png（200 で許可）と、marker-basic.php / marker-bypass.phtml / 偽装 .png（400 で拒否）を試してください。' ?>
        </p>
        <form action="/upload.php?lang=<?= esc($lang) ?>" method="post" enctype="multipart/form-data" class="upload-form">
          <label for="file-input" class="file-label">
            <?= $isEn ? 'Select image file:' : 'アップロードするファイルを選択:' ?>
          </label>
          <input id="file-input" type="file" name="file" required>
          <button type="submit" class="btn btn-primary">
            <?= $isEn ? 'Upload to Stage 3' : 'Stage 3 にアップロード' ?>
          </button>
        </form>
      </section>

      <section class="card">
        <h2><?= $isEn ? '2. Infra Guard & Reset' : '2. インフラ層ガード確認とリセット' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Verify that pre-seeded /uploads/infra-guard-check.phtml inside DocumentRoot is blocked by Apache (HTTP 403) and never runs:'
              : 'DocumentRoot 内に事前配置された検証用フィクスチャ /uploads/infra-guard-check.phtml が Apache により HTTP 403 で遮断され、動作しないことを確認できます:' ?>
        </p>
        <p>
          <a class="btn btn-outline" href="/uploads/infra-guard-check.phtml" target="_blank" rel="noopener">
            <?= $isEn ? 'Test /uploads/infra-guard-check.phtml (Expect 403)' : '/uploads/infra-guard-check.phtml を確認 (HTTP 403)' ?>
          </a>
        </p>
        <hr class="divider">
        <form action="/reset.php?lang=<?= esc($lang) ?>" method="post">
          <button type="submit" class="btn btn-secondary">
            <?= $isEn ? 'Reset Stage 3 Uploads' : 'Stage 3 のアップロードをリセット' ?>
          </button>
        </form>
      </section>
    </div>

    <section class="card">
      <h2><?= $isEn ? 'Stored Images (Outside DocumentRoot via /image.php)' : '保存済み画像一覧（DocumentRoot 外・/image.php 経由配信）' ?></h2>
      <?php if ($files === []): ?>
        <p class="muted"><?= $isEn ? 'No uploaded images currently stored.' : '現在保存されているアップロード画像はありません。' ?></p>
      <?php else: ?>
        <table class="file-table">
          <thead>
            <tr>
              <th><?= $isEn ? 'Safe Delivery URL (UUIDv4)' : '安全な配信 URL (UUIDv4)' ?></th>
              <th><?= $isEn ? 'Size (bytes)' : 'サイズ (bytes)' ?></th>
              <th><?= $isEn ? 'Modified (UTC)' : '更新日時 (UTC)' ?></th>
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
  <title><?= esc($pageTitle) ?> | Upload-to-Execution Defense Lab</title>
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
      padding: 0.75rem 1.25rem;
      font-weight: 700;
      text-align: center;
      border-bottom: 3px solid #ef4444;
      font-size: 0.95rem;
    }
    .topbar {
      background: #1f2937;
      color: #f9fafb;
      padding: 0.75rem 1.25rem;
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
      WARNING: FOR LOCAL EDUCATIONAL USE ONLY - DO NOT DEPLOY PUBLICLY (127.0.0.1 Isolated Container Lab)
    <?php else: ?>
      警告: ローカル学習専用・公開サーバへのデプロイ厳禁（127.0.0.1 ローカル分離ネットワーク専用ラボ）
    <?php endif; ?>
  </div>

  <header class="topbar">
    <div class="topbar-inner">
      <nav class="stage-nav" aria-label="Stage Switcher">
        <a class="stage-link" href="http://127.0.0.1:8081/?lang=<?= esc($lang) ?>">
          Stage 1 (:8081 <?= $isEn ? 'Vulnerable' : '脆弱' ?>)
        </a>
        <a class="stage-link" href="http://127.0.0.1:8082/?lang=<?= esc($lang) ?>">
          Stage 2 (:8082 <?= $isEn ? 'App-Only' : 'アプリ層のみ' ?>)
        </a>
        <a class="stage-link active" href="http://127.0.0.1:8083/?lang=<?= esc($lang) ?>">
          Stage 3 (:8083 <?= $isEn ? 'Defense-in-Depth' : '多層防御' ?>)
        </a>
      </nav>
      <nav class="lang-nav" aria-label="Language Switcher">
        <a class="lang-link <?= !$isEn ? 'active' : '' ?>" href="/?lang=ja">日本語 (JA)</a>
        <a class="lang-link <?= $isEn ? 'active' : '' ?>" href="/?lang=en">English (EN)</a>
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
