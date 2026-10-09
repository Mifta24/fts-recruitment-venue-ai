<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The approved venue knowledge base the AI retrieves from. No record
     * here, no answer from the AI — static/semi-static facts only, never
     * price or availability (see space_inventory).
     */
    public function up(): void
    {
        Schema::create('venue_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('body');
            $table->json('translations')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['venue_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_knowledge_items');
    }
};
