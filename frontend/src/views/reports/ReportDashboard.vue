<template>
  <div class="report-dashboard">
    <div class="header-section">
      <div class="title-area">
        <h1>Trung tâm Báo cáo Chuyên sâu</h1>
        <p class="subtitle">Phân tích doanh thu, lợi nhuận và hiệu quả kinh doanh</p>
      </div>
      <div class="filter-area">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          unlink-panels
          range-separator="Tới"
          start-placeholder="Ngày bắt đầu"
          end-placeholder="Ngày kết thúc"
          :shortcuts="dateShortcuts"
          @change="fetchData"
          format="DD/MM/YYYY"
          value-format="YYYY-MM-DD"
        />
        <el-button type="success" :icon="Download" @click="handleExport" :loading="exportLoading">
          Xuất Excel
        </el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab" class="report-tabs">
      <el-tab-pane label="Báo cáo phân tích" name="analytics">
        <!-- Summary Cards -->
        <el-row :gutter="20" class="summary-cards">
          <el-col :span="6">
            <el-card shadow="hover" class="stat-card">
              <div class="stat-content">
                <div class="stat-label">Tổng Doanh Thu</div>
                <div class="stat-value text-primary">{{ formatCurrency(stats.summary.total_revenue) }}</div>
                <div class="stat-sub">Từ {{ stats.summary.order_count }} đơn hàng</div>
              </div>
              <el-icon class="stat-icon bg-primary-light"><Money /></el-icon>
            </el-card>
          </el-col>
          <el-col :span="6">
            <el-card shadow="hover" class="stat-card">
              <div class="stat-content">
                <div class="stat-label">Tổng Lợi Nhuận</div>
                <div class="stat-value text-success">{{ formatCurrency(stats.summary.gross_profit) }}</div>
                <div class="stat-sub">Thực nhận sau khi trừ vốn</div>
              </div>
              <el-icon class="stat-icon bg-success-light"><TrendCharts /></el-icon>
            </el-card>
          </el-col>
          <el-col :span="6">
            <el-card shadow="hover" class="stat-card">
              <div class="stat-content">
                <div class="stat-label">Tỷ suất sinh lời (ROI)</div>
                <div class="stat-value" :class="getRoiClass(stats.summary.roi)">
                  {{ stats.summary.roi }}%
                </div>
                <div class="stat-sub">Lợi nhuận / Vốn bỏ ra</div>
              </div>
              <el-icon class="stat-icon bg-warning-light"><PieChart /></el-icon>
            </el-card>
          </el-col>
          <el-col :span="6">
            <el-card shadow="hover" class="stat-card">
              <div class="stat-content">
                <div class="stat-label">Lợi nhuận trung bình / Đơn</div>
                <div class="stat-value text-info">
                  {{ formatCurrency(stats.summary.order_count > 0 ? stats.summary.gross_profit / stats.summary.order_count : 0) }}
                </div>
                <div class="stat-sub">Hiệu quả trên mỗi giao dịch</div>
              </div>
              <el-icon class="stat-icon bg-info-light"><Ticket /></el-icon>
            </el-card>
          </el-col>
        </el-row>

        <!-- Charts Row 1: Trend and Categories -->
        <el-row :gutter="20" class="chart-row">
          <el-col :span="16">
            <el-card shadow="hover" class="chart-card main-chart">
              <template #header>
                <div class="card-header">
                  <span><el-icon><Histogram /></el-icon> So sánh Doanh thu vs Lợi nhuận</span>
                </div>
              </template>
              <v-chart class="chart" :option="trendOption" autoresize />
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover" class="chart-card">
              <template #header>
                <div class="card-header">
                  <span><el-icon><PieChart /></el-icon> Tỷ trọng Lợi nhuận Nhóm SP</span>
                </div>
              </template>
              <v-chart class="chart" :option="categoryOption" autoresize />
            </el-card>
          </el-col>
        </el-row>

        <!-- Charts Row 2: Hourly Density and Top Profitable -->
        <el-row :gutter="20" class="chart-row">
          <el-col :span="10">
            <el-card shadow="hover" class="chart-card">
              <template #header>
                <div class="card-header">
                  <span><el-icon><Watch /></el-icon> Mật độ Bán hàng theo Giờ</span>
                </div>
              </template>
              <v-chart class="chart" :option="hourlyOption" autoresize />
            </el-card>
          </el-col>
          <el-col :span="14">
            <el-card shadow="hover" class="table-card">
              <template #header>
                <div class="card-header">
                  <span><el-icon><Star /></el-icon> Top 10 Sản phẩm Lợi nhuận cao nhất</span>
                </div>
              </template>
              <el-table :data="topProducts" style="width: 100%" height="300" stripe>
                <el-table-column prop="name" label="Sản phẩm" />
                <el-table-column prop="total_quantity" label="Đã bán" width="100" align="center" />
                <el-table-column prop="total_profit" label="Tổng Lời (VNĐ)" align="right">
                  <template #default="{ row }">
                    <span class="text-success font-bold">{{ formatCurrency(row.total_profit) }}</span>
                  </template>
                </el-table-column>
                <el-table-column prop="avg_roi" label="ROI trung bình" width="120" align="center">
                  <template #default="{ row }">
                    <el-tag :type="getRoiTagType(row.avg_roi)" effect="plain">
                      {{ Math.round(row.avg_roi) }}%
                    </el-tag>
                  </template>
                </el-table-column>
              </el-table>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <el-tab-pane label="Gợi ý xu hướng" name="trends">
        <div class="trends-container" v-loading="trendsLoading">
          <div class="trends-header">
            <h3><el-icon color="#E6A23C"><Lightning /></el-icon> Xu hướng ẩm thực đột biến (Rising)</h3>
            <p>Dựa trên dữ liệu tìm kiếm thời gian thực tại Việt Nam</p>
          </div>
          
          <el-empty v-if="trends.length === 0" description="Không có xu hướng mới nào hôm nay" />
          
          <el-row :gutter="20">
            <el-col v-for="trend in trends" :key="trend.id" :span="8" class="trend-col">
              <el-card shadow="hover" class="trend-item-card">
                <div class="trend-score-badge" :class="getTrendBadgeClass(trend.trend_score)">
                  {{ trend.trend_score === 100 ? '🔥 BREAKOUT' : '+' + trend.trend_score + '%' }}
                </div>
                <h4 class="trend-title">{{ trend.item_name }}</h4>
                <p class="trend-reason">{{ trend.recommendation_reason }}</p>
                
                <div class="trend-actions">
                  <el-button 
                    type="primary" 
                    plain 
                    size="small" 
                    :icon="Link"
                    @click="viewOnGoogle(trend.source_url)"
                  >
                    Xem chi tiết
                  </el-button>
                  <el-button 
                    :type="trend.status === 'added' ? 'info' : 'success'" 
                    size="small" 
                    :icon="Plus"
                    :disabled="trend.status === 'added' || addingTrendId === trend.id"
                    @click="addToMenu(trend)"
                  >
                    {{ trend.status === 'added' ? 'Đã thêm' : 'Thêm vào Menu' }}
                  </el-button>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <el-tab-pane label="Năng suất nhân viên" name="staff">
        <div class="staff-container" v-loading="staffLoading">
          <el-row :gutter="20" class="chart-row">
            <el-col :span="24">
              <el-card shadow="hover" class="chart-card staff-chart-card">
                <template #header>
                  <div class="card-header">
                    <span><el-icon><Histogram /></el-icon> So sánh tổng đơn hoàn thành giữa các nhân viên</span>
                  </div>
                </template>
                <v-chart class="chart staff-chart" :option="staffOrderOption" autoresize />
              </el-card>
            </el-col>
          </el-row>

          <el-card shadow="hover" class="table-card performance-table-card">
            <template #header>
              <div class="card-header">
                <span><el-icon><UserFilled /></el-icon> Bảng xếp hạng năng suất xử lý đơn</span>
              </div>
            </template>
            <el-table :data="staffStats" style="width: 100%" stripe @row-click="handleStaffClick" class="clickable-table">
              <el-table-column prop="name" label="Nhân viên" min-width="150">
                <template #default="{ row }">
                  <div class="user-info">
                    <el-avatar :size="30" class="user-avatar">{{ row.name.charAt(0) }}</el-avatar>
                    <span class="user-name">{{ row.name }}</span>
                  </div>
                </template>
              </el-table-column>
              <el-table-column prop="total_completed" label="Đơn hoàn thành" width="150" align="center">
                <template #default="{ row }">
                  <el-tag type="success" effect="dark">{{ row.total_completed }} đơn</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="Tốc độ tiếp nhận TB" width="180" align="center">
                <template #default="{ row }">
                  <div class="time-stat">
                    <el-icon><Timer /></el-icon>
                    <span>{{ formatDuration(row.avg_acceptance_time) }}</span>
                  </div>
                </template>
              </el-table-column>
              <el-table-column label="Tốc độ chế biến TB" width="180" align="center">
                <template #default="{ row }">
                  <div class="time-stat">
                    <el-icon><Check /></el-icon>
                    <span>{{ formatDuration(row.avg_processing_time) }}</span>
                  </div>
                </template>
              </el-table-column>
              <el-table-column label="Thao tác" width="120" align="center">
                <template #default>
                  <el-button link type="primary">Xem chi tiết</el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
      </el-tab-pane>

    </el-tabs>

    <!-- Staff Order History Drawer -->
    <el-drawer
      v-model="drawerVisible"
      :title="'Lịch sử 10 đơn gần nhất: ' + selectedStaff?.name"
      size="500px"
      destroy-on-close
    >
      <div v-loading="historyLoading" class="history-container">
        <el-empty v-if="orderHistory.length === 0" description="Chưa có lịch sử xử lý đơn" />
        <el-timeline v-else>
          <el-timeline-item
            v-for="item in orderHistory"
            :key="item.order_id"
            :timestamp="item.completed_at"
            placement="top"
            type="primary"
          >
            <el-card shadow="hover" class="history-item-card">
              <div class="history-header">
                <span class="order-id">Đơn #{{ item.order_id }}</span>
                <span class="order-amount">{{ formatCurrency(item.total_amount) }}</span>
              </div>
              <p class="history-customer">Khách: {{ item.customer_name || 'Khách lẻ' }} ({{ item.items_count }} món)</p>
              <div class="history-times">
                <span class="time-tag">
                  <el-icon><Timer /></el-icon> Tiếp nhận: {{ formatDuration(item.acceptance_time) }}
                </span>
                <span class="time-tag">
                  <el-icon><Check /></el-icon> Chế biến: {{ formatDuration(item.processing_time) }}
                </span>
              </div>
            </el-card>
          </el-timeline-item>
        </el-timeline>
      </div>
    </el-drawer>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { 
  Money, 
  TrendCharts, 
  PieChart, 
  Ticket, 
  Download,
  Histogram,
  Watch,
  Star,
  Lightning,
  Plus,
  Link,
  UserFilled,
  Timer,
  Check
} from '@element-plus/icons-vue'
import { format, subDays, startOfMonth, endOfMonth } from 'date-fns'
import { 
  reportService, 
  type ReportStats, 
  type ProfitableProduct, 
  type HourlyDensity, 
  type TrendSuggestion,
  type StaffPerformance,
  type StaffOrderHistory
} from '@/services/reportService'
import { ElMessage, ElMessageBox } from 'element-plus'

