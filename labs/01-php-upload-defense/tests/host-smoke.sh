#!/bin/sh
# Host-side POSIX sh/bash end-to-end smoke test for Lab 01 (127.0.0.1:8081..8083).

set -eu

STAGE1_URL="${STAGE1_URL:-http://127.0.0.1:8081}"
STAGE2_URL="${STAGE2_URL:-http://127.0.0.1:8082}"
STAGE3_URL="${STAGE3_URL:-http://127.0.0.1:8083}"

STAGE1_URL="${STAGE1_URL%/}"
STAGE2_URL="${STAGE2_URL%/}"
STAGE3_URL="${STAGE3_URL%/}"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
LAB_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)"
SAMPLES_DIR="$LAB_ROOT/samples"

PASS_COUNT=0
FAIL_COUNT=0

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT INT TERM

pass() {
    PASS_COUNT=$((PASS_COUNT + 1))
    printf '[PASS] %s\n' "$1"
}

fail() {
    FAIL_COUNT=$((FAIL_COUNT + 1))
    printf '[FAIL] %s\n' "$1"
}

is_reset_ok() {
    case "$1" in
        200|204|302|303) return 0 ;;
        *) return 1 ;;
    esac
}

is_png_file() {
    magic="$(od -An -tx1 -N8 "$1" 2>/dev/null | tr -d ' \n')"
    [ "$magic" = "89504e470d0a1a0a" ]
}

printf '==================================================================\n'
printf ' Lab 01 Host Smoke Test (POSIX sh + curl)\n'
printf '==================================================================\n'
printf 'Stage 1 : %s\n' "$STAGE1_URL"
printf 'Stage 2 : %s\n' "$STAGE2_URL"
printf 'Stage 3 : %s\n' "$STAGE3_URL"
printf '------------------------------------------------------------------\n'

# 1. Bilingual UI & Safety Banners
for pair in "Stage1|$STAGE1_URL" "Stage2|$STAGE2_URL" "Stage3|$STAGE3_URL"; do
    name="${pair%%|*}"
    url="${pair#*|}"

    code_ja="$(curl -sS -o "$TMP_DIR/ja.html" -w '%{http_code}' "$url/?lang=ja")"
    if [ "$code_ja" = "200" ] && grep -q 'ローカル学習専用' "$TMP_DIR/ja.html"; then
        pass "$name: GET /?lang=ja returns 200 with JP safety warning"
    else
        fail "$name: GET /?lang=ja failed (HTTP $code_ja)"
    fi

    code_en="$(curl -sS -o "$TMP_DIR/en.html" -w '%{http_code}' "$url/?lang=en")"
    if [ "$code_en" = "200" ] && grep -qi 'FOR LOCAL EDUCATIONAL USE ONLY' "$TMP_DIR/en.html"; then
        pass "$name: GET /?lang=en returns 200 with EN safety warning"
    else
        fail "$name: GET /?lang=en failed (HTTP $code_en)"
    fi
done

# 2. Stage 1
code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$STAGE1_URL/reset.php")"
if is_reset_ok "$code"; then
    pass "Stage 1: POST /reset.php succeeds (HTTP $code)"
else
    fail "Stage 1: POST /reset.php unexpected HTTP $code"
fi

up_png1="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$SAMPLES_DIR/sample-valid.png;type=image/png" "$STAGE1_URL/upload.php")"
get_png1="$(curl -sS -o "$TMP_DIR/s1.png" -w '%{http_code}' "$STAGE1_URL/uploads/sample-valid.png")"
if [ "$up_png1" = "200" ] && [ "$get_png1" = "200" ] && is_png_file "$TMP_DIR/s1.png"; then
    pass "Stage 1: sample-valid.png uploads and serves valid PNG bytes"
else
    fail "Stage 1: sample-valid.png check failed (upload=$up_png1, get=$get_png1)"
fi

