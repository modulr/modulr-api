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
        $number = $request->number;

        $autoparts = DB::table('autoparts')
            ->select('autoparts.id', 'autoparts.name', 'autoparts.sale_price', 'autopart_images.basename')
            ->join('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->where('autoparts.status_id', '!=', 4)
            ->whereNull('autoparts.deleted_at')
            ->when($make, function ($query, $make) {
                return $query->where('autoparts.make_id', $make['id']);
            })
            ->when($model, function ($query, $model) {
                return $query->where('autoparts.model_id', $model['id']);
            })
            ->when($category, function ($query, $category) {
                return $query->where('autoparts.category_id', $category['id']);
            })
            ->when($number, function ($query, $number) {
                $query->where(function($q) use ($number) {
                    return $q->where('autoparts.name', 'like', '%'.$number.'%')
                    ->orWhere('autoparts.id', 'like', '%'.$number.'%')
                    ->orWhere('autoparts.description', 'like', '%'.$number.'%')
                    ->orWhere('autoparts.ml_id', 'like', '%'.$number.'%')
                    ->orWhere('autoparts.autopart_number', 'like', '%'.$number.'%')
                    ->orWhere(function ($subQuery) use ($number) {
                        $subQuery->whereJsonContains('autoparts.years', $number);
                    });
                });
            })
            ->latest('autoparts.created_at')
            ->paginate(52);

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
            'storeMl',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
            ])
            ->find($request->id);
    }
}
