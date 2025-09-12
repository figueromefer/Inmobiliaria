<?php
// database/migrations/2025_09_11_000001_create_maintenance_comments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('maintenance_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('maintenance_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->json('attachments')->nullable(); // guarda paths de archivos
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('maintenance_comments');
    }
};
