<template>
  <div class="dispatch-container">
    <div class="header">
      <h2>Màn hình Điều phối Bếp/Pha chế</h2>
      <div class="stats">
        Đang chờ: {{ orders.filter(o => o.status === 'paid').length }} | 
        Đang làm: {{ orders.filter(o => o.status === 'preparing').length }}
      </div>
    </div>

    <!-- Danh sách đơn hàng -->
    <div class="grid">
      <div 
        v-for="order in sortedOrders" 
        :key="order.id" 
        class="order-card"
        :class="{ 'is-preparing': order.status === 'preparing' }"
      >
        <div class="card-header">
          <h3>Đơn #{{ order.id }}</h3>
          <span class="timer" :class="{'warning': getWaitMinutes(order) > 5, 'danger': getWaitMinutes(order) > 10}">
            ⏱ {{ getWaitMinutes(order) }} phút
          </span>
        </div>
        
        <div v-if="order.status === 'preparing' && order.preparer" class="preparer-info">
          🧑‍🍳 Người thực hiện: <strong>{{ order.preparer.name }}</strong>
        </div>
        <div v-if="order.customer_name" class="customer-name">
          Khách: {{ order.customer_name }}
        </div>

        <ul class="item-list">
          <li v-for="item in order.items" :key="item.id">
            <span class="qty">{{ item.quantity }}x</span> 
            <span class="name">{{ item.product?.name || 'Món' }}</span>
          </li>
        </ul>

        <div class="card-actions">
          <button 
            v-if="order.status === 'paid'" 
            @click="updateStatus(order.id, 'preparing')"
            class="btn-start"
            :disabled="loadingOrders.has(order.id)"
          >
            <span v-if="loadingOrders.has(order.id)">Đang xử lý...</span>
            <span v-else>Bắt đầu chuẩn bị</span>
          </button>
          
          <button 
            v-if="order.status === 'preparing'" 
            @click="updateStatus(order.id, 'completed')"
            class="btn-complete"
            :disabled="loadingOrders.has(order.id)"
          >
            <span v-if="loadingOrders.has(order.id)">Đang xử lý...</span>
            <span v-else>Hoàn thành &amp; Giao khách</span>
          </button>
        </div>
      </div>
      
      <div v-if="orders.length === 0" class="empty-state">
        <p>🎉 Tuyệt vời! Hiện tại không có đơn hàng nào chờ.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { apiClient } from '@/services/axios';
import echo from '@/services/echo';

interface Order {
  id: number;
  status: string;
  created_at: string;
  customer_name?: string;
  preparer?: {
    id: number;
    name: string;
  };
  items: any[];
}

const orders = ref<Order[]>([]);
const now = ref(Date.now());
const loadingOrders = ref<Set<number>>(new Set());
let timerInterval: any = null;

// Lấy danh sách ban đầu
const fetchOrders = async () => {
  try {
    const res = await apiClient.get('/api/dispatch');
    if (res.data.success) {
      orders.value = res.data.data;
    }
  } catch (error) {
    console.error('Failed to fetch dispatch orders', error);
  }
};

// Cập nhật trạng thái
const updateStatus = async (id: number, newStatus: string) => {
  // Chặn double-click
  if (loadingOrders.value.has(id)) return;
  loadingOrders.value = new Set([...loadingOrders.value, id]);

  // Optimistic update: đổi UI ngay lập tức trước khi API trả về
  const idx = orders.value.findIndex(o => o.id === id);
  const prevStatus = idx !== -1 ? orders.value[idx].status : null;
  if (idx !== -1) {
    orders.value[idx] = { ...orders.value[idx], status: newStatus };
  }

  try {
    const res = await apiClient.put(`/api/dispatch/${id}/status`, { status: newStatus });
    if (res.data.success) {
      if (newStatus === 'completed') {
        orders.value = orders.value.filter(o => o.id !== id);
      } else {
        // Ghi đè với dữ liệu đầy đủ từ server (bao gồm preparer)
        const updatedIdx = orders.value.findIndex(o => o.id === id);
        if (updatedIdx !== -1) {
          orders.value[updatedIdx] = res.data.data;
        }
      }
    }
  } catch (error: any) {
    console.error('Failed to update status', error);
    // Rollback optimistic update nếu thất bại
    if (idx !== -1 && prevStatus) {
      const rollbackIdx = orders.value.findIndex(o => o.id === id);
      if (rollbackIdx !== -1) {
        orders.value[rollbackIdx] = { ...orders.value[rollbackIdx], status: prevStatus };
      }
    }
  } finally {
    loadingOrders.value = new Set([...loadingOrders.value].filter(x => x !== id));
  }
};

