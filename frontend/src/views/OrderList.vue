<template>
  <div class="order-list-container">
    <div class="header-actions">
      <h2>Lịch sử Đơn hàng</h2>
    </div>

    <!-- TODO: Filters like Date Range, Status can be added here -->

    <el-table :data="orders" v-loading="loading" style="width: 100%" border>
      <el-table-column prop="id" label="Mã Đơn" width="100" />
      <el-table-column prop="customer_name" label="Khách hàng" min-width="150">
        <template #default="{ row }">
          {{ row.customer_name || 'Khách lẻ' }}
        </template>
      </el-table-column>
      <el-table-column prop="total_amount" label="Tổng tiền" width="150">
        <template #default="{ row }">
          <span class="text-danger font-bold">{{ formatCurrency(row.total_amount) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="payment_method" label="Thanh toán" width="120">
        <template #default="{ row }">
          {{ getPaymentMethodLabel(row.payment_method) }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="Trạng thái" width="150">
        <template #default="{ row }">
          <el-select
            v-model="row.status"
            size="small"
            @change="updateStatus(row, row.status)"
            :class="`status-${row.status}`"
          >
            <el-option label="Chờ xử lý" value="pending" />
            <el-option label="Hoàn thành" value="completed" />
            <el-option label="Đã hủy" value="cancelled" />
          </el-select>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="Ngày tạo" width="180">
        <template #default="{ row }">
          {{ new Date(row.created_at).toLocaleString('vi-VN') }}
        </template>
      </el-table-column>
      <el-table-column label="Hành động" width="150" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="primary" :icon="View" circle @click="viewDetails(row)" />
          <el-button v-if="authStore.hasRole('admin')" size="small" type="danger" :icon="Delete" circle @click="deleteOrder(row.id)" />
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-container">
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="15"
        layout="prev, pager, next, total"
        :total="totalItems"
        @current-change="handlePageChange"
      />
    </div>

    <!-- Order Details Dialog -->
    <el-dialog title="Chi tiết Đơn hàng" v-model="detailsVisible" width="600px">
      <div v-if="selectedOrder" class="order-details">
        <div class="info-row">
          <strong>Mã đơn:</strong> #{{ selectedOrder.id }}
        </div>
        <div class="info-row">
          <strong>Khách hàng:</strong> {{ selectedOrder.customer_name || 'Khách lẻ' }}
        </div>
        <div class="info-row">
          <strong>Ngày tạo:</strong> {{ new Date(selectedOrder.created_at).toLocaleString('vi-VN') }}
        </div>
        
        <el-divider>Sản phẩm</el-divider>
        <el-table :data="selectedOrder.items" border size="small">
          <el-table-column prop="product.name" label="Tên SP" min-width="150" />
          <el-table-column prop="price_at_purchase" label="Đơn giá" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.price_at_purchase) }}
            </template>
          </el-table-column>
          <el-table-column prop="quantity" label="SL" width="80" />
          <el-table-column label="Thành tiền" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.price_at_purchase * row.quantity) }}
            </template>
          </el-table-column>
        </el-table>
        
        <div class="total-row mt-4 flex justify-between font-bold text-lg">
          <span>Tổng cộng:</span>
          <span class="text-danger">{{ formatCurrency(selectedOrder.total_amount) }}</span>
        </div>
      </div>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="detailsVisible = false">Đóng</el-button>
          <el-button type="success" @click="printReceipt(selectedOrder)">In Hóa Đơn</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { View, Delete } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { apiClient } from '@/services/axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const orders = ref<any[]>([])
const loading = ref(false)
const currentPage = ref(1)
const totalItems = ref(0)

const detailsVisible = ref(false)
const selectedOrder = ref<any>(null)

const fetchOrders = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get('/api/orders', {
      params: { page: currentPage.value }
    })
    orders.value = data.data.data
    totalItems.value = data.data.total
  } catch (error) {
    ElMessage.error('Không thể tải danh sách đơn hàng')
  } finally {
    loading.value = false
  }
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  fetchOrders()
}

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const getPaymentMethodLabel = (method: string) => {
  const map: Record<string, string> = {
    'cash': 'Tiền mặt',
    'transfer': 'Chuyển khoản',
    'card': 'Thẻ'
  }
  return map[method] || method
}

const updateStatus = async (order: any, newStatus: string) => {
  try {
    await apiClient.put(`/api/orders/${order.id}`, { status: newStatus })
    ElMessage.success('Cập nhật trạng thái thành công')
  } catch (error) {
    ElMessage.error('Cập nhật trạng thái thất bại')
    fetchOrders() // Revert
  }
}

const viewDetails = (order: any) => {
  selectedOrder.value = order
  detailsVisible.value = true
}

const deleteOrder = async (id: number) => {
  try {
    await ElMessageBox.confirm('Đơn hàng sẽ bị ẩn đi. Bạn có chắc chắn?', 'Xác nhận xóa soft-delete', {
      confirmButtonText: 'Xóa',
      cancelButtonText: 'Hủy',
      type: 'warning',
    })
    await apiClient.delete(`/api/orders/${id}`)
    ElMessage.success('Xóa đơn hàng thành công')
    fetchOrders()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('Xóa đơn hàng thất bại')
    }
  }
}

const printReceipt = async (order: any) => {
  try {
    await apiClient.post(`/api/orders/print/${order.id}`)
    ElMessage.success('Đã gửi lệnh in')
  } catch (error) {
    ElMessage.error('Lỗi khi gửi lệnh in')
  }
}

onMounted(() => {
  fetchOrders()
})
</script>

<style scoped>
.order-list-container {
  padding: 20px;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
}
.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.text-danger {
  color: #F56C6C;
}
.font-bold {
  font-weight: bold;
}
.info-row {
  margin-bottom: 10px;
}
.mt-4 { margin-top: 16px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.text-lg { font-size: 1.125rem; }

/* Status coloring for select inputs */
:deep(.status-pending .el-input__inner) { color: #E6A23C; }
:deep(.status-completed .el-input__inner) { color: #67C23A; }
:deep(.status-cancelled .el-input__inner) { color: #F56C6C; }
</style>
