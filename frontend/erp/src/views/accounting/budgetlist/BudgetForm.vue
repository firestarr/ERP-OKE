<template>
  <AppLayout>
    <template #page-title>{{ isEdit ? 'Edit Budget' : 'Create New Budget' }}</template>
    <template #page-subtitle>{{ isEdit ? 'Update budget information and amounts' : 'Set up a new budget for accounting period' }}</template>
    
    <template #page-actions>
      <button @click="goBack" class="action-button secondary">
        <i class="fas fa-arrow-left"></i>
        Back to List
      </button>
      <button @click="resetForm" class="action-button secondary" :disabled="loading">
        <i class="fas fa-undo"></i>
        Reset
      </button>
    </template>

    <div class="budget-form-container">
      <div class="form-layout">
        <!-- Main Form -->
        <div class="form-section">
          <div class="form-card">
            <div class="form-header">
              <h3>
                <i class="fas fa-edit"></i>
                Budget Information
              </h3>
              <p>Enter the budget details for the selected account and period</p>
            </div>
            
            <form @submit.prevent="saveBudget" class="budget-form">
              <!-- Account Selection -->
              <div class="form-group">
                <label for="account_id" class="form-label required">
                  <i class="fas fa-chart-pie"></i>
                  Chart of Account
                </label>
                <div class="select-wrapper">
                  <select 
                    id="account_id" 
                    v-model="formData.account_id" 
                    :disabled="isEdit || loading"
                    class="form-select"
                    :class="{ 'error': errors.account_id }"
                    @change="clearError('account_id')"
                  >
                    <option value="">Select an account</option>
                    <optgroup v-for="(accounts, type) in groupedAccounts" :key="type" :label="type">
                      <option v-for="account in accounts" :key="account.account_id" :value="account.account_id">
                        {{ account.account_code }} - {{ account.name }}
                      </option>
                    </optgroup>
                  </select>
                  <i class="fas fa-chevron-down select-icon"></i>
                </div>
                <div v-if="errors.account_id" class="error-message">
                  {{ errors.account_id }}
                </div>
                <div v-if="selectedAccount" class="account-preview">
                  <div class="account-details">
                    <strong>{{ selectedAccount.name }}</strong>
                    <div class="account-meta">
                      <span class="account-type">{{ selectedAccount.account_type }}</span>
                      <span class="account-code">{{ selectedAccount.account_code }}</span>
                    </div>
                    <p v-if="selectedAccount.description" class="account-description">
                      {{ selectedAccount.description }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Period Selection -->
              <div class="form-group">
                <label for="period_id" class="form-label required">
                  <i class="fas fa-calendar-alt"></i>
                  Accounting Period
                </label>
                <div class="select-wrapper">
                  <select 
                    id="period_id" 
                    v-model="formData.period_id" 
                    :disabled="isEdit || loading"
                    class="form-select"
                    :class="{ 'error': errors.period_id }"
                    @change="clearError('period_id')"
                  >
                    <option value="">Select a period</option>
                    <option v-for="period in periods" :key="period.period_id" :value="period.period_id">
                      {{ period.name }} ({{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }})
                    </option>
                  </select>
                  <i class="fas fa-chevron-down select-icon"></i>
                </div>
                <div v-if="errors.period_id" class="error-message">
                  {{ errors.period_id }}
                </div>
                <div v-if="selectedPeriod" class="period-preview">
                  <div class="period-details">
                    <strong>{{ selectedPeriod.name }}</strong>
                    <div class="period-meta">
                      <span class="period-dates">
                        {{ formatDate(selectedPeriod.start_date) }} - {{ formatDate(selectedPeriod.end_date) }}
                      </span>
                      <span class="period-status" :class="selectedPeriod.status">
                        {{ selectedPeriod.status }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Budget Amount -->
              <div class="form-group">
                <label for="budgeted_amount" class="form-label required">
                  <i class="fas fa-money-bill-wave"></i>
                  Budgeted Amount
                </label>
                <div class="input-wrapper">
                  <span class="input-prefix">IDR</span>
                  <input 
                    id="budgeted_amount"
                    type="text"
                    v-model="formattedBudgetedAmount"
                    @input="handleBudgetedAmountInput"
                    @blur="clearError('budgeted_amount')"
                    class="form-input"
                    :class="{ 'error': errors.budgeted_amount }"
                    placeholder="0"
                    :disabled="loading"
                  />
                </div>
                <div v-if="errors.budgeted_amount" class="error-message">
                  {{ errors.budgeted_amount }}
                </div>
                <small class="form-hint">
                  Enter the planned budget amount for this account and period
                </small>
              </div>

              <!-- Actual Amount (Optional) -->
              <div class="form-group">
                <label for="actual_amount" class="form-label">
                  <i class="fas fa-receipt"></i>
                  Actual Amount (Optional)
                </label>
                <div class="input-wrapper">
                  <span class="input-prefix">IDR</span>
                  <input 
                    id="actual_amount"
                    type="text"
                    v-model="formattedActualAmount"
                    @input="handleActualAmountInput"
                    @blur="clearError('actual_amount')"
                    class="form-input"
                    :class="{ 'error': errors.actual_amount }"
                    placeholder="0"
                    :disabled="loading"
                  />
                </div>
                <div v-if="errors.actual_amount" class="error-message">
                  {{ errors.actual_amount }}
                </div>
                <small class="form-hint">
                  You can leave this empty and update it later when actual data is available
                </small>
              </div>

              <!-- Form Actions -->
              <div class="form-actions">
                <button type="button" @click="goBack" class="btn-cancel">
                  <i class="fas fa-times"></i>
                  Cancel
                </button>
                <button type="submit" class="btn-save" :disabled="loading || !isFormValid">
                  <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                  <i v-else class="fas fa-save"></i>
                  {{ loading ? 'Saving...' : (isEdit ? 'Update Budget' : 'Create Budget') }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Preview Section -->
        <div class="preview-section">
          <!-- Preview Card -->
          <div class="preview-card">
            <div class="preview-header">
              <h3>
                <i class="fas fa-eye"></i>
                Budget Preview
              </h3>
            </div>
            
            <div class="preview-content">
              <div v-if="!selectedAccount || !selectedPeriod" class="preview-placeholder">
                <i class="fas fa-clipboard-list"></i>
                <p>Select account and period to see preview</p>
              </div>
              
              <div v-else class="preview-details">
                <div class="preview-item">
                  <label>Account</label>
                  <div class="preview-value">
                    <strong>{{ selectedAccount.account_code }}</strong>
                    <span>{{ selectedAccount.name }}</span>
                    <small>{{ selectedAccount.account_type }}</small>
                  </div>
                </div>
                
                <div class="preview-item">
                  <label>Period</label>
                  <div class="preview-value">
                    <strong>{{ selectedPeriod.name }}</strong>
                    <span>{{ formatDate(selectedPeriod.start_date) }} - {{ formatDate(selectedPeriod.end_date) }}</span>
                  </div>
                </div>
                
                <div class="preview-item">
                  <label>Budgeted Amount</label>
                  <div class="preview-value amount budgeted">
                    {{ formatCurrency(formData.budgeted_amount) }}
                  </div>
                </div>
                
                <div class="preview-item" v-if="formData.actual_amount">
                  <label>Actual Amount</label>
                  <div class="preview-value amount actual">
                    {{ formatCurrency(formData.actual_amount) }}
                  </div>
                </div>
                
                <div class="preview-item" v-if="variance !== null">
                  <label>Variance</label>
                  <div class="preview-value amount variance" :class="variance >= 0 ? 'positive' : 'negative'">
                    {{ formatCurrency(variance) }}
                    <small>({{ variancePercentage }}%)</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Validation Card -->
          <div class="validation-card" v-if="Object.keys(errors).length > 0">
            <div class="validation-header">
              <h3>
                <i class="fas fa-exclamation-triangle"></i>
                Validation Errors
              </h3>
            </div>
            <div class="validation-content">
              <ul class="error-list">
                <li v-for="(error, field) in errors" :key="field" class="error-item">
                  <i class="fas fa-times-circle"></i>
                  {{ error }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Help Card -->
          <div class="help-card">
            <div class="help-header">
              <h3>
                <i class="fas fa-question-circle"></i>
                Help & Guidelines
              </h3>
            </div>
            <div class="help-content">
              <div class="help-item">
                <h4>Budget Creation</h4>
                <p>Each budget entry represents a planned amount for a specific account during a particular period.</p>
              </div>
              <div class="help-item">
                <h4>Account Selection</h4>
                <p>Choose the chart of account that this budget applies to. Accounts are grouped by type for easier navigation.</p>
              </div>
              <div class="help-item">
                <h4>Actual vs Budget</h4>
                <p>You can enter actual amounts now or update them later. The system will automatically calculate variance.</p>
              </div>
              <div class="help-item">
                <h4>Variance Analysis</h4>
                <p>Positive variance means actual exceeds budget, negative means under budget.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
      <div class="modal-content success" @click.stop>
        <div class="modal-header">
          <h3><i class="fas fa-check-circle"></i> Success</h3>
        </div>
        <div class="modal-body">
          <p>Budget has been {{ isEdit ? 'updated' : 'created' }} successfully!</p>
          <div class="success-details" v-if="savedBudget">
            <strong>{{ savedBudget.chart_of_account?.name }}</strong><br>
            <small>{{ savedBudget.accounting_period?.name }} - {{ formatCurrency(savedBudget.budgeted_amount) }}</small>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="goBack" class="btn-primary">
            <i class="fas fa-list"></i>
            Back to List
          </button>
          <button @click="createAnother" class="btn-secondary" v-if="!isEdit">
            <i class="fas fa-plus"></i>
            Create Another
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BudgetForm',
  components: {
  },
  setup() {
    const router = useRouter()
    const route = useRoute()
    const loading = ref(false)
    const accounts = ref([])
    const periods = ref([])
    const showSuccessModal = ref(false)
    const savedBudget = ref(null)
    
    const isEdit = computed(() => !!route.params.id)
    const budgetId = computed(() => route.params.id)
    
    const formData = reactive({
      account_id: '',
      period_id: '',
      budgeted_amount: 0,
      actual_amount: null
    })
    
    const errors = reactive({})
    const formattedBudgetedAmount = ref('')
    const formattedActualAmount = ref('')
    
    const groupedAccounts = computed(() => {
      const grouped = {}
      accounts.value.forEach(account => {
        const type = account.account_type || 'Other'
        if (!grouped[type]) {
          grouped[type] = []
        }
        grouped[type].push(account)
      })
      return grouped
    })
    
    const selectedAccount = computed(() => {
      return accounts.value.find(account => account.account_id === formData.account_id)
    })
    
    const selectedPeriod = computed(() => {
      return periods.value.find(period => period.period_id === formData.period_id)
    })
    
    const variance = computed(() => {
      if (formData.actual_amount !== null && formData.budgeted_amount) {
        return parseFloat(formData.actual_amount) - parseFloat(formData.budgeted_amount)
      }
      return null
    })
    
    const variancePercentage = computed(() => {
      if (variance.value !== null && formData.budgeted_amount) {
        return ((variance.value / parseFloat(formData.budgeted_amount)) * 100).toFixed(2)
      }
      return '0'
    })
    
    const isFormValid = computed(() => {
      return formData.account_id && 
             formData.period_id && 
             formData.budgeted_amount > 0 && 
             Object.keys(errors).length === 0
    })

    const formatCurrency = (amount) => {
      if (amount === null || amount === undefined || amount === '') return 'IDR 0'
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount)
    }

    const formatDate = (dateString) => {
      if (!dateString) return ''
      return new Intl.DateTimeFormat('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      }).format(new Date(dateString))
    }

    const formatNumberInput = (value) => {
      if (!value) return ''
      const number = value.toString().replace(/[^\d]/g, '')
      return new Intl.NumberFormat('id-ID').format(number)
    }

    const parseFormattedNumber = (formattedValue) => {
      if (!formattedValue) return 0
      return parseFloat(formattedValue.replace(/[^\d]/g, '')) || 0
    }

    const handleBudgetedAmountInput = (event) => {
      const value = event.target.value
      formattedBudgetedAmount.value = formatNumberInput(value)
      formData.budgeted_amount = parseFormattedNumber(value)
      clearError('budgeted_amount')
    }

    const handleActualAmountInput = (event) => {
      const value = event.target.value
      if (value === '') {
        formattedActualAmount.value = ''
        formData.actual_amount = null
      } else {
        formattedActualAmount.value = formatNumberInput(value)
        formData.actual_amount = parseFormattedNumber(value)
      }
      clearError('actual_amount')
    }

    const clearError = (field) => {
      if (errors[field]) {
        delete errors[field]
      }
    }

    const validateForm = () => {
      Object.keys(errors).forEach(key => delete errors[key])
      
      if (!formData.account_id) {
        errors.account_id = 'Please select an account'
      }
      
      if (!formData.period_id) {
        errors.period_id = 'Please select a period'
      }
      
      if (!formData.budgeted_amount || formData.budgeted_amount <= 0) {
        errors.budgeted_amount = 'Budgeted amount must be greater than 0'
      }
      
      if (formData.actual_amount !== null && formData.actual_amount < 0) {
        errors.actual_amount = 'Actual amount cannot be negative'
      }
      
      return Object.keys(errors).length === 0
    }

    const fetchDropdownData = async () => {
      try {
        const [accountsResponse, periodsResponse] = await Promise.all([
          axios.get('/accounting/chart-of-accounts'),
          axios.get('/accounting/accounting-periods')
        ])
        
        accounts.value = accountsResponse.data.data || accountsResponse.data
        periods.value = periodsResponse.data.data || periodsResponse.data
      } catch (error) {
        console.error('Error fetching dropdown data:', error)
      }
    }

    const fetchBudget = async () => {
      if (!budgetId.value) return
      
      try {
        loading.value = true
        const response = await axios.get(`/accounting/budgets/${budgetId.value}`)
        const budget = response.data.data
        
        Object.assign(formData, {
          account_id: budget.account_id,
          period_id: budget.period_id,
          budgeted_amount: parseFloat(budget.budgeted_amount),
          actual_amount: budget.actual_amount ? parseFloat(budget.actual_amount) : null
        })
        
        formattedBudgetedAmount.value = formatNumberInput(budget.budgeted_amount)
        formattedActualAmount.value = budget.actual_amount ? formatNumberInput(budget.actual_amount) : ''
        
      } catch (error) {
        console.error('Error fetching budget:', error)
        router.push('/budgets')
      } finally {
        loading.value = false
      }
    }

    const saveBudget = async () => {
      if (!validateForm()) return
      
      try {
        loading.value = true
        
        const payload = {
          account_id: formData.account_id,
          period_id: formData.period_id,
          budgeted_amount: formData.budgeted_amount,
          actual_amount: formData.actual_amount
        }
        
        let response
        if (isEdit.value) {
          response = await axios.put(`/accounting/budgets/${budgetId.value}`, payload)
        } else {
          response = await axios.post('/accounting/budgets', payload)
        }
        
        savedBudget.value = response.data.data
        showSuccessModal.value = true
        
      } catch (error) {
        console.error('Error saving budget:', error)
        
        if (error.response && error.response.data.errors) {
          Object.assign(errors, error.response.data.errors)
        } else if (error.response && error.response.data.message) {
          errors.general = error.response.data.message
        }
      } finally {
        loading.value = false
      }
    }

    const resetForm = () => {
      if (isEdit.value) {
        fetchBudget()
      } else {
        Object.assign(formData, {
          account_id: '',
          period_id: '',
          budgeted_amount: 0,
          actual_amount: null
        })
        formattedBudgetedAmount.value = ''
        formattedActualAmount.value = ''
        Object.keys(errors).forEach(key => delete errors[key])
      }
    }

    const goBack = () => {
      router.push('/budgets')
    }

    const closeSuccessModal = () => {
      showSuccessModal.value = false
    }

    const createAnother = () => {
      showSuccessModal.value = false
      resetForm()
    }

    // Watch for formatted amount changes
    watch(() => formData.budgeted_amount, (newVal) => {
      if (newVal && !formattedBudgetedAmount.value) {
        formattedBudgetedAmount.value = formatNumberInput(newVal)
      }
    })

    watch(() => formData.actual_amount, (newVal) => {
      if (newVal && !formattedActualAmount.value) {
        formattedActualAmount.value = formatNumberInput(newVal)
      }
    })

    onMounted(async () => {
      await fetchDropdownData()
      if (isEdit.value) {
        await fetchBudget()
      }
    })

    return {
      loading,
      isEdit,
      formData,
      errors,
      accounts,
      periods,
      groupedAccounts,
      selectedAccount,
      selectedPeriod,
      variance,
      variancePercentage,
      isFormValid,
      formattedBudgetedAmount,
      formattedActualAmount,
      showSuccessModal,
      savedBudget,
      handleBudgetedAmountInput,
      handleActualAmountInput,
      clearError,
      saveBudget,
      resetForm,
      goBack,
      closeSuccessModal,
      createAnother,
      formatCurrency,
      formatDate
    }
  }
}
</script>

<style scoped>
.budget-form-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0;
}

.form-layout {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 2rem;
  align-items: start;
}

/* Form Section */
.form-section {
  min-width: 0;
}

.form-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.form-header {
  padding: 2rem 2rem 1rem 2rem;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.form-header h3 {
  color: #1e293b;
  margin-bottom: 0.5rem;
  font-size: 1.25rem;
}

.form-header h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.form-header p {
  color: #64748b;
  margin: 0;
  font-size: 0.9rem;
}

.budget-form {
  padding: 2rem;
}

.form-group {
  margin-bottom: 2rem;
}

.form-label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
}

.form-label i {
  color: #6366f1;
  margin-right: 0.5rem;
  width: 16px;
}

.form-label.required::after {
  content: '*';
  color: #dc2626;
  margin-left: 0.25rem;
}

.select-wrapper,
.input-wrapper {
  position: relative;
}

.form-select,
.form-input {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.9rem;
  background: white;
  transition: all 0.3s ease;
  font-family: inherit;
}

.form-select {
  padding-right: 2.5rem;
  appearance: none;
  cursor: pointer;
}

.form-input[type="text"] {
  padding-left: 3.5rem;
}

.form-select:focus,
.form-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-select.error,
.form-input.error {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.form-select:disabled,
.form-input:disabled {
  background: #f1f5f9;
  color: #64748b;
  cursor: not-allowed;
}

.select-icon {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  pointer-events: none;
  font-size: 0.8rem;
}

.input-prefix {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  font-weight: 500;
  font-size: 0.9rem;
  pointer-events: none;
}

.error-message {
  color: #dc2626;
  font-size: 0.8rem;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.error-message::before {
  content: '⚠';
  font-size: 0.9rem;
}

.form-hint {
  color: #64748b;
  font-size: 0.8rem;
  margin-top: 0.5rem;
  display: block;
}

/* Preview Section */
.account-preview,
.period-preview {
  margin-top: 0.75rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 4px solid #6366f1;
}

.account-details strong,
.period-details strong {
  display: block;
  color: #1e293b;
  font-size: 0.95rem;
  margin-bottom: 0.25rem;
}

.account-meta,
.period-meta {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.account-type,
.account-code,
.period-dates,
.period-status {
  font-size: 0.8rem;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-weight: 500;
}

.account-type {
  background: #ede9fe;
  color: #7c3aed;
}

.account-code {
  background: #fef3c7;
  color: #d97706;
}

.period-dates {
  background: #dcfce7;
  color: #16a34a;
}

.period-status {
  background: #e0e7ff;
  color: #3730a3;
}

.account-description {
  color: #64748b;
  font-size: 0.85rem;
  margin: 0;
  line-height: 1.4;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e2e8f0;
}

.btn-cancel,
.btn-save {
  padding: 0.875rem 1.5rem;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: none;
}

.btn-cancel {
  background: #f1f5f9;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.btn-cancel:hover {
  background: #e2e8f0;
  color: #475569;
}

.btn-save {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border: 2px solid transparent;
}

.btn-save:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-save:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Preview Section */
.preview-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  position: sticky;
  top: 2rem;
}

.preview-card,
.validation-card,
.help-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.preview-header,
.validation-header,
.help-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.preview-header h3,
.validation-header h3,
.help-header h3 {
  color: #1e293b;
  margin: 0;
  font-size: 1rem;
}

.preview-header h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.validation-header h3 i {
  color: #dc2626;
  margin-right: 0.5rem;
}

.help-header h3 i {
  color: #059669;
  margin-right: 0.5rem;
}

.preview-content,
.validation-content,
.help-content {
  padding: 1.5rem;
}

.preview-placeholder {
  text-align: center;
  color: #94a3b8;
  padding: 2rem 0;
}

.preview-placeholder i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  display: block;
}

.preview-item {
  margin-bottom: 1.5rem;
}

.preview-item:last-child {
  margin-bottom: 0;
}

.preview-item label {
  display: block;
  font-size: 0.8rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.preview-value {
  font-size: 0.9rem;
  color: #1e293b;
}

.preview-value strong {
  display: block;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.preview-value span {
  display: block;
  color: #64748b;
  font-size: 0.85rem;
  margin-bottom: 0.25rem;
}

.preview-value small {
  color: #94a3b8;
  font-size: 0.75rem;
}

.preview-value.amount {
  font-size: 1.1rem;
  font-weight: 700;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  margin: 0;
}

.preview-value.amount.budgeted {
  background: #ede9fe;
  color: #7c3aed;
}

.preview-value.amount.actual {
  background: #dcfce7;
  color: #16a34a;
}

.preview-value.amount.variance.positive {
  background: #dcfce7;
  color: #16a34a;
}

.preview-value.amount.variance.negative {
  background: #fee2e2;
  color: #dc2626;
}

.preview-value.amount small {
  display: block;
  font-size: 0.8rem;
  opacity: 0.8;
  margin-top: 0.25rem;
}

/* Validation Card */
.error-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.error-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  color: #dc2626;
  font-size: 0.9rem;
}

.error-item:not(:last-child) {
  border-bottom: 1px solid #fee2e2;
}

.error-item i {
  color: #dc2626;
  font-size: 0.8rem;
}

/* Help Card */
.help-item {
  margin-bottom: 1.5rem;
}

.help-item:last-child {
  margin-bottom: 0;
}

.help-item h4 {
  color: #1e293b;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.help-item p {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.5;
  margin: 0;
}

/* Success Modal */
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
  max-width: 400px;
  width: 90%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-content.success .modal-header h3 {
  color: #059669;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  margin: 0;
}

.modal-header h3 i {
  margin-right: 0.5rem;
}

.modal-body {
  padding: 1.5rem;
  text-align: center;
}

.success-details {
  margin-top: 1rem;
  padding: 1rem;
  background: #f0fdf4;
  border-radius: 8px;
  border-left: 4px solid #16a34a;
}

.modal-footer {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.btn-primary,
.btn-secondary {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background: #6366f1;
  color: white;
}

.btn-primary:hover {
  background: #5b5bd6;
}

.btn-secondary {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

/* Action Button Styles */
.action-button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  border-radius: 12px;
  border: none;
  cursor: pointer;
  font-weight: 500;
  font-size: 0.85rem;
  transition: all 0.3s ease;
}

.action-button.secondary {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.action-button.secondary:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .form-layout {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  
  .preview-section {
    position: static;
    order: -1;
  }
  
  .preview-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
  }
}

@media (max-width: 768px) {
  .budget-form {
    padding: 1.5rem;
  }
  
  .form-header {
    padding: 1.5rem;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn-cancel,
  .btn-save {
    width: 100%;
    justify-content: center;
  }
  
  .preview-section {
    grid-template-columns: 1fr;
  }
  
  .modal-content {
    margin: 1rem;
    width: calc(100% - 2rem);
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .btn-primary,
  .btn-secondary {
    width: 100%;
    justify-content: center;
  }
}
</style>