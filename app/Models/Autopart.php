<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Autopart extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];
    protected $appends = ['qr'];

    public function make()
    {
        return $this->belongsTo(AutopartListMake::class);
    }

    public function model()
    {
        return $this->belongsTo(AutopartListModel::class);
    }

    public function origin()
    {
        return $this->belongsTo(AutopartListOrigin::class);
    }

    public function status()
    {
        return $this->belongsTo(AutopartListStatus::class);
    }

    public function images()
    {
        return $this->hasMany(AutopartImage::class);
    }

    public function comments()
    {
        return $this->hasMany(AutopartComment::class);
    }

    public function activity()
    {
        return $this->hasMany(AutopartActivity::class);
    }

    public function latestActivity()
    {
        return $this->hasOne(AutopartActivity::class)->latest();
    }

    public function store()
    {
        return $this->belongsTo(AutopartStore::class);
    }

    public function storeMl()
    {
        return $this->belongsTo(AutopartStoreMl::class);
    }

    public function getQrAttribute()
    {
        return Storage::url('autoparts/'.$this->id.'/qr/'.$this->id.'.png');
    }
}
