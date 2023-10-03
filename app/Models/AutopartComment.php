<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutopartComment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    public function autopart()
    {
        return $this->belongsTo(Autopart::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
