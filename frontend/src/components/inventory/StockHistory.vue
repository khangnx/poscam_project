<template>
  <div class="stock-history">
    <el-card shadow="never" class="table-card">
      <template #header>
        <div class="card-header">
          <span class="text-lg font-bold">Lịch sử Kho</span>
          <div class="header-actions">
            <el-select v-model="filterType" placeholder="Loại giao dịch" clearable @change="fetchHistory">
              <el-option label="Nhập kho" value="import" />
              <el-option label="Bán hàng" value="sale" />
              <el-option label="Xuất kho" value="export" />
              <el-option label="Hoàn hàng" value="return" />
            </el-select>
          </div>
        </div>
      </template>

      <el-table :data="historyData" v-loading="loading" stripe style="width: 100%">
        <el-table-column label="Thời gian" width="180">
          <template #default="scope">
            {{ formatDateTime(scope.row.created_at) }}
          </template>
        </el-table-column>
        
        <el-table-column label="Nguyên liệu" min-width="200">
          <template #default="scope">
            <div class="product-info">
              <div class="font-medium text-blue-600">{{ scope.row.material?.name }}</div>
              <div class="text-xs text-secondary">SKU: {{ scope.row.material?.sku }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Loại" width="120">
          <template #default="scope">
            <el-tag :type="getTypeTag(scope.row.type)">
              {{ getTypeText(scope.row.type) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="Số lượng" width="100" align="center">
          <template #default="scope">
            <span :class="scope.row.quantity > 0 ? 'text-success' : 'text-danger'">
              {{ scope.row.quantity > 0 ? '+' : '' }}{{ scope.row.quantity }}
            </span>
          </template>
        </el-table-column>

        <el-table-column label="Tồn cũ" width="100" align="center" prop="old_stock" />
        <el-table-column label="Tồn mới" width="100" align="center" prop="new_stock" />

        <el-table-column label="Ghi chú / Đơn hàng" min-width="200">
          <template #default="scope">
            <div class="note-cell">
              <el-tag
                v-if="scope.row.order_id"
                type="info"
                size="small"
                class="order-badge"
                :title="'Xem đơn hàng #' + scope.row.order_id"
              >
                🛒 #{{ scope.row.order_id }}
              </el-tag>
              <span class="note-text">{{ scope.row.note }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Người thực hiện" width="150" prop="user.name" />
      </el-table>

      <div class="pagination-container mt-4">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchHistory"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiClient } from '@/services/axios';
import { format } from 'date-fns';

const loading = ref(false);
const historyData = ref([]);
const filterType = ref('');
const currentPage = ref(1);
const pageSize = ref(20);
const total = ref(0);

const fetchHistory = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      type: filterType.value,
    };
    const response = await apiClient.get('/api/inventory/history', { params });
    if (response.data.success) {
      historyData.value = response.data.data.data;
      total.value = response.data.data.total;
    }
  } catch (error) {
    console.error('Error fetching inventory history:', error);
  } finally {
    loading.value = false;
  }
};

const formatDateTime = (dateString: string) => {
  if (!dateString) return '-';
  return format(new Date(dateString), 'dd/MM/yyyy HH:mm');
};

const getTypeTag = (type: string) => {
  switch (type) {
    case 'import': return 'success';
    case 'sale':   return 'danger';
    case 'export': return 'danger';
    case 'return': return 'warning';
    default: return 'info';
  }
};

const getTypeText = (type: string) => {
  switch (type) {
    case 'import': return 'Nhập kho';
    case 'sale':   return 'Bán hàng';
    case 'export': return 'Xuất kho';
    case 'return': return 'Hoàn hàng';
    default: return type;
  }
};

onMounted(() => {
  fetchHistory();
});

defineExpose({
  fetchHistory
});
</script>

<style scoped>
.stock-history {
  padding: 0;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.text-success { color: #67C23A; font-weight: bold; }
.text-danger  { color: #F56C6C; font-weight: bold; }
.text-secondary { color: #909399; }
.note-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.order-badge {
  font-weight: 600;
  cursor: default;
  width: fit-content;
}
.note-text {
  color: #606266;
  font-size: 12px;
}
</style>
