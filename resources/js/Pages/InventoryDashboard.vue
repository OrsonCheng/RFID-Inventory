<template>
    <div class="container mx-auto p-6 bg-gray-50 min-h-screen">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">盤點工單儀表板</h1>

        <!-- 操作 -->
        <div class="mb-4">
            <button @click="showCreateModal = true"
                class="bg-purple-600 text-white px-4 py-2 rounded-lg shadow hover:bg-purple-700 transition duration-300">
                + 生成新工單
            </button>
        </div>

        <!-- 工單列表 -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">工單標題</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">保管人</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">應盤數量</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">創建時間</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="loading" class="text-center">
                        <td colspan="6" class="py-4">加載中...</td>
                    </tr>
                    <tr v-for="order in workOrders" :key="order.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.title }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ order.responsible_user?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                             <span :class="statusClass(order.status)"
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                {{ order.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ order.items_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <router-link :to="{ name: 'InventoryWorkPage', params: { id: order.id } }"
                                class="text-indigo-600 hover:text-indigo-900">
                                進入盤點
                            </router-link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- TODO: 分頁 -->

        <!-- 生成工單 Modal -->
        <div v-if="showCreateModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex justify-center items-center">
            <div class="relative bg-white rounded-lg shadow-xl p-8 m-4 w-full max-w-md">
                <h3 class="text-2xl font-semibold mb-6">生成盤點工單</h3>
                <form @submit.prevent="createWorkOrder">
                    <div class->
                        <label class="block text-sm font-medium text-gray-700">選擇保管人 <span class="text-red-500">*</span></label>
                        <select v-model="newOrderForm.responsible_user_id" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                            <option disabled value="">請選擇要盤點的保管人</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                         <p v-if="createError" class="text-red-500 text-sm mt-2">{{ createError }}</p>
                    </div>
                    <div class="mt-8 flex justify-end space-x-3">
                        <button type="button" @click="showCreateModal = false; createError = null;"
                            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">取消</button>
                        <button type="submit" :disabled="isCreating"
                            class="bg-purple-600 text-white px-4 py-2 rounded-lg shadow hover:bg-purple-700 disabled:opacity-50">
                            {{ isCreating ? '生成中...' : '確認生成' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const workOrders = ref([]);
const users = ref([]); // 保管人列表
const loading = ref(false);
const isCreating = ref(false);
const createError = ref(null);
const showCreateModal = ref(false);

const newOrderForm = reactive({
    responsible_user_id: '',
});

async function fetchWorkOrders() {
    loading.value = true;
    try {
        const response = await axios.get('/api/inventory/work-orders');
        workOrders.value = response.data.data; // 假設使用分頁
    } catch (error) {
        console.error('獲取工單列表失敗:', error);
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

async function createWorkOrder() {
    isCreating.value = true;
    createError.value = null;
    try {
        const response = await axios.post('/api/inventory/work-orders', newOrderForm);
        workOrders.value.unshift(response.data); // 加到列表最前面
        showCreateModal.value = false;
        newOrderForm.responsible_user_id = '';
        // 成功後，跳轉到該工單頁面
        router.push({ name: 'InventoryWorkPage', params: { id: response.data.id } });
    } catch (error) {
        console.error('生成工單失敗:', error);
        createError.value = error.response?.data?.message || '生成失敗，請重試。';
    } finally {
        isCreating.value = false;
    }
}

function statusClass(status) {
    if (status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'in_progress') return 'bg-blue-100 text-blue-800';
    return 'bg-yellow-100 text-yellow-800'; // pending
}

onMounted(() => {
    fetchWorkOrders();
    fetchUsers();
});
</script>