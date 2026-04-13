<template>
  <div class="pos-container">
    <el-row :gutter="24">
      <!-- Left side: Product List -->
      <el-col :span="16">
        <div class="products-section">
          <div class="section-header">
            <h2 class="section-title">Thực đơn</h2>
            <div class="search-box">
              <el-input 
                v-model="search" 
                placeholder="Tìm tên món hoặc SKU..." 
                :prefix-icon="Search" 
                clearable 
                class="premium-input"
              />
            </div>
          </div>

          <div class="categories-container">
            <div 
              class="cat-item" 
              :class="{ active: selectedCategory === 'Tất cả' }"
              @click="selectedCategory = 'Tất cả'"
            >
              Tất cả
            </div>
            <div 
              v-for="cat in categories.filter(c => c !== 'Tất cả')" 
              :key="cat"
              class="cat-item"
              :class="{ active: selectedCategory === cat }"
              @click="selectedCategory = cat"
            >
              {{ cat }}
            </div>
          </div>
          
          <el-scrollbar height="calc(100vh - 220px)">
            <div class="product-grid" v-loading="loading">
              <div 
                v-for="product in displayProducts" 
                :key="product.id"
                class="premium-card" 
                :class="{ 
                  'unavailable': product.effectiveAvail <= 0,
                  'low-stock': product.effectiveAvail > 0 && product.effectiveAvail <= 5
                }"
                @click="product.effectiveAvail > 0 && addToCart(product)"
              >
                <div class="card-status" v-if="product.effectiveAvail <= 0">HẾT NGUYÊN LIỆU</div>
                <div class="card-image">
                  <el-image 
                    v-if="product.image_url && !imageErrors[product.id]" 
                    :src="product.image_url" 
                    class="product-img-full" 
                    fit="cover"
                    @error="imageErrors[product.id] = true"
                  >
                    <template #error>
                      <div class="img-placeholder">
                        <el-icon><Picture /></el-icon>
                      </div>
                    </template>
                  </el-image>
                  <div v-else class="img-placeholder">
                    <el-icon><Picture /></el-icon>
                  </div>
                  <span class="sku-badge">{{ product.sku }}</span>
                  <div class="price-overlap">{{ formatCurrency(product.selling_price || product.price) }}</div>
                </div>
                <div class="card-body">
                  <h4 class="product-name">{{ product.name }}</h4>
                  <div class="stock-info">
                    <span class="stock-label">Có thể phục vụ:</span>
                    <span class="stock-value" :class="{ 'danger': product.effectiveAvail <= 0 }">
                      {{ product.effectiveAvail }} món
                    </span>
                  </div>
                </div>
                <div class="card-overlay" v-if="product.effectiveAvail > 0">
                  <el-icon><Plus /></el-icon>
                  <span>Thêm vào giỏ</span>
                </div>
              </div>
            </div>
          </el-scrollbar>
        </div>
      </el-col>

      <!-- Right side: Ordering Cart -->
      <el-col :span="8">
        <div class="cart-section">
          <div class="cart-header">
            <div class="header-left">
              <h3>Giỏ hàng</h3>
              <el-tag type="info" round effect="plain">{{ cartItemsCount }} món</el-tag>
            </div>
            <el-button type="danger" link @click="clearCart" :disabled="cart.length === 0">
              Làm trống
            </el-button>
          </div>

          <div class="cart-content">
            <el-scrollbar height="calc(100vh - 420px)">
              <div v-if="cart.length === 0" class="empty-state">
                <el-icon class="empty-icon"><ShoppingCart /></el-icon>
                <p>Giỏ hàng đang trống</p>
                <span>Chọn món bên trái để bắt đầu</span>
              </div>
              <div v-else class="cart-list">
                <div v-for="(item, index) in cart" :key="item.product.id" class="cart-item-row">
                  <div class="item-info">
                    <div class="item-name">{{ item.product.name }}</div>
                    <div class="item-price">{{ formatCurrency(item.product.selling_price || item.product.price) }}</div>
                  </div>
                  <div class="item-controls">
                    <div class="quantity-picker">
                      <button @click="updateQuantity(index, item.quantity - 1)">-</button>
                      <span>{{ item.quantity }}</span>
                      <button 
                        @click="updateQuantity(index, item.quantity + 1)"
                        :disabled="getRemainingForProduct(item.product) <= 0"
                      >+</button>
                    </div>
                    <el-button 
                      type="danger" 
                      :icon="Delete" 
                      circle 
                      size="small" 
                      @click="removeFromCart(index)" 
                      class="delete-btn" 
                    />
                  </div>
                </div>
              </div>
            </el-scrollbar>
          </div>

          <div class="cart-footer">
            <div class="summary-card">
              <div class="summary-row">
                <span>Tạm tính</span>
                <span>{{ formatCurrency(baseTotal) }}</span>
              </div>
              <div class="summary-row discount" v-if="groupDiscount > 0">
                <span>Giảm giá hạng ({{ selectedCustomer?.group?.discount_percent }}%)</span>
                <span>-{{ formatCurrency(groupDiscount) }}</span>
              </div>
              <div class="summary-row total">
                <span>Tổng cộng</span>
                <span class="total-price">{{ formatCurrency(finalTotal) }}</span>
              </div>
            </div>
            
            <div class="payment-section">
              <label>Thanh toán & Khách hàng</label>
              
              <!-- Customer Search -->
              <div v-if="!selectedCustomer" class="customer-search-wrapper mb-3">
                <el-input 
                  v-model="customerPhone" 
                  placeholder="Nhập SĐT khách hàng..." 
                  :prefix-icon="Search"
                  @keyup.enter="searchCustomer"
                  class="search-input"
                >
                  <template #append>
                    <el-button :icon="Search" @click="searchCustomer" :loading="searchingCustomer" />
                  </template>
                </el-input>
                <el-button 
                  v-if="!customerPhone || lastSearchFailed"
                  type="success"
                  :icon="Plus"
                  @click="openQuickAdd"
                  class="quick-add-btn"
                />
              </div>

              <!-- Selected Customer Card -->
              <div v-else class="selected-customer-card mb-3">
                <div class="customer-main">
                  <div class="cust-info">
                    <span class="cust-name">{{ selectedCustomer.name }}</span>
                    <el-tag size="small" :type="getRankType(selectedCustomer.group?.name)" effect="dark">
                      {{ selectedCustomer.group?.name || 'Thành viên' }}
                    </el-tag>
                  </div>
                  <el-button type="info" link :icon="Delete" @click="deselectCustomer">Bỏ chọn</el-button>
                </div>
                
                <div class="loyalty-info">
                  <div class="info-row">
                    <span>Điểm hiện tại:</span>
                    <strong>{{ selectedCustomer.points }} p</strong>
                  </div>
                  
                  <!-- Rank Up Progress -->
                  <div class="rank-progress" v-if="nextRank">
                    <div class="progress-labels">
                      <span>Tiến trình lên {{ nextRank.name }}</span>
                      <span>{{ selectedCustomer.points }}/{{ nextRank.min_points }}</span>
                    </div>
                    <el-progress 
                      :percentage="Math.min(100, Math.floor((selectedCustomer.points / nextRank.min_points) * 100))" 
                      :show-text="false"
                      stroke-width="8"
                      color="#f59e0b"
                    />
                    <p class="progress-hint">Còn thiếu {{ nextRank.min_points - selectedCustomer.points }} điểm nữa</p>
                  </div>
                </div>

                <!-- Voucher Section -->
                <div class="voucher-section" v-if="availableVoucher">
                  <el-button 
                    :type="useVoucher ? 'danger' : 'warning'" 
                    size="small" 
                    class="w-100" 
                    :class="{ 'reward-blink': isRewardBlinking }"
                    @click="toggleVoucher(); isRewardBlinking = false"
                  >
                    {{ useVoucher ? 'Hủy áp dụng Voucher' : 'Áp dụng Voucher mới nhận (Giảm 3%)' }}
                  </el-button>
                </div>
                <div class="voucher-section" v-else-if="selectedCustomer.group?.min_points > 0">
                   <el-button 
                    type="primary" 
                    plain 
                    size="small" 
                    class="w-100" 
                    @click="launchGame"
                    :loading="launchingGame"
                  >
                    Chơi Game Nhận Ưu Đãi 🎁
                  </el-button>
                </div>

                <div class="history-trigger mt-2">
                  <el-button type="primary" link size="small" @click="historyVisible = true">
                    Xem 5 đơn hàng gần nhất
                  </el-button>
                </div>
              </div>

              <div class="method-grid">
                <div 
                  v-for="method in ['cash', 'transfer', 'card', 'momo']" 
                  :key="method"
                  class="method-item"
                  :class="{ active: paymentMethod === method }"
                  @click="paymentMethod = method"
                >
                  {{ method === 'cash' ? 'Tiền mặt' : method === 'transfer' ? 'CK' : method === 'card' ? 'Thẻ' : 'MoMo' }}
                </div>
              </div>
              
              <el-button 
                type="primary" 
                class="pay-btn" 
                @click="checkout"
                :disabled="cart.length === 0"
                :loading="submitting"
              >
                XÁC NHẬN THANH TOÁN
              </el-button>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- Order History Drawer -->
    <el-drawer
      v-model="historyVisible"
      title="Lịch sử 5 khách hàng gần nhất"
      size="350px"
    >
      <div v-if="selectedCustomer && selectedCustomer.orders?.length" class="history-list">
        <div v-for="order in selectedCustomer.orders" :key="order.id" class="history-order-card">
          <div class="oh-header">
            <span>#{{ order.id }}</span>
            <span>{{ new Date(order.created_at).toLocaleDateString() }}</span>
          </div>
          <div class="oh-items">
            <span v-for="oi in order.items" :key="oi.id">{{ oi.product.name }} x{{ oi.quantity }}</span>
          </div>
          <div class="oh-total">{{ formatCurrency(order.total_amount) }}</div>
        </div>
      </div>
      <div v-else class="empty-history">Chưa có đơn hàng nào</div>
    </el-drawer>
    
    <!-- Quick Add Customer Dialog -->
    <el-dialog
      v-model="quickAddVisible"
      title="Thêm nhanh khách hàng"
      width="400px"
      append-to-body
      @opened="onQuickAddOpened"
    >
      <el-form :model="quickAddForm" label-position="top">
        <el-form-item label="Họ và tên" required :error="quickAddErrors.name?.[0]">
          <el-input 
            ref="nameInputRef"
            v-model="quickAddForm.name" 
            placeholder="Nhập họ và tên..." 
            @keyup.enter="handleQuickConfirm"
          />
        </el-form-item>
        <el-form-item label="Số điện thoại" required :error="quickAddErrors.phone?.[0]">
          <el-input 
            v-model="quickAddForm.phone" 
            placeholder="Nhập số điện thoại..." 
            @keyup.enter="handleQuickConfirm"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="quickAddVisible = false">Hủy (Esc)</el-button>
          <el-button type="primary" :loading="quickAddLoading" @click="handleQuickConfirm">
            Xác nhận (Enter)
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { Search, Delete, Plus, ShoppingCart, Picture } from '@element-plus/icons-vue'
import { ElMessage, ElNotification } from 'element-plus'
import { apiClient } from '@/services/axios'

