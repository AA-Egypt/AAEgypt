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
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'name')) {
                $table->string('name')->nullable()->after('group_id');
            }
            if (! Schema::hasColumn('meetings', 'start_time')) {
                $table->string('start_time', 5)->nullable()->comment('H:i format')->after('day_of_week');
            }
            if (! Schema::hasColumn('meetings', 'end_time')) {
                $table->string('end_time', 5)->nullable()->comment('H:i format')->after('start_time');
            }
            if (! Schema::hasColumn('meetings', 'venue_address')) {
                $table->string('venue_address')->nullable()->after('time');
            }
            if (! Schema::hasColumn('meetings', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('city');
            }
            if (! Schema::hasColumn('meetings', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('meetings', 'type')) {
                $table->string('type')->default('in_person')->comment('in_person, online, hybrid')->after('longitude');
            }
            if (! Schema::hasColumn('meetings', 'online_link')) {
                $table->string('online_link')->nullable()->after('type');
            }
            if (! Schema::hasColumn('meetings', 'language')) {
                $table->string('language')->default('ar')->comment('ar, en, bilingual')->after('online_link');
            }
            if (! Schema::hasColumn('meetings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('search_vector');
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasIndex('meetings', ['latitude', 'longitude'])) {
                $table->index(['latitude', 'longitude']);
            }
            if (! Schema::hasIndex('meetings', ['is_active'])) {
                $table->index('is_active');
            }
            if (! Schema::hasIndex('meetings', ['day_of_week'])) {
                $table->index('day_of_week');
            }
            if (! Schema::hasIndex('meetings', ['city'])) {
                $table->index('city');
            }
        });

        // Sync existing data to new columns if populated
        if (Schema::hasColumn('meetings', 'name') && Schema::hasColumn('meetings', 'title')) {
            DB::table('meetings')->whereNull('name')->whereNotNull('title')->update(['name' => DB::raw('title')]);
        }
        if (Schema::hasColumn('meetings', 'venue_address') && Schema::hasColumn('meetings', 'venue')) {
            DB::table('meetings')->whereNull('venue_address')->whereNotNull('venue')->update(['venue_address' => DB::raw('venue')]);
        }
        if (Schema::hasColumn('meetings', 'start_time') && Schema::hasColumn('meetings', 'time')) {
            DB::table('meetings')->whereNull('start_time')->whereNotNull('time')->update(['start_time' => DB::raw('time')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $columnsToDrop = array_filter(
                [
                    'name',
                    'start_time',
                    'end_time',
                    'venue_address',
                    'latitude',
                    'longitude',
                    'type',
                    'online_link',
                    'language',
                    'is_active',
                ],
                fn (string $column) => Schema::hasColumn('meetings', $column)
            );

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
