<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->increments('id');
            // 商家基本信息
            $table->integer('oper_id')->index()->default(0)->comment('所属运营中心ID');
            $table->integer('merchant_category_id')->index()->default(0)->comment('商家类别ID');
            $table->string('name')->default('')->comment('商家名称');
            $table->string('brand')->default('')->comment('商家品牌');
            $table->tinyInteger('region')->index()->default(1)->comment('运营地区/大区 1-中国 2-美国 3-韩国 4-香港');
            $table->string('province')->default('')->comment('所在省份');
            $table->integer('province_id')->index()->default(0)->comment('所在省份Id');
            $table->string('city')->default('')->comment('所在城市');
            $table->integer('city_id')->index()->default(0)->comment('所在城市Id');
            $table->string('area')->default('')->comment('所在县区');
            $table->integer('area_id')->index()->default(0)->comment('所在县区Id');
            $table->string('business_time')->default('')->comment('营业时间, json格式字符串 {startTime, endTime}');
            $table->string('logo')->default('')->comment('商家logo');
            $table->string('desc_pic')->default('')->comment('商家介绍图片 [保留, 使用desc_pic_list]');
            $table->string('desc_pic_list', 2000)->default('')->comment('商家介绍图片列表  多图, 使用逗号分隔 ');
            $table->string('desc')->default('')->comment('商家介绍');
            $table->string('invoice_title')->default('')->comment('发票抬头 [废弃, 使用商户名或营业执照图片中的公司名]');
            $table->string('invoice_no')->default('')->comment('发票税号 [废弃, 同商户营业执照编号]');
            $table->tinyInteger('status')->index()->default(1)->comment('状态 1-正常 2-禁用');
            $table->decimal('lng', 15, 12)->default(0)->comment('商家所在经度');
            $table->decimal('lat', 15, 12)->default(0)->comment('商家所在纬度');
            $table->string('address')->default('')->comment('商家地址');
            $table->string('contacter')->default('')->comment('负责人姓名');
            $table->string('contacter_phone')->default('')->comment('负责人联系方式');

            // 商务信息
            $table->tinyInteger('settlement_cycle_type')->default(1)->comment('结款周期 1-周结 2-半月结 3-月结 4-半年结 5-年结');
            $table->decimal('settlement_rate', 4, 2)->default(0)->comment('分利比例(结算时的费率)');

            $table->string('business_licence_pic_url')->default('')->comment('营业执照');
            $table->string('organization_code')->default('')->comment('组织机构代码, 即营业执照代码');
            $table->string('tax_cert_pic_url')->default('')->comment('税务登记证');
            $table->string('legal_id_card_pic_a')->default('')->comment('法人身份证正面');
            $table->string('legal_id_card_pic_b')->default('')->comment('法人身份证反面');
            $table->string('contract_pic_url', 2000)->default('')->comment('合同照片, 多张图片使用逗号分隔');
            $table->string('licence_pic_url')->default('')->comment('开户许可证');
            $table->string('hygienic_licence_pic_url')->default('')->comment('卫生许可证');
            $table->string('agreement_pic_url')->default('')->comment('协议文件');

            // 银行信息
            $table->tinyInteger('bank_card_type')->default(1)->comment('银行账户类型 1-公司账户 2-个人账户');
            $table->string('bank_open_name')->default('')->comment('银行开户名');
            $table->string('bank_card_no')->default('')->comment('银行账号');
            $table->string('sub_bank_name')->default('')->comment('开户支行名称');
            $table->string('bank_open_address')->default('')->comment('开户支行地址');

            $table->tinyInteger('audit_status')->default(0)->comment('商户资料审核状态 0-未审核 1-已审核 2-审核不通过 3-重新提交审核');

            $table->timestamps();

            $table->comment = '商家表';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchants');
    }
}
