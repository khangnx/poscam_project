export interface User {
  id: number
  name: string
  email: string
  tenant_id: string
  roles?: { name: string }[]
  permissions_list?: string[]
}

export interface Camera {
  id: string
  name: string
  status: 'online' | 'offline' | 'error'
  stream_url: string
}

export interface Order {
  id: number
  customer_name: string
  total_amount: number
  status: string
  created_at: string
}
