<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApproverSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'delegate_id',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    // Relations
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * Check if the setting is currently active
     */
    public function isActive(): bool
    {
        $today = date('Y-m-d');
        return $this->is_active 
            && $this->effective_from <= $today 
            && (is_null($this->effective_to) || $this->effective_to >= $today);
    }
}
