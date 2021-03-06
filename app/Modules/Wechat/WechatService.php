<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/13
 * Time: 12:38
 */

namespace App\Modules\Wechat;


use App\Exceptions\BaseResponseException;
use App\Exceptions\MiniprogramPageNotExistException;
use App\Modules\Merchant\MerchantService;
use App\Modules\Oper\OperMiniprogram;
use App\Modules\Oper\OperService;
use App\Modules\Oper\Oper;
use App\ResultCode;
use App\Support\ImageTool;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;
use Intervention\Image\Gd\Font;
use App\Modules\Wechat\MiniprogramScene;

class WechatService
{
    /**
     * 获取运营中心的微信小程序实例
     * @param $operId int
     * @return \EasyWeChat\MiniProgram\Application
     */
    public static function getWechatMiniAppForOper($operId)
    {
        Log::info('login_oper_id',['login_oper_id' => $operId]);
        $miniProgram = OperMiniprogram::where('oper_id', $operId)->first();
        if(empty($miniProgram)){
            throw new BaseResponseException('运营中心小程序配置不存在', ResultCode::MINIPROGRAM_CONFIG_NOT_EXIST);
        }
        $config = [
            'app_id' => $miniProgram->appid,
            'secret' => $miniProgram->secret,

            'response_type' => 'array',
            'log' => [
                'level' => 'debug',
                'file' => storage_path().'/logs/wechat.log',
            ],
        ];

        return Factory::miniProgram($config);
    }

    /**
     * 获取平台的小程序
     * @return \EasyWeChat\MiniProgram\Application
     */
    public static function getWechatMiniAppForPlatform()
    {
        $miniProgram = config('platform.miniprogram');
        if(request()->get('current_oper_id') == -1){
            // 旧的官方小程序中
            $miniProgram = $miniProgram['old'];
        }
        $config = [
            'app_id' => $miniProgram['app_id'],
            'secret' => $miniProgram['app_secret'],

            'response_type' => 'array',
            'log' => [
                'level' => 'debug',
                'file' => storage_path().'/logs/wechat.log',
            ],
        ];

        return Factory::miniProgram($config);
    }

    /**
     * 根据请求信息自动判断获取平台或运营中心的小程序实例
     * @return \EasyWeChat\MiniProgram\Application
     */
    public static function getWechatMiniAppFromRequest()
    {
        $oper = request()->get('current_oper');
        if(empty($oper)){
            return self::getWechatMiniAppForPlatform();
        }else {
            return WechatService::getWechatMiniAppForOper(request()->get('current_oper')->id);
        }
    }

    /**
     * 获取平台的微信开放平台实例
     * @return \EasyWeChat\OpenPlatform\Application
     */
    public static function getOpenPlatformAppForPlatform()
    {

        $wechatOpen = config('platform.wechat_open');

        $config = [
            'app_id'   => $wechatOpen['app_id'],
            'secret'   => $wechatOpen['e0f7d6492e089f45671b7a5408962315'],
//            'token'    => '开放平台第三方平台 Token',
//            'aes_key'  => '开放平台第三方平台 AES Key'
        ];

        return Factory::openPlatform($config);
    }

    /**
     * 获取微信支付的 EasyWechat App
     * @param $operId
     * @return \EasyWeChat\Payment\Application
     */
    public static function getWechatPayAppForOper($operId)
    {
        if($operId instanceof OperMiniprogram){
            $miniProgram = $operId;
        }else {
            $miniProgram = OperMiniprogram::where('oper_id', $operId)->first();
        }

        $config = [
            // 必要配置
            'app_id' => $miniProgram->appid,
            'mch_id'             => $miniProgram->mch_id,
            'key'                => $miniProgram->key,   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path'          => OperMiniprogram::getAbsoluteCertPemFilePath($miniProgram->mch_id), // XXX: 绝对路径！！！！
            'key_path'           => OperMiniprogram::getAbsoluteCertKeyFilePath($miniProgram->mch_id),      // XXX: 绝对路径！！！！

            'notify_url' => request()->getSchemeAndHttpHost() . '/api/pay/notify',     // 你也可以在下单时单独设置来想覆盖它
        ];

        return Factory::payment($config);
    }

    /**
     * 获取平台微信支付的 EasyWechat App
     * @return \EasyWeChat\Payment\Application
     */
    public static function getWechatPayAppForPlatform()
    {

        $platform = config('platform');
        $miniprogram = $platform['miniprogram'];
        $wechatPay = $platform['wechat_pay']['miniprogram'];

        $config = [
            // 必要配置
            'app_id'             => $miniprogram['app_id'],
            'mch_id'             => $wechatPay['mch_id'],
            'key'                => $wechatPay['key'],   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path'          => $wechatPay['cert_path'], // XXX: 绝对路径！！！！
            'key_path'           => $wechatPay['key_path'],      // XXX: 绝对路径！！！！

            'notify_url' => request()->getSchemeAndHttpHost() . '/api/pay/notify',     // 你也可以在下单时单独设置来想覆盖它
        ];

        return Factory::payment($config);
    }

