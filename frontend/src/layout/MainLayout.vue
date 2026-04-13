<template>
  <el-container class="layout-container">
    <el-aside width="280px" class="aside">
      <div class="logo-container">
        <h2>Shop SaaS</h2>
      </div>
      <el-menu
        :default-active="$route.path"
        class="el-menu-vertical"
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409EFF"
        router
      >
        <el-menu-item index="/">
          <el-icon><DataBoard /></el-icon>
          <span>Dashboard</span>
        </el-menu-item>
        <el-menu-item index="/pos">
          <el-icon><ShoppingCart /></el-icon>
          <span>Bán hàng (POS)</span>
        </el-menu-item>
        <el-sub-menu index="product-group" v-if="authStore.hasRole('admin') || authStore.hasRole('manager')">
          <template #title>
            <el-icon><Goods /></el-icon>
            <span>Thực đơn & Món ăn</span>
          </template>
          <el-menu-item index="/products">Danh sách món ăn</el-menu-item>
          <el-menu-item index="/categories">Danh mục món</el-menu-item>
        </el-sub-menu>
        <el-menu-item index="/orders">
          <el-icon><List /></el-icon>
          <span>Lịch sử Đơn hàng</span>
        </el-menu-item>

        <el-sub-menu index="inventory-group" v-if="authStore.hasRole('admin') || authStore.hasRole('manager')">
          <template #title>
            <el-icon><Box /></el-icon>
            <span>Quản lý Kho & nguyên liệu</span>
          </template>
          <el-menu-item index="/materials">Danh mục Nguyên liệu</el-menu-item>
          <el-menu-item index="/inventory">Quản lý Kho (Nghiệp vụ)</el-menu-item>
          <el-menu-item index="/suppliers">Nhà cung cấp</el-menu-item>
        </el-sub-menu>
        
        <el-sub-menu index="camera-group">
          <template #title>
            <el-icon><VideoCamera /></el-icon>
            <span>Cameras</span>
          </template>
          <el-menu-item index="/cameras">Giám sát Stream</el-menu-item>
          <el-menu-item index="/camera-manager">Quản lý Thiết bị</el-menu-item>
        </el-sub-menu>

        <el-sub-menu index="crm-group" v-if="authStore.hasRole('admin') || authStore.hasRole('manager')">
          <template #title>
            <el-icon><Star /></el-icon>
            <span>CRM & Tích điểm</span>
          </template>
          <el-menu-item index="/customers">Danh sách khách hàng</el-menu-item>
          <el-menu-item index="/customer-groups">Nhóm khách hàng</el-menu-item>
        </el-sub-menu>

        <el-sub-menu index="hr-group" v-if="authStore.hasPermission('manage_users') || authStore.hasPermission('manage_roles') || authStore.hasPermission('view_shifts')">
          <template #title>
            <el-icon><Avatar /></el-icon>
            <span>Quản lý nhân sự</span>
          </template>
          <el-menu-item index="/staff" v-if="authStore.hasPermission('manage_users')">Danh sách nhân viên</el-menu-item>
          <el-menu-item index="/roles" v-if="authStore.hasPermission('manage_roles')">Phân quyền</el-menu-item>
          <el-menu-item index="/shifts" v-if="authStore.hasPermission('manage_shifts')">Quản lý ca</el-menu-item>
          <el-menu-item index="/shifts/report" v-if="authStore.hasPermission('view_shifts')">Lịch sử ca làm việc</el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>
    
    <el-container>
      <el-header class="header">
        <div class="header-left">
          <h3>{{ $route.meta.title || 'Shop Management' }}</h3>
        </div>
        <div class="header-right">
          <el-dropdown trigger="click" @command="handleCommand">
            <span class="el-dropdown-link user-profile">
              <el-avatar size="small" icon="UserFilled" />
              <span class="username">{{ authStore.tenantId || 'User' }}</span>
              <el-icon class="el-icon--right"><arrow-down /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <!-- Chỉ hiển thị nút Kết ca cho Staff hoặc Quản lý đã vào ca -->
                <el-dropdown-item v-if="!activeShift" command="start_shift">Bắt đầu ca</el-dropdown-item>
                <el-dropdown-item v-if="activeShift && authStore.hasRole('staff')" command="end_shift">Kết ca</el-dropdown-item>
                <el-dropdown-item v-if="activeShift && !authStore.hasRole('staff')" command="end_shift">Kết ca (Manual)</el-dropdown-item>
                <el-dropdown-item divided command="logout">Đăng xuất</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>
      
      <el-main class="main-content">
        <router-view />
      </el-main>
    </el-container>

    <!-- Start Shift Modal -->
    <el-dialog title="Bắt đầu ca làm việc" v-model="startShiftVisible" width="400px" :close-on-click-modal="false" :show-close="false">
      <el-form label-position="top">
        <el-form-item label="Tiền mặt đầu ca (VNĐ)">
          <el-input-number v-model="shiftForm.start_cash" :min="0" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button type="primary" @click="startShift" :loading="shiftLoading">Bắt đầu</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- End Shift Modal -->
    <el-dialog title="Kết ca làm việc" v-model="endShiftVisible" width="500px" :close-on-click-modal="false">
      <div v-if="activeShift">
        <!-- Phần 1: Báo cáo doanh thu -->
        <el-divider content-position="left">📊 Báo cáo doanh thu (Hệ thống)</el-divider>
        <div class="revenue-report">
          <div class="report-item">
            <span>💵 Tiền mặt:</span>
            <strong>{{ formatCurrency(activeShift.sales_summary?.cash) }}</strong>
          </div>
          <div class="report-item">
            <span>💳 Thẻ/Chuyển khoản:</span>
            <strong>{{ formatCurrency(Number(activeShift.sales_summary?.transfer) + Number(activeShift.sales_summary?.card)) }}</strong>
          </div>
          <div class="report-item">
            <span>📱 Ví điện tử:</span>
            <strong>{{ formatCurrency(Number(activeShift.sales_summary?.momo) + Number(activeShift.sales_summary?.apple_pay)) }}</strong>
          </div>
          <el-divider />
          <div class="report-item total">
            <span>TỔNG DOANH THU CA:</span>
            <span class="total-value">{{ formatCurrency(activeShift.sales_summary?.total_revenue) }}</span>
          </div>
        </div>

        <!-- Phần 2: Đối soát tiền mặt thực tế -->
        <el-divider content-position="left">💰 Đối soát tiền mặt thực tế</el-divider>
        <el-form label-position="top">
          <div class="formula-box">
            <div class="formula-line">
              <span>Tiền đầu ca:</span>
              <span>{{ formatCurrency(activeShift.start_cash) }}</span>
            </div>
            <div class="formula-line">
              <span>+ Tiền mặt bán được:</span>
              <span>{{ formatCurrency(activeShift.sales_summary?.cash) }}</span>
            </div>
            <el-divider style="margin: 8px 0" />
            <div class="formula-line expected">
              <span>= Tổng lý thuyết:</span>
              <strong>{{ formatCurrency(expectedTotal) }}</strong>
            </div>
          </div>

          <el-form-item label="Tiền mặt thực tế tại quầy (VNĐ)" required>
            <el-input-number 
              v-model="shiftForm.end_cash" 
              :min="0" 
              style="width: 100%" 
              placeholder="Nhập số tiền mặt thực tế"
              :precision="0"
              :step="1000"
            />
          </el-form-item>

          <div class="gap-display" :class="gapColorClass" v-if="shiftForm.end_cash !== null && shiftForm.end_cash !== undefined">
            <div class="gap-text">
              <span class="label">Chênh lệch:</span>
              <span class="value">{{ realTimeGap > 0 ? '+' : '' }}{{ formatCurrency(realTimeGap) }}</span>
            </div>
            <div class="gap-status">
              <el-tag v-if="realTimeGap === 0" type="success" effect="dark" round>Khớp (Hoàn hảo)</el-tag>
              <el-tag v-else-if="realTimeGap > 0" type="warning" effect="dark" round>Thành thừa (Dương)</el-tag>
              <el-tag v-else type="danger" effect="dark" round>Thiếu tiền (Âm)</el-tag>
            </div>
          </div>

          <el-form-item label="Lý do chênh lệch" v-if="realTimeGap !== 0" required>
            <el-input 
              type="textarea" 
              v-model="shiftForm.reason" 
              placeholder="Bắt buộc nhập lý do nếu tiền thực tế không khớp với hệ thống" 
              rows="2"
            />
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="endShiftVisible = false">Hủy</el-button>
          <el-button 
            type="success" 
            @click="endShift" 
            :loading="shiftLoading"
            :disabled="!canCloseShift"
          >
            Xác nhận Kết ca
          </el-button>
        </span>
      </template>
    </el-dialog>
  </el-container>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { apiClient } from '@/services/axios'
