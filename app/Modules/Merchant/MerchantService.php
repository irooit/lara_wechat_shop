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
use App\Modules\Oper\OperBizer;
use App\Modules\Oper\OperBizerService;
use App\Modules\Oper\OperBizMember;
use App\Result;
use App\Support\Lbs;
use Illuminate\Database\Eloquent\Builder;
use App\Modules\Area\Area;
use Illuminate\Support\Collection;
use App\Modules\Setting\SettingService;
use App\Modules\Goods\GoodsService;

use App\Modules\Oper\MyOperBizer;


class MerchantService extends BaseService
{

    /**
     * 根据ID获取商户信息
     * @param $merchantId
     * @param array|string $fields
     * @return Merchant
     */
    public static function getById($merchantId, $fields = ['*'])
    {
        if(is_string($fields)){
            $fields = explode(',', $fields);
        }
        return Merchant::find($merchantId, $fields);
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
        $value = Merchant::where('id', $merchantId)->value('signboard_name');
        return $value;
    }

    /**
     * @param array $data
     * @return Merchant[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getAllNames(array $data)
    {
        // todo 后期可以对查询结果做缓存
        $auditStatus = array_get($data, 'audit_status');
        $status =array_get($data, 'status');
        $operId =array_get($data, 'operId'); //运营中心ID
        $bizer_id = array_get($data, 'bizer_id');//业务员ID
//        $list = Merchant::where(function (Builder $query) use ($operId) {
//            $query->where('oper_id', $operId)
//                ->orWhere('audit_oper_id', $operId);
//        })
        $list = Merchant::select('id', 'name')
            ->when(!empty($operId), function (Builder $query) use ($operId) {
                $query->where('oper_id', $operId)
                ->orWhere('audit_oper_id', $operId);
            })
            ->when($status, function (Builder $query) use ($status) {
                $query->where('status', $status);
            })
            ->when(!empty($bizer_id), function (Builder $query) use ($bizer_id) {
                $query->where('bizer_id', $bizer_id);
            })
            ->when(!empty($auditStatus), function (Builder $query) use ($auditStatus) {
                if ($auditStatus == -1) {
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
    public static function getList(array $data, bool $getWithQuery = false)
    {
        $id = array_get($data,'id');
        $operId = array_get($data,'operId');
        $creatorOperId = array_get($data,'creatorOperId');
        $name = array_get($data,'name');
        $signboardName = array_get($data,'signboardName');
        $status = array_get($data,'status');
        $auditStatus = array_get($data,'auditStatus');
        $merchantCategory = array_get($data,'merchantCategory');
        $isPilot = array_get($data,'isPilot');
        $startCreatedAt = array_get($data,'startCreatedAt');
        $endCreatedAt = array_get($data,'endCreatedAt');

        $cityId =array_get($data,"cityId");
        $bizer_id = array_get($data, "bizer_id");

        // 全局限制条件
        $query = Merchant::where('audit_oper_id', '>', 0)
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
        if($bizer_id){
             $query->where('bizer_id', $bizer_id);
        }

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
        if ($status) {
            $query->where('status', $status);
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
        if ($signboardName) {
            $query->where('signboard_name', 'like', "%$signboardName%");
        }
        if (!empty($merchantCategory)) {
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
        }
        if ($isPilot) {
            $query->where('is_pilot', Merchant::PILOT_MERCHANT);
        } else {
            $query->where('is_pilot', Merchant::NORMAL_MERCHANT);
        }

        if ($getWithQuery) {
            return $query;
        } else {

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
                if ($item->bizer_id) {
                    $operBizer = OperBizerService::getOperBizerByParam([
                        'operId' => $item->oper_id > 0 ? $item->oper_id : $item->audit_oper_id,
                        'bizerId' => $item->bizer_id,
                    ]);
                    $item->divide = empty($operBizer) ? 0 : $operBizer->divide;
                }
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

        $userId = request()->get('current_user')->id;
        $merchant = Merchant::findOrFail($id);
        $merchant->categoryPath = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id);
        $merchant->categoryPathText = '';
        foreach ($merchant->categoryPath as $item) {
            $merchant->categoryPathText .= $item->name . ' ';
        }
        $merchant->categoryPathOnlyEnable = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id, true);
        $merchant->account = MerchantAccount::where('merchant_id', $merchant->id)->first();
        $merchant->business_time = json_decode($merchant->business_time, 1);
        $merchant->desc_pic_list = $merchant->desc_pic_list ? explode(',', $merchant->desc_pic_list) : '';
        $merchant->contract_pic_url = $merchant->contract_pic_url ? explode(',', $merchant->contract_pic_url) : '';
        $merchant->other_card_pic_urls = $merchant->other_card_pic_urls ? explode(',', $merchant->other_card_pic_urls) : '';
        $merchant->bank_card_pic_a = $merchant->bank_card_pic_a ? explode(',', $merchant->bank_card_pic_a) : '';

        $merchant->operName = Oper::where('id', $merchant->oper_id > 0 ? $merchant->oper_id : $merchant->audit_oper_id)->value('name');
        $merchant->creatorOperName = Oper::where('id', $merchant->creator_oper_id)->value('name');
        if ($merchant->oper_biz_member_code) {
            $merchant->operBizMemberName = OperBizMember::where('code', $merchant->oper_biz_member_code)->value('name');
        }
        $oper = Oper::where('id', $merchant->oper_id > 0 ? $merchant->oper_id : $merchant->audit_oper_id)->first();
        if ($oper) {
            $merchant->operAddress = $oper->province . $oper->city . $oper->area . $oper->address;
        }
        $merchantFollow = MerchantFollow::where('user_id',$userId)->where('merchant_id',$id);
        if($merchantFollow){
            $merchant->user_follow_status = 2;
        }else{
            $merchant->user_follow_status =1;
        }
        return $merchant;
    }

    /**
     * 编辑商户
     * @param $id
     * @param $currentOperId
     * @return Merchant
     */
    public static function edit($id,$currentOperId)
    {

        $merchant = Merchant::where('id', $id)
            ->where('audit_oper_id', $currentOperId)
            ->first();
        if (empty($merchant)) {
            throw new BaseResponseException('该商户不存在');
        }

        if (!empty($merchant->bizer_id)) {
            // 记录原业务员ID
            $originOperBizMemberCode = $merchant->bizer_id;
        }

        $merchant->fillMerchantPoolInfoFromRequest();
        $merchant->fillMerchantActiveInfoFromRequest();

        // 商户名不能重复

        $exists = Merchant::where('name', $merchant->name)
            ->where('id', '<>', $merchant->id)->first();
        $existsDraft = MerchantDraft::where('name', $merchant->name)->first();
        if ($exists || $existsDraft) {
            throw new ParamInvalidException('商户名称不能重复');
        }
        // 招牌名不能重复
        $exists = Merchant::where('signboard_name', $merchant->signboard_name)
            ->where('id', '<>', $merchant->id)->first();
        $existsDraft = MerchantDraft::where('signboard_name', $merchant->signboard_name)->first();
        if ($exists || $existsDraft) {
            throw new ParamInvalidException('招牌名称不能重复');
        }

        if ($merchant->oper_id > 0) {
            // 如果当前商户已有所属运营中心,且不是试点商户, 则此次提交为重新提交审核
            // 如果当前商户已有所属运营中心，且是试点商户，且有商户描述，则是补全资料提交，修改审核状态为待审核
            // 添加审核记录
            if ($merchant->is_pilot && $merchant->desc) {
                $merchant->oper_id = 0;
                $merchant->is_pilot = Merchant::NORMAL_MERCHANT;
                $merchant->audit_status = Merchant::AUDIT_STATUS_AUDITING;
            } else {
                MerchantAuditService::addAudit($merchant->id, $currentOperId, Merchant::AUDIT_STATUS_RESUBMIT);
                $merchant->audit_status = Merchant::AUDIT_STATUS_RESUBMIT;
            }
        } else {
            // 如果当前商户没有所属运营中心，且是试点商户，且有商户描述，则是待审核状态下的补全资料，修改商户为正常商户
            if ($merchant->is_pilot && $merchant->desc) {
                $merchant->is_pilot = Merchant::NORMAL_MERCHANT;
                $merchant->audit_status = Merchant::AUDIT_STATUS_AUDITING;
            } else {
                MerchantAuditService::addAudit($merchant->id, $currentOperId);
                $merchant->audit_status = Merchant::AUDIT_STATUS_AUDITING;
            }
        }

        $merchant->save();

        // 更新业务员已激活商户数量
        if ($merchant->bizer_id) {

            MyOperBizer::updateActiveMerchantNumberByCode($merchant->bizer_id);
            MyOperBizer::updateAuditMerchantNumberByCode($merchant->bizer_id);

            //OperBizMember::updateActiveMerchantNumberByCode($merchant->oper_biz_member_code);
            //OperBizMember::updateAuditMerchantNumberByCode($merchant->oper_biz_member_code);
        }

        // 如果存在原有的业务员, 并且不等于现有的业务员, 更新原有业务员邀请用户数量
        if (isset($originOperBizMemberCode) && $originOperBizMemberCode != $merchant->bizer_id) {
            MyOperBizer::updateActiveMerchantNumberByCode($originOperBizMemberCode);
            MyOperBizer::updateAuditMerchantNumberByCode($originOperBizMemberCode);
            //OperBizMember::updateActiveMerchantNumberByCode($originOperBizMemberCode);
            //OperBizMember::updateAuditMerchantNumberByCode($originOperBizMemberCode);
        }

        return $merchant;
    }

