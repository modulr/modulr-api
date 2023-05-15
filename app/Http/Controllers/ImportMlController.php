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
    
                $autopart = [];

                // FALTA PULIR ESTA FUNCION
                // getItemValues($response)
    
                if ($response->status == 'active' && $response->available_quantity > 0) {
    
                    $autopart['id'] = $item->id;
                    $autopart['name'] = $response->title;
                    $autopart['description'] = '';
                    $autopart['ml_id'] = $response->id;
                    $autopart['sale_price'] = $response->price;
                    $autopart['status_id'] = 1;
                    $autopart['make_id'] = null;
                    $autopart['model_id'] = null;
                    $autopart['years'] = [];
                    $autopart['years_ids'] = [];
                    $autopart['images'] = [];
    
                    if ($response->condition == 'new') {
                        $autopart['origin_id'] = 1;
                    } else {
                        $autopart['origin_id'] = 2;
                    }
    
                    // Get Description
                    $description = ApiMl::getItemDescription($response->id, $conexion);
                    $autopart['description'] = $description->plain_text;
    
                    
    
                    foreach ($response->attributes as $value) {
                    
                        if (!isset($autopart['make_id'])) {
                            if ($value->id == 'BRAND') {
                                $autopart['make'] = $value->value_name;
                                $make = DB::table('autopart_list_makes')
                                    ->where('name', 'like', $value->value_name)
                                    ->whereNull('deleted_at')->first();
    
                                if ($make) {
                                    $autopart['make_id'] = $make->id;
                                }
                            }
                        }
    
                        if (!isset($autopart['model_id'])) {
                            if ($value->id == 'MODEL') {
                                $autopart['model'] = $value->value_name;
                                $model = DB::table('autopart_list_models')
                                    ->where('name', 'like', $value->value_name)
                                    ->whereNull('deleted_at')->first();
                                
                                if ($model) {
                                    $autopart['model_id'] = $model->id;
                                }
                            }
                        }
    
                        if (count($autopart['years_ids']) == 0) {
                            if ($value->id == 'VEHICLE_YEAR') {
                                array_push($autopart['years'], $value->value_name);
        
                                $year = DB::table('autopart_list_years')
                                    ->where('name', 'like', $value->value_name)
                                    ->whereNull('deleted_at')->first();
    
                                if ($year) {
                                    $autopart['year_id'] = $year->id;
                                    array_push($autopart['years_ids'], $year->id);
                                }
                            }
        
                            if ($value->id == 'CAR_MODEL') {
                                array_push($autopart['years'], implode(',', explode(' ', $value->value_name)));
                                $years = explode(' ', $value['value_name']);
        
                                foreach($years as $item){
                                    $year = DB::table('autopart_list_years')
                                        ->where('name', 'like', $item)
                                        ->whereNull('deleted_at')->first();
    
                                    if ($year) {
                                        array_push($autopart['years_ids'], $year->id);
                                    }
                                }
                            }
                        }
                    }
    
                    // Get info from name
                    $nameArray = $this->getInfoName($autopart['name']);
    
                    foreach ($nameArray as $value) {
    
                        // Make
                        if (is_null($autopart['make_id'])) {
                            $make = DB::table('autopart_list_makes')
                                ->select('id', 'name')
                                ->where('name', 'like', $value)
                                ->whereNull('deleted_at')
                                ->first();
                            
                            if ($make) {
                                $autopart['make_id'] = $make->id;
                                $autopart['make'] = $make->name;
                            }
                        }else{
                            $model = DB::table('autopart_list_models')
                                ->select('id', 'name')
                                ->where('make_id', $autopart['make_id'])
                                ->where('name', 'like', $value)
                                ->whereNull('deleted_at')
                                ->first();
    
                                if ($model) {
                                    $autopart['model_id'] = $model->id;
                                    $autopart['model'] = $model->name;
                                }
                        }
                        
                        // Years
                        if (str_contains($value, '-')) {
                            $yearsArray = explode('-',$value);
                            foreach ($yearsArray as $val) {
                                $year = DB::table('autopart_list_years')
                                    ->where('name', 'like', $val)
                                    ->whereNull('deleted_at')->first();
                                if ($year) {
                                    array_push($autopart['years_ids'], $year->id);
                                    array_push($autopart['years'], $year->name);
                                }
                            }
                        } else {
                            $year = DB::table('autopart_list_years')
                                ->where('name', 'like', $value)
                                ->whereNull('deleted_at')->first();
                            if ($year) {
                                array_push($autopart['years_ids'], $year->id);
                                array_push($autopart['years'], $year->name);
                            }
                        }
                    }
    
                    if (is_null($autopart['make_id']) || is_null($autopart['model_id'])) {
                        $autopart['status_id'] = 5;
                    }

                    // Get images
                    // FALTA TRAER EL ORDER
                    foreach ($response->pictures as $value) {
                        $url = str_replace("-O.jpg", "-F.jpg", $value->secure_url);
                        array_push($autopart['images'], $url);
                    }
    
                    DB::table('autoparts_ml')
                        ->where('id', $autopart['id'])
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

    private function getInfo ($name)
    {
        $excptionWords = [
            'central', 'superior', 'derecha', 'derecho', 'der', 'izquierda', 'izquierdo', 'izq', 'delantera', 'delantero', 'trasera', 'trasero', 'tras', 'tra', 'lateral', 'vestidura',
            'puerta', 'chapa', 'fascia', 'facia', 'parrilla', 'rejilla', 'elevador', 'calavera', 'moldura', 'cristal', 'emblema', 'mica', 'tapa', 'foco', 'faro', 'tablero', 'switch',
            'niebla', 'deflector', 'reflejante', 'filtro', 'aire', 'refuerzo', 'base', 'guia', 'guias', 'de', 'del', 'en', 'tiron', 'placa', 'luz', 'juego', 'inf', 'usado', 'tirante',
            'radiador', 'marco', 'cromo', 'cromada', 'parrila', 'manguera', 'motor', 'bomba', 'led', 'xenon', 'nueva', 'nuevo', 'original', 'al', 'con', 'usada', 'bisagra', 'cofre',
            'cola', 'pato', 'spoiler', 'cajuela', 'modulo', 'para', 'halogeno', 'sin', 'hoyo', 'suspension', 'brazo', 'aleron', 'salpicadera', 'reflejantes', 'arco', 'control', 'vidrios',
            'vidrio', 'ventana', 'limpiaparabrisas', 'bocina', 'botagua', 'sensor', 'impacto', 'manija', 'exterior', 'interior', 'camara', 'reversa', 'condensador', 'stop', 'bisagras', 'oem',
            'direccional','espejo','retrovisor', 'sensores','ducto','agua','deposito','limpiaparabrisas', 'direccion','puntas', 'horquilla', 'bolsa', 'alarma', 'soporte', 'generica',
            'banda', 'maestro','boton','seguros', 'portafiltro', 'inferior', 'poleas', 'puertas', 'cubierta', 'anticongelante', 'limpiabrisas', 'mofle', 'cañuela', 'vestidura', 'electrico',
            'cortesia', 'lava', 'actuador'
        ];
        
        $name = trim(mb_strtolower($name));
        
        $cadBuscar = array("á", "Á", "é", "É", "í", "Í", "ó", "Ó", "ú", "Ú");
        $cadPoner = array("a", "A", "e", "E", "i", "I", "o", "O", "u", "U");
        $name = str_replace($cadBuscar, $cadPoner, $name);

        $name = str_replace('/', ' ', $name);
        $name = str_replace('.', '', $name);
        $name = str_replace(',', '', $name);
        $name = preg_replace('/\s{1,}/', ' ', $name);

        $nameArray = explode(' ', $name);

        foreach ($nameArray as $key => $value) {

            if (in_array($value, $excptionWords)) {
                unset($nameArray[$key]);
            }

            $before = '';
            $after = '';
            if (array_key_exists($key-1, $nameArray)) {
                $before = $nameArray[$key-1];
            }
            if (array_key_exists($key+1, $nameArray)) {
                $after = $nameArray[$key+1];
            }

            if ($value == 'mercedes' || $value == 'mb' || $value == 'mercedes-benz') {
                $nameArray[$key] = 'mercedes benz';
            } else
            if ($value == 'vw') {
                $nameArray[$key] = 'volkswagen';
            } else
            if ($value == 'clasec') {
                $nameArray[$key] = 'clase c';
            } else
            if ($value == 'class') {
                $nameArray[$key] = 'clase';
            } else
            if ($value == 'boxter') {
                $nameArray[$key] = 'boxster';
            } else
            if ($value == 'escalate') {
                $nameArray[$key] = 'escalade';
            } else
            if ($value == 'avalance') {
                $nameArray[$key] = 'avalanche';
            } else
            if ($value == 'f150') {
                $nameArray[$key] = 'f-150';
            } else
            if ($value == 'trailblazer') {
                $nameArray[$key] = 'trail blazer';
            } else
            if ($value == 'crusier') {
                $nameArray[$key] = $before.' cruiser';
            } else
            if ($value == 'x-trail' || $value == 'x-terra' || $value == 's-type' || $value == 'cx-3' || $value == 'cx-5' || $value == 'cx-7' || $value == 'cx-9' || $value == 'cx-30' || $value == 's-40' || $value == 'v-40' || $value == 'cr-v' || $value == 'rav-4' || $value == 'xc-60') {
                $nameArray[$key] = str_replace('-', '', $value);
            } else
            if ($value == 'cooper' || $value == 'blazer' || $value == 'hundred' || $value == 'romeo') {
                $nameArray[$key] = $before.' '.$value;
            } else
            if ($value == 'john') {
                $nameArray[$key] = $before.' '.$value.' '.$after;
            } else
            if ($value == 'clase' || $value == 'serie' || $value == 'santa' || $value == 'grand' || $value == 'super' || $value == 'town' || $value == 'land' || $value == 'rav' || $value == 'monte') {
                $nameArray[$key] = $value.' '.$after;
            }
        }

        // logger($nameArray);

        return $nameArray;
    }

    private function getMake ($autopart)
    {
        $nameArray = $this->getInfo($autopart['name']);

        foreach ($nameArray as $value) {

            // Make
            if (is_null($autopart['make_id'])) {
                $make = DB::table('autopart_list_makes')
                    ->select('id', 'name')
                    ->where('name', 'like', $value)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($make) {
                    $autopart['make_id'] = $make->id;
                    $autopart['make'] = $make->name;
                }
            }

            // Model
            if (is_null($autopart['model_id'])) {

                if (is_null($autopart['make_id'])) {
                    $model = DB::table('autopart_list_models')
                    ->select('autopart_list_models.id', 'autopart_list_models.name', 'autopart_list_models.make_id', 'autopart_list_makes.name as make')
                    ->join('autopart_list_makes', 'autopart_list_makes.id', '=', 'autopart_list_models.make_id')
                    ->where('autopart_list_models.name', 'like', $value)
                    ->whereNull('autopart_list_models.deleted_at')
                    ->first();

                    if ($model) {
                        $autopart['model_id'] = $model->id;
                        $autopart['model'] = $model->name;
                        $autopart['make_id'] = $model->make_id;
                        $autopart['make'] = $model->make;
                    }
                } else {
                    $model = DB::table('autopart_list_models')
                    ->select('id', 'name')
                    ->where('make_id', $autopart['make_id'])
                    ->where('name', 'like', $value)
                    ->whereNull('deleted_at')
                    ->first();

                    if ($model) {
                        $autopart['model_id'] = $model->id;
                        $autopart['model'] = $model->name;
                    }
                }
                
            }
            
            // Years
            if (str_contains($value, '-')) {
                $yearsArray = explode('-',$value);
                foreach ($yearsArray as $val) {
                    $year = DB::table('autopart_list_years')
                        ->where('name', 'like', $val)
                        ->whereNull('deleted_at')->first();
                    if ($year) {
                        array_push($autopart['years_ids'], $year->id);
                        array_push($autopart['years'], $year->name);
                    }
                }
            } else {
                $year = DB::table('autopart_list_years')
                    ->where('name', 'like', $value)
                    ->whereNull('deleted_at')->first();
                if ($year) {
                    array_push($autopart['years_ids'], $year->id);
                    array_push($autopart['years'], $year->name);
                }
            }
        }

        // Get info from fields
        // if (!isset($autopart['make_id']) || !isset($autopart['model_id']) || count($autopart['years_ids']) == 0) {
        //     foreach ($response[0]['body']['attributes'] as $value) {
            
        //         if (!isset($autopart['make_id'])) {
        //             if ($value['id'] == 'BRAND') {
        //                 $autopart['make'] = $value['value_name'];
        //                 $make = DB::table('autopart_list_makes')
        //                     ->where('name', 'like', $value['value_name'])
        //                     ->whereNull('deleted_at')->first();

        //                 if ($make) {
        //                     $autopart['make_id'] = $make->id;
        //                 }
        //             }
        //         }

        //         if (!isset($autopart['model_id'])) {
        //             if ($value['id'] == 'MODEL') {
        //                 $autopart['model'] = $value['value_name'];
        //                 $model = DB::table('autopart_list_models')
        //                     ->where('name', 'like', $value['value_name'])
        //                     ->whereNull('deleted_at')->first();
                        
        //                 if ($model) {
        //                     $autopart['model_id'] = $model->id;
        //                 }
        //             }
        //         }

        //         if (count($autopart['years_ids']) == 0) {
        //             if ($value['id'] == 'VEHICLE_YEAR') {
        //                 array_push($autopart['years'], $value['value_name']);

        //                 $year = DB::table('autopart_list_years')
        //                     ->where('name', 'like', $value['value_name'])
        //                     ->whereNull('deleted_at')->first();

        //                 if ($year) {
        //                     $autopart['year_id'] = $year->id;
        //                     array_push($autopart['years_ids'], $year->id);
        //                 }
        //             }

        //             if ($value['id'] == 'CAR_MODEL') {
        //                 array_push($autopart['years'], implode(',', explode(' ', $value['value_name'])));
        //                 $years = explode(' ', $value['value_name']);

        //                 foreach($years as $item){
        //                     $year = DB::table('autopart_list_years')
        //                         ->where('name', 'like', $item)
        //                         ->whereNull('deleted_at')->first();

        //                     if ($year) {
        //                         array_push($autopart['years_ids'], $year->id);
        //                     }
        //                 }
        //             }
        //         }
        //     }
        // }
    }

    private function getInfoName ($name)
    {
        $excptionWords = [
            'central', 'superior', 'derecha', 'derecho', 'der', 'izquierda', 'izquierdo', 'izq', 'delantera', 'delantero', 'trasera', 'trasero', 'tras', 'tra', 'lateral', 'vestidura',
            'puerta', 'chapa', 'fascia', 'facia', 'parrilla', 'rejilla', 'elevador', 'calavera', 'moldura', 'cristal', 'emblema', 'mica', 'tapa', 'foco', 'faro', 'tablero', 'switch',
            'niebla', 'deflector', 'reflejante', 'filtro', 'aire', 'refuerzo', 'base', 'guia', 'guias', 'de', 'del', 'en', 'tiron', 'placa', 'luz', 'juego', 'inf', 'usado', 'tirante',
            'radiador', 'marco', 'cromo', 'cromada', 'parrila', 'manguera', 'motor', 'bomba', 'led', 'xenon', 'nueva', 'nuevo', 'original', 'al', 'con', 'usada', 'bisagra', 'cofre',
            'cola', 'pato', 'spoiler', 'cajuela', 'modulo', 'para', 'halogeno', 'sin', 'hoyo', 'suspension', 'brazo', 'aleron', 'salpicadera', 'reflejantes', 'arco', 'control', 'vidrios',
            'vidrio', 'ventana', 'limpiaparabrisas', 'bocina', 'botagua', 'sensor', 'impacto', 'manija', 'exterior', 'interior', 'camara', 'reversa', 'condensador', 'stop', 'bisagras', 'oem',
            'direccional','espejo','retrovisor', 'sensores','ducto','agua','deposito','limpiaparabrisas', 'direccion','puntas', 'horquilla', 'bolsa', 'alarma', 'soporte', 'generica',
            'banda', 'maestro','boton','seguros', 'portafiltro', 'inferior', 'poleas', 'puertas', 'cubierta', 'anticongelante', 'limpiabrisas', 'mofle', 'cañuela', 'vestidura', 'electrico',
            'cortesia', 'lava', 'actuador'
        ];
        
        $name = trim(mb_strtolower($name));
        
        $cadBuscar = array("á", "Á", "é", "É", "í", "Í", "ó", "Ó", "ú", "Ú");
        $cadPoner = array("a", "A", "e", "E", "i", "I", "o", "O", "u", "U");
        $name = str_replace($cadBuscar, $cadPoner, $name);

        $name = str_replace('/', ' ', $name);
        $name = str_replace('.', '', $name);
        $name = str_replace(',', '', $name);
        $name = preg_replace('/\s{1,}/', ' ', $name);

        $nameArray = explode(' ', $name);

        foreach ($nameArray as $key => $value) {

            if (in_array($value, $excptionWords)) {
                unset($nameArray[$key]);
            }

            $before = '';
            $after = '';
            if (array_key_exists($key-1, $nameArray)) {
                $before = $nameArray[$key-1];
            }
            if (array_key_exists($key+1, $nameArray)) {
                $after = $nameArray[$key+1];
            }

            if ($value == 'mercedes' || $value == 'mb' || $value == 'mercedes-benz') {
                $nameArray[$key] = 'mercedes benz';
            } else
            if ($value == 'vw') {
                $nameArray[$key] = 'volkswagen';
            } else
            if ($value == 'clasec') {
                $nameArray[$key] = 'clase c';
            } else
            if ($value == 'class') {
                $nameArray[$key] = 'clase';
            } else
            if ($value == 'boxter') {
                $nameArray[$key] = 'boxster';
            } else
            if ($value == 'escalate') {
                $nameArray[$key] = 'escalade';
            } else
            if ($value == 'avalance') {
                $nameArray[$key] = 'avalanche';
            } else
            if ($value == 'f150') {
                $nameArray[$key] = 'f-150';
            } else
            if ($value == 'x-trail' || $value == 's-type' || $value == 'cx-3' || $value == 'cx-5' || $value == 'cx-7' || $value == 'cx-9' || $value == 'cx-30' || $value == 's-40' || $value == 'v-40' || $value == 'cr-v' || $value == 'rav-4' || $value == 'xc-60') {
                $nameArray[$key] = str_replace('-', '', $value);
            } else
            if ($value == 'cooper' || $value == 'blazer' || $value == 'hundred' || $value == 'romeo') {
                $nameArray[$key] = $before.' '.$value;
            } else
            if ($value == 'john') {
                $nameArray[$key] = $before.' '.$value.' '.$after;
            } else
            if ($value == 'clase' || $value == 'serie' || $value == 'santa' || $value == 'grand' || $value == 'super' || $value == 'town' || $value == 'land' || $value == 'rav' || $value == 'monte') {
                $nameArray[$key] = $value.' '.$after;
            }
        }

        // logger($nameArray);

        return $nameArray;
    }

    private function getMakeModelYear ($autopartMl)
    {
        $autopart = [];
        if ($autopartMl->status == 'active' && $autopartMl->available_quantity > 0) {
            $autopart['name'] = $autopartMl->title;
            $autopart['ml_id'] = $autopartMl->id;
            $autopart['sale_price'] = $autopartMl->price;
            $autopart['status_id'] = 1;
            $autopart['make_id'] = null;
            $autopart['model_id'] = null;
            $autopart['years_ids'] = [];
            $autopart['years'] = [];
            $autopart['images'] = [];
            
            if (!isset($autopart['make_id']) || !isset($autopart['model_id'])) {
                foreach ($response[0]['body']['attributes'] as $value) {
                
                    if (!isset($autopart['make_id'])) {
                        if ($value['id'] == 'BRAND') {
                            $autopart['make'] = $value['value_name'];
                            $make = DB::table('autopart_list_makes')
                                ->where('name', 'like', $value['value_name'])
                                ->whereNull('deleted_at')->first();

                            if ($make) {
                                $autopart['make_id'] = $make->id;
                            }
                        }
                    }

                    if (!isset($autopart['model_id'])) {
                        if ($value['id'] == 'MODEL') {
                            $autopart['model'] = $value['value_name'];
                            $model = DB::table('autopart_list_models')
                                ->where('name', 'like', $value['value_name'])
                                ->whereNull('deleted_at')->first();
                            
                            if ($model) {
                                $autopart['model_id'] = $model->id;
                            }
                        }
                    }

                    if (count($autopart['years_ids']) == 0) {
                        if ($value['id'] == 'VEHICLE_YEAR') {
                            array_push($autopart['years'], $value['value_name']);
    
                            $year = DB::table('autopart_list_years')
                                ->where('name', 'like', $value['value_name'])
                                ->whereNull('deleted_at')->first();

                            if ($year) {
                                $autopart['year_id'] = $year->id;
                                array_push($autopart['years_ids'], $year->id);
                            }
                        }
    
                        if ($value['id'] == 'CAR_MODEL') {
                            array_push($autopart['years'], implode(',', explode(' ', $value['value_name'])));
                            $years = explode(' ', $value['value_name']);
    
                            foreach($years as $item){
                                $year = DB::table('autopart_list_years')
                                    ->where('name', 'like', $item)
                                    ->whereNull('deleted_at')->first();

                                if ($year) {
                                    array_push($autopart['years_ids'], $year->id);
                                }
                            }
                        }
                    }
                }

                // Get info from name
                $nameArray = $this->getInfoName($autopart['name']);

                foreach ($nameArray as $value) {

                    // Make
                    if (is_null($autopart['make_id'])) {
                        $make = DB::table('autopart_list_makes')
                            ->select('id', 'name')
                            ->where('name', 'like', $value)
                            ->whereNull('deleted_at')
                            ->first();
                        
                        if ($make) {
                            $autopart['make_id'] = $make->id;
                            $autopart['make'] = $make->name;
                        }
                    }else{
                        $model = DB::table('autopart_list_models')
                            ->select('id', 'name')
                            ->where('make_id', $autopart['make_id'])
                            ->where('name', 'like', $value)
                            ->whereNull('deleted_at')
                            ->first();

                            if ($model) {
                                $autopart['model_id'] = $model->id;
                                $autopart['model'] = $model->name;
                            }
                    }
                    
                    // Years
                    if (str_contains($value, '-')) {
                        $yearsArray = explode('-',$value);
                        foreach ($yearsArray as $val) {
                            $year = DB::table('autopart_list_years')
                                ->where('name', 'like', $val)
                                ->whereNull('deleted_at')->first();
                            if ($year) {
                                array_push($autopart['years_ids'], $year->id);
                                array_push($autopart['years'], $year->name);
                            }
                        }
                    } else {
                        $year = DB::table('autopart_list_years')
                            ->where('name', 'like', $value)
                            ->whereNull('deleted_at')->first();
                        if ($year) {
                            array_push($autopart['years_ids'], $year->id);
                            array_push($autopart['years'], $year->name);
                        }
                    }
                }
            }
        }

        return $autopart;
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
