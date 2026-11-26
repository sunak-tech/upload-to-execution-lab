# Upload-to-Execution Lab 

**🌐 Language / 言語を選択してください:**
**[🇯🇵 日本語ガイド (README.ja.md)](./README.ja.md)** | **[🇺🇸 English Guide (README.en.md)](./README.en.md)**

---

> [!CAUTION]
> **ローカル学習専用・公開サーバへのデプロイ厳禁 / FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY**
>
> - 本リポジトリは脆弱な実装（Stage 1 / Stage 2）を意図的に含む**ローカル環境専用のセキュリティ教育・脅威モデリング教材**です。インターネット公開サーバ・クラウド環境・共有ネットワーク上では絶対に起動しないでください。
> - This repository intentionally includes vulnerable stages (Stage 1 / Stage 2) strictly for **local security education and threat modeling**. Never deploy or expose any part of this lab on public servers, cloud environments, or shared networks.
> - 詳細な安全設計ポリシー / Full Safety Policy: **[SAFETY.md](./SAFETY.md)** ([日本語](./docs/safety.ja.md) | [English](./docs/safety.en.md))

---

## 概要 / Overview

**Upload-to-Execution Lab** は、ファイルアップロード機能がどのような経路（`Upload -> Store -> Serve -> Execute`）を経てRCEに至るのか、なぜアプリケーション層のブラックリスト単体では防御が破綻するのか、そして**アプリケーション層とインフラ層の多層防御（Defense in Depth）**によってどのように攻撃連鎖を断ち切るのかを体験的に学ぶハンズオン教材です。

**Upload-to-Execution Lab** is a hands-on threat modeling and secure architecture lab exploring how file upload features lead to server-side code execution (`Upload -> Store -> Serve -> Execute`), why naive application-only blacklists fail across parser trust boundaries, and how **Defense in Depth** across application and infrastructure layers breaks the attack chain.

### 収録ラボ / Included Labs

| Lab | テーマ / Topic | ガイド / Documentation |
| :--- | :--- | :--- |
| **[Lab 01](./labs/01-php-upload-defense/README.md)** | PHP + Apache におけるファイルアップロードからコード実行への連鎖と多層防御<br>File Upload to Code Execution & Defense in Depth on PHP + Apache | [🇯🇵 日本語](./labs/01-php-upload-defense/README.ja.md) \| [🇺🇸 English](./labs/01-php-upload-defense/README.en.md) |
| **Future Labs** | 将来の拡張計画（Nginx + PHP-FPM 境界、画像ポリグロット、署名付き URL 等）<br>Multi-lab roadmap (Nginx + PHP-FPM trust boundaries, polyglots, presigned URLs, etc.) | [🇯🇵 ロードマップ](./docs/roadmap.ja.md) \| [🇺🇸 Roadmap](./docs/roadmap.en.md) |

---

## クイックスタート / Quickstart (Lab 01)

前提条件: Docker Engine / Docker Desktop（`docker compose` v2 対応、Windows WSL2 / macOS Intel & Apple Silicon / Linux 対応）。初回ビルド後は**完全オフライン動作**します。

Prerequisites: Docker Engine / Docker Desktop with `docker compose` v2 (Windows WSL2, macOS Intel & Apple Silicon, or Linux). Works **100% offline** after the initial image build.

```bash
cd labs/01-php-upload-defense
docker compose up --build -d
```

起動後、ホスト自身のブラウザからのみアクセス可能です（すべて `127.0.0.1` 限定バインド）:
Once running, open the stages from your local browser (strictly bound to `127.0.0.1`):

| Stage | URL (`ja` / `en`) | 特徴 / Summary |
| :--- | :--- | :--- |
| **Stage 1: Vulnerable** | `http://127.0.0.1:8081/?lang=ja` / `http://127.0.0.1:8081/?lang=en` | 検証なし・公開ディレクトリ保存・PHP 実行成立 / No validation, webroot storage, PHP executes |
| **Stage 2: App-Only Defense** | `http://127.0.0.1:8082/?lang=ja` / `http://127.0.0.1:8082/?lang=en` | `.php` ブラックリストのみ・`.phtml` でバイパス成立 / Naive `.php` blacklist bypassed by `.phtml` |
| **Stage 3: Defense in Depth** | `http://127.0.0.1:8083/?lang=ja` / `http://127.0.0.1:8083/?lang=en` | App 層検証＋公開領域外保存＋配信制御＋Infra 層実行無効化 / Multi-layer App + Infra hardening |

自動検証の実行と片付け / Automated Verification & Teardown:

```bash
# 全ステージの安全設計・挙動を自動テスト / Run automated E2E verification
docker compose --profile test run --rm verifier

# コンテナの停止・削除 / Stop and remove containers
docker compose down
```

---

## ドキュメント一覧 / Documentation Map

- **ルートガイド / Root Guides**: [日本語 (README.ja.md)](./README.ja.md) | [English (README.en.md)](./README.en.md)
- **安全ポリシー / Safety Policy**: [SAFETY.md](./SAFETY.md) ([日本語](./docs/safety.ja.md) | [English](./docs/safety.en.md))
- **拡張ロードマップ / Roadmap**: [日本語 (docs/roadmap.ja.md)](./docs/roadmap.ja.md) | [English (docs/roadmap.en.md)](./docs/roadmap.en.md)
- **Lab 01 詳細・脅威モデリング / Lab 01 Architecture & DFDs**: [Index](./labs/01-php-upload-defense/README.md) ([日本語](./labs/01-php-upload-defense/README.ja.md) | [English](./labs/01-php-upload-defense/README.en.md))
- **Lab 01 勉強会・LT 教材 / Workshop & LT Materials**:
  - 日本語: [ハンズオン手順書](./labs/01-php-upload-defense/docs/ja/hands-on-guide.md) | [脅威モデリング・ワークシート](./labs/01-php-upload-defense/docs/ja/worksheet.md) | [LT 登壇台本](./labs/01-php-upload-defense/docs/ja/lt-script.md)
  - English: [Hands-On Guide](./labs/01-php-upload-defense/docs/en/hands-on-guide.md) | [Threat Modeling Worksheet](./labs/01-php-upload-defense/docs/en/worksheet.md) | [LT Demo Script](./labs/01-php-upload-defense/docs/en/lt-script.md)
- **ライセンス / License**: [MIT License](./LICENSE)