up_php1="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$SAMPLES_DIR/marker-basic.php;type=application/octet-stream" "$STAGE1_URL/upload.php")"
get_php1="$(curl -sS -o "$TMP_DIR/s1_marker.out" -w '%{http_code}' "$STAGE1_URL/uploads/marker-basic.php")"
if [ "$up_php1" = "200" ] && [ "$get_php1" = "200" ] && grep -q 'LAB_EXECUTION_MARKER_STAGE1' "$TMP_DIR/s1_marker.out" && ! grep -q '<?php' "$TMP_DIR/s1_marker.out"; then
    pass "Stage 1: marker-basic.php executes and outputs LAB_EXECUTION_MARKER_STAGE1"
else
    fail "Stage 1: marker-basic.php execution check failed (upload=$up_php1, get=$get_php1)"
fi

code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$STAGE1_URL/reset.php")"
after_s1="$(curl -sS -o /dev/null -w '%{http_code}' "$STAGE1_URL/uploads/marker-basic.php")"
if is_reset_ok "$code" && [ "$after_s1" = "404" ]; then
    pass "Stage 1: reset removes marker-basic.php (HTTP 404)"
else
    fail "Stage 1: reset cleanup failed (reset=$code, get=$after_s1)"
fi

# 3. Stage 2
code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$STAGE2_URL/reset.php")"
if is_reset_ok "$code"; then
    pass "Stage 2: POST /reset.php succeeds (HTTP $code)"
else
    fail "Stage 2: POST /reset.php unexpected HTTP $code"
fi

up_php2="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$SAMPLES_DIR/marker-basic.php;type=application/octet-stream" "$STAGE2_URL/upload.php")"
if [ "$up_php2" = "400" ]; then
    pass "Stage 2: marker-basic.php blocked with HTTP 400"
else
    fail "Stage 2: marker-basic.php expected HTTP 400, got $up_php2"
fi

up_phtml2="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$SAMPLES_DIR/marker-bypass.phtml;type=application/octet-stream" "$STAGE2_URL/upload.php")"
get_phtml2="$(curl -sS -o "$TMP_DIR/s2_marker.out" -w '%{http_code}' "$STAGE2_URL/uploads/marker-bypass.phtml")"
if [ "$up_phtml2" = "200" ] && [ "$get_phtml2" = "200" ] && grep -q 'LAB_EXECUTION_MARKER_STAGE2' "$TMP_DIR/s2_marker.out" && ! grep -q '<?php' "$TMP_DIR/s2_marker.out"; then
    pass "Stage 2: marker-bypass.phtml uploads and executes LAB_EXECUTION_MARKER_STAGE2"
else
    fail "Stage 2: marker-bypass.phtml execution check failed (upload=$up_phtml2, get=$get_phtml2)"
fi

code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$STAGE2_URL/reset.php")"
after_s2="$(curl -sS -o /dev/null -w '%{http_code}' "$STAGE2_URL/uploads/marker-bypass.phtml")"
if is_reset_ok "$code" && [ "$after_s2" = "404" ]; then
    pass "Stage 2: reset removes marker-bypass.phtml (HTTP 404)"
else
    fail "Stage 2: reset cleanup failed (reset=$code, get=$after_s2)"
fi

# 4. Stage 3
code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$STAGE3_URL/reset.php")"
if is_reset_ok "$code"; then
    pass "Stage 3: POST /reset.php succeeds (HTTP $code)"
else
    fail "Stage 3: POST /reset.php unexpected HTTP $code"
fi

up_php3="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$SAMPLES_DIR/marker-basic.php;type=application/octet-stream" "$STAGE3_URL/upload.php")"
up_phtml3="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$SAMPLES_DIR/marker-bypass.phtml;type=application/octet-stream" "$STAGE3_URL/upload.php")"
printf '<?php echo "SPOOF"; ?>' > "$TMP_DIR/evil.png"
up_spoof3="$(curl -sS -o /dev/null -w '%{http_code}' -F "file=@$TMP_DIR/evil.png;filename=evil.png;type=image/png" "$STAGE3_URL/upload.php")"

