import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue')
    },
    {
      path: '/',
      component: () => import('@/layout/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/Dashboard.vue'),
          meta: { title: 'Dashboard' }
        },
        {
          path: 'cameras',
          name: 'cameras',
          component: () => import('@/views/CameraList.vue'),
          meta: { title: 'Camera Monitors' }
        },
        {
          path: 'camera-manager',
          name: 'camera-manager',
          component: () => import('@/views/CameraManager.vue'),
          meta: { title: 'Quản lý Camera' }
        },
        {
          path: 'pos',
          name: 'pos',
          component: () => import('@/views/PosView.vue'),
          meta: { title: 'Bán hàng (POS)' }
        },
        {
          path: 'products',
          name: 'products',
          component: () => import('@/views/ProductList.vue'),
          meta: { title: 'Thực đơn & Món ăn' }
        },
        {
          path: 'materials',
          name: 'materials',
          component: () => import('@/views/MaterialList.vue'),
          meta: { title: 'Danh mục Nguyên liệu', roles: ['admin', 'manager'] }
        },
        {
          path: 'categories',
          name: 'categories',
          component: () => import('@/views/CategoryList.vue'),
          meta: { title: 'Dah mục sản phẩm' }
        },
        {
          path: 'orders',
          name: 'orders',
          component: () => import('@/views/OrderList.vue'),
          meta: { title: 'Lịch sử Đơn hàng' }
        },
        {
          path: 'staff',
          name: 'staff',
          component: () => import('@/views/StaffList.vue'),
          meta: { title: 'Quản lý Nhân viên' }
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/RoleManager.vue'),
          meta: { title: 'Phân quyền' }
        },
        {
          path: 'shifts',
          name: 'shifts',
          component: () => import('@/views/ShiftManager.vue'),
          meta: { title: 'Quản lý Ca làm việc' }
        },
        {
          path: 'shifts/report',
          name: 'shift-report',
          component: () => import('@/views/ShiftReport.vue'),
          meta: { title: 'Lịch sử Ca làm việc' }
        },
        {
          path: 'inventory',
          name: 'inventory',
          component: () => import('@/views/InventoryManager.vue'),
          meta: { title: 'Quản lý Kho (Nghiệp vụ Nhập/Xuất)', roles: ['admin', 'manager'] }
        },
        {
          path: 'suppliers',
          name: 'suppliers',
          component: () => import('@/views/SupplierList.vue'),
          meta: { title: 'Nhà cung cấp', roles: ['admin', 'manager'] }
        },
        {
          path: 'customers',
          name: 'customers',
          component: () => import('@/views/CustomerManager.vue'),
          meta: { title: 'Quản lý Khách hàng', roles: ['admin', 'manager'] }
        },
        {
          path: 'customer-groups',
          name: 'customer-groups',
          component: () => import('@/views/CustomerGroupList.vue'),
          meta: { title: 'Nhóm khách hàng', roles: ['admin', 'manager'] }
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/views/reports/ReportDashboard.vue'),
          meta: { title: 'Báo cáo Chuyên sâu', roles: ['admin', 'manager'] }
        }
      ]
    }
  ]
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()
  
  // If we have a token but no user data (e.g. after refresh), fetch it first
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated()) {
    return { name: 'login' }
  }
})

export default router
