<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable(); // Phase 2: constraints
            $table->string('type')->default('comptant'); // comptant | crédit
            $table->decimal('total', 12, 2);
            $table->decimal('montant_recu', 12, 2);
            $table->decimal('monnaie_rendue', 12, 2);
            $table->string('statut')->default('complétée');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};
