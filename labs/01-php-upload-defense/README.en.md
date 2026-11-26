# Lab 01: File Upload Defense & Defense in Depth on PHP + Apache

[日本語](./README.md) | [Root Guide](../../README.en.md) | [Safety Policy](../../SAFETY.en.md) | [Roadmap](../../docs/roadmap.en.md)

> [!CAUTION]
> Stage 1 (`8081`) and Stage 2 (`8082`) are intentionally vulnerable environments strictly for local study. Never expose them on public servers or shared networks; follow [SAFETY.en.md](../../SAFETY.en.md).

## 1. Learning Objectives

1. **Structure the Attack Chain**: Explain why server-side code execution requires all four phases (`Upload -> Store -> Serve -> Execute`) to align.
2. **Distrust Client Metadata**: Explain why filenames, extensions, and client-supplied `Content-Type` headers are trivially spoofed untrusted inputs.
3. **Spot Denylist Gaps**: Explain why blocking `.php` in application code still fails when Apache maps `.phtml` to the PHP interpreter.
4. **Build Defense in Depth**: Combine Application controls (allowlist, server-side content checks, `UUIDv4` rename, outside-webroot isolation, controlled delivery) with Infrastructure controls (`AllowOverride None`, PHP engine off, direct access denied) to eliminate single points of failure.

## 2. Quickstart & Automated Verification

```bash
cd labs/01-php-upload-defense
docker compose up --build -d
```

All stages run Apache + PHP 8.3 (internal port `8080`, non-root user `www-data`, `internal: true` isolated network) bound strictly to `127.0.0.1`:

| Stage | Local URL | Language Toggle | State Reset |
| :--- | :--- | :--- | :--- |
| **Stage 1** | `http://127.0.0.1:8081` | `?lang=ja` / `?lang=en` | UI Reset button (`POST /reset.php`) |
| **Stage 2** | `http://127.0.0.1:8082` | `?lang=ja` / `?lang=en` | UI Reset button (`POST /reset.php`) |
| **Stage 3** | `http://127.0.0.1:8083` | `?lang=ja` / `?lang=en` | UI Reset button (`POST /reset.php`) |

### Sample Payloads ([`samples/README.en.md`](./samples/README.en.md))

All marker scripts output fixed strings and read-only runtime info only—zero user input reading and zero OS command execution functions.

| File | Purpose | Outcome Across Stages |
| :--- | :--- | :--- |
| [`samples/sample-valid.png`](./samples/sample-valid.png) | Valid PNG image | Accepted and displayed in Stages 1, 2, and 3 |
| [`samples/marker-basic.php`](./samples/marker-basic.php) | `.php` execution marker | Executes in Stage 1 (`LAB_EXECUTION_MARKER_STAGE1`); blocked (`HTTP 400`) in Stages 2 & 3 |
| [`samples/marker-bypass.phtml`](./samples/marker-bypass.phtml) | `.phtml` bypass marker | Bypasses filter and executes in Stage 2 (`LAB_EXECUTION_MARKER_STAGE2`); blocked (`HTTP 400`) in Stage 3 |

### Automated Verification & Teardown ([`tests/README.en.md`](./tests/README.en.md))

```bash
# Verify stage behaviors, security headers, and static safety rules
docker compose --profile test run --rm verifier

# Stop and remove containers
docker compose down
```

## 3. Data Flow Diagrams & Trust Boundary Analysis

### 3.1 Stage 1 (`127.0.0.1:8081`): Unvalidated Webroot Upload

Stage 1 performs no extension or content checks, applying only `basename()` before writing directly into `/var/www/html/uploads/{name}` inside `DocumentRoot`.

