<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/20
 * Time: 18:27
 */

namespace App\Exports;


use App\Modules\Merchant\MerchantCategoryService;
use App\Modules\Merchant\MerchantService;
use App\Modules\Oper\Oper;
use App\Modules\Oper\OperBizMember;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MerchantExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable;

    protected $id;
    protected $startDate;
    protected $endDate;
    protected $name;
    protected $status;
    protected $auditStatus;
    protected $operId;
    protected $operName;
    protected $creatorOperId;
    protected $creatorOperName;
    protected $signboardName;
    protected $merchantCategory;
    protected $isPilot;

    public function __construct($id = '', $startDate = '',$endDate = '',$signboardName='', $name = '', $status = '', $auditStatus = [], $operId = '', $operName = '', $merchantCategory = [], $isPilot = 0)
    {
        $this->id = $id;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->name = $name;
        $this->status = $status;
        $this->auditStatus = $auditStatus;
        $this->operId = $operId;
        $this->operName = $operName;
        $this->signboardName = $signboardName;
        $this->creatorOperId = '';
        $this->creatorOperName = '';
        $this->merchantCategory = $merchantCategory;
        $this->isPilot = $isPilot;
    }

    /**
     * 在 MerchantExport 类中,添加 FromQuery 关系, 并且添加一个查询, 并且确保不要使用 ->get() 来获取到数据!
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        $id = $this->id;
        $startDate = $this->startDate;
        $endDate = $this->endDate;
        $name = $this->name;
        $status = $this->status;
        $auditStatus = $this->auditStatus;
        if(empty($auditStatus)){
            $auditStatus=["0","1","2","3"];
        }
        $operId = $this->operId;
        $operName = $this->operName;
        $creatorOperId = $this->creatorOperId;
        $creatorOperName = $this->creatorOperName;
        $signboardName = $this->signboardName;
        $merchantCategory = $this->merchantCategory;
        $isPilot = $this->isPilot;

        $operIds = null;
        if($operName) {
            $operIds = Oper::where('name', 'like', "%$operName%")
                ->select('id')
                ->get()
                ->pluck('id');
        }

        $createOperIds=null;
        if($creatorOperName){
            $createOperIds = Oper::where('name', 'like', "%$creatorOperName%")
                ->select('id')
                ->get()
                ->pluck('id');
        }

        $query = MerchantService::getList([
            'id' => $id,
            'name' => $name,
            'signboardName' => $signboardName,
            'operId' => $operIds ?? $operId,
            'creatorOperId' => $createOperIds ?? $creatorOperId,
            'status' => $status,
            'auditStatus' => $auditStatus,
            'merchantCategory' => $merchantCategory,
            'isPilot' => $isPilot,
            'startCreatedAt' => $startDate,
            'endCreatedAt' => $endDate,
        ], true);

        return $query;
    }

    /**
     * 遍历行
     * @param mixed $data
     * @return array
     */
    public function map($data): array
    {
        return [
            $data->created_at,
            $data->id,
            $data->operId = $data->oper_id > 0 ? $data->oper_id : $data->audit_oper_id,
            Oper::where('id', $data->oper_id > 0 ? $data->oper_id : $data->audit_oper_id)->value('name'),
//            $data->creator_oper_id,
//            Oper::where('id', $data->creator_oper_id)->value('name'),
            $data->name,
            $data->signboard_name,
            $this->getOperBizMemberName($data->operId,$data->oper_biz_member_code),
            $this->getCategoryPathName($data->merchant_category_id),
            $data->city . ' ' . $data->area,
            ['待审核', '审核通过', '审核不通过', '待审核(重新提交)'][$data->audit_status],
        ];
    }

    /**
     * 获取行业名称
     * @param $merchant_category_id
     * @return string
     */
    public function getCategoryPathName($merchant_category_id)
    {
        $categoryPath = MerchantCategoryService::getCategoryPath($merchant_category_id);
        $categoryPathName = '';
        foreach ($categoryPath as $item){
            $categoryPathName = $categoryPathName . $item['name'] . ' ';
        }
        return $categoryPathName;
    }

    /**
     * 获取业务员
     * @param $oper_id
     * @param $oper_biz_member_code
     * @return string
     */
    public function getOperBizMemberName($oper_id,$oper_biz_member_code){
        return OperBizMember::where('oper_id', $oper_id)->where('code', $oper_biz_member_code)->value('name') ?: '';
    }

    /**
     * 添加表头
     * @return array
     */
    public function headings(): array
    {
        return [
            '添加时间',
            '商户ID',
            '激活运营中心ID',
            '激活运营中心名称',
//            '录入运营中心ID',
//            '录入运营中心名称',
            '商户名称',
            '商户招牌名',
            '业务员',
            '行业',
            '城市',
            '审核状态',
        ];
    }
}