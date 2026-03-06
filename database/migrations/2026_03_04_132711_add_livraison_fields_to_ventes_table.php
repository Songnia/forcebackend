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
        Schema::table('ventes', function (Blueprint $table) {
            $table->boolean('avec_livraison')->default(false)->after('total');
            $table->decimal('frais_livraison', 12, 2)->default(0)->after('avec_livraison');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['avec_livraison', 'frais_livraison']);
        });
    }
};
