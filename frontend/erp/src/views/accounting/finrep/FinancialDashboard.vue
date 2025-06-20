<template>
  <div class="financial-dashboard">
    <!-- Header Section -->
    <div class="dashboard-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="dashboard-title">
            <i class="fas fa-chart-line"></i>
            Financial Dashboard
          </h1>
          <p class="dashboard-subtitle">
            Real-time financial insights and key performance indicators
          </p>
        </div>
        <div class="header-actions">
          <select v-model="selectedPeriod" @change="loadDashboardData" class="period-selector">
            <option value="">Select Period</option>
            <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
              {{ formatPeriodName(period) }}
            </option>
          </select>
          <button @click="refreshData" class="refresh-btn" :disabled="isLoading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': isLoading }"></i>
            Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading financial data...</p>
    </div>

    <!-- Dashboard Content -->
    <div v-else class="dashboard-content">
      <!-- Key Metrics Cards -->
      <div class="metrics-grid">
        <div class="metric-card revenue">
          <div class="metric-icon">
            <i class="fas fa-arrow-up"></i>
          </div>
          <div class="metric-content">
            <h3>{{ formatCurrency(dashboardData.totalRevenue) }}</h3>
            <p>Total Revenue</p>
            <span class="metric-change positive" v-if="dashboardData.revenueChange > 0">
              <i class="fas fa-arrow-up"></i>
              {{ dashboardData.revenueChange }}%
            </span>
            <span class="metric-change negative" v-else-if="dashboardData.revenueChange < 0">
              <i class="fas fa-arrow-down"></i>
              {{ Math.abs(dashboardData.revenueChange) }}%
            </span>
          </div>
        </div>

        <div class="metric-card expenses">
          <div class="metric-icon">
            <i class="fas fa-arrow-down"></i>
          </div>
          <div class="metric-content">
            <h3>{{ formatCurrency(dashboardData.totalExpenses) }}</h3>
            <p>Total Expenses</p>
            <span class="metric-change positive" v-if="dashboardData.expenseChange < 0">
              <i class="fas fa-arrow-down"></i>
              {{ Math.abs(dashboardData.expenseChange) }}%
            </span>
            <span class="metric-change negative" v-else-if="dashboardData.expenseChange > 0">
              <i class="fas fa-arrow-up"></i>
              {{ dashboardData.expenseChange }}%
            </span>
          </div>
        </div>

        <div class="metric-card profit">
          <div class="metric-icon">
            <i class="fas fa-chart-pie"></i>
          </div>
          <div class="metric-content">
            <h3>{{ formatCurrency(dashboardData.netIncome) }}</h3>
            <p>Net Income</p>
            <span class="metric-change" :class="dashboardData.netIncome >= 0 ? 'positive' : 'negative'">
              <i class="fas" :class="dashboardData.netIncome >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
              Profit {{ dashboardData.netIncome >= 0 ? 'Margin' : 'Loss' }}
            </span>
          </div>
        </div>

        <div class="metric-card assets">
          <div class="metric-icon">
            <i class="fas fa-building"></i>
          </div>
          <div class="metric-content">
            <h3>{{ formatCurrency(dashboardData.totalAssets) }}</h3>
            <p>Total Assets</p>
            <span class="metric-change neutral">
              <i class="fas fa-equals"></i>
              Balance Sheet
            </span>
          </div>
        </div>
      </div>

      <!-- Charts and Reports Section -->
      <div class="reports-grid">
        <!-- Revenue vs Expenses Chart -->
        <div class="report-card chart-card">
          <div class="card-header">
            <h3>Revenue vs Expenses Trend</h3>
            <div class="chart-legend">
              <span class="legend-item revenue"><span class="legend-color"></span>Revenue</span>
              <span class="legend-item expenses"><span class="legend-color"></span>Expenses</span>
            </div>
          </div>
          <div class="chart-container">
            <canvas ref="revenueChart" class="chart-canvas"></canvas>
          </div>
        </div>

        <!-- Account Balances -->
        <div class="report-card">
          <div class="card-header">
            <h3>Top Account Balances</h3>
            <router-link to="/reports/trial-balance" class="view-all-link">
              View All <i class="fas fa-arrow-right"></i>
            </router-link>
          </div>
          <div class="account-list">
            <div v-for="account in dashboardData.topAccounts" :key="account.account_id" class="account-item">
              <div class="account-info">
                <span class="account-code">{{ account.account_code }}</span>
                <span class="account-name">{{ account.name }}</span>
              </div>
              <div class="account-balance" :class="getBalanceClass(account.balance)">
                {{ formatCurrency(account.balance) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="report-card">
          <div class="card-header">
            <h3>Quick Reports</h3>
          </div>
          <div class="quick-actions">
            <router-link to="/reports/trial-balance" class="action-item">
              <div class="action-icon">
                <i class="fas fa-balance-scale"></i>
              </div>
              <div class="action-content">
                <h4>Trial Balance</h4>
                <p>View account balances</p>
              </div>
            </router-link>

            <router-link to="/reports/income-statement" class="action-item">
              <div class="action-icon">
                <i class="fas fa-chart-line"></i>
              </div>
              <div class="action-content">
                <h4>Income Statement</h4>
                <p>Profit & loss report</p>
              </div>
            </router-link>

            <router-link to="/reports/balance-sheet" class="action-item">
              <div class="action-icon">
                <i class="fas fa-building"></i>
              </div>
              <div class="action-content">
                <h4>Balance Sheet</h4>
                <p>Assets, liabilities & equity</p>
              </div>
            </router-link>

            <router-link to="/reports/cash-flow" class="action-item">
              <div class="action-icon">
                <i class="fas fa-water"></i>
              </div>
              <div class="action-content">
                <h4>Cash Flow</h4>
                <p>Cash movement analysis</p>
              </div>
            </router-link>
          </div>
        </div>

        <!-- Financial Health Indicators -->
        <div class="report-card">
          <div class="card-header">
            <h3>Financial Health</h3>
          </div>
          <div class="health-indicators">
            <div class="indicator">
              <div class="indicator-label">Cash Ratio</div>
              <div class="indicator-value">
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: dashboardData.cashRatio + '%' }"></div>
                </div>
                <span>{{ dashboardData.cashRatio }}%</span>
              </div>
            </div>

            <div class="indicator">
              <div class="indicator-label">Profit Margin</div>
              <div class="indicator-value">
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: Math.max(0, dashboardData.profitMargin) + '%' }"></div>
                </div>
                <span>{{ dashboardData.profitMargin }}%</span>
              </div>
            </div>

            <div class="indicator">
              <div class="indicator-label">Asset Turnover</div>
              <div class="indicator-value">
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: dashboardData.assetTurnover + '%' }"></div>
                </div>
                <span>{{ dashboardData.assetTurnover }}</span>
              </div>
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
  name: 'FinancialDashboard',
  data() {
    return {
      isLoading: false,
      selectedPeriod: '',
      accountingPeriods: [],
      dashboardData: {
        totalRevenue: 0,
        totalExpenses: 0,
        netIncome: 0,
        totalAssets: 0,
        revenueChange: 0,
        expenseChange: 0,
        topAccounts: [],
        cashRatio: 0,
        profitMargin: 0,
        assetTurnover: 0
      }
    }
  },
  async mounted() {
    await this.loadAccountingPeriods()
    if (this.accountingPeriods.length > 0) {
      this.selectedPeriod = this.accountingPeriods[0].period_id
      await this.loadDashboardData()
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

    async loadDashboardData() {
      if (!this.selectedPeriod) return

      this.isLoading = true
      try {
        // Load multiple reports in parallel
        const [trialBalance, incomeStatement, balanceSheet] = await Promise.all([
          axios.get('/reports/trial-balance', { params: { period_id: this.selectedPeriod } }),
          axios.get('/reports/income-statement', { params: { period_id: this.selectedPeriod } }),
          axios.get('/reports/balance-sheet', { params: { period_id: this.selectedPeriod } })
        ])

        // Process data from different reports
        this.processDashboardData(trialBalance.data, incomeStatement.data, balanceSheet.data)
        
      } catch (error) {
        console.error('Error loading dashboard data:', error)
      } finally {
        this.isLoading = false
      }
    },

    processDashboardData(trialBalanceData, incomeData, balanceSheetData) {
      // Process income statement data
      this.dashboardData.totalRevenue = incomeData.total_revenue || 0
      this.dashboardData.totalExpenses = incomeData.total_expenses || 0
      this.dashboardData.netIncome = incomeData.net_income || 0

      // Process balance sheet data
      this.dashboardData.totalAssets = balanceSheetData.total_assets || 0

      // Get top accounts from trial balance
      if (trialBalanceData.accounts) {
        this.dashboardData.topAccounts = trialBalanceData.accounts
          .sort((a, b) => Math.abs(b.balance) - Math.abs(a.balance))
          .slice(0, 5)
      }

      // Calculate financial ratios
      this.calculateFinancialRatios(incomeData, balanceSheetData)
    },

    calculateFinancialRatios(incomeData, balanceSheetData) {
      // Calculate profit margin
      if (incomeData.total_revenue > 0) {
        this.dashboardData.profitMargin = ((incomeData.net_income / incomeData.total_revenue) * 100).toFixed(1)
      }

      // Simple calculations for demo (in real app, you'd need more detailed data)
      this.dashboardData.cashRatio = Math.min(100, Math.max(0, (this.dashboardData.totalAssets / (this.dashboardData.totalExpenses + 1)) * 10))
      this.dashboardData.assetTurnover = (incomeData.total_revenue / (balanceSheetData.total_assets + 1)).toFixed(2)
      
      // Mock change percentages (in real app, compare with previous period)
      this.dashboardData.revenueChange = Math.random() * 20 - 10
      this.dashboardData.expenseChange = Math.random() * 15 - 7.5
    },

    async refreshData() {
      await this.loadDashboardData()
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
      return `${period.period_name} (${period.start_date} - ${period.end_date})`
    },

    getBalanceClass(balance) {
      if (balance > 0) return 'positive'
      if (balance < 0) return 'negative'
      return 'neutral'
    }
  }
}
</script>

