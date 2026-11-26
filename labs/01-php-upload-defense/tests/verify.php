<?php
declare(strict_types=1);

/**
 * Lab 01 (PHP Upload Defense) - Automated End-to-End Verification Suite
 *
 * Zero external extension dependencies beyond standard PHP 8.3 core.
 * Default targets use internal Docker Compose service hostnames on lab_net,
 * overridable via STAGE1_URL, STAGE2_URL, and STAGE3_URL environment variables.
 */

$stage1Url = rtrim(getenv('STAGE1_URL') ?: 'http://stage1-vulnerable:8080', '/');
$stage2Url = rtrim(getenv('STAGE2_URL') ?: 'http://stage2-app-only:8080', '/');
$stage3Url = rtrim(getenv('STAGE3_URL') ?: 'http://stage3-defense-in-depth:8080', '/');

$labRoot = is_dir('/workspace/samples') ? '/workspace' : dirname(__DIR__);
$samplesDir = $labRoot . '/samples';

$passCount = 0;
$failCount = 0;
$failures = [];

function recordCheck(bool $condition, string $label, string $detail = ''): bool
{
    global $passCount, $failCount, $failures;
    if ($condition) {
        $passCount++;
        echo "[PASS] {$label}" . ($detail !== '' ? " ({$detail})" : '') . PHP_EOL;
        return true;
    }
    $failCount++;
    $msg = $label . ($detail !== '' ? " — {$detail}" : '');
    $failures[] = $msg;
    echo "[FAIL] {$msg}" . PHP_EOL;
    return false;
}

/**
 * Perform an HTTP request using pure PHP stream contexts.
 *
 * @param string               $method
 * @param string               $url
 * @param array<string,string> $headers
 * @param string               $body
 * @return array{status:int, headers:array<string,string>, raw_headers:list<string>, body:string, error:string}
 */
function httpRequest(string $method, string $url, array $headers = [], string $body = ''): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = "{$name}: {$value}";
    }
    if ($body !== '' && !isset($headers['Content-Length']) && !isset($headers['content-length'])) {
        $headerLines[] = 'Content-Length: ' . strlen($body);
    }

    $context = stream_context_create([
        'http' => [
            'method'          => $method,
            'header'          => implode("\r\n", $headerLines),
            'content'         => $body,
            'ignore_errors'   => true,
            'follow_location' => 0,
            'timeout'         => 10,
            'protocol_version'=> 1.1,
        ],
    ]);

    $http_response_header = [];
    $error = '';
    set_error_handler(static function (int $severity, string $message) use (&$error): bool {
        $error = $message;
        return true;
    });
    $responseBody = file_get_contents($url, false, $context);
    restore_error_handler();

    if ($responseBody === false) {
        $responseBody = '';
    }

    $status = 0;
    $parsedHeaders = [];
    $rawHeaders = is_array($http_response_header) ? array_values($http_response_header) : [];

    foreach ($rawHeaders as $index => $line) {
        if ($index === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos !== false) {
            $hName = strtolower(trim(substr($line, 0, $pos)));
            $hVal = trim(substr($line, $pos + 1));
            if (isset($parsedHeaders[$hName])) {
                $parsedHeaders[$hName] .= ', ' . $hVal;
            } else {
                $parsedHeaders[$hName] = $hVal;
            }
        }
    }

    return [
        'status'      => $status,
        'headers'     => $parsedHeaders,
        'raw_headers' => $rawHeaders,
        'body'        => $responseBody,
        'error'       => $error,
    ];
}

/**
 * Send a multipart/form-data file upload request.
 *
 * @return array{status:int, headers:array<string,string>, raw_headers:list<string>, body:string, error:string}
 */
function uploadMultipart(
    string $url,
    string $fieldName,
    string $filename,
    string $contentType,
    string $fileBytes
): array {
    $boundary = '----Lab01VerifyBoundary' . bin2hex(random_bytes(8));
    $eol = "\r\n";

    $body = '--' . $boundary . $eol
        . 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $filename . '"' . $eol
        . 'Content-Type: ' . $contentType . $eol . $eol
        . $fileBytes . $eol
        . '--' . $boundary . '--' . $eol;

    return httpRequest(
        'POST',
        $url,
        ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
        $body
    );
}

/**
 * Wait briefly for all stages to accept HTTP connections (avoids container startup races).
 *
 * @param array<string,string> $stages
 */