// Chuông báo từ worker nội bộ
const playSound = async () => {
  try {
    await fetch('http://localhost:8001/api/sound/notification');
  } catch (e) {
    console.warn("Could not play sound via Native Worker", e);
  }
};

// Helper tính phút đợi
const getWaitMinutes = (order: Order) => {
  const orderTime = new Date(order.created_at).getTime();
  const diffMs = now.value - orderTime;
  return Math.max(0, Math.floor(diffMs / 60000));
};

// Sắp xếp đơn (cũ nhất lên trước)
const sortedOrders = computed(() => {
  return [...orders.value].sort((a, b) => {
    return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
  });
});

onMounted(() => {
  fetchOrders();

  // Tick mỗi 30s để cập nhật timer trên UI
  timerInterval = setInterval(() => {
    now.value = Date.now();
  }, 30000);

  // Lắng nghe WebSocket
  if (echo) {
    // Lắng nghe đơn mới hoàn thành thanh toán
    echo.channel('orders')
      .listen('PaymentReceived', (e: any) => {
        if (e.order) {
          // Thêm đơn hàng nếu chưa có
          if (!orders.value.find(o => o.id === e.order.id)) {
            // Cần fetch lại hoặc append
            fetchOrders(); 
            playSound(); // Kêu tít tít báo có đơn
          }
        }
      });

    // Lắng nghe nhân viên khác cập nhật
    echo.channel('orders.dispatch')
      .listen('OrderDispatchUpdated', (e: any) => {
        if (e.order) {
          if (e.order.status === 'completed') {
            orders.value = orders.value.filter(o => o.id !== e.order.id);
          } else {
            const idx = orders.value.findIndex(o => o.id === e.order.id);
            if (idx !== -1) {
              orders.value[idx] = e.order;
            } else {
              orders.value.push(e.order);
            }
          }
        }
      });
  }
});

onUnmounted(() => {
  clearInterval(timerInterval);
  if (echo) {
    echo.leave('orders.dispatch');
    echo.leave('orders');
  }
});
</script>

<style scoped>
.dispatch-container {
  padding: 20px;
  background: #f8f9fa;
  min-height: 100vh;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0;
}

.stats {
  font-size: 16px;
  font-weight: 600;
  color: #4a5568;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.order-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  border-top: 4px solid #3b82f6; /* Blue cho paid */
  display: flex;
  flex-direction: column;
}

.order-card.is-preparing {
  border-top-color: #f59e0b; /* Yellow/Orange cho preparing */
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: bold;
}

.timer {
  background: #e2e8f0;
  color: #475569;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
}

.timer.warning {
  background: #fef08a;
  color: #854d0e;
}

.timer.danger {
  background: #fecaca;
  color: #991b1b;
}

.preparer-info {
  background: #dbeafe;
  color: #1e3a8a;
  padding: 8px;
  border-radius: 6px;
  margin-bottom: 12px;
  font-size: 14px;
}

.customer-name {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 12px;
}

.item-list {
  list-style: none;
  padding: 0;
  margin: 0 0 16px 0;
  flex-grow: 1;
}

.item-list li {
  display: flex;
  align-items: flex-start;
  margin-bottom: 8px;
  font-size: 15px;
}

.qty {
  font-weight: bold;
  margin-right: 12px;
  color: #0f172a;
  min-width: 25px;
}

.name {
  color: #334155;
  flex-grow: 1;
}

.card-actions button {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 15px;
}

.card-actions button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-start {
  background: #3b82f6;
  color: white;
}
.btn-start:hover {
  background: #2563eb;
}

.btn-complete {
  background: #10b981;
  color: white;
}
.btn-complete:hover {
  background: #059669;
}

.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px;
  color: #64748b;
  font-size: 18px;
  background: white;
  border-radius: 12px;
}
</style>
