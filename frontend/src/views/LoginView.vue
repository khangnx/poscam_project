<template>
  <div class="login-container">
    <el-card class="login-card">
      <div class="login-header">
        <h2>Shop SaaS Login</h2>
      </div>
      <el-form @submit.prevent="handleLogin" :model="loginForm" label-position="top">
        <el-form-item label="Email">
          <el-input 
            v-model="loginForm.email" 
            placeholder="admin@example.com" 
            type="email"
            required 
          />
        </el-form-item>
        <el-form-item label="Password">
          <el-input 
            v-model="loginForm.password" 
            type="password" 
            placeholder="••••••••" 
            show-password
            required 
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" native-type="submit" :loading="loading" class="login-btn">
            Sign In
          </el-button>
        </el-form-item>
      </el-form>
      <div class="tenant-info" v-if="detectedTenantId">
        Logging in to workspace: <strong>{{ detectedTenantId }}</strong>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiClient } from '@/services/axios'
import { getTenantId } from '@/utils/tenant'
import { ElMessage } from 'element-plus'

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const detectedTenantId = ref<string | null>(null)

const loginForm = ref({
  email: '',
  password: ''
})

onMounted(() => {
  detectedTenantId.value = getTenantId()
})

const handleLogin = async () => {
  loading.value = true
  try {
    // In MVP, we often assume Laravel standard path is /api/login
    // We attach tenant_id in payload if needed, or rely on headers
    const payload = {
      email: loginForm.value.email,
      password: loginForm.value.password,
      tenant_id: detectedTenantId.value // Optional based on backend requirement
    }

    const { data } = await apiClient.post('/api/login', payload)
    
    const responseData = data.data || {}
    const token = responseData.access_token || 'dummy-token' 
    const user = responseData.user || { id: 1, name: 'Admin', email: loginForm.value.email, tenant_id: detectedTenantId.value || 'default-tenant' }
    const tenant = user.tenant_id

    authStore.setAuthData(token, tenant, user)
    
    ElMessage.success('Login successful')
    router.push({ name: 'dashboard' })
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || 'Login failed. Please check your credentials.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  background-color: #f2f3f5;
}

.login-card {
  width: 400px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.login-header {
  text-align: center;
  margin-bottom: 24px;
}

.login-header h2 {
  margin: 0;
  color: #303133;
}

.login-btn {
  width: 100%;
}

.tenant-info {
  margin-top: 15px;
  text-align: center;
  font-size: 13px;
  color: #909399;
}
</style>
