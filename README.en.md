# Upload-to-Execution Lab

[日本語](./README.md) | [Safety Policy](./SAFETY.en.md) | [Glossary](./docs/glossary.en.md) | [Roadmap](./docs/roadmap.en.md)

> [!CAUTION]
> This repository contains intentionally vulnerable applications strictly for local security education. Never deploy it to public servers or shared networks; read [SAFETY.en.md](./SAFETY.en.md) before use.

## Purpose

A hands-on threat modeling lab demonstrating how file upload features lead to server-side code execution (`Upload -> Store -> Serve -> Execute`) and how Defense in Depth stops the chain.

- **Trace the Attack Chain**: Map how untrusted uploads cross trust boundaries and get interpreted as executable code by the Web server.
- **See Why Single-Layer Filters Fail**: Observe how a naive `.php` denylist in application code is bypassed when Apache also maps `.phtml` to PHP.
- **Apply App + Infra Defense in Depth**: Combine strict allowlisting, content inspection, and outside-webroot isolation with Apache execution disabling and direct-access denial.

## Target Audience

- Web and infrastructure engineers examining security boundaries between application code and server handlers
- Study group facilitators running 60–90 minute offline-capable hands-on workshops
- Lightning Talk speakers delivering a 5–10 minute live demo of upload exploitation and mitigation
- Learners practicing Data Flow Diagram threat modeling using structured worksheets

## Repository Structure

```text
upload-to-execution-lab/
├── README.md                              # Root guide in Japanese (README.en.md: English)
├── SAFETY.md                              # Safety policy in Japanese (SAFETY.en.md: English)
├── LICENSE                                # MIT License
├── docs/
│   ├── glossary.md                        # Glossary in Japanese (glossary.en.md: English)
│   └── roadmap.md                         # Lab roadmap in Japanese (roadmap.en.md: English)
└── labs/
    └── 01-php-upload-defense/
        ├── README.md                      # Architecture & DFDs in Japanese (README.en.md: English)
        ├── compose.yaml                   # 3 stages + verifier container definition
        ├── stage1-vulnerable/             # Stage 1 (8081): Unvalidated upload
        ├── stage2-app-only/               # Stage 2 (8082): Naive app-only denylist
        ├── stage3-defense-in-depth/       # Stage 3 (8083): App + Infra defense in depth
        ├── samples/                       # Harmless verification sample files
        ├── tests/                         # Automated E2E verification (verify.php)
        └── docs/
            ├── ja/                        # Japanese workshop guide, worksheet & LT script
            └── en/                        # English workshop guide, worksheet & LT script
```

## System Requirements

| Item | Requirement |
| :--- | :--- |
| **Container Runtime** | Docker Desktop or Docker Engine with `docker compose` v2 |
| **Supported OS** | Windows 11/10 (WSL2 recommended), macOS (Intel & Apple Silicon), Linux |
| **Local Ports** | `127.0.0.1:8081`, `127.0.0.1:8082`, `127.0.0.1:8083` free on localhost |
| **Connectivity** | Internet needed only once during initial build; runs 100% offline afterward |

## Quickstart (Lab 01)

```bash
cd labs/01-php-upload-defense
docker compose up --build -d
```

All stages bind strictly to localhost (`?lang=ja` / `?lang=en` toggle and one-click Reset included):

| Stage | Local URL | Key Behavior |
| :--- | :--- | :--- |
| **Stage 1** | `http://127.0.0.1:8081/?lang=en` | No validation; saves inside `DocumentRoot`; `marker-basic.php` executes |
| **Stage 2** | `http://127.0.0.1:8082/?lang=en` | Blocks `.php` (`HTTP 400`), but `marker-bypass.phtml` bypasses filter and executes |
| **Stage 3** | `http://127.0.0.1:8083/?lang=en` | Image allowlist, content checks, outside-webroot storage, safe delivery, and Apache execution off |

Run automated verification and stop containers when finished:

```bash
# Run end-to-end automated checks across all stages
docker compose --profile test run --rm verifier

# Stop and remove containers
docker compose down
```

## Safety Summary

See [SAFETY.en.md](./SAFETY.en.md) for complete technical specifications.

- **Loopback-Only Binding**: All ports explicitly bind `127.0.0.1`, blocking access from LAN neighbors.
- **Outbound Network Isolation**: Docker network `lab_net` sets `internal: true`, preventing internet egress from containers.
- **Non-Root Containers**: Apache and PHP run as unprivileged user `www-data` on internal port `8080`.
- **Harmless Fixed-Marker Payloads**: Sample scripts output static markers and read-only runtime info only—zero user input reading and zero OS command execution functions.

## Documentation Map

- **Lab 01 Architecture & Threat Modeling**: [labs/01-php-upload-defense/README.en.md](./labs/01-php-upload-defense/README.en.md)
- **Lab 01 Workshop & LT Materials**:
  - [Hands-On Workshop Guide](./labs/01-php-upload-defense/docs/en/hands-on-guide.md)
  - [Threat Modeling Worksheet](./labs/01-php-upload-defense/docs/en/worksheet.md)
  - [Lightning Talk Demo Script](./labs/01-php-upload-defense/docs/en/lt-script.md)
- **Samples & Test Suite**: [samples/README.en.md](./labs/01-php-upload-defense/samples/README.en.md) | [tests/README.en.md](./labs/01-php-upload-defense/tests/README.en.md)
- **Glossary, Safety Policy & Roadmap**: [docs/glossary.en.md](./docs/glossary.en.md) | [SAFETY.en.md](./SAFETY.en.md) | [docs/roadmap.en.md](./docs/roadmap.en.md) | [LICENSE](./LICENSE)
