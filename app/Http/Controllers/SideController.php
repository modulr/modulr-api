<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SideController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_sides')
            ->select('id', 'name', 'variants')
            ->whereNull('deleted_at')
            ->orderBy('name', 'desc')
            ->get();
    }
}
