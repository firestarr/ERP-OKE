<template>
  <div class="income-statement-report">
    <!-- Header Section -->
    <div class="report-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="report-title">
            <i class="fas fa-chart-line"></i>
            Income Statement
          </h1>
          <p class="report-subtitle">
            Profit and Loss Statement - Revenue, Expenses, and Net Income Analysis
          </p>
        </div>
        <div class="header-actions">
          <select v-model="selectedPeriod" @change="loadIncomeStatement" class="period-selector">
            <option value="">Select Period</option>
            <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
              {{ formatPeriodName(period) }}
            </option>
          </select>
          <div class="comparative-toggle">
            <label class="toggle-label">
              <input type="checkbox" v-model="enableComparative" @change="toggleComparative">
              <span class="toggle-slider"></span>
              Comparative
            </label>
          </div>
          <select v-if="enableComparative" v-model="previousPeriod" @change="loadIncomeStatement" class="period-selector">
            <option value="">Previous Period</option>
            <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
              {{ formatPeriodName(period) }}
            </option>
          </select>
          <button @click="exportReport" class="export-btn" :disabled="!reportData.revenues.length">
            <i class="fas fa-download"></i>
            Export
          </button>
          <button @click="printReport" class="print-btn" :disabled="!reportData.revenues.length">
            <i class="fas fa-print"></i>
            Print
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading income statement data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Error Loading Report</h3>
      <p>{{ error }}</p>
      <button @click="loadIncomeStatement" class="retry-btn">
        <i class="fas fa-redo"></i>
        Retry
      </button>
    </div>

    <!-- Report Content -->
    <div v-else-if="reportData.revenues.length || reportData.expenses.length" class="report-content">
      <!-- Key Performance Indicators -->
      <div class="kpi-section">
        <div class="kpi-grid">
          <div class="kpi-card revenue">
            <div class="kpi-icon">
              <i class="fas fa-arrow-up"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ formatCurrency(reportData.total_revenue) }}</h3>
              <p>Total Revenue</p>
              <div v-if="enableComparative && reportData.comparative" class="kpi-change">
                <span :class="getChangeClass(calculateChange(reportData.total_revenue, reportData.comparative.total_revenue))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.total_revenue, reportData.comparative.total_revenue))"></i>
                  {{ Math.abs(calculateChange(reportData.total_revenue, reportData.comparative.total_revenue)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="kpi-card expenses">
            <div class="kpi-icon">
              <i class="fas fa-arrow-down"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ formatCurrency(reportData.total_expenses) }}</h3>
              <p>Total Expenses</p>
              <div v-if="enableComparative && reportData.comparative" class="kpi-change">
                <span :class="getChangeClass(calculateChange(reportData.total_expenses, reportData.comparative.total_expenses))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.total_expenses, reportData.comparative.total_expenses))"></i>
                  {{ Math.abs(calculateChange(reportData.total_expenses, reportData.comparative.total_expenses)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="kpi-card profit">
            <div class="kpi-icon">
              <i class="fas fa-chart-pie"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ formatCurrency(reportData.net_income) }}</h3>
              <p>Net Income</p>
              <div v-if="enableComparative && reportData.comparative" class="kpi-change">
                <span :class="getChangeClass(calculateChange(reportData.net_income, reportData.comparative.net_income))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.net_income, reportData.comparative.net_income))"></i>
                  {{ Math.abs(calculateChange(reportData.net_income, reportData.comparative.net_income)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="kpi-card margin">
            <div class="kpi-icon">
              <i class="fas fa-percentage"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ calculateProfitMargin() }}%</h3>
              <p>Profit Margin</p>
              <div v-if="enableComparative && reportData.comparative" class="kpi-change">
                <span :class="getChangeClass(calculateProfitMargin() - calculateProfitMargin(true))">
                  <i class="fas" :class="getChangeIcon(calculateProfitMargin() - calculateProfitMargin(true))"></i>
                  {{ Math.abs(calculateProfitMargin() - calculateProfitMargin(true)).toFixed(1) }}pp
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Income Statement Table -->
      <div class="income-statement-table">
        <div class="table-header">
          <h3>{{ formatPeriodName(reportData.period) }}</h3>
          <div class="report-period">
            <span class="period-label">Reporting Period:</span>
            <span class="period-value">{{ formatPeriodName(reportData.period) }}</span>
          </div>
        </div>

        <div class="financial-statement">
          <!-- Revenue Section -->
          <div class="statement-section revenue-section">
            <div class="section-header">
              <h4>
                <i class="fas fa-plus-circle"></i>
                REVENUE
              </h4>
            </div>
            <div class="account-lines">
              <div v-for="revenue in reportData.revenues" :key="revenue.account_id" class="account-line">
                <div class="account-info">
                  <span class="account-code">{{ revenue.account_code }}</span>
                  <span class="account-name">{{ revenue.name }}</span>
                </div>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="amount positive">{{ formatCurrency(revenue.balance) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="amount">{{ formatCurrency(getComparativeAmount(revenue, 'revenues')) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="amount" :class="getVarianceClass(revenue.balance - getComparativeAmount(revenue, 'revenues'))">
                      {{ formatCurrency(revenue.balance - getComparativeAmount(revenue, 'revenues')) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="section-total">
              <div class="total-line">
                <span class="total-label">Total Revenue</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount positive">{{ formatCurrency(reportData.total_revenue) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount">{{ formatCurrency(reportData.comparative.total_revenue) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass(reportData.total_revenue - reportData.comparative.total_revenue)">
                      {{ formatCurrency(reportData.total_revenue - reportData.comparative.total_revenue) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Expenses Section -->
          <div class="statement-section expenses-section">
            <div class="section-header">
              <h4>
                <i class="fas fa-minus-circle"></i>
                EXPENSES
              </h4>
            </div>
            <div class="account-lines">
              <div v-for="expense in reportData.expenses" :key="expense.account_id" class="account-line">
                <div class="account-info">
                  <span class="account-code">{{ expense.account_code }}</span>
                  <span class="account-name">{{ expense.name }}</span>
                </div>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="amount negative">{{ formatCurrency(expense.balance) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="amount">{{ formatCurrency(getComparativeAmount(expense, 'expenses')) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="amount" :class="getVarianceClass(expense.balance - getComparativeAmount(expense, 'expenses'))">
                      {{ formatCurrency(expense.balance - getComparativeAmount(expense, 'expenses')) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="section-total">
              <div class="total-line">
                <span class="total-label">Total Expenses</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount negative">{{ formatCurrency(reportData.total_expenses) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount">{{ formatCurrency(reportData.comparative.total_expenses) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass(reportData.total_expenses - reportData.comparative.total_expenses)">
                      {{ formatCurrency(reportData.total_expenses - reportData.comparative.total_expenses) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Net Income Section -->
          <div class="statement-section net-income-section">
            <div class="section-total net-income-total">
              <div class="total-line">
                <span class="total-label">NET INCOME</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount" :class="reportData.net_income >= 0 ? 'positive' : 'negative'">
                      {{ formatCurrency(reportData.net_income) }}
                    </span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount" :class="reportData.comparative.net_income >= 0 ? 'positive' : 'negative'">
                      {{ formatCurrency(reportData.comparative.net_income) }}
                    </span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass(reportData.net_income - reportData.comparative.net_income)">
                      {{ formatCurrency(reportData.net_income - reportData.comparative.net_income) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Column Headers for Comparative View -->
        <div v-if="enableComparative && reportData.comparative" class="column-headers">
          <div class="header-row">
            <div class="account-header">Account</div>
            <div class="amount-columns">
              <div class="amount-column current">
                <span class="column-title">{{ formatPeriodName(reportData.period) }}</span>
              </div>
              <div class="amount-column comparative">
                <span class="column-title">{{ formatPeriodName(reportData.comparative.period) }}</span>
              </div>
              <div class="amount-column variance">
                <span class="column-title">Variance</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Financial Analysis -->
      <div class="financial-analysis">
        <div class="analysis-card">
          <h3>Financial Performance Analysis</h3>
          <div class="analysis-grid">
            <div class="analysis-metric">
              <div class="metric-label">Gross Profit Margin</div>
              <div class="metric-value">{{ calculateProfitMargin() }}%</div>
              <div class="metric-description">Percentage of revenue remaining after expenses</div>
            </div>
            <div class="analysis-metric">
              <div class="metric-label">Revenue Growth</div>
              <div class="metric-value" v-if="enableComparative && reportData.comparative">
                {{ calculateChange(reportData.total_revenue, reportData.comparative.total_revenue).toFixed(1) }}%
              </div>
              <div class="metric-value" v-else>N/A</div>
              <div class="metric-description">Period-over-period revenue change</div>
            </div>
            <div class="analysis-metric">
              <div class="metric-label">Expense Ratio</div>
              <div class="metric-value">{{ calculateExpenseRatio() }}%</div>
              <div class="metric-description">Expenses as percentage of revenue</div>
            </div>
            <div class="analysis-metric">
              <div class="metric-label">Operating Efficiency</div>
              <div class="metric-value" :class="getEfficiencyClass()">
                {{ getEfficiencyRating() }}
              </div>
              <div class="metric-description">Overall operational performance rating</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-chart-line"></i>
      </div>
      <h3>No Income Statement Data</h3>
      <p>Select an accounting period to view the income statement report.</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'IncomeStatementReport',
  data() {
    return {
      isLoading: false,
      error: null,
      selectedPeriod: '',
      previousPeriod: '',
      enableComparative: false,
      accountingPeriods: [],
      reportData: {
        period: null,
        revenues: [],
        expenses: [],
        total_revenue: 0,
        total_expenses: 0,
        net_income: 0,
        comparative: null
      }
    }
  },
  async mounted() {
    await this.loadAccountingPeriods()
    if (this.accountingPeriods.length > 0 && this.$route.query.period_id) {
      this.selectedPeriod = this.$route.query.period_id
      await this.loadIncomeStatement()
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

    async loadIncomeStatement() {
      if (!this.selectedPeriod) return

      this.isLoading = true
      this.error = null

      try {
        const params = {
          period_id: this.selectedPeriod,
          comparative: this.enableComparative,
          previous_period_id: this.previousPeriod
        }

        const response = await axios.get('/reports/income-statement', { params })
        this.reportData = response.data
      } catch (error) {
        console.error('Error loading income statement:', error)
        this.error = error.response?.data?.message || 'Failed to load income statement report'
      } finally {
        this.isLoading = false
      }
    },

    toggleComparative() {
      if (!this.enableComparative) {
        this.previousPeriod = ''
        this.reportData.comparative = null
      }
      this.loadIncomeStatement()
    },

    calculateChange(current, previous) {
      if (!previous || previous === 0) return 0
      return ((current - previous) / Math.abs(previous)) * 100
    },

    calculateProfitMargin(isComparative = false) {
      const data = isComparative ? this.reportData.comparative : this.reportData
      if (!data || data.total_revenue === 0) return 0
      return ((data.net_income / data.total_revenue) * 100).toFixed(1)
    },

    calculateExpenseRatio() {
      if (this.reportData.total_revenue === 0) return 0
      return ((this.reportData.total_expenses / this.reportData.total_revenue) * 100).toFixed(1)
    },

    getEfficiencyRating() {
      const margin = parseFloat(this.calculateProfitMargin())
      if (margin >= 20) return 'Excellent'
      if (margin >= 10) return 'Good'
      if (margin >= 5) return 'Average'
      if (margin >= 0) return 'Below Average'
      return 'Poor'
    },

    getEfficiencyClass() {
      const margin = parseFloat(this.calculateProfitMargin())
      if (margin >= 15) return 'excellent'
      if (margin >= 10) return 'good'
      if (margin >= 5) return 'average'
      if (margin >= 0) return 'below-average'
      return 'poor'
    },

    getComparativeAmount(account, type) {
      if (!this.reportData.comparative) return 0
      const comparativeAccounts = this.reportData.comparative[type] || []
      const comparativeAccount = comparativeAccounts.find(a => a.account_id === account.account_id)
      return comparativeAccount ? comparativeAccount.balance : 0
    },

    getChangeClass(change) {
      if (change > 0) return 'positive-change'
      if (change < 0) return 'negative-change'
      return 'no-change'
    },

    getChangeIcon(change) {
      if (change > 0) return 'fa-arrow-up'
      if (change < 0) return 'fa-arrow-down'
      return 'fa-minus'
    },

    getVarianceClass(variance) {
      if (variance > 0) return 'positive'
      if (variance < 0) return 'negative'
      return 'neutral'
    },

    exportReport() {
      const csvContent = this.generateCSV()
      const blob = new Blob([csvContent], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `income-statement-${this.selectedPeriod}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    },

    generateCSV() {
      let csv = 'Income Statement Report\n\n'
      csv += 'Period,' + this.formatPeriodName(this.reportData.period) + '\n\n'
      
      csv += 'REVENUE\n'
      csv += 'Account Code,Account Name,Amount\n'
      this.reportData.revenues.forEach(revenue => {
        csv += `${revenue.account_code},${revenue.name},${revenue.balance}\n`
      })
      csv += `,,${this.reportData.total_revenue}\n\n`
      
      csv += 'EXPENSES\n'
      csv += 'Account Code,Account Name,Amount\n'
      this.reportData.expenses.forEach(expense => {
        csv += `${expense.account_code},${expense.name},${expense.balance}\n`
      })
      csv += `,,${this.reportData.total_expenses}\n\n`
      
      csv += `NET INCOME,${this.reportData.net_income}\n`
      
      return csv
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
    }
  }
}
</script>

<style scoped>
.income-statement-report {
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
  flex-wrap: wrap;
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

.comparative-toggle {
  display: flex;
  align-items: center;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-weight: 500;
  color: #1e293b;
}

.toggle-label input[type="checkbox"] {
  display: none;
}

.toggle-slider {
  position: relative;
  width: 44px;
  height: 24px;
  background: #e2e8f0;
  border-radius: 12px;
  transition: all 0.3s ease;
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
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.toggle-label input:checked + .toggle-slider {
  background: #6366f1;
}

.toggle-label input:checked + .toggle-slider::before {
  transform: translateX(20px);
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

.kpi-section {
  margin-bottom: 2rem;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.kpi-card {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.kpi-card.revenue::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.kpi-card.expenses::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.kpi-card.profit::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.kpi-card.margin::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.kpi-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.kpi-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.kpi-card.revenue .kpi-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.kpi-card.expenses .kpi-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.kpi-card.profit .kpi-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.kpi-card.margin .kpi-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.kpi-content h3 {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.kpi-content p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0 0 0.5rem 0;
}

.kpi-change {
  font-size: 0.8rem;
  font-weight: 500;
}

.positive-change {
  color: #059669;
}

.negative-change {
  color: #dc2626;
}

.no-change {
  color: #64748b;
}

.income-statement-table {
  background: white;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.table-header {
  padding: 2rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.report-period {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.period-label {
  color: #64748b;
  font-weight: 500;
}

.period-value {
  color: #1e293b;
  font-weight: 600;
}

.column-headers {
  position: sticky;
  top: 0;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  z-index: 10;
}

.header-row {
  display: flex;
  padding: 1rem 2rem;
  font-weight: 600;
  color: #1e293b;
}

.account-header {
  flex: 1;
  font-size: 0.9rem;
}

.financial-statement {
  padding: 0;
}

.statement-section {
  border-bottom: 1px solid #e2e8f0;
}

.section-header {
  padding: 1.5rem 2rem 1rem 2rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.section-header h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.revenue-section .section-header h4 {
  color: #059669;
}

.expenses-section .section-header h4 {
  color: #dc2626;
}

.account-lines {
  padding: 0;
}

.account-line {
  display: flex;
  padding: 1rem 2rem;
  border-bottom: 1px solid #f1f5f9;
  transition: all 0.3s ease;
}

.account-line:hover {
  background: #f8fafc;
}

.account-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.account-code {
  font-weight: 600;
  color: #1e293b;
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
}

.account-name {
  color: #64748b;
  font-size: 0.9rem;
}

.amount-columns {
  display: flex;
  gap: 2rem;
  min-width: 400px;
}

.amount-column {
  min-width: 120px;
  text-align: right;
}

.amount {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  font-size: 0.95rem;
}

.amount.positive {
  color: #059669;
}

.amount.negative {
  color: #dc2626;
}

.amount.neutral {
  color: #64748b;
}

.section-total {
  background: #f8fafc;
  border-top: 2px solid #e2e8f0;
}

.total-line {
  display: flex;
  padding: 1.5rem 2rem;
  font-weight: 700;
}

.total-label {
  flex: 1;
  color: #1e293b;
  font-size: 1rem;
}

.total-amount {
  font-family: 'Courier New', monospace;
  font-weight: 700;
  font-size: 1.1rem;
}

.total-amount.positive {
  color: #059669;
}

.total-amount.negative {
  color: #dc2626;
}

.net-income-section {
  border-bottom: none;
}

.net-income-total {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  margin: 0;
}

.net-income-total .total-label,
.net-income-total .total-amount {
  color: white;
}

.column-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
}

.financial-analysis {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.financial-analysis h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1.5rem 0;
}

.analysis-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.analysis-metric {
  text-align: center;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.metric-label {
  font-weight: 500;
  color: #64748b;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.metric-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  color: #1e293b;
}

.metric-value.excellent {
  color: #059669;
}

.metric-value.good {
  color: #10b981;
}

.metric-value.average {
  color: #f59e0b;
}

.metric-value.below-average {
  color: #ef4444;
}

.metric-value.poor {
  color: #dc2626;
}

.metric-description {
  color: #64748b;
  font-size: 0.8rem;
  line-height: 1.4;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .kpi-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  }
  
  .amount-columns {
    gap: 1rem;
    min-width: 300px;
  }
  
  .amount-column {
    min-width: 90px;
  }
}

@media (max-width: 768px) {
  .income-statement-report {
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
  
  .kpi-grid {
    grid-template-columns: 1fr;
  }
  
  .kpi-card {
    padding: 1.5rem;
  }
  
  .table-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .account-line {
    flex-direction: column;
    gap: 1rem;
  }
  
  .amount-columns {
    justify-content: space-between;
    min-width: auto;
  }
  
  .total-line {
    flex-direction: column;
    gap: 1rem;
  }
  
  .analysis-grid {
    grid-template-columns: 1fr;
  }
}

/* Print Styles */
@media print {
  .header-actions,
  .kpi-section {
    display: none !important;
  }
  
  .income-statement-report {
    background: white !important;
    padding: 0 !important;
  }
  
  .report-content > * {
    box-shadow: none !important;
    border: 1px solid #ccc !important;
    page-break-inside: avoid;
  }
  
  .account-line:hover {
    background: transparent !important;
  }
}
</style>