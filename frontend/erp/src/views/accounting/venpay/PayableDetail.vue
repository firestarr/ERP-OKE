<template>
  <div class="payable-form-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <button @click="goBack" class="btn-back">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="page-title">
              <i class="fas fa-file-invoice-dollar"></i>
              {{ isEditing ? 'Edit' : 'Create' }} Vendor Payable
            </h1>
            <p class="page-subtitle">
              {{ isEditing ? 'Update payable information' : 'Create a new vendor payable entry' }}
            </p>
          </div>
        </div>
        <div class="header-actions">
          <button v-if="isEditing" @click="viewPayable" class="btn btn-outline">
            <i class="fas fa-eye"></i>
            View Details
          </button>
        </div>
      </div>
    </div>

    <!-- Progress Indicator -->
    <div class="progress-section">
      <div class="progress-steps">
        <div class="step" :class="{ active: currentStep >= 1, completed: currentStep > 1 }">
          <div class="step-number">1</div>
          <span>Basic Information</span>
        </div>
        <div class="step" :class="{ active: currentStep >= 2, completed: currentStep > 2 }">
          <div class="step-number">2</div>
          <span>Payment Details</span>
        </div>
        <div class="step" :class="{ active: currentStep >= 3 }">
          <div class="step-number">3</div>
          <span>Review & Save</span>
        </div>
      </div>
    </div>

    <!-- Form Content -->
    <div class="form-content">
      <div class="form-grid">
        <!-- Main Form -->
        <div class="main-form">
          <form @submit.prevent="savePayable">
            <!-- Step 1: Basic Information -->
            <div v-show="currentStep === 1" class="form-step">
              <div class="step-header">
                <h3>
                  <i class="fas fa-info-circle"></i>
                  Basic Information
                </h3>
                <p>Enter the basic payable information</p>
              </div>
              
              <div class="form-section">
                <div class="form-row">
                  <div class="form-group">
                    <label for="vendor_id">
                      Vendor *
                      <span class="field-hint">Select the vendor for this payable</span>
                    </label>
                    <div class="select-wrapper">
                      <select 
                        id="vendor_id"
                        v-model="form.vendor_id" 
                        :class="{ 'error': errors.vendor_id }"
                        @change="onVendorChange"
                        required 
                        class="form-select"
                      >
                        <option value="">Choose a vendor...</option>
                        <option 
                          v-for="vendor in vendors" 
                          :key="vendor.vendor_id" 
                          :value="vendor.vendor_id"
                        >
                          {{ vendor.name }} ({{ vendor.vendor_code }})
                        </option>
                      </select>
                      <i class="fas fa-chevron-down select-icon"></i>
                    </div>
                    <span v-if="errors.vendor_id" class="error-message">{{ errors.vendor_id }}</span>
                  </div>
                  
                  <div class="form-group">
                    <label for="invoice_id">
                      Invoice *
                      <span class="field-hint">Select the invoice to create payable for</span>
                    </label>
                    <div class="select-wrapper">
                      <select 
                        id="invoice_id"
                        v-model="form.invoice_id" 
                        :class="{ 'error': errors.invoice_id }"
                        @change="onInvoiceChange"
                        :disabled="!form.vendor_id || loadingInvoices"
                        required 
                        class="form-select"
                      >
                        <option value="">
                          {{ loadingInvoices ? 'Loading invoices...' : 'Choose an invoice...' }}
                        </option>
                        <option 
                          v-for="invoice in filteredInvoices" 
                          :key="invoice.invoice_id" 
                          :value="invoice.invoice_id"
                        >
                          {{ invoice.invoice_number }} - {{ formatCurrency(invoice.total_amount) }}
                        </option>
                      </select>
                      <i class="fas fa-chevron-down select-icon"></i>
                    </div>
                    <span v-if="errors.invoice_id" class="error-message">{{ errors.invoice_id }}</span>
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <label for="amount">
                      Amount *
                      <span class="field-hint">Enter the payable amount</span>
                    </label>
                    <div class="currency-input">
                      <span class="currency-symbol">IDR</span>
                      <input 
                        id="amount"
                        type="number" 
                        v-model="form.amount" 
                        :class="{ 'error': errors.amount }"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required 
                        class="form-input"
                        @blur="validateAmount"
                      >
                    </div>
                    <span v-if="errors.amount" class="error-message">{{ errors.amount }}</span>
                    <div v-if="selectedInvoice" class="amount-info">
                      <small>Invoice Total: {{ formatCurrency(selectedInvoice.total_amount) }}</small>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label for="due_date">
                      Due Date *
                      <span class="field-hint">When is this payment due?</span>
                    </label>
                    <input 
                      id="due_date"
                      type="date" 
                      v-model="form.due_date" 
                      :class="{ 'error': errors.due_date }"
                      :min="today"
                      required 
                      class="form-input"
                    >
                    <span v-if="errors.due_date" class="error-message">{{ errors.due_date }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 2: Payment Details -->
            <div v-show="currentStep === 2" class="form-step">
              <div class="step-header">
                <h3>
                  <i class="fas fa-credit-card"></i>
                  Payment Details
                </h3>
                <p>Configure payment settings and status</p>
              </div>
              
              <div class="form-section">
                <div class="form-row">
                  <div class="form-group">
                    <label for="status">
                      Status *
                      <span class="field-hint">Current status of this payable</span>
                    </label>
                    <div class="status-selector">
                      <div class="status-options">
                        <label 
                          v-for="status in statusOptions" 
                          :key="status.value"
                          class="status-option"
                          :class="{ selected: form.status === status.value }"
                        >
                          <input 
                            type="radio" 
                            :value="status.value" 
                            v-model="form.status"
                            class="status-radio"
                          >
                          <div class="status-card">
                            <div class="status-icon" :class="`status-${status.value}`">
                              <i :class="status.icon"></i>
                            </div>
                            <div class="status-content">
                              <span class="status-title">{{ status.label }}</span>
                              <small class="status-description">{{ status.description }}</small>
                            </div>
                          </div>
                        </label>
                      </div>
                    </div>
                    <span v-if="errors.status" class="error-message">{{ errors.status }}</span>
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <label for="paid_amount">
                      Paid Amount
                      <span class="field-hint">Amount already paid (if any)</span>
                    </label>
                    <div class="currency-input">
                      <span class="currency-symbol">IDR</span>
                      <input 
                        id="paid_amount"
                        type="number" 
                        v-model="form.paid_amount" 
                        step="0.01"
                        min="0"
                        :max="form.amount"
                        placeholder="0.00"
                        class="form-input"
                        @input="calculateBalance"
                      >
                    </div>
                    <div class="balance-info">
                      <small>
                        Remaining Balance: 
                        <strong class="balance-amount">{{ formatCurrency(calculatedBalance) }}</strong>
                      </small>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label>
                      Payment Terms
                      <span class="field-hint">Additional payment information</span>
                    </label>
                    <div class="payment-terms">
                      <div class="term-item">
                        <span class="term-label">Days Until Due:</span>
                        <span class="term-value">{{ daysUntilDue }}</span>
                      </div>
                      <div class="term-item">
                        <span class="term-label">Payment Priority:</span>
                        <span class="term-value priority" :class="paymentPriority.class">
                          {{ paymentPriority.text }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="form-row">
                  <div class="form-group full-width">
                    <label for="notes">
                      Notes
                      <span class="field-hint">Additional information or comments</span>
                    </label>
                    <textarea 
                      id="notes"
                      v-model="form.notes" 
                      rows="4"
                      placeholder="Enter any additional notes or comments..."
                      class="form-textarea"
                    ></textarea>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 3: Review & Save -->
            <div v-show="currentStep === 3" class="form-step">
              <div class="step-header">
                <h3>
                  <i class="fas fa-check-circle"></i>
                  Review & Save
                </h3>
                <p>Review all information before saving</p>
              </div>
              
              <div class="review-section">
                <div class="review-card">
                  <h4>Payable Summary</h4>
                  <div class="review-grid">
                    <div class="review-item">
                      <label>Vendor:</label>
                      <span>{{ selectedVendor?.name }}</span>
                    </div>
                    <div class="review-item">
                      <label>Invoice:</label>
                      <span>{{ selectedInvoice?.invoice_number }}</span>
                    </div>
                    <div class="review-item">
                      <label>Amount:</label>
                      <span class="amount-highlight">{{ formatCurrency(form.amount) }}</span>
                    </div>
                    <div class="review-item">
                      <label>Due Date:</label>
                      <span>{{ formatDate(form.due_date) }}</span>
                    </div>
                    <div class="review-item">
                      <label>Status:</label>
                      <span class="status-badge" :class="`status-${form.status}`">
                        {{ getStatusText(form.status) }}
                      </span>
                    </div>
                    <div class="review-item">
                      <label>Paid Amount:</label>
                      <span>{{ formatCurrency(form.paid_amount || 0) }}</span>
                    </div>
                    <div class="review-item">
                      <label>Balance:</label>
                      <span class="balance-highlight">{{ formatCurrency(calculatedBalance) }}</span>
                    </div>
                  </div>
                  
                  <div v-if="form.notes" class="review-notes">
                    <label>Notes:</label>
                    <p>{{ form.notes }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Form Navigation -->
            <div class="form-navigation">
              <div class="nav-buttons">
                <button 
                  v-if="currentStep > 1"
                  type="button" 
                  @click="previousStep" 
                  class="btn btn-outline"
                >
                  <i class="fas fa-chevron-left"></i>
                  Previous
                </button>
                
                <button 
                  v-if="currentStep < 3"
                  type="button" 
                  @click="nextStep" 
                  class="btn btn-primary"
                  :disabled="!canProceed"
                >
                  Next
                  <i class="fas fa-chevron-right"></i>
                </button>
                
                <button 
                  v-if="currentStep === 3"
                  type="submit" 
                  :disabled="submitting || !isFormValid"
                  class="btn btn-success"
                >
                  <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
                  <i v-else class="fas fa-save"></i>
                  {{ isEditing ? 'Update' : 'Save' }} Payable
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Sidebar Info -->
        <div class="sidebar-info">
          <!-- Vendor Information -->
          <div v-if="selectedVendor" class="info-card">
            <div class="card-header">
              <h4>
                <i class="fas fa-building"></i>
                Vendor Information
              </h4>
            </div>
            <div class="card-content">
              <div class="vendor-details">
                <div class="vendor-avatar">
                  {{ selectedVendor.name.charAt(0).toUpperCase() }}
                </div>
                <div class="vendor-info">
                  <h5>{{ selectedVendor.name }}</h5>
                  <p>{{ selectedVendor.vendor_code }}</p>
                  <div class="vendor-contact">
                    <div v-if="selectedVendor.email">
                      <i class="fas fa-envelope"></i>
                      {{ selectedVendor.email }}
                    </div>
                    <div v-if="selectedVendor.phone">
                      <i class="fas fa-phone"></i>
                      {{ selectedVendor.phone }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Invoice Information -->
          <div v-if="selectedInvoice" class="info-card">
            <div class="card-header">
              <h4>
                <i class="fas fa-file-invoice"></i>
                Invoice Details
              </h4>
            </div>
            <div class="card-content">
              <div class="invoice-details">
                <div class="detail-row">
                  <span>Invoice Number:</span>
                  <strong>{{ selectedInvoice.invoice_number }}</strong>
                </div>
                <div class="detail-row">
                  <span>Date:</span>
                  <span>{{ formatDate(selectedInvoice.invoice_date) }}</span>
                </div>
                <div class="detail-row">
                  <span>Total Amount:</span>
                  <strong>{{ formatCurrency(selectedInvoice.total_amount) }}</strong>
                </div>
                <div class="detail-row">
                  <span>Status:</span>
                  <span class="invoice-status">{{ selectedInvoice.status }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Tips -->
          <div class="info-card tips-card">
            <div class="card-header">
              <h4>
                <i class="fas fa-lightbulb"></i>
                Quick Tips
              </h4>
            </div>
            <div class="card-content">
              <ul class="tips-list">
                <li>
                  <i class="fas fa-check"></i>
                  Ensure the vendor and invoice information is correct
                </li>
                <li>
                  <i class="fas fa-check"></i>
                  Set appropriate due dates to manage cash flow
                </li>
                <li>
                  <i class="fas fa-check"></i>
                  Update status as payments are processed
                </li>
                <li>
                  <i class="fas fa-check"></i>
                  Add notes for internal reference
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'PayableForm',
  data() {
    return {
      isEditing: false,
      payableId: null,
      currentStep: 1,
      submitting: false,
      loadingInvoices: false,
      vendors: [],
      invoices: [],
      form: {
        vendor_id: '',
        invoice_id: '',
        amount: '',
        due_date: '',
        status: 'pending',
        paid_amount: 0,
        notes: ''
      },
      errors: {},
      statusOptions: [
        {
          value: 'pending',
          label: 'Pending',
          description: 'Payment is pending',
          icon: 'fas fa-clock'
        },
        {
          value: 'partial',
          label: 'Partial',
          description: 'Partially paid',
          icon: 'fas fa-hourglass-half'
        },
        {
          value: 'paid',
          label: 'Paid',
          description: 'Fully paid',
          icon: 'fas fa-check-circle'
        },
        {
          value: 'overdue',
          label: 'Overdue',
          description: 'Payment is overdue',
          icon: 'fas fa-exclamation-triangle'
        }
      ]
    }
  },
  computed: {
    today() {
      return new Date().toISOString().split('T')[0]
    },
    
    selectedVendor() {
      return this.vendors.find(v => v.vendor_id == this.form.vendor_id)
    },
    
    selectedInvoice() {
      return this.invoices.find(i => i.invoice_id == this.form.invoice_id)
    },
    
    filteredInvoices() {
      if (!this.form.vendor_id) return []
      return this.invoices.filter(invoice => invoice.vendor_id == this.form.vendor_id)
    },
    
    calculatedBalance() {
      return (this.form.amount || 0) - (this.form.paid_amount || 0)
    },
    
    daysUntilDue() {
      if (!this.form.due_date) return 'N/A'
      const today = new Date()
      const dueDate = new Date(this.form.due_date)
      const diffTime = dueDate - today
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      
      if (diffDays < 0) return `${Math.abs(diffDays)} days overdue`
      if (diffDays === 0) return 'Due today'
      return `${diffDays} days`
    },
    
    paymentPriority() {
      if (!this.form.due_date) return { text: 'N/A', class: '' }
      
      const today = new Date()
      const dueDate = new Date(this.form.due_date)
      const diffDays = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24))
      
      if (diffDays < 0) return { text: 'Overdue', class: 'high' }
      if (diffDays <= 7) return { text: 'High', class: 'high' }
      if (diffDays <= 30) return { text: 'Medium', class: 'medium' }
      return { text: 'Low', class: 'low' }
    },
    
    canProceed() {
      switch (this.currentStep) {
        case 1:
          return this.form.vendor_id && this.form.invoice_id && this.form.amount && this.form.due_date
        case 2:
          return this.form.status
        default:
          return true
      }
    },
    
    isFormValid() {
      return this.form.vendor_id && 
             this.form.invoice_id && 
             this.form.amount && 
             this.form.due_date && 
             this.form.status &&
             Object.keys(this.errors).length === 0
    }
  },
  created() {
    this.payableId = this.$route.params.id
    this.isEditing = !!this.payableId
    
    this.loadVendors()
    this.loadInvoices()
    
    if (this.isEditing) {
      this.loadPayable()
    }
  },
  methods: {
    async loadVendors() {
      try {
        const response = await axios.get('/vendors')
        this.vendors = response.data.data || response.data
      } catch (error) {
        console.error('Error loading vendors:', error)
        this.$toast?.error('Failed to load vendors')
      }
    },
    
    async loadInvoices() {
      this.loadingInvoices = true
      try {
        const response = await axios.get('/vendor-invoices')
        this.invoices = response.data.data || response.data
      } catch (error) {
        console.error('Error loading invoices:', error)
        this.$toast?.error('Failed to load invoices')
      } finally {
        this.loadingInvoices = false
      }
    },
    
    async loadPayable() {
      try {
        const response = await axios.get(`/accounting/vendor-payables/${this.payableId}`)
        const payable = response.data.data
        
        this.form = {
          vendor_id: payable.vendor_id,
          invoice_id: payable.invoice_id,
          amount: payable.amount,
          due_date: payable.due_date,
          status: payable.status,
          paid_amount: payable.paid_amount || 0,
          notes: payable.notes || ''
        }
      } catch (error) {
        console.error('Error loading payable:', error)
        this.$toast?.error('Failed to load payable')
        this.goBack()
      }
    },
    
    onVendorChange() {
      this.form.invoice_id = ''
      this.errors.vendor_id = null
    },
    
    onInvoiceChange() {
      if (this.selectedInvoice && !this.form.amount) {
        this.form.amount = this.selectedInvoice.total_amount
      }
      this.errors.invoice_id = null
    },
    
    validateAmount() {
      if (this.selectedInvoice && this.form.amount > this.selectedInvoice.total_amount) {
        this.errors.amount = 'Amount cannot exceed invoice total'
      } else {
        this.errors.amount = null
      }
    },
    
    calculateBalance() {
      // Balance calculation is handled by computed property
    },
    
    nextStep() {
      if (this.canProceed && this.currentStep < 3) {
        this.currentStep++
      }
    },
    
    previousStep() {
      if (this.currentStep > 1) {
        this.currentStep--
      }
    },
    
    async savePayable() {
      if (!this.isFormValid) return
      
      this.submitting = true
      try {
        const payload = {
          ...this.form,
          balance: this.calculatedBalance
        }
        
        if (this.isEditing) {
          await axios.put(`/accounting/vendor-payables/${this.payableId}`, payload)
          this.$toast?.success('Payable updated successfully')
        } else {
          await axios.post('/accounting/vendor-payables', payload)
          this.$toast?.success('Payable created successfully')
        }
        
        this.$router.push('/payables')
      } catch (error) {
        console.error('Error saving payable:', error)
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors
        }
        this.$toast?.error('Failed to save payable')
      } finally {
        this.submitting = false
      }
    },
    
    viewPayable() {
      this.$router.push(`/payables/${this.payableId}`)
    },
    
    goBack() {
      this.$router.go(-1)
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
      }).format(amount || 0)
    },
    
    formatDate(date) {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    },
    
    getStatusText(status) {
      const statusOption = this.statusOptions.find(s => s.value === status)
      return statusOption ? statusOption.label : status
    }
  }
}
</script>

