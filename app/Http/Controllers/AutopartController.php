<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\Autopart;

class AutopartController extends Controller
{
    public function search(Request $request)
    {
        $make = $request->make;
        $model = $request->model;
        $category = $request->category;

        $autoparts = DB::table('autoparts')
            ->whereIn('autoparts.status_id', [1,6])
            ->when($make, function ($query, $make) {
                $query->where('autoparts.make_id', $make['id']);
            })
            ->when($model, function ($query, $model) {
                $query->where('autoparts.model_id', $model['id']);
            })
            ->when($category, function ($query, $category) {
                $query->where('autoparts.category_id', $category['id']);
            })
            ->join('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->select('autoparts.*', 'autopart_images.basename', 'autopart_images.order')
            ->inRandomOrder()
            ->paginate(50);

        foreach ($autoparts as $autopart) {
            $autopart->discount_price = number_format($autopart->sale_price + ($autopart->sale_price * 0.10));
            $autopart->sale_price = number_format($autopart->sale_price);
            // if (Storage::exists('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename)) {
            //     $autopart->url = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename);
            // } else {
                $autopart->url = Storage::url('autoparts/'.$autopart->id.'/images/'.$autopart->basename);
            //}
        }

        return $autoparts;
    }

    public function show(Request $request)
    {
        return Autopart::with([
            'make',
            'model',
            'years',
            'origin',
            'status',
            'store',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
            ])->find($request->id);
    }
}
