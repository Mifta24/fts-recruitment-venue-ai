<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name');
            $table->string('company_name')->nullable();
            $table->string('event_type', 30)->nullable(); // job_fair | walk_in_interview | assessment | briefing | campus_hiring | other
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('candidates')->default(20);
            $table->unsignedSmallInteger('recruiters')->default(0);
            $table->boolean('tech_crew')->default(false);
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