function waitForStages(array $stages, int $maxWaitSeconds = 20): void
{
    $deadline = microtime(true) + $maxWaitSeconds;
    foreach ($stages as $name => $baseUrl) {
        while (true) {
            $res = httpRequest('GET', $baseUrl . '/');
            if ($res['status'] > 0) {
                break;
            }
            if (microtime(true) >= $deadline) {
                echo "[WARN] Timed out waiting for {$name} ({$baseUrl}): {$res['error']}" . PHP_EOL;
                break;
            }
            usleep(400000);
        }
    }
}

/**
 * Detect any external stylesheet, script, font, image, or CDN URLs in HTML/CSS.
 * Allows local stage-switcher links (127.0.0.1, localhost, stage*-*) and W3C XML/SVG namespaces.
 *
 * @return list<string>
 */
function findExternalAssets(string $html): array
{
    $found = [];

    // 1. Check resource-loading HTML attributes and CSS url()/@import for any remote URL (including protocol-relative //)
    $assetPatterns = [
        '#<(?:link|script|img|iframe|source|object|embed)\b[^>]*(?:href|src)\s*=\s*["\']((?:https?:)?//[^"\']+)["\']#i',
        '#@import\s+(?:url\()?\s*["\']?((?:https?:)?//[^"\')\s]+)#i',
        '#url\(\s*["\']?((?:https?:)?//[^"\')\s]+)#i',
    ];
    foreach ($assetPatterns as $pattern) {
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $url) {
                $found[] = $url;
            }
        }
    }

    // 2. Check all http:// or https:// URLs in the page; allow only loopback, internal stage names, or W3C SVG namespace
    $allowedHosts = [
        '127.0.0.1',
        'localhost',
        'stage1-vulnerable',
        'stage2-app-only',
        'stage3-defense-in-depth',
        'www.w3.org',
    ];
    if (preg_match_all('#https?://([a-zA-Z0-9.-]+)(?::\d+)?(/[^\s"\'<>)]*)?#i', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $host = strtolower($m[1]);
            if (!in_array($host, $allowedHosts, true)) {
                $found[] = $m[0];
            }
        }
    }

    return array_values(array_unique($found));
}

/**
 * Scan PHP source code using PHP's tokenizer to detect forbidden superglobals,
 * forbidden OS/code execution functions, and backtick execution operators.
 *
 * @return list<string>
 */
function scanPhpSafety(string $filePath, bool $forbidSuperglobals): array
{
    $code = @file_get_contents($filePath);
    if ($code === false) {
        return ["Unable to read file: {$filePath}"];
    }

    $forbiddenVars = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES'];
    $forbiddenFuncs = [
        'system',
        'exec',
        'passthru',
        'shell_exec',
        'popen',
        'proc_open',
        'eval',
        'assert',
    ];

    $violations = [];
    $tokens = token_get_all($code);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $tok = $tokens[$i];

        if (is_string($tok)) {
            if ($tok === '`') {
                $violations[] = 'backtick operator (`)';
            }
            continue;
        }

        [$id, $text, $line] = $tok;

        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            continue;
        }

        if ($forbidSuperglobals && $id === T_VARIABLE && in_array($text, $forbiddenVars, true)) {
            $violations[] = "{$text} at line {$line}";
        }

        if ($id === T_EVAL) {
            $violations[] = "eval at line {$line}";
        }

        if ($id === T_STRING) {
            $lower = strtolower($text);
            if (in_array($lower, $forbiddenFuncs, true)) {
                // Check if followed by '(' (function call) or used as callable reference
                $prevNonWs = null;
                for ($p = $i - 1; $p >= 0; $p--) {
                    $pt = $tokens[$p];
                    if (is_array($pt) && $pt[0] === T_WHITESPACE) {
                        continue;
                    }
                    $prevNonWs = $pt;
                    break;
                }
                $isMethodOrConst = is_array($prevNonWs) && in_array($prevNonWs[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
                if (!$isMethodOrConst) {
                    $violations[] = "{$lower}() at line {$line}";
                }
            }
        }
    }

    return array_values(array_unique($violations));
}

function isResetSuccess(int $status): bool
{
    return in_array($status, [200, 204, 302, 303], true);
}

function startsWithPngMagic(string $bytes): bool
{
    return strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0;
}

