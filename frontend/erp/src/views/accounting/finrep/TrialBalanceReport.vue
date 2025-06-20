<template>
  <div class="trial-balance-report">
    <!-- Header Section -->
    <div class="report-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="report-title">
            <i class="fas fa-balance-scale"></i>
            Trial Balance Report
          </h1>
          <p class="report-subtitle">
            Summary of all account balances to ensure accounting equation balance
          </p>
        </div>
        <div class="header-actions">
          <select v-model="selectedPeriod" @change="loadTrialBalance" class="period-selector">
            <option value="">Select Period</option>
            <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
              {{ formatPeriodName(period) }}
            </option>
          </select>
          <button @click="exportReport" class="export-btn" :disabled="!reportData.accounts.length">
            <i class="fas fa-download"></i>
            Export
          </button>
          <button @click="printReport" class="print-btn" :disabled="!reportData.accounts.length">
            <i class="fas fa-print"></i>
            Print
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading trial balance data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Error Loading Report</h3>
      <p>{{ error }}</p>
      <button @click="loadTrialBalance" class="retry-btn">
        <i class="fas fa-redo"></i>
        Retry
      </button>
    </div>

    <!-- Report Content -->
    <div v-else-if="reportData.accounts.length" class="report-content">
      <!-- Report Summary -->
      <div class="report-summary">
        <div class="summary-card">
          <div class="summary-info">
            <h3>Report Period</h3>
            <p>{{ formatPeriodName(reportData.period) }}</p>
          </div>
          <div class="summary-stats">
            <div class="stat-item">
              <span class="stat-label">Total Accounts</span>
              <span class="stat-value">{{ reportData.accounts.length }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Balance Status</span>
              <span class="stat-value" :class="getBalanceStatusClass()">
                {{ getBalanceStatus() }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="report-filters">
        <div class="filter-group">
          <label for="accountTypeFilter">Account Type:</label>
          <select id="accountTypeFilter" v-model="filters.accountType" @change="applyFilters">
            <option value="">All Types</option>
            <option value="Asset">Assets</option>
            <option value="Liability">Liabilities</option>
            <option value="Equity">Equity</option>
            <option value="Revenue">Revenue</option>
            <option value="Expense">Expenses</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="searchFilter">Search Account:</label>
          <input 
            id="searchFilter"
            type="text" 
            v-model="filters.search" 
            @input="applyFilters"
            placeholder="Search by account code or name..."
            class="search-input"
          >
        </div>
        <div class="filter-group">
          <label for="balanceFilter">Balance:</label>
          <select id="balanceFilter" v-model="filters.balance" @change="applyFilters">
            <option value="">All Balances</option>
            <option value="positive">Positive Only</option>
            <option value="negative">Negative Only</option>
            <option value="zero">Zero Balance</option>
            <option value="nonzero">Non-Zero Only</option>
          </select>
        </div>
        <button @click="clearFilters" class="clear-filters-btn">
          <i class="fas fa-times"></i>
          Clear Filters
        </button>
      </div>

      <!-- Trial Balance Table -->
      <div class="trial-balance-table">
        <div class="table-header">
          <h3>Account Balances</h3>
          <div class="table-actions">
            <button @click="toggleSort('account_code')" class="sort-btn" :class="{ active: sortField === 'account_code' }">
              Sort by Code
              <i class="fas" :class="sortField === 'account_code' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </button>
            <button @click="toggleSort('balance')" class="sort-btn" :class="{ active: sortField === 'balance' }">
              Sort by Balance
              <i class="fas" :class="sortField === 'balance' ? (sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </button>
          </div>
        </div>

        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th>Account Type</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="account in filteredAccounts" :key="account.account_id" class="account-row">
                <td class="account-code">
                  <span class="code">{{ account.account_code }}</span>
                </td>
                <td class="account-name">
                  <span class="name">{{ account.name }}</span>
                </td>
                <td class="account-type">
                  <span class="type-badge" :class="getTypeClass(account.account_type)">
                    {{ account.account_type }}
                  </span>
                </td>
                <td class="text-right amount-cell">
                  <span class="amount debit">{{ formatCurrency(account.total_debit) }}</span>
                </td>
                <td class="text-right amount-cell">
                  <span class="amount credit">{{ formatCurrency(account.total_credit) }}</span>
                </td>
                <td class="text-right amount-cell">
                  <span class="amount balance" :class="getBalanceClass(account.balance)">
                    {{ formatCurrency(account.balance) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Table Footer with Totals -->
        <div class="table-footer">
          <div class="totals-section">
            <div class="total-row">
              <span class="total-label">Total Debits:</span>
              <span class="total-amount debit">{{ formatCurrency(reportData.totals.total_debit) }}</span>
            </div>
            <div class="total-row">
              <span class="total-label">Total Credits:</span>
              <span class="total-amount credit">{{ formatCurrency(reportData.totals.total_credit) }}</span>
            </div>
            <div class="total-row difference" :class="getDifferenceClass()">
              <span class="total-label">Difference:</span>
              <span class="total-amount">{{ formatCurrency(reportData.totals.difference) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Balance Analysis -->
      <div class="balance-analysis">
        <div class="analysis-card">
          <h3>Balance Analysis</h3>
          <div class="analysis-grid">
            <div class="analysis-item">
              <div class="analysis-label">Assets</div>
              <div class="analysis-value positive">{{ formatCurrency(getTypeTotal('Asset')) }}</div>
            </div>
            <div class="analysis-item">
              <div class="analysis-label">Liabilities</div>
              <div class="analysis-value negative">{{ formatCurrency(getTypeTotal('Liability')) }}</div>
            </div>
            <div class="analysis-item">
              <div class="analysis-label">Equity</div>
              <div class="analysis-value neutral">{{ formatCurrency(getTypeTotal('Equity')) }}</div>
            </div>
            <div class="analysis-item">
              <div class="analysis-label">Revenue</div>
              <div class="analysis-value positive">{{ formatCurrency(getTypeTotal('Revenue')) }}</div>
            </div>
            <div class="analysis-item">
              <div class="analysis-label">Expenses</div>
              <div class="analysis-value negative">{{ formatCurrency(getTypeTotal('Expense')) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-file-alt"></i>
      </div>
      <h3>No Data Available</h3>
      <p>Select an accounting period to view the trial balance report.</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'TrialBalanceReport',
  data() {
    return {
      isLoading: false,
      error: null,
      selectedPeriod: '',
      accountingPeriods: [],
      reportData: {
        period: null,
        accounts: [],
        totals: {
          total_debit: 0,
          total_credit: 0,
          difference: 0
        }
      },
      filters: {
        accountType: '',
        search: '',
        balance: ''
      },
      sortField: 'account_code',
      sortDirection: 'asc'
    }
  },
  computed: {
    filteredAccounts() {
      let accounts = [...this.reportData.accounts]

      // Apply filters
      if (this.filters.accountType) {
        accounts = accounts.filter(account => account.account_type === this.filters.accountType)
      }

      if (this.filters.search) {
        const search = this.filters.search.toLowerCase()
        accounts = accounts.filter(account => 
          account.account_code.toLowerCase().includes(search) ||
          account.name.toLowerCase().includes(search)
        )
      }

      if (this.filters.balance) {
        switch (this.filters.balance) {
          case 'positive':
            accounts = accounts.filter(account => account.balance > 0)
            break
          case 'negative':
            accounts = accounts.filter(account => account.balance < 0)
            break
          case 'zero':
            accounts = accounts.filter(account => account.balance === 0)
            break
          case 'nonzero':
            accounts = accounts.filter(account => account.balance !== 0)
            break
        }
      }

      // Apply sorting
      accounts.sort((a, b) => {
        let aValue = a[this.sortField]
        let bValue = b[this.sortField]

        if (this.sortField === 'balance') {
          aValue = parseFloat(aValue) || 0
          bValue = parseFloat(bValue) || 0
        }

        if (this.sortDirection === 'asc') {
          return aValue > bValue ? 1 : -1
        } else {
          return aValue < bValue ? 1 : -1
        }
      })

      return accounts
    }
  },
  async mounted() {
    await this.loadAccountingPeriods()
    if (this.accountingPeriods.length > 0 && this.$route.query.period_id) {
      this.selectedPeriod = this.$route.query.period_id
      await this.loadTrialBalance()
    }
  },
  methods: {
    async loadAccountingPeriods() {
      try {
        const response = await axios.get('/accounting-periods')
        this.accountingPeriods = response.data.data || []
      } catch (error) {
        console.error('Error loading accounting periods:', error)
      }
    },

    async loadTrialBalance() {
      if (!this.selectedPeriod) return

      this.isLoading = true
      this.error = null

      try {
        const response = await axios.get('/reports/trial-balance', {
          params: { period_id: this.selectedPeriod }
        })

        this.reportData = response.data
      } catch (error) {
        console.error('Error loading trial balance:', error)
        this.error = error.response?.data?.message || 'Failed to load trial balance report'
      } finally {
        this.isLoading = false
      }
    },

    applyFilters() {
      // Filters are applied through computed property
    },

    clearFilters() {
      this.filters = {
        accountType: '',
        search: '',
        balance: ''
      }
    },

    toggleSort(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortDirection = 'asc'
      }
    },

    getTypeTotal(accountType) {
      return this.reportData.accounts
        .filter(account => account.account_type === accountType)
        .reduce((total, account) => total + (account.balance || 0), 0)
    },

    exportReport() {
      // Implementation for exporting report
      const csvContent = this.generateCSV()
      const blob = new Blob([csvContent], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `trial-balance-${this.selectedPeriod}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    },

    generateCSV() {
      const headers = ['Account Code', 'Account Name', 'Account Type', 'Debit', 'Credit', 'Balance']
      const rows = this.filteredAccounts.map(account => [
        account.account_code,
        account.name,
        account.account_type,
        account.total_debit,
        account.total_credit,
        account.balance
      ])

      return [headers, ...rows].map(row => row.join(',')).join('\n')
    },

    printReport() {
      window.print()
    },

    formatCurrency(amount) {
      if (!amount) return 'Rp 0'
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount)
    },

    formatPeriodName(period) {
      if (!period) return ''
      return `${period.period_name} (${period.start_date} - ${period.end_date})`
    },

    getBalanceClass(balance) {
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    },

    getTypeClass(type) {
      return type.toLowerCase().replace(' ', '-')
    },

    getBalanceStatus() {
      if (Math.abs(this.reportData.totals.difference) < 0.01) {
        return 'Balanced'
      }
      return 'Out of Balance'
    },

    getBalanceStatusClass() {
      return Math.abs(this.reportData.totals.difference) < 0.01 ? 'balanced' : 'unbalanced'
    },

    getDifferenceClass() {
      if (Math.abs(this.reportData.totals.difference) < 0.01) return 'balanced'
      return 'unbalanced'
    }
  }
}
</script>

<style scoped>
.trial-balance-report {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  padding: 2rem;
}

.report-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: white;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.title-section {
  flex: 1;
}

.report-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.report-title i {
  margin-right: 1rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.report-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.period-selector {
  padding: 0.75rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: white;
  font-size: 0.9rem;
  min-width: 200px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.period-selector:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.export-btn, .print-btn {
  padding: 0.75rem 1.25rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: white;
  color: #64748b;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.export-btn:hover:not(:disabled), .print-btn:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.export-btn:disabled, .print-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  color: #64748b;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-icon, .empty-icon {
  font-size: 3rem;
  color: #ef4444;
  margin-bottom: 1rem;
}

.empty-icon {
  color: #94a3b8;
}

.retry-btn {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 500;
  margin-top: 1rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.report-content {
  max-width: 1400px;
  margin: 0 auto;
}

.report-summary {
  margin-bottom: 2rem;
}

.summary-card {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-info h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.summary-info p {
  color: #64748b;
  margin: 0;
}

.summary-stats {
  display: flex;
  gap: 2rem;
}

.stat-item {
  text-align: center;
}

.stat-label {
  display: block;
  color: #64748b;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
}

.stat-value.balanced {
  color: #059669;
}

.stat-value.unbalanced {
  color: #dc2626;
}

.report-filters {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  display: flex;
  gap: 1.5rem;
  align-items: end;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 200px;
}

.filter-group label {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.9rem;
}

.filter-group select, .search-input {
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

.filter-group select:focus, .search-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.clear-filters-btn {
  padding: 0.75rem 1rem;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  height: fit-content;
}

.clear-filters-btn:hover {
  background: #dc2626;
}

.trial-balance-table {
  background: white;
  border-radius: 16px;
  padding: 0;
  margin-bottom: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.table-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.table-actions {
  display: flex;
  gap: 0.5rem;
}

.sort-btn {
  padding: 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #64748b;
  cursor: pointer;
  font-size: 0.85rem;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.sort-btn:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.sort-btn.active {
  border-color: #6366f1;
  color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #1e293b;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.9rem;
}

.data-table th.text-right {
  text-align: right;
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}

.data-table td.text-right {
  text-align: right;
}

.account-row:hover {
  background: #f8fafc;
}

.account-code .code {
  font-weight: 600;
  color: #1e293b;
  font-family: 'Courier New', monospace;
}

.account-name .name {
  color: #1e293b;
  font-weight: 500;
}

.type-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
}

.type-badge.asset {
  background: rgba(5, 150, 105, 0.1);
  color: #059669;
}

.type-badge.liability {
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
}

.type-badge.equity {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.type-badge.revenue {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.type-badge.expense {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.amount-cell .amount {
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

.amount.debit {
  color: #1e293b;
}

.amount.credit {
  color: #1e293b;
}

.amount.balance.positive {
  color: #059669;
}

.amount.balance.negative {
  color: #dc2626;
}

.amount.balance.neutral {
  color: #64748b;
}

.table-footer {
  padding: 1.5rem;
  border-top: 2px solid #e2e8f0;
  background: #f8fafc;
}

.totals-section {
  display: flex;
  justify-content: flex-end;
  gap: 2rem;
}

.total-row {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.total-label {
  font-weight: 600;
  color: #1e293b;
}

.total-amount {
  font-family: 'Courier New', monospace;
  font-weight: 700;
  font-size: 1.1rem;
  min-width: 120px;
  text-align: right;
}

.total-amount.debit {
  color: #1e293b;
}

.total-amount.credit {
  color: #1e293b;
}

.total-row.difference {
  padding: 0.75rem;
  border-radius: 8px;
  margin-left: 1rem;
}

.total-row.difference.balanced {
  background: rgba(5, 150, 105, 0.1);
  color: #059669;
}

.total-row.difference.unbalanced {
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
}

.balance-analysis {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.balance-analysis h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1.5rem 0;
}

.analysis-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.analysis-item {
  text-align: center;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.analysis-label {
  font-weight: 500;
  color: #64748b;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.analysis-value {
  font-size: 1.5rem;
  font-weight: 700;
  font-family: 'Courier New', monospace;
}

.analysis-value.positive {
  color: #059669;
}

.analysis-value.negative {
  color: #dc2626;
}

.analysis-value.neutral {
  color: #6366f1;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .report-filters {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filter-group {
    min-width: auto;
  }
  
  .totals-section {
    flex-direction: column;
    align-items: flex-end;
    gap: 1rem;
  }
}

@media (max-width: 768px) {
  .trial-balance-report {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1.5rem;
    align-items: stretch;
  }
  
  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }
  
  .period-selector {
    min-width: auto;
  }
  
  .report-title {
    font-size: 2rem;
  }
  
  .summary-card {
    flex-direction: column;
    gap: 1.5rem;
    align-items: stretch;
  }
  
  .summary-stats {
    justify-content: space-around;
  }
  
  .table-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .table-actions {
    justify-content: center;
  }
  
  .analysis-grid {
    grid-template-columns: 1fr;
  }
}

/* Print Styles */
@media print {
  .report-header .header-actions,
  .report-filters,
  .table-actions {
    display: none !important;
  }
  
  .trial-balance-report {
    background: white !important;
    padding: 0 !important;
  }
  
  .report-content > * {
    box-shadow: none !important;
    border: 1px solid #ccc !important;
    page-break-inside: avoid;
  }
}
</style>