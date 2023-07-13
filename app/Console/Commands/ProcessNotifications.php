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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notifications = DB::table('notifications_ml')->where('done', false)->limit(10)->get();

        foreach ($notifications as $notification) {
            
            $autopart = Autopart::withTrashed()->where('ml_id', $notification->ml_id)->first();
    
            if ($autopart) {
                $response = ApiMl::getItemValues($autopart->store_ml_id, $notification->ml_id);
                
                if ($response->status == 200) {
                    $change = null;

                    $newStatusId = $autopart->status_id;

                    if ($autopart->status_id == 1 && (!isset($autopart->make_id) || !isset($autopart->model_id))) {
                        $newStatusId = 5; // Incompleto
                    }

                    if (($autopart->status_id == 1 || $autopart->status_id == 2 || $autopart->status_id == 5) && ($response->autopart['status'] == 'paused' || $response->autopart['status'] == 'closed')) {
                        $newStatusId = 4; // Vendido
                    }

                    if ($autopart->status_id == 3 && $response->autopart['status'] == 'closed') {
                        $newStatusId = 4; // Vendido
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
    
                        $change = "🚦 Estatus actualizado: ".$statuses[$autopart->status_id]." ⏩ ".$statuses[$newStatusId]."\n";

                        $autopart->status_id = $newStatusId;
    
                        // AUTOPARTE VENDIDA
                        if($autopart->status_id == 4){
                            AutopartActivity::create([
                                'activity' => 'Autoparte vendida en Mercadolibre',
                                'autopart_id' => $autopart->id,
                                'user_id' => 38
                            ]);
                            
                            $channel = env('TELEGRAM_CHAT_SALES_ID');
                            $content = "💰*¡Autoparte Vendida!*\n*".$autopart->storeMl->name."*\n".$autopart->ml_id."\nID: ".$autopart->id."\n".$response->autopart['name']."\nPrecio: $".number_format($response->autopart['sale_price']);
                            $button = $autopart->id;
                            $user = User::find(38);
                            $user->notify(new AutopartNotification($channel, $content, $button)); 
                        }
                    }
    
                    if ($autopart->sale_price !== $response->autopart['sale_price']) {
    
                        if ($response->autopart['sale_price'] > $autopart->sale_price) {
                            $change = $change . "💵 Aumento de Precio: $".number_format($autopart->sale_price)." ⏫ ".number_format($response->autopart['sale_price']) ;
                        } else if ($response->autopart['sale_price'] < $autopart->sale_price) {
                            $change = $change . "💵 Reducción de Precio: $".number_format($autopart->sale_price)." ⏬ ".number_format($response->autopart['sale_price']) ;
                        }
    
                        $autopart->sale_price = $response->autopart['sale_price'];
                    }
    
                    if($autopart->name !== $response->autopart['name']){
                        $change = $change . "🖋 Título actualizado\n".$autopart->name."\n🔽🔽🔽\n".$response->autopart['name']."\n";
                        $autopart->name = $response->autopart['name'];
                    }
    
                    // if($autopart->description !== $response->autopart['description']){
                    //     $change = $change."🖋 Descripción actualizada\n".$autopart->description."\n🔽🔽🔽\n".$response->autopart['description']."\n";
                    //     $autopart->description = $response->autopart['description'];
                    // }
    
                    if ($change) {
                        $autopart->save();
                        
                        AutopartActivity::create([
                            'activity' => "Se actualizó la autoparte en Mercadolibre\n".$change,
                            'autopart_id' => $autopart->id,
                            'user_id' => 38
                        ]);
                        
                        $channel = env('TELEGRAM_CHAT_UPDATES_ID');
                        $content = "*¡Autoparte Actualizada!*\n*".$autopart->storeMl->name."*\n".$autopart->ml_id."\nID: ".$autopart->id."\n".$change;
                        $button = $autopart->id;
                        $user = User::find(38);
                        $user->notify(new AutopartNotification($channel, $content, $button));
                    }
    
                } else {
                    $channel = '-858634389';
                    $content = '*No se actualizó la autoparte:* '.$notification->ml_id;
                    $user = User::find(38);
                    $user->notify(new AutopartNotification($channel, $content));
                }
    
            } else {
                $storeMl = DB::table('stores_ml')->where('user_id', $notification->user_id)->first();
                $response = ApiMl::getItemValues($storeMl->id, $notification->ml_id);
                
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
                        $name = substr($img['url'], strrpos($img['url'], '/') + 1);
                        Storage::put('autoparts/'.$autopartId.'/images/'.$name, $contents);
                        Storage::put('autoparts/'.$autopartId.'/images/thumbnail_'.$name, $contentsThumbnail);
    
                        DB::table('autopart_images')->insert([
                            'basename' => $name,
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
    
                    $channel = env('TELEGRAM_CHAT_NEWS_ID');
                    $content = "✅ *¡Nueva autoparte!*\n*".$storeMl->name."*\n".$notification->ml_id."\nID: ".$autopartId."\n".$response->autopart['name']."\nPrecio: $".number_format($response->autopart['sale_price']);
                    $button = $autopartId;
                    $user = User::find(38);
                    $user->notify(new AutopartNotification($channel, $content, $button));
    
                } else {
                    $channel = '-858634389';
                    $content = '*No se creo la autoparte:* '.$notification->ml_id;
                    $user = User::find(38);
                    $user->notify(new AutopartNotification($channel, $content));
                }
            }

            DB::table('notifications_ml')->where('id', $notification->id)->update(['done' => true]);

        }

        //return response()->json(['success' => 'success'], 200);
    }
}