echo "==================================================================" . PHP_EOL;
echo " Lab 01: PHP Upload Defense — Automated Verification Suite" . PHP_EOL;
echo "==================================================================" . PHP_EOL;
echo "Stage 1 URL : {$stage1Url}" . PHP_EOL;
echo "Stage 2 URL : {$stage2Url}" . PHP_EOL;
echo "Stage 3 URL : {$stage3Url}" . PHP_EOL;
echo "Lab Root    : {$labRoot}" . PHP_EOL;
echo "------------------------------------------------------------------" . PHP_EOL;

$stages = [
    'stage1-vulnerable'       => $stage1Url,
    'stage2-app-only'         => $stage2Url,
    'stage3-defense-in-depth' => $stage3Url,
];

waitForStages($stages);

// ------------------------------------------------------------------
// 1. Bilingual UI & Safety Banners (Stages 1, 2, 3)
// ------------------------------------------------------------------
echo PHP_EOL . "--- [1/5] Bilingual UI, Safety Banners & Offline Asset Check ---" . PHP_EOL;

foreach ($stages as $stageName => $baseUrl) {
    $ja = httpRequest('GET', $baseUrl . '/?lang=ja');
    recordCheck(
        $ja['status'] === 200,
        "{$stageName}: GET /?lang=ja returns HTTP 200",
        "got HTTP {$ja['status']}"
    );
    recordCheck(
        strpos($ja['body'], 'ローカル学習専用') !== false,
        "{$stageName}: GET /?lang=ja contains Japanese safety warning ('ローカル学習専用')"
    );
    $jaExternal = findExternalAssets($ja['body']);
    recordCheck(
        count($jaExternal) === 0,
        "{$stageName}: GET /?lang=ja has zero external CDN/stylesheet/script/font URLs",
        count($jaExternal) === 0 ? 'offline-clean' : 'found: ' . implode(', ', $jaExternal)
    );

    $en = httpRequest('GET', $baseUrl . '/?lang=en');
    recordCheck(
        $en['status'] === 200,
        "{$stageName}: GET /?lang=en returns HTTP 200",
        "got HTTP {$en['status']}"
    );
    recordCheck(
        stripos($en['body'], 'FOR LOCAL EDUCATIONAL USE ONLY') !== false,
        "{$stageName}: GET /?lang=en contains English safety warning ('FOR LOCAL EDUCATIONAL USE ONLY')"
    );
    $enExternal = findExternalAssets($en['body']);
    recordCheck(
        count($enExternal) === 0,
        "{$stageName}: GET /?lang=en has zero external CDN/stylesheet/script/font URLs",
        count($enExternal) === 0 ? 'offline-clean' : 'found: ' . implode(', ', $enExternal)
    );
}

// ------------------------------------------------------------------
// 2. Static Safety Scan of Samples & Stage Sources
// ------------------------------------------------------------------
echo PHP_EOL . "--- [2/5] Static Safety Scan of Samples & Stage Sources ---" . PHP_EOL;

$samplePngPath    = $samplesDir . '/sample-valid.png';
$markerBasicPath  = $samplesDir . '/marker-basic.php';
$markerBypassPath = $samplesDir . '/marker-bypass.phtml';

$hasSamplePng    = is_file($samplePngPath) && filesize($samplePngPath) > 0;
$hasMarkerBasic  = is_file($markerBasicPath) && filesize($markerBasicPath) > 0;
$hasMarkerBypass = is_file($markerBypassPath) && filesize($markerBypassPath) > 0;

recordCheck($hasSamplePng, 'Sample file exists: samples/sample-valid.png');
recordCheck($hasMarkerBasic, 'Sample file exists: samples/marker-basic.php');
recordCheck($hasMarkerBypass, 'Sample file exists: samples/marker-bypass.phtml');

$samplePngBytes    = $hasSamplePng ? (string) file_get_contents($samplePngPath) : '';
$markerBasicBytes  = $hasMarkerBasic ? (string) file_get_contents($markerBasicPath) : '';
$markerBypassBytes = $hasMarkerBypass ? (string) file_get_contents($markerBypassPath) : '';

recordCheck(
    $hasSamplePng && startsWithPngMagic($samplePngBytes),
    'samples/sample-valid.png has valid PNG signature bytes'
);

