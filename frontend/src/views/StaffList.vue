<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>Danh sách Nhân viên</span>
        <el-button type="primary" @click="openDialog()">
          <el-icon><Plus /></el-icon> Thêm nhân viên
        </el-button>
      </div>
    </template>

    <el-table :data="users" v-loading="loading" border style="width: 100%">
      <el-table-column label="Nhân viên" min-width="200">
        <template #default="{ row }">
          <div style="display: flex; align-items: center; gap: 10px;">
            <el-avatar :size="40" :src="row.avatar || 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png'" />
            <div style="display: flex; flex-direction: column;">
              <span style="font-weight: 500;">{{ row.name }}</span>
              <span style="color: #909399; font-size: 13px;">{{ row.email }}</span>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="phone" label="Số điện thoại" width="150" />
      <el-table-column label="Vai trò" width="180">
        <template #default="{ row }">
          <el-tag :type="getRoleTagType(row.roles?.[0]?.name)">
            {{ row.roles?.[0]?.name || 'Chưa phân quyền' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Trạng thái" width="120" align="center">
        <template #default="{ row }">
          <el-switch
            v-model="row.is_active"
            :active-value="true"
            :inactive-value="false"
            inline-prompt
            active-text="Mở"
            inactive-text="Khóa"
            @change="handleStatusChange(row)"
            :disabled="row.id === authStore.user?.id"
          />
        </template>
      </el-table-column>
      <el-table-column label="Ngày tạo" width="130">
        <template #default="{ row }">
          {{ new Date(row.created_at).toLocaleDateString('vi-VN') }}
        </template>
      </el-table-column>
      <el-table-column label="Thao tác" width="220" align="center" fixed="right">
        <template #default="{ row }">
          <el-tooltip content="Đổi mật khẩu" placement="top">
            <el-button type="warning" circle size="small" @click="openPasswordDialog(row)">
               <el-icon><Key /></el-icon>
            </el-button>
          </el-tooltip>
          <el-tooltip content="Chỉnh sửa" placement="top">
            <el-button type="primary" circle size="small" @click="openDialog(row)">
              <el-icon><Edit /></el-icon>
            </el-button>
          </el-tooltip>
          <el-tooltip content="Xóa" placement="top">
            <el-button type="danger" circle size="small" @click="handleDelete(row)" :disabled="row.id === authStore.user?.id">
              <el-icon><Delete /></el-icon>
            </el-button>
          </el-tooltip>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog thêm/sửa nhân viên -->
    <el-dialog
      :title="isEdit ? 'Chỉnh sửa nhân viên' : 'Thêm nhân viên mới'"
      v-model="dialogVisible"
      width="650px"
      @close="resetForm"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-width="120px" label-position="left">
        <el-form-item label="Avatar" prop="avatar">
           <el-upload
            class="avatar-uploader"
            action="#"
            :show-file-list="false"
            :auto-upload="false"
            :on-change="handleAvatarChange"
            accept="image/*"
          >
            <img v-if="imageUrl" :src="imageUrl" class="avatar" />
            <el-icon v-else class="avatar-uploader-icon"><Plus /></el-icon>
          </el-upload>
          <div style="font-size: 12px; color: #999; margin-top: 5px; line-height: 1.2;">Click để tải lên ảnh đại diện (Tối đa 2MB)</div>
        </el-form-item>

        <el-form-item label="Họ và tên" prop="name">
          <el-input v-model="form.name" placeholder="Nhập tên nhân viên"></el-input>
        </el-form-item>
        
        <el-form-item label="Email" prop="email">
          <el-input v-model="form.email" placeholder="Nhập email nhân viên"></el-input>
        </el-form-item>
        
        <el-form-item label="Số điện thoại" prop="phone">
          <el-input v-model="form.phone" placeholder="Nhập số điện thoại"></el-input>
        </el-form-item>

        <el-form-item label="Phân quyền" prop="role">
          <el-select v-model="form.role" placeholder="Chọn vai trò">
            <el-option
              v-for="role in roles"
              :key="role.id"
              :label="role.name"
              :value="role.name"
            ></el-option>
          </el-select>
        </el-form-item>

        <div v-if="!isEdit" style="background: #f4f4f5; padding: 10px; border-radius: 4px; margin-top: 20px;">
          <el-icon style="vertical-align: middle; margin-right: 5px; color: #E6A23C;"><InfoFilled /></el-icon>
          <span style="font-size: 13px;">Mật khẩu mặc định khi tạo mới là <b>123456</b>. Nhân viên có thể đổi sau khi đăng nhập.</span>
        </div>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Hủy</el-button>
          <el-button type="primary" @click="submitForm" :loading="submitLoading">Lưu</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- Dialog đổi mật khẩu -->
    <el-dialog
      title="Đổi mật khẩu nhân viên"
      v-model="pwdDialogVisible"
      width="400px"
    >
      <el-form :model="pwdForm" ref="pwdFormRef" label-position="top">
        <el-form-item label="Mật khẩu mới (tối thiểu 6 ký tự)" prop="password" :rules="[{ required: true, message: 'Vui lòng nhập mật khẩu', trigger: 'blur' }, { min: 6, message: 'Tối thiểu 6 ký tự', trigger: 'blur' }]">
          <el-input v-model="pwdForm.password" type="password" show-password placeholder="Nhập mật khẩu mới"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="pwdDialogVisible = false">Hủy</el-button>
          <el-button type="warning" @click="submitPassword" :loading="submitLoading">Cập nhật mật khẩu</el-button>
        </span>
      </template>
    </el-dialog>

  </el-card>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus, Edit, Delete, Key, InfoFilled } from '@element-plus/icons-vue'
import { apiClient } from '@/services/axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const users = ref<any[]>([])
const roles = ref<any[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref<FormInstance>()
const submitLoading = ref(false)
const authStore = useAuthStore()

const pwdDialogVisible = ref(false)
const pwdFormRef = ref<FormInstance>()
const pwdTargetId = ref<number | null>(null)
const pwdForm = reactive({
  password: ''
})

const form = reactive({
  id: null as number | null,
  name: '',
  email: '',
  phone: '',
  role: '',
})

// Image upload handling
const avatarFile = ref<File | null>(null)
const imageUrl = ref<string>('')

const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost'

const getRoleTagType = (role: string) => {
  if (role === 'admin') return 'danger'
  if (role === 'manager') return 'warning'
  return 'info'
}

const rules = reactive<FormRules>({
  name: [{ required: true, message: 'Vui lòng nhập họ tên', trigger: 'blur' }],
  email: [
    { required: true, message: 'Vui lòng nhập email', trigger: 'blur' },
    { type: 'email', message: 'Email không hợp lệ', trigger: ['blur', 'change'] }
  ],
  role: [{ required: true, message: 'Vui lòng chọn vai trò', trigger: 'change' }]
})

const fetchRoles = async () => {
  try {
    const { data } = await apiClient.get('/api/roles')
    roles.value = data.data
  } catch(err) {
    ElMessage.error('Lỗi khi tải danh sách vai trò')
  }
}

const fetchUsers = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get('/api/users')
    users.value = data.data.map((u: any) => {
        // Prepare full URL for avatar if it exists
        if (u.avatar && !u.avatar.startsWith('http')) {
             u.avatar = BASE_URL + u.avatar
        }
        return u
    })
  } catch (error) {
    ElMessage.error('Lỗi tải danh sách nhân viên')
  } finally {
    loading.value = false
  }
}

