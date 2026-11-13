<?php

namespace App\Services\Dms\Sanitizer;

use Illuminate\Support\Facades\Process;
use ZipArchive;

class MetadataScrubber
{
    /**
     * Scrub metadata from the file.
     */
    public function scrub(string $filePath, string $extension): bool
    {
        if (! file_exists($filePath) || ! is_writable($filePath)) {
            return false;
        }

        $ext = strtolower(ltrim($extension, '.'));

        // 1. Try mat2 if installed on host
        if ($this->hasCommand('mat2')) {
            $escaped = escapeshellarg($filePath);
            $result = Process::run("mat2 --inplace {$escaped}");
            if ($result->successful()) {
                return true;
            }
        }

        // 2. Try exiftool if installed on host
        if ($this->hasCommand('exiftool')) {
            $escaped = escapeshellarg($filePath);
            $result = Process::run("exiftool -all= -overwrite_original {$escaped}");
            if ($result->successful()) {
                return true;
            }
        }

        // 3. Fallback PHP metadata cleaners
        return $this->scrubViaPhpFallback($filePath, $ext);
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

    /**
     * Fallback PHP metadata cleaners per file type.
     */
    public function scrubViaPhpFallback(string $filePath, string $ext): bool
    {
        return match ($ext) {
            'jpg', 'jpeg' => $this->scrubJpeg($filePath),
            'png' => $this->scrubPng($filePath),
            'pdf' => $this->scrubPdf($filePath),
            'docx', 'xlsx', 'pptx' => $this->scrubOffice($filePath),
            default => true,
        };
    }

    /**
     * Scrub EXIF and metadata from JPEG.
     */
    public function scrubJpeg(string $filePath): bool
    {
        if (! function_exists('imagecreatefromjpeg') || ! function_exists('imagejpeg')) {
            return $this->stripJpegMarkersDirectly($filePath);
        }

        $image = @imagecreatefromjpeg($filePath);
        if ($image) {
            @imagejpeg($image, $filePath, 95);
            imagedestroy($image);
        }

        // Strip any residual APP / COM segments
        return $this->stripJpegMarkersDirectly($filePath);
    }

    /**
     * Parse JPEG binary and drop APP1-APP15 and COM segments.
     */
    protected function stripJpegMarkersDirectly(string $filePath): bool
    {
        $content = @file_get_contents($filePath);
        if ($content === false || strlen($content) < 4 || substr($content, 0, 2) !== "\xFF\xD8") {
            return false;
        }

        $pos = 2;
        $len = strlen($content);
        $clean = "\xFF\xD8";

        while ($pos < $len) {
            if ($content[$pos] !== "\xFF") {
                $clean .= substr($content, $pos);
                break;
            }

            $marker = ord($content[$pos + 1]);

            // SOS (Start of Scan) - image entropy data follows
            if ($marker === 0xDA) {
                $clean .= substr($content, $pos);
                break;
            }

            // EOI (End of Image)
            if ($marker === 0xD9) {
                $clean .= "\xFF\xD9";
                break;
            }

            // Fill or restart marker without payload length
            if ($marker === 0x00 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $clean .= substr($content, $pos, 2);
                $pos += 2;

                continue;
            }

            if ($pos + 3 >= $len) {
                break;
            }

            $segmentLength = (ord($content[$pos + 2]) << 8) + ord($content[$pos + 3]);
            if ($segmentLength < 2) {
                break;
            }

            // Drop APP1-APP15 (0xE1 - 0xEF) and COM (0xFE)
            if (($marker >= 0xE1 && $marker <= 0xEF) || $marker === 0xFE) {
                $pos += 2 + $segmentLength;

                continue;
            }

            $clean .= substr($content, $pos, 2 + $segmentLength);
            $pos += 2 + $segmentLength;
        }

        return @file_put_contents($filePath, $clean) !== false;
    }

    /**
     * Scrub metadata from PNG by re-encoding and stripping text chunks.
     */
    public function scrubPng(string $filePath): bool
    {
        if (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
            $image = @imagecreatefrompng($filePath);
            if ($image) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                @imagepng($image, $filePath);
                imagedestroy($image);
            }
        }

        return $this->stripPngMetadataChunks($filePath);
    }

