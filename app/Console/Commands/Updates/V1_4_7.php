<?php

namespace App\Console\Commands\Updates;

use App\Modules\Merchant\Merchant;
use App\Modules\Oper\OperBizer;
use App\Modules\Settlement\SettlementPlatform;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class V1_4_7 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:v1.4.7';

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

        /*
        $sql = 'update admin_auth_rules  set pid=5 where id=38;';

        DB::statement($sql);

        $sql = "ALTER TABLE `admin_auth_rules`
	CHANGE COLUMN `url_all` `url_all` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '权限全部菜单地址, 使用逗号分隔' COLLATE 'utf8mb4_unicode_ci' AFTER `url`";

        DB::statement($sql);


        OperBizer::chunk(1000, function ($operBizers) {
            foreach ($operBizers as $operBizer) {
                $operBizer->divide = number_format(20, 2);
                $operBizer->save();
            }
        });

        */


        /*Merchant::where('audit_status',3)
            ->chunk(1000,function ($merchants){
            foreach ($merchants as $merchant){
                $merchant->audit_status = 0;
                $merchant->save();
            }
        });*/

        /*
        SettlementPlatform::chunk(1000,function ($sps) {
            foreach ($sps as $sp) {
                $merchant = Merchant::find($sp->merchant_id) ;
                if (!empty($merchant->bank_province)) {
                    $sp->bank_open_address = $merchant->bank_province . $merchant->bank_city . $merchant->bank_area .'|' .$sp->bank_open_address;
                    $sp->save();
                }

            }
        });

        */

        /*
        $sql = 'UPDATE orders set settlement_status=1 where settlement_id=159;';

        DB::statement($sql);

        $sql = 'delete from settlement_platforms where id=159;';

        DB::statement($sql);

        */
        $sql = 'update merchants set audit_status=0 where id=49671;';

        DB::statement($sql);
        $this->info('执行成功');
    }
}
