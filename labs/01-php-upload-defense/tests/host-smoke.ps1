#!/usr/bin/env pwsh
<#
.SYNOPSIS
  Host-side end-to-end smoke test for Lab 01 (PHP Upload Defense) on 127.0.0.1:8081..8083.
.DESCRIPTION
  Verifies all three stages over loopback from the host OS using .NET HttpClient
  (compatible with both PowerShell 7+ pwsh and Windows PowerShell 5.1).
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Net.Http

$Stage1Url = (if ($env:STAGE1_URL) { $env:STAGE1_URL } else { 'http://127.0.0.1:8081' }).TrimEnd('/')
$Stage2Url = (if ($env:STAGE2_URL) { $env:STAGE2_URL } else { 'http://127.0.0.1:8082' }).TrimEnd('/')
$Stage3Url = (if ($env:STAGE3_URL) { $env:STAGE3_URL } else { 'http://127.0.0.1:8083' }).TrimEnd('/')

$TestsDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$LabRoot    = Split-Path -Parent $TestsDir
$SamplesDir = Join-Path $LabRoot 'samples'

$script:PassCount = 0
$script:FailCount = 0

function Test-Check {
    param(
        [bool]$Condition,
        [string]$Label,
        [string]$Detail = ''
    )
    if ($Condition) {
        $script:PassCount++
        $suffix = if ($Detail) { " ($Detail)" } else { '' }
        Write-Host "[PASS] $Label$suffix"
    } else {
        $script:FailCount++
        $suffix = if ($Detail) { " - $Detail" } else { '' }
        Write-Host "[FAIL] $Label$suffix"
    }
}

$Handler = [System.Net.Http.HttpClientHandler]::new()
$Handler.AllowAutoRedirect = $false
$Client = [System.Net.Http.HttpClient]::new($Handler)
$Client.Timeout = [TimeSpan]::FromSeconds(10)

function Invoke-RawHttp {
    param(
        [string]$Method,
        [string]$Url
    )
    $req = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::new($Method), $Url)
    $res = $Client.SendAsync($req).GetAwaiter().GetResult()
    $bytes = $res.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
    $text  = [System.Text.Encoding]::UTF8.GetString($bytes)
    $headers = @{}
    foreach ($h in $res.Headers) {
        $headers[$h.Key.ToLowerInvariant()] = ($h.Value -join ', ')
    }
    foreach ($h in $res.Content.Headers) {
        $headers[$h.Key.ToLowerInvariant()] = ($h.Value -join ', ')
    }
    $res.Dispose()
    $req.Dispose()
    return @{
        Status  = [int]$res.StatusCode
        Bytes   = $bytes
        Body    = $text
        Headers = $headers
    }
}

function Invoke-MultipartUpload {
    param(
        [string]$Url,
        [string]$Filename,
        [string]$ContentType,
        [byte[]]$FileBytes
    )
    $multipart = [System.Net.Http.MultipartFormDataContent]::new()
    $byteContent = [System.Net.Http.ByteArrayContent]::new($FileBytes)
    $byteContent.Headers.ContentType = [System.Net.Http.Headers.MediaTypeHeaderValue]::Parse($ContentType)
    $multipart.Add($byteContent, 'file', $Filename)

    $res = $Client.PostAsync($Url, $multipart).GetAwaiter().GetResult()
    $bytes = $res.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
    $text  = [System.Text.Encoding]::UTF8.GetString($bytes)
    $status = [int]$res.StatusCode
    $res.Dispose()
    $multipart.Dispose()
    return @{
        Status = $status
        Bytes  = $bytes
        Body   = $text
    }
}

function Test-PngMagic {
    param([byte[]]$Bytes)
    if ($null -eq $Bytes -or $Bytes.Length -lt 8) { return $false }
    $pngSig = [byte[]](0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A)
    for ($i = 0; $i -lt 8; $i++) {
        if ($Bytes[$i] -ne $pngSig[$i]) { return $false }
    }
    return $true
}

function Test-ResetStatus {
    param([int]$Status)
    return ($Status -in @(200, 204, 302, 303))
}

Write-Host '=================================================================='
Write-Host ' Lab 01 Host Smoke Test (PowerShell)'
Write-Host '=================================================================='
Write-Host "Stage 1 : $Stage1Url"
Write-Host "Stage 2 : $Stage2Url"
Write-Host "Stage 3 : $Stage3Url"
Write-Host '------------------------------------------------------------------'

$SamplePngPath    = Join-Path $SamplesDir 'sample-valid.png'
$MarkerBasicPath  = Join-Path $SamplesDir 'marker-basic.php'
$MarkerBypassPath = Join-Path $SamplesDir 'marker-bypass.phtml'

Test-Check (Test-Path -LiteralPath $SamplePngPath) 'Sample file present: sample-valid.png'
Test-Check (Test-Path -LiteralPath $MarkerBasicPath) 'Sample file present: marker-basic.php'
Test-Check (Test-Path -LiteralPath $MarkerBypassPath) 'Sample file present: marker-bypass.phtml'

