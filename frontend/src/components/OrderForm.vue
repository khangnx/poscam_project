<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? 'Edit Order' : 'New Order'"
    width="800px"
    @closed="handleClose"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="200px">
      <el-form-item label="Customer Name" prop="customer_name">
        <el-input v-model="form.customer_name" placeholder="Enter customer name"></el-input>
      </el-form-item>

      <el-form-item label="Product Item" prop="item_name">
        <el-input v-model="form.item_name" placeholder="Enter product name"></el-input>
      </el-form-item>

      <el-form-item label="Total Amount" prop="total_amount">
        <el-input-number v-model="form.total_amount" :min="0" :precision="2" :step="1" style="width: 100%"></el-input-number>
      </el-form-item>

      <el-form-item label="Status" prop="status">
        <el-select v-model="form.status" style="width: 100%">
          <el-option label="Pending" value="pending"></el-option>
          <el-option label="Completed" value="completed"></el-option>
          <el-option label="Cancelled" value="cancelled"></el-option>
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <div class="dialog-footer">
        <el-button @click="visible = false">Cancel</el-button>
        <el-button type="primary" :loading="saving" @click="submitForm">
          Save
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { apiClient } from '@/services/axios'

const props = defineProps<{
  modelValue: boolean
  orderData?: any
}>()

const emit = defineEmits(['update:modelValue', 'saved'])

const visible = ref(props.modelValue)

watch(() => props.modelValue, (val) => {
  visible.value = val
  if (val) {
    if (props.orderData) {
      isEdit.value = true
      // Extract first item name for simplicity in this form
      let itemName = ''
      if (props.orderData.items && props.orderData.items.length > 0) {
        itemName = props.orderData.items[0].name || ''
      }
      Object.assign(form, {
        customer_name: props.orderData.customer_name || '',
        item_name: itemName,
        total_amount: props.orderData.total_amount || 0,
        status: props.orderData.status || 'pending',
      })
    } else {
      isEdit.value = false
      resetForm()
    }
  }
})

watch(visible, (val) => {
  emit('update:modelValue', val)
})

const isEdit = ref(false)
const saving = ref(false)
const formRef = ref<FormInstance>()

const form = reactive({
  customer_name: '',
  item_name: '',
  total_amount: 0,
  status: 'pending'
})

const rules = reactive<FormRules>({
  customer_name: [{ required: true, message: 'Please input customer name', trigger: 'blur' }],
  item_name: [{ required: true, message: 'Please input item name', trigger: 'blur' }],
  total_amount: [{ required: true, message: 'Please input total amount', trigger: 'blur' }],
  status: [{ required: true, message: 'Please select status', trigger: 'change' }]
})

const resetForm = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
  Object.assign(form, {
    customer_name: '',
    item_name: '',
    total_amount: 0,
    status: 'pending'
  })
}

const handleClose = () => {
  resetForm()
}

const submitForm = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const payload = {
          customer_name: form.customer_name,
          total_amount: form.total_amount,
          status: form.status,
          items: [{ name: form.item_name, quantity: 1, price: form.total_amount }], // Simple 1-item structure
          payment_method: 'Cash'
        }

        if (isEdit.value && props.orderData) {
          await apiClient.put(`/api/orders/${props.orderData.id}`, payload)
          ElMessage.success('Order updated successfully')
        } else {
          await apiClient.post('/api/orders', payload)
          ElMessage.success('Order created successfully')
        }
        visible.value = false
        emit('saved')
      } catch (error) {
        console.error(error)
        ElMessage.error('Failed to save order')
      } finally {
        saving.value = false
      }
    }
  })
}
</script>
