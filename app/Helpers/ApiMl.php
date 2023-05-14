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
        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mercadolibre.com']);

        try {
            $response = $client->request('POST', 'oauth/token', [
                'headers' => [
                    'Accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $store->client_id,
                    'client_secret' => $store->client_secret,
                    'refresh_token' => $store->token
                ]
            ]);

            $res = json_decode($response->getBody());

            // Update token
            $store = DB::table('stores_ml')->where('id', $store->id)->update([
                'token' => $res->refresh_token,
                'access_token' => $res->access_token
            ]);

            logger('Refresh token');
            //logger(['code' => $response->getStatusCode(), 'item' => json_decode($response->getBody()), 'update' => $update]);
            return $store;
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            logger('Do not refresh token');
            // Refresh Token
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

        } while ( count($response['results']) > 0 );

        return $ids;
    }    

    public static function getItem($conexion, $id)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$conexion->access_token,
        ])->get('https://api.mercadolibre.com/items', [
            'ids' => $id,
        ]);
    }

    public static function getItemDescription($id, $conexion)
    {
        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mercadolibre.com']);

        try {
            $response = $client->request('GET', 'items/'.$id.'/description', [
                'headers' => [
                    'Authorization' => 'Bearer '.$conexion->access_token,
                ]
            ]);
            $description = json_decode($response->getBody());
            logger('Se obtuvo la descripción de mercadolibre '.$id);
            return $description->plain_text;
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            logger($e->getResponse()->getBody());
            logger('No se obtuvo la descripción de mercadolibre '.$id);
            return false;
        }

    }
}