<?php

namespace App\Modules\User;
use App\BaseModel;

/**
 * 验证记录
 * Author:  Jerry
 * Date:    180831
 * Class UserIdentityAuditRecord
 * @package App\Modules\User
 */

/**
 * Author:  Jerry
 * Date:    180831
 * Class UserIdentityAuditRecord
 * @package App\Modules\User
 * @property integer    user_id
 * @property integer    name
 * @property integer    number
 * @property string     front_pic
 * @property string     opposite_pic
 * @property integer    status
 * @property string     reason
 * @property integer    update_user
 *
 */
class UserIdentityAuditRecord extends BaseModel
{
    // '状态 1：为待审核，2：为审核通过， 3：为审核失败',
    const STATUS_TO_AUDIT   = 1;
    const STATUS_SUCCESS    = 2;
    const STATUS_FAIL       = 3;

    public static function getStatusText($status)
    {
        $status_arr = [self::STATUS_TO_AUDIT=>'待审核',self::STATUS_SUCCESS=>'审核通过',self::STATUS_FAIL=>'审核失败'];
        return !empty($status_arr[$status])?$status_arr[$status]:'未知状态';
    }
}