interface Material {
  id: number
  name: string
  stock: number
  unit: string
}

interface Recipe {
  id: number
  material_id: number
  quantity: number
  material: Material
}

interface Product {
  id: number
  name: string
  sku: string
  selling_price: number
  price: number
  available_quantity: number
  recipes: Recipe[]
  category: {
    id: number
    name: string
  } | null
  status: string
}

interface CartItem {
  product: Product
  quantity: number
}

const products = ref<Product[]>([])
const loading = ref(false)
const search = ref('')
const selectedCategory = ref('Tất cả')
const cart = ref<CartItem[]>([])
const customerPhone = ref('')
const selectedCustomer = ref<any>(null)
const groups = ref<any[]>([])
const searchingCustomer = ref(false)
const historyVisible = ref(false)
const paymentMethod = ref('cash')
const submitting = ref(false)

// Voucher & Game State
const availableVoucher = ref<any>(null)
const useVoucher = ref(false)
const launchingGame = ref(false)

// Quick Add Customer State
const quickAddVisible = ref(false)
const quickAddLoading = ref(false)
const quickAddForm = ref({ name: '', phone: '' })
const quickAddErrors = ref<any>({})
const lastSearchFailed = ref(false)
const nameInputRef = ref<any>(null)
const imageErrors = ref<Record<number, boolean>>({})
const pollingInterval = ref<any>(null)
const lastVoucherId = ref<number | null>(null)
const isRewardBlinking = ref(false)

