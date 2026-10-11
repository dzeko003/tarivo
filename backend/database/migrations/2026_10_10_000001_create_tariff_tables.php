<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barème versionné : une version de tarif et ses grilles (primes de base, coefficients, taxes).
 *
 * Choix propres à PostgreSQL :
 * - coefficients et taux en `numeric` SANS échelle imposée (+ CHECK sur scale()) : la valeur saisie
 *   « 1.15 » revient « 1.15 » et non « 1.1500 », ce qui garde le détail du calcul identique ;
 * - les règles du barème sont aussi garanties par la base : CHECK, et EXCLUDE (btree_gist)
 *   pour interdire les tranches qui se chevauchent et deux versions publiées sur la même période.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('tariff_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->char('country', 2);
            $table->char('currency', 3);
            $table->string('label');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->bigInteger('policy_fee');
            $table->smallInteger('minimum_driver_age');
            $table->smallInteger('minimum_license_age');
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tariff_versions
                ADD COLUMN bonus_malus_min numeric NOT NULL,
                ADD COLUMN bonus_malus_max numeric NOT NULL,
                ADD CONSTRAINT tariff_versions_status_check CHECK (status IN ('draft', 'published', 'archived')),
                ADD CONSTRAINT tariff_versions_period_check CHECK (valid_to IS NULL OR valid_to > valid_from),
                ADD CONSTRAINT tariff_versions_published_at_check CHECK (status = 'draft' OR published_at IS NOT NULL),
                ADD CONSTRAINT tariff_versions_policy_fee_check CHECK (policy_fee >= 0),
                ADD CONSTRAINT tariff_versions_minimum_ages_check CHECK (minimum_driver_age > 0 AND minimum_license_age > 0),
                ADD CONSTRAINT tariff_versions_bonus_malus_check CHECK (
                    bonus_malus_min > 0 AND bonus_malus_min <= bonus_malus_max
                    AND scale(bonus_malus_min) <= 4 AND scale(bonus_malus_max) <= 4
                ),
                -- Une seule version publiée applicable par pays et par date : périodes [valid_from, valid_to).
                ADD CONSTRAINT tariff_versions_no_overlap EXCLUDE USING gist (
                    country WITH =,
                    daterange(valid_from, valid_to, '[)') WITH &&
                ) WHERE (status = 'published')
            SQL);

        Schema::create('base_premiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_version_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_category', 50);
            $table->integer('cv_min');
            $table->integer('cv_max')->nullable();
            $table->bigInteger('amount');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE base_premiums
                ADD CONSTRAINT base_premiums_cv_check CHECK (cv_min >= 1 AND (cv_max IS NULL OR cv_max > cv_min)),
                ADD CONSTRAINT base_premiums_amount_check CHECK (amount > 0),
                ADD CONSTRAINT base_premiums_no_overlap EXCLUDE USING gist (
                    tariff_version_id WITH =,
                    vehicle_category WITH =,
                    int4range(cv_min, cv_max, '[)') WITH &&
                )
            SQL);

        Schema::create('coefficients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_version_id')->constrained()->cascadeOnDelete();
            $table->string('factor', 20);
            $table->string('key', 50)->nullable();
            $table->integer('band_min')->nullable();
            $table->integer('band_max')->nullable();
            $table->string('label');

            $table->unique(['tariff_version_id', 'factor', 'key']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE coefficients
                ADD COLUMN value numeric NOT NULL,
                ADD CONSTRAINT coefficients_factor_check CHECK (factor IN ('usage', 'zone', 'age', 'license')),
                ADD CONSTRAINT coefficients_value_check CHECK (value > 0 AND scale(value) <= 4),
                -- Usage et zone : une clé, pas de tranche. Âge et permis : une tranche, pas de clé.
                ADD CONSTRAINT coefficients_shape_check CHECK (
                    (factor IN ('usage', 'zone') AND key IS NOT NULL AND band_min IS NULL AND band_max IS NULL)
                    OR (factor IN ('age', 'license') AND key IS NULL AND band_min IS NOT NULL
                        AND band_min >= 0 AND (band_max IS NULL OR band_max > band_min))
                ),
                ADD CONSTRAINT coefficients_no_band_overlap EXCLUDE USING gist (
                    tariff_version_id WITH =,
                    factor WITH =,
                    int4range(band_min, band_max, '[)') WITH &&
                ) WHERE (key IS NULL)
            SQL);

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_version_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('position');
            $table->string('code', 30);
            $table->string('label');
            $table->string('base', 50)->nullable();
            $table->bigInteger('fixed_amount')->nullable();

            $table->unique(['tariff_version_id', 'code']);
            $table->unique(['tariff_version_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE taxes
                ADD COLUMN rate numeric,
                ADD CONSTRAINT taxes_base_check CHECK (base IS NULL OR base IN ('prime_nette', 'prime_nette_et_accessoires')),
                -- Soit un taux sur une assiette, soit un montant fixe (même règle que TaxRule).
                ADD CONSTRAINT taxes_shape_check CHECK (
                    (rate IS NOT NULL AND base IS NOT NULL AND fixed_amount IS NULL
                        AND rate >= 0 AND rate < 1 AND scale(rate) <= 5)
                    OR (rate IS NULL AND base IS NULL AND fixed_amount IS NOT NULL AND fixed_amount >= 0)
                )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('coefficients');
        Schema::dropIfExists('base_premiums');
        Schema::dropIfExists('tariff_versions');
    }
};
