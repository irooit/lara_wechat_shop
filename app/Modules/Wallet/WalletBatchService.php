<?php

namespace App\Modules\Wallet;


use App\BaseService;
use Illuminate\Support\Carbon;

class WalletBatchService extends BaseService
{

    /**
     * 获取提现批次列表
     * @param $params
     * @param int $pageSize
     * @param bool $withQuery
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Builder
     */
    public static function getWalletBatch($params, $pageSize = 15, $withQuery = false)
    {
        $batchNo = array_get($params, 'batchNo', '');
        $type = array_get($params, 'type', '');
        $status = array_get($params, 'status', '');
        $start = array_get($params, 'start', '');
        $end = array_get($params, 'end', '');

        $query = WalletBatch::query();

        if ($batchNo) {
            $query->where('batch_no', $batchNo);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if($start && $start instanceof Carbon){
            $start = $start->format('Y-m-d H:i:s');
        }
        if($end && $end instanceof Carbon){
            $end = $end->format('Y-m-d H:i:s');
        }
        if($start && $end){
            $query->whereBetween('created_at', [$start, $end]);
        }else if($start){
            $query->where('created_at', '>', $start);
        }else if($end){
            $query->where('created_at', '<', $end);
        }
        if ($withQuery) {
            return $query;
        } else {
            $data = $query->paginate($pageSize);
            return $data;
        }
    }

    /**
     * 根据id 获取提现批次
     * @param $id
     * @param array $field
     * @return WalletBatch
     */
    public static function getById($id, $field = ['*'])
    {
        $walletBatch = WalletBatch::find($id, $field);
        return $walletBatch;
    }
}