<?php
/**
 * 运营中心接口路由
 */

use Illuminate\Support\Facades\Route;

Route::prefix('oper')
    ->namespace('Oper')
    ->middleware('oper')->group(function (){

        Route::post('login', 'SelfController@login');
        Route::post('logout', 'SelfController@logout');
        Route::post('self/modifyPassword', 'SelfController@modifyPassword');
        Route::get('self/menus', 'SelfController@getMenus');

        Route::get('area/tree', 'AreaController@getTree');

        Route::get('merchant/categories/tree', 'MerchantCategoryController@getTree');

        Route::get('inviteChannels', 'InviteChannelController@getList');
        Route::get('inviteChannel/export', 'InviteChannelController@export');
        Route::post('inviteChannel/add', 'InviteChannelController@add');
        Route::post('inviteChannel/edit', 'InviteChannelController@edit');
        Route::get('inviteChannel/downloadInviteQrcode', 'InviteChannelController@downloadInviteQrcode');
        Route::get('inviteChannel/inviteRecords', 'InviteChannelController@getInviteRecords');
        Route::get('inviteChannel/inviteRecords/export', 'InviteChannelController@exportInviteRecords');

        Route::get('invite/statistics/dailyList', 'InviteStatisticsController@dailyList');

        Route::get('sms/getVerifyCode', 'SmsController@sendVerifyCode');

        Route::get('orders', 'OrderController@getList');
        Route::get('order/export', 'OrderController@export');
        Route::get('order/cs/export', 'OrderController@csExport');
        Route::get('order/undelivered/num', 'OrderController@getUndeliveredNum');

        Route::get('/tps/getBindInfo', 'TpsBindController@getBindInfo');
        Route::get('/member/userlist', 'MemberController@getList');
        Route::get('/member/channels', 'MemberController@getAllChannel');
        Route::get('/member/export', 'MemberController@export');
        Route::get('/member/statistics/daily', 'MemberController@statisticsDaily');
        Route::get('/member/statistics/total', 'MemberController@getTotal');
        Route::get('/member/statistics/getTodayAndTotalInviteNumber', 'MemberController@getTodayAndTotalInviteNumber');

        Route::get('/country/list', 'CountryController@getList');

        Route::get('goods', 'CsGoodsController@getList');
        Route::get('sub_cat', 'CsGoodsController@getSubCat');
        Route::get('goods/detail', 'CsGoodsController@detail');
        Route::post('cs_goods/audit', 'CsGoodsController@audit');
        Route::get('cs_goods/download', 'CsGoodsController@download');


        Route::group([], base_path('routes/api/oper/merchant.php'));
        Route::group([], base_path('routes/api/oper/csmerchant.php'));
        Route::group([], base_path('routes/api/oper/settlements.php'));
        Route::group([], base_path('routes/api/oper/operBizMember.php'));
        Route::group([], base_path('routes/api/oper/mapping_user.php'));
        Route::group([], base_path('routes/api/oper/wallet.php'));
    });
Route::get('/oper/message/systems', 'Admin\MessageSystemController@getSystems')->middleware('oper');