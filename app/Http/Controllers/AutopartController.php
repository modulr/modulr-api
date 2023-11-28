<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use QrCode;

use App\Models\Autopart;
use App\Models\AutopartImage;
use App\Models\AutopartActivity;
use App\Models\AutopartComment;
use App\Models\AutopartListLocation;
use App\Helpers\ApiMl;
use App\Notifications\AutopartNotification;

class AutopartController extends Controller
{
    // public function search(Request $request)
    // {
    //     $make = $request->make;
    //     $model = $request->model;
    //     $category = $request->category;
    //     $number = $request->number;

    //     $autoparts = DB::table('autoparts')
    //         ->select('autoparts.id', 'autoparts.name', 'autoparts.sale_price', 'autopart_images.basename')
    //         ->leftjoin('autopart_images', function ($join) {
    //             $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
    //         })
    //         ->where('autoparts.status_id', '!=', 4)
    //         ->whereNull('autoparts.deleted_at')
    //         ->when($make, function ($query, $make) {
    //             return $query->where('autoparts.make_id', $make['id']);
    //         })
    //         ->when($model, function ($query, $model) {
    //             return $query->where('autoparts.model_id', $model['id']);
    //         })
    //         ->when($category, function ($query, $category) {
    //             return $query->where('autoparts.category_id', $category['id']);
    //         })
    //         ->when($number, function ($query, $number) {
    //             $query->where(function($q) use ($number) {
    //                 return $q->where('autoparts.name', 'like', '%'.$number.'%')
    //                 ->orWhere('autoparts.id', 'like', '%'.$number.'%')
    //                 ->orWhere('autoparts.description', 'like', '%'.$number.'%')
    //                 ->orWhere('autoparts.ml_id', 'like', '%'.$number.'%')
    //                 ->orWhere('autoparts.autopart_number', 'like', '%'.$number.'%')
    //                 ->orWhere(function ($subQuery) use ($number) {
    //                     $subQuery->whereJsonContains('autoparts.years', $number);
    //                 });
    //             });
    //         })
    //         ->latest('autoparts.created_at')
    //         ->paginate(52);

    //     foreach ($autoparts as $autopart) {
    //         $autopart->url_thumbnail = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->basename);
    //     }

    //     return $autoparts;
    // }

    public function search (Request $request)
    {
        $make = $request->make;
        $model = $request->model;
        $category = $request->category;
        $number = $request->number;
        $keywords = preg_split('/\s+/', $number, -1, PREG_SPLIT_NO_EMPTY);
        $years = collect($request->years)->pluck('name')->toArray();
        $sort = $request->sort ? $request->sort : "latest";
        $sortColumn = 'autoparts.created_at';
        $sortDirection = 'desc'; 

        if ($sort === 'oldest') {
            $sortColumn = 'autoparts.created_at';
            $sortDirection = 'asc';
        } elseif ($sort === 'atoz') {
            $sortColumn = 'autoparts.name';
            $sortDirection = 'asc';
        } elseif ($sort === 'ztoa') {
            $sortColumn = 'autoparts.name';
            $sortDirection = 'desc';
        } elseif ($sort === 'pricetohigh') {
            $sortColumn = 'autoparts.sale_price';
            $sortDirection = 'desc';
        } elseif ($sort === 'pricetolow') {
            $sortColumn = 'autoparts.sale_price';
            $sortDirection = 'asc';
        }

        $autopartsQuery = DB::table('autoparts')
            ->select([
                'autoparts.id',
                'autoparts.name',
                'autoparts.sale_price',
                'autopart_images.basename',
                'autoparts.store_id',
                'autoparts.store_ml_id',
                DB::raw("CONCAT('" . Storage::url('autoparts/') . "', autoparts.id, '/images/thumbnail_', autopart_images.basename) as url_thumbnail")
            ])
            ->leftjoin('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->where('autoparts.status_id', '!=', 4)
            ->where('autoparts.status_id', '!=', 3)
            ->whereNull('autoparts.deleted_at');

        if ($make) {
            $autopartsQuery->where('autoparts.make_id', $make['id']);
        }

        if ($model) {
            $autopartsQuery->where('autoparts.model_id', $model['id']);
        }
        
        if ($category) {
            $autopartsQuery->where('autoparts.category_id', $category['id']);
        }

        if ($years) {
            $autopartsQuery->where(function ($subQuery) use ($years) {
                foreach ($years as $year) {
                    $subQuery->orWhereJsonContains('autoparts.years', $year);
                }
            });
        }

        if ($number) {
            foreach ($keywords as $keyword) {
                $autopartsQuery->where(function ($subQuery) use ($keyword) {
                    $subQuery->orWhere('autoparts.name', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.id', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.description', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.ml_id', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.autopart_number', 'like', '%' . $keyword . '%')
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereJsonContains('autoparts.years', $keyword);
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.category_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_categories')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.make_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_makes')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.model_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_models')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        });
                });
            }
        }
                
        $autopartsQuery->orderBy($sortColumn, $sortDirection);

        $autoparts = $autopartsQuery->paginate(52);

        return $autoparts;
    }

