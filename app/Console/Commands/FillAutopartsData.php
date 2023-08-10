<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use App\Helpers\ApiMl;

class FillAutopartsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fill-autoparts-data {--skip=0} {--limit=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para llenar datos vacíos de autopartes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Obtener los argumentos y opciones pasados al comando
        $skip = $this->option('skip');
        $limit = $this->option('limit');

        $this->fillImagesIdMl($skip,$limit);

        // // Mostrar las opciones al usuario
        // $options = ['Descripcion', 'Lado', 'Posicion', 'Numero_Parte', 'Anios', 'Orden_Anios', 'Imagenes'];
        // $question = new ChoiceQuestion('Elige una opción para editar autopartes:', $options);
        // $question->setErrorMessage('Opción inválida.');

        // $helper = $this->getHelper('question');
        // $selectedOption = $helper->ask($this->input, $this->output, $question);

        // // Ejecutar la función correspondiente según la opción seleccionada
        // switch ($selectedOption) {
        //     case 'Descripcion':
        //         $this->fillDescription($skip,$limit);
        //         break;
        //     case 'Lado':
        //         $this->fillSides($skip,$limit);
        //         break;
        //     case 'Posicion':
        //         $this->fillPosition($skip,$limit);
        //         break;
        //     case 'Numero_Parte':
        //         $this->fillPartNumber($skip,$limit);
        //         break;
        //     case 'Anios':
        //         $this->fillYears($skip,$limit);
        //         break;
        //     case 'Orden_Anios':
        //         $this->orderCompleteYears($skip,$limit);
        //         break;
        //     case 'Imagenes':
        //         $this->fillImagesIdMl($skip,$limit);
        //         break;
        //     default:
        //         $this->info('Opción no reconocida.');
        //         break;
        // }
    }

    // Aquí defines las funciones para cada opción
    private function fillDescription($skip,$limit)
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('description')
        ->whereNotNull('ml_id')
        ->orderBy('id', 'desc')
        ->skip($skip)
        ->take($limit)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            logger('ID: '.$autopart->id);

            if (isset($autopart->store_ml_id) && isset($autopart->ml_id)) {
                try {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $autopart->ml_id);

                    if ($response->status == 200 && isset($response->autopart['description'])) {
                        logger('old_description: '.$autopart->description.' new_description: '.$response->autopart['description']);
                        
                        DB::table('autoparts')
                            ->where('id', $autopart->id)
                            ->update(['description' => $response->autopart['description']]);
                    }
                } catch (\Throwable $th) {
                    logger($th);
                }
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Completar descripción terminado.');
    }

    private function fillSides($skip,$limit)
    {
        $autoparts = DB::table('autoparts')
            ->whereNull('deleted_at')
            ->where('status_id', '!=', 4)
            ->whereNull('side_id')
            ->whereNotNull('ml_id')
            ->orderBy('id', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            logger('ID: '.$autopart->id);

            if (isset($autopart->store_ml_id) && isset($autopart->ml_id)) {
                try {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $autopart->ml_id);
    
                    if ($response->status == 200 && isset($response->autopart['side_id'])) {
                        logger('old_side_id: '.$autopart->side_id.' new_side_id: '.$response->autopart['side_id']);
                        
                        DB::table('autoparts')
                            ->where('id', $autopart->id)
                            ->update(['side_id' => $response->autopart['side_id']]);
                    }
                } catch (\Throwable $th) {
                    logger($th);
                    //throw $th;
                }
            }
            $progressBar->advance();
        }

        // Finaliza la barra de progreso
        $progressBar->finish();

        // Agrega una línea en blanco para un formato limpio en la terminal
        $this->output->writeln('');
        $this->info('Completar lados terminado.');
    }

    private function fillPosition($skip,$limit)
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('position_id')
        ->whereNotNull('ml_id')
        ->orderBy('id', 'desc')
        ->skip($skip)
        ->take($limit)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            logger('ID: '.$autopart->id);

            if (isset($autopart->store_ml_id) && isset($autopart->ml_id)) {
                try {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $autopart->ml_id);

                    if ($response->status == 200 && isset($response->autopart['position_id'])) {
                        logger('old_position_id: '.$autopart->position_id.' new_position_id: '.$response->autopart['position_id']);
                        
                        DB::table('autoparts')
                            ->where('id', $autopart->id)
                            ->update(['position_id' => $response->autopart['position_id']]);
                    }
                } catch (\Throwable $th) {
                    logger($th);
                    //throw $th;
                }
            }
            $progressBar->advance();
        }

        // Finaliza la barra de progreso
        $progressBar->finish();

        // Agrega una línea en blanco para un formato limpio en la terminal
        $this->output->writeln('');
        $this->info('Completar posicion terminado.');
    }

    private function fillPartNumber($skip,$limit)
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('autopart_number')
        ->whereNotNull('ml_id')
        ->orderBy('id', 'desc')
        ->skip($skip)
        ->take($limit)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            logger('ID: '.$autopart->id);

            if (isset($autopart->store_ml_id) && isset($autopart->ml_id)) {
                try {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $autopart->ml_id);

                    if ($response->status == 200 && isset($response->autopart['autopart_number'])) {
                        logger('old_autopart_number: '.$autopart->autopart_number.' new_autopart_number: '.$response->autopart['autopart_number']);
                        
                        DB::table('autoparts')
                            ->where('id', $autopart->id)
                            ->update(['autopart_number' => $response->autopart['autopart_number']]);
                    }
                } catch (\Throwable $th) {
                    logger($th);
                    //throw $th;
                }
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Completar numero de parte terminado.');
    }

    private function fillYears($skip,$limit)
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('years')
        ->orderBy('id', 'desc')
        ->skip($skip)
        ->take($limit)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            logger('ID: '.$autopart->id);

            if (isset($autopart->store_ml_id) && isset($autopart->ml_id)) {
                try {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $autopart->ml_id);

                    if ($response->status == 200 && isset($response->autopart['years'])) {
                        logger('old_years: '.$autopart->years.' new_years: '.$response->autopart['years']);
                        
                        DB::table('autoparts')
                            ->where('id', $autopart->id)
                            ->update(['years' => $response->autopart['years']]);
                    }
                } catch (\Throwable $th) {
                    logger($th);
                }
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Completar años terminado.');
    }

    private function orderCompleteYears($skip,$limit)
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNotNull('years')
        ->orderBy('id', 'desc')
        ->skip($skip)
        ->take($limit)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            logger('ID: '.$autopart->id);
            $autopart->years = json_decode($autopart->years);
            try {
                if (count($autopart->years) > 1) {
                    $years = [];
                    for ($i = min($autopart->years); $i <= max($autopart->years); $i++) {
                        $years[] = (string) $i;
                    }
                    DB::table('autoparts')
                        ->where('id', $autopart->id)
                        ->update(['years' => $years]);
                }
            } catch (\Throwable $th) {
                    logger($th);
                    //throw $th;
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Ordenar y completar años terminado.');
    }

    private function fillImagesIdMl($skip,$limit)
    {
        $lastImageId = DB::table('autopart_images')
            ->select('autopart_id')
            ->latest()
            ->first();

        // $autopartsIds = DB::table('autopart_images')
        //     ->select('autopart_id')
        //     ->distinct()
        //     ->whereNull('img_ml_id')
        //     ->whereNull('deleted_at')
        //     ->pluck('autopart_id')
        //     ->toArray();

        $autoparts = DB::table('autoparts')
            //->whereNull('deleted_at')
            //->where('status_id', '!=', 4)
            //->whereIn('id', $autopartsIds)
            //->whereNotNull('ml_id')
            // ->skip($skip)
            // ->take($limit)
            ->where('id', '>', $lastImageId->autopart_id)
            ->where('store_ml_id', '!=', 8)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $autopart) {
            //logger('ID: '.$autopart->id);
            
            if (isset($autopart->store_ml_id) && isset($autopart->ml_id)) {
                try {
                    $response = ApiMl::getItemValues($autopart->store_ml_id, $autopart->ml_id);

                    if ($response->status == 200 && isset($response->autopart['images'])) {
                        foreach ($response->autopart['images'] as $key => $img) {
                            $contents = file_get_contents($img['url']);
                            $contentsThumbnail = file_get_contents($img['url_thumbnail']);

                            if (!Storage::exists('autoparts/'.$autopart->id.'/images/'.$img['name'])) {
                                Storage::put('autoparts/'.$autopart->id.'/images/'.$img['name'], $contents);
                                Storage::put('autoparts/'.$autopart->id.'/images/thumbnail_'.$img['name'], $contentsThumbnail);
                            }
                            
    
                            DB::table('autopart_images')->insert([
                                'basename' => $img['name'],
                                'img_ml_id' => $img['id'],
                                'order' => $key,
                                'autopart_id' => $autopart->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                            
                        }
                    } else {
                        // search images in bucket
                        $images = DB::connection('export')
                            ->table('autopart_images')
                            ->where('autopart_id', $autopart->id)
                            ->get();

                        foreach ($images as $key => $img) {
                            $image = Storage::disk('export')->get('autoparts/'.$autopart->id.'/images/'.$img->basename);

                            if (!Storage::exists('autoparts/'.$autopart->id.'/images/'.$img->basename)) {
                                Storage::put('autoparts/'.$autopart->id.'/images/'.$img->basename, $image);
                                Storage::put('autoparts/'.$autopart->id.'/images/thumbnail_'.$img->basename, $image);
                            }

                            DB::table('autopart_images')->insert([
                                'basename' => $img->basename,
                                'order' => $img->order,
                                'autopart_id' => $autopart->id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now()
                            ]);
                        }
                    }
                } catch (\Throwable $th) {
                    logger($th);
                    //throw $th;
                }
            } else {
                // search images in bucket
                $images = DB::connection('export')
                    ->table('autopart_images')
                    ->where('autopart_id', $autopart->id)
                    ->get();

                foreach ($images as $key => $img) {
                    $image = Storage::disk('export')->get('autoparts/'.$autopart->id.'/images/'.$img->basename);

                    if (!Storage::exists('autoparts/'.$autopart->id.'/images/'.$img->basename)) {
                        Storage::put('autoparts/'.$autopart->id.'/images/'.$img->basename, $image);
                        Storage::put('autoparts/'.$autopart->id.'/images/thumbnail_'.$img->basename, $image);
                    }

                    DB::table('autopart_images')->insert([
                        'basename' => $img->basename,
                        'order' => $img->order,
                        'autopart_id' => $autopart->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                }
            }
            // create qr
            $qr = QrCode::format('png')->size(200)->margin(1)->generate($autopart->id);
            Storage::put('autoparts/'.$autopart->id.'/qr/'.$autopart->id.'.png', (string) $qr);

            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Crear imagenes completado.');
    }
}
