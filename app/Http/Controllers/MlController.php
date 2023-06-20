<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Helpers\ApiMl;

use App\Models\Autopart;
use App\Models\AutopartActivity;

class MlController extends Controller
{
    public function auth (Request $request)
    {
        // Update token
        $storeMl = DB::table('stores_ml')->where('user_id', $request->state)->update([
            'token' => $request->code
        ]);

        $storeMl = DB::table('stores_ml')->where('user_id', $request->state)->first();

        // Create token
        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mercadolibre.com']);

        try {
            $response = $client->request('POST', 'oauth/token', [
                'headers' => [
                    'Accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $storeMl->client_id,
                    'client_secret' => $storeMl->client_secret,
                    'code' => $storeMl->token,
                    'redirect_uri' => $storeMl->redirect_uri
                ]
            ]);

            $res = json_decode($response->getBody());
    
            // Update token & access_token
            DB::table('stores_ml')->where('user_id', $request->state)->update([
                'token' => $res->refresh_token,
                'access_token' => $res->access_token
            ]);
    
            logger('Create token');
            return response()->json(['success' => 'success'], 200);
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            logger('Do not create token');
            return response()->json(['error' => json_decode((string) $e->getResponse()->getBody())], 200);
        }
    }

    public function notifications (Request $request)
    {
        $request = (object) [
            'topic' => 'items',
            'resource' => '/items/MLM929833854',
            'user_id' => 616994509,
            'application_id' => 8010506070145637,
            'sent' => '2022-01-07T18:55:57.75Z',
            'attempts' => 1,
            'received' => '2022-01-07T18:55:57.649Z',
        ];

        //logger(['request' => $request]);


        $mlId = trim($request->resource, '/items/');

        DB::table('notifications_ml')->insert([
            'ml_id' => $mlId,
            'topic' => $request->topic,
            'resource' => $request->resource,
            'attempts' => $request->attempts,
            'user_id' => $request->user_id,
            'application_id' => $request->application_id,
            'sent' => $request->sent,
            'received' => $request->received,
            'created_at' => date("Y-m-d H:i:s", strtotime('now')),
            'updated_at' => date("Y-m-d H:i:s", strtotime('now'))
        ]);

        $autopart = Autopart::where('ml_id', $mlId)->first();

        //If autopart: Editar autoparte existente Else: Si no existe crearla
        if ($autopart) {            

            $response = ApiMl::getItemValues($autopart->store_ml_id, $mlId);

            if ($response['status'] == 200) {

                logger($response['autopart']);

                $autopart->name = $response['autopart']['name'];
                $autopart->description = $response['autopart']['description'];
                $autopart->status_id = $response['autopart']['status_id'];
                $autopart->sale_price = $response['autopart']['sale_price'];
                $autopart->save();

                // Create activity
                AutopartActivity::create([
                    'activity' => 'Se actualizo la autoparte en Mercadolibre',
                    'autopart_id' => $autopart->id,
                    'user_id' => 1
                ]);

                // $content = "Autoparte vendida en ML ".$autopart->storeMl." ID: ".$autopart->ml_id." y en Auto Global ID: ".$autopart->id;
                // $user = \App\User::find(1);
                // $user->notify(new AutopartNotification($content));

                logger('Se actualizo la autoparte '.$mlId.' statusId '.$autopart->status_id.' statusName '.$autopart->status->name);
            } else {
                logger('No se actualizo la autoparte '.$mlId);
            }
        } else {
        //} else if ($request->user_id !== 141862124){
            $storeMl = DB::table('stores_ml')->where('user_id', $request->user_id)->first();
            $response = ApiMl::getItemValues($storeMl->id, $mlId);
            

            if ($response['status'] == 200) {

                $autopartId = DB::table('autoparts')->insertGetId([
                    'name' => $response['autopart']['name'],
                    'description'=>$response['autopart']['description'] ? $response['autopart']['description'] : null,
                    'category_id' => $response['autopart']['category_id'] ? $response['autopart']['category_id'] : null,
                    'make_id' => $response['autopart']['make_id'],
                    'model_id' => $response['autopart']['model_id'],
                    'sale_price' => $response['autopart']['sale_price'],
                    'origin_id' => $response['autopart']['origin_id'],
                    'status_id' => $response['autopart']['status_id'],
                    'ml_id' => $response['autopart']['ml_id'],
                    'store_ml_id' => $storeMl->id,
                    'store_id' => $storeMl->store_id,
                    'created_by' => 1,
                    'created_at' => date("Y-m-d H:i:s", strtotime('now')),
                    'updated_at' => date("Y-m-d H:i:s", strtotime('now'))
                ]);
                    //HASTA AQUI LE MOVI , ABAJO SE LLENA LA TABLA DE AÑOS Y DE IMAGENES 
                if (count($response['autopart']['years_ids'])) {
                    $response['autopart']['years_ids'] = array_unique($response['autopart']['years_ids']);
                    foreach ($response['autopart']['years_ids'] as $yearId) {
                        DB::table('autopart_years')->insert([
                            'autopart_id' => $autopartId,
                            'year_id' => $yearId,
                        ]);
                    }
                }
                logger(["IMAGES"=>$autopart['images']]);
                foreach ($autopart['images'] as $key => $img) {
                    $contents = file_get_contents($img['url']);
                    $name = substr($img['url'], strrpos($img['url'], '/') + 1);
                    Storage::put('autoparts/'.$autopartId.'/images/'.$name, $contents);

                    DB::table('autopart_images')->insert([
                        'basename' => $name,
                        'img_ml_id' =>$img['img_ml_id'],
                        'autopart_id' => $autopartId,
                        'order' => $key,
                    ]);
                }

                $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopartId);
                Storage::put('autoparts/'.$autopartId.'/qr/'.$autopartId.'.png', (string) $qr);

                $content = "Nueva autoparte en ML: ".$storeMl->name.", ID: ".$mlId." y en Auto Global, ID: ".$autopartId;
                $user = \App\User::find(1);
                $user->notify(new AutopartNotification($content));

                logger('Se creo la autoparte '.$mlId);
            } else {
                logger('No se creo la autoparte en sistema '.$mlId);
            }
        }
        

        return response()->json(['success' => 'success'], 200);
    }

    private function getCategoryName ($category_ml_id, $store_ml_id)
    {
        $this->checkToken($store_ml_id);

        $storeMl = DB::table('stores_ml')->find($store_ml_id);

        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mercadolibre.com']);

        try {
            $response = $client->request('GET', 'categories/'.$category_ml_id, [
                'headers' => [
                    'Authorization' => 'Bearer '.$storeMl->access_token,
                ]
            ]);

            $category = json_decode($response->getBody());

            return $category->name;
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            logger($e->getResponse()->getBody());

            return "Otros";
        }
    }
}
