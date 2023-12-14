<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulbPositionController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_bulb_pos')
            ->select('id', 'name')
            ->orderBy('name', 'desc')
            ->get();
    }
}