// ECharts setup
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart, LineChart, PieChart as ECPieChart } from 'echarts/charts'
import { 
  TitleComponent, 
  TooltipComponent, 
  LegendComponent, 
  GridComponent, 
  DatasetComponent 
} from 'echarts/components'

use([
  CanvasRenderer, 
  BarChart, 
  LineChart, 
  ECPieChart,
  TitleComponent, 
  TooltipComponent, 
  LegendComponent, 
  GridComponent, 
  DatasetComponent
])

const activeTab = ref('analytics')
const trends = ref<TrendSuggestion[]>([])
const trendsLoading = ref(false)
const addingTrendId = ref<number | null>(null)

const dateRange = ref<[string, string]>([
  format(startOfMonth(new Date()), 'yyyy-MM-dd'),
  format(new Date(), 'yyyy-MM-dd')
])

const dateShortcuts = [
  { text: 'Hôm nay', value: () => [format(new Date(), 'yyyy-MM-dd'), format(new Date(), 'yyyy-MM-dd')] },
  { text: '7 ngày qua', value: () => [format(subDays(new Date(), 6), 'yyyy-MM-dd'), format(new Date(), 'yyyy-MM-dd')] },
  { text: 'Tháng này', value: () => [format(startOfMonth(new Date()), 'yyyy-MM-dd'), format(endOfMonth(new Date()), 'yyyy-MM-dd')] },
  { text: 'Tháng trước', value: () => {
    const start = startOfMonth(subDays(startOfMonth(new Date()), 1))
    const end = endOfMonth(start)
    return [format(start, 'yyyy-MM-dd'), format(end, 'yyyy-MM-dd')]
  }}
]

