<template>
  <div class="camera-monitor">
    <div class="header-actions">
      <h2>Màn hình Giám sát Camera</h2>
      <div class="controls">
        <el-select v-model="gridSize" placeholder="Chế độ xem" style="width: 120px; margin-right: 15px;">
          <el-option label="Lưới 1x1" :value="1" />
          <el-option label="Lưới 2x2" :value="4" />
          <el-option label="Lưới 3x3" :value="9" />
        </el-select>
        <el-button type="primary" :icon="Refresh" @click="fetchCameras">Làm mới</el-button>
      </div>
    </div>

    <!-- Grid Layout based on gridSize -->
    <div 
      class="camera-grid" 
      :style="gridStyle"
    >
      <!-- Dùng slice để lấy số lượng camera hiển thị vừa với grid, hoặc hiển thị tất cả nếu có thanh cuộn. 
           Quyết định: Hiển thị tất cả, nhưng chia CSS Grid với số cột cố định (1, 2, hoặc 3). -->
      <div 
        v-for="(camera, index) in paginatedCameras" 
        :key="camera.id" 
        class="camera-card"
        ref="cameraCards"
      >
        <div class="camera-header">
          <span class="camera-name">{{ camera.name }}</span>
          <el-tag :type="camera.status === 'online' ? 'success' : 'danger'" size="small" effect="dark">
            {{ camera.status === 'online' ? 'Trực tuyến' : 'Ngoại tuyến' }}
          </el-tag>
        </div>

        <div class="camera-stream-wrapper">
          <template v-if="camera.status === 'online'">
            <img 
              :src="getStreamUrl(camera.id)" 
              alt="Stream" 
              class="camera-stream"
              crossorigin="anonymous"
              @error="handleStreamError(camera)"
              :ref="el => setStreamRef(el, index)"
            />
          </template>
          <div v-else class="camera-offline">
            <el-icon :size="48" color="#909399"><VideoCamera /></el-icon>
            <p>Mất kết nối</p>
          </div>
          
          <div class="camera-overlay-actions">
            <el-tooltip content="Chụp ảnh nhanh" placement="top">
              <el-button circle :icon="Camera" @click="takeSnapshot(index, camera.name)" />
            </el-tooltip>
            <el-tooltip content="Toàn màn hình" placement="top">
              <el-button circle :icon="FullScreen" @click="toggleFullscreen(index)" />
            </el-tooltip>
          </div>
        </div>
      </div>
      
      <!-- Phân trang nếu số camera lớn hơn lưới -->
    </div>
    
    <div class="pagination-container" v-if="cameras.length > gridSize">
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="gridSize"
        layout="prev, pager, next"
        :total="cameras.length"
        @current-change="handlePageChange"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { Refresh, VideoCamera, Camera, FullScreen } from '@element-plus/icons-vue'
import { apiClient } from '@/services/axios'
import type { Camera as CameraType } from '@/types'
import { ElMessage } from 'element-plus'

// We extend the general type, or redefine
interface AppCamera extends CameraType {
  status: 'online' | 'offline' | 'error';
}

const cameras = ref<AppCamera[]>([])
const gridSize = ref(4) // Mặc định 2x2
const currentPage = ref(1)
const streamRefs = ref<HTMLImageElement[]>([])
const cameraCards = ref<HTMLDivElement[]>([])

const STEAM_BASE_URL = import.meta.env.VITE_STREAM_URL || 'http://localhost:8000'

// CSS Grid Style
const gridStyle = computed(() => {
  const cols = Math.sqrt(gridSize.value) // 1, 2, or 3
  return {
    display: 'grid',
    gridTemplateColumns: `repeat(${cols}, 1fr)`,
    gap: '15px'
  }
})

const paginatedCameras = computed(() => {
  const start = (currentPage.value - 1) * gridSize.value
  const end = start + gridSize.value
  return cameras.value.slice(start, end)
})

