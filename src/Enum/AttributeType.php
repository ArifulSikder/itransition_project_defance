<?php

namespace App\Enum;

enum AttributeType: string
{
    case String = 'string';
    case Text = 'text';
    case Image = 'image';
    case Numeric = 'numeric';
    case Date = 'date';
    case Period = 'period';
    case Boolean = 'boolean';
    case Choice = 'choice';

    public function label(): string
    {
        return match ($this) {
            self::String => 'String',
            self::Text => 'Text (Markdown)',
            self::Image => 'Image',
            self::Numeric => 'Numeric',
            self::Date => 'Date',
            self::Period => 'Period',
            self::Boolean => 'Boolean',
            self::Choice => 'One of many',
        };
    }

    /** @return list<FilterOperator> */
    public function operators(): array
    {
        return match ($this) {
            self::String, self::Text => [FilterOperator::Equals, FilterOperator::Contains],
            self::Numeric => [
                FilterOperator::Equals,
                FilterOperator::GreaterThan,
                FilterOperator::GreaterOrEqual,
                FilterOperator::LessThan,
                FilterOperator::LessOrEqual,
            ],
            self::Date => [FilterOperator::Equals, FilterOperator::Before, FilterOperator::After],
            self::Period => [FilterOperator::Overlaps],
            self::Boolean, self::Choice => [FilterOperator::Equals],
            self::Image => [],
        };
    }
}
