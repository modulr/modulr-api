<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class AutopartImage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];
    protected $appends = ['url', 'url_thumbnail'];

    public function autopart()
    {
        return $this->belongsTo(Autopart::class);
    }

    public function getUrlAttribute()
    {
        return Storage::url('autoparts/'.$this->autopart_id.'/images/'.$this->basename);
    }

    public function getUrlThumbnailAttribute()
    {
        if (Storage::exists('autoparts/'.$this->autopart_id.'/images/thumbnail_'.$this->basename))
            return Storage::url('autoparts/'.$this->autopart_id.'/images/thumbnail_'.$this->basename);

        return Storage::url('autoparts/'.$this->autopart_id.'/images/'.$this->basename);
    }
}
