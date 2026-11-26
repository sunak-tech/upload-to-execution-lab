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
        . esc($isEn ? 'Use POST /reset.php to reset uploads.' : 'リセットには POST /reset.php を使用してください。')
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
    ? sprintf('Reset completed: removed %d stored image(s) from Stage 3 (/uploads/infra-guard-check.phtml preserved).', $deleted)
    : sprintf('リセット完了: Stage 3 から %d 件の保存済み画像を削除しました（/uploads/infra-guard-check.phtml は保持）。', $deleted);

$flash = '<div class="alert alert-ok">' . esc($msg) . '</div>'
    . '<script>if (window.history && window.history.replaceState) { window.history.replaceState(null, "", "/?lang=' . esc($lang) . '"); }</script>';

render_stage3_main($lang, $flash);
