<template>
    <page title="账户总览">
        <div class="group">
            <div class="item" v-for="(item, index) in wallet">
                <p class="label">{{item.label}}</p >
                <p class="val">{{item.val}}元</p >
                <!--<el-tooltip v-if="index == 2" placement="top">
                    <div slot="content">
                        <p>冻结金额：</p>
                        <p>1、包括提现中和暂未结算完成的金额</p>
                        <p>2、暂未结算的金额包括用户还未消费的订单以及运营中心还未结算的金额</p>
                    </div>
                    <i class="el-icon-info"></i>
                </el-tooltip>-->
            </div>
            <div class="handler">
                <el-button type="success" @click="withdraw">我要提现</el-button>
            </div>
        </div>

        <el-form :model="query" inline size="small">
            <el-form-item prop="billNo" label="交易号">
                <el-input v-model="query.billNo" placeholder="请输入交易号" clearable/>
            </el-form-item>
            <el-form-item label="交易时间">
                <el-date-picker
                        v-model="query.startDate"
                        type="date"
                        placeholder="选择开始日期"
                        format="yyyy 年 MM 月 dd 日"
                        value-format="yyyy-MM-dd"
                        clearable
                >
                </el-date-picker>
                <span>—</span>
                <el-date-picker
                        v-model="query.endDate"
                        type="date"
                        placeholder="选择结束日期"
                        format="yyyy 年 MM 月 dd 日"
                        value-format="yyyy-MM-dd"
                        clearable
                        :picker-options="{disabledDate: (time) => {return time.getTime() < new Date(query.startDate) - 8.64e7}}"
                >
                </el-date-picker>
            </el-form-item>
            <el-form-item label="交易类型">
                <el-select v-model="query.type" placeholder="请选择" clearable class="w-150">
                    <el-option label="全部" :value="0"></el-option>
                    <el-option label="提现" :value="7"></el-option>
                    <el-option label="提现失败" :value="8"></el-option>
                    <el-option label="交易分润入账" :value="9"></el-option>
                    <!--<el-option label="交易分润退款" :value="10"></el-option>-->
                </el-select>
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="search">查 询</el-button>
                <el-button type="success" @click="download">导出Excel</el-button>
            </el-form-item>
        </el-form>
        <el-table :data="list" v-loading="tableLoading" stripe>
            <el-table-column prop="created_at" label="交易时间"></el-table-column>
            <el-table-column prop="bill_no" label="交易号"></el-table-column>
            <el-table-column prop="type" label="交易类型">
                <template slot-scope="scope">
                    <span v-if="scope.row.type == 2">下级消费返利</span>
                    <span v-else-if="scope.row.type == 4">下级消费返利退款</span>
                    <span v-else-if="scope.row.type == 5">交易分润入账</span>
                    <span v-else-if="scope.row.type == 6">交易分润退款</span>
                    <span v-else-if="scope.row.type == 7">
                        提现
                        <span v-if="scope.row.status == 1">[审核中]</span>
                        <span v-else-if="scope.row.status == 2">[审核通过]</span>
                        <span v-else-if="scope.row.status == 3">[已打款]</span>
                        <span v-else-if="scope.row.status == 4">[打款失败]</span>
                        <span v-else-if="scope.row.status == 5">[审核不通过]</span>
                        <span v-else>[未知 ({{scope.row.status}})]</span>
                    </span>
                    <span v-else-if="scope.row.type == 8">提现失败</span>
                    <span v-else-if="scope.row.type == 9">交易分润入账</span>
                    <span v-else-if="scope.row.type == 10">交易分润退款</span>
                    <span v-else>未知{{scope.row.type}}</span>
                </template>
            </el-table-column>
            <el-table-column prop="amount" label="账户交易金额">
                <template slot-scope="scope">
                    <span v-if="scope.row.inout_type == 1">+</span>
                    <span v-else>-</span>
                    {{scope.row.amount}}
                </template>
            </el-table-column>
            <!--<el-table-column prop="after_amount" label="账户余额"></el-table-column>-->
            <el-table-column label="操作">
                <template slot-scope="scope">
                    <el-button type="text" @click="detail(scope.row)">明 细</el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-pagination
                class="fr m-t-20"
                layout="total, prev, pager, next"
                :current-page.sync="query.page"
                @current-change="getList"
                :page-size="query.pageSize"
                :total="total"
        />

        <el-dialog title="明细" center :visible.sync="showDetailDialog" width="50%">
            <detail :billData="billData" :orderOrWithdrawData="orderOrWithdrawData"></detail>
        </el-dialog>

        <el-dialog title="提现"
               center
               :visible.sync="showWithdrawDialog"
               :close-on-click-modal="false"
               :close-on-press-escape="false"
               width="50%">
            <withdraw-form
                @closeWithdrawDialog="showWithdrawDialog = false"
                @getList="getList"
            ></withdraw-form>
        </el-dialog>
    </page>
