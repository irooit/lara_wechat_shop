<template>
    <el-row>
        <el-col :span="22">
            <el-form :model="form" label-width="120px" :rules="formRules" ref="form" @submit.native.prevent>
                <el-form-item prop="name" label="单品名称">
                    <el-input v-model="form.name"/>
                </el-form-item>
                <el-form-item prop="market_price" label="市场价" required>
                    <el-input-number v-model="form.market_price" :min="0"></el-input-number>
                </el-form-item>
                <el-form-item prop="sale_price" label="销售价" required>
                    <el-input-number v-model="form.sale_price" :min="0"></el-input-number>
                </el-form-item>
                <el-form-item prop="dishes_category_id" label="类别">
                    <el-select v-model="form.dishes_category_id" placeholder="请选择">
                        <el-option
                            v-for="item in categories"
                            :key="item.id"
                            :label="item.name"
                            :value="item.id"
                        ></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item prop="detail_image" label="单品图片">
                    <image-upload v-model="form.detail_image" :limit="1" :width="imageWidth" :height="imageHeight"></image-upload>
                    <span>图片尺寸：{{imageWidth}}px * {{imageHeight}}px</span>
                </el-form-item>
                <el-form-item prop="intro" label="单品简介">
                    <el-input type="textarea" v-model="form.intro"></el-input>
                </el-form-item>
                <el-form-item prop="status" label="状态">
                    <el-radio-group v-model="form.status">
                        <el-radio :label="1">正常</el-radio>
                        <el-radio :label="2">禁用</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item prop="is_hot" label="是否热销">
                    <el-radio-group v-model="form.is_hot">
                        <el-radio :label="1">是</el-radio>
                        <el-radio :label="0">否</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item>
                    <el-button @click="cancel">取消</el-button>
                    <el-button type="primary" @click="save">保存</el-button>
                </el-form-item>
            </el-form>
        </el-col>
    </el-row>

</template>
<script>
    import api from '../../../assets/js/api'

    let defaultForm = {
        name: '',
        market_price: 0,
        sale_price: 0,
        dishes_category_id: '',
        detail_image: '',
        intro: '',
        status: 1,
        is_hot: 0,
    };
    export default {
        name: 'dishesGoods-form',
        props: {
            data: Object,
        },
        computed:{

        },
        data(){
            var validateSalePrice = (rule, value, callback) => {
                if (value <= 0 || value>=1000000){
                    callback(new Error('销售价必须在0到1000000元之间'));
                }else {
                    callback();
                }
            };
            var validateMarketPrice = (rule, value, callback) => {
                if (value <= 0 || value>=1000000) {
                    callback(new Error('市场价必须在0到1000000元之间'));
                }else {
                    callback();
                }
            };
            return {
                imageWidth: 750,
                imageHeight: 750,
                form: deepCopy(defaultForm),
                categories: [],
                formRules: {
                    name: [
                        {required: true, message: '商品名称不能为空'},
                        {max: 10, message: '商品名称不能超过10个字'}
                    ],
                    market_price: [
                        {validator: validateMarketPrice, trigger: 'blur'}
                    ],
                    sale_price: [
                        {validator: validateSalePrice, trigger: 'blur'}
                    ],
                    dishes_category_id: [
                        {required: true, message: '类别不能为空'}
                    ],
                    detail_image: [
                        {required: true, message: '商品图片不能为空'}
                    ],
                    intro: [
                        {max: 200, message: '商品简介不能超过200个字'}
                    ]
                },
            }
        },
        methods: {
            initForm(){
                if(this.data.id){
                    this.form = deepCopy(this.data)
                }else {
                    this.form = deepCopy(defaultForm)
                }
            },
            cancel(){
                this.$emit('cancel');
                this.reset();
            },
            reset() {
                this.$refs.form.resetFields();
            },
            save(){
                this.$refs.form.validate(valid => {
                    if(valid){
                        let data = deepCopy(this.form);
                        this.$emit('save', data);
                    }
                })
            },
            getCategorys() {
                api.get('/dishes/categories/all',{'status':defaultForm.status}).then(data => {
                    this.categories = data.list;
                })
            }
        },
        created(){
            this.initForm();
            this.getCategorys();
        },
        watch: {
            data(){
                this.initForm();
            }
        },
        components: {
        }
    }
</script>
<style scoped>

</style>
