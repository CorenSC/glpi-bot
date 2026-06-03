<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GlpiAiAssignmentSuggestion;
use App\Models\User;

class GlpiAiAssignmentSuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, GlpiAiAssignmentSuggestion $suggestion): bool
    {
        return $this->allowed($user);
    }

    public function approve(User $user, GlpiAiAssignmentSuggestion $suggestion): bool
    {
        return $this->allowed($user);
    }

    public function executeAssignment(User $user, GlpiAiAssignmentSuggestion $suggestion): bool
    {
        return $this->allowed($user);
    }

    private function allowed(User $user): bool
    {
        $required = (string) config('ldap.required_description_contains', 'DTI');
        if ((bool) config('ldap.enabled') && $required !== '' && stripos((string) ($user->ldap_description ?? ''), $required) === false) {
            return false;
        }

        return (bool) ($user->is_admin ?? false) || (bool) ($user->is_it_admin ?? false);
    }
}
