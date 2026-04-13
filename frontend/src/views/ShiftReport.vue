<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>Lịch sử Ca làm việc</span>
        <el-button type="primary" size="small" @click="fetchHistory" :icon="'Refresh'">Làm mới</el-button>
      </div>
    </template>

    <el-table :data="shifts" v-loading="loading" border style="width: 100%">
      <el-table-column label="Nhân viên" min-width="150">
        <template #default="{ row }">
          <strong>{{ row.user?.name || 'Unknown' }}</strong>
        </template>
      </el-table-column>
      <el-table-column label="Giờ mở/kết ca" min-width="200">
        <template #default="{ row }">
          <div class="text-sm">
            Mở: {{ new Date(row.start_time).toLocaleString('vi-VN') }}
          </div>
          <div class="text-sm" v-if="row.end_time">
            Đóng: <span class="text-success">{{ new Date(row.end_time).toLocaleString('vi-VN') }}</span>
          </div>
          <el-tag v-else type="warning" size="small">Đang làm việc</el-tag>
        </template>
      </el-table-column>
      
      <el-table-column label="Tóm tắt doanh thu" min-width="280">
        <template #default="{ row }">
            <div class="text-xs text-muted">Đầu ca: {{ formatCurrency(row.start_cash) }}</div>
            <div v-if="row.end_time">
              <div>💵 Tiền mặt: {{ formatCurrency(row.total_cash_sales) }}</div>
              <div>💳 Phi tiền mặt: {{ formatCurrency(row.total_non_cash_sales) }}</div>
              <div class="text-primary font-bold">Tổng doanh thu: {{ formatCurrency(row.total_revenue) }}</div>
              <el-divider style="margin: 4px 0" />
              <div class="text-success">Thực tế bàn giao: {{ formatCurrency(row.end_cash) }}</div>
            </div>
        </template>
      </el-table-column>

      <el-table-column label="Chênh lệch" width="150" align="right">
        <template #default="{ row }">
          <div v-if="row.end_time">
              <span :class="{'text-danger': row.balance_gap < 0, 'text-success': row.balance_gap > 0}">
                {{ row.balance_gap > 0 ? '+' : '' }}{{ formatCurrency(row.balance_gap) }}
              </span>
          </div>
        </template>
      </el-table-column>

      <el-table-column prop="reason" label="Lý do chênh lệch" min-width="200">
        <template #default="{ row }">
          <span v-if="row.reason" class="text-danger italic">{{ row.reason }}</span>
          <span v-else class="text-muted">-</span>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient } from '@/services/axios'
import { ElMessage } from 'element-plus'

const shifts = ref<any[]>([])
const loading = ref(false)

const formatCurrency = (val: number | string) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val) || 0)
}

const fetchHistory = async () => {
    loading.value = true
    try {
        const { data } = await apiClient.get('/api/shifts')
        shifts.value = data.data
    } catch(err) {
        ElMessage.error('Lỗi khi tải lịch sử ca')
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchHistory()
})
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.text-sm { font-size: 0.85rem; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; font-weight: bold; }
.text-primary { color: #409eff; }
.text-muted { color: #909399; }
.font-bold { font-weight: bold; }
.italic { font-style: italic; }
</style>
