# Multi-Lab Expansion Roadmap (`docs/roadmap.en.md`)

**[🏠 English Root (README.en.md)](../README.en.md)** | **[🇯🇵 日本語 (roadmap.ja.md)](./roadmap.ja.md)** | **[🛡️ Safety Policy (safety.en.md)](./safety.en.md)** | **[🔬 Lab 01 (README.en.md)](../labs/01-php-upload-defense/README.en.md)**

---

## 1. Vision for Multi-Lab Expansion

**Upload-to-Execution Lab** is designed as an extensible threat-modeling platform focused on a recurring architectural problem: **how untrusted uploaded files cross trust boundaries across ingestion, storage, routing, transformation, and delivery to trigger unintended execution or privilege escalation**.

Instead of coupling the repository root to a single language stack, each lab lives in an independent `labs/<number>-<slug>/` directory with its own self-contained `compose.yaml`, stage implementations, harmless marker samples, automated verification suite, and bilingual workshop materials.

---

## 2. Current Lab Status

| Lab ID | Directory | Status | Core Threat & Defense Focus |
| :--- | :--- | :--- | :--- |
| **Lab 01** | [`labs/01-php-upload-defense/`](../labs/01-php-upload-defense/README.en.md) | **Available (v1.0)** | Unvalidated webroot upload on Apache + `mod_php` (Stage 1), naive `.php` blacklist vs. `.phtml` handler mismatch (Stage 2), and App + Infra Defense in Depth with outside-webroot isolation, safe delivery, and disabled `/uploads` script execution (Stage 3) |

---

## 3. Future Advanced Lab Candidates

Building upon the `Upload -> Store -> Serve -> Execute` mental model introduced in Lab 01, the following candidate labs explore real-world parser mismatches, file processing pipelines, and cloud-native storage boundaries:

### Candidate Lab 02: `labs/02-nginx-phpfpm-pathinfo/`
**Topic: Reverse Proxy / Web Server to FastCGI Backend Trust Boundary Mismatches (Nginx + PHP-FPM)**
- **Architectural Risks Explored**:
  - Path interpretation discrepancies when Nginx and PHP-FPM run as separate services—such as classic `cgi.fix_pathinfo=1` behavior or permissive `fastcgi_split_path_info` / `location ~ \.php$` routing causing an uploaded image URL like `/uploads/avatar.png/notfound.php` to be executed by PHP-FPM.
- **Defense-in-Depth Controls**:
  - Enforcing `cgi.fix_pathinfo=0`, `security.limit_extensions`, explicit `try_files $uri =404;` in Nginx, strict isolation between static asset locations and FastCGI upstream blocks, and outside-webroot storage.

### Candidate Lab 03: `labs/03-image-polyglot-reencoding/`
**Topic: Image Polyglots & Hardened Media Re-Encoding Pipelines**
- **Architectural Risks Explored**:
  - How valid-header "polyglot" images (possessing valid PNG/GIF/JPEG magic bytes and dimensions while embedding script payloads inside EXIF metadata or trailing chunks) pass `finfo` and `getimagesize()` checks.
  - Chained impact when combined with Local File Inclusion (LFI) or handler misconfigurations, plus parser attack surface inside media processing libraries (ImageMagick / GD / libvips).
- **Defense-in-Depth Controls**:
  - Full pixel-buffer re-encoding with complete metadata stripping, isolating media transcoding inside sandboxed low-privilege worker containers, and strict pixel-dimension / memory quotas against decompression bombs.

### Candidate Lab 04: `labs/04-archive-path-traversal-config-overwrite/`
**Topic: Path Traversal, Configuration Injection (`.htaccess` / `.user.ini`), and Zip Slip**
- **Architectural Risks Explored**:
  - Path traversal (`../`), path separator normalization edge cases, and symlink / relative-path archive extraction bugs (Zip Slip).
  - "Configuration Injection" chains where uploading `.htaccess` (Apache) or `.user.ini` (PHP-FPM) reconfigures the server to execute benign extensions like `.png` or `.txt` as server-side scripts.
- **Defense-in-Depth Controls**:
  - Never deriving storage paths from client filenames (server-generated UUIDs only), enforcing `AllowOverride None` and `user_ini.filename = ""`, canonical path prefix checks (`realpath`) during archive extraction, and rejecting symlinks inside archives.

### Candidate Lab 05: `labs/05-object-storage-presigned-url/`
**Topic: Object Storage Direct Uploads & Presigned URL Trust Boundaries (Local S3-Compatible MinIO)**
- **Architectural Risks Explored**:
  - Security pitfalls in direct-to-object-storage architectures using Presigned POST / PUT URLs: missing policy constraints on `Content-Type` or byte length, Stored XSS via uploaded `.svg` or `.html` served from a shared origin, and public bucket misconfigurations.
- **Defense-in-Depth Controls**:
  - Tight Presigned POST Policy conditions, two-bucket Quarantine -> Async Verification / Re-encoding Worker -> Public Delivery promotion pipelines, dedicated sandboxed content domains (isolated from session cookies) with `Content-Security-Policy: sandbox` and `Content-Disposition: attachment`.

---

## 4. Standard Directory Convention for New Labs

Every future lab should follow the same structure and developer experience established in Lab 01:

```text
labs/<NN>-<slug>/
├── README.md                  # Bilingual lab index
├── README.ja.md               # Architecture, DFDs & Threat-to-Mitigation matrix (Japanese)
├── README.en.md               # Architecture, DFDs & Threat-to-Mitigation matrix (English, 100% parity)
├── compose.yaml               # 127.0.0.1 bindings + internal: true + test profile verifier
├── stage1-.../                # Vulnerable baseline stage
├── stage2-.../                # Partial / insufficient single-layer defense & bypass
├── stage3-.../                # Hardened App + Infra defense-in-depth stage
├── samples/                   # Harmless fixed-marker sample files + samples/README.md
├── tests/                     # verify.* + host-smoke.ps1 + host-smoke.sh + tests/README.md
└── docs/
    ├── ja/                    # hands-on-guide.md / worksheet.md / lt-script.md
    └── en/                    # hands-on-guide.md / worksheet.md / lt-script.md
```

All contributed labs must strictly comply with Section 4 ("Contributor Safety Requirements") of [`docs/safety.en.md`](./safety.en.md)—even cloud-themed labs must run 100% locally via self-contained emulators (such as MinIO on an `internal: true` network) with zero external network traffic.
