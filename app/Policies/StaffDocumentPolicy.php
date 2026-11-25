<?php

namespace App\Policies;

use App\Models\StaffDocument;
use App\Models\User;

class StaffDocumentPolicy
{
    /**
     * ONLY an Admin, an authorized Legal Team / `legal_tasks` viewer, or the exact employee
     * who owns the document may view/download it.
     * Any other user (including Salon Managers viewing another employee's document) is denied (403).
     */
    public function view(User $user, StaffDocument $document): bool
    {
        if ($user->isAdmin() || $user->canView('legal_tasks')) {
            return true;
        }

        $ownStaffId = $user->staff()->value('id');

        return $ownStaffId !== null && (int) $ownStaffId === (int) $document->staff_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->canEdit('legal_tasks');
    }

    public function verify(User $user, StaffDocument $document): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admin may delete any document; Legal Team (`legal_tasks` edit) may delete legal staff documents;
     * an employee may ONLY delete their own self-uploaded document while it is still `pending_review`.
     */
    public function delete(User $user, StaffDocument $document): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (
            $user->canEdit('legal_tasks')
            && array_key_exists((string) $document->category, StaffDocument::LEGAL_STAFF_CATEGORIES)
        ) {
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