const categories = computed(() => {
  const cats = new Set(products.value.map(p => p.category?.name).filter(Boolean))
  return ['Tất cả', ...Array.from(cats)]
})

// Material usage tracker for the cart
const materialUsage = computed(() => {
  const usage = new Map<number, number>()
  cart.value.forEach(item => {
    item.product.recipes?.forEach(recipe => {
      const current = usage.get(recipe.material_id) || 0
      usage.set(recipe.material_id, current + (recipe.quantity * item.quantity))
    })
  })
  return usage
})

// Calculate effective availability for any product
const getRemainingForProduct = (product: Product) => {
  if (!product.recipes || product.recipes.length === 0) return 0
  
  let minMore = Infinity
  product.recipes.forEach(recipe => {
    const totalStock = recipe.material?.stock || 0
    const usedInCart = materialUsage.value.get(recipe.material_id) || 0
    const remainingStock = Math.max(0, totalStock - usedInCart)
    const canMakeMore = Math.floor(remainingStock / recipe.quantity)
    if (canMakeMore < minMore) minMore = canMakeMore
  })
  return minMore === Infinity ? 0 : minMore
}

const displayProducts = computed(() => {
  return products.value
    .filter(p => {
      const matchSearch = p.name.toLowerCase().includes(search.value.toLowerCase()) || 
                          p.sku.toLowerCase().includes(search.value.toLowerCase())
      const matchCat = selectedCategory.value === 'Tất cả' || p.category?.name === selectedCategory.value
      return matchSearch && matchCat && p.status === 'active'
    })
    .map(p => ({
      ...p,
      effectiveAvail: getRemainingForProduct(p)
    }))
})

