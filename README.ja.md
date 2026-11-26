# Upload-to-Execution Lab — 日本語総合ガイド

**[🏠 Top (README.md)](./README.md)** | **[🇺🇸 English (README.en.md)](./README.en.md)** | **[🛡️ 安全ポリシー (docs/safety.ja.md)](./docs/safety.ja.md)** | **[🗺️ ロードマップ (docs/roadmap.ja.md)](./docs/roadmap.ja.md)**

---

> [!CAUTION]
> **ローカル学習専用・公開サーバへのデプロイ厳禁**
>
> 本リポジトリには、ファイルアップロードの設計不備によるサーバーサイドコード実行を再現するため、**意図的に脆弱な Web アプリケーション（Stage 1・Stage 2）**が含まれています。必ず個人のローカル端末上の Docker 環境（`127.0.0.1` 限定公開・内部閉域ネットワーク）でのみ実行してください。パブリッククラウド、VPS、社内共有サーバー、ポートフォワーディング／トンネル（ngrok 等）経由での公開は厳禁です。使用前に必ず [安全設計ポリシー (docs/safety.ja.md)](./docs/safety.ja.md) を確認してください。

---

## 1. プロジェクトの目的

Web アプリケーションにおける「ファイルアップロード機能」は、プロフィール画像や添付資料の受け渡しなど日常的に実装される一方で、**「外部から任意のバイト列とファイル名を受け取り、サーバー上のストレージに永続化し、再び HTTP 経由で配信する」**という性質上、設計の前提が一つ崩れるだけで重大なサーバーサイドコード実行（Remote Code Execution: RCE）に直結します。

**Upload-to-Execution Lab** は、単なる「脆弱性攻略（CTF）」ではなく、以下の**脅威モデリングと多層防御設計（Defense in Depth）**を体系的に体得するために設計されたローカル専用ハンズオン教材です。

1. **攻撃連鎖の構造化（`Upload -> Store -> Serve -> Execute`）**: アップロードされたファイルがどの信頼境界（Trust Boundary）を通過し、どのコンポーネントによって「データ」ではなく「コード」として解釈されるのかを可視化する。
2. **アプリケーション単体防御の限界（パーサー解釈の不一致）**: アプリケーションコード側で `.php` 拡張子をブラックリスト拒否しても、Web サーバー（Apache）側のハンドラ設定（`.phtml` 等）との認識ギャップによって容易にバイパスされる構造を理解する。
3. **App 層 × Infra 層の多層防御**: 拡張子ホワイトリスト・実ファイル内容検証・UUIDv4 リネーム・Web 公開領域外への隔離保存・安全な配信エンドポイント（App 層）と、Web サーバーでのスクリプト実行無効化・`.htaccess` 無効化・直接アクセス遮断（Infra 層）を組み合わせ、単一障害点を作らない設計を学ぶ。

---

## 2. 対象読者・想定ユースケース

- **Web アプリケーション開発者・インフラ／SRE エンジニア**: アプリ層とミドルウェア／インフラ層の責務分担と設定不備リスクを実機で確認したい方。
- **セキュリティ勉強会・社内研修の主催者**: 60〜90 分のハンズオンワークショップ（ブラウザ操作のみで完結可能、上級者向け `curl` 演習付き）を安全かつオフライン環境で開催したい方。
- **Lightning Talk (LT) 登壇者**: 5〜10 分の短時間で「脆弱な実装 → 不十分な対策の突破 → 多層防御による遮断」をライブデモし、設計の勘所を伝えたい方。
- **セキュリティ設計・脅威モデリング学習者**: データフロー図（DFD）と信頼境界から対策を導出する思考プロセスをワークシート形式で練習したい方。

---

## 3. リポジトリ構成

本リポジトリは、将来的に複数の技術スタックや発展的テーマを追加できるよう、マルチ・ラボ構成（`labs/` 配下）を採用しています。