$SamplePngBytes    = [System.IO.File]::ReadAllBytes($SamplePngPath)
$MarkerBasicBytes  = [System.IO.File]::ReadAllBytes($MarkerBasicPath)
$MarkerBypassBytes = [System.IO.File]::ReadAllBytes($MarkerBypassPath)

# 1. Bilingual UI & Safety Banners
$Stages = @(
    @{ Name = 'Stage 1'; Url = $Stage1Url },
    @{ Name = 'Stage 2'; Url = $Stage2Url },
    @{ Name = 'Stage 3'; Url = $Stage3Url }
)
foreach ($s in $Stages) {
    $ja = Invoke-RawHttp -Method 'GET' -Url "$($s.Url)/?lang=ja"
    Test-Check ($ja.Status -eq 200 -and $ja.Body.Contains('ローカル学習専用')) "$($s.Name): GET /?lang=ja returns 200 with JP safety warning" "HTTP $($ja.Status)"

    $en = Invoke-RawHttp -Method 'GET' -Url "$($s.Url)/?lang=en"
    Test-Check ($en.Status -eq 200 -and $en.Body.IndexOf('FOR LOCAL EDUCATIONAL USE ONLY', [System.StringComparison]::OrdinalIgnoreCase) -ge 0) "$($s.Name): GET /?lang=en returns 200 with EN safety warning" "HTTP $($en.Status)"
}

# 2. Stage 1
$r = Invoke-RawHttp -Method 'POST' -Url "$Stage1Url/reset.php"
Test-Check (Test-ResetStatus $r.Status) 'Stage 1: POST /reset.php succeeds' "HTTP $($r.Status)"

$uPng1 = Invoke-MultipartUpload -Url "$Stage1Url/upload.php" -Filename 'sample-valid.png' -ContentType 'image/png' -FileBytes $SamplePngBytes
$gPng1 = Invoke-RawHttp -Method 'GET' -Url "$Stage1Url/uploads/sample-valid.png"
Test-Check ($uPng1.Status -eq 200 -and $gPng1.Status -eq 200 -and (Test-PngMagic $gPng1.Bytes)) 'Stage 1: sample-valid.png uploads and serves valid PNG bytes' "Upload=$($uPng1.Status), Get=$($gPng1.Status)"

$uPhp1 = Invoke-MultipartUpload -Url "$Stage1Url/upload.php" -Filename 'marker-basic.php' -ContentType 'application/octet-stream' -FileBytes $MarkerBasicBytes
$gPhp1 = Invoke-RawHttp -Method 'GET' -Url "$Stage1Url/uploads/marker-basic.php"
Test-Check ($uPhp1.Status -eq 200 -and $gPhp1.Status -eq 200 -and $gPhp1.Body.Contains('LAB_EXECUTION_MARKER_STAGE1') -and (-not $gPhp1.Body.Contains('<?php'))) 'Stage 1: marker-basic.php executes and outputs LAB_EXECUTION_MARKER_STAGE1' "Upload=$($uPhp1.Status), Get=$($gPhp1.Status)"

$r = Invoke-RawHttp -Method 'POST' -Url "$Stage1Url/reset.php"
$gPhp1After = Invoke-RawHttp -Method 'GET' -Url "$Stage1Url/uploads/marker-basic.php"
Test-Check ((Test-ResetStatus $r.Status) -and $gPhp1After.Status -eq 404) 'Stage 1: reset removes marker-basic.php (HTTP 404)' "HTTP $($gPhp1After.Status)"

# 3. Stage 2
$r = Invoke-RawHttp -Method 'POST' -Url "$Stage2Url/reset.php"
Test-Check (Test-ResetStatus $r.Status) 'Stage 2: POST /reset.php succeeds' "HTTP $($r.Status)"

$uPhp2 = Invoke-MultipartUpload -Url "$Stage2Url/upload.php" -Filename 'marker-basic.php' -ContentType 'application/octet-stream' -FileBytes $MarkerBasicBytes
Test-Check ($uPhp2.Status -eq 400) 'Stage 2: marker-basic.php blocked with HTTP 400' "HTTP $($uPhp2.Status)"

$uPhtml2 = Invoke-MultipartUpload -Url "$Stage2Url/upload.php" -Filename 'marker-bypass.phtml' -ContentType 'application/octet-stream' -FileBytes $MarkerBypassBytes
$gPhtml2 = Invoke-RawHttp -Method 'GET' -Url "$Stage2Url/uploads/marker-bypass.phtml"
Test-Check ($uPhtml2.Status -eq 200 -and $gPhtml2.Status -eq 200 -and $gPhtml2.Body.Contains('LAB_EXECUTION_MARKER_STAGE2') -and (-not $gPhtml2.Body.Contains('<?php'))) 'Stage 2: marker-bypass.phtml uploads and executes LAB_EXECUTION_MARKER_STAGE2' "Upload=$($uPhtml2.Status), Get=$($gPhtml2.Status)"

$r = Invoke-RawHttp -Method 'POST' -Url "$Stage2Url/reset.php"
$gPhtml2After = Invoke-RawHttp -Method 'GET' -Url "$Stage2Url/uploads/marker-bypass.phtml"
Test-Check ((Test-ResetStatus $r.Status) -and $gPhtml2After.Status -eq 404) 'Stage 2: reset removes marker-bypass.phtml (HTTP 404)' "HTTP $($gPhtml2After.Status)"

