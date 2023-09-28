<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index()
    {
        return DB::table('stores')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->get();
    }
}
