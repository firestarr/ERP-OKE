<template>
  <div class="cash-flow-statement">
    <!-- Header Section -->
    <div class="report-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="report-title">
            <i class="fas fa-water"></i>
            Cash Flow Statement
          </h1>
          <p class="report-subtitle">
            Analysis of cash flows from operating, investing, and financing activities
          </p>
        </div>
        <div class="header-actions">
          <select v-model="selectedPeriod" @change="loadCashFlow" class="period-selector">
            <option value="">Select Period</option>
            <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
              {{ formatPeriodName(period) }}
            </option>
          </select>
          <div class="method-toggle">
            <label class="toggle-label">
              <input type="radio" v-model="reportMethod" value="direct" @change="loadCashFlow">
              Direct Method
            </label>
            <label class="toggle-label">
              <input type="radio" v-model="reportMethod" value="indirect" @change="loadCashFlow">
              Indirect Method
            </label>
          </div>
          <div class="comparative-toggle">
            <label class="toggle-label">
              <input type="checkbox" v-model="enableComparative" @change="toggleComparative">
              <span class="toggle-slider"></span>
              Comparative
            </label>
          </div>
          <select v-if="enableComparative" v-model="previousPeriod" @change="loadCashFlow" class="period-selector">
            <option value="">Previous Period</option>
            <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
              {{ formatPeriodName(period) }}
            </option>
          </select>
          <button @click="exportReport" class="export-btn" :disabled="!hasData">
            <i class="fas fa-download"></i>
            Export
          </button>
          <button @click="printReport" class="print-btn" :disabled="!hasData">
            <i class="fas fa-print"></i>
            Print
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading cash flow data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Error Loading Report</h3>
      <p>{{ error }}</p>
      <button @click="loadCashFlow" class="retry-btn">
        <i class="fas fa-redo"></i>
        Retry
      </button>
    </div>

    <!-- Development Notice -->
    <div v-else-if="!reportData.implemented" class="development-notice">
      <div class="notice-icon">
        <i class="fas fa-tools"></i>
      </div>
      <h3>Cash Flow Statement - In Development</h3>
      <p>This feature is currently being implemented. In the meantime, here's a preview of the cash flow analysis structure:</p>
      
      <!-- Mock Cash Flow Structure -->
      <div class="mock-structure">
        <div class="cash-flow-preview">
          <div class="preview-section">
            <h4>Operating Activities</h4>
            <div class="preview-items">
              <div class="preview-item">
                <span>Cash received from customers</span>
                <span class="amount positive">{{ formatCurrency(mockData.operating.receipts) }}</span>
              </div>
              <div class="preview-item">
                <span>Cash paid to suppliers</span>
                <span class="amount negative">{{ formatCurrency(mockData.operating.payments) }}</span>
              </div>
              <div class="preview-item">
                <span>Cash paid for operating expenses</span>
                <span class="amount negative">{{ formatCurrency(mockData.operating.expenses) }}</span>
              </div>
              <div class="preview-total">
                <span>Net Cash from Operating Activities</span>
                <span class="amount" :class="mockData.operating.net >= 0 ? 'positive' : 'negative'">
                  {{ formatCurrency(mockData.operating.net) }}
                </span>
              </div>
            </div>
          </div>

          <div class="preview-section">
            <h4>Investing Activities</h4>
            <div class="preview-items">
              <div class="preview-item">
                <span>Purchase of equipment</span>
                <span class="amount negative">{{ formatCurrency(mockData.investing.purchases) }}</span>
              </div>
              <div class="preview-item">
                <span>Sale of investments</span>
                <span class="amount positive">{{ formatCurrency(mockData.investing.sales) }}</span>
              </div>
              <div class="preview-total">
                <span>Net Cash from Investing Activities</span>
                <span class="amount" :class="mockData.investing.net >= 0 ? 'positive' : 'negative'">
                  {{ formatCurrency(mockData.investing.net) }}
                </span>
              </div>
            </div>
          </div>

          <div class="preview-section">
            <h4>Financing Activities</h4>
            <div class="preview-items">
              <div class="preview-item">
                <span>Proceeds from loans</span>
                <span class="amount positive">{{ formatCurrency(mockData.financing.proceeds) }}</span>
              </div>
              <div class="preview-item">
                <span>Loan repayments</span>
                <span class="amount negative">{{ formatCurrency(mockData.financing.repayments) }}</span>
              </div>
              <div class="preview-item">
                <span>Dividend payments</span>
                <span class="amount negative">{{ formatCurrency(mockData.financing.dividends) }}</span>
              </div>
              <div class="preview-total">
                <span>Net Cash from Financing Activities</span>
                <span class="amount" :class="mockData.financing.net >= 0 ? 'positive' : 'negative'">
                  {{ formatCurrency(mockData.financing.net) }}
                </span>
              </div>
            </div>
          </div>

          <div class="preview-section summary-section">
            <div class="summary-items">
              <div class="summary-item">
                <span>Net increase in cash</span>
                <span class="amount" :class="mockData.summary.netChange >= 0 ? 'positive' : 'negative'">
                  {{ formatCurrency(mockData.summary.netChange) }}
                </span>
              </div>
              <div class="summary-item">
                <span>Cash at beginning of period</span>
                <span class="amount">{{ formatCurrency(mockData.summary.beginningCash) }}</span>
              </div>
              <div class="summary-total">
                <span>Cash at end of period</span>
                <span class="amount positive">{{ formatCurrency(mockData.summary.endingCash) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cash Flow Analysis Tools -->
      <div class="analysis-tools">
        <h4>Cash Flow Analysis Tools</h4>
        <div class="tools-grid">
          <div class="tool-card">
            <div class="tool-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="tool-content">
              <h5>Operating Cash Ratio</h5>
              <p>Measures company's ability to pay current liabilities with cash from operations</p>
              <div class="tool-value">
                {{ calculateOperatingCashRatio() }}
              </div>
            </div>
          </div>

          <div class="tool-card">
            <div class="tool-icon">
              <i class="fas fa-coins"></i>
            </div>
            <div class="tool-content">
              <h5>Cash Conversion Cycle</h5>
              <p>Time taken to convert investments into cash flows from sales</p>
              <div class="tool-value">
                {{ mockData.analysis.conversionCycle }} days
              </div>
            </div>
          </div>

          <div class="tool-card">
            <div class="tool-icon">
              <i class="fas fa-percentage"></i>
            </div>
            <div class="tool-content">
              <h5>Operating Margin</h5>
              <p>Operating cash flow as percentage of revenue</p>
              <div class="tool-value">
                {{ calculateOperatingMargin() }}%
              </div>
            </div>
          </div>

          <div class="tool-card">
            <div class="tool-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <div class="tool-content">
              <h5>Cash Coverage</h5>
              <p>Ability to cover expenses with available cash</p>
              <div class="tool-value" :class="getCoverageClass()">
                {{ getCoverageStatus() }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Implementation Timeline -->
      <div class="implementation-timeline">
        <h4>Implementation Progress</h4>
        <div class="timeline-items">
          <div class="timeline-item completed">
            <div class="timeline-marker"></div>
            <div class="timeline-content">
              <h5>UI Design & Layout</h5>
              <p>User interface and report structure completed</p>
            </div>
          </div>
          <div class="timeline-item in-progress">
            <div class="timeline-marker"></div>
            <div class="timeline-content">
              <h5>Backend API Development</h5>
              <p>Currently implementing cash flow calculation logic</p>
            </div>
          </div>
          <div class="timeline-item pending">
            <div class="timeline-marker"></div>
            <div class="timeline-content">
              <h5>Data Integration</h5>
              <p>Connect with transaction data and bank reconciliations</p>
            </div>
          </div>
          <div class="timeline-item pending">
            <div class="timeline-marker"></div>
            <div class="timeline-content">
              <h5>Testing & Validation</h5>
              <p>Comprehensive testing of calculations and reporting</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actual Report Content (when implemented) -->
    <div v-else-if="hasData" class="report-content">
      <!-- Cash Flow Metrics -->
      <div class="cash-flow-metrics">
        <div class="metrics-grid">
          <div class="metric-card operating">
            <div class="metric-icon">
              <i class="fas fa-cogs"></i>
            </div>
            <div class="metric-content">
              <h3>{{ formatCurrency(reportData.operating.net) }}</h3>
              <p>Operating Cash Flow</p>
              <div v-if="enableComparative && reportData.comparative" class="metric-change">
                <span :class="getChangeClass(calculateChange(reportData.operating.net, reportData.comparative.operating.net))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.operating.net, reportData.comparative.operating.net))"></i>
                  {{ Math.abs(calculateChange(reportData.operating.net, reportData.comparative.operating.net)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="metric-card investing">
            <div class="metric-icon">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="metric-content">
              <h3>{{ formatCurrency(reportData.investing.net) }}</h3>
              <p>Investing Cash Flow</p>
              <div v-if="enableComparative && reportData.comparative" class="metric-change">
                <span :class="getChangeClass(calculateChange(reportData.investing.net, reportData.comparative.investing.net))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.investing.net, reportData.comparative.investing.net))"></i>
                  {{ Math.abs(calculateChange(reportData.investing.net, reportData.comparative.investing.net)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="metric-card financing">
            <div class="metric-icon">
              <i class="fas fa-university"></i>
            </div>
            <div class="metric-content">
              <h3>{{ formatCurrency(reportData.financing.net) }}</h3>
              <p>Financing Cash Flow</p>
              <div v-if="enableComparative && reportData.comparative" class="metric-change">
                <span :class="getChangeClass(calculateChange(reportData.financing.net, reportData.comparative.financing.net))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.financing.net, reportData.comparative.financing.net))"></i>
                  {{ Math.abs(calculateChange(reportData.financing.net, reportData.comparative.financing.net)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="metric-card net-change">
            <div class="metric-icon">
              <i class="fas fa-balance-scale"></i>
            </div>
            <div class="metric-content">
              <h3>{{ formatCurrency(reportData.summary.netChange) }}</h3>
              <p>Net Cash Change</p>
              <div v-if="enableComparative && reportData.comparative" class="metric-change">
                <span :class="getChangeClass(calculateChange(reportData.summary.netChange, reportData.comparative.summary.netChange))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.summary.netChange, reportData.comparative.summary.netChange))"></i>
                  {{ Math.abs(calculateChange(reportData.summary.netChange, reportData.comparative.summary.netChange)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cash Flow Statement Table -->
      <div class="cash-flow-table">
        <!-- Implementation will go here when backend is ready -->
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-water"></i>
      </div>
      <h3>No Cash Flow Data</h3>
      <p>Select an accounting period to view the cash flow statement.</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'CashFlowStatement',
  data() {
    return {
      isLoading: false,
      error: null,
      selectedPeriod: '',
      previousPeriod: '',
      enableComparative: false,
      reportMethod: 'indirect',
      accountingPeriods: [],
      reportData: {
        implemented: false,
        period: null,
        operating: { net: 0 },
        investing: { net: 0 },
        financing: { net: 0 },
        summary: { netChange: 0 },
        comparative: null
      },
      mockData: {
        operating: {
          receipts: 150000000,
          payments: -80000000,
          expenses: -45000000,
          net: 25000000
        },
        investing: {
          purchases: -30000000,
          sales: 5000000,
          net: -25000000
        },
        financing: {
          proceeds: 50000000,
          repayments: -20000000,
          dividends: -10000000,
          net: 20000000
        },
        summary: {
          netChange: 20000000,
          beginningCash: 30000000,
          endingCash: 50000000
        },
        analysis: {
          conversionCycle: 45,
          operatingRatio: 1.25,
          margin: 16.7
        }
      }
    }
  },
  computed: {
    hasData() {
      return this.reportData.implemented && (
        this.reportData.operating.net !== 0 ||
        this.reportData.investing.net !== 0 ||
        this.reportData.financing.net !== 0
      )
    }
  },
  async mounted() {
    await this.loadAccountingPeriods()
    if (this.accountingPeriods.length > 0 && this.$route.query.period_id) {
      this.selectedPeriod = this.$route.query.period_id
      await this.loadCashFlow()
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

    async loadCashFlow() {
      if (!this.selectedPeriod) return

      this.isLoading = true
      this.error = null

      try {
        const params = {
          period_id: this.selectedPeriod,
          method: this.reportMethod,
          comparative: this.enableComparative,
          previous_period_id: this.previousPeriod
        }

        const response = await axios.get('/reports/cash-flow', { params })
        
        // Check if the backend returns implementation status
        if (response.data.message && response.data.message.includes('functionality to be implemented')) {
          this.reportData.implemented = false
        } else {
          this.reportData = response.data
          this.reportData.implemented = true
        }
      } catch (error) {
        console.error('Error loading cash flow statement:', error)
        if (error.response?.status === 501 || 
            error.response?.data?.message?.includes('functionality to be implemented')) {
          // Backend indicates feature not implemented yet
          this.reportData.implemented = false
        } else {
          this.error = error.response?.data?.message || 'Failed to load cash flow statement'
        }
      } finally {
        this.isLoading = false
      }
    },

    toggleComparative() {
      if (!this.enableComparative) {
        this.previousPeriod = ''
        this.reportData.comparative = null
      }
      this.loadCashFlow()
    },

    calculateChange(current, previous) {
      if (!previous || previous === 0) return 0
      return ((current - previous) / Math.abs(previous)) * 100
    },

    calculateOperatingCashRatio() {
      // Mock calculation for preview
      return this.mockData.analysis.operatingRatio.toFixed(2)
    },

    calculateOperatingMargin() {
      return this.mockData.analysis.margin.toFixed(1)
    },

    getCoverageStatus() {
      const ratio = this.mockData.summary.endingCash / Math.abs(this.mockData.operating.expenses)
      if (ratio >= 3) return 'Excellent'
      if (ratio >= 2) return 'Good'
      if (ratio >= 1) return 'Adequate'
      return 'Poor'
    },

    getCoverageClass() {
      const ratio = this.mockData.summary.endingCash / Math.abs(this.mockData.operating.expenses)
      if (ratio >= 3) return 'excellent'
      if (ratio >= 2) return 'good'
      if (ratio >= 1) return 'adequate'
      return 'poor'
    },

    getChangeClass(change) {
      if (Math.abs(change) < 0.1) return 'no-change'
      if (change > 0) return 'positive-change'
      return 'negative-change'
    },

    getChangeIcon(change) {
      if (Math.abs(change) < 0.1) return 'fa-minus'
      if (change > 0) return 'fa-arrow-up'
      return 'fa-arrow-down'
    },

    exportReport() {
      const csvContent = this.generateCSV()
      const blob = new Blob([csvContent], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `cash-flow-statement-${this.selectedPeriod}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    },

    generateCSV() {
      let csv = 'Cash Flow Statement (Preview)\n\n'
      csv += 'OPERATING ACTIVITIES\n'
      csv += 'Cash received from customers,' + this.mockData.operating.receipts + '\n'
      csv += 'Cash paid to suppliers,' + this.mockData.operating.payments + '\n'
      csv += 'Cash paid for operating expenses,' + this.mockData.operating.expenses + '\n'
      csv += 'Net Cash from Operating Activities,' + this.mockData.operating.net + '\n\n'
      
      csv += 'INVESTING ACTIVITIES\n'
      csv += 'Purchase of equipment,' + this.mockData.investing.purchases + '\n'
      csv += 'Sale of investments,' + this.mockData.investing.sales + '\n'
      csv += 'Net Cash from Investing Activities,' + this.mockData.investing.net + '\n\n'
      
      csv += 'FINANCING ACTIVITIES\n'
      csv += 'Proceeds from loans,' + this.mockData.financing.proceeds + '\n'
      csv += 'Loan repayments,' + this.mockData.financing.repayments + '\n'
      csv += 'Dividend payments,' + this.mockData.financing.dividends + '\n'
      csv += 'Net Cash from Financing Activities,' + this.mockData.financing.net + '\n\n'
      
      csv += 'SUMMARY\n'
      csv += 'Net increase in cash,' + this.mockData.summary.netChange + '\n'
      csv += 'Cash at beginning of period,' + this.mockData.summary.beginningCash + '\n'
      csv += 'Cash at end of period,' + this.mockData.summary.endingCash + '\n'
      
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
      }).format(Math.abs(amount))
    },

    formatPeriodName(period) {
      if (!period) return ''
      return `${period.period_name} (${period.start_date} - ${period.end_date})`
    }
  }
}
</script>

<style scoped>
.cash-flow-statement {
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

.method-toggle {
  display: flex;
  gap: 1rem;
  padding: 0.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.method-toggle .toggle-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  color: #64748b;
  transition: all 0.3s ease;
}

.method-toggle .toggle-label input[type="radio"] {
  margin: 0;
}

.method-toggle .toggle-label input[type="radio"]:checked + span,
.method-toggle .toggle-label:has(input[type="radio"]:checked) {
  background: white;
  color: #6366f1;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

.loading-state, .error-state, .empty-state, .development-notice {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  color: #64748b;
}

.development-notice {
  text-align: left;
  max-width: 1200px;
  margin: 0 auto;
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

.error-icon, .empty-icon, .notice-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.error-icon {
  color: #ef4444;
}

.empty-icon {
  color: #94a3b8;
}

.notice-icon {
  color: #f59e0b;
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

.development-notice h3 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 1rem;
  text-align: center;
}

.development-notice > p {
  font-size: 1.1rem;
  color: #64748b;
  margin-bottom: 2rem;
  text-align: center;
}

.mock-structure {
  margin-bottom: 3rem;
}

.cash-flow-preview {
  background: #f8fafc;
  border-radius: 12px;
  padding: 2rem;
  border: 1px solid #e2e8f0;
}

.preview-section {
  margin-bottom: 2rem;
  padding: 1.5rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.preview-section h4 {
  font-size: 1.2rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1rem 0;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
}

.preview-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
}

.preview-total, .summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border-radius: 8px;
  font-weight: 600;
  margin-top: 1rem;
}

.summary-section {
  border: 2px solid #6366f1;
}

.summary-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
}

.amount {
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

.amount.positive {
  color: #059669;
}

.amount.negative {
  color: #dc2626;
}

.analysis-tools {
  margin-bottom: 3rem;
}

.analysis-tools h4 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
}

.tools-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.tool-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
  display: flex;
  gap: 1rem;
  transition: all 0.3s ease;
}

.tool-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.tool-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.tool-content h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.tool-content p {
  font-size: 0.85rem;
  color: #64748b;
  margin: 0 0 0.75rem 0;
  line-height: 1.4;
}

.tool-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #6366f1;
}

.tool-value.excellent {
  color: #059669;
}

.tool-value.good {
  color: #10b981;
}

.tool-value.adequate {
  color: #f59e0b;
}

.tool-value.poor {
  color: #dc2626;
}

.implementation-timeline h4 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
}

.timeline-items {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.timeline-item {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.timeline-marker {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 3px solid #e2e8f0;
  background: white;
  flex-shrink: 0;
  margin-top: 0.25rem;
}

.timeline-item.completed .timeline-marker {
  background: #059669;
  border-color: #059669;
}

.timeline-item.in-progress .timeline-marker {
  background: #f59e0b;
  border-color: #f59e0b;
  animation: pulse 2s infinite;
}

.timeline-item.pending .timeline-marker {
  background: #e2e8f0;
  border-color: #94a3b8;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.timeline-content h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.timeline-content p {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0;
  line-height: 1.4;
}

.cash-flow-metrics {
  margin-bottom: 2rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
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

.metric-card.operating::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.metric-card.investing::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.metric-card.financing::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.metric-card.net-change::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

.metric-card.operating .metric-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.metric-card.investing .metric-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.metric-card.financing .metric-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.metric-card.net-change .metric-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

/* Responsive Design */
@media (max-width: 1200px) {
  .metrics-grid, .tools-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  }
}

@media (max-width: 768px) {
  .cash-flow-statement {
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
  
  .metrics-grid, .tools-grid {
    grid-template-columns: 1fr;
  }
  
  .metric-card, .tool-card {
    padding: 1.5rem;
  }
  
  .development-notice {
    padding: 2rem;
  }
  
  .preview-section {
    padding: 1rem;
  }
  
  .timeline-item {
    flex-direction: column;
    gap: 0.5rem;
  }
}

/* Print Styles */
@media print {
  .header-actions {
    display: none !important;
  }
  
  .cash-flow-statement {
    background: white !important;
    padding: 0 !important;
  }
  
  .development-notice,
  .analysis-tools,
  .implementation-timeline {
    page-break-inside: avoid;
  }
}
</style>