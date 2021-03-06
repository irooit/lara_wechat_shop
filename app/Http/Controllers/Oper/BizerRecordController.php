<?php

namespace App\Http\Controllers\Oper;

use App\Exceptions\BaseResponseException;
use App\Http\Controllers\Controller;
use App\Modules\Oper\OperBizerLog;
use App\Modules\Oper\OperBizerService;
use App\Modules\Oper\OperBizer;

use App\Modules\Oper\OperService;
use App\Result;
use Illuminate\Support\Facades\DB;

class BizerRecordController extends Controller {

    /**
     * 业务员申请，默认申请中的业务员
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList() {
        $status = request("status");
        $where =[
            "oper_ids" => request()->get('current_user')->oper_id,//登录所属运营中心ID
            "status" =>$status,//查询业务员的状态
        ];

        $data = OperBizerService::getList($where);
        
        return Result::success([
            'list' => $data->items(),
            'total' => $data->total(),
        ]);
    }

    /**
     * 签约或者拒绝签约，修改状态
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function contractBizer()
    {
        $validate = array(
            'id' => 'required|integer|min:1',
            'status' => 'required|integer',
        );

        $this->validate(request(),$validate);

        $id = request('id');
        $status = request('status');
        $note = request('note', '');

        DB::beginTransaction();
        try {
            $operBizer = OperBizer::findOrFail($id);
            $divide = OperService::getById($operBizer->oper_id)->bizer_divide;
            $operBizer->status = $status;
            $operBizer->note = $note;
            //签约成功，更新签约时间,分成比例
            if ($status == OperBizer::STATUS_SIGNED) {
                $operBizer->divide = number_format($divide, 2);
                $operBizer->sign_time = date("Y-m-d H:i:s");
            }
            $operBizer->save();

            OperBizerService::updateOperBizerLog($operBizer->oper_id, $operBizer->bizer_id, $status, $note);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $msg = $e->getResponse()->original['message'] ?: '操作失败';
            throw new BaseResponseException($msg);
        }

        return Result::success($operBizer);
    }

    /**
     * 获取运营中间拒绝业务员记录
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRejectList() {
        $params =[
            'operId' => request()->get('current_user')->oper_id,
            'status' => OperBizerLog::STATUS_REJECTED,
        ];
        $data = OperBizerService::getOperBizerLogList($params);

        return Result::success([
            'list' => $data->items(),
            'total' => $data->total(),
        ]);
    }
}