foreach ([
    'samples/marker-basic.php'    => $markerBasicPath,
    'samples/marker-bypass.phtml' => $markerBypassPath,
] as $relName => $fullPath) {
    $issues = is_file($fullPath) ? scanPhpSafety($fullPath, true) : ["Missing {$relName}"];
    recordCheck(
        count($issues) === 0,
        "Safety scan of {$relName} (zero forbidden superglobals & execution functions)",
        count($issues) === 0 ? 'clean' : implode('; ', $issues)
    );
}

$stageDirs = ['stage1-vulnerable', 'stage2-app-only', 'stage3-defense-in-depth'];
$stageFilesScanned = 0;
$stageViolations = [];
foreach ($stageDirs as $sDir) {
    $dirPath = $labRoot . '/' . $sDir;
    if (!is_dir($dirPath)) {
        continue;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if (in_array($ext, ['php', 'phtml'], true)) {
            $stageFilesScanned++;
            $v = scanPhpSafety($fileInfo->getPathname(), false);
            foreach ($v as $item) {
                $stageViolations[] = $sDir . '/' . $fileInfo->getFilename() . ': ' . $item;
            }
        }
    }
}
if ($stageFilesScanned > 0) {
    recordCheck(
        count($stageViolations) === 0,
        "Safety scan across {$stageFilesScanned} stage PHP/PHTML source files (zero OS exec/eval/assert)",
        count($stageViolations) === 0 ? 'clean' : implode('; ', $stageViolations)
    );
}

// ------------------------------------------------------------------
// 3. Stage 1 (stage1-vulnerable) Behavior
// ------------------------------------------------------------------
echo PHP_EOL . "--- [3/5] Stage 1 (stage1-vulnerable) Behavior ---" . PHP_EOL;

$s1Reset1 = httpRequest('POST', $stage1Url . '/reset.php');
recordCheck(
    isResetSuccess($s1Reset1['status']),
    'Stage 1: initial POST /reset.php succeeds',
    "HTTP {$s1Reset1['status']}"
);

$s1UpPng = uploadMultipart($stage1Url . '/upload.php', 'file', 'sample-valid.png', 'image/png', $samplePngBytes);
recordCheck(
    $s1UpPng['status'] === 200,
    'Stage 1: POST /upload.php accepts sample-valid.png with HTTP 200',
    "HTTP {$s1UpPng['status']}"
);

$s1GetPng = httpRequest('GET', $stage1Url . '/uploads/sample-valid.png');
recordCheck(
    $s1GetPng['status'] === 200 && startsWithPngMagic($s1GetPng['body']),
    'Stage 1: GET /uploads/sample-valid.png returns HTTP 200 with valid PNG bytes',
    "HTTP {$s1GetPng['status']}, bytes=" . strlen($s1GetPng['body'])
);

$s1UpPhp = uploadMultipart($stage1Url . '/upload.php', 'file', 'marker-basic.php', 'application/x-php', $markerBasicBytes);
recordCheck(
    $s1UpPhp['status'] === 200,
    'Stage 1: POST /upload.php accepts marker-basic.php with HTTP 200',
    "HTTP {$s1UpPhp['status']}"
);

$s1ExecPhp = httpRequest('GET', $stage1Url . '/uploads/marker-basic.php');
recordCheck(
    $s1ExecPhp['status'] === 200
        && strpos($s1ExecPhp['body'], 'LAB_EXECUTION_MARKER_STAGE1') !== false
        && strpos($s1ExecPhp['body'], '<?php') === false,
    'Stage 1: GET /uploads/marker-basic.php executes PHP (outputs LAB_EXECUTION_MARKER_STAGE1, no raw <?php)',
    "HTTP {$s1ExecPhp['status']}"
);

$s1Reset2 = httpRequest('POST', $stage1Url . '/reset.php');
recordCheck(
    isResetSuccess($s1Reset2['status']),
    'Stage 1: cleanup POST /reset.php succeeds',
    "HTTP {$s1Reset2['status']}"
);

$s1AfterReset = httpRequest('GET', $stage1Url . '/uploads/marker-basic.php');
recordCheck(
    $s1AfterReset['status'] === 404,
    'Stage 1: GET /uploads/marker-basic.php returns HTTP 404 after reset',
    "HTTP {$s1AfterReset['status']}"
);

// ------------------------------------------------------------------
// 4. Stage 2 (stage2-app-only) Behavior
// ------------------------------------------------------------------
echo PHP_EOL . "--- [4/5] Stage 2 (stage2-app-only) Behavior ---" . PHP_EOL;