<style scoped>
.financial-dashboard {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  padding: 2rem;
}

.dashboard-header {
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

.dashboard-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.dashboard-title i {
  margin-right: 1rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.dashboard-subtitle {
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

.refresh-btn {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.refresh-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loading-state {
  text-align: center;
  padding: 4rem;
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

.dashboard-content {
  max-width: 1400px;
  margin: 0 auto;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.metric-card {
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

.metric-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.metric-card.revenue::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.metric-card.expenses::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.metric-card.profit::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.metric-card.assets::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.metric-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.metric-icon {
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

.metric-card.revenue .metric-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.metric-card.expenses .metric-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.metric-card.profit .metric-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.metric-card.assets .metric-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.metric-content h3 {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.metric-content p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0 0 0.5rem 0;
}

.metric-change {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
}

.metric-change.positive {
  color: #059669;
  background: rgba(5, 150, 105, 0.1);
}

.metric-change.negative {
  color: #dc2626;
  background: rgba(220, 38, 38, 0.1);
}

.metric-change.neutral {
  color: #64748b;
  background: rgba(100, 116, 139, 0.1);
}

.reports-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.report-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.card-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.view-all-link {
  color: #6366f1;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
}

.view-all-link:hover {
  color: #4f46e5;
}

.chart-legend {
  display: flex;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: #64748b;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 2px;
}

.legend-item.revenue .legend-color {
  background: #10b981;
}

.legend-item.expenses .legend-color {
  background: #ef4444;
}

.chart-container {
  height: 300px;
  position: relative;
}

.chart-canvas {
  width: 100%;
  height: 100%;
}

.account-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.account-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.account-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.account-code {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.9rem;
}

.account-name {
  color: #64748b;
  font-size: 0.8rem;
}

.account-balance {
  font-weight: 600;
  font-size: 1rem;
}

.account-balance.positive {
  color: #059669;
}

.account-balance.negative {
  color: #dc2626;
}

.account-balance.neutral {
  color: #64748b;
}

.quick-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  text-decoration: none;
  transition: all 0.3s ease;
}

.action-item:hover {
  background: #f1f5f9;
  border-color: #6366f1;
  transform: translateY(-2px);
}

.action-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1rem;
  flex-shrink: 0;
}

.action-content h4 {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.action-content p {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
}

.health-indicators {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.indicator {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.indicator-label {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.9rem;
}

.indicator-value {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
  margin-left: 1rem;
}

.progress-bar {
  flex: 1;
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  transition: width 0.3s ease;
}

.indicator-value span {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.9rem;
  min-width: 60px;
  text-align: right;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .metrics-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  }
  
  .reports-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .financial-dashboard {
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
  
  .metrics-grid {
    grid-template-columns: 1fr;
  }
  
  .metric-card {
    padding: 1.5rem;
  }
  
  .dashboard-title {
    font-size: 2rem;
  }
  
  .quick-actions {
    grid-template-columns: 1fr;
  }
}
</style>