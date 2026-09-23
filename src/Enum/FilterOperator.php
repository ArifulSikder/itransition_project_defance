<?php

namespace App\Enum;

enum FilterOperator: string
{
    case Equals = 'eq';
    case Contains = 'contains';
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';
    case Before = 'before';
    case After = 'after';
    case Overlaps = 'overlaps';

    public function label(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::Contains => 'contains',
            self::GreaterThan => '>',
            self::GreaterOrEqual => '≥',
            self::LessThan => '<',
            self::LessOrEqual => '≤',
            self::Before => 'before',
            self::After => 'after',
            self::Overlaps => 'overlaps',
        };
    }
}
