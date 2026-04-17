import { apiClient } from './axios'

export interface ReportStats {
  summary: {
    total_revenue: number;
    gross_profit: number;
    roi: number;
    order_count: number;
  };
  trend: Array<{
    date: string;
    revenue: number;
    profit: number;
  }>;
  categories: Array<{
    name: string;
    value: number;
  }>;
}

export interface ProfitableProduct {
  name: string;
  total_quantity: number;
  total_profit: number;
  avg_roi: number;
}

export interface HourlyDensity {
  hour: string;
  order_count: number;
  revenue: number;
}

export interface StaffPerformance {
  id: number;
  name: string;
  total_completed: number;
  avg_acceptance_time: number;
  avg_processing_time: number;
}

export interface StaffOrderHistory {
  order_id: number;
  total_amount: number;
  customer_name: string;
  items_count: number;
  completed_at: string;
  acceptance_time: number;
  processing_time: number;
}

export const reportService = {
  getStats(startDate?: string, endDate?: string) {
    return apiClient.get<ReportStats>('/api/reports/stats', {
      params: { start_date: startDate, end_date: endDate }
    })
  },

  getTopProfitable(startDate?: string, endDate?: string) {
    return apiClient.get<ProfitableProduct[]>('/api/reports/top-profitable', {
      params: { start_date: startDate, end_date: endDate }
    })
  },

  getHourlyDensity(startDate?: string, endDate?: string) {
    return apiClient.get<HourlyDensity[]>('/api/reports/hourly-density', {
      params: { start_date: startDate, end_date: endDate }
    })
  },

  exportExcel(startDate?: string, endDate?: string) {
    return apiClient.get('/api/reports/export/excel', {
      params: { start_date: startDate, end_date: endDate },
      responseType: 'blob'
    })
  },

  downloadReprint(orderId: number) {
    return apiClient.get(`/api/reports/print/${orderId}`, {
      responseType: 'blob'
    })
  },

  getTrendSuggestions() {
    return apiClient.get<TrendSuggestion[]>('/api/reports/trends')
  },

  addTrendToMenu(id: number) {
    return apiClient.post(`/api/reports/trends/${id}/add-to-menu`)
  },

  getStaffPerformance(startDate?: string, endDate?: string) {
    return apiClient.get<StaffPerformance[]>('/api/reports/staff-performance', {
      params: { start_date: startDate, end_date: endDate }
    })
  },

  getStaffOrderHistory(userId: number) {
    return apiClient.get<StaffOrderHistory[]>(`/api/reports/staff-performance/${userId}/history`)
  }
}

export interface TrendSuggestion {
  id: number;
  item_name: string;
  trend_score: number;
  source_url: string;
  status: 'active' | 'added';
  product_id: number | null;
  recommendation_reason: string;
  created_at: string;
}