```mermaid
flowchart LR
    subgraph Client1["Untrusted Zone: Browser (127.0.0.1)"]
        U1["User / Attacker"]
    end

    subgraph Server1["Trust Boundary: stage1-vulnerable (Apache + PHP 8.3 / www-data)"]
        UP1["1. Upload: POST /upload.php (Unvalidated)"]
        ST1["2. Store: /var/www/html/uploads/{name} (In DocumentRoot)"]
        SV1["3. Serve: GET /uploads/marker-basic.php"]
        EX1["4. Execute: Apache runs PHP -> LAB_EXECUTION_MARKER_STAGE1"]
    end

    U1 -->|"Upload marker-basic.php"| UP1
    UP1 -->|"Keep original .php extension"| ST1
    U1 -->|"Direct URL request"| SV1
    ST1 -->|"Publicly routable path"| SV1
    SV1 -->|"Routed to PHP handler"| EX1
    EX1 -->|"Executed output"| U1
```

**Why Stage 1 Executes**:
- Saved on disk with an attacker-controlled `.php` extension.
- Stored inside `DocumentRoot`, making it directly reachable by URL.
- Served from a directory where Apache's PHP handler is active, turning uploaded data into executed code.

### 3.2 Stage 2 (`127.0.0.1:8082`): Naive App Denylist vs. Handler Mismatch

Stage 2 rejects `.php` in PHP code (`HTTP 400`), yet still stores files in `/var/www/html/uploads/{name}` while Apache maps both `.php` and `.phtml` to PHP.

```mermaid
flowchart LR
    subgraph Client2["Untrusted Zone: Browser / HTTP Client (127.0.0.1)"]
        U2["User / Attacker"]
    end

    subgraph Server2["Trust Boundary: stage2-app-only (Apache + PHP 8.3 / www-data)"]
        subgraph App2["Application Layer (upload.php)"]
            BL2["1. Check: Rejects .php only (HTTP 400)"]
        end
        subgraph Infra2["Webroot & Apache Handler Layer"]
            ST2["2. Store: /var/www/html/uploads/marker-bypass.phtml"]
            SV2["3. Serve: GET /uploads/marker-bypass.phtml"]
            EX2["4. Execute: Apache runs .phtml -> LAB_EXECUTION_MARKER_STAGE2"]
        end
    end

    U2 -->|"marker-basic.php -> Blocked (HTTP 400)"| BL2
    U2 -->|"marker-bypass.phtml -> Passes check"| BL2
    BL2 -->|"Saved in DocumentRoot"| ST2
    U2 -->|"Direct URL request"| SV2
    ST2 -->|"Exposed under /uploads/"| SV2
    SV2 -->|".phtml mapped to PHP"| EX2
    EX2 -->|"Executed output"| U2
```

**Why Stage 2 Fails**:
- **App vs. Server Handler Mismatch**: The PHP filter assumes only `.php` executes, whereas Apache's `<FilesMatch "\.(php|phtml)$">` also executes `.phtml`. Denylists fail whenever any server-handled extension or `.htaccess` override is missed.
- **Untrusted Client `Content-Type`**: Checking the request's `Content-Type: image/png` header adds no security because any client (`curl`) can spoof it in a single command.

### 3.3 Stage 3 (`127.0.0.1:8083`): App + Infra Defense in Depth

Stage 3 places independent controls across all four phases and locks down `/var/www/html/uploads/` at the Apache layer (verified via the pre-seeded probe `infra-guard-check.phtml`).

```mermaid
flowchart TD
    subgraph Client3["Untrusted Zone: Browser / HTTP Client (127.0.0.1)"]
        U3["User / Attacker"]
    end

    subgraph Server3["Trust Boundary: stage3-defense-in-depth (Apache + PHP 8.3 / www-data)"]
        subgraph App3["Application Controls"]
            V3["1. Validate (upload.php): 4-ext allowlist + 2MB cap + finfo + getimagesize()"]
            R3["2. Sanitize Name: Server UUIDv4 ({uuid}.{ext})"]
            DL3["3. Safe Delivery (image.php): UUID regex + nosniff + CSP + X-Frame-Options"]
        end

        subgraph Store3["Isolated Storage (Outside DocumentRoot)"]
            ISO3["/var/lab-storage/uploads/{uuid}.{ext} (No direct URL)"]
        end

        subgraph Infra3["Infrastructure Controls (Apache Config)"]
            IG3["/var/www/html/uploads/infra-guard-check.phtml (AllowOverride None + PHP off + Denied)"]
        end
    end

    U3 -->|"Script / spoofed PNG -> Blocked (HTTP 400)"| V3
    U3 -->|"sample-valid.png -> Valid"| V3
    V3 -->|"Discard client filename"| R3
    R3 -->|"Write outside webroot"| ISO3
    U3 -->|"GET /image.php?file={uuid}.png"| DL3
    ISO3 -->|"Stream raw bytes via readfile()"| DL3
    DL3 -->|"200 OK (Image bytes + security headers)"| U3
    U3 -->|"GET /uploads/infra-guard-check.phtml"| IG3
    IG3 -->|"HTTP 403 Forbidden (PHP never runs)"| U3
```

