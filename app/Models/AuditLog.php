<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'landlord';

    public $timestamps = false;

    protected $fillable = [
        'account_id', 'actor_id', 'effective_user_id', 'impersonation_id',
        'event', 'subject_type', 'subject_id', 'changes', 'ip', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
