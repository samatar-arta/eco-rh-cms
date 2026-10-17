<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAssignment extends Model
{
    protected $table = 'role_user';

    protected $fillable = ['user_id', 'role'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