const cartItemsCount = computed(() => cart.value.reduce((sum, item) => sum + item.quantity, 0))

// Pricing Logic
const baseTotal = computed(() => cart.value.reduce((total, item) => total + ((item.product.selling_price || item.product.price) * item.quantity), 0))

const groupDiscount = computed(() => {
  if (!selectedCustomer.value || !selectedCustomer.value.group) return 0
  const percent = selectedCustomer.value.group.discount_percent || 0
  return (baseTotal.value * percent) / 100
})

const voucherDiscount = computed(() => {
  if (useVoucher.value && availableVoucher.value) {
    return (baseTotal.value * availableVoucher.value.discount_rate) / 100
  }
  return 0
})

const finalTotal = computed(() => baseTotal.value - groupDiscount.value - voucherDiscount.value)

const nextRank = computed(() => {
  if (!selectedCustomer.value || !groups.value.length) return null
  const currentPoints = selectedCustomer.value.points || 0
  const sorted = [...groups.value].sort((a, b) => a.min_points - b.min_points)
  return sorted.find(g => g.min_points > currentPoints)
})

const fetchProducts = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get('/api/products', { params: { per_page: 200 } })
    products.value = data.data.data
  } catch (error) {
    ElMessage.error('Không thể tải menu')
  } finally {
    loading.value = false
  }
}

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const addToCart = (product: Product) => {
  const moreAvailable = getRemainingForProduct(product)
  if (moreAvailable <= 0) {
    ElMessage.warning('Đã đạt giới hạn nguyên liệu cho món này!')
    return
  }

  const existing = cart.value.find(item => item.product.id === product.id)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({ product, quantity: 1 })
  }
}

const updateQuantity = (index: number, newQty: number) => {
  if (newQty <= 0) {
    removeFromCart(index)
    return
  }
  
  const item = cart.value[index]
  if (newQty > item.quantity) {
    // Adding more
    const moreAvailable = getRemainingForProduct(item.product)
    if (moreAvailable <= 0) {
      ElMessage.warning('Không đủ nguyên liệu để thêm!')
      return
    }
  }
  item.quantity = newQty
}

const removeFromCart = (idx: number) => cart.value.splice(idx, 1)
const clearCart = () => cart.value = []

const handleImageError = (id: number) => {
  imageErrors.value[id] = true
}

