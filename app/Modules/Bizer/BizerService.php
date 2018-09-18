<?php

namespace App\Modules\Bizer;

use App\BaseService;
use App\Exceptions\BaseResponseException;
use App\ResultCode;

class BizerService extends BaseService
{


    /**
     * 查询所有业务员，不分页
     * @param array $data
     * @param bool $getWithQuery
     * @return Bizer|Bizer[]|array|\Illuminate\Database\Eloquent\Collection
     */
    public static function getAll(array $data, bool $getWithQuery = false)
    {
        $status = array_get($data,"status");
        // 全局限制条件
        $query = Bizer::where('status', $status)->orderByDesc('id');

        if ($getWithQuery) {
            return $query;
        } else {

            $data = $query->get();

            return $data;
        }
    }

    /**
     * 通过id获取业务员
     * @param $id
     * @param array $fields
     * @return Bizer
     */
    public static function getById($id, $fields = ['*'])
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        $bizer = Bizer::find($id, $fields);
        return $bizer;
    }

    /**
     * 添加业务员审核表 记录
     * @param $data
     * @param Bizer $bizer
     * @return BizerIdentityAuditRecord
     */
    public static function addBizerIdentityAuditRecord($data, Bizer $bizer)
    {
        $bizerIdentityAuditRecord = new BizerIdentityAuditRecord();
        $bizerIdentityAuditRecord->bizer_id = $bizer->id;
        $bizerIdentityAuditRecord->name = $data['name'];
        $bizerIdentityAuditRecord->id_card_no = $data['idCardNo'];
        $bizerIdentityAuditRecord->front_pic = $data['frontPic'];
        $bizerIdentityAuditRecord->opposite_pic = $data['oppositePic'];
        $bizerIdentityAuditRecord->status = BizerIdentityAuditRecord::STATUS_AUDIT_PREPARE;
        $bizerIdentityAuditRecord->reason = '';
        $bizerIdentityAuditRecord->update_user = 0;
        $bizerIdentityAuditRecord->save();
        return $bizerIdentityAuditRecord;
    }

    /**
     * 通过业务员id 获取业务员审核信息
     * @param $bizerId
     * @return BizerIdentityAuditRecord
     */
    public static function getBizerIdentityAuditRecordByBizerId($bizerId)
    {
        $bizerIdentityAuditRecord = BizerIdentityAuditRecord::where('bizer_id', $bizerId)->first();
        return $bizerIdentityAuditRecord;
    }

    /**
     * 修改业务员审核信息
     * @param $data
     * @param BizerIdentityAuditRecord $bizerIdentityAuditRecord
     * @return BizerIdentityAuditRecord
     */
    public static function editBizerIdentityAuditRecord($data, BizerIdentityAuditRecord $bizerIdentityAuditRecord)
    {
        $exist = self::checkRecordCardNoUsed($bizerIdentityAuditRecord->bizer_id, $data['idCardNo']);
        if( $exist ) {
            throw new BaseResponseException( '该身份证号已被他人使用!');
        }

        $bizerIdentityAuditRecord->name = $data['name'];
        $bizerIdentityAuditRecord->id_card_no = $data['idCardNo'];
        $bizerIdentityAuditRecord->front_pic = $data['frontPic'];
        $bizerIdentityAuditRecord->opposite_pic = $data['oppositePic'];
        $bizerIdentityAuditRecord->status = BizerIdentityAuditRecord::STATUS_AUDIT_PREPARE;
        if (!$bizerIdentityAuditRecord->save()) {
            throw new BaseResponseException('修改失败证件信息失败', ResultCode::DB_UPDATE_FAIL);
        }
        return $bizerIdentityAuditRecord;
    }

    /**
     * 根据id获取业务员审核信息
     * @param $id
     * @return BizerIdentityAuditRecord
     */
    public static function getBizerIdentityAuditRecordById($id)
    {
        $bizerIdentityAuditRecord = BizerIdentityAuditRecord::find($id);
        return $bizerIdentityAuditRecord;
    }

    public static function checkRecordCardNoUsed($bizerId, $cardNo)
    {
        $exist=  BizerIdentityAuditRecord::where('id_card_no', $cardNo)
            ->where('bizer_id', '!=', $bizerId)->exists();
        return $exist;
    }
}