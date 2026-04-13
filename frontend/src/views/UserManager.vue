<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>Quản lý Nhân sự</span>
      </div>
    </template>

    <el-table :data="users" v-loading="loading" border style="width: 100%">
      <el-table-column prop="id" label="ID" width="80" align="center" />
      <el-table-column prop="name" label="Họ tên" />
      <el-table-column prop="email" label="Email" />
      <el-table-column prop="created_at" label="Ngày tạo">
        <template #default="{ row }">
          {{ new Date(row.created_at).toLocaleDateString('vi-VN') }}
        </template>
      </el-table-column>
      <el-table-column label="Vai trò" width="200" align="center">
        <template #default="{ row }">
          <el-select
            v-model="row.role_id"
            placeholder="Chọn vai trò"
            style="width: 100%"
            @change="handleRoleChange(row)"
          >
            <el-option
              v-for="role in roles"
              :key="role.id"
              :label="role.name"
              :value="role.name"
            />
          </el-select>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient } from '@/services/axios'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const users = ref<any[]>([])
const roles = ref<any[]>([])
const loading = ref(false)
const authStore = useAuthStore()

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
    // map role for v-model
    users.value = data.data.map((u: any) => ({
      ...u,
      role_id: u.roles && u.roles.length > 0 ? u.roles[0].name : ''
    }))
  } catch (error) {
    ElMessage.error('Lỗi tải danh sách nhân viên')
  } finally {
    loading.value = false
  }
}

const handleRoleChange = async (user: any) => {
  try {
    await apiClient.put(`/api/users/${user.id}/assign-role`, {
      role: user.role_id
    })
    ElMessage.success(`Cập nhật vai trò cho ${user.name} thành công.`)
    // re-fetch me if it's the current user
    if (user.id === authStore.user?.id) {
       await authStore.fetchUser()
    }
  } catch (error) {
    ElMessage.error('Cập nhật vai trò thất bại')
    fetchUsers() // revert
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
</style>