    /**
     * Strip ancillary metadata chunks from PNG (tEXt, zTXt, iTXt, eXIf, tIME).
     */
    protected function stripPngMetadataChunks(string $filePath): bool
    {
        $data = @file_get_contents($filePath);
        if ($data === false || strlen($data) < 8 || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }

        $pos = 8;
        $len = strlen($data);
        $clean = substr($data, 0, 8);
        $droppedChunks = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];

        while ($pos < $len) {
            if ($pos + 8 > $len) {
                break;
            }

            $chunkLen = unpack('N', substr($data, $pos, 4))[1];
            $chunkType = substr($data, $pos + 4, 4);
            $totalChunkLen = 12 + $chunkLen;

            if ($pos + $totalChunkLen > $len) {
                break;
            }

            if (! in_array($chunkType, $droppedChunks, true)) {
                $clean .= substr($data, $pos, $totalChunkLen);
            }

            $pos += $totalChunkLen;
        }

        return @file_put_contents($filePath, $clean) !== false;
    }

    /**
     * Scrub metadata from PDF (/Info dictionary and XMP packets).
     */
    public function scrubPdf(string $filePath): bool
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Scrub /Info keys
        $metadataKeys = [
            'Author', 'Title', 'Subject', 'Keywords', 'Creator',
            'Producer', 'CreationDate', 'ModDate', 'Company', 'Manager',
        ];

        foreach ($metadataKeys as $key) {
            // Strip string literals: /Author (John Doe) -> /Author ()
            $content = preg_replace("/\/{$key}\s*\((?>[^()\\\\]+|\\\\.)*\)/is", "/{$key} ()", $content);
            // Strip hex strings: /Author <4a6f686e> -> /Author <>
            $content = preg_replace("/\/{$key}\s*<[0-9a-fA-F\s]*>/is", "/{$key} <>", $content);
        }

        // Remove XMP metadata stream/packet
        $content = preg_replace('/<\?xpacket\s+begin=.*?<\?xpacket\s+end=.*?\?>/is', '', $content);
        $content = preg_replace('/<x:xmpmeta.*?<\/x:xmpmeta>/is', '', $content);

        return @file_put_contents($filePath, $content) !== false;
    }

    /**
     * Scrub metadata from Office OpenXML zip files (DOCX, XLSX, PPTX).
     */
    public function scrubOffice(string $filePath): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return true;
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return false;
        }

        // Clean docProps/core.xml
        $coreXml = $zip->getFromName('docProps/core.xml');
        if ($coreXml !== false) {
            $coreXml = preg_replace('/<dc:creator>.*?<\/dc:creator>/is', '<dc:creator></dc:creator>', $coreXml);
            $coreXml = preg_replace('/<cp:lastModifiedBy>.*?<\/cp:lastModifiedBy>/is', '<cp:lastModifiedBy></cp:lastModifiedBy>', $coreXml);
            $coreXml = preg_replace('/<cp:revision>.*?<\/cp:revision>/is', '<cp:revision>1</cp:revision>', $coreXml);
            $coreXml = preg_replace('/<dcterms:created[^>]*>.*?<\/dcterms:created>/is', '', $coreXml);
            $coreXml = preg_replace('/<dcterms:modified[^>]*>.*?<\/dcterms:modified>/is', '', $coreXml);
            $coreXml = preg_replace('/<dc:title>.*?<\/dc:title>/is', '', $coreXml);
            $coreXml = preg_replace('/<dc:subject>.*?<\/dc:subject>/is', '', $coreXml);
            $coreXml = preg_replace('/<dc:description>.*?<\/dc:description>/is', '', $coreXml);
            $zip->addFromString('docProps/core.xml', $coreXml);
        }

        // Clean docProps/app.xml
        $appXml = $zip->getFromName('docProps/app.xml');
        if ($appXml !== false) {
            $appXml = preg_replace('/<Company>.*?<\/Company>/is', '', $appXml);
            $appXml = preg_replace('/<Manager>.*?<\/Manager>/is', '', $appXml);
            $appXml = preg_replace('/<Application>.*?<\/Application>/is', '', $appXml);
            $zip->addFromString('docProps/app.xml', $appXml);
        }

        // Delete custom properties if present
        if ($zip->locateName('docProps/custom.xml') !== false) {
            $zip->deleteName('docProps/custom.xml');
        }

        return $zip->close();
    }
}