```text
upload-to-execution-lab/
├── README.md                              # 日英バイリンガル入口ページ
├── README.ja.md                           # 日本語総合ガイド（本ファイル）
├── README.en.md                           # 英語総合ガイド
├── SAFETY.md                              # 安全ルール入口（日英）
├── LICENSE                                # MIT License
├── docs/
│   ├── safety.ja.md                       # 安全設計ポリシー詳細（日本語）
│   ├── safety.en.md                       # 安全設計ポリシー詳細（英語）
│   ├── roadmap.ja.md                      # マルチ・ラボ拡張計画と今後の候補（日本語）
│   └── roadmap.en.md                      # マルチ・ラボ拡張計画と今後の候補（英語）
└── labs/
    └── 01-php-upload-defense/             # Lab 01: PHP + Apache アップロード防御ラボ
        ├── README.md                      # Lab 01 日英インデックス
        ├── README.ja.md                   # Lab 01 アーキテクチャ・DFD・脅威分析（日本語）
        ├── README.en.md                   # Lab 01 アーキテクチャ・DFD・脅威分析（英語）
        ├── compose.yaml                   # 3 ステージ + 自動検証コンテナ定義（127.0.0.1 限定 / internal ネットワーク）
        ├── stage1-vulnerable/             # Stage 1 (Port 8081): 無防備なアップロード実装
        ├── stage2-app-only/               # Stage 2 (Port 8082): 不十分なアプリ単体ブラックリスト防御
        ├── stage3-defense-in-depth/       # Stage 3 (Port 8083): App 層 + Infra 層の多層防御実装
        ├── samples/                       # 無害な検証用サンプルファイル群（PNG / 固定マーカー出力 PHP・PHTML）
        ├── tests/                         # 自動 E2E 検証スクリプト (verify.php / host-smoke.ps1 / host-smoke.sh)
        └── docs/
            ├── ja/                        # 日本語教材（ハンズオン手順書・ワークシート・LT 台本）
            └── en/                        # 英語教材（Hands-On Guide / Worksheet / LT Script）
```

---

## 4. 動作環境・システム要件

| 項目 | 要件 |
| :--- | :--- |
| **コンテナ実行環境** | Docker Desktop または Docker Engine + `docker compose` v2 |
| **対応 OS / アーキテクチャ** | Windows 11/10 (WSL2 バックエンド推奨)、macOS (Intel `amd64` / Apple Silicon `arm64` 両対応)、Linux (`amd64` / `arm64`) |
| **空きポート（ローカルのみ）** | `127.0.0.1:8081`、`127.0.0.1:8082`、`127.0.0.1:8083` が未使用であること |
| **Web ブラウザ** | モダンブラウザ（Chrome / Edge / Firefox / Safari 等）。標準演習はブラウザのみで完結します |
| **ネットワーク要件** | 初回 `docker compose up --build -d` 時に公式ベースイメージ（`php:8.3-apache`）の取得が必要ですが、**ビルド完了後は完全オフライン（会場 Wi-Fi なし・機内モード等）で動作**します（外部 CDN・外部フォント不使用） |

---

## 5. クイックスタート（Lab 01 の起動・検証・終了）

### ステップ 1: コンテナのビルドと起動

```bash
cd labs/01-php-upload-defense
docker compose up --build -d
```

### ステップ 2: ブラウザで 3 つのステージにアクセス

すべてのステージは `?lang=ja` / `?lang=en` による UI 言語切替と、アップロード済みファイルを初期状態に戻す **Reset ボタン**（`POST /reset.php`）を備えています。

| ステージ | ローカル URL | 概要 |
| :--- | :--- | :--- |
| **Stage 1: Vulnerable** | `http://127.0.0.1:8081/?lang=ja` | 拡張子・内容検証なし。`/var/www/html/uploads/<name>` に保存され、`marker-basic.php` がサーバー上で PHP 実行されます（`LAB_EXECUTION_MARKER_STAGE1`）。 |
| **Stage 2: App-Only Insufficient Defense** | `http://127.0.0.1:8082/?lang=ja` | `.php` のみを拒否するブラックリスト実装。`marker-basic.php` は HTTP 400 で拒否されますが、Apache が処理する `marker-bypass.phtml` でバイパス・PHP 実行が成立します（`LAB_EXECUTION_MARKER_STAGE2`）。 |
| **Stage 3: Defense in Depth** | `http://127.0.0.1:8083/?lang=ja` | 画像ホワイトリスト・`finfo` + `getimagesize()`・2MB 上限・UUIDv4 リネーム・Web ルート外保存（`/var/lab-storage/uploads/`）・`/image.php` 安全配信に加え、Apache 側で `/uploads` の PHP 実行無効化・直接アクセス遮断（HTTP 403）を実施します。 |