    public function searchInventory (Request $request)
    {
        $make = $request->make;
        $model = $request->model;
        $category = $request->category;
        $number = $request->number;
        $keywords = preg_split('/\s+/', $number, -1, PREG_SPLIT_NO_EMPTY);
        $origin = $request->origin;
        $condition = $request->condition;
        $side = $request->side;
        $position = $request->position;
        $quality = $request->quality;
        $location = $request->location;
        $store = $request->store;
        $store_ml = $request->store_ml;
        $status = collect($request->status)->pluck('id')->toArray();
        $years = collect($request->years)->pluck('name')->toArray();
        $sort = $request->sort;
        $user = $request->user();

        $inventory = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 3) {
                $inventory = true;
            }
        }

        $superamdin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superamdin = true;
            }
        }

        $sortColumn = 'autoparts.created_at';
        $sortDirection = 'desc'; 

        // Verifica la opción de ordenamiento seleccionada y establece la columna y dirección correspondientes
        if ($sort === 'oldest') {
            $sortColumn = 'autoparts.created_at';
            $sortDirection = 'asc';
        } elseif ($sort === 'atoz') {
            $sortColumn = 'autoparts.name';
            $sortDirection = 'asc';
        } elseif ($sort === 'ztoa') {
            $sortColumn = 'autoparts.name';
            $sortDirection = 'desc';
        } elseif ($sort === 'pricetohigh') {
            $sortColumn = 'autoparts.sale_price';
            $sortDirection = 'desc';
        } elseif ($sort === 'pricetolow') {
            $sortColumn = 'autoparts.sale_price';
            $sortDirection = 'asc';
        }

        $autopartsQuery = DB::table('autoparts')
            ->select([
                'autoparts.id',
                'autoparts.name',
                'autoparts.sale_price',
                'autoparts.status_id', 
                'autoparts.store_id', 
                'autoparts.store_ml_id', 
                'autopart_list_status.name as status',
                'autopart_images.basename',
                DB::raw("CONCAT('" . Storage::url('autoparts/') . "', autoparts.id, '/images/thumbnail_', autopart_images.basename) as url_thumbnail")
            ])
            ->leftjoin('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->leftjoin('autopart_list_status', function ($join) {
                $join->on('autopart_list_status.id', '=', 'autoparts.status_id');
            })
            ->whereNull('autoparts.deleted_at');

        if (!$superamdin) {
            $autopartsQuery->where('autoparts.store_id', $user->store_id);
        }
        
        if ($inventory) {
            $autopartsQuery->where('autoparts.created_by', $user->id);
        }
        
        if ($make) {
            $autopartsQuery->where('autoparts.make_id', $make['id']);
        }

        if ($model) {
            $autopartsQuery->where('autoparts.model_id', $model['id']);
        }
        
        if ($category) {
            $autopartsQuery->where('autoparts.category_id', $category['id']);
        }

        if ($origin) {
            $autopartsQuery->where('autoparts.origin_id', $origin['id']);
        }

        if ($condition) {
            $autopartsQuery->where('autoparts.condition_id', $condition['id']);
        }

        if ($side) {
            $autopartsQuery->where('autoparts.side_id', $side['id']);
        }

        if ($position) {
            $autopartsQuery->where('autoparts.position_id', $position['id']);
        }

        if ($quality) {
            $autopartsQuery->where('autoparts.quality_id', $quality['id']);
        }

        if ($location) {
            $autopartsQuery->where('autoparts.location_id', $location['id']);
        }

        if ($store) {
            $autopartsQuery->where('autoparts.store_id', $store['id']);
        }

        if ($store_ml) {
            $autopartsQuery->where('autoparts.store_ml_id', $store_ml['id']);
        }

        if ($status) {
            $autopartsQuery->whereIn('autoparts.status_id', $status);
        }

        if ($years) {
            $autopartsQuery->where(function ($subQuery) use ($years) {
                foreach ($years as $year) {
                    $subQuery->orWhereJsonContains('autoparts.years', $year);
                }
            });
        }

        if ($number) {
            foreach ($keywords as $keyword) {
                $autopartsQuery->where(function ($subQuery) use ($keyword) {
                    $subQuery->orWhere('autoparts.name', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.id', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.description', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.ml_id', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.autopart_number', 'like', '%' . $keyword . '%')
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereJsonContains('autoparts.years', $keyword);
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.category_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_categories')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.make_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_makes')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.model_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_models')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        });
                });
            }
        }
                
        $autopartsQuery->orderBy($sortColumn, $sortDirection);

        $autoparts = $autopartsQuery->paginate(24);

        return $autoparts;
    }

    public function searchSales (Request $request)
    {
        $make = $request->make;
        $model = $request->model;
        $category = $request->category;
        $number = $request->number;
        $keywords = preg_split('/\s+/', $number, -1, PREG_SPLIT_NO_EMPTY);
        $origin = $request->origin;
        $condition = $request->condition;
        $side = $request->side;
        $position = $request->position;
        $quality = $request->quality;
        $location = $request->location;
        $store = $request->store;
        $store_ml = $request->store_ml;
        $status = collect($request->status)->pluck('id')->toArray();
        $years = collect($request->years)->pluck('name')->toArray();
        $sort = $request->sort;
        $user = $request->user();

        $inventory = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 3) {
                $inventory = true;
            }
        }

        $superamdin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superamdin = true;
            }
        }

        $sortColumn = 'autoparts.updated_at';
        $sortDirection = 'desc'; 

        if ($sort === 'oldest') {
            $sortColumn = 'autoparts.updated_at';
            $sortDirection = 'asc';
        } elseif ($sort === 'atoz') {
            $sortColumn = 'autoparts.name';
            $sortDirection = 'asc';
        } elseif ($sort === 'ztoa') {
            $sortColumn = 'autoparts.name';
            $sortDirection = 'desc';
        } elseif ($sort === 'pricetohigh') {
            $sortColumn = 'autoparts.sale_price';
            $sortDirection = 'desc';
        } elseif ($sort === 'pricetolow') {
            $sortColumn = 'autoparts.sale_price';
            $sortDirection = 'asc';
        }

        $autopartsQuery = DB::table('autoparts')
            ->select([
                'autoparts.id',
                'autoparts.name',
                'autoparts.sale_price',
                'autoparts.status_id', 
                'autoparts.store_id', 
                'autoparts.store_ml_id', 
                'autopart_list_status.name as status',
                'autopart_images.basename',
                DB::raw("CONCAT('" . Storage::url('autoparts/') . "', autoparts.id, '/images/thumbnail_', autopart_images.basename) as url_thumbnail")
            ])
            ->leftjoin('autopart_images', function ($join) {
                $join->on('autopart_images.id', '=', DB::raw('(SELECT autopart_images.id FROM autopart_images WHERE autopart_images.autopart_id = autoparts.id ORDER BY autopart_images.order ASC LIMIT 1)'));
            })
            ->leftjoin('autopart_list_status', function ($join) {
                $join->on('autopart_list_status.id', '=', 'autoparts.status_id');
            })
            ->whereNull('autoparts.deleted_at');

        if (!$superamdin) {
            $autopartsQuery->where('autoparts.store_id', $user->store_id);
        }
        
        if ($inventory) {
            $autopartsQuery->where('autoparts.created_by', $user->id);
        }
        
        if ($make) {
            $autopartsQuery->where('autoparts.make_id', $make['id']);
        }

        if ($model) {
            $autopartsQuery->where('autoparts.model_id', $model['id']);
        }
        
        if ($category) {
            $autopartsQuery->where('autoparts.category_id', $category['id']);
        }

        if ($origin) {
            $autopartsQuery->where('autoparts.origin_id', $origin['id']);
        }

        if ($condition) {
            $autopartsQuery->where('autoparts.condition_id', $condition['id']);
        }

        if ($side) {
            $autopartsQuery->where('autoparts.side_id', $side['id']);
        }

        if ($position) {
            $autopartsQuery->where('autoparts.position_id', $position['id']);
        }

        if ($quality) {
            $autopartsQuery->where('autoparts.quality', $quality);
        }

        if ($location) {
            $autopartsQuery->where('autoparts.location_id', $location['id']);
        }

        if ($store) {
            $autopartsQuery->where('autoparts.store_id', $store['id']);
        }

        if ($store_ml) {
            $autopartsQuery->where('autoparts.store_ml_id', $store_ml['id']);
        }

        if ($status) {
            $autopartsQuery->whereIn('autoparts.status_id', $status);
        }

        if ($years) {
            $autopartsQuery->where(function ($subQuery) use ($years) {
                foreach ($years as $year) {
                    $subQuery->orWhereJsonContains('autoparts.years', $year);
                }
            });
        }

        if ($number) {
            foreach ($keywords as $keyword) {
                $autopartsQuery->where(function ($subQuery) use ($keyword) {
                    $subQuery->orWhere('autoparts.name', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.id', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.description', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.ml_id', 'like', '%' . $keyword . '%')
                        ->orWhere('autoparts.autopart_number', 'like', '%' . $keyword . '%')
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereJsonContains('autoparts.years', $keyword);
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.category_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_categories')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.make_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_makes')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        })
                        ->orWhere(function ($subSubQuery) use ($keyword) {
                            $subSubQuery->whereIn('autoparts.model_id', function ($query) use ($keyword) {
                                $query->select('id')
                                    ->from('autopart_list_models')
                                    ->whereJsonContains('variants', $keyword);
                            });
                        });
                });
            }
        }
                
        $autopartsQuery->orderBy($sortColumn, $sortDirection);

        $autoparts = $autopartsQuery->paginate(24);

        return $autoparts;
    }

    public function show (Request $request)
    {
        return Autopart::with([
            'category',
            'position',
            'side',
            'origin',
            'condition',
            'status',
            'make',
            'model',
            'store',
            'storeMl',
            'location',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
            ])
            ->find($request->id);
    }

    public function showByUser (Request $request)
    {
        $user = $request->user();
        $inventory = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 3) {
                $inventory = true;
            }
        }

        $superadmin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superadmin = true;
            }
        }

        $autopartQuery = Autopart::with([
            'category',
            'position',
            'side',
            'origin',
            'condition',
            'status',
            'make',
            'model',
            'store',
            'storeMl',
            'location',
            'comments' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'comments.user',
            'activity' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'activity.user',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
        ]);

        if (!$superadmin) {
            $autopartQuery->where('store_id', $user->store_id);
        }
        if ($inventory) {
            $autopartQuery->where('created_by', $user->id);
        }

        $autopart = $autopartQuery->find($request->id);

        return $autopart; 
    }

    public function store (Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required|integer',
            'make_id' => 'required|integer',
            //'location' => 'required|string',
        ]);

        $autopart = Autopart::create([
            'name' => $request->name,
            'make_id' => $request->make_id,
            'category_id' => $request->category_id,
            'autopart_number' => $request->autopart_number,
            'location_id' => $request->location_id,
            'years' => '[]',
            'quality' => 0,
            'sale_price' => 0,
            'status_id' => 5,
            'store_id' => $request->user()->store_id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->images && count($request->images) > 0) {
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

        if($request->location_id){
            $location = AutopartListLocation::find($request->location_id);
            $location->stock = $location->stock + 1;
            $location->save();
        }

        $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopart->id);
        Storage::put('autoparts/'.$autopart->id.'/qr/'.$autopart->id.'.png', (string) $qr);

        AutopartActivity::create([
            'activity' => 'Autoparte creada',
            'autopart_id' => $autopart->id,
            'user_id' => $request->user()->id
        ]);

        $autopart = Autopart::with([
            'category',
            'status',
            'make',
            'model',
            'store',
            'location',
            'activity' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'activity.user',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
        ])
        ->find($autopart->id);

        if (count($autopart->images) > 0) {
            $autopart->url_thumbnail = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->images->first()->basename);
        }

        $channel = $autopart->store->telegram;
        $content = "✅ *¡Nueva autoparte en AG!*\n*".$autopart->store->name."*\nID: ".$autopart->id."\n".$autopart->name;
        $button = $autopart->id;
        $user = $request->user();
        $user->notify(new AutopartNotification($channel, $content, $button));

        return $autopart;
    }

    public function update (Request $request)
    {

        $request->validate([
            'name' => 'required|string',
        ]);

        $autopart = Autopart::find($request->id);
        
        //Validar cambio ubicacion
        if($autopart->location_id !== $request->location_id){
            if($request->location_id){
                $alta_stock = AutopartListLocation::find($request->location_id);
                $alta_stock->stock = $alta_stock->stock + 1;
                $alta_stock->save();
            }
            
            if($autopart->location_id){
                $baja_stock = AutopartListLocation::find($autopart->location_id);
                $baja_stock->stock = $baja_stock->stock - 1;
                $baja_stock->save();
            }
        }
        
        //Validar y llenar rango de años
        $years = $request->years ? Arr::pluck($request->years, 'name'): [];
        if (count($years) > 1) {
            sort($years);
            $firstYear = min($years);
            $lastYear = max($years);
            $missingYears = [];

            for ($year = $firstYear; $year <= $lastYear; $year++) {
                if (!in_array($year, $years)) {
                    $missingYears[] = json_encode($year);
                }
            }
    
            if (!empty($missingYears)) {
                // Agregar los años faltantes al array de años
                $years = array_merge($years, $missingYears);
            }
            sort($years);
        }

        if ($request->status_id == 5 && $request->sale_price > 0) {
            $request->status_id = 1;
        }

        if ($autopart->store_ml_id !== $request->store_ml_id) {
            $changeStore = true;
        } else {
            $changeStore = false;
        }
        
        $autopart->name = $request->name;     
        $autopart->description = $request->description;
        $autopart->autopart_number = $request->autopart_number;
        $autopart->location_id = $request->location_id;
        $autopart->category_id = $request->category_id;
        $autopart->position_id = $request->position_id;
        $autopart->side_id = $request->side_id;
        $autopart->condition_id = $request->condition_id;
        $autopart->origin_id = $request->origin_id;
        $autopart->make_id = $request->make_id;
        $autopart->model_id = $request->model_id;
        $autopart->years = json_encode($years);
        $autopart->quality = $request->quality;
        $autopart->sale_price = $request->sale_price;
        $autopart->status_id = $request->status_id;
        $autopart->store_ml_id = $request->store_ml_id;
        $autopart->updated_by = $request->user()->id;
        $autopart->save();

        $autopart->bulb_tech = $request->bulb_tech;
        $autopart->brake_light_pos = $request->brake_light_pos;
        $autopart->includes_mirror = $request->includes_mirror ? $request->includes_mirror : "Si";

        if ($changeStore) {
            $sync = ApiMl::createAutopart($autopart);
        } else if ($request->ml_id) {
            $response = ApiMl::getAutopart($autopart);
            if ($response->response) {
                $sync = ApiMl::updateAutopart($autopart);
            } else {
                $sync = false;
            }
        } else {
            $sync = false;
        }

        AutopartActivity::create([
            'activity' => 'Autoparte actualizada',
            'autopart_id' => $request->id,
            'user_id' => $request->user()->id
        ]);

        $autopart = Autopart::with([
            'location',
            'category',
            'position',
            'side',
            'condition',
            'origin',
            'make',
            'model',
            'status',
            'store',
            'storeMl',
            'comments' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'comments.user',
            'activity' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'activity.user',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
        ])
        ->find($autopart->id);

        if (count($autopart->images) > 0) {
            $autopart->url_thumbnail = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->images->first()->basename);
        }

        $channel = $autopart->store->telegram;
        $content = "🖋 *¡Autoparte actualizada en AG!*\n*".$autopart->store->name."*\nID: ".$autopart->id."\n".$autopart->name;
        $button = $autopart->id;
        $user = $request->user();
        $user->notify(new AutopartNotification($channel, $content, $button));

        return ["autopart" => $autopart, "sync" => $sync];
    }

    public function updateStatus (Request $request)
    {
        $this->validate($request, [
            'status_id' => 'required|integer'
        ]);

        $autopart = Autopart::find($request->id);

        $oldStatus = $autopart->status_id;
        $statuses = [
            1 => "Disponible",
            2 => "No Disponible",
            3 => "Separado",
            4 => "Vendido",
            5 => "Incompleto",
            6 => "Sin Mercado Libre"
        ];

        $autopart->status_id = $request->status_id;
        $autopart->save();

        $sync = true;

        //Reactivar en una nueva publicación ML
        if ($autopart->ml_id) {
            if ($oldStatus == 4) {
                $response = ApiMl::getAutopart($autopart);
                if ($response->response) {
                    $sync = ApiMl::createAutopart($autopart);
                } else {
                    $sync = false;
                }
                AutopartComment::create([
                    'comment' => 'Se reactivó la autoparte, a partir de la publicación: '.$autopart->ml_id,
                    'autopart_id' => $autopart->id,
                    'created_by' => 38,
                ]);
            } else {
                $response = ApiMl::getAutopart($autopart);
                if ($response->response) {
                    $sync = ApiMl::updateAutopart($autopart);
                } else {
                    $sync = false;
                }
            }
        }

        AutopartActivity::create([
            'activity' => 'Estatus actualizado ' . $statuses[$oldStatus]." ⏩ ".$autopart->status->name,
            'autopart_id' => $autopart->id,
            'user_id' => $request->user()->id
        ]);

        $autopart = Autopart::with([
            'location',
            'category',
            'position',
            'side',
            'condition',
            'origin',
            'make',
            'model',
            'status',
            'store',
            'storeMl',
            'comments' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'comments.user',
            'activity' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'activity.user',
            'images' => function ($query) {
                $query->orderBy('order', 'asc');
            }
        ])
        ->find($request->id);

        if (count($autopart->images) > 0) {
            $autopart->url_thumbnail = Storage::url('autoparts/'.$autopart->id.'/images/thumbnail_'.$autopart->images->first()->basename);
        }

        $channel = $autopart->store->telegram;
        $content = "🚦 *Estatus actualizado en AG!*\n".$statuses[$oldStatus]." ⏩ ".$autopart->status->name."\n*".$autopart->store->name."*\nID: ".$autopart->id."\n".$autopart->name;
        $button = $autopart->id;
        $user = $request->user();
        $user->notify(new AutopartNotification($channel, $content, $button));
        
        return ['autopart' => $autopart, 'sync' => $sync];
    }

    public function destroy (Request $request)
    {
        $autopart = Autopart::find($request->id);

        if($autopart->ml_id){
            $autopart->status_id = 3;
            ApiMl::updateAutopart($autopart);   
        }

        AutopartActivity::create([
            'activity' => 'Autoparte Eliminada',
            'autopart_id' => $autopart->id,
            'user_id' => $request->user()->id
        ]);

        return Autopart::destroy($request->id);
    }

    public function clone (Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            //'location' => 'required|string',
        ]);

        $years = $request->years ? Arr::pluck($request->years, 'name'): [];
        if (count($years) > 1) {
            sort($years);
            $firstYear = min($years);
            $lastYear = max($years);
            $missingYears = [];

            for ($year = $firstYear; $year <= $lastYear; $year++) {
                if (!in_array($year, $years)) {
                    $missingYears[] = json_encode($year);
                }
            }
    
            if (!empty($missingYears)) {
                // Agregar los años faltantes al array de años
                $years = array_merge($years, $missingYears);
            }
            sort($years);
        }

        if($request->location_id){
            $location = AutopartListLocation::find($request->location_id);
            $location->stock = $location->stock + 1;
            $location->save();
        }

        if ($request->status_id == 5 && $request->sale_price > 0) {
            $request->status_id = 1;
        }

        $autopart = Autopart::create([
            'name' => $request->name,
            'description' => $request->description,
            'autopart_number' => $request->autopart_number,
            'location_id' => $request->location_id,
            'category_id' => $request->category_id,
            'position_id' => $request->position_id,
            'side_id' => $request->side_id,
            'condition_id' => $request->condition_id,
            'origin_id' => $request->origin_id,
            'make_id' => $request->make_id,
            'model_id' => $request->model_id,
            'years' => json_encode($years),
            'quality' => $request->quality,
            'sale_price' => $request->sale_price,
            'status_id' => $request->status_id,
            'store_id' => $request->user()->store_id,
            'created_by' => $request->user()->id,
        ]);

        $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopart->id);
        Storage::put('autoparts/'.$autopart->id.'/qr/'.$autopart->id.'.png', (string) $qr);

        AutopartActivity::create([
            'activity' => 'Autoparte clonada',
            'autopart_id' => $autopart->id,
            'user_id' => $request->user()->id
        ]);

        return Autopart::with([
            'location',
            'category',
            'position',
            'side',
            'condition',
            'origin',
            'make',
            'model',
            'status',
            'store',
            'images',
            'activity' => function ($query) {
                $query->orderBy('id', 'desc');
            },
            'activity.user',
            ])
            ->find($autopart->id);

    }

    public function getDescription (Request $request)
    {
        return DB::table('stores')->where('id', $request->user()->store_id)->first();
    }

    public function qr (Request $request)
    {
        $autopart = Autopart::with(['make', 'model', 'location'])->find($request->id);
        $autopart->years = json_decode($autopart->years);

        if (count($autopart->years) > 0) {
            if (count($autopart->years) > 1) {
                $autopart->yearsRange .= " ".Arr::first($autopart->years).' - '.Arr::last($autopart->years);
            } else {
                $autopart->yearsRange .= " ".Arr::first($autopart->years);
            }
        }

        if (!Storage::exists('autoparts/'.$autopart->id.'/qr/'.$autopart->id.'.png')) {
            $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopart->id);
            Storage::put('autoparts/'.$autopart->id.'/qr/'.$autopart->id.'.png', (string) $qr);
        }

        return view('qr', ['autopart' => $autopart]);
    }
}
