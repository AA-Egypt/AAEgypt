<?php

namespace Tests\Feature\Service;

use App\Http\Controllers\Service\DmsDownloadController;
use App\Models\AuditLog;
use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DmsDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected string $vaultBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vaultBaseDir = storage_path('app/dms_vault');

        if (! File::exists($this->vaultBaseDir)) {
            File::makeDirectory($this->vaultBaseDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
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
     * Helper to create a clean document with an actual file on disk.
     */
    protected function createCleanDocument(array $docAttributes = [], ?DmsFolder $folder = null): DmsDocument
    {
        $folder = $folder ?? DmsFolder::create([
            'name_en' => 'Meeting Minutes',
            'name_ar' => 'محاضر الاجتماعات',
            'required_role' => null,
            'group_id' => null,
        ]);

        $docId = (string) Str::uuid();
        $sanitizedName = $docAttributes['sanitized_name'] ?? 'minutes-2026-11.pdf';
        $relativeFilePath = "{$folder->id}/{$docId}_{$sanitizedName}";

        $folderDiskDir = "{$this->vaultBaseDir}/{$folder->id}";
        if (! File::exists($folderDiskDir)) {
            File::makeDirectory($folderDiskDir, 0755, true);
        }

        $fullPath = "{$this->vaultBaseDir}/{$relativeFilePath}";
        file_put_contents($fullPath, '%PDF-1.4 Minimal Clean Test PDF Content');

        return DmsDocument::create(array_merge([
            'id' => $docId,
            'folder_id' => $folder->id,
            'user_id' => null,
            'original_name' => 'Minutes 2026-11.pdf',
            'sanitized_name' => $sanitizedName,
            'file_path' => $relativeFilePath,
            'file_size' => filesize($fullPath),
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash_file('sha256', $fullPath),
            'version' => 1,
            'status' => 'clean',
        ], $docAttributes));
    }

    public function test_direct_access_to_vault_is_blocked_by_web_server(): void
    {
        $response = $this->get('/storage/app/dms_vault/1/secret-document.pdf');
        $response->assertStatus(403);

        $responseAlias = $this->get('/storage/dms_vault/1/secret-document.pdf');
        $responseAlias->assertStatus(403);
    }

    public function test_unauthenticated_request_is_prevented_from_downloading(): void
    {
        $document = $this->createCleanDocument();

        // Web route redirects unauthenticated requests to login
        $response = $this->get("/service/documents/{$document->id}/download");
        $response->assertRedirect(route('login'));

        // Direct controller call by unauthenticated user aborts 403
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unauthorized.');

        (new DmsDownloadController)->download($document->id);
    }

    public function test_unauthorized_user_without_required_role_receives_403(): void
    {
        $treasuryFolder = DmsFolder::create([
            'name_en' => 'Treasury Reports',
            'name_ar' => 'تقارير الخزانة',
            'required_role' => 'treasurer',
        ]);

        $document = $this->createCleanDocument([], $treasuryFolder);
        $gsrUser = $this->createMfaUser(['role' => 'gsr']);

        $response = $this->actingAsMfaUser($gsrUser)
            ->get("/service/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_from_different_group_receives_403(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $folderB = DmsFolder::create([
            'name_en' => 'Group B Documents',
            'name_ar' => 'وثائق مجموعة ب',
            'group_id' => $groupB->id,
        ]);

        $documentB = $this->createCleanDocument([], $folderB);
        $gsrA = $this->createMfaUser([
            'role' => 'gsr',
            'group_id' => $groupA->id,
        ]);

        $response = $this->actingAsMfaUser($gsrA)
            ->get("/service/documents/{$documentB->id}/download");

        $response->assertStatus(403);
    }

    public function test_authorized_user_downloads_clean_document_with_mandatory_security_headers(): void
    {
        $group = Group::factory()->create(['name_en' => 'Group Alpha']);
        $folder = DmsFolder::create([
            'name_en' => 'Group Alpha Docs',
            'name_ar' => 'وثائق مجموعة ألفا',
            'group_id' => $group->id,
        ]);

        $document = $this->createCleanDocument([
            'sanitized_name' => 'financial-report-2026.pdf',
        ], $folder);

        $gsr = $this->createMfaUser([
            'role' => 'gsr',
            'group_id' => $group->id,
        ]);

        $response = $this->actingAsMfaUser($gsr)
            ->get("/service/documents/{$document->id}/download");

        $response->assertStatus(200);

        // 1. Content-Disposition: attachment; filename="sanitized-name.pdf" (forces download, never executes in-browser)
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('filename="financial-report-2026.pdf"', $contentDisposition);

        // 2. X-Content-Type-Options: nosniff
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // 3. Content-Security-Policy: default-src 'none'; sandbox
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);

        // 4. Cache-Control: private, no-cache, no-store, must-revalidate
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);

        // 5. Additional security and indexing header
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_download_triggers_audit_log_event(): void
    {
        $document = $this->createCleanDocument();
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get("/service/documents/{$document->id}/download");

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_download',
            'resource' => 'dms_document',
            'resource_id' => $document->id,
            'user_id' => $admin->id,
        ]);

        $log = AuditLog::where('resource_id', $document->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('document_download', $log->action);
        $this->assertIsArray($log->payload);
        $this->assertSame($document->sanitized_name, $log->payload['sanitized_name']);
    }

    public function test_download_non_clean_document_fails_with_404(): void
    {
        $user = $this->createMfaUser(['role' => 'web_admin']);

        foreach (['processing', 'quarantined', 'rejected'] as $status) {
            $document = $this->createCleanDocument(['status' => $status]);

            $response = $this->actingAsMfaUser($user)
                ->get("/service/documents/{$document->id}/download");

            $response->assertStatus(404);
        }
    }

    public function test_download_non_existent_uuid_fails_with_404(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAsMfaUser($admin)
            ->get("/service/documents/{$randomUuid}/download");

        $response->assertStatus(404);
    }

    public function test_download_missing_vault_file_fails_with_404(): void
    {
        $document = $this->createCleanDocument();
        $vaultFile = $document->getVaultPath();

        // Delete physical file from disk while DB record remains clean
        if (file_exists($vaultFile)) {
            unlink($vaultFile);
        }

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get("/service/documents/{$document->id}/download");

        $response->assertStatus(404);
    }

    public function test_web_admin_can_download_any_document(): void
    {
        $treasuryFolder = DmsFolder::create([
            'name_en' => 'Restricted Treasury',
            'name_ar' => 'الخزانة المقيدة',
            'required_role' => 'treasurer',
        ]);

        $document = $this->createCleanDocument([], $treasuryFolder);
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get("/service/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_subdomain_download_route_is_operational(): void
    {
        $document = $this->createCleanDocument();
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get("https://dms.aaegypt.org/{$document->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
