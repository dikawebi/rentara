<?php

namespace App\Enums;

enum WorkspaceMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
}