const stats = ref<ReportStats>({
  summary: { total_revenue: 0, gross_profit: 0, roi: 0, order_count: 0 },
  trend: [],
  categories: []
})

const topProducts = ref<ProfitableProduct[]>([])
const hourlyDensity = ref<HourlyDensity[]>([])
const staffStats = ref<StaffPerformance[]>([])
const orderHistory = ref<StaffOrderHistory[]>([])

const loading = ref(false)
const exportLoading = ref(false)
const staffLoading = ref(false)
const historyLoading = ref(false)
const drawerVisible = ref(false)
const selectedStaff = ref<StaffPerformance | null>(null)

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

const formatDuration = (seconds: number) => {
  const minutes = seconds / 60
  return `${minutes.toFixed(2)} phút`
}

const getRoiClass = (roi: number) => {
  if (roi >= 100) return 'text-success'
  if (roi >= 50) return 'text-primary'
  if (roi > 0) return 'text-warning'
  return 'text-danger'
}

const getRoiTagType = (roi: number) => {
  if (roi >= 100) return 'success'
  if (roi >= 50) return 'primary'
  if (roi > 0) return 'warning'
  return 'danger'
}

const fetchData = async () => {
  if (!dateRange.value) return
  loading.value = true
  fetchTrends()
  fetchStaffPerformance()
  try {
    const [start, end] = dateRange.value
    const [sData, tData, hData] = await Promise.all([
      reportService.getStats(start, end),
      reportService.getTopProfitable(start, end),
      reportService.getHourlyDensity(start, end)
    ])
    
    stats.value = sData.data.data
    topProducts.value = tData.data.data
    hourlyDensity.value = hData.data.data
  } catch (error) {
    ElMessage.error('Lỗi khi tải dữ liệu báo cáo')
  } finally {
    loading.value = false
  }
}

