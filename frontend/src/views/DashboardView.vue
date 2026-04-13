<template>
  <div class="dashboard-container">
    <el-row :gutter="20">
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <template #header>
            <div class="card-header">
              <span>Total Revenue</span>
              <el-icon color="#67C23A"><Money /></el-icon>
            </div>
          </template>
          <div class="stat-value text-success">${{ stats.revenue.toFixed(2) }}</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <template #header>
            <div class="card-header">
              <span>Total Orders</span>
              <el-icon color="#409EFF"><ShoppingCart /></el-icon>
            </div>
          </template>
          <div class="stat-value text-primary">{{ stats.total_orders }}</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="stat-card">
          <template #header>
            <div class="card-header">
              <span>Active Cameras</span>
              <el-icon :color="stats.active_cameras > 0 ? '#67C23A' : '#F56C6C'"><VideoCamera /></el-icon>
            </div>
          </template>
          <div class="stat-value">{{ stats.active_cameras }}</div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { apiClient } from '@/services/axios'
import { Money, ShoppingCart, VideoCamera } from '@element-plus/icons-vue'

const stats = ref({
  revenue: 0,
  total_orders: 0,
  active_cameras: 0
})

let pollInterval: ReturnType<typeof setInterval>

const fetchStats = async () => {
  try {
    const { data } = await apiClient.get('/api/orders/stats')
    // stats response format uses data.data
    stats.value = data.data
  } catch (error) {
    console.error('Failed to fetch dashboard stats', error)
  }
}

onMounted(() => {
  fetchStats()
  // MVP: Realtime updates polling every 30 seconds
  pollInterval = setInterval(fetchStats, 30000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<style scoped>
.dashboard-container {
  padding: 10px;
}

.stat-card {
  margin-bottom: 20px;
  border-radius: 8px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 500;
}

.stat-value {
  font-size: 32px;
  font-weight: bold;
  text-align: center;
  padding: 10px 0;
}

.text-success { color: #67C23A; }
.text-primary { color: #409EFF; }
</style>
