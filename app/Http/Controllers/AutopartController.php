<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use QrCode;
use Illuminate\Support\Arr;

use App\Models\Autopart;
use App\Models\AutopartImage;
use App\Models\AutopartActivity;

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
            ->leftjoin('autopart_images', function ($join) {
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
            $autopart->url_thumbnail = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename);
        }

        return $autoparts;
    }

    public function searchByUser(Request $request)
    {
        $make = $request->make;
        $model = $request->model;
        $category = $request->category;
        $number = $request->number;

        $autoparts = DB::table('autoparts')
            ->select('autoparts.id', 'autoparts.name', 'autoparts.sale_price', 'autopart_images.basename', 'autoparts.status_id', 'autopart_list_status.name as status')
            ->leftjoin('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->leftjoin('autopart_list_status', function ($join) {
                $join->on('autopart_list_status.id', '=', 'autoparts.status_id');
            })
            ->where('autoparts.created_by', $request->user()->id)
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
            $autopart->url_thumbnail = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename);
        }

        return $autoparts;
    }

    public function show(Request $request)
    {
        return Autopart::with([
            'origin',
            'category',
            'make',
            'model',
            'store',
            'storeMl',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
            ])
            ->find($request->id);
    }

    public function store (Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            //'location' => 'required|string',
        ]);

        $autopart = Autopart::create([
            'name' => $request->name,
            'autopart_number' => $request->autopart_number,
            'location' => $request->location,
            'category_id' => $request->category_id,
            'position_id' => $request->position_id,
            'side_id' => $request->side_id,
            'condition_id' => $request->condition_id,
            'origin_id' => $request->origin_id,
            'make_id' => $request->make_id,
            'model_id' => $request->model_id,
            'years' => json_encode(Arr::wrap(data_get($request->years, 'name'))),
            'quality' => $request->quality,
            'sale_price' => $request->sale_price,
            'status_id' => 5,
            'store_id' => $request->user()->store_id,
            'created_by' => $request->user()->id,
        ]);

        if (count($request->images)) {
            foreach ($request->images as $key => $value) {
                if (isset($value['url'])) {
                    $img = pathinfo($value['url']);

                    Storage::move('temp/'.$request->user()->id.'/'.$img['basename'], 'autoparts/'.$autopart->id.'/images/'.$img['basename']);
                    Storage::move('temp/'.$request->user()->id.'/thumbnail_'.$img['basename'], 'autoparts/'.$autopart->id.'/images/thumbnail_'.$img['basename']);

                    AutopartImage::create([
                        'basename' => $img['basename'],
                        'order' => $key,
                        'autopart_id' => $autopart->id
                    ]);
                }
            }
        }

        $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopart->id);
        Storage::put('autoparts/'.$autopart->id.'/qr/'.$autopart->id.'.png', (string) $qr);

        AutopartActivity::create([
            'activity' => 'Autoparte creada',
            'autopart_id' => $autopart->id,
            'user_id' => $request->user()->id
        ]);

        return Autopart::with([
            'origin',
            'category',
            'make',
            'model',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
            ])
            ->find($autopart->id);
    }

    public function update (Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            //'location' => 'required|string',
        ]);

        logger(Arr::pluck($request->years, 'name'));

        $autopart = Autopart::find($request->id);
        $autopart->name = $request->name;     
        $autopart->autopart_number = $request->autopart_number;     
        $autopart->location = $request->location;
        $autopart->category_id = $request->category_id;
        $autopart->position_id = $request->position_id;
        $autopart->side_id = $request->side_id;
        $autopart->condition_id = $request->condition_id;
        $autopart->origin_id = $request->origin_id;
        $autopart->make_id = $request->make_id;
        $autopart->model_id = $request->model_id;
        $autopart->years = json_encode(Arr::pluck($request->years, 'name'));
        $autopart->quality = $request->quality;
        $autopart->sale_price = $request->sale_price;
        $autopart->created_by = $request->user()->id;
        $autopart->save();

        AutopartActivity::create([
            'activity' => 'Autoparte actualizada',
            'autopart_id' => $request->id,
            'user_id' => $request->user()->id
        ]);

        return Autopart::with([
            'origin',
            'category',
            'make',
            'model',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
            ])
            ->find($autopart->id);;
    }
}
