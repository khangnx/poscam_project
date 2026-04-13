<template>
  <el-dialog
    v-model="visible"
    title="Lập phiếu Nhập hàng"
    width="700px"
    @closed="$emit('close')"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
      <el-form-item label="Nhà cung cấp" prop="supplier_id">
        <el-select v-model="form.supplier_id" placeholder="Chọn nhà cung cấp" filterable class="w-full">
          <el-option
            v-for="item in suppliers"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="Nguyên liệu" prop="material_id">
        <el-select v-model="form.material_id" placeholder="Chọn nguyên liệu" filterable class="w-full">
          <el-option
            v-for="item in materials"
            :key="item.id"
            :label="`${item.name} (${item.sku})`"
            :value="item.id"
          />
        </el-select>
      </el-form-item>

      <div class="flex gap-4">
        <el-form-item label="Số lượng" prop="quantity" class="flex-1">
          <el-input-number v-model="form.quantity" :min="1" class="w-full" />
        </el-form-item>

        <el-form-item label="Giá nhập mới" prop="cost_price" class="flex-1">
          <el-input-number v-model="form.cost_price" :min="0" class="w-full" />
        </el-form-item>
      </div>

      <el-alert v-if="selectedMaterial" :closable="false" type="info" show-icon class="mb-4">
        <template #title>
          Phân tích giá vốn (WAC)
        </template>
        <div>
          Giá vốn hiện tại: <b>{{ formatNumber(selectedMaterial.cost_price) }}đ</b><br>
          Giá vốn mới (ước tính): <b class="text-primary">{{ formatNumber(estimatedNewCost) }}đ</b>
        </div>
      </el-alert>

      <el-form-item label="Ghi chú" prop="note">
        <el-input v-model="form.note" type="textarea" placeholder="Nội dung nhập hàng..." />
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="visible = false">Hủy</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">
          Xác nhận Nhập kho
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { apiClient } from '@/services/axios';
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
  show: Boolean,
});

const emit = defineEmits(['close', 'success', 'editProduct']);

const visible = computed({
  get: () => props.show,
  set: (val) => !val && emit('close'),
});

const formRef = ref<FormInstance>();
const submitting = ref(false);
const suppliers = ref([]);
const materials = ref([]);

const form = reactive({
  supplier_id: null,
  material_id: null,
  quantity: 1,
  cost_price: 0,
  note: '',
});

const rules = reactive<FormRules>({
  supplier_id: [{ required: true, message: 'Vui lòng chọn nhà cung cấp', trigger: 'change' }],
  material_id: [{ required: true, message: 'Vui lòng chọn nguyên liệu', trigger: 'change' }],
  quantity: [{ required: true, message: 'Vui lòng nhập số lượng', trigger: 'blur' }],
  cost_price: [{ required: true, message: 'Vui lòng nhập giá nhập', trigger: 'blur' }],
});

const selectedMaterial = computed(() => {
  return materials.value.find((p: any) => p.id === form.material_id);
});

const estimatedNewCost = computed(() => {
  if (!selectedMaterial.value) return form.cost_price;
  const currentStock = selectedMaterial.value.stock || 0;
  const currentCost = selectedMaterial.value.cost_price || 0;
  const newQty = form.quantity || 0;
  const newPrice = form.cost_price || 0;
  
  const totalStock = currentStock + newQty;
  if (totalStock <= 0) return newPrice;
  
  return Math.round(((currentStock * currentCost) + (newQty * newPrice)) / totalStock);
});

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('vi-VN').format(num || 0);
};

const fetchSuppliers = async () => {
  try {
    const response = await apiClient.get('/api/suppliers');
    if (response.data.success) {
      suppliers.value = response.data.data.data;
    }
  } catch (error) {
    console.error('Error fetching suppliers:', error);
  }
};

const fetchMaterials = async () => {
  try {
    const response = await apiClient.get('/api/materials?all=1');
    if (response.status === 200) {
      materials.value = response.data.data || response.data;
    }
  } catch (error) {}
};

const submitForm = async () => {
  if (!formRef.value) return;
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true;
      try {
        const response = await apiClient.post('/api/inventory/import', form);
        if (response.data.success) {
          const updatedMaterial = response.data.data;
          const oldCost = selectedMaterial.value?.cost_price || 0;
          const newCost = updatedMaterial.cost_price;

          ElMessage.success('Nhập kho thành công!');
          emit('success');
          visible.value = false;

          if (Math.abs(newCost - oldCost) > 0) {
            ElMessage.info(`Giá vốn trung bình đã cập nhật: ${formatNumber(newCost)}đ`);
          }
        }
      } catch (error: any) {
        const errorMsg = error.response?.data?.message || error.message || 'Có lỗi xảy ra';
        ElMessage.error(errorMsg);
        console.error('Import error:', error);
      } finally {
        submitting.value = false;
      }
    }
  });
};

onMounted(() => {
  fetchSuppliers();
  fetchMaterials();
});
</script>

<style scoped>
.w-full { width: 100%; }
.flex { display: flex; }
.gap-4 { gap: 1rem; }
.flex-1 { flex: 1; }
</style>
