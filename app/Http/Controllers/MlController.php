<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use App\Helpers\ApiMl;

use App\Models\Autopart;
use App\Models\AutopartActivity;

class MlController extends Controller
{
    public function auth (Request $request)
    {
        DB::table('stores_ml')->where('user_id', $request->state)->update([
            'token' => $request->code
        ]);

        $storeMl = DB::table('stores_ml')->where('user_id', $request->state)->first();

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'content-type' => 'application/x-www-form-urlencoded',
        ])->post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $storeMl->client_id,
            'client_secret' => $storeMl->client_secret,
            'code' => $storeMl->token,
            'redirect_uri' => $storeMl->redirect_uri
        ]);

        if ($response->ok()) {
            $res = $response->object();

            DB::table('stores_ml')->where('user_id', $request->state)->update([
                'token' => $res->refresh_token,
                'access_token' => $res->access_token,
                'updated_at' => Carbon::now()
            ]);

            logger('Create token');
        } else {
            logger('Do not create token');
        }

        return $response->status();
    }

    public function notifications (Request $request)
    {
        // $request = (object) [
        //     'topic' => 'items',
        //     'resource' => '/items/MLM929833854',
        //     'user_id' => 616994509,
        //     'application_id' => 8010506070145637,
        //     'sent' => '2022-01-07T18:55:57.75Z',
        //     'attempts' => 1,
        //     'received' => '2022-01-07T18:55:57.649Z',
        // ];

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

        if ($autopart) {
            $response = ApiMl::getItemValues($autopart->store_ml_id, $mlId);

            if ($response->status == 200) {

                $autopart->name = $response->autopart['name'];
                $autopart->description = $response->autopart['description'];
                $autopart->status_id = $response->autopart['status_id'];
                $autopart->sale_price = $response->autopart['sale_price'];
                $autopart->save();

                AutopartActivity::create([
                    'activity' => 'Se actualizo la autoparte en Mercadolibre',
                    'autopart_id' => $autopart->id,
                    'user_id' => 1
                ]);

                // $content = "Autoparte actualizada en ML ".$autopart->storeMl." ID: ".$autopart->ml_id." y en Auto Global ID: ".$autopart->id;
                // $user = \App\User::find(1);
                // $user->notify(new AutopartNotification($content));

                logger('Se actualizo la autoparte '.$mlId.' statusId '.$autopart->status_id.' statusName '.$autopart->status->name);
            } else {
                logger('No se actualizo la autoparte '.$mlId);
            }

        } else {
            $storeMl = DB::table('stores_ml')->where('user_id', $request->user_id)->first();
            $response = ApiMl::getItemValues($storeMl->id, $mlId);
            
            if ($response->status == 200) {

                $autopartId = DB::table('autoparts')->insertGetId([
                    'name' => $response->autopart['name'],
                    'description'=> $response->autopart['description'] ? $response->autopart['description'] : null,
                    'category_id' => $response->autopart['category_id'] ? $response->autopart['category_id'] : null,
                    'make_id' => $response->autopart['make_id'],
                    'model_id' => $response->autopart['model_id'],
                    'years' => json_encode($response->autopart['years']),
                    'sale_price' => $response->autopart['sale_price'],
                    'origin_id' => $response->autopart['origin_id'],
                    'status_id' => $response->autopart['status_id'],
                    'ml_id' => $response->autopart['ml_id'],
                    'store_ml_id' => $storeMl->id,
                    'store_id' => $storeMl->store_id,
                    'created_by' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

                if (count($response->autopart['years_ids'])) {
                    $response->autopart['years_ids'] = array_unique($response->autopart['years_ids']);
                    foreach ($response->autopart['years_ids'] as $yearId) {
                        DB::table('autopart_years')->insert([
                            'autopart_id' => $autopartId,
                            'year_id' => $yearId,
                        ]);
                    }
                }

                foreach ($response->autopart['images'] as $key => $img) {
                    $contents = file_get_contents($img['url']);
                    $contentsThumbnail = file_get_contents($img['url_thumbnail']);
                    $name = substr($img['url'], strrpos($img['url'], '/') + 1);
                    Storage::put('autoparts/'.$autopartId.'/images/'.$name, $contents);
                    Storage::put('autoparts/'.$autopartId.'/images/thumbnail_'.$name, $contentsThumbnail);

                    DB::table('autopart_images')->insert([
                        'basename' => $name,
                        'img_ml_id' => $img['id'],
                        'autopart_id' => $autopartId,
                        'order' => $key,
                    ]);
                }

                $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopartId);
                Storage::put('autoparts/'.$autopartId.'/qr/'.$autopartId.'.png', (string) $qr);

                AutopartActivity::create([
                    'activity' => 'Se creo la autoparte en Mercadolibre',
                    'autopart_id' => $autopartId,
                    'user_id' => 1
                ]);

                // $content = "Nueva autoparte en ML: ".$storeMl->name.", ID: ".$mlId." y en Auto Global, ID: ".$autopartId;
                // $user = \App\User::find(1);
                // $user->notify(new AutopartNotification($content));

                logger('Se creo la autoparte '.$mlId);
            } else {
                logger('No se creo la autoparte '.$mlId);
            }
        }
        
        return response()->json(['success' => 'success'], 200);
    }
}
