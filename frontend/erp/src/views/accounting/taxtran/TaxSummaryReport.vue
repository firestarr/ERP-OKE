<template>
  <AppLayout>
    <div class="tax-summary-container">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="breadcrumb">
            <router-link to="/tax-transactions" class="breadcrumb-link">
              <i class="fas fa-receipt"></i>
              Tax Transactions
            </router-link>
            <i class="fas fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current">Tax Summary Report</span>
          </div>
          
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-chart-bar"></i>
              Tax Summary Report
            </h1>
            <p class="page-description">Comprehensive overview of tax transactions and analytics</p>
          </div>

          <div class="header-actions">
            <button @click="exportReport" class="btn btn-outline" :disabled="loading">
              <i class="fas fa-download"></i>
              Export Report
            </button>
            <button @click="printReport" class="btn btn-outline">
              <i class="fas fa-print"></i>
              Print Report
            </button>
            <button @click="scheduleReport" class="btn btn-primary">
              <i class="fas fa-clock"></i>
              Schedule Report
            </button>
          </div>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="filter-section">
        <div class="filter-card">
          <div class="filter-header">
            <h3>
              <i class="fas fa-filter"></i>
              Report Filters
            </h3>
            <button @click="resetFilters" class="btn btn-text">
              <i class="fas fa-undo"></i>
              Reset
            </button>
          </div>
          
          <div class="filter-grid">
            <div class="filter-group">
              <label class="filter-label">
                <i class="fas fa-calendar-alt"></i>
                Date Range
              </label>
              <div class="date-range-wrapper">
                <input 
                  v-model="filters.from_date" 
                  type="date" 
                  class="form-input"
                  @change="applyFilters"
                >
                <span class="date-separator">to</span>
                <input 
                  v-model="filters.to_date" 
                  type="date" 
                  class="form-input"
                  @change="applyFilters"
                >
              </div>
            </div>

            <div class="filter-group">
              <label class="filter-label">
                <i class="fas fa-tag"></i>
                Tax Type
              </label>
              <select v-model="filters.tax_type" @change="applyFilters" class="form-select">
                <option value="">All Tax Types</option>
                <option value="VAT">VAT</option>
                <option value="Income Tax">Income Tax</option>
                <option value="Corporate Tax">Corporate Tax</option>
                <option value="Sales Tax">Sales Tax</option>
                <option value="Withholding Tax">Withholding Tax</option>
                <option value="Property Tax">Property Tax</option>
              </select>
            </div>

            <div class="filter-group">
              <label class="filter-label">
                <i class="fas fa-chart-line"></i>
                Report Period
              </label>
              <select v-model="reportPeriod" @change="setReportPeriod" class="form-select">
                <option value="custom">Custom Range</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="this_week">This Week</option>
                <option value="last_week">Last Week</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="this_quarter">This Quarter</option>
                <option value="last_quarter">Last Quarter</option>
                <option value="this_year">This Year</option>
                <option value="last_year">Last Year</option>
              </select>
            </div>

            <div class="filter-group">
              <label class="filter-label">
                <i class="fas fa-eye"></i>
                Report View
              </label>
              <div class="view-toggle">
                <button 
                  @click="currentView = 'summary'"
                  :class="['view-btn', { active: currentView === 'summary' }]"
                >
                  <i class="fas fa-chart-pie"></i>
                  Summary
                </button>
                <button 
                  @click="currentView = 'detailed'"
                  :class="['view-btn', { active: currentView === 'detailed' }]"
                >
                  <i class="fas fa-list-alt"></i>
                  Detailed
                </button>
                <button 
                  @click="currentView = 'trends'"
                  :class="['view-btn', { active: currentView === 'trends' }]"
                >
                  <i class="fas fa-chart-line"></i>
                  Trends
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-section">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Generating tax summary report...</p>
      </div>

      <!-- Report Content -->
      <div v-else class="report-content">
        <!-- Key Metrics -->
        <div class="metrics-section">
          <div class="metrics-grid">
            <div class="metric-card total-amount">
              <div class="metric-icon">
                <i class="fas fa-dollar-sign"></i>
              </div>
              <div class="metric-content">
                <h3>${{ formatCurrency(summary.grand_total || 0) }}</h3>
                <p>Total Tax Amount</p>
                <div class="metric-change" :class="getTrendClass(summary.total_change)">
                  <i :class="getTrendIcon(summary.total_change)"></i>
                  {{ Math.abs(summary.total_change || 0) }}% vs last period
                </div>
              </div>
            </div>

            <div class="metric-card transaction-count">
              <div class="metric-icon">
                <i class="fas fa-file-invoice"></i>
              </div>
              <div class="metric-content">
                <h3>{{ summary.transaction_count || 0 }}</h3>
                <p>Total Transactions</p>
                <div class="metric-change" :class="getTrendClass(summary.count_change)">
                  <i :class="getTrendIcon(summary.count_change)"></i>
                  {{ Math.abs(summary.count_change || 0) }}% vs last period
                </div>
              </div>
            </div>

            <div class="metric-card avg-amount">
              <div class="metric-icon">
                <i class="fas fa-calculator"></i>
              </div>
              <div class="metric-content">
                <h3>${{ formatCurrency(summary.average_amount || 0) }}</h3>
                <p>Average Tax Amount</p>
                <div class="metric-change" :class="getTrendClass(summary.avg_change)">
                  <i :class="getTrendIcon(summary.avg_change)"></i>
                  {{ Math.abs(summary.avg_change || 0) }}% vs last period
                </div>
              </div>
            </div>

            <div class="metric-card compliance-rate">
              <div class="metric-icon">
                <i class="fas fa-shield-alt"></i>
              </div>
              <div class="metric-content">
                <h3>{{ summary.compliance_rate || 0 }}%</h3>
                <p>Compliance Rate</p>
                <div class="metric-change positive">
                  <i class="fas fa-check-circle"></i>
                  On track
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Summary View -->
        <div v-if="currentView === 'summary'" class="summary-view">
          <!-- Tax Type Breakdown -->
          <div class="breakdown-section">
            <div class="breakdown-card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-chart-pie"></i>
                  Tax Type Breakdown
                </h3>
                <div class="card-actions">
                  <button @click="toggleBreakdownView" class="btn-icon">
                    <i :class="breakdownView === 'chart' ? 'fas fa-table' : 'fas fa-chart-pie'"></i>
                  </button>
                </div>
              </div>
              
              <div v-if="breakdownView === 'chart'" class="chart-container">
                <div class="pie-chart">
                  <svg viewBox="0 0 200 200" class="pie-svg">
                    <g v-for="(segment, index) in pieChartData" :key="index" class="pie-segment">
                      <path 
                        :d="segment.path" 
                        :fill="segment.color"
                        :stroke="'var(--card-bg)'"
                        stroke-width="2"
                        class="segment-path"
                      />
                    </g>
                  </svg>
                  <div class="chart-center">
                    <h4>${{ formatCurrency(summary.grand_total || 0) }}</h4>
                    <p>Total</p>
                  </div>
                </div>
                <div class="chart-legend">
                  <div v-for="(item, index) in summary.summary" :key="index" class="legend-item">
                    <div class="legend-color" :style="{ backgroundColor: getChartColor(index) }"></div>
                    <div class="legend-content">
                      <span class="legend-label">{{ item.tax_type }}</span>
                      <span class="legend-value">${{ formatCurrency(item.total_amount) }}</span>
                      <span class="legend-percentage">{{ ((item.total_amount / summary.grand_total) * 100).toFixed(1) }}%</span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="breakdown-table-wrapper">
                <table class="breakdown-table">
                  <thead>
                    <tr>
                      <th>Tax Type</th>
                      <th>Tax Code</th>
                      <th>Transactions</th>
                      <th>Total Amount</th>
                      <th>Percentage</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in summary.summary" :key="`${item.tax_type}-${item.tax_code}`">
                      <td>
                        <div class="tax-type-cell">
                          <div class="type-indicator" :style="{ backgroundColor: getChartColor(summary.summary.indexOf(item)) }"></div>
                          {{ item.tax_type }}
                        </div>
                      </td>
                      <td>
                        <span class="tax-code">{{ item.tax_code }}</span>
                      </td>
                      <td>
                        <span class="transaction-count">{{ item.transaction_count }}</span>
                      </td>
                      <td>
                        <span class="amount">${{ formatCurrency(item.total_amount) }}</span>
                      </td>
                      <td>
                        <div class="percentage-bar">
                          <div 
                            class="percentage-fill" 
                            :style="{ 
                              width: ((item.total_amount / summary.grand_total) * 100) + '%',
                              backgroundColor: getChartColor(summary.summary.indexOf(item))
                            }"
                          ></div>
                          <span class="percentage-text">{{ ((item.total_amount / summary.grand_total) * 100).toFixed(1) }}%</span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Monthly Trends -->
          <div class="trends-section">
            <div class="trends-card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-chart-line"></i>
                  Monthly Trends
                </h3>
                <div class="trend-controls">
                  <select v-model="trendMetric" class="form-select small">
                    <option value="amount">Tax Amount</option>
                    <option value="count">Transaction Count</option>
                    <option value="average">Average Amount</option>
                  </select>
                </div>
              </div>
              
              <div class="trend-chart-container">
                <div class="trend-chart">
                  <div class="chart-y-axis">
                    <div v-for="label in yAxisLabels" :key="label" class="y-axis-label">
                      {{ label }}
                    </div>
                  </div>
                  <div class="chart-area">
                    <svg class="trend-svg" viewBox="0 0 600 300">
                      <!-- Grid lines -->
                      <g class="grid-lines">
                        <line v-for="i in 5" :key="`h-${i}`" 
                              :x1="0" :y1="i * 60" 
                              :x2="600" :y2="i * 60" 
                              stroke="var(--border-color)" 
                              stroke-width="1" 
                              opacity="0.3"/>
                        <line v-for="i in 12" :key="`v-${i}`" 
                              :x1="i * 50" :y1="0" 
                              :x2="i * 50" :y2="300" 
                              stroke="var(--border-color)" 
                              stroke-width="1" 
                              opacity="0.3"/>
                      </g>
                      
                      <!-- Trend line -->
                      <polyline 
                        :points="trendLinePoints"
                        fill="none" 
                        stroke="var(--primary-color)" 
                        stroke-width="3"
                        class="trend-line"
                      />
                      
                      <!-- Data points -->
                      <circle v-for="(point, index) in trendPoints" :key="index"
                              :cx="point.x" :cy="point.y" r="5"
                              fill="var(--primary-color)"
                              class="trend-point"
                              @mouseover="showTrendTooltip(point, $event)"
                              @mouseout="hideTrendTooltip"/>
                    </svg>
                    
                    <!-- Tooltip -->
                    <div v-if="trendTooltip" class="trend-tooltip" :style="trendTooltipStyle">
                      <div class="tooltip-content">
                        <strong>{{ trendTooltip.month }}</strong><br>
                        {{ trendTooltip.label }}: {{ trendTooltip.value }}
                      </div>
                    </div>
                  </div>
                  
                  <div class="chart-x-axis">
                    <div v-for="month in monthLabels" :key="month" class="x-axis-label">
                      {{ month }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Detailed View -->
        <div v-if="currentView === 'detailed'" class="detailed-view">
          <div class="detailed-table-card">
            <div class="card-header">
              <h3>
                <i class="fas fa-list-alt"></i>
                Detailed Tax Summary
              </h3>
              <div class="table-controls">
                <input 
                  v-model="detailedSearch" 
                  type="text" 
                  placeholder="Search..." 
                  class="form-input small"
                >
                <select v-model="detailedSort" class="form-select small">
                  <option value="tax_type">Sort by Tax Type</option>
                  <option value="total_amount">Sort by Amount</option>
                  <option value="transaction_count">Sort by Count</option>
                </select>
              </div>
            </div>
            
            <div class="detailed-table-wrapper">
              <table class="detailed-table">
                <thead>
                  <tr>
                    <th @click="sortBy('tax_type')" class="sortable">
                      Tax Type
                      <i class="fas fa-sort"></i>
                    </th>
                    <th @click="sortBy('tax_code')" class="sortable">
                      Tax Code
                      <i class="fas fa-sort"></i>
                    </th>
                    <th @click="sortBy('transaction_count')" class="sortable">
                      Transactions
                      <i class="fas fa-sort"></i>
                    </th>
                    <th @click="sortBy('total_amount')" class="sortable">
                      Total Amount
                      <i class="fas fa-sort"></i>
                    </th>
                    <th>Average Amount</th>
                    <th>Min Amount</th>
                    <th>Max Amount</th>
                    <th>Percentage</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in filteredDetailedData" :key="`${item.tax_type}-${item.tax_code}`">
                    <td>
                      <div class="tax-type-cell">
                        <i class="fas fa-tag"></i>
                        {{ item.tax_type }}
                      </div>
                    </td>
                    <td>
                      <span class="tax-code">{{ item.tax_code }}</span>
                    </td>
                    <td>
                      <span class="transaction-count">{{ item.transaction_count }}</span>
                    </td>
                    <td>
                      <span class="amount primary">${{ formatCurrency(item.total_amount) }}</span>
                    </td>
                    <td>
                      <span class="amount">${{ formatCurrency(item.total_amount / item.transaction_count) }}</span>
                    </td>
                    <td>
                      <span class="amount">${{ formatCurrency(item.min_amount || 0) }}</span>
                    </td>
                    <td>
                      <span class="amount">${{ formatCurrency(item.max_amount || 0) }}</span>
                    </td>
                    <td>
                      <div class="percentage-cell">
                        <span class="percentage-value">{{ ((item.total_amount / summary.grand_total) * 100).toFixed(2) }}%</span>
                        <div class="mini-bar">
                          <div 
                            class="mini-bar-fill" 
                            :style="{ width: ((item.total_amount / summary.grand_total) * 100) + '%' }"
                          ></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="action-buttons">
                        <button @click="viewTransactions(item)" class="btn-icon" title="View Transactions">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button @click="exportTypeData(item)" class="btn-icon" title="Export Data">
                          <i class="fas fa-download"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Trends View -->
        <div v-if="currentView === 'trends'" class="trends-view">
          <div class="analytics-grid">
            <!-- Seasonal Analysis -->
            <div class="analytics-card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-calendar-alt"></i>
                  Seasonal Analysis
                </h3>
              </div>
              <div class="seasonal-chart">
                <div v-for="quarter in seasonalData" :key="quarter.name" class="quarter-bar">
                  <div class="quarter-name">{{ quarter.name }}</div>
                  <div class="quarter-bar-container">
                    <div 
                      class="quarter-bar-fill" 
                      :style="{ 
                        height: (quarter.percentage) + '%',
                        backgroundColor: quarter.color 
                      }"
                    ></div>
                  </div>
                  <div class="quarter-value">${{ formatCurrency(quarter.amount) }}</div>
                </div>
              </div>
            </div>

            <!-- Growth Analysis -->
            <div class="analytics-card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-chart-area"></i>
                  Growth Analysis
                </h3>
              </div>
              <div class="growth-metrics">
                <div class="growth-item">
                  <span class="growth-label">Month over Month</span>
                  <span class="growth-value" :class="getTrendClass(growthData.mom)">
                    <i :class="getTrendIcon(growthData.mom)"></i>
                    {{ Math.abs(growthData.mom || 0) }}%
                  </span>
                </div>
                <div class="growth-item">
                  <span class="growth-label">Quarter over Quarter</span>
                  <span class="growth-value" :class="getTrendClass(growthData.qoq)">
                    <i :class="getTrendIcon(growthData.qoq)"></i>
                    {{ Math.abs(growthData.qoq || 0) }}%
                  </span>
                </div>
                <div class="growth-item">
                  <span class="growth-label">Year over Year</span>
                  <span class="growth-value" :class="getTrendClass(growthData.yoy)">
                    <i :class="getTrendIcon(growthData.yoy)"></i>
                    {{ Math.abs(growthData.yoy || 0) }}%
                  </span>
                </div>
              </div>
            </div>

            <!-- Compliance Status -->
            <div class="analytics-card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-shield-alt"></i>
                  Compliance Status
                </h3>
              </div>
              <div class="compliance-metrics">
                <div class="compliance-item">
                  <div class="compliance-circle" :class="getComplianceClass(complianceData.filing)">
                    <span>{{ complianceData.filing }}%</span>
                  </div>
                  <span class="compliance-label">Filing Compliance</span>
                </div>
                <div class="compliance-item">
                  <div class="compliance-circle" :class="getComplianceClass(complianceData.payment)">
                    <span>{{ complianceData.payment }}%</span>
                  </div>
                  <span class="compliance-label">Payment Compliance</span>
                </div>
                <div class="compliance-item">
                  <div class="compliance-circle" :class="getComplianceClass(complianceData.documentation)">
                    <span>{{ complianceData.documentation }}%</span>
                  </div>
                  <span class="compliance-label">Documentation</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Report Footer -->
        <div class="report-footer">
          <div class="footer-info">
            <p>Report generated on {{ formatDateTime(new Date()) }}</p>
            <p>Period: {{ formatDate(filters.from_date) }} to {{ formatDate(filters.to_date) }}</p>
            <p v-if="filters.tax_type">Filtered by: {{ filters.tax_type }}</p>
          </div>
          <div class="footer-actions">
            <button @click="saveReport" class="btn btn-outline">
              <i class="fas fa-save"></i>
              Save Report
            </button>
            <button @click="shareReport" class="btn btn-primary">
              <i class="fas fa-share-alt"></i>
              Share Report
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'

