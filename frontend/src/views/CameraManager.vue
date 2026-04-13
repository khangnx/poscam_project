<template>
  <div class="camera-manager">
    <div class="page-header">
      <h2>Quản lý Thiết bị Camera</h2>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog">Thêm Mới</el-button>
    </div>

    <el-card>
      <el-table :data="cameras" v-loading="loading" border style="width: 100%">
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="name" label="Tên Camera" min-width="200" />
        <el-table-column prop="type" label="Phân Loại" width="150">
          <template #default="scope">
            <el-tag :type="scope.row.type === 'ip_camera' ? 'info' : 'warning'" effect="light">
              {{ scope.row.type === 'ip_camera' ? 'IP Camera' : 'NVR-Channel' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="rtsp_url" label="RTSP URL" min-width="250">
          <template #default="scope">
            <span class="rtsp-text">{{ maskUrl(scope.row.rtsp_url) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Trạng thái" width="150" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.is_active ? 'success' : 'danger'" effect="dark">
              {{ scope.row.is_active ? 'Trực tuyến' : 'Ngoại tuyến' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Thao tác" width="200" align="center" fixed="right">
          <template #default="scope">
            <el-button size="small" type="primary" :icon="Edit" @click="openEditDialog(scope.row)">
              Sửa
            </el-button>
            <el-popconfirm
              title="Bạn có chắc muốn xóa camera này?"
              confirm-button-text="Xóa"
              cancel-button-text="Hủy"
              @confirm="handleDelete(scope.row.id)"
            >
              <template #reference>
                <el-button size="small" type="danger" :icon="Delete">Xóa</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <CameraForm 
      v-model="dialogVisible" 
      :cameraData="currentCamera"
      @saved="fetchCameras" 
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Plus, Edit, Delete } from '@element-plus/icons-vue'
import { apiClient } from '@/services/axios'
import { ElMessage } from 'element-plus'
import CameraForm from './CameraForm.vue'

const cameras = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const currentCamera = ref(null)

const fetchCameras = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get('/api/cameras')
    if (data.success) {
      cameras.value = data.data
    }
  } catch (error) {
    ElMessage.error('Lỗi khi tải danh sách camera')
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  currentCamera.value = null
  dialogVisible.value = true
}

const openEditDialog = (row: any) => {
  // Pass a copy so that canceling the form doesn't mutate table data immediately
  currentCamera.value = { ...row }
  dialogVisible.value = true
}

const handleDelete = async (id: number) => {
  try {
    const { data } = await apiClient.delete(`/api/cameras/${id}`)
    if (data.success) {
      ElMessage.success('Xóa camera thành công')
      fetchCameras()
    }
  } catch (error) {
    ElMessage.error('Lỗi khi xóa camera')
  }
}

const maskUrl = (url: string) => {
  if (!url) return ''
  try {
    // Hide passwords in RTSP url if present e.g rtsp://admin:1234@ip
    const regex = /(rtsp:\/\/[^:]+:)([^@]+)(@.*)/;
    return url.replace(regex, '$1***$3');
  } catch {
    return url
  }
}

onMounted(() => {
  fetchCameras()
})
</script>

<style scoped>
.camera-manager {
  display: flex;
  flex-direction: column;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.rtsp-text {
  font-family: monospace;
  color: #606266;
  font-size: 13px;
  word-break: break-all;
}
</style>