const fetchCameras = async () => {
  try {
    const { data } = await apiClient.get('/api/cameras')
    cameras.value = data.data.map((c: any) => ({
      ...c,
      status: c.is_active ? 'online' : 'offline'
    }))
    // Reset page if needed
    if (paginatedCameras.value.length === 0 && currentPage.value > 1) {
      currentPage.value = 1
    }
  } catch (error) {
    ElMessage.error('Không thể tải danh sách Camera')
  }
}

const getStreamUrl = (cameraId: string | number) => {
  return `${STEAM_BASE_URL}/stream/${cameraId}?t=${new Date().getTime()}`
}

const handleStreamError = (camera: AppCamera) => {
  camera.status = 'error'
}

const handlePageChange = (val: number) => {
  currentPage.value = val
}

const setStreamRef = (el: any, index: number) => {
  if (el) {
    streamRefs.value[index] = el
  }
}

const takeSnapshot = async (index: number, cameraName: string) => {
  const imgElement = streamRefs.value[index]
  if (!imgElement) {
    ElMessage.warning('Chưa có nguồn video nội dung để chụp')
    return
  }

  // Draw on canvas to get base64
  const canvas = document.createElement('canvas')
  canvas.width = imgElement.naturalWidth || imgElement.width
  canvas.height = imgElement.naturalHeight || imgElement.height
  const ctx = canvas.getContext('2d')
  if (ctx) {
    ctx.drawImage(imgElement, 0, 0, canvas.width, canvas.height)
    try {
      const dataUrl = canvas.toDataURL('image/jpeg', 0.9)
      // download
      const link = document.createElement('a')
      link.href = dataUrl
      link.download = `snapshot_${cameraName}_${new Date().getTime()}.jpg`
      link.click()
      ElMessage.success('Đã lưu chụp màn hình')
    } catch(e) {
      ElMessage.error('Lỗi khi chụp: Stream có thể bị chặn CORS')
    }
  }
}

const toggleFullscreen = (index: number) => {
  const container = cameraCards.value[index]
  if (!container) return

  if (!document.fullscreenElement) {
    container.requestFullscreen().catch((err) => {
      ElMessage.error(`Lỗi toàn màn hình: ${err.message}`)
    })
  } else {
    document.exitFullscreen()
  }
}

onMounted(() => {
  fetchCameras()
})
</script>

<style scoped>
.camera-monitor {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.controls {
  display: flex;
  align-items: center;
}

.camera-grid {
  flex-grow: 1;
}

.camera-card {
  background: var(--el-bg-color);
  border-radius: 8px;
  border: 1px solid var(--el-border-color-light);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
  transition: all 0.3s;
}

.camera-card:hover {
  box-shadow: 0 4px 16px 0 rgba(0,0,0,0.1);
}

.camera-header {
  padding: 10px 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--el-border-color-lighter);
  background: var(--el-fill-color-light);
}

.camera-name {
  font-weight: 600;
  font-size: 14px;
}

.camera-stream-wrapper {
  position: relative;
  aspect-ratio: 16 / 9;
  background-color: #000;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
}

.camera-stream {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.camera-offline {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  color: #909399;
  height: 100%;
}

.camera-overlay-actions {
  position: absolute;
  top: 10px;
  right: 10px;
  display: flex;
  gap: 10px;
  opacity: 0;
  transition: opacity 0.2s;
}

.camera-stream-wrapper:hover .camera-overlay-actions {
  opacity: 1;
}

/* Fullscreen adjustments */
:fullscreen .camera-stream-wrapper {
  height: 100vh;
  aspect-ratio: unset;
}
:fullscreen .camera-header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
  background: rgba(0,0,0,0.5);
  color: white;
  border: none;
}
:fullscreen .camera-overlay-actions {
  top: 60px;
  opacity: 1;
  z-index: 10;
}

.pagination-container {
  display: flex;
  justify-content: center;
  margin-top: 20px;
  padding-bottom: 20px;
}
</style>
