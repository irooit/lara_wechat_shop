<template>
    <el-row>
        <el-col :span="22">
            <el-form :model="form" label-width="80px" :rules="formRules" ref="form" @submit.native.prevent>
                <el-form-item prop="name" label="姓名">
                    <el-input v-model="form.name"/>
                </el-form-item>
                <el-form-item prop="mobile" label="手机号">
                    <el-input v-model="form.mobile" :maxlength="11"/>
                </el-form-item>
                <el-form-item prop="remark" label="备注">
                    <el-input v-model="form.remark" :maxlength="20" placeholder="20个字以内"/>
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
    let defaultForm = {
        name: '',
        mobile: '',
        remark: '',
    };
    export default {
        name: 'operBizMember-form',
        props: {
            data: Object,
        },
        computed:{

        },
        data(){
            let validateTel = (rule, value, callback) => {
                if (!(/^1[3456789]\d{9}$/.test(value))){
                    callback(new Error('请输入正确的手机号'));
                }else {
                    callback();
                }
            };

            return {
                form: deepCopy(defaultForm),
                formRules: {
                    name: [
                        {required: true, message: '姓名不能为空'},
                        {max: 10, message: '姓名名称不能超过10个字'},
                    ],
                    mobile: [
                        {required: true, message: '手机号不能为空'},
                        {validator: validateTel}
                    ],
                },
            }
        },
        methods: {
            initForm(){
                if(this.data){
                    this.form = deepCopy(this.data)
                }else {
                    this.form = deepCopy(defaultForm)
                }
            },
            cancel(){
                this.$emit('cancel');
                this.resetForm();
            },
            resetForm(){
                this.$refs.form.resetFields();
            },
            save(){
                this.$refs.form.validate(valid => {
                    if(valid){
                        let data = deepCopy(this.form);
                        this.$emit('save', data);
                    }
                })

            }
        },
        created(){
            this.initForm();
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
