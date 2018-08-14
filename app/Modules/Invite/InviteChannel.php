<?php

namespace App\Modules\Invite;

use App\BaseModel;

/**
 * Class InviteChannel
 * @package App\Modules\Invite
 *
 * @property number oper_id
 * @property number origin_id
 * @property number origin_type
 * @property number scene_id
 * @property string name
 * @property string remark
 */
class InviteChannel extends BaseModel
{
    //推广人类型  1-用户
    const ORIGIN_TYPE_USER = 1;
    //推广人类型  2-商户
    const ORIGIN_TYPE_MERCHANT = 2;
    //推广人类型  3-运营中心
    const ORIGIN_TYPE_OPER = 3;

    // 换绑所需的固定运营中心的ID
    const FIXED_OPER_ID = 3;

    public function inviteUserRecords()
    {
        return $this->hasMany(InviteUserRecord::class);
    }
}