**How Stage 3 Breaks the Chain**:
- **Upload Validation**: Enforces a 4-extension allowlist (`png`, `jpg`, `jpeg`, `gif`), 2MB cap, `finfo(FILEINFO_MIME_TYPE)` magic-byte inspection, and `getimagesize()` structural checks.
- **Outside-Webroot Storage & Renaming**: Replaces client filenames with random `UUIDv4.<ext>` names and stores files in `/var/lab-storage/uploads/` outside `DocumentRoot`, removing direct URL routing.
- **Controlled Delivery**: Streams bytes via `/image.php?file=<uuid>.<ext>` after strict UUID regex validation, attaching `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`, and `X-Frame-Options: DENY`.
- **Independent Apache Hardening**: Enforces `AllowOverride None`, disables PHP execution and CGI/Indexes, and sets `Require all denied` on `/var/www/html/uploads/` so `GET /uploads/infra-guard-check.phtml` returns `HTTP 403` without executing PHP.

## 4. Threat-to-Mitigation Matrix

| Phase | Threat Vector | Stage 1 | Stage 2 | Stage 3: App Layer | Stage 3: Infra Layer |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Upload** | Direct `.php` upload (`marker-basic.php`) | Executes | Blocked (400) | Blocked by allowlist (400) | PHP off & access denied |
| **Upload** | Alternate extension (`marker-bypass.phtml`) | Executes | Bypassed & Executes | Blocked by allowlist (400) | PHP off & denied (403) |
| **Upload** | Spoofed `Content-Type` or PHP disguised as `.png` | Accepted | Accepted if non-`.php` | Rejected by `finfo` + `getimagesize()` (400) | Not routed to PHP |
| **Upload** | Oversized upload resource exhaustion | Uncapped | Uncapped | Blocked above 2MB (400) | Contained in non-root container |
| **Store** | Attacker-chosen filename / path traversal | `basename()` only | `basename()` only | Forced `UUIDv4.<ext>` rename | Minimal `www-data` write perms |
| **Store** | `.htaccess` handler override upload | Writable in webroot | Allowed (not `.php`) | Allowlist block + outside webroot | Ignored via `AllowOverride None` |
| **Serve** | Direct URL navigation to stored upload | Reachable at `/uploads/<name>` | Reachable at `/uploads/<name>` | Outside webroot; `/image.php` only | `/uploads` returns `HTTP 403` |
| **Serve** | Browser MIME sniffing / embedded script execution | No security headers | No security headers | `nosniff` + `CSP: default-src 'none'` + `DENY` | Hardened header defaults |
| **Execute** | Server-side PHP execution of uploaded file | Executed by `mod_php` | `.phtml` executed | Streamed as raw bytes (`readfile()`) | PHP engine disabled & `-ExecCGI` |

## 5. Companion Materials

- **Hands-On Workshop Guide (60–90 min + `curl` spoofing appendix)**: [docs/en/hands-on-guide.md](./docs/en/hands-on-guide.md)
- **Participant Threat Modeling Worksheet (with answer key)**: [docs/en/worksheet.md](./docs/en/worksheet.md)
- **Lightning Talk Demo Script (5-min & 10-min)**: [docs/en/lt-script.md](./docs/en/lt-script.md)
- **Samples & Automated Verifier Reference**: [samples/README.en.md](./samples/README.en.md) | [tests/README.en.md](./tests/README.en.md)
- **Future Multi-Lab Candidates**: [../../docs/roadmap.en.md](../../docs/roadmap.en.md)
