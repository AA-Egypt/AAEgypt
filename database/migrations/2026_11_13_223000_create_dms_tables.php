<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('web_admin', 'gsr', 'delegate', 'service_position', 'treasurer') DEFAULT 'gsr'");
        }

        Schema::create('dms_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('dms_folders')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('required_role')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('dms_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('folder_id')->constrained('dms_folders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('sanitized_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->string('sha256_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['quarantined', 'processing', 'clean', 'rejected'])->default('clean');
            $table->timestamps();
        });

        $now = now();
        DB::table('dms_folders')->insertOrIgnore([
            [
                'name_en' => 'Meeting Minutes',
                'name_ar' => 'محاضر الاجتماعات',
                'required_role' => null,
                'group_id' => null,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Charters & Guidelines',
                'name_ar' => 'اللوائح والإرشادات',
                'required_role' => null,
                'group_id' => null,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Treasury Reports',
                'name_ar' => 'تقارير الخزانة',
                'required_role' => 'treasurer',
                'group_id' => null,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Literature Inventory',
                'name_ar' => 'مخزون المطبوعات',
                'required_role' => null,
                'group_id' => null,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Group Folders',
                'name_ar' => 'ملفات المجموعات',
                'required_role' => null,
                'group_id' => null,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Announcements',
                'name_ar' => 'الإعلانات',
                'required_role' => null,
                'group_id' => null,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dms_documents');
        Schema::dropIfExists('dms_folders');

        if (Schema::hasTable('users') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('web_admin', 'gsr', 'delegate', 'service_position') DEFAULT 'gsr'");
        }
    }
};
