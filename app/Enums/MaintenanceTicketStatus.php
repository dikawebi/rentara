<?php
namespace App\Enums;
enum MaintenanceTicketStatus: string { case Submitted='submitted'; case Reviewed='reviewed'; case Assigned='assigned'; case InProgress='in_progress'; case Waiting='waiting'; case Resolved='resolved'; case Closed='closed'; case Rejected='rejected'; }
