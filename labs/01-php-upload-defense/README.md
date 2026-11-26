# Lab 01: PHP + Apache File Upload Defense (`01-php-upload-defense`)

**🌐 Select Language / 言語を選択してください:**
**[🇯🇵 日本語詳細ガイド (README.ja.md)](./README.ja.md)** | **[🇺🇸 English Deep-Dive Guide (README.en.md)](./README.en.md)**

**[🏠 Root Portal (../../README.md)](../../README.md)** | **[🛡️ Safety Policy (../../SAFETY.md)](../../SAFETY.md)**

---

> [!CAUTION]
> **ローカル学習専用・公開サーバへのデプロイ厳禁 / FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY**
>
> Stage 1 (`127.0.0.1:8081`) および Stage 2 (`127.0.0.1:8082`) は学習用に意図的な脆弱性を含みます。必ず個人のローカル環境（`127.0.0.1` 限定バインド・`internal: true` 隔離ネットワーク）でのみ起動してください。
> Stage 1 (`127.0.0.1:8081`) and Stage 2 (`127.0.0.1:8082`) are intentionally vulnerable for educational threat modeling. Run strictly on localhost (`127.0.0.1` binding with `internal: true` network isolation).

---

## クイックスタート / Quickstart

```bash
# 起動 (初回ビルド後は完全オフライン動作) / Start all 3 stages (100% offline after build)
docker compose up --build -d

# 自動検証の実行 / Run automated E2E verification container
docker compose --profile test run --rm verifier

# 停止・削除 / Stop and remove containers
docker compose down
```

### ステージ一覧 / Stages Overview

| Stage | Local URL (`ja` / `en`) | 概要 / Description |
| :--- | :--- | :--- |
| **Stage 1** | `http://127.0.0.1:8081/?lang=ja`<br>`http://127.0.0.1:8081/?lang=en` | **Vulnerable**: 検証なし・Web 公開領域保存・`.php` 実行成立 (`LAB_EXECUTION_MARKER_STAGE1`)<br>No validation, webroot `/uploads/` storage, `.php` executes |
| **Stage 2** | `http://127.0.0.1:8082/?lang=ja`<br>`http://127.0.0.1:8082/?lang=en` | **App-Only Insufficient Defense**: `.php` ブラックリストのみ・`.phtml` でバイパス成立 (`LAB_EXECUTION_MARKER_STAGE2`)<br>Naive `.php` block bypassed by Apache `.phtml` handler |
| **Stage 3** | `http://127.0.0.1:8083/?lang=ja`<br>`http://127.0.0.1:8083/?lang=en` | **Defense in Depth**: App 層多段検証＋公開領域外隔離＋`/image.php` 安全配信＋Infra 層実行無効化 (`HTTP 403`)<br>App allowlist/content checks + outside-webroot storage + Infra PHP execution off |

---

## 教材・ドキュメント索引 / Documentation & Workshop Materials

| カテゴリ / Category | 🇯🇵 日本語 (Japanese) | 🇺🇸 English |
| :--- | :--- | :--- |
| **アーキテクチャ・DFD・脅威分析**<br>Architecture, DFDs & Threat Matrix | **[README.ja.md](./README.ja.md)** | **[README.en.md](./README.en.md)** |
| **勉強会ハンズオン手順書 (60–90分)**<br>Workshop Hands-On Guide (60–90 min) | **[docs/ja/hands-on-guide.md](./docs/ja/hands-on-guide.md)** | **[docs/en/hands-on-guide.md](./docs/en/hands-on-guide.md)** |
| **脅威モデリング・ワークシート**<br>Participant Threat Modeling Worksheet | **[docs/ja/worksheet.md](./docs/ja/worksheet.md)** | **[docs/en/worksheet.md](./docs/en/worksheet.md)** |
| **LT ライブデモ台本 (5分 / 10分)**<br>Lightning Talk Demo Script (5 / 10 min) | **[docs/ja/lt-script.md](./docs/ja/lt-script.md)** | **[docs/en/lt-script.md](./docs/en/lt-script.md)** |
| **サンプル・自動検証リファレンス**<br>Samples & Test Suite Reference | [samples/README.md](./samples/README.md) \| [tests/README.md](./tests/README.md) | [samples/README.md](./samples/README.md) \| [tests/README.md](./tests/README.md) |
