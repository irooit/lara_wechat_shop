<template>
    <el-row>
        <el-col :span="24">
            <el-form label-width="120px" label-position="left" size="small">
                <el-col>
                    <div class="title">商户录入信息</div>
                </el-col>
                <!--商户录入信息左侧块-->
                <el-col :span="11">
                    <el-form-item prop="status" label="商户状态">
                        <span v-if="data.status === 1" class="c-green">已启用</span>
                        <span v-else-if="data.status === 2" class="c-danger">已冻结</span>
                        <span v-else>未知 ({{data.status}})</span>
                    </el-form-item>
                    <el-form-item prop="id" label="商户ID">{{data.id}}</el-form-item>
                    <el-form-item v-if="data.operName" prop="operName" label="运营中心">{{data.operName}}</el-form-item>
                    <el-form-item prop="name" label="商户名称">{{data.name}}</el-form-item>
                    <el-form-item prop="signboard_name" label="招牌名称">{{data.signboard_name}}</el-form-item>
                    <el-form-item prop="merchant_category" label="所属行业">
                        <span v-for="item in data.categoryPath" :key="item.id">
                            {{ item.name }}
                        </span>
                    </el-form-item>
                    <el-form-item label="营业执照">
                        <div class="licence" v-viewer style="display: none;">
                                <img :src="data.business_licence_pic_url" />
                            </div>
                        <el-button type="text" @click="previewImage('licence')">查看</el-button>
                        <!-- <el-button type="text" @click="previewImage(data.business_licence_pic_url)">查看</el-button> -->
                    </el-form-item>

                </el-col>
                <el-col :span="12" :offset="1">
                    <el-form-item prop="audit_status" label="审核状态">
                        <span v-if="data.audit_status === 0" class="c-warning">待审核</span>
                        <span v-else-if="data.audit_status === 1" class="c-green">审核通过({{data.active_time}})</span>
                        <span v-else-if="data.audit_status === 2" class="c-danger">审核不通过</span>
                        <span v-else-if="data.audit_status === 3" class="c-warning">重新提交审核</span>
                        <span v-else>未知 ({{data.audit_status}})</span>
                    </el-form-item>
                </el-col>

                <!-- 商户录入信息右侧块 -->
                <el-col :span="11" :offset="1">
                    <el-form-item prop="location" label="商户坐标">
                        {{data.lng}} , {{data.lat}}
                    </el-form-item>
                    <el-form-item prop="operAddress" label="运营中心地址">
                        {{data.operAddress}}
                    </el-form-item>
                    <el-form-item prop="area" label="省市区">
                        {{data.province}} {{data.city}} {{data.area}}
                    </el-form-item>
                    <el-form-item prop="address" label="详细地址">
                        {{data.address}}
                    </el-form-item>
                    <el-form-item label="营业执照代码">
                        {{ data.organization_code}}
                    </el-form-item>
                </el-col>

                <!-- 商户激活信息 -->
                <el-col v-if="type != 'poolOnly'">
                    <el-col>
                        <div class="title">商户激活信息</div>
                    </el-col>
                    <!-- 商户激活信息左侧块 -->
                    <el-col :span="11">
                        <el-form-item prop="oper_biz_member_code" label="业务员">
                            <template v-if="data.oper_biz_member_code">
                                {{data.operBizMemberName}}
                            </template>
                        </el-form-item>
                        <!--<el-form-item prop="brand" label="品牌">{{data.brand}}</el-form-item>-->
                        <!--<el-form-item prop="invoice_title" label="发票抬头">{{data.invoice_title}}</el-form-item>-->
                        <!--<el-form-item prop="invoice_no" label="发票税号">{{data.invoice_no}}</el-form-item>-->

                        <el-form-item prop="business_time" label="营业时间">
                            {{data.business_time[0]}} 至 {{data.business_time[1]}}
                        </el-form-item>
                        <el-form-item prop="logo" label="商家logo">
                            <div v-viewer>
                                <img :src="data.logo" alt="商家logo" width="190px" height="190px" />
                            </div>
                            <!-- <preview-img :url="data.logo" width="50px" height="50px"/> -->
                        </el-form-item>
                        <el-form-item prop="desc_pic" label="商家介绍图片">
                            <div class="desc" v-viewer style="display: none;">
                                <img v-for="(item,index) in data.desc_pic_list" :src="item" :key="index" />
                            </div>
                            <el-button type="text" @click="previewImage('desc')">查看</el-button>
                            <!-- <el-button type="text" @click="previewImage(data.desc_pic_list)">查看</el-button> -->
                        </el-form-item>
                        <el-form-item prop="desc_pic" label="商家介绍">
                            {{data.desc}}
                        </el-form-item>
                        <el-form-item label="结算周期">
                            {{ {1: '周结', 2: '半月结', 3: 'T+1(自动)', 4: '半年结', 5: '年结', 6: 'T+1(人工)', 7: '未知',}[data.settlement_cycle_type] }}
                        </el-form-item>
                        <el-form-item label="分利比例">
                            {{ data.settlement_rate }} %
                        </el-form-item>

                        <!-- 银行卡信息 start -->
                        <el-form-item label="银行帐户类型">
                            <span v-if="data.bank_card_type === 1" class="c-gray">公司</span>
                            <span v-if="data.bank_card_type === 2" class="c-green">个人</span>
                        </el-form-item>
                        <el-form-item label="开户名">
                            {{data.bank_open_name}}
                        </el-form-item>
                        <el-form-item label="银行账号">
                            {{data.bank_card_no}}
                        </el-form-item>
                        <el-form-item label="开户支行名称">
                            {{data.sub_bank_name}}
                        </el-form-item>
                        <el-form-item label="开户支行地址">
                            {{data.bank_open_address}}
                        </el-form-item>
                        <el-form-item v-if="data.bank_card_type == 1" required prop="licence_pic_url" label="开户许可证">
                            <div v-viewer>
                                <img :src="data.licence_pic_url" alt="开户许可证" width="200px" height="100px" />
                            </div>
                            <!-- <preview-img :url="data.licence_pic_url" width="100px" height="100px"/> -->
                        </el-form-item>
                        <el-form-item v-if="data.bank_card_type == 2" required label="法人银行卡正面照" prop="bank_card_pic_a">
                            <div v-viewer>
                                <img v-if="data.bank_card_pic_a.length == 1" :src="data.bank_card_pic_a" alt="法人银行卡正面照" width="200px" height="100px" />
                                <div>
                                    <img v-if="data.bank_card_pic_a.length == 2" :src="data.bank_card_pic_a[0]" alt="法人银行卡正面照" width="200px" height="100px" />
                                </div>
                                <div>
                                    <img v-if="data.bank_card_pic_a.length == 2" :src="data.bank_card_pic_a[1]" alt="法人银行卡正面照" width="200px" height="100px" />
                                </div>
                            </div>
                            <!-- <preview-img :url="data.bank_card_pic_a" width="100px" height="100px"/> -->
                        </el-form-item>
                        <!-- 银行卡信息 end -->

                        <el-form-item label="法人身份证正反面">
                            <div v-viewer>
                                <img :src="data.legal_id_card_pic_a" width="200px" height="100px" alt="法人身份证正反面" />
                            </div>
                            <div v-viewer>
                                <img :src="data.legal_id_card_pic_b" width="200px" height="100px" alt="法人身份证正反面" />
                            </div>
                            <!-- <preview-img :url="data.legal_id_card_pic_a" width="200px" height="100px"/>
                            <preview-img :url="data.legal_id_card_pic_b" width="200px" height="100px"/> -->
                        </el-form-item>
                        <el-form-item label="合同">
                            <div class="contract" v-viewer style="display: none;">
                                <img v-for="(item, index) in data.contract_pic_url" :src="item" :key="index" alt="合同" />
                            </div>
                            <el-button type="text" @click="previewImage('contract')">查看</el-button>
                        </el-form-item>

                        <el-form-item prop="other_card_pic_urls" label="其他证件">

                            <viewer :images="data.other_card_pic_urls">
                                <img v-for="(src,index) in data.other_card_pic_urls" :src="src" :key="index" width="200px" height="100px" alt="其他证件" />
                            </viewer>
                            <!-- <template v-for="pic in data.other_card_pic_urls">
                                <preview-img :url="pic" width="200px" height="100px"/>
                            </template> -->
                        </el-form-item>
                    </el-col>


                    <!-- 商户激活信息右侧块 -->
                    <el-col :span="11" :offset="1">
                        <el-form-item prop="contacter" label="负责人姓名">
                            {{data.contacter}}
                        </el-form-item>
                        <el-form-item prop="contacter_phone" label="负责人联系方式">
                            {{data.contacter_phone}}
                        </el-form-item>
                        <el-form-item prop="service_phone" label="客服电话">
                            {{data.service_phone}}
                        </el-form-item>
                        <el-form-item prop="site_acreage" label="商户面积">
                            {{data.site_acreage}} ㎡
                        </el-form-item>
                        <el-form-item prop="employees_number" label="商户员工人数">
                            {{data.employees_number}} 人
                        </el-form-item>
                    </el-col>

                </el-col>


                <el-col  >
                    <el-form-item >
                        <el-button type="primary" @click="back()">返回</el-button>
                        <!-- <el-button type="text" @click="previewImage(data.business_licence_pic_url)">查看</el-button> -->
                    </el-form-item>

                </el-col>
            </el-form>

            <img-preview-dialog :visible.sync="isShowPreviewImage" :url="currentPreviewImage"/>

        </el-col>
    </el-row>

</template>

<script>

    import previewImg from '../../../assets/components/img/preview-img'
    import imgPreviewDialog from '../../../assets/components/img/preview-dialog'
    import 'viewerjs/dist/viewer.css'

    export default {
        name: 'merchant-detail',
        props: {
            data: Object,
            type: String,
        },
        computed:{

        },
        data(){
            return {
                isShowPreviewImage: false,
                currentPreviewImage: '',
            }
        },
        methods: {
            previewImage(viewerEl){
                // this.currentPreviewImage = url;
                // this.isShowPreviewImage = true;
                const viewer = this.$el.querySelector('.' + viewerEl).$viewer
                viewer.show()
            },

            back(){
                router.back(-1)
            }
        },
        created(){
        },
        components: {
            previewImg,
            imgPreviewDialog,
        }
    }

</script>

<style scoped>
    .title {
        font-weight: 600;
        line-height: 50px;
    }
</style>