import axios from 'axios'

// 1. Client for Laravel Backend (Auth & Data)
export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request Interceptor: Attach Token and Tenant ID
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  const tenantId = localStorage.getItem('tenant_id')

  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`
  }
  if (tenantId) {
    config.headers['X-Tenant-ID'] = tenantId
  }

  return config
}, (error) => {
  return Promise.reject(error)
})


// 2. Client for FastAPI Backend (Camera Stream / Prints)
export const streamClient = axios.create({
  baseURL: import.meta.env.VITE_STREAM_URL || 'http://localhost:8000',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

export default {
  apiClient,
  streamClient
}
