<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$lang = current_lang();
$isEn = ($lang === 'en');

function reject_upload(string $lang, string $layerEn, string $layerJa, string $detailEn, string $detailJa): never
{
    $isEn = ($lang === 'en');
    http_response_code(400);
    ob_start();
    ?>
    <div class="alert alert-err">
      <h2><?= $isEn ? 'Upload Blocked (400)' : 'アップロード拒否 (400)' ?></h2>
      <p>
        <strong><?= esc($isEn ? $layerEn : $layerJa) ?>:</strong>
        <?= esc($isEn ? $detailEn : $detailJa) ?>
      </p>
    </div>
    <p>
      <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
        <?= $isEn ? 'Back to Stage 3' : 'Stage 3 へ戻る' ?>
      </a>
    </p>
    <?php
    render_layout($lang, $isEn ? 'Upload Blocked - Stage 3' : 'アップロード拒否 - Stage 3', (string) ob_get_clean());
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    render_layout(
        $lang,
        $isEn ? 'Method Not Allowed' : '許可されていないメソッド',
        '<div class="alert alert-err">'
        . esc($isEn ? 'Use POST /upload.php to upload a file.' : 'POST /upload.php から送信してください。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 3' : 'Stage 3 へ戻る') . '</a></p>'
    );
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    reject_upload(
        $lang,
        'Request Check',
        'リクエスト検証',
        'Missing "file" field in multipart/form-data request.',
        'フォーム項目 "file" が指定されていません。'
    );
}

$errCode = (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
    reject_upload(
        $lang,
        'App Layer — Size Limit',
        'アプリ層 — サイズ上限',
        'File exceeds the 2 MB maximum size.',
        'ファイルサイズが上限 2 MB を超えています。'
    );
}

$tmpPath = (string) ($_FILES['file']['tmp_name'] ?? '');
$rawName = (string) ($_FILES['file']['name'] ?? '');
$fileSize = (int) ($_FILES['file']['size'] ?? 0);

if ($errCode !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
    reject_upload(
        $lang,
        'Request Check',
        '受信検証',
        sprintf('Invalid or incomplete upload (code %d).', $errCode),
        sprintf('ファイルの受信に失敗しました（コード: %d）。', $errCode)
    );
}

if ($fileSize <= 0 || $fileSize > MAX_UPLOAD_BYTES) {
    reject_upload(
        $lang,
        'App Layer — Size Limit',
        'アプリ層 — サイズ上限',
        sprintf('File size (%d bytes) must be 1 B to 2 MB.', $fileSize),
        sprintf('ファイルサイズ（%d バイト）が 2 MB 上限の範囲外です。', $fileSize)
    );
}

if (str_contains($rawName, "\0")) {
    reject_upload(
        $lang,
        'App Layer — Filename Check',
        'アプリ層 — ファイル名検証',
        'Null byte detected in filename.',
        'ファイル名に不正な制御文字が含まれています。'
    );
}

$safeBase = basename(str_replace('\\', '/', $rawName));
$ext = strtolower((string) pathinfo($safeBase, PATHINFO_EXTENSION));
$allowedExtensions = [
    'png' => ['mime' => 'image/png', 'imagetype' => IMAGETYPE_PNG],
    'jpg' => ['mime' => 'image/jpeg', 'imagetype' => IMAGETYPE_JPEG],
    'jpeg' => ['mime' => 'image/jpeg', 'imagetype' => IMAGETYPE_JPEG],
    'gif' => ['mime' => 'image/gif', 'imagetype' => IMAGETYPE_GIF],
];

if (!isset($allowedExtensions[$ext])) {
    reject_upload(
        $lang,
        'App Layer — Extension Allowlist',
        'アプリ層 — 拡張子許可リスト',
        'Only .png, .jpg, .jpeg, and .gif are permitted.',
        '許可されている拡張子は .png・.jpg・.jpeg・.gif のみです。'
    );
}

$expectedSpec = $allowedExtensions[$ext];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = (string) $finfo->file($tmpPath);
if ($detectedMime !== $expectedSpec['mime']) {
    reject_upload(
        $lang,
        'App Layer — Server-Side finfo Check',
        'アプリ層 — finfo 実データ検証',
        sprintf(
            'Detected MIME "%s" does not match "%s" (client Content-Type is ignored).',
            $detectedMime,
            $expectedSpec['mime']
        ),
        sprintf(
            '実データの MIME 判定 "%s" が "%s" と一致しません（送信ヘッダの Content-Type は使用しません）。',
            $detectedMime,
            $expectedSpec['mime']
        )
    );
}

$imageInfo = @getimagesize($tmpPath);
if (
    !is_array($imageInfo)
    || ($imageInfo[0] ?? 0) <= 0
    || ($imageInfo[1] ?? 0) <= 0
    || ($imageInfo[2] ?? 0) !== $expectedSpec['imagetype']
) {
    reject_upload(
        $lang,
        'App Layer — Image Header Check',
        'アプリ層 — getimagesize 画像ヘッダ検証',
        'Failed structural image header parsing.',
        '画像ヘッダ構造の検証に失敗しました。'
    );
}

$storedName = generate_uuid_v4() . '.' . $ext;
$destPath = STORAGE_DIR . '/' . $storedName;

if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    render_layout(
        $lang,
        $isEn ? 'Storage Failure' : '保存エラー',
        '<div class="alert alert-err">'
        . esc($isEn ? 'Failed to store verified image.' : '検証済み画像の保存に失敗しました。')
        . '</div>'
    );
    exit;
}
@chmod($destPath, 0640);

$deliveryUrl = '/image.php?file=' . $storedName;
http_response_code(200);

ob_start();
?>
<div class="alert alert-ok">
  <h2><?= $isEn ? 'Image Verified & Stored (Stage 3)' : 'Stage 3 検証・保存完了' ?></h2>
  <p>
    <?= $isEn
        ? 'Client filename discarded; stored outside DocumentRoot with a UUIDv4 name:'
        : '元のファイル名を破棄し、UUIDv4 名で DocumentRoot 外に保存しました:' ?>
  </p>
  <p>
    <strong>URL:</strong>
    <a href="<?= esc($deliveryUrl) ?>" target="_blank" rel="noopener"><?= esc($deliveryUrl) ?></a>
  </p>
  <img class="preview-img" src="<?= esc($deliveryUrl) ?>" alt="Verified uploaded image">
</div>
<p>
  <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
    <?= $isEn ? 'Back to Stage 3' : 'Stage 3 へ戻る' ?>
  </a>
</p>
<?php
render_layout($lang, $isEn ? 'Upload Accepted - Stage 3' : 'アップロード完了 - Stage 3', (string) ob_get_clean());