// Customer Logic
const searchCustomer = async () => {
  if (!customerPhone.value) return
  searchingCustomer.value = true
  lastSearchFailed.value = false
  try {
    const { data } = await apiClient.get('/api/customers/search', { params: { phone: customerPhone.value } })
    selectedCustomer.value = data.data
    ElMessage.success(`Đã chọn: ${data.data.name}`)
    await checkVouchers(data.data.id)
  } catch (error: any) {
    lastSearchFailed.value = true
    ElMessage.warning('Không tìm thấy khách hàng với SĐT này')
  } finally {
    searchingCustomer.value = false
  }
}

// Quick Add Handlers
const openQuickAdd = () => {
  quickAddForm.value = {
    name: '',
    phone: customerPhone.value
  }
  quickAddErrors.value = {}
  quickAddVisible.value = true
}

const onQuickAddOpened = () => {
  nextTick(() => {
    nameInputRef.value?.focus()
  })
}

const handleQuickConfirm = async () => {
  if (!quickAddForm.value.name || !quickAddForm.value.phone) {
    ElMessage.warning('Vui lòng nhập đầy đủ thông tin')
    return
  }
  
  quickAddLoading.value = true
  quickAddErrors.value = {}
  
  try {
    const { data } = await apiClient.post('/api/customers', {
      ...quickAddForm.value,
      source: 'pos'
    })
    selectedCustomer.value = data.data
    quickAddVisible.value = false
    customerPhone.value = ''
    ElMessage.success('Đã tạo và chọn khách hàng mới')
    await checkVouchers(data.data.id)
  } catch (error: any) {
    if (error.response?.status === 422) {
      quickAddErrors.value = error.response.data.errors || {}
    } else {
      ElMessage.error(error.response?.data?.message || 'Lỗi khi tạo khách hàng')
    }
  } finally {
    quickAddLoading.value = false
  }
}

// Smart Polling Logic (Replaced WebSockets)
const startSmartPolling = (customerId: number) => {
  stopSmartPolling()
  console.log('Smart Polling started for customer:', customerId, 'Baseline Voucher ID:', lastVoucherId.value)
  
  pollingInterval.value = setInterval(async () => {
    try {
      const { data } = await apiClient.get(`/api/customers/${customerId}/vouchers`)
      const currentVoucher = data.data
      
      // Validation: Only notify if it's a NEW voucher (ID > baseline)
      if (currentVoucher && (!lastVoucherId.value || currentVoucher.id > lastVoucherId.value)) {
        console.log('New Voucher Found via Polling:', currentVoucher)
        availableVoucher.value = currentVoucher
        isRewardBlinking.value = true
        
        // Play sound
        const audio = new Audio('/assets/sounds/ting.mp3')
        audio.play().catch(err => console.log('Audio play failed:', err))
        
        // Show professional notification
        ElNotification({
          title: 'Chúc mừng! 🎁',
          message: `Khách hàng ${selectedCustomer.value?.name || 'thành viên'} vừa nhận được Voucher ${currentVoucher.discount_rate}%!`,
          type: 'success',
          duration: 10000,
          position: 'top-right'
        })
        
        stopSmartPolling()
      }
    } catch (e) {
      console.error('Polling error:', e)
    }
  }, 3000) // Poll every 3 seconds as requested
}

const stopSmartPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

const deselectCustomer = () => {
  stopSmartPolling()
  selectedCustomer.value = null
  customerPhone.value = ''
  lastSearchFailed.value = false
  availableVoucher.value = null
  useVoucher.value = false
  isRewardBlinking.value = false
}

const getRankType = (name: string) => {
  if (!name) return 'info'
  const n = name.toLowerCase()
  if (n.includes('vàng') || n.includes('gold')) return 'warning'
  if (n.includes('kim cương') || n.includes('diamond')) return 'danger'
  if (n.includes('bạc') || n.includes('silver')) return 'primary'
  return 'success'
}

const fetchGroups = async () => {
  try {
    const { data } = await apiClient.get('/api/customer-groups')
    groups.value = data.data
  } catch (error) {}
}