<style scoped>
/* Main Container */
.payable-form-container {
  padding: 2rem;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  min-height: 100vh;
}

/* Header */
.page-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 24px;
  padding: 2rem;
  margin-bottom: 2rem;
  color: white;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.title-section {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.btn-back {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  border-radius: 12px;
  width: 48px;
  height: 48px;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.btn-back:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateX(-2px);
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-subtitle {
  opacity: 0.9;
  font-size: 1.1rem;
}

/* Progress Steps */
.progress-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.progress-steps {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 2rem;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  position: relative;
}

.step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 24px;
  left: 100%;
  width: 2rem;
  height: 3px;
  background: #e5e7eb;
  transition: background 0.3s ease;
}

.step.completed:not(:last-child)::after {
  background: #10b981;
}

.step-number {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  background: #e5e7eb;
  color: #6b7280;
  transition: all 0.3s ease;
}

.step.active .step-number {
  background: #6366f1;
  color: white;
}

.step.completed .step-number {
  background: #10b981;
  color: white;
}

.step span {
  font-weight: 600;
  color: #6b7280;
  text-align: center;
  transition: color 0.3s ease;
}

.step.active span,
.step.completed span {
  color: #1f2937;
}

/* Form Content */
.form-content {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 2rem;
}

.main-form {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

/* Form Steps */
.form-step {
  animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateX(20px); }
  to { opacity: 1; transform: translateX(0); }
}

.step-header {
  margin-bottom: 2rem;
  text-align: center;
}

.step-header h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.step-header p {
  color: #6b7280;
  font-size: 1rem;
}

/* Form Sections */
.form-section {
  margin-bottom: 2rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.field-hint {
  font-weight: 400;
  font-size: 0.8rem;
  color: #6b7280;
}

/* Form Controls */
.form-input,
.form-select,
.form-textarea {
  padding: 0.75rem;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  font-size: 0.9rem;
  transition: all 0.3s ease;
  background: white;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input.error,
.form-select.error {
  border-color: #ef4444;
}

.select-wrapper {
  position: relative;
}

.select-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

.currency-input {
  position: relative;
  display: flex;
  align-items: center;
}

.currency-symbol {
  position: absolute;
  left: 12px;
  color: #6b7280;
  font-weight: 600;
  z-index: 1;
}

.currency-input .form-input {
  padding-left: 48px;
}

.error-message {
  color: #ef4444;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.amount-info,
.balance-info {
  margin-top: 0.5rem;
}

.amount-info small,
.balance-info small {
  color: #6b7280;
  font-size: 0.8rem;
}

.balance-amount {
  color: #059669;
  font-weight: 600;
}

/* Status Selector */
.status-selector {
  margin-top: 0.5rem;
}

.status-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.status-option {
  cursor: pointer;
}

.status-radio {
  display: none;
}

.status-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  transition: all 0.3s ease;
  background: white;
}

.status-option.selected .status-card {
  border-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
}

.status-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.status-icon.status-pending { background: #f59e0b; }
.status-icon.status-partial { background: #3b82f6; }
.status-icon.status-paid { background: #10b981; }
.status-icon.status-overdue { background: #ef4444; }

.status-content {
  flex: 1;
}

.status-title {
  font-weight: 600;
  color: #1f2937;
  display: block;
  margin-bottom: 0.25rem;
}

.status-description {
  color: #6b7280;
  font-size: 0.8rem;
}

/* Payment Terms */
.payment-terms {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  margin-top: 0.5rem;
}

.term-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.term-label {
  color: #6b7280;
  font-size: 0.9rem;
}

.term-value {
  font-weight: 600;
  color: #1f2937;
}

.priority.high { color: #ef4444; }
.priority.medium { color: #f59e0b; }
.priority.low { color: #10b981; }

/* Review Section */
.review-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.review-card {
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
}

.review-card h4 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 1rem;
}

.review-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.review-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.review-item label {
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: 500;
}

.review-item span {
  font-weight: 600;
  color: #1f2937;
}

.amount-highlight {
  color: #6366f1;
  font-size: 1.1rem;
}

.balance-highlight {
  color: #059669;
  font-size: 1.1rem;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-partial { background: #dbeafe; color: #1e40af; }
.status-paid { background: #d1fae5; color: #065f46; }
.status-overdue { background: #fee2e2; color: #991b1b; }

.review-notes {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.review-notes label {
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: 500;
  margin-bottom: 0.5rem;
  display: block;
}

.review-notes p {
  color: #1f2937;
  line-height: 1.6;
}

/* Form Navigation */
.form-navigation {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
}

.nav-buttons {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

/* Sidebar */
.sidebar-info {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.info-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.card-header {
  padding: 1.5rem;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-bottom: 1px solid #e5e7eb;
}

.card-header h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #1f2937;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-content {
  padding: 1.5rem;
}

/* Vendor Details */
.vendor-details {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.vendor-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.2rem;
}

.vendor-info h5 {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.vendor-info p {
  color: #6b7280;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.vendor-contact {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.vendor-contact div {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: #6b7280;
}

/* Invoice Details */
.invoice-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-row span:first-child {
  color: #6b7280;
  font-size: 0.9rem;
}

.detail-row strong {
  color: #1f2937;
  font-weight: 600;
}

.invoice-status {
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 600;
  background: #dbeafe;
  color: #1e40af;
}

/* Tips Card */
.tips-card {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}

.tips-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.tips-list li {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  font-size: 0.9rem;
  color: #374151;
  line-height: 1.5;
}

.tips-list i {
  color: #10b981;
  margin-top: 0.1rem;
  flex-shrink: 0;
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  font-size: 0.9rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.btn-success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.btn-outline {
  background: white;
  color: #6366f1;
  border: 2px solid #6366f1;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .form-content {
    grid-template-columns: 1fr;
  }
  
  .sidebar-info {
    grid-row: 1;
  }
}

@media (max-width: 768px) {
  .payable-form-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .status-options {
    grid-template-columns: 1fr;
  }
  
  .progress-steps {
    gap: 1rem;
  }
  
  .step span {
    font-size: 0.8rem;
  }
  
  .nav-buttons {
    flex-direction: column;
  }
}
</style>