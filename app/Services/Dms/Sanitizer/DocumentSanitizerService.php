<?php

namespace App\Services\Dms\Sanitizer;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ZipArchive;

class DocumentSanitizerService
{
    /**
     * Allowed file extensions (normalized lowercase).
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'docx',
        'xlsx',
        'pptx',
        'jpg',
        'jpeg',
        'png',
    ];

    /**
     * Explicitly blocked extensions (executables, scripts, standalone archives, macro files, HTML, SVG).
     */
    public const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'sh', 'bin', 'dll', 'com', 'scr', 'msi', 'ps1', 'vbs', 'wsf',
        'js', 'py', 'php', 'pl', 'rb', 'cgi', 'jar',
        'zip', 'tar', 'gz', 'tgz', 'bz2', '7z', 'rar', 'iso',
        'html', 'htm', 'xhtml', 'svg', 'xml',
        'docm', 'xlsm', 'pptm', 'dotm', 'xltm', 'potm',
    ];

    /**
     * Dangerous MIME types that must be rejected immediately regardless of extension.
     */
    public const DANGEROUS_MIMES = [
        'application/x-executable',
        'application/x-dosexec',
        'application/x-msdownload',
        'application/x-sharedlib',
        'application/x-sh',
        'application/x-shellscript',
        'text/x-shellscript',
        'text/x-php',
        'application/x-php',
        'text/x-python',
        'text/x-perl',
        'text/html',
        'image/svg+xml',
        'application/javascript',
        'text/javascript',
        'application/x-bat',
        'application/x-msdos-program',
    ];

    protected ClamAvScanner $clamAvScanner;

    protected MetadataScrubber $metadataScrubber;

    protected string $quarantineDir;

    public function __construct(
        ?ClamAvScanner $clamAvScanner = null,
        ?MetadataScrubber $metadataScrubber = null,
        ?string $quarantineDir = null
    ) {
        $this->clamAvScanner = $clamAvScanner ?? new ClamAvScanner;
        $this->metadataScrubber = $metadataScrubber ?? new MetadataScrubber;
        $this->quarantineDir = $quarantineDir ?? (string) config('dms.quarantine_path', storage_path('app/quarantine'));
    }

    /**
     * Sanitize an uploaded document through the 5-step sanitization pipeline.
     */
    public function sanitize(string $tempFilePath, string $extension): SanitizerResult
    {
        if (! file_exists($tempFilePath) || ! is_readable($tempFilePath)) {
            return SanitizerResult::rejected($tempFilePath, 'File does not exist or is not readable.');
        }

        $normalizedExt = strtolower(ltrim(trim($extension), '.'));
        if ($normalizedExt === '') {
            $normalizedExt = strtolower((string) pathinfo($tempFilePath, PATHINFO_EXTENSION));
        }

        // ==========================================
        // Step 1: Allowed Types
        // ==========================================
        if (in_array($normalizedExt, self::BLOCKED_EXTENSIONS, true) || ! in_array($normalizedExt, self::ALLOWED_EXTENSIONS, true)) {
            $quarantinePath = $this->quarantineFile($tempFilePath, 'disallowed_type');

            return SanitizerResult::rejected(
                $tempFilePath,
                "File type '{$normalizedExt}' is not allowed. Only PDF, DOCX, XLSX, PPTX, JPG, and PNG are permitted.",
                $quarantinePath,
                ['extension' => $normalizedExt]
            );
        }

        // ==========================================
        // Step 2: Real Content Check (Magic Bytes)
        // ==========================================
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($tempFilePath);

        // Check for dangerous MIME types
        foreach (self::DANGEROUS_MIMES as $dangerousMime) {
            if (str_starts_with($detectedMime, $dangerousMime)) {
                $quarantinePath = $this->quarantineFile($tempFilePath, 'dangerous_mime');

                return SanitizerResult::rejected(
                    $tempFilePath,
                    "Detected dangerous MIME type '{$detectedMime}'. Upload rejected.",
                    $quarantinePath,
                    ['detected_mime' => $detectedMime]
                );
            }
        }

        // Validate MIME type consistency against declared extension
        $mimeCheck = $this->validateMimeConsistency($tempFilePath, $normalizedExt, $detectedMime);
        if (! $mimeCheck['valid']) {
            $quarantinePath = $this->quarantineFile($tempFilePath, 'mime_mismatch');

            return SanitizerResult::rejected(
                $tempFilePath,
                $mimeCheck['error'] ?? "MIME type '{$detectedMime}' does not match extension '{$normalizedExt}'.",
                $quarantinePath,
                ['detected_mime' => $detectedMime, 'extension' => $normalizedExt]
            );
        }

        // Check zip containers for zip bombs (ratio & entry limits)
        if (in_array($normalizedExt, ['docx', 'xlsx', 'pptx'], true)) {
            $zipBombCheck = $this->checkZipContainer($tempFilePath);
            if (! $zipBombCheck['valid']) {
                $quarantinePath = $this->quarantineFile($tempFilePath, 'zip_bomb');

                return SanitizerResult::rejected(
                    $tempFilePath,
                    $zipBombCheck['error'] ?? 'Zip container failed security validation (possible zip bomb).',
                    $quarantinePath,
                    $zipBombCheck['details'] ?? []
                );
            }
        }

        // ==========================================
        // Step 3: Malware Scan (ClamAV)
        // ==========================================
        $scanResult = $this->clamAvScanner->scan($tempFilePath);
        if ($scanResult->isInfected()) {
            $quarantinePath = $this->quarantineFile($tempFilePath, 'malware');
            $virus = $scanResult->getVirusName() ?: 'Generic.Malware';

            return SanitizerResult::infected(
                $tempFilePath,
                "Malware detected: {$virus}",
                $quarantinePath,
                ['virus' => $virus]
            );
        }

        // ==========================================
        // Step 4: Active Content Removal
        // ==========================================
        $activeContentDetails = [];

        if ($normalizedExt === 'pdf') {
            $pdfResult = $this->sanitizePdf($tempFilePath);
            if (! $pdfResult['valid']) {
                $quarantinePath = $this->quarantineFile($tempFilePath, 'pdf_exploit');

                return SanitizerResult::rejected($tempFilePath, $pdfResult['error'], $quarantinePath);
            }
            $activeContentDetails = array_merge($activeContentDetails, $pdfResult['details'] ?? []);
        } elseif (in_array($normalizedExt, ['docx', 'xlsx', 'pptx'], true)) {
            $officeResult = $this->sanitizeOffice($tempFilePath);
            if (! $officeResult['valid']) {
                $quarantinePath = $this->quarantineFile($tempFilePath, 'office_macro');

                return SanitizerResult::rejected($tempFilePath, $officeResult['error'], $quarantinePath);
            }
            $activeContentDetails = array_merge($activeContentDetails, $officeResult['details'] ?? []);
        } elseif (in_array($normalizedExt, ['jpg', 'jpeg', 'png'], true)) {
            $imageResult = $this->sanitizeImage($tempFilePath, $normalizedExt);
            if (! $imageResult['valid']) {
                $quarantinePath = $this->quarantineFile($tempFilePath, 'corrupt_image');

                return SanitizerResult::rejected($tempFilePath, $imageResult['error'], $quarantinePath);
            }
            $activeContentDetails = array_merge($activeContentDetails, $imageResult['details'] ?? []);
        }

        // ==========================================
        // Step 5: Metadata Scrub
        // ==========================================
        $this->metadataScrubber->scrub($tempFilePath, $normalizedExt);

        return SanitizerResult::clean(
            $tempFilePath,
            $detectedMime,
            array_merge([
                'extension' => $normalizedExt,
                'mime_type' => $detectedMime,
                'sanitized' => true,
            ], $activeContentDetails)
        );
    }

    /**
     * Validate that file magic bytes match expected formats.
     *
     * @return array{valid: bool, error?: string}
     */
    protected function validateMimeConsistency(string $filePath, string $ext, string $mime): array
    {
        $handle = @fopen($filePath, 'rb');
        if (! $handle) {
            return ['valid' => false, 'error' => 'Unable to read file header.'];
        }
        $header = fread($handle, 32);
        fclose($handle);

        switch ($ext) {
            case 'pdf':
                if (! str_starts_with($header, '%PDF-') && $mime !== 'application/pdf') {
                    return ['valid' => false, 'error' => 'File does not have a valid PDF header.'];
                }

                return ['valid' => true];

            case 'jpg':
            case 'jpeg':
                if (substr($header, 0, 2) !== "\xFF\xD8" || ! in_array($mime, ['image/jpeg', 'image/pjpeg'], true)) {
                    return ['valid' => false, 'error' => 'File does not have valid JPEG magic bytes.'];
                }

                return ['valid' => true];

            case 'png':
                if (substr($header, 0, 8) !== "\x89PNG\r\n\x1a\n" || $mime !== 'image/png') {
                    return ['valid' => false, 'error' => 'File does not have valid PNG magic bytes.'];
                }

                return ['valid' => true];

            case 'docx':
            case 'xlsx':
            case 'pptx':
                // OpenXML documents are ZIP archives (PK\x03\x04)
                if (substr($header, 0, 4) !== "PK\x03\x04" && substr($header, 0, 4) !== "PK\x05\x06") {
                    return ['valid' => false, 'error' => 'Office file does not have valid ZIP magic bytes.'];
                }

                // Verify internal OpenXML structure
                $zip = new ZipArchive;
                if ($zip->open($filePath) !== true) {
                    return ['valid' => false, 'error' => 'Corrupt or unreadable Office OpenXML container.'];
                }

                $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
                $mainPartExists = match ($ext) {
                    'docx' => $zip->locateName('word/document.xml') !== false,
                    'xlsx' => $zip->locateName('xl/workbook.xml') !== false,
                    'pptx' => $zip->locateName('ppt/presentation.xml') !== false,
                };
                $zip->close();

                if (! $hasContentTypes || ! $mainPartExists) {
                    return ['valid' => false, 'error' => "Archive is not a valid {$ext} OpenXML document."];
                }

                return ['valid' => true];

            default:
                return ['valid' => false, 'error' => "Unsupported extension {$ext}."];
        }
    }

    /**
     * Inspect zip containers for zip bombs (ratio limits, uncompressed size limits).
     *
     * @return array{valid: bool, error?: string, details?: array<string, mixed>}
     */
    protected function checkZipContainer(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return ['valid' => false, 'error' => 'Failed to open zip archive for inspection.'];
        }

        $maxEntries = (int) config('dms.max_zip_entries', 1000);
        $maxUncompressedBytes = (int) config('dms.max_zip_uncompressed_bytes', 100 * 1024 * 1024);
        $maxRatio = (float) config('dms.max_zip_ratio', 100.0);

        $numFiles = $zip->numFiles;
        if ($numFiles > $maxEntries) {
            $zip->close();

            return [
                'valid' => false,
                'error' => "Zip archive entry count ({$numFiles}) exceeds limit of {$maxEntries}.",
                'details' => ['entry_count' => $numFiles],
            ];
        }

        $totalCompressed = 0;
        $totalUncompressed = 0;

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! $stat) {
                continue;
            }

            $entryName = strtolower($stat['name'] ?? '');

            // Reject nested archives
            if (preg_match('/\.(zip|tar|gz|tgz|bz2|7z|rar|iso)$/i', $entryName)) {
                $zip->close();

                return [
                    'valid' => false,
                    'error' => "Zip archive contains nested archive entry '{$stat['name']}'.",
                    'details' => ['entry' => $stat['name']],
                ];
            }

            $totalCompressed += $stat['comp_size'] ?? 0;
            $totalUncompressed += $stat['size'] ?? 0;

            if ($totalUncompressed > $maxUncompressedBytes) {
                $zip->close();

                return [
                    'valid' => false,
                    'error' => 'Zip archive uncompressed size exceeds maximum safe limit (100MB).',
                    'details' => ['uncompressed_bytes' => $totalUncompressed],
                ];
            }
        }

        $zip->close();

        $ratio = $totalCompressed > 0 ? ($totalUncompressed / $totalCompressed) : 1;
        if ($ratio > $maxRatio && $totalUncompressed > (10 * 1024 * 1024)) {
            return [
                'valid' => false,
                'error' => sprintf('Zip compression ratio (%.1f:1) exceeds maximum safe threshold (%.1f:1).', $ratio, $maxRatio),
                'details' => ['ratio' => $ratio],
            ];
        }

        return ['valid' => true];
    }

    /**
     * Active Content Removal for PDFs: run qpdf if present, and strip JavaScript / launch actions.
     *
     * @return array{valid: bool, error?: string, details?: array<string, mixed>}
     */
    protected function sanitizePdf(string $filePath): array
    {
        // 1. If qpdf is available, linearize and strip empty objects
        if ($this->hasCommand('qpdf')) {
            $outputPath = $filePath.'.qpdf.tmp';
            $escapedIn = escapeshellarg($filePath);
            $escapedOut = escapeshellarg($outputPath);

            $result = Process::run("qpdf --linearize --strip-empty-objects {$escapedIn} {$escapedOut}");
            if ($result->successful() && file_exists($outputPath) && filesize($outputPath) > 0) {
                rename($outputPath, $filePath);
            } else {
                @unlink($outputPath);
            }
        }

        // 2. Strip JavaScript, launch actions, and embedded files
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ['valid' => false, 'error' => 'Unable to read PDF file content.'];
        }

        $hadJavascript = (bool) preg_match('/(\/JavaScript|\/JS\b)/i', $content);
        $hadLaunch = (bool) preg_match('/\/Launch\b/i', $content);
        $hadEmbeddedFiles = (bool) preg_match('/\/EmbeddedFiles\b/i', $content);

        if ($hadJavascript || $hadLaunch || $hadEmbeddedFiles) {
            $content = $this->stripPdfActiveScriptTokens($content);
            @file_put_contents($filePath, $content);
        }

        return [
            'valid' => true,
            'details' => [
                'stripped_javascript' => $hadJavascript,
                'stripped_launch' => $hadLaunch,
                'stripped_embedded_files' => $hadEmbeddedFiles,
            ],
        ];
    }

    /**
     * Strip active PDF script tokens, /JS literal string contents, /Launch, and /EmbeddedFiles.
     */
    protected function stripPdfActiveScriptTokens(string $pdf): string
    {
        // Neutralize /S /JavaScript -> /S /None
        $pdf = preg_replace('/\/S\s*\/JavaScript/i', '/S /None', $pdf);

        // Strip /JS literal strings with balanced parentheses: /JS (app.alert(...)) -> /JS ()
        $pos = 0;
        while (($jsPos = stripos($pdf, '/JS', $pos)) !== false) {
            $after = $jsPos + 3;
            while ($after < strlen($pdf) && ctype_space($pdf[$after])) {
                $after++;
            }
            if ($after >= strlen($pdf)) {
                break;
            }

            if ($pdf[$after] === '(') {
                $depth = 1;
                $i = $after + 1;
                $escaped = false;
                while ($i < strlen($pdf) && $depth > 0) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($pdf[$i] === '\\') {
                        $escaped = true;
                    } elseif ($pdf[$i] === '(') {
                        $depth++;
                    } elseif ($pdf[$i] === ')') {
                        $depth--;
                    }
                    $i++;
                }

                if ($depth === 0) {
                    $pdf = substr($pdf, 0, $after).'()'.substr($pdf, $i);
                    $pos = $after + 2;

                    continue;
                }
            } elseif ($pdf[$after] === '<' && substr($pdf, $after, 2) !== '<<') {
                $close = strpos($pdf, '>', $after);
                if ($close !== false) {
                    $pdf = substr($pdf, 0, $after).'<>'.substr($pdf, $close + 1);
                    $pos = $after + 2;

                    continue;
                }
            }

            $pos = $after;
        }

        // Replace any remaining /JavaScript keyword with /None
        $pdf = preg_replace('/\/JavaScript\b/i', '/None', $pdf);

        // Strip /Launch actions
        $pdf = preg_replace('/\/S\s*\/Launch/i', '/S /None', $pdf);
        $pdf = preg_replace('/\/Launch\s*<<.*?>>/is', '', $pdf);

        // Strip /EmbeddedFiles references
        $pdf = preg_replace('/\/EmbeddedFiles\s+\d+\s+\d+\s+R/i', '', $pdf);
        $pdf = preg_replace('/\/EmbeddedFiles\s*<<.*?>>/is', '', $pdf);

        return $pdf;
    }

    /**
     * Active Content Removal for Office files: verify macro absence, strip active objects.
     *
     * @return array{valid: bool, error?: string, details?: array<string, mixed>}
     */
    protected function sanitizeOffice(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return ['valid' => false, 'error' => 'Failed to open Office document archive.'];
        }

        $numFiles = $zip->numFiles;
        $hasMacros = false;
        $macroFiles = [];

        for ($i = 0; $i < $numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            $lower = strtolower($name);

            // Detect VBA projects, macros, and executable bin files
            if (
                str_contains($lower, 'vbaproject') ||
                str_contains($lower, 'vba_data') ||
                str_ends_with($lower, '.vba') ||
                str_ends_with($lower, '.bas') ||
                str_ends_with($lower, '.cls') ||
                (str_starts_with($lower, 'word/embeddings/oleobject') && str_ends_with($lower, '.bin')) ||
                (str_starts_with($lower, 'xl/embeddings/oleobject') && str_ends_with($lower, '.bin'))
            ) {
                $hasMacros = true;
                $macroFiles[] = $name;
            }
        }

        // Check [Content_Types].xml for macro-enabled content types
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        if ($contentTypes !== false) {
            if (
                stripos($contentTypes, 'vbaProject') !== false ||
                stripos($contentTypes, 'macroEnabled') !== false ||
                stripos($contentTypes, 'application/vnd.ms-office.vbaProject') !== false
            ) {
                $hasMacros = true;
                $macroFiles[] = '[Content_Types].xml (macro content type)';
            }
        }

        $zip->close();

        if ($hasMacros) {
            return [
                'valid' => false,
                'error' => 'Macro-enabled Office document or active VBA project detected: '.implode(', ', $macroFiles),
                'details' => ['macro_files' => $macroFiles],
            ];
        }

        return [
            'valid' => true,
            'details' => ['macros_detected' => false],
        ];
    }

    /**
     * Active Content Removal for Images: re-encode via GD to discard non-pixel payloads.
     *
     * @return array{valid: bool, error?: string, details?: array<string, mixed>}
     */
    protected function sanitizeImage(string $filePath, string $ext): array
    {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            if (! function_exists('imagecreatefromjpeg') || ! function_exists('imagejpeg')) {
                return ['valid' => false, 'error' => 'PHP GD extension is not available for JPEG processing.'];
            }

            $image = @imagecreatefromjpeg($filePath);
            if (! $image) {
                return ['valid' => false, 'error' => 'Invalid or corrupt JPEG image payload.'];
            }

            $saved = @imagejpeg($image, $filePath, 95);
            imagedestroy($image);

            if (! $saved) {
                return ['valid' => false, 'error' => 'Failed to re-encode JPEG image.'];
            }

            return ['valid' => true, 'details' => ['reencoded' => true]];
        }

        if ($ext === 'png') {
            if (! function_exists('imagecreatefrompng') || ! function_exists('imagepng')) {
                return ['valid' => false, 'error' => 'PHP GD extension is not available for PNG processing.'];
            }

            $image = @imagecreatefrompng($filePath);
            if (! $image) {
                return ['valid' => false, 'error' => 'Invalid or corrupt PNG image payload.'];
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = @imagepng($image, $filePath);
            imagedestroy($image);

            if (! $saved) {
                return ['valid' => false, 'error' => 'Failed to re-encode PNG image.'];
            }

            return ['valid' => true, 'details' => ['reencoded' => true]];
        }

        return ['valid' => true];
    }

    /**
     * Quarantine a rejected or infected file.
     */
    protected function quarantineFile(string $filePath, string $reason): ?string
    {
        try {
            if (! file_exists($this->quarantineDir)) {
                @mkdir($this->quarantineDir, 0750, true);
            }

            $baseName = pathinfo($filePath, PATHINFO_BASENAME);
            $safeName = sprintf('%s_%s_%s_%s.quarantine', date('Ymd_His'), $reason, Str::random(8), $baseName);
            $quarantinePath = rtrim($this->quarantineDir, '/').'/'.$safeName;

            @copy($filePath, $quarantinePath);
            @chmod($quarantinePath, 0600);

            return $quarantinePath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check whether a CLI command exists on the host.
     */
    protected function hasCommand(string $command): bool
    {
        $escaped = escapeshellcmd($command);
        $output = shell_exec("which {$escaped} 2>/dev/null");

        return ! empty($output) && trim($output) !== '';
    }
}
