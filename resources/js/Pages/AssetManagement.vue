<template>
    <div class="container mx-auto p-6 bg-gray-50 min-h-screen">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">資產管理 (RFID 綁定)</h1>

        <!-- 操作按鈕 -->
        <div class="mb-4 flex space-x-2">
            <button @click="showAddModal = true"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition duration-300">
                新增單筆資產
            </button>
            <button @click="showImportModal = true"
                class="bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 transition duration-300">
                批量導入
            </button>
        </div>

        <!-- 搜尋 -->
        <input type="text" v-model="searchQuery" @input="fetchAssets" placeholder="搜尋資產名稱、編號、RFID..."
            class="w-full p-2 border border-gray-300 rounded-lg mb-4 focus:ring-blue-500 focus:border-blue-500">

        <!-- 資產列表 -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">固資編號
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RFID 標籤
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">設備名稱
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">保管人</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="loading" class="text-center">
                        <td colspan="6" class="py-4">加載中...</td>
                    </tr>
                    <tr v-for="asset in assets" :key="asset.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ asset.asset_tag }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span v-if="asset.rfid_tag"
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                {{ asset.rfid_tag }}
                            </span>
                            <span v-else
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                未綁定
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ asset.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ asset.responsible_user?.name || 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ asset.status }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click="openEditModal(asset)"
                                class="text-indigo-600 hover:text-indigo-900">編輯/綁定</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- TODO: 分頁組件 -->

        <!-- 編輯/新增 Modal -->
        <div v-if="showEditModal || showAddModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex justify-center items-center">
            <div class="relative bg-white rounded-lg shadow-xl p-8 m-4 w-full max-w-lg">
                <h3 class="text-2xl font-semibold mb-6">{{ isEditMode ? '編輯/綁定資產' : '新增資產' }}</h3>
                <form @submit.prevent="saveAsset">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">固資編號 <span class="text-red-500">*</span></label>
                            <input type="text" v-model="currentAsset.asset_tag" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">RFID 標籤 (掃描器輸入)</label>
                            <input type="text" v-model="currentAsset.rfid_tag"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="請在此欄位掃描 RFID">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">設備名稱 <span class="text-red-500">*</span></label>
                            <input type="text" v-model="currentAsset.name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">保管人 <span class="text-red-500">*</span></label>
                            <select v-model="currentAsset.responsible_user_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                                <option disabled value="">請選擇</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-700">資產狀態 <span class="text-red-500">*</span></label>
                            <select v-model="currentAsset.status" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                                <option>使用中</option>
                                <option>閒置</option>
                                <option>維修中</option>
                                <option>已報廢</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end space-x-3">
                        <button type="button" @click="closeModal"
                            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">取消</button>
                        <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700">儲存</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 批量導入 Modal -->
        <!-- TODO: 實作批量導入 Modal 介面 -->
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import axios from 'axios';

// 狀態
const assets = ref([]);
const users = ref([]); // 保管人列表
const loading = ref(false);
const searchQuery = ref('');

const showAddModal = ref(false);
const showEditModal = ref(false);
const showImportModal = ref(false);
const isEditMode = ref(false);

const defaultAsset = {
    asset_tag: '',
    rfid_tag: '',
    name: '',
    responsible_user_id: '',
    status: '使用中',
};
const currentAsset = reactive({ ...defaultAsset });

// API 請求
async function fetchAssets() {
    loading.value = true;
    try {
        const response = await axios.get('/api/assets', {
            params: { search: searchQuery.value }
        });
        assets.value = response.data.data; // 假設使用分頁
    } catch (error) {
        console.error('獲取資產列表失敗:', error);
    } finally {
        loading.value = false;
    }
}

async function fetchUsers() {
    try {
        const response = await axios.get('/api/users');
        users.value = response.data;
    } catch (error) {
        console.error('獲取用戶列表失敗:', error);
    }
}

async function saveAsset() {
    try {
        if (isEditMode.value) {
            // 更新
            const response = await axios.put(`/api/assets/${currentAsset.id}`, currentAsset);
            // 更新列表中的該筆資料
            const index = assets.value.findIndex(a => a.id === currentAsset.id);
            if (index !== -1) {
                assets.value[index] = response.data;
            }
        } else {
            // 新增
            const response = await axios.post('/api/assets', currentAsset);
            assets.value.unshift(response.data); // 加到列表最前面
        }
        closeModal();
    } catch (error) {
        console.error('儲存資產失敗:', error);
        alert('儲存失敗：' + (error.response?.data?.message || '請檢查欄位'));
    }
}

// Modal 控制
function openEditModal(asset) {
    isEditMode.value = true;
    Object.assign(currentAsset, asset); // 複製資產資料到表單
    showEditModal.value = true;
}

function closeModal() {
    showAddModal.value = false;
    showEditModal.value = false;
    isEditMode.value = false;
    Object.assign(currentAsset, defaultAsset); // 重置表單
}

// 初始化
onMounted(() => {
    fetchAssets();
    fetchUsers();
});
</script>