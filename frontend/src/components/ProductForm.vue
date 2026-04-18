<template>
  <el-dialog
    :title="isEdit ? 'Sửa Món Ăn' : 'Thêm Món Ăn'"
    v-model="dialogVisible"
    width="900px"
    @closed="resetForm"
  >
    <el-tabs type="border-card">
      <el-tab-pane label="Thông tin cơ bản">
        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          label-width="120px"
          label-position="right"
        >
          <el-form-item label="Tên SP" prop="name">
            <el-input v-model="form.name" placeholder="VD: Cà phê sữa" />
          </el-form-item>

          <el-form-item label="Mã SP (SKU)" prop="sku">
            <el-input v-model="form.sku" placeholder="VD: CF-001" />
          </el-form-item>

          <el-form-item label="Danh mục" prop="category_id">
            <el-select v-model="form.category_id" placeholder="Chọn danh mục" clearable style="width: 100%">
              <el-option
                v-for="item in categories"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              />
            </el-select>
          </el-form-item>

          <div class="row-flex">
            <el-form-item label="Giá bán" prop="selling_price" class="flex-1">
              <el-input-number v-model="form.selling_price" :min="0" style="width: 100%" />
            </el-form-item>
            <el-form-item label="Giá vốn" class="flex-1 ml-4">
              <el-input-number v-model="form.cost_price" disabled style="width: 100%" />
              <small class="text-info font-bold">Tính từ định mức</small>
            </el-form-item>
          </div>

          <el-form-item label="Lợi nhuận" v-if="form.selling_price > 0">
            <el-tag :type="marginPercent >= 20 ? 'success' : (marginPercent > 0 ? 'warning' : 'danger')" size="large">
              Margin: {{ marginPercent }}% (Lãi: {{ formatNumber(form.selling_price - form.cost_price) }}đ)
            </el-tag>
          </el-form-item>

          <el-form-item label="Hình sản phẩm">
            <div class="image-upload-wrapper">
              <div 
                class="image-preview-box" 
                @click="triggerUpload"
                :class="{ 'has-image': previewUrl || form.image_url }"
              >
                <img 
                  v-if="(previewUrl || form.image_url) && !imageLoadError" 
                  :src="previewUrl || form.image_url" 
                  class="preview-img" 
                  @error="handleImageError"
                />
                <div v-else class="upload-placeholder">
                  <el-icon><Plus /></el-icon>
                  <span>{{ imageLoadError ? 'Lỗi tải ảnh' : 'Tải ảnh lên' }}</span>
                </div>
                <div class="image-overlay" v-if="previewUrl || form.image_url">
                  <el-icon><Edit /></el-icon>
                  <span>Thay đổi</span>
                </div>
              </div>
              <input 
                type="file" 
                ref="fileInput" 
                style="display: none" 
                accept="image/*" 
                @change="onFileChange"
              />
              <div class="upload-tip" v-if="!previewUrl && !form.image_url">
                Hỗ trợ: JPG, PNG, GIF. Tối đa 2MB.
              </div>
              <el-button v-if="previewUrl" type="danger" link size="small" @click.stop="clearSelection" style="margin-top: 5px;">
                Hủy chọn ảnh mới
              </el-button>
            </div>
          </el-form-item>
          
          <el-form-item label="Trạng thái" prop="status">
            <el-switch v-model="form.status" active-value="active" inactive-value="inactive" active-text="Hoạt động" inactive-text="Tạm ngưng" />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <el-tab-pane label="Định mức / Công thức">
        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
          <span>Nguyên liệu cấu thành:</span>
          <el-button type="primary" size="small" @click="addRecipeRow">Thêm nguyên liệu</el-button>
        </div>
        
        <el-table :data="form.recipes" style="width: 100%" border size="small">
          <el-table-column label="Nguyên liệu" min-width="180">
            <template #default="scope">
              <el-select 
                v-model="scope.row.material_id" 
                placeholder="Chọn NL" 
                filterable
                style="width: 100%"
              >
                <el-option
                  v-for="item in allMaterials"
                  :key="item.id"
                  :label="`${item.name} (${formatNumber(item.cost_price)}đ / ${item.usage_unit})`"
                  :value="item.id"
                />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="SL tiêu hao" width="200">
            <template #default="scope">
              <div class="row-flex items-center gap-1">
                <el-input-number v-model="scope.row.quantity" :precision="4" :step="0.1" :min="0.0001" controls-position="right" style="flex: 1" />
                <span class="text-unit" v-if="scope.row.material_id">
                  {{ allMaterials.find(p => p.id === scope.row.material_id)?.usage_unit }}
                </span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Thành tiền" width="140">
            <template #default="scope">
              {{ formatNumber(getRecipeRowCost(scope.row)) }}đ
            </template>
          </el-table-column>
          <el-table-column label="Xóa" width="60">
            <template #default="scope">
              <el-button type="danger" icon="el-icon-delete" size="small" circle @click="removeRecipeRow(scope.$index)" />
            </template>
          </el-table-column>
        </el-table>

        <div style="margin-top: 15px; text-align: right; font-weight: bold;">
          Tổng giá vốn dự tính: {{ formatNumber(calculatedCost) }}đ
        </div>
      </el-tab-pane>
    </el-tabs>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="dialogVisible = false">Hủy</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">
          Lưu lại
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { Plus, Edit } from '@element-plus/icons-vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { apiClient } from '@/services/axios'

