<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            if (!Schema::hasColumn('annonces', 'est_boostee')) {
                $table->boolean('est_boostee')->default(false)->after('statut_publication');
            }
            if (!Schema::hasColumn('annonces', 'boostee_jusqu_a')) {
                $table->timestamp('boostee_jusqu_a')->nullable()->after('est_boostee');
            }
        });

        Schema::create('boosts_annonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('annonce_id')->constrained('annonces')->onDelete('cascade');
            $table->string('pack_type', 30); // 'boost_3j', 'boost_7j'
            $table->unsignedInteger('duree_jours'); // 3 ou 7
            $table->unsignedInteger('montant'); // 200 ou 500
            $table->string('reference_transaction', 100)->unique();
            $table->string('statut_paiement', 30)->default('valide');
            $table->string('methode_paiement', 30)->default('kkiapay');
            $table->string('devise', 10)->default('XOF');
            $table->string('payeur', 150)->nullable();
            $table->timestamp('date_debut');
            $table->timestamp('date_fin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boosts_annonces');

        Schema::table('annonces', function (Blueprint $table) {
            if (Schema::hasColumn('annonces', 'boostee_jusqu_a')) {
                $table->dropColumn('boostee_jusqu_a');
            }
            if (Schema::hasColumn('annonces', 'est_boostee')) {
                $table->dropColumn('est_boostee');
            }
        });
    }
};

