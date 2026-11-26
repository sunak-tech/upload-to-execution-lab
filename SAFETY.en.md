# Safety Policy

[日本語](./SAFETY.md) | [Root Guide](./README.en.md) | [Lab 01](./labs/01-php-upload-defense/README.en.md) | [Roadmap](./docs/roadmap.en.md)

> [!CAUTION]
> **FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY**: This repository contains intentionally vulnerable Web applications (Stage 1 & Stage 2). Run them strictly inside local Docker containers on your personal machine.

## 1. Non-Negotiable Rules

Every learner, workshop facilitator, and contributor must enforce the following rules:

1. **Zero Public or Shared Exposure**: Never deploy to cloud instances, VPS hosts, or shared servers, and never expose ports via `0.0.0.0` bindings or tunneling tools (`ngrok`, `cloudflared`, `ssh -R`, etc.).
2. **Strict `127.0.0.1` Loopback Binding**: Every published port must explicitly bind `127.0.0.1:<host_port>:<container_port>` so only the local host can connect.
3. **Outbound Network Isolation**: Lab Docker networks must set `internal: true` to block outbound internet traffic from containers.
4. **Non-Root Container Execution**: Web and application processes must run as an unprivileged user (`www-data` on internal port `8080`).
5. **No Interactive Webshells**: Proof-of-execution samples may output only static marker strings and read-only runtime metadata—zero external input handling and zero OS command execution functions.
6. **Authorized Local Targets Only**: Test the techniques and sample files in this repository exclusively against your own local lab containers.

## 2. Technical Guardrail Specifications

Rather than relying on user caution alone, [`labs/01-php-upload-defense/compose.yaml`](./labs/01-php-upload-defense/compose.yaml) and the stage containers enforce hard technical boundaries:

- **Explicit Loopback Ports**: Stages 1–3 bind `"127.0.0.1:8081:8080"` through `"8083:8080"`, preventing access from other devices on the local network.
- **Internal Network & Offline UI**: `lab_net` sets `internal: true`, and all HTML/CSS pages avoid external CDNs, fonts, and scripts.
- **Minimal Filesystem Privileges**: `www-data` can write only to the upload directory (`/var/www/html/uploads` in Stages 1–2; `/var/lab-storage/uploads` in Stage 3); application code and Apache configs remain read-only.
- **Harmless Fixed-Marker Payloads**: `samples/marker-basic.php` and `samples/marker-bypass.phtml` output only static stage markers (`LAB_EXECUTION_MARKER_STAGE1` / `STAGE2`) plus `PHP_VERSION`, process user, hostname, and UTC time. They never read `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, or `$_FILES`, and never call `system`, `exec`, `passthru`, `shell_exec`, `popen`, `proc_open`, `eval`, or `assert`—enforced statically by `tests/verify.php`.
- **Persistent Warning Banner & Instant Reset**: Every page renders a local-only safety banner at the top and provides a Reset button (`POST /reset.php`) to wipe uploaded files immediately (Stages 1–2 also apply `basename()` to prevent path traversal outside `/uploads`).

## 3. Operational Rules

- **Shut Down After Use**: Run `docker compose down` immediately after finishing your study session, workshop, or talk.
- **Workshop Delivery**: Never host a shared lab server; have participants run containers locally on their own laptops or present via screen share.
- **Do Not Copy Vulnerable Code**: `stage1-vulnerable` and `stage2-app-only` are deliberate anti-patterns and must never be copied into production systems.

## 4. Contributor Safety Checklist for Future Labs

Any new lab proposed under the [Roadmap](./docs/roadmap.en.md) must satisfy every item below:

- [ ] All published ports in `compose.yaml` explicitly prefix `127.0.0.1:` (never bare `"8080:8080"` or `0.0.0.0`).
- [ ] Container networks configure `internal: true` with zero outbound internet dependencies.
- [ ] Containers run as a non-root user with minimal writable paths.
- [ ] Sample files use static fixed markers only—zero external input handling and zero command or code-eval primitives.
- [ ] Zero external CDN links so the lab operates 100% offline after the initial build.
- [ ] Every Web UI page includes the local-only warning banner (`ローカル学習専用` in `ja` / `FOR LOCAL EDUCATIONAL USE ONLY` in `en`).
- [ ] `docker compose --profile test run --rm verifier` validates functional behavior and static safety checks end to end.
