<?php
/**
 * Created by PhpStorm.
 * User: 57458
 * Date: 2018/7/23
 * Time: 17:38
 */

namespace App\Modules\Oper;


use App\BaseService;
use App\Exceptions\BaseResponseException;
use App\Exceptions\DataNotFoundException;
use App\Modules\Area\Area;
use Illuminate\Database\Eloquent\Builder;

class OperService extends BaseService
{

    /**
     * 获取运营中心列表
     * @param $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getList($params)
    {
        $name = array_get($params, 'name');
        $status = array_get($params, 'status');
        $tel = array_get($params, 'tel');

        $data = Oper::when($status, function (Builder $query) use ($status){
            $query->where('status', $status);
        })
            ->when($name, function(Builder $query) use ($name){
                $query->where('name', 'like', "%$name%");
            })
            ->when($tel, function(Builder $query) use ($tel){
                $query->where('tel', 'like', "%$tel%");
            })
            ->orderBy('id', 'desc')
            ->paginate();

        $data->each(function ($item){
            $item->account = OperAccountService::getByOperId($item->id) ?: null;
            $item->miniprogram = OperMiniprogramService::getByOperId($item->id) ?: null;
        });

        return $data;
    }

    /**
     * 获取运营中心详情
     * @param $id
     * @return Oper
     */
    public static function detail($id)
    {
        $oper = Oper::findOrFail($id);
        $oper->account = OperAccountService::getByOperId($oper->id) ?: null;
        $oper->miniprogram = OperMiniprogramService::getByOperId($oper->id) ?: null;
        return $oper;
    }

    /**
     * 添加运营中心
     * @return Oper
     */
    public static function addFromRequest()
    {
        $oper = new Oper();
        $oper->name = request('name');
        $oper->status = request('status', 1);
        $oper->contacter = request('contacter', '');
        $oper->tel = request('tel', '');
        $provinceId = request('province_id', 0);
        $cityId = request('city_id', 0);
        $oper->province_id = $provinceId;
        $oper->city_id = $cityId;
        $oper->province = Area::where('area_id', $provinceId)->value('name');
        $oper->city = Area::where('area_id', $cityId)->value('name');
        $oper->address = request('address', '');
        $oper->email = request('email', '');
        $oper->legal_name = request('legal_name', '');
        $oper->legal_id_card = request('legal_id_card', '');
        $oper->invoice_type = request('invoice_type', 0);
        $oper->invoice_tax_rate = request('invoice_tax_rate', 0);
//        $oper->settlement_cycle_type = request('settlement_cycle_type', 1);
        $oper->bank_card_no = request('bank_card_no', '');
        $oper->sub_bank_name = request('sub_bank_name', '');
        $oper->bank_open_name = request('bank_open_name', '');
        $oper->bank_open_address = request('bank_open_address', '');
        $oper->bank_code = request('bank_code', '');
        $oper->licence_pic_url = request('licence_pic_url', '');
        $oper->business_licence_pic_url = request('business_licence_pic_url', '');

        $oper->save();
        return $oper;
    }

    /**
     * @param $id
     * @return Oper
     */
    public static function editFromRequest($id)
    {

        $oper = Oper::find($id);
        if(empty($oper)){
            throw new DataNotFoundException('运营中心信息不存在');
        }
        $oper->name = request('name');
        $oper->status = request('status', 1);
        $oper->contacter = request('contacter', '');
        $oper->tel = request('tel', '');
        $provinceId = request('province_id', 0);
        $cityId = request('city_id', 0);
        $oper->province_id = $provinceId;
        $oper->city_id = $cityId;
        $province = Area::where('area_id', $provinceId)->value('name');
        if (!$province) {
            throw new BaseResponseException('请选择省份');
        }
        $oper->province = $province;
        $city = Area::where('area_id', $cityId)->value('name');
        if (!$city) {
            throw new BaseResponseException('请选择城市');
        }
        $oper->city = $city;
        $oper->address = request('address', '');
        $oper->email = request('email', '');
        $oper->legal_name = request('legal_name', '');
        $oper->legal_id_card = request('legal_id_card', '');
        $oper->invoice_type = request('invoice_type', 0);
        $oper->invoice_tax_rate = request('invoice_tax_rate', 0);
//        $oper->settlement_cycle_type = request('settlement_cycle_type', 1);
        $oper->bank_card_no = request('bank_card_no', '');
        $oper->sub_bank_name = request('sub_bank_name', '');
        $oper->bank_open_name = request('bank_open_name', '');
        $oper->bank_open_address = request('bank_open_address', '');
        $oper->bank_code = request('bank_code', '');
        $oper->licence_pic_url = request('licence_pic_url', '');
        $oper->business_licence_pic_url = request('business_licence_pic_url', '');

        $oper->save();

        return $oper;
    }

    /**
     * 更新状态
     * @param $id
     * @param $status
     * @return Oper
     */
    public static function changeStatus($id, $status)
    {

        $oper = Oper::find($id);
        if(empty($oper)){
            throw new DataNotFoundException('运营中心信息不存在');
        }
        $oper->status = $status;

        $oper->save();
        return $oper;
    }

    /**
     * 切换运营中心支付到平台
     * @param $id
     * @return Oper
     */
    public static function switchPayToPlatform($id)
    {
        $oper = Oper::find($id);
        if(empty($oper)){
            throw new DataNotFoundException('运营中心信息不存在');
        }
        $oper->pay_to_platform = 1;
        $oper->save();
        return $oper;
    }
}