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
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'venue')) {
                $table->string('venue')->nullable()->after('time');
            }
            if (! Schema::hasColumn('meetings', 'city')) {
                $table->string('city')->nullable()->after('venue');
            }
            if (! Schema::hasColumn('meetings', 'notes')) {
                $table->text('notes')->nullable()->after('city');
            }
            if (! Schema::hasColumn('meetings', 'search_vector')) {
                $table->text('search_vector')->nullable()->after('notes');
            }
        });

        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'search_vector')) {
                $table->text('search_vector')->nullable()->after('meta_description_en');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $columnsToDrop = array_filter(
                ['venue', 'city', 'notes', 'search_vector'],
                fn (string $column) => Schema::hasColumn('meetings', $column)
            );

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'search_vector')) {
                $table->dropColumn('search_vector');
            }
        });
    }
};