const handleAvatarChange = (uploadFile: any) => {
  const file = uploadFile.raw
  const isJpgOrPng = file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/webp'
  const isLt2M = file.size / 1024 / 1024 < 2

  if (!isJpgOrPng) {
    ElMessage.error('Avatar chỉ hỗ trợ JPG, PNG, WEBP!')
    return false
  }
  if (!isLt2M) {
    ElMessage.error('Kích thước ảnh tối đa 2MB!')
    return false
  }
  
  avatarFile.value = file
  imageUrl.value = URL.createObjectURL(file)
  return true
}

const openDialog = (row?: any) => {
  isEdit.value = !!row
  if (row) {
    form.id = row.id
    form.name = row.name
    form.email = row.email
    form.phone = row.phone || ''
    form.role = row.roles?.[0]?.name || ''
    imageUrl.value = row.avatar || ''
  } else {
    form.id = null
    form.name = ''
    form.email = ''
    form.phone = ''
    form.role = ''
    imageUrl.value = ''
  }
  avatarFile.value = null
  dialogVisible.value = true
}

const resetForm = () => {
  if (formRef.value) formRef.value.resetFields()
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitLoading.value = true
      try {
        const formData = new FormData()
        formData.append('name', form.name)
        formData.append('email', form.email)
        if (form.phone) formData.append('phone', form.phone)
        formData.append('role', form.role)
        
        if (avatarFile.value) {
          formData.append('avatar', avatarFile.value)
        }

        if (isEdit.value) {
            // Laravel PUT with FormData can be tricky. Use POST with _method = PUT.
            formData.append('_method', 'PUT')
            await apiClient.post(`/api/users/${form.id}`, formData, {
               headers: { 'Content-Type': 'multipart/form-data' }
            })
            ElMessage.success('Cập nhật nhân viên thành công')
        } else {
            await apiClient.post('/api/users', formData, {
               headers: { 'Content-Type': 'multipart/form-data' }
            })
            ElMessage.success('Tạo nhân viên mới thành công')
        }
        dialogVisible.value = false
        fetchUsers()
      } catch (error: any) {
        ElMessage.error(error.response?.data?.message || 'Lỗi khi lưu nhân viên')
      } finally {
        submitLoading.value = false
      }
    }
  })
}

