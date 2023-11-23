<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AutopartStoreMl;
use App\Models\AutopartStore;
use App\Models\Autopart;

class StoresMlController extends Controller
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
            return DB::table('stores_ml')
                ->select('stores_ml.id', 'stores_ml.name', 'stores_ml.store_id', 'stores_ml.user_id','stores_ml.client_id', 'st.name as store_name')
                ->leftJoin('stores as st', 'stores_ml.store_id', '=', 'st.id')
                ->whereNull('stores_ml.deleted_at')
                ->get();
        
        } else {
            return DB::table('stores_ml')
                ->select('stores_ml.id', 'stores_ml.name', 'stores_ml.store_id', 'stores_ml.user_id','stores_ml.client_id', 'st.name as store_name')
                ->leftJoin('stores as st', 'stores_ml.store_id', '=', 'stores.id')
                ->whereNull('stores_ml.deleted_at')
                ->where('stores_ml.store_id', $user->store_id)
                ->get();
        }
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'user_id' => 'required'
        ]);

        logger(["REQUEST"=>$request]);

        $user = $request->user();

        $superadmin = false;
        if (count($user->roles) > 0) {
            if ($user->roles[0]->role_id == 1) {
                $superadmin = true;
            }
        }

        if ($superadmin) {
            $storeMl = AutopartStoreMl::create([
                'name' => $request->name,
                'store_id' => $request->store['id'],
                'user_id' => $request->user_id,
                'client_id' => "4153017922311053",
                'client_secret' => "0",
                'token' => "0",
                'access_token' => "0",
                'redirect_uri' => "https://api.autoglobal.mx/api/ml/auth",
                'created_by' => $request->user()->id,
            ]);
        } else {
            $storeMl = AutopartStoreMl::create([
                'name' => $request->name,
                'store_id' => $request->user()->store_id,
                'user_id' => $request->user_id,
                'client_id' => "4153017922311053",
                'client_secret' => "0",
                'token' => "0",
                'access_token' => "0",
                'redirect_uri' => "https://api.autoglobal.mx/api/ml/auth",
                'created_by' => $request->user()->id,
            ]);
        }

        $store = AutopartStore::find($storeMl->store_id);
        $storeMl->store_name = $store_name;
        return $storeMl;

    }

    public function destroy($id)
    {
        $storeMl = AutopartStoreMl::find($id);
        $autoparts = Autopart::where('store_ml_id', $storeMl->id)
            ->where('status_id', 1)
            ->get();

        if ($autoparts->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar una tienda en uso.'], 422);
        } else {
            return AutopartStoreMl::destroy($id);
        }
    }

    public function update (Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string'
        ]);

        $storeMl = AutopartStoreMl::find($request->id);
        $store = AutopartStore::find($storeMl->store_id);
        $storeMl->name = $request->name;
        $storeMl->save();

        $storeMl->store_name =$store->name;

        return $storeMl;
    }
}
