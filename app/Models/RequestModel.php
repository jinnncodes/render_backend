<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\DriverReport;

class RequestModel extends Model
{
    use HasFactory;

    protected $table = 'requests';

    protected $fillable = [
        'request_type',
        'urgency',
        'user_id',
        'driver_id',
        'car_id',
        'approver_id',
        'description',
        'date',
        'time',
        'image_url',
        'status',
        'driver_status',
        'approval_date',
        'approval_time',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
    'approver_id' => 'array',
    'date' => 'date',
    'time' => 'datetime:H:i:s'
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function driverReports()
    {
        return $this->hasMany(DriverReport::class, 'request_id');
    }
}
