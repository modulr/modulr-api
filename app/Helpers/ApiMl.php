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

    private static function checkAccessToken($storeMlId)
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

            logger('Refresh access_token');
            return $response->status();
        } else {
            logger('Do not refresh access_token');
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
            $autopart['ml_id'] = $response->body->id;
            $autopart['sale_price'] = $response->body->price;
            $autopart['status_id'] = 1;
            $autopart['make_id'] = null;
            $autopart['category_id'] = null;
            $autopart['model_id'] = null;
            $autopart['years_ids'] = [];
            $autopart['years'] = [];
            $autopart['images'] = [];

            if ($response->body->status == 'paused' || $response->body->status == 'closed') {
                $autopart['status_id'] = 4;

                $channel = '-858634389';
                $content = "*Status:* ".$response->body->id." -> ".$response->body->status;
                $user = User::find(38);
                $user->notify(new AutopartNotification($channel, $content));

                //logger(['response' => $response->body]);
            }

            if ($response->body->condition == 'new') {
                $autopart['origin_id'] = 1;
            } else {
                $autopart['origin_id'] = 2;
            }

            $description = self::getItemDescription($response->body->id);
            $autopart['description'] = isset($description->plain_text) ? $description->plain_text : '';

            if ($response->body->category_id) {
                $cat = DB::table('autopart_list_categories')->where('ml_id', $response->body->category_id)->first();
                
                if (isset($cat)) {
                    $autopart['category_id'] = $cat->id;
                } else {
                    $category = self::getCategory($response->body->category_id);

                    $catId = DB::table('autopart_list_categories')->insertGetId([
                        'name' => $category->name,
                        'ml_id' => $category->id,
                        'name_ml' => $category->name,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);

                    $autopart['category_id'] = $catId;
                }
            }
            
            foreach ($response->body->attributes as $value) {
            
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

                //if (count($autopart['years_ids']) == 0) {
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
                        $years = explode(' ', $value->value_name);

                        foreach($years as $item){
                            $year = DB::table('autopart_list_years')
                                ->where('name', 'like', $item)
                                ->whereNull('deleted_at')->first();

                            if ($year) {
                                array_push($autopart['years_ids'], $year->id);
                            }
                        }
                    }
                //}
            }

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
                //if (count($autopart['years_ids']) == 0) {
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
                //}
                
            }

            if (!isset($autopart['make_id']) || !isset($autopart['model_id'])) {
                $autopart['status_id'] = 5;
            }

            if (isset($response->body->pictures)) {
                foreach ($response->body->pictures as $value) {
                    $url = str_replace("-O.jpg", "-F.jpg", $value->secure_url);
                    $url_thumbnail = $value->secure_url;
                    $id = $value->id;
                    array_push($autopart['images'], ['id' => $id, 'url' => $url, 'url_thumbnail' => $url_thumbnail]);
                };
            }

        } else {
            $channel = '-858634389';
            $content = "*Code:* ".$mlId." -> ".$response->code;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            //logger(['response' => $response]);
        }
        
        return (object) ['status' => 200, 'autopart' => $autopart, 'store' => self::$store];
    }

    private static function getInfoName($name)
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

        return $nameArray;
    }
}