<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use App\Helpers\ApiMl;

class ImportMlController extends Controller
{
    // app()->call('App\Http\Controllers\ImportMlController@getIds');
    public function getIds (Request $request)
    {
        $response = ApiMl::getItems($request->id);

        return view('welcome', ['store' => $response['data']['store'], 'ids' => $response['data']['ids']]);
    }

    // app()->call('App\Http\Controllers\ImportMlController@getNewIds');
    public function getNewIds (Request $request)
    {
        $response = ApiMl::getItems($request->id);

        $idsDB = DB::table('autoparts')
                    ->where('store_ml_id', $request->id)
                    ->pluck('ml_id')
                    ->toArray();

        $idsDBMl = DB::table('autoparts_ml')
                    ->where('store_ml_id', $request->id)
                    ->pluck('ml_id')
                    ->toArray();

        $idsMerge = array_merge($idsDB, $idsDBMl);

        $idsNew = array_diff($response['data']['ids'], $idsMerge);

        foreach ($idsNew as $value) {
            DB::table('autoparts_ml')->insert([
                'ml_id' => $value,
                'store_ml_id' => $request->id,
                'store_id' => $response['data']['store']->store_id,
                'created_at' => Carbon::now()
            ]);
        }

        $autopartsMl = DB::table('autoparts_ml')
                            ->where('store_ml_id', $request->id)
                            ->where('import', 0)
                            ->get();

        return view('welcome', ['store' => $response['data']['store'], 'autoparts' => $autopartsMl]);
    }

    // app()->call('App\Http\Controllers\ImportMlController@import');
    public function import(Request $request)
    {
        $autopartsMl = DB::table('autoparts_ml')
                            ->where('store_ml_id', $request->id)
                            ->where('import', 0)
                            ->get();

        foreach($autopartsMl as $item) {

            if ($item->status_id !== 1) {
    
                $response = ApiMl::getItemValues($request->id, $item->ml_id);

                DB::table('autoparts_ml')
                    ->where('id', $item->id)
                    ->update([
                        'name' => $response->autopart['name'],
                        'autopart_number' => $response->autopart['autopart_number'],
                        'description' => $response->autopart['description'],
                        'sale_price' => $response->autopart['sale_price'],
                        'origin_id' => $response->autopart['origin_id'],
                        'status_id' => $response->autopart['status_id'],
                        'category_id' => $response->autopart['category_id'],
                        'position_id' => $response->autopart['position_id'],
                        'side_id' => $response->autopart['side_id'],
                        'make_id' => $response->autopart['make_id'],
                        'model_id' => $response->autopart['model_id'],
                        //'years_ids' => $response->autopart['years_ids'],
                        'years' => $response->autopart['years'],
                        'images' => $response->autopart['images'],
                        'updated_at' => Carbon::now()
                    ]);
    
            }

        }

        $autoparts = DB::table('autoparts_ml')
                ->leftJoin('autopart_list_categories', 'autopart_list_categories.id', '=', 'autoparts_ml.category_id')
                ->leftJoin('autopart_list_makes', 'autopart_list_makes.id', '=', 'autoparts_ml.make_id')
                ->leftJoin('autopart_list_models', 'autopart_list_models.id', '=', 'autoparts_ml.model_id')
                ->leftJoin('autopart_list_origins', 'autopart_list_origins.id', '=', 'autoparts_ml.origin_id')
                ->leftJoin('autopart_list_status', 'autopart_list_status.id', '=', 'autoparts_ml.status_id')
                ->select('autoparts_ml.*', 'autopart_list_categories.name as category', 'autopart_list_makes.name as make', 'autopart_list_models.name as model', 'autopart_list_origins.name as origin', 'autopart_list_status.name as status')
                ->where('autoparts_ml.store_ml_id', $request->id)
                ->where('autoparts_ml.import', 0)
                ->orderByDesc('autoparts_ml.status_id')
                ->get();

        $store = DB::table('stores_ml')->find($request->id);

        $autopartsCount = [
            'total' => DB::table('autoparts_ml')->where('store_ml_id', $request->id)->where('import', 0)->count(),
            'incomplete' => DB::table('autoparts_ml')->where('store_ml_id', $request->id)->where('import', 0)->where('status_id', 5)->count()
        ];

        return view('welcome', ['store' => $store, 'autoparts' => $autoparts, 'count' => $autopartsCount]);
    }

