<?php

namespace App\Jobs\Schedule;

use App\Modules\Oper\OperStatistics;
use App\Modules\Oper\OperStatisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Oper\Oper;

/**
 * 用于每日新增运营中心营销统计
 * Class OperStatisticsDailyJob
 * Author:   JerryChan
 * Date:     2018/9/20 16:46
 * @package App\Jobs\Schedule
 */
class OperStatisticsDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $endTime = '';
    /**
     * Create a new job instance.
     * @return void
     */
    public function __construct($endTime='')
    {
        //
        if (empty($endTime)) {
            $endTime = date('Y-m-d H:i:s');
        }
        $this->endTime = $endTime;
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        Log::info('生成运营中心营销统计数据 :Start' . $this->endTime);

        OperStatisticsService::statistics($this->endTime);

        Log::info('生成运营中心营销统计数据 :end');
    }
}
