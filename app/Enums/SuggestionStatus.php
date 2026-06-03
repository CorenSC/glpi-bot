<?php

declare(strict_types=1);

namespace App\Enums;

enum SuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case AutoAssigned = 'auto_assigned';
    case ManualTriage = 'manual_triage';
    case Failed = 'failed';
    case Ignored = 'ignored';
    case GlpiClosed = 'glpi_closed';
}
