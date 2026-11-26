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
      <h2><?= $isEn ? 'Upload Blocked by Defense-in-Depth (HTTP 400)' : '多層防御チェックによりアップロードを拒否しました (HTTP 400)' ?></h2>
      <p>
        <strong>EN [<?= esc($layerEn) ?>]:</strong> <?= esc($detailEn) ?><br>
        <strong>JA [<?= esc($layerJa) ?>]:</strong> <?= esc($detailJa) ?>
      </p>
    </div>
    <p>
      <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
        <?= $isEn ? 'Back to Stage 3 Top' : 'Stage 3 トップへ戻る' ?>
      </a>
    </p>
    <?php
    render_layout($lang, $isEn ? 'Upload Blocked (400)' : 'アップロード拒否 (400)', (string) ob_get_clean());
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    render_layout(
        $lang,
        $isEn ? 'Method Not Allowed' : '許可されていないメソッド',
        '<div class="alert alert-err">'
        . esc($isEn ? 'Use POST /upload.php to submit a file.' : 'POST /upload.php を使用してファイルを送信してください。')
        . '</div><p><a class="btn btn-secondary" href="/?lang=' . esc($lang) . '">'
        . esc($isEn ? 'Back to Stage 3' : 'Stage 3 トップへ戻る') . '</a></p>'
    );
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    reject_upload(
        $lang,
        'Request Check',
        'リクエスト形式検証',
        'Missing "file" field in multipart/form-data request.',
        'multipart/form-data の "file" フィールドが存在しません。'
    );
}

$errCode = (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
    reject_upload(
        $lang,
        'App Layer — Size Limit',
        'アプリ層 — サイズ上限チェック',
        'File exceeds the 2 MB maximum upload limit.',
        'ファイルサイズが上限（2 MB）を超えています。'
    );
}

$tmpPath = (string) ($_FILES['file']['tmp_name'] ?? '');
$rawName = (string) ($_FILES['file']['name'] ?? '');
$fileSize = (int) ($_FILES['file']['size'] ?? 0);

if ($errCode !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
    reject_upload(
        $lang,
        'Request Check',
        'アップロード受信検証',
        sprintf('Invalid or incomplete file upload (error code %d).', $errCode),
        sprintf('ファイルの受信が完了していないか不正です (error code %d)。', $errCode)
    );
}

// Check (c): 2 MB max size
if ($fileSize <= 0 || $fileSize > MAX_UPLOAD_BYTES) {
    reject_upload(
        $lang,
        'App Layer — Size Limit (2 MB)',
        'アプリ層 — サイズ上限チェック (2 MB)',
        sprintf('File size (%d bytes) must be between 1 byte and 2,097,152 bytes (2 MB).', $fileSize),
        sprintf('ファイルサイズ (%d bytes) が許可範囲（1〜2,097,152 bytes / 2 MB）外です。', $fileSize)
    );
}

// Check (a): Strict extension allowlist (png, jpg, jpeg, gif ONLY)
if (str_contains($rawName, "\0")) {
    reject_upload(
        $lang,
        'App Layer — Filename Check',
        'アプリ層 — ファイル名検査',
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
        'App Layer 1 — Strict Extension Allowlist',
        'アプリ層 1 — 拡張子ホワイトリスト検証',
        'Only .png, .jpg, .jpeg, and .gif extensions are permitted.',
        '許可されている拡張子は .png, .jpg, .jpeg, .gif のみです。'
    );
}

$expectedSpec = $allowedExtensions[$ext];

// Check (b-1): Server-side finfo(FILEINFO_MIME_TYPE) magic-byte check (never trust $_FILES['file']['type'])
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = (string) $finfo->file($tmpPath);
if ($detectedMime !== $expectedSpec['mime']) {
    reject_upload(
        $lang,
        'App Layer 2a — Server-Side finfo Magic-Byte Inspection',
        'アプリ層 2a — サーバ側 finfo マジックバイト検証',
        sprintf(
            'Server-side MIME inspection detected "%s", which does not match expected "%s" (client Content-Type header is ignored).',
            $detectedMime,
            $expectedSpec['mime']
        ),
        sprintf(
            'サーバ側 finfo 検査の結果 "%s" と判定され、期待される "%s" と一致しませんでした（クライアント送信 Content-Type は信頼しません）。',
            $detectedMime,
            $expectedSpec['mime']
        )
    );
}

// Check (b-2): Structural image header check via getimagesize()
$imageInfo = @getimagesize($tmpPath);
if (
    !is_array($imageInfo)
    || ($imageInfo[0] ?? 0) <= 0
    || ($imageInfo[1] ?? 0) <= 0
    || ($imageInfo[2] ?? 0) !== $expectedSpec['imagetype']
) {
    reject_upload(
        $lang,
        'App Layer 2b — Structural Image Header Check (getimagesize)',
        'アプリ層 2b — 画像構造ヘッダ検証 (getimagesize)',
        'Payload failed structural image header parsing (getimagesize) and was rejected.',
        'getimagesize() による画像ヘッダ構造の検証に失敗したため拒否されました。'
    );
}

// Check (d) & (e): Random UUIDv4 filename + save outside DocumentRoot (/var/lab-storage/uploads/)
$storedName = generate_uuid_v4() . '.' . $ext;
$destPath = STORAGE_DIR . '/' . $storedName;

if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    render_layout(
        $lang,
        $isEn ? 'Storage Failure' : '保存失敗',
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
  <h2><?= $isEn ? 'Image Verified & Stored Safely (Stage 3)' : '検証完了・安全に保存されました（Stage 3）' ?></h2>
  <p>
    <?= $isEn
        ? 'Passed extension allowlist, finfo MIME inspection, and getimagesize() checks. Client filename was discarded; stored outside DocumentRoot with a UUIDv4 name:'
        : '拡張子ホワイトリスト・finfo・getimagesize() の全検証を通過しました。元のファイル名は破棄され、UUIDv4 名で DocumentRoot 外に保存されました:' ?>
  </p>
  <p>
    <strong><?= $isEn ? 'Safe Delivery URL:' : '安全な配信 URL:' ?></strong>
    <a href="<?= esc($deliveryUrl) ?>" target="_blank" rel="noopener"><?= esc($deliveryUrl) ?></a>
  </p>
  <img class="preview-img" src="<?= esc($deliveryUrl) ?>" alt="Verified uploaded image">
</div>
<p>
  <a class="btn btn-secondary" href="/?lang=<?= esc($lang) ?>">
    <?= $isEn ? 'Back to Stage 3 Top' : 'Stage 3 トップへ戻る' ?>
  </a>
</p>
<?php
render_layout($lang, $isEn ? 'Upload Accepted - Stage 3' : 'アップロード完了 - Stage 3', (string) ob_get_clean());
