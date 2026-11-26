<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$lang = current_lang();
$flash = '';
if (($_GET['reset'] ?? '') === '1') {
    $msg = ($lang === 'en')
        ? 'Reset complete: removed uploaded images in Stage 3.'
        : 'リセット完了: Stage 3 の保存済み画像を削除しました。';
    $flash = '<div class="alert alert-ok">' . esc($msg) . '</div>';
}

render_stage3_main($lang, $flash);
