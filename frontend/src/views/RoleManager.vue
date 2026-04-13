<template>
  <el-card>
    <template #header>
      <div class="card-header">
        <span>Quản lý Phân quyền</span>
      </div>
    </template>

    <el-table :data="roles" v-loading="loading" border style="width: 100%">
      <el-table-column prop="id" label="ID" width="80" align="center" />
      <el-table-column prop="name" label="Tên Vai trò">
        <template #default="{ row }">
            <el-tag size="large">{{ row.name }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Quyền hạn">
        <template #default="{ row }">
          <el-tag
            v-for="perm in row.permissions"
            :key="perm.id"
            type="info"
            class="mx-1 my-1"
          >
            {{ perm.name }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Thao tác" width="150" align="center">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="openEdit(row)">
            Chỉnh sửa
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog title="Chỉnh sửa quyền hạn" v-model="dialogVisible" width="700px">
      <div v-if="editingRole">
        <h4 class="mb-4">Vai trò: {{ editingRole.name }}</h4>
        <el-checkbox-group v-model="selectedPermissions" class="permission-grid">
          <el-checkbox
            v-for="perm in permissions"
            :key="perm.id"
            :label="perm.name"
            :value="perm.name"
            border
            class="permission-item"
          >
            {{ perm.name }}
          </el-checkbox>
        </el-checkbox-group>
      </div>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Hủy</el-button>
          <el-button type="primary" @click="savePermissions" :loading="saving">Lưu lại</el-button>
        </span>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient } from '@/services/axios'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const roles = ref<any[]>([])
const permissions = ref<any[]>([])
const loading = ref(false)

const dialogVisible = ref(false)
const editingRole = ref<any>(null)
const selectedPermissions = ref<string[]>([])
const saving = ref(false)

const authStore = useAuthStore()

const fetchData = async () => {
    loading.value = true
    try {
        const [rolesRes, permsRes] = await Promise.all([
            apiClient.get('/api/roles'),
            apiClient.get('/api/roles/permissions')
        ])
        roles.value = rolesRes.data.data
        permissions.value = permsRes.data.data
    } catch(err) {
        ElMessage.error('Lỗi khi tải dữ liệu phân quyền')
    } finally {
        loading.value = false
    }
}

const openEdit = (role: any) => {
    editingRole.value = role
    selectedPermissions.value = role.permissions.map((p: any) => p.name)
    dialogVisible.value = true
}

const savePermissions = async () => {
    if (!editingRole.value) return
    saving.value = true
    try {
        await apiClient.post(`/api/roles/${editingRole.value.id}/sync-permissions`, {
            permissions: selectedPermissions.value
        })
        ElMessage.success('Cập nhật quyền thành công!')
        dialogVisible.value = false
        fetchData()
        
        // Refresh my own permissions if I edited my own role (e.g. admin)
        if (authStore.hasRole(editingRole.value.name)) {
            await authStore.fetchUser()
        }
    } catch(err) {
        ElMessage.error('Có lỗi xảy ra khi lưu quyền.')
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    fetchData()
})
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mx-1 {
    margin-left: 4px;
    margin-right: 4px;
}
.my-1 {
    margin-top: 4px;
    margin-bottom: 4px;
}
.permission-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.permission-item {
    margin-right: 0 !important;
}
</style>
