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
    protected $casts = ['sale_price'];
    protected $appends = ['discount_price', 'ml_url', 'qr'];

    public function getSalePriceAttribute($value)
    {
        return number_format($this->attributes['sale_price']);
    }

    public function getDiscountPriceAttribute($value)
    {
        return number_format($this->attributes['sale_price'] + ($this->attributes['sale_price'] * 0.10));
    }

    public function getMlUrlAttribute($value)
    {
        if ($this->attributes['ml_id']) {
            $array = explode('MLM', $this->attributes['ml_id']);
            $url = 'MLM-'.$array[1];
            return 'https://articulo.mercadolibre.com.mx/'.$url;
        } else {
            return $this->attributes['ml_id'];
        }
    }

    public function getQrAttribute()
    {
        return Storage::url('autoparts/'.$this->id.'/qr/'.$this->id.'.png');
    }

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

    // public function years()
    // {
    //     return $this->belongsToMany(AutopartListYear::class, 'autopart_years', 'autopart_id', 'year_id');
    // }

    public function comments()
    {
        return $this->hasMany(AutopartComment::class);
    }

    public function activity()
    {
        return $this->hasMany(AutopartActivity::class);
    }

    public function store()
    {
        return $this->belongsTo(AutopartStore::class);
    }

    public function storeMl()
    {
        return $this->belongsTo(AutopartStoreMl::class);
    }
}
