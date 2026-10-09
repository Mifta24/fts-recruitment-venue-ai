<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mock reservation-system data: the one table the AI is NEVER allowed to
     * answer from memory. A per-venue PMS/booking-engine adapter replaces
     * this table later without touching the AI or knowledge-base layers.
     */
    public function up(): void
    {
        Schema::create('space_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_type_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->unsignedSmallInteger('total_spaces');
            $table->unsignedSmallInteger('booked_spaces')->default(0);
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['space_type_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_inventory');
    }
};
