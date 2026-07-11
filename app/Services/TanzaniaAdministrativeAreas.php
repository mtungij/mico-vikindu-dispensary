<?php

namespace App\Services;

class TanzaniaAdministrativeAreas
{
    /**
     * @return array<int, string>
     */
    public function regions(): array
    {
        return array_keys(config('tanzania.regions', []));
    }

    /**
     * @return array<int, string>
     */
    public function districtsForRegion(string $region): array
    {
        return config("tanzania.regions.$region", []);
    }

    public function isValidRegion(string $region): bool
    {
        return array_key_exists($region, config('tanzania.regions', []));
    }

    public function isValidDistrict(string $region, string $district): bool
    {
        return in_array($district, $this->districtsForRegion($region), true);
    }
}
