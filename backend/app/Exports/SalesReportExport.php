<?php

namespace App\Exports;

use App\Models\OrderItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $startDate;
    protected $endDate;
    protected $tenantId;

    public function __construct($startDate, $endDate, $tenantId)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->tenantId = $tenantId;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return OrderItem::with(['order', 'product'])
            ->whereHas('order', function($query) {
                $query->where('tenant_id', $this->tenantId)
                      ->where('status', 'completed')
                      ->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })
            ->get();
    }

    public function headings(): array
    {
        return [
            'Ngày',
            'Mã đơn',
            'Sản phẩm',
            'Số lượng',
            'Giá bán',
            'Giá vốn',
            'Thành tiền',
            'Lợi nhuận',
            'ROI (%)',
        ];
    }

    /**
    * @var OrderItem $item
    */
    public function map($item): array
    {
        $price = (float)$item->price_at_purchase;
        $cost = (float)$item->cost_at_purchase;
        $qty = (int)$item->quantity;
        
        $subtotal = $price * $qty;
        $profit = ($price - $cost) * $qty;
        
        // ROI = (Profit / Cost) * 100
        $totalCost = $cost * $qty;
        $roi = $totalCost > 0 ? ($profit / $totalCost) * 100 : ($profit > 0 ? 100 : 0);

        return [
            $item->created_at->format('d/m/Y H:i'),
            $item->order_id,
            $item->product->name ?? 'N/A',
            $qty,
            $price,
            $cost,
            $subtotal,
            $profit,
            round($roi, 2) . '%',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => '"#"##0\ "VND"',
            'F' => '"#"##0\ "VND"',
            'G' => '"#"##0\ "VND"',
            'H' => '"#"##0\ "VND"',
        ];
    }
}
