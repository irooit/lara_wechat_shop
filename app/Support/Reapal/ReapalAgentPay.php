<?php
/**
 * Created by PhpStorm.
 * User: tim.tang
 * Date: 2018/8/29/029
 * Time: 11:27
 */

namespace App\Support\Reapal;


use App\Modules\Log\LogDbService;
use App\Modules\Log\LogOrderNotifyReapal;
use App\Modules\Settlement\SettlementPayBatch;
use Illuminate\Support\Facades\Log;

class ReapalAgentPay
{

    protected $merchantId;
    protected $sellerEmail;
    protected $merchantPrivateKey;
    protected $reapalPublicKey;
    protected $apiKey;
    protected $charset;

    protected $dsfUrl;
    protected $dsf_sign_type;
    protected $dsfVersion;

    public function __construct()
    {

        //正式商户ID
        $this->merchantId = config('reapal.merchant_id');
        //正式商户邮箱
        $this->sellerEmail = config('reapal.seller_email');

        //商户私钥
        $this->merchantPrivateKey = config('reapal.merchantPrivateKey');
        //融宝公钥
        $this->reapalPublicKey = config('reapal.reapalPublicKey');

        $this->apiKey = config('reapal.apiKey');
        $this->dsfUrl = config('reapal.dsfUrl');
        $this->charset = config('reapal.charset');
        $this->dsf_sign_type = config('reapal.dsf_sign_type');
        $this->dsfVersion = config('reapal.dsf_version');


        /*//测试商户ID
        $this->merchantId = '100000000000147';
        //商户邮箱
        $this->sellerEmail = config('reapal.seller_email');
        //商户私钥
        $this->merchantPrivateKey = resource_path('reapal/cert/test/itrus001_pri.pem');
        //融宝公钥
        $this->reapalPublicKey = resource_path('reapal/cert/test/itrus001.pem');
        $this->apiKey = 'g0be2385657fa355af68b74e9913a1320af82gb7ae5f580g79bffd04a402ba8f';
        $this->dsfUrl = config('reapal.dsfUrl');
        $this->charset = config('reapal.charset');
        $this->dsf_sign_type = config('reapal.dsf_sign_type');
        $this->dsfVersion = config('reapal.dsf_version');*/

    }

    protected function apiPost($url, $data)
    {
        $reapalMap = new ReapalUtils();
        $result = $reapalMap->send($data, $url, $this->apiKey, $this->reapalPublicKey, $this->merchantId, $this->dsfVersion, $this->dsf_sign_type);
        $response = json_decode($result, true);
        $encryptkey = $reapalMap->decryptKey($response['encryptkey'], $this->merchantPrivateKey);
        $result = $reapalMap->decrypt($response['data'], $encryptkey);
        $result = json_decode($result, 1);
        return $result;
    }

    /**
     * 代付提交
     * @param $batch_no
     * @param $batch_count
     * @param $batch_amount
     * @param $content
     * @return string
     */
    public function agentpay($batch_no, $batch_count, $batch_amount, $content)
    {
        $nowTime = date('Y-m-d H:i:s');

        /*$content = '1,62220215080205389633,jack-cooper,工商银行,分行,支行,私,0.01,CNY,北京,北京,18910116131,身份证,420321199202150718,0001,12306,hehe,200100000001422,67180118000001421|2,62220215080205389634,jack,工商银行,分行,支行,私,0.11,CNY,北京,北京,18910116133,身份证,420321199202150728,0002,12307,hehe2,200100000001423,67180118000001422|3,62220215080205389635,cooper,工商银行,分行,支行,私,0.1,CNY,北京,北京,18910116134,身份证,420321199202150729,0003,12308,hehe3,200100000001424,67180118000001423|';*/

        $paramArr = array(
            'charset' => $this->charset,
            'notify_url' => url('/api/agentPay/reapalNotify'),
            'trans_time' => $nowTime,
            'batch_no' => $batch_no,
            'batch_count' => $batch_count,
            'batch_amount' => $batch_amount,
            'pay_type' => 1,
            'content' => $content,
        );

        $url = $this->dsfUrl . 'agentpay/pay';
        $result = $this->apiPost($url, $paramArr);

        Log::info('融宝代付提交接口返回结果： ', $result);

        $batch = SettlementPayBatch::where('batch_no', $batch_no)->first();
        if ($result['result_code'] == 0000) {
            $batch->status = SettlementPayBatch::STATUS_IS_SUBMIT;
        } else {
            $batch->error_code = $result['result_code'];
            $batch->error_msg = $result['result_msg'];
        }
        $batch->save();

        return $batch;
    }

    /**
     * 代付批次查询
     * @param array $params
     * @return bool|mixed|string
     */
    public function agentpayQueryBatch(array $params)
    {
        //参数数组
        $paramArr = array(
            'charset' => $this->charset,
            'trans_time' => array_get($params,'trans_time'),
            'batch_no' => array_get($params,'batch_no'),
            'next_tag' => array_get($params,'next_tag'),
        );

        $url = $this->dsfUrl . 'agentpay/batchpayquery';

        $result = $this->apiPost($url, $paramArr);
        return $result;
    }

    /**
     * 代付单笔查询
     * @param array $params
     * @return bool|mixed|string
     */
    public function agentpayQuerySingle(array $params)
    {
        //参数数组
        $paramArr = array(
            'charset' => $this->charset,
            'trans_time' => array_get($params,'trans_time'),
            'batch_no' => array_get($params,'batch_no'),
            'detail_no' => array_get($params,'detail_no'),
        );

        $url = $this->dsfUrl . 'agentpay/singlepayquery';
        $result = $this->apiPost($url, $paramArr);
        return $result;
    }

    /**
     * 代付账户查询余额
     */
    public function agentpayQueryBalance()
    {
        //参数数组
        $paramArr = array(
            'charset' => $this->charset,
        );

        $url = $this->dsfUrl . 'agentpay/balancequery';
        $result = $this->apiPost($url, $paramArr);
        return $result;
    }

    /**
     * 融宝代付异步通知接口
     * @return \Illuminate\Config\Repository|mixed
     */
    public function agentNotify()
    {
        //获取参数
        $resultArr = request()->all();
        $reapalMap = new ReapalUtils();
        $encryptkey = $reapalMap->decryptKey($resultArr['encryptkey'], $this->merchantPrivateKey);
        $result = $reapalMap->decrypt($resultArr['data'], $encryptkey);

        LogDbService::reapalNotify(LogOrderNotifyReapal::TYPE_AGENT_PAY, $result);

        $result = json_decode($result, 1);

        //"data":"交易日期，批次号,序号,银行账户,开户名,分行,支行,开户行,公/私,金额,币种,备注,商户订单号,交易反馈,失败原因"

        $arraykey = [
            'trade_date', 'batch_no', 'serial_num', 'bank_account', 'bank_name', 'bank_branch', 'bank_sub_branch', 'opening_bank', 'bank_public_or_private', 'amount', 'currency', 'remark', 'settlement_no', 'return_msg', 'error_message'
        ];
        $res = array_combine($arraykey, $result);
        return $res;
    }
}

