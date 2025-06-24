<template>
  <div class="bank-transaction-form-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <router-link to="/accounting/bank-accounts" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Bank Accounts
          </router-link>
          <h1 class="page-title">
            <i class="fas fa-exchange-alt"></i>
            {{ isEdit ? 'Edit Bank Transaction' : 'Create New Bank Transaction' }}
          </h1>
          <p class="page-subtitle">
            {{ isEdit ? 'Update transaction information' : 'Record a new bank transaction' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
      <form @submit.prevent="submitForm" class="bank-transaction-form">
        <!-- Transaction Information Section -->
        <div class="form-section">
          <div class="section-header">
            <h3>
              <i class="fas fa-info-circle"></i>
              Transaction Information
            </h3>
            <p>Basic transaction details</p>
          </div>
          
          <div class="form-grid">
            <div class="form-group">
              <label for="bank_account_id" class="form-label required">
                Bank Account
              </label>
              <select
                id="bank_account_id"
                v-model="form.bank_account_id"
                class="form-select"
                :class="{ 'error': errors.bank_account_id }"
                required
              >
                <option value="">Select Bank Account</option>
                <option 
                  v-for="account in bankAccounts" 
                  :key="account.bank_id" 
                  :value="account.bank_id"
                >
                  {{ account.bank_name }} - {{ account.account_name }}
                </option>
              </select>
              <div class="error-message" v-if="errors.bank_account_id">
                {{ errors.bank_account_id[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="transaction_date" class="form-label required">
                Transaction Date
              </label>
              <input
                id="transaction_date"
                v-model="form.transaction_date"
                type="date"
                class="form-input"
                :class="{ 'error': errors.transaction_date }"
                required
              >
              <div class="error-message" v-if="errors.transaction_date">
                {{ errors.transaction_date[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="type" class="form-label required">
                Transaction Type
              </label>
              <select
                id="type"
                v-model="form.type"
                class="form-select"
                :class="{ 'error': errors.type }"
                required
              >
                <option value="">Select Type</option>
                <option value="credit">Credit (Money In)</option>
                <option value="debit">Debit (Money Out)</option>
              </select>
              <div class="error-message" v-if="errors.type">
                {{ errors.type[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="amount" class="form-label required">
                Amount
              </label>
              <input
                id="amount"
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0"
                class="form-input"
                :class="{ 'error': errors.amount }"
                placeholder="0.00"
                required
              >
              <div class="error-message" v-if="errors.amount">
                {{ errors.amount[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="description" class="form-label required">
                Description
              </label>
              <input
                id="description"
                v-model="form.description"
                type="text"
                class="form-input"
                :class="{ 'error': errors.description }"
                placeholder="Transaction description"
                maxlength="255"
                required
              >
              <div class="error-message" v-if="errors.description">
                {{ errors.description[0] }}
              </div>
            </div>

            <div class="form-group">
              <label for="reference" class="form-label">
                Reference Number
              </label>
              <input
                id="reference"
                v-model="form.reference"
                type="text"
                class="form-input"
                placeholder="Check number, transfer reference, etc."
                maxlength="100"
              >
            </div>

            <div class="form-group">
              <label for="category" class="form-label">
                Category
              </label>
              <input
                id="category"
                v-model="form.category"
                type="text"
                class="form-input"
                placeholder="e.g., Office Supplies, Client Payment"
                maxlength="100"
              >
            </div>

            <div class="form-group">
              <label for="status" class="form-label">
                Status
              </label>
              <select
                id="status"
                v-model="form.status"
                class="form-select"
              >
                <option value="pending">Pending</option>
                <option value="cleared">Cleared</option>
                <option value="reconciled">Reconciled</option>
              </select>
            </div>

            <div class="form-group full-width">
              <label for="notes" class="form-label">
                Notes
              </label>
              <textarea
                id="notes"
                v-model="form.notes"
                class="form-textarea"
                rows="3"
                placeholder="Add any additional notes about this transaction..."
                maxlength="500"
              ></textarea>
              <div class="character-count">
                {{ (form.notes || '').length }}/500 characters
              </div>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <div class="actions-left">
            <router-link to="/accounting/bank-accounts" class="btn btn-outline">
              <i class="fas fa-times"></i>
              Cancel
            </router-link>
          </div>
          <div class="actions-right">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="submitting || !isFormValid"
            >
              <i class="fas fa-spinner fa-spin" v-if="submitting"></i>
              <i class="fas fa-check" v-else></i>
              {{ submitting ? 'Saving...' : (isEdit ? 'Update Transaction' : 'Create Transaction') }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loadingTransaction" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>Loading transaction data...</p>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BankTransactionForm',
  setup() {
    const router = useRouter()
    const route = useRoute()
    
    // Reactive data
    const form = ref({
      bank_account_id: '',
      transaction_date: '',
      type: '',
      amount: '',
      description: '',
      reference: '',
      category: '',
      status: 'pending',
      notes: ''
    })
    
    const errors = ref({})
    const submitting = ref(false)
    const loadingTransaction = ref(false)
    const loadingBankAccounts = ref(false)
    const bankAccounts = ref([])
    
    // Computed properties
    const isEdit = computed(() => route.name === 'EditBankTransaction')
    const transactionId = computed(() => route.params.id)
    
    const isFormValid = computed(() => {
      return form.value.bank_account_id &&
             form.value.transaction_date &&
             form.value.type &&
             form.value.amount &&
             form.value.description
    })

    // Methods
    const fetchBankAccounts = async () => {
      loadingBankAccounts.value = true
      try {
        const response = await axios.get('/accounting/bank-accounts')
        bankAccounts.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching bank accounts:', error)
        showNotification('Error fetching bank accounts', 'error')
      } finally {
        loadingBankAccounts.value = false
      }
    }

    const fetchTransaction = async (id) => {
      loadingTransaction.value = true
      try {
        const response = await axios.get(`/accounting/bank-transactions/${id}`)
        const transaction = response.data.data
        
        // Populate form with existing data
        Object.keys(form.value).forEach(key => {
          if (transaction[key] !== undefined) {
            form.value[key] = transaction[key]
          }
        })
      } catch (error) {
        console.error('Error fetching transaction:', error)
        showNotification('Error loading transaction data', 'error')
        router.push('/accounting/bank-accounts')
      } finally {
        loadingTransaction.value = false
      }
    }

    const submitForm = async () => {
      submitting.value = true
      errors.value = {}
      
      try {
        const url = isEdit.value 
          ? `/accounting/bank-transactions/${transactionId.value}` 
          : '/accounting/bank-transactions'
        const method = isEdit.value ? 'put' : 'post'
        
        const response = await axios[method](url, form.value)
        
        showNotification(
          isEdit.value ? 'Transaction updated successfully' : 'Transaction created successfully',
          'success'
        )
        
        // Redirect back to bank account detail or transaction list
        if (form.value.bank_account_id) {
          router.push(`/accounting/bank-accounts/${form.value.bank_account_id}`)
        } else {
          router.push('/accounting/bank-accounts')
        }
        
      } catch (error) {
        console.error('Error submitting form:', error)
        
        if (error.response?.data?.errors) {
          errors.value = error.response.data.errors
        } else {
          showNotification(
            error.response?.data?.message || 'Error saving transaction',
            'error'
          )
        }
      } finally {
        submitting.value = false
      }
    }

    const showNotification = (message, type = 'info') => {
      // Implement notification system here
      console.log(`${type.toUpperCase()}: ${message}`)
    }

    // Handle query parameters
    const handleQueryParams = () => {
      if (route.query.bank_id) {
        form.value.bank_account_id = route.query.bank_id
      }
      
      if (route.query.duplicate) {
        // If duplicating, fetch the original transaction
        fetchTransaction(route.query.duplicate)
      }
    }

    // Lifecycle
    onMounted(() => {
      fetchBankAccounts()
      
      if (isEdit.value && transactionId.value) {
        fetchTransaction(transactionId.value)
      } else {
        handleQueryParams()
      }
    })

    return {
      form,
      errors,
      submitting,
      loadingTransaction,
      loadingBankAccounts,
      bankAccounts,
      isEdit,
      transactionId,
      isFormValid,
      submitForm
    }
  }
}
</script>

<style scoped>
/* Gunakan style yang sama dengan BankAccountForm */
.bank-transaction-form-container {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
}

.page-header {
  margin-bottom: 2rem;
}

.form-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.bank-transaction-form {
  padding: 2rem;
}

.form-section {
  margin-bottom: 3rem;
}

.section-header {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--gray-100);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 2px solid var(--gray-100);
}

.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-outline {
  background: transparent;
  border: 2px solid var(--gray-300);
  color: var(--gray-700);
}

.character-count {
  font-size: 0.875rem;
  color: var(--gray-500);
  text-align: right;
  margin-top: 0.5rem;
}
</style>