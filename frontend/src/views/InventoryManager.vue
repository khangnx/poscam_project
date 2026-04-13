<template>
  <div class="inventory-manager p-4">
      <h1 class="text-2xl font-bold">Quản lý Kho (Nghiệp vụ Nhập/Xuất)</h1>
      <div class="flex gap-2">
        <el-button type="success" size="large" :icon="Download" @click="showFormalImport = true">Nhập hàng</el-button>
        <el-button type="danger" size="large" :icon="BrokenImage" @click="showWasteForm = true">Xuất hủy</el-button>
        <el-button type="warning" size="large" :icon="Edit" @click="showAdjustForm = true">Kiểm kho</el-button>
      </div>

    <!-- Cảnh báo hết hàng ngay đầu trang -->
    <el-alert
      v-if="lowStockMaterials.length > 0"
      title="Cảnh báo: Nguyên liệu sắp hết hàng"
      type="warning"
      effect="dark"
      class="mb-4"
      show-icon
      :closable="false"
    >
      <div class="mt-2">
        Có <b>{{ lowStockMaterials.length }}</b> nguyên liệu đang ở mức báo động tồn kho. 
        <el-button link type="primary" @click="activeTab = 'low-stock'" style="color: #fff; text-decoration: underline">
          Xem danh sách và nhập hàng
        </el-button>
      </div>
    </el-alert>

    <el-tabs v-model="activeTab" class="inventory-tabs">
      <el-tab-pane label="Kho Nguyên liệu" name="ingredients">
        <el-card shadow="never">
          <div class="mb-4">
             <el-text class="mx-1" type="info">Danh sách tồn kho thực tế của các loại nguyên liệu. Các biến động kho phải được thực hiện qua các phiếu nghiệp vụ.</el-text>
          </div>

          <el-table :data="materials" v-loading="loadingMaterials" stripe style="width: 100%">
            <el-table-column prop="name" label="Nguyên liệu" min-width="180" />
            <el-table-column prop="sku" label="Mã kho" width="100" />
            <el-table-column label="Đơn vị (Nhập / Dùng)" width="180">
              <template #default="scope">
                <span v-if="scope.row.purchase_unit">
                  {{ scope.row.purchase_unit }} / <b>{{ scope.row.usage_unit }}</b>
                </span>
                <span v-else class="text-gray">-</span>
              </template>
            </el-table-column>
            <el-table-column label="Giá vốn (Dùng)" width="150" align="right">
              <template #default="scope">
                {{ formatNumber(scope.row.cost_price) }}đ
              </template>
            </el-table-column>
            <el-table-column prop="stock" label="Tồn kho" width="120" align="center">
              <template #default="scope">
                <el-tag :type="scope.row.stock <= scope.row.min_stock ? 'danger' : 'success'">
                  {{ scope.row.stock }} {{ scope.row.usage_unit }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <el-tab-pane label="Phiếu Nhập kho" name="imports">
        <StockImportList ref="importListRef" />
      </el-tab-pane>

      <el-tab-pane label="Lịch sử Biến động" name="history">
        <StockHistory ref="historyRef" />
      </el-tab-pane>

      <el-tab-pane label="Cảnh báo Hết hàng" name="low-stock">
        <el-card shadow="never">
          <el-table :data="lowStockMaterials" v-loading="loadingLowStock" style="width: 100%">
            <el-table-column prop="name" label="Nguyên liệu" />
            <el-table-column prop="sku" label="Mã" width="100" />
            <el-table-column prop="stock" label="Tồn kho" width="120">
              <template #default="scope">
                <span class="text-danger font-bold">{{ scope.row.stock }} {{ scope.row.usage_unit }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="min_stock" label="Tối thiểu" width="120" />
            <el-table-column label="Thao tác" width="150" align="right">
              <template #default="scope">
                <el-button type="primary" size="small" @click="showFormalImport = true">
                  Nhập thêm hàng
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- Forms -->
    <FormalImportForm 
      :show="showFormalImport" 
      @close="showFormalImport = false" 
      @success="onImportSuccess" 
    />

    <WastageForm
      :show="showWasteForm"
      @close="showWasteForm = false"
      @success="onImportSuccess"
    />

    <StockCountForm
      :show="showAdjustForm"
      @close="showAdjustForm = false"
      @success="onImportSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Download, Delete as BrokenImage, Edit } from '@element-plus/icons-vue';
import { apiClient } from '@/services/axios';
import StockHistory from '@/components/inventory/StockHistory.vue';
import FormalImportForm from '@/components/inventory/FormalImportForm.vue';
import WastageForm from '@/components/inventory/WastageForm.vue';
import StockCountForm from '@/components/inventory/StockCountForm.vue';
import StockImportList from '@/components/inventory/StockImportList.vue';

const activeTab = ref('ingredients');
const materials = ref([]);
const lowStockMaterials = ref([]);
const loadingMaterials = ref(false);
const loadingLowStock = ref(false);

const showFormalImport = ref(false);
const showWasteForm = ref(false);
const showAdjustForm = ref(false);

const historyRef = ref();
const importListRef = ref();

const fetchMaterials = async () => {
  loadingMaterials.value = true;
  try {
    const { data } = await apiClient.get('/api/materials');
    if (data.success) {
      materials.value = data.data.data || data.data;
    }
  } catch (error) {
    console.error('Lỗi tải nguyên liệu:', error);
  } finally {
    loadingMaterials.value = false;
  }
};

const fetchLowStock = async () => {
  loadingLowStock.value = true;
  try {
    const response = await apiClient.get('/api/inventory/low-stock');
    if (response.data.success) {
      lowStockMaterials.value = response.data.data.data || response.data.data;
    }
  } finally {
    loadingLowStock.value = false;
  }
};

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('vi-VN').format(num);
};

const onImportSuccess = () => {
  fetchMaterials();
  fetchLowStock();
  if (historyRef.value) {
    historyRef.value.fetchHistory();
  }
};

watch(activeTab, (val) => {
  if (val === 'ingredients') fetchMaterials();
  if (val === 'low-stock') fetchLowStock();
});

onMounted(() => {
  fetchMaterials();
  fetchLowStock();
});
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 1rem; }
.text-danger { color: #f56c6c; }
.text-gray { color: #909399; font-size: 12px; }
.font-bold { font-weight: bold; }
</style>