    /**
     * @param $currentOperId
     * 添加商户
     * @return Merchant
     */
    public static function add($currentOperId)
    {
        $merchant = new Merchant();
        $merchant->fillMerchantPoolInfoFromRequest();
        $merchant->fillMerchantActiveInfoFromRequest();

        // 补充商家创建者及审核提交者
        $merchant->audit_oper_id = $currentOperId;
        $merchant->creator_oper_id = $currentOperId;

        // 商户名不能重复
        $exists = Merchant::where('name', $merchant->name)->first();
        $existsDraft = MerchantDraft::where('name', $merchant->name)->first();
        if ($exists || $existsDraft) {
            throw new ParamInvalidException('商户名称不能重复');
        }
        // 招牌名不能重复
        $exists = Merchant::where('signboard_name', $merchant->signboard_name)->first();
        $existsDraft = MerchantDraft::where('signboard_name', $merchant->signboard_name)->first();
        if ($exists || $existsDraft) {
            throw new ParamInvalidException('招牌名称不能重复');
        }

        $merchant->save();

        // 添加审核记录
        MerchantAuditService::addAudit($merchant->id, $currentOperId);

        // 更新业务员已激活商户数量
        if ($merchant->bizer_id) {
            //OperBizMember::updateActiveMerchantNumberByCode($merchant->oper_biz_member_code);
            MyOperBizer::updateActiveMerchantNumberByCode($merchant->bizer_id);
        }

        return $merchant;
    }