const fetchStaffPerformance = async () => {
  if (!dateRange.value) return
  staffLoading.value = true
  try {
    const [start, end] = dateRange.value
    const res = await reportService.getStaffPerformance(start, end)
    staffStats.value = res.data.data
  } catch (error) {
    console.error('Lỗi tải dữ liệu nhân viên:', error)
  } finally {
    staffLoading.value = false
  }
}

const handleStaffClick = async (row: StaffPerformance) => {
  selectedStaff.value = row
  drawerVisible.value = true
  historyLoading.value = true
  try {
    const res = await reportService.getStaffOrderHistory(row.id)
    orderHistory.value = res.data.data
  } catch (error) {
    console.error('Lỗi tải lịch sử đơn:', error)
  } finally {
    historyLoading.value = false
  }
}

const fetchTrends = async () => {
  trendsLoading.value = true
  try {
    const res = await reportService.getTrendSuggestions()
    trends.value = res.data.data
  } catch (error) {
    console.error('Lỗi tải xu hướng:', error)
  } finally {
    trendsLoading.value = false
  }
}

const viewOnGoogle = (url: string) => {
  window.open(url, '_blank')
}

const addToMenu = async (trend: TrendSuggestion) => {
  try {
    await ElMessageBox.confirm(
      `Bạn muốn tạo bản nháp cho món "${trend.item_name}" trong danh sách sản phẩm?`,
      'Xác nhận',
      { confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy', type: 'success' }
    )
    
    addingTrendId.value = trend.id
    await reportService.addTrendToMenu(trend.id)
    ElMessage.success('Đã thêm bản nháp thành công! Bạn có thể chỉnh sửa trong QL Sản phẩm.')
    
    // Update local state
    trend.status = 'added'
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || 'Có lỗi xảy ra khi thêm vào menu')
    }
  } finally {
    addingTrendId.value = null
  }
}

