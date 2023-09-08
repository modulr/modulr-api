<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class AutopartListMake extends Model
{
    use HasFactory, Searchable;
    use SoftDeletes;
    
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    const SEARCHABLE_FIELDS = ['id', 'name', 'variants'];

    public function toSearchableArray()
    {
        return $this->only(self::SEARCHABLE_FIELDS);
    }

    public function models()
    {
        return $this->hasMany(AutopartListModel::class, 'make_id');
    }
}
