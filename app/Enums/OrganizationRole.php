<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Member = 'MEMBER';

    public function isStaff(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
