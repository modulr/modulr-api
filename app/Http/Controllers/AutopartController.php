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
            ->whereIn('autoparts.status_id', [1,2,3,5,6])
            ->when($make, function ($query, $make) {
                $query->where('autoparts.make_id', $make['id']);
            })
            ->when($model, function ($query, $model) {
                $query->where('autoparts.model_id', $model['id']);
            })
            ->when($category, function ($query, $category) {
                $query->where('autoparts.category_id', $category['id']);
            })
            ->when($number, function ($query, $number) {
                $query->where('autoparts.name', 'like', '%'.$number.'%')
                ->orWhere('autoparts.description', 'like', '%'.$number.'%')
                ->orWhere('autoparts.id', 'like', '%'.$number.'%')
                ->orWhere('autoparts.ml_id', 'like', '%'.$number.'%')
                ->orWhere('autoparts.autopart_number', 'like', '%'.$number.'%');
            })
            ->join('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->select('autoparts.id', 'autoparts.name', 'autoparts.sale_price', 'autopart_images.basename','autoparts.status_id')
            ->inRandomOrder()
            ->paginate(52);

        foreach ($autoparts as $autopart) {
            if ($autopart->status_id == 4) {
                unset($autoparts[$key]);
            }else{
                $autopart->discount_price = number_format($autopart->sale_price + ($autopart->sale_price * 0.10));
                $autopart->sale_price = number_format($autopart->sale_price);
                // if (Storage::exists('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename)) {
                //     $autopart->url = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename);
                // } else {
                    $autopart->url = Storage::url('autoparts/'.$autopart->id.'/images/'.$autopart->basename);
                //}
            }
            
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
            ])->whereIn('status_id', [1,2,3,5,6])
            ->find($request->id);
    }
}
