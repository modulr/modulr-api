<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TeikerController extends Controller
{
    public function quotation (Request $request)
    {

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'content-type' => 'application/json',
            // Si necesitas otras cabeceras, añádelas aquí
        ])->post('https://dev.tecc.app/teiker_v2/public/api/CotizarEnvio', [
            'User' => '3994967872',
            'Password' => '2UObs5rq9KYrgbNwdD',
            'CPOrigen' => '64000',
            'CPDestino' => $request->cp,
            'Paquetes' => [
                [
                    'TipoPaquete' => '1',
                    'Contenido' => 'Contenido',
                    'InfoAdicional' => '',
                    'Dimensiones' => [
                        'Unidad' => 'CM',
                        'Largo' => '200',
                        'Alto' => '50',
                        'Ancho' => '20',
                    ],
                    'Peso' => [
                        'Unidad' => 'KG',
                        'Peso' => '20',
                    ],
                ],
            ],
        ]);

        if ($response->ok()) {
            logger(["Response" => $response->object()]);
            return $response->object();
        } else {
            logger(["Response" => $response->status()]);
            return false;
        }

    }
}
