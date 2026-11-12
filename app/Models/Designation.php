<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $fillable = ['title', 'level'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
