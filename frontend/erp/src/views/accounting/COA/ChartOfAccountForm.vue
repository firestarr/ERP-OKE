<!-- src/views/accounting/COA/ChartOfAccountForm.vue -->
<template>
  <div class="account-form-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <div class="breadcrumb">
            <router-link to="/accounting/chart-of-accounts" class="breadcrumb-item">
              <i class="fas fa-sitemap"></i>
              Chart of Accounts
            </router-link>
            <i class="fas fa-chevron-right"></i>
            <span class="breadcrumb-current">{{ isEdit ? 'Edit Account' : 'Create Account' }}</span>
          </div>
          <h1 class="page-title">
            <i :class="isEdit ? 'fas fa-edit' : 'fas fa-plus'"></i>
            {{ isEdit ? 'Edit Account' : 'Create New Account' }}
          </h1>
          <p class="page-subtitle">
            {{ isEdit ? 'Update account information and settings' : 'Add a new account to your chart of accounts' }}
          </p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/chart-of-accounts" class="btn btn-secondary">
            <i class="fas fa-times"></i>
            Cancel
          </router-link>
          <button @click="saveAccount" class="btn btn-primary" :disabled="!isFormValid || saving">
            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
            <i v-else :class="isEdit ? 'fas fa-save' : 'fas fa-plus'"></i>
            {{ isEdit ? 'Update Account' : 'Create Account' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>{{ isEdit ? 'Loading account details...' : 'Loading form data...' }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Error Loading Form</h3>
      <p>{{ error }}</p>
      <button @click="loadFormData" class="btn btn-primary">Try Again</button>
    </div>

    <!-- Form Content -->
    <div v-else class="form-content">
      <form @submit.prevent="saveAccount" class="account-form">
        <!-- Basic Information Card -->
        <div class="form-card">
          <div class="card-header">
            <h2>
              <i class="fas fa-info-circle"></i>
              Basic Information
            </h2>
          </div>
          <div class="card-body">
            <div class="form-grid">
              <!-- Account Code -->
              <div class="form-group" :class="{ error: errors.account_code }">
                <label for="account_code" class="form-label required">
                  Account Code
                </label>
                <input
                  id="account_code"
                  v-model="form.account_code"
                  type="text"
                  class="form-input"
                  placeholder="e.g., 1001"
                  maxlength="50"
                  @blur="validateField('account_code')"
                  @input="clearError('account_code')"
                />
                <div v-if="errors.account_code" class="error-message">
                  {{ errors.account_code }}
                </div>
                <div class="field-hint">
                  Unique identifier for this account. Use a systematic numbering scheme.
                </div>
              </div>

              <!-- Account Name -->
              <div class="form-group" :class="{ error: errors.name }">
                <label for="name" class="form-label required">
                  Account Name
                </label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="form-input"
                  placeholder="e.g., Cash and Cash Equivalents"
                  maxlength="100"
                  @blur="validateField('name')"
                  @input="clearError('name')"
                />
                <div v-if="errors.name" class="error-message">
                  {{ errors.name }}
                </div>
                <div class="field-hint">
                  Descriptive name for the account.
                </div>
              </div>

              <!-- Account Type -->
              <div class="form-group" :class="{ error: errors.account_type }">
                <label for="account_type" class="form-label required">
                  Account Type
                </label>
                <select
                  id="account_type"
                  v-model="form.account_type"
                  class="form-select"
                  @change="onAccountTypeChange"
                >
                  <option value="">Select Account Type</option>
                  <option value="Asset">Asset</option>
                  <option value="Liability">Liability</option>
                  <option value="Equity">Equity</option>
                  <option value="Revenue">Revenue</option>
                  <option value="Expense">Expense</option>
                </select>
                <div v-if="errors.account_type" class="error-message">
                  {{ errors.account_type }}
                </div>
                <div class="field-hint">
                  Classification determines how the account behaves in financial statements.
                </div>
              </div>

              <!-- Parent Account -->
              <div class="form-group" :class="{ error: errors.parent_account_id }">
                <label for="parent_account_id" class="form-label">
                  Parent Account
                </label>
                <select
                  id="parent_account_id"
                  v-model="form.parent_account_id"
                  class="form-select"
                  @change="clearError('parent_account_id')"
                >
                  <option value="">None (Top Level Account)</option>
                  <optgroup 
                    v-for="group in groupedParentAccounts" 
                    :key="group.type" 
                    :label="group.type"
                  >
                    <option 
                      v-for="account in group.accounts" 
                      :key="account.account_id"
                      :value="account.account_id"
                      :disabled="account.account_id === accountId"
                    >
                      {{ account.account_code }} - {{ account.name }}
                    </option>
                  </optgroup>
                </select>
                <div v-if="errors.parent_account_id" class="error-message">
                  {{ errors.parent_account_id }}
                </div>
                <div v-if="parentAccountName" class="selected-parent">
                  <i class="fas fa-link"></i>
                  Parent: {{ parentAccountName }}
                </div>
                <div class="field-hint">
                  Optional. Select a parent account to create a hierarchy.
                </div>
              </div>

              <!-- Status -->
              <div class="form-group">
                <label class="form-label">
                  Account Status
                </label>
                <div class="toggle-switch">
                  <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="toggle-input"
                  />
                  <label for="is_active" class="toggle-label">
                    <span class="toggle-slider"></span>
                    <span class="toggle-text">
                      {{ form.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </label>
                </div>
                <div class="field-hint">
                  Inactive accounts are hidden from most views and cannot be used in new transactions.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Multi-Currency Settings Card -->
        <div class="form-card">
          <div class="card-header">
            <h2>
              <i class="fas fa-money-bill-wave"></i>
              Multi-Currency Settings
            </h2>
            <div class="card-actions">
              <button
                type="button"
                @click="toggleCurrencySettings"
                class="btn btn-sm btn-outline"
              >
                <i :class="showCurrencySettings ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                {{ showCurrencySettings ? 'Hide' : 'Show' }} Settings
              </button>
            </div>
          </div>
          <div v-show="showCurrencySettings" class="card-body">
            <div class="form-grid">
              <!-- Allow Multi-Currency -->
              <div class="form-group full-width">
                <div class="checkbox-group">
                  <input
                    id="allow_multi_currency"
                    v-model="form.allow_multi_currency"
                    type="checkbox"
                    class="checkbox-input"
                    @change="onMultiCurrencyChange"
                  />
                  <label for="allow_multi_currency" class="checkbox-label">
                    <i class="fas fa-globe"></i>
                    Allow Multi-Currency Transactions
                  </label>
                </div>
                <div class="field-hint">
                  Enable this account to handle transactions in multiple currencies.
                </div>
              </div>

              <!-- Default Currency -->
              <div v-if="form.allow_multi_currency" class="form-group" :class="{ error: errors.default_currency }">
                <label for="default_currency" class="form-label">
                  Default Currency
                </label>
                <select
                  id="default_currency"
                  v-model="form.default_currency"
                  class="form-select"
                  @change="clearError('default_currency')"
                >
                  <option value="">Select Default Currency</option>
                  <option 
                    v-for="currency in availableCurrencies" 
                    :key="currency.code"
                    :value="currency.code"
                  >
                    {{ currency.code }} - {{ currency.name }} ({{ currency.symbol }})
                  </option>
                </select>
                <div v-if="errors.default_currency" class="error-message">
                  {{ errors.default_currency }}
                </div>
                <div class="field-hint">
                  The primary currency for this account. Used as default in transactions.
                </div>
              </div>

              <!-- Currency Preview -->
              <div v-if="form.allow_multi_currency && form.default_currency" class="currency-preview">
                <div class="preview-header">
                  <h4>
                    <i class="fas fa-eye"></i>
                    Currency Preview
                  </h4>
                </div>
                <div class="currency-info">
                  <div class="currency-detail">
                    <span class="currency-code">{{ selectedCurrencyInfo?.code }}</span>
                    <span class="currency-name">{{ selectedCurrencyInfo?.name }}</span>
                    <span class="currency-symbol">{{ selectedCurrencyInfo?.symbol }}</span>
                  </div>
                  <div class="decimal-info">
                    Decimal Places: {{ selectedCurrencyInfo?.decimal_places || 2 }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Account Preview Card -->
        <div v-if="isFormValid" class="form-card preview-card">
          <div class="card-header">
            <h2>
              <i class="fas fa-eye"></i>
              Account Preview
            </h2>
          </div>
          <div class="card-body">
            <div class="account-preview">
              <div class="preview-item">
                <span class="preview-label">Full Account</span>
                <span class="preview-value">
                  {{ form.account_code }} - {{ form.name }}
                </span>
              </div>
              <div class="preview-item">
                <span class="preview-label">Type</span>
                <span class="preview-value account-type" :class="form.account_type.toLowerCase()">
                  {{ form.account_type }}
                </span>
              </div>
              <div v-if="parentAccountName" class="preview-item">
                <span class="preview-label">Hierarchy</span>
                <span class="preview-value">
                  {{ parentAccountName }} > {{ form.account_code }} - {{ form.name }}
                </span>
              </div>
              <div class="preview-item">
                <span class="preview-label">Status</span>
                <span class="preview-value status" :class="{ active: form.is_active, inactive: !form.is_active }">
                  <i :class="form.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                  {{ form.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <div v-if="form.allow_multi_currency" class="preview-item">
                <span class="preview-label">Multi-Currency</span>
                <span class="preview-value">
                  <i class="fas fa-globe"></i>
                  Enabled ({{ form.default_currency || 'No default set' }})
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Validation Summary -->
        <div v-if="Object.keys(errors).length > 0" class="validation-summary">
          <div class="validation-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h4>Please fix the following errors:</h4>
          </div>
          <ul class="validation-list">
            <li v-for="(error, field) in errors" :key="field">
              <strong>{{ getFieldLabel(field) }}:</strong> {{ error }}
            </li>
          </ul>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <router-link to="/accounting/chart-of-accounts" class="btn btn-secondary">
            <i class="fas fa-times"></i>
            Cancel
          </router-link>
          <button
            v-if="isEdit"
            type="button"
            @click="resetForm"
            class="btn btn-outline"
            :disabled="saving"
          >
            <i class="fas fa-undo"></i>
            Reset Changes
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="!isFormValid || saving"
          >
            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
            <i v-else :class="isEdit ? 'fas fa-save' : 'fas fa-plus'"></i>
            {{ isEdit ? 'Update Account' : 'Create Account' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Loading Overlay for Save -->
    <div v-if="saving" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>{{ isEdit ? 'Updating account...' : 'Creating account...' }}</p>
        <div class="progress-info">Please wait, this may take a few moments.</div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ChartOfAccountForm',
  props: {
    id: {
      type: [String, Number],
      default: null
    }
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      showCurrencySettings: false,
      
      // Form data
      form: {
        account_code: '',
        name: '',
        account_type: '',
        parent_account_id: '',
        is_active: true,
        default_currency: '',
        allow_multi_currency: false
      },
      
      // Original form data for reset
      originalForm: {},
      
      // Validation errors
      errors: {},
      
      // Options data
      accounts: [],
      availableCurrencies: [],
      
      // Validation rules
      validationRules: {
        account_code: [
          { required: true, message: 'Account code is required' },
          { maxLength: 50, message: 'Account code must not exceed 50 characters' },
          { pattern: /^[A-Za-z0-9.-]+$/, message: 'Account code can only contain letters, numbers, dots, and hyphens' }
        ],
        name: [
          { required: true, message: 'Account name is required' },
          { maxLength: 100, message: 'Account name must not exceed 100 characters' }
        ],
        account_type: [
          { required: true, message: 'Account type is required' }
        ],
        default_currency: [
          { 
            conditional: () => this.form.allow_multi_currency,
            rule: { required: true, message: 'Default currency is required when multi-currency is enabled' }
          }
        ]
      }
    };
  },
  computed: {
    isEdit() {
      return !!this.id;
    },
    accountId() {
      return this.id;
    },
    isFormValid() {
      return (
        this.form.account_code &&
        this.form.name &&
        this.form.account_type &&
        Object.keys(this.errors).length === 0 &&
        (!this.form.allow_multi_currency || this.form.default_currency)
      );
    },
    groupedParentAccounts() {
      const groups = {};
      
      // Filter out current account and accounts of different types
      const availableParents = this.accounts.filter(account => {
        return account.account_id !== this.accountId &&
               (this.form.account_type === '' || account.account_type === this.form.account_type);
      });
      
      availableParents.forEach(account => {
        if (!groups[account.account_type]) {
          groups[account.account_type] = [];
        }
        groups[account.account_type].push(account);
      });
      
      return Object.keys(groups).map(type => ({
        type,
        accounts: groups[type].sort((a, b) => a.account_code.localeCompare(b.account_code))
      }));
    },
    parentAccountName() {
      if (!this.form.parent_account_id) return null;
      const parent = this.accounts.find(acc => acc.account_id == this.form.parent_account_id);
      return parent ? `${parent.account_code} - ${parent.name}` : null;
    },
    selectedCurrencyInfo() {
      if (!this.form.default_currency) return null;
      return this.availableCurrencies.find(c => c.code === this.form.default_currency);
    }
  },
  async mounted() {
    await this.loadFormData();
  },
  methods: {
    async loadFormData() {
      this.loading = true;
      this.error = null;
      
      try {
        // Load currencies and accounts in parallel
        const [currenciesResponse, accountsResponse] = await Promise.all([
          axios.get('/accounting/chart-of-accounts/currencies/available'),
          axios.get('/accounting/chart-of-accounts')
        ]);

        this.availableCurrencies = currenciesResponse.data.data || [];
        this.accounts = accountsResponse.data.data || [];

        // If editing, load the specific account
        if (this.isEdit) {
          await this.loadAccount();
        } else {
          // Set default currency for new accounts
          const baseCurrency = this.availableCurrencies.find(c => c.code === 'USD');
          if (baseCurrency) {
            this.form.default_currency = baseCurrency.code;
          }
        }

        // Store original form state
        this.originalForm = { ...this.form };
      } catch (error) {
        console.error('Error loading form data:', error);
        this.error = error.response?.data?.message || 'Failed to load form data';
      } finally {
        this.loading = false;
      }
    },

    async loadAccount() {
      try {
        const response = await axios.get(`/accounting/chart-of-accounts/${this.id}`);
        const account = response.data.data;
        
        this.form = {
          account_code: account.account_code || '',
          name: account.name || '',
          account_type: account.account_type || '',
          parent_account_id: account.parent_account_id || '',
          is_active: account.is_active !== false,
          default_currency: account.default_currency || '',
          allow_multi_currency: account.allow_multi_currency || false
        };

        // Show currency settings if multi-currency is enabled
        if (this.form.allow_multi_currency) {
          this.showCurrencySettings = true;
        }
      } catch (error) {
        console.error('Error loading account:', error);
        throw error;
      }
    },

    async saveAccount() {
      if (!this.isFormValid || this.saving) return;

      // Final validation
      this.validateForm();
      if (Object.keys(this.errors).length > 0) {
        this.$toast.error('Please fix validation errors before saving');
        return;
      }

      this.saving = true;
      
      try {
        const accountData = { ...this.form };
        
        // Remove empty values
        Object.keys(accountData).forEach(key => {
          if (accountData[key] === '' || accountData[key] === null) {
            delete accountData[key];
          }
        });

        let response;
        if (this.isEdit) {
          response = await axios.put(`/accounting/chart-of-accounts/${this.id}`, accountData);
        } else {
          response = await axios.post('/accounting/chart-of-accounts', accountData);
        }

        const message = response.data.message || 
          (this.isEdit ? 'Account updated successfully' : 'Account created successfully');
        
        this.$toast.success(message);
        
        // Navigate to the account detail page
        const accountId = this.isEdit ? this.id : response.data.data.account_id;
        this.$router.push(`/accounting/chart-of-accounts/${accountId}`);
      } catch (error) {
        console.error('Error saving account:', error);
        
        if (error.response?.data?.errors) {
          // Handle validation errors from server
          this.errors = error.response.data.errors;
        } else {
          const message = error.response?.data?.message || 
            (this.isEdit ? 'Failed to update account' : 'Failed to create account');
          this.$toast.error(message);
        }
      } finally {
        this.saving = false;
      }
    },

    validateForm() {
      this.errors = {};
      
      // Validate each field
      Object.keys(this.validationRules).forEach(field => {
        this.validateField(field);
      });

      // Custom validations
      this.validateCustomRules();
    },

    validateField(field) {
      const value = this.form[field];
      const rules = this.validationRules[field];
      
      if (!rules) return;

      for (const rule of rules) {
        // Handle conditional rules
        if (rule.conditional && !rule.conditional()) {
          continue;
        }
        
        const actualRule = rule.rule || rule;
        
        // Required validation
        if (actualRule.required && (!value || value.toString().trim() === '')) {
          this.errors[field] = actualRule.message;
          return;
        }
        
        // Skip other validations if value is empty and not required
        if (!value || value.toString().trim() === '') continue;
        
        // Max length validation
        if (actualRule.maxLength && value.length > actualRule.maxLength) {
          this.errors[field] = actualRule.message;
          return;
        }
        
        // Pattern validation
        if (actualRule.pattern && !actualRule.pattern.test(value)) {
          this.errors[field] = actualRule.message;
          return;
        }
      }
      
      // Clear error if validation passes
      if (this.errors[field]) {
        delete this.errors[field];
      }
    },

    async validateCustomRules() {
      // Check for duplicate account code
      if (this.form.account_code) {
        try {
          const params = {
            account_code: this.form.account_code
          };
          if (this.isEdit) {
            params.exclude_id = this.id;
          }
          
          // This would need a custom endpoint to check duplicates
          // For now, we'll skip this validation
        } catch (error) {
          // Handle validation error
        }
      }

      // Validate parent account relationship
      if (this.form.parent_account_id) {
        const parent = this.accounts.find(acc => acc.account_id == this.form.parent_account_id);
        if (parent && parent.account_type !== this.form.account_type) {
          this.errors.parent_account_id = 'Parent account must be of the same type';
        }
      }
    },

    clearError(field) {
      if (this.errors[field]) {
        delete this.errors[field];
      }
    },

    onAccountTypeChange() {
      // Clear parent account if type changes
      if (this.form.parent_account_id) {
        const parent = this.accounts.find(acc => acc.account_id == this.form.parent_account_id);
        if (parent && parent.account_type !== this.form.account_type) {
          this.form.parent_account_id = '';
        }
      }
      this.clearError('account_type');
    },

    onMultiCurrencyChange() {
      if (!this.form.allow_multi_currency) {
        this.form.default_currency = '';
        this.clearError('default_currency');
      }
    },

    toggleCurrencySettings() {
      this.showCurrencySettings = !this.showCurrencySettings;
    },

    resetForm() {
      this.form = { ...this.originalForm };
      this.errors = {};
    },

    getFieldLabel(field) {
      const labels = {
        account_code: 'Account Code',
        name: 'Account Name',
        account_type: 'Account Type',
        parent_account_id: 'Parent Account',
        default_currency: 'Default Currency'
      };
      return labels[field] || field;
    }
  }
};
</script>

<style scoped>
/* Base Styles */
.account-form-page {
  padding: 1rem;
  background: var(--bg-primary, #f8fafc);
  min-height: 100vh;
}

/* Page Header */
.page-header {
  background: var(--white, #ffffff);
  border-radius: var(--border-radius, 8px);
  box-shadow: var(--box-shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
  margin-bottom: 1.5rem;
  position: sticky;
  top: 1rem;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
}

.title-section {
  flex: 1;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.breadcrumb-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  color: var(--primary-color, #3b82f6);
  text-decoration: none;
}

.breadcrumb-current {
  color: var(--text-secondary, #6b7280);
}

.page-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 0.5rem 0;
  color: var(--text-primary, #1f2937);
  font-size: 1.75rem;
  font-weight: 600;
}

.page-subtitle {
  color: var(--text-secondary, #6b7280);
  margin: 0;
  font-size: 0.875rem;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

/* Form Content */
.form-content {
  max-width: 800px;
  margin: 0 auto;
}

.account-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Form Cards */
.form-card {
  background: var(--white, #ffffff);
  border-radius: var(--border-radius, 8px);
  box-shadow: var(--box-shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  background: var(--bg-secondary, #f1f5f9);
}

.card-header h2 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  color: var(--text-primary, #1f2937);
  font-size: 1.125rem;
  font-weight: 600;
}

.card-actions {
  display: flex;
  gap: 0.5rem;
}

.card-body {
  padding: 1.5rem;
}

/* Form Layout */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.full-width {
  grid-column: 1 / -1;
}

/* Form Groups */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group.error .form-input,
.form-group.error .form-select {
  border-color: #dc2626;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary, #1f2937);
}

.form-label.required::after {
  content: ' *';
  color: #dc2626;
}

.form-input,
.form-select {
  padding: 0.75rem;
  border: 2px solid var(--border-color, #e5e7eb);
  border-radius: var(--border-radius, 8px);
  font-size: 0.875rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--primary-color, #3b82f6);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.error-message {
  color: #dc2626;
  font-size: 0.75rem;
  font-weight: 500;
}

.field-hint {
  color: var(--text-secondary, #6b7280);
  font-size: 0.75rem;
  line-height: 1.4;
}

/* Toggle Switch */
.toggle-switch {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.toggle-input {
  display: none;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.toggle-slider {
  position: relative;
  width: 48px;
  height: 24px;
  background: #d1d5db;
  border-radius: 12px;
  transition: background-color 0.2s;
}

.toggle-slider::before {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s;
}

.toggle-input:checked + .toggle-label .toggle-slider {
  background: var(--primary-color, #3b82f6);
}

.toggle-input:checked + .toggle-label .toggle-slider::before {
  transform: translateX(24px);
}

.toggle-text {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary, #1f2937);
}

/* Checkbox Group */
.checkbox-group {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.checkbox-input {
  width: 18px;
  height: 18px;
  accent-color: var(--primary-color, #3b82f6);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary, #1f2937);
  cursor: pointer;
}

/* Selected Parent */
.selected-parent {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  background: var(--bg-secondary, #f1f5f9);
  border-radius: 4px;
  font-size: 0.875rem;
  color: var(--text-secondary, #6b7280);
}

/* Currency Preview */
.currency-preview {
  grid-column: 1 / -1;
  padding: 1rem;
  background: var(--bg-secondary, #f1f5f9);
  border-radius: var(--border-radius, 8px);
  border: 1px solid var(--border-color, #e5e7eb);
}

.preview-header {
  margin-bottom: 0.75rem;
}

.preview-header h4 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  color: var(--text-primary, #1f2937);
  font-size: 0.875rem;
  font-weight: 600;
}

.currency-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.currency-detail {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.currency-code {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  color: var(--primary-color, #3b82f6);
}

.currency-name {
  color: var(--text-primary, #1f2937);
}

.currency-symbol {
  padding: 0.25rem 0.5rem;
  background: var(--white, #ffffff);
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.875rem;
}

.decimal-info {
  color: var(--text-secondary, #6b7280);
  font-size: 0.75rem;
}

/* Account Preview */
.preview-card {
  border-left: 4px solid var(--primary-color, #3b82f6);
}

.account-preview {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.preview-item:last-child {
  border-bottom: none;
}

.preview-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary, #6b7280);
}

.preview-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary, #1f2937);
  text-align: right;
}

.preview-value.account-type {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.account-type.asset { background: #dbeafe; color: #1e40af; }
.account-type.liability { background: #fee2e2; color: #dc2626; }
.account-type.equity { background: #d1fae5; color: #065f46; }
.account-type.revenue { background: #e0e7ff; color: #5b21b6; }
.account-type.expense { background: #fef3c7; color: #92400e; }

.preview-value.status {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.preview-value.status.active { color: #059669; }
.preview-value.status.inactive { color: #dc2626; }

/* Validation Summary */
.validation-summary {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: var(--border-radius, 8px);
  padding: 1rem;
}

.validation-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.validation-header i {
  color: #dc2626;
  font-size: 1.125rem;
}

.validation-header h4 {
  color: #dc2626;
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
}

.validation-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.validation-list li {
  color: #7f1d1d;
  font-size: 0.875rem;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  background: var(--white, #ffffff);
  border-radius: var(--border-radius, 8px);
  box-shadow: var(--box-shadow, 0 1px 3px rgba(0, 0, 0, 0.1));
  position: sticky;
  bottom: 1rem;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: var(--border-radius, 8px);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary-color, #3b82f6);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-secondary {
  background: var(--bg-secondary, #f1f5f9);
  color: var(--text-primary, #1f2937);
}

.btn-secondary:hover:not(:disabled) {
  background: #e2e8f0;
}

.btn-outline {
  background: transparent;
  border: 1px solid var(--border-color, #e5e7eb);
  color: var(--text-primary, #1f2937);
}

.btn-outline:hover:not(:disabled) {
  background: var(--bg-secondary, #f1f5f9);
}

.btn-sm {
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Loading States */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--border-color, #e5e7eb);
  border-top-color: var(--primary-color, #3b82f6);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(248, 250, 252, 0.95);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.loading-content {
  text-align: center;
  color: var(--text-primary, #1f2937);
}

.progress-info {
  color: var(--text-secondary, #6b7280);
  font-size: 0.875rem;
  margin-top: 0.5rem;
}

/* Error States */
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.error-container i {
  font-size: 3rem;
  color: #dc2626;
  margin-bottom: 1rem;
}

.error-container h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary, #1f2937);
}

.error-container p {
  margin: 0 0 1.5rem 0;
  color: var(--text-secondary, #6b7280);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .account-form-page {
    padding: 0.5rem;
  }

  .form-content {
    max-width: none;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .page-title {
    font-size: 1.5rem;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .card-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .card-body {
    padding: 1rem;
  }

  .form-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .preview-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .preview-value {
    text-align: left;
  }

  .currency-detail {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}

@media (max-width: 480px) {
  .breadcrumb {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .breadcrumb i {
    display: none;
  }

  .toggle-switch {
    flex-direction: column;
    align-items: flex-start;
  }

  .checkbox-group {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>