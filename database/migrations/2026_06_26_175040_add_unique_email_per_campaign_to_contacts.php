<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Email becomes unique per campaign so an import can upsert by
     * (campaign_id, email) idempotently. Existing rows are first normalized to
     * the same trim+lowercase form the write path now enforces, so the whole
     * column agrees with the rule before the index lands. A case-insensitive
     * pre-flight guarantees this lowercasing cannot collide.
     */
    public function up(): void
    {
        DB::table('contacts')->update(['email' => DB::raw('LOWER(TRIM(email))')]);

        Schema::table('contacts', function (Blueprint $table) {
            $table->unique(['campaign_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['campaign_id', 'email']);
        });
    }
};
