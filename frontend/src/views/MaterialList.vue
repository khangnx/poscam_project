<template>
  <div class="material-list p-4">
    <div class="header-section flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Danh mục Nguyên liệu</h1>
      <el-button type="primary" size="large" :icon="Plus" @click="handleAddMaterial">Thêm Nguyên liệu mới</el-button>
    </div>

    <el-card shadow="never">
      <div class="mb-4">
        <el-text class="mx-1" type="info">Quản lý danh mục các loại nguyên liệu sử dụng trong quán. Định nghĩa đơn vị quy đổi và nhà cung cấp.</el-text>
      </div>

      <el-table :data="materials" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="name" label="Tên nguyên liệu" min-width="180" />
        <el-table-column prop="sku" label="Mã SKU" width="120" />
        <el-table-column label="Đơn vị (Nhập / Dùng)" width="180">
          <template #default="scope">
            <span v-if="scope.row.purchase_unit">
              {{ scope.row.purchase_unit }} / <b>{{ scope.row.usage_unit }}</b>
            </span>
            <span v-else class="text-gray">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="category.name" label="Danh mục" width="150" />
        <el-table-column prop="supplier.name" label="Nhà cung cấp" width="180" />
        <el-table-column label="Thao tác" width="120" align="center">
          <template #default="scope">
            <el-button link type="primary" @click="handleEditMaterial(scope.row)">Chi tiết / Sửa</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <MaterialForm
      v-model:visible="showMaterialForm"
      :material="selectedMaterial"
      @saved="fetchMaterials"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Plus } from '@element-plus/icons-vue';
import { apiClient } from '@/services/axios';
import MaterialForm from '@/components/inventory/MaterialForm.vue';

const materials = ref([]);
const loading = ref(false);
const showMaterialForm = ref(false);
const selectedMaterial = ref(null);

const fetchMaterials = async () => {
  loading.value = true;
  try {
    const { data } = await apiClient.get('/api/materials');
    if (data.success) {
      materials.value = data.data.data || data.data;
    }
  } catch (error) {
    console.error('Lỗi tải nguyên liệu:', error);
  } finally {
    loading.value = false;
  }
};

const handleAddMaterial = () => {
  selectedMaterial.value = null;
  showMaterialForm.value = true;
};

const handleEditMaterial = (material: any) => {
  selectedMaterial.value = { ...material };
  showMaterialForm.value = true;
};

onMounted(() => {
  fetchMaterials();
});
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 1rem; }
.text-gray { color: #909399; font-size: 12px; }
.font-bold { font-weight: bold; }
</style>
