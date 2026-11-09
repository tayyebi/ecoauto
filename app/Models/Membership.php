<?php

declare(strict_types=1);

namespace App\Models;

use App\Store;
use App\Tenancy\Account;
use Illuminate\Database\Eloquent\Model;

// DECISION (tenancy): a membership is the only grant of access. There is no
// per-row ownership and no implicit super-user, which keeps "who may do what in
// this subtree" answerable from one table.
class Membership extends Model
{
    public const ROLES = ['owner', 'admin', 'editor', 'viewer'];

    protected $connection = 'landlord';

    protected $fillable = ['user_id', 'account_id', 'role', 'invited_by'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account(): ?Account
    {
        return Store::resolver()->registry()->find((string) $this->account_id);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'owner' => 'مالک',
            'admin' => 'مدیر',
            'editor' => 'نویسنده',
            'viewer' => 'بازدیدکننده',
            default => $this->role,
        };
    }
}