const props = defineProps<{
  visible: boolean
  product: any
  forcedType?: string
}>()

const emit = defineEmits(['update:visible', 'saved'])

const dialogVisible = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const isEdit = computed(() => !!props.product?.id)

const formRef = ref<FormInstance>()
const fileInput = ref<HTMLInputElement | null>(null)
const submitting = ref(false)
const categories = ref<any[]>([])
const allMaterials = ref<any[]>([])
const selectedFile = ref<File | null>(null)
const previewUrl = ref<string>('')
const imageLoadError = ref(false)

const form = ref({
  name: '',
  sku: '',
  selling_price: 0,
  cost_price: 0,
  category_id: null as number | null,
  status: 'active',
  image_path: '',
  image_url: '',
  recipes: [] as any[]
})

const calculatedCost = computed(() => {
  if (!form.value.recipes.length) return 0;
  return form.value.recipes.reduce((sum, item) => sum + getRecipeRowCost(item), 0);
})

watch(calculatedCost, (newCost) => {
  form.value.cost_price = newCost;
})

const getRecipeRowCost = (row: any) => {
  if (!row.material_id) return 0;
  const material = allMaterials.value.find(p => p.id === row.material_id);
  return (material?.cost_price || 0) * (row.quantity || 0);
}

const addRecipeRow = () => {
  form.value.recipes.push({
    material_id: null,
    quantity: 1
  })
}

const removeRecipeRow = (index: number) => {
  form.value.recipes.splice(index, 1)
}

const triggerUpload = () => {
  fileInput.value?.click()
}

const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    if (file.size > 2 * 1024 * 1024) {
      ElMessage.error('Ảnh không được vượt quá 2MB')
      return
    }
    selectedFile.value = file
    previewUrl.value = URL.createObjectURL(file)
    imageLoadError.value = false
  }
}

const handleImageError = () => {
  imageLoadError.value = true
}

