<template>
  <div class="asset-list p-4">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Danh sách Tài sản</h1>
      <el-button type="primary" :icon="Plus" @click="handleAdd">Thêm Tài sản</el-button>
    </div>

    <el-card shadow="never">
      <div class="mb-4 flex flex-wrap gap-4">
        <el-input
          v-model="filters.search"
          placeholder="Tìm tên hoặc mã tài sản..."
          style="width: 250px"
          clearable
          @keyup.enter="fetchAssets"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>

        <el-select v-model="filters.category_id" placeholder="Lọc theo danh mục" clearable style="width: 200px">
          <el-option
            v-for="cat in categories"
            :key="cat.id"
            :label="cat.name"
            :value="cat.id"
          />
        </el-select>

        <el-select v-model="filters.status" placeholder="Trạng thái" clearable style="width: 150px">
          <el-option label="Đang sử dụng" value="active" />
          <el-option label="Đang bảo trì" value="maintenance" />
          <el-option label="Đã hỏng" value="broken" />
          <el-option label="Thanh lý" value="liquidated" />
        </el-select>

        <el-button type="primary" :icon="Search" @click="fetchAssets">Lọc</el-button>
        <el-button @click="resetFilters">Reset</el-button>
      </div>

      <el-table :data="assets" v-loading="loading" style="width: 100%">
        <el-table-column prop="code" label="Mã TS" width="120" sortable />
        <el-table-column prop="name" label="Tên Tài sản" min-width="180" />
        <el-table-column label="Danh mục" width="150">
          <template #default="scope">
            <el-tag size="small">{{ scope.row.category?.name || 'N/A' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="quantity" label="SL" width="80" align="center" />
        <el-table-column prop="status" label="Trạng thái" width="130">
          <template #default="scope">
            <el-tag :type="getStatusType(scope.row.status)">
              {{ getStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Thông tin thêm" min-width="200">
          <template #default="scope">
            <div v-if="scope.row.metadata && Object.keys(scope.row.metadata).length" class="text-xs">
              <div v-for="(val, key) in scope.row.metadata" :key="key">
                <strong>{{ key }}:</strong> {{ val }}
              </div>
            </div>
            <span v-else class="text-gray-400">---</span>
          </template>
        </el-table-column>
        <el-table-column label="Thao tác" width="150" align="right">
          <template #default="scope">
            <el-button link type="primary" @click="handleEdit(scope.row)">Sửa</el-button>
            <el-button link type="danger" @click="handleDelete(scope.row)">Xóa</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          :total="total"
          :page-size="15"
          layout="total, prev, pager, next"
          @current-change="fetchAssets"
        />
      </div>
    </el-card>

    <!-- Dialog Thêm/Sửa -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? 'Sửa Tài sản' : 'Thêm Tài sản mới'"
      width="650px"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
        <div class="grid grid-cols-2 gap-4">
          <el-form-item label="Tên Tài sản" prop="name">
            <el-input v-model="form.name" placeholder="VD: Bàn gỗ 1m2" />
          </el-form-item>
          <el-form-item label="Mã Tài sản" prop="code">
            <el-input v-model="form.code" placeholder="VD: TS001" :disabled="!!editingId" />
          </el-form-item>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <el-form-item label="Danh mục" prop="category_id">
            <el-select v-model="form.category_id" placeholder="Chọn danh mục" class="w-full">
              <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="Số lượng" prop="quantity">
            <el-input-number v-model="form.quantity" :min="0" class="w-full" />
          </el-form-item>
          <el-form-item label="Trạng thái" prop="status">
            <el-select v-model="form.status" class="w-full">
              <el-option label="Đang sử dụng" value="active" />
              <el-option label="Đang bảo trì" value="maintenance" />
              <el-option label="Đã hỏng" value="broken" />
              <el-option label="Thanh lý" value="liquidated" />
            </el-select>
          </el-form-item>
        </div>

        <el-divider content-position="left">Thông tin bổ sung (Metadata)</el-divider>
        <div v-for="(item, index) in metadataList" :key="index" class="flex gap-2 mb-2 items-center">
          <el-input v-model="item.key" placeholder="Tên (VD: Hãng sản xuất)" style="width: 40%" />
          <el-input v-model="item.value" placeholder="Giá trị (VD: IKEA)" style="width: 50%" />
          <el-button type="danger" :icon="Delete" circle @click="removeMetadata(index)" />
        </div>
        <el-button link type="primary" :icon="Plus" @click="addMetadata">Thêm trường thông tin</el-button>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Hủy</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">Lưu lại</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { Plus, Search, Delete } from '@element-plus/icons-vue';
import { assetService } from '@/services/assetService';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { FormInstance, FormRules } from 'element-plus';

const loading = ref(false);
const submitting = ref(false);
const assets = ref([]);
const categories = ref([]);
const total = ref(0);
const currentPage = ref(1);

const filters = reactive({
  search: '',
  category_id: null,
  status: '',
});

const dialogVisible = ref(false);
const editingId = ref<number | null>(null);
const formRef = ref<FormInstance>();

const form = reactive({
  name: '',
  code: '',
  category_id: null,
  quantity: 1,
  status: 'active',
});

const metadataList = ref<{ key: string; value: string }[]>([]);

const rules = reactive<FormRules>({
  name: [{ required: true, message: 'Vui lòng nhập tên tài sản', trigger: 'blur' }],
  code: [{ required: true, message: 'Vui lòng nhập mã tài sản', trigger: 'blur' }],
  category_id: [{ required: true, message: 'Vui lòng chọn danh mục', trigger: 'change' }],
  status: [{ required: true, message: 'Vui lòng chọn trạng thái', trigger: 'change' }],
});

const fetchAssets = async () => {
  loading.value = true;
  try {
    const params: any = {
      page: currentPage.value,
    };
    
    // Only append filters if they are not empty
    if (filters.search && filters.search.trim() !== '') {
      params.search = filters.search.trim();
    }
    if (filters.category_id) {
      params.category_id = filters.category_id;
    }
    if (filters.status) {
      params.status = filters.status;
    }

    const { data } = await assetService.getAssets(params);
    if (data.success) {
      assets.value = data.data.data;
      total.value = data.data.total;
    }
  } catch (error) {
    ElMessage.error('Không thể tải danh sách tài sản');
  } finally {
    loading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const { data } = await assetService.getCategories({ all: 1 });
    if (data.success) {
      categories.value = data.data;
    }
  } catch (error) {
    console.error(error);
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.category_id = null;
  filters.status = '';
  currentPage.value = 1;
  fetchAssets();
};

const addMetadata = () => {
  metadataList.value.push({ key: '', value: '' });
};

const removeMetadata = (index: number) => {
  metadataList.value.splice(index, 1);
};

const handleAdd = () => {
  editingId.value = null;
  form.name = '';
  form.code = '';
  form.category_id = null;
  form.quantity = 1;
  form.status = 'active';
  metadataList.value = [];
  dialogVisible.value = true;
};

const handleEdit = (row: any) => {
  editingId.value = row.id;
  form.name = row.name;
  form.code = row.code;
  form.category_id = row.category_id;
  form.quantity = row.quantity;
  form.status = row.status;
  
  // Parse metadata object to list
  metadataList.value = [];
  if (row.metadata) {
    Object.entries(row.metadata).forEach(([key, value]) => {
      metadataList.value.push({ key, value: String(value) });
    });
  }
  
  dialogVisible.value = true;
};

const submitForm = async () => {
  if (!formRef.value) return;
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true;
      
      // Convert list back to object
      const metadata: Record<string, any> = {};
      metadataList.value.forEach(item => {
        if (item.key.trim()) {
          metadata[item.key.trim()] = item.value;
        }
      });

      const payload = { ...form, metadata };

      try {
        if (editingId.value) {
          await assetService.updateAsset(editingId.value, payload);
          ElMessage.success('Cập nhật tài sản thành công');
        } else {
          await assetService.createAsset(payload);
          ElMessage.success('Thêm tài sản thành công');
        }
        dialogVisible.value = false;
        fetchAssets();
      } catch (error: any) {
        ElMessage.error(error.response?.data?.message || 'Có lỗi xảy ra khi lưu');
      } finally {
        submitting.value = false;
      }
    }
  });
};

const handleDelete = (row: any) => {
  ElMessageBox.confirm(`Bạn có chắc muốn xóa tài sản "${row.name}"?`, 'Xác nhận xóa', {
    type: 'warning',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy',
    confirmButtonClass: 'el-button--danger',
  }).then(async () => {
    try {
      await assetService.deleteAsset(row.id);
      ElMessage.success('Đã xóa thành công');
      fetchAssets();
    } catch (error) {
      ElMessage.error('Không thể xóa tài sản');
    }
  });
};

const getStatusType = (status: string) => {
  const types: any = {
    active: 'success',
    maintenance: 'warning',
    broken: 'danger',
    liquidated: 'info',
  };
  return types[status] || 'info';
};

const getStatusLabel = (status: string) => {
  const labels: any = {
    active: 'Đang sử dụng',
    maintenance: 'Bảo trì',
    broken: 'Đã hỏng',
    liquidated: 'Thanh lý',
  };
  return labels[status] || status;
};

onMounted(() => {
  fetchAssets();
  fetchCategories();
});
</script>

<style scoped>
.flex { display: flex; }
.flex-wrap { flex-wrap: wrap; }
.justify-between { justify-content: space-between; }
.justify-end { justify-content: flex-end; }
.grid { display: grid; }
.grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.gap-4 { gap: 1rem; }
.gap-2 { gap: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.items-center { align-items: center; }
.w-full { width: 100%; }
</style>
