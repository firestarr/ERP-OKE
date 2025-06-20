<template>
  <div class="payment-detail-container">
    <!-- Header Section -->
    <div class="page-header" v-if="payment">
      <div class="header-content">
        <div class="title-section">
          <div class="breadcrumb">
            <router-link to="/accounting/receivable-payments" class="breadcrumb-link">
              <i class="fas fa-credit-card"></i>
              Receivable Payments
            </router-link>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">Payment #{{ payment.payment_id }}</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-receipt"></i>
            Payment Details
            <span class="payment-badge">{{ getStatusBadge() }}</span>
          </h1>
          <p class="page-subtitle">Complete payment information and transaction details</p>
        </div>
        <div class="header-actions">
          <button @click="printPayment" class="btn btn-outline">
            <i class="fas fa-print"></i>
            Print
          </button>
          <button @click="downloadPDF" class="btn btn-secondary">
            <i class="fas fa-file-pdf"></i>
            Download PDF
          </button>
          <button @click="showDeleteModal = true" class="btn btn-danger">
            <i class="fas fa-trash"></i>
            Delete Payment
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p class="loading-text">Loading payment details...</p>
    </div>

    <!-- Payment Content -->
    <div v-if="payment && !loading" class="payment-content">
      <!-- Quick Stats -->
      <div class="stats-row">
        <div class="stat-card primary">
          <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ formatCurrency(payment.amount) }}</div>
            <div class="stat-label">Payment Amount</div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-calendar"></i>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ formatDate(payment.payment_date) }}</div>
            <div class="stat-label">Payment Date</div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-exchange-alt"></i>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ payment.currency }}</div>
            <div class="stat-label">Currency</div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-credit-card"></i>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ payment.payment_method }}</div>
            <div class="stat-label">Payment Method</div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="content-grid">
        <!-- Payment Information -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-info-circle"></i>
              Payment Information
            </h3>
          </div>
          <div class="card-content">
            <div class="info-grid">
              <div class="info-item">
                <label class="info-label">Payment ID</label>
                <div class="info-value">
                  <span class="payment-id-badge">#{{ payment.payment_id }}</span>
                </div>
              </div>
              
              <div class="info-item">
                <label class="info-label">Payment Date</label>
                <div class="info-value">
                  <i class="fas fa-calendar"></i>
                  {{ formatDateFull(payment.payment_date) }}
                </div>
              </div>
              
              <div class="info-item">
                <label class="info-label">Payment Method</label>
                <div class="info-value">
                  <span class="method-badge" :class="getMethodClass(payment.payment_method)">
                    <i :class="getMethodIcon(payment.payment_method)"></i>
                    {{ payment.payment_method }}
                  </span>
                </div>
              </div>
              
              <div class="info-item">
                <label class="info-label">Reference Number</label>
                <div class="info-value">
                  <span class="reference-value">{{ payment.reference_number || 'Not provided' }}</span>
                </div>
              </div>
              
              <div class="info-item">
                <label class="info-label">Transaction ID</label>
                <div class="info-value">
                  <span class="transaction-id">{{ payment.transaction_id || 'Not provided' }}</span>
                </div>
              </div>
              
              <div class="info-item">
                <label class="info-label">Created Date</label>
                <div class="info-value">
                  <i class="fas fa-clock"></i>
                  {{ formatDateFull(payment.created_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer Information -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-user"></i>
              Customer Information
            </h3>
            <router-link 
              v-if="payment.customer_receivable?.customer" 
              :to="`/customers/${payment.customer_receivable.customer.customer_id}`"
              class="view-customer-btn"
            >
              <i class="fas fa-external-link-alt"></i>
              View Customer
            </router-link>
          </div>
          <div class="card-content">
            <div class="customer-info" v-if="payment.customer_receivable?.customer">
              <div class="customer-avatar">
                <i class="fas fa-user-circle"></i>
              </div>
              <div class="customer-details">
                <h4 class="customer-name">{{ payment.customer_receivable.customer.name }}</h4>
                <p class="customer-code">Customer Code: {{ payment.customer_receivable.customer.customer_code }}</p>
                <p class="customer-email" v-if="payment.customer_receivable.customer.email">
                  <i class="fas fa-envelope"></i>
                  {{ payment.customer_receivable.customer.email }}
                </p>
                <p class="customer-phone" v-if="payment.customer_receivable.customer.phone">
                  <i class="fas fa-phone"></i>
                  {{ payment.customer_receivable.customer.phone }}
                </p>
              </div>
            </div>
            <div v-else class="no-customer-info">
              <i class="fas fa-exclamation-triangle"></i>
              Customer information not available
            </div>
          </div>
        </div>

        <!-- Receivable Information -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-file-invoice"></i>
              Related Receivable
            </h3>
            <router-link 
              v-if="payment.customer_receivable" 
              :to="`/accounting/customer-receivables/${payment.customer_receivable.receivable_id}`"
              class="view-receivable-btn"
            >
              <i class="fas fa-external-link-alt"></i>
              View Receivable
            </router-link>
          </div>
          <div class="card-content">
            <div class="receivable-info" v-if="payment.customer_receivable">
              <div class="receivable-grid">
                <div class="receivable-item">
                  <label class="receivable-label">Receivable ID</label>
                  <div class="receivable-value">
                    <span class="receivable-id-badge">#{{ payment.customer_receivable.receivable_id }}</span>
                  </div>
                </div>
                
                <div class="receivable-item">
                  <label class="receivable-label">Invoice Number</label>
                  <div class="receivable-value">
                    <span class="invoice-number">#{{ payment.customer_receivable.invoice_id }}</span>
                  </div>
                </div>
                
                <div class="receivable-item">
                  <label class="receivable-label">Original Amount</label>
                  <div class="receivable-value">
                    <span class="amount-original">{{ formatCurrency(payment.customer_receivable.amount) }}</span>
                  </div>
                </div>
                
                <div class="receivable-item">
                  <label class="receivable-label">Paid Amount</label>
                  <div class="receivable-value">
                    <span class="amount-paid">{{ formatCurrency(payment.customer_receivable.paid_amount) }}</span>
                  </div>
                </div>
                
                <div class="receivable-item">
                  <label class="receivable-label">Current Balance</label>
                  <div class="receivable-value">
                    <span class="amount-balance">{{ formatCurrency(payment.customer_receivable.balance) }}</span>
                  </div>
                </div>
                
                <div class="receivable-item">
                  <label class="receivable-label">Due Date</label>
                  <div class="receivable-value">
                    <span class="due-date" :class="{ 'overdue': isOverdue(payment.customer_receivable.due_date) }">
                      {{ formatDate(payment.customer_receivable.due_date) }}
                      <span v-if="isOverdue(payment.customer_receivable.due_date)" class="overdue-badge">Overdue</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Amount Details -->
        <div class="info-card amount-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-calculator"></i>
              Amount Details
            </h3>
          </div>
          <div class="card-content">
            <div class="amount-breakdown">
              <div class="amount-row">
                <span class="amount-label">Payment Amount ({{ payment.currency }})</span>
                <span class="amount-value primary">{{ formatCurrency(payment.amount) }}</span>
              </div>
              
              <div v-if="payment.base_currency_amount && payment.currency !== 'USD'" class="amount-row">
                <span class="amount-label">Base Currency Amount (USD)</span>
                <span class="amount-value">{{ formatCurrency(payment.base_currency_amount) }}</span>
              </div>
              
              <div v-if="payment.exchange_rate && payment.exchange_rate !== 1" class="amount-row">
                <span class="amount-label">Exchange Rate</span>
                <span class="amount-value exchange-rate">
                  1 {{ payment.currency }} = {{ payment.exchange_rate }} USD
                </span>
              </div>
              
              <div v-if="payment.foreign_amount" class="amount-row">
                <span class="amount-label">Foreign Amount</span>
                <span class="amount-value">{{ formatCurrency(payment.foreign_amount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Bank Information (if applicable) -->
        <div v-if="payment.bank_account_id" class="info-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-university"></i>
              Bank Information
            </h3>
          </div>
          <div class="card-content">
            <div class="bank-info">
              <div class="bank-item">
                <label class="bank-label">Bank Account</label>
                <div class="bank-value">{{ payment.bank_account?.account_name || 'Not specified' }}</div>
              </div>
              <div class="bank-item">
                <label class="bank-label">Account Number</label>
                <div class="bank-value">{{ payment.bank_account?.account_number || 'Not specified' }}</div>
              </div>
              <div class="bank-item">
                <label class="bank-label">Transaction ID</label>
                <div class="bank-value">{{ payment.transaction_id || 'Not provided' }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Notes Section -->
        <div v-if="payment.notes" class="info-card notes-card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-sticky-note"></i>
              Payment Notes
            </h3>
          </div>
          <div class="card-content">
            <div class="notes-content">
              {{ payment.notes }}
            </div>
          </div>
        </div>
      </div>

      <!-- Journal Entry Section -->
      <div v-if="journalEntries.length > 0" class="journal-section">
        <div class="section-header">
          <h3 class="section-title">
            <i class="fas fa-book"></i>
            Related Journal Entries
          </h3>
        </div>
        <div class="journal-entries">
          <div v-for="entry in journalEntries" :key="entry.journal_id" class="journal-card">
            <div class="journal-header">
              <div class="journal-info">
                <h4 class="journal-title">Journal Entry #{{ entry.journal_id }}</h4>
                <p class="journal-date">{{ formatDate(entry.entry_date) }}</p>
              </div>
              <div class="journal-total">
                {{ formatCurrency(entry.total_amount) }}
              </div>
            </div>
            <div class="journal-lines">
              <div v-for="line in entry.lines" :key="line.line_id" class="journal-line">
                <div class="account-info">
                  <span class="account-name">{{ line.account?.account_name }}</span>
                  <span class="account-code">{{ line.account?.account_code }}</span>
                </div>
                <div class="line-amounts">
                  <span v-if="line.debit_amount > 0" class="debit-amount">
                    Dr. {{ formatCurrency(line.debit_amount) }}
                  </span>
                  <span v-if="line.credit_amount > 0" class="credit-amount">
                    Cr. {{ formatCurrency(line.credit_amount) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity Timeline -->
      <div class="timeline-section">
        <div class="section-header">
          <h3 class="section-title">
            <i class="fas fa-history"></i>
            Activity Timeline
          </h3>
        </div>
        <div class="timeline">
          <div class="timeline-item">
            <div class="timeline-marker created">
              <i class="fas fa-plus"></i>
            </div>
            <div class="timeline-content">
              <h4 class="timeline-title">Payment Recorded</h4>
              <p class="timeline-description">Payment was successfully recorded in the system</p>
              <p class="timeline-date">{{ formatDateFull(payment.created_at) }}</p>
            </div>
          </div>
          
          <div v-if="payment.updated_at !== payment.created_at" class="timeline-item">
            <div class="timeline-marker updated">
              <i class="fas fa-edit"></i>
            </div>
            <div class="timeline-content">
              <h4 class="timeline-title">Payment Updated</h4>
              <p class="timeline-description">Payment information was modified</p>
              <p class="timeline-date">{{ formatDateFull(payment.updated_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error && !loading" class="error-container">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3 class="error-title">Payment Not Found</h3>
      <p class="error-description">{{ error }}</p>
      <router-link to="/accounting/receivable-payments" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i>
        Back to Payments List
      </router-link>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="showDeleteModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3 class="modal-title">Confirm Delete Payment</h3>
          <button @click="showDeleteModal = false" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <p>Are you sure you want to delete this payment record?</p>
          <div class="delete-details">
            <div class="detail-row">
              <span>Payment ID:</span>
              <span>#{{ payment?.payment_id }}</span>
            </div>
            <div class="detail-row">
              <span>Amount:</span>
              <span>{{ formatCurrency(payment?.amount) }}</span>
            </div>
            <div class="detail-row">
              <span>Customer:</span>
              <span>{{ payment?.customer_receivable?.customer?.name }}</span>
            </div>
          </div>
          <p class="warning-text">This action cannot be undone and will affect the customer's receivable balance.</p>
        </div>
        <div class="modal-footer">
          <button @click="showDeleteModal = false" class="btn btn-outline">Cancel</button>
          <button @click="deletePayment" class="btn btn-danger" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-trash"></i>
            {{ deleting ? 'Deleting...' : 'Delete Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'PaymentDetail',
  setup() {
    const route = useRoute()
    const router = useRouter()
    
    const loading = ref(true)
    const error = ref(null)
    const payment = ref(null)
    const journalEntries = ref([])
    const showDeleteModal = ref(false)
    const deleting = ref(false)

    const paymentId = computed(() => route.params.id)

    const fetchPaymentDetails = async () => {
      try {
        loading.value = true
        error.value = null
        
        const response = await axios.get(`/accounting/receivable-payments/${paymentId.value}`)
        payment.value = response.data.data
        
        // Fetch related journal entries
        await fetchJournalEntries()
        
      } catch (err) {
        console.error('Error fetching payment details:', err)
        error.value = err.response?.data?.message || 'Payment not found'
      } finally {
        loading.value = false
      }
    }

    const fetchJournalEntries = async () => {
      try {
        const response = await axios.get('/accounting/journal-entries', {
          params: {
            reference_type: 'ReceivablePayment',
            reference_id: paymentId.value
          }
        })
        journalEntries.value = response.data.data || []
      } catch (err) {
        console.error('Error fetching journal entries:', err)
        journalEntries.value = []
      }
    }

    const deletePayment = async () => {
      try {
        deleting.value = true
        await axios.delete(`/accounting/receivable-payments/${paymentId.value}`)
        router.push('/accounting/receivable-payments')
        // Show success message
      } catch (err) {
        console.error('Error deleting payment:', err)
        // Show error message
      } finally {
        deleting.value = false
        showDeleteModal.value = false
      }
    }

    const printPayment = () => {
      window.print()
    }

    const downloadPDF = () => {
      // Implement PDF download functionality
      console.log('Download PDF')
    }

    const getStatusBadge = () => {
      return 'Completed'
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

    const formatDateFull = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const isOverdue = (dueDate) => {
      return new Date(dueDate) < new Date()
    }

    onMounted(() => {
      fetchPaymentDetails()
    })

    return {
      loading,
      error,
      payment,
      journalEntries,
      showDeleteModal,
      deleting,
      paymentId,
      deletePayment,
      printPayment,
      downloadPDF,
      getStatusBadge,
      getMethodClass,
      getMethodIcon,
      formatCurrency,
      formatDate,
      formatDateFull,
      isOverdue
    }
  }
}
</script>

<style scoped>
.payment-detail-container {
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
  flex-wrap: wrap;
}

.page-title i {
  color: #6366f1;
  font-size: 2rem;
}

.payment-badge {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.125rem;
  font-weight: 400;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
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

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
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

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
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

.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

.stat-card.primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.stat-card:not(.primary) .stat-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 1.875rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.875rem;
  opacity: 0.8;
}

.content-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 2rem;
  margin-bottom: 2rem;
}

.info-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
  transition: all 0.3s ease;
}

.info-card:hover {
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

.card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.card-title i {
  color: #6366f1;
}

.view-customer-btn,
.view-receivable-btn {
  background: #f1f5f9;
  color: #6366f1;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.view-customer-btn:hover,
.view-receivable-btn:hover {
  background: #6366f1;
  color: white;
}

.card-content {
  padding: 1.5rem;
}

.info-grid {
  display: grid;
  gap: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.info-label {
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.info-value {
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.payment-id-badge {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
}

.method-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
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

.reference-value,
.transaction-id {
  font-family: monospace;
  background: #f8fafc;
  padding: 0.5rem;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.customer-info {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
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
  flex-shrink: 0;
}

.customer-details {
  flex: 1;
}

.customer-name {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.customer-code {
  color: #64748b;
  font-size: 0.875rem;
  margin-bottom: 0.25rem;
}

.customer-email,
.customer-phone {
  color: #64748b;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.no-customer-info {
  text-align: center;
  color: #64748b;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.receivable-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.receivable-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.receivable-label {
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.receivable-value {
  font-weight: 600;
  color: #1e293b;
}

.receivable-id-badge {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 16px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
}

.invoice-number {
  font-family: monospace;
  background: #6366f1;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 16px;
  font-size: 0.75rem;
  font-weight: 600;
}

.amount-original {
  color: #64748b;
}

.amount-paid {
  color: #059669;
}

.amount-balance {
  color: #ef4444;
  font-size: 1.1rem;
}

.due-date.overdue {
  color: #ef4444;
}

.overdue-badge {
  background: #fecaca;
  color: #dc2626;
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  margin-left: 0.5rem;
}

.amount-card {
  grid-column: 1 / -1;
}

.amount-breakdown {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.amount-label {
  font-weight: 500;
  color: #64748b;
}

.amount-value {
  font-weight: 600;
  color: #1e293b;
  font-size: 1.1rem;
}

.amount-value.primary {
  color: #6366f1;
  font-size: 1.5rem;
}

.exchange-rate {
  font-family: monospace;
  font-size: 0.95rem;
}

.bank-info {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.bank-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.bank-label {
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.bank-value {
  font-weight: 600;
  color: #1e293b;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.notes-card {
  grid-column: 1 / -1;
}

.notes-content {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  line-height: 1.6;
  color: #374151;
  white-space: pre-wrap;
}

.journal-section,
.timeline-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.section-header {
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-title i {
  color: #6366f1;
}

.journal-entries {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.journal-card {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.journal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: white;
  border-bottom: 1px solid #e2e8f0;
}

.journal-info {
  flex: 1;
}

.journal-title {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.journal-date {
  color: #64748b;
  font-size: 0.875rem;
}

.journal-total {
  font-weight: 600;
  color: #6366f1;
  font-size: 1.1rem;
}

.journal-lines {
  padding: 1rem;
}

.journal-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: white;
  border-radius: 8px;
  margin-bottom: 0.5rem;
  border: 1px solid #e2e8f0;
}

.journal-line:last-child {
  margin-bottom: 0;
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.account-name {
  font-weight: 600;
  color: #1e293b;
}

.account-code {
  font-size: 0.75rem;
  color: #64748b;
}

.line-amounts {
  display: flex;
  gap: 1rem;
}

.debit-amount {
  color: #ef4444;
  font-weight: 600;
}

.credit-amount {
  color: #059669;
  font-weight: 600;
}

.timeline {
  position: relative;
  padding-left: 2rem;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 1rem;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}

.timeline-item {
  position: relative;
  margin-bottom: 2rem;
}

.timeline-marker {
  position: absolute;
  left: -2rem;
  top: 0;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.875rem;
}

.timeline-marker.created {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
}

.timeline-marker.updated {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.timeline-content {
  padding-left: 1rem;
}

.timeline-title {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.timeline-description {
  color: #64748b;
  margin-bottom: 0.25rem;
}

.timeline-date {
  color: #94a3b8;
  font-size: 0.875rem;
}

.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  text-align: center;
  background: white;
  border-radius: 16px;
  padding: 3rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.error-icon {
  font-size: 4rem;
  color: #f59e0b;
  margin-bottom: 1rem;
}

.error-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.error-description {
  color: #64748b;
  margin-bottom: 2rem;
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

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
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

.modal-body {
  padding: 1.5rem;
  text-align: center;
}

.warning-icon {
  font-size: 3rem;
  color: #f59e0b;
  margin-bottom: 1rem;
}

.delete-details {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
  margin: 1rem 0;
  text-align: left;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.detail-row:last-child {
  border-bottom: none;
}

.warning-text {
  color: #ef4444;
  font-weight: 500;
  margin-top: 1rem;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #f1f5f9;
}

@media (max-width: 768px) {
  .payment-detail-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
  
  .header-actions {
    justify-content: center;
  }
  
  .content-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-row {
    grid-template-columns: 1fr;
  }
  
  .receivable-grid {
    grid-template-columns: 1fr;
  }
  
  .amount-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .modal-footer {
    flex-direction: column;
  }
}

@media print {
  .header-actions,
  .modal-overlay {
    display: none !important;
  }
  
  .payment-detail-container {
    background: white !important;
    padding: 0 !important;
  }
  
  .page-header,
  .info-card,
  .journal-section,
  .timeline-section {
    box-shadow: none !important;
    border: 1px solid #ddd !important;
  }
}
</style>