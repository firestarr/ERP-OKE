<template>
  <div class="balance-sheet-report">
    <!-- Header Section -->
    <div class="report-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="report-title">
            <i class="fas fa-building"></i>
            Balance Sheet
          </h1>
          <p class="report-subtitle">
            Statement of Financial Position - Assets, Liabilities, and Equity
          </p>
        </div>
        <div class="header-actions">
          <select v-model="selectedPeriod" @change="loadBalanceSheet" class="period-selector">
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
          <select v-if="enableComparative" v-model="previousPeriod" @change="loadBalanceSheet" class="period-selector">
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
      <p>Loading balance sheet data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Error Loading Report</h3>
      <p>{{ error }}</p>
      <button @click="loadBalanceSheet" class="retry-btn">
        <i class="fas fa-redo"></i>
        Retry
      </button>
    </div>

    <!-- Report Content -->
    <div v-else-if="hasData" class="report-content">
      <!-- Financial Health Indicators -->
      <div class="health-indicators">
        <div class="indicator-grid">
          <div class="indicator-card assets">
            <div class="indicator-icon">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="indicator-content">
              <h3>{{ formatCurrency(reportData.total_assets) }}</h3>
              <p>Total Assets</p>
              <div v-if="enableComparative && reportData.comparative" class="indicator-change">
                <span :class="getChangeClass(calculateChange(reportData.total_assets, reportData.comparative.total_assets))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.total_assets, reportData.comparative.total_assets))"></i>
                  {{ Math.abs(calculateChange(reportData.total_assets, reportData.comparative.total_assets)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="indicator-card liabilities">
            <div class="indicator-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="indicator-content">
              <h3>{{ formatCurrency(reportData.total_liabilities) }}</h3>
              <p>Total Liabilities</p>
              <div v-if="enableComparative && reportData.comparative" class="indicator-change">
                <span :class="getChangeClass(calculateChange(reportData.total_liabilities, reportData.comparative.total_liabilities))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.total_liabilities, reportData.comparative.total_liabilities))"></i>
                  {{ Math.abs(calculateChange(reportData.total_liabilities, reportData.comparative.total_liabilities)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="indicator-card equity">
            <div class="indicator-icon">
              <i class="fas fa-balance-scale"></i>
            </div>
            <div class="indicator-content">
              <h3>{{ formatCurrency(reportData.total_equity) }}</h3>
              <p>Total Equity</p>
              <div v-if="enableComparative && reportData.comparative" class="indicator-change">
                <span :class="getChangeClass(calculateChange(reportData.total_equity, reportData.comparative.total_equity))">
                  <i class="fas" :class="getChangeIcon(calculateChange(reportData.total_equity, reportData.comparative.total_equity))"></i>
                  {{ Math.abs(calculateChange(reportData.total_equity, reportData.comparative.total_equity)).toFixed(1) }}%
                </span>
              </div>
            </div>
          </div>

          <div class="indicator-card ratio">
            <div class="indicator-icon">
              <i class="fas fa-percentage"></i>
            </div>
            <div class="indicator-content">
              <h3>{{ calculateDebtRatio() }}%</h3>
              <p>Debt-to-Assets Ratio</p>
              <div class="ratio-status" :class="getDebtRatioClass()">
                {{ getDebtRatioStatus() }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Balance Sheet Statement -->
      <div class="balance-sheet-statement">
        <div class="statement-header">
          <h3>{{ formatPeriodName(reportData.period) }}</h3>
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
                  <span class="column-title">Change</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="statement-body">
          <!-- Assets Section -->
          <div class="statement-section assets-section">
            <div class="section-header">
              <h4>
                <i class="fas fa-chart-bar"></i>
                ASSETS
              </h4>
            </div>
            
            <!-- Current Assets -->
            <div class="subsection">
              <div class="subsection-header">
                <h5>Current Assets</h5>
              </div>
              <div class="account-lines">
                <div v-for="asset in getCurrentAssets()" :key="asset.account_id" class="account-line">
                  <div class="account-info">
                    <span class="account-code">{{ asset.account_code }}</span>
                    <span class="account-name">{{ asset.name }}</span>
                  </div>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="amount positive">{{ formatCurrency(asset.balance) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="amount">{{ formatCurrency(getComparativeAmount(asset, 'assets')) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="amount" :class="getVarianceClass(asset.balance - getComparativeAmount(asset, 'assets'))">
                        {{ formatCurrency(asset.balance - getComparativeAmount(asset, 'assets')) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="subsection-total">
                <div class="total-line">
                  <span class="total-label">Total Current Assets</span>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="total-amount">{{ formatCurrency(getCurrentAssetsTotal()) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="total-amount">{{ formatCurrency(getCurrentAssetsTotal(true)) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="total-amount" :class="getVarianceClass(getCurrentAssetsTotal() - getCurrentAssetsTotal(true))">
                        {{ formatCurrency(getCurrentAssetsTotal() - getCurrentAssetsTotal(true)) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Non-Current Assets -->
            <div class="subsection">
              <div class="subsection-header">
                <h5>Non-Current Assets</h5>
              </div>
              <div class="account-lines">
                <div v-for="asset in getNonCurrentAssets()" :key="asset.account_id" class="account-line">
                  <div class="account-info">
                    <span class="account-code">{{ asset.account_code }}</span>
                    <span class="account-name">{{ asset.name }}</span>
                  </div>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="amount positive">{{ formatCurrency(asset.balance) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="amount">{{ formatCurrency(getComparativeAmount(asset, 'assets')) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="amount" :class="getVarianceClass(asset.balance - getComparativeAmount(asset, 'assets'))">
                        {{ formatCurrency(asset.balance - getComparativeAmount(asset, 'assets')) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="subsection-total">
                <div class="total-line">
                  <span class="total-label">Total Non-Current Assets</span>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="total-amount">{{ formatCurrency(getNonCurrentAssetsTotal()) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="total-amount">{{ formatCurrency(getNonCurrentAssetsTotal(true)) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="total-amount" :class="getVarianceClass(getNonCurrentAssetsTotal() - getNonCurrentAssetsTotal(true))">
                        {{ formatCurrency(getNonCurrentAssetsTotal() - getNonCurrentAssetsTotal(true)) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Total Assets -->
            <div class="section-total">
              <div class="total-line major-total">
                <span class="total-label">TOTAL ASSETS</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount">{{ formatCurrency(reportData.total_assets) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount">{{ formatCurrency(reportData.comparative.total_assets) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass(reportData.total_assets - reportData.comparative.total_assets)">
                      {{ formatCurrency(reportData.total_assets - reportData.comparative.total_assets) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Liabilities Section -->
          <div class="statement-section liabilities-section">
            <div class="section-header">
              <h4>
                <i class="fas fa-exclamation-triangle"></i>
                LIABILITIES
              </h4>
            </div>
            
            <!-- Current Liabilities -->
            <div class="subsection">
              <div class="subsection-header">
                <h5>Current Liabilities</h5>
              </div>
              <div class="account-lines">
                <div v-for="liability in getCurrentLiabilities()" :key="liability.account_id" class="account-line">
                  <div class="account-info">
                    <span class="account-code">{{ liability.account_code }}</span>
                    <span class="account-name">{{ liability.name }}</span>
                  </div>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="amount negative">{{ formatCurrency(liability.balance) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="amount">{{ formatCurrency(getComparativeAmount(liability, 'liabilities')) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="amount" :class="getVarianceClass(liability.balance - getComparativeAmount(liability, 'liabilities'))">
                        {{ formatCurrency(liability.balance - getComparativeAmount(liability, 'liabilities')) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="subsection-total">
                <div class="total-line">
                  <span class="total-label">Total Current Liabilities</span>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="total-amount">{{ formatCurrency(getCurrentLiabilitiesTotal()) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="total-amount">{{ formatCurrency(getCurrentLiabilitiesTotal(true)) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="total-amount" :class="getVarianceClass(getCurrentLiabilitiesTotal() - getCurrentLiabilitiesTotal(true))">
                        {{ formatCurrency(getCurrentLiabilitiesTotal() - getCurrentLiabilitiesTotal(true)) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Non-Current Liabilities -->
            <div class="subsection">
              <div class="subsection-header">
                <h5>Non-Current Liabilities</h5>
              </div>
              <div class="account-lines">
                <div v-for="liability in getNonCurrentLiabilities()" :key="liability.account_id" class="account-line">
                  <div class="account-info">
                    <span class="account-code">{{ liability.account_code }}</span>
                    <span class="account-name">{{ liability.name }}</span>
                  </div>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="amount negative">{{ formatCurrency(liability.balance) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="amount">{{ formatCurrency(getComparativeAmount(liability, 'liabilities')) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="amount" :class="getVarianceClass(liability.balance - getComparativeAmount(liability, 'liabilities'))">
                        {{ formatCurrency(liability.balance - getComparativeAmount(liability, 'liabilities')) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="subsection-total">
                <div class="total-line">
                  <span class="total-label">Total Non-Current Liabilities</span>
                  <div class="amount-columns">
                    <div class="amount-column current">
                      <span class="total-amount">{{ formatCurrency(getNonCurrentLiabilitiesTotal()) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                      <span class="total-amount">{{ formatCurrency(getNonCurrentLiabilitiesTotal(true)) }}</span>
                    </div>
                    <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                      <span class="total-amount" :class="getVarianceClass(getNonCurrentLiabilitiesTotal() - getNonCurrentLiabilitiesTotal(true))">
                        {{ formatCurrency(getNonCurrentLiabilitiesTotal() - getNonCurrentLiabilitiesTotal(true)) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Total Liabilities -->
            <div class="section-total">
              <div class="total-line major-total">
                <span class="total-label">TOTAL LIABILITIES</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount">{{ formatCurrency(reportData.total_liabilities) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount">{{ formatCurrency(reportData.comparative.total_liabilities) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass(reportData.total_liabilities - reportData.comparative.total_liabilities)">
                      {{ formatCurrency(reportData.total_liabilities - reportData.comparative.total_liabilities) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Equity Section -->
          <div class="statement-section equity-section">
            <div class="section-header">
              <h4>
                <i class="fas fa-balance-scale"></i>
                EQUITY
              </h4>
            </div>
            <div class="account-lines">
              <div v-for="equityItem in reportData.equity" :key="equityItem.account_id || 'retained-earnings'" class="account-line">
                <div class="account-info">
                  <span class="account-code">{{ equityItem.account_code || 'RE' }}</span>
                  <span class="account-name">{{ equityItem.name }}</span>
                </div>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="amount positive">{{ formatCurrency(equityItem.balance) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="amount">{{ formatCurrency(getComparativeAmount(equityItem, 'equity')) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="amount" :class="getVarianceClass(equityItem.balance - getComparativeAmount(equityItem, 'equity'))">
                      {{ formatCurrency(equityItem.balance - getComparativeAmount(equityItem, 'equity')) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="section-total">
              <div class="total-line major-total">
                <span class="total-label">TOTAL EQUITY</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount">{{ formatCurrency(reportData.total_equity) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount">{{ formatCurrency(reportData.comparative.total_equity) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass(reportData.total_equity - reportData.comparative.total_equity)">
                      {{ formatCurrency(reportData.total_equity - reportData.comparative.total_equity) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Liabilities and Equity -->
          <div class="statement-section final-total-section">
            <div class="section-total">
              <div class="total-line final-total" :class="getBalanceCheckClass()">
                <span class="total-label">TOTAL LIABILITIES + EQUITY</span>
                <div class="amount-columns">
                  <div class="amount-column current">
                    <span class="total-amount">{{ formatCurrency(reportData.total_liabilities + reportData.total_equity) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column comparative">
                    <span class="total-amount">{{ formatCurrency(reportData.comparative.total_liabilities + reportData.comparative.total_equity) }}</span>
                  </div>
                  <div v-if="enableComparative && reportData.comparative" class="amount-column variance">
                    <span class="total-amount" :class="getVarianceClass((reportData.total_liabilities + reportData.total_equity) - (reportData.comparative.total_liabilities + reportData.comparative.total_equity))">
                      {{ formatCurrency((reportData.total_liabilities + reportData.total_equity) - (reportData.comparative.total_liabilities + reportData.comparative.total_equity)) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Financial Ratios Analysis -->
      <div class="ratios-analysis">
        <div class="analysis-card">
          <h3>Financial Ratios Analysis</h3>
          <div class="ratios-grid">
            <div class="ratio-metric">
              <div class="ratio-label">Current Ratio</div>
              <div class="ratio-value" :class="getCurrentRatioClass()">
                {{ calculateCurrentRatio() }}
              </div>
              <div class="ratio-description">Current Assets ÷ Current Liabilities</div>
              <div class="ratio-interpretation">{{ getCurrentRatioInterpretation() }}</div>
            </div>
            <div class="ratio-metric">
              <div class="ratio-label">Debt-to-Assets Ratio</div>
              <div class="ratio-value" :class="getDebtRatioClass()">
                {{ calculateDebtRatio() }}%
              </div>
              <div class="ratio-description">Total Liabilities ÷ Total Assets</div>
              <div class="ratio-interpretation">{{ getDebtRatioInterpretation() }}</div>
            </div>
            <div class="ratio-metric">
              <div class="ratio-label">Equity Ratio</div>
              <div class="ratio-value" :class="getEquityRatioClass()">
                {{ calculateEquityRatio() }}%
              </div>
              <div class="ratio-description">Total Equity ÷ Total Assets</div>
              <div class="ratio-interpretation">{{ getEquityRatioInterpretation() }}</div>
            </div>
            <div class="ratio-metric">
              <div class="ratio-label">Asset Composition</div>
              <div class="ratio-value">
                {{ calculateAssetComposition() }}%
              </div>
              <div class="ratio-description">Current Assets ÷ Total Assets</div>
              <div class="ratio-interpretation">Liquidity of asset base</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon">
        <i class="fas fa-building"></i>
      </div>
      <h3>No Balance Sheet Data</h3>
      <p>Select an accounting period to view the balance sheet report.</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BalanceSheetReport',
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
        assets: [],
        liabilities: [],
        equity: [],
        total_assets: 0,
        total_liabilities: 0,
        total_equity: 0,
        comparative: null
      }
    }
  },
  computed: {
    hasData() {
      return this.reportData.assets.length > 0 || 
             this.reportData.liabilities.length > 0 || 
             this.reportData.equity.length > 0
    }
  },
  async mounted() {
    await this.loadAccountingPeriods()
    if (this.accountingPeriods.length > 0 && this.$route.query.period_id) {
      this.selectedPeriod = this.$route.query.period_id
      await this.loadBalanceSheet()
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

    async loadBalanceSheet() {
      if (!this.selectedPeriod) return

      this.isLoading = true
      this.error = null

      try {
        const params = {
          period_id: this.selectedPeriod,
          comparative: this.enableComparative,
          previous_period_id: this.previousPeriod
        }

        const response = await axios.get('/reports/balance-sheet', { params })
        this.reportData = response.data
      } catch (error) {
        console.error('Error loading balance sheet:', error)
        this.error = error.response?.data?.message || 'Failed to load balance sheet report'
      } finally {
        this.isLoading = false
      }
    },

    toggleComparative() {
      if (!this.enableComparative) {
        this.previousPeriod = ''
        this.reportData.comparative = null
      }
      this.loadBalanceSheet()
    },

    // Asset categorization methods
    getCurrentAssets() {
      return this.reportData.assets.filter(asset => this.isCurrentAsset(asset))
    },

    getNonCurrentAssets() {
      return this.reportData.assets.filter(asset => !this.isCurrentAsset(asset))
    },

    getCurrentLiabilities() {
      return this.reportData.liabilities.filter(liability => this.isCurrentLiability(liability))
    },

    getNonCurrentLiabilities() {
      return this.reportData.liabilities.filter(liability => !this.isCurrentLiability(liability))
    },

    isCurrentAsset(asset) {
      // Logic to determine if asset is current (typically based on account code or name)
      const currentAssetKeywords = ['cash', 'bank', 'receivable', 'inventory', 'prepaid']
      const name = asset.name.toLowerCase()
      return currentAssetKeywords.some(keyword => name.includes(keyword)) || 
             (asset.account_code && asset.account_code.startsWith('1'))
    },

    isCurrentLiability(liability) {
      // Logic to determine if liability is current
      const currentLiabilityKeywords = ['payable', 'accrued', 'short-term', 'current']
      const name = liability.name.toLowerCase()
      return currentLiabilityKeywords.some(keyword => name.includes(keyword)) ||
             (liability.account_code && liability.account_code.startsWith('21'))
    },

    // Total calculation methods
    getCurrentAssetsTotal(isComparative = false) {
      const assets = isComparative && this.reportData.comparative ? 
        this.reportData.comparative.assets : this.reportData.assets
      return assets
        .filter(asset => this.isCurrentAsset(asset))
        .reduce((total, asset) => total + (asset.balance || 0), 0)
    },

    getNonCurrentAssetsTotal(isComparative = false) {
      const assets = isComparative && this.reportData.comparative ? 
        this.reportData.comparative.assets : this.reportData.assets
      return assets
        .filter(asset => !this.isCurrentAsset(asset))
        .reduce((total, asset) => total + (asset.balance || 0), 0)
    },

    getCurrentLiabilitiesTotal(isComparative = false) {
      const liabilities = isComparative && this.reportData.comparative ? 
        this.reportData.comparative.liabilities : this.reportData.liabilities
      return liabilities
        .filter(liability => this.isCurrentLiability(liability))
        .reduce((total, liability) => total + (liability.balance || 0), 0)
    },

    getNonCurrentLiabilitiesTotal(isComparative = false) {
      const liabilities = isComparative && this.reportData.comparative ? 
        this.reportData.comparative.liabilities : this.reportData.liabilities
      return liabilities
        .filter(liability => !this.isCurrentLiability(liability))
        .reduce((total, liability) => total + (liability.balance || 0), 0)
    },

    // Financial ratio calculations
    calculateCurrentRatio() {
      const currentAssets = this.getCurrentAssetsTotal()
      const currentLiabilities = this.getCurrentLiabilitiesTotal()
      if (currentLiabilities === 0) return 'N/A'
      return (currentAssets / currentLiabilities).toFixed(2)
    },

    calculateDebtRatio() {
      if (this.reportData.total_assets === 0) return 0
      return ((this.reportData.total_liabilities / this.reportData.total_assets) * 100).toFixed(1)
    },

    calculateEquityRatio() {
      if (this.reportData.total_assets === 0) return 0
      return ((this.reportData.total_equity / this.reportData.total_assets) * 100).toFixed(1)
    },

    calculateAssetComposition() {
      if (this.reportData.total_assets === 0) return 0
      const currentAssets = this.getCurrentAssetsTotal()
      return ((currentAssets / this.reportData.total_assets) * 100).toFixed(1)
    },

    calculateChange(current, previous) {
      if (!previous || previous === 0) return 0
      return ((current - previous) / Math.abs(previous)) * 100
    },

    // Ratio interpretation methods
    getCurrentRatioClass() {
      const ratio = parseFloat(this.calculateCurrentRatio())
      if (isNaN(ratio)) return 'neutral'
      if (ratio >= 2) return 'excellent'
      if (ratio >= 1.5) return 'good'
      if (ratio >= 1) return 'average'
      return 'poor'
    },

    getCurrentRatioInterpretation() {
      const ratio = parseFloat(this.calculateCurrentRatio())
      if (isNaN(ratio)) return 'Unable to calculate'
      if (ratio >= 2) return 'Excellent liquidity'
      if (ratio >= 1.5) return 'Good liquidity'
      if (ratio >= 1) return 'Adequate liquidity'
      return 'Poor liquidity - may struggle to meet obligations'
    },

    getDebtRatioClass() {
      const ratio = parseFloat(this.calculateDebtRatio())
      if (ratio <= 30) return 'excellent'
      if (ratio <= 50) return 'good'
      if (ratio <= 70) return 'average'
      return 'poor'
    },

    getDebtRatioStatus() {
      const ratio = parseFloat(this.calculateDebtRatio())
      if (ratio <= 30) return 'Low Risk'
      if (ratio <= 50) return 'Moderate Risk'
      if (ratio <= 70) return 'High Risk'
      return 'Very High Risk'
    },

    getDebtRatioInterpretation() {
      const ratio = parseFloat(this.calculateDebtRatio())
      if (ratio <= 30) return 'Conservative debt level'
      if (ratio <= 50) return 'Moderate debt level'
      if (ratio <= 70) return 'High debt level'
      return 'Very high debt level - potential solvency risk'
    },

    getEquityRatioClass() {
      const ratio = parseFloat(this.calculateEquityRatio())
      if (ratio >= 70) return 'excellent'
      if (ratio >= 50) return 'good'
      if (ratio >= 30) return 'average'
      return 'poor'
    },

    getEquityRatioInterpretation() {
      const ratio = parseFloat(this.calculateEquityRatio())
      if (ratio >= 70) return 'Strong equity position'
      if (ratio >= 50) return 'Good equity position'
      if (ratio >= 30) return 'Moderate equity position'
      return 'Weak equity position'
    },

    // Helper methods
    getComparativeAmount(item, type) {
      if (!this.reportData.comparative) return 0
      const comparativeItems = this.reportData.comparative[type] || []
      const comparativeItem = comparativeItems.find(i => 
        (i.account_id && i.account_id === item.account_id) || 
        (i.name === item.name)
      )
      return comparativeItem ? comparativeItem.balance : 0
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

    getVarianceClass(variance) {
      if (Math.abs(variance) < 0.01) return 'neutral'
      if (variance > 0) return 'positive'
      return 'negative'
    },

    getBalanceCheckClass() {
      const difference = Math.abs(this.reportData.total_assets - (this.reportData.total_liabilities + this.reportData.total_equity))
      return difference < 0.01 ? 'balanced' : 'unbalanced'
    },

    exportReport() {
      const csvContent = this.generateCSV()
      const blob = new Blob([csvContent], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `balance-sheet-${this.selectedPeriod}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    },

    generateCSV() {
      let csv = 'Balance Sheet Report\n\n'
      csv += 'Period,' + this.formatPeriodName(this.reportData.period) + '\n\n'
      
      csv += 'ASSETS\n'
      csv += 'Account Code,Account Name,Amount\n'
      this.reportData.assets.forEach(asset => {
        csv += `${asset.account_code},${asset.name},${asset.balance}\n`
      })
      csv += `,,${this.reportData.total_assets}\n\n`
      
      csv += 'LIABILITIES\n'
      csv += 'Account Code,Account Name,Amount\n'
      this.reportData.liabilities.forEach(liability => {
        csv += `${liability.account_code},${liability.name},${liability.balance}\n`
      })
      csv += `,,${this.reportData.total_liabilities}\n\n`
      
      csv += 'EQUITY\n'
      csv += 'Account Code,Account Name,Amount\n'
      this.reportData.equity.forEach(equity => {
        csv += `${equity.account_code || 'RE'},${equity.name},${equity.balance}\n`
      })
      csv += `,,${this.reportData.total_equity}\n`
      
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
.balance-sheet-report {
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

.health-indicators {
  margin-bottom: 2rem;
}

.indicator-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.indicator-card {
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

.indicator-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.indicator-card.assets::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.indicator-card.liabilities::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.indicator-card.equity::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.indicator-card.ratio::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.indicator-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.indicator-icon {
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

.indicator-card.assets .indicator-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.indicator-card.liabilities .indicator-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.indicator-card.equity .indicator-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.indicator-card.ratio .indicator-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.indicator-content h3 {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.indicator-content p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0 0 0.5rem 0;
}

.indicator-change, .ratio-status {
  font-size: 0.8rem;
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
}

.positive-change {
  color: #059669;
  background: rgba(5, 150, 105, 0.1);
}

.negative-change {
  color: #dc2626;
  background: rgba(220, 38, 38, 0.1);
}

.no-change {
  color: #64748b;
  background: rgba(100, 116, 139, 0.1);
}

.ratio-status.excellent {
  color: #059669;
  background: rgba(5, 150, 105, 0.1);
}

.ratio-status.good {
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
}

.ratio-status.average {
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
}

.ratio-status.poor {
  color: #dc2626;
  background: rgba(220, 38, 38, 0.1);
}

.balance-sheet-statement {
  background: white;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.statement-header {
  padding: 2rem;
  border-bottom: 1px solid #e2e8f0;
}

.statement-header h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 1rem 0;
}

.column-headers {
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
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

.statement-body {
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
  font-size: 1.2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.assets-section .section-header h4 {
  color: #059669;
}

.liabilities-section .section-header h4 {
  color: #dc2626;
}

.equity-section .section-header h4 {
  color: #6366f1;
}

.subsection {
  border-bottom: 1px solid #f1f5f9;
}

.subsection-header {
  padding: 1rem 2rem 0.5rem 2rem;
  background: #fafbfc;
}

.subsection-header h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #475569;
  margin: 0;
}

.account-lines {
  padding: 0;
}

.account-line {
  display: flex;
  padding: 1rem 2rem;
  border-bottom: 1px solid #f8fafc;
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

.subsection-total, .section-total {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.section-total {
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
  color: #1e293b;
}

.major-total {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%) !important;
  border-top: 3px solid #6366f1 !important;
}

.major-total .total-label,
.major-total .total-amount {
  color: #6366f1 !important;
  font-size: 1.2rem;
}

.final-total-section {
  border-bottom: none;
}

.final-total {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
  color: white !important;
}

.final-total .total-label,
.final-total .total-amount {
  color: white !important;
}

.final-total.balanced {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
}

.final-total.unbalanced {
  background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%) !important;
}

.column-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
}

.ratios-analysis {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.ratios-analysis h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1.5rem 0;
}

.ratios-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.ratio-metric {
  text-align: center;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.ratio-label {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.ratio-value {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  color: #1e293b;
}

.ratio-value.excellent {
  color: #059669;
}

.ratio-value.good {
  color: #10b981;
}

.ratio-value.average {
  color: #f59e0b;
}

.ratio-value.poor {
  color: #dc2626;
}

.ratio-description {
  color: #64748b;
  font-size: 0.8rem;
  margin-bottom: 0.5rem;
  font-style: italic;
}

.ratio-interpretation {
  color: #475569;
  font-size: 0.8rem;
  font-weight: 500;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .indicator-grid {
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
  .balance-sheet-report {
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
  
  .indicator-grid {
    grid-template-columns: 1fr;
  }
  
  .indicator-card {
    padding: 1.5rem;
  }
  
  .statement-header {
    padding: 1.5rem;
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
  
  .ratios-grid {
    grid-template-columns: 1fr;
  }
}

/* Print Styles */
@media print {
  .header-actions,
  .health-indicators {
    display: none !important;
  }
  
  .balance-sheet-report {
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