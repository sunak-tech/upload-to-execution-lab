# マルチ・ラボ拡張ロードマップ

[English](./roadmap.en.md) | [トップページ](../README.md) | [安全ポリシー](../SAFETY.md) | [Lab 01](../labs/01-php-upload-defense/README.md)

> [!CAUTION]
> すべてのラボはローカル専用の学習環境です。利用・拡張時は必ず [SAFETY.md](../SAFETY.md) の安全規約を遵守してください。

## 1. 設計方針

本リポジトリは「外部から受け取ったファイルが、保存・ルーティング・変換・配信の過程で信頼境界を越え、意図しないコード実行を引き起こす構造」を横断的に学ぶため、`labs/<番号>-<テーマ名>/` ごとに独立したモジュラー構成を採用しています。

## 2. 現在の公開ラボ

| ラボ ID | ディレクトリ | 状態 | 主なテーマ |
| :--- | :--- | :--- | :--- |
| **Lab 01** | [`labs/01-php-upload-defense/`](../labs/01-php-upload-defense/README.md) | 公開済 | Apache + `mod_php` での未検証保存（Stage 1）、`.php` 拒否リストと `.phtml` ハンドラ不一致（Stage 2）、公開領域外隔離＋安全配信＋Apache 実行無効化による多層防御（Stage 3） |

## 3. 今後の拡張ラボ候補

### 候補 Lab 02: `labs/02-nginx-phpfpm-pathinfo/`
**テーマ: Nginx と PHP-FPM 間のパス解釈不一致**
- **構造的リスク**: リバースプロキシと FastCGI バックエンド間で URL 解釈がずれ、`cgi.fix_pathinfo=1` や不適切な `location ~ \.php$` 設定により `/uploads/avatar.png/notfound.php` が PHP として実行される問題。
- **多層防御の要点**: `cgi.fix_pathinfo=0`、`security.limit_extensions`、Nginx 側の `try_files $uri =404;`、静的配信ロケーションでの FastCGI 転送分離、公開領域外保存の併用。

### 候補 Lab 03: `labs/03-image-polyglot-reencoding/`
**テーマ: 画像ポリグロットと再エンコード・パイプライン**
- **構造的リスク**: 有効な PNG / JPEG ヘッダを持ちつつ EXIF やチャンク末尾にコード断片を含むファイルが `finfo` と `getimagesize()` を通過し、ファイルインクルード不備や画像変換ライブラリの脆弱性と連鎖する問題。
- **多層防御の要点**: ピクセル配列からの画像再生成とメタデータ完全除去、低権限ワーカーコンテナへの変換処理分離、解像度・メモリ上限の設定。

### 候補 Lab 04: `labs/04-archive-path-traversal-config-overwrite/`
**テーマ: パストラバーサル・設定ファイル注入（`.htaccess` / `.user.ini`）・アーカイブ展開**
- **構造的リスク**: 相対パスやシンボリックリンクを含むアーカイブ展開での任意パス上書き、および `.htaccess` や `.user.ini` の配置によって無害な拡張子をスクリプト実行させる設定注入連鎖。
- **多層防御の要点**: サーバー生成 `UUIDv4` によるファイル名固定、`AllowOverride None` / `user_ini.filename = ""`、展開時の正規化パス検査とシンボリックリンク拒否。

### 候補 Lab 05: `labs/05-object-storage-presigned-url/`
**テーマ: オブジェクトストレージ直接アップロードと署名付き URL の信頼境界（ローカル MinIO）**
- **構造的リスク**: 署名付き URL でのポリシー制約漏れ、同一オリジン配信による `.svg` / `.html` 経由のスクリプト実行、バケット公開設定ミス。
- **多層防御の要点**: 厳格なポリシー条件、検疫バケット → 非同期検証・再エンコード → 配信バケットの分離、専用配信ドメインと `Content-Security-Policy` / `Content-Disposition: attachment` の適用。

## 4. 新規ラボの標準構成

新しいラボを追加する際は、Lab 01 と共通のディレクトリ構成と [SAFETY.md](../SAFETY.md) の必須チェックリストに従います（オブジェクトストレージ等もローカルの `internal: true` コンテナ内で完結させます）。

```text
labs/<NN>-<slug>/
├── README.md                  # アーキテクチャ・脅威分析 (README.en.md: 英語版)
├── compose.yaml               # 127.0.0.1 限定 + internal: true + verifier
├── stage1-.../                # 脆弱なベースライン実装
├── stage2-.../                # 不十分な単層防御とバイパス
├── stage3-.../                # アプリ層 + インフラ層の多層防御
├── samples/                   # 無害な固定マーカー検証ファイル (README.md / README.en.md)
├── tests/                     # verify.* 自動検証 (README.md / README.en.md)
└── docs/
    ├── ja/                    # hands-on-guide.md / worksheet.md / lt-script.md
    └── en/                    # hands-on-guide.md / worksheet.md / lt-script.md
```
