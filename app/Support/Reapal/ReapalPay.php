<?php

namespace App\Support\Reapal;

use App\Support\Utils;
use Illuminate\Support\Carbon;

/**
 * 融宝支付
 * Class ReapalPay
 * @package App\Support
 */
class ReapalPay
{
    protected $merchantId;
    protected $sellerEmail;
    protected $merchantPrivateKey;
    protected $reapalPublicKey;
    protected $apiKey;
    protected $dsfUrl;
    protected $charset;
    protected $dsf_sign_type;
    protected $dsfVersion;

    protected $apiUrl;
    protected $apiVersion;
    protected $api_sign_type;

    public function __construct()
    {

        //测试商户ID
        $this->merchantId = config('reapal.merchant_id');
        //测试商户邮箱
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

        $this->apiUrl = config('reapal.api_url');
        $this->apiVersion = config('reapal.api_version');
        $this->api_sign_type = config('reapal.api_sign_type');
    }



    /**
     * 预支付接口, 返回调起微信支付需要的参数
     * @param array $param
     * @return mixed|string
     */
    public function prepay(array $param)
    {
        //参数数组
        $paramArr = [

            'merchant_id' => $this->merchantId,
            'order_no' => array_get($param, 'order_no'),
            'transtime' => date('Y-m-d H:i:s'),
            'currency' => '156',
            'total_fee' => array_get($param, 'total_fee'),
            'title' => array_get($param, 'title'),
            'body' => array_get($param, 'body'),

            'client_type' => '0',
            'user_id' => array_get($param, 'open_id'),
            'appid_source' => config('platform.miniprogram.app_id'),
            'store_phone' => array_get($param, 'store_phone'),
            'store_name' => array_get($param, 'store_name'),
            'store_id' => array_get($param, 'merchantId'),
            'token_id' => Utils::create_uuid(),
            'terminal_type' => 'mobile',
            'terminal_info' => 'null_MAC/' . Utils::create_uuid() . '_SIM',
            'member_ip' => request()->ip(),
            'seller_email' => $this->sellerEmail,

            'notify_url' => url('/pay/reapalPayNotify'),

            'version' => $this->apiVersion,

        ];

        $url = $this->apiUrl . '/qrcode/scan/encryptline';
        $result = $this->apiPost($url, $paramArr);
        return $result;
    }

    protected function apiPost($url, $data){
        $reapalMap = new ReapalUtils();
        $result = $reapalMap->send($data, $url, $this->apiKey, $this->reapalPublicKey, $this->merchantId, $this->apiVersion, $this->api_sign_type);
        $response = json_decode($result, true);
        $encryptkey = $reapalMap->decryptKey($response['encryptkey'], $this->merchantPrivateKey);
        $result = $reapalMap->decrypt($response['data'], $encryptkey);
        $result = json_decode($result, 1);
        return $result;
    }

    /**
     * 支付结果查询接口
     */
    public function paySearch()
    {

        //参数数组
        $paramArr = array(
            'merchant_id' => $this->merchantId,
            'order_no' => request('order_no'),
        );
        $reapalMap = new ReapalUtils();

        $url = $this->apiUrl . '/qrcode/scan/encryptSearch';

        $result = $reapalMap->send($paramArr, $url, $this->apiKey, $this->reapalPublicKey, $this->merchantId);
        $response = json_decode($result, true);
        $encryptkey = $reapalMap->RSADecryptkey($response['encryptkey'], $this->merchantPrivateKey);

        return $reapalMap->AESDecryptResponse($encryptkey, $response['data']);
    }

    /**
     * 退款接口
     */
    public function refund()
    {
        //参数数组
        $paramArr = [
            'merchant_id' => request('merchant_id'),
            'refund_order_no' => request('refund_order_no'),
            'order_no' => request('order_no'),
            'amount' => request('amount'),

        ];

        $merchantId = request('merchat_id') ? request('merchat_id') : $this->merchantId;

        $reapalMap = new ReapalUtils();

        $url = $this->apiUrl . '/qrcode/scan/encryptRefundApply';
        $result = $reapalMap->send($paramArr, $url, $this->apiKey, $this->reapalPublicKey, $merchantId);
        $response = json_decode($result, true);
        $encryptkey = $reapalMap->RSADecryptkey($response['encryptkey'], $this->merchantPrivateKey);
        return $reapalMap->AESDecryptResponse($encryptkey, $response['data']);

    }

    /**
     * 退款接口查询
     */
    public function refundSearch()
    {
        //参数数组
        $paramArr = [
            'merchant_id' => request('merchant_id'),
            'refund_order_no' => request('refund_order_no'),
            'order_no' => request('order_no'),
        ];

        $merchantId = request('merchat_id') ? request('merchat_id') : $this->merchantId;

        $reapalMap = new ReapalUtils();

        $url = $this->apiUrl . '/qrcode/scan/encryptRefundQuery';
        $result = $reapalMap->send($paramArr, $url, $this->apiKey, $this->reapalPublicKey, $merchantId, $this->apiVersion, $this->api_sign_type);
        $response = json_decode($result, true);
        $encryptkey = $reapalMap->RSADecryptkey($response['encryptkey'], $this->merchantPrivateKey);
        return $reapalMap->AESDecryptResponse($encryptkey, $response['data']);

    }

    /**
     * 异步通知接口
     */

    //public function payNotify(){

    //获取参数
    /*$resultArr = json_decode(request(),true);
    return $resultArr;*/

    //$str = request()->getContent();
    //$xml = simplexml_load_string($str);
    //return $xml;
    //}

    /**
     * 异步通知接口(退款)
     */

    public function refundNotify()
    {

        //获取参数
        $resultArr = json_decode(request(), true);
        return $resultArr;
    }


}