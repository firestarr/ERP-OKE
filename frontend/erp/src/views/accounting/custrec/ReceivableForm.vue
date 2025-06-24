<template>
  <div class="receivable-form">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <h1 class="page-title">
            <i class="fas fa-receipt"></i>
            {{ isEdit ? 'Edit' : 'Create' }} Receivable
          </h1>
          <p class="page-subtitle">
            {{ isEdit ? 'Update receivable information' : 'Create a new customer receivable' }}
          </p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/receivables" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i>
            Back to List
          </router-link>
        </div>
      </div>
    </div>

    <!-- Form Section -->
    <div class="form-container">
      <form @submit.prevent="submitForm" class="receivable-form-content">
        <!-- Customer & Invoice Selection -->
        <div class="form-section">
          <h3 class="section-title">
            <i class="fas fa-user"></i>
            Customer & Invoice Information
          </h3>
          
          <div class="form-grid">
            <div class="form-group">
              <label for="customer_id" class="required">Customer</label>
              <select 
                id="customer_id"
                v-model="form.customer_id" 
                :disabled="isEdit"
                :class="{ error: errors.customer_id }"
                @change="onCustomerChange"
                required
              >
                <option value="">Select Customer</option>
                <option 
                  v-for="customer in customers" 
                  :key="customer.customer_id" 
                  :value="customer.customer_id"
                >
                  {{ customer.name }}
                </option>
              </select>
              <span v-if="errors.customer_id" class="error-message">{{ errors.customer_id }}</span>
            </div>

            <div class="form-group">
              <label for="invoice_id" class="required">Sales Invoice</label>
              <select 
                id="invoice_id"
                v-model="form.invoice_id" 
                :disabled="isEdit || !form.customer_id"
                :class="{ error: errors.invoice_id }"
                @change="onInvoiceChange"
                required
              >
                <option value="">Select Invoice</option>
                <option 
                  v-for="invoice in availableInvoices" 
                  :key="invoice.invoice_id" 
                  :value="invoice.invoice_id"
                >
                  #{{ invoice.invoice_number }} - {{ formatCurrency(invoice.total_amount) }}
                </option>
              </select>
              <span v-if="errors.invoice_id" class="error-message">{{ errors.invoice_id }}</span>
            </div>
          </div>

          <!-- Customer Details Card (when selected) -->
          <div v-if="selectedCustomer" class="customer-card">
            <div class="customer-header">
              <h4>{{ selectedCustomer.name }}</h4>
              <span class="customer-code">{{ selectedCustomer.customer_code }}</span>
            </div>
            <div class="customer-details">
              <div class="detail-item">
                <i class="fas fa-envelope"></i>
                <span>{{ selectedCustomer.email }}</span>
              </div>
              <div class="detail-item">
                <i class="fas fa-phone"></i>
                <span>{{ selectedCustomer.phone }}</span>
              </div>
              <div class="detail-item">
                <i class="fas fa-map-marker-alt"></i>
                <span>{{ selectedCustomer.address }}</span>
              </div>
            </div>
          </div>

          <!-- Invoice Details Card (when selected) -->
          <div v-if="selectedInvoice" class="invoice-card">
            <div class="invoice-header">
              <h4>Invoice #{{ selectedInvoice.invoice_number }}</h4>
              <span class="invoice-date">{{ formatDate(selectedInvoice.invoice_date) }}</span>
            </div>
            <div class="invoice-details">
              <div class="detail-row">
                <span class="label">Total Amount:</span>
                <span class="value">{{ formatCurrency(selectedInvoice.total_amount) }}</span>
              </div>
              <div class="detail-row">
                <span class="label">Due Date:</span>
                <span class="value">{{ formatDate(selectedInvoice.due_date) }}</span>
              </div>
              <div class="detail-row">
                <span class="label">Status:</span>
                <span class="value status" :class="selectedInvoice.status.toLowerCase()">
                  {{ selectedInvoice.status }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Amount & Terms Section -->
        <div class="form-section">
          <h3 class="section-title">
            <i class="fas fa-dollar-sign"></i>
            Amount & Terms
          </h3>
          
          <div class="form-grid">
            <div class="form-group">
              <label for="amount" class="required">Receivable Amount</label>
              <div class="input-group">
                <span class="input-prefix">$</span>
                <input 
                  id="amount"
                  type="number" 
                  v-model.number="form.amount" 
                  :class="{ error: errors.amount }"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  required
                >
              </div>
              <span v-if="errors.amount" class="error-message">{{ errors.amount }}</span>
              <small class="field-hint">Enter the total receivable amount</small>
            </div>

            <div class="form-group">
              <label for="due_date" class="required">Due Date</label>
              <input 
                id="due_date"
                type="date" 
                v-model="form.due_date" 
                :class="{ error: errors.due_date }"
                required
              >
              <span v-if="errors.due_date" class="error-message">{{ errors.due_date }}</span>
              <small class="field-hint">When this receivable is due</small>
            </div>

            <div class="form-group">
              <label for="status" class="required">Status</label>
              <select 
                id="status"
                v-model="form.status" 
                :class="{ error: errors.status }"
                required
              >
                <option value="">Select Status</option>
                <option value="Outstanding">Outstanding</option>
                <option value="Overdue">Overdue</option>
                <option value="Paid">Paid</option>
                <option value="Partial">Partial</option>
              </select>
              <span v-if="errors.status" class="error-message">{{ errors.status }}</span>
            </div>

            <div v-if="isEdit" class="form-group">
              <label for="paid_amount">Paid Amount</label>
              <div class="input-group">
                <span class="input-prefix">$</span>
                <input 
                  id="paid_amount"
                  type="number" 
                  v-model.number="form.paid_amount" 
                  step="0.01"
                  min="0"
                  :max="form.amount"
                  placeholder="0.00"
                  readonly
                >
              </div>
              <small class="field-hint">This is calculated from payments received</small>
            </div>
          </div>

          <!-- Amount Summary (for edit mode) -->
          <div v-if="isEdit" class="amount-summary">
            <div class="summary-row">
              <span class="label">Total Amount:</span>
              <span class="value">{{ formatCurrency(form.amount) }}</span>
            </div>
            <div class="summary-row">
              <span class="label">Paid Amount:</span>
              <span class="value paid">{{ formatCurrency(form.paid_amount) }}</span>
            </div>
            <div class="summary-row highlight">
              <span class="label">Balance:</span>
              <span class="value">{{ formatCurrency(form.amount - form.paid_amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Additional Information -->
        <div class="form-section">
          <h3 class="section-title">
            <i class="fas fa-info-circle"></i>
            Additional Information
          </h3>
          
          <div class="form-group">
            <label for="notes">Notes</label>
            <textarea 
              id="notes"
              v-model="form.notes" 
              rows="4"
              placeholder="Add any additional notes or comments..."
            ></textarea>
            <small class="field-hint">Optional notes about this receivable</small>
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input 
                type="checkbox" 
                v-model="form.send_reminder"
              >
              <span class="checkmark"></span>
              Send payment reminder to customer
            </label>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <div class="actions-left">
            <button 
              type="button" 
              @click="resetForm" 
              class="btn btn-ghost"
              :disabled="loading"
            >
              <i class="fas fa-undo"></i>
              Reset
            </button>
          </div>
          
          <div class="actions-right">
            <router-link to="/accounting/receivables" class="btn btn-outline">
              Cancel
            </router-link>
            <button 
              type="submit" 
              class="btn btn-primary"
              :disabled="loading || !isFormValid"
            >
              <i v-if="loading" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              {{ loading ? 'Saving...' : (isEdit ? 'Update' : 'Create') }} Receivable
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ReceivableForm',
  props: {
    id: {
      type: [String, Number],
      default: null
    }
  },
  data() {
    return {
      loading: false,
      customers: [],
      availableInvoices: [],
      selectedCustomer: null,
      selectedInvoice: null,
      form: {
        customer_id: '',
        invoice_id: '',
        amount: 0,
        due_date: '',
        status: 'Outstanding',
        paid_amount: 0,
        notes: '',
        send_reminder: false
      },
      errors: {},
      originalForm: {}
    }
  },
  computed: {
    isEdit() {
      return !!this.id
    },
    isFormValid() {
      return this.form.customer_id && 
             this.form.invoice_id && 
             this.form.amount > 0 && 
             this.form.due_date && 
             this.form.status
    }
  },
  async mounted() {
    await this.loadCustomers()
    
    if (this.isEdit) {
      await this.loadReceivable()
    } else {
      // Set default due date to 30 days from now
      const futureDate = new Date()
      futureDate.setDate(futureDate.getDate() + 30)
      this.form.due_date = futureDate.toISOString().split('T')[0]
    }
    
    this.originalForm = { ...this.form }
  },
  methods: {
    async loadCustomers() {
      try {
        const response = await axios.get('/customers')
        this.customers = response.data.data || response.data
      } catch (error) {
        console.error('Error loading customers:', error)
        this.$toast?.error('Failed to load customers')
      }
    },
    
    async loadReceivable() {
      try {
        this.loading = true
        const response = await axios.get(`/accounting/customer-receivables/${this.id}`)
        const receivable = response.data.data
        
        this.form = {
          customer_id: receivable.customer_id,
          invoice_id: receivable.invoice_id,
          amount: receivable.amount,
          due_date: receivable.due_date,
          status: receivable.status,
          paid_amount: receivable.paid_amount,
          notes: receivable.notes || '',
          send_reminder: false
        }
        
        // Load customer and invoice details
        await this.onCustomerChange()
        await this.onInvoiceChange()
        
      } catch (error) {
        console.error('Error loading receivable:', error)
        this.$toast?.error('Failed to load receivable data')
        this.$router.push('/accounting/receivables')
      } finally {
        this.loading = false
      }
    },
    
    async onCustomerChange() {
      if (!this.form.customer_id) {
        this.selectedCustomer = null
        this.availableInvoices = []
        this.selectedInvoice = null
        return
      }
      
      // Get customer details
      this.selectedCustomer = this.customers.find(c => c.customer_id == this.form.customer_id)
      
      // Load available invoices for this customer
      try {
        const response = await axios.get(`/customers/${this.form.customer_id}/invoices`, {
          params: { status: 'Unpaid' }
        })
        this.availableInvoices = response.data.data || response.data
      } catch (error) {
        console.error('Error loading invoices:', error)
        this.availableInvoices = []
      }
      
      // Clear invoice selection if not in edit mode
      if (!this.isEdit) {
        this.form.invoice_id = ''
        this.selectedInvoice = null
      }
    },
    
    async onInvoiceChange() {
      if (!this.form.invoice_id) {
        this.selectedInvoice = null
        return
      }
      
      // Get invoice details
      try {
        const response = await axios.get(`/invoices/${this.form.invoice_id}`)
        this.selectedInvoice = response.data.data
        
        // Auto-fill amount and due date if not in edit mode
        if (!this.isEdit && this.selectedInvoice) {
          this.form.amount = this.selectedInvoice.total_amount
          this.form.due_date = this.selectedInvoice.due_date
        }
      } catch (error) {
        console.error('Error loading invoice:', error)
        this.selectedInvoice = null
      }
    },
    
    async submitForm() {
      if (!this.isFormValid) return
      
      this.loading = true
      this.errors = {}
      
      try {
        const formData = { ...this.form }
        delete formData.send_reminder // Remove UI-only field
        
        let response
        if (this.isEdit) {
          response = await axios.put(`/accounting/customer-receivables/${this.id}`, formData)
        } else {
          response = await axios.post('/accounting/customer-receivables', formData)
        }
        
        this.$toast?.success(response.data.message || `Receivable ${this.isEdit ? 'updated' : 'created'} successfully`)
        
        // Send reminder if requested
        if (this.form.send_reminder && !this.isEdit) {
          await this.sendPaymentReminder(response.data.data.receivable_id)
        }
        
        this.$router.push('/accounting/receivables')
        
      } catch (error) {
        console.error('Error saving receivable:', error)
        
        if (error.response?.status === 422) {
          this.errors = error.response.data.errors || {}
        } else {
          this.$toast?.error('Failed to save receivable')
        }
      } finally {
        this.loading = false
      }
    },
    
    async sendPaymentReminder(receivableId) {
      try {
        await axios.post(`/accounting/customer-receivables/${receivableId}/send-reminder`)
        this.$toast?.success('Payment reminder sent to customer')
      } catch (error) {
        console.error('Error sending reminder:', error)
        this.$toast?.warning('Receivable created but failed to send reminder')
      }
    },
    
    resetForm() {
      if (this.isEdit) {
        this.form = { ...this.originalForm }
      } else {
        this.form = {
          customer_id: '',
          invoice_id: '',
          amount: 0,
          due_date: '',
          status: 'Outstanding',
          paid_amount: 0,
          notes: '',
          send_reminder: false
        }
        
        // Reset default due date
        const futureDate = new Date()
        futureDate.setDate(futureDate.getDate() + 30)
        this.form.due_date = futureDate.toISOString().split('T')[0]
      }
      
      this.errors = {}
      this.selectedCustomer = null
      this.selectedInvoice = null
      this.availableInvoices = []
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
.receivable-form {
  max-width: 1000px;
  margin: 0 auto;
  padding: 2rem;
  background: var(--gray-50);
  min-height: 100vh;
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

/* Form Container */
.form-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.receivable-form-content {
  padding: 2rem;
}

/* Form Sections */
.form-section {
  margin-bottom: 2.5rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid var(--gray-200);
}

.form-section:last-of-type {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.125rem;
  color: var(--gray-800);
  margin-bottom: 1.5rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid var(--primary-color);
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

/* Form Groups */
.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: var(--gray-700);
  font-size: 0.875rem;
}

.form-group label.required::after {
  content: '*';
  color: var(--danger-color);
  margin-left: 0.25rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: all 0.2s;
  background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group input.error,
.form-group select.error,
.form-group textarea.error {
  border-color: var(--danger-color);
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.form-group input:disabled,
.form-group select:disabled {
  background: var(--gray-100);
  color: var(--gray-500);
  cursor: not-allowed;
}

/* Input Group */
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

/* Error Messages */
.error-message {
  color: var(--danger-color);
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

/* Field Hints */
.field-hint {
  color: var(--gray-500);
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

/* Customer & Invoice Cards */
.customer-card,
.invoice-card {
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.customer-header,
.invoice-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.customer-header h4,
.invoice-header h4 {
  color: var(--gray-800);
  margin: 0;
}

.customer-code,
.invoice-date {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.customer-details {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--gray-600);
  font-size: 0.875rem;
}

.detail-item i {
  color: var(--gray-400);
  width: 12px;
}

.invoice-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 0.75rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-row .label {
  color: var(--gray-600);
  font-size: 0.875rem;
}

.detail-row .value {
  font-weight: 500;
  color: var(--gray-800);
}

.detail-row .value.status {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.detail-row .value.status.paid {
  background: #d1fae5;
  color: #065f46;
}

.detail-row .value.status.unpaid {
  background: #fee2e2;
  color: #dc2626;
}

/* Amount Summary */
.amount-summary {
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.summary-row:last-child {
  margin-bottom: 0;
}

.summary-row.highlight {
  padding-top: 0.5rem;
  border-top: 1px solid var(--gray-300);
  font-weight: 600;
}

.summary-row .label {
  color: var(--gray-600);
}

.summary-row .value {
  font-weight: 500;
  color: var(--gray-800);
}

.summary-row .value.paid {
  color: var(--success-color);
}

/* Checkbox */
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--gray-700);
}

.checkbox-label input[type="checkbox"] {
  display: none;
}

.checkmark {
  width: 18px;
  height: 18px;
  border: 2px solid var(--gray-300);
  border-radius: 4px;
  position: relative;
  transition: all 0.2s;
}

.checkbox-label input[type="checkbox"]:checked + .checkmark {
  background: var(--primary-color);
  border-color: var(--primary-color);
}

.checkbox-label input[type="checkbox"]:checked + .checkmark::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 12px;
  font-weight: bold;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 2rem;
  border-top: 1px solid var(--gray-200);
}

.actions-right {
  display: flex;
  gap: 1rem;
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
@media (max-width: 768px) {
  .receivable-form {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .customer-details {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .invoice-details {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .actions-right {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .receivable-form-content {
    padding: 1rem;
  }
  
  .actions-right {
    flex-direction: column;
    gap: 0.5rem;
  }
}
</style>