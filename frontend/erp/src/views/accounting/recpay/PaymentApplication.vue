<template>
  <div class="payment-application-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <div class="breadcrumb">
            <router-link to="/accounting/receivable-payments" class="breadcrumb-link">
              <i class="fas fa-credit-card"></i>
              Receivable Payments
            </router-link>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">Payment Application</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-link"></i>
            Payment Application
          </h1>
          <p class="page-subtitle">Apply payments to outstanding receivables and manage allocations</p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/receivable-payments" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Payments
          </router-link>
        </div>
      </div>
    </div>

    <!-- Customer Selection -->
    <div class="selection-card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-user-check"></i>
          Customer Selection
        </h3>
        <p class="card-description">Select customer to view outstanding receivables and unapplied payments</p>
      </div>
      <div class="card-content">
        <div class="selection-row">
          <div class="form-group">
            <label class="form-label">Select Customer</label>
            <select 
              v-model="selectedCustomerId" 
              @change="loadCustomerData"
              class="form-select"
              :disabled="loading"
            >
              <option value="">Choose a customer...</option>
              <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                {{ customer.name }} ({{ customer.customer_code }})
              </option>
            </select>
          </div>
          <div class="quick-stats" v-if="customerSummary">
            <div class="quick-stat">
              <span class="stat-value">{{ formatCurrency(customerSummary.totalReceivables) }}</span>
              <span class="stat-label">Total Receivables</span>
            </div>
            <div class="quick-stat">
              <span class="stat-value">{{ formatCurrency(customerSummary.unappliedPayments) }}</span>
              <span class="stat-label">Unapplied Payments</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer Data Display -->
    <div v-if="selectedCustomerId && !loading" class="customer-data">
      <!-- Customer Overview -->
      <div class="overview-card">
        <div class="overview-header">
          <div class="customer-info">
            <div class="customer-avatar">
              <i class="fas fa-user-circle"></i>
            </div>
            <div class="customer-details">
              <h3 class="customer-name">{{ selectedCustomer?.name }}</h3>
              <p class="customer-code">{{ selectedCustomer?.customer_code }}</p>
              <p class="customer-contact" v-if="selectedCustomer?.email">
                <i class="fas fa-envelope"></i>
                {{ selectedCustomer.email }}
              </p>
            </div>
          </div>
          <div class="balance-summary">
            <div class="balance-item">
              <span class="balance-label">Outstanding Balance</span>
              <span class="balance-value outstanding">{{ formatCurrency(customerSummary.totalReceivables) }}</span>
            </div>
            <div class="balance-item">
              <span class="balance-label">Unapplied Payments</span>
              <span class="balance-value unapplied">{{ formatCurrency(customerSummary.unappliedPayments) }}</span>
            </div>
            <div class="balance-item">
              <span class="balance-label">Net Balance</span>
              <span class="balance-value net">{{ formatCurrency(customerSummary.netBalance) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Application Interface -->
      <div class="application-interface">
        <!-- Outstanding Receivables -->
        <div class="receivables-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-file-invoice"></i>
              Outstanding Receivables
            </h3>
            <div class="section-actions">
              <button @click="selectAllReceivables" class="btn btn-sm btn-outline">
                <i class="fas fa-check-square"></i>
                Select All
              </button>
              <button @click="clearReceivableSelection" class="btn btn-sm btn-outline">
                <i class="fas fa-times"></i>
                Clear Selection
              </button>
            </div>
          </div>
          
          <div class="receivables-table-container">
            <table class="receivables-table">
              <thead>
                <tr>
                  <th width="50">
                    <input 
                      type="checkbox" 
                      @change="toggleAllReceivables"
                      :checked="allReceivablesSelected"
                      class="checkbox-input"
                    />
                  </th>
                  <th>Invoice</th>
                  <th>Date</th>
                  <th>Due Date</th>
                  <th>Original Amount</th>
                  <th>Balance</th>
                  <th>Apply Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="receivable in outstandingReceivables" 
                  :key="receivable.receivable_id"
                  class="receivable-row"
                  :class="{ 
                    'selected': selectedReceivables.includes(receivable.receivable_id),
                    'overdue': isOverdue(receivable.due_date)
                  }"
                >
                  <td>
                    <input 
                      type="checkbox" 
                      v-model="selectedReceivables"
                      :value="receivable.receivable_id"
                      class="checkbox-input"
                    />
                  </td>
                  <td class="invoice-cell">
                    <div class="invoice-info">
                      <span class="invoice-number">#{{ receivable.invoice_id }}</span>
                      <span class="invoice-type">{{ receivable.invoice_type || 'Standard' }}</span>
                    </div>
                  </td>
                  <td class="date-cell">
                    {{ formatDate(receivable.invoice_date) }}
                  </td>
                  <td class="due-date-cell">
                    <span :class="{ 'text-danger': isOverdue(receivable.due_date) }">
                      {{ formatDate(receivable.due_date) }}
                      <span v-if="isOverdue(receivable.due_date)" class="overdue-indicator">
                        <i class="fas fa-exclamation-triangle"></i>
                        {{ getDaysOverdue(receivable.due_date) }} days overdue
                      </span>
                    </span>
                  </td>
                  <td class="amount-cell">
                    {{ formatCurrency(receivable.amount) }}
                  </td>
                  <td class="balance-cell">
                    <span class="balance-amount">{{ formatCurrency(receivable.balance) }}</span>
                  </td>
                  <td class="apply-cell">
                    <div class="amount-input-wrapper">
                      <input 
                        type="number"
                        step="0.01"
                        v-model="applicationAmounts[receivable.receivable_id]"
                        @input="calculateTotals"
                        :max="receivable.balance"
                        :disabled="!selectedReceivables.includes(receivable.receivable_id)"
                        class="amount-input"
                        placeholder="0.00"
                      />
                      <button 
                        @click="applyFullBalance(receivable)"
                        :disabled="!selectedReceivables.includes(receivable.receivable_id)"
                        class="apply-full-btn"
                        title="Apply full balance"
                      >
                        <i class="fas fa-percentage"></i>
                      </button>
                    </div>
                  </td>
                  <td class="status-cell">
                    <span class="status-badge" :class="getReceivableStatusClass(receivable)">
                      {{ getReceivableStatus(receivable) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <!-- Empty State for Receivables -->
            <div v-if="outstandingReceivables.length === 0" class="empty-state">
              <div class="empty-icon">
                <i class="fas fa-file-invoice"></i>
              </div>
              <h4 class="empty-title">No Outstanding Receivables</h4>
              <p class="empty-description">This customer has no outstanding receivables to apply payments to.</p>
            </div>
          </div>
        </div>

        <!-- Unapplied Payments -->
        <div class="payments-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-money-bill-wave"></i>
              Unapplied Payments
            </h3>
            <div class="section-actions">
              <button @click="selectAllPayments" class="btn btn-sm btn-outline">
                <i class="fas fa-check-square"></i>
                Select All
              </button>
              <button @click="clearPaymentSelection" class="btn btn-sm btn-outline">
                <i class="fas fa-times"></i>
                Clear Selection
              </button>
            </div>
          </div>
          
          <div class="payments-table-container">
            <table class="payments-table">
              <thead>
                <tr>
                  <th width="50">
                    <input 
                      type="checkbox" 
                      @change="toggleAllPayments"
                      :checked="allPaymentsSelected"
                      class="checkbox-input"
                    />
                  </th>
                  <th>Payment ID</th>
                  <th>Date</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Available Amount</th>
                  <th>Use Amount</th>
                  <th>Reference</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="payment in unappliedPayments" 
                  :key="payment.payment_id"
                  class="payment-row"
                  :class="{ 'selected': selectedPayments.includes(payment.payment_id) }"
                >
                  <td>
                    <input 
                      type="checkbox" 
                      v-model="selectedPayments"
                      :value="payment.payment_id"
                      class="checkbox-input"
                    />
                  </td>
                  <td class="payment-id-cell">
                    <span class="payment-id-badge">#{{ payment.payment_id }}</span>
                  </td>
                  <td class="date-cell">
                    {{ formatDate(payment.payment_date) }}
                  </td>
                  <td class="method-cell">
                    <span class="method-badge" :class="getMethodClass(payment.payment_method)">
                      <i :class="getMethodIcon(payment.payment_method)"></i>
                      {{ payment.payment_method }}
                    </span>
                  </td>
                  <td class="amount-cell">
                    {{ formatCurrency(payment.amount) }}
                  </td>
                  <td class="available-cell">
                    <span class="available-amount">{{ formatCurrency(payment.unapplied_amount) }}</span>
                  </td>
                  <td class="use-cell">
                    <div class="amount-input-wrapper">
                      <input 
                        type="number"
                        step="0.01"
                        v-model="paymentUseAmounts[payment.payment_id]"
                        @input="calculateTotals"
                        :max="payment.unapplied_amount"
                        :disabled="!selectedPayments.includes(payment.payment_id)"
                        class="amount-input"
                        placeholder="0.00"
                      />
                      <button 
                        @click="useFullAmount(payment)"
                        :disabled="!selectedPayments.includes(payment.payment_id)"
                        class="use-full-btn"
                        title="Use full available amount"
                      >
                        <i class="fas fa-percentage"></i>
                      </button>
                    </div>
                  </td>
                  <td class="reference-cell">
                    <span class="reference-text">{{ payment.reference_number || '-' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <!-- Empty State for Payments -->
            <div v-if="unappliedPayments.length === 0" class="empty-state">
              <div class="empty-icon">
                <i class="fas fa-money-bill-wave"></i>
              </div>
              <h4 class="empty-title">No Unapplied Payments</h4>
              <p class="empty-description">This customer has no unapplied payments available for application.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Application Summary -->
      <div v-if="hasSelections" class="application-summary">
        <div class="summary-card">
          <div class="summary-header">
            <h3 class="summary-title">
              <i class="fas fa-calculator"></i>
              Application Summary
            </h3>
            <div class="auto-apply-section">
              <button @click="autoApplyPayments" class="btn btn-secondary">
                <i class="fas fa-magic"></i>
                Auto Apply
              </button>
              <button @click="clearAllApplications" class="btn btn-outline">
                <i class="fas fa-eraser"></i>
                Clear All
              </button>
            </div>
          </div>
          
          <div class="summary-content">
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Total Payment Amount to Use</span>
                <span class="summary-value">{{ formatCurrency(totalPaymentAmount) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Total Application Amount</span>
                <span class="summary-value">{{ formatCurrency(totalApplicationAmount) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Difference</span>
                <span class="summary-value" :class="getDifferenceClass()">
                  {{ formatCurrency(applicationDifference) }}
                </span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Selected Receivables</span>
                <span class="summary-value">{{ selectedReceivables.length }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Selected Payments</span>
                <span class="summary-value">{{ selectedPayments.length }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Application Status</span>
                <span class="summary-value">
                  <span class="status-indicator" :class="getApplicationStatusClass()">
                    {{ getApplicationStatus() }}
                  </span>
                </span>
              </div>
            </div>
            
            <!-- Validation Messages -->
            <div v-if="validationErrors.length > 0" class="validation-errors">
              <div class="error-header">
                <i class="fas fa-exclamation-triangle"></i>
                Application Validation Errors
              </div>
              <ul class="error-list">
                <li v-for="error in validationErrors" :key="error" class="error-item">
                  {{ error }}
                </li>
              </ul>
            </div>
          </div>
          
          <div class="summary-actions">
            <button 
              @click="previewApplication" 
              class="btn btn-outline"
              :disabled="!canPreview"
            >
              <i class="fas fa-eye"></i>
              Preview Application
            </button>
            <button 
              @click="processApplication" 
              class="btn btn-primary"
              :disabled="!canProcess || processing"
            >
              <i v-if="processing" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-check"></i>
              {{ processing ? 'Processing...' : 'Process Application' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p class="loading-text">Loading customer data...</p>
    </div>

    <!-- Preview Modal -->
    <div v-if="showPreviewModal" class="modal-overlay" @click="closePreviewModal">
      <div class="modal-content preview-modal" @click.stop>
        <div class="modal-header">
          <h3 class="modal-title">Application Preview</h3>
          <button @click="closePreviewModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="preview-content">
            <div class="preview-section">
              <h4 class="preview-subtitle">Payment Applications</h4>
              <div class="preview-table">
                <div class="preview-header">
                  <span>Invoice</span>
                  <span>Amount</span>
                  <span>Payment</span>
                </div>
                <div 
                  v-for="application in previewApplications" 
                  :key="`${application.receivableId}-${application.paymentId}`"
                  class="preview-row"
                >
                  <span>Invoice #{{ application.invoiceId }}</span>
                  <span>{{ formatCurrency(application.amount) }}</span>
                  <span>Payment #{{ application.paymentId }}</span>
                </div>
              </div>
            </div>
            
            <div class="preview-summary">
              <div class="preview-summary-item">
                <span>Total Applications:</span>
                <span>{{ formatCurrency(totalApplicationAmount) }}</span>
              </div>
              <div class="preview-summary-item">
                <span>Payment Amount Used:</span>
                <span>{{ formatCurrency(totalPaymentAmount) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closePreviewModal" class="btn btn-outline">Close Preview</button>
          <button @click="confirmAndProcess" class="btn btn-primary">
            <i class="fas fa-check"></i>
            Confirm & Process
          </button>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header success">
          <div class="success-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <h3 class="modal-title">Application Successful!</h3>
        </div>
        <div class="modal-body">
          <p>Payment applications have been processed successfully.</p>
          <div class="success-summary">
            <div class="success-item">
              <span>Applications Processed:</span>
              <span>{{ processedApplications }}</span>
            </div>
            <div class="success-item">
              <span>Total Amount Applied:</span>
              <span>{{ formatCurrency(totalApplicationAmount) }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="processAnother" class="btn btn-outline">
            Process Another
          </button>
          <button @click="viewPayments" class="btn btn-primary">
            View Payments
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'PaymentApplication',
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const processing = ref(false)
    const customers = ref([])
    const selectedCustomerId = ref('')
    const selectedCustomer = ref(null)
    const outstandingReceivables = ref([])
    const unappliedPayments = ref([])
    const selectedReceivables = ref([])
    const selectedPayments = ref([])
    const applicationAmounts = reactive({})
    const paymentUseAmounts = reactive({})
    const customerSummary = ref(null)
    const showPreviewModal = ref(false)
    const showSuccessModal = ref(false)
    const previewApplications = ref([])
    const processedApplications = ref(0)
    
    const validationErrors = computed(() => {
      const errors = []
      
      if (selectedReceivables.value.length === 0) {
        errors.push('Please select at least one receivable')
      }
      
      if (selectedPayments.value.length === 0) {
        errors.push('Please select at least one payment')
      }
      
      if (Math.abs(applicationDifference.value) > 0.01) {
        errors.push('Payment amount and application amount must match')
      }
      
      // Check individual application amounts
      selectedReceivables.value.forEach(receivableId => {
        const amount = parseFloat(applicationAmounts[receivableId] || 0)
        const receivable = outstandingReceivables.value.find(r => r.receivable_id === receivableId)
        if (amount > receivable?.balance) {
          errors.push(`Application amount for Invoice #${receivable.invoice_id} exceeds balance`)
        }
      })
      
      return errors
    })
    
    const totalPaymentAmount = computed(() => {
      return selectedPayments.value.reduce((total, paymentId) => {
        return total + (parseFloat(paymentUseAmounts[paymentId]) || 0)
      }, 0)
    })
    
    const totalApplicationAmount = computed(() => {
      return selectedReceivables.value.reduce((total, receivableId) => {
        return total + (parseFloat(applicationAmounts[receivableId]) || 0)
      }, 0)
    })
    
    const applicationDifference = computed(() => {
      return totalPaymentAmount.value - totalApplicationAmount.value
    })
    
    const allReceivablesSelected = computed(() => {
      return outstandingReceivables.value.length > 0 && 
             selectedReceivables.value.length === outstandingReceivables.value.length
    })
    
    const allPaymentsSelected = computed(() => {
      return unappliedPayments.value.length > 0 && 
             selectedPayments.value.length === unappliedPayments.value.length
    })
    
    const hasSelections = computed(() => {
      return selectedReceivables.value.length > 0 || selectedPayments.value.length > 0
    })
    
    const canPreview = computed(() => {
      return selectedReceivables.value.length > 0 && 
             selectedPayments.value.length > 0 && 
             validationErrors.value.length === 0
    })
    
    const canProcess = computed(() => {
      return canPreview.value && Math.abs(applicationDifference.value) < 0.01
    })

    const fetchCustomers = async () => {
      try {
        const response = await axios.get('/customers')
        customers.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching customers:', error)
      }
    }

    const loadCustomerData = async () => {
      if (!selectedCustomerId.value) {
        clearData()
        return
      }
      
      try {
        loading.value = true
        
        // Get selected customer details
        selectedCustomer.value = customers.value.find(c => c.customer_id == selectedCustomerId.value)
        
        // Fetch outstanding receivables
        const receivablesResponse = await axios.get('/accounting/customer-receivables', {
          params: {
            customer_id: selectedCustomerId.value,
            status: 'Open'
          }
        })
        outstandingReceivables.value = receivablesResponse.data.data || []
        
        // Fetch unapplied payments
        const paymentsResponse = await axios.get('/accounting/receivable-payments', {
          params: {
            customer_id: selectedCustomerId.value,
            status: 'Unapplied'
          }
        })
        unappliedPayments.value = paymentsResponse.data.data || []
        
        // Calculate customer summary
        calculateCustomerSummary()
        
        // Clear previous selections
        clearSelections()
        
      } catch (error) {
        console.error('Error loading customer data:', error)
      } finally {
        loading.value = false
      }
    }

    const calculateCustomerSummary = () => {
      const totalReceivables = outstandingReceivables.value.reduce((sum, r) => sum + r.balance, 0)
      const unappliedPaymentsTotal = unappliedPayments.value.reduce((sum, p) => sum + (p.unapplied_amount || p.amount), 0)
      
      customerSummary.value = {
        totalReceivables,
        unappliedPayments: unappliedPaymentsTotal,
        netBalance: totalReceivables - unappliedPaymentsTotal
      }
    }

    const clearData = () => {
      selectedCustomer.value = null
      outstandingReceivables.value = []
      unappliedPayments.value = []
      customerSummary.value = null
      clearSelections()
    }

    const clearSelections = () => {
      selectedReceivables.value = []
      selectedPayments.value = []
      Object.keys(applicationAmounts).forEach(key => delete applicationAmounts[key])
      Object.keys(paymentUseAmounts).forEach(key => delete paymentUseAmounts[key])
    }

    const selectAllReceivables = () => {
      selectedReceivables.value = outstandingReceivables.value.map(r => r.receivable_id)
      // Auto-fill application amounts
      outstandingReceivables.value.forEach(receivable => {
        applicationAmounts[receivable.receivable_id] = receivable.balance
      })
      calculateTotals()
    }

    const clearReceivableSelection = () => {
      selectedReceivables.value = []
      Object.keys(applicationAmounts).forEach(key => delete applicationAmounts[key])
      calculateTotals()
    }

    const selectAllPayments = () => {
      selectedPayments.value = unappliedPayments.value.map(p => p.payment_id)
      // Auto-fill use amounts
      unappliedPayments.value.forEach(payment => {
        paymentUseAmounts[payment.payment_id] = payment.unapplied_amount || payment.amount
      })
      calculateTotals()
    }

    const clearPaymentSelection = () => {
      selectedPayments.value = []
      Object.keys(paymentUseAmounts).forEach(key => delete paymentUseAmounts[key])
      calculateTotals()
    }

    const toggleAllReceivables = () => {
      if (allReceivablesSelected.value) {
        clearReceivableSelection()
      } else {
        selectAllReceivables()
      }
    }

    const toggleAllPayments = () => {
      if (allPaymentsSelected.value) {
        clearPaymentSelection()
      } else {
        selectAllPayments()
      }
    }

    const applyFullBalance = (receivable) => {
      applicationAmounts[receivable.receivable_id] = receivable.balance
      calculateTotals()
    }

    const useFullAmount = (payment) => {
      paymentUseAmounts[payment.payment_id] = payment.unapplied_amount || payment.amount
      calculateTotals()
    }

    const calculateTotals = () => {
      // Totals are computed automatically
    }

    const autoApplyPayments = () => {
      // Smart auto-application logic
      let remainingPaymentAmount = totalPaymentAmount.value
      
      // Sort receivables by due date (oldest first)
      const sortedReceivables = [...outstandingReceivables.value]
        .filter(r => selectedReceivables.value.includes(r.receivable_id))
        .sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
      
      // Clear current applications
      selectedReceivables.value.forEach(id => {
        applicationAmounts[id] = 0
      })
      
      // Apply payments to receivables
      for (const receivable of sortedReceivables) {
        if (remainingPaymentAmount <= 0) break
        
        const applyAmount = Math.min(remainingPaymentAmount, receivable.balance)
        applicationAmounts[receivable.receivable_id] = applyAmount
        remainingPaymentAmount -= applyAmount
      }
    }

    const clearAllApplications = () => {
      clearSelections()
    }

    const previewApplication = () => {
      previewApplications.value = []
      
      selectedReceivables.value.forEach(receivableId => {
        const amount = parseFloat(applicationAmounts[receivableId] || 0)
        if (amount > 0) {
          const receivable = outstandingReceivables.value.find(r => r.receivable_id === receivableId)
          
          // For simplicity, apply to first selected payment
          // In real implementation, you might want more sophisticated allocation
          const paymentId = selectedPayments.value[0]
          
          previewApplications.value.push({
            receivableId,
            paymentId,
            invoiceId: receivable.invoice_id,
            amount
          })
        }
      })
      
      showPreviewModal.value = true
    }

    const closePreviewModal = () => {
      showPreviewModal.value = false
    }

    const confirmAndProcess = () => {
      closePreviewModal()
      processApplication()
    }

    const processApplication = async () => {
      try {
        processing.value = true
        
        const applications = []
        
        selectedReceivables.value.forEach(receivableId => {
          const amount = parseFloat(applicationAmounts[receivableId] || 0)
          if (amount > 0) {
            // For simplicity, allocate to first payment
            // In real implementation, implement proper allocation logic
            const paymentId = selectedPayments.value[0]
            
            applications.push({
              receivable_id: receivableId,
              payment_id: paymentId,
              application_amount: amount
            })
          }
        })
        
        await axios.post('/accounting/payment-applications', {
          customer_id: selectedCustomerId.value,
          applications
        })
        
        processedApplications.value = applications.length
        showSuccessModal.value = true
        
        // Reload data
        await loadCustomerData()
        
      } catch (error) {
        console.error('Error processing application:', error)
        // Show error message
      } finally {
        processing.value = false
      }
    }

    const closeSuccessModal = () => {
      showSuccessModal.value = false
    }

    const processAnother = () => {
      closeSuccessModal()
      selectedCustomerId.value = ''
      clearData()
    }

    const viewPayments = () => {
      router.push('/accounting/receivable-payments')
    }

    const getApplicationStatus = () => {
      if (validationErrors.value.length > 0) return 'Invalid'
      if (Math.abs(applicationDifference.value) < 0.01) return 'Balanced'
      if (applicationDifference.value > 0) return 'Overpaid'
      return 'Underpaid'
    }

    const getApplicationStatusClass = () => {
      const status = getApplicationStatus()
      return {
        'status-valid': status === 'Balanced',
        'status-invalid': status === 'Invalid',
        'status-warning': status === 'Overpaid' || status === 'Underpaid'
      }
    }

    const getDifferenceClass = () => {
      if (Math.abs(applicationDifference.value) < 0.01) return 'text-success'
      return 'text-danger'
    }

    const getReceivableStatus = (receivable) => {
      if (isOverdue(receivable.due_date)) return 'Overdue'
      if (receivable.balance === receivable.amount) return 'Open'
      return 'Partial'
    }

    const getReceivableStatusClass = (receivable) => {
      const status = getReceivableStatus(receivable)
      return {
        'status-overdue': status === 'Overdue',
        'status-open': status === 'Open',
        'status-partial': status === 'Partial'
      }
    }

    const getMethodClass = (method) => {
      const methodClasses = {
        'Cash': 'method-cash',
        'Bank Transfer': 'method-transfer',
        'Credit Card': 'method-card',
        'Check': 'method-check',
        'Wire Transfer': 'method-wire',
        'Online Payment': 'method-online'
      }
      return methodClasses[method] || 'method-default'
    }

    const getMethodIcon = (method) => {
      const methodIcons = {
        'Cash': 'fas fa-money-bill',
        'Bank Transfer': 'fas fa-exchange-alt',
        'Credit Card': 'fas fa-credit-card',
        'Check': 'fas fa-money-check',
        'Wire Transfer': 'fas fa-wire',
        'Online Payment': 'fas fa-globe'
      }
      return methodIcons[method] || 'fas fa-payment'
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }

    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }

    const isOverdue = (dueDate) => {
      return new Date(dueDate) < new Date()
    }

    const getDaysOverdue = (dueDate) => {
      const today = new Date()
      const due = new Date(dueDate)
      const diffTime = today - due
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    }

    onMounted(() => {
      fetchCustomers()
    })

    return {
      loading,
      processing,
      customers,
      selectedCustomerId,
      selectedCustomer,
      outstandingReceivables,
      unappliedPayments,
      selectedReceivables,
      selectedPayments,
      applicationAmounts,
      paymentUseAmounts,
      customerSummary,
      showPreviewModal,
      showSuccessModal,
      previewApplications,
      processedApplications,
      validationErrors,
      totalPaymentAmount,
      totalApplicationAmount,
      applicationDifference,
      allReceivablesSelected,
      allPaymentsSelected,
      hasSelections,
      canPreview,
      canProcess,
      loadCustomerData,
      selectAllReceivables,
      clearReceivableSelection,
      selectAllPayments,
      clearPaymentSelection,
      toggleAllReceivables,
      toggleAllPayments,
      applyFullBalance,
      useFullAmount,
      calculateTotals,
      autoApplyPayments,
      clearAllApplications,
      previewApplication,
      closePreviewModal,
      confirmAndProcess,
      processApplication,
      closeSuccessModal,
      processAnother,
      viewPayments,
      getApplicationStatus,
      getApplicationStatusClass,
      getDifferenceClass,
      getReceivableStatus,
      getReceivableStatusClass,
      getMethodClass,
      getMethodIcon,
      formatCurrency,
      formatDate,
      isOverdue,
      getDaysOverdue
    }
  }
}
</script>

<style scoped>
.payment-application-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  padding: 2rem;
}

.page-header {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.title-section {
  flex: 1;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.breadcrumb-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6366f1;
  text-decoration: none;
  transition: all 0.3s ease;
}

.breadcrumb-link:hover {
  color: #4f46e5;
}

.breadcrumb-separator {
  color: #94a3b8;
}

.breadcrumb-current {
  color: #64748b;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title i {
  color: #6366f1;
  font-size: 2rem;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.125rem;
  font-weight: 400;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
  background: linear-gradient(135deg, #64748b 0%, #475569 100%);
  color: white;
}

.btn-outline {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.btn-outline:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.selection-card,
.overview-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
  margin-bottom: 2rem;
}

.card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.card-title i {
  color: #6366f1;
}

.card-description {
  color: #64748b;
  font-size: 0.95rem;
}

.card-content {
  padding: 1.5rem;
}

.selection-row {
  display: flex;
  gap: 2rem;
  align-items: end;
  flex-wrap: wrap;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 300px;
  flex: 1;
}

.form-label {
  font-weight: 600;
  color: #374151;
  font-size: 0.95rem;
}

.form-select {
  padding: 0.875rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.quick-stats {
  display: flex;
  gap: 2rem;
  flex-wrap: wrap;
}

.quick-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 1rem;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  min-width: 150px;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #6366f1;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.75rem;
  color: #64748b;
  text-align: center;
}

.overview-header {
  padding: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 2rem;
}

.customer-info {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.customer-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem;
}

.customer-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.customer-name {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.customer-code {
  color: #64748b;
  font-size: 0.875rem;
}

.customer-contact {
  color: #64748b;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.balance-summary {
  display: flex;
  gap: 2rem;
  flex-wrap: wrap;
}

.balance-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.balance-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.balance-value {
  font-size: 1.125rem;
  font-weight: 600;
}

.balance-value.outstanding {
  color: #ef4444;
}

.balance-value.unapplied {
  color: #059669;
}

.balance-value.net {
  color: #6366f1;
}

.application-interface {
  display: grid;
  gap: 2rem;
  margin-bottom: 2rem;
}

.receivables-section,
.payments-section {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-title i {
  color: #6366f1;
}

.section-actions {
  display: flex;
  gap: 0.75rem;
}

.receivables-table-container,
.payments-table-container {
  overflow-x: auto;
}

.receivables-table,
.payments-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.receivables-table th,
.receivables-table td,
.payments-table th,
.payments-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
}

.receivables-table th,
.payments-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.receivable-row,
.payment-row {
  transition: all 0.3s ease;
}

.receivable-row:hover,
.payment-row:hover {
  background: #f8fafc;
}

.receivable-row.selected,
.payment-row.selected {
  background: rgba(99, 102, 241, 0.05);
}

.receivable-row.overdue {
  background: rgba(239, 68, 68, 0.05);
}

.checkbox-input {
  width: 18px;
  height: 18px;
  border-radius: 4px;
  border: 2px solid #e2e8f0;
  cursor: pointer;
}

.checkbox-input:checked {
  background: #6366f1;
  border-color: #6366f1;
}

.invoice-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.invoice-number {
  font-weight: 600;
  color: #1e293b;
}

.invoice-type {
  font-size: 0.75rem;
  color: #64748b;
}

.date-cell,
.due-date-cell {
  color: #64748b;
}

.text-danger {
  color: #ef4444;
}

.overdue-indicator {
  display: block;
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 0.25rem;
}

.amount-cell,
.balance-cell,
.available-cell {
  font-weight: 600;
}

.balance-amount {
  color: #ef4444;
}

.available-amount {
  color: #059669;
}

.amount-input-wrapper {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.amount-input {
  flex: 1;
  padding: 0.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.875rem;
  min-width: 100px;
}

.amount-input:focus {
  outline: none;
  border-color: #6366f1;
}

.amount-input:disabled {
  background: #f8fafc;
  color: #94a3b8;
}

.apply-full-btn,
.use-full-btn {
  padding: 0.5rem;
  border: 2px solid #e2e8f0;
  background: white;
  border-radius: 6px;
  color: #6366f1;
  cursor: pointer;
  transition: all 0.3s ease;
}

.apply-full-btn:hover:not(:disabled),
.use-full-btn:hover:not(:disabled) {
  background: #6366f1;
  color: white;
}

.apply-full-btn:disabled,
.use-full-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-overdue {
  background: #fecaca;
  color: #dc2626;
}

.status-open {
  background: #dbeafe;
  color: #2563eb;
}

.status-partial {
  background: #fef3c7;
  color: #d97706;
}

.payment-id-badge {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.method-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
}

.method-cash {
  background: #dcfdf7;
  color: #059669;
}

.method-transfer {
  background: #dbeafe;
  color: #2563eb;
}

.method-card {
  background: #fef3c7;
  color: #d97706;
}

.method-check {
  background: #f3e8ff;
  color: #8b5cf6;
}

.method-wire {
  background: #fecaca;
  color: #dc2626;
}

.method-online {
  background: #dcfce7;
  color: #16a34a;
}

.method-default {
  background: #f1f5f9;
  color: #64748b;
}

.reference-text {
  font-family: monospace;
  color: #64748b;
  font-size: 0.875rem;
}

.empty-state {
  text-align: center;
  padding: 3rem 2rem;
  color: #64748b;
}

.empty-icon {
  font-size: 3rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-description {
  color: #6b7280;
}

.application-summary {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.summary-card {
  padding: 0;
}

.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.summary-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.summary-title i {
  color: #6366f1;
}

.auto-apply-section {
  display: flex;
  gap: 0.75rem;
}

.summary-content {
  padding: 1.5rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.summary-label {
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.summary-value {
  font-weight: 600;
  color: #1e293b;
  font-size: 1rem;
}

.text-success {
  color: #059669;
}

.text-danger {
  color: #ef4444;
}

.status-indicator {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-valid {
  background: #dcfdf7;
  color: #059669;
}

.status-invalid {
  background: #fecaca;
  color: #dc2626;
}

.status-warning {
  background: #fef3c7;
  color: #d97706;
}

.validation-errors {
  background: #fef2f2;
  border: 2px solid #fecaca;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.error-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #dc2626;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.error-list {
  margin: 0;
  padding-left: 1.5rem;
}

.error-item {
  color: #dc2626;
  margin-bottom: 0.25rem;
}

.summary-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding: 1.5rem;
  border-top: 1px solid #f1f5f9;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  text-align: center;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f4f6;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  color: #64748b;
  font-size: 1.1rem;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  max-width: 500px;
  width: 100%;
  margin: 1rem;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.preview-modal {
  max-width: 700px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.modal-header.success {
  background: linear-gradient(135deg, #dcfdf7 0%, #ffffff 100%);
  text-align: center;
  justify-content: center;
  flex-direction: column;
  gap: 1rem;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 0.25rem;
}

.success-icon {
  font-size: 4rem;
  color: #059669;
}

.modal-body {
  padding: 1.5rem;
}

.preview-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.preview-section {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
}

.preview-subtitle {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1rem;
}

.preview-table {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.preview-header {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1rem;
  padding: 0.75rem;
  background: white;
  border-radius: 6px;
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.preview-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1rem;
  padding: 0.75rem;
  background: white;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.preview-summary {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
}

.preview-summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.preview-summary-item:last-child {
  border-bottom: none;
  font-weight: 600;
  color: #6366f1;
}

.success-summary {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.success-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.success-item:last-child {
  border-bottom: none;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #f1f5f9;
}

@media (max-width: 768px) {
  .payment-application-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
  
  .selection-row {
    flex-direction: column;
    align-items: stretch;
  }
  
  .quick-stats {
    justify-content: center;
  }
  
  .overview-header {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
    gap: 1rem;
  }
  
  .balance-summary {
    justify-content: center;
  }
  
  .section-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .section-actions {
    justify-content: center;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-actions {
    flex-direction: column;
  }
  
  .auto-apply-section {
    justify-content: center;
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .preview-header,
  .preview-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
}
</style>