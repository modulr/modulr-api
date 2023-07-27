<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Support\Facades\DB;
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
        $options = ['Lado', 'Posicion', 'Numero_Parte','Anios','Imagenes','Orden_Anios'];
        $question = new ChoiceQuestion('Elige una opción para editar autopartes:', $options);
        $question->setErrorMessage('Opción inválida.');

        $helper = $this->getHelper('question');
        $selectedOption = $helper->ask($this->input, $this->output, $question);

        // Ejecutar la función correspondiente según la opción seleccionada
        switch ($selectedOption) {
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
    private function fillSides()
    {
        $autoparts = DB::table('autoparts')
            ->whereNull('deleted_at')
            ->where('status_id', '!=', 4)
            ->whereNull('side_id')
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
                    throw $th;
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
        ->whereNull('position_id')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(5)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $aut) {
            try {
                $response = ApiMl::getItemValues($aut->store_ml_id, $aut->ml_id);

                if ($response->status == 200 && isset($response->autopart['position_id'])) {
                    $aut->position_id = $response->autopart['position_id'];
                    // $aut->save();
                }
            } catch (\Throwable $th) {
                //throw $th;
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
        ->whereNull('autopart_number')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(5)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $aut) {
            try {
                $response = ApiMl::getItemValues($aut->store_ml_id, $aut->ml_id);

                if ($response->status == 200 && isset($autopart['autopart_number'])) {
                    $aut->autopart_number = $autopart['autopart_number'];
                    // $aut->save();
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Completar numero de parte terminado.');
    }

    private function fillYears()
    {
        $autoparts = DB::table('autoparts')
        ->whereNull('years')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(5)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $aut) {
            try {
                $response = ApiMl::getItemValues($aut->store_ml_id, $aut->ml_id);

                if ($response->status == 200 && isset($autopart['years'])) {
                    $aut->years = $autopart['years'];
                    // $aut->save();
                }
            } catch (\Throwable $th) {
                //throw $th;
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
            ->pluck('autopart_id')
            ->toArray();

        $autoparts = DB::table('autoparts')
            ->whereIn('id', $autopartsIds)
            ->orderBy('id', 'desc')
            ->skip(0)
            ->take(5)
            ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $aut) {
            try {
                $response = ApiMl::getItemValues($aut->store_ml_id, $aut->ml_id);

                if ($response->status == 200 && isset($response->autopart['images'])) {
                    foreach ($response->autopart['images'] as $key => $img) {
                        $contents = file_get_contents($img['url']);
                        $contentsThumbnail = file_get_contents($img['url_thumbnail']);
                        $name = substr($img['url'], strrpos($img['url'], '/') + 1);
                        //COMENTADO PARA PRUEBAS
                        // Storage::put('autoparts/'.$autopartId.'/images/'.$name, $contents);
 
                        // DB::table('autopart_images')
                        // ->where('autopart_id', $aut->id)
                        // ->where('order', $key)
                        // ->update([
                        //     'basename' => $name,
                        //     'img_ml_id' => $img['id'],
                        //     'updated_at' => Carbon::now()
                        // ]);
                        
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Completar ids de imagenes completado.');
    }

    private function orderCompleteYears()
    {
        $autoparts = DB::table('autoparts')
        ->whereNotNull('years')
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(5)
        ->get();

        // Crea una instancia de ProgressBar
        $progressBar = new ProgressBar($this->output, count($autoparts));
        // Inicia la barra de progreso
        $progressBar->start();

        // Recorre las autoparts y realiza el proceso para cada una
        foreach ($autoparts as $aut) {
            try {
                $response = ApiMl::getItemValues($aut->store_ml_id, $aut->ml_id);

                if ($response->status == 200 && isset($autopart['years'])) {
                    if (count($autopart['years']) > 1) {
                        $years = [];
                        for ($i = min($autopart['years']); $i <= max($autopart['years']); $i++) {
                            $years[] = (string) $i;
                        }
                        $aut->years = $years;
                    }else{
                        $aut->years = $autopart['years'];
                    }
                    // $aut->save();
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
            $progressBar->advance();
        }
        $this->output->writeln('');
        $this->info('Ordenar y completar años terminado.');
    }
}
