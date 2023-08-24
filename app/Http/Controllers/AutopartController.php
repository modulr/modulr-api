<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Image;
use QrCode;

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
            'location' => 'required|string',
        ]);

        $autopart = Autopart::create([
            'name' => $request->name,
            'location' => $request->location,
            'origin_id' => $request->origin_id,
            'category_id' => $request->category_id,
            'make_id' => $request->make_id,
            'model_id' => $request->model_id,
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
            'location' => 'required|string',
        ]);

        $autopart = Autopart::find($request->id);
        $autopart->name = $request->name;     
        $autopart->location = $request->location;
        $autopart->origin_id = $request->origin_id;
        $autopart->category_id = $request->category_id;
        $autopart->make_id = $request->make_id;
        $autopart->model_id = $request->model_id;
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

    public function uploadTemp (Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png|max:20000',
        ]);

        $url = Storage::putFile('temp/'.$request->user()->id, $request->file('file'));
        $img = pathinfo($url);

        $thumb = Image::make($request->file('file'));
        $thumb->resize(400, 400, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $thumb->resizeCanvas(400, 400);
        $thumb->encode('jpg');
    
        $url_thumbnail = Storage::put($img['dirname'].'/thumbnail_'.$img['basename'], (string) $thumb);

        return ['url' => Storage::url($url), 'url_thumbnail' => Storage::url($img['dirname'].'/thumbnail_'.$img['basename'])];
    }

    public function upload (Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png|max:20000',
        ]);

        $url = Storage::putFile('autoparts/'.$request->id.'/images', $request->file('file'));
        $img = pathinfo($url);

        $thumb = Image::make($request->file('file'));
        $thumb->resize(400, 400, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $thumb->resizeCanvas(400, 400);
        $thumb->encode('jpg');
    
        Storage::put($img['dirname'].'/thumbnail_'.$img['basename'], (string) $thumb);

        $lastImg = AutopartImage::where('autopart_id', $request->id)->orderBy('order', 'desc')->first();

        if (isset($lastImg)) {
            $order = $lastImg->order + 1;
        } else {
            $order = 0;
        }

        return AutopartImage::create([
            'basename' => $img['basename'],
            'order' => $order,
            'autopart_id' => $request->id
        ]);
    }

}
