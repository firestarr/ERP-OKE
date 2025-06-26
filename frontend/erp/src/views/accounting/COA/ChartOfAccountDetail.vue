<!-- src/views/accounting/COA/ChartOfAccountDetail.vue -->
<template>
  <div class="account-detail-page">
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
            <span class="breadcrumb-current">Account Details</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-file-alt"></i>
            Account Details
          </h1>
          <p class="page-subtitle" v-if="account">
            Detailed information for {{ account.account_code }} - {{ account.name }}
          </p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button @click="showBalanceHistory" class="btn btn-info" v-if="account">
            <i class="fas fa-chart-line"></i>
            Balance History
          </button>
          <router-link 
            v-if="account" 
            :to="`/accounting/chart-of-accounts/${account.account_id}/edit`" 
            class="btn btn-outline"
          >
            <i class="fas fa-edit"></i>
            Edit Account
          </router-link>
          <button @click="deleteAccount" class="btn btn-danger" v-if="account && canDelete" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-trash"></i>
            Delete
          </button>
          <router-link to="/accounting/chart-of-accounts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Account
          </router-link>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading account details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Error Loading Account</h3>
      <p>{{ error }}</p>
      <button @click="loadAccount" class="btn btn-primary">Try Again</button>
    </div>

    <!-- Account Content -->
    <div v-else-if="account" class="account-content">
      <!-- Account Overview Cards -->
      <div class="overview-grid">
        <!-- Main Account Info -->
        <div class="info-card main-info">
          <div class="card-header">
            <h2>
              <i class="fas fa-info-circle"></i>
              Account Information
            </h2>
            <div class="account-status" :class="{ active: account.is_active, inactive: !account.is_active }">
              <i :class="account.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
              {{ account.is_active ? 'Active' : 'Inactive' }}
            </div>
          </div>
          <div class="card-content">
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Account Code</span>
                <span class="info-value code">{{ account.account_code }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Account Name</span>
                <span class="info-value">{{ account.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Account Type</span>
                <span class="info-value type" :class="account.account_type.toLowerCase()">
                  {{ account.account_type }}
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">Parent Account</span>
                <span class="info-value" v-if="account.parent_account">
                  <router-link 
                    :to="`/accounting/chart-of-accounts/${account.parent_account.account_id}`"
                    class="parent-link"
                  >
                    {{ account.parent_account.account_code }} - {{ account.parent_account.name }}
                  </router-link>
                </span>
                <span class="info-value muted" v-else>None (Top Level)</span>
              </div>
              <div class="info-item" v-if="account.default_currency">
                <span class="info-label">Default Currency</span>
                <span class="info-value currency">
                  <i class="fas fa-money-bill-wave"></i>
                  {{ account.default_currency }}
                </span>
              </div>
              <div class="info-item" v-if="account.allow_multi_currency">
                <span class="info-label">Multi-Currency</span>
                <span class="info-value">
                  <i class="fas fa-globe"></i>
                  Enabled
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Balance Information -->
        <div class="info-card balance-info">
          <div class="card-header">
            <h2>
              <i class="fas fa-balance-scale"></i>
              Balance Information
            </h2>
            <div class="balance-controls">
              <select v-model="selectedCurrency" @change="loadBalanceInCurrencies" class="currency-select">
                <option value="">Base Currency</option>
                <option v-for="currency in availableCurrencies" :key="currency.code" :value="currency.code">
                  {{ currency.code }}
                </option>
              </select>
              <input 
                v-model="asOfDate" 
                type="date" 
                @change="loadBalanceInCurrencies"
                class="date-input"
              />
            </div>
          </div>
          <div class="card-content">
            <div v-if="balanceLoading" class="balance-loading">
              <div class="loading-spinner small"></div>
              <span>Loading balances...</span>
            </div>
            <div v-else-if="balanceData" class="balance-grid">
              <div class="balance-item primary">
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount">
                  {{ formatCurrency(balanceData.base_balance || 0) }}
                </div>
                <div class="balance-currency">Base Currency</div>
              </div>
              <div v-if="selectedCurrency && balanceData.converted_balance" class="balance-item secondary">
                <div class="balance-label">Converted Balance</div>
                <div class="balance-amount">
                  {{ formatCurrency(balanceData.converted_balance, selectedCurrency) }}
                </div>
                <div class="balance-currency">{{ selectedCurrency }}</div>
                <div class="exchange-rate" v-if="balanceData.exchange_rate">
                  Rate: {{ balanceData.exchange_rate }}
                </div>
              </div>
              <div v-if="balanceData.multi_currency_balances" class="multi-currency-section">
                <h4>Multi-Currency Balances</h4>
                <div class="currency-balances">
                  <div 
                    v-for="balance in balanceData.multi_currency_balances" 
                    :key="balance.currency"
                    class="currency-balance-item"
                  >
                    <span class="currency-code">{{ balance.currency }}</span>
                    <span class="currency-amount">{{ formatCurrency(balance.balance, balance.currency) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="no-balance">
              <i class="fas fa-info-circle"></i>
              <span>No balance data available</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Account Hierarchy -->
      <div class="info-card hierarchy-card">
        <div class="card-header">
          <h2>
            <i class="fas fa-sitemap"></i>
            Account Hierarchy
          </h2>
          <button @click="expandHierarchy" class="btn btn-sm btn-outline">
            <i :class="hierarchyExpanded ? 'fas fa-compress' : 'fas fa-expand'"></i>
            {{ hierarchyExpanded ? 'Collapse' : 'Expand' }}
          </button>
        </div>
        <div class="card-content">
          <div class="hierarchy-tree">
            <!-- Parent Chain -->
            <div v-if="account.parent_account" class="hierarchy-level parent-level">
              <div class="hierarchy-node">
                <i class="fas fa-folder"></i>
                <router-link 
                  :to="`/accounting/chart-of-accounts/${account.parent_account.account_id}`"
                  class="hierarchy-link"
                >
                  {{ account.parent_account.account_code }} - {{ account.parent_account.name }}
                </router-link>
              </div>
            </div>
            
            <!-- Current Account -->
            <div class="hierarchy-level current-level">
              <div class="hierarchy-node current">
                <i class="fas fa-file-alt"></i>
                <span class="hierarchy-text">
                  {{ account.account_code }} - {{ account.name }}
                </span>
                <span class="current-indicator">Current</span>
              </div>
            </div>

            <!-- Child Accounts -->
            <div v-if="account.child_accounts && account.child_accounts.length > 0" class="hierarchy-level child-level">
              <div class="child-header">
                <i class="fas fa-folder-open"></i>
                <span>Child Accounts ({{ account.child_accounts.length }})</span>
              </div>
              <div class="child-accounts" :class="{ expanded: hierarchyExpanded }">
                <div 
                  v-for="child in visibleChildAccounts" 
                  :key="child.account_id"
                  class="hierarchy-node child"
                >
                  <i class="fas fa-file-alt"></i>
                  <router-link 
                    :to="`/accounting/chart-of-accounts/${child.account_id}`"
                    class="hierarchy-link"
                  >
                    {{ child.account_code }} - {{ child.name }}
                  </router-link>
                  <span class="child-type">{{ child.account_type }}</span>
                </div>
                <div v-if="!hierarchyExpanded && account.child_accounts.length > 3" class="more-children">
                  <button @click="hierarchyExpanded = true" class="btn btn-sm btn-link">
                    Show {{ account.child_accounts.length - 3 }} more...
                  </button>
                </div>
              </div>
            </div>
            
            <div v-else class="no-children">
              <i class="fas fa-info-circle"></i>
              <span>No child accounts</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="info-card transactions-card">
        <div class="card-header">
          <h2>
            <i class="fas fa-list"></i>
            Recent Transactions
          </h2>
          <div class="transaction-controls">
            <select v-model="transactionLimit" @change="loadRecentTransactions" class="limit-select">
              <option value="10">10 transactions</option>
              <option value="25">25 transactions</option>
              <option value="50">50 transactions</option>
            </select>
            <router-link 
              :to="`/accounting/journal-entries?account_id=${account.account_id}`"
              class="btn btn-sm btn-outline"
            >
              View All
            </router-link>
          </div>
        </div>
        <div class="card-content">
          <div v-if="transactionsLoading" class="transactions-loading">
            <div class="loading-spinner small"></div>
            <span>Loading transactions...</span>
          </div>
          <div v-else-if="recentTransactions && recentTransactions.length > 0" class="transactions-table">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Entry</th>
                  <th>Description</th>
                  <th>Debit</th>
                  <th>Credit</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="transaction in recentTransactions" :key="transaction.id">
                  <td>{{ formatDate(transaction.transaction_date) }}</td>
                  <td>
                    <router-link 
                      :to="`/accounting/journal-entries/${transaction.journal_entry_id}`"
                      class="entry-link"
                    >
                      {{ transaction.entry_number }}
                    </router-link>
                  </td>
                  <td class="description">{{ transaction.description }}</td>
                  <td class="amount debit">
                    {{ transaction.debit_amount > 0 ? formatCurrency(transaction.debit_amount) : '-' }}
                  </td>
                  <td class="amount credit">
                    {{ transaction.credit_amount > 0 ? formatCurrency(transaction.credit_amount) : '-' }}
                  </td>
                  <td class="amount balance">
                    {{ formatCurrency(transaction.running_balance) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="no-transactions">
            <i class="fas fa-inbox"></i>
            <h4>No Recent Transactions</h4>
            <p>This account has no recent transaction history.</p>
            <router-link 
              to="/accounting/journal-entries/create"
              class="btn btn-primary"
            >
              Create Journal Entry
            </router-link>
          </div>
        </div>
      </div>

      <!-- Account Settings -->
      <div class="info-card settings-card">
        <div class="card-header">
          <h2>
            <i class="fas fa-cog"></i>
            Account Settings
          </h2>
        </div>
        <div class="card-content">
          <div class="settings-grid">
            <div class="setting-item">
              <div class="setting-label">
                <i class="fas fa-toggle-on"></i>
                Status
              </div>
              <div class="setting-value">
                <span class="status-badge" :class="{ active: account.is_active, inactive: !account.is_active }">
                  {{ account.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
            <div class="setting-item" v-if="account.allow_multi_currency">
              <div class="setting-label">
                <i class="fas fa-globe"></i>
                Multi-Currency
              </div>
              <div class="setting-value">
                <span class="feature-badge enabled">Enabled</span>
                <span v-if="account.default_currency" class="default-currency">
                  Default: {{ account.default_currency }}
                </span>
              </div>
            </div>
            <div class="setting-item">
              <div class="setting-label">
                <i class="fas fa-lock"></i>
                Deletion
              </div>
              <div class="setting-value">
                <span class="feature-badge" :class="canDelete ? 'allowed' : 'restricted'">
                  {{ canDelete ? 'Allowed' : 'Restricted' }}
                </span>
                <span v-if="!canDelete" class="restriction-reason">
                  {{ deletionRestriction }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Balance History Modal -->
    <div v-if="showBalanceHistoryModal" class="modal-overlay" @click="closeBalanceHistory">
      <div class="modal-content balance-history-modal" @click.stop>
        <div class="modal-header">
          <h2>
            <i class="fas fa-chart-line"></i>
            Balance History - {{ account?.account_code }}
          </h2>
          <button @click="closeBalanceHistory" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="history-controls">
            <div class="control-group">
              <label>Period:</label>
              <select v-model="historyPeriod" @change="loadBalanceHistory" class="form-select">
                <option value="30">Last 30 days</option>
                <option value="90">Last 3 months</option>
                <option value="180">Last 6 months</option>
                <option value="365">Last year</option>
              </select>
            </div>
            <div class="control-group">
              <label>Currency:</label>
              <select v-model="historyCurrency" @change="loadBalanceHistory" class="form-select">
                <option value="">Base Currency</option>
                <option v-for="currency in availableCurrencies" :key="currency.code" :value="currency.code">
                  {{ currency.code }}
                </option>
              </select>
            </div>
          </div>
          <div v-if="balanceHistoryLoading" class="loading-container">
            <div class="loading-spinner"></div>
            <p>Loading balance history...</p>
          </div>
          <div v-else-if="balanceHistory" class="balance-history">
            <!-- This would contain a chart component -->
            <div class="chart-placeholder">
              <i class="fas fa-chart-line"></i>
              <p>Balance history chart would be displayed here</p>
              <p class="chart-note">Integrate with Chart.js or similar charting library</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content delete-modal" @click.stop>
        <div class="modal-header">
          <h2>
            <i class="fas fa-exclamation-triangle"></i>
            Confirm Delete
          </h2>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this account?</p>
          <div class="account-info">
            <strong>{{ account?.account_code }} - {{ account?.name }}</strong>
          </div>
          <div class="warning-text">
            This action cannot be undone. All associated data will be permanently removed.
          </div>
        </div>
        <div class="modal-actions">
          <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            <span v-else>Delete Account</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ChartOfAccountDetail',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      account: null,
      loading: false,
      error: null,
      
      // Balance data
      balanceData: null,
      balanceLoading: false,
      selectedCurrency: '',
      asOfDate: new Date().toISOString().split('T')[0],
      availableCurrencies: [],
      
      // Hierarchy
      hierarchyExpanded: false,
      
      // Transactions
      recentTransactions: [],
      transactionsLoading: false,
      transactionLimit: 10,
      
      // Balance History
      showBalanceHistoryModal: false,
      balanceHistory: null,
      balanceHistoryLoading: false,
      historyPeriod: 90,
      historyCurrency: '',
      
      // Delete
      showDeleteModal: false,
      deleting: false,
      
      // Computed properties for restrictions
      canDelete: true,
      deletionRestriction: ''
    };
  },
  computed: {
    visibleChildAccounts() {
      if (!this.account?.child_accounts) return [];
      return this.hierarchyExpanded 
        ? this.account.child_accounts 
        : this.account.child_accounts.slice(0, 3);
    }
  },
  async mounted() {
    await this.loadAvailableCurrencies();
    await this.loadAccount();
    await this.loadBalanceInCurrencies();
    await this.loadRecentTransactions();
    this.checkDeletionRestrictions();
  },
  methods: {
    async loadAccount() {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await axios.get(`/accounting/chart-of-accounts/${this.id}`);
        this.account = response.data.data;
      } catch (error) {
        console.error('Error loading account:', error);
        this.error = error.response?.data?.message || 'Failed to load account details';
      } finally {
        this.loading = false;
      }
    },

    async loadAvailableCurrencies() {
      try {
        const response = await axios.get('/accounting/chart-of-accounts/currencies/available');
        this.availableCurrencies = response.data.data || [];
      } catch (error) {
        console.error('Error loading currencies:', error);
      }
    },

    async loadBalanceInCurrencies() {
      if (!this.account) return;
      
      this.balanceLoading = true;
      
      try {
        const params = {
          as_of_date: this.asOfDate
        };
        
        if (this.selectedCurrency) {
          params.currencies = [this.selectedCurrency];
        }

        const response = await axios.get(
          `/accounting/chart-of-accounts/${this.account.account_id}/balances-in-currencies`,
          { params }
        );
        
        this.balanceData = response.data.data;
      } catch (error) {
        console.error('Error loading balance:', error);
        this.balanceData = null;
      } finally {
        this.balanceLoading = false;
      }
    },

    async loadRecentTransactions() {
      if (!this.account) return;
      
      this.transactionsLoading = true;
      
      try {
        // This would need a specific endpoint for account transactions
        // For now, we'll simulate the data structure
        const response = await axios.get(`/accounting/accounts/${this.account.account_id}/transactions`, {
          params: {
            limit: this.transactionLimit,
            order: 'desc'
          }
        });
        
        this.recentTransactions = response.data.data || [];
      } catch (error) {
        console.error('Error loading transactions:', error);
        this.recentTransactions = [];
      } finally {
        this.transactionsLoading = false;
      }
    },

    async refreshData() {
      await Promise.all([
        this.loadAccount(),
        this.loadBalanceInCurrencies(),
        this.loadRecentTransactions()
      ]);
    },

    expandHierarchy() {
      this.hierarchyExpanded = !this.hierarchyExpanded;
    },

    showBalanceHistory() {
      this.showBalanceHistoryModal = true;
      this.loadBalanceHistory();
    },

    async loadBalanceHistory() {
      this.balanceHistoryLoading = true;
      
      try {
        // This would need a specific endpoint for balance history
        const params = {
          account_id: this.account.account_id,
          days: this.historyPeriod,
          currency: this.historyCurrency || undefined
        };
        
        const response = await axios.get('/accounting/accounts/balance-history', { params });
        this.balanceHistory = response.data.data;
      } catch (error) {
        console.error('Error loading balance history:', error);
        this.balanceHistory = null;
      } finally {
        this.balanceHistoryLoading = false;
      }
    },

    closeBalanceHistory() {
      this.showBalanceHistoryModal = false;
      this.balanceHistory = null;
    },

    deleteAccount() {
      if (!this.canDelete) {
        this.$toast.error(this.deletionRestriction);
        return;
      }
      this.showDeleteModal = true;
    },

    async confirmDelete() {
      this.deleting = true;
      
      try {
        await axios.delete(`/accounting/chart-of-accounts/${this.account.account_id}`);
        this.$toast.success('Account deleted successfully');
        this.$router.push('/accounting/chart-of-accounts');
      } catch (error) {
        console.error('Error deleting account:', error);
        this.$toast.error(error.response?.data?.message || 'Failed to delete account');
      } finally {
        this.deleting = false;
        this.closeDeleteModal();
      }
    },

    closeDeleteModal() {
      this.showDeleteModal = false;
    },

    async checkDeletionRestrictions() {
      if (!this.account) return;
      
      try {
        // Check if account has child accounts
        if (this.account.child_accounts && this.account.child_accounts.length > 0) {
          this.canDelete = false;
          this.deletionRestriction = 'Account has child accounts';
          return;
        }

        // Check if account has transactions (this would need a specific endpoint)
        const response = await axios.get(`/accounting/accounts/${this.account.account_id}/can-delete`);
        this.canDelete = response.data.can_delete;
        this.deletionRestriction = response.data.reason || '';
      } catch (error) {
        console.error('Error checking deletion restrictions:', error);
        this.canDelete = false;
        this.deletionRestriction = 'Unable to verify deletion eligibility';
      }
    },

    formatCurrency(amount, currency = 'USD') {
      if (amount === undefined || amount === null) return '-';
      const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
      });
      return formatter.format(amount);
    },

    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    }
  }
};
</script>

<style scoped>
/* Base Styles */
.account-detail-page {
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
  flex-wrap: wrap;
}

/* Account Content */
.account-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.overview-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

/* Info Cards */
.info-card {
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

.card-content {
  padding: 1.5rem;
}

/* Account Status */
.account-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: var(--border-radius, 8px);
  font-size: 0.875rem;
  font-weight: 500;
}

.account-status.active {
  background: #d1fae5;
  color: #065f46;
}

.account-status.inactive {
  background: #fee2e2;
  color: #dc2626;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.info-item:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary, #6b7280);
}

.info-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary, #1f2937);
  text-align: right;
}

.info-value.code {
  font-family: 'Courier New', monospace;
  background: var(--bg-secondary, #f1f5f9);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.info-value.type {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  text-transform: uppercase;
}

.type.asset { background: #dbeafe; color: #1e40af; }
.type.liability { background: #fee2e2; color: #dc2626; }
.type.equity { background: #d1fae5; color: #065f46; }
.type.revenue { background: #e0e7ff; color: #5b21b6; }
.type.expense { background: #fef3c7; color: #92400e; }

.info-value.currency {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.info-value.muted {
  color: var(--text-secondary, #6b7280);
  font-style: italic;
}

.parent-link {
  color: var(--primary-color, #3b82f6);
  text-decoration: none;
  font-family: 'Courier New', monospace;
}

/* Balance Controls */
.balance-controls {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.currency-select,
.date-input {
  padding: 0.5rem;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 4px;
  font-size: 0.75rem;
}

/* Balance Information */
.balance-loading,
.transactions-loading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-secondary, #6b7280);
  font-size: 0.875rem;
}

.loading-spinner.small {
  width: 20px;
  height: 20px;
  border-width: 2px;
}

.balance-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.balance-item {
  padding: 1rem;
  border-radius: var(--border-radius, 8px);
  text-align: center;
}

.balance-item.primary {
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: white;
}

.balance-item.secondary {
  background: var(--bg-secondary, #f1f5f9);
  border: 1px solid var(--border-color, #e5e7eb);
}

.balance-label {
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.5rem;
  opacity: 0.8;
}

.balance-amount {
  font-size: 1.5rem;
  font-weight: 700;
  font-family: 'Courier New', monospace;
  margin-bottom: 0.25rem;
}

.balance-currency {
  font-size: 0.75rem;
  opacity: 0.7;
}

.exchange-rate {
  font-size: 0.75rem;
  margin-top: 0.25rem;
  opacity: 0.7;
}

.multi-currency-section {
  margin-top: 1rem;
  padding: 1rem;
  background: var(--bg-secondary, #f1f5f9);
  border-radius: var(--border-radius, 8px);
}

.multi-currency-section h4 {
  margin: 0 0 1rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary, #1f2937);
}

.currency-balances {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.currency-balance-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  background: white;
  border-radius: 4px;
}

.currency-code {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  color: var(--primary-color, #3b82f6);
}

.currency-amount {
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

.no-balance {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-secondary, #6b7280);
  font-size: 0.875rem;
  justify-content: center;
  padding: 2rem;
}

/* Hierarchy */
.hierarchy-tree {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.hierarchy-level {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.hierarchy-node {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: var(--border-radius, 8px);
  transition: background-color 0.2s;
}

.hierarchy-node.current {
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: white;
}

.hierarchy-node.child {
  background: var(--bg-secondary, #f1f5f9);
  margin-left: 2rem;
}

.hierarchy-link {
  color: var(--primary-color, #3b82f6);
  text-decoration: none;
  font-weight: 500;
}

.hierarchy-node.current .hierarchy-link {
  color: white;
}

.current-indicator {
  margin-left: auto;
  padding: 0.25rem 0.5rem;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
}

.child-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: var(--text-primary, #1f2937);
  margin-top: 1rem;
}

.child-accounts:not(.expanded) .hierarchy-node:nth-child(n+4) {
  display: none;
}

.more-children {
  margin-left: 2rem;
  margin-top: 0.5rem;
}

.child-type {
  margin-left: auto;
  padding: 0.25rem 0.5rem;
  background: var(--bg-tertiary, #e2e8f0);
  border-radius: 4px;
  font-size: 0.75rem;
  color: var(--text-secondary, #6b7280);
}

.no-children {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-secondary, #6b7280);
  font-size: 0.875rem;
  padding: 1rem;
  text-align: center;
  justify-content: center;
}

/* Transactions */
.transaction-controls {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.limit-select {
  padding: 0.5rem;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 4px;
  font-size: 0.75rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.data-table th,
.data-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.data-table th {
  background: var(--bg-secondary, #f1f5f9);
  font-weight: 600;
  color: var(--text-primary, #1f2937);
}

.data-table .amount {
  text-align: right;
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

.data-table .debit {
  color: #dc2626;
}

.data-table .credit {
  color: #059669;
}

.data-table .description {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.entry-link {
  color: var(--primary-color, #3b82f6);
  text-decoration: none;
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

.no-transactions {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
  color: var(--text-secondary, #6b7280);
}

.no-transactions i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.no-transactions h4 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary, #1f2937);
}

.no-transactions p {
  margin: 0 0 1.5rem 0;
}

/* Settings */
.settings-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.setting-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-secondary, #f1f5f9);
  border-radius: var(--border-radius, 8px);
}

.setting-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: var(--text-primary, #1f2937);
}

.setting-value {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-badge,
.feature-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-badge.active,
.feature-badge.enabled,
.feature-badge.allowed {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.inactive,
.feature-badge.restricted {
  background: #fee2e2;
  color: #dc2626;
}

.default-currency {
  font-family: 'Courier New', monospace;
  font-size: 0.75rem;
  color: var(--text-secondary, #6b7280);
}

.restriction-reason {
  font-size: 0.75rem;
  color: var(--text-secondary, #6b7280);
  font-style: italic;
}

/* Modals */
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
  background: var(--white, #ffffff);
  border-radius: var(--border-radius, 8px);
  box-shadow: var(--box-shadow-lg, 0 10px 25px rgba(0, 0, 0, 0.15));
  max-width: 90vw;
  max-height: 90vh;
  overflow: auto;
}

.balance-history-modal {
  width: 800px;
}

.delete-modal {
  width: 400px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.modal-header h2 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-primary, #1f2937);
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: var(--text-secondary, #6b7280);
}

.modal-body {
  padding: 1.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color, #e5e7eb);
}

/* History Controls */
.history-controls {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.control-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary, #1f2937);
}

.chart-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  background: var(--bg-secondary, #f1f5f9);
  border-radius: var(--border-radius, 8px);
  text-align: center;
}

.chart-placeholder i {
  font-size: 3rem;
  color: var(--text-secondary, #6b7280);
  margin-bottom: 1rem;
}

.chart-note {
  color: var(--text-secondary, #6b7280);
  font-style: italic;
  font-size: 0.875rem;
}

/* Form Elements */
.form-select {
  padding: 0.5rem;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 4px;
  font-size: 0.875rem;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
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

.btn-secondary {
  background: var(--bg-secondary, #f1f5f9);
  color: var(--text-primary, #1f2937);
}

.btn-outline {
  background: transparent;
  border: 1px solid var(--border-color, #e5e7eb);
  color: var(--text-primary, #1f2937);
}

.btn-info {
  background: #06b6d4;
  color: white;
}

.btn-danger {
  background: #dc2626;
  color: white;
}

.btn-link {
  background: transparent;
  color: var(--primary-color, #3b82f6);
  padding: 0.5rem;
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
  color: var(--text-secondary, #6b7280);
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
  .account-detail-page {
    padding: 0.5rem;
  }

  .overview-grid {
    grid-template-columns: 1fr;
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

  .modal-content {
    margin: 1rem;
    width: auto !important;
  }

  .info-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .info-value {
    text-align: left;
  }

  .balance-controls,
  .transaction-controls,
  .history-controls {
    flex-direction: column;
    align-items: stretch;
  }

  .data-table {
    font-size: 0.75rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.5rem 0.25rem;
  }

  .setting-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>