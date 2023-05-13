<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ExportController extends Controller
{
    public function export()
    {
        $autoparts = DB::connection('export')
            ->table('autoparts')
            ->join('autopart_list_makes', 'autopart_list_makes.id', '=', 'autoparts.make_id')
            ->join('autopart_list_models', 'autopart_list_models.id', '=', 'autoparts.model_id')
            ->select('autoparts.*', 'autopart_list_makes.name as make', 'autopart_list_models.name as model')
            ->where('autoparts.status_id', 1)
            ->where('autoparts.created_at', '!=', null)
            //->where('autoparts.id', '<>', 1)
            ->get();

        foreach ($autoparts as $value) {
            if ($value->make_id) {
                if ($value->make == 'Volkswagens') {
                    $value->make = 'Volkswagen';
                }

                $make = DB::table('autopart_list_makes')
                    ->select('id', 'name')
                    ->where('name', 'like', $value->make)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($make) {
                    $value->makeId = $make->id;
                    $value->makeName = $make->name;
                    //$value->name .= " ".$make->name;
                } else {
                    $value->makeId = null;
                    $value->makeName = null;
                }
            }

            if ($value->model_id) {
                $model = DB::table('autopart_list_models')
                    ->select('id', 'name')
                    ->where('name', 'like', $value->model)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($model) {
                    $value->modelId = $model->id;
                    $value->modelName = $model->name;
                    //$value->name .= " ".$model->name;
                } else {
                    $value->modelId = null;
                    $value->modelName = null;
                }
            }

            $value->years = DB::connection('export')
                ->table('autopart_years')
                ->where('autopart_id', $value->id)
                ->get();

            $value->images = DB::connection('export')
                ->table('autopart_images')
                ->where('autopart_id', $value->id)
                ->get();

            //return $value;

            $autopartId = DB::table('autoparts')->insertGetId([
                'name' => $value->name,
                'make_id' => $value->makeId,
                'model_id' => $value->modelId,
                'origin_id' => $value->origin_id,
                'sale_price' => $value->sale_price,
                'description' => $value->description,
                'location' => $value->location,
                'status_id' => 5,
                'store_id' => 3, // Cambiar tienda
                'created_by' => 1,
                'created_at' => date("Y-m-d H:i:s", strtotime('now')),
                'updated_at' => date("Y-m-d H:i:s", strtotime('now'))
            ]);

            if (count($value->years)) {
                foreach ($value->years as $year) {
                    if ($year->year_id != 28 && $year->year_id != 29) {
                        DB::table('autopart_years')->insert([
                            'autopart_id' => $autopartId,
                            'year_id' => $year->year_id,
                        ]);
                    }
                }
            }

            foreach ($value->images as $val) {
                $img = Storage::disk('export')->get('autoparts/'.$value->id.'/images/'.$val->basename);
                //Storage::disk('s3')->put('autoparts/'.$autopartId.'/images/'.$val->basename, $img);
                Storage::put('autoparts/'.$autopartId.'/images/'.$val->basename, $img);

                DB::table('autopart_images')->insert([
                    'basename' => $val->basename,
                    'autopart_id' => $autopartId,
                    'order' => $val->order,
                ]);
            }

            $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopartId);
            //Storage::disk('s3')->put('autoparts/'.$autopartId.'/qr/'.$autopartId.'.png', (string) $qr);
            Storage::put('autoparts/'.$autopartId.'/qr/'.$autopartId.'.png', (string) $qr);

            //return $value;
        }
        
        return 'success';
        //return response()->json($autoparts);
    }
}
