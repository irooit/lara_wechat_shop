<template>
    <page title="订单列表" v-loading="isLoading">
        <el-form class="fl" inline size="small">
            <el-form-item prop="createdAt" label="创建时间">
                <el-date-picker
                        class="w-150"
                        v-model="query.startTime"
                        type="date"
                        placeholder="开始日期"
                        value-format="yyyy-MM-dd 00:00:00"

                />
                -
                <el-date-picker
                        class="w-150"
                        v-model="query.endTime"
                        type="date"
                        placeholder="结束日期"
                        value-format="yyyy-MM-dd 23:59:59"
                        :picker-options="{disabledDate: (time) => {return time.getTime() < new Date(query.startTime)}}"
                />
            </el-form-item>
            <el-form-item prop="orderNo" label="订单号">
                <el-input v-model="query.orderNo" placeholder="请输入订单号" clearable @keyup.enter.native="search"/>
            </el-form-item>
            <el-form-item prop="order_type" label="订单类型">
                <el-select v-model="query.order_type" class="w-100" clearable>
                    <el-option label="全部" value=""/>
                    <el-option label="团购订单" :value="1"/>
                    <el-option label="扫码买单" :value="2"/>
                    <el-option label="单品订单" :value="3"/>
                </el-select>
            </el-form-item>
            <el-form-item prop="goodsName" label="商品名称" v-if="query.order_type == 1">
                <el-input v-model="query.goodsName" placeholder="请输入商品名称" clearable @keyup.enter.native="search"/>
            </el-form-item>
            <el-form-item label="商户名称">
                <el-select v-model="query.merchantId" filterable clearable >
                    <el-option v-for="item in merchantOptions" :key="item.id" :value="item.id" :label="item.name"/>
                </el-select>
            </el-form-item>
            <el-form-item label="所属运营中心">
                <el-select v-model="query.operId" filterable clearable>
                    <el-option v-for="item in operOptions" :key="item.id" :value="item.oper_id" :label="item.operName"/>
                </el-select>
            </el-form-item>
            <el-form-item>
                <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
            </el-form-item>
            <el-form-item>
                <el-button type="success" @click="exportExcel">导出订单</el-button>
            </el-form-item>
        </el-form>

        <el-table :data="list" stripe>
            <el-table-column prop="created_at" label="创建时间"/>
            <el-table-column prop="order_no" label="订单号"/>
            <el-table-column prop="type" label="订单类型">
                <template slot-scope="scope">
                    <span v-if="scope.row.type == 1">团购订单</span>
                    <span v-else-if="scope.row.type == 2">扫码买单</span>
                    <span v-else-if="scope.row.type == 3">单品订单</span>
                    <span v-else>未知({{scope.row.type}})</span>
                </template>
            </el-table-column>
            <el-table-column prop="goods_name" label="商品名称">
                <template slot-scope="scope">
                    <span v-if="scope.row.type == 3 && scope.row.dishes_items && scope.row.dishes_items.length == 1">
                        {{scope.row.dishes_items[0].dishes_goods_name}}
                    </span>
                    <span v-else-if="scope.row.type == 3 && scope.row.dishes_items && scope.row.dishes_items.length > 1">
                        {{scope.row.dishes_items[0].dishes_goods_name}}等{{getNumber(scope.row.dishes_items)}}件商品
                    </span>
                    <span v-else-if="scope.row.type == 2">
                        无
                    </span>
                    <span v-else>
                        {{scope.row.goods_name}}
                    </span>
                </template>
            </el-table-column>
            <el-table-column prop="pay_price" label="总价（元）"/>
            <el-table-column prop="notify_mobile" label="手机号"/>
            <el-table-column prop="status" label="订单状态">
                <template slot-scope="scope">
                    <span v-if="parseInt(scope.row.status) === 1" class="c-danger">未支付</span>
                    <span v-else-if="parseInt(scope.row.status) === 2">已取消</span>
                    <span v-else-if="parseInt(scope.row.status) === 3">已关闭[超时自动关闭]</span>
                    <span v-else-if="parseInt(scope.row.status) === 4" class="c-green" >已支付</span>
                    <span v-else-if="parseInt(scope.row.status) === 5">退款中[保留状态]</span>
                    <span v-else-if="parseInt(scope.row.status) === 6">已退款</span>
                    <span v-else-if="parseInt(scope.row.status) === 7">已完成</span>
                    <span v-else>未知 ({{scope.row.status}})</span>
                </template>
            </el-table-column>
            <el-table-column prop="merchant_name" label="商户名称"/>
            <el-table-column prop="operName" label="所属运营中心"/>            
            <el-table-column fixed="right" label="操作">
                <template slot-scope="scope">
                    <el-button type="text" @click="details(scope.$index)">查看详情</el-button>
                </template>
            </el-table-column>
        </el-table>

        <el-pagination
                class="fr m-t-20"
                layout="total, prev, pager, next"
                :current-page.sync="query.page"
                @current-change="getList"
                :page-size="15"
                :total="total"/>

        <el-dialog title="订单详情" :visible.sync="dialogDetailVisible" width="700px">
            <div class="dialog-details clearfix">
                <dl>
                    
                    <dd v-if="detailOption.type== 1" class="c-danger">订单类型：团购订单</dd>
                    <dd v-else-if="detailOption.type== 2" class="c-danger">订单类型：扫码买单</dd>
                    <dd v-else-if="detailOption.type== 3" class="c-danger">订单类型：单品订单</dd>
                    <dd v-else class="c-danger">订单类型：未知</dd>
                    
                    <dd>商户名称：{{detailOption.merchant_name}}</dd>
                    <!--<template v-if="detailOption.type== 1">
                        <dd>身份：{{detailOption.merchant_name}}</dd>
                    </template>-->
                    <template v-if="detailOption.type== 1">
                        <dd>单价：{{detailOption.price}}元</dd>
                    </template>
                    <dd>总价：{{detailOption.pay_price}}元</dd>
                    <dd v-if="parseInt(detailOption.status) === 1">订单状态：未支付</dd>
                    <dd v-else-if="parseInt(detailOption.status) === 2">订单状态：已取消</dd>
                    <dd v-else-if="parseInt(detailOption.status) === 3">订单状态：已关闭[超时自动关闭]</dd>
                    <dd v-else-if="parseInt(detailOption.status) === 4">订单状态：已支付</dd>
                    <dd v-else-if="parseInt(detailOption.status) === 5">订单状态：退款中[保留状态]</dd>
                    <dd v-else-if="parseInt(detailOption.status) === 6">订单状态：已退款</dd>
                    <dd v-else-if="parseInt(detailOption.status) === 7">订单状态：已完成</dd>
                    <dd v-else>订单状态：未知</dd>
                    
                </dl>
                <dl>
                    <dd>订单号：{{detailOption.order_no}}</dd>
                    <!--<template v-if="detailOption.type== 1 || detailOption.type== 2">
                        <dd>数量：{{detailOption.buy_number}}</dd>
                    </template>-->
                    <dd>手机号：{{detailOption.notify_mobile}}</dd>
                    <!--<dd>返利积分：20</dd>-->
                    <dd>订单创建时间：{{detailOption.created_at}}</dd>
                    <template v-if="detailOption.type== 1">
                        <dd>商品名称：{{detailOption.goods_name}}</dd>
                    </template>
                    <template v-if="detailOption.type== 3">
                        <dd>
                            <p>商品信息：</p>
                            <div v-for="(item, index) in detailOption.dishes_items" :key="index">
                                <span>{{item.dishes_goods_name}}</span>&nbsp;&nbsp;&nbsp;
                                <span>¥{{item.dishes_goods_sale_price}}</span>&nbsp;&nbsp;&nbsp;
                                <span>×{{item.number}}</span><br/>
                            </div>
                        </dd>
                    </template>
                </dl>
            </div>
        </el-dialog>
    </page>
