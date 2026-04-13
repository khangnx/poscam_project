<template>
  <div class="product-list-container">
    <div class="header-actions">
      <h2>Món ăn & Thực đơn</h2>
      <el-button type="primary" @click="openForm()">
        <el-icon><Plus /></el-icon> Thêm món ăn
      </el-button>
    </div>

    <div class="filter-container">
      <el-input
        v-model="searchQuery"
        placeholder="Tìm theo tên hoặc SKU..."
        clearable
        @keyup.enter="fetchProducts"
        style="width: 250px; margin-right: 15px"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
      </el-input>

      <el-select v-model="categoryFilter" placeholder="Danh mục" clearable style="width: 180px; margin-right: 15px">
        <el-option
          v-for="item in categories"
          :key="item.id"
          :label="item.name"
          :value="item.id"
        />
      </el-select>

      <el-button @click="fetchProducts">Lọc</el-button>
    </div>

    <el-table :data="products" v-loading="loading" style="width: 100%" border>
      <el-table-column label="Hình" width="80" align="center">
        <template #default="{ row }">
          <el-image 
            v-if="row.image_url"
            :src="row.image_url" 
            style="width: 40px; height: 40px; border-radius: 8px"
            :preview-src-list="[row.image_url]"
            preview-teleported
            fit="cover"
          >
            <template #error>
              <div class="image-placeholder-mini">
                <el-icon><Picture /></el-icon>
              </div>
            </template>
          </el-image>
          <div v-else class="image-placeholder-mini">
            <el-icon><Picture /></el-icon>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="sku" label="Mã kho (SKU)" width="120" />
      <el-table-column prop="name" label="Tên sản phẩm" min-width="200" />
      <el-table-column label="Danh mục" width="150">
        <template #default="{ row }">
          <el-tag v-if="row.category" size="small" effect="plain">{{ row.category.name }}</el-tag>
          <span v-else class="text-gray">-</span>
        </template>
      </el-table-column>

      <el-table-column label="Giá Bán / Vốn" width="180">
        <template #default="{ row }">
          <div>
            <div class="text-primary"><b>{{ formatCurrency(row.selling_price || row.price) }}</b></div>
            <div class="text-gray" style="font-size: 11px;">Vốn: {{ formatCurrency(row.cost_price) }}</div>
          </div>
        </template>
      </el-table-column>

      <el-table-column prop="stock_status" label="Tình trạng kho" width="150">
        <template #default="{ row }">
          <el-tag v-if="row.is_out_of_stock" type="danger">Hết nguyên liệu</el-tag>
          <el-tag v-else-if="row.is_low_stock" type="warning">Sắp hết NVL</el-tag>
          <el-tag v-else type="success">Sẵn sàng</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="Trạng thái" width="120">
        <template #default="{ row }">
          <el-switch
            v-model="row.status"
            active-value="active"
            inactive-value="inactive"
            @change="toggleStatus(row)"
          />
        </template>
      </el-table-column>
      <el-table-column label="Hành động" width="150" fixed="right">
        <template #default="{ row }">
          <el-button size="small" type="primary" :icon="Edit" circle @click="openForm(row)" />
          <el-button size="small" type="danger" :icon="Delete" circle @click="deleteProduct(row.id)" />
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-container">
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="15"
        layout="prev, pager, next, total"
        :total="totalItems"
        @current-change="handlePageChange"
      />
    </div>

    <ProductForm
      v-model:visible="formVisible"
      :product="selectedProduct"
      @saved="fetchProducts"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { Plus, Search, Edit, Delete, Picture } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRoute } from 'vue-router'
import { apiClient } from '@/services/axios'
import ProductForm from '@/components/ProductForm.vue'

const route = useRoute()

const products = ref<any[]>([])
const loading = ref(false)
const searchQuery = ref('')
const categoryFilter = ref('')
const currentPage = ref(1)
const totalItems = ref(0)

const formVisible = ref(false)
const selectedProduct = ref(null)
const categories = ref<any[]>([])

const fetchProducts = async () => {
  loading.value = true
  try {
    const params: any = {
      page: currentPage.value
    }
    
    if (searchQuery.value) params.search = searchQuery.value
    if (categoryFilter.value) params.category_id = categoryFilter.value

    const { data } = await apiClient.get('/api/products', { params })
    
    if (data.success) {
      products.value = data.data.data
      totalItems.value = data.data.total
    }
  } catch (error) {
    console.error('Lỗi tải sản phẩm', error)
    ElMessage.error('Không thể tải danh sách sản phẩm')
  } finally {
    loading.value = false
  }
}

// Tự động tải khi thay đổi filter
watch(categoryFilter, () => {
  currentPage.value = 1
  fetchProducts()
})

const fetchCategories = async () => {
  try {
    const { data } = await apiClient.get('/api/categories?all=1')
    categories.value = data.data
  } catch (error) {
    console.error('Lỗi tải danh mục', error)
  }
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  fetchProducts()
}

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const openForm = (product: any = null) => {
  selectedProduct.value = product ? { ...product } : null
  formVisible.value = true
}

const toggleStatus = async (product: any) => {
  try {
    await apiClient.put(`/api/products/${product.id}`, { status: product.status })
    ElMessage.success('Cập nhật trạng thái thành công')
  } catch (error) {
    product.status = product.status === 'active' ? 'inactive' : 'active'
    ElMessage.error('Cập nhật trạng thái thất bại')
  }
}

const deleteProduct = async (id: number) => {
  try {
    await ElMessageBox.confirm('Bạn có chắc chắn muốn xóa sản phẩm này?', 'Xác nhận xóa', {
      confirmButtonText: 'Xóa',
      cancelButtonText: 'Hủy',
      type: 'warning',
    })
    await apiClient.delete(`/api/products/${id}`)
    ElMessage.success('Xóa sản phẩm thành công')
    fetchProducts()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('Xóa sản phẩm thất bại')
    }
  }
}

onMounted(() => {
  fetchProducts()
  fetchCategories()
})
</script>

<style scoped>
.product-list-container {
  padding: 20px;
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
}
.header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.filter-container {
  margin-bottom: 20px;
  display: flex;
  align-items: center;
}
.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.image-placeholder-mini {
  width: 40px;
  height: 40px;
  background-color: #f5f7fa;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #909399;
}
</style>
