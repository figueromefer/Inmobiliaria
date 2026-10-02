<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('contratos_pendientes', fn(Blueprint $t) => $t->json('manual_overrides')->nullable()->after('mapped_payload')); }
 public function down(): void { Schema::table('contratos_pendientes', fn(Blueprint $t) => $t->dropColumn('manual_overrides')); }
};