$s2Reset1 = httpRequest('POST', $stage2Url . '/reset.php');
recordCheck(
    isResetSuccess($s2Reset1['status']),
    'Stage 2: initial POST /reset.php succeeds',
    "HTTP {$s2Reset1['status']}"
);

$s2UpPhp = uploadMultipart($stage2Url . '/upload.php', 'file', 'marker-basic.php', 'application/x-php', $markerBasicBytes);
recordCheck(
    $s2UpPhp['status'] === 400,
    'Stage 2: POST /upload.php blocks marker-basic.php with HTTP 400',
    "HTTP {$s2UpPhp['status']}"
);

$s2UpPhtml = uploadMultipart($stage2Url . '/upload.php', 'file', 'marker-bypass.phtml', 'application/octet-stream', $markerBypassBytes);
recordCheck(
    $s2UpPhtml['status'] === 200,
    'Stage 2: POST /upload.php allows marker-bypass.phtml with HTTP 200',
    "HTTP {$s2UpPhtml['status']}"
);

$s2ExecPhtml = httpRequest('GET', $stage2Url . '/uploads/marker-bypass.phtml');
recordCheck(
    $s2ExecPhtml['status'] === 200
        && strpos($s2ExecPhtml['body'], 'LAB_EXECUTION_MARKER_STAGE2') !== false
        && strpos($s2ExecPhtml['body'], '<?php') === false,
    'Stage 2: GET /uploads/marker-bypass.phtml executes PHP (outputs LAB_EXECUTION_MARKER_STAGE2, no raw <?php)',
    "HTTP {$s2ExecPhtml['status']}"
);

$s2Reset2 = httpRequest('POST', $stage2Url . '/reset.php');
recordCheck(
    isResetSuccess($s2Reset2['status']),
    'Stage 2: cleanup POST /reset.php succeeds',
    "HTTP {$s2Reset2['status']}"
);

$s2AfterReset = httpRequest('GET', $stage2Url . '/uploads/marker-bypass.phtml');
recordCheck(
    $s2AfterReset['status'] === 404,
    'Stage 2: GET /uploads/marker-bypass.phtml returns HTTP 404 after reset',
    "HTTP {$s2AfterReset['status']}"
);

// ------------------------------------------------------------------
// 5. Stage 3 (stage3-defense-in-depth) Behavior
// ------------------------------------------------------------------
echo PHP_EOL . "--- [5/5] Stage 3 (stage3-defense-in-depth) Behavior ---" . PHP_EOL;

$s3Reset1 = httpRequest('POST', $stage3Url . '/reset.php');
recordCheck(
    isResetSuccess($s3Reset1['status']),
    'Stage 3: initial POST /reset.php succeeds',
    "HTTP {$s3Reset1['status']}"
);

$s3UpPhp = uploadMultipart($stage3Url . '/upload.php', 'file', 'marker-basic.php', 'application/x-php', $markerBasicBytes);
recordCheck(
    $s3UpPhp['status'] === 400,
    'Stage 3: POST /upload.php rejects marker-basic.php with HTTP 400',
    "HTTP {$s3UpPhp['status']}"
);

$s3UpPhtml = uploadMultipart($stage3Url . '/upload.php', 'file', 'marker-bypass.phtml', 'application/octet-stream', $markerBypassBytes);
recordCheck(
    $s3UpPhtml['status'] === 400,
    'Stage 3: POST /upload.php rejects marker-bypass.phtml with HTTP 400',
    "HTTP {$s3UpPhtml['status']}"
);

$spoofedBody = '<?php echo "SPOOF"; ?>';
$s3UpSpoof = uploadMultipart($stage3Url . '/upload.php', 'file', 'evil.png', 'image/png', $spoofedBody);
recordCheck(
    $s3UpSpoof['status'] === 400,
    'Stage 3: POST /upload.php rejects spoofed evil.png (Content-Type: image/png with PHP payload) with HTTP 400',
    "HTTP {$s3UpSpoof['status']}"
);

$s3UpPng = uploadMultipart($stage3Url . '/upload.php', 'file', 'sample-valid.png', 'image/png', $samplePngBytes);
recordCheck(
    $s3UpPng['status'] === 200,
    'Stage 3: POST /upload.php accepts valid sample-valid.png with HTTP 200',
    "HTTP {$s3UpPng['status']}"
);

