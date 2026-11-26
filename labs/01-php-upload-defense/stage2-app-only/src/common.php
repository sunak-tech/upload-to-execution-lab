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

function render_stage2_main(string $lang, string $flashHtml = ''): void
{
    $isEn = ($lang === 'en');
    $files = list_uploaded_files();

    $title = $isEn
        ? 'Stage 2: App-Only Naive Defense (.php Denylist)'
        : 'Stage 2: アプリ層のみの不十分な防御（.php ブラックリスト）';

    ob_start();
    if ($flashHtml !== '') {
        echo $flashHtml;
    }
    ?>
    <section class="card stage-summary stage-warn">
      <div class="stage-badge warn"><?= $isEn ? 'STAGE 2 — INSUFFICIENT APP-ONLY CHECK' : 'STAGE 2 — 不十分な単一層チェック' ?></div>
      <h1><?= esc($title) ?></h1>
      <?php if ($isEn): ?>
        <p>
          This stage adds a naive application-layer denylist that blocks <code>.php</code> only, leaving a critical trust-boundary gap between PHP validation and Apache handler configuration.
        </p>
        <ul class="boundary-list">
          <li><strong>App Layer Assumption Failure:</strong> Blocks <code>.php</code> via a single denylist rule, overlooking alternative extensions such as <code>.phtml</code>.</li>
          <li><strong>Why Client <code>Content-Type</code> Cannot Be Trusted:</strong> Request headers like <code>Content-Type: image/png</code> are supplied by the client (browser or <code>curl</code>) and can be forged trivially without changing file contents.</li>
          <li><strong>Storage &amp; Web Server Mismatch:</strong> Files still land inside <code>DocumentRoot</code> (<code>/var/www/html/uploads/</code>) where Apache's <code>&lt;FilesMatch "\.(php|phtml)$"&gt;</code> interprets <code>.phtml</code> as PHP!</li>
        </ul>
      <?php else: ?>
        <p>
          このステージではアプリ層で <code>.php</code> 拡張子のみを拒否するブラックリストを追加していますが、PHP 側の検証と Apache 側のハンドラ設定の「認識のズレ」により突破されます。
        </p>
        <ul class="boundary-list">
          <li><strong>アプリ層の思い込み（ブラックリストの限界）:</strong> <code>.php</code> のみを拒否し、<code>.phtml</code> などの別拡張子を見落としています。</li>
          <li><strong>クライアント送信 <code>Content-Type</code> が信頼できない理由:</strong> マルチパート送信時の <code>Content-Type: image/png</code> ヘッダはブラウザや <code>curl</code> 側で任意に偽装できるため、防御境界として機能しません。</li>
          <li><strong>ストレージ・Web サーバ層の不整合:</strong> 保存先が依然として <code>DocumentRoot</code> 内（<code>/var/www/html/uploads/</code>）であり、Apache の <code>&lt;FilesMatch "\.(php|phtml)$"&gt;</code> によって <code>.phtml</code> が PHP として動作します。</li>
        </ul>
      <?php endif; ?>
    </section>

    <div class="grid-two">
      <section class="card">
        <h2><?= $isEn ? '1. Upload a File' : '1. ファイルをアップロード' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Compare samples/marker-basic.php (blocked: 400) vs. samples/marker-bypass.phtml (accepted: 200 & interpreted!).'
              : 'samples/marker-basic.php（400 で拒否）と samples/marker-bypass.phtml（200 で通過し動作！）を比較してください。' ?>
        </p>
        <form action="/upload.php?lang=<?= esc($lang) ?>" method="post" enctype="multipart/form-data" class="upload-form">
          <label for="file-input" class="file-label">
            <?= $isEn ? 'Select file to upload:' : 'アップロードするファイルを選択:' ?>
          </label>
          <input id="file-input" type="file" name="file" required>
          <button type="submit" class="btn btn-primary">
            <?= $isEn ? 'Upload to Stage 2' : 'Stage 2 にアップロード' ?>
          </button>
        </form>
      </section>

      <section class="card">
        <h2><?= $isEn ? '2. Lab State Control (Reset)' : '2. ラボ状態のリセット' ?></h2>
        <p class="muted">
          <?= $isEn
              ? 'Removes all uploaded files in Stage 2 (/var/www/html/uploads/) while preserving .gitkeep.'
              : 'Stage 2 のアップロード済みファイルをすべて削除し、初期状態に戻します（.gitkeep は保持）。' ?>
        </p>
        <form action="/reset.php?lang=<?= esc($lang) ?>" method="post">
          <button type="submit" class="btn btn-secondary">
            <?= $isEn ? 'Reset Stage 2 Uploads' : 'Stage 2 のアップロードをリセット' ?>
          </button>
        </form>
      </section>
    </div>

    <section class="card">
      <h2><?= $isEn ? 'Uploaded Files in /uploads/' : '/uploads/ 内のアップロード済みファイル一覧' ?></h2>
      <?php if ($files === []): ?>
        <p class="muted"><?= $isEn ? 'No uploaded files currently stored.' : '現在アップロードされているファイルはありません。' ?></p>
      <?php else: ?>
        <table class="file-table">
          <thead>
            <tr>
              <th><?= $isEn ? 'Stored URL (Inside DocumentRoot)' : '保存先 URL（DocumentRoot 内）' ?></th>
              <th><?= $isEn ? 'Size (bytes)' : 'サイズ (bytes)' ?></th>
              <th><?= $isEn ? 'Modified (UTC)' : '更新日時 (UTC)' ?></th>
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
      background: #d97706;
      border-color: #fbbf24;
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
    .stage-warn { border-left: 6px solid #d97706; }
    .stage-badge {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.03em;
    }
    .stage-badge.warn { background: #fef3c7; color: #92400e; }
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
    .btn-primary { background: #d97706; color: #ffffff; }
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
        <a class="stage-link active" href="http://127.0.0.1:8082/?lang=<?= esc($lang) ?>">
          Stage 2 (:8082 <?= $isEn ? 'App-Only' : 'アプリ層のみ' ?>)
        </a>
        <a class="stage-link" href="http://127.0.0.1:8083/?lang=<?= esc($lang) ?>">
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
