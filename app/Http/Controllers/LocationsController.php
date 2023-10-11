<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $superadmin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superadmin = true;
            }
        }

        if ($superadmin) {
            return DB::table('autopart_list_locations')
                ->select('id', 'name', 'stock', 'store_id')
                ->whereNull('deleted_at')
                ->get();
        } else {
            return DB::table('autopart_list_locations')
                ->select('id', 'name', 'stock', 'store_id')
                ->whereNull('deleted_at')
                ->where('store_id', $user->store_id)
                ->get();
        }
    }
}
