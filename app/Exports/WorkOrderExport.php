<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkOrderExport implements WithMultipleSheets
{
    protected Collection $unscannedItems;
    protected Collection $scannedItems;

    public function __construct(Collection $unscannedItems, Collection $scannedItems)
    {
        $this->unscannedItems = $unscannedItems;
        $this->scannedItems = $scannedItems;
    }

    public function sheets(): array
    {
        return [
            new WorkOrderItemsSheet($this->unscannedItems, '未盤點'),
            new WorkOrderItemsSheet($this->scannedItems, '已盤點'),
        ];
    }
}

