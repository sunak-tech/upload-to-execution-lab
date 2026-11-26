# マルチ・ラボ拡張ロードマップ (`docs/roadmap.ja.md`)

**[🏠 日本語トップ (README.ja.md)](../README.ja.md)** | **[🇺🇸 English (roadmap.en.md)](./roadmap.en.md)** | **[🛡️ 安全ポリシー (safety.ja.md)](./safety.ja.md)** | **[🔬 Lab 01 (README.ja.md)](../labs/01-php-upload-defense/README.ja.md)**

---

## 1. マルチ・ラボ設計のビジョン

**Upload-to-Execution Lab** は、単一のフレームワークや単一の脆弱性パターンにとどまらず、**「外部から受け取ったファイルが、保存・ルーティング・変換・配信の過程でどのように信頼境界を越え、意図しない実行や権限侵害を引き起こすか」**を横断的に学べるプラットフォームとして設計されています。

そのため、リポジトリ直下に特定の言語環境を密結合させるのではなく、`labs/<番号>-<テーマ名>/` ごとに独立した `compose.yaml`・ステージ実装・無害な検証サンプル・自動テスト・勉強会教材を格納するモジュラー構成を採用しています。

---

## 2. 現在の実装状況

| ラボ ID | ディレクトリ | ステータス | 扱う主な脅威と防御テーマ |
| :--- | :--- | :--- | :--- |
| **Lab 01** | [`labs/01-php-upload-defense/`](../labs/01-php-upload-defense/README.ja.md) | **公開済み (v1.0)** | Apache + `mod_php` 環境における未検証アップロード（Stage 1）、`.php` ブラックリストと `.phtml` ハンドラ不一致（Stage 2）、App 層検証 + Web ルート外隔離 + 安全配信 + Infra 層スクリプト実行無効化による多層防御（Stage 3） |

---

## 3. 今後の拡張ラボ候補（Advanced Threat Modeling Candidates）

Lab 01 で基礎となる `Upload -> Store -> Serve -> Execute` の連鎖を学んだ次のステップとして、実務やクラウドアーキテクチャで頻発する以下の発展的ラボが計画候補となっています。

### 候補 Lab 02: `labs/02-nginx-phpfpm-pathinfo/`
**テーマ: リバースプロキシ／Web サーバーと FastCGI バックエンド間の信頼境界不一致（Nginx + PHP-FPM）**
- **学ぶ構造的リスク**:
  - Nginx と PHP-FPM が別プロセス・別コンテナに分かれている構成における URL パス解釈のズレ（歴史的な `cgi.fix_pathinfo=1` 問題や不適切な `fastcgi_split_path_info` / `location ~ \.php$` 設定により、`/uploads/avatar.png/notfound.php` が画像ファイルとして保存されたまま PHP として実行される現象）。
- **多層防御のポイント**:
  - `cgi.fix_pathinfo=0`、`security.limit_extensions`、Nginx 側での `try_files $uri =404;`、静的配信ロケーションでの FastCGI 転送完全分離、Web ルート外ストレージの併用。

### 候補 Lab 03: `labs/03-image-polyglot-reencoding/`
**テーマ: 画像ポリグロット（Polyglot）と画像再エンコード・パイプラインの設計**
- **学ぶ構造的リスク**:
  - 先頭マジックバイト（PNG/GIF/JPEG ヘッダ）と有効な画像寸法を持ちながら、EXIF メタデータやチャンク末尾にスクリプトコード断片を埋め込んだ「ポリグロット画像」が、`finfo` や `getimagesize()` 単体を通過する仕組み。
  - ローカルファイルインクルード（LFI）や誤ったパーサー設定と組み合わさった際の二次被害リスク、および画像変換ライブラリ（ImageMagick / GD / libvips）自体のパーサー脆弱性リスク。
- **多層防御のポイント**:
  - ピクセル配列からの画像再生成（Re-encoding）とメタデータ完全除去（Strip）、画像変換ワーカーのサンドボックス／低権限コンテナ分離、メモリ・解像度上限（Decompression Bomb 対策）。

### 候補 Lab 04: `labs/04-archive-path-traversal-config-overwrite/`
**テーマ: ファイル名正規化不備・設定ファイル上書き（`.htaccess` / `.user.ini`）・Zip Slip**
- **学ぶ構造的リスク**:
  - アップロード時のパストラバーサル（`../`）、マルチバイト文字・NULL バイト・OS 固有パス区切り文字の解釈差、アーカイブ展開時のシンボリックリンク／相対パス展開（Zip Slip）。
  - コードそのものではなく `.htaccess`（Apache）や `.user.ini`（PHP-FPM）をアップロードすることで、無害な拡張子（`.txt` や `.png`）をスクリプトとして実行させる「設定注入（Configuration Injection）」連鎖。
- **多層防御のポイント**:
  - ユーザー由来ファイル名をストレージパスに一切使用しない（サーバー生成 UUID 固定）、`AllowOverride None` / `user_ini.filename = ""`、展開処理時の正規化パス検証（`realpath` プレフィックス検査）とシンボリックリンク拒否。

### 候補 Lab 05: `labs/05-object-storage-presigned-url/`
**テーマ: クラウドオブジェクトストレージ（S3 互換 MinIO ローカルエミュレーション）と Presigned URL の信頼境界**
- **学ぶ構造的リスク**:
  - アプリケーションサーバーを経由せずクライアントが直接ストレージへアップロードする「署名付き URL（Presigned POST / PUT）」アーキテクチャにおいて、Content-Type 制約漏れ・ファイルサイズ条件漏れ・同一オリジン／カスタムドメイン配信による Stored XSS（SVG / HTML アップロード）・バケット公開設定ミスが引き起こすリスク。
- **多層防御のポイント**:
  - Presigned POST Policy での厳格な条件指定、非公開（Quarantine）バケットへの一次受け入れ → 非同期検証ワーカー（マジックバイト検査・再エンコード） → 配信専用バケットへの昇格、アプリ本体ドメインとは分離されたサンドボックスドメイン（例: `*-usercontent` 相当）＋ `Content-Security-Policy: sandbox` / `Content-Disposition: attachment` 配信。

---

## 4. 新規ラボ追加時の標準ディレクトリ規約

将来新しいラボを追加する際は、Lab 01 と同じ構成・操作感を維持するため以下の標準テンプレートに従います。

```text
labs/<NN>-<slug>/
├── README.md                  # 日英インデックス
├── README.ja.md               # アーキテクチャ・DFD・多層防御マトリクス（日本語）
├── README.en.md               # アーキテクチャ・DFD・多層防御マトリクス（英語・100% 対訳）
├── compose.yaml               # 127.0.0.1 限定バインド + internal: true + test プロファイル verifier
├── stage1-.../                # 脆弱なベースライン実装
├── stage2-.../                # 部分的・不十分な対策とそのバイパス
├── stage3-.../                # App 層 + Infra 層の多層防御実装
├── samples/                   # 完全無害な固定マーカー検証ファイル + samples/README.md
├── tests/                     # verify.* + host-smoke.ps1 + host-smoke.sh + tests/README.md
└── docs/
    ├── ja/                    # hands-on-guide.md / worksheet.md / lt-script.md
    └── en/                    # hands-on-guide.md / worksheet.md / lt-script.md
```

すべての追加ラボは、必ず [`docs/safety.ja.md`](./safety.ja.md) の「4. コントリビュータ向け安全規約」を満たす必要があります（クラウド SDK を使うテーマであっても、必ずローカルコンテナ上の MinIO 等で完結させ、外部ネットワーク通信を一切発生させないこと）。
