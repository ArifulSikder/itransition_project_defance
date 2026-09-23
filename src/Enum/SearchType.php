<?php

namespace App\Enum;

enum SearchType: string
{
    case Position = 'position';
    case Cv = 'cv';
    case User = 'user';
    case Attribute = 'attribute';
}
