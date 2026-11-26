# Detailed Safety Policy (`docs/safety.en.md`)

**[🛡️ SAFETY.md](../SAFETY.md)** | **[🇯🇵 日本語 (safety.ja.md)](./safety.ja.md)** | **[🏠 English Root (README.en.md)](../README.en.md)** | **[🔬 Lab 01 (README.en.md)](../labs/01-php-upload-defense/README.en.md)**

---

> [!CAUTION]
> **FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY**
>
> This project is a **strictly local educational lab** designed to safely demonstrate how file upload design flaws lead to server-side code execution (RCE) and how Defense in Depth prevents it. Never deploy or expose any component of this repository on the public internet, cloud instances, shared corporate servers, or untrusted networks.

---

## 1. Philosophy: Safety by Design

In hands-on security labs, operational mistakes—such as leaving containers running on a shared Wi-Fi network, binding ports to `0.0.0.0`, or bundling real interactive webshells as test payloads—can create genuine security risks.

Rather than relying solely on user caution, **Upload-to-Execution Lab** enforces **technical safety guardrails across container networking, host port bindings, OS privileges, Web UI warnings, sample payloads, and automated verification tests**.

---

## 2. Technical Guardrail Specifications

### 2.1 Strict Host Loopback Binding (`127.0.0.1` Only)

In [`labs/01-php-upload-defense/compose.yaml`](../labs/01-php-upload-defense/compose.yaml), every published port explicitly binds the host loopback interface `127.0.0.1`:

- Stage 1: `"127.0.0.1:8081:8080"`
- Stage 2: `"127.0.0.1:8082:8080"`
- Stage 3: `"127.0.0.1:8083:8080"`

In Docker Compose, omitting the host IP (e.g., `"8081:8080"`) binds `0.0.0.0` by default and exposes the service to other devices on the local network. Binding `0.0.0.0` is strictly prohibited across this repository.

### 2.2 Isolated Internal Container Network (`internal: true`)

The Docker bridge network `lab_net` is configured with `internal: true`:

- Outbound internet traffic from inside the containers (such as reverse callbacks, remote file inclusion fetches, or package downloads at runtime) is blocked at the Docker network layer.
- All Web UI HTML and CSS assets avoid external CDNs, remote fonts, and remote scripts so the lab runs 100% offline once built.

### 2.3 Non-Root Container Execution

Every stage container runs its Apache / PHP worker processes as the unprivileged `www-data` user rather than `root`:

- Apache listens on unprivileged internal port `8080` instead of privileged port `80`.
- Write permissions are restricted solely to the dedicated upload directories (`/var/www/html/uploads` in Stages 1 & 2; `/var/lab-storage/uploads` in Stage 3). Application source files and Apache configuration files are read-only to `www-data`.

### 2.4 Harmless Fixed-Marker Payloads (Zero Webshell Capability)

The sample scripts used to verify code execution (`samples/marker-basic.php` and `samples/marker-bypass.phtml`) are **harmless fixed-marker probes**, not interactive webshells:

- **Read-Only Output Only**:
  - Static stage verification string (`LAB_EXECUTION_MARKER_STAGE1` or `LAB_EXECUTION_MARKER_STAGE2`)
  - Read-only runtime metadata (`PHP_VERSION`, effective process username, container hostname, current UTC timestamp)
- **Strictly Prohibited Constructs**:
  - Reading any external request input: `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_FILES`, `php://input`, `$_SERVER['QUERY_STRING']`, etc.
  - Invoking any OS command or dynamic code evaluation primitive: `system()`, `exec()`, `passthru()`, `shell_exec()`, `popen()`, `proc_open()`, `eval()`, `assert()`, backtick operators, etc.
- The automated test suite (`tests/verify.php`) statically scans sample files to enforce zero forbidden superglobals and zero execution primitives.

### 2.5 Persistent UI Warning Banners & One-Click Reset

- Every page across all three stages displays a prominent top banner in Japanese (`ローカル学習専用・公開サーバへのデプロイ厳禁`) and English (`FOR LOCAL EDUCATIONAL USE ONLY - DO NOT DEPLOY PUBLICLY`), ensuring screenshots or live demo shares clearly communicate the local-only educational context.
- Each stage provides a **Reset button** (`POST /reset.php`) that removes user-uploaded files and restores a clean state immediately (Stages 1 & 2 also normalize filenames with `basename()` so uploads cannot traverse outside `/var/www/html/uploads/`).

---

## 3. Operational Rules for Learners & Workshop Facilitators

1. **Shut Down After Use**: Run `docker compose down` as soon as you finish your study session, workshop, or demo.
2. **Never Use Port Forwarding or Tunneling**: Do not expose lab ports via `ngrok`, `cloudflared`, `ssh -R`, router port forwarding, or external interface proxies. In workshops, each participant should run the containers locally on their own laptop, or the facilitator should share their screen.
3. **Never Copy Vulnerable Stage Code into Production**: `stage1-vulnerable` and `stage2-app-only` are deliberate anti-patterns. Even when referencing `stage3-defense-in-depth`, always adapt and review controls for your production environment's specific threat model.
4. **Authorized Local Testing Only**: Never test sample files or techniques from this lab against third-party systems or any system without explicit authorization. Keep all experimentation inside your local Docker lab containers.

---

## 4. Contributor Safety Requirements for Future Labs

Any Pull Request proposing a new lab (see [`docs/roadmap.en.md`](./roadmap.en.md), e.g., `labs/02-...`) or modifying existing labs must satisfy every item on this checklist:

- [ ] Every published port in `compose.yaml` explicitly prefixes `127.0.0.1:` (never bare `"8080:8080"` or `"0.0.0.0:..."`).
- [ ] Container networks set `internal: true` to prevent outbound internet access.
- [ ] Application containers run as a non-root user with minimal writable directories.
- [ ] All proof-of-execution sample files use static fixed markers only—zero external input handling and zero OS command or `eval` primitives.
- [ ] Zero external CDN dependencies (fonts, stylesheets, scripts) so the lab functions 100% offline after build.
- [ ] Every Web UI page renders the prominent bilingual local-only safety warning banner.
- [ ] Automated verification scripts under `tests/` validate normal flows, attack reproductions, defense-in-depth controls, and static safety invariants.
