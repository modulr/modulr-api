<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class AutopartListLocation extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];
    protected $appends = ['qr'];

    public function store()
    {
        return $this->belongsTo(AutopartStore::class);
    }

    public function getQrAttribute()
    {
        return Storage::url('location/'.$this->store_id.'/'.$this->id.'.png');
    }
}
