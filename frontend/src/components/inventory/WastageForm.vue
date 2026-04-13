<template>
  <el-dialog
    v-model="visible"
    title="Phiếu Xuất hủy Nguyên liệu"
    width="700px"
    @closed="$emit('close')"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
      <el-form-item label="Nguyên liệu" prop="material_id">
        <el-select v-model="form.material_id" placeholder="Chọn nguyên liệu" filterable class="w-full">
          <el-option
            v-for="item in materials"
            :key="item.id"
            :label="`${item.name} (Tồn: ${item.stock} ${item.usage_unit})`"
            :value="item.id"
          />
        </el-select>
      </el-form-item>

      <div class="flex gap-4">
        <el-form-item label="Số lượng xuất hủy" prop="quantity" class="flex-1">
          <el-input-number v-model="form.quantity" :min="0.0001" class="w-full" />
          <small class="text-muted" v-if="selectedMaterial">
            Đơn vị: {{ selectedMaterial.usage_unit }}
          </small>
        </el-form-item>
      </div>

      <el-form-item label="Lý do xuất hủy" prop="reason">
        <el-select v-model="form.reason" placeholder="Chọn lý do" class="w-full" allow-create filterable>
          <el-option label="Hết hạn sử dụng" value="Hết hạn sử dụng" />
          <el-option label="Hư hỏng / Đổ vỡ" value="Hư hỏng / Đổ vỡ" />
          <el-option label="Mẫu thử / Test" value="Mẫu thử / Test" />
          <el-option label="Khác" value="Khác" />
        </el-select>
      </el-form-item>

      <el-form-item label="Ghi chú chi tiết" prop="note">
        <el-input v-model="form.note" type="textarea" placeholder="..." />
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="visible = false">Hủy</el-button>
        <el-button type="danger" :loading="submitting" @click="submitForm">
          Xác nhận Xuất hủy
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
  quantity: 0,
  reason: 'Hư hỏng / Đổ vỡ',
  note: '',
});

const rules = {
  material_id: [{ required: true, message: 'Vui lòng chọn nguyên liệu', trigger: 'change' }],
  quantity: [{ required: true, message: 'Nhập số lượng', trigger: 'blur' }],
  reason: [{ required: true, message: 'Chọn hoặc nhập lý do', trigger: 'change' }],
};

const selectedMaterial = computed(() => {
  return materials.value.find((p: any) => p.id === form.material_id);
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
        await apiClient.post('/api/inventory/waste', form);
        ElMessage.success('Ghi nhận xuất hủy thành công');
        emit('success');
        visible.value = false;
        // Reset
        form.material_id = null;
        form.quantity = 0;
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
.flex { display: flex; }
.gap-4 { gap: 1rem; }
.flex-1 { flex: 1; }
.text-muted { color: #909399; font-size: 12px; }
</style>
