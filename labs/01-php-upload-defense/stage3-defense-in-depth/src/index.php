<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

$lang = current_lang();
$flash = '';
if (($_GET['reset'] ?? '') === '1') {
    $msg = ($lang === 'en')
        ? 'Reset completed: all user-uploaded files in Stage 3 storage have been deleted.'
        : 'リセット完了: Stage 3 ストレージ内のアップロード済み画像をすべて削除しました。';
    $flash = '<div class="alert alert-ok">' . esc($msg) . '</div>';
}

render_stage3_main($lang, $flash);