[ "$up_php3" = "400" ] && pass "Stage 3: marker-basic.php rejected with HTTP 400" || fail "Stage 3: marker-basic.php got HTTP $up_php3"
[ "$up_phtml3" = "400" ] && pass "Stage 3: marker-bypass.phtml rejected with HTTP 400" || fail "Stage 3: marker-bypass.phtml got HTTP $up_phtml3"
[ "$up_spoof3" = "400" ] && pass "Stage 3: spoofed evil.png rejected with HTTP 400" || fail "Stage 3: spoofed evil.png got HTTP $up_spoof3"

up_png3="$(curl -sS -o "$TMP_DIR/s3_upload.html" -w '%{http_code}' -F "file=@$SAMPLES_DIR/sample-valid.png;type=image/png" "$STAGE3_URL/upload.php")"
uuid_file="$(grep -Eo 'image\.php\?file=[0-9a-fA-F-]+\.png' "$TMP_DIR/s3_upload.html" | head -n 1 | sed 's/^image\.php?file=//' || true)"

if [ "$up_png3" = "200" ] && [ -n "$uuid_file" ]; then
    pass "Stage 3: valid sample-valid.png accepted and renamed to $uuid_file"
    get_img3="$(curl -sS -D "$TMP_DIR/s3_headers.txt" -o "$TMP_DIR/s3_out.png" -w '%{http_code}' "$STAGE3_URL/image.php?file=$uuid_file")"
    if [ "$get_img3" = "200" ] \
        && is_png_file "$TMP_DIR/s3_out.png" \
        && grep -qi '^x-content-type-options:.*nosniff' "$TMP_DIR/s3_headers.txt" \
        && grep -qi '^content-security-policy:' "$TMP_DIR/s3_headers.txt"; then
        pass "Stage 3: /image.php?file=$uuid_file returns 200 with PNG bytes, nosniff, and CSP"
    else
        fail "Stage 3: /image.php verification failed (HTTP $get_img3)"
    fi
else
    fail "Stage 3: sample-valid.png upload or UUID extraction failed (HTTP $up_png3)"
fi

guard_code="$(curl -sS -o "$TMP_DIR/guard1.out" -w '%{http_code}' "$STAGE3_URL/uploads/infra-guard-check.phtml")"
if [ "$guard_code" = "403" ] && ! grep -q 'INFRA_LAYER_FAILED_EXECUTION' "$TMP_DIR/guard1.out"; then
    pass "Stage 3: GET /uploads/infra-guard-check.phtml blocked with HTTP 403"
else
    fail "Stage 3: infra-guard-check.phtml expected 403, got $guard_code"
fi

code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$STAGE3_URL/reset.php")"
if [ -n "$uuid_file" ]; then
    after_img3="$(curl -sS -o /dev/null -w '%{http_code}' "$STAGE3_URL/image.php?file=$uuid_file")"
    if is_reset_ok "$code" && [ "$after_img3" = "404" ]; then
        pass "Stage 3: reset removes uploaded UUID PNG (HTTP 404)"
    else
        fail "Stage 3: reset did not remove UUID PNG (reset=$code, get=$after_img3)"
    fi
else
    fail "Stage 3: skipped post-reset UUID PNG 404 check"
fi

guard_after="$(curl -sS -o "$TMP_DIR/guard2.out" -w '%{http_code}' "$STAGE3_URL/uploads/infra-guard-check.phtml")"
if [ "$guard_after" = "403" ] && ! grep -q 'INFRA_LAYER_FAILED_EXECUTION' "$TMP_DIR/guard2.out"; then
    pass "Stage 3: infra-guard-check.phtml remains HTTP 403 after reset"
else
    fail "Stage 3: infra-guard-check.phtml post-reset expected 403, got $guard_after"
fi

TOTAL=$((PASS_COUNT + FAIL_COUNT))
printf '==================================================================\n'
printf ' Summary: %d/%d passed, %d failed\n' "$PASS_COUNT" "$TOTAL" "$FAIL_COUNT"
printf '==================================================================\n'

if [ "$FAIL_COUNT" -gt 0 ]; then
    exit 1
fi
printf 'ALL HOST SMOKE CHECKS PASSED.\n'
exit 0
