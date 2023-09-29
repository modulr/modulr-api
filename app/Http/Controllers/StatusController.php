<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    public function index()
    {
        return DB::table('autopart_list_status')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->get();
    }
}
