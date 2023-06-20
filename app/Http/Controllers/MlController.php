<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
        // $request = (object) [
        //     'topic' => 'items',
        //     'resource' => '/items/MLM1526173617',
        //     'user_id' => 141862124,
        //     'application_id' => 7047716906725820,
        //     'sent' => '2022-01-07T18:55:57.75Z',
        //     'attempts' => 1,
        //     'received' => '2022-01-07T18:55:57.649Z',
        // ];

        // logger(['request' => $request]);


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
            $response = $this->getAutopartMl($autopart);
            $description = $this->getDescriptionAutopartMl($response->autopart, $autopart->store_ml_id);

            if ($response->response) {
                $status = $response->autopart->status;


                // Update status
                if (($status == 'paused' || $status == 'closed') && $autopart->status_id !== 3) {
                    $autopart->status_id = 4;
                } else if ($status == 'active') {
                    $autopart->status_id = 1;
                }

                if($response->autopart->category_id){
                    $cat = AutopartListCategory::where('ml_id',$response->autopart->category_id)->first();
                    if(!$cat){
                        $catName = $this->getCategoryName($response->autopart->category_id, $autopart->store_ml_id);

                        $cat = AutopartListCategory::create([
                            'name' => strtolower($catName),
                            'ml_id' => $response->autopart->category_id,
                            'name_ml' => $catName
                        ]);
                        $autopart->category_id = $cat->id;
                    }else{
                        $autopart->category_id = $cat->id;
                    }
                }

                $autopart->name = $response->autopart->title;
                $autopart->description = $description;
                $autopart->sale_price = $response->autopart->price;
                $autopart->save();        
                // Create activity
                AutopartActivity::create([
                    'activity' => 'Cambio el estatus a '.$autopart->status->name.' en Mercadolibre',
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
            
            $autopartMl = $this->getAutopartMl((object)['store_ml_id' => $storeMl->id, 'ml_id' => $mlId]);

            if ($autopartMl->response && $autopartMl->autopart->status == 'active') {
                $description = $this->getDescriptionAutopartMl($autopartMl->autopart, $storeMl->id);

                //BUSCAR CAGTEGORIA EN CASO DE NO EXISTIR CREARLA
                $cat = AutopartListCategory::where('ml_id',$autopartMl->autopart->category_id)->first();
                $catName = $this->getCategoryName($autopartMl->autopart->category_id, $storeMl->id);
                if(!$cat){

                    $cat = AutopartListCategory::create([
                        'name' => strtolower($catName),
                        'ml_id' => $autopartMl->autopart->category_id,
                        'name_ml' => $catName
                    ]);
                }else if(is_null($cat->ml_id) || is_null($cat->name_ml)){
                    $cat->ml_id = $autopartMl->autopart->category_id;
                    $cat->name_ml = $catName;
                    $cat->save();
                }

                $autopart = $this->getInfo($autopartMl->autopart);

                logger(['ml' => $autopartMl, 'aut' => $autopart]);

                $autopartId = DB::table('autoparts')->insertGetId([
                    'name' => $autopart['name'],
                    'description'=>$description ? $description : null,
                    'category_id' => $cat ? $cat->id : null,
                    'make_id' => $autopart['make_id'],
                    'model_id' => $autopart['model_id'],
                    'sale_price' => $autopart['sale_price'],
                    'origin_id' => $autopart['origin_id'],
                    'status_id' => $autopart['status_id'],
                    'ml_id' => $autopart['ml_id'],
                    'store_ml_id' => $storeMl->id,
                    'store_id' => $storeMl->store_id,
                    'created_by' => 1,
                    'created_at' => date("Y-m-d H:i:s", strtotime('now')),
                    'updated_at' => date("Y-m-d H:i:s", strtotime('now'))
                ]);

                if (count($autopart['years_ids'])) {
                    $autopart['years_ids'] = array_unique($autopart['years_ids']);
                    foreach ($autopart['years_ids'] as $yearId) {
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
}
