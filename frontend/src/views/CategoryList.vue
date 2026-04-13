<template>
  <div class="category-list p-4">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Quản lý Danh mục Sản phẩm</h1>
      <el-button type="primary" :icon="Plus" @click="handleAdd">Thêm Danh mục</el-button>
    </div>

    <el-card shadow="never">
      <div class="mb-4 flex gap-4">
        <el-input
          v-model="searchQuery"
          placeholder="Tìm tên danh mục..."
          style="width: 300px"
          clearable
          @clear="fetchCategories"
          @keyup.enter="fetchCategories"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <el-button type="primary" @click="fetchCategories">Tìm kiếm</el-button>
      </div>

      <el-table :data="categories" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="Tên Danh mục" min-width="150" />
        <el-table-column prop="slug" label="Slug" width="180" />
        <el-table-column prop="description" label="Mô tả" min-width="250" show-overflow-tooltip />
        <el-table-column prop="status" label="Trạng thái" width="120">
          <template #default="scope">
            <el-tag :type="scope.row.status === 'active' ? 'success' : 'info'">
              {{ scope.row.status === 'active' ? 'Hiện' : 'Ẩn' }}
            </el-tag>
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
          @current-change="fetchCategories"
        />
      </div>
    </el-card>

    <!-- Dialog Thêm/Sửa -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? 'Sửa Danh mục' : 'Thêm Danh mục'"
      width="600px"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
        <el-form-item label="Tên Danh mục" prop="name">
          <el-input v-model="form.name" placeholder="VD: Đồ uống, Đồ ăn nhanh..." />
        </el-form-item>
        <el-form-item label="Mô tả" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" placeholder="Mô tả ngắn về danh mục" />
        </el-form-item>
        <el-form-item label="Trạng thái" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio label="active">Hiển thị</el-radio>
            <el-radio label="inactive">Ẩn</el-radio>
          </el-radio-group>
        </el-form-item>
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
import { Plus, Search } from '@element-plus/icons-vue';
import { apiClient } from '@/services/axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { FormInstance, FormRules } from 'element-plus';

const loading = ref(false);
const submitting = ref(false);
const categories = ref([]);
const total = ref(0);
const currentPage = ref(1);
const searchQuery = ref('');

const dialogVisible = ref(false);
const editingId = ref<number | null>(null);
const formRef = ref<FormInstance>();

const form = reactive({
  name: '',
  description: '',
  status: 'active',
});

const rules = reactive<FormRules>({
  name: [{ required: true, message: 'Vui lòng nhập tên danh mục', trigger: 'blur' }],
  status: [{ required: true, message: 'Vui lòng chọn trạng thái', trigger: 'change' }],
});

const fetchCategories = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      search: searchQuery.value,
    };
    const { data } = await apiClient.get('/api/categories', { params });
    if (data.success) {
      categories.value = data.data.data;
      total.value = data.data.total;
    }
  } catch (error) {
    console.error('Error fetching categories:', error);
    ElMessage.error('Không thể tải danh sách danh mục');
  } finally {
    loading.value = false;
  }
};

const handleAdd = () => {
  editingId.value = null;
  form.name = '';
  form.description = '';
  form.status = 'active';
  dialogVisible.value = true;
};

const handleEdit = (row: any) => {
  editingId.value = row.id;
  form.name = row.name;
  form.description = row.description;
  form.status = row.status;
  dialogVisible.value = true;
};

const submitForm = async () => {
  if (!formRef.value) return;
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true;
      try {
        if (editingId.value) {
          await apiClient.put(`/api/categories/${editingId.value}`, form);
          ElMessage.success('Cập nhật danh mục thành công');
        } else {
          await apiClient.post('/api/categories', form);
          ElMessage.success('Thêm danh mục thành công');
        }
        dialogVisible.value = false;
        fetchCategories();
      } catch (error: any) {
        ElMessage.error(error.response?.data?.message || 'Có lỗi xảy ra khi lưu');
      } finally {
        submitting.value = false;
      }
    }
  });
};

const handleDelete = (row: any) => {
  ElMessageBox.confirm(
    `Bạn có chắc muốn xóa danh mục "${row.name}"? Hệ thống sẽ kiểm tra xem có sản phẩm nào thuộc danh mục này không.`,
    'Xác nhận xóa',
    {
      type: 'warning',
      confirmButtonText: 'Xóa',
      cancelButtonText: 'Hủy',
      confirmButtonClass: 'el-button--danger',
    }
  ).then(async () => {
    try {
      const { data } = await apiClient.delete(`/api/categories/${row.id}`);
      if (data.success) {
        ElMessage.success('Đã xóa danh mục thành công');
        fetchCategories();
      }
    } catch (error: any) {
      ElMessage.error(error.response?.data?.message || 'Không thể xóa danh mục này');
    }
  });
};

onMounted(fetchCategories);
</script>

<style scoped>
.flex { display: flex; }
.gap-4 { gap: 1rem; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 1rem; }
.mt-4 { margin-top: 1rem; }
.justify-end { justify-content: flex-end; }
</style>
