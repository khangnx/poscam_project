<template>
  <div class="customer-manager">
    <div class="header-section">
      <div class="header-content">
        <h1 class="page-title">Quản lý Khách hàng</h1>
        <p class="page-subtitle">Xem thông tin chi tiết, hạng thành viên và lịch sử mua hàng.</p>
      </div>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog" class="premium-btn">
        Thêm khách hàng
      </el-button>
    </div>

    <div class="filter-section premium-card-bg mb-4">
      <el-input
        v-model="search"
        placeholder="Tìm kiếm theo Tên hoặc Số điện thoại..."
        :prefix-icon="Search"
        clearable
        @input="handleSearch"
        class="search-input"
      />
    </div>

    <div class="table-container premium-card-bg">
      <el-table :data="customers" v-loading="loading" style="width: 100%">
        <el-table-column label="Khách hàng" min-width="200">
          <template #default="{ row }">
            <div class="customer-cell">
              <el-avatar :size="40" class="customer-avatar">{{ row.name.charAt(0) }}</el-avatar>
              <div class="customer-info">
                <span class="name">{{ row.name }}</span>
                <span class="phone">{{ row.phone }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Hạng" width="150">
          <template #default="{ row }">
            <el-tag :type="getRankType(row.group?.name)" effect="dark" round>
              {{ row.group?.name || 'Chưa có hạng' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="points" label="Điểm tích lũy" width="150">
          <template #default="{ row }">
            <span class="points-value">{{ row.points }} p</span>
          </template>
        </el-table-column>
        <el-table-column label="Tổng chi tiêu" width="180">
          <template #default="{ row }">
            <span class="spent-value">{{ formatCurrency(row.total_spent) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Thao tác" width="200" align="right">
          <template #default="{ row }">
            <el-button-group>
              <el-button :icon="View" @click="viewCustomer(row)" circle />
              <el-button :icon="Edit" @click="editCustomer(row)" circle />
              <el-button type="danger" :icon="Delete" @click="confirmDelete(row)" circle />
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
      
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchCustomers"
        />
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? 'Chỉnh sửa thông tin khách hàng' : 'Thêm khách hàng mới'"
      width="450px"
      class="premium-dialog"
    >
      <el-form :model="form" label-position="top">
        <el-form-item label="Họ và tên" required>
          <el-input v-model="form.name" placeholder="Nhập tên khách hàng..." />
        </el-form-item>
        <el-form-item label="Số điện thoại" required>
          <el-input v-model="form.phone" placeholder="Nhập số điện thoại..." />
        </el-form-item>
        <el-form-item label="Nhóm khách hàng (Tùy chọn)">
          <el-select v-model="form.group_id" placeholder="Chọn nhóm..." style="width: 100%" clearable>
            <el-option
              v-for="group in groups"
              :key="group.id"
              :label="group.name"
              :value="group.id"
            />
          </el-select>
        </el-form-item>
        <div v-if="isEdit" class="loyalty-edit-fields">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="Điểm tích lũy">
                <el-input-number 
                  v-model="form.points" 
                  :min="0" 
                  controls-position="right" 
                  style="width: 100%" 
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Tổng chi tiêu (VNĐ)">
                <el-input-number 
                  v-model="form.total_spent" 
                  :min="0" 
                  :step="1000"
                  controls-position="right" 
                  style="width: 100%" 
                />
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Hủy</el-button>
          <el-button type="primary" @click="saveCustomer" :loading="submitting">
            {{ isEdit ? 'Cập nhật' : 'Lưu lại' }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- Drawer for Detail View -->
    <el-drawer
      v-model="drawerVisible"
      title="Chi tiết khách hàng"
      size="600px"
      custom-class="premium-drawer"
    >
      <div v-if="selectedCustomer" class="customer-detail">
        <div class="detail-header">
          <el-avatar :size="80" class="large-avatar">{{ selectedCustomer.name.charAt(0) }}</el-avatar>
          <div class="header-text">
            <h2>{{ selectedCustomer.name }}</h2>
            <p>{{ selectedCustomer.phone }}</p>
            <el-tag :type="getRankType(selectedCustomer.group?.name)" effect="dark">
              {{ selectedCustomer.group?.name || 'Thành viên' }}
            </el-tag>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <span class="label">Điểm hiện tại</span>
            <span class="value">{{ selectedCustomer.points }}</span>
          </div>
          <div class="stat-card">
            <span class="label">Tổng chi tiêu</span>
            <span class="value">{{ formatCurrency(selectedCustomer.total_spent) }}</span>
          </div>
        </div>

        <el-tabs v-model="activeTab" class="detail-tabs">
          <el-tab-pane label="Lịch sử tích điểm" name="loyalty">
            <div v-if="selectedCustomer.loyalty_logs && selectedCustomer.loyalty_logs.length > 0">
              <el-table :data="selectedCustomer.loyalty_logs" size="small" style="width: 100%" class="history-table">
                <el-table-column label="Thời gian" width="160">
                  <template #default="{ row }">
                    <span class="history-time">{{ formatDate(row.created_at) }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="Nội dung" prop="description" min-width="150" />
                <el-table-column label="Biến động" width="100" align="right">
                  <template #default="{ row }">
                    <span class="log-points" :class="row.type">
                      {{ row.type === 'plus' ? '+' : '-' }}{{ row.points_changed }}
                    </span>
                  </template>
                </el-table-column>
                <el-table-column label="Số dư" width="100" align="right">
                  <template #default="{ row }">
                    <span class="balance-cell">{{ row.balance ?? '-' }} p</span>
                  </template>
                </el-table-column>
              </el-table>
            </div>
            <el-empty v-else description="Chưa có dữ liệu lịch sử tích điểm" :image-size="100" />
          </el-tab-pane>
          <el-tab-pane label="Đơn hàng gần đây" name="orders">
            <div v-for="order in selectedCustomer.orders" :key="order.id" class="order-item-mini">
              <div class="order-header">
                <span class="order-id">#{{ order.id }}</span>
                <span class="order-date">{{ formatDate(order.created_at) }}</span>
              </div>
              <div class="order-body">
                <div v-for="item in order.items" :key="item.id" class="product-line">
                  {{ item.product.name }} x {{ item.quantity }}
                </div>
              </div>
              <div class="order-footer">
                <span>Tổng cộng:</span>
                <strong>{{ formatCurrency(order.total_amount) }}</strong>
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus, Search, Edit, Delete, View } from '@element-plus/icons-vue'
import { apiClient } from '@/services/axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import debounce from 'lodash/debounce'

const customers = ref<any[]>([])
const groups = ref<any[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const drawerVisible = ref(false)
const isEdit = ref(false)
const submitting = ref(false)
const search = ref('')
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(15)
const selectedCustomer = ref<any>(null)
const activeTab = ref('loyalty')

const form = reactive({
  id: null,
  name: '',
  phone: '',
  group_id: null,
  points: 0,
  total_spent: 0
})

const fetchCustomers = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get('/api/customers', {
      params: {
        page: currentPage.value,
        search: search.value,
        per_page: pageSize.value
      }
    })
    customers.value = data.data.data
    total.value = data.data.total
  } catch (error) {
    ElMessage.error('Không thể tải danh sách khách hàng')
  } finally {
    loading.value = false
  }
}

const fetchGroups = async () => {
  try {
    const { data } = await apiClient.get('/api/customer-groups')
    groups.value = data.data
  } catch (error) {}
}

const handleSearch = debounce(() => {
  currentPage.value = 1
  fetchCustomers()
}, 300)

const openCreateDialog = () => {
  isEdit.value = false
  Object.assign(form, { 
    id: null, 
    name: '', 
    phone: '', 
    group_id: null,
    points: 0,
    total_spent: 0
  })
  dialogVisible.value = true
}

const editCustomer = (row: any) => {
  isEdit.value = true
  // individual assignments to ensure reactivity and explicit casting
  form.id = row.id
  form.name = row.name
  form.phone = row.phone
  form.group_id = row.group_id
  form.points = Number(row.points) || 0
  form.total_spent = Number(row.total_spent) || 0
  dialogVisible.value = true
}

const saveCustomer = async () => {
  submitting.value = true
  try {
    if (isEdit.value) {
      await apiClient.put(`/api/customers/${form.id}`, form)
      ElMessage.success('Cập nhật thành công')
    } else {
      await apiClient.post('/api/customers', form)
      ElMessage.success('Đã thêm khách hàng mới')
    }
    dialogVisible.value = false
    fetchCustomers()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || 'Có lỗi xảy ra')
  } finally {
    submitting.value = false
  }
}

const viewCustomer = async (row: any) => {
  try {
    const { data } = await apiClient.get(`/api/customers/${row.id}`)
    selectedCustomer.value = data.data
    drawerVisible.value = true
  } catch (error) {
    ElMessage.error('Không thể tải chi tiết khách hàng')
  }
}

const confirmDelete = (row: any) => {
  ElMessageBox.confirm(`Bạn có chắc muốn xóa khách hàng "${row.name}"? Dữ liệu tích điểm sẽ bị mất hoàn toàn.`, 'Cảnh báo', {
    type: 'warning',
    confirmButtonText: 'Xóa ngay',
    cancelButtonText: 'Hủy'
  }).then(async () => {
    try {
      await apiClient.delete(`/api/customers/${row.id}`)
      ElMessage.success('Đã xóa khách hàng')
      fetchCustomers()
    } catch (error) {
      ElMessage.error('Lỗi khi xóa')
    }
  })
}

const getRankType = (name: string) => {
  if (!name) return 'info'
  const n = name.toLowerCase()
  if (n.includes('vàng') || n.includes('gold')) return 'warning'
  if (n.includes('kim cương') || n.includes('diamond')) return 'danger'
  if (n.includes('bạc') || n.includes('silver')) return 'primary'
  return 'success'
}

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleString('vi-VN')
}

onMounted(() => {
  fetchCustomers()
  fetchGroups()
})
</script>

<style scoped>
.customer-manager {
  max-width: 1200px;
  margin: 0 auto;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  color: #1a1a1a;
  margin: 0 0 4px;
}

.page-subtitle {
  color: #666;
  margin: 0;
}

.premium-card-bg {
  background: white;
  padding: 24px;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.mb-4 { margin-bottom: 24px; }

.search-input :deep(.el-input__wrapper) {
  border-radius: 12px;
  height: 48px;
  background: #f8faff;
}

.customer-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.customer-avatar {
  background: #409eff;
  color: white;
  font-weight: bold;
}

.customer-info {
  display: flex;
  flex-direction: column;
}

.name {
  font-weight: 700;
  color: #2c3e51;
}

.phone {
  font-size: 13px;
  color: #94a3b8;
}

.points-value {
  color: #27ae60;
  font-weight: 700;
}

.spent-value {
  font-weight: 600;
  color: #2c3e50;
}

.pagination-container {
  margin-top: 24px;
  display: flex;
  justify-content: center;
}

.premium-btn {
  height: 44px;
  padding: 0 24px;
  border-radius: 10px;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.3);
}

/* Detail view styles */
.detail-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 30px;
}

.header-text h2 { margin: 0 0 4px; font-weight: 800; }
.header-text p { margin: 0 0 8px; color: #64748b; }

.large-avatar {
  background: #409eff;
  font-size: 32px;
  font-weight: bold;
}

.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 30px;
}

.stat-card {
  background: #f8faff;
  padding: 16px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
}

.stat-card .label {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 4px;
}

.stat-card .value {
  font-size: 20px;
  font-weight: 800;
  color: #2563eb;
}

.log-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.log-desc { font-size: 14px; color: #334155; }
.log-points { font-weight: 700; }
.log-points.plus { color: #22c55e; }
.log-points.minus { color: #ef4444; }

.history-time {
  font-size: 13px;
  color: #64748b;
}

.balance-cell {
  font-weight: 600;
  color: #475569;
}

.history-table :deep(.el-table__header) th {
  background-color: #f8fafc;
  color: #475569;
  font-weight: 600;
}

.order-item-mini {
  background: #f1f5f9;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 12px;
}

.order-header {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 8px;
}

.order-id { font-weight: bold; color: #1e293b; }
.order-date { color: #94a3b8; }

.product-line { font-size: 13px; color: #475569; margin-bottom: 2px; }

.order-footer {
  margin-top: 8px;
  border-top: 1px dashed #cbd5e1;
  padding-top: 8px;
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}
</style>