$uuidFile = '';
if (preg_match('#/?image\.php\?file=([0-9a-fA-F-]+\.png)#', $s3UpPng['body'], $m)) {
    $uuidFile = $m[1];
}

$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.png$/i';
recordCheck(
    $uuidFile !== ''
        && preg_match($uuidPattern, $uuidFile) === 1
        && stripos($uuidFile, 'sample-valid') === false,
    'Stage 3: response provides /image.php?file=<uuid>.png renamed via UUIDv4 (no original filename)',
    $uuidFile !== '' ? "file={$uuidFile}" : 'URL not found in upload response'
);

if ($uuidFile !== '') {
    $s3GetImg = httpRequest('GET', $stage3Url . '/image.php?file=' . rawurlencode($uuidFile));
    $nosniff = strtolower($s3GetImg['headers']['x-content-type-options'] ?? '');
    $csp     = $s3GetImg['headers']['content-security-policy'] ?? '';

    recordCheck(
        $s3GetImg['status'] === 200 && startsWithPngMagic($s3GetImg['body']),
        'Stage 3: GET /image.php?file=<uuid>.png returns HTTP 200 with valid PNG magic bytes',
        "HTTP {$s3GetImg['status']}, bytes=" . strlen($s3GetImg['body'])
    );
    recordCheck(
        $nosniff === 'nosniff',
        'Stage 3: GET /image.php sets X-Content-Type-Options: nosniff',
        "got '{$nosniff}'"
    );
    recordCheck(
        $csp !== '',
        'Stage 3: GET /image.php sets Content-Security-Policy header',
        "got '{$csp}'"
    );
} else {
    recordCheck(false, 'Stage 3: GET /image.php?file=<uuid>.png verification skipped due to missing UUID URL');
    recordCheck(false, 'Stage 3: X-Content-Type-Options header check skipped due to missing UUID URL');
    recordCheck(false, 'Stage 3: Content-Security-Policy header check skipped due to missing UUID URL');
}

$s3GuardBefore = httpRequest('GET', $stage3Url . '/uploads/infra-guard-check.phtml');
recordCheck(
    $s3GuardBefore['status'] === 403
        && strpos($s3GuardBefore['body'], 'INFRA_LAYER_FAILED_EXECUTION') === false,
    'Stage 3: GET /uploads/infra-guard-check.phtml returns HTTP 403 and never executes PHP',
    "HTTP {$s3GuardBefore['status']}"
);

$s3Reset2 = httpRequest('POST', $stage3Url . '/reset.php');
recordCheck(
    isResetSuccess($s3Reset2['status']),
    'Stage 3: cleanup POST /reset.php succeeds',
    "HTTP {$s3Reset2['status']}"
);

if ($uuidFile !== '') {
    $s3ImgAfterReset = httpRequest('GET', $stage3Url . '/image.php?file=' . rawurlencode($uuidFile));
    recordCheck(
        $s3ImgAfterReset['status'] === 404,
        'Stage 3: GET /image.php?file=<uuid>.png returns HTTP 404 after reset',
        "HTTP {$s3ImgAfterReset['status']}"
    );
} else {
    recordCheck(false, 'Stage 3: post-reset /image.php 404 check skipped due to missing UUID URL');
}

$s3GuardAfter = httpRequest('GET', $stage3Url . '/uploads/infra-guard-check.phtml');
recordCheck(
    $s3GuardAfter['status'] === 403
        && strpos($s3GuardAfter['body'], 'INFRA_LAYER_FAILED_EXECUTION') === false,
    'Stage 3: GET /uploads/infra-guard-check.phtml still enforces HTTP 403 after reset',
    "HTTP {$s3GuardAfter['status']}"
);

// ------------------------------------------------------------------
// Final Summary
// ------------------------------------------------------------------
$total = $passCount + $failCount;
echo PHP_EOL . "==================================================================" . PHP_EOL;
echo " Summary: {$passCount}/{$total} passed, {$failCount} failed" . PHP_EOL;
echo "==================================================================" . PHP_EOL;

if ($failCount > 0) {
    echo "Failed checks:" . PHP_EOL;
    foreach ($failures as $f) {
        echo "  - {$f}" . PHP_EOL;
    }
    exit(1);
}

echo "ALL CHECKS PASSED." . PHP_EOL;
exit(0);
