# Sample Test Files / サンプル検証ファイル (`samples/`)

> **WARNING / 警告**
> - **JA**: ローカル学習専用・公開サーバへのデプロイ厳禁
> - **EN**: FOR LOCAL EDUCATIONAL USE ONLY - DO NOT DEPLOY PUBLICLY

---

## 日本語 (Japanese)

このディレクトリには、Lab 01 (`01-php-upload-defense`) の Stage 1〜Stage 3 をブラウザや自動テストから安全に検証するための無害なサンプルファイルが含まれています。

### ファイル一覧と期待される挙動

| ファイル名 | 種類・内容 | Stage 1 (`:8081`) | Stage 2 (`:8082`) | Stage 3 (`:8083`) |
| --- | --- | --- | --- | --- |
| `sample-valid.png` | 正常な PNG 画像 (64×64 RGB) | アップロード成功 (`200`)・画像表示 | アップロード成功 (`200`)・画像表示 | アップロード成功 (`200`)・UUID リネーム＋Web ルート外保存＋`/image.php` 経由で安全に配信 |
| `marker-basic.php` | 無害な固定マーカー (`LAB_EXECUTION_MARKER_STAGE1`) | アップロード成功 (`200`)・`/uploads/marker-basic.php` で PHP 動作 | アプリ層の `.php` 拒否リストによりブロック (`400`) | アプリ層の拡張子許可リストによりブロック (`400`) |
| `marker-bypass.phtml` | 無害な固定マーカー (`LAB_EXECUTION_MARKER_STAGE2`) | アップロード成功 (`200`)・PHP 動作 | **回避成功 (`200`)**・Apache の `.phtml` ハンドラ設定により `/uploads/marker-bypass.phtml` で PHP 動作 | アプリ層の拡張子許可リストによりブロック (`400`)（万一配置されてもインフラ層で遮断） |

### 安全設計ポリシー (Safety Design)

- `marker-basic.php` および `marker-bypass.phtml` は **外部入力（クエリ文字列、POST ボディ、Cookie 等）を一切読み取りません**。
- OS コマンド呼び出しや動的コード評価を行う関数は **一切含まれていません**。
- 出力される情報は固定のマーカー文字列（`LAB_EXECUTION_MARKER_STAGE1` / `LAB_EXECUTION_MARKER_STAGE2`）と読み取り専用のランタイム情報（PHP バージョン、プロセス実行ユーザ `www-data`、コンテナホスト名、UTC 時刻）のみです。

---

## English

This directory contains harmless verification files used to test Stage 1 through Stage 3 of Lab 01 (`01-php-upload-defense`) safely from a browser or automated test runner.

### File Overview & Expected Behavior

| File | Description | Stage 1 (`:8081`) | Stage 2 (`:8082`) | Stage 3 (`:8083`) |
| --- | --- | --- | --- | --- |
| `sample-valid.png` | Valid PNG image (64×64 RGB) | Accepted (`200`) & served | Accepted (`200`) & served | Accepted (`200`), renamed to UUIDv4, stored outside webroot, served safely via `/image.php` |
| `marker-basic.php` | Harmless fixed marker (`LAB_EXECUTION_MARKER_STAGE1`) | Accepted (`200`) & interpreted as PHP at `/uploads/marker-basic.php` | Blocked (`400`) by naive `.php` check | Blocked (`400`) by strict extension allowlist |
| `marker-bypass.phtml` | Harmless fixed marker (`LAB_EXECUTION_MARKER_STAGE2`) | Accepted (`200`) & interpreted as PHP | **Bypass (`200`)** — accepted by app and interpreted as PHP by Apache `.phtml` handler | Blocked (`400`) by strict extension allowlist (and blocked at infra layer even if placed on disk) |

### Safety Design

- `marker-basic.php` and `marker-bypass.phtml` **never read external input** (no query parameters, form fields, cookies, or uploaded payloads).
- They contain **zero OS command calls or dynamic code evaluation primitives** (not WebShells).
- They output only a fixed verification marker string (`LAB_EXECUTION_MARKER_STAGE1` / `LAB_EXECUTION_MARKER_STAGE2`) along with read-only runtime metadata (PHP version, `www-data` process user, container hostname, and UTC timestamp).
