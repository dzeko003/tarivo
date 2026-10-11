<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TariffVersionStatus;
use App\Exceptions\TariffVersionLocked;
use App\Models\BasePremium;
use App\Models\Coefficient;
use App\Models\TariffVersion;
use App\Models\Tax;

/**
 * Interdit toute écriture dans les grilles d'une version qui n'est plus un brouillon
 * (doublé par un trigger PostgreSQL). Commun à BasePremium, Coefficient et Tax.
 */
class TariffGridRowObserver
{
    public function creating(BasePremium|Coefficient|Tax $row): void
    {
        $this->ensureDraft($row);
    }

    public function updating(BasePremium|Coefficient|Tax $row): void
    {
        $this->ensureDraft($row);
    }

    public function deleting(BasePremium|Coefficient|Tax $row): void
    {
        $this->ensureDraft($row);
    }

    /**
     * Vérifie la version d'origine et, si la ligne change de version, la nouvelle.
     */
    private function ensureDraft(BasePremium|Coefficient|Tax $row): void
    {
        $versionIds = array_unique(array_filter([$row->getOriginal('tariff_version_id'), $row->tariff_version_id]));

        $locked = TariffVersion::query()
            ->whereKey($versionIds)
            ->where('status', '!=', TariffVersionStatus::Draft)
            ->first();

        if ($locked !== null) {
            throw TariffVersionLocked::grids($locked);
        }
    }
}
