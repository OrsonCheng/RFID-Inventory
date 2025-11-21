<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-white shadow mb-4">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
          <div class="flex space-x-8 items-center">
            <span class="font-bold text-xl">RFID 資產系統</span>
            <router-link to="/assets" class="text-gray-700 hover:text-blue-600">資產管理</router-link>
            <router-link to="/inventory" class="text-gray-700 hover:text-blue-600">盤點工單</router-link>
          </div>
        </div>
      </div>
    </nav>
    <router-view></router-view>
  </div>
</template>