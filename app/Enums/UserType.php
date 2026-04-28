<?php

namespace App\Enums;

enum UserType: string
{
    case ADMIN = 'ADMIN';
    case EMPLOYEE = 'EMPLOYEE';
    case CLIENT = 'CLIENT';
    case SELLER = 'SELLER';
    case BUYER = 'BUYER';
    case PARTNER = 'PARTNER';
}
