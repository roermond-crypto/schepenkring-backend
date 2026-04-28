<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case DISABLED = 'DISABLED';
    case BLOCKED = 'BLOCKED';
    case PENDING = 'PENDING';
    case VERIFYING = 'VERIFYING';
    case EMAIL_PENDING = 'EMAIL_PENDING';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
}
