<?php

namespace App\Support;

class RwandaLocations
{
    public static function provinces(): array
    {
        return [
            'Kigali City' => 'Kigali City',
            'Northern' => 'Northern',
            'Southern' => 'Southern',
            'Eastern' => 'Eastern',
            'Western' => 'Western',
        ];
    }

    public static function districtsByProvince(): array
    {
        return [
            'Kigali City' => ['Gasabo', 'Kicukiro', 'Nyarugenge'],
            'Northern' => ['Musanze', 'Gicumbi', 'Burera', 'Rulindo', 'Gakenke'],
            'Southern' => ['Huye', 'Muhanga', 'Nyanza', 'Gisagara', 'Nyamagabe', 'Nyaruguru', 'Kamonyi'],
            'Eastern' => ['Rwamagana', 'Kayonza', 'Ngoma', 'Kirehe', 'Bugesera', 'Nyagatare', 'Gatsibo'],
            'Western' => ['Rubavu', 'Rusizi', 'Nyamasheke', 'Karongi', 'Rutsiro', 'Ngororero', 'Nyabihu'],
        ];
    }

    public static function districtOptions(string $province): array
    {
        $districts = self::districtsByProvince()[$province] ?? [];

        return collect($districts)->mapWithKeys(fn (string $district) => [$district => $district])->all();
    }
}
