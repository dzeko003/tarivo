<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Immutabilité des versions de tarif garantie par PostgreSQL, en dernier rempart derrière les observers
 * (qu'une requête `->update()` en masse ou du SQL direct contourneraient).
 *
 * Une fois la version sortie du brouillon :
 * - ses grilles (base_premiums, coefficients, taxes) sont figées ;
 * - la version elle-même est figée, sauf deux changements :
 *   publiée → archivée, et clôture d'une période ouverte (valid_to NULL → date), pour laisser place
 *   à la version suivante, sans écarter des devis déjà faits sur cette version ;
 * - elle ne peut plus être supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tariff_versions_guard_update() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'draft' THEN
                    RETURN NEW;
                END IF;

                IF (to_jsonb(NEW) - 'status' - 'valid_to' - 'updated_at')
                    IS DISTINCT FROM (to_jsonb(OLD) - 'status' - 'valid_to' - 'updated_at') THEN
                    RAISE EXCEPTION 'tariff_version_locked: la version % (%) ne peut plus être modifiée', OLD.code, OLD.status;
                END IF;

                IF NEW.status IS DISTINCT FROM OLD.status
                    AND NOT (OLD.status = 'published' AND NEW.status = 'archived') THEN
                    RAISE EXCEPTION 'tariff_version_locked: transition % → % interdite pour la version %', OLD.status, NEW.status, OLD.code;
                END IF;

                IF NEW.valid_to IS DISTINCT FROM OLD.valid_to THEN
                    IF OLD.status <> 'published' OR OLD.valid_to IS NOT NULL OR NEW.valid_to IS NULL THEN
                        RAISE EXCEPTION 'tariff_version_locked: seule une période ouverte d''une version publiée peut être clôturée (version %)', OLD.code;
                    END IF;
                    IF EXISTS (SELECT 1 FROM quotes WHERE tariff_version_id = OLD.id AND effective_date >= NEW.valid_to) THEN
                        RAISE EXCEPTION 'tariff_version_locked: des devis de la version % prennent effet après le %', OLD.code, NEW.valid_to;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER tariff_versions_guard_update
                BEFORE UPDATE ON tariff_versions
                FOR EACH ROW EXECUTE FUNCTION tariff_versions_guard_update();

            CREATE OR REPLACE FUNCTION tariff_versions_guard_delete() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status <> 'draft' THEN
                    RAISE EXCEPTION 'tariff_version_locked: la version % (%) ne peut pas être supprimée', OLD.code, OLD.status;
                END IF;

                RETURN OLD;
            END;
            $$;

            CREATE TRIGGER tariff_versions_guard_delete
                BEFORE DELETE ON tariff_versions
                FOR EACH ROW EXECUTE FUNCTION tariff_versions_guard_delete();

            -- Commune aux trois grilles. En UPDATE, on vérifie l'ancienne ET la nouvelle version
            -- (déplacer une ligne d'un brouillon vers une version publiée est aussi interdit).
            CREATE OR REPLACE FUNCTION tariff_grid_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                locked_code text;
            BEGIN
                SELECT code INTO locked_code
                FROM tariff_versions
                WHERE status <> 'draft'
                  AND id IN (
                      CASE WHEN TG_OP <> 'INSERT' THEN OLD.tariff_version_id END,
                      CASE WHEN TG_OP <> 'DELETE' THEN NEW.tariff_version_id END
                  )
                LIMIT 1;

                IF locked_code IS NOT NULL THEN
                    RAISE EXCEPTION 'tariff_version_locked: les grilles de la version % ne peuvent plus être modifiées (%)', locked_code, TG_TABLE_NAME;
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$;

            CREATE TRIGGER base_premiums_guard BEFORE INSERT OR UPDATE OR DELETE ON base_premiums
                FOR EACH ROW EXECUTE FUNCTION tariff_grid_guard();
            CREATE TRIGGER coefficients_guard BEFORE INSERT OR UPDATE OR DELETE ON coefficients
                FOR EACH ROW EXECUTE FUNCTION tariff_grid_guard();
            CREATE TRIGGER taxes_guard BEFORE INSERT OR UPDATE OR DELETE ON taxes
                FOR EACH ROW EXECUTE FUNCTION tariff_grid_guard();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS taxes_guard ON taxes;
            DROP TRIGGER IF EXISTS coefficients_guard ON coefficients;
            DROP TRIGGER IF EXISTS base_premiums_guard ON base_premiums;
            DROP FUNCTION IF EXISTS tariff_grid_guard();
            DROP TRIGGER IF EXISTS tariff_versions_guard_delete ON tariff_versions;
            DROP FUNCTION IF EXISTS tariff_versions_guard_delete();
            DROP TRIGGER IF EXISTS tariff_versions_guard_update ON tariff_versions;
            DROP FUNCTION IF EXISTS tariff_versions_guard_update();
            SQL);
    }
};