const clearSelection = () => {
  selectedFile.value = null
  previewUrl.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

const marginPercent = computed(() => {
  if (!form.value.selling_price || form.value.selling_price <= 0) return 0;
  return Math.round(((form.value.selling_price - form.value.cost_price) / form.value.selling_price) * 100);
})

const formatNumber = (num: number) => {
  return new Intl.NumberFormat('vi-VN').format(num || 0);
}

const rules = computed<FormRules>(() => {
  return {
    name: [{ required: true, message: 'Vui lòng nhập tên món', trigger: 'blur' }],
    sku: [{ required: true, message: 'Vui lòng nhập mã', trigger: 'blur' }],
    selling_price: [{ required: true, message: 'Giá bán phải lớn hơn 0', trigger: 'blur', type: 'number', min: 1 }]
  }
})

const fetchCategories = async () => {
  try {
    const { data } = await apiClient.get('/api/categories?all=1')
    categories.value = data.data
  } catch (error) {
    console.error('Lỗi tải danh mục', error)
  }
}

const fetchAllMaterials = async () => {
  try {
    const { data } = await apiClient.get('/api/materials?all=1')
    allMaterials.value = data.data
  } catch (error) {
    console.error('Lỗi tải nguyên liệu', error)
  }
}

onMounted(() => {
  fetchCategories()
  fetchAllMaterials()
})

watch(() => props.product, (newVal) => {
  if (newVal) {
    form.value = {
      name: newVal.name,
      sku: newVal.sku,
      selling_price: Number(newVal.selling_price || newVal.price || 0),
      cost_price: Number(newVal.cost_price || 0),
      category_id: newVal.category_id,
      status: newVal.status,
      image_path: newVal.image_path || '',
      image_url: newVal.image_url || '',
      recipes: newVal.recipes ? newVal.recipes.map((r: any) => ({
        material_id: r.material_id,
        quantity: Number(r.quantity)
      })) : []
    }
  } else {
    resetForm()
  }
}, { deep: true })

const resetForm = () => {
  if (formRef.value) formRef.value.resetFields()
  selectedFile.value = null
  previewUrl.value = ''
  imageLoadError.value = false
  if (fileInput.value) fileInput.value.value = ''
  form.value = {
    name: '',
    sku: '',
    selling_price: 0,
    cost_price: 0,
    category_id: null,
    status: 'active',
    image_path: '',
    image_url: '',
    recipes: []
  }
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        const formData = new FormData()
        formData.append('name', form.value.name)
        formData.append('sku', form.value.sku)
        formData.append('selling_price', String(form.value.selling_price))
        formData.append('status', form.value.status)
        if (form.value.category_id) formData.append('category_id', String(form.value.category_id))
        if (selectedFile.value) {
          formData.append('image', selectedFile.value)
        }
        
        // Append recipes as JSON string or individual items (Laravel handles array in FormData)
        form.value.recipes.forEach((r, idx) => {
          if (r.material_id) {
            formData.append(`recipes[${idx}][material_id]`, String(r.material_id))
            formData.append(`recipes[${idx}][quantity]`, String(r.quantity))
          }
        })

        if (isEdit.value) {
          // Use POST with _method=PUT to support multipart/form-data in Laravel
          formData.append('_method', 'PUT')
          await apiClient.post(`/api/products/${props.product.id}`, formData)
          ElMessage.success('Cập nhật sản phẩm thành công')
        } else {
          await apiClient.post('/api/products', formData)
          ElMessage.success('Thêm sản phẩm thành công')
        }
        emit('saved')
        dialogVisible.value = false
      } catch (error: any) {
        if (error.response?.data?.errors?.sku) {
          ElMessage.error('Mã SKU đã tồn tại')
        } else {
          ElMessage.error('Có lỗi xảy ra khi lưu')
        }
      } finally {
        submitting.value = false
      }
    }
  })
}
</script>

<style scoped>
.row-flex {
  display: flex;
}
.flex-1 {
  flex: 1;
}
.ml-4 {
  margin-left: 1rem;
}
.dialog-footer {
  text-align: right;
}
:deep(.el-form-item__content) {
  display: flex;
}
:deep(.el-input-number) {
  width: 100% !important;
}
.text-muted {
  color: #909399;
  font-size: 11px;
}
.text-info {
  color: #409EFF;
  font-size: 11px;
}
.font-bold {
  font-weight: bold;
}
.text-unit {
  background: #f4f4f5;
  color: #909399;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  white-space: nowrap;
}
.items-center {
  align-items: center;
}
.gap-1 {
  gap: 4px;
}
:deep(.el-tabs--border-card) {
  border: none;
  box-shadow: none;
}
:deep(.el-tabs__content) {
  padding: 15px 0 0 0;
}

/* Image Upload Styling */
.image-upload-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.image-preview-box {
  width: 150px;
  height: 150px;
  border: 2px dashed #dcdfe6;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
  position: relative;
  transition: all 0.3s ease;
  background-color: #f8fafc;
}

.image-preview-box:hover {
  border-color: #409eff;
  background-color: #f0f7ff;
}

.image-preview-box.has-image {
  border-style: solid;
}

.preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #909399;
  gap: 8px;
}

.upload-placeholder el-icon {
  font-size: 24px;
}

.image-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  color: white;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
  gap: 5px;
}

.image-preview-box:hover .image-overlay {
  opacity: 1;
}

.upload-tip {
  font-size: 11px;
  color: #909399;
  margin-top: 4px;
}
</style>
