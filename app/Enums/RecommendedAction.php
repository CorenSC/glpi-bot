<?php

declare(strict_types=1);

namespace App\Enums;

enum RecommendedAction: string
{
    case AssignToTechnician = 'assign_to_technician';
    case AssignToGroup = 'assign_to_group';
    case ManualTriage = 'manual_triage';
}
