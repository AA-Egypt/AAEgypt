<?php

namespace Tests\Feature\Service;

use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DmsAccessTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $folder = DmsFolder::firstOrCreate(
            ['name_en' => 'Meeting Minutes'],
            ['name_ar' => 'محاضر الاجتماعات', 'required_role' => null, 'group_id' => null]
        );

        $document = DmsDocument::factory()->create([
            'folder_id' => $folder->id,
        ]);

        // 1. Root DMS index
        $this->get('/service/documents')
            ->assertRedirect(route('login'));

        // 2. Folder view
        $this->get("/service/documents/folders/{$folder->id}")
            ->assertRedirect(route('login'));

        // 3. Document download
        $this->get("/service/documents/{$document->id}/download")
            ->assertRedirect(route('login'));

        // 4. Document upload
        $this->post('/service/documents/upload', [
            'folder_id' => $folder->id,
        ])->assertRedirect(route('login'));

        // 5. Document delete
        $this->delete("/service/documents/{$document->id}")
            ->assertRedirect(route('login'));
    }

    public function test_dms_responses_include_noindex_nofollow_headers_and_meta_tags(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get('/service/documents');

        $response->assertStatus(200);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_default_folder_hierarchy_is_seeded_and_present(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get('/service/documents');

        $response->assertStatus(200);
        $response->assertSee('Meeting Minutes');
        $response->assertSee('محاضر الاجتماعات');
        $response->assertSee('Charters & Guidelines');
        $response->assertSee('اللوائح والإرشادات');
        $response->assertSee('Treasury Reports');
        $response->assertSee('تقارير الخزانة');
        $response->assertSee('Literature Inventory');
        $response->assertSee('مخزون المطبوعات');
        $response->assertSee('Group Folders');
        $response->assertSee('ملفات المجموعات');
        $response->assertSee('Announcements');
        $response->assertSee('الإعلانات');
    }

    public function test_regular_gsr_cannot_view_or_open_treasury_folder(): void
    {
        $group = Group::factory()->create(['name_en' => 'Maadi Group']);
        $gsr = $this->createMfaUser([
            'role' => 'gsr',
            'group_id' => $group->id,
        ]);

        $treasuryFolder = DmsFolder::where('name_en', 'Treasury Reports')->firstOrFail();

        // 1. In root listing, Treasury folder must not be shown to GSR
        $indexResponse = $this->actingAsMfaUser($gsr)
            ->get('/service/documents');

        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Treasury Reports');
        $indexResponse->assertDontSee('تقارير الخزانة');
        $indexResponse->assertSee('Meeting Minutes');

        // 2. Direct GET to /service/documents/folders/{id} returns 403 Forbidden
        $directFolderResponse = $this->actingAsMfaUser($gsr)
            ->get("/service/documents/folders/{$treasuryFolder->id}");

        $directFolderResponse->assertStatus(403);

        // 3. Query string /service/documents?folder={id} returns 403 Forbidden
        $queryFolderResponse = $this->actingAsMfaUser($gsr)
            ->get("/service/documents?folder={$treasuryFolder->id}");

        $queryFolderResponse->assertStatus(403);

        // 4. Attempting to upload to Treasury folder returns 403 Forbidden
        $file = UploadedFile::fake()->create('budget.pdf', 500, 'application/pdf');
        $uploadResponse = $this->actingAsMfaUser($gsr)
            ->post('/service/documents/upload', [
                'folder_id' => $treasuryFolder->id,
                'file' => $file,
            ]);

        $uploadResponse->assertStatus(403);

        // 5. Attempting to download a document from Treasury folder returns 403 Forbidden
        $doc = DmsDocument::factory()->create([
            'folder_id' => $treasuryFolder->id,
            'file_path' => "{$treasuryFolder->id}/fake_budget.pdf",
        ]);

        $downloadResponse = $this->actingAsMfaUser($gsr)
            ->get("/service/documents/{$doc->id}/download");

        $downloadResponse->assertStatus(403);
    }

    public function test_treasurer_can_view_and_open_treasury_folder(): void
    {
        $treasurer = $this->createMfaUser(['role' => 'treasurer']);
        $treasuryFolder = DmsFolder::where('name_en', 'Treasury Reports')->firstOrFail();

        // 1. Visible in index
        $indexResponse = $this->actingAsMfaUser($treasurer)
            ->get('/service/documents');

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Treasury Reports');
        $indexResponse->assertSee('تقارير الخزانة');

        // 2. Can open folder
        $folderResponse = $this->actingAsMfaUser($treasurer)
            ->get("/service/documents/folders/{$treasuryFolder->id}");

        $folderResponse->assertStatus(200);
        $folderResponse->assertSee('Upload Document to this Folder');
    }

    public function test_web_admin_can_view_and_open_all_folders_including_treasury(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);
        $treasuryFolder = DmsFolder::where('name_en', 'Treasury Reports')->firstOrFail();

        $response = $this->actingAsMfaUser($admin)
            ->get("/service/documents/folders/{$treasuryFolder->id}");

        $response->assertStatus(200);
        $response->assertSee('Treasury Reports');
        $response->assertSee('Upload Document to this Folder');
    }

    public function test_gsr_can_only_access_their_own_group_folder(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $groupFolderParent = DmsFolder::where('name_en', 'Group Folders')->firstOrFail();

        $folderA = DmsFolder::create([
            'parent_id' => $groupFolderParent->id,
            'name_en' => 'Group A Folder',
            'name_ar' => 'ملف مجموعة أ',
            'required_role' => null,
            'group_id' => $groupA->id,
        ]);

        $folderB = DmsFolder::create([
            'parent_id' => $groupFolderParent->id,
            'name_en' => 'Group B Folder',
            'name_ar' => 'ملف مجموعة ب',
            'required_role' => null,
            'group_id' => $groupB->id,
        ]);

        $gsrA = $this->createMfaUser([
            'role' => 'gsr',
            'group_id' => $groupA->id,
        ]);

        // 1. GSR A can open own group folder A
        $this->actingAsMfaUser($gsrA)
            ->get("/service/documents/folders/{$folderA->id}")
            ->assertStatus(200)
            ->assertSee('Group A Folder');

        // 2. GSR A is forbidden from opening Group B folder
        $this->actingAsMfaUser($gsrA)
            ->get("/service/documents/folders/{$folderB->id}")
            ->assertStatus(403);

        // 3. GSR A cannot upload into Group B folder
        $file = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');
        $this->actingAsMfaUser($gsrA)
            ->post('/service/documents/upload', [
                'folder_id' => $folderB->id,
                'file' => $file,
            ])
            ->assertStatus(403);

        // 4. GSR A cannot download from Group B folder
        $docB = DmsDocument::factory()->create([
            'folder_id' => $folderB->id,
            'file_path' => "{$folderB->id}/secret_b.pdf",
        ]);

        $this->actingAsMfaUser($gsrA)
            ->get("/service/documents/{$docB->id}/download")
            ->assertStatus(403);
    }

    public function test_web_admin_can_access_any_group_folder(): void
    {
        $groupB = Group::factory()->create(['name_en' => 'Group B']);
        $groupFolderParent = DmsFolder::where('name_en', 'Group Folders')->firstOrFail();

        $folderB = DmsFolder::create([
            'parent_id' => $groupFolderParent->id,
            'name_en' => 'Group B Folder',
            'name_ar' => 'ملف مجموعة ب',
            'required_role' => null,
            'group_id' => $groupB->id,
        ]);

        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $this->actingAsMfaUser($admin)
            ->get("/service/documents/folders/{$folderB->id}")
            ->assertStatus(200)
            ->assertSee('Group B Folder');
    }

    public function test_files_are_stored_in_dms_vault_with_metadata_and_hash(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);
        $folder = DmsFolder::where('name_en', 'Meeting Minutes')->firstOrFail();

        $fileContent = 'Meeting Minutes AA Egypt - Verified Content';
        $file = UploadedFile::fake()->createWithContent('Minutes_Nov_2026.pdf', $fileContent);

        $response = $this->actingAsMfaUser($admin)
            ->post('/service/documents/upload', [
                'folder_id' => $folder->id,
                'file' => $file,
            ]);

        $response->assertRedirect(route('service.documents.folder', $folder));

        $this->assertDatabaseHas('dms_documents', [
            'folder_id' => $folder->id,
            'user_id' => $admin->id,
            'original_name' => 'Minutes_Nov_2026.pdf',
            'sanitized_name' => 'minutes-nov-2026.pdf',
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash('sha256', $fileContent),
            'version' => 1,
            'status' => 'clean',
        ]);

        $document = DmsDocument::where('original_name', 'Minutes_Nov_2026.pdf')->firstOrFail();

        // Check file location in storage/app/dms_vault/
        $expectedPath = storage_path("app/dms_vault/{$document->file_path}");
        $this->assertFileExists($expectedPath);
        $this->assertSame($fileContent, file_get_contents($expectedPath));

        // Test secure download
        $downloadResponse = $this->actingAsMfaUser($admin)
            ->get("/service/documents/{$document->id}/download");

        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // Clean up created file
        if (file_exists($expectedPath)) {
            @unlink($expectedPath);
        }
    }

    public function test_regular_gsr_cannot_delete_documents_but_admin_can(): void
    {
        $group = Group::factory()->create(['name_en' => 'Zamalek Group']);
        $folder = DmsFolder::where('name_en', 'Meeting Minutes')->firstOrFail();

        $gsr = $this->createMfaUser(['role' => 'gsr', 'group_id' => $group->id]);
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $vaultSubdir = storage_path("app/dms_vault/{$folder->id}");
        if (! file_exists($vaultSubdir)) {
            mkdir($vaultSubdir, 0775, true);
        }
        $filePath = "{$folder->id}/dummy.pdf";
        file_put_contents(storage_path("app/dms_vault/{$filePath}"), 'dummy content');

        $document = DmsDocument::factory()->create([
            'folder_id' => $folder->id,
            'user_id' => $gsr->id,
            'file_path' => $filePath,
        ]);

        // GSR attempt delete -> 403 Forbidden
        $this->actingAsMfaUser($gsr)
            ->delete("/service/documents/{$document->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('dms_documents', ['id' => $document->id]);

        // Admin attempt delete -> 302 Redirect with record deleted
        $this->actingAsMfaUser($admin)
            ->delete("/service/documents/{$document->id}")
            ->assertRedirect(route('service.documents.folder', $folder));

        $this->assertDatabaseMissing('dms_documents', ['id' => $document->id]);
    }

    public function test_subdomain_routing_for_dms_portal(): void
    {
        $admin = $this->createMfaUser(['role' => 'web_admin']);

        $response = $this->actingAsMfaUser($admin)
            ->get('http://dms.aaegypt.org/');

        $response->assertStatus(200);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('Document Management System');
    }
}