const handleStatusChange = async (row: any) => {
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('is_active', row.is_active ? '1' : '0')
    await apiClient.post(`/api/users/${row.id}`, formData)
    ElMessage.success(`Đã ${row.is_active ? 'mở' : 'khóa'} tài khoản ${row.name}`)
  } catch (err: any) {
    ElMessage.error('Lỗi khi cập nhật trạng thái')
    row.is_active = !row.is_active // revert toggle
  }
}

const openPasswordDialog = (row: any) => {
  pwdTargetId.value = row.id
  pwdForm.password = ''
  pwdDialogVisible.value = true
  if (pwdFormRef.value) pwdFormRef.value.resetFields()
}

const submitPassword = async () => {
  if (!pwdFormRef.value) return
  await pwdFormRef.value.validate(async (valid) => {
    if (valid) {
      submitLoading.value = true
      try {
        const formData = new FormData()
        formData.append('_method', 'PUT')
        formData.append('password', pwdForm.password)
        await apiClient.post(`/api/users/${pwdTargetId.value}`, formData)
        ElMessage.success('Đổi mật khẩu nhân viên thành công')
        pwdDialogVisible.value = false
      } catch (error: any) {
        ElMessage.error(error.response?.data?.message || 'Lỗi khi đổi mật khẩu')
      } finally {
        submitLoading.value = false
      }
    }
  })
}

const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `Bạn có chắc chắn muốn xóa nhân viên ${row.name}? Hành động này sẽ không thể hoàn tác nếu mất dữ liệu liên quan.`,
      'Xóa nhân viên',
      { confirmButtonText: 'Xóa', cancelButtonText: 'Hủy', type: 'warning' }
    )
    
    await apiClient.delete(`/api/users/${row.id}`)
    ElMessage.success('Đã xóa nhân viên thành công')
    fetchUsers()
  } catch (error: any) {
    if (error === 'cancel') return
    ElMessage.error(error.response?.data?.message || 'Lỗi khi xóa nhân viên')
  }
}

onMounted(() => {
    fetchRoles()
    fetchUsers()
})
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.avatar-uploader .el-upload {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: .2s;
}
.avatar-uploader .el-upload:hover {
  border-color: #409EFF;
}
.avatar-uploader-icon {
  font-size: 28px;
  color: #8c939d;
  width: 100px;
  height: 100px;
  display: flex;
  justify-content: center;
  align-items: center;
  border: 1px dashed var(--el-border-color);
  border-radius: 50%;
}
.avatar {
  width: 100px;
  height: 100px;
  display: block;
  object-fit: cover;
  border-radius: 50%;
}
</style>
