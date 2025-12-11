<?php

namespace App\Observers;

use App\Models\FixedAsset;
use Illuminate\Support\Facades\Cache;

class FixedAssetObserver
{
    /**
     * Handle the FixedAsset "saved" event.
     * 這個方法會同時處理 "Created" (新增) 和 "Updated" (更新)
     * 只要資料有寫入 DB，這裡就會觸發
     */
    public function saved(FixedAsset $fixedAsset)
    {
        $this->clearCache($fixedAsset);
    }

    /**
     * Handle the FixedAsset "deleted" event.
     * 刪除資料時，saved 不會觸發，所以這一個必須保留
     */
    public function deleted(FixedAsset $fixedAsset)
    {
        $this->clearCache($fixedAsset);
    }

    /**
     * 把清除邏輯抽離出來，方便重複使用
     */
    protected function clearCache(FixedAsset $fixedAsset)
    {
        // 確保這裡的 Key 邏輯跟 Controller 裡的一模一樣
        $cacheKey = "rfid_lookup:{$fixedAsset->asset_tag}";
        Cache::forget($cacheKey);
    }
}
