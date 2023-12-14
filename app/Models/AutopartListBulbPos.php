<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutopartListBulbPos extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    protected $table = 'autopart_list_bulb_pos';
}
