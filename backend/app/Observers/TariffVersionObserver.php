<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TariffVersionStatus;
use App\Exceptions\TariffVersionLocked;
use App\Models\TariffVersion;

/**
 * Fige une version de tarif dès qu'elle quitte le brouillon (doublé par un trigger PostgreSQL).
 *
 * Seuls changements permis ensuite, sur une version publiée : l'archiver, ou clôturer
 * sa période ouverte (valid_to NULL → date).
 */
class TariffVersionObserver
{
    public function updating(TariffVersion $version): void
    {
        $originalStatus = $version->getOriginal('status');
        if ($originalStatus === TariffVersionStatus::Draft) {
            return;
        }

        $allowed = $originalStatus === TariffVersionStatus::Published ? ['status', 'valid_to', 'updated_at'] : ['updated_at'];
        $forbidden = array_values(array_diff(array_keys($version->getDirty()), $allowed));

        if ($version->isDirty('status') && $version->status !== TariffVersionStatus::Archived) {
            $forbidden[] = 'status';
        }
        if ($version->isDirty('valid_to') && ($version->getOriginal('valid_to') !== null || $version->valid_to === null)) {
            $forbidden[] = 'valid_to';
        }

        if ($forbidden !== []) {
            throw TariffVersionLocked::fields($version, array_values(array_unique($forbidden)));
        }
    }

    public function deleting(TariffVersion $version): void
    {
        if ($version->getOriginal('status') !== TariffVersionStatus::Draft) {
            throw TariffVersionLocked::deletion($version);
        }
    }
}
