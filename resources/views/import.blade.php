<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoGlobal Import</title>
</head>
<body>
    
    @if (isset($error))
        <h2>{{$error}}</h3>
    @endif

    @if (isset($save))
        <h1>{{ $save }}</h1>
    @endif


    @if (isset($id))
        <h1>Importar tienda {{$id}}</h1>

        <a href="/import/getIds/{{$id}}">getIds</a>
        
        <br>

        <a href="/import/getNewIds/{{$id}}">getNewIds</a>

        <br>
    @endif

    @if (isset($ids))

        <a href="/import/import/{{$id}}">import</a>

        {{ dd($ids) }}

    @endif

    @if (isset($autoparts))

        <a href="/import/save/{{$id}}">save</a>

        <table>
            <tr>
                <th>ID</th>
                <th>ML ID</th>
                <th>Status</th>
                <th>Import</th>
                <th>Autopart</th>
            </tr>
            @foreach ($autoparts as $autopart)
            <tr>
                <td>{{$autopart->id}}</td>
                <td>{{$autopart->ml_id}}</td>
                <td>{{$autopart->status}}</td>
                <td>{{$autopart->import}}</td>
                <td>{{$autopart->autopart}}</td>
            </tr>
            @endforeach
        </table>

        {{ dd($autoparts) }}

    @endif
</body>
</html>