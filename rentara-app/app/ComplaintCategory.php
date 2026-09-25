<?php

namespace App;

enum ComplaintCategory: string
{
    case Maintenance = 'maintenance';
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Safety = 'safety';
    case Other = 'other';
}
