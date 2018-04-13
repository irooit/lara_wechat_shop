<?php

namespace App\Console\Commands;

use App\Modules\Merchant\Merchant;
use App\Support\Lbs;
use Illuminate\Console\Command;

class AddMerchantGeo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geo:add-merchants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $merchants = Merchant::all();
        Lbs::merchantGpsAdd($merchants);
    }
}
