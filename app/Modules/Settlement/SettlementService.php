<?php
/**
 * Created by PhpStorm.
 * User: 57458
 * Date: 2018/7/25
 * Time: 17:03
 */

namespace App\Modules\Settlement;


use App\BaseService;
use App\Modules\Order\Order;
use App\Modules\Order\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SettlementService extends BaseService
{

    /**
     * 获取结算单列表
     * @param array $params {merchantId, operId}
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getList(array $params)
    {
        $merchantId = array_get($params, 'merchantId');
        $data = Settlement::where('merchant_id', $merchantId)
            ->orderBy('id', 'desc')
            ->paginate();
        return $data;
    }

    /**
     * SAAS获取结算单列表【旧】
     * @param array $params {merchantId, operId}
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getListForSaas(array $params = [])
    {
        //DB::enableQueryLog();
        $data = Settlement::where('id', '>', 0)
            ->whereHas('merchant', function( $query){
                $query->where('name', 'like', '百事可乐');
            })
            ->with('merchant:id,name')
            ->orderBy('id', 'desc')
            ->paginate();
        //dd(DB::getQueryLog());
        return $data;
    }

    /**
     * 根据ID获取结算单
     * @param $settlementId
     * @return Settlement
     */
    public static function getById($settlementId)
    {
        return Settlement::find($settlementId);
    }

    /**
     * 根据商户ID及结算单获取结算单信息
     * @param $settlementId
     * @param $merchantId
     * @return \Illuminate\Support\Collection
     */
    public static function getByIdAndMerchantId($settlementId, $merchantId)
    {
        return Settlement::where('id', $settlementId)->where('merchant_id', $merchantId)->pluck('settlement_date');
    }

    /**
     * 根据所属运营中心ID及商家ID获取结时间
     * @param $operId
     * @param $merchantId
     * @return Settlement
     */
    public static function getByOperIdAndMerchantId($operId, $merchantId)
    {
        return Settlement::where('oper_id', $operId)->where('merchant_id', $merchantId)->first();
    }

    /**
     * @param $operId
     * @param $params
     * @param bool $getWithQuery
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Query\Builder
     */
    public static function getOperSettlements($operId, $params, $getWithQuery = false)
    {
        $merchantId = array_get($params, 'merchantId', '');
        $status = array_get($params, 'status', '');
        $showAmount = array_get($params, 'showAmount', '');
        $settlementDate = array_get($params, 'settlementDate', []);
        $operBizMemberName = array_get($params, 'operBizMemberName', '');
        $operBizMemberMobile = array_get($params, 'operBizMemberMobile', '');
        $bizerId = array_get($params, 'bizerId', '');
        $memberId = array_get($params, 'memberId', '');

        $query = DB::table('settlements')
            ->leftJoin('merchants','settlements.merchant_id','=','merchants.id')
            ->leftJoin('oper_biz_members as om','om.code','=','merchants.oper_biz_member_code')
            ->leftJoin('bizers', 'bizers.id', '=', 'merchants.bizer_id')
            ->select('settlements.*','merchants.name as merchant_name','om.name as oper_biz_member_name','om.mobile as oper_biz_member_mobile', 'om.id as oper_biz_member_id', 'bizers.id as bizer_id', 'bizers.name as bizer_name', 'bizers.mobile as bizer_mobile')
            ->where('settlements.oper_id', $operId)
            ->where('settlements.amount','>',0)
            ->orderBy('settlements.id', 'desc');

        if($merchantId){
            $query->where('settlements.merchant_id', $merchantId);
        }
        if ($bizerId) {
            $query->where('bizers.id', $bizerId);
        }
        if ($memberId) {
            $query->where('om.id', $memberId);
        }
        if($status){
            $query->where('settlements.status', $status);
        }
        if($showAmount){
            $query->where('settlements.amount', '>', 0);
        }
        if(count($settlementDate) > 1){
            $query->whereBetween('settlements.created_at', [$settlementDate[0] . ' 00:00:00', $settlementDate[1] . ' 23:59:59']);
        }
        if($operBizMemberName){
            $query->where('om.name','like', '%'.$operBizMemberName.'%');
        }
        if($operBizMemberMobile){
            $query->where('om.mobile', 'like','%'.$operBizMemberMobile.'%');
        }

        if ($getWithQuery) {
            return $query;
        } else {
            $data = $query->paginate();
            return $data;
        }
    }

    public static function updateInvoice($id,$invoice_type,$invoice_pic_url,$logistics_name,$logistics_no)
    {
        $settlement = Settlement::findOrFail($id);
        $settlement->invoice_type = $invoice_type;
        $settlement->invoice_pic_url = $invoice_pic_url;
        $settlement->logistics_name = $logistics_name;
        $settlement->logistics_no = $logistics_no;
        $settlement->save();

        return $settlement;
    }

    public static function updatePayPicUrl($id,$pay_pic_url)
    {
        $settlement = Settlement::findOrFail($id);
        $settlement->pay_pic_url = $pay_pic_url;
        $settlement->status = 2;
        $settlement->save();

        return $settlement;
    }
}