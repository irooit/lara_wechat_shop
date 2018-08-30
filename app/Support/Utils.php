<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/12
 * Time: 14:31
 */

namespace App\Support;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Utils
{

    public static function convertListToTree($list, $pid=0, $tier = null, $pidKey = 'pid', $idKey = 'id')
    {
        if(!is_null($tier)) $tier--;
        $tree = [];
        foreach ($list as &$item) {
            if($item->{$pidKey} == $pid){
                if(is_null($tier) || $tier > 0){
                    $sub = self::convertListToTree($list, $item->{$idKey}, $tier, $pidKey, $idKey);
                    if(count($sub) > 0){
                        $item->sub = $sub;
                    }
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    public static function getRequestContext(Request $request)
    {
        $attributes = $request->attributes->all();
        foreach ($attributes as $key => $attribute) {
            if($attribute instanceof Model){
                $attributes[$key] = $attribute->toArray();
            }
        }

        $data = [
            'ip' => $request->ip(),
            'fullUrl' => $request->fullUrl(),
            'header' => $request->header(),
            'params' => $request->all(),
            'attributes' => $attributes,
        ];
        if($request->hasSession()){
            $data['session'] = $request->session()->all();
        }
        return $data;
    }

    /**
     * 获取半隐藏的手机号
     * @param $mobile
     * @return string
     */
    public static function getHalfHideMobile($mobile)
    {
        return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
    }

    /**
     * 保留n位小数 且 不四舍五入
     * @param $number
     * @param int $decimalQuantity
     * @return float|int
     */
    public static function getDecimalByNotRounding($number, $decimalQuantity = 2)
    {
        if ($decimalQuantity < 0) $decimalQuantity = 2;
        $pow = pow(10, $decimalQuantity);
        $decimal = floor($number * $pow) / $pow;
        return $decimal;
    }
}