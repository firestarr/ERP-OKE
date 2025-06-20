<template>
  <div class="record-payment-container">
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
            <span class="breadcrumb-current">Record Payment</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-plus-circle"></i>
            Record New Payment
          </h1>
          <p class="page-subtitle">Record customer payment against receivable</p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/receivable-payments" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to List
          </router-link>
        </div>
      </div>
    </div>

    <!-- Payment Form -->
    <div class="form-container">
      <form @submit.prevent="submitPayment" class="payment-form">
        <!-- Customer & Receivable Selection -->
        <div class="form-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-user"></i>
              Customer & Receivable Information
            </h3>
            <p class="section-description">Select customer and outstanding receivable</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label required">Customer</label>
              <select 
                v-model="form.customerId" 
                @change="fetchCustomerReceivables"
                class="form-select"
                :class="{ 'error': errors.customerId }"
                required
              >
                <option value="">Select Customer</option>
                <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                  {{ customer.name }} ({{ customer.customer_code }})
                </option>
              </select>
              <span v-if="errors.customerId" class="error-message">{{ errors.customerId }}</span>
            </div>

            <div class="form-group">
              <label class="form-label required">Outstanding Receivable</label>
              <select 
                v-model="form.receivableId" 
                @change="onReceivableChange"
                class="form-select"
                :class="{ 'error': errors.receivableId }"
                :disabled="!form.customerId || loadingReceivables"
                required
              >
                <option value="">Select Receivable</option>
                <option v-for="receivable in customerReceivables" :key="receivable.receivable_id" :value="receivable.receivable_id">
                  Invoice #{{ receivable.invoice_id }} - {{ formatCurrency(receivable.balance) }} (Due: {{ formatDate(receivable.due_date) }})
                </option>
              </select>
              <span v-if="errors.receivableId" class="error-message">{{ errors.receivableId }}</span>
              <div v-if="loadingReceivables" class="loading-text">
                <i class="fas fa-spinner fa-spin"></i>
                Loading receivables...
              </div>
            </div>
          </div>

          <!-- Selected Receivable Details -->
          <div v-if="selectedReceivable" class="receivable-details">
            <div class="details-card">
              <h4 class="details-title">Selected Receivable Details</h4>
              <div class="details-grid">
                <div class="detail-item">
                  <span class="detail-label">Invoice Number</span>
                  <span class="detail-value">#{{ selectedReceivable.invoice_id }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Total Amount</span>
                  <span class="detail-value">{{ formatCurrency(selectedReceivable.amount) }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Paid Amount</span>
                  <span class="detail-value">{{ formatCurrency(selectedReceivable.paid_amount) }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Outstanding Balance</span>
                  <span class="detail-value outstanding">{{ formatCurrency(selectedReceivable.balance) }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Due Date</span>
                  <span class="detail-value" :class="{ 'overdue': isOverdue(selectedReceivable.due_date) }">
                    {{ formatDate(selectedReceivable.due_date) }}
                    <span v-if="isOverdue(selectedReceivable.due_date)" class="overdue-badge">Overdue</span>
                  </span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Currency</span>
                  <span class="detail-value">{{ selectedReceivable.currency || 'USD' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Details -->
        <div class="form-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-money-bill-wave"></i>
              Payment Details
            </h3>
            <p class="section-description">Enter payment amount and details</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label required">Payment Date</label>
              <input 
                type="date" 
                v-model="form.paymentDate"
                class="form-input"
                :class="{ 'error': errors.paymentDate }"
                required
              />
              <span v-if="errors.paymentDate" class="error-message">{{ errors.paymentDate }}</span>
            </div>

            <div class="form-group">
              <label class="form-label required">Payment Method</label>
              <select 
                v-model="form.paymentMethod"
                class="form-select"
                :class="{ 'error': errors.paymentMethod }"
                required
              >
                <option value="">Select Payment Method</option>
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Check">Check</option>
                <option value="Wire Transfer">Wire Transfer</option>
                <option value="Online Payment">Online Payment</option>
              </select>
              <span v-if="errors.paymentMethod" class="error-message">{{ errors.paymentMethod }}</span>
            </div>

            <div class="form-group">
              <label class="form-label required">Payment Amount</label>
              <div class="amount-input-wrapper">
                <span class="currency-prefix">{{ form.currency || 'USD' }}</span>
                <input 
                  type="number" 
                  step="0.01"
                  v-model="form.amount"
                  @input="calculateRemainingBalance"
                  class="form-input amount-input"
                  :class="{ 'error': errors.amount }"
                  :max="selectedReceivable?.balance"
                  placeholder="0.00"
                  required
                />
              </div>
              <span v-if="errors.amount" class="error-message">{{ errors.amount }}</span>
              <div v-if="selectedReceivable && form.amount" class="amount-info">
                <div class="amount-detail">
                  <span>Remaining Balance: </span>
                  <span class="remaining-amount">{{ formatCurrency(remainingBalance) }}</span>
                </div>
                <div v-if="form.amount > selectedReceivable.balance" class="overpayment-warning">
                  <i class="fas fa-exclamation-triangle"></i>
                  Payment amount exceeds outstanding balance
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Currency</label>
              <select 
                v-model="form.currency"
                class="form-select"
                :class="{ 'error': errors.currency }"
                @change="onCurrencyChange"
              >
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
                <option value="IDR">IDR - Indonesian Rupiah</option>
                <option value="JPY">JPY - Japanese Yen</option>
                <option value="SGD">SGD - Singapore Dollar</option>
              </select>
              <span v-if="errors.currency" class="error-message">{{ errors.currency }}</span>
            </div>

            <div class="form-group full-width">
              <label class="form-label">Reference Number</label>
              <input 
                type="text" 
                v-model="form.referenceNumber"
                class="form-input"
                :class="{ 'error': errors.referenceNumber }"
                placeholder="Transaction reference, check number, etc."
              />
              <span v-if="errors.referenceNumber" class="error-message">{{ errors.referenceNumber }}</span>
            </div>

            <div class="form-group full-width">
              <label class="form-label">Notes</label>
              <textarea 
                v-model="form.notes"
                class="form-textarea"
                :class="{ 'error': errors.notes }"
                placeholder="Additional payment notes or remarks..."
                rows="3"
              ></textarea>
              <span v-if="errors.notes" class="error-message">{{ errors.notes }}</span>
            </div>
          </div>
        </div>

        <!-- Currency Exchange (if applicable) -->
        <div v-if="showExchangeSection" class="form-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-exchange-alt"></i>
              Currency Exchange
            </h3>
            <p class="section-description">Exchange rate details for foreign currency payment</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label required">Exchange Rate</label>
              <div class="exchange-rate-wrapper">
                <span class="rate-prefix">1 {{ form.currency }} =</span>
                <input 
                  type="number" 
                  step="0.000001"
                  v-model="form.exchangeRate"
                  @input="calculateBaseCurrencyAmount"
                  class="form-input exchange-rate-input"
                  :class="{ 'error': errors.exchangeRate }"
                  placeholder="0.000000"
                  required
                />
                <span class="rate-suffix">{{ baseCurrency }}</span>
              </div>
              <span v-if="errors.exchangeRate" class="error-message">{{ errors.exchangeRate }}</span>
              <button type="button" @click="fetchExchangeRate" class="fetch-rate-btn">
                <i class="fas fa-sync-alt"></i>
                Get Current Rate
              </button>
            </div>

            <div class="form-group">
              <label class="form-label">Base Currency Amount</label>
              <div class="calculated-amount">
                <span class="calculated-value">{{ formatCurrency(calculatedBaseCurrencyAmount) }} {{ baseCurrency }}</span>
                <small class="calculated-note">Auto-calculated based on exchange rate</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Bank Account Details (for bank transfers) -->
        <div v-if="form.paymentMethod === 'Bank Transfer' || form.paymentMethod === 'Wire Transfer'" class="form-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-university"></i>
              Bank Account Details
            </h3>
            <p class="section-description">Bank account information for the payment</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Bank Account</label>
              <select 
                v-model="form.bankAccountId"
                class="form-select"
                :class="{ 'error': errors.bankAccountId }"
              >
                <option value="">Select Bank Account</option>
                <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                  {{ account.account_name }} - {{ account.account_number }}
                </option>
              </select>
              <span v-if="errors.bankAccountId" class="error-message">{{ errors.bankAccountId }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">Transaction ID</label>
              <input 
                type="text" 
                v-model="form.transactionId"
                class="form-input"
                :class="{ 'error': errors.transactionId }"
                placeholder="Bank transaction ID"
              />
              <span v-if="errors.transactionId" class="error-message">{{ errors.transactionId }}</span>
            </div>
          </div>
        </div>

        <!-- Summary Section -->
        <div v-if="selectedReceivable" class="form-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-calculator"></i>
              Payment Summary
            </h3>
          </div>
          
          <div class="summary-card">
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Customer</span>
                <span class="summary-value">{{ getSelectedCustomer()?.name }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Invoice Amount</span>
                <span class="summary-value">{{ formatCurrency(selectedReceivable.amount) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Previously Paid</span>
                <span class="summary-value">{{ formatCurrency(selectedReceivable.paid_amount) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Current Payment</span>
                <span class="summary-value payment-amount">{{ formatCurrency(form.amount || 0) }}</span>
              </div>
              <div class="summary-item total">
                <span class="summary-label">Remaining Balance</span>
                <span class="summary-value">{{ formatCurrency(remainingBalance) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="button" @click="resetForm" class="btn btn-outline">
            <i class="fas fa-undo"></i>
            Reset Form
          </button>
          <button type="button" @click="saveAsDraft" class="btn btn-secondary" :disabled="submitting">
            <i class="fas fa-save"></i>
            Save as Draft
          </button>
          <button type="submit" class="btn btn-primary" :disabled="submitting || !isFormValid">
            <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-check"></i>
            {{ submitting ? 'Recording Payment...' : 'Record Payment' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header success">
          <div class="success-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <h3 class="modal-title">Payment Recorded Successfully!</h3>
        </div>
        <div class="modal-body">
          <p>Payment of {{ formatCurrency(form.amount) }} has been successfully recorded.</p>
          <div class="success-details">
            <div class="detail-row">
              <span>Payment ID:</span>
              <span class="payment-id">#{{ recordedPaymentId }}</span>
            </div>
            <div class="detail-row">
              <span>Customer:</span>
              <span>{{ getSelectedCustomer()?.name }}</span>
            </div>
            <div class="detail-row">
              <span>Remaining Balance:</span>
              <span>{{ formatCurrency(remainingBalance) }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="recordAnother" class="btn btn-outline">
            Record Another Payment
          </button>
          <button @click="viewPayment" class="btn btn-primary">
            View Payment Details
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'RecordPaymentForm',
  setup() {
    const router = useRouter()
    const submitting = ref(false)
    const loadingReceivables = ref(false)
    const customers = ref([])
    const customerReceivables = ref([])
    const bankAccounts = ref([])
    const showSuccessModal = ref(false)
    const recordedPaymentId = ref(null)
    const baseCurrency = ref('USD')
    
    const form = reactive({
      customerId: '',
      receivableId: '',
      paymentDate: new Date().toISOString().split('T')[0],
      paymentMethod: '',
      amount: '',
      currency: 'USD',
      exchangeRate: 1,
      referenceNumber: '',
      notes: '',
      bankAccountId: '',
      transactionId: ''
    })
    
    const errors = reactive({})
    
    const selectedReceivable = computed(() => {
      return customerReceivables.value.find(r => r.receivable_id == form.receivableId)
    })
    
    const showExchangeSection = computed(() => {
      return form.currency && form.currency !== baseCurrency.value
    })
    
    const calculatedBaseCurrencyAmount = computed(() => {
      return (parseFloat(form.amount) || 0) * (parseFloat(form.exchangeRate) || 1)
    })
    
    const remainingBalance = computed(() => {
      if (!selectedReceivable.value) return 0
      return selectedReceivable.value.balance - (parseFloat(form.amount) || 0)
    })
    
    const isFormValid = computed(() => {
      return form.customerId && 
             form.receivableId && 
             form.paymentDate && 
             form.paymentMethod && 
             form.amount && 
             form.currency &&
             Object.keys(errors).length === 0
    })

    const fetchCustomers = async () => {
      try {
        const response = await axios.get('/customers')
        customers.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching customers:', error)
      }
    }

    const fetchCustomerReceivables = async () => {
      if (!form.customerId) {
        customerReceivables.value = []
        return
      }
      
      try {
        loadingReceivables.value = true
        const response = await axios.get(`/accounting/customer-receivables`, {
          params: {
            customer_id: form.customerId,
            status: 'Open'
          }
        })
        customerReceivables.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching receivables:', error)
        customerReceivables.value = []
      } finally {
        loadingReceivables.value = false
      }
    }

    const fetchBankAccounts = async () => {
      try {
        const response = await axios.get('/accounting/bank-accounts')
        bankAccounts.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching bank accounts:', error)
      }
    }

    const onReceivableChange = () => {
      if (selectedReceivable.value) {
        form.currency = selectedReceivable.value.currency || 'USD'
        form.amount = selectedReceivable.value.balance
      }
    }

    const onCurrencyChange = () => {
      if (form.currency === baseCurrency.value) {
        form.exchangeRate = 1
      } else {
        fetchExchangeRate()
      }
    }

    const fetchExchangeRate = async () => {
      if (form.currency === baseCurrency.value) {
        form.exchangeRate = 1
        return
      }
      
      try {
        const response = await axios.get(`/accounting/exchange-rates`, {
          params: {
            from_currency: form.currency,
            to_currency: baseCurrency.value,
            date: form.paymentDate
          }
        })
        form.exchangeRate = response.data.rate || 1
      } catch (error) {
        console.error('Error fetching exchange rate:', error)
        // Use default rate of 1 if unable to fetch
        form.exchangeRate = 1
      }
    }

    const calculateBaseCurrencyAmount = () => {
      // Automatically calculated in computed property
    }

    const calculateRemainingBalance = () => {
      // Automatically calculated in computed property
    }

    const validateForm = () => {
      Object.keys(errors).forEach(key => delete errors[key])
      
      if (!form.customerId) {
        errors.customerId = 'Customer is required'
      }
      
      if (!form.receivableId) {
        errors.receivableId = 'Receivable is required'
      }
      
      if (!form.paymentDate) {
        errors.paymentDate = 'Payment date is required'
      }
      
      if (!form.paymentMethod) {
        errors.paymentMethod = 'Payment method is required'
      }
      
      if (!form.amount || parseFloat(form.amount) <= 0) {
        errors.amount = 'Valid payment amount is required'
      } else if (selectedReceivable.value && parseFloat(form.amount) > selectedReceivable.value.balance) {
        errors.amount = 'Payment amount cannot exceed outstanding balance'
      }
      
      if (showExchangeSection.value && (!form.exchangeRate || parseFloat(form.exchangeRate) <= 0)) {
        errors.exchangeRate = 'Valid exchange rate is required'
      }
      
      return Object.keys(errors).length === 0
    }

    const submitPayment = async () => {
      if (!validateForm()) return
      
      try {
        submitting.value = true
        
        const paymentData = {
          receivable_id: form.receivableId,
          payment_date: form.paymentDate,
          payment_method: form.paymentMethod,
          amount: parseFloat(form.amount),
          currency: form.currency,
          reference_number: form.referenceNumber,
          notes: form.notes,
          bank_account_id: form.bankAccountId || null,
          transaction_id: form.transactionId || null
        }
        
        if (showExchangeSection.value) {
          paymentData.exchange_rate = parseFloat(form.exchangeRate)
          paymentData.base_currency_amount = calculatedBaseCurrencyAmount.value
        }
        
        const response = await axios.post('/accounting/receivable-payments', paymentData)
        recordedPaymentId.value = response.data.data.payment_id
        showSuccessModal.value = true
        
      } catch (error) {
        console.error('Error recording payment:', error)
        if (error.response?.data?.errors) {
          Object.assign(errors, error.response.data.errors)
        }
        // Show error message
      } finally {
        submitting.value = false
      }
    }

    const saveAsDraft = () => {
      // Implement save as draft functionality
      console.log('Save as draft')
    }

    const resetForm = () => {
      Object.keys(form).forEach(key => {
        if (key === 'paymentDate') {
          form[key] = new Date().toISOString().split('T')[0]
        } else if (key === 'currency') {
          form[key] = 'USD'
        } else if (key === 'exchangeRate') {
          form[key] = 1
        } else {
          form[key] = ''
        }
      })
      Object.keys(errors).forEach(key => delete errors[key])
      customerReceivables.value = []
    }

    const getSelectedCustomer = () => {
      return customers.value.find(c => c.customer_id == form.customerId)
    }

    const closeSuccessModal = () => {
      showSuccessModal.value = false
    }

    const recordAnother = () => {
      showSuccessModal.value = false
      resetForm()
    }

    const viewPayment = () => {
      router.push(`/accounting/receivable-payments/${recordedPaymentId.value}`)
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

    // Watch for customer changes to reset receivable selection
    watch(() => form.customerId, () => {
      form.receivableId = ''
      customerReceivables.value = []
    })

    onMounted(() => {
      fetchCustomers()
      fetchBankAccounts()
    })

    return {
      form,
      errors,
      submitting,
      loadingReceivables,
      customers,
      customerReceivables,
      bankAccounts,
      selectedReceivable,
      showExchangeSection,
      calculatedBaseCurrencyAmount,
      remainingBalance,
      isFormValid,
      showSuccessModal,
      recordedPaymentId,
      baseCurrency,
      fetchCustomerReceivables,
      onReceivableChange,
      onCurrencyChange,
      fetchExchangeRate,
      calculateBaseCurrencyAmount,
      calculateRemainingBalance,
      submitPayment,
      saveAsDraft,
      resetForm,
      getSelectedCustomer,
      closeSuccessModal,
      recordAnother,
      viewPayment,
      formatCurrency,
      formatDate,
      isOverdue
    }
  }
}
</script>

<style scoped>
.record-payment-container {
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

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
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

.btn-outline:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.form-container {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.payment-form {
  padding: 2rem;
}

.form-section {
  margin-bottom: 3rem;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 2rem;
}

.form-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
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
  margin-bottom: 0.5rem;
}

.section-title i {
  color: #6366f1;
  font-size: 1.1rem;
}

.section-description {
  color: #64748b;
  font-size: 0.95rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-label {
  font-weight: 600;
  color: #374151;
  font-size: 0.95rem;
}

.form-label.required::after {
  content: ' *';
  color: #ef4444;
}

.form-input,
.form-select,
.form-textarea {
  padding: 0.875rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
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
.form-select.error,
.form-textarea.error {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.error-message {
  color: #ef4444;
  font-size: 0.875rem;
  font-weight: 500;
}

.loading-text {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #64748b;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.receivable-details {
  margin-top: 1.5rem;
}

.details-card {
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
}

.details-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1rem;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: white;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.detail-label {
  font-weight: 500;
  color: #64748b;
  font-size: 0.875rem;
}

.detail-value {
  font-weight: 600;
  color: #1e293b;
}

.detail-value.outstanding {
  color: #059669;
  font-size: 1.1rem;
}

.detail-value.overdue {
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

.amount-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.currency-prefix {
  position: absolute;
  left: 0.875rem;
  color: #64748b;
  font-weight: 600;
  z-index: 1;
}

.amount-input {
  padding-left: 3rem;
}

.amount-info {
  margin-top: 0.5rem;
}

.amount-detail {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.remaining-amount {
  font-weight: 600;
  color: #059669;
}

.overpayment-warning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #f59e0b;
  font-size: 0.875rem;
  font-weight: 500;
}

.exchange-rate-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.rate-prefix,
.rate-suffix {
  color: #64748b;
  font-weight: 500;
  font-size: 0.95rem;
}

.exchange-rate-input {
  flex: 1;
  min-width: 120px;
}

.fetch-rate-btn {
  background: #f1f5f9;
  border: 2px solid #e2e8f0;
  color: #6366f1;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 0.5rem;
}

.fetch-rate-btn:hover {
  background: #6366f1;
  color: white;
}

.calculated-amount {
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.875rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.calculated-value {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
}

.calculated-note {
  color: #64748b;
  font-size: 0.75rem;
}

.summary-card {
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
}

.summary-item.total {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border: none;
}

.summary-label {
  font-weight: 500;
  font-size: 0.875rem;
}

.summary-value {
  font-weight: 600;
  font-size: 1rem;
}

.summary-value.payment-amount {
  color: #059669;
  font-size: 1.1rem;
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding-top: 2rem;
  border-top: 1px solid #f1f5f9;
  margin-top: 2rem;
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
  border-radius: 20px;
  max-width: 500px;
  width: 100%;
  margin: 1rem;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 2rem;
  text-align: center;
}

.modal-header.success {
  background: linear-gradient(135deg, #dcfdf7 0%, #ffffff 100%);
}

.success-icon {
  font-size: 4rem;
  color: #059669;
  margin-bottom: 1rem;
}

.modal-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
}

.modal-body {
  padding: 0 2rem 2rem;
  text-align: center;
}

.success-details {
  background: #f8fafc;
  border-radius: 12px;
  padding: 1rem;
  margin-top: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.detail-row:last-child {
  border-bottom: none;
}

.payment-id {
  font-family: monospace;
  background: #6366f1;
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.875rem;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 2rem;
  border-top: 1px solid #f1f5f9;
}

@media (max-width: 768px) {
  .record-payment-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .exchange-rate-wrapper {
    flex-direction: column;
    align-items: stretch;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .modal-footer {
    flex-direction: column;
  }
}
</style>