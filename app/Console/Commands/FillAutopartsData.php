<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Helpers\ApiMl;

class FillAutopartsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fill-autoparts-data';

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
        // Mostrar las opciones al usuario
        $options = ['Descripcion','Lado', 'Posicion', 'Numero_Parte','Anios','Imagenes','Orden_Anios'];
        $question = new ChoiceQuestion('Elige una opción para editar autopartes:', $options);
        $question->setErrorMessage('Opción inválida.');

        $helper = $this->getHelper('question');
        $selectedOption = $helper->ask($this->input, $this->output, $question);

        // Ejecutar la función correspondiente según la opción seleccionada
        switch ($selectedOption) {
            case 'Descripcion':
                $this->fillDescription();
                break;
            case 'Lado':
                $this->fillSides();
                break;
            case 'Posicion':
                $this->fillPosition();
                break;
            case 'Numero_Parte':
                $this->fillPartNumber();
                break;
            case 'Anios':
                $this->fillYears();
                break;
            case 'Imagenes':
                $this->fillImagesIdMl();
                break;
            case 'Orden_Anios':
                $this->orderCompleteYears();
                break;
            default:
                $this->info('Opción no reconocida.');
                break;
        }
    }

    // Aquí defines las funciones para cada opción
    private function fillDescription()
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('description')
        ->whereNotNull('ml_id')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(100)
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

    private function fillSides()
    {
        $autoparts = DB::table('autoparts')
            ->whereNull('deleted_at')
            ->where('status_id', '!=', 4)
            ->whereNull('side_id')
            ->whereNotNull('ml_id')
            ->orderBy('id', 'desc')
            ->skip(0)
            ->take(1000)
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

    private function fillPosition()
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('position_id')
        ->whereNotNull('ml_id')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(500)
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

    private function fillPartNumber()
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('autopart_number')
        ->whereNotNull('ml_id')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(500)
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

    private function fillYears()
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNull('years')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(100)
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

    private function fillImagesIdMl()
    {
        $autopartsIds = DB::table('autopart_images')
            ->select('autopart_id')
            ->distinct()
            ->whereNull('img_ml_id')
            ->whereNull('deleted_at')
            ->pluck('autopart_id')
            ->toArray();

        $autoparts = DB::table('autoparts')
            ->whereNull('deleted_at')
            ->where('status_id', '!=', 4)
            ->whereIn('id', $autopartsIds)
            ->whereNotNull('ml_id')
            ->orderBy('id', 'desc')
            ->skip(0)
            ->take(100)
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

                    if ($response->status == 200 && isset($response->autopart['images'])) {
                        foreach ($response->autopart['images'] as $key => $img) {
                            $contents = file_get_contents($img['url']);
                            $contentsThumbnail = file_get_contents($img['url_thumbnail']);
                            $name = substr($img['url'], strrpos($img['url'], '/') + 1);
                            
                            if (!Storage::exists('autoparts/'.$autopart->id.'/images/thumbnail_'.$name)){
                                Storage::put('autoparts/'.$autopart->id.'/images/thumbnail_'.$name, $contentsThumbnail);
                            }
    
                            DB::table('autopart_images')
                            ->where('autopart_id', $autopart->id)
                            ->where('order', $key)
                            ->update([
                                'basename' => $name,
                                'img_ml_id' => $img['id'],
                                'updated_at' => Carbon::now()
                            ]);
                            
                        }
                    }
                } catch (\Throwable $th) {
                    logger($th);
                    //throw $th;
                }
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Completar ids de imagenes completado.');
    }

    private function orderCompleteYears()
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('deleted_at')
        ->where('status_id', '!=', 4)
        ->whereNotNull('years')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(500)
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
}
