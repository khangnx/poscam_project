<template>
  <el-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :title="isEdit ? 'Sửa Camera' : 'Thêm Camera Mới'"
    width="500px"
    @closed="handleClosed"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="120px"
      label-position="top"
    >
      <el-form-item label="Tên Camera" prop="name">
        <el-input v-model="form.name" placeholder="VD: Camera Quầy Thu Ngân" />
      </el-form-item>

      <el-form-item label="Phân Loại" prop="type">
        <el-select v-model="form.type" placeholder="Chọn loại camera" style="width: 100%">
          <el-option label="IP Camera" value="ip_camera" />
          <el-option label="NVR / DVR Channel" value="nvr" />
        </el-select>
      </el-form-item>

      <el-form-item prop="rtsp_url">
        <template #label>
          RTSP URL
          <el-tooltip
            content="Định dạng: rtsp://[user]:[pass]@[ip]:554/stream. (Hikvision, Dahua, Ezviz...)"
            placement="top"
          >
            <el-icon style="margin-left: 5px; cursor: pointer"><InfoFilled /></el-icon>
          </el-tooltip>
        </template>
        
        <div class="url-input-container">
          <el-input 
            v-model="form.rtsp_url" 
            placeholder="rtsp://admin:123456@192.168.1.10:554/Streaming/Channels/101" 
          />
          <el-button 
            type="info" 
            plain 
            class="check-btn" 
            :loading="isChecking"
            @click="checkConnection"
          >
            Kiểm tra
          </el-button>
        </div>
      </el-form-item>
      
      <el-form-item label="Ghi chú vị trí" prop="location_note">
        <el-input v-model="form.location_note" type="textarea" :rows="2" placeholder="Tùy chọn..." />
      </el-form-item>

      <el-form-item label="Trạng thái" prop="is_active">
        <el-switch v-model="form.is_active" active-text="Hoạt động" inactive-text="Tạm ngưng" />
      </el-form-item>
    </el-form>

    <template #footer>
      <span class="dialog-footer">
        <el-button @click="$emit('update:modelValue', false)">Hủy</el-button>
        <el-button type="primary" :loading="isSaving" @click="submitForm">
          Lưu lại
        </el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import { InfoFilled } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { apiClient } from '@/services/axios'
import axios from 'axios'

const FASTAPI_URL = import.meta.env.VITE_STREAM_URL || 'http://localhost:8000'

const props = defineProps<{
  modelValue: boolean
  cameraData: any
}>()

const emit = defineEmits(['update:modelValue', 'saved'])

const formRef = ref<FormInstance>()
const isSaving = ref(false)
const isChecking = ref(false)

const isEdit = computed(() => !!props.cameraData?.id)

const form = reactive({
  id: 0,
  name: '',
  type: 'ip_camera',
  rtsp_url: '',
  location_note: '',
  is_active: true
})

const rules = reactive<FormRules>({
  name: [
    { required: true, message: 'Vui lòng nhập tên camera', trigger: 'blur' },
    { max: 255, message: 'Độ dài không vượt quá 255 ký tự', trigger: 'blur' }
  ],
  type: [
    { required: true, message: 'Vui lòng chọn phân loại', trigger: 'change' }
  ],
  rtsp_url: [
    { required: true, message: 'Vui lòng nhập RTSP URL', trigger: 'blur' },
    { 
      pattern: /^rtsp:\/\//i, 
      message: 'RTSP URL phải bắt đầu bằng rtsp://', 
      trigger: 'blur' 
    }
  ]
})

watch(
  () => props.cameraData,
  (newVal) => {
    if (newVal) {
      form.id = newVal.id
      form.name = newVal.name || ''
      form.type = newVal.type || 'ip_camera'
      form.rtsp_url = newVal.rtsp_url || ''
      form.location_note = newVal.location_note || ''
      form.is_active = newVal.is_active !== false // default true
    } else {
      // Reset form if create
      resetFormInternal()
    }
  },
  { immediate: true }
)

function resetFormInternal() {
  form.id = 0
  form.name = ''
  form.type = 'ip_camera'
  form.rtsp_url = ''
  form.location_note = ''
  form.is_active = true
  if (formRef.value) {
    formRef.value.clearValidate()
  }
}

const handleClosed = () => {
  resetFormInternal()
  if (formRef.value) {
    formRef.value.resetFields()
  }
}

const checkConnection = async () => {
  if (!form.rtsp_url) {
    ElMessage.warning('Vui lòng nhập RTSP URL trước')
    return
  }
  if (!form.rtsp_url.toLowerCase().startsWith('rtsp://')) {
    ElMessage.error('RTSP URL sai định dạng')
    return
  }

  isChecking.value = true
  try {
    const { data } = await axios.post(`${FASTAPI_URL}/api/camera/check`, {
      rtsp_url: form.rtsp_url
    })
    
    if (data.success) {
      ElMessage.success({ message: 'Kết nối stream thành công!', duration: 3000 })
    } else {
      ElMessage.error({ message: `Không thể kết nối stream: ${data.message}`, duration: 5000 })
    }
  } catch (error) {
    ElMessage.error('Lỗi khi gọi API kiểm tra. Vui lòng đảm bảo FastAPI đang chạy.')
  } finally {
    isChecking.value = false
  }
}

const submitForm = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      isSaving.value = true
      try {
        const payload = {
          name: form.name,
          type: form.type,
          rtsp_url: form.rtsp_url,
          location_note: form.location_note,
          is_active: form.is_active,
          status: form.is_active ? 'active' : 'inactive' // for compatibility
        }
        
        let res
        if (isEdit.value) {
          res = await apiClient.put(`/api/cameras/${form.id}`, payload)
        } else {
          res = await apiClient.post('/api/cameras', payload)
        }
        
        if (res.data.success) {
          ElMessage.success(isEdit.value ? 'Cập nhật thành công' : 'Thêm mới thành công')
          emit('saved')
          emit('update:modelValue', false)
        }
      } catch (error: any) {
        if (error.response?.data?.errors) {
          // Flatten laravel validation errors
          const msgs = Object.values(error.response.data.errors).flat().join(', ')
          ElMessage.error(`Lỗi: ${msgs}`)
        } else {
          ElMessage.error('Đã xảy ra lỗi khi lưu thông tin camera.')
        }
      } finally {
        isSaving.value = false
      }
    }
  })
}
</script>

<style scoped>
.url-input-container {
  display: flex;
  width: 100%;
  gap: 10px;
}
.check-btn {
  white-space: nowrap;
}
</style>
