<template>
  <el-dialog
    v-model="visible"
    title="Kiểm kho Nguyên liệu"
    width="700px"
    @closed="$emit('close')"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
      <el-form-item label="Nguyên liệu cần kiểm" prop="material_id">
        <el-select v-model="form.material_id" placeholder="Chọn nguyên liệu" filterable class="w-full">
          <el-option
            v-for="item in materials"
            :key="item.id"
            :label="`${item.name} (${item.sku})`"
            :value="item.id"
          />
        </el-select>
      </el-form-item>

      <el-alert v-if="selectedMaterial" :closable="false" type="info" class="mb-4">
        Tồn kho hiện tại trong hệ thống: <b>{{ selectedMaterial.stock }} {{ selectedMaterial.usage_unit }}</b>
      </el-alert>

      <el-form-item label="Số lượng thực tế kiểm đếm" prop="actual_stock">
        <el-input-number v-model="form.actual_stock" :min="0" class="w-full" />
        <small class="text-muted" v-if="selectedMaterial">
          Đơn vị tính: {{ selectedMaterial.usage_unit }}
        </small>
      </el-form-item>

      <el-form-item label="Chênh lệch" v-if="selectedMaterial">
        <el-tag :type="stockDiff >= 0 ? 'success' : 'danger'" effect="dark">
          {{ stockDiff >= 0 ? '+' : '' }}{{ stockDiff }} {{ selectedMaterial.usage_unit }}
        </el-tag>
        <span class="ml-2 text-gray-500">(Thực tế - Hệ thống)</span>
      </el-form-item>

      <el-form-item label="Ghi chú kiểm kho" prop="note">
        <el-input v-model="form.note" type="textarea" placeholder="Lý do chênh lệch (nếu có)..." />
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="visible = false">Hủy</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">
          Xác nhận Điều chỉnh
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { apiClient } from '@/services/axios';
import { ElMessage } from 'element-plus';

const props = defineProps({
  show: Boolean,
});

const emit = defineEmits(['close', 'success']);

const visible = computed({
  get: () => props.show,
  set: (val) => !val && emit('close'),
});

const formRef = ref();
const submitting = ref(false);
const materials = ref([]);

const form = reactive({
  material_id: null,
  actual_stock: 0,
  note: '',
});

const rules = {
  material_id: [{ required: true, message: 'Vui lòng chọn nguyên liệu', trigger: 'change' }],
  actual_stock: [{ required: true, message: 'Nhập số lượng thực tế', trigger: 'blur' }],
};

const selectedMaterial = computed(() => {
  const m = materials.value.find((m: any) => m.id === form.material_id);
  return m;
});

const stockDiff = computed(() => {
  if (!selectedMaterial.value) return 0;
  return Number((form.actual_stock - selectedMaterial.value.stock).toFixed(4));
});

const fetchMaterials = async () => {
  try {
    const { data } = await apiClient.get('/api/materials?all=1');
    materials.value = data.data;
  } catch (error) {}
};

const submitForm = async () => {
  if (!formRef.value) return;
  await formRef.value.validate(async (valid: boolean) => {
    if (valid) {
      submitting.value = true;
      try {
        await apiClient.post('/api/inventory/adjust', form);
        ElMessage.success('Điều chỉnh kho thành công');
        emit('success');
        visible.value = false;
        form.material_id = null;
        form.actual_stock = 0;
      } catch (error: any) {
        ElMessage.error(error.response?.data?.message || 'Lỗi xử lý');
      } finally {
        submitting.value = false;
      }
    }
  });
};

onMounted(() => {
  fetchMaterials();
});
</script>

<style scoped>
.w-full { width: 100%; }
.mb-4 { margin-bottom: 1rem; }
.ml-2 { margin-left: 0.5rem; }
.text-muted { color: #909399; font-size: 12px; }
.text-gray-500 { color: #6b7280; font-size: 12px; }
</style>