</template>

<script>
    import api from '../../../assets/js/api'

    export default {
        data(){
            return {
                isLoading: false,
                query: {
                    // createdAt: '',
                    startTime: '',
                    endTime :'',
                    orderNo: '',
                    order_type: '',
                    goodsName: '',
                    merchantName: '',
                    operId: '',
                    page: 1,
                    merchantId:''
                },
                list: [],
                total: 0,
                operOptions:[],
                dialogDetailVisible: false,
                detailOption: {},
                merchantOptions:[],
            }
        },
        computed: {

        },
        methods: {
            search(){
                let _self = this;
                _self.query.page = 1;
                _self.getList();
            },
            getList(){
                let _self = this;
                _self.isLoading = true;
                let params = {};
                Object.assign(params, _self.query);
                api.get('/orders', params).then(data => {
                    _self.query.page = params.page;
                    _self.list = data.list;
                    _self.total = data.total;
                }).catch(() =>{
                    _self.$message({
                      message: '请求失败',
                      type: 'warning'
                    });
                }).finally(() => {
                    _self.isLoading = false;
                })
            },
            details(index){
                this.detailOption = this.list[index];
                this.dialogDetailVisible = true;
            },
            getNumber(row) {
                let num = 0;
                row.forEach(function (item) {
                    num = num + item.number;
                })
                return num;
            },
            exportExcel() {
                let data = this.query;

                let day = 0;
                if (data.startTime && data.endTime) {
                    day = (new Date(data.endTime) - new Date(data.startTime)) / 24 / 3600 / 1000;
                }
                if (!data.startTime || !data.endTime || day > 31) {
                    this.$message.warning('一次最多只能下载一个月的数据');
                    return;
                }
                
                let param = [];
                Object.keys(data).forEach((key) => {
                    let value = data[key];
                    if (typeof value === 'undefined' || value == null) {
                        value = '';
                    }
                    param.push([key, encodeURIComponent(value)].join('='));
                });
                let uri = param.join('&');

                location.href = `/api/bizer/order/export?${uri}`;
            }
        },
        created(){
            let _self = this;
            
            api.get('merchant/opers/tree').then(data => {
                _self.operOptions = data.list;
            });
            api.get('merchant/allMerchantNames').then(data => {
                _self.merchantOptions = data.list; 
            });
            if (_self.$route.params){
                Object.assign(_self.query, _self.$route.params);
            }
            if (_self.$route.query) {
                Object.assign(_self.query,_self.$route.query);
                _self.$route.query.merchantId ? _self.query.merchantId = parseInt(_self.$route.query.merchantId):'';
            }

            _self.getList();
             
        },
        components: {

        },
        watch: {
            'query.order_type': function (val){
                if(val != 1) {
                    this.query.goodsName = '';
                }
            }
        }
    }
</script>

<style scoped>
.clearfix::after {
    display: block;
    clear: both;
    content: "";
    visibility: hidden;
    height: 0;
}
.clearfix {
    zoom: 1;
}
.dialog-details {

}
.dialog-details dl {
    float: left;
    margin: 0;
    width: 50%;
    padding: 0 40px;
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}
.dialog-details dl + dl {
    border-left: 1px solid #ebeef5;
}
.dialog-details dl dd {
    margin: 0;
    line-height: 24px;
    padding: 5px 0;
}
.dialog-details dl dd p {
    margin: 0;
}
</style>
