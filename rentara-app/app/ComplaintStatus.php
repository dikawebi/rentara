<?php

namespace App;

enum ComplaintStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingOnTenant = 'waiting_on_tenant';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Open => in_array($next, [self::InProgress, self::WaitingOnTenant, self::Closed], true),
            self::InProgress => in_array($next, [self::WaitingOnTenant, self::Resolved, self::Closed], true),
            self::WaitingOnTenant => in_array($next, [self::InProgress, self::Resolved, self::Closed], true),
            self::Resolved => in_array($next, [self::Closed, self::InProgress], true),
            self::Closed => $next === self::InProgress,
        };
    }
}
