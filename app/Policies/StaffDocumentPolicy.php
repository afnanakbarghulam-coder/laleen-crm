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

    public function verify(User $user, StaffDocument $document): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admin may delete any document; an employee may ONLY delete their own
     * self-uploaded document while it is still `pending_review`. Verified or
     * HR-uploaded documents are strictly protected (HTTP 403).
     */
    public function delete(User $user, StaffDocument $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ownStaffId = $user->staff()->value('id');

        return $ownStaffId !== null
            && (int) $ownStaffId === (int) $document->staff_id
            && $document->isPendingReview()
            && $document->uploaded_by !== null
            && (int) $document->uploaded_by === (int) $user->id;
    }
}
