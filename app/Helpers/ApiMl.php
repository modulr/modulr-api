<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ApiMl
{
    public static function conexion($id)
    {
        $store = DB::table('stores_ml')->find($id);
        return self::checkAccessToken($store);
    }

    private static function checkAccessToken($store)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$store->access_token,
        ])->get('https://api.mercadolibre.com/users/me');

        if ($response['status'] == 401) {
            return self::refreshAccessToken($store);
        }

        return $store;
    }

    private static function refreshAccessToken($store)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'content-type' => 'application/x-www-form-urlencoded',
            ])->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $store->client_id,
                'client_secret' => $store->client_secret,
                'refresh_token' => $store->token,
            ]);

            $res = $response->json();

            // Update token
            $store = DB::table('stores_ml')->where('id', $store->id)->update([
                'token' => $res['refresh_token'],
                'access_token' => $res['access_token'],
            ]);

            logger('Refresh token');
            return $store;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            logger('Do not refresh token');
            return false;
        }
    }


    public static function getItems($conexion)
    {
        $ids = [];
        $scrollId = null;

        do {

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$conexion->access_token,
            ])->get('https://api.mercadolibre.com/users/'.$conexion->user_id.'/items/search', [
                'status' => 'active',
                'search_type' => 'scan',
                'limit' => 100,
                'scroll_id' => $scrollId,
            ]);

            if (isset($response['results']) && count($response['results']) > 0) {
                $ids = array_merge($ids, $response['results']);
            }

            if (isset($response['scroll_id'])) {
                $scrollId = $response['scroll_id'];
            }

        } while (isset($response['results']) && count($response['results']) > 0);

        return $ids;
    }    

    public static function getItem($conexion, $id)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$conexion->access_token,
        ])->get('https://api.mercadolibre.com/items', [
            'ids' => $id,
        ]);

        return $response->object()[0]->body;
    }

    public static function getItemDescription($id, $conexion)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$conexion->access_token,
        ])->get('https://api.mercadolibre.com/items/'.$id.'/description');

        return $response->object();
    }

    public static function getItemValues($response)
    {
        $autopart = [];

        if ($response->status == 'active' && $response->available_quantity > 0) {
            $autopart['name'] = $response->title;
            $autopart['description'] = '';
            $autopart['ml_id'] = $response->id;
            $autopart['sale_price'] = $response->price;
            $autopart['status_id'] = 1;
            $autopart['make_id'] = null;
            $autopart['model_id'] = null;
            $autopart['years_ids'] = [];
            $autopart['years'] = [];
            $autopart['images'] = [];

            if ($response->condition == 'new') {
                $autopart['origin_id'] = 1;
            } else {
                $autopart['origin_id'] = 2;
            }

            // Get Description
            $description = ApiMl::getItemDescription($response->id);
            $autopart['description'] = $description->plain_text;

            
            if (!isset($autopart['make_id']) || !isset($autopart['model_id'])||count($autopart['years_ids']) == 0) {
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
            }

            if (!isset($autopart['make_id']) || !isset($autopart['model_id']) || count($autopart['years_ids']) == 0) {

                // Get info from name
                $nameArray = self::getInfoName($autopart['name']);

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

            if (is_null($autopart['make_id']) || is_null($autopart['model_id'])) {
                $autopart['status_id'] = 5;
            }

            // Get images
            if (isset($response->pictures)) {
                //$sortedImages = $response->pictures->sortBy('order')->take(10);
                foreach ($response->pictures as $value) {
                    $url = str_replace("-O.jpg", "-F.jpg", $value->secure_url);
                    array_push($autopart['images'], $url);
                };
            }

        }

        return $autopart;
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
}