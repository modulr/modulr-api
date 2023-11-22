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
                <td><div class="code">{{ $location->id }}</div></td>
            </tr>
            <tr>
                <td class="qr">
                    <img src="{{ asset($location->qr) }}">
                </td>
                <td>
                    <table>
                        @if ($location->name)
                        <tr>
                            <td>
                                <span class="title">Ubicación</span>
                                {{ $location->name }}
                            </td>
                        </tr>
                        @endif
                        @if ($location->store)
                        <tr>
                            <td class="location">
                                <span class="title">Empresa</span>
                                {{ $location->store->name }}
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td class="date">
                                <span class="title">Fecha</span>
                                {{ $location->created_at }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
