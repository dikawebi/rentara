<?php

namespace App\Enums;

enum WorkspaceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
