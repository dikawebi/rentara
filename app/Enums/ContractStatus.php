<?php
namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Expiring = 'expiring';
    case Completed = 'completed';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';
}
