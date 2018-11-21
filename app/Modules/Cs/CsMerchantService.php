<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/11/20
 * Time: 11:29 AM
 */
namespace App\Modules\Cs;

use App\BaseService;
use App\DataCacheService;
use App\Exceptions\BaseResponseException;
use App\Exceptions\ParamInvalidException;
use App\Modules\Area\Area;
use App\Modules\Country\CountryService;
use App\Modules\Merchant\Merchant;
use App\Modules\Merchant\MerchantFollow;
use App\Modules\Oper\Oper;
use App\Support\Lbs;
use App\Support\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CsMerchantService extends BaseService {

    /**
     * 根据ID获取商户信息
     * @param $id
     * @param array|string $fields
     * @return CsMerchant
     */
    public static function getById($id, $fields = ['*'])
    {
        if(is_string($fields)){
            $fields = explode(',', $fields);
        }
        return CsMerchant::find($id, $fields);
    }

    /**
     * 根据ID获取商户名称
     * @param $merchantId
     * @param null $default
     * @return null|string
     */
    public static function getNameById($merchantId, $default = null)
    {
        $merchant = self::getById($merchantId, ['name']);
        if (empty($merchant)) {
            return $default;
        }
        return $merchant->name;
    }

    /**
     * 获取商户的招牌名
     * @param $merchantId
     * @return mixed
     */
    public static function getSignboardNameById($merchantId)
    {
        $value = CsMerchant::where('id', $merchantId)->value('signboard_name');
        return $value;
    }

    /**
     * @param array $data
     * @return CsMerchant[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getAllNames(array $data = [])
    {
        // todo 后期可以对查询结果做缓存
        $auditStatus = array_get($data, 'audit_status');
        $status =array_get($data, 'status');
        //$isPilot =array_get($data, 'isPilot');//是否为试点商户
        $operId =array_get($data, 'operId'); //运营中心ID
        //$bizer_id = array_get($data, 'bizer_id');//业务员ID
//        $list = Merchant::where(function (Builder $query) use ($operId) {
//            $query->where('oper_id', $operId)
//                ->orWhere('audit_oper_id', $operId);
//        })

        $data = CsMerchant::select('id', 'name')
            ->when($status, function (Builder $query) use ($status) {
                $query->where('status', $status);
            })
            /*->when(!empty($bizer_id), function (Builder $query) use ($bizer_id) {
                $query->where('bizer_id', $bizer_id);
            })*/
            ->when(!empty($auditStatus), function (Builder $query) use ($auditStatus) {
                if ($auditStatus == -1) {
                    $auditStatus = 0;
                }
                $query->where('audit_status', $auditStatus);
            });

        if ($operId) {
            $data->where(function (Builder $query) use ($operId) {
                $query->where('oper_id', $operId)
                    ->orWhere('audit_oper_id', $operId);
            });
        }

        /*if($isPilot){
            $data->where('is_pilot', CsMerchant::PILOT_MERCHANT);
        }else{
            $data->where('is_pilot', CsMerchant::NORMAL_MERCHANT);
        }*/

        $list = $data->orderBy('updated_at', 'desc')->get();

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
     * @return CsMerchant|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getList(array $data, bool $getWithQuery = false)
    {
        $id = array_get($data,'id');
        $operId = array_get($data,'operId');
        $creatorOperId = array_get($data,'creatorOperId');
        $name = array_get($data,'name');
        $merchantId = array_get($data,'merchantId');
        $signboardName = array_get($data,'signboardName');
        $status = array_get($data,'status');
        $settlementCycleType = array_get($data,'settlementCycleType');
        $auditStatus = array_get($data,'auditStatus');
        //$merchantCategory = array_get($data,'merchantCategory');
        //$isPilot = array_get($data,'isPilot');
        $startCreatedAt = array_get($data,'startCreatedAt');
        $endCreatedAt = array_get($data,'endCreatedAt');

        $cityId = array_get($data, "cityId");
        //$bizerIds = array_get($data, "bizer_id");
        //$operBizMemberCodes = array_get($data, 'operBizMemberCodes');

        // 全局限制条件
        $query = CsMerchant::where('audit_oper_id', '>', 0)
            ->orderByDesc('id');

        // 筛选条件
        if ($id) {
            $query->where('id', $id);
        }
        if ($operId) {
            if (is_array($operId) || $operId instanceof Collection) {
                $query->where(function (Builder $query) use ($operId) {
                    $query->whereIn('oper_id', $operId)
                        ->orWhereIn('audit_oper_id', $operId);
                });
            } else {
                $query->where(function (Builder $query) use ($operId) {
                    $query->where('oper_id', $operId)
                        ->orWhere('audit_oper_id', $operId);
                });
            }
        }
        if (!empty($creatorOperId)) {
            if (is_array($creatorOperId) || $creatorOperId instanceof Collection) {
                $query->whereIn('creator_oper_id', $creatorOperId);
            } else {
                $query->where('creator_oper_id', $creatorOperId);
            }
        }

        /*if (!empty($bizerIds)) {
            if (is_array($bizerIds) || $bizerIds instanceof Collection) {
                $query->whereIn('bizer_id', $bizerIds);
            } else {
                $query->where('bizer_id', $bizerIds);
            }
        }*/

        /*if (!empty($operBizMemberCodes)) {
            if (is_array($operBizMemberCodes) || $operBizMemberCodes instanceof Collection) {
                $query->whereIn('oper_biz_member_code', $operBizMemberCodes);
            } else {
                $query->where('oper_biz_member_code', $operBizMemberCodes);
            }
        }*/

        if(!empty($cityId)){
            if(isset($cityId[0]) && $cityId[0]){
                $province_Id = Area::findOrFail($cityId[0]);
                $query->where('province_id', $province_Id->area_id);
            }
            if(isset($cityId[1]) && $cityId[1]){
                $city_Id = Area::findOrFail($cityId[1]);
                $query->where('city_id', $city_Id->area_id);
            }
            if(isset($cityId[2]) && $cityId[2]){
                $area_Id = Area::findOrFail($cityId[2]);
                $query->where('area_id', $area_Id->area_id);
            }
        }
        if (!empty($status)){
            // todo 不能根据status改变 audit_status
            if($status == 1){
                $query->where('status',1)->whereIn('audit_status',[1,3]);
            }elseif($status ==2){
                $query->where('status',2)->whereIn('audit_status',[1,3]);
            }else{
                $query->whereIn('audit_status',[0,2]);
            }
        }
        if ($settlementCycleType) {
            $query->whereIn('settlement_cycle_type', $settlementCycleType);
        }

        if (!empty($auditStatus)) {
            if (is_array($auditStatus) || $auditStatus instanceof Collection) {
                $query->whereIn('audit_status', $auditStatus);
            } else {
                if ($auditStatus == -1) $auditStatus = 0;
                $query->where('audit_status', $auditStatus);
            }
        }
        if ($startCreatedAt && $endCreatedAt) {
            if ($startCreatedAt == $endCreatedAt) {
                $query->whereDate('created_at', $startCreatedAt);
            } else {
                $query->whereBetween('created_at', [$startCreatedAt . ' 00:00:00', $endCreatedAt . ' 23:59:59']);
            }
        } else if ($startCreatedAt) {
            $query->where('created_at', '>=', $startCreatedAt . ' 00:00:00');
        } else if ($endCreatedAt) {
            $query->where('created_at', '<=', $endCreatedAt . ' 23:59:59');
        }
        if ($name) {
            $query->where('name', 'like', "%$name%");
        }
        if(!empty($merchantId)){
            if (is_array($merchantId) || $merchantId instanceof Collection) {
                $query->whereIn('id', $merchantId);
            } else {
                $query->where('id', $merchantId);
            }
        }
        if ($signboardName) {
            $query->where('signboard_name', 'like', "%$signboardName%");
        }
        /*if (!empty($merchantCategory)) {
            if (count($merchantCategory) == 1) {
                $merchantCategoryFinalId = MerchantCategory::where('pid', $merchantCategory[0])
                    ->select('id')->get()
                    ->pluck('id')->toArray();
            } else {
                $merchantCategoryFinalId = intval($merchantCategory[1]);
            }
            if (is_array($merchantCategoryFinalId) || $merchantCategoryFinalId instanceof Collection) {
                $query->whereIn('merchant_category_id', $merchantCategoryFinalId);
            } else {
                $query->where('merchant_category_id', $merchantCategoryFinalId);
            }
        }*/
        /*if ($isPilot) {
            $query->where('is_pilot', Merchant::PILOT_MERCHANT);
        } else {
            $query->where('is_pilot', Merchant::NORMAL_MERCHANT);
        }*/

        if ($getWithQuery) {
            return $query;
        } else {

            $data = $query->paginate();
            $data->each(function ($item) {
                /*if ($item->merchant_category_id) {
                    $item->categoryPath = MerchantCategoryService::getCategoryPath($item->merchant_category_id);
                }*/
                $item->desc_pic_list = $item->desc_pic_list ? explode(',', $item->desc_pic_list) : [];
                $item->account = $item->name;
                $item->business_time = json_decode($item->business_time, 1);
                $item->operName = Oper::where('id', $item->oper_id > 0 ? $item->oper_id : $item->audit_oper_id)->value('name');
                $item->operId = $item->oper_id > 0 ? $item->oper_id : $item->audit_oper_id;
                /*if ($item->bizer_id) {
                    $operBizer = OperBizerService::getOperBizerByParam([
                        'operId' => $item->oper_id > 0 ? $item->oper_id : $item->audit_oper_id,
                        'bizerId' => $item->bizer_id,
                    ]);
                    $item->divide = empty($operBizer) ? 0 : $operBizer->divide;
                }
                $item->bizer = BizerService::getById($item->bizer_id);
                $operBizMember = OperBizMember::where('oper_id', $item->operId)
                    ->where('code', $item->oper_biz_member_code)
                    ->first();
                $item->operBizMember = $operBizMember;
                $item->operBizMemberName = !empty($operBizMember) ? $operBizMember->value('name') : '无';*/
            });

            return $data;
        }
    }

    /**
     * 根据ID获取商户详情
     * @param $id
     * @return CsMerchant
     */
    public static function detail($id)
    {

        $userId = request()->get('current_user')->id;
        $merchant = CsMerchant::findOrFail($id);
        /*$merchant->categoryPath = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id);
        $merchant->categoryPathText = '';
        foreach ($merchant->categoryPath as $item) {
            $merchant->categoryPathText .= $item->name . ' ';
        }
        $merchant->categoryPathOnlyEnable = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id, true);*/
        $merchant->account = $merchant->name;
        $merchant->business_time = json_decode($merchant->business_time, 1);
        $merchant->desc_pic_list = $merchant->desc_pic_list ? explode(',', $merchant->desc_pic_list) : '';
        $merchant->contract_pic_url = $merchant->contract_pic_url ? explode(',', $merchant->contract_pic_url) : '';
        $merchant->other_card_pic_urls = $merchant->other_card_pic_urls ? explode(',', $merchant->other_card_pic_urls) : '';
        $merchant->bank_card_pic_a = $merchant->bank_card_pic_a ? explode(',', $merchant->bank_card_pic_a) : '';

        $merchant->operName = Oper::where('id', $merchant->oper_id > 0 ? $merchant->oper_id : $merchant->audit_oper_id)->value('name');
        $merchant->creatorOperName = Oper::where('id', $merchant->creator_oper_id)->value('name');
        /*if ($merchant->oper_biz_member_code) {
            $operBizMember = OperBizMember::where('code', $merchant->oper_biz_member_code)->first();
            $merchant->operBizMember = $operBizMember;
            $merchant->operBizMemberName = !empty($operBizMember) ? $operBizMember->name : '';
        }
        if ($merchant->bizer_id) {
            $merchant->bizer = BizerService::getById($merchant->bizer_id);
        }*/
        $oper = Oper::where('id', $merchant->oper_id > 0 ? $merchant->oper_id : $merchant->audit_oper_id)->first();
        if ($oper) {
            $merchant->operAddress = $oper->province . $oper->city . $oper->area . $oper->address;
            $merchant->isPayToPlatform = in_array($oper->pay_to_platform, [Oper::PAY_TO_PLATFORM_WITHOUT_SPLITTING, Oper::PAY_TO_PLATFORM_WITH_SPLITTING]);
        }
        $merchantFollow = MerchantFollow::where('user_id',$userId)->where('merchant_id',$id);
        if($merchantFollow){
            $merchant->user_follow_status = 2;
        }else{
            $merchant->user_follow_status =1;
        }
        $merchant->countryName = CountryService::getNameZhById($merchant->country_id);
        return $merchant;
    }

    /**
     * 编辑商户
     * @param $id
     * @param $currentOperId
     * @param string $auditStauts
     * @param bool $isAdmin
     * @return CsMerchant
     */
    public static function edit($id, $currentOperId, $auditStauts = '', $isAdmin = false)
    {
        if(empty($currentOperId)){
            $currentOperId = 0;
        }

        $merchant = CsMerchant::where('id', $id)
            //->where('audit_oper_id', $currentOperId)
            ->first();
        //获取原有结算周期
        $settlement_cycle_type = $merchant->settlement_cycle_type;
        if (empty($merchant)) {
            throw new BaseResponseException('该商户不存在');
        }

        $merchant->fillMerchantPoolInfoFromRequest();
        $merchant->fillMerchantActiveInfoFromRequest();

        // 商户名不能重复

        $exists = CsMerchant::where('name', $merchant->name)
            ->where('id', '<>', $merchant->id)->first();
        $existsDraft = CsMerchantAudit::where('name', $merchant->name)->first();
        if ($exists || $existsDraft) {
            throw new ParamInvalidException('商户名称不能重复');
        }
        // 招牌名不能重复
        $exists = CsMerchant::where('signboard_name', $merchant->signboard_name)
            ->where('id', '<>', $merchant->id)->first();
        $existsDraft = CsMerchantAudit::where('signboard_name', $merchant->signboard_name)->first();
        if ($exists || $existsDraft) {
            throw new ParamInvalidException('招牌名称不能重复');
        }

        if($merchant->settlement_cycle_type == CsMerchant::SETTLE_DAY_ADD_ONE || $merchant->settlement_cycle_type == CsMerchant::SETTLE_MONTHLY){

            //判断编辑之前商户是否已切换到T+1
            /*if($settlement_cycle_type == CsMerchant::SETTLE_DAY_ADD_ONE || $settlement_cycle_type
                == CsMerchant::SETTLE_MONTHLY){

            }else{
                $date = Carbon::now()->startOfDay();
                $week = Carbon::now()->startOfWeek();
                if($date != $week){
                    throw new ParamInvalidException('周结改T+1需要周一才能修改');
                }
            }*/

            if($merchant->bank_card_type == CsMerchant::BANK_CARD_TYPE_COMPANY){
                if($merchant->name != $merchant->bank_open_name){
                    throw new ParamInvalidException('提交失败，申请T+1结算，商户名称需和开户名一致');
                }
            }elseif($merchant->bank_card_type == CsMerchant::BANK_CARD_TYPE_PEOPLE){
                if($merchant->corporation_name != $merchant->bank_open_name){
                    throw new ParamInvalidException('提交失败，申请T+1结算，营业执照及法人姓名需和开户名一致');
                }
            }
        }

        CsMerchantAuditService::addAudit($merchant->id, $currentOperId);

        //admin编辑商户，除审核通过的商户编辑完成直接默认审核通过外，其他状态的商户编辑后都是待审核
        if ($isAdmin) {
            if ($auditStauts != CsMerchant::AUDIT_STATUS_SUCCESS) {
                $merchant->audit_status = CsMerchant::AUDIT_STATUS_AUDITING;
            } else {
                $merchant->audit_status = CsMerchant::AUDIT_STATUS_SUCCESS;
            }
        }

        $merchant->save();

        return $merchant;
    }

    /**
     * @param $currentOperId
     * 添加商户
     * @return CsMerchant
     */
    public static function add($currentOperId)
    {
        $csmerchant = new CsMerchant();
        $csmerchant->fillMerchantPoolInfoFromRequest();
        $csmerchant->fillMerchantActiveInfoFromRequest();

        // 补充商家创建者及审核提交者
        $csmerchant->audit_oper_id = $currentOperId;
        $csmerchant->creator_oper_id = $currentOperId;

        // 商户名不能重复
        //查询超市表
        $exists = CsMerchant::where('name', $csmerchant->name)->first();
        //查询普通商户表
        $existsMerchant = Merchant::where('name', $csmerchant->name)->first();
        //查询超市审核记录表
        $existsCsmerchantAudit = CsMerchantAudit::where('name', $csmerchant->name)->first();
        if ($exists || $existsMerchant || $existsCsmerchantAudit) {
            throw new ParamInvalidException('商户名称不能重复');
        }
        // 招牌名不能重复
        $signboardName = CsMerchant::where('signboard_name', $csmerchant->signboard_name)->first();
        $existsMerchantSignboardName = Merchant::where('signboard_name',$csmerchant->signboard_name)->first();
        if ($signboardName || $existsMerchantSignboardName) {
            throw new ParamInvalidException('招牌名称不能重复');
        }

        $csmerchant->toArray();
        $jsonTomerchant = json_encode($csmerchant);

        $params = [
            'oper_id' => $currentOperId,
            'name' => $csmerchant['name'],
            'dataAfter' => $jsonTomerchant,
        ];

        // 添加审核记录
        CsMerchantAuditService::addAudit($params);

        return $csmerchant;
    }

    public static function addFromDraft()
    {
        // todo 从草稿箱添加
    }




    /**
     * 同步商户地区数据到Redis中
     * @param $merchant Collection|Merchant
     */
    public static function geoAddToRedis($merchant)
    {
        if ($merchant instanceof Collection) {
            Lbs::merchantGpsAdd($merchant);
        } else {
            Lbs::merchantGpsAdd($merchant->id, $merchant->lng, $merchant->lat);
        }
    }

    /**
     * 同步所有商户的LBS数据到Redis中
     */
    public static function geoAddAllToRedis()
    {
        CsMerchant::chunk(100, function (Collection $list) {
            self::geoAddToRedis($list);
        });
    }


    /**
     * 更新商户的最低价格
     * @param $merchant_id
     */
    public static function updateMerchantLowestAmount($merchant_id)
    {
        $merchant = CsMerchant::findOrFail($merchant_id);
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
        $goodsLowestAmount = CsGood::where('merchant_id', $merchantId)
                ->where('status', CsGood::STATUS_ON)
                ->orderBy('price')
                ->value('price') ?? 0;

        return $goodsLowestAmount;
    }

    /**
     * 获取商家列表可以计算距离
     * @param array $params
     * @return array
     */
    public static function getListAndDistance(array $params = [])
    {
        $city_id = array_get($params, 'city_id');
        $lng = array_get($params,'lng');
        $lat = array_get($params, 'lat');

        // 暂时去掉商户列表中的距离限制
        $radius = array_get($params, 'radius');
        $radius = $radius == 200000 ? 0 : $radius;
        // 价格搜索
        $lowestPrice = array_get($params,'lowest_price', 0);
        $highestPrice = array_get($params, 'highest_price', 0);
        if ($lowestPrice && $highestPrice && $lowestPrice > $highestPrice){
            throw new BaseResponseException('搜索条件的最低价格不能高于最高价格');
        }
        $onlyPayToPlatform = array_get($params, 'onlyPayToPlatform', 0);

        $distances = null;
        if($lng && $lat && $radius){
            // 如果经纬度及范围都存在, 则按距离筛选出附近的商家
            $distances = Lbs::getNearlyMerchantDistanceByGps($lng, $lat, $radius);
        }
        $user_key = array_get($params, 'user_key', 0); //终端的唯一表示，用于计算距离
        $current_oper_id = array_get($params, 'current_oper_id', 0); //是否只看当前运营中心的商户

        //只能查询切换到平台的商户
        $query = CsMerchant::where('status', 1)
            ->where('oper_id', '>', 0)
            ->when($current_oper_id, function (Builder $query) use ($current_oper_id) {
                $query->where('oper_id', $current_oper_id);
            })
            ->when($onlyPayToPlatform, function (Builder $query) {
                $query->whereHas('oper', function(Builder $query){
                    $query->whereIn('pay_to_platform', [ Oper::PAY_TO_PLATFORM_WITHOUT_SPLITTING, Oper::PAY_TO_PLATFORM_WITH_SPLITTING ]);
                });
            })
            ->whereIn('audit_status', [CsMerchant::AUDIT_STATUS_SUCCESS, CsMerchant::AUDIT_STATUS_RESUBMIT])
            ->when($city_id, function(Builder $query) use ($city_id){
                // 特殊城市，如澳门。属于省份，要显示下属所有城市的商户
                $areaInfo = Area::where('area_id', $city_id)->where('path', 1)->first();
                if (empty($areaInfo)) {
                    $query->where(function(Builder $query)use ($city_id){
                        $query->where('city_id', $city_id)
                            ->orWhere('area_id', $city_id);
                    });
                } else {
                    $cityIdArray = Area::where('parent_id', $city_id)
                        ->where('path', 2)
                        ->select('area_id')
                        ->get()
                        ->pluck('area_id');
                    $query->whereIn('city_id', $cityIdArray);
                }
            })
            ->when($lng && $lat && $radius, function (Builder $query) use ($distances) {
                // 如果范围存在, 按距离搜索, 并按距离排序
                $query->whereIn('id', array_keys($distances));
            })
            ->when($lowestPrice || $highestPrice, function (Builder $query) use ($lowestPrice, $highestPrice){
                // 有价格限制时 按照价格区间筛选 并按照价格排序
                $query->when($lowestPrice && !$highestPrice, function (Builder $query) use ($lowestPrice) {
                    $query->where('lowest_amount', '>=', $lowestPrice);
                })
                    ->when($highestPrice, function (Builder $query) use ($lowestPrice, $highestPrice) {
                        $query->where('lowest_amount', '>=', $lowestPrice)
                            ->where('lowest_amount', '<', $highestPrice);
                    })
                    ->orderBy('lowest_amount');
            });

        if($lng && $lat){
            // 如果是按距离搜索, 需要在程序中按距离排序
            $allList = $query->select('id')->get();
            $total = $query->count();
            $list = $allList->map(function ($item) use ($lng, $lat, $user_key) {
                $item->distance = Lbs::getDistanceOfMerchant($item->id, $user_key, floatval($lng), floatval($lat));

                return $item;
            })
                ->sortBy('distance')
                ->forPage(request('page', 1), 15)
                ->values()
                ->map(function($item) {
                    if ($item->is_pilot == 1) {
                        $item->distance -= 100000000;
                    }
                    $item->distance = Utils::getFormativeDistance($item->distance);
                    $merchant = DataCacheService::getMerchantDetail($item->id);
                    $merchant->distance = $item->distance;
                    // 格式化距离
                    return $merchant;
                });
        }else {
            // 没有按距离搜索时, 直接在数据库中排序并分页
            $data = $query->paginate();
            $list = $data->items();
            $total = $data->total();
        }

        // 补充商家其他信息
        $list = collect($list);
        $list->each(function ($item){
            $item->desc_pic_list = $item->desc_pic_list ? explode(',', $item->desc_pic_list) : [];
            if($item->business_time) $item->business_time = json_decode($item->business_time, 1);
            // 最低消费
            $item->lowestAmount = CsMerchantService::getLowestPriceForMerchant($item->id);
            // 兼容v1.0.0版客服电话字段
            $item->contacter_phone = $item->service_phone;
            // 商户评级字段，暂时全部默认为5星
            $item->grade = 5;
            // 首页商户列表，显示价格最低的n个团购商品
            $item->lowestGoods = CsGoodService::getLowestPriceGoodsForMerchant($item->id, 2);
        });

        return ['list' => $list, 'total' => $total];
    }

    public static function userAppMerchantDetial($data)
    {
        $id = array_get($data,'id');
        $lng = array_get($data,'lng');
        $lat = array_get($data,'lat');

        $detail = CsMerchant::findOrFail($id);
        $detail->desc_pic_list = $detail->desc_pic_list ? explode(',', $detail->desc_pic_list) : [];
        if($detail->business_time) $detail->business_time = json_decode($detail->business_time, 1);
        if($lng && $lat){
            $currentUser = request()->get('current_user');
            $tempToken = empty($currentUser) ? str_random() : $currentUser->id;
            $distance = Lbs::getDistanceOfMerchant($id, $tempToken, floatval($lng), floatval($lat));
            // 格式化距离
            $detail->distance = Utils::getFormativeDistance($distance);
        }
        // 最低消费
        $detail->lowestAmount = CsMerchantService::getLowestPriceForMerchant($detail->id);
        // 兼容v1.0.0版客服电话字段
        $detail->contacter_phone = $detail->service_phone;

        return $detail;
    }

    /**
     * 根据商户名称获取商户某个字段的数组
     * @param $params
     * @param $field
     * @return Collection
     */
    public static function getMerchantColumnArrayByParams($params, $field)
    {
        $merchantName = array_get($params, 'merchantName', '');
        $operIds = array_get($params, 'operIds', []);

        $query = CsMerchant::query();
        if ($merchantName) {
            $query->where('name', 'like', "%$merchantName%");
        }
        if (!empty($operIds)) {
            if (is_array($operIds)) {
                $query->whereIn('oper_id', $operIds);
            } else {
                $query->where('oper_id', $operIds);
            }
        }
        $arr = $query->select($field)
            ->get()
            ->pluck($field);
        return $arr;
    }
}