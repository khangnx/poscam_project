import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getTenantId } from '@/utils/tenant'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const tenantId = ref<string | null>(getTenantId())
  const user = ref<User | null>(null)

  function setAuthData(newToken: string, newTenant: string, newUser: User) {
    token.value = newToken
    tenantId.value = newTenant
    user.value = newUser

    localStorage.setItem('auth_token', newToken)
    localStorage.setItem('tenant_id', newTenant)
  }

  function logout() {
    token.value = null
    user.value = null
    // Keep tenant_id in localStorage intentionally if using subdomains, or clear if needed.
    localStorage.removeItem('auth_token')
  }

  async function fetchUser() {
    if (!token.value) return null;
    try {
      const response = await fetch('/api/me', {
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Accept': 'application/json',
          'X-Tenant-ID': tenantId.value || ''
        }
      });
      if (response.ok) {
        const body = await response.json();
        user.value = body.data;
        return body.data;
      }
      return null;
    } catch (error) {
      console.error('Failed to fetch user', error);
      return null;
    }
  }

  const isAuthenticated = () => !!token.value

  const hasRole = (role: string) => {
    return user.value?.roles?.some((r: any) => r.name === role) || false
  }

  const hasPermission = (permission: string) => {
    return user.value?.permissions_list?.includes(permission) || false
  }

  const isStaff = () => hasRole('staff')
  const canViewDashboard = () => !isStaff() || hasPermission('view_dashboard')

  return {
    token,
    tenantId,
    user,
    setAuthData,
    logout,
    fetchUser,
    isAuthenticated,
    hasRole,
    hasPermission,
    isStaff,
    canViewDashboard
  }
})