    public static function addFromDraft()
    {
        // todo 从草稿箱添加
    }

    /**
     * 从商户池添加商户信息
     * @param $operId
     * @param Merchant $merchant
     * @return Merchant
     */
    public static function addFromMerchantPool($operId, Merchant $merchant)
    {
        // 补充激活商户需要的信息
        $merchant->fillMerchantActiveInfoFromRequest();
        // 设置当前商户提交审核的运营中心
        $merchant->audit_oper_id = $operId;
        $merchant->audit_status = Merchant::AUDIT_STATUS_AUDITING;

        // 商户名不能重复
        $exists = Merchant::where('name', $merchant->name)
            ->where('id', '<>', $merchant->id)->first();
        if($exists){
            throw new ParamInvalidException('商户名称不能重复');
        }

        $merchant->save();
        // 添加审核记录
        MerchantAuditService::addAudit($merchant->id, $operId);

        // 更新业务员已激活商户数量
        if($merchant->bizer_id){
            //OperBizMember::updateActiveMerchantNumberByCode($merchant->oper_biz_member_code);
            MyOperBizer::updateActiveMerchantNumberByCode($merchant->bizer_id);
        }

        return $merchant;
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
        Merchant::chunk(100, function (Collection $list) {
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

    public static function getListForUserApp(array $params, bool $isFollows = false)
    {
        $city_id = array_get($params, 'city_id');
        $merchant_category_id = array_get($params, 'merchant_category_id');
        $keyword = array_get($params, 'keyword');
        $lng = array_get($params,'lng');
        $lat = array_get($params, 'lat');

        // 暂时去掉商户列表中的距离限制
        $radius = request('radius');
        $radius = $radius == 200000 ? 0 : $radius;
        // 价格搜索
        $lowestPrice = request('lowest_price', 0);
        $highestPrice = request('highest_price', 0);
        if ($lowestPrice && $highestPrice && $lowestPrice > $highestPrice){
            throw new BaseResponseException('搜索条件的最低价格不能高于最高价格');
        }

        $distances = null;
        if($lng && $lat && $radius){
            // 如果经纬度及范围都存在, 则按距离筛选出附近的商家
            $distances = Lbs::getNearlyMerchantDistanceByGps($lng, $lat, $radius);
        }
        $userId = request()->get('current_user')->id ?? 0;

        // todo 只获取切换到平台的运营中心下的商家信息
        //只能查询切换到平台的商户
        $query = Merchant::query()
            ->where('oper_id', '>', 0)
            ->where('status', 1)
            ->when($isFollows, function (Builder $query) use ($userId){
                $query->whereHas('merchantFollow',function (Builder $q) use ($userId) {
                    $q->where('user_id',$userId)
                        ->where('status',MerchantFollow::USER_YES_FOLLOW);
                });
            })
            ->whereHas('oper', function(Builder $query){
                $query->whereIn('pay_to_platform', [ Oper::PAY_TO_PLATFORM_WITHOUT_SPLITTING, Oper::PAY_TO_PLATFORM_WITH_SPLITTING ]);
            })
            ->whereIn('audit_status', [Merchant::AUDIT_STATUS_SUCCESS, Merchant::AUDIT_STATUS_RESUBMIT])
            ->when($city_id, function(Builder $query) use ($city_id){
                // 特殊城市，如澳门。属于省份，要显示下属所有城市的商户
                $areaInfo = Area::where('area_id', $city_id)->where('path', 1)->first();
                if (empty($areaInfo)) {
                    $query->where('city_id', $city_id);
                } else {
                    $cityIdArray = Area::where('parent_id', $city_id)
                        ->where('path', 2)
                        ->select('area_id')
                        ->get()
                        ->pluck('area_id');
                    $query->whereIn('city_id', $cityIdArray);
                }
            })
            ->when(!$merchant_category_id && $keyword, function(Builder $query) use ($keyword){
                // 不传商家类别id且关键字存在时, 若关键字等同于类别, 则搜索该类别以及携带该关键字的商家
                $category = MerchantCategory::where('name', $keyword)->first();
                if($category){
                    $query->where(function(Builder $query) use ($keyword,$category) {
                        $query->where('merchant_category_id', $category->id)
                            ->orWhere('name', 'like', "%$keyword%")
                            ->orWhere('signboard_name', 'like', "%$keyword%");
                    });
                }else {
                    $query->where(function (Builder $query) use ($keyword){
                        $query->where('name', 'like', "%$keyword%")
                            ->orWhere('signboard_name', 'like', "%$keyword%");
                    });
                }
            })
            ->when($merchant_category_id && $keyword, function(Builder $query) use ($merchant_category_id, $keyword){
                // 如果传了类别及关键字, 则类别和关键字都搜索
                $merchantCategorySubArray = MerchantCategoryService::getSubCategoryIds($merchant_category_id);
                $query->where(function (Builder $query) use ($keyword) {
                    $query->where('name', 'like', "%$keyword%")
                        ->orWhere('signboard_name', 'like', "%$keyword%");
                })
                    ->when($merchantCategorySubArray, function (Builder $query) use ($merchantCategorySubArray) {
                        $query->whereIn('merchant_category_id', $merchantCategorySubArray);
                    })
                    ->when(!$merchantCategorySubArray, function (Builder $query) use ($merchant_category_id) {
                        $query->where('merchant_category_id', $merchant_category_id);
                    });
            })
            ->when($merchant_category_id && empty($keyword), function(Builder $query) use ($merchant_category_id, $keyword){
                // 如果只传了类别, 没有关键字
                $merchantCategorySubArray = MerchantCategoryService::getSubCategoryIds($merchant_category_id);
                $query->when($merchantCategorySubArray, function (Builder $query) use ($merchantCategorySubArray) {
                    $query->whereIn('merchant_category_id', $merchantCategorySubArray);
                })
                    ->when(!$merchantCategorySubArray, function (Builder $query) use ($merchant_category_id) {
                        $query->where('merchant_category_id', $merchant_category_id);
                    });
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
            $allList = $query->get();
            $total = $query->count();
            $list = $allList->map(function ($item) use ($lng, $lat) {
                $item->distance = Lbs::getDistanceOfMerchant($item->id, request()->get('current_open_id'), floatval($lng), floatval($lat));
                return $item;
            })
                ->sortBy('distance')
                ->forPage(request('page', 1), 15)
                ->values()
                ->each(function($item) {
                    // 格式化距离
                    $item->distance = self::_getFormativeDistance($item->distance);
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
            $category = MerchantCategory::find($item->merchant_category_id);
            $item->merchantCategoryName = $category->name;
            // 最低消费
            $item->lowestAmount = MerchantService::getLowestPriceForMerchant($item->id);
            // 兼容v1.0.0版客服电话字段
            $item->contacter_phone = $item->service_phone;
            // 商户评级字段，暂时全部默认为5星
            $item->grade = 5;
            // 首页商户列表，显示价格最低的n个团购商品
            $item->lowestGoods = GoodsService::getLowestPriceGoodsForMerchant($item->id, 2);
        });

        return Result::success(['list' => $list, 'total' => $total]);
    }

    public static function userAppMerchantDetial($data)
    {
        $id = array_get($data,'id');
        $lng = array_get($data,'lng');
        $lat = array_get($data,'lat');

        $detail = Merchant::findOrFail($id);
        $detail->desc_pic_list = $detail->desc_pic_list ? explode(',', $detail->desc_pic_list) : [];
        if($detail->business_time) $detail->business_time = json_decode($detail->business_time, 1);
        if($lng && $lat){
            $currentUser = request()->get('current_user');
            $tempToken = empty($currentUser) ? str_random() : $currentUser->id;
            $distance = Lbs::getDistanceOfMerchant($id, $tempToken, floatval($lng), floatval($lat));
            // 格式化距离
            $detail->distance = self::_getFormativeDistance($distance);
        }
        $category = MerchantCategory::find($detail->merchant_category_id);
        $detail->merchantCategoryName = $category->name;
        // 最低消费
        $detail->lowestAmount = MerchantService::getLowestPriceForMerchant($detail->id);
        // 兼容v1.0.0版客服电话字段
        $detail->contacter_phone = $detail->service_phone;

        return $detail;
    }

    public static function _getFormativeDistance($distance)
    {
        return $distance >= 1000 ? (number_format($distance / 1000, 1) . '千米') : ($distance . '米');
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

        $query = Merchant::query();
        if ($merchantName) {
            $query->where('name', 'like', "%$merchantName%");
        }
        if (!empty($operIds)) {
            $query->whereIn('oper_id', $operIds);
        }
        $arr = $query->select($field)
            ->get()
            ->pluck($field);
        return $arr;
    }
}