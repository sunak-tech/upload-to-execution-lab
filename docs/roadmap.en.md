# Multi-Lab Expansion Roadmap

[日本語](./roadmap.md) | [Root Guide](../README.en.md) | [Safety Policy](../SAFETY.en.md) | [Lab 01](../labs/01-php-upload-defense/README.en.md)

> [!CAUTION]
> Every lab in this repository is strictly for local educational use. Follow all guardrails in [SAFETY.en.md](../SAFETY.en.md) when running or contributing labs.

## 1. Design Approach

To explore how untrusted uploaded files cross trust boundaries across ingestion, storage, routing, transformation, and delivery, each lab is self-contained under `labs/<number>-<slug>/` with its own isolated containers, harmless marker payloads, automated verifier, and workshop materials.

## 2. Available Labs

| Lab ID | Directory | Status | Core Focus |
| :--- | :--- | :--- | :--- |
| **Lab 01** | [`labs/01-php-upload-defense/`](../labs/01-php-upload-defense/README.en.md) | Available | Unvalidated webroot upload on Apache + `mod_php` (Stage 1), `.php` denylist vs. `.phtml` handler mismatch (Stage 2), and App + Infra Defense in Depth with outside-webroot storage and disabled script execution (Stage 3) |

## 3. Future Lab Candidates

### Candidate Lab 02: `labs/02-nginx-phpfpm-pathinfo/`
**Topic: Path Interpretation Mismatches Between Nginx and PHP-FPM**
- **Structural Risk**: URL routing discrepancies between a reverse proxy and FastCGI backend (such as `cgi.fix_pathinfo=1` or permissive `location ~ \.php$` blocks) causing `/uploads/avatar.png/notfound.php` to execute as PHP.
- **Defense-in-Depth Controls**: Enforce `cgi.fix_pathinfo=0`, `security.limit_extensions`, `try_files $uri =404;` in Nginx, strict separation of static locations from FastCGI upstreams, and outside-webroot storage.

### Candidate Lab 03: `labs/03-image-polyglot-reencoding/`
**Topic: Image Polyglots and Media Re-Encoding Pipelines**
- **Structural Risk**: Valid-header PNG/JPEG polyglots embedding code fragments in EXIF or trailing chunks that pass `finfo` and `getimagesize()`, chaining with file inclusion bugs or media parser vulnerabilities.
- **Defense-in-Depth Controls**: Re-encode images from raw pixel buffers while stripping all metadata, isolate transcoding in low-privilege worker containers, and enforce dimension/memory caps.

### Candidate Lab 04: `labs/04-archive-path-traversal-config-overwrite/`
**Topic: Path Traversal, Configuration Injection (`.htaccess` / `.user.ini`), and Archive Extraction**
- **Structural Risk**: Arbitrary file overwrites via relative paths or symlinks during archive extraction, plus `.htaccess` / `.user.ini` uploads that reconfigure benign extensions to execute as server-side code.
- **Defense-in-Depth Controls**: Assign server-generated `UUIDv4` filenames, enforce `AllowOverride None` and `user_ini.filename = ""`, validate canonical path prefixes on extraction, and reject symlinks.

### Candidate Lab 05: `labs/05-object-storage-presigned-url/`
**Topic: Direct-to-Object-Storage Uploads and Presigned URL Trust Boundaries (Local MinIO)**
- **Structural Risk**: Missing Presigned POST policy constraints, Stored XSS via `.svg` / `.html` served on a shared origin, and public bucket misconfigurations.
- **Defense-in-Depth Controls**: Strict Presigned POST conditions, Quarantine -> Async Verification / Re-encoding -> Delivery bucket promotion, dedicated sandbox domains, and `Content-Security-Policy` / `Content-Disposition: attachment`.

## 4. Standard Lab Layout

New labs follow the Lab 01 directory structure and must satisfy every requirement in [SAFETY.en.md](../SAFETY.en.md) (cloud-style workflows must use local `internal: true` emulators such as MinIO with zero internet egress).

```text
labs/<NN>-<slug>/
├── README.md                  # Architecture & DFDs in Japanese (README.en.md: English)
├── compose.yaml               # 127.0.0.1 bindings + internal: true + verifier
├── stage1-.../                # Vulnerable baseline implementation
├── stage2-.../                # Insufficient single-layer defense & bypass
├── stage3-.../                # App + Infra defense-in-depth implementation
├── samples/                   # Harmless fixed-marker payloads (README.md / README.en.md)
├── tests/                     # verify.* automated checks (README.md / README.en.md)
└── docs/
    ├── ja/                    # hands-on-guide.md / worksheet.md / lt-script.md
    └── en/                    # hands-on-guide.md / worksheet.md / lt-script.md
```
