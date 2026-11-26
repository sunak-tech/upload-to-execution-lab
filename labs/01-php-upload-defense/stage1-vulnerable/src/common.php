<?php
declare(strict_types=1);

const UPLOAD_DIR = '/var/www/html/uploads';

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
function list_uploaded_files(): array
{
    if (!is_dir(UPLOAD_DIR)) {
        return [];
    }
    $entries = scandir(UPLOAD_DIR);
    if ($entries === false) {
        return [];
    }
    $files = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
            continue;
        }
        $fullPath = UPLOAD_DIR . '/' . $entry;
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

function render_stage1_main(string $lang, string $flashHtml = ''): void
{
    $isEn = ($lang === 'en');
    $files = list_uploaded_files();

    $title = $isEn
        ? 'Stage 1: Vulnerable Upload (No Validation)'
        : 'Stage 1: 脆弱な実装（検証なし・公開領域内保存）';

    ob_start();
    if ($flashHtml !== '') {
        echo $flashHtml;
    }
    ?>
    <section class="card stage-summary stage-danger">
      <div class="stage-badge danger"><?= $isEn ? 'STAGE 1 — VULNERABLE' : 'STAGE 1 — 脆弱な初期実装' ?></div>
      <h1><?= esc($title) ?></h1>
      <?php if ($isEn): ?>
        <p>
          Unrestricted upload turns directly into server-side script execution across <strong>Upload &rarr; Store &rarr; Serve &rarr; Execute</strong>.
        </p>
        <ul class="boundary-list">
          <li><strong>App Layer:</strong> Zero extension or content checks; keeps the untrusted client filename via <code>basename()</code>.</li>
          <li><strong>Storage Layer:</strong> Saves directly inside <code>DocumentRoot</code> at <code>/var/www/html/uploads/&lt;name&gt;</code>.</li>
          <li><strong>Web Server Layer:</strong> Apache maps <code>.php</code> and <code>.phtml</code> in <code>/uploads/</code> to the PHP handler.</li>
        </ul>
      <?php else: ?>
        <p>
          無検証のファイルが <strong>アップロード → 保存 → 配信 → 実行</strong> の連鎖でそのままサーバ側で動作します。
        </p>
        <ul class="boundary-list">
          <li><strong>アプリ層:</strong> 拡張子・内容ともに未検証で、<code>basename()</code> のみで元のファイル名を維持します。</li>
          <li><strong>保存層:</strong> <code>DocumentRoot</code> 内の <code>/var/www/html/uploads/&lt;name&gt;</code> に直接保存します。</li>
          <li><strong>Web サーバ層:</strong> <code>/uploads/</code> 配下でも <code>.php</code>・<code>.phtml</code> が PHP ハンドラで実行されます。</li>
        </ul>
      <?php endif; ?>
    </section>

    <div class="grid-two">
      <section class="card">
        <h2><?= $isEn ? '1. Upload File' : '1. ファイルアップロード' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Compare sample-valid.png and marker-basic.php.'
              : 'sample-valid.png と marker-basic.php の挙動を確認します。' ?>
        </p>
        <form action="/upload.php?lang=<?= esc($lang) ?>" method="post" enctype="multipart/form-data" class="upload-form">
          <label for="file-input" class="file-label">
            <?= $isEn ? 'Choose file:' : 'ファイルを選択:' ?>
          </label>
          <input id="file-input" type="file" name="file" required>
          <button type="submit" class="btn btn-primary">
            <?= $isEn ? 'Upload' : 'アップロード' ?>
          </button>
        </form>
      </section>

      <section class="card">
        <h2><?= $isEn ? '2. Reset State' : '2. 状態リセット' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Deletes all uploaded files in /var/www/html/uploads/.'
              : '/var/www/html/uploads/ 内のアップロード済みファイルを削除します。' ?>
        </p>
        <form action="/reset.php?lang=<?= esc($lang) ?>" method="post">
          <button type="submit" class="btn btn-secondary">
            <?= $isEn ? 'Reset Stage 1' : 'Stage 1 をリセット' ?>
          </button>
        </form>
      </section>
    </div>

    <section class="card">
      <h2><?= $isEn ? 'Uploaded Files (/uploads/)' : 'アップロード済みファイル一覧' ?></h2>
      <?php if ($files === []): ?>
        <p class="muted"><?= $isEn ? 'No uploaded files.' : 'アップロードされたファイルはありません。' ?></p>
      <?php else: ?>
        <table class="file-table">
          <thead>
            <tr>
              <th><?= $isEn ? 'Stored URL' : '保存先 URL' ?></th>
              <th><?= $isEn ? 'Size (bytes)' : 'バイト数' ?></th>
              <th><?= $isEn ? 'Updated (UTC)' : 'UTC 更新日時' ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $file): ?>
              <?php $url = '/uploads/' . rawurlencode($file['name']); ?>
              <tr>
                <td><a href="<?= esc($url) ?>" target="_blank" rel="noopener"><?= esc('/uploads/' . $file['name']) ?></a></td>
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
      background: #dc2626;
      border-color: #f87171;
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
    .stage-danger { border-left: 6px solid #dc2626; }
    .stage-badge {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.03em;
    }
    .stage-badge.danger { background: #fee2e2; color: #991b1b; }
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
    .btn-primary { background: #dc2626; color: #ffffff; }
    .btn-secondary { background: #374151; color: #ffffff; }
    .alert {
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.25rem;
      border: 1px solid transparent;
    }
    .alert-ok { background: #ecfdf5; border-color: #10b981; color: #065f46; }
    .alert-err { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
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
        <a class="stage-link active" href="http://127.0.0.1:8081/?lang=<?= esc($lang) ?>">
          Stage 1 :8081 <?= $isEn ? 'Vulnerable' : '脆弱' ?>
        </a>
        <a class="stage-link" href="http://127.0.0.1:8082/?lang=<?= esc($lang) ?>">
          Stage 2 :8082 <?= $isEn ? 'App-Only' : '単一層防御' ?>
        </a>
        <a class="stage-link" href="http://127.0.0.1:8083/?lang=<?= esc($lang) ?>">
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
