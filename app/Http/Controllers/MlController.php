<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

use App\Notifications\AutopartNotification;

use App\Models\User;

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

            $channel = '-858634389';
            $content = '*Create token:* '.$storeMl->name;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));
        } else {
            $channel = '-858634389';
            $content = '*Do not create token:* '.$storeMl->name;
            $user = User::find(38);
            $user->notify(new AutopartNotification($channel, $content));
        }

        return $response->status();
    }

    public function notifications (Request $request)
    {
        // $request = (object) [
        //     'topic' => 'items',
        //     'resource' => '/items/MLM2279763980',
        //     'user_id' => 1150852266,
        //     'application_id' => 751467155218399,
        //     'sent' => '2022-01-07T18:55:57.75Z',
        //     'attempts' => 1,
        //     'received' => '2022-01-07T18:55:57.649Z',
        // ];

        $mlId = trim($request->resource, '/items/');

        return DB::table('notifications_ml')->insert([
            'ml_id' => $mlId,
            'topic' => $request->topic,
            'resource' => $request->resource,
            'attempts' => $request->attempts,
            'user_id' => $request->user_id,
            'application_id' => $request->application_id,
            'sent' => $request->sent,
            'received' => $request->received,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
