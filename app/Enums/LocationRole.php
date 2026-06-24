<?php

namespace App\Enums;

enum LocationRole: string
{
    case LOCATION_MANAGER  = 'LOCATION_MANAGER';
    case LOCATION_EMPLOYEE = 'LOCATION_EMPLOYEE';
    case BROKER            = 'BROKER';

    public function label(): string
    {
        return match ($this) {
            self::LOCATION_MANAGER  => 'Vestigingsmanager',
            self::LOCATION_EMPLOYEE => 'Medewerker',
            self::BROKER            => 'Makelaar',
        };
    }
}
