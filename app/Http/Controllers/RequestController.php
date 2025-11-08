<?php

namespace App\Http\Controllers;

use App\Models\RequestModel;
use App\Models\User;
use App\Models\ApproverSetting;
use App\Models\DriverReport;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    // ------------------ List Requests ------------------
    public function index()
    {
        $user = auth()->user();
        $requests = $user->role === 'admin'
            ? RequestModel::with('driver', 'approver')->get()
            : RequestModel::with('driver', 'approver')->where('user_id', $user->id)->get();

        return response()->json($requests);
    }

    // ------------------ Create Request ------------------
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_type' => 'required|string',
            'urgency'      => 'required|in:Regular,Urgent',
            'date'         => 'required|date',
            'time'         => 'required',
            'description'  => 'nullable|string',
            'images.*'     => 'nullable|image|max:2048'
        ]);

        $imageUrls = $this->handleImages($request);
        $userId = auth()->id();
        $approver = $this->getActiveApprover();

        if (!$approver || empty($approver['approver_id'])) {
            return response()->json([
                'error' => 'No Approver has been setup.'
            ], 400);
        }

        $approverId = $approver['approver_id'];

        $req = RequestModel::create([
            'request_type'  => $validated['request_type'],
            'urgency'       => $validated['urgency'],
            'user_id'       => $userId,
            'approver_id'   => $approverId,
            'driver_id'     => null,
            'car_id'        => null,
            'description'   => $validated['description'] ?? '',
            'date'          => $validated['date'],
            'time'          => $validated['time'],
            'image_url'     => $imageUrls,
            'status'        => 'pending',
            'driver_status' => 'pending',
        ]);

        return response()->json(['message' => 'Request created', 'data' => $req], 201);
    }

    // ------------------ Update Request ------------------
    public function update(Request $request, $id)
    {
        $req = RequestModel::find($id);
        if (!$req) return response()->json(['message' => 'Request not found'], 404);
        if ($req->user_id !== auth()->id()) return response()->json(['message' => 'Unauthorized'], 403);

        $validated = $request->validate([
            'request_type' => 'sometimes|string',
            'urgency' => 'sometimes|in:Regular,Urgent',
            'date' => 'sometimes|date',
            'time' => 'sometimes',
            'description' => 'sometimes|string',
            'images.*' => 'sometimes|image|max:2048'
        ]);

        if ($request->hasFile('images')) $validated['image_url'] = $this->handleImages($request);

        $req->update([
            'request_type' => $validated['request_type'] ?? $req->request_type,
            'urgency' => $validated['urgency'] ?? $req->urgency,
            'date' => $validated['date'] ?? $req->date,
            'time' => $validated['time'] ?? $req->time,
            'description' => $validated['description'] ?? $req->description,
            'image_url' => $validated['image_url'] ?? $req->image_url,
        ]);

        return response()->json(['message' => 'Request updated', 'data' => $req]);
    }

    // ------------------ Delete Request ------------------
    public function destroy($id)
    {
        $req = RequestModel::find($id);
        if (!$req) return response()->json(['message' => 'Request not found'], 404);
        if ($req->user_id !== auth()->id()) return response()->json(['message' => 'Unauthorized'], 403);

        $req->delete();
        return response()->json(['message' => 'Request deleted']);
    }

    // ------------------ Approve / Reject ------------------
    public function approve($id)
    {
        return $this->handleApproval($id, 'approved');
    }
    public function reject($id)
    {
        return $this->handleApproval($id, 'rejected');
    }

    // ------------------ Update Driver Status ------------------
    public function updateDriverStatus(Request $request, $id)
    {
        $req = RequestModel::find($id);
        if (!$req) return response()->json(['message' => 'Request not found'], 404);

        $user = auth()->user();
        if ($req->driver_id != $user->id) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate(['driver_status' => 'required|in:pending,assigned,accepted,done']);
        $updateData = ['driver_status' => $request->driver_status];

        // Create/update driver report
        $report = DriverReport::firstOrCreate(['driver_id' => $user->id, 'request_id' => $req->id]);
        if ($request->driver_status === 'accepted') $report->driver_accepted_date_time = now();
        if ($request->driver_status === 'done') {
            $updateData['status'] = 'completed';
            $report->driver_done_date_time = now();
        }
        $req->update($updateData);
        $report->save();

        return response()->json(['message' => 'Driver status updated', 'data' => $req]);
    }

    // ------------------ Helpers ------------------
    private function handleImages(Request $request)
    {
        if (!$request->hasFile('images')) return null;
        return implode(',', array_map(fn($img) => $img->store('uploads/requests', 'public'), $request->file('images')));
    }


    private function getActiveApprover()
    {
        // GETS APPROVER IN THE approver_settings
        // where is_active = 1 effective_from <= CURDATE()
        // AND (effective_to IS NULL OR effective_to >= CURDATE())
        // ORDER BY id DESC
        // LIMIT 1
        // If no rows returned, fallback to any admin in the table (user)
        $today = date('Y-m-d');
        $delegate = ApproverSetting::where('is_active', 1)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->latest()
            ->first();

        if ($delegate) {
            return ['approver_id' => $delegate->delegate_id];
        }

        $admin = User::where('role', 'admin')->first();

        if ($admin) {
            return ['approver_id' => $admin->id];
        }

        return null;
    }

    private function handleApproval($id, $status)
    {
        $req = RequestModel::find($id);
        if (!$req) {
            return response()->json(['error' => 'Request not found'], 404);
        }

        $user = auth()->user();
        $approver = $this->getActiveApprover();

        if (!$approver) {
            return response()->json(['error' => 'No active approver found'], 403);
        }

        if ($user->id != ($approver['approver_id'] ?? $approver->approver_id ?? null)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (in_array($req->status, ['approved', 'rejected', 'completed'], true)) {
            return response()->json([
                'error' => 'This request has already been processed and cannot be changed.'
            ], 400);
        }

        if ($status === 'rejected' && $req->status === 'approved') {
            return response()->json([
                'error' => 'Approved requests cannot be rejected.'
            ], 400);
        }

        $driverId = request('driver_id');

        $updateData = [
            'status'        => $status,
            'approval_date' => now()->toDateString(),
            'approval_time' => now(),
            'driver_id'     => $driverId ?? $req->driver_id,
        ];

        // APPROVED: set driver_status to 'assigned' and create driver report
        if ($status === 'approved' && $updateData['driver_id']) {
            $updateData['driver_status'] = 'assigned';

            // Create driver report
            DriverReport::updateOrCreate(
                ['request_id' => $req->id, 'driver_id' => $updateData['driver_id']],
                ['driver_status' => 'pending']
            );
        }

        $req->update($updateData);

        return response()->json([
            'message' => "Request {$status} successfully.",
            'data' => $req
        ]);
    }
}
