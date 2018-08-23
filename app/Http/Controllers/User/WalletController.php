<?php
/**
 * Created by PhpStorm.
 * User: 57458
 * Date: 2018/8/23
 * Time: 18:01
 */

namespace App\Http\Controllers\User;


use App\Http\Controllers\Controller;
use App\Modules\Wallet\Wallet;
use App\Modules\Wallet\WalletBill;
use App\Modules\Wallet\WalletService;
use App\Result;

class WalletController extends Controller
{

    /**
     * 获取用户钱包信息
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getWallet()
    {
        $user = request('current_user');
        $wallet = WalletService::getWalletInfoByOriginInfo($user->id, Wallet::ORIGIN_TYPE_USER);

        return Result::success($wallet);
    }

    /**
     * 获取账单信息
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function getBills()
    {
        $startDate = request('startDate');
        $endDate = request('endDate');
        $type = request('type');
        $user = request('current_user');
        $bills = WalletService::getBillList([
            'originId' => $user->id,
            'originType' => WalletBill::ORIGIN_TYPE_USER,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'typeArr' => [$type],
        ], 20);

        return Result::success($bills);
    }

    /**
     * 获取账单详情
     * @return WalletBill|null
     */
    public function getBillDetail()
    {
        $this->validate(request(), [
            'id' => 'required|integer|min:1'
        ]);
        $id = request('id');
        $billInfo = WalletService::getBillDetailById($id);

        return $billInfo;
    }
}