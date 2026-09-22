<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_public_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_draft_id')->unique()->constrained('contract_drafts')->cascadeOnDelete();
            $table->string('public_reference', 32)->unique();
            $table->char('token_hash', 64);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_public_requests');
    }
};