### ステップ 3: 自動検証スイートの実行（任意）

`labs/01-php-upload-defense/` ディレクトリ内で以下のコマンドを実行すると、全ステージの正常系・攻撃再現・防御挙動・安全ガードレールを一括検証できます。

```bash
docker compose --profile test run --rm verifier
```

ホスト側からの疎通スモークテストスクリプトも用意されています:
- Windows PowerShell: `pwsh -File tests/host-smoke.ps1`
- macOS / Linux: `sh tests/host-smoke.sh`

### ステップ 4: ラボの停止とクリーンアップ

演習終了後は速やかにコンテナを停止・削除してください。

```bash
docker compose down
```

---

## 6. 安全設計の要点（Safety by Design）

本ラボは脆弱な挙動を安全に観察できるよう、インフラ・アプリ・サンプルデータの全階層に厳格な安全制約を組み込んでいます。詳細は **[docs/safety.ja.md](./docs/safety.ja.md)** を参照してください。

1. **ホストループバック限定バインド**: すべての公開ポートは `127.0.0.1:8081:8080`〜`8083:8080` として明示的に指定されており、LAN 内の他端末からは到達できません。
2. **外部通信遮断ネットワーク（`internal: true`）**: Docker ネットワーク `lab_net` に `internal: true` を設定し、コンテナからインターネットへのアウトバウンド通信を遮断しています。
3. **非 root コンテナ実行**: コンテナ内の Apache / PHP プロセスは非特権ユーザー `www-data`（内部ポート `8080`）で稼働します。
4. **完全無害な固定マーカー検証ファイル**: `samples/marker-basic.php` および `samples/marker-bypass.phtml` は、固定文字列マーカーと読み取り専用のランタイム情報（PHP バージョン・実行ユーザー・ホスト名・UTC 時刻）のみを表示します。外部入力（`$_GET` / `$_POST` 等）の受け取りや OS コマンド実行関数（`system` / `exec` 等）は一切含みません。

---

## 7. 学習の進め方・ドキュメントリンク

目的に応じて以下のドキュメントへ進んでください。

- **アーキテクチャと脅威モデリングを深く学ぶ**:
  - [Lab 01 詳細ガイド・DFD・多層防御マトリクス (labs/01-php-upload-defense/README.ja.md)](./labs/01-php-upload-defense/README.ja.md)
- **勉強会・独習で手を動かして学ぶ**:
  - [60〜90 分ハンズオン手順書 (hands-on-guide.md)](./labs/01-php-upload-defense/docs/ja/hands-on-guide.md)
  - [脅威モデリング実践ワークシート（解答例付き） (worksheet.md)](./labs/01-php-upload-defense/docs/ja/worksheet.md)
- **5〜10 分の LT でライブデモを行う**:
  - [LT 登壇・ライブデモ台本 (lt-script.md)](./labs/01-php-upload-defense/docs/ja/lt-script.md)
- **サンプルファイル・テスト仕様を確認する**:
  - [検証用サンプルファイル解説 (samples/README.md)](./labs/01-php-upload-defense/samples/README.md)
  - [自動検証スイート解説 (tests/README.md)](./labs/01-php-upload-defense/tests/README.md)
- **今後のラボ拡張構想を知る・コントリビュートする**:
  - [安全設計ポリシー (docs/safety.ja.md)](./docs/safety.ja.md)
  - [マルチ・ラボ拡張ロードマップ (docs/roadmap.ja.md)](./docs/roadmap.ja.md)
