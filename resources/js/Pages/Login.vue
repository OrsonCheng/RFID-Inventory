<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 relative overflow-hidden px-4">
    <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:24px_24px]"></div>
        <div class="absolute left-0 right-0 top-0 -z-10 m-auto h-[310px] w-[310px] rounded-full bg-indigo-500 opacity-20 blur-[100px]"></div>
    </div>

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl shadow-slate-200/50 overflow-hidden relative z-10 border border-slate-100">
      
      <div class="bg-gradient-to-br from-indigo-600 to-blue-600 p-8 text-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full opacity-10">
            <div class="absolute right-[-20px] top-[-20px] w-24 h-24 rounded-full bg-white blur-xl"></div>
            <div class="absolute left-[-20px] bottom-[-20px] w-20 h-20 rounded-full bg-white blur-lg"></div>
        </div>

        <div class="relative z-10">
            <div class="mx-auto bg-white/20 w-12 h-12 rounded-xl flex items-center justify-center backdrop-blur-sm mb-4 border border-white/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">固定資產 RFID 管理</h1>
            <p class="text-indigo-100 text-xs mt-2 uppercase tracking-wider font-medium opacity-90">TE Department System</p>
        </div>
      </div>

      <div class="p-8 pt-6">
        
        <button 
            @click="handleSSOLogin" 
            class="group w-full bg-slate-900 text-white font-bold py-3.5 rounded-xl hover:bg-slate-800 transition-all shadow-lg shadow-slate-900/20 flex items-center justify-center gap-3 border border-transparent hover:scale-[1.01] active:scale-[0.99]"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
            <span>使用公司 AD 帳號登入 (SSO)</span>
        </button>

        <div class="relative flex py-6 items-center">
            <div class="flex-grow border-t border-slate-200"></div>
            <span class="flex-shrink-0 mx-4 text-slate-400 text-[10px] uppercase tracking-widest font-bold">Or Local Admin</span>
            <div class="flex-grow border-t border-slate-200"></div>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-4">
          
          <div v-if="authStore.error" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm border border-red-100 flex items-center gap-2">
             <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
             {{ authStore.error }}
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-500 uppercase ml-1">Email</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                </div>
                <input v-model="form.email" type="email" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm placeholder:text-slate-400" placeholder="admin@te.com">
            </div>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-500 uppercase ml-1">Password</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input v-model="form.password" type="password" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm placeholder:text-slate-400" placeholder="••••••••">
            </div>
          </div>

          <button type="submit" :disabled="authStore.loading" class="w-full bg-white border border-slate-200 text-slate-600 font-bold py-2.5 rounded-lg hover:bg-slate-50 hover:text-indigo-600 hover:border-indigo-200 transition-all text-sm shadow-sm mt-2">
            {{ authStore.loading ? '驗證中...' : '本地管理員登入' }}
          </button>
        </form>
      </div>
    </div>
    
    <div class="absolute bottom-6 text-slate-400 text-xs font-mono">
        © 2025 Test Engineering Dept.
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

// SSO 登入
const handleSSOLogin = () => {
    // ⬇️ 修改這裡：根據環境動態加入前綴
    const prefix = import.meta.env.MODE === 'production' ? '/rfid-inventory' : '';
    window.location.href = `${prefix}/api/auth/redirect`;
};
</script>