<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use App\Helpers\ApiMl;

use App\Notifications\AutopartNotification;

use App\Models\User;
use App\Models\Autopart;
use App\Models\AutopartActivity;
use App\Models\AutopartImage;

class ProcessNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para procesar las notificaciones de mercado libre';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notifications = DB::table('notifications_ml')->where('done', false)->limit(10)->get();

        $duplicates = $notifications->duplicates('ml_id');

        foreach ($notifications as $notification) {

            $key = $duplicates->search(function($item) use($notification) {
                return $item == $notification->ml_id;
            });

            if ($key) {
                DB::table('notifications_ml')->where('id', $notification->id)->update(['done' => true, 'updated_at' => Carbon::now()]);
                $duplicates->pull($key);
            } else {
                $autopart = Autopart::withTrashed()->where('ml_id', $notification->ml_id)->first();
        
                if ($autopart) {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $notification->ml_id);
                    logger(["Autopart ProcessNotification"=>$response->autopart]);
                    $autopart->make_id = $response->autopart['make_id'];
                    $autopart->model_id = $response->autopart['model_id'];
                    $autopart->position_id = $response->autopart['position_id'];
                    $autopart->side_id = $response->autopart['side_id'];
                    
                    if ($response->status == 200) {
                        $change = null;
    
                        $newStatusId = $autopart->status_id;
                        $autopart->ml_status = $response->autopart['status'];
    
                        if ($response->autopart['status'] == 'active') {
                            $newStatusId = 1; // Disponible
                        }
    
                        if ($autopart->status_id == 1 && (!isset($autopart->make_id) || !isset($autopart->model_id))) {
                            $newStatusId = 5; // Incompleto
                        }
    
                        if (($autopart->status_id == 1 || $autopart->status_id == 2 || $autopart->status_id == 5) && ($response->autopart['status'] == 'paused' || $response->autopart['status'] == 'closed')) {
                            $dateCreated = Carbon::parse($response->autopart['date_created']);
                            $minutesAgo = Carbon::now()->subMinutes(15);
    
                            if ($dateCreated->greaterThanOrEqualTo($minutesAgo)) {
                                $newStatusId = 2; //No Disponible
                            } else {
                                $newStatusId = 4; //Vendido
                            }
                        }
    
                        if ($autopart->status_id == 3 && $response->autopart['status'] == 'closed') {
                            $newStatusId = 4; // Vendido
                        }

                        if($autopart->moderation_active){
                            $newStatusId = 1;
                            $autopart->status_id = 1;
                            ApiMl::updateAutopart($autopart);
                        }
        
                        if($autopart->status_id !== $newStatusId){
        
                            $statuses = [
                                1 => "Disponible",
                                2 => "No Disponible",
                                3 => "Separado",
                                4 => "Vendido",
                                5 => "Incompleto",
                                6 => "Sin Mercado Libre"
                            ];
                            
                            if ($newStatusId !== 4) {
                                $change = "🚦 Estatus: ".$statuses[$autopart->status_id]." ⏩ ".$statuses[$newStatusId]."\n";
                            }
                            
                            $autopart->status_id = $newStatusId;
        
                            // AUTOPARTE VENDIDA
                            if($autopart->status_id == 4){

                                $autopart->save();
                                
                                AutopartActivity::create([
                                    'activity' => 'Autoparte vendida en Mercadolibre',
                                    'autopart_id' => $autopart->id,
                                    'user_id' => 38
                                ]);
                                
                                //$channel = env('TELEGRAM_CHAT_SALES_ID');
                                $channel = $autopart->store->telegram;
                                $content = "💰*¡Autoparte Vendida!*\n*".$autopart->storeMl->name."*\n".$autopart->ml_id."\nID: ".$autopart->id."\n".$response->autopart['name']."\nPrecio: $".number_format($response->autopart['sale_price']);
                                $button = $autopart->id;
                                $user = User::find(38);
                                $user->notify(new AutopartNotification($channel, $content, $button)); 
                            }
                        }
        
                        if ($autopart->sale_price !== $response->autopart['sale_price']) {
        
                            if ($response->autopart['sale_price'] > $autopart->sale_price) {
                                $change = $change . "💵 Precio: $".number_format($autopart->sale_price)." ⏫ $".number_format($response->autopart['sale_price'])."\n";
                            } else if ($response->autopart['sale_price'] < $autopart->sale_price) {
                                $change = $change . "💵 Precio: $".number_format($autopart->sale_price)." ⏬ $".number_format($response->autopart['sale_price'])."\n";
                            }
        
                            $autopart->sale_price = $response->autopart['sale_price'];
                        }
        
                        if(strcmp($autopart->name, $response->autopart['name']) !== 0){
                            $change = $change . "🖋 Título actualizado\n".$autopart->name."\n🔽🔽🔽\n".$response->autopart['name']."\n";
                            $autopart->name = $response->autopart['name'];
                        }
        
                        if(strcmp($autopart->description, $response->autopart['description']) !== 0){
                            $change = $change . "🖋 Descripción actualizada\n".$autopart->description."\n🔽🔽🔽\n".$response->autopart['description']."\n";
                            $autopart->description = $response->autopart['description'];
                        }
    
                        $autopartImagesArray = $autopart->images->toArray();
                        $autopartImageIds = array_column($autopartImagesArray, 'img_ml_id');
    
                        // Obtener los ids de las imágenes en la respuesta del API
                        $responseImageIds = array_column($response->autopart['images'], 'id');
    
                        //Encontrar los ids que no se moverán
                        $imagesExist = array_intersect($responseImageIds, $autopartImageIds);
    
                        // Encontrar los ids que están en $autopartImageIds pero no en $responseImageIds
                        $imagesToDeleteIds = array_diff($autopartImageIds, $responseImageIds);
    
                        // Encontrar los ids que están en $responseImageIds pero no en $autopartImageIds
                        $imagesToCreateIds = array_diff($responseImageIds, $autopartImageIds);
    
                        AutopartImage::where(function($query) use ($imagesToDeleteIds, $autopart) {
                            $query->whereIn('img_ml_id', $imagesToDeleteIds)
                                ->orWhereNull('img_ml_id')
                                ->where('autopart_id', $autopart->id);
                        })->delete();
    
                        //Borrar del bucket
                        foreach ($autopart->images as $key => $image) {
                            if (in_array($image->img_ml_id, $imagesToDeleteIds)) {
                                if (!Storage::exists('autoparts/'.$autopart->id.'/images/'.$image->name)){
                                    Storage::delete('autoparts/'.$autopart->id.'/images/'.$image->name);
                                }
                                
                                if (!Storage::exists('autoparts/'.$autopart->id.'/images/thumbnail_'.$image->name)){
                                    Storage::delete('autoparts/'.$autopart->id.'/images/thumbnail_'.$image->name);
                                }
                            }
                        }
    
                        // Agregar las imágenes que están en $imagesToCreateIds a la base de datos
                        foreach ($response->autopart['images'] as $key => $img) {
                            if (in_array($img['id'], $imagesToCreateIds)) {
                                $contents = file_get_contents($img['url']);
                                $contentsThumbnail = file_get_contents($img['url_thumbnail']);
    
                                if (!Storage::exists('autoparts/'.$autopart->id.'/images/'.$img['name'])){
                                    Storage::put('autoparts/'.$autopart->id.'/images/'.$img['name'], $contents);
                                }
    
                                if (!Storage::exists('autoparts/'.$autopart->id.'/images/thumbnail_'.$img['name'])){
                                    Storage::put('autoparts/'.$autopart->id.'/images/thumbnail_'.$img['name'], $contentsThumbnail);
                                }
    
                                DB::table('autopart_images')->insert([
                                    'basename' => $img['name'],
                                    'img_ml_id' => $img['id'],
                                    'autopart_id' => $autopart->id,
                                    'order' => $key,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ]);
                            }else if(in_array($img['id'], $imagesExist)){
                                DB::table('autopart_images')->where('img_ml_id', $img['id'])->update([
                                    'order' => $key,
                                    'updated_at' => Carbon::now()
                                ]);
                            }
                        }
    
                        //$imagesFinal = DB::table('autopart_images')->where('autopart_id', $autopart->id)->orderBy('order','asc')->get();
    
    
                        if ($change) {
                            $autopart->save();
                            
                            AutopartActivity::create([
                                'activity' => "Se actualizó la autoparte en Mercadolibre\n".$change,
                                'autopart_id' => $autopart->id,
                                'user_id' => 38
                            ]);
                            
                            //$channel = env('TELEGRAM_CHAT_UPDATES_ID');
                            $channel = $autopart->store->telegram;
                            $content = $change."*".$autopart->storeMl->name."*\n".$autopart->ml_id."\nID: ".$autopart->id;
                            $button = $autopart->id;
                            $user = User::find(38);
                            $user->notify(new AutopartNotification($channel, $content, $button));
                        }
        
                    } else {
                        $channel = env('TELEGRAM_CHAT_LOG');
                        $content = '*No se actualizó la autoparte:* '.$notification->ml_id;
                        $user = User::find(38);
                        $user->notify(new AutopartNotification($channel, $content));
                    }
        
                } else {
                    $storeMl = DB::table('stores_ml')
                        ->join('stores', 'stores.id', '=', 'stores_ml.store_id')
                        ->select('stores_ml.*', 'stores.telegram as telegram')
                        ->where('stores_ml.user_id', $notification->user_id)->first();

                    $response = ApiMl::getItemValues($storeMl->id, $notification->ml_id);
                    logger(["Model"=>$response->autopart['model_id']]);
                    if ($response->status == 200 && $response->autopart['status'] == 'active') {
        
                        $autopartId = DB::table('autoparts')->insertGetId([
                            'name' => $response->autopart['name'],
                            'autopart_number' => $response->autopart['autopart_number'],
                            'description'=> $response->autopart['description'],
                            'category_id' => $response->autopart['category_id'],
                            'position_id' => $response->autopart['position_id'],
                            'side_id' => $response->autopart['side_id'],
                            'make_id' => $response->autopart['make_id'],
                            'model_id' => $response->autopart['model_id'],
                            'years' => json_encode($response->autopart['years']),
                            'sale_price' => $response->autopart['sale_price'],
                            'origin_id' => $response->autopart['origin_id'],
                            'condition_id' => $response->autopart['condition_id'],
                            'status_id' => $response->autopart['status_id'],
                            'ml_id' => $response->autopart['ml_id'],
                            'store_ml_id' => $storeMl->id,
                            'store_id' => $storeMl->store_id,
                            'created_by' => 1,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ]);
        
                        foreach ($response->autopart['images'] as $key => $img) {
                            $contents = file_get_contents($img['url']);
                            $contentsThumbnail = file_get_contents($img['url_thumbnail']);
                            Storage::put('autoparts/'.$autopartId.'/images/'.$img['name'], $contents);
                            Storage::put('autoparts/'.$autopartId.'/images/thumbnail_'.$img['name'], $contentsThumbnail);
        
                            DB::table('autopart_images')->insert([
                                'basename' => $img['name'],
                                'img_ml_id' => $img['id'],
                                'autopart_id' => $autopartId,
                                'order' => $key,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                        }
        
                        $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopartId);
                        Storage::put('autoparts/'.$autopartId.'/qr/'.$autopartId.'.png', (string) $qr);
        
                        AutopartActivity::create([
                            'activity' => 'Se creo la autoparte en Mercadolibre',
                            'autopart_id' => $autopartId,
                            'user_id' => 38
                        ]);
        
                        //$channel = env('TELEGRAM_CHAT_NEWS_ID');
                        $channel = $storeMl->telegram;
                        $content = "✅ *¡Nueva autoparte!*\n*".$storeMl->name."*\n".$notification->ml_id."\nID: ".$autopartId."\n".$response->autopart['name']."\nPrecio: $".number_format($response->autopart['sale_price']);
                        $button = $autopartId;
                        $user = User::find(38);
                        $user->notify(new AutopartNotification($channel, $content, $button));
        
                    } else {
                        $channel = env('TELEGRAM_CHAT_LOG');
                        $content = '*No se creo la autoparte:* '.$notification->ml_id;
                        $user = User::find(38);
                        $user->notify(new AutopartNotification($channel, $content));
                    }
                }
    
                DB::table('notifications_ml')->where('id', $notification->id)->update(['done' => true, 'updated_at' => Carbon::now()]);
            }
        }
    }
}
