<?php

namespace App\Enum;

enum PositionLevel: string
{
    case Junior = 'junior';
    case Middle = 'middle';
    case Senior = 'senior';
    case CLevel = 'c_level';

    public function label(): string
    {
        return match ($this) {
            self::Junior => 'Junior',
            self::Middle => 'Middle',
            self::Senior => 'Senior',
            self::CLevel => 'C-level',
        };
    }
}
