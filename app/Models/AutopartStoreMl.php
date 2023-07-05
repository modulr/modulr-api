<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutopartStoreMl extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];
    protected $table = 'stores_ml';
}
