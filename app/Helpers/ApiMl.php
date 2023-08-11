<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Notifications\AutopartNotification;

use App\Models\User;

class ApiMl
{
    protected static $store;

    public static function checkAccessToken($storeMlId)
    {
        if (!isset(self::$store) || self::$store->id !== $storeMlId) {
            self::$store = DB::table('stores_ml')->find($storeMlId);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/users/me');

        if ($response->status() !== 200) {
            return self::refreshAccessToken();
        }

        return $response->status();
    }

    private static function refreshAccessToken()
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'content-type' => 'application/x-www-form-urlencoded',
        ])->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => self::$store->client_id,
            'client_secret' => self::$store->client_secret,
            'refresh_token' => self::$store->token,
        ]);

        if ($response->ok()) {
            $res = $response->object();

            DB::table('stores_ml')->where('id', self::$store->id)->update([
                'token' => $res->refresh_token,
                'access_token' => $res->access_token,
                'updated_at' => Carbon::now()
            ]);

            self::$store = DB::table('stores_ml')->find(self::$store->id);

            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*Refresh access_token:* ".self::$store->name;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            return $response->status();
        } else {
            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*Do not refresh access_token:* ".self::$store->name;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            return $response->status();
        }
    }

    public static function getItems($storeMlId)
    {
        self::checkAccessToken($storeMlId);

        $ids = [];
        $scrollId = null;

        do {

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.self::$store->access_token,
            ])->get('https://api.mercadolibre.com/users/'.self::$store->user_id.'/items/search', [
                'status' => 'active',
                'search_type' => 'scan',
                'limit' => 100,
                'scroll_id' => $scrollId,
            ]);

            $res = $response->object();

            if (isset($res->results) && count($res->results) > 0) {
                $ids = array_merge($ids, $res->results);
            }

            if (isset($res->scroll_id)) {
                $scrollId = $res->scroll_id;
            }

        } while (isset($res->results) && count($res->results) > 0);

        return ['status' => $response->status(), 'data' => ['ids' => $ids, 'store' => self::$store]];
    }    

    private static function getItem($mlId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/items', [
            'ids' => $mlId,
        ]);

        return $response->object()[0];
    }

    private static function getItemDescription($mlId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/items/'.$mlId.'/description');

        return $response->object();
    }

    private static function getCategory ($categoryMlId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/categories/'.$categoryMlId);

        return $response->object();
    }

    public static function getItemValues($storeMlId, $mlId)
    {
        self::checkAccessToken($storeMlId);

        $response = self::getItem($mlId);

        $autopart = [];

        if ($response->code == 200) {

            $autopart['name'] = $response->body->title;
            $autopart['description'] = '';
            $autopart['autopart_number'] = null;
            $autopart['ml_id'] = $response->body->id;
            $autopart['sale_price'] = $response->body->price;
            $autopart['status'] = $response->body->status;
            $autopart['status_id'] = 1; // Disponible
            $autopart['make_id'] = null;
            $autopart['model_id'] = null;
            $autopart['category_id'] = null;
            $autopart['position_id'] = null;
            $autopart['side_id'] = null;
            $autopart['years'] = [];
            $autopart['images'] = [];

            if ($response->body->condition == 'new') {
                $autopart['origin_id'] = 1;
            } else {
                $autopart['origin_id'] = 2;
            }

            if ($response->body->category_id) {
                $cat = DB::table('autopart_list_categories')->where('ml_id', $response->body->category_id)->first();
                
                if (isset($cat)) {
                    $autopart['category_id'] = $cat->id;
                } else {
                    $category = self::getCategory($response->body->category_id);

                    if($category->name !== 'Otros'){
                        $catId = DB::table('autopart_list_categories')->insertGetId([
                            'name' => $category->name,
                            'ml_id' => $category->id,
                            'name_ml' => $category->name,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ]);    
                        $autopart['category_id'] = $catId;
                    }else{
                        $autopart['category_id'] = 424;
                    }
                    
                }
            }

            if (isset($response->body->pictures)) {
                foreach ($response->body->pictures as $value) {
                    $id = $value->id;
                    $name = substr($value->secure_url, strrpos($value->secure_url, '/') + 1);
                    $url = str_replace("-O.jpg", "-F.jpg", $value->secure_url);
                    $url_thumbnail = str_replace("-O.jpg", "-C.jpg", $value->secure_url);
                    array_push($autopart['images'], ['id' => $id, 'name' => $name,'url' => $url, 'url_thumbnail' => $url_thumbnail]);
                };
            }

            $description = self::getItemDescription($response->body->id);
            $autopart['description'] = isset($description->plain_text) ? $description->plain_text : null;

            $nameArray = self::getInfoName($autopart['name']);

            foreach ($nameArray as $value) {

                // Make
                if (!isset($autopart['make_id'])) {
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
                if (!isset($autopart['model_id'])) {
                    if (!isset($autopart['make_id'])) {
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
                //if (count($autopart['years']) == 0) {
                    if (str_contains($value, '-')) {
                        $yearsArray = explode('-',$value);
                        foreach ($yearsArray as $val) {
                            $year = DB::table('autopart_list_years')
                                ->where('name', 'like', $val)
                                ->whereNull('deleted_at')->first();
                            if ($year) {
                                array_push($autopart['years'], $year->name);
                            }
                        }
                    } else {
                        $year = DB::table('autopart_list_years')
                            ->where('name', 'like', $value)
                            ->whereNull('deleted_at')->first();
                        if ($year) {
                            array_push($autopart['years'], $year->name);
                        }
                    }
                //}

                if (!isset($autopart['side_id'])) {
                    $side = DB::table('autopart_list_sides')
                        ->where('name', 'like', $value)
                        ->orWhere('variants', 'LIKE', "%".strtolower($value)."%")
                        ->whereNull('deleted_at')->first();
                    
                    if ($side) {
                        $autopart['side_id'] = $side->id;
                        $autopart['side'] = $side->name;
                    }
                }

                if (!isset($autopart['position_id'])) {
                    $position = DB::table('autopart_list_positions')
                        ->where('name', 'like', $value)
                        ->orWhere('variants', 'LIKE', "%".strtolower($value)."%")
                        ->whereNull('deleted_at')->first();
                    
                    if ($position) {
                        $autopart['position_id'] = $position->id;
                        $autopart['position'] = $position->name;
                    }
                }
                
            }

            foreach ($response->body->attributes as $value) {
            
                if (!isset($autopart['make_id'])) {
                    if (($value->id == 'BRAND' || $value->id == 'CAR_BRAND') && isset($value->value_name)) {
                        $autopart['makeMl'] = $value->value_name;
                        $make = DB::table('autopart_list_makes')
                            ->where('name', 'like', $value->value_name)
                            ->whereNull('deleted_at')->first();

                        if ($make) {
                            $autopart['make_id'] = $make->id;
                            $autopart['make'] = $make->name;
                        }
                    }
                }

                if (!isset($autopart['model_id'])) {
                    if (($value->id == 'MODEL' || $value->id == 'CAR_MODEL') && isset($value->value_name)) {
                        $autopart['modelMl'] = $value->value_name;
                        $model = DB::table('autopart_list_models')
                            ->where('name', 'like', $value->value_name)
                            ->whereNull('deleted_at')->first();
                        
                        if ($model) {
                            $autopart['model_id'] = $model->id;
                            $autopart['model'] = $model->name;
                        }
                    }
                }

                //if (count($autopart['years']) == 0) {
                    if ($value->id == 'VEHICLE_YEAR' && isset($value->value_name)) {
                        array_push($autopart['years'], $value->value_name);
                    }

                    // if ($value->id == 'CAR_MODEL') {
                    //     array_push($autopart['years'], implode(',', explode(' ', $value->value_name)));
                    // }
                //}

                if (!isset($autopart['autopart_number'])) {
                    if ($value->id == 'PART_NUMBER' && isset($value->value_name)) {
                        $autopart['autopart_number'] = $value->value_name;
                    }
                }

                if (!isset($autopart['side_id'])) {
                    if ($value->id == 'SIDE_POSITION' && isset($value->value_name)) {
                        $autopart['sideMl'] = $value->value_name;
                        $side = DB::table('autopart_list_sides')
                            ->where('name', 'like', $value->value_name)
                            ->orWhere('variants', 'LIKE', "%".strtolower($value->value_name)."%")
                            ->whereNull('deleted_at')->first();
                        
                        if ($side) {
                            $autopart['side_id'] = $side->id;
                            $autopart['side'] = $side->name;
                        }
                    }
                }

                if (!isset($autopart['position_id'])) {
                    if ($value->id == 'POSITION'&& isset($value->value_name)) {
                        $autopart['positionMl'] = $value->value_name;
                        $position = DB::table('autopart_list_positions')
                            ->where('name', 'like', $value->value_name)
                            ->orWhere('variants', 'LIKE', "%".strtolower($value->value_name)."%")
                            ->whereNull('deleted_at')->first();
                        
                        if ($position) {
                            $autopart['position_id'] = $position->id;
                            $autopart['position'] = $position->name;
                        }
                    }
                }
            }

            foreach ($response->body->variations as $val) {

                foreach ($val->attribute_combinations as $value) {
                    if (!isset($autopart['side_id'])) {
                        if (($value->id == 'SIDE' || $value->id == 'SIDE_POSITION') && isset($value->value_name)) {
                            $autopart['sideMl'] = $value->value_name;
                            $side = DB::table('autopart_list_sides')
                                ->where('name', 'like', $value->value_name)
                                ->orWhere('variants', 'LIKE', "%".strtolower($value->value_name)."%")
                                ->whereNull('deleted_at')->first();
                            
                            if ($side) {
                                $autopart['side_id'] = $side->id;
                                $autopart['side'] = $side->name;
                            }
                        }
                    }
    
                    if (!isset($autopart['position_id'])) {
                        if ($value->id == 'POSITION' && isset($value->value_name)) {
                            $autopart['positionMl'] = $value->value_name;
                            $position = DB::table('autopart_list_positions')
                                ->where('name', 'like', $value->value_name)
                                ->orWhere('variants', 'LIKE', "%".strtolower($value->value_name)."%")
                                ->whereNull('deleted_at')->first();
                            
                            if ($position) {
                                $autopart['position_id'] = $position->id;
                                $autopart['position'] = $position->name;
                            }
                        }
                    }
                }

            }

            if (count($autopart['years']) > 1) {
                $years = [];
                for ($i = min($autopart['years']); $i <= max($autopart['years']); $i++) {
                    $years[] = (string) $i;
                }
                $autopart['years'] = $years;
            }

        } else {
            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*ERROR:* ".$response->code." -> ".$mlId;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));
        }
        
        return (object) ['status' => $response->code, 'autopart' => $autopart, 'store' => self::$store];
    }

    private static function getInfoName($name)
    {
        $excptionWords = [
            'oem', 'central', 'superior', 'lateral', 'de', 'del', 'en', 'al', 'con', 'para', 'sin', 'nueva', 'nuevo', 'usada', 'usado', 'original', 'generica', 'inf', 'cortesia',
            'puerta', 'chapa', 'fascia', 'facia', 'parrilla', 'rejilla', 'elevador', 'calavera', 'moldura', 'cristal', 'emblema', 'mica', 'tapa', 'foco', 'faro', 'tablero', 'switch',
            'niebla', 'deflector', 'reflejante', 'filtro', 'aire', 'refuerzo', 'base', 'guia', 'guias',  'tiron', 'placa', 'luz', 'juego', 'tirante',
            'radiador', 'marco', 'cromo', 'cromada', 'parrila', 'manguera', 'motor', 'bomba', 'led', 'xenon', 'bisagra', 'cofre', 'lava', 'actuador', 'vestidura',
            'cola', 'pato', 'spoiler', 'cajuela', 'modulo', 'halogeno', 'hoyo', 'suspension', 'brazo', 'aleron', 'salpicadera', 'reflejantes', 'arco', 'control', 'vidrios',
            'vidrio', 'ventana', 'limpiaparabrisas', 'bocina', 'botagua', 'sensor', 'impacto', 'manija', 'exterior', 'interior', 'camara', 'reversa', 'condensador', 'stop', 'bisagras', 
            'direccional','espejo','retrovisor', 'sensores','ducto','agua','deposito','limpiaparabrisas', 'direccion','puntas', 'horquilla', 'bolsa', 'alarma', 'soporte',
            'banda', 'maestro','boton','seguros', 'portafiltro', 'inferior', 'poleas', 'puertas', 'cubierta', 'anticongelante', 'limpiabrisas', 'mofle', 'cañuela', 'electrico'
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

        return $nameArray;
    }
}