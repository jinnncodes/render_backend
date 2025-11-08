<?php

use App\Http\Controllers\ApproverSettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DriverReportController;
use Illuminate\Support\Facades\Route;



// ==========================
// DASHBOARD
// ==========================
Route::get('/dashboard', function () {
    $user = auth()->user();

    // Example dashboard data — you can customize
    $data = [
        // 'user' => $user,
        // 'requestsCount' => \App\Models\RequestModel::count(),
        // 'pendingApprovals' => \App\Models\RequestModel::where('status', 'pending')->count(),
        // 'driverReportsToday' => \App\Models\DriverReport::whereDate('created_at', today())->count(),
    ];

    return response()->json($data);
});

// ==========================
// AUTHENTICATION
// ==========================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // ==========================
    // USERS
    // ==========================
    Route::middleware('auth:sanctum')->get('/user', function () {
        $user = auth()->user();

        $permissions = match (strtolower($user->role)) {
            'admin' => ['admin.view','users.view', 'requests.view', 'drivers.view'],
            'coordinator' => ['requests.view'],
            'driver' => ['drivers.view'],
            default => [],
        };

        return response()->json(array_merge($user->toArray(), [
            'permissions' => $permissions,
        ]));
    });



    Route::get('/users', [UserController::class, 'index']);
    Route::post('/user', [UserController::class, 'store']);
    Route::put('/user/{id}', [UserController::class, 'update']);
    Route::delete('/user/{id}', [UserController::class, 'destroy']);

    // ==========================
    // REQUESTS
    // ==========================
    Route::prefix('requests')->group(function () {
        Route::get('/', [RequestController::class, 'index']);
        Route::post('/', [RequestController::class, 'store']);
        Route::put('{id}', [RequestController::class, 'update']);
        Route::delete('{id}', [RequestController::class, 'destroy']);

        Route::post('{id}/approve', [RequestController::class, 'approve']);
        Route::post('{id}/reject', [RequestController::class, 'reject']);
        Route::post('{id}/driver-status', [RequestController::class, 'updateDriverStatus']);
    });

    // ==========================
    // APPROVER SETTINGS
    // ==========================
    Route::prefix('approvers')->group(function () {
        Route::get('/active', [ApproverSettingController::class, 'getActiveApprover']);
        Route::post('/set', [ApproverSettingController::class, 'setDelegate']);
        Route::post('/migrate', [ApproverSettingController::class, 'migratePendingRequests']);
    });

    // ==========================
    // DRIVER REPORTS
    // ==========================
    Route::prefix('driver-reports')->group(function () {
        Route::get('/', [DriverReportController::class, 'index']);
        Route::get('/drivers', [DriverReportController::class, 'getDriversByDate']);
        Route::get('/driver/{driverId}', [DriverReportController::class, 'getReportsByDriver']);
        Route::put('{id}', [DriverReportController::class, 'updateReport']);
        Route::put('{id}/review', [DriverReportController::class, 'reviewReport']);
    });
});
