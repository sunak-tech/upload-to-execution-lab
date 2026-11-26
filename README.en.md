# Upload-to-Execution Lab — Comprehensive Guide (English)

**[🏠 Top (README.md)](./README.md)** | **[🇯🇵 日本語 (README.ja.md)](./README.ja.md)** | **[🛡️ Safety Policy (docs/safety.en.md)](./docs/safety.en.md)** | **[🗺️ Roadmap (docs/roadmap.en.md)](./docs/roadmap.en.md)**

---

> [!CAUTION]
> **FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY**
>
> This repository contains **intentionally vulnerable Web applications (Stage 1 & Stage 2)** designed to demonstrate how file upload flaws lead to server-side code execution. Run these containers **strictly on your personal local machine** bound to `127.0.0.1` over an isolated internal Docker network. Never deploy this repository to public cloud instances, VPS hosts, shared corporate networks, or expose it via port forwarding / tunneling tools (such as ngrok). Read the [Safety Policy (docs/safety.en.md)](./docs/safety.en.md) before starting.

---

## 1. Purpose of This Project

File upload features—such as profile avatars and document attachments—are ubiquitous in modern Web applications. Yet because they **accept arbitrary client-supplied bytes and filenames, persist them on server storage, and subsequently serve them back over HTTP**, a single broken trust assumption can turn an ordinary upload form into full server-side code execution (Remote Code Execution: RCE).

**Upload-to-Execution Lab** is not a puzzle-style CTF; it is a structured, local-only hands-on lab built to teach **Threat Modeling and Defense-in-Depth Architecture**:

1. **Structuring the Attack Chain (`Upload -> Store -> Serve -> Execute`)**: Visualize which trust boundaries an uploaded file crosses and how downstream server components end up interpreting untrusted data as executable code.
2. **Why Application-Only Defenses Fail (Parser & Handler Mismatches)**: Experience firsthand why blocking `.php` in application code still fails when the underlying Web server (Apache) maps additional extensions like `.phtml` to the PHP interpreter.
3. **Defense in Depth Across Application & Infrastructure Layers**: Combine strict extension allowlisting, server-side content verification, UUIDv4 renaming, outside-webroot storage, and hardened delivery headers (Application Layer) with disabled script execution, `AllowOverride None`, and direct-access denial (Infrastructure Layer) so no single control is a single point of failure.

---

## 2. Target Audience & Use Cases

- **Web Application Developers & Infrastructure / SRE Engineers**: Understand the security contract between application validation logic and Web server handler configurations.
- **Security Study Group Organizers & Trainers**: Run a self-contained, 60–90 minute hands-on workshop (100% completable in a browser, with optional `curl` spoofing exercises) that works offline.
- **Lightning Talk (LT) Speakers**: Deliver a crisp 5–10 minute live demo showing *vulnerable execution -> naive blacklist bypass -> multi-layer defense* with one-click UI state resets.
- **Threat Modeling Practitioners & Students**: Practice deriving concrete security controls from Data Flow Diagrams (DFDs) and Trust Boundaries using the guided worksheet.

---

## 3. Repository Structure

The repository uses a multi-lab layout under `labs/` so future labs covering different stacks and threat patterns can be added cleanly alongside Lab 01.

```text
upload-to-execution-lab/
├── README.md                              # Bilingual landing page (JP / EN)
├── README.ja.md                           # Full root guide (Japanese)
├── README.en.md                           # Full root guide (English - this file)
├── SAFETY.md                              # Bilingual safety rules entrypoint
├── LICENSE                                # MIT License
├── docs/
│   ├── safety.ja.md                       # Detailed local-only safety policy (Japanese)
│   ├── safety.en.md                       # Detailed local-only safety policy (English)
│   ├── roadmap.ja.md                      # Multi-lab expansion guide & roadmap (Japanese)
│   └── roadmap.en.md                      # Multi-lab expansion guide & roadmap (English)
└── labs/
    └── 01-php-upload-defense/             # Lab 01: PHP + Apache File Upload Defense Lab
        ├── README.md                      # Lab 01 bilingual index
        ├── README.ja.md                   # Lab 01 architecture, DFDs & threat matrix (Japanese)
        ├── README.en.md                   # Lab 01 architecture, DFDs & threat matrix (English)
        ├── compose.yaml                   # 3 stages + verifier definition (127.0.0.1 only / internal net)
        ├── stage1-vulnerable/             # Stage 1 (Port 8081): Unvalidated webroot upload
        ├── stage2-app-only/               # Stage 2 (Port 8082): Naive app-only .php blacklist
        ├── stage3-defense-in-depth/       # Stage 3 (Port 8083): Hardened App + Infra defense in depth
        ├── samples/                       # Harmless test samples (valid PNG + fixed-marker PHP/PHTML)
        ├── tests/                         # Automated E2E verification (verify.php / host-smoke.ps1 / .sh)
        └── docs/
            ├── ja/                        # Japanese workshop guide, worksheet & LT script
            └── en/                        # English workshop guide, worksheet & LT script
```

---

## 4. System Requirements

