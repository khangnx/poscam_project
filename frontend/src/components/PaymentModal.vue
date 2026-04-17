<template>
  <el-dialog
    v-model="visible"
    title="Thanh toán VietQR"
    width="450px"
    :close-on-click-modal="false"
    :close-on-press-escape="false"
    class="payment-dialog"
    destroy-on-close
    @closed="handleClosed"
  >
    <div class="payment-content" v-loading="loading">
      <div v-if="paymentData" class="qr-section">
        <div class="qr-container">
          <img :src="paymentData.qrCode" alt="VietQR" class="qr-image" />
          <div class="qr-overlay" v-if="orderCompleted">
            <el-icon class="success-icon"><CircleCheckFilled /></el-icon>
            <span>Thành công!</span>
          </div>
        </div>
        
        <div class="payment-details">
          <div class="detail-row">
            <span class="label">Số tiền:</span>
            <span class="value highlighting">{{ formatCurrency(paymentData.amount) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Nội dung:</span>
            <span class="value">{{ paymentData.description }}</span>
          </div>
        </div>

        <div class="status-indicator" :class="{ 'success': orderCompleted }">
          <el-icon v-if="!orderCompleted" class="is-loading"><Loading /></el-icon>
          <span>{{ orderCompleted ? 'Đã nhận thanh toán!' : 'Đang chờ khách quét mã...' }}</span>
        </div>
      </div>

      <div v-else-if="error" class="error-section">
        <el-result icon="error" title="Lỗi khởi tạo" :sub-title="error">
          <template #extra>
            <el-button type="primary" @click="initPayment">Thử lại</el-button>
          </template>
        </el-result>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button 
          v-if="isDev"
          type="danger" 
          plain
          :loading="simulating" 
          :disabled="orderCompleted"
          @click="simulatePayment"
          style="margin-right: auto"
        >
          Giả lập (Dev)
        </el-button>
        <el-button @click="visible = false" :disabled="orderCompleted">Hủy bỏ</el-button>
        <el-button 
          type="warning" 
          :loading="checking" 
          :disabled="orderCompleted"
          @click="manualCheck"
        >
          Kiểm tra lại
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { Loading, CircleCheckFilled } from '@element-plus/icons-vue'
import { ElMessage, ElNotification } from 'element-plus'
import { apiClient, streamClient } from '@/services/axios'
import echo from '@/services/echo'

const props = defineProps<{
  modelValue: boolean
  orderId: number | null
}>()

const emit = defineEmits(['update:modelValue', 'success'])

const visible = ref(false)
const loading = ref(false)
const checking = ref(false)
const orderCompleted = ref(false)
const paymentData = ref<any>(null)
const error = ref('')

const isDev = ref(import.meta.env.DEV)
const simulating = ref(false)

const simulatePayment = async () => {
  if (!props.orderId || simulating.value) return
  simulating.value = true
  try {
    const { data } = await apiClient.post('/api/test-payment/simulate', {
      order_id: props.orderId,
      amount: paymentData.value?.amount || 0
    })
    if (!data.success) {
      ElMessage.error(data.message || 'Lỗi giả lập thanh toán')
    }
  } catch (err: any) {
    ElMessage.error('Lỗi khi giả lập thanh toán')
  } finally {
    simulating.value = false
  }
}

watch(() => props.modelValue, (val) => {
  visible.value = val
  if (val && props.orderId) {
    initPayment()
    subscribeToOrder()
  }
})

watch(visible, (val) => {
  emit('update:modelValue', val)
  if (!val) {
    unsubscribeFromOrder()
  }
})

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const initPayment = async () => {
  if (!props.orderId) return
  loading.value = true
  error.value = ''
  orderCompleted.value = false
  
  try {
    const { data } = await apiClient.post(`/api/orders/${props.orderId}/payos-link`)
    if (data.success) {
      paymentData.value = data.data
    } else {
      error.value = data.message || 'Không thể tạo link thanh toán'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Lỗi kết nối server PayOS'
  } finally {
    loading.value = false
  }
}

const subscribeToOrder = () => {
  if (!props.orderId) return
  console.log(`Subscribing to order.${props.orderId}`)
  echo.channel(`order.${props.orderId}`)
    .listen('.payment.received', (e: any) => {
      console.log('Payment broadcast received:', e)
      handlePaymentSuccess()
    })
}

const unsubscribeFromOrder = () => {
  if (props.orderId) {
    echo.leaveChannel(`order.${props.orderId}`)
  }
}

const handlePaymentSuccess = async () => {
  orderCompleted.value = true
  ElNotification({
    title: 'Thanh toán thành công!',
    message: `Đơn hàng #${props.orderId} đã được xác nhận. Máy in đang in hóa đơn...`,
    type: 'success',
    duration: 5000
  })
  
  // Audio feedback
  const audio = new Audio('/assets/sounds/success.mp3')
  audio.play().catch(() => {})

  // Tự động gọi Native Worker ở cổng 8001
  try {
    const { data } = await apiClient.get(`/api/orders/${props.orderId}`)
    if (data.success) {
      const order = data.data
      const printPayload = {
        shop_name: 'PosCam Shop',
        address: '123 Fake Street, HCM City',
        greeting: 'Cảm ơn quý khách và hẹn gặp lại!',
        items: order.items.map((i: any) => ({
          name: i.product.name,
          quantity: i.quantity,
          price: i.price_at_purchase
        })),
        total: order.total_amount,
        note: `Thanh toán: Chuyển khoản (VietQR)`
      }
      await streamClient.post('/api/print', printPayload)
    }
  } catch (e) {
    console.error('Lỗi gọi Native Worker in bill:', e)
  }

  setTimeout(() => {
    visible.value = false
    emit('success')
  }, 2000)
}

const manualCheck = async () => {
  if (!props.orderId || checking.value) return
  checking.value = true
  try {
    const { data } = await apiClient.get(`/api/orders/${props.orderId}/check-payment`)
    if (data.success && data.status === 'PAID') {
      handlePaymentSuccess()
    } else {
      ElMessage.info(data.message || 'Chưa nhận được thanh toán')
    }
  } catch (err: any) {
    ElMessage.error('Lỗi khi kiểm tra trạng thái')
  } finally {
    checking.value = false
  }
}

const handleClosed = () => {
  paymentData.value = null
  orderCompleted.value = false
  error.value = ''
}

onUnmounted(() => {
  unsubscribeFromOrder()
})
</script>

<style scoped>
.payment-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px 0;
  min-height: 200px;
}

.qr-section {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.qr-container {
  position: relative;
  padding: 15px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.qr-image {
  width: 280px;
  height: 280px;
  display: block;
}

.qr-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  border-radius: 12px;
  z-index: 5;
}

.success-icon {
  font-size: 60px;
  color: #67c23a;
  margin-bottom: 10px;
}

.qr-overlay span {
  font-weight: bold;
  font-size: 20px;
  color: #1e293b;
}

.payment-details {
  width: 100%;
  background: #f8fafc;
  padding: 16px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.detail-row:last-child {
  margin-bottom: 0;
}

.label {
  color: #64748b;
  font-size: 14px;
}

.value {
  color: #1e293b;
  font-weight: 600;
}

.value.highlighting {
  color: #3b82f6;
  font-size: 18px;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  border-radius: 20px;
  background: #eff6ff;
  color: #2563eb;
  font-weight: 500;
  font-size: 14px;
}

.status-indicator.success {
  background: #f0fdf4;
  color: #16a34a;
}

.payment-dialog :deep(.el-dialog) {
  border-radius: 16px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
}

.payment-dialog :deep(.el-dialog__header) {
  margin-right: 0;
  padding: 20px;
  border-bottom: 1px solid #f1f5f9;
}

.payment-dialog :deep(.el-dialog__body) {
  padding: 24px;
}

.payment-dialog :deep(.el-dialog__footer) {
  padding: 16px 24px 24px;
  border-top: 1px solid #f1f5f9;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
