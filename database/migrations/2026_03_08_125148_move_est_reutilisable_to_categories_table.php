<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::hasColumn('articles', 'est_reutilisable')) {
                $table->dropColumn('est_reutilisable');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('est_reutilisable')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('est_reutilisable');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('est_reutilisable')->default(false)->after('statut');
        });
    }
};
