<?php

namespace Tests\Feature\Admin;

use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Models\Group;
use App\Models\User;
use App\Services\Dms\Audit\DmsAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmsAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected string $testLogPath;

    protected string $vaultBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testLogPath = storage_path('logs/dms-audit-test.log');
        DmsAuditLogger::setLogPath($this->testLogPath);

        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }

        $this->vaultBaseDir = storage_path('app/dms_vault');
        if (! File::exists($this->vaultBaseDir)) {
            File::makeDirectory($this->vaultBaseDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testLogPath)) {
            File::delete($this->testLogPath);
        }

        if (File::exists($this->vaultBaseDir)) {
            File::deleteDirectory($this->vaultBaseDir);
        }

        DmsAuditLogger::reset();

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
     * Helper to create a clean document and its vault file.
     */
    protected function createCleanDocument(array $attributes = [], ?DmsFolder $folder = null): DmsDocument
    {
        $group = Group::factory()->create();

        $folder = $folder ?? DmsFolder::create([
            'name_en' => 'General Reports',
            'name_ar' => 'تقارير عامة',
            'required_role' => null,
            'group_id' => $group->id,
        ]);

        $docId = (string) Str::uuid();
        $storedName = "{$docId}_minutes.pdf";
        $folderDir = storage_path("app/dms_vault/{$folder->id}");
        if (! File::exists($folderDir)) {
            File::makeDirectory($folderDir, 0755, true);
        }

        $fullPath = "{$folderDir}/{$storedName}";
        file_put_contents($fullPath, '%PDF-1.4 test document content');

        return DmsDocument::create(array_merge([
            'id' => $docId,
            'folder_id' => $folder->id,
            'user_id' => User::factory()->create(['group_id' => $group->id])->id,
            'original_name' => 'minutes.pdf',
            'sanitized_name' => 'minutes.pdf',
            'file_path' => "{$folder->id}/{$storedName}",
            'file_size' => strlen('%PDF-1.4 test document content'),
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash('sha256', '%PDF-1.4 test document content'),
            'version' => 1,
            'status' => 'clean',
        ], $attributes));
    }

    public function test_file_download_writes_structured_jsonl_event(): void
    {
        $group = Group::factory()->create(['id' => 3]);
        $folder = DmsFolder::create([
            'name_en' => 'General Reports',
            'name_ar' => 'تقارير عامة',
            'required_role' => null,
            'group_id' => $group->id,
        ]);

        $document = $this->createCleanDocument([], $folder);

        $admin = $this->createMfaUser([
            'id' => 42,
            'email' => 'gsr.maadi@aaegypt.org',
            'role' => 'web_admin',
            'group_id' => 3,
        ]);

        $response = $this->actingAsMfaUser($admin)
            ->get("/service/documents/{$document->id}/download");

        $response->assertStatus(200);

        // Verify audit log file exists and contains the structured JSONL record
        $this->assertFileExists($this->testLogPath);

        $content = file_get_contents($this->testLogPath);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertNotEmpty($lines);

        $lastRecord = json_decode(end($lines), true);
        $this->assertIsArray($lastRecord);

        // Required JSON schema assertions:
        // {"timestamp": "ISO8601", "event": "upload|download|delete", "user_id": 42,
        //  "user_email": "gsr.maadi@aaegypt.org", "group_id": 3, "doc_id": "uuid",
        //  "doc_name": "minutes.pdf", "sha256": "hash...", "ip": "1.2.3.4", "status": "clean|infected"}
        $this->assertArrayHasKey('timestamp', $lastRecord);
        $this->assertSame('download', $lastRecord['event']);
        $this->assertSame(42, $lastRecord['user_id']);
        $this->assertSame('gsr.maadi@aaegypt.org', $lastRecord['user_email']);
        $this->assertSame(3, $lastRecord['group_id']);
        $this->assertSame($document->id, $lastRecord['doc_id']);
        $this->assertSame('minutes.pdf', $lastRecord['doc_name']);
        $this->assertSame($document->sha256_hash, $lastRecord['sha256']);
        $this->assertArrayHasKey('ip', $lastRecord);
        $this->assertSame('clean', $lastRecord['status']);
    }

    public function test_file_upload_writes_structured_jsonl_event(): void
    {
        $group = Group::factory()->create(['id' => 5]);

        $folder = DmsFolder::create([
            'name_en' => 'Uploads',
            'name_ar' => 'المرفوعات',
            'required_role' => null,
            'group_id' => $group->id,
        ]);

        $admin = $this->createMfaUser([
            'id' => 88,
            'email' => 'admin@aaegypt.org',
            'role' => 'web_admin',
            'group_id' => $group->id,
        ]);

        $fakeFile = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->actingAsMfaUser($admin)
            ->post('/service/documents/upload', [
                'folder_id' => $folder->id,
                'file' => $fakeFile,
            ]);

        $response->assertStatus(302);

        $this->assertFileExists($this->testLogPath);
        $content = file_get_contents($this->testLogPath);
        $lines = array_filter(explode("\n", trim($content)));

        $uploadRecord = null;
        foreach ($lines as $line) {
            $rec = json_decode($line, true);
            if (($rec['event'] ?? '') === 'upload') {
                $uploadRecord = $rec;
                break;
            }
        }

        $this->assertNotNull($uploadRecord);
        $this->assertSame('upload', $uploadRecord['event']);
        $this->assertSame(88, $uploadRecord['user_id']);
        $this->assertSame('admin@aaegypt.org', $uploadRecord['user_email']);
        $this->assertSame(5, $uploadRecord['group_id']);
        $this->assertSame('report.pdf', $uploadRecord['doc_name']);
        $this->assertSame('clean', $uploadRecord['status']);
    }

    public function test_version_bump_writes_structured_jsonl_event(): void
    {
        $group = Group::factory()->create(['id' => 2]);

        $folder = DmsFolder::create([
            'name_en' => 'Proposals',
            'name_ar' => 'المقترحات',
            'required_role' => null,
            'group_id' => $group->id,
        ]);

        $admin = $this->createMfaUser([
            'role' => 'web_admin',
            'group_id' => $group->id,
        ]);

        // First upload (version 1)
        $file1 = UploadedFile::fake()->create('charter.pdf', 300, 'application/pdf');
        $this->actingAsMfaUser($admin)->post('/service/documents/upload', [
            'folder_id' => $folder->id,
            'file' => $file1,
        ]);

        // Second upload with same name (version 2 bump)
        $file2 = UploadedFile::fake()->create('charter.pdf', 400, 'application/pdf');
        $this->actingAsMfaUser($admin)->post('/service/documents/upload', [
            'folder_id' => $folder->id,
            'file' => $file2,
        ]);

        $content = file_get_contents($this->testLogPath);
        $lines = array_filter(explode("\n", trim($content)));

        $versionBumpFound = false;
        foreach ($lines as $line) {
            $rec = json_decode($line, true);
            if (($rec['event'] ?? '') === 'version_bump') {
                $versionBumpFound = true;
                $this->assertSame('charter.pdf', $rec['doc_name']);
                break;
            }
        }

        $this->assertTrue($versionBumpFound, 'Failed to find version_bump event in audit log.');
    }

    public function test_scan_pass_and_scan_fail_write_structured_jsonl_events(): void
    {
        $document = $this->createCleanDocument(['original_name' => 'scan_test.pdf']);
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        // Test scan pass
        DmsAuditLogger::logScanPass($document, $admin);

        // Test scan fail
        DmsAuditLogger::logScanFail($document, $admin);

        $entries = DmsAuditLogger::getEntries();
        $scanPass = $entries->firstWhere('event', 'scan_pass');
        $scanFail = $entries->firstWhere('event', 'scan_fail');

        $this->assertNotNull($scanPass);
        $this->assertSame('clean', $scanPass['status']);
        $this->assertSame($document->id, $scanPass['doc_id']);

        $this->assertNotNull($scanFail);
        $this->assertSame('infected', $scanFail['status']);
        $this->assertSame($document->id, $scanFail['doc_id']);
    }

    public function test_file_delete_writes_structured_jsonl_event(): void
    {
        $document = $this->createCleanDocument(['original_name' => 'to_delete.pdf']);
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->delete("/service/documents/{$document->id}");

        $response->assertStatus(302);

        $entries = DmsAuditLogger::getEntries();
        $deleteEntry = $entries->firstWhere('event', 'delete');

        $this->assertNotNull($deleteEntry);
        $this->assertSame('to_delete.pdf', $deleteEntry['doc_name']);
        $this->assertSame($document->id, $deleteEntry['doc_id']);
        $this->assertSame($admin->id, $deleteEntry['user_id']);
    }

    public function test_permission_change_writes_structured_jsonl_event(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);
        $group = Group::factory()->create();
        $targetUser = User::factory()->create([
            'role' => 'gsr',
            'group_id' => null,
        ]);

        $response = $this->actingAs($admin)->postJson('/admin/access-matrix', [
            'user_id' => $targetUser->id,
            'role' => 'delegate',
            'group_id' => $group->id,
        ]);

        $response->assertStatus(200);

        $entries = DmsAuditLogger::getEntries();
        $permEntry = $entries->firstWhere('event', 'permission_change');

        $this->assertNotNull($permEntry);
        $this->assertSame('permission_change', $permEntry['event']);
        $this->assertSame($admin->id, $permEntry['user_id']);
    }

    public function test_log_file_permissions_are_0640(): void
    {
        DmsAuditLogger::log('test_action', [
            'id' => (string) Str::uuid(),
            'original_name' => 'permissions_check.pdf',
        ]);

        $this->assertFileExists($this->testLogPath);
        $perms = decoct(fileperms($this->testLogPath) & 0777);
        $this->assertSame('640', $perms);
    }

    public function test_web_admin_can_view_audit_logs(): void
    {
        DmsAuditLogger::log('upload', [
            'id' => (string) Str::uuid(),
            'original_name' => 'view_test.pdf',
            'user_id' => 10,
            'user_email' => 'member@aaegypt.org',
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get('/admin/dms/audit-logs');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dms.audit-logs');
        $response->assertSee('view_test.pdf');
        $response->assertSee('member@aaegypt.org');
        $response->assertSee('DMS Audit Logs');
    }

    public function test_web_admin_can_inspect_real_time_stream_via_json(): void
    {
        $uuid = (string) Str::uuid();
        DmsAuditLogger::log('download', [
            'id' => $uuid,
            'original_name' => 'realtime.pdf',
            'user_id' => 42,
            'user_email' => 'realtime@aaegypt.org',
            'group_id' => 3,
            'sha256' => 'aabbcc112233',
            'status' => 'clean',
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->getJson('/admin/dms/audit-logs?format=json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'log_path',
            'total',
            'filters',
            'data',
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($uuid, $data[0]['doc_id']);
        $this->assertSame('realtime.pdf', $data[0]['doc_name']);
        $this->assertSame('download', $data[0]['event']);
    }

    public function test_non_admin_cannot_view_audit_logs(): void
    {
        $gsr = $this->createMfaUser(['role' => 'gsr']);

        $response = $this->actingAsMfaUser($gsr)
            ->get('/admin/dms/audit-logs');

        $response->assertStatus(403);
    }

    public function test_admin_can_filter_logs_by_action(): void
    {
        DmsAuditLogger::log('upload', ['id' => '1', 'original_name' => 'file1.pdf']);
        DmsAuditLogger::log('download', ['id' => '2', 'original_name' => 'file2.pdf']);
        DmsAuditLogger::log('delete', ['id' => '3', 'original_name' => 'file3.pdf']);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->getJson('/admin/dms/audit-logs?action=download');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('download', $data[0]['event']);
        $this->assertSame('file2.pdf', $data[0]['doc_name']);
    }

    public function test_admin_can_filter_logs_by_user(): void
    {
        DmsAuditLogger::log('upload', [
            'id' => '1',
            'original_name' => 'doc1.pdf',
            'user_id' => 101,
            'user_email' => 'user101@aaegypt.org',
        ]);
        DmsAuditLogger::log('upload', [
            'id' => '2',
            'original_name' => 'doc2.pdf',
            'user_id' => 202,
            'user_email' => 'user202@aaegypt.org',
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->getJson('/admin/dms/audit-logs?user=user202@aaegypt.org');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('user202@aaegypt.org', $data[0]['user_email']);
    }

    public function test_admin_can_filter_logs_by_date_range(): void
    {
        // Entry from yesterday
        $yesterday = now()->subDays(1)->toIso8601String();
        DmsAuditLogger::log('upload', [
            'id' => '1',
            'original_name' => 'yesterday.pdf',
            'timestamp' => $yesterday,
        ], extra: ['timestamp' => $yesterday]);

        // Entry from today
        $today = now()->toIso8601String();
        DmsAuditLogger::log('upload', [
            'id' => '2',
            'original_name' => 'today.pdf',
            'timestamp' => $today,
        ], extra: ['timestamp' => $today]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $todayDate = now()->toDateString();
        $response = $this->actingAsMfaUser($admin)
            ->getJson("/admin/dms/audit-logs?date_from={$todayDate}&date_to={$todayDate}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('today.pdf', $data[0]['doc_name']);
    }

    public function test_records_are_immutable_and_cannot_be_updated_or_deleted(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $putResponse = $this->actingAsMfaUser($admin)->put('/admin/dms/audit-logs/1', [
            'event' => 'tampered',
        ]);
        $putResponse->assertStatus(405);

        $patchResponse = $this->actingAsMfaUser($admin)->patch('/admin/dms/audit-logs/1', [
            'event' => 'tampered',
        ]);
        $patchResponse->assertStatus(405);

        $deleteResponse = $this->actingAsMfaUser($admin)->delete('/admin/dms/audit-logs/1');
        $deleteResponse->assertStatus(405);
    }
}
