<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The contact list is always campaign-scoped ($campaign->contacts()) and
     * ordered by a whitelisted column. The existing (campaign_id, email) unique
     * index already serves the email sort, but name and created_at fall back to a
     * filesort over the whole campaign for every page. These composite indexes let
     * the ORDER BY be served directly from the index for those two columns.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['campaign_id', 'name']);
            $table->index(['campaign_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'name']);
            $table->dropIndex(['campaign_id', 'created_at']);
        });
    }
};
