<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AutopartController extends Controller
{
    public function getAll()
    {
        $autoparts = DB::table('autoparts')
            ->whereIn('autoparts.status_id', [1,6])
            ->join('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->select('autoparts.*', 'autopart_images.basename', 'autopart_images.order')
            ->inRandomOrder()
            ->limit(100)
            ->get();

        foreach ($autoparts as $autopart) {
            $autopart->name = Str::limit($autopart->name, 50);
            $autopart->discount_price = number_format($autopart->sale_price + ($autopart->sale_price * 0.10));
            $autopart->sale_price = number_format($autopart->sale_price);
            $autopart->url = Storage::url('autoparts/'.$autopart->id.'/images/'.$autopart->basename);
        }

        return $autoparts;
    }
}
