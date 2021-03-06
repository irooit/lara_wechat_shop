<?php

namespace App\Http\Controllers\Oper;

use App\Exceptions\DataNotFoundException;
use App\Exports\OperCsMerchantExport;
use App\Http\Controllers\Controller;
use App\Modules\Cs\CsMerchantAudit;
use App\Modules\Merchant\MerchantAccount;
use App\Modules\Merchant\MerchantAccountService;
use App\Modules\Cs\CsMerchantAuditService;
use App\Modules\Cs\CsMerchantService;
use App\Result;


class CsMerchantController extends Controller
{

    /**
     * 获取列表 (分页)
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getList()
    {
        $data = [
            'operId' => request()->get('current_user')->oper_id,
            'name' => request('name'),
            'merchantId' => request('merchantId'),
            'signboardName' => request('signboardName'),
            'status' => request('status'),
            'auditStatus' => request('audit_status')
        ];

        $data = CsMerchantService::getList($data);

        return Result::success([
            'list' => $data->items(),
            'total' => $data->total(),
        ]);
    }

    /**
     * 导出商户
     */
    public function export(){

        $data = [
            'operId' => request()->get('current_user')->oper_id,
            'name' => request('name'),
            'merchantId' => request('merchantId'),
            'signboardName' => request('signboardName'),
            'status' => request('status'),
            'auditStatus' => request('audit_status')
        ];

        $query = CsMerchantService::getList($data,true);

        $list = $query->get();

        return (new OperCsMerchantExport($list))->download('我的超市商户列表.xlsx');

    }

    /**
     * 获取全部的商户名称
     */
    public function allNames()
    {
        $data = [
            'audit_status' => request('audit_status'),
            'status' => request('status'),
            'isPilot' => request('isPilot'),
            'operId' => request()->get('current_user')->oper_id,
        ];
        $list = CsMerchantService::getAllNames($data);
        foreach ($list as $key){
            $key->name = $key->id.":".$key->name;
        }
        return Result::success([
            'list' => $list
        ]);
    }

    /**
     * 详情
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function detail()
    {
        $this->validate(request(), [
            'id' => 'required|integer|min:1'
        ]);

        //为重新编辑，提取审核记录表里最新的数据进行填充到详情页面
        if(request('isReEdit') == 'true'){
            $merchant = CsMerchantService::getReEditData(request('id'));
        }else{
            $merchant = CsMerchantService::detail(request('id'),request()->get('current_user')->id);
        }

        return Result::success($merchant);
    }


    /**
     * 修改状态
     */
    public function changeStatus()
    {
        $this->validate(request(), [
            'id' => 'required|integer|min:1',
            'status' => 'required|integer',
        ]);
        $merchant = CsMerchantService::getById(request('id'));
        if(empty($merchant)){
            throw new DataNotFoundException('商户信息不存在');
        }
        $merchant->status = request('status');
        $merchant->save();

        //$merchant->categoryPath = MerchantCategoryService::getCategoryPath($merchant->merchant_category_id);
        $merchant->account = MerchantAccount::where('merchant_id', $merchant->id)->first();

        return Result::success($merchant);
    }

    public function recall()
    {
        $this->validate(request(), [
            'id' => 'required|integer|min:1',
        ]);
        $csMerchantAudit = CsMerchantAuditService::getById(request('id'));
        if(empty($csMerchantAudit)){
            throw new DataNotFoundException('超市商户审核信息不存在');
        }
        $csMerchantAudit->status = CsMerchantAudit::AUDIT_STATUS_RECALL;
        $csMerchantAudit->save();
        return Result::success($csMerchantAudit);
    }

    /**
     * 创建商户账号
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function createAccount()
    {
        $this->validate(request(), [
            'merchant_id' => 'required|integer|min:1',
            'account' => 'required',
            'password' => 'required|min:6',
        ]);
        $merchantId = request('merchant_id');
        $account = request('account');
        $operId = request()->get('current_user')->oper_id;
        $password = request('password');
        $type = request('type');

        $account = MerchantAccountService::createAccount($merchantId,$account,$operId,$password,$type);

        return Result::success($account);
    }

    /**
     * 编辑商户账号信息, 即修改密码
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function editAccount()
    {

        $this->validate(request(), [
            'id' => 'required|integer|min:1',
            'password' => 'required|min:6',
        ]);
        $id = request('id');
        $password = request('password');

        $account = MerchantAccountService::editAccount($id,$password);

        return Result::success($account);
    }

    /**
     * 获取最新一条审核记录
     */
    /*public function getNewestAuditRecord()
    {
        $this->validate(request(), [
            'id' => 'required|integer|min:1'
        ]);
        $merchantId = request('id');
        $record = CsMerchantAuditService::getNewestAuditRecordByMerchantId($merchantId);
        return Result::success($record);
    }*/

    //判断运营中心是否切换到平台
    /*public function isPayToPlatform(){

        $oper = OperService::getById(request()->get('current_user')->oper_id);

        $isPayToPlatform = in_array($oper->pay_to_platform, [Oper::PAY_TO_PLATFORM_WITHOUT_SPLITTING, Oper::PAY_TO_PLATFORM_WITH_SPLITTING]);

        return $isPayToPlatform;
    }*/


}