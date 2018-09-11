<?php

namespace App\Http\Controllers\User;

use App\Exceptions\DataNotFoundException;
use App\Result;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\User\UserIdentityAuditRecordService;
use App\Exceptions\BaseResponseException;

/**
 * 验证记录
 * Author:  Jerry
 * Date:    180831
 * Class UserIdentityAuditRecord
 * @package App\Http\Controllers\User
 */
class UserIdentityAuditRecordController extends Controller
{

    /**
     * Author:  Jerry
     * Date:    180831
     * 新增身份验证记录
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function addRecord( Request $request )
    {
        // 注入user_id
        $request->offsetSet('user_id',$request->get('current_user')->id);
        $request->validate([
            'name'          => 'required|max:20',
            'id_card_no'    => 'bail|required|min:18|identitycards|unique:user_identity_audit_records',
            'front_pic'     => 'required',
            'opposite_pic'  => 'required',
            'user_id'       => 'unique:user_identity_audit_records'
        ]);
        $saveData = [
            'name'          => $request->get('name'),
            'id_card_no'    => $request->get('id_card_no'),
            'front_pic'     => $request->get('front_pic'),
            'opposite_pic'  => $request->get('opposite_pic'),
        ];
        UserIdentityAuditRecordService::addRecord( $saveData , $request->get('current_user') );
        return Result::success('提交成功');
    }

    /**
     * Author:  Jerry
     * Date:    180831
     * 修改身份验证记录
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function modRecord( Request $request )
    {
        $request->validate([
            'name'          => 'required|max:20',
            'id_card_no'    => 'bail|required|min:18|identitycards',
            'front_pic'     => 'required',
            'opposite_pic'  => 'required',
        ]);

        $saveData = [
            'name'          => $request->get('name'),
            'id_card_no'    => $request->get('id_card_no'),
            'front_pic'     => $request->get('front_pic'),
            'opposite_pic'  => $request->get('opposite_pic')
        ];
        UserIdentityAuditRecordService::modRecord( $saveData, $request->get('current_user') );
        return Result::success('修改成功');
    }

    /**
     * 获取用户验证记录
     * Author：  Jerry
     * Date:    180901
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function getRecord( Request $request )
    {
        $record = UserIdentityAuditRecordService::getRecordByUser( $request->get('current_user')->id);
        return Result::success($record);
    }
}
