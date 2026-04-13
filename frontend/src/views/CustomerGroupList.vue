<template>
  <div class="customer-groups-container">
    <div class="header-section">
      <div class="header-content">
        <h1 class="page-title">Nhóm khách hàng</h1>
        <p class="page-subtitle">Quản lý các hạng thành viên và cấu hình ưu đãi.</p>
      </div>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog" class="premium-btn">
        Thêm nhóm mới
      </el-button>
    </div>

    <div class="table-container premium-card-bg">
      <el-table :data="groups" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="Tên nhóm" min-width="150">
          <template #default="{ row }">
            <span class="group-name">{{ row.name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="min_points" label="Mốc điểm" width="150">
          <template #default="{ row }">
            <el-tag type="info" effect="plain" round>{{ row.min_points }} điểm</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="discount_percent" label="Giảm giá (%)" width="150">
          <template #default="{ row }">
            <span class="discount-value">{{ row.discount_percent }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="earning_rate" label="Tỷ lệ tích điểm" width="150">
          <template #default="{ row }">
            <span class="rate-value">{{ (row.earning_rate * 100).toFixed(1) }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="customers_count" label="Số khách hàng" width="150">
          <template #default="{ row }">
            <span>{{ row.customers_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Thao tác" width="200" align="right">
          <template #default="{ row }">
            <el-button-group>
              <el-button :icon="Edit" @click="editGroup(row)" circle />
              <el-button type="danger" :icon="Delete" @click="confirmDelete(row)" circle />
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- Create/Edit Dialog -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? 'Chỉnh sửa nhóm' : 'Thêm nhóm mới'"
      width="500px"
      class="premium-dialog"
    >
      <el-form :model="form" label-position="top">
        <el-form-item label="Tên nhóm" required>
          <el-input v-model="form.name" placeholder="Ví dụ: Bạc, Vàng, Kim cương..." />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Mốc điểm đạt" required>
              <el-input-number v-model="form.min_points" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Giảm giá (%)" required>
              <el-input-number v-model="form.discount_percent" :min="0" :max="100" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Tỷ lệ tích điểm" required>
              <el-input-number v-model="form.earning_rate" :min="0" :max="1" :step="0.01" style="width: 100%" />
              <div class="helper-text">0.01 = 1% số tiền chi tiêu</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Mốc đổi điểm tối thiểu">
              <el-input-number v-model="form.min_points_to_redeem" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Hủy</el-button>
          <el-button type="primary" @click="saveGroup" :loading="submitting">
            {{ isEdit ? 'Cập nhật' : 'Lưu lại' }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- Delete Migration Dialog -->
    <el-dialog
      v-model="deleteVisible"
      title="Yêu cầu chuyển đổi nhóm"
      width="450px"
      class="premium-dialog"
    >
      <p style="margin-bottom: 20px; color: #606266;">
        Nhóm <strong>{{ groupToDelete?.name }}</strong> đang có <strong>{{ groupToDelete?.customers_count }}</strong> khách hàng.
        Vui lòng chọn nhóm mới để chuyển các khách hàng này sang trước khi xóa.
      </p>
      <el-form label-position="top">
        <el-form-item label="Nhóm đích" required>
          <el-select v-model="migrateToId" placeholder="Chọn nhóm mới..." style="width: 100%">
            <el-option
              v-for="group in otherGroups"
              :key="group.id"
              :label="group.name"
              :value="group.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="deleteVisible = false">Hủy</el-button>
          <el-button type="danger" @click="handleDelete" :loading="submitting">
            Xác nhận xóa & chuyển đổi
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { Plus, Edit, Delete } from '@element-plus/icons-vue'
import { apiClient } from '@/services/axios'
import { ElMessage, ElMessageBox } from 'element-plus'

const groups = ref<any[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const submitting = ref(false)

const form = reactive({
  id: null,
  name: '',
  min_points: 0,
  discount_percent: 0,
  earning_rate: 0.01,
  min_points_to_redeem: 0
})

const fetchGroups = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get('/api/customer-groups')
    groups.value = data.data
  } catch (error) {
    ElMessage.error('Không thể tải nhóm khách hàng')
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  isEdit.value = false
  Object.assign(form, {
    id: null,
    name: '',
    min_points: 0,
    discount_percent: 0,
    earning_rate: 0.01,
    min_points_to_redeem: 0
  })
  dialogVisible.value = true
}

const editGroup = (row: any) => {
  isEdit.value = true
  Object.assign(form, {
    id: row.id,
    name: row.name,
    min_points: row.min_points,
    discount_percent: row.discount_percent,
    earning_rate: row.earning_rate,
    min_points_to_redeem: row.min_points_to_redeem
  })
  dialogVisible.value = true
}

const saveGroup = async () => {
  submitting.value = true
  try {
    if (isEdit.value) {
      await apiClient.put(`/api/customer-groups/${form.id}`, form)
      ElMessage.success('Cập nhật thành công')
    } else {
      await apiClient.post('/api/customer-groups', form)
      ElMessage.success('Đã thêm nhóm mới')
    }
    dialogVisible.value = false
    fetchGroups()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || 'Có lỗi xảy ra')
  } finally {
    submitting.value = false
  }
}

// Delete logic
const deleteVisible = ref(false)
const groupToDelete = ref<any>(null)
const migrateToId = ref<number | null>(null)

const otherGroups = computed(() => {
  return groups.value.filter(g => g.id !== groupToDelete.value?.id)
})

const confirmDelete = (row: any) => {
  if (row.customers_count > 0) {
    if (groups.value.length <= 1) {
      ElMessage.warning('Không thể xóa nhóm cuối cùng khi vẫn còn khách hàng. Vui lòng tạo nhóm mới trước.')
      return
    }
    groupToDelete.value = row
    migrateToId.value = null
    deleteVisible.value = true
  } else {
    ElMessageBox.confirm(`Bạn có chắc muốn xóa nhóm "${row.name}"?`, 'Cảnh báo', {
      type: 'warning',
      confirmButtonText: 'Xóa',
      cancelButtonText: 'Hủy'
    }).then(() => {
      handleDeleteSimple(row.id)
    })
  }
}

const handleDeleteSimple = async (id: number) => {
  try {
    await apiClient.delete(`/api/customer-groups/${id}`)
    ElMessage.success('Đã xóa nhóm')
    fetchGroups()
  } catch (error) {
    ElMessage.error('Lỗi khi xóa')
  }
}

const handleDelete = async () => {
  if (!migrateToId.value) {
    ElMessage.warning('Vui lòng chọn nhóm đích')
    return
  }
  submitting.value = true
  try {
    await apiClient.delete(`/api/customer-groups/${groupToDelete.value.id}`, {
      data: { migrate_to_id: migrateToId.value }
    })
    ElMessage.success('Đã chuyển khách hàng và xóa nhóm thành công')
    deleteVisible.value = false
    fetchGroups()
  } catch (error) {
    ElMessage.error('Lỗi khi chuyển đổi và xóa')
  } finally {
    submitting.value = false
  }
}

onMounted(fetchGroups)
</script>

<style scoped>
.customer-groups-container {
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

.group-name {
  font-weight: 700;
  color: #2c3e50;
}

.discount-value {
  color: #e67e22;
  font-weight: 700;
}

.rate-value {
  color: #27ae60;
  font-weight: 700;
}

.helper-text {
  font-size: 11px;
  color: #999;
  margin-top: 4px;
}

.premium-btn {
  height: 44px;
  padding: 0 24px;
  border-radius: 10px;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.3);
}
</style>