</template>

<script>
    import api from '../../../assets/js/api'
    import detail from './detail'
    import WithdrawForm from '../withdraw/form'

    export default {
        name: "wallet-list",
        data() {
            return {
                query: {
                    billNo: '',
                    startDate: '',
                    endDate: '',
                    type: 0,
                    page: 1,
                    pageSize: 15,
                },
                total: 0,
                list: [],
                tableLoading: false,

                amount_balance: 0,
                balance: 0,
                freeze_balance: 0,
                has_balanced: 0,

                showDetailDialog: false,
                billData: null,
                orderOrWithdrawData: {},

                showWithdrawDialog: false,
            }
        },
        computed: {
            wallet() {
                return [
                    {
                        label: '账户余额',
                        val: this.amount_balance
                    },
                    {
                        label: '可提现金额',
                        val: this.balance
                    },
                    /*{
                        label: '冻结金额',
                        val: this.freeze_balance
                    },*/
                    {
                        label: '已提现金额',
                        val: this.has_balanced
                    },
                ];
            }
        },
        methods: {
            getList() {
                this.tableLoading = true;
                api.get('/wallet/bill/list', this.query).then(data => {
                    this.list = data.list;
                    this.total = data.total;
                    this.amount_balance = data.amountBalance;
                    this.balance = data.balance;
                    this.freeze_balance = data.freezeBalance;
                    this.has_balanced = data.hasBalanced;
                    this.tableLoading = false;
                })
            },
            search() {
                this.query.page = 1;
                this.getList();
            },
            download() {
                let query = this.query;
                location.href = '/api/bizer/wallet/bill/exportExcel?billNo=' + query.billNo + '&startDate=' + query.startDate + '&endDate=' + query.endDate +'&type=' + query.type;
            },
            detail(row) {
                api.get('/wallet/bill/detail', {id: row.id}).then(data => {
                    this.billData = data.billData;
                    this.orderOrWithdrawData = data.orderOrWithdrawData;
                    this.showDetailDialog = true;
                })
            },
            withdraw() {
                api.get('/wallet/withdraw/getRecordAndWallet').then(data => {
                    let record = data.record;
                    if (record && record.id) {
                        if (record.status == 1) {
                            // 待审核
                            this.$message.error('提现资料正在审核，审核通过才行进行提现操作');
                        } else if (record.status == 2) {
                            // 审核成功
                            if (data.isSetPassword) {
                                this.showWithdrawDialog = true;
                            } else {
                                this.$message.error('请绑定银行卡并设置提现密码');
                                router.replace({
                                    path: '/refresh',
                                    query: {
                                        name: 'WithdrawPasswordForm',
                                        key: '/withdraw/password/form',
                                    }
                                });
                            }
                        } else if (record.status == 3) {
                            // 审核失败
                            this.$message.error('提现资料审核失败，请重新提交审核');
                            router.replace({
                                path: '/refresh',
                                query: {
                                    name: 'WithdrawPasswordForm',
                                    key: '/withdraw/password/form',
                                }
                            });
                        } else {
                            this.$message.error('改审核状态不存在');
                        }
                    } else {
                        router.replace({
                            path: '/refresh',
                            query: {
                                name: 'WithdrawPasswordForm',
                                key: '/withdraw/password/form',
                            }
                        });
                    }
                })
            }
        },
        created() {
            this.getList();
        },
        components: {
            detail,
            WithdrawForm,
        }
    }
</script>

<style lang="less" scoped>
    .group {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 30px 0;
        text-align: center;
        border: 1px solid #ccc;
        margin-bottom: 30px;

        & > * {
            border-right: 1px solid #ccc;
            padding: 15px 0;
        }

        .item {
            flex: 1;
            position: relative;

            p {
                margin: 0;
            }

            .label {
                font-size: 14px;
            }

            .val {
                margin-top: 10px;
                font-size: 28px;
                font-weight: bold;
            }
        }

        .handler {
            flex: 1;
            border-right: 0 none;
        }

        .btn {
            width: 150px;
            height: 40px;
            line-height: 40px;
            margin: 0 auto;
            background: #999;
            color: #fff;
        }
    }
    .el-icon-info {
        position: absolute;
        top: 5px;
        right: 15px;
    }
</style>