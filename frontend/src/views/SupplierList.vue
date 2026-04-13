<template>
  <div class="supplier-list p-4">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Quản lý Nhà cung cấp</h1>
      <el-button type="primary" :icon="Plus" @click="handleAdd">Thêm Nhà cung cấp</el-button>
    </div>

    <el-card shadow="never">
      <el-table :data="suppliers" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="Tên Nhà cung cấp" min-width="150" />
        <el-table-column prop="contact" label="Liên hệ" width="150" />
        <el-table-column prop="address" label="Địa chỉ" min-width="250" />
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
          layout="total, prev, pager, next"
          @current-change="fetchSuppliers"
        />
      </div>
    </el-card>

    <!-- Dialog Thêm/Sửa -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? 'Sửa Nhà cung cấp' : 'Thêm Nhà cung cấp'"
      width="600px"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
        <el-form-item label="Tên Nhà cung cấp" prop="name">
          <el-input v-model="form.name" placeholder="VD: Công ty ABC" />
        </el-form-item>
        <el-form-item label="Liên hệ (SĐT/Email)" prop="contact">
          <el-input v-model="form.contact" placeholder="Số điện thoại hoặc Email" />
        </el-form-item>
        <el-form-item label="Địa chỉ" prop="address">
          <el-input v-model="form.address" type="textarea" placeholder="Địa chỉ trụ sở" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Hủy</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">Lưu</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { Plus } from '@element-plus/icons-vue';
import { apiClient } from '@/services/axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { FormInstance, FormRules } from 'element-plus';

const loading = ref(false);
const submitting = ref(false);
const suppliers = ref([]);
const total = ref(0);
const currentPage = ref(1);

const dialogVisible = ref(false);
const editingId = ref<number | null>(null);
const formRef = ref<FormInstance>();

const form = reactive({
  name: '',
  contact: '',
  address: '',
});

const rules = reactive<FormRules>({
  name: [{ required: true, message: 'Vui lòng nhập tên nhà cung cấp', trigger: 'blur' }],
});

const fetchSuppliers = async () => {
  loading.value = true;
  try {
    const response = await apiClient.get(`/api/suppliers?page=${currentPage.value}`);
    if (response.data.success) {
      suppliers.value = response.data.data.data;
      total.value = response.data.data.total;
    }
  } catch (error) {
    console.error('Error fetching suppliers:', error);
  } finally {
    loading.value = false;
  }
};

const handleAdd = () => {
  editingId.value = null;
  form.name = '';
  form.contact = '';
  form.address = '';
  dialogVisible.value = true;
};

const handleEdit = (row: any) => {
  editingId.value = row.id;
  form.name = row.name;
  form.contact = row.contact;
  form.address = row.address;
  dialogVisible.value = true;
};

const submitForm = async () => {
  if (!formRef.value) return;
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true;
      try {
        if (editingId.value) {
          await apiClient.put(`/api/suppliers/${editingId.value}`, form);
          ElMessage.success('Cập nhật thành công');
        } else {
          await apiClient.post('/api/suppliers', form);
          ElMessage.success('Thêm thành công');
        }
        dialogVisible.value = false;
        fetchSuppliers();
      } catch (error) {
        ElMessage.error('Có lỗi xảy ra');
      } finally {
        submitting.value = false;
      }
    }
  });
};

const handleDelete = (row: any) => {
  ElMessageBox.confirm(`Bạn có chắc muốn xóa nhà cung cấp ${row.name}?`, 'Cảnh báo', {
    type: 'warning',
    confirmButtonText: 'Xóa',
    confirmButtonClass: 'el-button--danger',
  }).then(async () => {
    try {
      await apiClient.delete(`/api/suppliers/${row.id}`);
      ElMessage.success('Đã xóa');
      fetchSuppliers();
    } catch (error) {
      ElMessage.error('Không thể xóa nhà cung cấp này');
    }
  });
};

onMounted(fetchSuppliers);
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 1rem; }
.mt-4 { margin-top: 1rem; }
.justify-end { justify-content: flex-end; }
</style>
