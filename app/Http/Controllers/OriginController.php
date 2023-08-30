<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OriginController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_origins')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->orderBy('id', 'asc')
            ->get();
    }
}
