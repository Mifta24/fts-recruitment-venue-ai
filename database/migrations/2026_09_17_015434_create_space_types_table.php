<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedSmallInteger('size_sqm')->nullable();
            $table->unsignedSmallInteger('max_candidates')->default(50);
            $table->unsignedSmallInteger('max_recruiters')->default(0);
            $table->json('layout_config')->nullable();
            $table->string('ambience')->nullable();
            $table->boolean('catering_included')->default(false);
            $table->boolean('tech_crew_available')->default(false);
            $table->decimal('tech_crew_price', 12, 2)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['venue_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_types');
    }
};
