<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$lang = current_lang();
$flash = '';
if (($_GET['reset'] ?? '') === '1') {
    $msg = ($lang === 'en')
        ? 'Reset complete: removed uploaded files in Stage 1.'
        : 'リセット完了: Stage 1 のアップロード済みファイルを削除しました。';
    $flash = '<div class="alert alert-ok">' . esc($msg) . '</div>';
}

render_stage1_main($lang, $flash);