import { ElMessage } from 'element-plus'
import {
  DataBoard,
  List,
  VideoCamera,
  ArrowDown,
  ShoppingCart,
  Goods,
  Avatar,
  Box,
  Star
} from '@element-plus/icons-vue'

const authStore = useAuthStore()
const router = useRouter()

// Shift Logic
const activeShift = ref<any>(null)
const startShiftVisible = ref(false)
const endShiftVisible = ref(false)
const shiftLoading = ref(false)
const requireReason = ref(false)
const systemExpected = ref(0)
const gap = ref(0)

const expectedTotal = computed(() => {
  if (!activeShift.value) return 0
  const opening = Number(activeShift.value.start_cash) || 0
  const cashSales = Number(activeShift.value.sales_summary?.cash) || 0
  return opening + cashSales
})

const realTimeGap = computed(() => {
  return (shiftForm.end_cash || 0) - expectedTotal.value
})

const gapColorClass = computed(() => {
  if (realTimeGap.value === 0) return 'gap-green'
  if (realTimeGap.value > 0) return 'gap-orange'
  return 'gap-red'
})

const canCloseShift = computed(() => {
  if (realTimeGap.value === 0) return true
  return shiftForm.reason && shiftForm.reason.trim().length > 0
})

const shiftForm = reactive({
  start_cash: 0,
  end_cash: 0,
  reason: ''
})

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const checkCurrentShift = async () => {
  try {
    const { data } = await apiClient.get('/api/shifts/current')
    if (data.data) {
      activeShift.value = data.data
    } else if (authStore.hasRole('staff')) {
      // Chỉ bắt buộc mở ca đối với Staff
      startShiftVisible.value = true
    }
  } catch (error) {
    console.error('Lỗi kiểm tra ca làm việc', error)
  }
}

