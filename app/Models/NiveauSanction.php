<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NiveauSanction extends Model
{
    protected $table = 'niveaux_sanction';

    protected $fillable = ['code', 'name', 'ordre', 'is_active'];

    protected function casts(): array
    {
        return ['ordre' => 'integer', 'is_active' => 'boolean'];
    }
}
