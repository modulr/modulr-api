<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class Autopart extends Model
{
    use HasFactory, Searchable;
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];
    protected $appends = ['qr'];

    const SEARCHABLE_FIELDS = ['id', 'name', 'description','autopart_number','ml_id','years', 'make', 'model','category','position','side'];

    public function toSearchableArray()
    {
        // return $this->only(self::SEARCHABLE_FIELDS);
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'categoryName' => $this->category['name'] ?? '', 
            'categoryVariants' => $this->category['variant'] ?? '', 
        ];
    }

    public function make()
    {
        return $this->belongsTo(AutopartListMake::class);
    }

    public function model()
    {
        return $this->belongsTo(AutopartListModel::class);
    }

    public function category()
    {
        return $this->belongsTo(AutopartListCategory::class);
    }

    public function position()
    {
        return $this->belongsTo(AutopartListPosition::class);
    }

    public function side()
    {
        return $this->belongsTo(AutopartListSide::class);
    }

    public function condition()
    {
        return $this->belongsTo(AutopartListCondition::class);
    }

    public function origin()
    {
        return $this->belongsTo(AutopartListOrigin::class);
    }

    public function status()
    {
        return $this->belongsTo(AutopartListStatus::class);
    }

    public function store()
    {
        return $this->belongsTo(AutopartStore::class);
    }

    public function storeMl()
    {
        return $this->belongsTo(AutopartStoreMl::class);
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

    public function getQrAttribute()
    {
        return Storage::url('autoparts/'.$this->id.'/qr/'.$this->id.'.png');
    }
}
