<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update instructor status from 'pending' to 'active' if they have at least one approved authorization from ACC.
     */
    public function up(): void
    {
        // Get all instructor IDs that have at least one approved authorization
        $authorizedInstructorIds = InstructorAccAuthorization::where('status', 'approved')
            ->distinct()
            ->pluck('instructor_id')
            ->toArray();

        // Update instructors that have approved authorizations from 'pending' to 'active'
        if (!empty($authorizedInstructorIds)) {
            Instructor::where('status', 'pending')
                ->whereIn('id', $authorizedInstructorIds)
                ->update(['status' => 'active']);
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This cannot be fully reversed as we don't know which instructors were originally 'pending'.
     * We'll set them back to 'pending' if they have no approved authorizations.
     */
    public function down(): void
    {
        // Get all instructor IDs that have at least one approved authorization
        $authorizedInstructorIds = InstructorAccAuthorization::where('status', 'approved')
            ->distinct()
            ->pluck('instructor_id')
            ->toArray();

        // Revert instructors that have no approved authorizations back to 'pending'
        if (!empty($authorizedInstructorIds)) {
            Instructor::where('status', 'active')
                ->whereNotIn('id', $authorizedInstructorIds)
                ->update(['status' => 'pending']);
        } else {
            // If no authorized instructors, revert all active instructors to pending
            Instructor::where('status', 'active')
                ->update(['status' => 'pending']);
        }
    }
};