    // app()->call('App\Http\Controllers\ImportMlController@save');
    public function save (Request $request)
    {
        $autoparts = DB::table('autoparts_ml')
                ->leftJoin('autopart_list_categories', 'autopart_list_categories.id', '=', 'autoparts_ml.category_id')
                ->leftJoin('autopart_list_makes', 'autopart_list_makes.id', '=', 'autoparts_ml.make_id')
                ->leftJoin('autopart_list_models', 'autopart_list_models.id', '=', 'autoparts_ml.model_id')
                ->leftJoin('autopart_list_origins', 'autopart_list_origins.id', '=', 'autoparts_ml.origin_id')
                ->leftJoin('autopart_list_status', 'autopart_list_status.id', '=', 'autoparts_ml.status_id')
                ->select('autoparts_ml.*', 'autopart_list_makes.name as make', 'autopart_list_models.name as model', 'autopart_list_origins.name as origin', 'autopart_list_status.name as status')
                ->where('autoparts_ml.store_ml_id', $request->id)
                ->where('autoparts_ml.import', 0)
                ->limit($request->limit)
                ->orderByDesc('autoparts_ml.status_id')
                ->get();

        foreach ($autoparts as $autopart) {

            $autopartId = DB::table('autoparts')->insertGetId([
                'name' => $autopart->name,
                'autopart_number' => $autopart->autopart_number,
                'description' => $autopart->description,
                'category_id' => $autopart->category_id,
                'position_id' => $autopart->position_id,
                'side_id' => $autopart->side_id,
                'make_id' => $autopart->make_id,
                'model_id' => $autopart->model_id,
                'sale_price' => $autopart->sale_price,
                'origin_id' => $autopart->origin_id,
                'status_id' => $autopart->status_id,
                'years' => $autopart->years,
                'ml_id' => $autopart->ml_id,
                'store_ml_id' => $autopart->store_ml_id,
                'store_id' => $autopart->store_id,
                'created_by' => 38,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // if (count(json_decode($autopart->years_ids))) {
            //     $autopart->years_ids = array_unique(json_decode($autopart->years_ids));
            //     foreach ($autopart->years_ids as $yearId) {
            //         DB::table('autopart_years')->insert([
            //             'autopart_id' => $autopartId,
            //             'year_id' => $yearId,
            //         ]);
            //     }
            // }
            
            // Falta agregar el id y el thumbnail
            foreach (json_decode($autopart->images) as $key => $img) {

                $contents = file_get_contents($img->url);
                $contentsThumbnail = file_get_contents($img->url_thumbnail);
                $name = substr($img->name, strrpos($img->name, '/') + 1);
                Storage::put('autoparts/'.$autopartId.'/images/'.$name, $contents);
                Storage::put('autoparts/'.$autopartId.'/images/thumbnail_'.$name, $contentsThumbnail);

                DB::table('autopart_images')->insert([
                    'basename' => $name,
                    'img_ml_id' => $img->id,
                    'autopart_id' => $autopartId,
                    'order' => $key,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopartId);
            Storage::put('autoparts/'.$autopartId.'/qr/'.$autopartId.'.png', (string) $qr);

            DB::table('autoparts_ml')
                    ->where('id', $autopart->id)
                    ->update([
                        'import' => true,
                        'updated_at' => Carbon::now()
                    ]);
        }

        $store = DB::table('stores_ml')->find($request->id);

        $autopartsCount = [
            'total' => DB::table('autoparts_ml')->where('store_ml_id', $request->id)->where('import', 0)->count(),
            'incomplete' => DB::table('autoparts_ml')->where('store_ml_id', $request->id)->where('import', 0)->where('status_id', 5)->count()
        ];
        
        return view('welcome', ['store' => $store, 'autoparts' => $autoparts, 'count' => $autopartsCount, 'save' => 'Success']);
    }

    // app()->call('App\Http\Controllers\ImportMlController@getCarsMakesModels');
    public function getCarsMakesModels ()
    {
        $response = Http::withHeaders([
            'X-Parse-Application-Id' => 'hlhoNKjOvEhqzcVAJ1lxjicJLZNVv36GdbboZj3Z',
            'X-Parse-Master-Key' => 'SNMJJF0CZZhTPhLDIqGhTlUNV9r60M2Z5spyWfXW'
        ])->get('https://parseapi.back4app.com/classes/Car_Model_List?limit=10000&order=Make,Model');

        logger($response['results']);

        return true;
    }
}
