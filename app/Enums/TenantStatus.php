<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