const checkout = async () => {
  if (cart.value.length === 0) return
  submitting.value = true
  try {
    await apiClient.post('/api/orders', {
      customer_id: selectedCustomer.value?.id,
      payment_method: paymentMethod.value,
      voucher_id: useVoucher.value && availableVoucher.value ? availableVoucher.value.id : null,
      items: cart.value.map(i => ({ product_id: i.product.id, quantity: i.quantity }))
    })
    ElMessage.success('Thanh toán thành công!')
    clearCart()
    deselectCustomer()
    fetchProducts()
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || 'Lỗi thanh toán')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchProducts()
  fetchGroups()
})

// Voucher Methods
const checkVouchers = async (customerId: number) => {
  availableVoucher.value = null
  useVoucher.value = false
  try {
    const { data } = await apiClient.get(`/api/customers/${customerId}/vouchers`)
    if (data.data) {
      availableVoucher.value = data.data
    }
  } catch (e) {
    console.error("Lỗi kiểm tra voucher:", e)
  }
}

const toggleVoucher = () => {
  useVoucher.value = !useVoucher.value
}

// Chạy Game
const launchGame = async () => {
  if (!selectedCustomer.value) return
  
  // Reset blinking and capture baseline voucher ID
  isRewardBlinking.value = false
  lastVoucherId.value = availableVoucher.value?.id || 0
  
  launchingGame.value = true
  try {
    // 1. Check eligibility qua Backend chính (Port 80)
    const checkRes = await apiClient.post('/api/vouchers/check-eligibility', {
      phone: selectedCustomer.value.phone
    })
    
    if (checkRes.data.success) {
      // 2. Gọi Game Agent chạy trên máy POS Windows (Port 8001) để bung màn hình Game
      const agentRes = await apiClient.post('http://localhost:8001/api/game/start', {
        phone: selectedCustomer.value.phone,
        customer_id: selectedCustomer.value.id,
        tenant_id: checkRes.data.tenant_id
      })
      if (agentRes.data.success) {
        ElMessage.success('Đã bung cửa sổ Game! Đang theo dõi kết quả...')
        // 3. Start Smart Polling for results
        startSmartPolling(selectedCustomer.value.id)
      } else {
        ElMessage.warning(agentRes.data.message)
      }
    } else {
      ElMessage.warning(checkRes.data.message || 'Không đủ điều kiện chơi')
    }
  } catch (error: any) {
    if (error.code === 'ERR_NETWORK') {
      ElMessage.error('Không kết nối được Game Agent! Hãy chắc chắn bạn đã chạy file game_agent.py trên máy POS.')
    } else {
      ElMessage.error(error.response?.data?.message || 'Phát sinh lỗi kiểm tra điều kiện')
    }
  } finally {
    launchingGame.value = false
  }
}
</script>

<style scoped>
.pos-container {
  padding: 20px;
  background: #f8fafc;
  min-height: calc(100vh - 40px);
}

/* Sections */
.products-section, .cart-section {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  height: calc(100vh - 80px);
  display: flex;
  flex-direction: column;
}

.section-header {
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  font-size: 24px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
}

.premium-input :deep(.el-input__wrapper) {
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  background: #f1f5f9;
}

/* Categories */
.categories-container {
  padding: 0 24px 20px;
  display: flex;
  gap: 12px;
  overflow-x: auto;
}

.cat-item {
  padding: 8px 16px;
  background: #f1f5f9;
  border-radius: 10px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.3s ease;
}

.cat-item.active {
  background: #2563eb;
  color: white;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Product Cards */
.product-grid {
  padding: 0 24px 24px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 20px;
}

.premium-card {
  position: relative;
  background: white;
  border: 1px solid #f1f5f9;
  border-radius: 16px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-card:hover:not(.unavailable) {
  transform: translateY(-5px);
  box-shadow: 0 12px 24px rgba(0,0,0,0.1);
  border-color: #2563eb;
}

.card-image {
  height: 120px;
  background: #f1f5f9;
  position: relative;
  overflow: hidden;
}

.product-img-full {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
  color: #94a3b8;
  font-size: 32px;
}

.sku-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  background: rgba(255,255,255,0.9);
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  color: #64748b;
}

.price-overlap {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background: #1e293b;
  color: white;
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 800;
  font-size: 14px;
}

.card-body {
  padding: 12px;
}

.product-name {
  margin: 0 0 8px;
  font-size: 15px;
  font-weight: 700;
  color: #334155;
}

.stock-info {
  display: flex;
  flex-direction: column;
}

.stock-label {
  font-size: 11px;
  color: #94a3b8;
}

.stock-value {
  font-size: 13px;
  font-weight: 600;
  color: #22c55e;
}

.stock-value.danger {
  color: #ef4444;
}

.card-status {
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,0.8);
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  color: #ef4444;
  backdrop-filter: blur(2px);
}

