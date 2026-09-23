<?php

namespace App\Enum;

enum AttributeCategory: string
{
    case Personal = 'personal';
    case Certification = 'certification';
    case Domain = 'domain';
    case SoftSkill = 'soft_skill';
    case Technical = 'technical';
    case Language = 'language';
    case Education = 'education';
    case Preference = 'preference';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal Information',
            self::Certification => 'Certification',
            self::Domain => 'Domain Knowledge',
            self::SoftSkill => 'Soft Skills',
            self::Technical => 'Technical Skills',
            self::Language => 'Languages',
            self::Education => 'Education',
            self::Preference => 'Work Preferences',
        };
    }
}
