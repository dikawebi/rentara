<?php

namespace App;

enum BookingStatus: string
{
    case Verified = 'verified';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
