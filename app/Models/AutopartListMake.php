<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutopartListMake extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    protected $casts = ['name'];

    public function getNameAttribute($value)
    {
        return strtoupper($this->attributes['name']);
    }

    public function models()
    {
        return $this->hasMany(AutopartListModel::class, 'make_id');
    }
}
