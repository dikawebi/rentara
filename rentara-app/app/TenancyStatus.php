<?php

namespace App;

enum TenancyStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
