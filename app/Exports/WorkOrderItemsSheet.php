<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class WorkOrderItemsSheet extends StringValueBinder implements FromCollection, WithHeadings, WithTitle, WithCustomValueBinder
{
    protected Collection $items;
    protected string $title;

    public function __construct(Collection $items, string $title)
    {
        $this->items = $items;
        $this->title = $title;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return [
            '固資編號',
            '設備名稱',
            'RFID 標籤 ID',
            '保管人',
            '狀態',
            '廠區',
            '樓層',
            '區域',
            '存放位置',
        ];
    }

    public function collection(): Collection
    {
        return $this->items->map(function ($item) {
            $asset = $item->fixedAsset;

            return [
                (string) ($asset?->asset_tag ?? ''),
                (string) ($asset?->name ?? ''),
                (string) ($asset?->rfid_tag ?? ''),
                (string) ($asset?->responsibleUser?->name ?? $asset?->keeper ?? ''),
                (string) ($asset?->status ?? ''),
                (string) ($asset?->factory_area ?? ''),
                (string) ($asset?->floor ?? ''),
                (string) ($asset?->area ?? ''),
                (string) ($asset?->location ?? ''),
            ];
        });
    }
}

