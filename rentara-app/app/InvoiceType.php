<?php

namespace App;

enum InvoiceType: string
{
    case Deposit = 'deposit';
    case Rent = 'rent';
    case OneTime = 'one_time';
}
