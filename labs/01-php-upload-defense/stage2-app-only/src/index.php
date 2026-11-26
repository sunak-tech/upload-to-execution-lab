<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$lang = current_lang();
$flash = '';
if (($_GET['reset'] ?? '') === '1') {
    $msg = ($lang === 'en')
        ? 'Reset completed: all user-uploaded files in Stage 2 have been deleted.'
        : 'リセット完了: Stage 2 のアップロード済みファイルをすべて削除しました。';
    $flash = '<div class="alert alert-ok">' . esc($msg) . '</div>';
}

render_stage2_main($lang, $flash);
