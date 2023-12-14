<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingTypeController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_shipping_type')
            ->select('id', 'name')
            ->orderBy('name', 'desc')
            ->get();
    }
}
