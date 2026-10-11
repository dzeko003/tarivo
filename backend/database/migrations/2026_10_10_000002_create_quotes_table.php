<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devis enregistrés. Un devis est figé : il garde la saisie, la version de tarif appliquée,
 * le détail complet du calcul et le total. Une version de tarif utilisée par un devis
 * ne peut donc pas être supprimée (restrictOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('tariff_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('effective_date');
            $table->smallInteger('duration_months')->default(12);
            $table->jsonb('input');
            $table->jsonb('breakdown');
            $table->bigInteger('total_ttc');
            $table->timestamps();

            $table->index('effective_date');
            $table->index('created_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE quotes
                ADD CONSTRAINT quotes_duration_check CHECK (duration_months IN (1, 3, 6, 12)),
                ADD CONSTRAINT quotes_total_check CHECK (total_ttc > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
