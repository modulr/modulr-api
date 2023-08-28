<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class YearController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_years')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->orderBy('name', 'desc')
            ->get();
    }
}
