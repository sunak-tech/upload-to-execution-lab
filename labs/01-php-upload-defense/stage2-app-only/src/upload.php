<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$lang = current_lang();
$isEn = ($lang === 'en');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    render_layout(
        $lang,
        $isEn ? 'Method Not Allowed' : '許可されていないメソッド',
        '<div class="alert alert-err">'
        . esc($isEn ? 'Use POST /upload.php to submit a file.' : 'POST /upload.php を使用してファイルを送信してください。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 2' : 'Stage 2 トップへ戻る') . '</a></p>'
    );
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    http_response_code(400);
    render_layout(
        $lang,
        $isEn ? 'Upload Rejected (400)' : 'アップロード拒否 (400)',
        '<div class="alert alert-err"><strong>'
        . esc($isEn ? 'Upload Error:' : 'アップロードエラー:')
        . '</strong> '
        . esc('No file payload found under form field "file". / フォーム項目 "file" が送信されていません。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 2' : 'Stage 2 トップへ戻る') . '</a></p>'
    );
    exit;
}

$errCode = (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
$tmpPath = (string) ($_FILES['file']['tmp_name'] ?? '');
$rawName = (string) ($_FILES['file']['name'] ?? '');

if ($errCode !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    render_layout(
        $lang,
        $isEn ? 'Upload Rejected (400)' : 'アップロード拒否 (400)',
        '<div class="alert alert-err"><strong>'
        . esc($isEn ? 'Upload Error:' : 'アップロードエラー:')
        . '</strong> '
        . esc(sprintf('PHP upload transport error (code %d) / ファイル受信エラー (code %d)', $errCode, $errCode))
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 2' : 'Stage 2 トップへ戻る') . '</a></p>'
    );
    exit;
}

$normalized = str_replace('\\', '/', $rawName);
$safeName = basename($normalized);

if (
    $safeName === ''
    || $safeName === '.'
    || $safeName === '..'
    || $safeName === '.gitkeep'
    || str_contains($safeName, "\0")
) {
    http_response_code(400);
    render_layout(
        $lang,
        $isEn ? 'Invalid Filename (400)' : '不正なファイル名 (400)',
        '<div class="alert alert-err">'
        . esc('Empty or reserved filename is not allowed. / 空または予約済みのファイル名は指定できません。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 2' : 'Stage 2 トップへ戻る') . '</a></p>'
    );
    exit;
}

// Stage 2 intentional flaw: naive denylist blocks ".php" ONLY, allowing ".phtml" and trusting other extensions!
$ext = strtolower((string) pathinfo($safeName, PATHINFO_EXTENSION));
if ($ext === 'php') {
    http_response_code(400);
    ob_start();
    ?>
    <div class="alert alert-err">
      <h2><?= $isEn ? 'Blocked by App Layer (HTTP 400)' : 'アプリケーション層でブロックされました (HTTP 400)' ?></h2>
      <p>
        <strong>EN:</strong> Stage 2 naive denylist rejected extension <code>.php</code> (<code>strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'php'</code>).<br>
        <strong>JA:</strong> Stage 2 の単純なブラックリスト判定により、拡張子 <code>.php</code> が拒否されました。
      </p>
    </div>
    <p>
      <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
        <?= $isEn ? 'Back to Stage 2 Top' : 'Stage 2 トップへ戻る' ?>
      </a>
    </p>
    <?php
    render_layout($lang, $isEn ? 'Blocked (.php Denylist)' : 'ブロック (.php 拒否リスト)', (string) ob_get_clean());
    exit;
}

$destPath = UPLOAD_DIR . '/' . $safeName;
if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    render_layout(
        $lang,
        $isEn ? 'Storage Failure' : '保存失敗',
        '<div class="alert alert-err">'
        . esc($isEn ? 'Failed to move uploaded file.' : 'ファイルの保存に失敗しました。')
        . '</div>'
    );
    exit;
}
@chmod($destPath, 0644);

$publicUrl = '/uploads/' . rawurlencode($safeName);
http_response_code(200);

ob_start();
?>
<div class="alert alert-ok">
  <h2><?= $isEn ? 'Upload Accepted (Passed Stage 2 .php Denylist)' : 'アップロード成功（Stage 2 の .php ブラックリストを通過）' ?></h2>
  <p>
    <?= $isEn
        ? 'Extension was not ".php", so Stage 2 stored the file directly inside DocumentRoot:'
        : '拡張子が ".php" ではないため、Stage 2 のチェックを通過して DocumentRoot 内に保存されました:' ?>
  </p>
  <p>
    <strong>URL:</strong>
    <a href="<?= esc($publicUrl) ?>" target="_blank" rel="noopener"><?= esc('/uploads/' . $safeName) ?></a>
  </p>
</div>
<p>
  <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
    <?= $isEn ? 'Back to Stage 2 Top' : 'Stage 2 トップへ戻る' ?>
  </a>
</p>
<?php
render_layout($lang, $isEn ? 'Upload Accepted - Stage 2' : 'アップロード完了 - Stage 2', (string) ob_get_clean());
