<template>
  <div class="dashboard-container">
    <div class="header-actions">
      <h2>Tổng quan Kinh doanh</h2>
    </div>

    <!-- Quick Stats -->
    <el-row :gutter="20" class="stat-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card revenue-card">
          <div class="stat-title">Doanh Thu Hôm Nay</div>
          <div class="stat-value text-success">{{ formatCurrency(stats.revenue.today) }}</div>
          <div class="stat-compare mt-2">
            Hôm qua: {{ formatCurrency(stats.revenue.yesterday) }}
            <span :class="stats.revenue.growth >= 0 ? 'text-success' : 'text-danger'">
              ({{ stats.revenue.growth >= 0 ? '+' : '' }}{{ stats.revenue.growth }}%)
            </span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card profit-card">
          <div class="stat-title">Lợi Nhuận Hôm Nay</div>
          <div class="stat-value text-primary">{{ formatCurrency(stats.profit.today) }}</div>
          <div class="stat-compare mt-2">
            Hôm qua: {{ formatCurrency(stats.profit.yesterday) }}
            <span :class="stats.profit.growth >= 0 ? 'text-success' : 'text-danger'">
              ({{ stats.profit.growth >= 0 ? '+' : '' }}{{ stats.profit.growth }}%)
            </span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-title">Đơn Thành Công (Tháng)</div>
          <div class="stat-value text-info">{{ stats.orders.completed }}</div>
          <div class="stat-compare mt-2">
            Tổng đơn: {{ stats.orders.total }} (Hủy: {{ stats.orders.cancelled }})
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-title">Sản phẩm nổi bật (Tháng)</div>
          <div class="stat-value text-warning">
            {{ stats.top_products.length > 0 ? stats.top_products[0].name : 'Chưa có' }}
          </div>
          <div class="stat-compare mt-2" v-if="stats.top_products.length > 0">
            Đã bán: {{ stats.top_products[0].total_sold }}
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Charts and Tables -->
    <el-row :gutter="20" class="mt-4">
      <el-col :span="14">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <div class="card-header">
              <span>Xu Hướng Doanh Thu (7 Ngày Gần Nhất)</span>
            </div>
          </template>
          <v-chart class="chart" :option="chartOption" autoresize />
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <span>Top 5 Sản Phẩm Bán Chạy</span>
            </div>
          </template>
          <el-table :data="stats.top_products" style="width: 100%" size="small">
            <el-table-column prop="name" label="Sản phẩm" />
            <el-table-column prop="price" label="Giá" width="120">
              <template #default="{ row }">{{ formatCurrency(row.price) }}</template>
            </el-table-column>
            <el-table-column prop="total_sold" label="Đã bán" width="80" align="center" />
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-row class="mt-4">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header flex justify-between">
              <span>Đơn Hàng Gần Đây</span>
              <el-button type="primary" link @click="$router.push('/orders')">Xem tất cả</el-button>
            </div>
          </template>
          <el-table :data="stats.recent_orders" style="width: 100%" size="small" border>
            <el-table-column prop="id" label="Mã Đơn" width="80" />
            <el-table-column prop="customer_name" label="Khách hàng">
              <template #default="{ row }">{{ row.customer_name || 'Khách lẻ' }}</template>
            </el-table-column>
            <el-table-column prop="total_amount" label="Tổng tiền" width="120">
              <template #default="{ row }">
                <strong class="text-danger">{{ formatCurrency(row.total_amount) }}</strong>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="Thời gian" width="150" align="center">
              <template #default="{ row }">{{ new Date(row.created_at).toLocaleString('vi-VN') }}</template>
            </el-table-column>
            <el-table-column label="Hành động" width="100" align="center">
              <template #default="{ row }">
                <el-button type="success" size="small" plain @click="printReceipt(row)">In HĐ</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { apiClient } from '@/services/axios'
import { ElMessage } from 'element-plus'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, GridComponent, DatasetComponent } from 'echarts/components'

use([CanvasRenderer, LineChart, TitleComponent, TooltipComponent, GridComponent, DatasetComponent])

interface ProductStat {
  name: string;
  price: number;
  total_sold: number;
}

interface StatsData {
  revenue: { today: number; yesterday: number; growth: number };
  profit: { today: number; yesterday: number; growth: number };
  orders: { completed: number; cancelled: number; total: number };
  top_products: ProductStat[];
  revenue_trend: number[];
  revenue_trend_labels: string[];
  recent_orders: any[];
}

const stats = ref<StatsData>({
  revenue: { today: 0, yesterday: 0, growth: 0 },
  profit: { today: 0, yesterday: 0, growth: 0 },
  orders: { completed: 0, cancelled: 0, total: 0 },
  top_products: [],
  revenue_trend: [],
  revenue_trend_labels: [],
  recent_orders: []
})

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const fetchStats = async () => {
  try {
    const { data } = await apiClient.get('/api/dashboard/stats')
    stats.value = data.data
  } catch (error) {
    ElMessage.error('Không thể tải dữ liệu dashboard')
  }
}

const chartOption = computed(() => {
  return {
    tooltip: { trigger: 'axis' },
    grid: { left: '3%', right: '4%', bottom: '3%' },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: stats.value.revenue_trend_labels
    },
    yAxis: { type: 'value' },
    series: [
      {
        name: 'Doanh thu',
        type: 'line',
        itemStyle: { color: '#409EFF' },
        areaStyle: {
            color: {
                type: 'linear',
                x: 0, y: 0, x2: 0, y2: 1,
                colorStops: [{ offset: 0, color: 'rgba(64,158,255,0.5)' }, { offset: 1, color: 'rgba(64,158,255,0.05)' }]
            }
        },
        smooth: true,
        data: stats.value.revenue_trend
      }
    ]
  }
})

const printReceipt = async (order: any) => {
  try {
    await apiClient.post(`/api/orders/print/${order.id}`)
    ElMessage.success('Đã gửi lệnh in')
  } catch (error) {
    ElMessage.error('Lỗi khi gửi lệnh in')
  }
}

onMounted(() => {
  fetchStats()
})
</script>

<style scoped>
.dashboard-container {
  padding: 20px;
  background-color: #f5f7fa;
  min-height: calc(100vh - 60px);
}
.header-actions { margin-bottom: 20px; }
.mt-4 { margin-top: 20px; }
.mt-2 { margin-top: 8px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }

.stat-card {
  height: 100%;
}
.stat-title {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}
.stat-value {
  font-size: 28px;
  font-weight: bold;
}
.stat-compare {
  font-size: 13px;
  color: #606266;
}

.text-success { color: #67C23A; }
.text-danger { color: #F56C6C; }
.text-primary { color: #409EFF; }
.text-warning { color: #E6A23C; }

.chart-card {
  height: 400px;
}
.chart {
  height: 300px;
  width: 100%;
}
</style>