export default {
  name: 'TaxSummaryReport',
  components: {
    AppLayout
  },
  setup() {
    const loading = ref(false)
    const currentView = ref('summary')
    const reportPeriod = ref('this_month')
    const breakdownView = ref('chart')
    const trendMetric = ref('amount')
    const detailedSearch = ref('')
    const detailedSort = ref('tax_type')
    const trendTooltip = ref(null)
    const trendTooltipStyle = ref({})

    const filters = reactive({
      from_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
      to_date: new Date().toISOString().split('T')[0],
      tax_type: ''
    })

    const summary = reactive({
      grand_total: 0,
      transaction_count: 0,
      average_amount: 0,
      compliance_rate: 95,
      total_change: 12.5,
      count_change: 8.3,
      avg_change: 4.2,
      summary: []
    })

    const monthlyTrends = ref([])
    const seasonalData = ref([])
    const growthData = reactive({
      mom: 5.2,
      qoq: 12.8,
      yoy: 18.5
    })

    const complianceData = reactive({
      filing: 95,
      payment: 98,
      documentation: 92
    })

    // Computed properties
    const pieChartData = computed(() => {
      if (!summary.summary.length) return []
      
      let currentAngle = 0
      return summary.summary.map((item, index) => {
        const percentage = (item.total_amount / summary.grand_total) * 100
        const angle = (percentage / 100) * 360
        const startAngle = currentAngle
        const endAngle = currentAngle + angle
        
        const startX = 100 + 80 * Math.cos((startAngle - 90) * Math.PI / 180)
        const startY = 100 + 80 * Math.sin((startAngle - 90) * Math.PI / 180)
        const endX = 100 + 80 * Math.cos((endAngle - 90) * Math.PI / 180)
        const endY = 100 + 80 * Math.sin((endAngle - 90) * Math.PI / 180)
        
        const largeArc = angle > 180 ? 1 : 0
        const path = `M 100 100 L ${startX} ${startY} A 80 80 0 ${largeArc} 1 ${endX} ${endY} Z`
        
        currentAngle = endAngle
        
        return {
          path,
          color: getChartColor(index),
          percentage
        }
      })
    })

    const filteredDetailedData = computed(() => {
      let data = [...summary.summary]
      
      if (detailedSearch.value) {
        data = data.filter(item => 
          item.tax_type.toLowerCase().includes(detailedSearch.value.toLowerCase()) ||
          item.tax_code.toLowerCase().includes(detailedSearch.value.toLowerCase())
        )
      }
      
      data.sort((a, b) => {
        switch (detailedSort.value) {
          case 'total_amount':
            return b.total_amount - a.total_amount
          case 'transaction_count':
            return b.transaction_count - a.transaction_count
          default:
            return a.tax_type.localeCompare(b.tax_type)
        }
      })
      
      return data
    })

    const trendPoints = computed(() => {
      return monthlyTrends.value.map((data, index) => ({
        x: (index + 1) * 50,
        y: 300 - (data[trendMetric.value] / Math.max(...monthlyTrends.value.map(d => d[trendMetric.value])) * 250),
        month: data.month,
        value: data[trendMetric.value]
      }))
    })

    const trendLinePoints = computed(() => {
      return trendPoints.value.map(point => `${point.x},${point.y}`).join(' ')
    })

    const yAxisLabels = computed(() => {
      const maxValue = Math.max(...monthlyTrends.value.map(d => d[trendMetric.value]))
      return Array.from({ length: 6 }, (_, i) => formatAxisLabel(maxValue * (5 - i) / 5))
    })

    const monthLabels = computed(() => {
      return monthlyTrends.value.map(data => data.month.substring(0, 3))
    })

    // Methods
    const fetchSummaryData = async () => {
      loading.value = true
      try {
        const response = await axios.get('/accounting/tax-transactions/summary', {
          params: filters
        })
        
        Object.assign(summary, response.data)
        
        // Generate mock monthly trends
        generateMonthlyTrends()
        generateSeasonalData()
      } catch (error) {
        console.error('Error fetching summary data:', error)
        showNotification('Error loading report data', 'error')
      } finally {
        loading.value = false
      }
    }

    const generateMonthlyTrends = () => {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
      monthlyTrends.value = months.map(month => ({
        month,
        amount: Math.random() * 100000 + 50000,
        count: Math.floor(Math.random() * 100) + 20,
        average: Math.random() * 5000 + 1000
      }))
    }

    const generateSeasonalData = () => {
      seasonalData.value = [
        { name: 'Q1', amount: summary.grand_total * 0.22, percentage: 22, color: '#3b82f6' },
        { name: 'Q2', amount: summary.grand_total * 0.28, percentage: 28, color: '#10b981' },
        { name: 'Q3', amount: summary.grand_total * 0.25, percentage: 25, color: '#f59e0b' },
        { name: 'Q4', amount: summary.grand_total * 0.25, percentage: 25, color: '#ef4444' }
      ]
    }

    const applyFilters = () => {
      fetchSummaryData()
    }

    const resetFilters = () => {
      filters.from_date = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]
      filters.to_date = new Date().toISOString().split('T')[0]
      filters.tax_type = ''
      reportPeriod.value = 'this_month'
      fetchSummaryData()
    }

    const setReportPeriod = () => {
      const now = new Date()
      const periods = {
        today: () => {
          filters.from_date = now.toISOString().split('T')[0]
          filters.to_date = now.toISOString().split('T')[0]
        },
        yesterday: () => {
          const yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000)
          filters.from_date = yesterday.toISOString().split('T')[0]
          filters.to_date = yesterday.toISOString().split('T')[0]
        },
        this_week: () => {
          const startOfWeek = new Date(now.setDate(now.getDate() - now.getDay()))
          filters.from_date = startOfWeek.toISOString().split('T')[0]
          filters.to_date = new Date().toISOString().split('T')[0]
        },
        this_month: () => {
          filters.from_date = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
          filters.to_date = new Date().toISOString().split('T')[0]
        },
        this_quarter: () => {
          const quarter = Math.floor(now.getMonth() / 3)
          filters.from_date = new Date(now.getFullYear(), quarter * 3, 1).toISOString().split('T')[0]
          filters.to_date = new Date().toISOString().split('T')[0]
        },
        this_year: () => {
          filters.from_date = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0]
          filters.to_date = new Date().toISOString().split('T')[0]
        }
      }
      
      if (periods[reportPeriod.value]) {
        periods[reportPeriod.value]()
        fetchSummaryData()
      }
    }

    const toggleBreakdownView = () => {
      breakdownView.value = breakdownView.value === 'chart' ? 'table' : 'chart'
    }

    const sortBy = (field) => {
      detailedSort.value = field
    }

    const showTrendTooltip = (point, event) => {
      trendTooltip.value = {
        month: point.month,
        label: getTrendLabel(),
        value: formatTrendValue(point.value)
      }
      
      trendTooltipStyle.value = {
        left: event.offsetX + 'px',
        top: event.offsetY - 50 + 'px'
      }
    }

    const hideTrendTooltip = () => {
      trendTooltip.value = null
    }

    const viewTransactions = (item) => {
      // Navigate to filtered transaction list
      const query = {
        tax_type: item.tax_type,
        tax_code: item.tax_code,
        from_date: filters.from_date,
        to_date: filters.to_date
      }
      
      window.open(`/tax-transactions?${new URLSearchParams(query).toString()}`, '_blank')
    }

    const exportTypeData = async (item) => {
      try {
        const response = await axios.get('/accounting/tax-transactions/export', {
          params: {
            tax_type: item.tax_type,
            tax_code: item.tax_code,
            from_date: filters.from_date,
            to_date: filters.to_date
          },
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `${item.tax_type}-${item.tax_code}-report.xlsx`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        showNotification('Data exported successfully', 'success')
      } catch (error) {
        console.error('Error exporting data:', error)
        showNotification('Error exporting data', 'error')
      }
    }

    const exportReport = async () => {
      try {
        const response = await axios.post('/accounting/tax-transactions/export-report', {
          filters,
          view: currentView.value
        }, {
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `tax-summary-report-${new Date().toISOString().split('T')[0]}.xlsx`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        showNotification('Report exported successfully', 'success')
      } catch (error) {
        console.error('Error exporting report:', error)
        showNotification('Error exporting report', 'error')
      }
    }

    const printReport = () => {
      window.print()
    }

    const scheduleReport = () => {
      showNotification('Report scheduling feature coming soon', 'info')
    }

    const saveReport = () => {
      showNotification('Report saved successfully', 'success')
    }

    const shareReport = () => {
      if (navigator.share) {
        navigator.share({
          title: 'Tax Summary Report',
          text: 'Tax Summary Report',
          url: window.location.href
        })
      } else {
        navigator.clipboard.writeText(window.location.href)
        showNotification('Report link copied to clipboard', 'success')
      }
    }

    // Utility functions
    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US').format(amount || 0)
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }

    const formatDateTime = (date) => {
      return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const formatAxisLabel = (value) => {
      if (trendMetric.value === 'amount') {
        return '$' + (value / 1000).toFixed(0) + 'K'
      } else if (trendMetric.value === 'count') {
        return Math.round(value).toString()
      } else {
        return '$' + (value / 1000).toFixed(1) + 'K'
      }
    }

    const formatTrendValue = (value) => {
      if (trendMetric.value === 'amount') {
        return '$' + formatCurrency(value)
      } else if (trendMetric.value === 'count') {
        return value.toString() + ' transactions'
      } else {
        return '$' + formatCurrency(value) + ' avg'
      }
    }

    const getTrendLabel = () => {
      const labels = {
        amount: 'Tax Amount',
        count: 'Transactions',
        average: 'Average Amount'
      }
      return labels[trendMetric.value]
    }

    const getChartColor = (index) => {
      const colors = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
        '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'
      ]
      return colors[index % colors.length]
    }

    const getTrendClass = (value) => {
      return value >= 0 ? 'positive' : 'negative'
    }

    const getTrendIcon = (value) => {
      return value >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'
    }

    const getComplianceClass = (value) => {
      if (value >= 95) return 'excellent'
      if (value >= 85) return 'good'
      if (value >= 70) return 'warning'
      return 'critical'
    }

    const showNotification = (message, type = 'info') => {
      console.log(`${type}: ${message}`)
    }

    // Lifecycle
    onMounted(() => {
      fetchSummaryData()
    })

    // Watch for filter changes
    watch(trendMetric, () => {
      // Regenerate trend data when metric changes
    })

    return {
      loading,
      currentView,
      reportPeriod,
      breakdownView,
      trendMetric,
      detailedSearch,
      detailedSort,
      trendTooltip,
      trendTooltipStyle,
      filters,
      summary,
      monthlyTrends,
      seasonalData,
      growthData,
      complianceData,
      pieChartData,
      filteredDetailedData,
      trendPoints,
      trendLinePoints,
      yAxisLabels,
      monthLabels,
      applyFilters,
      resetFilters,
      setReportPeriod,
      toggleBreakdownView,
      sortBy,
      showTrendTooltip,
      hideTrendTooltip,
      viewTransactions,
      exportTypeData,
      exportReport,
      printReport,
      scheduleReport,
      saveReport,
      shareReport,
      formatCurrency,
      formatDate,
      formatDateTime,
      getChartColor,
      getTrendClass,
      getTrendIcon,
      getComplianceClass
    }
  }
}
</script>

<style scoped>
.tax-summary-container {
  padding: 2rem;
  background: var(--bg-secondary);
  min-height: 100vh;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.breadcrumb-link {
  color: var(--primary-color);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.breadcrumb-separator {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.breadcrumb-current {
  color: var(--text-secondary);
}

.title-section {
  text-align: center;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-description {
  font-size: 1.1rem;
  color: var(--text-secondary);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Filter Section */
.filter-section {
  margin-bottom: 2rem;
}

.filter-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.filter-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-label {
  font-weight: 500;
  color: var(--text-primary);
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-range-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-separator {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.view-toggle {
  display: flex;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.view-btn {
  flex: 1;
  padding: 0.75rem 1rem;
  background: var(--card-bg);
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.view-btn.active {
  background: var(--primary-color);
  color: white;
}

.view-btn:hover:not(.active) {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

/* Loading */
.loading-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  background: var(--card-bg);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.loading-spinner {
  font-size: 3rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

/* Metrics Section */
.metrics-section {
  margin-bottom: 2rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.metric-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 2rem;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.metric-card.total-amount {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
  border-color: rgba(16, 185, 129, 0.2);
}

.metric-card.transaction-count {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%);
  border-color: rgba(59, 130, 246, 0.2);
}

.metric-card.avg-amount {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(217, 119, 6, 0.05) 100%);
  border-color: rgba(245, 158, 11, 0.2);
}

.metric-card.compliance-rate {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
  border-color: rgba(99, 102, 241, 0.2);
}

.metric-icon {
  width: 70px;
  height: 70px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  flex-shrink: 0;
}

.metric-card.total-amount .metric-icon {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.metric-card.transaction-count .metric-icon {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.metric-card.avg-amount .metric-icon {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.metric-card.compliance-rate .metric-icon {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.metric-content h3 {
  font-size: 2.25rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.metric-content p {
  font-size: 0.95rem;
  color: var(--text-secondary);
  margin: 0 0 0.5rem 0;
}

.metric-change {
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.metric-change.positive {
  color: #10b981;
}

.metric-change.negative {
  color: #ef4444;
}

/* Breakdown Section */
.breakdown-section {
  margin-bottom: 2rem;
}

.breakdown-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.card-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-tertiary);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-actions {
  display: flex;
  gap: 0.5rem;
}

/* Chart Container */
.chart-container {
  padding: 2rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  align-items: center;
}

.pie-chart {
  position: relative;
  width: 250px;
  height: 250px;
  margin: 0 auto;
}

.pie-svg {
  width: 100%;
  height: 100%;
}

.segment-path {
  transition: all 0.3s ease;
  cursor: pointer;
}

.segment-path:hover {
  stroke-width: 4;
  filter: brightness(1.1);
}

.chart-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  background: var(--card-bg);
  border-radius: 50%;
  width: 100px;
  height: 100px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.chart-center h4 {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.chart-center p {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0;
}

.chart-legend {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  border-radius: 8px;
  background: var(--bg-tertiary);
  transition: all 0.2s ease;
}

.legend-item:hover {
  background: var(--border-color);
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
}

.legend-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
}

.legend-label {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-primary);
}

.legend-value {
  font-size: 1rem;
  font-weight: 600;
  color: var(--primary-color);
}

.legend-percentage {
  font-size: 0.8rem;
  color: var(--text-muted);
}

/* Breakdown Table */
.breakdown-table-wrapper {
  overflow-x: auto;
}

.breakdown-table {
  width: 100%;
  border-collapse: collapse;
}

.breakdown-table th {
  background: var(--bg-tertiary);
  padding: 1rem 1.5rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
  font-size: 0.9rem;
}

.breakdown-table td {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.tax-type-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 500;
}

.type-indicator {
  width: 12px;
  height: 12px;
  border-radius: 2px;
  flex-shrink: 0;
}

.tax-code {
  background: var(--bg-tertiary);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.85rem;
}

.percentage-bar {
  position: relative;
  height: 20px;
  background: var(--bg-tertiary);
  border-radius: 10px;
  overflow: hidden;
  min-width: 100px;
}

.percentage-fill {
  height: 100%;
  border-radius: 10px;
  transition: width 0.3s ease;
}

.percentage-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-primary);
}

/* Trends Section */
.trends-section {
  margin-bottom: 2rem;
}

.trends-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.trend-controls {
  display: flex;
  gap: 0.5rem;
}

.trend-chart-container {
  padding: 2rem;
}

.trend-chart {
  display: grid;
  grid-template-rows: auto 1fr auto;
  gap: 1rem;
  height: 400px;
}

.chart-y-axis {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 60px;
  height: 300px;
  padding-right: 1rem;
}

.y-axis-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-align: right;
}

.chart-area {
  position: relative;
  flex: 1;
}

.trend-svg {
  width: 100%;
  height: 100%;
}

.trend-line {
  filter: drop-shadow(0 2px 4px rgba(99, 102, 241, 0.3));
}

.trend-point {
  cursor: pointer;
  transition: all 0.2s ease;
}

.trend-point:hover {
  r: 8;
  filter: drop-shadow(0 2px 8px rgba(99, 102, 241, 0.5));
}

.trend-tooltip {
  position: absolute;
  background: var(--text-primary);
  color: var(--card-bg);
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  pointer-events: none;
  z-index: 10;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.chart-x-axis {
  display: flex;
  justify-content: space-between;
  padding: 0 2rem;
}

.x-axis-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-align: center;
}

/* Detailed View */
.detailed-view {
  margin-bottom: 2rem;
}

.detailed-table-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.table-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.detailed-table-wrapper {
  overflow-x: auto;
}

.detailed-table {
  width: 100%;
  border-collapse: collapse;
}

.detailed-table th {
  background: var(--bg-tertiary);
  padding: 1rem 1.5rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
  font-size: 0.9rem;
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}

.detailed-table th.sortable:hover {
  background: var(--border-color);
}

.detailed-table th i {
  margin-left: 0.5rem;
  color: var(--text-muted);
}

.detailed-table td {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.amount {
  font-weight: 600;
  color: var(--text-primary);
}

.amount.primary {
  color: var(--primary-color);
  font-size: 1.1rem;
}

.percentage-cell {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 120px;
}

.percentage-value {
  font-weight: 500;
  color: var(--text-primary);
}

.mini-bar {
  height: 4px;
  background: var(--bg-tertiary);
  border-radius: 2px;
  overflow: hidden;
}

.mini-bar-fill {
  height: 100%;
  background: var(--primary-color);
  border-radius: 2px;
  transition: width 0.3s ease;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

/* Trends View */
.trends-view {
  margin-bottom: 2rem;
}

.analytics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.analytics-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

/* Seasonal Chart */
.seasonal-chart {
  padding: 2rem;
  display: flex;
  align-items: end;
  gap: 1rem;
  height: 250px;
}

.quarter-bar {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  height: 100%;
}

.quarter-name {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-primary);
}

.quarter-bar-container {
  flex: 1;
  width: 40px;
  background: var(--bg-tertiary);
  border-radius: 20px;
  display: flex;
  align-items: end;
  overflow: hidden;
}

.quarter-bar-fill {
  width: 100%;
  border-radius: 20px;
  transition: height 0.8s ease;
  min-height: 4px;
}

.quarter-value {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-align: center;
}

/* Growth Analysis */
.growth-metrics {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.growth-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-tertiary);
  border-radius: 8px;
}

.growth-label {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.growth-value {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.growth-value.positive {
  color: #10b981;
}

.growth-value.negative {
  color: #ef4444;
}

/* Compliance Metrics */
.compliance-metrics {
  padding: 2rem;
  display: flex;
  justify-content: space-around;
  align-items: center;
  gap: 1rem;
}

.compliance-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  text-align: center;
}

.compliance-circle {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 600;
  color: white;
  position: relative;
}

.compliance-circle.excellent {
  background: conic-gradient(#10b981 0%, #10b981 95%, var(--bg-tertiary) 95%);
}

.compliance-circle.good {
  background: conic-gradient(#3b82f6 0%, #3b82f6 85%, var(--bg-tertiary) 85%);
}

.compliance-circle.warning {
  background: conic-gradient(#f59e0b 0%, #f59e0b 70%, var(--bg-tertiary) 70%);
}

.compliance-circle.critical {
  background: conic-gradient(#ef4444 0%, #ef4444 50%, var(--bg-tertiary) 50%);
}

.compliance-label {
  font-size: 0.8rem;
  color: var(--text-secondary);
}

/* Report Footer */
.report-footer {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  margin-top: 2rem;
}

.footer-info {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.footer-info p {
  margin: 0.25rem 0;
}

.footer-actions {
  display: flex;
  gap: 1rem;
}

/* Form Elements */
.form-input,
.form-select {
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 0.9rem;
  transition: all 0.2s ease;
  width: 100%;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input.small,
.form-select.small {
  padding: 0.5rem 0.75rem;
  font-size: 0.85rem;
  width: auto;
  min-width: 120px;
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-outline {
  background: transparent;
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-tertiary);
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.btn-text {
  background: transparent;
  color: var(--text-muted);
  border: none;
  padding: 0.5rem;
}

.btn-text:hover {
  color: var(--text-primary);
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  transform: scale(1.05);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .filter-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }

  .metrics-grid {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  }

  .chart-container {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .analytics-grid {
    grid-template-columns: 1fr;
  }

  .compliance-metrics {
    flex-direction: column;
    gap: 1.5rem;
  }
}

@media (max-width: 768px) {
  .tax-summary-container {
    padding: 1rem;
  }

  .page-title {
    font-size: 2rem;
  }

  .filter-grid {
    grid-template-columns: 1fr;
  }

  .metrics-grid {
    grid-template-columns: 1fr;
  }

  .chart-container {
    padding: 1.5rem;
  }

  .pie-chart {
    width: 200px;
    height: 200px;
  }

  .chart-center {
    width: 80px;
    height: 80px;
  }

  .date-range-wrapper {
    flex-direction: column;
    gap: 0.75rem;
  }

  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .table-controls {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }

  .report-footer {
    flex-direction: column;
    gap: 1.5rem;
    text-align: center;
  }

  .footer-actions {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .metric-card {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }

  .legend-item {
    flex-direction: column;
    text-align: center;
    gap: 0.5rem;
  }

  .seasonal-chart {
    padding: 1rem;
    gap: 0.5rem;
  }

  .compliance-circle {
    width: 60px;
    height: 60px;
    font-size: 0.9rem;
  }

  .view-toggle {
    flex-direction: column;
  }

  .detailed-table th,
  .detailed-table td {
    padding: 0.75rem 0.5rem;
    font-size: 0.8rem;
  }
}

@media print {
  .header-actions,
  .filter-section,
  .card-actions,
  .action-buttons,
  .footer-actions {
    display: none;
  }

  .tax-summary-container {
    padding: 0;
  }

  .card-header {
    background: white;
  }
}
</style>