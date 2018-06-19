<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/12
 * Time: 23:04
 */

namespace App\Modules\Invite;
use App\Exceptions\BaseResponseException;
use App\Exceptions\ParamInvalidException;
use App\Jobs\MerchantLevelCalculationJob;
use App\Modules\Merchant\Merchant;
use App\Modules\Oper\Oper;
use App\Modules\User\User;
use App\Modules\User\UserMapping;
use App\Modules\Wechat\MiniprogramScene;
use App\ResultCode;

/**
 * 用户邀请相关服务
 * Class InviteService
 * @package App\Modules\Invite
 */
class InviteService
{

    /**
     * 获取用户上级, 可能是用户/商户或运营中心
     * @param $userId
     * @return Merchant|Oper|User|null
     */
    public static function getParent($userId)
    {
        $inviteRecord = InviteUserRecord::where('user_id', $userId)->first();
        if(empty($inviteRecord)){
            // 如果没有用户没有上级, 不做任何处理
            return null;
        }
        if($inviteRecord->origin_type == InviteUserRecord::ORIGIN_TYPE_MERCHANT){
            $object = Merchant::where('id', $inviteRecord->origin_id)->first();
        }else if($inviteRecord->origin_type == InviteUserRecord::ORIGIN_TYPE_OPER){
            $object = Oper::where('id', $inviteRecord->origin_id)->first();
        }else {
            $object = User::find($inviteRecord->origin_id);
        }
        return $object;
    }

    /**
     * 获取上级用户
     * @param $userId
     * @return User
     */
    public static function getParentUser($userId)
    {
        $inviteRecord = InviteUserRecord::where('user_id', $userId)->first();
        if(empty($inviteRecord)){
            // 如果没有用户没有上级, 不做任何处理
            return null;
        }
        if($inviteRecord->origin_type == InviteUserRecord::ORIGIN_TYPE_MERCHANT){
            $userMapping = UserMapping::where('origin_id', $inviteRecord->origin_id)
                ->where('origin_type', UserMapping::ORIGIN_TYPE_MERCHANT)
                ->first();
            if(empty($userMapping)){
                return null;
            }
            $user = User::find($userMapping->user_id);
        }else if($inviteRecord->origin_type == InviteUserRecord::ORIGIN_TYPE_OPER){
            $userMapping = UserMapping::where('origin_id', $inviteRecord->origin_id)
                ->where('origin_type', UserMapping::ORIGIN_TYPE_OPER)
                ->first();
            if(empty($userMapping)){
                return null;
            }
            $user = User::find($userMapping->user_id);
        }else {
            $user = User::find($inviteRecord->origin_id);
        }
        return $user;
    }

    /**
     * 根据运营中心ID, originId 以及originType获取邀请渠道 (不存在时创建)
     * @param $originId int 邀请人ID
     * @param $originType int 邀请人类型 1-用户 2-商户 3-运营中心
     * @param $operId int 运营中心ID, 存在时则生成对应运营中心的小程序码
     * @return InviteChannel
     */
    public static function getInviteChannel($originId, $originType, $operId=0)
    {
        $inviteChannel = InviteChannel::where('origin_id', $originId)
            ->where('oper_id', $operId)
            ->where('origin_type', $originType)
            ->first();
        if(empty($inviteChannel)){
            $inviteChannel = self::createInviteChannel($originId, $originType, $operId);
        }
        return $inviteChannel;
    }

    /**
     * 生成推广渠道
     * @param $originId int 邀请人ID
     * @param $originType int 邀请人类型 1-用户 2-商户 3-运营中心
     * @param $operId int 运营中心ID, 存在时则生成对应运营中心的小程序码
     * @return InviteChannel
     */
    public static function createInviteChannel($originId, $originType, $operId=0)
    {
        // 不能重复生成
        $inviteChannel = InviteChannel::where('oper_id', $operId)
            ->where('origin_id', $originId)
            ->where('origin_type', $originType)
            ->first();
        if($inviteChannel) {
            return $inviteChannel;
        }
        if($operId > 0){
            // 如果运营中心ID存在, 则生成该运营中心的小程序码场景
            $scene = new MiniprogramScene();
            $scene->oper_id = $operId;
            // 小程序端邀请注册页面地址
            $scene->page = MiniprogramScene::PAGE_INVITE_REGISTER;
            $scene->type = MiniprogramScene::TYPE_INVITE_CHANNEL;
            $scene->payload = json_encode([
                'origin_id' => $originId,
                'origin_type' => $originType,
            ]);
            $scene->save();
            $sceneId = $scene->id;
        }else {
            $sceneId = 0;
        }

        $inviteChannel = new InviteChannel();
        $inviteChannel->oper_id = $operId;
        $inviteChannel->origin_id = $originId;
        $inviteChannel->origin_type = $originType;
        $inviteChannel->scene_id = $sceneId;
        $inviteChannel->save();
        return $inviteChannel;
    }

    /**
     * 绑定邀请人信息到用户
     * @param $userId
     * @param InviteChannel $inviteChannel
     */
    public static function bindInviter($userId, InviteChannel $inviteChannel)
    {
        $inviteRecord = InviteUserRecord::where('user_id', $userId)->first();
        if($inviteRecord){
            // 如果当前用户已被邀请过, 不能重复邀请
            throw new BaseResponseException('您已经被邀请过了, 不能重复接收邀请', ResultCode::USER_ALREADY_BEEN_INVITE);
        }
        if($inviteChannel->origin_type == InviteChannel::ORIGIN_TYPE_USER && $inviteChannel->origin_id == $userId){
            throw new ParamInvalidException('不能扫描自己的邀请码');
        }
        $inviteRecord = new InviteUserRecord();
        $inviteRecord->user_id = $userId;
        $inviteRecord->invite_channel_id = $inviteChannel->id;
        $inviteRecord->origin_id = $inviteChannel->origin_id;
        $inviteRecord->origin_type = $inviteChannel->origin_type;
        $inviteRecord->save();

        if ($inviteRecord->origin_type == InviteUserRecord::ORIGIN_TYPE_MERCHANT){
            MerchantLevelCalculationJob::dispatch($inviteRecord->origin_id);
        }
    }

    /**
     * 根据邀请渠道获取邀请者名称
     * @param InviteChannel $inviteChannel
     * @return mixed|string
     */
    public static function getInviteChannelOriginName(InviteChannel $inviteChannel)
    {
        $originType = $inviteChannel->origin_type;
        $originId = $inviteChannel->origin_id;

        $originName = '';
        if($originType == 1){
            $user = User::findOrFail($originId);
            $originName = $user->name ?: self::_getHalfHideMobile($user->mobile);
        }else if($originType == 2){
            $originName = Merchant::where('id', $originId)->value('name');
        }else if($originType == 3){
            $originName = Oper::where('id', $originId)->value('name');
        }
        return $originName;
    }

    private static function _getHalfHideMobile($mobile){
        return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
    }

}