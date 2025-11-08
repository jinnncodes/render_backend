<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverReport extends Model
{
    use HasFactory;

    protected $table = 'driver_reports';

    protected $fillable = [
        'driver_id',
        'request_id',
        'report_text',
        'status',
        'driver_accepted_date_time',
        'driver_done_date_time',
    ];

      protected $casts = [
        'driver_accepted_date_time' => 'datetime',
        'driver_done_date_time'     => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function request()
    {
        return $this->belongsTo(RequestModel::class, 'request_id');
    }
}
