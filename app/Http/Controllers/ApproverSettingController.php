<?php

namespace App\Http\Controllers;

use App\Models\ApproverSetting;
use App\Models\User;
use App\Models\RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApproverSettingController extends Controller
{
    /**
     * Get the currently active approver
     */
    public function getActiveApprover()
    {
        $today = date('Y-m-d');

        $delegate = ApproverSetting::where('is_active', 1)
            ->where('effective_from', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->latest()
            ->first();

        if ($delegate) {
            return response()->json([
                'approver_id' => $delegate->delegate_id,
                'approver_name' => $delegate->delegate->name
            ]);
        }

        $admin = User::where('role', 'admin')->first();
        return response()->json([
            'approver_id' => $admin->id,
            'approver_name' => $admin->name
        ]);
    }

    /**
     * Set a delegate approver
     */
    public function setDelegate(Request $request)
    {
        $request->validate([
            'delegate_id' => 'required|exists:users,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $admin_id = auth()->id();

        // Disable any previous active delegate for this admin
        ApproverSetting::where('admin_id', $admin_id)->update(['is_active' => 0]);

        // Create new delegate
        $approver = ApproverSetting::create([
            'admin_id' => $admin_id,
            'delegate_id' => $request->delegate_id,
            'effective_from' => $request->effective_from,
            'effective_to' => $request->effective_to,
            'is_active' => 1,
        ]);

        return response()->json([
            'message' => 'Delegate approver set successfully.',
            'data' => $approver
        ]);
    }

    /**
     * Migrate pending requests from one approver to another
     */
    public function migratePendingRequests(Request $request)
    {
        $request->validate([
            'old_approver_id' => 'required|exists:users,id',
            'new_approver_id' => 'required|exists:users,id',
        ]);

        $affected = RequestModel::where('approver_id', $request->old_approver_id)
            ->where('status', 'pending')
            ->update([
                'approver_id' => $request->new_approver_id
            ]);

        return response()->json([
            'message' => "Migrated $affected pending requests to new approver.",
        ]);
    }
}
