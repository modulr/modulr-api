<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QR</title>
        <style media="all">
        @page {
            size: 2.4in 4in;
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
            width: 100%;
        }
        .qr img {
            height: 200px;
            width: 200px;
        }
        .location {
            font-size: 30pt;
        }
        .center {
            text-align: center;
        }
        </style>
    </head>
    <!-- <body onload="window.print(); setTimeout(function () { window.close(); }, 500);"> -->
    <body onload="window.print();">
    <body>
        <table>
            <tr>
                <td class="qr center">
                    <img src="{{ asset($location->qr) }}">
                </td>
            </tr>
            @if ($location->name)
            <tr>
                <td class="center">
                    <div class="location center">{{ $location->name }}</div>
                </td>
            </tr>
            @endif
            @if ($location->store)
            <tr>
                <td class="center">
                    {{ $location->store->name }}
                </td>
            </tr>
            @endif
        </table>
    </body>
</html>
