<template>
  <el-dialog
    v-model="dialogVisible"
    :title="material ? 'Chỉnh sửa nguyên liệu' : 'Thêm nguyên liệu mới'"
    width="800px"
    @close="handleClose"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="150px" class="material-form">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="Thông tin cơ bản" name="basic">
          <el-form-item label="Tên nguyên liệu" prop="name">
            <el-input v-model="form.name" placeholder="Ví dụ: Cà phê hạt Arabica" />
          </el-form-item>
          
          <el-form-item label="Mã kho (SKU)" prop="sku">
            <el-input v-model="form.sku" placeholder="Ví dụ: CP-ARA-01" />
          </el-form-item>

          <el-form-item label="Danh mục" prop="category_id">
            <el-select v-model="form.category_id" placeholder="Chọn danh mục" clearable style="width: 100%">
              <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
            </el-select>
          </el-form-item>

          <el-form-item label="Nhà cung cấp" prop="supplier_id">
            <el-select v-model="form.supplier_id" placeholder="Chọn NCC" clearable style="width: 100%">
              <el-option v-for="sup in suppliers" :key="sup.id" :label="sup.name" :value="sup.id" />
            </el-select>
          </el-form-item>
        </el-tab-pane>

        <el-tab-pane label="Quy đổi & Tồn kho" name="inventory">
          <div class="unit-conversion-box mb-4">
            <el-row :gutter="20">
              <el-col :span="11">
                <el-form-item label="Đơn vị nhập" prop="purchase_unit" label-width="100px">
                  <el-input v-model="form.purchase_unit" placeholder="Bao, Thùng..." />
                </el-form-item>
              </el-col>
              <el-col :span="2" class="text-center pt-2"> = </el-col>
              <el-col :span="11">
                <el-form-item label="Hệ số quy đổi" prop="conversion_factor" label-width="100px">
                  <el-input-number v-model="form.conversion_factor" :min="0.0001" style="width: 100%" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="Đơn vị dùng" prop="usage_unit">
              <el-input v-model="form.usage_unit" placeholder="g, ml, cái... (Đơn vị nhỏ nhất để tính định mức)" />
              <div class="text-gray mt-1">Lưu ý: 1 {{ form.purchase_unit || 'đơn vị nhập' }} = {{ form.conversion_factor }} {{ form.usage_unit || 'đơn vị dùng' }}</div>
            </el-form-item>
          </div>

          <el-form-item label="Tồn tối thiểu" prop="min_stock">
            <el-input-number v-model="form.min_stock" :min="0" style="width: 100%" />
            <div class="text-gray">Hệ thống sẽ cảnh báo khi tồn kho thấp hơn mức này (tính theo {{ form.usage_unit || 'đơn vị dùng' }}).</div>
          </el-form-item>
        </el-tab-pane>
      </el-tabs>
    </el-form>

    <template #footer>
      <el-button @click="handleClose">Hủy</el-button>
      <el-button type="primary" :loading="loading" @click="handleSubmit">
        {{ material ? 'Cập nhật' : 'Lưu nguyên liệu' }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import { apiClient } from '@/services/axios';
import { ElMessage } from 'element-plus';

const props = defineProps({
  visible: Boolean,
  material: Object
});

const emit = defineEmits(['update:visible', 'saved']);

const dialogVisible = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
});

const formRef = ref();
const activeTab = ref('basic');
const loading = ref(false);
const categories = ref([]);
const suppliers = ref([]);

const form = ref({
  name: '',
  sku: '',
  category_id: null,
  supplier_id: null,
  purchase_unit: '',
  usage_unit: '',
  conversion_factor: 1,
  min_stock: 0,
  status: 'active'
});

const rules = {
  name: [{ required: true, message: 'Vui lòng nhập tên nguyên liệu', trigger: 'blur' }],
  purchase_unit: [{ required: true, message: 'Vui lòng nhập đơn vị nhập', trigger: 'blur' }],
  usage_unit: [{ required: true, message: 'Vui lòng nhập đơn vị dùng', trigger: 'blur' }],
  conversion_factor: [{ required: true, message: 'Nhập hệ số quy đổi', trigger: 'blur' }],
};

watch(() => props.visible, (val) => {
  if (val) {
    if (props.material) {
      form.value = { ...props.material };
    } else {
      resetForm();
    }
    fetchOptions();
  }
});

const resetForm = () => {
  form.value = {
    name: '',
    sku: '',
    category_id: null,
    supplier_id: null,
    purchase_unit: '',
    usage_unit: '',
    conversion_factor: 1,
    min_stock: 0,
    status: 'active'
  };
};

const fetchOptions = async () => {
  try {
    const [catRes, supRes] = await Promise.all([
      apiClient.get('/api/categories?all=1'),
      apiClient.get('/api/suppliers?all=1')
    ]);
    categories.value = catRes.data.data;
    suppliers.value = supRes.data.data.data || supRes.data.data;
  } catch (error) {
    console.error('Lỗi tải dữ liệu mẫu:', error);
  }
};

const handleClose = () => {
  emit('update:visible', false);
};

const handleSubmit = async () => {
  if (!formRef.value) return;
  
  await formRef.value.validate(async (valid: boolean) => {
    if (!valid) return;

    loading.value = true;
    try {
      if (props.material) {
        await apiClient.put(`/api/materials/${props.material.id}`, form.value);
        ElMessage.success('Cập nhật nguyên liệu thành công');
      } else {
        await apiClient.post('/api/materials', form.value);
        ElMessage.success('Thêm nguyên liệu thành công');
      }
      emit('saved');
      handleClose();
    } catch (error: any) {
      ElMessage.error(error.response?.data?.message || 'Có lỗi xảy ra');
    } finally {
      loading.value = false;
    }
  });
};
</script>

<style scoped>
.unit-conversion-box {
  background: #f8f9fb;
  padding: 15px;
  border-radius: 8px;
  border: 1px dashed #dcdfe6;
}
.text-gray { color: #909399; font-size: 12px; line-height: 1.5; }
.text-center { text-align: center; }
.pt-2 { padding-top: 8px; }
.mb-4 { margin-bottom: 20px; }
</style>
