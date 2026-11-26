# Safety Policy / 安全設計ポリシー (`SAFETY.md`)

**🌐 Detailed Policy Documents / 詳細ポリシー文書:**
**[🇯🇵 日本語版安全ポリシー (docs/safety.ja.md)](./docs/safety.ja.md)** | **[🇺🇸 English Safety Policy (docs/safety.en.md)](./docs/safety.en.md)**

---

> [!CAUTION]
> **ローカル学習専用・公開サーバへのデプロイ厳禁 / FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY**

本リポジトリを利用・改変・フォーク・教材追加するすべての利用者およびコントリビュータは、以下の**絶対遵守ルール（Non-Negotiable Safety Rules）**に従う必要があります。

Every user, workshop facilitator, and contributor using, modifying, or extending this repository must strictly follow these **Non-Negotiable Safety Rules**.

---

## 絶対遵守ルール / Core Non-Negotiable Safety Rules

1. **公開デプロイ・外部公開の厳禁 / Zero Public or Shared Network Exposure**
   - クラウド環境（AWS / GCP / Azure 等）、VPS、共有開発サーバーへのデプロイ、および `0.0.0.0` バインドやトンネルツール（ngrok / Cloudflare Tunnel / `ssh -R` 等）による外部公開を一切禁止します。
   - Never deploy to cloud instances, VPS hosts, or shared servers, and never expose ports via `0.0.0.0` bindings or tunneling tools (ngrok, Cloudflare Tunnel, `ssh -R`, etc.).

2. **ホスト `127.0.0.1` 限定バインド / Strict `127.0.0.1` Loopback Binding**
   - すべての Docker Compose 公開ポートは `127.0.0.1:<host_port>:<container_port>` 形式で指定し、ホスト自身からのみ到達可能にします。
   - All published container ports must explicitly bind `127.0.0.1:<host_port>:<container_port>` so only the local host can connect.

3. **コンテナの外部ネットワーク隔離 / Container Network Isolation (`internal: true`)**
   - ラボ用 Docker ネットワークは `internal: true` とし、コンテナから外部インターネットへの通信を遮断します。
   - Lab Docker networks must set `internal: true` to block outbound internet egress from containers.

4. **非特権ユーザーでの実行 / Non-Root Container Processes**
   - コンテナ内のアプリケーションサーバーは非 root ユーザー（例: `www-data`、内部ポート `8080`）で実行します。
   - Application processes inside containers must run as an unprivileged non-root user (e.g., `www-data` on internal port `8080`).

5. **完全無害な検証用ファイル（Web シェル厳禁） / Harmless Fixed-Marker Samples Only (No Webshells)**
   - 検証用スクリプトは固定文字列マーカーと読み取り専用の環境情報のみを出力します。外部入力（`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_FILES` 等）の参照や、OS コマンド実行・動的評価（`system`, `exec`, `passthru`, `shell_exec`, `popen`, `proc_open`, `eval`, `assert` 等）の実装・同梱は厳禁です。
   - Sample payloads may output only static marker strings and read-only runtime metadata. Reading external input (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_FILES`, etc.) or invoking command/code execution primitives (`system`, `exec`, `passthru`, `shell_exec`, `popen`, `proc_open`, `eval`, `assert`, etc.) is strictly prohibited.

6. **第三者システムへのテスト厳禁 / Authorized Local Lab Targets Only**
   - 本教材で扱う知識およびサンプルファイルは、自身のローカル環境上の本ラボコンテナに対してのみ使用してください。
   - Use the techniques and sample files in this repository exclusively against your own local lab containers.

---

## 詳細ドキュメントへのリンク / Read the Full Safety Policy

- **🇯🇵 日本語詳細**: [docs/safety.ja.md](./docs/safety.ja.md) — 技術的ガードレールの実装仕様、運用ルール、将来ラボ追加時のコントリビュータ規約
- **🇺🇸 English Details**: [docs/safety.en.md](./docs/safety.en.md) — Technical guardrail specifications, operational rules, and contributor safety requirements for future labs
- **🏠 トップページへ戻る / Back to Root**: [README.md](./README.md) ([日本語](./README.ja.md) | [English](./README.en.md))
