<template>
    <page title="添加商户" :breadcrumbs="breadcrumbs">
        <merchant-form v-loading="isLoading" @cancel="cancel" @save="doAdd" @saveDraft="addDraft"/>
    </page>
</template>

<script>
    import api from '../../../assets/js/api'
    import MerchantForm from './merchant-form'

    export default {
        name: "merchant-add",
        components: {
            MerchantForm,
        },
        data() {
            return {
                isLoading: false,
                isDraft: false,
                breadcrumbs: {},
            }
        },
        methods: {
            doAdd(data){
                this.isLoading = true;
                api.post('/merchant/add', data).then(() => {
                    this.$message.success('保存成功');
                    router.push('/merchants');
                }).finally(() => {
                    this.isLoading = false;
                })
            },
            cancel(){
                if (this.isDraft){
                    router.push('/merchant/drafts');
                } else {
                    router.push('/merchants')
                }
            },
            addDraft(data) {
                if (!data.name) {
                    this.$message.error('商户名称不能为空');
                    return false;
                }
                api.post('/merchant/draft/add', data).then((data) => {
                    this.$message.success('保存成功');
                    router.replace({
                        path: '/refresh',
                        query: {
                            name: 'MerchantDraftList',
                            key: '/merchant/drafts'
                        }
                    });

                    let menu_copy = Lockr.get('userMenuList');
                    menu_copy[0].sub[4].name = '草稿箱(' + data.count + ')';
                    store.commit('setMenus', menu_copy);
                }).finally(() => {
                    this.isLoading = false;
                })
            }
        },
        created() {
            if (this.$route.query.type == 'draft-list') {
                this.isDraft = true;
            }
            if (this.isDraft){
                this.breadcrumbs = {'草稿箱': '/merchant/drafts'};
            } else {
                this.breadcrumbs = {'我的商户': '/merchants'};
            }
        }
    }
</script>

<style scoped>

</style>