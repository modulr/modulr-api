<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulbTechController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_bulb_tech')
            ->select('id', 'name')
            ->orderBy('name', 'desc')
            ->get();
    }
}
