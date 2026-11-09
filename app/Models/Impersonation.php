<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Impersonation extends Model
{
    use HasUlids;

    protected $connection = 'landlord';

    protected $fillable = [
        'actor_id', 'target_id', 'account_id', 'reason', 'ip', 'user_agent',
        'started_at', 'ended_at', 'ended_by_id', 'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
