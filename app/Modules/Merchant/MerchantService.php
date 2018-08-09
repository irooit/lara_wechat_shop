<?php
/**
 * Created by PhpStorm.
 * User: 57458
 * Date: 2018/7/16
 * Time: 15:49
 */

namespace App\Modules\Merchant;


use App\BaseService;
use App\Exceptions\BaseResponseException;
use App\Exceptions\ParamInvalidException;
use App\Modules\Dishes\DishesGoods;
use App\Modules\Goods\Goods;
use App\Modules\Oper\Oper;
use App\Modules\Oper\OperBizMember;
use App\Support\Lbs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MerchantService extends BaseService
{

    /**
     * @param array $data
     * @return Merchant[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getAllNames(array $data)
    {
        // todo 后期可以对查询结果做缓存
        $auditStatus = $data['audit_status'];
        $status = $data['status'];
        $list = Merchant::where(function (Builder $query){
            $currentOperId = request()->get('current_user')->oper_id;
            $query->where('oper_id', $currentOperId)
                ->orWhere('audit_oper_id', $currentOperId);
        })
            ->when($status, function (Builder $query) use ($status){
                $query->where('status', $status);
            })
            ->when(!empty($auditStatus), function (Builder $query) use ($auditStatus){
                if($auditStatus == -1){
                    $auditStatus = 0;
                }
                $query->where('audit_status', $auditStatus);
            })
            ->orderBy('updated_at', 'desc')
            ->select('id', 'name')
            ->get();

        return $list;
    }

    /**
     * 查询商户列表
     * @param array $data 查询条件 {
     *      id,
     *      operId,
     *      creatorOperId,
     *      name,
     *      signboardName,
     *      auditStatus,
     *      startCreatedAt,
     *      endCreatedAt,
     *  }
     * @param bool $getWithQuery
     * @return Merchant|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getList(array $data, bool $getWithQuery=false)
    {
        $id = $data['id'];
        $operId = $data['operId'];
        $creatorOperId = $data['creatorOperId'];
        $name = $data['name'];
        $signboardName = $data['signboardName'];
        $status = $data['status'];
        $auditStatus = $data['auditStatus'];
        $merchantCategory = $data['merchantCategory'];
        $isPilot = $data['isPilot'];
        $startCreatedAt = $data['startCreatedAt'];
        $endCreatedAt = $data['endCreatedAt'];

        // 全局限制条件
        $query = Merchant::where('audit_oper_id', '>', 0)->orderByDesc('id');

        // 筛选条件
        if($id){
            $query->where('id', $id);
        }
        if($operId){
            if(is_array($operId) || $operId instanceof Collection){
                $query->where(function (Builder $query) use ($operId) {
                    $query->whereIn('oper_id',  $operId)
                        ->orWhereIn('audit_oper_id', $operId);
                });
            }else {
                $query->where(function (Builder $query) use ($operId) {
                    $query->where('oper_id',  $operId)
                        ->orWhere('audit_oper_id', $operId);
                });
            }
        }
        if(!empty($creatorOperId)){
            if(is_array($creatorOperId) || $creatorOperId instanceof Collection){
                $query->whereIn('creator_oper_id', $creatorOperId);
            }else {
                $query->where('creator_oper_id', $creatorOperId);
            }
        }
        if ($status) {
            $query->where('status', $status);
        }
        if(!empty($auditStatus)){
            if(is_array($auditStatus) || $auditStatus instanceof Collection){
                $query->whereIn('audit_status', $auditStatus);
            }else {
                if ($auditStatus == -1) $auditStatus = 0;
                $query->where('audit_status', $auditStatus);
            }
        }
        if($startCreatedAt && $endCreatedAt){
            if($startCreatedAt==$endCreatedAt){
                $query->whereDate('created_at', $startCreatedAt);
            }else{
                $query->whereBetween('created_at', [$startCreatedAt . ' 00:00:00', $endCreatedAt . ' 23:59:59']);
            }
        }else if($startCreatedAt){
            $query->where('created_at', '>=', $startCreatedAt . ' 00:00:00');
        }else if($endCreatedAt){
            $query->where('created_at', '<=', $endCreatedAt . ' 23:59:59');
        }
        if($name){
            $query->where('name', 'like', "%$name%");
        }
        if($signboardName){
            $query->where('signboard_name', 'like', "%$signboardName%");
        }
        if(!empty($merchantCategory)){
            if(count($merchantCategory)==1){
                $merchantCategoryFinalId = MerchantCategory::where('pid',$merchantCategory[0])
                    ->select('id')->get()
                    ->pluck('id');
            }else{
                $merchantCategoryFinalId = intval($merchantCategory[1]);
            }
            if(is_array($merchantCategoryFinalId) || $merchantCategoryFinalId instanceof Collection ){
                $query->whereIn('merchant_category_id',$merchantCategoryFinalId);
            }else{
                $query->where('merchant_category_id', $merchantCategoryFinalId);
            }
        }
        if ($isPilot) {
            $query->where('is_pilot', Merchant::PILOT_MERCHANT);
        } else {
            $query->where('is_pilot', Merchant::NORMAL_MERCHANT);
        }

        if($getWithQuery){
            return $query;
        }else {

            $data = $query->paginate();

            $data->each(function ($item) {
                if ($item->merchant_category_id) {
                    $item->categoryPath = MerchantCategoryService::getCategoryPath($item->merchant_category_id);
                }
                $item->desc_pic_list = $item->desc_pic_list ? explode(',', $item->desc_pic_list) : [];
                $item->account = MerchantAccount::where('merchant_id', $item->id)->first();
                $item->business_time = json_decode($item->business_time, 1);
                $item->operName = Oper::where('id', $item->oper_id > 0 ? $item->oper_id : $item->audit_oper_id)->value('name');
                $item->operId = $item->oper_id > 0 ? $item->oper_id : $item->audit_oper_id;
                $item->operBizMemberName = OperBizMember::where('oper_id', $item->operId)->where('code', $item->oper_biz_member_code)->value('name') ?: '无';
            });

            return $data;
        }
    }

    /**
     * 根据ID获取商户详情
     * @param $id
     * @return Merchant
     */
    public static function detail($id)
    {

        $merchant = Merchant::findOrFail($id);
        $merchant->categoryPath = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id);
        $merchant->categoryPathOnlyEnable = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id, true);
        $merchant->account = MerchantAccount::where('merchant_id', $merchant->id)->first();
        $merchant->business_time = json_decode($merchant->business_time, 1);
        $merchant->desc_pic_list = $merchant->desc_pic_list ? explode(',', $merchant->desc_pic_list) : '';
        $merchant->contract_pic_url = $merchant->contract_pic_url ? explode(',', $merchant->contract_pic_url) : '';
        $merchant->other_card_pic_urls = $merchant->other_card_pic_urls ? explode(',', $merchant->other_card_pic_urls) : '';
        $merchant->bank_card_pic_a = $merchant->bank_card_pic_a ? explode(',', $merchant->bank_card_pic_a) : '';

        $merchant->operName = Oper::where('id', $merchant->oper_id > 0 ? $merchant->oper_id : $merchant->audit_oper_id)->value('name');
        $merchant->creatorOperName = Oper::where('id', $merchant->creator_oper_id)->value('name');
        if($merchant->oper_biz_member_code){
            $merchant->operBizMemberName = OperBizMember::where('code', $merchant->oper_biz_member_code)->value('name');
        }
        $oper = Oper::where('id', $merchant->oper_id > 0 ? $merchant->oper_id : $merchant->audit_oper_id)->first();
        if ($oper){
            $merchant->operAddress = $oper->province.$oper->city.$oper->area.$oper->address;
        }
        return $merchant;
    }

    public static function audit()
    {
        // todo 审核商户
    }

    /**
     * 编辑商户
     * @param $id
     * @return Merchant
     */
    public static function edit($id)
    {
        $currentOperId = request()->get('current_user')->oper_id;
        $merchant = Merchant::where('id', $id)
            ->where('audit_oper_id', $currentOperId)
            ->first();
        if (empty($merchant)) {
            throw new BaseResponseException('该商户不存在');
        }

        if(!empty($merchant->oper_biz_member_code)){
            // 记录原业务员ID
            $originOperBizMemberCode = $merchant->oper_biz_member_code;
        }

        $merchant->fillMerchantPoolInfoFromRequest();
        $merchant->fillMerchantActiveInfoFromRequest();

        // 商户名不能重复
        $exists = Merchant::where('name', $merchant->name)
            ->where('id', '<>', $merchant->id)->first();
        $existsDraft = MerchantDraft::where('name', $merchant->name)->first();
        if($exists || $existsDraft){
            throw new ParamInvalidException('商户名称不能重复');
        }

        if($merchant->oper_id > 0){
            // 如果当前商户已有所属运营中心, 则此次提交为重新提交审核
            // 添加审核记录
            MerchantAuditService::addAudit($merchant->id, $currentOperId,Merchant::AUDIT_STATUS_RESUBMIT);
            $merchant->audit_status = Merchant::AUDIT_STATUS_RESUBMIT;
        }else {
            MerchantAuditService::addAudit($merchant->id, $currentOperId);
            $merchant->audit_status = Merchant::AUDIT_STATUS_AUDITING;
        }

        $merchant->save();

        // 更新业务员已激活商户数量
        if($merchant->oper_biz_member_code){
            OperBizMember::updateActiveMerchantNumberByCode($merchant->oper_biz_member_code);
            OperBizMember::updateAuditMerchantNumberByCode($merchant->oper_biz_member_code);
        }

        // 如果存在原有的业务员, 并且不等于现有的业务员, 更新原有业务员邀请用户数量
        if(isset($originOperBizMemberCode) && $originOperBizMemberCode != $merchant->oper_biz_member_code){
            OperBizMember::updateActiveMerchantNumberByCode($originOperBizMemberCode);
            OperBizMember::updateAuditMerchantNumberByCode($originOperBizMemberCode);
        }

        return $merchant;
    }

    /**
     * 添加商户
     * @return Merchant
     */
    public static function add()
    {
        $merchant = new Merchant();
        $merchant->fillMerchantPoolInfoFromRequest();
        $merchant->fillMerchantActiveInfoFromRequest();

        // 补充商家创建者及审核提交者
        $currentOperId = request()->get('current_user')->oper_id;
        $merchant->audit_oper_id = $currentOperId;
        $merchant->creator_oper_id = $currentOperId;

        // 商户名不能重复
        $exists = Merchant::where('name', $merchant->name)->first();
        $existsDraft = MerchantDraft::where('name', $merchant->name)->first();
        if($exists || $existsDraft){
            throw new ParamInvalidException('商户名称不能重复');
        }

        $merchant->save();

        // 添加审核记录
        MerchantAuditService::addAudit($merchant->id, $currentOperId);

        // 更新业务员已激活商户数量
        if($merchant->oper_biz_member_code){
            OperBizMember::updateActiveMerchantNumberByCode($merchant->oper_biz_member_code);
        }

        return $merchant;
    }

    public static function addFromDraft()
    {
        // todo 从草稿箱添加
    }

    public static function addFromMerchantPool()
    {
        // todo 从商户池添加
    }


    /**
     * 同步商户地区数据到Redis中
     * @param $merchant Collection|Merchant
     */
    public static function geoAddToRedis($merchant)
    {
        if($merchant instanceof Collection){
            Lbs::merchantGpsAdd($merchant);
        }else {
            Lbs::merchantGpsAdd($merchant->id, $merchant->lng, $merchant->lat);
        }
    }

    /**
     * 同步所有商户的LBS数据到Redis中
     */
    public static function geoAddAllToRedis()
    {
        Merchant::chunk(100, function (Collection $list){
            self::geoAddToRedis($list);
        });
    }


    /**
     * 更新商户的最低价格
     * @param $merchant_id
     */
    public static function updateMerchantLowestAmount($merchant_id)
    {
        $merchant = Merchant::findOrFail($merchant_id);
        $merchant->lowest_amount = self::getLowestPriceForMerchant($merchant_id);
        $merchant->save();
    }

    /**
     * 根据商家获取最低价商品的价格, 没有商品是返回null
     * @param $merchantId
     * @return number|null
     */
    public static function getLowestPriceForMerchant($merchantId)
    {
        $goodsLowestAmount = Goods::where('merchant_id', $merchantId)
                ->where('status', Goods::STATUS_ON)
                ->orderBy('price')
                ->value('price') ?? 0;
        $dishesGoodsLowestAmount = DishesGoods::where('merchant_id', $merchantId)
                ->where('status', DishesGoods::STATUS_ON)
                ->orderBy('sale_price')
                ->value('sale_price') ?? 0;
        if (!$goodsLowestAmount || !$dishesGoodsLowestAmount) {
            return max($goodsLowestAmount, $dishesGoodsLowestAmount);
        } else {
            return min($goodsLowestAmount, $dishesGoodsLowestAmount);
        }
    }

    /**
     * 获取商户的某个值
     * @param $merchantId
     * @param $key
     * @return mixed
     */
    public static function getMerchantValueByIdAndKey($merchantId, $key)
    {
        $value = Merchant::where('id', $merchantId)->value($key);
        return $value;
    }

}