const getTrendBadgeClass = (score: number) => {
  if (score >= 100) return 'badge-breakout'
  if (score >= 300) return 'badge-high'
  return 'badge-normal'
}


const handleExport = async () => {
  if (!dateRange.value) return
  exportLoading.value = true
  try {
    const [start, end] = dateRange.value
    const response = await reportService.exportExcel(start, end)
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Bao-cao-doanh-thu-${start}-${end}.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    ElMessage.error('Lỗi khi xuất file Excel')
  } finally {
    exportLoading.value = false
  }
}

// Chart Options
const trendOption = computed(() => ({
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
  legend: { bottom: '0', left: 'center', data: ['Doanh thu', 'Lợi nhuận'] },
  grid: { left: '3%', right: '4%', bottom: '45px', containLabel: true },
  xAxis: { type: 'category', data: stats.value.trend.map(t => t.date) },
  yAxis: { type: 'value' },
  series: [
    {
      name: 'Doanh thu',
      type: 'bar',
      data: stats.value.trend.map(t => t.revenue),
      itemStyle: { color: '#409EFF' }
    },
    {
      name: 'Lợi nhuận',
      type: 'bar',
      data: stats.value.trend.map(t => t.profit),
      itemStyle: { color: '#67C23A' }
    }
  ]
}))

const categoryOption = computed(() => ({
  tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
  legend: { bottom: '0', left: 'center' },
  series: [
    {
      name: 'Lợi nhuận',
      type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: false,
      itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
      label: { show: false, position: 'center' },
      emphasis: { label: { show: true, fontSize: '18', fontWeight: 'bold' } },
      labelLine: { show: false },
      data: stats.value.categories
    }
  ]
}))

const hourlyOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  grid: { left: '3%', right: '4%', bottom: '15%', containLabel: true },
  xAxis: { type: 'category', data: hourlyDensity.value.map(h => h.hour) },
  yAxis: { type: 'value' },
  series: [
    {
      name: 'Số đơn hàng',
      type: 'line',
      smooth: true,
      data: hourlyDensity.value.map(h => h.order_count),
      areaStyle: { opacity: 0.1 },
      itemStyle: { color: '#E6A23C' }
    }
  ]
}))

const staffOrderOption = computed(() => ({
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
  grid: { left: '3%', right: '4%', bottom: '5%', containLabel: true },
  xAxis: { type: 'value' },
  yAxis: { type: 'category', data: staffStats.value.map(s => s.name) },
  series: [
    {
      name: 'Đơn hàng hoàn thành',
      type: 'bar',
      data: staffStats.value.map(s => s.total_completed),
      itemStyle: {
        color: (params: any) => {
          const colors = ['#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#909399'];
          return colors[params.dataIndex % colors.length];
        },
        borderRadius: [0, 5, 5, 0]
      },
      label: { show: true, position: 'insideRight' }
    }
  ]
}))

