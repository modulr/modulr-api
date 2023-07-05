<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_models')
            ->select('id', 'name', 'make_id')
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get();
    }
}
