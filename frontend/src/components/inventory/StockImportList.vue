<template>
  <el-card shadow="never">
    <el-table :data="imports" v-loading="loading" stripe style="width: 100%">
      <el-table-column type="expand">
        <template #default="props">
          <div class="p-4 bg-gray-50">
            <h4 class="font-bold mb-2">Chi tiết phiếu #{{ props.row.id }}</h4>
              <el-table :data="props.row.items" border size="small">
                <el-table-column prop="material.name" label="Nguyên liệu" />
              <el-table-column label="Số lượng nhập" width="150">
                <template #default="scope">
                  {{ scope.row.quantity }} {{ scope.row.purchase_unit }}
                </template>
              </el-table-column>
              <el-table-column label="Quy đổi" width="120">
                <template #default="scope">
                  x {{ scope.row.conversion_factor }}
                </template>
              </el-table-column>
              <el-table-column label="Giá nhập" width="150">
                <template #default="scope">
                  {{ formatCurrency(scope.row.purchase_price) }}
                </template>
              </el-table-column>
              <el-table-column label="Thành tiền" width="150" align="right">
                <template #default="scope">
                  {{ formatCurrency(scope.row.subtotal) }}
                </template>
              </el-table-column>
            </el-table>
          </div>
        </template>
      </el-table-column>
      
      <el-table-column prop="id" label="Mã phiếu" width="100" />
      <el-table-column label="Ngày nhập" width="180">
        <template #default="scope">
          {{ formatDate(scope.row.import_date) }}
        </template>
      </el-table-column>
      <el-table-column label="Nhà cung cấp" min-width="150">
        <template #default="scope">
          {{ scope.row.supplier?.name || 'Vãng lai' }}
        </template>
      </el-table-column>
      <el-table-column label="Tổng tiền" width="150" align="right">
        <template #default="scope">
          <b class="text-primary">{{ formatCurrency(scope.row.total_amount) }}</b>
        </template>
      </el-table-column>
      <el-table-column prop="user.name" label="Người lập" width="120" />
      <el-table-column prop="note" label="Ghi chú" show-overflow-tooltip />
    </el-table>

    <div class="mt-4 flex justify-end">
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="15"
        layout="prev, pager, next"
        :total="total"
        @current-change="fetchImports"
      />
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiClient } from '@/services/axios';
import { format } from 'date-fns';

const imports = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const total = ref(0);

const fetchImports = async () => {
  loading.value = true;
  try {
    const { data } = await apiClient.get(`/api/inventory/imports?page=${currentPage.value}`);
    if (data.success) {
      imports.value = data.data.data;
      total.value = data.data.total;
    }
  } catch (error) {
    console.error('Lỗi tải danh sách nhập kho:', error);
  } finally {
    loading.value = false;
  }
};

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (date: string) => {
  return format(new Date(date), 'dd/MM/yyyy HH:mm');
};

defineExpose({ fetchImports });

onMounted(() => {
  fetchImports();
});
</script>

<style scoped>
.p-4 { padding: 1rem; }
.bg-gray-50 { background-color: #f9fafb; }
.mb-2 { margin-bottom: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
.text-primary { color: #409EFF; }
.font-bold { font-weight: bold; }
</style>
