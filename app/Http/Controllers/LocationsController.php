<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AutopartListLocation;
use QrCode;
use Illuminate\Support\Facades\Storage;

class LocationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $superadmin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superadmin = true;
            }
        }

        if ($superadmin) {
            return DB::table('autopart_list_locations')
                ->select('id', 'name', 'stock', 'store_id')
                ->whereNull('deleted_at')
                ->get();
        } else {
            return DB::table('autopart_list_locations')
                ->select('id', 'name', 'stock', 'store_id')
                ->whereNull('deleted_at')
                ->where('store_id', $user->store_id)
                ->get();
        }
    }

    public function show (Request $request)
    {
        return AutopartListLocation::find($request->id);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $superadmin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superadmin = true;
            }
        }

        if ($superadmin) {
            $location = AutopartListLocation::create([
                'name' => $request->name,
                'stock' => 0,
                'store_id' => $request->store['id'],
                'created_by' => $request->user()->id,
            ]);
        } else {
            $location = AutopartListLocation::create([
                'name' => $request->name,
                'stock' => 0,
                'store_id' => $request->user()->store_id,
                'created_by' => $request->user()->id,
            ]);
        }

        $generateName = 'location-'.$location->id;
        $qr = QrCode::format('png')->size(200)->margin(1)->generate($generateName);
        Storage::put('locations/'.$location->store_id.'/'.$location->id.'.png', (string) $qr);

        return $location;

    }

    public function destroy($id)
    {
        $location = AutopartListLocation::find($id);

        if ($location->stock > 0) {
            return response()->json(['error' => 'No se puede eliminar ubicacion con stock.'], 422);
        } else {
            return AutopartListLocation::destroy($id);
        }
    }

    public function update (Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string'
        ]);

        $location = AutopartListLocation::find($request->id);
        $location->name = $request->name;
        $location->save();

        if (!Storage::exists('locations/'.$location->store_id.'/'.$location->id.'.png')) {
            $qr = QrCode::format('png')->size(200)->margin(1)->generate('location-'.$location->id);
            Storage::put('locations/'.$location->store_id.'/'.$location->id.'.png', (string) $qr);
        }

        return $location;
    }

    public function qr (Request $request)
    {
        $cleanedId = str_replace('location-', '', $request->id);

        $location = AutopartListLocation::with(['store'])->find($cleanedId);

        if (!Storage::exists('locations/'.$location->store_id.'/'.$location->id.'.png')) {
            $qr = QrCode::format('png')->size(200)->margin(1)->generate('location-'.$location->id);
            Storage::put('locations/'.$location->store_id.'/'.$location->id.'.png', (string) $qr);
        }

        return view('qr_location', ['location' => $location]);
    }
}
