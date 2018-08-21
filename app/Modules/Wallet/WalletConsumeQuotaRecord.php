<?php

namespace App\Modules\Wallet;

use App\BaseModel;

/**
 * Class WalletConsumeQuotaRecord
 * @package App\Modules\Wallet
 * @property integer wallet_id
 * @property integer origin_id
 * @property integer origin_type
 * @property integer type
 * @property integer order_id
 * @property string order_no
 * @property float order_profit_amount
 * @property float consume_quota
 * @property string consume_user_mobile
 * @property integer status
 */

class WalletConsumeQuotaRecord extends BaseModel
{

    /**
     * 消费额记录用户类型
     */
    const ORIGIN_TYPE_USER = 1; // 用户
    const ORIGIN_TYPE_MERCHANT = 2; // 商户
    const ORIGIN_TYPE_OPER = 3; // 运营中心

    /**
     * 来源类型 1-消费自返 2-直接下级消费返
     */
    const TYPE_SELF = 1;
    const TYPE_SUBORDINATE = 2;

    /**
     * 状态 1-冻结中 2-已解冻待置换 3-已置换 4-已退款
     */
    const STATUS_FREEZE = 1;
    const STATUS_UNFREEZE = 2;
    const STATUS_REPLACEMENT = 3;
    const STATUS_REFUND = 4;
}
