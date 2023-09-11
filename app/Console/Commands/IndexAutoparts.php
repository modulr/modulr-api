<?php

namespace App\Console\Commands;

use Config;
use Illuminate\Console\Command;
use TNTSearch;

class IndexAutoparts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:autoparts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index autoparts';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $indexer = TNTSearch::createIndex('autoparts.index');
        $indexer->query('SELECT
                autoparts.id,
                autoparts.name,
                make.name AS marca,
                make.variants AS marca_variante,
                model.name AS modelo,
                model.variants AS modelo_variante,
                category.name AS categoria,
                category.variants AS categoria_variante
            FROM autoparts
            JOIN autopart_list_categories AS category ON autoparts.category_id = category.id
            JOIN autopart_list_makes AS make ON autoparts.make_id = make.id
            JOIN autopart_list_models AS model ON autoparts.model_id = model.id;');
        $indexer->run();
    }
}
