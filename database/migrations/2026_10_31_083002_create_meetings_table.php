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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable()->comment('0=Sunday to 6=Saturday');
            $table->string('start_time', 5)->nullable()->comment('H:i format');
            $table->string('end_time', 5)->nullable()->comment('H:i format');
            $table->string('time', 5)->nullable();
            $table->string('venue_address')->nullable();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('type')->default('in_person')->comment('in_person, online, hybrid');
            $table->string('online_link')->nullable();
            $table->string('language')->default('ar')->comment('ar, en, bilingual');
            $table->text('notes')->nullable();
            $table->text('search_vector')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('is_active');
            $table->index('day_of_week');
            $table->index('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
