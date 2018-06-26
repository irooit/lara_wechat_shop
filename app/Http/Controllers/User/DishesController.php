<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/5/27
 * Time: 16:46
 */

namespace App\Http\Controllers\User;

use App\Exceptions\BaseResponseException;
use App\Exceptions\ParamInvalidException;
use App\Http\Controllers\Controller;
use App\Modules\Dishes\Dishes;
use App\Modules\Dishes\DishesCategory;
use App\Modules\Dishes\DishesGoods;
use App\Modules\Dishes\DishesItem;
use App\Modules\Goods\Goods;
use App\Modules\Merchant\Merchant;
use App\Modules\Merchant\MerchantSettingService;
use App\Result;

class DishesController extends Controller
{
    /**
     * 判断单品购买功能是否开启
     * MerchantDishesController constructor.
     */
    public function __construct()
    {
        $merchantId = request('merchant_id');
        if (!$merchantId){
            throw new BaseResponseException('商户ID不能为空');
        }
        $result = MerchantSettingService::getValueByKey($merchantId, 'dishes_enabled');
        if (!$result){
            throw new BaseResponseException('单品购买功能尚未开启！');
        }
    }

    /**
     * 获取单品分类
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getDishesCategory()
    {

        $merchantId = request('merchant_id');
        $categorys =DishesCategory::has('dishesGoods')
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->orderBy('sort')
            ->get();
        return Result::success([
            'list' => $categorys
        ]);
    }


    /**
     * 获取热门菜品
     *
     */
    public function getHotDishesGoods()
    {

        $merchantId = request('merchant_id');
        $hotDishesGoods =DishesGoods::where('merchant_id', $merchantId)
            ->where('status', 1)
            ->where('is_hot',1)
            ->get();
        return Result::success([
            'list' => $hotDishesGoods
        ]);
    }


    /**
     * 获取单品指定分类的商品
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getDishesGoods()
    {
        $merchantId = request('merchant_id');
        $categoryId = request('category_id');
        if (!$categoryId){
            throw new BaseResponseException('分类ID不能为空');
        }
        $list = DishesGoods::where('merchant_id', $merchantId)
            ->where('status', 1)
            ->where('dishes_category_id',$categoryId)
            ->get();

         return Result::success([
             'list' => $list,
         ]);

    }


   //点菜操作

    public function add()
    {
        $userId = request()->get('current_user')->id;
        $this->validate(request(), [
            'merchant_id' => 'required|integer|min:1',
        ]);
        $dishesList = request('goods_list');
        if(is_string($dishesList)){
            $dishesList = json_decode($dishesList,true);
        }

        $merchantId = request('merchant_id');
        if (empty($dishesList)){
            throw new ParamInvalidException('单品列表为空');
        }
        if(sizeof($dishesList) < 1){
            throw new ParamInvalidException('参数不合法1');
        }
        foreach ($dishesList as $item) {
            if(!isset($item['id']) || !isset($item['number'])){
                throw new ParamInvalidException('参数不合法2');
            }
            $dishesGoods = DishesGoods::findOrFail($item['id']);
            if ($dishesGoods->status == DishesGoods::STATUS_OFF){
                throw new BaseResponseException('菜单已变更, 请刷新页面');
            }
        }
        $merchant = Merchant::findOrFail($merchantId);
        $dishes = new Dishes();
        $dishes->oper_id = $merchant->oper_id;
        $dishes->merchant_id = $merchant->id;
        $dishes->user_id = $userId;
        $dishes->save();

        foreach ($dishesList as $item){
            $dishesGoods = DishesGoods::findOrFail($item['id']);
            if ($dishesGoods['oper_id'] !== $merchant->oper_id){
                continue;
            }
            $dishesItem = new DishesItem();
            $dishesItem->oper_id = $merchant->oper_id;
            $dishesItem->merchant_id = $merchant->id;
            $dishesItem->dishes_id = $dishes->id;
            $dishesItem->user_id = $userId;
            $dishesItem->dishes_goods_id = $item['id'];
            $dishesItem->number = $item['number'];
            $dishesItem->dishes_goods_sale_price = $dishesGoods['sale_price'];
            $dishesItem->dishes_goods_logo = $dishesGoods['logo'];
            $dishesItem->dishes_goods_name = $dishesGoods['name'];
            $dishesItem->save();
        }
        return Result::success($dishes);
    }

    /**
     * 点菜的菜单详情
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function detail()
    {
        $this->validate(request(), [
            'dishes_id' => 'required|integer|min:1',
        ]);

        $list = DishesItem::where('dishes_id',request('dishes_id'))->get();
        $detailDishes = [];
        foreach ($list as  $k=>$item){
            $detailDishes[$k]['dishes_goods_name'] = $item->dishes_goods_name;
            $detailDishes[$k]['number'] = $item->number;
            $detailDishes[$k]['total_price'] = ($item->number)*($item->dishes_goods_sale_price);
            $detailDishes[$k]['dishes_goods_logo'] = $item->dishes_goods_logo;
            $detailDishes[$k]['user_id'] = $item->user_id;
            $detailDishes[$k]['oper_id'] = $item->oper_id;
        }

        return Result::success($detailDishes);
    }





}