const startShift = async () => {
  shiftLoading.value = true
  try {
    const { data } = await apiClient.post('/api/shifts/start', { start_cash: shiftForm.start_cash })
    activeShift.value = data.data
    startShiftVisible.value = false
    ElMessage.success('Bắt đầu ca làm việc thành công')
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || 'Bắt đầu ca thất bại')
  } finally {
    shiftLoading.value = false
  }
}

const endShift = async () => {
  shiftLoading.value = true
  try {
    await apiClient.post('/api/shifts/end', {
      end_cash: shiftForm.end_cash,
      reason: shiftForm.reason
    })
    activeShift.value = null
    endShiftVisible.value = false
    requireReason.value = false
    ElMessage.success('Kết ca thành công. Đang đăng xuất...')
    
    // Auto logout after end shift? Or just let them be?
    // Let's logout
    setTimeout(() => {
      authStore.logout()
      router.push({ name: 'login' })
    }, 1000)
    
  } catch (error: any) {
    if (error.response?.data?.require_reason) {
      requireReason.value = true
      systemExpected.value = error.response.data.system_expected || 0
      gap.value = error.response.data.gap || 0
      ElMessage.warning(error.response.data.message)
    } else {
      ElMessage.error(error.response?.data?.message || 'Kết ca thất bại')
    }
  } finally {
    shiftLoading.value = false
  }
}

const handleCommand = async (command: string) => {
  if (command === 'logout') {
    // Admin và Manager đăng xuất ngay lập tức
    if (!authStore.hasRole('staff')) {
      authStore.logout()
      router.push({ name: 'login' })
      return
    }

    if (activeShift.value) {
      ElMessage.warning('Vui lòng kết ca trước khi đăng xuất!')
      await checkCurrentShift()
      endShiftVisible.value = true
    } else {
      authStore.logout()
      router.push({ name: 'login' })
    }
  } else if (command === 'start_shift') {
    startShiftVisible.value = true
  } else if (command === 'end_shift') {
    await checkCurrentShift()
    endShiftVisible.value = true
  }
}

onMounted(() => {
  checkCurrentShift()
})
</script>

<style scoped>
.layout-container {
  height: 100vh;
  width: 100vw;
}

.aside {
  background-color: #304156;
  color: white;
  display: flex;
  flex-direction: column;
}

.logo-container {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #2b3643;
  color: #fff;
  border-bottom: 1px solid #1f2d3d;
}

.el-menu-vertical {
  border-right: none;
  flex: 1;
}

.header {
  background-color: #fff;
  box-shadow: 0 1px 4px rgba(0,21,41,.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.header-left h3 {
  margin: 0;
  font-size: 18px;
  color: #303133;
}

.user-profile {
  display: flex;
  align-items: center;
  cursor: pointer;
  color: #606266;
}

.username {
  margin-left: 8px;
  font-weight: 500;
}

.main-content {
  background-color: #f0f2f5;
  padding: 20px;
}

/* Revenue Report Styles */
.revenue-report {
  background: #f8f9fb;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}
.report-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 14px;
}
.report-item.total {
  margin-top: 10px;
  font-weight: bold;
  font-size: 16px;
  color: #303133;
}
.total-value {
  color: #409EFF;
}

/* Formula Styles */
.formula-box {
  background: #fffbe6;
  border: 1px solid #ffe58f;
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 15px;
}
.formula-line {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #595959;
  margin-bottom: 4px;
}
.formula-line.expected {
  font-size: 15px;
  color: #262626;
}

/* Gap Display Styles */
.gap-display {
  padding: 15px;
  border-radius: 12px;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
  border: 1px solid transparent;
}
.gap-text {
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.gap-text .label {
  font-size: 14px;
  opacity: 0.9;
}
.gap-text .value {
  font-size: 20px;
  font-weight: 800;
}
.gap-green {
  background: linear-gradient(135deg, #f6ffed 0%, #d9f7be 100%);
  border-color: #b7eb8f;
  color: #389e0d;
}
.gap-orange {
  background: linear-gradient(135deg, #fff7e6 0%, #ffe7ba 100%);
  border-color: #ffd591;
  color: #d46b08;
}
.gap-red {
  background: linear-gradient(135deg, #fff1f0 0%, #ffccc7 100%);
  border-color: #ffa39e;
  color: #cf1322;
}
</style>
