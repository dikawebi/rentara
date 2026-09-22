<?php

namespace App\Enums;

enum RentalPeriod: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
