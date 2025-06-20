<template>
  <div class="receivable-detail">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading receivable details...</p>
    </div>

    <!-- Main Content -->
    <div v-else-if="receivable" class="detail-content">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="header-left">
            <h1 class="page-title">
              <i class="fas fa-receipt"></i>
              Receivable Details
            </h1>
            <p class="page-subtitle">
              {{ receivable.customer?.name }} - Invoice #{{ receivable.sales_invoice?.invoice_number }}
            </p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/receivables" class="btn btn-ghost">
              <i class="fas fa-arrow-left"></i>
              Back to List
            </router-link>
            <button @click="printReceivable" class="btn btn-outline">
              <i class="fas fa-print"></i>
              Print
            </button>
          </div>
        </div>
      </div>

      <!-- Status Alert -->
      <div class="status-alert" :class="getStatusAlertClass(receivable.status)">
        <div class="alert-content">
          <i :class="getStatusIcon(receivable.status)"></i>
          <div>
            <h4>{{ getStatusMessage(receivable.status) }}</h4>
            <p>{{ getStatusDescription(receivable.status) }}</p>
          </div>
        </div>
        <div class="alert-actions">
          <button 
            v-if="receivable.status !== 'Paid'" 
            @click="showAddPaymentModal = true"
            class="btn btn-sm btn-primary"
          >
            <i class="fas fa-plus"></i>
            Add Payment
          </button>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="content-grid">
        <!-- Left Column -->
        <div class="left-column">
          <!-- Receivable Information -->
          <div class="info-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-info-circle"></i>
                Receivable Information
              </h3>
              <div class="card-actions">
                <router-link 
                  :to="`/accounting/receivables/${receivable.receivable_id}/edit`"
                  class="btn btn-sm btn-outline"
                >
                  <i class="fas fa-edit"></i>
                  Edit
                </router-link>
              </div>
            </div>
            <div class="card-content">
              <div class="info-grid">
                <div class="info-item">
                  <span class="label">Receivable ID</span>
                  <span class="value">#{{ receivable.receivable_id }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Status</span>
                  <span class="value">
                    <span class="status-badge" :class="receivable.status.toLowerCase()">
                      {{ receivable.status }}
                    </span>
                  </span>
                </div>
                <div class="info-item">
                  <span class="label">Total Amount</span>
                  <span class="value amount">{{ formatCurrency(receivable.amount) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Paid Amount</span>
                  <span class="value amount paid">{{ formatCurrency(receivable.paid_amount) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Balance Due</span>
                  <span class="value amount balance">{{ formatCurrency(receivable.balance) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Due Date</span>
                  <span class="value" :class="{ overdue: isOverdue(receivable.due_date) }">
                    {{ formatDate(receivable.due_date) }}
                    <span v-if="isOverdue(receivable.due_date)" class="overdue-badge">
                      {{ getDaysOverdue(receivable.due_date) }} days overdue
                    </span>
                  </span>
                </div>
                <div class="info-item">
                  <span class="label">Created Date</span>
                  <span class="value">{{ formatDate(receivable.created_at) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Last Updated</span>
                  <span class="value">{{ formatDate(receivable.updated_at) }}</span>
                </div>
              </div>
              
              <div v-if="receivable.notes" class="notes-section">
                <h4>Notes</h4>
                <p>{{ receivable.notes }}</p>
              </div>
            </div>
          </div>

          <!-- Customer Information -->
          <div class="info-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-user"></i>
                Customer Information
              </h3>
              <div class="card-actions">
                <router-link 
                  :to="`/customers/${receivable.customer_id}`"
                  class="btn btn-sm btn-ghost"
                >
                  <i class="fas fa-external-link-alt"></i>
                  View Customer
                </router-link>
              </div>
            </div>
            <div class="card-content">
              <div class="customer-info">
                <div class="customer-header">
                  <h4>{{ receivable.customer?.name }}</h4>
                  <span class="customer-code">{{ receivable.customer?.customer_code }}</span>
                </div>
                <div class="customer-details">
                  <div class="detail-item">
                    <i class="fas fa-envelope"></i>
                    <span>{{ receivable.customer?.email || 'No email' }}</span>
                  </div>
                  <div class="detail-item">
                    <i class="fas fa-phone"></i>
                    <span>{{ receivable.customer?.phone || 'No phone' }}</span>
                  </div>
                  <div class="detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>{{ receivable.customer?.address || 'No address' }}</span>
                  </div>
                  <div class="detail-item">
                    <i class="fas fa-building"></i>
                    <span>{{ receivable.customer?.company || 'No company' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Invoice Information -->
          <div class="info-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-file-invoice"></i>
                Related Invoice
              </h3>
              <div class="card-actions">
                <router-link 
                  :to="`/sales/invoices/${receivable.invoice_id}`"
                  class="btn btn-sm btn-ghost"
                >
                  <i class="fas fa-external-link-alt"></i>
                  View Invoice
                </router-link>
              </div>
            </div>
            <div class="card-content">
              <div class="invoice-info">
                <div class="info-grid">
                  <div class="info-item">
                    <span class="label">Invoice Number</span>
                    <span class="value">#{{ receivable.sales_invoice?.invoice_number }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Invoice Date</span>
                    <span class="value">{{ formatDate(receivable.sales_invoice?.invoice_date) }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Invoice Total</span>
                    <span class="value amount">{{ formatCurrency(receivable.sales_invoice?.total_amount) }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Invoice Status</span>
                    <span class="value">
                      <span class="status-badge" :class="receivable.sales_invoice?.status?.toLowerCase()">
                        {{ receivable.sales_invoice?.status }}
                      </span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="right-column">
          <!-- Quick Actions -->
          <div class="actions-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-bolt"></i>
                Quick Actions
              </h3>
            </div>
            <div class="card-content">
              <div class="action-buttons">
                <button 
                  @click="showAddPaymentModal = true"
                  class="action-btn payment"
                  :disabled="receivable.status === 'Paid'"
                >
                  <i class="fas fa-dollar-sign"></i>
                  <span>Add Payment</span>
                </button>
                
                <button 
                  @click="sendReminder"
                  class="action-btn reminder"
                  :disabled="receivable.status === 'Paid'"
                >
                  <i class="fas fa-envelope"></i>
                  <span>Send Reminder</span>
                </button>
                
                <button 
                  @click="generateStatement"
                  class="action-btn statement"
                >
                  <i class="fas fa-file-alt"></i>
                  <span>Generate Statement</span>
                </button>
                
                <button 
                  @click="downloadPDF"
                  class="action-btn download"
                >
                  <i class="fas fa-download"></i>
                  <span>Download PDF</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Payment History -->
          <div class="payments-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-history"></i>
                Payment History
              </h3>
              <span class="payments-count">{{ payments.length }} payments</span>
            </div>
            <div class="card-content">
              <div v-if="payments.length === 0" class="empty-payments">
                <i class="fas fa-credit-card"></i>
                <p>No payments recorded yet</p>
              </div>
              
              <div v-else class="payments-list">
                <div 
                  v-for="payment in payments" 
                  :key="payment.payment_id"
                  class="payment-item"
                >
                  <div class="payment-header">
                    <span class="payment-amount">{{ formatCurrency(payment.amount) }}</span>
                    <span class="payment-date">{{ formatDate(payment.payment_date) }}</span>
                  </div>
                  <div class="payment-details">
                    <div class="payment-method">
                      <i class="fas fa-credit-card"></i>
                      {{ payment.payment_method || 'Cash' }}
                    </div>
                    <div v-if="payment.reference" class="payment-reference">
                      Ref: {{ payment.reference }}
                    </div>
                  </div>
                  <div v-if="payment.notes" class="payment-notes">
                    {{ payment.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Activity Timeline -->
          <div class="timeline-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-timeline"></i>
                Activity Timeline
              </h3>
            </div>
            <div class="card-content">
              <div class="timeline">
                <div class="timeline-item">
                  <div class="timeline-marker created"></div>
                  <div class="timeline-content">
                    <h5>Receivable Created</h5>
                    <p>{{ formatDate(receivable.created_at) }}</p>
                  </div>
                </div>
                
                <div 
                  v-for="payment in payments" 
                  :key="`payment-${payment.payment_id}`"
                  class="timeline-item"
                >
                  <div class="timeline-marker payment"></div>
                  <div class="timeline-content">
                    <h5>Payment Received</h5>
                    <p>{{ formatCurrency(payment.amount) }} on {{ formatDate(payment.payment_date) }}</p>
                  </div>
                </div>
                
                <div v-if="receivable.status === 'Paid'" class="timeline-item">
                  <div class="timeline-marker completed"></div>
                  <div class="timeline-content">
                    <h5>Fully Paid</h5>
                    <p>Receivable marked as paid</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="error-state">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Receivable Not Found</h3>
      <p>The requested receivable could not be found.</p>
      <router-link to="/accounting/receivables" class="btn btn-primary">
        Back to Receivables
      </router-link>
    </div>

    <!-- Add Payment Modal -->
    <div v-if="showAddPaymentModal" class="modal-overlay" @click="closeAddPaymentModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h3>Add Payment</h3>
          <button @click="closeAddPaymentModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <form @submit.prevent="addPayment" class="modal-content">
          <div class="form-group">
            <label>Payment Amount</label>
            <div class="input-group">
              <span class="input-prefix">$</span>
              <input 
                type="number" 
                v-model.number="paymentForm.amount"
                :max="receivable.balance"
                step="0.01"
                min="0.01"
                required
              >
            </div>
            <small>Maximum: {{ formatCurrency(receivable.balance) }}</small>
          </div>
          
          <div class="form-group">
            <label>Payment Date</label>
            <input 
              type="date" 
              v-model="paymentForm.payment_date"
              required
            >
          </div>
          
          <div class="form-group">
            <label>Payment Method</label>
            <select v-model="paymentForm.payment_method" required>
              <option value="">Select Method</option>
              <option value="Cash">Cash</option>
              <option value="Check">Check</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Credit Card">Credit Card</option>
              <option value="Other">Other</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Reference</label>
            <input 
              type="text" 
              v-model="paymentForm.reference"
              placeholder="Check number, transaction ID, etc."
            >
          </div>
          
          <div class="form-group">
            <label>Notes</label>
            <textarea 
              v-model="paymentForm.notes"
              rows="3"
              placeholder="Additional notes about this payment..."
            ></textarea>
          </div>
          
          <div class="modal-actions">
            <button type="button" @click="closeAddPaymentModal" class="btn btn-ghost">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="paymentLoading">
              <i v-if="paymentLoading" class="fas fa-spinner fa-spin"></i>
              Add Payment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ReceivableDetail',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      loading: true,
      receivable: null,
      payments: [],
      showAddPaymentModal: false,
      paymentLoading: false,
      paymentForm: {
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        reference: '',
        notes: ''
      }
    }
  },
  async mounted() {
    await this.loadReceivable()
    await this.loadPayments()
  },
  methods: {
    async loadReceivable() {
      try {
        this.loading = true
        const response = await axios.get(`/api/accounting/customer-receivables/${this.id}`)
        this.receivable = response.data.data
      } catch (error) {
        console.error('Error loading receivable:', error)
        this.$toast?.error('Failed to load receivable details')
      } finally {
        this.loading = false
      }
    },
    
    async loadPayments() {
      try {
        const response = await axios.get(`/api/accounting/receivable-payments`, {
          params: { receivable_id: this.id }
        })
        this.payments = response.data.data || response.data
      } catch (error) {
        console.error('Error loading payments:', error)
        this.payments = []
      }
    },
    
    async addPayment() {
      if (!this.paymentForm.amount || this.paymentForm.amount <= 0) return
      
      this.paymentLoading = true
      
      try {
        const paymentData = {
          receivable_id: this.id,
          ...this.paymentForm
        }
        
        await axios.post('/api/accounting/receivable-payments', paymentData)
        
        this.$toast?.success('Payment added successfully')
        this.closeAddPaymentModal()
        await this.loadReceivable()
        await this.loadPayments()
        
      } catch (error) {
        console.error('Error adding payment:', error)
        this.$toast?.error('Failed to add payment')
      } finally {
        this.paymentLoading = false
      }
    },
    
    closeAddPaymentModal() {
      this.showAddPaymentModal = false
      this.paymentForm = {
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        reference: '',
        notes: ''
      }
    },
    
    async sendReminder() {
      try {
        await axios.post(`/api/accounting/customer-receivables/${this.id}/send-reminder`)
        this.$toast?.success('Payment reminder sent successfully')
      } catch (error) {
        console.error('Error sending reminder:', error)
        this.$toast?.error('Failed to send payment reminder')
      }
    },
    
    async generateStatement() {
      this.$router.push(`/accounting/receivables/${this.id}/statement`)
    },
    
    async downloadPDF() {
      try {
        const response = await axios.get(`/api/accounting/customer-receivables/${this.id}/pdf`, {
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `receivable-${this.id}.pdf`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
      } catch (error) {
        console.error('Error downloading PDF:', error)
        this.$toast?.error('Failed to download PDF')
      }
    },
    
    printReceivable() {
      window.print()
    },
    
    getStatusAlertClass(status) {
      switch (status.toLowerCase()) {
        case 'paid': return 'alert-success'
        case 'overdue': return 'alert-danger'
        case 'outstanding': return 'alert-warning'
        default: return 'alert-info'
      }
    },
    
    getStatusIcon(status) {
      switch (status.toLowerCase()) {
        case 'paid': return 'fas fa-check-circle'
        case 'overdue': return 'fas fa-exclamation-triangle'
        case 'outstanding': return 'fas fa-clock'
        default: return 'fas fa-info-circle'
      }
    },
    
    getStatusMessage(status) {
      switch (status.toLowerCase()) {
        case 'paid': return 'Fully Paid'
        case 'overdue': return 'Payment Overdue'
        case 'outstanding': return 'Payment Outstanding'
        default: return 'Status Unknown'
      }
    },
    
    getStatusDescription(status) {
      switch (status.toLowerCase()) {
        case 'paid': return 'This receivable has been fully paid.'
        case 'overdue': return 'This receivable is past its due date.'
        case 'outstanding': return 'This receivable is awaiting payment.'
        default: return 'Status information unavailable.'
      }
    },
    
    isOverdue(dueDate) {
      return new Date(dueDate) < new Date()
    },
    
    getDaysOverdue(dueDate) {
      const today = new Date()
      const due = new Date(dueDate)
      const diffTime = today - due
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }
  }
}
</script>

<style scoped>
.receivable-detail {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
  background: var(--gray-50);
  min-height: 100vh;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--gray-200);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Error State */
.error-state {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.error-state i {
  font-size: 3rem;
  color: var(--danger-color);
  margin-bottom: 1rem;
}

/* Header Section */
.page-header {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: var(--gray-800);
  margin-bottom: 0.5rem;
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

/* Status Alert */
.status-alert {
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-alert.alert-success {
  background: #d1fae5;
  border: 1px solid #a7f3d0;
  color: #065f46;
}

.status-alert.alert-warning {
  background: #fef3c7;
  border: 1px solid #fde68a;
  color: #92400e;
}

.status-alert.alert-danger {
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #dc2626;
}

.status-alert.alert-info {
  background: #dbeafe;
  border: 1px solid #bfdbfe;
  color: #1d4ed8;
}

.alert-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.alert-content i {
  font-size: 1.5rem;
}

.alert-content h4 {
  margin-bottom: 0.25rem;
}

.alert-content p {
  margin: 0;
  opacity: 0.8;
}

/* Content Grid */
.content-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
}

/* Card Styles */
.info-card,
.actions-card,
.payments-card,
.timeline-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  margin-bottom: 2rem;
  overflow: hidden;
}

.card-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
  color: var(--gray-800);
}

.card-content {
  padding: 1.5rem;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-item .label {
  font-size: 0.75rem;
  color: var(--gray-500);
  text-transform: uppercase;
  font-weight: 500;
}

.info-item .value {
  font-weight: 500;
  color: var(--gray-800);
}

.info-item .value.amount {
  font-size: 1.125rem;
}

.info-item .value.amount.paid {
  color: var(--success-color);
}

.info-item .value.amount.balance {
  color: var(--warning-color);
}

.info-item .value.overdue {
  color: var(--danger-color);
}

.overdue-badge {
  display: block;
  font-size: 0.75rem;
  font-weight: 400;
  margin-top: 0.25rem;
}

/* Status Badge */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.outstanding {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.overdue {
  background: #fee2e2;
  color: #dc2626;
}

.status-badge.paid {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.partial {
  background: #dbeafe;
  color: #1d4ed8;
}

/* Notes Section */
.notes-section {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

.notes-section h4 {
  margin-bottom: 0.75rem;
  color: var(--gray-700);
}

.notes-section p {
  color: var(--gray-600);
  line-height: 1.6;
}

/* Customer Info */
.customer-info {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.customer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.customer-header h4 {
  margin: 0;
  color: var(--gray-800);
}

.customer-code {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.customer-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: var(--gray-600);
  font-size: 0.875rem;
}

.detail-item i {
  color: var(--gray-400);
  width: 14px;
}

/* Action Buttons */
.action-buttons {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
}

.action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  color: var(--gray-700);
}

.action-btn:hover:not(:disabled) {
  border-color: var(--primary-color);
  background: var(--gray-50);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn i {
  font-size: 1.25rem;
}

.action-btn.payment {
  border-color: var(--success-color);
  color: var(--success-color);
}

.action-btn.reminder {
  border-color: var(--warning-color);
  color: var(--warning-color);
}

.action-btn.statement {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.action-btn.download {
  border-color: var(--gray-400);
  color: var(--gray-600);
}

/* Payments */
.payments-count {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.empty-payments {
  text-align: center;
  padding: 2rem;
  color: var(--gray-500);
}

.empty-payments i {
  font-size: 2rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.payments-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.payment-item {
  padding: 1rem;
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  background: var(--gray-50);
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.payment-amount {
  font-weight: 600;
  color: var(--success-color);
}

.payment-date {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.payment-details {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.payment-method,
.payment-reference {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--gray-600);
  font-size: 0.875rem;
}

.payment-notes {
  color: var(--gray-600);
  font-size: 0.875rem;
  font-style: italic;
}

/* Timeline */
.timeline {
  position: relative;
  padding-left: 2rem;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 12px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--gray-300);
}

.timeline-item {
  position: relative;
  margin-bottom: 1.5rem;
}

.timeline-marker {
  position: absolute;
  left: -2rem;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 3px solid white;
  box-shadow: 0 0 0 2px var(--gray-300);
}

.timeline-marker.created {
  background: var(--primary-color);
}

.timeline-marker.payment {
  background: var(--success-color);
}

.timeline-marker.completed {
  background: var(--success-color);
}

.timeline-content h5 {
  margin-bottom: 0.25rem;
  color: var(--gray-800);
}

.timeline-content p {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

/* Modal */
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

.modal {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-800);
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: none;
  color: var(--gray-400);
  cursor: pointer;
  border-radius: 50%;
  transition: all 0.2s;
}

.close-btn:hover {
  background: var(--gray-100);
  color: var(--gray-600);
}

.modal-content {
  padding: 1.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

/* Form Styles */
.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: var(--gray-700);
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.input-group {
  position: relative;
  display: flex;
  align-items: center;
}

.input-prefix {
  position: absolute;
  left: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
  z-index: 1;
}

.input-group input {
  padding-left: 2rem;
}

.form-group small {
  color: var(--gray-500);
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}

/* Button Styles */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.75rem;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
}

.btn-outline {
  background: white;
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
}

.btn-outline:hover {
  background: var(--primary-color);
  color: white;
}

.btn-ghost {
  background: transparent;
  color: var(--gray-600);
  border: 1px solid var(--gray-300);
}

.btn-ghost:hover:not(:disabled) {
  background: var(--gray-100);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .receivable-detail {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: center;
  }
  
  .status-alert {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .action-buttons {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .customer-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .payment-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
  
  .payment-details {
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .modal {
    width: 95%;
    margin: 1rem;
  }
  
  .modal-actions {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .action-buttons {
    grid-template-columns: 1fr;
  }
  
  .modal-header {
    padding: 1rem;
  }
  
  .modal-content {
    padding: 1rem;
  }
}
</style>