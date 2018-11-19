<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCsGoodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cs_goods', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cs_merchant_id')->default(0)->comment('超市商户ID');
            $table->integer('cs_merchant_cat_id_level1')->default(0)->comment('一级分类ID');
            $table->integer('cs_merchant_cat_id_level2')->default(0)->comment('二级分类ID');
            $table->string('goods_name')->default('')->comment('商品名称');
            $table->decimal('market_price')->default(0.00)->comment('市场价');
            $table->decimal('price')->default(0.00)->comment('销售价');
            $table->integer('stock')->default(0)->comment('库存');
            $table->string('logo')->default('')->comment('产品LOGO');
            $table->string('detail_imgs',1200)->default('')->comment('详情图片最多五张，逗号分隔');
            $table->string('summary',1000)->default('')->comment('商品简介');
            $table->string('certificate1',1200)->default('其他证书1图片最多六张逗号分隔');
            $table->string('certificate2',1200)->default('其他证书2图片最多六张逗号分隔');
            $table->string('certificate3',1200)->default('其他证书3图片最多六张逗号分隔');
            $table->tinyInteger('status')->default(0)->comment('状态 1上架 2下架');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cs_goods');
    }
}