| Item | Requirement |
| :--- | :--- |
| **Container Runtime** | Docker Desktop or Docker Engine with `docker compose` v2 |
| **Supported OS & CPU** | Windows 11/10 (WSL2 backend recommended), macOS (Intel `amd64` & Apple Silicon `arm64`), Linux (`amd64` / `arm64`) |
| **Available Local Ports** | `127.0.0.1:8081`, `127.0.0.1:8082`, and `127.0.0.1:8083` free on localhost |
| **Web Browser** | Any modern browser (Chrome, Edge, Firefox, Safari). Core exercises require only a browser |
| **Network Connectivity** | Internet access is required **only once** during the initial `docker compose up --build -d` to pull `php:8.3-apache`. **After building, the entire lab runs 100% offline** (zero external CDNs, fonts, or scripts) |

---

## 5. Quickstart (Running, Verifying & Stopping Lab 01)

### Step 1: Build and Start the Containers

```bash
cd labs/01-php-upload-defense
docker compose up --build -d
```

### Step 2: Open the 3 Stages in Your Browser

Every stage supports UI language switching via `?lang=ja` / `?lang=en` and includes a **Reset button** (`POST /reset.php`) to wipe uploaded files cleanly between runs.

| Stage | Local URL | Behavior Summary |
| :--- | :--- | :--- |
| **Stage 1: Vulnerable** | `http://127.0.0.1:8081/?lang=en` | Zero extension or content validation. Saves directly to `/var/www/html/uploads/<name>`, allowing `marker-basic.php` to execute as PHP (`LAB_EXECUTION_MARKER_STAGE1`). |
| **Stage 2: App-Only Insufficient Defense** | `http://127.0.0.1:8082/?lang=en` | Naive blacklist rejects `.php` (`marker-basic.php` -> HTTP 400), but Apache handles both `.php` and `.phtml`; uploading `marker-bypass.phtml` bypasses the app filter and executes (`LAB_EXECUTION_MARKER_STAGE2`). |
| **Stage 3: Defense in Depth** | `http://127.0.0.1:8083/?lang=en` | App layer enforces image allowlist, `finfo` + `getimagesize()`, 2MB cap, UUIDv4 rename, outside-webroot storage (`/var/lab-storage/uploads/`), and safe delivery via `/image.php`. Infra layer disables PHP execution on `/uploads` and denies direct access (`HTTP 403`). |

### Step 3: Run the Automated Verification Suite (Optional)

From `labs/01-php-upload-defense/`, run the built-in verifier container to validate all three stages, normal uploads, execution markers, defenses, and safety invariants:

```bash
docker compose --profile test run --rm verifier
```

Host-side smoke test scripts are also provided:
- Windows PowerShell: `pwsh -File tests/host-smoke.ps1`
- macOS / Linux: `sh tests/host-smoke.sh`

### Step 4: Stop and Clean Up

Always stop and remove the containers when finished with your study session:

```bash
docker compose down
```

---

## 6. Safety Summary (Safety by Design)

To let learners safely observe server-side execution mechanics without creating real-world exposure, strict technical guardrails are built into every layer. See **[docs/safety.en.md](./docs/safety.en.md)** for full details.

1. **Strict Loopback Binding (`127.0.0.1`)**: Published ports explicitly bind `127.0.0.1:8081:8080` through `8083:8080`, preventing access from LAN neighbors or external networks.
2. **Isolated Internal Network (`internal: true`)**: Containers run on a Docker `internal: true` network (`lab_net`) with no outbound internet access.
3. **Non-Root Container Execution**: Apache and PHP run as the unprivileged `www-data` user listening on internal port `8080`.
4. **Harmless Fixed-Marker Payloads**: `samples/marker-basic.php` and `samples/marker-bypass.phtml` output only static marker strings and read-only runtime metadata (PHP version, process user, hostname, UTC timestamp). They never read user input (`$_GET`, `$_POST`, etc.) and never invoke OS command or code-eval functions (`system`, `exec`, `eval`, etc.).

---

## 7. Recommended Learning Path & Documentation Links

Choose the guide that matches your goal:

- **Deep-Dive Architecture & Threat Modeling**:
  - [Lab 01 Architecture, DFDs & Defense-in-Depth Matrix (labs/01-php-upload-defense/README.en.md)](./labs/01-php-upload-defense/README.en.md)
- **Self-Paced Study or 60–90 Min Study Group Workshop**:
  - [Hands-On Workshop Guide (hands-on-guide.md)](./labs/01-php-upload-defense/docs/en/hands-on-guide.md)
  - [Participant Threat Modeling Worksheet with Answer Key (worksheet.md)](./labs/01-php-upload-defense/docs/en/worksheet.md)
- **Delivering a 5–10 Min Lightning Talk Live Demo**:
  - [Lightning Talk Demo Script (lt-script.md)](./labs/01-php-upload-defense/docs/en/lt-script.md)
- **Sample Payloads & Test Suite Reference**:
  - [Sample Files Guide (samples/README.md)](./labs/01-php-upload-defense/samples/README.md)
  - [Automated Verification Suite Guide (tests/README.md)](./labs/01-php-upload-defense/tests/README.md)
- **Safety Policy & Future Lab Expansion**:
  - [Detailed Safety Policy (docs/safety.en.md)](./docs/safety.en.md)
  - [Multi-Lab Expansion Roadmap (docs/roadmap.en.md)](./docs/roadmap.en.md)
