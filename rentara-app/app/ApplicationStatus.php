<?php

namespace App;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case InfoRequested = 'info_requested';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Verified = 'verified';
    case Expired = 'expired';

    public function isActive(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::UnderReview,
            self::InfoRequested,
            self::Approved,
            self::Verified,
        ], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Submitted => in_array($next, [self::UnderReview, self::InfoRequested, self::Rejected, self::Approved], true),
            self::UnderReview => in_array($next, [self::InfoRequested, self::Rejected, self::Approved], true),
            self::InfoRequested => in_array($next, [self::UnderReview, self::Rejected, self::Approved], true),
            self::Approved => $next === self::Verified,
            self::Verified, self::Rejected, self::Expired => false,
        };
    }
}
