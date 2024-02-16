<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Number;

class OrderController extends Controller
{
    public function getOrders()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer key_vd7ajkrnkt7LdKgmX3IOeqI',
            'Accept-Language' => 'es',
            'content-type' => 'application/json',
            'accept' => 'application/vnd.conekta-v2.1.0+json',
        ])->get('https://api.conekta.io/orders?limit=20');
        
        return $response->json();
    }

    public function getOrder(Request $request)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer key_vd7ajkrnkt7LdKgmX3IOeqI',
            'Accept-Language' => 'es',
            'content-type' => 'application/json',
            'accept' => 'application/vnd.conekta-v2.1.0+json',
        ])->get('https://api.conekta.io/orders/'.$request->id);
        
        return $response->json();
    }

    public function createOrder ()
    {

        $lineItems = [];
        $lineItems[0] = ['name' => 'Motor Sonic 2015', 'quantity' => 1, 'unit_price' => 20000];
        $lineItems[1] = ['name' => 'Puerta Sonic 2015', 'quantity' => 1, 'unit_price' => 2000];


        $charges[0] = ["payment_method" => ["type" => "card"]]; //card, spei, cash


        if ($charges[0]["payment_method"]["type"] == "card") {
            // $token = Http::withHeaders([
            //     'Authorization' => 'Bearer key_vd7ajkrnkt7LdKgmX3IOeqI',
            //     'Accept-Language' => 'es',
            //     'content-type' => 'application/json',
            //     'accept' => 'application/vnd.conekta-v2.1.0+json',
            // ])->post('https://api.conekta.io/tokens', [
            //     "card" => [
            //         "cvc" => "123",
            //         "exp_month" => "12",
            //         "exp_year" => "26",
            //         "name" => "Alfredo",
            //         "number" => "424242424242424"
            //     ]
            // ]);
            //return $token;
            $charges[0]['payment_method']['token_id'] = 'tok_test_visa_4242';
        }


        $response = Http::withHeaders([
            'Authorization' => 'Bearer key_vd7ajkrnkt7LdKgmX3IOeqI',
            'Accept-Language' => 'es',
            'content-type' => 'application/json',
            'accept' => 'application/vnd.conekta-v2.1.0+json',
        ])->post('https://api.conekta.io/orders', [
            "customer_info" => ["name" => "Alfredo", "email" => "alfredo@modulr.io", "phone" => "5522997233"],
            "charges" => $charges,
            "pre_authorize" => false,
            "currency" => "MXN",
            "line_items" => $lineItems
        ]);

        $order = $response->json();

        if ($charges[0]["payment_method"]["type"] != 'card') {
            $expiresAt = Carbon::parse($order['charges']['data'][0]['payment_method']['expires_at']);
            $amount = Number::format($order['amount']);

            $order['charges']['data'][0]['payment_method']['expires_at'] = $expiresAt->toDayDateTimeString();
            $order['amount'] = $amount;
        }

        if ($order['charges']['data'][0]['payment_method']['type'] == 'oxxo') {
            return view('oxxo', ['order' => $order]);
        } elseif ($order['charges']['data'][0]['payment_method']['type'] == 'spei') {
            return view('spei', ['order' => $order]);
        } else {
            return $order;
        }

    }

    public function checkout()
    {
        $customer = Http::withHeaders([
            'Authorization' => 'Bearer key_vd7ajkrnkt7LdKgmX3IOeqI',
            'Accept-Language' => 'es',
            'content-type' => 'application/json',
            'accept' => 'application/vnd.conekta-v2.1.0+json',
        ])->post('https://api.conekta.io/customers', [
            "name" => "Alfredo",
            "email" => "alfredobarronc@gmail.com",
            "phone" => "5522997233"
        ]);

        //return $customer->json();

        $lineItems = [];
        $lineItems[0] = ['name' => 'Motor Sonic 2015', 'quantity' => 1, 'unit_price' => 2000000];
        $lineItems[1] = ['name' => 'Puerta Sonic 2015', 'quantity' => 1, 'unit_price' => 200000];

        $order = Http::withHeaders([
            'Authorization' => 'Bearer key_vd7ajkrnkt7LdKgmX3IOeqI',
            'Accept-Language' => 'es',
            'content-type' => 'application/json',
            'accept' => 'application/vnd.conekta-v2.1.0+json',
        ])->post('https://api.conekta.io/orders', [
            "currency" => "MXN",
            "checkout" => [
                "type" => "Integration",
                "allowed_payment_methods" => ['cash', 'card', 'bank_transfer']
            ],
            "customer_info" => ["customer_id" => $customer['id']],
            "line_items" => $lineItems,
        ]);

        //return $order->json();

        return view('checkout', ['checkout_id' => $order['checkout']['id']]);
    }

}