.card-overlay {
  position: absolute;
  inset: 0;
  background: rgba(37, 99, 235, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  opacity: 0;
  transition: opacity 0.3s ease;
  gap: 8px;
}

.premium-card:hover:not(.unavailable) .card-overlay {
  opacity: 1;
}

.unavailable {
  filter: grayscale(0.8);
  cursor: not-allowed;
}

/* Cart Styles */
.cart-header {
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-left h3 { margin: 0; font-weight: 800; }

.empty-state {
  height: 300px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  text-align: center;
}

.empty-icon { font-size: 48px; margin-bottom: 16px; }

.cart-item-row {
  padding: 16px 24px;
  border-bottom: 1px solid #f8fafc;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.item-name { font-weight: 700; color: #334155; }
.item-price { color: #2563eb; font-weight: 600; font-size: 13px; }

.item-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.quantity-picker {
  display: flex;
  align-items: center;
  background: #f1f5f9;
  border-radius: 8px;
  padding: 4px;
  gap: 10px;
}

.quantity-picker button {
  border: none;
  background: white;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 800;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
}

.quantity-picker button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.quantity-picker span {
  font-weight: 800;
  min-width: 20px;
  text-align: center;
}

/* Cart Footer */
.cart-footer {
  padding: 24px;
  background: #f8fafc;
  border-bottom-right-radius: 16px;
  border-bottom-left-radius: 16px;
}

.summary-card {
  background: white;
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  color: #64748b;
  font-size: 14px;
}

.summary-row.total {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #e2e8f0;
  font-weight: 800;
  color: #1e293b;
  font-size: 18px;
}

.total-price { color: #2563eb; }

.payment-section label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: #64748b;
  margin-bottom: 8px;
}

.method-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 16px;
}

.method-item {
  padding: 8px 4px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  text-align: center;
  cursor: pointer;
  color: #64748b;
  transition: all 0.2s;
}

.method-item.active {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.pay-btn {
  width: 100%;
  height: 52px;
  border-radius: 12px;
  font-weight: 800;
  font-size: 16px;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}

/* CRM Styles */
.selected-customer-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}

.customer-main {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.cust-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cust-name {
  font-weight: 800;
  font-size: 16px;
  color: #1e293b;
}

.loyalty-info {
  border-top: 1px dashed #e2e8f0;
  padding-top: 12px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 6px;
}

.label { color: #64748b; }
.discount-label { color: #f59e0b; font-weight: 800; }

.rank-progress {
  margin-top: 12px;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  margin-bottom: 4px;
}

.progress-hint {
  font-size: 10px;
  color: #94a3b8;
  margin-top: 4px;
}

.history-trigger {
  margin-top: 8px;
  text-align: center;
}

.summary-row.discount {
  color: #f59e0b;
}

.history-order-card {
  background: #f8fafc;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 12px;
}

.oh-header {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #94a3b8;
  margin-bottom: 4px;
}

.oh-items {
  display: flex;
  flex-direction: column;
  font-size: 13px;
  color: #1e293b;
}

.oh-total {
  margin-top: 8px;
  text-align: right;
  font-weight: 800;
  color: #2563eb;
}

.empty-history { text-align: center; color: #94a3b8; padding-top: 40px; }

.mb-3 { margin-bottom: 12px; }

.customer-search-wrapper {
  display: flex;
  gap: 8px;
  align-items: center;
}

.search-input {
  flex: 1;
}

.quick-add-btn {
  height: 40px;
  width: 40px;
  border-radius: 8px;
}

/* UX Animations */
@keyframes reward-pulse {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); background-color: #f59e0b; }
  50% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(34, 197, 94, 0); background-color: #22c55e; }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); background-color: #f59e0b; }
}

.reward-blink {
  animation: reward-pulse 1.5s infinite;
  border: 2px solid white !important;
  font-weight: 800 !important;
}
</style>
