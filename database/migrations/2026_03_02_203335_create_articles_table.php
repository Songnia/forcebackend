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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_id')->constrained('categories')->cascadeOnDelete();
            $table->string('nom');
            $table->string('reference')->unique();
            $table->string('unite')->default('unité');
            $table->decimal('prix_achat', 12, 2)->default(0); // PUMP
            $table->decimal('prix_vente', 12, 2)->default(0);
            $table->decimal('qte_actuelle', 12, 2)->default(0);
            $table->decimal('seuil_alerte', 12, 2)->default(0);
            $table->string('photo_url')->nullable();
            $table->string('statut')->default('actif'); // actif | archivé
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
