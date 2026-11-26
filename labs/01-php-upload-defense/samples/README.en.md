[日本語](./README.md)

# Sample Test Files (`samples/`)

> [!CAUTION]
> FOR LOCAL EDUCATIONAL USE ONLY — DO NOT DEPLOY PUBLICLY. Read [SAFETY.en.md](../../../SAFETY.en.md) before running this lab.

Harmless verification files for testing Stage 1 through Stage 3 of Lab 01 safely from a browser or automated test runner.

## Files and Expected Stage Behavior

| File | Contents | Stage 1 (`:8081`) | Stage 2 (`:8082`) | Stage 3 (`:8083`) |
| --- | --- | --- | --- | --- |
| `sample-valid.png` | Valid PNG image (64×64) | Accepted & served (`200`) | Accepted & served (`200`) | Accepted (`200`), renamed to `UUIDv4`, stored outside webroot, served via `/image.php` |
| `marker-basic.php` | Fixed marker (`LAB_EXECUTION_MARKER_STAGE1`) | Accepted & executes (`200`) | Blocked by `.php` denylist (`400`) | Blocked by extension allowlist (`400`) |
| `marker-bypass.phtml` | Fixed marker (`LAB_EXECUTION_MARKER_STAGE2`) | Accepted & executes (`200`) | **Bypass — executes (`200`)** | Blocked by extension allowlist (`400`) |

## Safety Design

- Marker files never read external input (no query parameters, POST fields, cookies, or upload payloads).
- Zero OS command calls or dynamic code evaluation primitives are included.
- Output is strictly limited to a fixed marker string and read-only runtime info (PHP version, `www-data` user, container hostname, UTC timestamp).
