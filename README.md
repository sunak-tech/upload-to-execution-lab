# Upload-to-Execution Lab

[English](./README.en.md) | [安全ポリシー](./SAFETY.md) | [用語集](./docs/glossary.md) | [ロードマップ](./docs/roadmap.md)

> [!CAUTION]
> 本リポジトリは意図的に脆弱な実装を含むローカル専用の学習教材です。公開サーバや共有ネットワークには絶対にデプロイせず、必ず [SAFETY.md](./SAFETY.md) を確認してください。

## 目的

ファイルアップロード機能がどのような経路（アップロード → 保存 → 配信 → 実行）でサーバーサイドのコード実行に至るのかを体験し、多層防御の設計を学ぶハンズオン教材です。

- **攻撃連鎖の可視化**: 外部から受け取ったファイルがどの信頼境界を越え、Web サーバーにコードとして解釈されるかを整理します。
- **単層防御の限界**: アプリ層で `.php` を拒否しても、Apache のハンドラ設定（`.phtml`）との認識差で突破される構造を確認します。
- **アプリ層とインフラ層の多層防御**: 許可リスト検証・実ファイル検査・公開領域外への隔離保存と、Apache 側のスクリプト実行無効化・直接アクセス遮断を組み合わせます。

## 対象読者

- アプリ層とミドルウェア層の責務分担を実機で確認したい Web 開発者・インフラエンジニア
- 60〜90 分のオフライン対応ハンズオン勉強会を開催したい主催者・講師
- 5〜10 分の LT で「脆弱な実装 → 回避 → 多層防御」をライブデモしたい登壇者
- データフロー図と信頼境界から対策を導く脅威モデリングを練習したい学習者

## リポジトリ構成

```text
upload-to-execution-lab/
├── README.md                              # 総合ガイド (README.en.md: 英語版)
├── SAFETY.md                              # 安全ポリシー (SAFETY.en.md: 英語版)
├── LICENSE                                # MIT License
├── docs/
│   ├── glossary.md                        # 初学者向け用語集 (glossary.en.md: 英語版)
│   └── roadmap.md                         # 拡張ロードマップ (roadmap.en.md: 英語版)
└── labs/
    └── 01-php-upload-defense/
        ├── README.md                      # アーキテクチャ・脅威分析 (README.en.md: 英語版)
        ├── compose.yaml                   # 3ステージ + 自動検証コンテナ定義
        ├── stage1-vulnerable/             # Stage 1 (8081): 未検証アップロード
        ├── stage2-app-only/               # Stage 2 (8082): アプリ層のみの拒否リスト
        ├── stage3-defense-in-depth/       # Stage 3 (8083): アプリ層 + インフラ層の多層防御
        ├── samples/                       # 無害な検証用サンプルファイル
        ├── tests/                         # 自動検証スクリプト (verify.php)
        └── docs/
            ├── ja/                        # ハンズオン手順書・ワークシート・LT台本
            └── en/                        # 英語版教材
```

## 動作要件

| 項目 | 要件 |
| :--- | :--- |
| **コンテナ環境** | Docker Desktop または Docker Engine（`docker compose` v2 対応） |
| **対応 OS** | Windows 11/10（WSL2 推奨）、macOS（Intel / Apple Silicon）、Linux |
| **空きポート** | `127.0.0.1:8081`、`127.0.0.1:8082`、`127.0.0.1:8083` |
| **ネットワーク** | 初回ビルド時のみベースイメージ取得が必要。ビルド完了後は完全オフラインで動作 |

## クイックスタート（Lab 01）

```bash
cd labs/01-php-upload-defense
docker compose up --build -d
```

すべてのステージはホスト自身のブラウザからのみアクセス可能です（`?lang=ja` / `?lang=en` 切替・初期化ボタン付き）。

| ステージ | ローカル URL | 挙動の要点 |
| :--- | :--- | :--- |
| **Stage 1** | `http://127.0.0.1:8081/?lang=ja` | 検証なし・`DocumentRoot` 配下に保存。`marker-basic.php` が実行される |
| **Stage 2** | `http://127.0.0.1:8082/?lang=ja` | `.php` のみ拒否（`HTTP 400`）。`marker-bypass.phtml` で回避・実行される |
| **Stage 3** | `http://127.0.0.1:8083/?lang=ja` | 画像許可リスト・内容検査・公開領域外保存・安全配信に加え、Apache で実行を無効化 |

自動検証と停止は以下のコマンドで実行します。

```bash
# 全ステージの挙動・安全制約を一括検証
docker compose --profile test run --rm verifier

# コンテナの停止・削除
docker compose down
```

## 安全設計の要点

詳細は [SAFETY.md](./SAFETY.md) を参照してください。

- **ループバック限定公開**: 公開ポートはすべて `127.0.0.1` に固定し、LAN 内の他端末からの到達を遮断しています。
- **外部通信の遮断**: Docker ネットワークに `internal: true` を設定し、コンテナからインターネットへの通信を禁止しています。
- **非特権ユーザー実行**: コンテナ内の Apache / PHP は非 root ユーザー `www-data`（内部ポート `8080`）で動作します。
- **無害な検証用ファイル**: サンプルスクリプトは固定文字列マーカーと読み取り専用の環境情報のみを出力し、外部入力の参照や OS コマンド実行関数を一切含みません。

## ドキュメント一覧

- **Lab 01 設計・脅威モデリング**: [labs/01-php-upload-defense/README.md](./labs/01-php-upload-defense/README.md)
- **Lab 01 勉強会・LT 教材**:
  - [ハンズオン手順書](./labs/01-php-upload-defense/docs/ja/hands-on-guide.md)
  - [脅威モデリング・ワークシート](./labs/01-php-upload-defense/docs/ja/worksheet.md)
  - [LT 登壇台本](./labs/01-php-upload-defense/docs/ja/lt-script.md)
- **サンプル・自動テスト解説**: [samples/README.md](./labs/01-php-upload-defense/samples/README.md) | [tests/README.md](./labs/01-php-upload-defense/tests/README.md)
- **用語集・安全ポリシー・今後の計画**: [docs/glossary.md](./docs/glossary.md) | [SAFETY.md](./SAFETY.md) | [docs/roadmap.md](./docs/roadmap.md) | [LICENSE](./LICENSE)
