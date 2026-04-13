<template>
  <el-dialog
    v-model="visible"
    title="Phiếu Nhập nguyên liệu"
    width="900px"
    @closed="$emit('close')"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-position="top">
      <div class="header-inputs flex gap-4">
        <el-form-item label="Nhà cung cấp" prop="supplier_id" class="flex-1">
          <el-select v-model="form.supplier_id" placeholder="Chọn nhà cung cấp" filterable class="w-full">
            <el-option
              v-for="item in suppliers"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="Ngày nhập" prop="import_date" class="flex-1">
          <el-date-picker
            v-model="form.import_date"
            type="datetime"
            placeholder="Chọn ngày giờ"
            class="w-full"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </el-form-item>
      </div>

      <el-form-item label="Danh sách mặt hàng">
        <el-table :data="form.items" border style="width: 100%" size="small">
          <el-table-column label="Nguyên liệu" min-width="180">
            <template #default="scope">
              <el-select 
                v-model="scope.row.material_id" 
                placeholder="Chọn NL" 
                filterable 
                @change="(val) => onMaterialChange(scope.row, val)"
                class="w-full"
              >
                <el-option
                  v-for="item in materials"
                  :key="item.id"
                  :label="`${item.name} (${item.sku})`"
                  :value="item.id"
                />
              </el-select>
            </template>
          </el-table-column>
          
          <el-table-column label="SL Nhập" width="120">
            <template #default="scope">
              <el-input-number v-model="scope.row.quantity" :min="0.0001" controls-position="right" class="w-full" />
            </template>
          </el-table-column>

          <el-table-column label="Đơn vị" width="100">
            <template #default="scope">
              <el-input v-model="scope.row.purchase_unit" placeholder="Thùng/Bao" />
            </template>
          </el-table-column>

          <el-table-column label="Quy đổi" width="120">
            <template #default="scope">
              <div class="flex items-center">
                <span>x</span>
                <el-input-number v-model="scope.row.conversion_factor" :min="0.0001" controls-position="right" style="width: 80px; margin-left: 5px" />
              </div>
            </template>
          </el-table-column>

          <el-table-column label="Giá Nhập / ĐV" width="140">
            <template #default="scope">
              <el-input-number v-model="scope.row.purchase_price" :min="0" controls-position="right" class="w-full" />
            </template>
          </el-table-column>

          <el-table-column label="Thành tiền" width="140" align="right">
            <template #default="scope">
              <b>{{ formatNumber(scope.row.quantity * scope.row.purchase_price) }}đ</b>
            </template>
          </el-table-column>

          <el-table-column width="50" align="center">
            <template #default="scope">
              <el-button type="danger" icon="el-icon-delete" circle size="small" @click="removeItem(scope.$index)" />
            </template>
          </el-table-column>
        </el-table>
        
        <div class="mt-2">
          <el-button type="primary" link @click="addItem">+ Thêm dòng</el-button>
        </div>
      </el-form-item>

      <el-form-item label="Ghi chú" prop="note">
        <el-input v-model="form.note" type="textarea" placeholder="Nội dung phiếu nhập..." />
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="flex justify-between items-center">
        <div class="total-summary text-xl font-bold">
          Tổng cộng: <span class="text-primary">{{ formatNumber(totalAmount) }}đ</span>
        </div>
        <div class="dialog-footer">
          <el-button @click="visible = false">Hủy</el-button>
          <el-button type="primary" :loading="submitting" @click="submitForm">
            Hoàn tất Nhập kho
          </el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
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
const suppliers = ref([]);
const materials = ref([]);

const form = reactive({
  supplier_id: null,
  import_date: new Date(),
  items: [
    { material_id: null, quantity: 1, purchase_unit: '', purchase_price: 0, conversion_factor: 1 }
  ],
  note: '',
});

const rules = {
  import_date: [{ required: true, message: 'Vui lòng chọn ngày nhập', trigger: 'change' }],
  items: [{ type: 'array', required: true, message: 'Cần ít nhất 1 mặt hàng', trigger: 'change' }],
};

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => sum + (item.quantity * item.purchase_price), 0);
});

const addItem = () => {
  form.items.push({ material_id: null, quantity: 1, purchase_unit: '', purchase_price: 0, conversion_factor: 1 });
};

const removeItem = (index: number) => {
  form.items.splice(index, 1);
};

const onMaterialChange = (row: any, materialId: any) => {
  const m = materials.value.find((item: any) => item.id === materialId);
  if (m) {
    row.purchase_unit = m.purchase_unit || '';
    row.conversion_factor = m.conversion_factor || 1;
    if (row.purchase_price === 0 && m.cost_price > 0) {
      row.purchase_price = m.cost_price * (m.conversion_factor || 1);
    }
  }
};

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('vi-VN').format(num || 0);
};

const fetchSuppliers = async () => {
  try {
    const { data } = await apiClient.get('/api/suppliers');
    suppliers.value = data.data.data || data.data;
  } catch (error) {}
};

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
      if (form.items.some(i => !i.material_id)) {
        ElMessage.warning('Vui lòng chọn nguyên liệu cho tất cả các dòng');
        return;
      }

      submitting.value = true;
      try {
        await apiClient.post('/api/inventory/imports', form);
        ElMessage.success('Lập phiếu nhập kho thành công!');
        emit('success');
        visible.value = false;
        // Reset form
        form.items = [{ material_id: null, quantity: 1, purchase_unit: '', purchase_price: 0, conversion_factor: 1 }];
        form.note = '';
      } catch (error: any) {
        ElMessage.error(error.response?.data?.message || 'Lỗi nhập kho');
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
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.text-primary { color: #409EFF; }
.text-xl { font-size: 1.25rem; }
.font-bold { font-weight: bold; }
.mt-2 { margin-top: 0.5rem; }
</style>
