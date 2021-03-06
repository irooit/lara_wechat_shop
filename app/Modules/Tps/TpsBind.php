<?php

namespace App\Modules\Tps;

use App\BaseModel;
use App\Events\TpsBindSave;

/**
 * Class TpsBind
 * @package App\Modules\Tps
 *
 * @property int origin_type
 * @property int origin_id
 * @property string tps_uid
 * @property string tps_account
 */
class TpsBind extends BaseModel
{
    //
    const ORIGIN_TYPE_USER = 1;
    const ORIGIN_TYPE_MERCHANT = 2;
    const ORIGIN_TYPE_OPER = 3;

    // ���¼� Jerry 180903  ����
    /*protected $dispatchesEvents = [
        'created' => TpsBindSave::class,
    ];*/

}
