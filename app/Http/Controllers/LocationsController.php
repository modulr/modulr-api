<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationsController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_locations')
            ->select('id', 'location', 'stock', 'store_id')
            ->whereNull('deleted_at')
            ->get();
    }

    public function getByStore(Request $request)
    {
        $storeId = $request->query('store_id');

        return DB::table('autopart_list_locations')
            ->select('id', 'name', 'stock', 'store_id')
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->get();
    }
}
