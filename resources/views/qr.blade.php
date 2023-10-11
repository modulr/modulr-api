<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QR</title>
        <style media="all">
        @page {
            size: 4in 2.4in;
            margin: 0;
        }
        @page :first {
            margin: 0;
        }
        html, body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12pt;
            margin: 0;
            padding: 0;
            border: 0;
        }
        table {
            border: none;
            border-collapse: collapse;
            border-spacing: 0;
        }
        .title {
            color: #333;
            text-transform: uppercase;
            font-size: 4pt;
            display: block;
        }
        .qr img {
            height: 160px;
            width: 160px;
        }
        .code {
            font-size: 34pt;
            margin-left: 18px;
        }
        .location {
            font-size: 10pt;
        }
        .date {
            font-size: 6pt;
        }
        </style>
    </head>
    <!-- <body onload="window.print(); setTimeout(function () { window.close(); }, 500);"> -->
    <body onload="window.print();">
    <body>
        <table>
            <tr>
                <td><div class="code">{{ $autopart->id }}</div></td>
            </tr>
            <tr>
                <td class="qr">
                    <img src="{{ asset($autopart->qr) }}">
                </td>
                <td>
                    <table>
                        @if ($autopart->ml_id)
                        <tr>
                            <td>
                                <span class="title">ML ID</span>
                                {{ $autopart->ml_id }}
                            </td>
                        </tr>
                        @endif
                        @if ($autopart->make)
                        <tr>
                            <td>
                                <span class="title">Marca</span>
                                {{ $autopart->make->name }}
                            </td>
                        </tr>
                        @endif
                        @if ($autopart->model)
                        <tr>
                            <td>
                                <span class="title">Modelo</span>
                                {{ $autopart->model->name }}
                            </td>
                        </tr>
                        @endif
                        @if ($autopart->yearsRange)
                        <tr>
                            <td>
                                <span class="title">Años</span>
                                {{ $autopart->yearsRange }}
                            </td>
                        </tr>
                        @endif
                        @if ($autopart->location)
                        <tr>
                            <td class="location">
                                <span class="title">Ubicación</span>
                                {{ $autopart->location->name }}
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td class="date">
                                <span class="title">Fecha</span>
                                {{ $autopart->created_at }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
