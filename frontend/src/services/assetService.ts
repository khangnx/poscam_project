import { apiClient } from './axios'

export interface AssetCategory {
  id?: number
  name: string
  description?: string
  created_at?: string
}

export interface Asset {
  id?: number
  category_id: number | null
  name: string
  code: string
  quantity: number
  status: string
  metadata?: Record<string, any>
  category?: AssetCategory
  created_at?: string
}

export const assetService = {
  // Categories
  getCategories(params?: any) {
    return apiClient.get('/api/asset-categories', { params })
  },
  getCategory(id: number) {
    return apiClient.get(`/api/asset-categories/${id}`)
  },
  createCategory(data: AssetCategory) {
    return apiClient.post('/api/asset-categories', data)
  },
  updateCategory(id: number, data: AssetCategory) {
    return apiClient.put(`/api/asset-categories/${id}`, data)
  },
  deleteCategory(id: number) {
    return apiClient.delete(`/api/asset-categories/${id}`)
  },

  // Assets
  getAssets(params?: any) {
    return apiClient.get('/api/assets', { params })
  },
  getAsset(id: number) {
    return apiClient.get(`/api/assets/${id}`)
  },
  createAsset(data: Asset) {
    return apiClient.post('/api/assets', data)
  },
  updateAsset(id: number, data: Asset) {
    return apiClient.put(`/api/assets/${id}`, data)
  },
  deleteAsset(id: number) {
    return apiClient.delete(`/api/assets/${id}`)
  }
}
