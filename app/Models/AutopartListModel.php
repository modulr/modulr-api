<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutopartListModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $guarded = ['id'];

    protected $casts = ['name'];

    public function getNameAttribute($value)
    {
        return strtoupper($this->attributes['name']);
    }

    public function make()
    {
        return $this->belongsTo(AutopartListMake::class);
    }
}
