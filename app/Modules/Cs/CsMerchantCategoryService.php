<?php
/**
 * Created by PhpStorm.
 * User: tim.tang
 * Date: 2018/11/20/020
 * Time: 18:25
 */
namespace App\Modules\Cs;

use App\BaseService;

class CsMerchantCategoryService extends BaseService
{
    /**
     * 获取商户的子分类
     * @param int $cs_merchant_id
     * @param int $parent_id
     * @return array
     */
    public static function getSubCat(int $cs_merchant_id, int $parent_id=0)
    {

        if ($cs_merchant_id <= 0) {
            return [];
        }

        $rs = CsMerchantCategory::where('cs_category_parent_id','=',$parent_id)
            ->where('cs_merchant_id','=',$cs_merchant_id)
            ->get();
        $rt = [];
        if ($rs) {
            foreach ($rs as $v) {
                $rt[$v['platform_category_id']] = $v['cs_cat_name'];
            }
        }

        return $rt;

    }

    /**
     * 同步平台分类
     * @param int $cs_merchant_id
     * @return bool
     */
    public static function synPlatFormCat(int $cs_merchant_id)
    {

        if ($cs_merchant_id <= 0) {
            return false;
        }
        $platform_cat = CsPlatformCategoryService::getAll();
        if (empty($platform_cat)) {
            return false;
        }

        foreach ($platform_cat as $cat) {

            $where['cs_merchant_id'] = $cs_merchant_id;
            $where['platform_category_id'] = $cat['id'];

            $row['cs_merchant_id'] = $cs_merchant_id;
            $row['platform_category_id'] = $cat['id'];
            $row['cs_cat_name'] = $cat['cat_name'];
            $row['cs_category_parent_id'] = $cat['parent_id'];
            $row['cs_category_level'] = $cat['level'];

            CsMerchantCategory::updateOrCreate($where, $row);
        }
        return true;
    }
}