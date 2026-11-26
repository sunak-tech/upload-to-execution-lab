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
        . esc($isEn ? 'Back to Stage 1' : 'Stage 1 トップへ戻る') . '</a></p>'
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
        . esc($isEn
            ? 'No file payload found under form field "file" (multipart/form-data required). / フォーム項目 "file" が見つかりません。'
            : 'フォーム項目 "file" が送信されていません (No "file" field in multipart/form-data request)。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 1' : 'Stage 1 トップへ戻る') . '</a></p>'
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
        . esc(sprintf(
            'PHP upload transport error (code %d) / ファイル受信エラー (code %d)',
            $errCode,
            $errCode
        ))
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 1' : 'Stage 1 トップへ戻る') . '</a></p>'
    );
    exit;
}

// Stage 1 intentional weakness: basename() path sanitization ONLY — no extension or content checks.
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
        '<div class="alert alert-err"><strong>'
        . esc($isEn ? 'Rejected by filename sanitizer:' : 'ファイル名サニタイズにより拒否:')
        . '</strong> '
        . esc('Empty or reserved filename is not allowed. / 空または予約済みのファイル名は指定できません。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 1' : 'Stage 1 トップへ戻る') . '</a></p>'
    );
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
  <h2><?= $isEn ? 'Upload Accepted (Stage 1 — No Validation)' : 'アップロード成功（Stage 1 — 検証なし）' ?></h2>
  <p>
    <?= $isEn
        ? 'Saved directly inside DocumentRoot without extension or content checks:'
        : '拡張子やファイル内容の検証を行わず、DocumentRoot 配下へそのまま保存されました:' ?>
  </p>
  <p>
    <strong>URL:</strong>
    <a href="<?= esc($publicUrl) ?>" target="_blank" rel="noopener"><?= esc('/uploads/' . $safeName) ?></a>
  </p>
</div>
<p>
  <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
    <?= $isEn ? 'Back to Stage 1 Top' : 'Stage 1 トップへ戻る' ?>
  </a>
</p>
<?php
$body = (string) ob_get_clean();
render_layout($lang, $isEn ? 'Upload Accepted - Stage 1' : 'アップロード完了 - Stage 1', $body);
