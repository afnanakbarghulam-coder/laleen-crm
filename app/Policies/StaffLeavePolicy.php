<?php

namespace App\Policies;

use App\Models\StaffLeave;
use App\Models\User;

class StaffLeavePolicy
{
    /**
     * ONLY an Admin or the exact employee whose leave owns the medical certificate may access it.
     */
    public function viewCertificate(User $user, StaffLeave $leave): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ownStaffId = $user->staff()->value('id');

        return $ownStaffId !== null && (int) $ownStaffId === (int) $leave->staff_id;
    }

    public function cancel(User $user, StaffLeave $leave): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ownStaffId = $user->staff()->value('id');

        return $ownStaffId !== null
            && (int) $ownStaffId === (int) $leave->staff_id
            && $leave->status === StaffLeave::STATUS_PENDING;
    }

    public function uploadCertificate(User $user, StaffLeave $leave): bool
    {
        if ($user->isAdmin()) {
            return $leave->leave_type === StaffLeave::TYPE_SICK;
        }

        $ownStaffId = $user->staff()->value('id');

        return $ownStaffId !== null
            && (int) $ownStaffId === (int) $leave->staff_id
            && $leave->leave_type === StaffLeave::TYPE_SICK
            && $leave->status === StaffLeave::STATUS_PENDING;
    }

    public function review(User $user): bool
    {
        return $user->isAdmin();
    }
}
