<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutopartActivity extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function autopart()
    {
        return $this->belongsTo(Autopart::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
