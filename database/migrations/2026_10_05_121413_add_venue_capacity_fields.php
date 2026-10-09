<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What sets a recruitment venue apart from a generic room: how a space is laid out
 * (breakout rooms, recruiter booths, which floors it sits on) and cheaper rates for
 * multi-day hiring campaigns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('breakout_rooms')->default(0)->after('size_sqm');
            $table->unsignedTinyInteger('booths')->default(0)->after('breakout_rooms');
            $table->string('floor_range', 20)->nullable()->after('booths');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->unsignedTinyInteger('multiday_discount_percent')->default(0)->after('close_time');
            $table->unsignedTinyInteger('campaign_discount_percent')->default(0)->after('multiday_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('space_types', function (Blueprint $table) {
            $table->dropColumn(['breakout_rooms', 'booths', 'floor_range']);
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['multiday_discount_percent', 'campaign_discount_percent']);
        });
    }
};
