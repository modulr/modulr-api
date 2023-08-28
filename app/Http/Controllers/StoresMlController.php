<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoresMlController extends Controller
{
    public function index()
    {
        return DB::table('stores_ml')
            ->select('id', 'name','store_id')
            ->whereNull('deleted_at')
            ->get();
    }
}
