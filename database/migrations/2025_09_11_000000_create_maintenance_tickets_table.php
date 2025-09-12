<?php
// database/migrations/2025_09_11_000000_create_maintenance_tickets_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('propiedades'); // ajusta nombre de tabla
            $table->foreignId('created_by')->constrained('users');        // o 'clientes' si aplica
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open'); // open|in_progress|completed|canceled
            $table->string('priority', 20)->nullable();    // low|medium|high
            $table->date('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('maintenance_tickets');
    }
};
