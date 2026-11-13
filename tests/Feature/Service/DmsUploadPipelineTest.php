<?php

namespace Tests\Feature\Service;

use App\Jobs\ProcessDmsUploadJob;
use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Models\User;
use App\Notifications\DocumentInfectedNotification;
use App\Services\Dms\Sanitizer\ClamAvResult;
use App\Services\Dms\Sanitizer\ClamAvScanner;
use App\Services\Dms\Sanitizer\DocumentSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DmsUploadPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected string $quarantineStagingDir;

    protected string $quarantineInfectedDir;

    protected string $vaultBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quarantineStagingDir = storage_path('app/dms_quarantine');
        $this->quarantineInfectedDir = storage_path('app/dms_quarantine/infected');
        $this->vaultBaseDir = storage_path('app/dms_vault');

        @mkdir($this->quarantineStagingDir, 0777, true);
        @mkdir($this->quarantineInfectedDir, 0777, true);
        @mkdir($this->vaultBaseDir, 0777, true);

        ClamAvScanner::resetFake();
    }

    protected function tearDown(): void
    {
        ClamAvScanner::resetFake();

        // Clean up test directories
        if (File::exists($this->quarantineStagingDir)) {
            File::deleteDirectory($this->quarantineStagingDir);
        }
        if (File::exists($this->vaultBaseDir)) {
            File::deleteDirectory($this->vaultBaseDir);
        }

        parent::tearDown();
    }

    /**
     * Create an MFA-enrolled user for testing authenticated service endpoints.
     */
    protected function createMfaUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'must_change_password' => false,
        ], $attributes));
    }

    /**
     * Helper to authenticate with verified MFA session.
     */
    protected function actingAsMfaUser(User $user): self
    {
        return $this->actingAs($user)->withSession(['mfa_verified' => true]);
    }

    /**
     * Create valid minimal PDF binary content.
     */
    protected function createValidPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
            ."xref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n"
            ."trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n185\n%%EOF\n";
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $file = UploadedFile::fake()->create('report.pdf', 500);

        $response = $this->post('/service/documents/async-upload', [
            'folder_id' => $folder->id,
            'file' => $file,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_upload_to_restricted_folder(): void
    {
        $treasuryFolder = DmsFolder::create([
            'name_en' => 'Treasury',
            'name_ar' => 'الخزانة',
            'required_role' => 'treasurer',
        ]);

        $gsr = $this->createMfaUser(['role' => 'gsr']);
        $file = UploadedFile::fake()->create('treasury.pdf', 100);

        $response = $this->actingAsMfaUser($gsr)
            ->post('/service/documents/async-upload', [
                'folder_id' => $treasuryFolder->id,
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }

    public function test_intake_validates_and_rejects_files_exceeding_25mb(): void
    {
        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        // 26 MB (26624 KB > 25600 KB limit)
        $largeFile = UploadedFile::fake()->create('huge.pdf', 26624);

        $response = $this->actingAsMfaUser($admin)
            ->postJson('/service/documents/async-upload', [
                'folder_id' => $folder->id,
                'file' => $largeFile,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_intake_validates_and_rejects_disallowed_extensions(): void
    {
        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $blockedFile = UploadedFile::fake()->create('payload.exe', 100);

        $response = $this->actingAsMfaUser($admin)
            ->postJson('/service/documents/async-upload', [
                'folder_id' => $folder->id,
                'file' => $blockedFile,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_intake_stores_raw_file_in_quarantine_with_uuid_name_and_dispatches_job(): void
    {
        Queue::fake([ProcessDmsUploadJob::class]);

        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $fileContent = $this->createValidPdfContent();
        $file = UploadedFile::fake()->createWithContent('Quarterly_Report.pdf', $fileContent);

        $response = $this->actingAsMfaUser($admin)
            ->postJson('/service/documents/async-upload', [
                'folder_id' => $folder->id,
                'file' => $file,
            ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'processing',
        ]);

        $documentId = $response->json('document_id');
        $this->assertNotNull($documentId);

        // Verify DmsDocument was created with 'processing' status
        $this->assertDatabaseHas('dms_documents', [
            'id' => $documentId,
            'folder_id' => $folder->id,
            'user_id' => $admin->id,
            'original_name' => 'Quarterly_Report.pdf',
            'sanitized_name' => 'quarterly-report.pdf',
            'status' => 'processing',
        ]);

        // Verify raw file stored in quarantine with UUID name: uuid.tmp
        $expectedQuarantineFile = storage_path("app/dms_quarantine/{$documentId}.tmp");
        $this->assertFileExists($expectedQuarantineFile);
        $this->assertSame($fileContent, file_get_contents($expectedQuarantineFile));

        // Verify ProcessDmsUploadJob dispatched with documentId
        Queue::assertPushed(ProcessDmsUploadJob::class, function (ProcessDmsUploadJob $job) use ($documentId) {
            return $job->documentId === $documentId;
        });
    }

    public function test_clean_file_transitions_to_vault_with_sha256_hash_recorded_and_temp_destroyed(): void
    {
        Queue::fake([ProcessDmsUploadJob::class]);

        $folder = DmsFolder::create([
            'name_en' => 'Meeting Minutes',
            'name_ar' => 'محاضر الاجتماعات',
        ]);

        $user = $this->createMfaUser(['role' => 'gsr']);

        $pdfContent = $this->createValidPdfContent();
        $file = UploadedFile::fake()->createWithContent('Minutes_Nov.pdf', $pdfContent);

        $response = $this->actingAsMfaUser($user)
            ->postJson('/service/documents/async-upload', [
                'folder_id' => $folder->id,
                'file' => $file,
            ]);

        $response->assertStatus(202);
        $documentId = $response->json('document_id');

        $document = DmsDocument::findOrFail($documentId);
        $tempStagingPath = storage_path("app/dms_quarantine/{$documentId}.tmp");
        $this->assertFileExists($tempStagingPath);

        // Execute the queued background job
        $job = new ProcessDmsUploadJob($documentId);
        $job->handle(app(DocumentSanitizerService::class));

        // Refresh document from database
        $document->refresh();

        // Document should be marked clean
        $this->assertSame('clean', $document->status);

        // Original temp file in quarantine staging must be destroyed
        $this->assertFileDoesNotExist($tempStagingPath);

        // File must exist in permanent vault storage
        $vaultFilePath = storage_path("app/dms_vault/{$document->file_path}");
        $this->assertFileExists($vaultFilePath);

        // SHA-256 hash must be recorded and match vaulted file
        $actualSha256 = hash_file('sha256', $vaultFilePath);
        $this->assertSame($actualSha256, $document->sha256_hash);

        // Audit log must be recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_sanitized_clean',
            'resource' => 'dms_document',
            'resource_id' => $documentId,
        ]);
    }

    public function test_infected_file_moves_to_infected_quarantine_marks_rejected_and_alerts_uploader_and_admins(): void
    {
        Queue::fake([ProcessDmsUploadJob::class]);
        Notification::fake();

        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $uploader = $this->createMfaUser(['role' => 'gsr', 'name' => 'John Uploader']);
        $admin1 = $this->createMfaUser(['role' => 'web_admin', 'name' => 'Admin One']);
        $admin2 = $this->createMfaUser(['role' => 'web_admin', 'name' => 'Admin Two']);

        $file = UploadedFile::fake()->createWithContent('virus_scan.pdf', $this->createValidPdfContent());

        $response = $this->actingAsMfaUser($uploader)
            ->postJson('/service/documents/async-upload', [
                'folder_id' => $folder->id,
                'file' => $file,
            ]);

        $documentId = $response->json('document_id');
        $tempStagingPath = storage_path("app/dms_quarantine/{$documentId}.tmp");
        $this->assertFileExists($tempStagingPath);

        // Fake ClamAV scanner to report infection
        ClamAvScanner::fake(function (string $path) {
            return ClamAvResult::infected('Win.Trojan.Generic-12345');
        });

        // Run background processing job
        $job = new ProcessDmsUploadJob($documentId);
        $job->handle(app(DocumentSanitizerService::class));

        $document = DmsDocument::findOrFail($documentId);

        // Document marked rejected
        $this->assertSame('rejected', $document->status);

        // Staging file moved to infected quarantine
        $this->assertFileDoesNotExist($tempStagingPath);

        $infectedPath = storage_path("app/dms_quarantine/infected/{$documentId}.tmp");
        $this->assertFileExists($infectedPath);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_infected',
            'resource' => 'dms_document',
            'resource_id' => $documentId,
        ]);

        // Notification dispatched to uploader and Web Admins
        Notification::assertSentTo($uploader, DocumentInfectedNotification::class, function ($notification) use ($documentId) {
            return $notification->document->id === $documentId;
        });

        Notification::assertSentTo($admin1, DocumentInfectedNotification::class, function ($notification) use ($documentId) {
            return $notification->document->id === $documentId;
        });

        Notification::assertSentTo($admin2, DocumentInfectedNotification::class, function ($notification) use ($documentId) {
            return $notification->document->id === $documentId;
        });
    }

    public function test_disguised_dangerous_content_is_rejected_and_moved_to_infected(): void
    {
        Queue::fake([ProcessDmsUploadJob::class]);
        Notification::fake();

        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $uploader = $this->createMfaUser(['role' => 'gsr']);
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        // PHP script disguised as PDF
        $dangerousContent = "<?php echo 'malicious script'; system('id'); ?>";
        $file = UploadedFile::fake()->createWithContent('exploit.pdf', $dangerousContent);

        $response = $this->actingAsMfaUser($uploader)
            ->postJson('/service/documents/async-upload', [
                'folder_id' => $folder->id,
                'file' => $file,
            ]);

        $documentId = $response->json('document_id');

        $job = new ProcessDmsUploadJob($documentId);
        $job->handle(app(DocumentSanitizerService::class));

        $document = DmsDocument::findOrFail($documentId);
        $this->assertSame('rejected', $document->status);

        // File moved to infected staging
        $infectedPath = storage_path("app/dms_quarantine/infected/{$documentId}.tmp");
        $this->assertFileExists($infectedPath);

        // Notifications sent
        Notification::assertSentTo($uploader, DocumentInfectedNotification::class);
        Notification::assertSentTo($admin, DocumentInfectedNotification::class);
    }

    public function test_quarantine_retention_purges_expired_files(): void
    {
        $infectedDir = storage_path('app/dms_quarantine/infected');
        @mkdir($infectedDir, 0777, true);

        // Recent infected file (1 day old)
        $recentFile = $infectedDir.'/recent.tmp';
        file_put_contents($recentFile, 'recent infected');
        touch($recentFile, time() - (86400 * 1));

        // Old infected file (35 days old)
        $oldFile = $infectedDir.'/old.tmp';
        file_put_contents($oldFile, 'expired infected');
        touch($oldFile, time() - (86400 * 35));

        $job = new ProcessDmsUploadJob('fake-id');
        $deleted = $job->purgeExpiredQuarantine(30);

        $this->assertSame(1, $deleted);
        $this->assertFileExists($recentFile);
        $this->assertFileDoesNotExist($oldFile);
    }

    public function test_document_status_endpoint_returns_tracking_information(): void
    {
        $folder = DmsFolder::create([
            'name_en' => 'General',
            'name_ar' => 'عام',
        ]);

        $user = $this->createMfaUser(['role' => 'gsr']);

        $document = DmsDocument::create([
            'folder_id' => $folder->id,
            'user_id' => $user->id,
            'original_name' => 'tracking_test.pdf',
            'sanitized_name' => 'tracking-test.pdf',
            'file_path' => 'dms_quarantine/test.tmp',
            'file_size' => 1234,
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash('sha256', 'test'),
            'version' => 1,
            'status' => 'processing',
        ]);

        $response = $this->actingAsMfaUser($user)
            ->getJson("/service/documents/uploads/{$document->id}/status");

        $response->assertStatus(200);
        $response->assertJson([
            'document_id' => $document->id,
            'status' => 'processing',
            'original_name' => 'tracking_test.pdf',
        ]);
    }
}
