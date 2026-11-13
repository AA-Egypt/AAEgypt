<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('meetings', 'version')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('is_active');
            });
        }

        Schema::create('meeting_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('old_payload');
            $table->json('new_payload');
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['meeting_id', 'version']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_revisions');

        if (Schema::hasColumn('meetings', 'version')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
