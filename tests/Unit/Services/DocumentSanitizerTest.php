<?php

namespace Tests\Unit\Services;

use App\Services\Dms\Sanitizer\ClamAvResult;
use App\Services\Dms\Sanitizer\ClamAvScanner;
use App\Services\Dms\Sanitizer\DocumentSanitizerService;
use App\Services\Dms\Sanitizer\MetadataScrubber;
use App\Services\Dms\Sanitizer\SanitizerResult;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class DocumentSanitizerTest extends TestCase
{
    protected string $tempDir;

    protected string $quarantineDir;

    protected DocumentSanitizerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/sanitizer_test_'.uniqid();
        $this->quarantineDir = sys_get_temp_dir().'/sanitizer_quarantine_'.uniqid();

        @mkdir($this->tempDir, 0777, true);
        @mkdir($this->quarantineDir, 0777, true);

        ClamAvScanner::resetFake();
        $this->service = new DocumentSanitizerService(
            clamAvScanner: new ClamAvScanner,
            metadataScrubber: new MetadataScrubber,
            quarantineDir: $this->quarantineDir
        );
    }

    protected function tearDown(): void
    {
        ClamAvScanner::resetFake();
        File::deleteDirectory($this->tempDir);
        File::deleteDirectory($this->quarantineDir);

        parent::tearDown();
    }

    /**
     * Acceptance Criterion:
     * Uploading an EICAR test file returns an infected status and triggers quarantine.
     */
    public function test_uploading_an_eicar_test_file_returns_infected_status_and_triggers_quarantine(): void
    {
        $eicarPayload = ClamAvScanner::EICAR_SIGNATURE;
        $filePath = $this->tempDir.'/eicar.pdf';
        file_put_contents($filePath, "%PDF-1.4\n".$eicarPayload);

        $result = $this->service->sanitize($filePath, 'pdf');

        $this->assertInstanceOf(SanitizerResult::class, $result);
        $this->assertTrue($result->isInfected());
        $this->assertSame('infected', $result->status);
        $this->assertFalse($result->isClean());
        $this->assertStringContainsString('Malware detected', (string) $result->error);
        $this->assertNotNull($result->quarantinedPath);
        $this->assertFileExists($result->quarantinedPath);

        $quarantinedContent = file_get_contents($result->quarantinedPath);
        $this->assertStringContainsString(ClamAvScanner::EICAR_SIGNATURE, $quarantinedContent);
    }

    /**
     * Acceptance Criterion:
     * PDF containing JavaScript has scripts stripped.
     */
    public function test_pdf_containing_javascript_has_scripts_stripped(): void
    {
        $pdfWithJs = <<<'PDF'
%PDF-1.4
1 0 obj
<<
  /Type /Catalog
  /Pages 2 0 R
  /OpenAction 3 0 R
  /Names << /JavaScript 5 0 R >>
>>
endobj
2 0 obj
<<
  /Type /Pages
  /Kids [4 0 R]
  /Count 1
>>
endobj
3 0 obj
<<
  /Type /Action
  /S /JavaScript
  /JS (app.alert("Malicious script executed!");)
>>
endobj
4 0 obj
<<
  /Type /Page
  /Parent 2 0 R
  /MediaBox [0 0 612 792]
>>
endobj
5 0 obj
<<
  /Names [ (Exploit) 3 0 R ]
>>
endobj
xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000120 00000 n 
0000000179 00000 n 
0000000261 00000 n 
0000000343 00000 n 
trailer
<<
  /Size 6
  /Root 1 0 R
>>
startxref
400
%%EOF
PDF;

        $filePath = $this->tempDir.'/document_with_script.pdf';
        file_put_contents($filePath, $pdfWithJs);

        // Pre-assertion: original has JavaScript and alert
        $this->assertStringContainsString('JavaScript', file_get_contents($filePath));
        $this->assertStringContainsString('app.alert', file_get_contents($filePath));

        $result = $this->service->sanitize($filePath, 'pdf');

        $this->assertTrue($result->isClean());
        $this->assertSame('clean', $result->status);
        $this->assertNull($result->error);

        $sanitizedContent = file_get_contents($filePath);
        $this->assertStringNotContainsString('JavaScript', $sanitizedContent);
        $this->assertStringNotContainsString('app.alert', $sanitizedContent);
        $this->assertStringNotContainsString('Malicious script executed', $sanitizedContent);
    }

    /**
     * Acceptance Criterion:
     * Images have EXIF metadata wiped completely.
     */
    public function test_images_have_exif_metadata_wiped_completely(): void
    {
        // 1. Create a true color JPEG
        $im = imagecreatetruecolor(40, 40);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefill($im, 0, 0, $red);
        $filePath = $this->tempDir.'/photo_with_exif.jpg';
        imagejpeg($im, $filePath, 95);
        imagedestroy($im);

        // 2. Inject an APP1 segment with Exif header containing Software = 'SpyCamera v9.0'
        $softwarePayload = "SpyCamera v9.0\x00";
        $exifData = "Exif\x00\x00II*\x00\x08\x00\x00\x00\x01\x00\x31\x01\x02\x00".pack('V', strlen($softwarePayload))."\x1a\x00\x00\x00\x00\x00\x00\x00".$softwarePayload;
        $app1Segment = "\xFF\xE1".pack('n', strlen($exifData) + 2).$exifData;

        $rawJpeg = file_get_contents($filePath);
        $rawWithExif = substr($rawJpeg, 0, 2).$app1Segment.substr($rawJpeg, 2);
        file_put_contents($filePath, $rawWithExif);

        // Verify EXIF was injected and readable
        $exifBefore = @exif_read_data($filePath);
        $this->assertIsArray($exifBefore);
        $this->assertSame('SpyCamera v9.0', $exifBefore['Software'] ?? null);

        // 3. Run Sanitizer
        $result = $this->service->sanitize($filePath, 'jpg');

        $this->assertTrue($result->isClean());
        $this->assertSame('clean', $result->status);

        // 4. Verify all EXIF metadata has been wiped
        $exifAfter = @exif_read_data($filePath);
        $this->assertNotSame('SpyCamera v9.0', $exifAfter['Software'] ?? null);
        $this->assertEmpty($exifAfter['Software'] ?? null);
        $this->assertEmpty($exifAfter['SectionsFound'] ?? null);
    }

    /**
     * Test Step 1: Disallowed file types are rejected and quarantined.
     */
    public function test_disallowed_file_types_are_rejected_and_quarantined(): void
    {
        $disallowed = [
            'malware.exe' => 'This is an executable payload',
            'script.sh' => '#!/bin/bash\nrm -rf /',
            'exploit.php' => '<?php system($_GET["c"]);',
            'archive.zip' => "PK\x03\x04empty",
            'macro_doc.docm' => "PK\x03\x04macro doc",
            'macro_sheet.xlsm' => "PK\x03\x04macro xls",
            'vector.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'web.html' => '<html><head><script>alert(1)</script></head><body>hi</body></html>',
        ];

        foreach ($disallowed as $fileName => $content) {
            $path = $this->tempDir.'/'.$fileName;
            file_put_contents($path, $content);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);

            $result = $this->service->sanitize($path, $ext);

            $this->assertTrue($result->isRejected(), "File {$fileName} should be rejected");
            $this->assertSame('rejected', $result->status);
            $this->assertNotNull($result->quarantinedPath, "File {$fileName} should have a quarantine path");
            $this->assertFileExists($result->quarantinedPath);
            $this->assertStringContainsString('not allowed', (string) $result->error);
        }
    }

    /**
     * Test Step 2: Real Content Check rejects executables masked as allowed extensions.
     */
    public function test_real_content_check_rejects_executable_masked_as_pdf(): void
    {
        $path = $this->tempDir.'/fake_invoice.pdf';
        // Shell script disguised with .pdf extension
        file_put_contents($path, "#!/bin/bash\necho 'running backdoor'\n");

        $result = $this->service->sanitize($path, 'pdf');

        $this->assertTrue($result->isRejected());
        $this->assertSame('rejected', $result->status);
        $this->assertNotNull($result->quarantinedPath);
        $this->assertFileExists($result->quarantinedPath);
    }

    /**
     * Test Step 2: Zip bomb ratio / entry limit rejection.
     */
    public function test_zip_container_with_nested_archive_is_rejected(): void
    {
        $docxPath = $this->tempDir.'/nested_bomb.docx';

        $zip = new ZipArchive;
        $zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types></Types>');
        $zip->addFromString('word/document.xml', '<w:document></w:document>');
        $zip->addFromString('word/embedded_payload.zip', "PK\x03\x04nested payload archive");
        $zip->close();

        $result = $this->service->sanitize($docxPath, 'docx');

        $this->assertTrue($result->isRejected());
        $this->assertSame('rejected', $result->status);
        $this->assertStringContainsString('nested archive', (string) $result->error);
        $this->assertNotNull($result->quarantinedPath);
        $this->assertFileExists($result->quarantinedPath);
    }

    /**
     * Test Step 4: Office file with VBA project macro is rejected and quarantined.
     */
    public function test_office_document_with_vba_macros_is_rejected_and_quarantined(): void
    {
        $docxPath = $this->tempDir.'/macro_document.docx';

        $zip = new ZipArchive;
        $zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types></Types>');
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:t>Hello</w:t></w:p></w:body></w:document>');
        $zip->addFromString('word/vbaProject.bin', 'binary vba code here');
        $zip->close();

        $result = $this->service->sanitize($docxPath, 'docx');

        $this->assertTrue($result->isRejected());
        $this->assertSame('rejected', $result->status);
        $this->assertStringContainsString('Macro-enabled', (string) $result->error);
        $this->assertNotNull($result->quarantinedPath);
        $this->assertFileExists($result->quarantinedPath);
    }

    /**
     * Test Step 5: Office document metadata scrubbing (author, company, lastModifiedBy).
     */
    public function test_office_metadata_is_scrubbed(): void
    {
        $docxPath = $this->tempDir.'/corporate_doc.docx';

        $zip = new ZipArchive;
        $zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types></Types>');
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:t>Confidential Report</w:t></w:p></w:body></w:document>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties><dc:creator>Alice Secret</dc:creator><cp:lastModifiedBy>Bob Admin</cp:lastModifiedBy></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0"?><Properties><Company>Defense Corp</Company><Manager>Top Executive</Manager></Properties>');
        $zip->addFromString('docProps/custom.xml', '<?xml version="1.0"?><Properties><CustomProp>Confidential</CustomProp></Properties>');
        $zip->close();

        $result = $this->service->sanitize($docxPath, 'docx');

        $this->assertTrue($result->isClean());

        // Inspect scrubbed DOCX
        $readZip = new ZipArchive;
        $readZip->open($docxPath);

        $coreXml = $readZip->getFromName('docProps/core.xml');
        $appXml = $readZip->getFromName('docProps/app.xml');
        $hasCustom = $readZip->locateName('docProps/custom.xml') !== false;
        $readZip->close();

        $this->assertStringNotContainsString('Alice Secret', $coreXml);
        $this->assertStringNotContainsString('Bob Admin', $coreXml);
        $this->assertStringNotContainsString('Defense Corp', (string) $appXml);
        $this->assertStringNotContainsString('Top Executive', (string) $appXml);
        $this->assertFalse($hasCustom, 'docProps/custom.xml should be deleted');
    }

    /**
     * Test PDF metadata scrub via MetadataScrubber.
     */
    public function test_pdf_metadata_is_scrubbed(): void
    {
        $pdfPath = $this->tempDir.'/metadata_test.pdf';
        $pdf = <<<'PDF'
%PDF-1.4
1 0 obj
<<
  /Type /Catalog
  /Pages 2 0 R
>>
endobj
2 0 obj
<<
  /Type /Pages
  /Kids [3 0 R]
  /Count 1
>>
endobj
3 0 obj
<<
  /Type /Page
  /Parent 2 0 R
  /MediaBox [0 0 612 792]
>>
endobj
4 0 obj
<<
  /Author (Secret Author)
  /Title (Classified Document)
  /Company (Secret Corp)
>>
endobj
xref
0 5
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000185 00000 n 
trailer
<<
  /Size 5
  /Root 1 0 R
  /Info 4 0 R
>>
startxref
260
%%EOF
PDF;
        file_put_contents($pdfPath, $pdf);

        $result = $this->service->sanitize($pdfPath, 'pdf');

        $this->assertTrue($result->isClean());
        $content = file_get_contents($pdfPath);
        $this->assertStringNotContainsString('Secret Author', $content);
        $this->assertStringNotContainsString('Classified Document', $content);
        $this->assertStringNotContainsString('Secret Corp', $content);
    }

    /**
     * Test PNG re-encoding and clean status.
     */
    public function test_valid_png_is_cleaned_successfully(): void
    {
        $im = imagecreatetruecolor(20, 20);
        $blue = imagecolorallocate($im, 0, 0, 255);
        imagefill($im, 0, 0, $blue);
        $pngPath = $this->tempDir.'/valid.png';
        imagepng($im, $pngPath);
        imagedestroy($im);

        $result = $this->service->sanitize($pngPath, 'png');

        $this->assertTrue($result->isClean());
        $this->assertSame('image/png', $result->mimeType);
        $this->assertFileExists($pngPath);
    }

    /**
     * Test ClamAV scanner mock/fake integration for custom virus detection.
     */
    public function test_clamav_scanner_custom_infection_detection(): void
    {
        ClamAvScanner::fake(function (string $path) {
            return ClamAvResult::infected('Trojan.PDF.Dropper-99');
        });

        $pdfPath = $this->tempDir.'/custom_trojan.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $result = $this->service->sanitize($pdfPath, 'pdf');

        $this->assertTrue($result->isInfected());
        $this->assertSame('infected', $result->status);
        $this->assertStringContainsString('Trojan.PDF.Dropper-99', (string) $result->error);
        $this->assertNotNull($result->quarantinedPath);
        $this->assertFileExists($result->quarantinedPath);
    }
}
