<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

use App\Modules\Merchant\Merchant;
use App\Modules\Settlement\SettlementPlatform;
use App\Modules\Settlement\SettlementPlatformService;

/**
 * Author: Jerry
 * Date:    180823
 * 处理商家每日结算
 */
class SettlementForMerchantDaily implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $merchantId;
    protected $date;

    /**
     *
     * @Author   Jerry
     * @DateTime 2018-08-23
     * @param    int $merchantId
     * @param Carbon $date
     */
    public function __construct($merchantId, Carbon $date)
    {
        $this->merchantId = $merchantId;
        $this->date = $date;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    /**
     * 新结算指定日期商家到平台的订单
     * @Author   Jerry
     * @DateTime 2018-08-23
     * @return   void
     * @throws \Exception
     */
    public function handle()
    {
        $merchant = Merchant::findOrFail($this->merchantId);
        // 判断该店是否已结算
        /*$exist = SettlementPlatform::where('merchant_id', $this->merchantId)
            ->where('date', $this->date)->first();
        if ($exist) {
            Log::info('该每日结算已结算,跳过结算', [
                'merchantId' => $this->merchantId,
                'date' => $this->date
            ]);
            return;
        }*/
        try {

            SettlementPlatformService::settlement($merchant, $this->date);
        }catch (\Exception $e){
            Log::error('该商家每日结算错误, 错误原因:' . $e->getMessage(), [
                'merchantId' => $this->merchantId,
                'date' => $this->date,
                'timestamp' => date('Y-m-d H:i:s'),
                'exception' => $e,
            ]);
        }
    }
}
