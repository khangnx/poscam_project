<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>Quản lý Ca làm việc hiện tại</span>
      </div>
    </template>
    
    <div v-if="loading" class="text-center my-4">
        <el-spin></el-spin> Loading...
    </div>

    <div v-else-assertive>
      <div v-if="activeShift">
          <el-alert title="Ca hiện tại đang mở" type="success" :closable="false" show-icon class="mb-4" />
          <el-descriptions border :column="2">
              <el-descriptions-item label="Nhân sự">{{ authStore.user?.name }} ({{ authStore.user?.email }})</el-descriptions-item>
              <el-descriptions-item label="Giờ mở ca">{{ new Date(activeShift.start_time).toLocaleString('vi-VN') }}</el-descriptions-item>
              <el-descriptions-item label="Tiền mặt đầu ca">{{ formatCurrency(activeShift.start_cash) }}</el-descriptions-item>
          </el-descriptions>
          <div class="mt-4 text-center">
              <el-button type="danger" @click="ElMessage.info('Vui lòng sử dụng tính năng Chốt ca ở menu góc trên cùng bên phải.')">Chốt ca ngay</el-button>
          </div>
      </div>
      <div v-else>
          <el-empty description="Hiện tại bạn không có ca làm việc nào đang mở.">
              <el-button type="primary" @click="ElMessage.info('Vui lòng sử dụng tính năng Bắt đầu ca ở menu góc trên cùng bên phải.')">Bắt đầu ca mới</el-button>
          </el-empty>
      </div>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient } from '@/services/axios'
import { useAuthStore } from '@/stores/auth'
import { ElMessage } from 'element-plus'

const activeShift = ref<any>(null)
const loading = ref(false)
const authStore = useAuthStore()

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const fetchCurrentShift = async () => {
    loading.value = true
    try {
        const { data } = await apiClient.get('/api/shifts/current')
        activeShift.value = data.data
    } catch (err) {
        console.error('Lỗi lấy ca hiện tại', err)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchCurrentShift()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-center { text-align: center; }
</style>