# 4. Stage 3
$r = Invoke-RawHttp -Method 'POST' -Url "$Stage3Url/reset.php"
Test-Check (Test-ResetStatus $r.Status) 'Stage 3: POST /reset.php succeeds' "HTTP $($r.Status)"

$uPhp3   = Invoke-MultipartUpload -Url "$Stage3Url/upload.php" -Filename 'marker-basic.php' -ContentType 'application/octet-stream' -FileBytes $MarkerBasicBytes
$uPhtml3 = Invoke-MultipartUpload -Url "$Stage3Url/upload.php" -Filename 'marker-bypass.phtml' -ContentType 'application/octet-stream' -FileBytes $MarkerBypassBytes
$SpoofBytes = [System.Text.Encoding]::UTF8.GetBytes('<?php echo "SPOOF"; ?>')
$uSpoof3 = Invoke-MultipartUpload -Url "$Stage3Url/upload.php" -Filename 'evil.png' -ContentType 'image/png' -FileBytes $SpoofBytes

Test-Check ($uPhp3.Status -eq 400) 'Stage 3: marker-basic.php rejected with HTTP 400' "HTTP $($uPhp3.Status)"
Test-Check ($uPhtml3.Status -eq 400) 'Stage 3: marker-bypass.phtml rejected with HTTP 400' "HTTP $($uPhtml3.Status)"
Test-Check ($uSpoof3.Status -eq 400) 'Stage 3: spoofed evil.png rejected with HTTP 400' "HTTP $($uSpoof3.Status)"

$uPng3 = Invoke-MultipartUpload -Url "$Stage3Url/upload.php" -Filename 'sample-valid.png' -ContentType 'image/png' -FileBytes $SamplePngBytes
Test-Check ($uPng3.Status -eq 200) 'Stage 3: valid sample-valid.png accepted with HTTP 200' "HTTP $($uPng3.Status)"

$m = [regex]::Match($uPng3.Body, '/?image\.php\?file=([0-9a-fA-F-]+\.png)')
$UuidFile = if ($m.Success) { $m.Groups[1].Value } else { '' }
Test-Check ($UuidFile -ne '' -and $UuidFile -notmatch 'sample-valid') 'Stage 3: uploaded PNG renamed to UUIDv4 .png via /image.php' "file=$UuidFile"

if ($UuidFile -ne '') {
    $gImg3 = Invoke-RawHttp -Method 'GET' -Url "$Stage3Url/image.php?file=$UuidFile"
    $nosniff = $gImg3.Headers['x-content-type-options']
    $csp     = $gImg3.Headers['content-security-policy']
    Test-Check ($gImg3.Status -eq 200 -and (Test-PngMagic $gImg3.Bytes) -and $nosniff -eq 'nosniff' -and (-not [string]::IsNullOrEmpty($csp))) 'Stage 3: /image.php serves PNG with nosniff and Content-Security-Policy' "HTTP $($gImg3.Status), nosniff=$nosniff, csp=$csp"
} else {
    Test-Check $false 'Stage 3: /image.php header & PNG verification skipped (no UUID URL found)'
}

$guard1 = Invoke-RawHttp -Method 'GET' -Url "$Stage3Url/uploads/infra-guard-check.phtml"
Test-Check ($guard1.Status -eq 403 -and (-not $guard1.Body.Contains('INFRA_LAYER_FAILED_EXECUTION'))) 'Stage 3: GET /uploads/infra-guard-check.phtml blocked with HTTP 403' "HTTP $($guard1.Status)"

$r = Invoke-RawHttp -Method 'POST' -Url "$Stage3Url/reset.php"
if ($UuidFile -ne '') {
    $gImg3After = Invoke-RawHttp -Method 'GET' -Url "$Stage3Url/image.php?file=$UuidFile"
    Test-Check ((Test-ResetStatus $r.Status) -and $gImg3After.Status -eq 404) 'Stage 3: reset removes uploaded UUID PNG (HTTP 404)' "HTTP $($gImg3After.Status)"
} else {
    Test-Check $false 'Stage 3: post-reset UUID PNG 404 check skipped'
}

$guard2 = Invoke-RawHttp -Method 'GET' -Url "$Stage3Url/uploads/infra-guard-check.phtml"
Test-Check ($guard2.Status -eq 403 -and (-not $guard2.Body.Contains('INFRA_LAYER_FAILED_EXECUTION'))) 'Stage 3: infra-guard-check.phtml remains HTTP 403 after reset' "HTTP $($guard2.Status)"

$Client.Dispose()
$Handler.Dispose()

$Total = $script:PassCount + $script:FailCount
Write-Host '=================================================================='
Write-Host " Summary: $($script:PassCount)/$Total passed, $($script:FailCount) failed"
Write-Host '=================================================================='

if ($script:FailCount -gt 0) {
    exit 1
}
Write-Host 'ALL HOST SMOKE CHECKS PASSED.'
exit 0