onMounted(fetchData)
</script>

<style scoped>
.report-dashboard {
  padding: 10px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 30px;
}

.title-area h1 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.subtitle {
  margin: 5px 0 0;
  color: #909399;
  font-size: 14px;
}

.filter-area {
  display: flex;
  gap: 15px;
}

.summary-cards {
  margin-bottom: 25px;
}

.stat-card {
  position: relative;
  overflow: hidden;
  height: 120px;
  display: flex;
  align-items: center;
}

.stat-card :deep(.el-card__body) {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 5px;
}

.stat-sub {
  font-size: 12px;
  color: #C0C4CC;
}

.stat-icon {
  font-size: 32px;
  padding: 12px;
  border-radius: 12px;
  margin-left: 10px;
}

.bg-primary-light { background-color: #ecf5ff; color: #409EFF; }
.bg-success-light { background-color: #f0f9eb; color: #67C23A; }
.bg-warning-light { background-color: #fdf6ec; color: #E6A23C; }
.bg-info-light { background-color: #f4f4f5; color: #909399; }

.chart-row {
  margin-bottom: 25px;
}

.chart-card {
  height: 400px;
}

.chart {
  height: 320px;
}

.table-card {
  height: 400px;
}

.text-primary { color: #409EFF; }
.text-success { color: #67C23A; }
.text-warning { color: #E6A23C; }
.text-danger { color: #F56C6C; }
.text-info { color: #909399; }
.font-bold { font-weight: bold; }

.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: bold;
}

/* Trends Styles */
.trends-container {
  padding: 20px 0;
}

.trends-header {
  margin-bottom: 30px;
}

.trends-header h3 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 20px;
}

.trends-header p {
  margin: 5px 0 0;
  color: #909399;
  font-size: 14px;
}

.trend-col {
  margin-bottom: 20px;
}

.trend-item-card {
  position: relative;
  height: 200px;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.trend-item-card:hover {
  transform: translateY(-5px);
  border-color: #409EFF;
}

.trend-score-badge {
  position: absolute;
  top: 15px;
  right: 15px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: bold;
}

.badge-breakout {
  background-color: #fef0f0;
  color: #f56c6c;
}

.badge-high {
  background-color: #fdf6ec;
  color: #e6a23c;
}

.badge-normal {
  background-color: #f0f9eb;
  color: #67c23a;
}

.trend-title {
  margin: 20px 0 10px;
  font-size: 18px;
  color: #303133;
}

.trend-reason {
  font-size: 14px;
  color: #606266;
  line-height: 1.6;
  margin-bottom: 20px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.trend-actions {
  display: flex;
  gap: 10px;
  margin-top: auto;
}

/* Staff Styles */
.staff-container {
  padding: 20px 0;
}

.staff-chart-card {
  height: 350px;
}

.staff-chart {
  height: 280px;
}

.performance-table-card {
  height: auto;
  margin-top: 20px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  background-color: #409EFF;
  color: #fff;
  font-weight: bold;
}

.user-name {
  font-weight: 600;
  color: #303133;
}

.time-stat {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #606266;
  font-size: 14px;
}

.clickable-table :deep(.el-table__row) {
  cursor: pointer;
}

.history-container {
  padding: 10px;
}

.history-item-card {
  margin-bottom: 10px;
  border-radius: 8px;
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.order-id {
  font-weight: bold;
  color: #409EFF;
}

.order-amount {
  font-weight: bold;
  color: #67C23A;
}

.history-customer {
  margin: 5px 0;
  font-size: 13px;
  color: #606266;
}

.history-times {
  display: flex;
  gap: 15px;
  margin-top: 10px;
}

.time-tag {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #909399;
  background-color: #f4f4f5;
  padding: 2px 8px;
  border-radius: 4px;
}

.report-tabs :deep(.el-tabs__item) {
  font-size: 16px;
  font-weight: bold;
  height: 50px;
}
</style>

