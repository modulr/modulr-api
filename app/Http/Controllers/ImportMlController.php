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
        $conexion = ApiMl::conexion($request->id);

        if (!$conexion) {
            return view('welcome', ['error' => 'No se pudo refrescar el token']);
        }

        $ids = ApiMl::getItems($conexion);

        return view('welcome', ['store' => $conexion, 'ids' => $ids]);
    }

    // app()->call('App\Http\Controllers\ImportMlController@getNewIds');
    public function getNewIds (Request $request)
    {
        $conexion = ApiMl::conexion($request->id);

        if (!$conexion) {
            return view('welcome', ['error' => 'No se pudo refrescar el token']);
        }

        $ids = ApiMl::getItems($conexion);

        $idsDB = DB::table('autoparts')
                    ->where('store_ml_id', $request->id)
                    ->pluck('ml_id')
                    ->toArray();

        $idsDBMl = DB::table('autoparts_ml')
                    ->where('store_ml_id', $request->id)
                    ->pluck('ml_id')
                    ->toArray();

        $idsMerge = array_merge($idsDB, $idsDBMl);

        $idsNew = array_diff($ids, $idsMerge);

        foreach ($idsNew as $value) {
            DB::table('autoparts_ml')->insert([
                'ml_id' => $value,
                'store_ml_id' => $request->id,
                'created_at' => Carbon::now()
            ]);
        }

        return view('welcome', ['store' => $conexion, 'ids' => $idsNew]);
    }

    // app()->call('App\Http\Controllers\ImportMlController@import');
    public function import(Request $request)
    {
        $conexion = ApiMl::conexion($request->id);

        if (!$conexion) {
            return view('welcome', ['error' => 'No se pudo refrescar el token']);
        }

        $autopartsMl = DB::table('autoparts_ml')
                            ->where('store_ml_id', $request->id)
                            ->where('import', 0)
                            ->get();

        foreach($autopartsMl as $item) {

            if ($item->status_id !== 1) {
                $response = ApiMl::getItem($conexion, $item->ml_id);
    
                $autopart = ApiMl::getItemValues($response, $conexion);

                logger($autopart);

                DB::table('autoparts_ml')
                    ->where('id', $item->id)
                    ->update([
                        'name' => $autopart['name'],
                        'description' => $autopart['description'],
                        'sale_price' => $autopart['sale_price'],
                        'origin_id' => $autopart['origin_id'],
                        'status_id' => $autopart['status_id'],
                        'make_id' => $autopart['make_id'],
                        'model_id' => $autopart['model_id'],
                        'years_ids' => $autopart['years_ids'],
                        'years' => $autopart['years'],
                        'images' => $autopart['images'],
                        'updated_at' => Carbon::now()
                    ]);
    
            }

        }

        $autoparts = DB::table('autoparts_ml')
                ->leftJoin('autopart_list_makes', 'autopart_list_makes.id', '=', 'autoparts_ml.make_id')
                ->leftJoin('autopart_list_models', 'autopart_list_models.id', '=', 'autoparts_ml.model_id')
                ->leftJoin('autopart_list_origins', 'autopart_list_origins.id', '=', 'autoparts_ml.origin_id')
                ->leftJoin('autopart_list_status', 'autopart_list_status.id', '=', 'autoparts_ml.status_id')
                ->select('autoparts_ml.*', 'autopart_list_makes.name as make', 'autopart_list_models.name as model', 'autopart_list_origins.name as origin', 'autopart_list_status.name as status')
                ->where('autoparts_ml.store_ml_id', $request->id)
                ->where('autoparts_ml.import', 0)
                ->orderByDesc('autoparts_ml.status_id')
                ->get();

        return view('welcome', ['store' => $conexion, 'autoparts' => $autoparts]);
    }

    // app()->call('App\Http\Controllers\ImportMlController@save');
    public function save (Request $request)
    {
        $conexion = ApiMl::conexion($request->id);

        if (!$conexion) {
            return view('welcome', ['error' => 'No se pudo refrescar el token']);
        }

        $autoparts = DB::table('autoparts_ml')
                ->where('store_ml_id', $request->id)
                ->where('import', 0)
                //->limit(3)
                ->get();

        foreach ($autoparts as $autopart) {

            $autopartId = DB::table('autoparts')->insertGetId([
                'name' => $autopart->name,
                'make_id' => $autopart->make_id,
                'model_id' => $autopart->model_id,
                'sale_price' => $autopart->sale_price,
                'origin_id' => $autopart->origin_id,
                'status_id' => $autopart->status_id,
                'ml_id' => $autopart->ml_id,
                'store_ml_id' => $conexion->id,
                'store_id' => $conexion->store_id,
                'created_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            if (count(json_decode($autopart->years_ids))) {
                $autopart->years_ids = array_unique(json_decode($autopart->years_ids));
                foreach ($autopart->years_ids as $yearId) {
                    DB::table('autopart_years')->insert([
                        'autopart_id' => $autopartId,
                        'year_id' => $yearId,
                    ]);
                }
            }
            
            foreach (json_decode($autopart->images) as $key => $url) {
                $contents = file_get_contents($url);
                $name = substr($url, strrpos($url, '/') + 1);
                Storage::put('autoparts/'.$autopartId.'/images/'.$name, $contents);

                DB::table('autopart_images')->insert([
                    'basename' => $name,
                    'autopart_id' => $autopartId,
                    'order' => $key,
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

        return view('welcome', ['store' => $conexion, 'autoparts' => $autoparts, 'save' => 'Success']);
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
