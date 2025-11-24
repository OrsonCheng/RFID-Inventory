<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden">
      
      <!-- Header -->
      <div class="bg-indigo-600 p-8 text-center">
        <h1 class="text-2xl font-bold text-white tracking-wide">固定資產 RFID 管理系統</h1>
        <p class="text-indigo-200 text-sm mt-2">TE 部門專用系統</p>
      </div>

      <!-- Form -->
      <div class="p-8">
        
        <!-- SSO 按鈕 -->
        <button 
            @click="handleSSOLogin" 
            class="w-full bg-slate-800 text-white font-bold py-3 rounded-lg hover:bg-slate-900 transition-all shadow-lg mb-6"
        >
            使用公司 AD 帳號登入 (SSO)
        </button>

        <!-- 分隔線 -->
        <div class="relative flex py-2 items-center mb-6">
            <div class="flex-grow border-t border-slate-200"></div>
            <span class="flex-shrink-0 mx-4 text-slate-400 text-xs uppercase tracking-wider font-medium">或是 本地管理員</span>
            <div class="flex-grow border-t border-slate-200"></div>
        </div>

        <!-- 本地表單 -->
        <form @submit.prevent="handleLogin" class="space-y-5">
          
          <!-- 錯誤訊息 -->
          <div v-if="authStore.error" class="bg-rose-50 text-rose-600 p-3 rounded-lg text-sm border border-rose-100">
             {{ authStore.error }}
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email</label>
            <input v-model="form.email" type="email" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm" placeholder="admin@te.com">
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Password</label>
            <input v-model="form.password" type="password" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm" placeholder="••••••••">
          </div>

          <button type="submit" :disabled="authStore.loading" class="w-full bg-white border border-slate-300 text-slate-600 font-bold py-2.5 rounded-lg hover:bg-slate-50 transition-all text-sm">
            {{ authStore.loading ? '驗證中...' : '本地登入' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const form = reactive({ email: '', password: '' });

// 本地登入
const handleLogin = () => {
    authStore.login(form);
};

// SSO 登入 (瀏覽器跳轉)
const handleSSOLogin = () => {
    window.location.href = '/api/auth/redirect';
};
</script>