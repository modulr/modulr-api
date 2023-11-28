<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Notifications\AutopartNotification;

use App\Models\User;
use App\Models\Autopart;
use App\Models\AutopartImage;
use App\Models\AutopartListCategory;

class ApiMl
{
    protected static $store;

    public static function checkAccessToken ($storeMlId)
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

    private static function refreshAccessToken ()
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

    public static function getItems ($storeMlId)
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

    private static function getItem ($mlId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/items', [
            'ids' => $mlId,
        ]);

        if($response->status() == 200){
            return $response->object()[0];
        }else{
            return $response->object();
        }
    }

    private static function getDescription ($mlId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/items/'.$mlId.'/description');

        return $response->object();
    }

    private static function updateDescription ($autopart,$put)
    {

        $storeMl = DB::table('stores_ml')->find($autopart->store_ml_id);
        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mercadolibre.com']);

        if($put){
            $request_type = 'PUT';
        }else{
            $request_type = 'POST';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $autopart->storeMl->access_token,
            ])->{$request_type}('https://api.mercadolibre.com/items/'.$autopart->ml_id.'/description', [
                "plain_text" => $autopart->description
            ]);

        if($response->successful()){
            // if($autopart->sale_price > 0){
            //     self::updatePrice($autopart);
            // }

            return true;
        } else {
            $response = $response->object();

            logger(["Do not update description in Mercadolibre" => $response->object(), "autopart" => $autopart->id]);

            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*Do not update description in Mercadolibre:* ".$autopart->ml_id;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            return false;
        }
    }

    private static function updatePrice($autopart)
    {

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$autopart->storeMl->access_token,
        ])->post('https://api.mercadolibre.com/items/'.$autopart->ml_id.'/prices/standard', [
            "prices"=> [
                [
                    "conditions" => [
                        "context_restrictions"=> []
                    ],
                    "amount" => $autopart->sale_price,
                    "currency_id" => "MXN"
                ]
            ]
        ]);

        if($response->successful()){
            return true;  
        }else{
            return false;
        }
        
    }

    private static function getCategory ($categoryMlId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/categories/'.$categoryMlId);

        return $response->object();
    }

    private static function getCategoryPredictor ($autopart)
    {
        self::checkAccessToken($autopart->store_ml_id);

        $storeMl = DB::table('stores_ml')->find($autopart->store_ml_id);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$store->access_token,
        ])->get('https://api.mercadolibre.com/sites/MLM/domain_discovery/search?q='.$autopart->name);

        if($response->successful()){
            $category = $response->object();

            if (count($category) > 0) {
                $categoryId = $category[0]->category_id;

                $cat = AutopartListCategory::where('id',$autopart->category_id)->first();
                if($cat !== null){
                    $cat->ml_id = $category[0]->category_id;
                    $cat->name_ml = $category[0]->category_name;
                    $cat->save();
                }
                

            } else {
                $categoryId = "MLM2232";

                $cat = AutopartListCategory::where('name','otros')->first();
                if(!$cat){

                    AutopartListCategory::create([
                        'name' => "OTROS",
                        'ml_id' => "MLM2232",
                        'name_ml' => "Otros"
                    ]);
                }
                
            }

            return $categoryId;
        }else{
            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*Do not get category from Mercadolibre:* ".$autopart->ml_id;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            return "MLM2232";
        }
    }

    public static function getItemValues ($storeMlId, $mlId)
    {
        self::checkAccessToken($storeMlId);

        $response = self::getItem($mlId);

        $autopart = [];

        $pattern = ['*', '#', "`", "~"];

        if ($response->code == 200) {

            $autopart['name'] = str_replace($pattern, '', $response->body->title);
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
            $autopart['date_created'] = $response->body->date_created;
            $autopart['moderation_active'] = false;

            if (isset($response->body->tags) && is_array($response->body->tags)) {
                if (in_array("moderation_penalty", $response->body->tags)) {
                    $autopart['moderation_active'] = true;
                }
            }

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

            $description = self::getDescription($response->body->id);
            $autopart['description'] = isset($description->plain_text) ? str_replace($pattern, '', $description->plain_text) : null;

            $nameArray = self::getInfoName($autopart['name']);

            foreach ($nameArray as $value) {

                // Make
                if (!isset($autopart['make_id'])) {
                    $make = DB::table('autopart_list_makes')
                        ->select('id', 'name')
                        ->where(function ($query) use ($value) {
                            $query->where('name', 'like', $value)
                                ->orWhere(function ($query) use ($value) {
                                    $query->whereNull('deleted_at')
                                        ->whereJsonContains('variants', strtolower($value));
                                });
                        })
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
                            ->where(function ($query) use ($value) {
                                $query->where('autopart_list_models.name', 'like', $value)
                                    ->orWhere(function ($query) use ($value) {
                                        $query->whereNull('autopart_list_models.deleted_at')
                                                ->whereJsonContains('autopart_list_models.variants', strtolower($value));
                                    });
                            })
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
                            ->where(function ($query) use ($value) {
                                $query->where('name', 'like', $value)
                                    ->orWhere(function ($query) use ($value) {
                                        $query->whereNull('deleted_at')
                                            ->whereJsonContains('variants', strtolower($value));
                                    });
                            })
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
                        ->where(function ($query) use ($value) {
                            $query->where('name', 'like', $value)
                                ->orWhere(function ($query) use ($value) {
                                    $query->whereNull('deleted_at')
                                            ->where('variants', 'LIKE', "%".strtolower($value)."%");
                                });
                        })
                        ->first();
                    
                    if ($side) {
                        $autopart['side_id'] = $side->id;
                        $autopart['side'] = $side->name;
                    }
                }

                if (!isset($autopart['position_id'])) {
                    $position = DB::table('autopart_list_positions')
                        ->where(function ($query) use ($value) {
                            $query->where('name', 'like', $value)
                                ->orWhere(function ($query) use ($value) {
                                    $query->where('variants', 'LIKE', "%".strtolower($value)."%");
                                });
                        })
                        ->whereNull('deleted_at')
                        ->first();
                    
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

                if (!isset($autopart['condition_id'])) {
                    if ($value->id == 'ITEM_CONDITION'&& isset($value->value_name)) {
                        $autopart['conditionMl'] = $value->value_name;
                        $condition = DB::table('autopart_list_conditions')
                            ->where('name', 'like', $value->value_name)
                            ->whereNull('deleted_at')->first();
                        
                        if ($condition) {
                            $autopart['condition_id'] = $condition->id;
                            $autopart['condition'] = $condition->name;
                        }
                    }
                }

                if (!isset($autopart['origin_id'])) {
                    if ($value->id == 'ORIGIN'&& isset($value->value_name)) {
                        $autopart['originMl'] = $value->value_name;
                        $origin = DB::table('autopart_list_origins')
                            ->where('name', 'like', $value->value_name)
                            ->whereNull('deleted_at')->first();
                        
                        if ($origin) {
                            $autopart['origin_id'] = $origin->id;
                            $autopart['origin'] = $origin->name;
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

    private static function getInfoName ($name)
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

    public static function getAutopart ($autopart)
    {
        self::checkAccessToken($autopart->store_ml_id);

        $storeMl = DB::table('stores_ml')->find($autopart->store_ml_id);

        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mercadolibre.com']);

        try {
            $response = $client->request('GET', 'items?ids='.$autopart->ml_id, [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => 'Bearer '. $storeMl->access_token
                ]
            ]);

            $autopartMl = json_decode($response->getBody());

            return (object) ['response' => true, 'autopart' => $autopartMl[0]->body];
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {

            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*Do not get autopart from Mercadolibre:* ".$autopart->ml_id;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));
            
            return (object) ['response' => false];
        }
    }

    public static function createAutopart ($autopart)
    {
        self::checkAccessToken($autopart->store_ml_id);
logger(["AUTOPART"=>$autopart]);
        $images = [];
        if (count($autopart->images) > 0) {
            $sortedImages = $autopart->images->sortBy('order')->take(10);
            foreach ($sortedImages as $value) {
                array_push($images, ['source' => $value['url']]);
            };
        }
        
        if (is_null($autopart->category->ml_id)) {
            $categoryId = self::getCategoryPredictor($autopart);
        } else {
            $categoryId = $autopart->category->ml_id;
        }

        $attributesList = [
            [
                "id" => "BRAND",
                "value_name" => $autopart->make ? $autopart->make->name : null
            ],
            [
                "id" => "MODEL",
                "value_name" => $autopart->model ? $autopart->model->name : null
            ],
            [
                "id" => "PART_NUMBER",
                "value_name" => $autopart->autopart_number ? $autopart->autopart_number : 0000
            ],
            [
                "id" => "ITEM_CONDITION",
                "value_name" => $autopart->condition ? $autopart->condition->name : "used"
            ],
            [
                "id" => "ORIGIN",
                "value_name" => $autopart->origin ? $autopart->origin->name : null
            ],
            [
                "id" => "SIDE",
                "value_name" => $autopart->side ? $autopart->side->name : null
            ],
            [
                "id" => "POSITION",
                "value_name" => $autopart->position ? $autopart->position->name : null
            ],
            [
                "id" => "VEHICLE_TYPE",
                "value_name" => "Auto/Camioneta"
            ]
            
        ];

        $attCombination = null;

        // Añadir atributos según la categoría
        if ($autopart->category) {
            switch ($autopart->category->id) {
                case 32: //Cajuela
                    $additionalAttributes = [
                        ["id" => "WIDTH", "value_name" => null],
                        ["id" => "LENGTH", "value_name" => null]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);
                    break;
                case 125: //Rejillas
                    $additionalAttributes = [
                        ["id" => "WIDTH", "value_name" => "0 cm"],
                        ["id" => "LENGTH", "value_name" => "0 cm"]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);

                    $additionalAttributes = [
                        ["id" => "WITH_FOG_LIGHT_HOLE", "value_name" => "0 cm"],
                        ["id" => "IS_OEM_REPLACEMENT", "value_name" => "0 cm"]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);

                    $additionalAttributes = [
                        ["id" => "REAR_BUMPER_MATERIAL", "value_name" => "X"],
                        ["id" => "REAR_BUMPER_FINISH", "value_name" => null],
                        ["id" => "INCLUDES_FOG_LIGHTS", "value_name" => "No"],
                        ["id" => "INCLUDES_MOLDINGS", "value_name" => "No"]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);
                    break;
                case 71: //Faros
                    $additionalAttributes = [
                        ["id" => "WITH_PARKING_LIGHTS", "value_name" => "No"],
                        ["id" => "INCLUDES_BULB", "value_name" => "No"],
                        ["id" => "BULB_TECHNOLOGY", "value_name" => $autopart->bulb_tech],
                        ["id" => "INCLUDES_MOUNTING_HARDWARE", "value_name" => "No"],
                        ["id" => "IS_STREET_LEGAL", "value_name" => "Si"]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);

                    $attCombination = [
                        ["id" => "SIDE", "value_name" => $autopart->side ? $autopart->side->name : null],
                    ];

                    // Eliminar "SIDE" de atributos
                    $attributesList = array_filter($attributesList, function ($attribute) use ($attCombination) {
                        return !in_array($attribute['id'], ['SIDE']);
                    });
                    break;
                case 1: //Puertas
                    $attCombination = [
                        ["id" => "POSITION", "value_name" => $autopart->position ? $autopart->position->name : null],
                        ["id" => "SIDE", "value_name" => $autopart->side ? $autopart->side->name : null],
                        ["id" => "COLOR", "value_name" => "X"]
                    ];

                    // Eliminar "POSITION" y "SIDE" de atributos
                    $attributesList = array_filter($attributesList, function ($attribute) use ($attCombination) {
                        return !in_array($attribute['id'], ['POSITION', 'SIDE']);
                    });
                    break;
                case 99: //Reflejantes
                    $additionalAttributes = [
                        ["id" => "SHAPE", "value_name" => null]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);

                    $attCombination = [
                        ["id" => "COLOR", "value_name" => "X"]
                    ];
                    break;
                case 144: //Luces Stop
                    $additionalAttributes = [
                        ["id" => "BRAKE_LIGHT_POSITION", "value_name" => $autopart->brake_light_pos],
                        ["id" => "BULBS_NUMBER", "value_name" => null],
                        ["id" => "BULBS_TYPE", "value_name" => $autopart->bulb_tech]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);
                    break;
                case 150: //Espejos Laterales
                    $additionalAttributes = [
                        ["id" => "MIRROR_LOCATION", "value_name" => $autopart->side ? $autopart->side->name : null],
                        ["id" => "INCLUDES_MIRROR", "value_name" => $autopart->includes_mirror ? $autopart->includes_mirror : "No"],
                        ["id" => "INCLUDES_CONTROL", "value_name" => "No"],
                        ["id" => "INCLUDES_INCLUDES_MIRROR_TURN_SIGNAL_INDICATORMIRROR", "value_name" => "No"],
                        ["id" => "INCLUDES_SUPPORT", "value_name" => "No"]
                    ];
                    $attributesList = array_merge($attributesList, $additionalAttributes);
                    break;
            }
        }

        if($attCombination){
            $variationsArray = [
                "price"=>$autopart->sale_price,
                "attribute_combinations" => $attCombination,
                "picture_ids" => $images,
                "available_quantity" => 1,
            ];

            $requestData["variations"] = $variationsArray;
        }

        $requestData = [
            "title" => substr($autopart->name, 0, 60),
            "status" => $status,
            "pictures" => $images,
            "attributes" => $attributesList,
            "category_id" => $categoryId,
            "currency_id" => "MXN",
            "available_quantity" => 1,
            "buying_mode" => "buy_it_now",
            "listing_type_id" => "gold_special",
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$autopart->storeMl->access_token,
        ])->post('https://api.mercadolibre.com/items', $requestData);

        if($response->successful()){
            $autopartMl = $response->object();

            if(count($autopartMl->pictures) > 0){
                foreach ($autopartMl->pictures as $key => $imageMl) {
                    $img = AutopartImage::where('autopart_id', $autopart->id)->where('order',$key)->first();
                    $img->img_ml_id = $imageMl->id;
                    $img->save(); 
                }
            }
            $autopart = Autopart::find($autopart->id);
            $autopart->store_ml_id = $autopart->store_ml_id;
            $autopart->ml_id = $autopartMl->id;
            $autopart->save();

            if($autopart->description !== null){
                self::updateDescription($autopart,false);
            }

            return true;

        } else {
            $autopart = Autopart::find($autopart->id);
            $autopart->store_ml_id = null;
            $autopart->save();

            logger(["Do not create autopart in Mercadolibre" => $response->object(), "autopart" => $autopart]);

            $channel = env('TELEGRAM_CHAT_LOG');
            $content = "*Do not create autopart in Mercadolibre:* ".$autopart->id;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            return false;
        }

    }

    public static function updateAutopart ($autopart)
    {
        self::checkAccessToken($autopart->store_ml_id);

        $response = self::getAutopart($autopart);

        $attributesArray = [];
        $variationsArray = [];

        $attributesToCheck = [
            "BRAND",
            "MODEL",
            "PART_NUMBER",
            "ITEM_CONDITION",
            "ORIGIN",
            "SELLER_SKU",
            "SIDE",
            "SIDE_POSITION",
            "POSITION",
            "VEHICLE_TYPE"
        ];

        foreach ($response->autopart->variations as $val) {
            foreach ($attributesToCheck as $attribute) {
                $attributeExists = false;
                foreach ($val->attribute_combinations as $value) {
                    if ($value->id == $attribute && isset($value->value_name)) {
                        $attributeExists = true;
                        switch ($attribute) {
                            case "BRAND":
                                $value->value_name = $autopart->make ? $autopart->make->name : null;
                                break;
                            case "MODEL":
                                $value->value_name = $autopart->model ? $autopart->model->name : null;
                                break;
                            case "PART_NUMBER":
                                $value->value_name = $autopart->autopart_number ? $autopart->autopart_number : null;
                                break;
                            case "ITEM_CONDITION":
                                $value->value_name = $autopart->condition ? $autopart->condition->name : "used";
                                break;
                            case "ORIGIN":
                                $value->value_name = $autopart->origin ? $autopart->origin->name : null;
                                break;
                            case "SIDE":
                                $value->value_name = $autopart->side ? $autopart->side->name : null;
                                break;
                            case "SIDE_POSITION":
                                $value->value_name = $autopart->side ? $autopart->side->name : null;
                                break;
                            case "POSITION":
                                $value->value_name = $autopart->position ? $autopart->position->name : null;
                                break;
                            case "VEHICLE_TYPE":
                                $value->value_name = "Auto/Camioneta";
                                break;
                        }
                        break;
                    }
                }

                if (!$attributeExists) {
                    switch ($attribute) {
                        case "BRAND":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->make ? $autopart->make->name : null
                            ];
                            break;
                        case "MODEL":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->model ? $autopart->model->name : null
                            ];
                            break;
                        case "PART_NUMBER":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->autopart_number ? $autopart->autopart_number : 0000
                            ];
                            break;
                        case "ITEM_CONDITION":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->condition ? $autopart->condition->name : null
                            ];
                            break;
                        case "ORIGIN":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->origin ? $autopart->origin->name : null
                            ];
                            break;
                        case "SIDE":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->side ? $autopart->side->name : null
                            ];
                            break;
                        case "POSITION":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => $autopart->position ? $autopart->position->name : null
                            ];
                            break;
                        case "VEHICLE_TYPE":
                            $attributesArray[] = [
                                "id" => $attribute,
                                "value_name" => "Auto/Camioneta"
                            ];
                            break;
                    }
                }
            }

            $variationsArray[] = [
                "id" => $val->id,
                "price"=>$autopart->sale_price,
                "attribute_combinations" => $val->attribute_combinations
            ];
        }

        if ($autopart->status_id == 4) {
            $status = 'closed';
        } else if ($autopart->status_id == 3){
            $status = 'paused';
        }else {
            $status = 'active';
        }

        if($response->autopart->available_quantity = 0){
            $stock = 1;
        }

        $images = [];
        if (count($autopart->images) > 0) {
            $sortedImages = $autopart->images->sortBy('order')->take(10);
            foreach ($sortedImages as $value) {
                if (isset($value['img_ml_id'])) {
                    array_push($images, ['id' => $value['img_ml_id']]);
                }else{
                    array_push($images, ['source' => $value['url']]);
                }
            };
        }

        foreach ($variationsArray as $variation) {
            if (is_array($variation['attribute_combinations'])) { 
                foreach ($variation['attribute_combinations'] as $combination) {
                    if ($combination->id === 'SIDE_POSITION') {
                        $index = array_search('SIDE', array_column($attributesArray, 'id'));
        
                        if ($index !== false) {
                            unset($attributesArray[$index]);
                        }
                    }
                }
            }
        }

        $attributesList = [];
        foreach ($attributesArray as $key => $value) {
            $attributesList[] = ['id' => $value['id'], 'value_name' => $value['value_name']];
        }

        $requestData = [
            "status" => $status,
            "pictures" => $images,
            "attributes" => $attributesList
        ];

        if (empty($response->autopart->variations)) {
            $requestData["price"] = $autopart->sale_price;
        }
        if ($response->autopart->sold_quantity < 1) {
            $requestData["title"] = substr($autopart->name, 0, 60);
            $requestData["variations"] = $variationsArray;
        }

        $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $autopart->storeMl->access_token,
        ])->put('https://api.mercadolibre.com/items/' . $autopart->ml_id, $requestData);
        

        if($response->successful()){
            $autopartMl = $response->object();
            
            if(count($autopartMl->pictures) > 0){
                foreach ($autopartMl->pictures as $key => $imageMl) {
                    $img = AutopartImage::where('autopart_id', $autopart->id)->where('order',$key)->first();
                    if(isset($img) && !isset($img->img_ml_id)){
                        $img->img_ml_id = $imageMl->id;
                        $img->save();
                    } 
                }
            }

            if($autopart->description !== null && $status !== "closed"){
                self::updateDescription($autopart,true);
            }

            // if($autopart->sale_price > 0){
            //     self::updatePrice($autopart);
            // }

            return true;
        } else {

            logger(["Do not update autopart in Mercadolibre" => $response->object(), "autopart" => $autopart->id]);
            $response = $response->object();
            // $messages = [];

            // foreach ($response->cause as $cause) {
            //     $messages[] = $cause->message;
            // }

            $channel = env('TELEGRAM_CHAT_LOG');
            //$content = "*Do not update autopart in Mercadolibre:* ".$autopart->id."\n".implode("\n", $messages);
            $content = "*Do not update autopart in Mercadolibre:* ".$autopart->id;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));

            return false;
        }
    }
}