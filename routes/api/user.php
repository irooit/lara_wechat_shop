<?php
/**
 * 运营中心接口路由
 */

use App\Http\Middleware\User\UserLoginFilter;
use App\Http\Middleware\AllowWithdrawDate;
use Illuminate\Support\Facades\Route;

Route::prefix('user')
    ->namespace('User')
    ->middleware('user')->group(function (){

        Route::any('wxLogin', 'WechatController@login');
        Route::any('sms/verify_code', 'SmsController@sendVerifyCode');

        Route::any('login', 'LoginController@login');
        Route::any('logout', 'LoginController@logout');
        Route::any('loginWithSceneId', 'LoginController@loginWithSceneId');

        Route::any('user/info', 'UserController@getInfo')->middleware(UserLoginFilter::class);
        Route::any('user/updateWxInfo', 'UserController@updateUserWxInfo')->middleware(UserLoginFilter::class);
        Route::post('user/setNameAndAvatar', 'UserController@setAvatar')->middleware(UserLoginFilter::class);

        Route::get('area/tree', 'AreaController@getTree');
        Route::get('area/cites/groupByFirstLetter', 'AreaController@getCityListGroupByFirstLetter');
        Route::get('area/cities/groupByFirstLetter', 'AreaController@getCityListGroupByFirstLetter');
        Route::get('area/cites/withHot', 'AreaController@getCitiesWithHot');
        Route::get('area/cities/withHot', 'AreaController@getCitiesWithHot');
        Route::get('area/getByGps', 'AreaController@getAreaByGps');
        Route::get('area/search', 'AreaController@searchCityList');

        Route::get('merchant/categories/tree', 'MerchantCategoryController@getTree');
        Route::get('merchants', 'MerchantController@getList');
        Route::get('merchant/detail', 'MerchantController@detail');
        Route::get('merchant/followStatus', 'MerchantFollowController@modifyFollowStatus')->middleware(UserLoginFilter::class);
        Route::get('merchant/followLists', 'MerchantFollowController@userFollowList')->middleware(UserLoginFilter::class);

        Route::get('goods', 'GoodsController@getList');
        Route::get('goods/detail', 'GoodsController@detail');

        Route::get('orders', 'OrderController@getList')->middleware(UserLoginFilter::class);
        Route::get('order/detail', 'OrderController@detail')->middleware(UserLoginFilter::class);
        Route::any('order/buy', 'OrderController@buy')->middleware(UserLoginFilter::class);
        Route::any('order/pay', 'OrderController@pay')->middleware(UserLoginFilter::class);
        Route::any('order/refund', 'OrderController@refund')->middleware(UserLoginFilter::class);
        Route::any('order/scanQrcodePay', 'OrderController@scanQrcodePay')->middleware(UserLoginFilter::class);
        Route::any('order/dishesBuy', 'OrderController@dishesBuy')->middleware(UserLoginFilter::class);


        Route::get('invite/qrcode', 'InviteChannelController@getInviteQrcode')->middleware(UserLoginFilter::class);
        Route::get('invite/getInviterBySceneId', 'InviteChannelController@getInviterBySceneId');
        Route::post('invite/bindInviter', 'InviteChannelController@bindInviter')->middleware(UserLoginFilter::class);
        Route::get('invite/getInviteUserStatistics', 'InviteChannelController@getInviteUserStatistics')->middleware(UserLoginFilter::class);

        Route::get('invite/getInviterInfo', 'UnbindInviterController@getBindInfo')->middleware(UserLoginFilter::class);
        Route::post('invite/unbind', 'UnbindInviterController@unbind')->middleware(UserLoginFilter::class);

        Route::get('scene/info', 'SceneController@getSceneInfo');

        Route::get('credit/payAmountToCreditRatio', 'CreditController@payAmountToCreditRatio')->middleware(UserLoginFilter::class); //废弃
        Route::get('credit/getCreditList', 'CreditController@getCreditList')->middleware(UserLoginFilter::class);
        Route::get('credit/getUserCredit', 'CreditController@getUserCredit')->middleware(UserLoginFilter::class);
        Route::get('credit/getConsumeQuotaRecordList', 'CreditController@getConsumeQuotaRecordList')->middleware(UserLoginFilter::class);

        Route::get('dishes/category', 'DishesController@getDishesCategory');
        Route::get('dishes/goods', 'DishesController@getDishesGoods');
        Route::get('dishes/hot', 'DishesController@getHotDishesGoods');
        Route::post('dishes/add','DishesController@add')->middleware(UserLoginFilter::class);
        Route::get('dishes/detail','DishesController@detail')->middleware(UserLoginFilter::class);


        Route::get('tps/getBindInfo', 'TpsBindController@getBindInfo')->middleware(UserLoginFilter::class);
        Route::post('tps/bindAccount', 'TpsBindController@bindAccount')->middleware(UserLoginFilter::class);


        Route::get('wallet/info', 'WalletController@getWallet')->middleware(UserLoginFilter::class);
        Route::get('wallet/bills', 'WalletController@getBills')->middleware(UserLoginFilter::class);
        Route::get('wallet/bill/detail', 'WalletController@getBillDetail')->middleware(UserLoginFilter::class);
        Route::get('wallet/consumeQuotas', 'WalletController@getTpsConsumeQuotas')->middleware(UserLoginFilter::class);
        Route::get('wallet/consumeQuota/detail', 'WalletController@getTpsConsumeQuotaDetail')->middleware(UserLoginFilter::class);
        Route::get('wallet/userFeeSplitting/ratio', 'WalletController@getUserFeeSplittingRatioToSelf')->middleware(UserLoginFilter::class);
        Route::get('wallet/tpsConsume/statistics', 'WalletController@getTpsConsumeStatistics')->middleware(UserLoginFilter::class);
        Route::get('wallet/tpsCredit/statistics', 'WalletController@getTpsCreditStatistics')->middleware(UserLoginFilter::class);
        Route::get('wallet/tpsCredit/list', 'WalletController@getTpsCreditList')->middleware(UserLoginFilter::class);

        Route::post('wallet/confirmPassword', 'WalletController@confirmPassword')->middleware(UserLoginFilter::class);
        Route::post('wallet/checkVerifyCode', 'WalletController@checkVerifyCode')->middleware(UserLoginFilter::class);
        Route::post('wallet/changePassword', 'WalletController@changePassword')->middleware(UserLoginFilter::class);

        // 提现相关
        Route::post('wallet/withdraw/config', 'WalletWithdrawController@getWithdrawConfig')->middleware(UserLoginFilter::class);
        Route::post('wallet/withdraw/withdraw', 'WalletWithdrawController@withdraw')->middleware(UserLoginFilter::class,AllowWithdrawDate::class);

        Route::post('bank/cards/addCard', 'BankCardsController@addCard')->middleware(UserLoginFilter::class);
        Route::post('bank/cards/changDefault', 'BankCardsController@changDefault')->middleware(UserLoginFilter::class);
        Route::post('bank/cards/delCard', 'BankCardsController@delCard')->middleware(UserLoginFilter::class);
        Route::get('bank/cards/getCardsList', 'BankCardsController@getCardsList')->middleware(UserLoginFilter::class);

        Route::post('identity/record/addRecord', 'UserIdentityAuditRecordController@addRecord')->middleware(UserLoginFilter::class);
        Route::post('identity/record/modRecord', 'UserIdentityAuditRecordController@modRecord')->middleware(UserLoginFilter::class);
        Route::get('identity/record/getRecord','UserIdentityAuditRecordController@getRecord')->middleware(UserLoginFilter::class);
        Route::get('bank/getList', 'BankController@getList')->middleware(UserLoginFilter::class);
        Route::get('message/isShowRedDot', 'MessageController@isShowReDot')->middleware(UserLoginFilter::class);

        Route::get('user/message/systems', 'Admin\MessageSystemController@getSystems')->middleware('user',UserLoginFilter::class);
        Route::get('user/message/notices', 'UserApp\MessageController@getNotices')->middleware('user',UserLoginFilter::class);
        Route::get('user/message/noticesNum', 'UserApp\MessageController@getNeedViewNum')->middleware('user',UserLoginFilter::class);
        Route::get('user/message/noticesDetail', 'UserApp\MessageController@getNoticeDetail')->middleware('user',UserLoginFilter::class);
        Route::get('user/message/systemDetail', 'UserApp\MessageController@getSystemDetail')->middleware('user',UserLoginFilter::class);

        Route::get('/country/list', 'CountryController@getList');

    });