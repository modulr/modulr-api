<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_categories')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get();
    }
}