    /**
     * 获取微信开发平台app应用对应的支付
     * @return \EasyWeChat\Payment\Application
     */
    public static function getOpenPlatformPayAppFromPlatform()
    {

        $wechatOpen = config('platform.wechat_open');
        $appPay = config('platform.wechat_pay.app');

        $config = [
            // 必要配置
            'app_id'             => $wechatOpen['app_id'],
            'mch_id'             => $appPay['mch_id'],
            'key'                => $appPay['key'],   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path'          => $appPay['cert_path'], // XXX: 绝对路径！！！！
            'key_path'           => $appPay['key_path'],      // XXX: 绝对路径！！！！

            'notify_url' => request()->getSchemeAndHttpHost() . '/api/pay/notify',     // 你也可以在下单时单独设置来想覆盖它
        ];

        return Factory::payment($config);
    }

    /**
     * 生成小程序码
     * @param $operId
     * @param  $sceneId
     * @param string $page
     * @param int $width
     * @param bool $getWithFilename
     * @param string $merchantId
     * @return bool|int|string
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     */
    public static function genMiniprogramAppCode($operId, $sceneId, $page='pages/index/index', $width=375, $getWithFilename=false,$merchantId ='')
    {
        if($operId){
            // 如果未切换到了支付到运营中心,则使用运营中心二维码
            $app = WechatService::getWechatMiniAppForOper($operId);
        }
        $app = $app ?? WechatService::getWechatMiniAppForPlatform();

        $response = $app->app_code->getUnlimit($sceneId, [
            'page' => $page,
            'width' => $width,
        ]);

        if($merchantId){
            $name = MerchantService::getSignboardNameById($merchantId);
        }else{
            $name = '';
        }

        if($json = json_decode($response, 1)){
            if($json['errcode'] == 41030){
                throw new MiniprogramPageNotExistException();
            }
            throw new BaseResponseException('小程序码生成失败' . $response);
        }
        try {
            $filename = $response->save(storage_path('app/public/miniprogram/app_code'), "_{$sceneId}_{$width}");

            $path = storage_path('app/public/miniprogram/app_code/') . "_{$sceneId}_{$width}.jpg";

            self::addSceneIdToAppCode($path, $sceneId);
            self::addNameToAppCode($path,$name);

        } catch (InvalidArgumentException $e) {
            throw new BaseResponseException('小程序码生成失败');
        }
        if($getWithFilename){
            return $filename;
        }

        return asset('storage/miniprogram/app_code/' . $filename);
    }

    /**
     * @param $sceneId int|MiniprogramScene
     * @return mixed|string
     */
    public static function getMiniprogramAppCodeUrl($sceneId)
    {
        if($sceneId instanceof MiniprogramScene){
            $scene = $sceneId;
        }else {
            $scene = MiniprogramSceneService::getById($sceneId);
        }
        if(!empty($scene->qrcode_url)){
            return $scene->qrcode_url;
        }else {
            $url = self::genMiniprogramAppCode($scene->oper_id, $scene->id, $scene->page,'',false,$scene->merchant_id);
            $scene->qrcode_url = $url;
            $scene->save();
            return $url;
        }
    }

    /**
     * 给小程序码增加场景ID
     * @param string $path
     * @param int|string $sceneId
     */
    public static function addSceneIdToAppCode($path, $sceneId)
    {
        // 设置基础比率 (字体大小与放大比例)
        $fontSizeRatio = 0.05; // 字体大小比例
        $biggerRatio = 0.25; // 图片整体放大比例

        $appCode = Image::make($path);
        $width = $appCode->width();

        // 计算画布所需大小
        $canvasWidth = $width * (1 + $biggerRatio);
        $canvasHeight = $width * (1 + $biggerRatio + $fontSizeRatio);
        $image = ImageTool::canvas($canvasWidth, $canvasHeight, '#ffffff');

        // 将小程序码添加到画布上
        $paddingX = intval($biggerRatio / 2 * $width);
        $paddingY = intval($biggerRatio / 2 * $width);
        $image = ImageTool::water($image, $appCode, 'top-left', $paddingX, $paddingY);

        // 计算文字大小
        $sceneIdSize = intval($fontSizeRatio * $width);
        $sceneIdX = intval($canvasWidth * 0.9);
        $sceneIdY = intval((1 + ($biggerRatio + $fontSizeRatio * 3 ) / 2) * $width);

        // 将文字添加到画布上
        $sceneId = 'ID：' . str_pad($sceneId, 8, "0", STR_PAD_LEFT);
        $image = ImageTool::text($image, $sceneId, $sceneIdSize, $sceneIdX, $sceneIdY, 'right', '#999999');

        $image->save($path);
    }

    /**
     * 在小程序码添加标题
     * @param string $path
     * @param string $name
     */
    public static function addNameToAppCode($path, $name)
    {
        // 设置基础比率 (字体大小与放大比例)
        $fontSizeRatio = 0.045; // 字体大小比例

        $appCode = Image::make($path);
        $width = $appCode->width();
        $height = $appCode->height();

        // 计算画布所需大小
        $canvasWidth = $width;
        $canvasHeight = $height * (1 + $fontSizeRatio * 2);
        $image = ImageTool::canvas($canvasWidth, $canvasHeight, '#ffffff');

        // 将小程序码添加到画布上
        $paddingX = 0;
        $paddingY = ($fontSizeRatio * 2) * $height;
        $image = ImageTool::water($image, $appCode, 'top-left', $paddingX, $paddingY);

        // 计算文字大小
        $nameSize = intval($fontSizeRatio * $width);
        $nameX = intval($canvasWidth / 2);
        $nameY = intval($fontSizeRatio * 3 * $height);

        // 将文字添加到画布上
        $image = ImageTool::text($image, $name, $nameSize, $nameX, $nameY);

        $image->save($path);
    }

}