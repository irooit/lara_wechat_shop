<?php

namespace App\Modules\Message;

use Illuminate\Database\Eloquent\Model;

class MessageSystemUserBehaviorRecordService extends Model
{
    public static function getRecordByUserId($userId)
    {
        return MessageSystemUserBehaviorRecord::firstOrCreate(['user_id' => $userId]);
    }

    public static function addRecords($userId, $type, $ids)
    {
        $record = MessageSystemUserBehaviorRecordService::getRecordByUserId($userId);
        $needSaveIds = $ids;
        if (!empty($record->$type)) {
            $needSaveIds = json_decode($record->$type);
            foreach ($ids as $k => $v){
                if(!in_array($v,$needSaveIds)){
                    // 保存没有存过的ID
                    array_push($needSaveIds,$v);
                }
            }
        }
        $record->$type = json_encode($needSaveIds);
        $record->save();
    }
}