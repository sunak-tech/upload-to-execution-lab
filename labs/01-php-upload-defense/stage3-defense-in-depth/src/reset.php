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
        . esc($isEn ? 'Use POST /reset.php to reset uploads.' : 'POST /reset.php から実行してください。')
        . '</div>'
    );
    exit;
}

$deleted = 0;
if (is_dir(STORAGE_DIR)) {
    $entries = scandir(STORAGE_DIR);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }
            $target = STORAGE_DIR . '/' . $entry;
            if (is_file($target) && !is_link($target)) {
                if (@unlink($target)) {
                    $deleted++;
                }
            }
        }
    }
}

http_response_code(200);
$msg = $isEn
    ? sprintf('Reset complete: deleted %d image(s) (/uploads/infra-guard-check.phtml preserved).', $deleted)
    : sprintf('リセット完了: %d 件の画像を削除しました（/uploads/infra-guard-check.phtml は保持）。', $deleted);

$flash = '<div class="alert alert-ok">' . esc($msg) . '</div>'
    . '<script>if (window.history && window.history.replaceState) { window.history.replaceState(null, "", "/?lang=' . esc($lang) . '"); }</script>';

render_stage3_main($lang, $flash);
