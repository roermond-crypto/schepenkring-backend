<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case DISABLED = 'DISABLED';
    case BLOCKED = 'BLOCKED';
}
