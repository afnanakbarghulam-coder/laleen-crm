<?php

namespace App\Policies;

use App\Models\StaffDocument;
use App\Models\User;

class StaffDocumentPolicy
{
    /**
     * ONLY an Admin or the exact employee who owns the document may view/download it.
     * Any other user (including Salon Managers viewing another employee's document) is denied (403).
     */
    public function view(User $user, StaffDocument $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ownStaffId = $user->staff()->value('id');

        return $ownStaffId !== null && (int) $ownStaffId === (int) $document->staff_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, StaffDocument $document): bool
    {
        return $user->isAdmin();
    }
}
