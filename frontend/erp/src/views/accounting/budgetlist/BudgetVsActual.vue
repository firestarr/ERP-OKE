<template>
  <AppLayout>
    <template #page-title>Budget vs Actual Analysis</template>
    <template #page-subtitle>Compare budgeted amounts with actual performance across periods and accounts</template>
    
    <template #page-actions>
      <button @click="exportReport" class="action-button secondary" :disabled="loading">
        <i class="fas fa-download"></i>
        Export Report
      </button>
      <button @click="refreshData" class="action-button secondary" :disabled="loading">
        <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
        Refresh
      </button>
      <button @click="toggleChartType" class="action-button primary">
        <i class="fas fa-chart-bar"></i>
        {{ chartType === 'bar' ? 'Line Chart' : 'Bar Chart' }}
      </button>
    </template>

    <div class="budget-vs-actual-container">
      <!-- Filters Section -->
      <div class="filters-section">
        <div class="filters-card">
          <h3><i class="fas fa-filter"></i> Analysis Filters</h3>
          <div class="filters-grid">
            <div class="filter-group">
              <label>Period</label>
              <select v-model="filters.period_id" @change="applyFilters" class="filter-select">
                <option value="">All Periods</option>
                <option v-for="period in periods" :key="period.period_id" :value="period.period_id">
                  {{ period.name }}
                </option>
              </select>
            </div>
            <div class="filter-group">
              <label>Account Type</label>
              <select v-model="filters.account_type" @change="applyFilters" class="filter-select">
                <option value="">All Account Types</option>
                <option value="Assets">Assets</option>
                <option value="Liabilities">Liabilities</option>
                <option value="Equity">Equity</option>
                <option value="Revenue">Revenue</option>
                <option value="Expenses">Expenses</option>
              </select>
            </div>
            <div class="filter-group">
              <label>View Mode</label>
              <select v-model="viewMode" @change="applyFilters" class="filter-select">
                <option value="summary">Summary View</option>
                <option value="detailed">Detailed View</option>
                <option value="trend">Trend Analysis</option>
              </select>
            </div>
            <div class="filter-actions">
              <button @click="clearFilters" class="btn-clear">
                <i class="fas fa-times"></i>
                Clear
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Dashboard -->
      <div class="kpi-section">
        <div class="kpi-grid">
          <div class="kpi-card primary">
            <div class="kpi-icon">
              <i class="fas fa-chart-pie"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ formatCurrency(kpis.totalBudgeted) }}</h3>
              <p>Total Budgeted</p>
              <small>{{ kpis.budgetCount }} accounts</small>
            </div>
            <div class="kpi-trend">
              <i class="fas fa-arrow-up"></i>
              <span>+12.5%</span>
            </div>
          </div>
          
          <div class="kpi-card success">
            <div class="kpi-icon">
              <i class="fas fa-receipt"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ formatCurrency(kpis.totalActual) }}</h3>
              <p>Total Actual</p>
              <small>{{ kpis.actualCount }} recorded</small>
            </div>
            <div class="kpi-trend">
              <i class="fas fa-arrow-up"></i>
              <span>+8.2%</span>
            </div>
          </div>
          
          <div class="kpi-card" :class="kpis.totalVariance >= 0 ? 'warning' : 'info'">
            <div class="kpi-icon">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ formatCurrency(Math.abs(kpis.totalVariance)) }}</h3>
              <p>Total Variance</p>
              <small>{{ kpis.variancePercentage }}% {{ kpis.totalVariance >= 0 ? 'over' : 'under' }}</small>
            </div>
            <div class="kpi-trend" :class="kpis.totalVariance >= 0 ? 'negative' : 'positive'">
              <i :class="kpis.totalVariance >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
              <span>{{ Math.abs(kpis.variancePercentage) }}%</span>
            </div>
          </div>
          
          <div class="kpi-card accent">
            <div class="kpi-icon">
              <i class="fas fa-percentage"></i>
            </div>
            <div class="kpi-content">
              <h3>{{ kpis.achievementRate }}%</h3>
              <p>Achievement Rate</p>
              <small>Budget realization</small>
            </div>
            <div class="kpi-trend positive">
              <i class="fas fa-check-circle"></i>
              <span>Good</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart Section -->
      <div class="chart-section">
        <div class="chart-card">
          <div class="chart-header">
            <h3><i class="fas fa-chart-line"></i> Budget vs Actual Comparison</h3>
            <div class="chart-controls">
              <div class="chart-legend">
                <div class="legend-item">
                  <div class="legend-color budgeted"></div>
                  <span>Budgeted</span>
                </div>
                <div class="legend-item">
                  <div class="legend-color actual"></div>
                  <span>Actual</span>
                </div>
                <div class="legend-item">
                  <div class="legend-color variance"></div>
                  <span>Variance</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="chart-container" v-if="!loading">
            <div class="chart-wrapper" ref="chartContainer">
              <!-- Simple chart representation -->
              <div class="chart-bars" v-if="chartType === 'bar'">
                <div v-for="(item, index) in chartData" :key="index" class="chart-bar-group">
                  <div class="bar-label">{{ item.label }}</div>
                  <div class="bars-container">
                    <div class="bar budgeted" 
                         :style="{ height: getBarHeight(item.budgeted, maxValue) + '%' }"
                         :title="`Budgeted: ${formatCurrency(item.budgeted)}`">
                      <span class="bar-value">{{ formatShortCurrency(item.budgeted) }}</span>
                    </div>
                    <div class="bar actual" 
                         :style="{ height: getBarHeight(item.actual, maxValue) + '%' }"
                         :title="`Actual: ${formatCurrency(item.actual)}`">
                      <span class="bar-value">{{ formatShortCurrency(item.actual) }}</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="chart-lines" v-else>
                <svg class="line-chart" viewBox="0 0 800 400">
                  <!-- Grid lines -->
                  <defs>
                    <pattern id="grid" width="80" height="40" patternUnits="userSpaceOnUse">
                      <path d="M 80 0 L 0 0 0 40" fill="none" stroke="#e2e8f0" stroke-width="1"/>
                    </pattern>
                  </defs>
                  <rect width="800" height="400" fill="url(#grid)" />
                  
                  <!-- Budget line -->
                  <polyline
                    :points="getBudgetLinePoints()"
                    fill="none"
                    stroke="#6366f1"
                    stroke-width="3"
                    stroke-dasharray="5,5"
                  />
                  
                  <!-- Actual line -->
                  <polyline
                    :points="getActualLinePoints()"
                    fill="none"
                    stroke="#10b981"
                    stroke-width="3"
                  />
                  
                  <!-- Data points -->
                  <g v-for="(point, index) in chartData" :key="index">
                    <circle
                      :cx="getPointX(index)"
                      :cy="getPointY(point.budgeted, maxValue)"
                      r="4"
                      fill="#6366f1"
                      class="data-point"
                    />
                    <circle
                      :cx="getPointX(index)"
                      :cy="getPointY(point.actual, maxValue)"
                      r="4"
                      fill="#10b981"
                      class="data-point"
                    />
                  </g>
                </svg>
              </div>
            </div>
          </div>
          
          <div v-else class="chart-loading">
            <div class="loading-spinner"></div>
            <p>Loading chart data...</p>
          </div>
        </div>
      </div>

      <!-- Data Table Section -->
      <div class="table-section" v-if="viewMode !== 'trend'">
        <div class="table-card">
          <div class="table-header">
            <h3><i class="fas fa-table"></i> {{ viewMode === 'summary' ? 'Summary by Account Type' : 'Detailed Analysis' }}</h3>
            <div class="table-actions">
              <button @click="toggleSortDirection" class="btn-sort">
                <i class="fas fa-sort"></i>
                Sort by {{ sortField }}
              </button>
            </div>
          </div>
          
          <div class="table-container">
            <table class="comparison-table">
              <thead>
                <tr>
                  <th @click="sortBy('name')" class="sortable">
                    {{ viewMode === 'summary' ? 'Account Type' : 'Account Name' }}
                    <i class="fas fa-sort" :class="getSortIcon('name')"></i>
                  </th>
                  <th @click="sortBy('budgeted')" class="sortable text-right">
                    Budgeted Amount
                    <i class="fas fa-sort" :class="getSortIcon('budgeted')"></i>
                  </th>
                  <th @click="sortBy('actual')" class="sortable text-right">
                    Actual Amount
                    <i class="fas fa-sort" :class="getSortIcon('actual')"></i>
                  </th>
                  <th @click="sortBy('variance')" class="sortable text-right">
                    Variance
                    <i class="fas fa-sort" :class="getSortIcon('variance')"></i>
                  </th>
                  <th @click="sortBy('percentage')" class="sortable text-right">
                    Variance %
                    <i class="fas fa-sort" :class="getSortIcon('percentage')"></i>
                  </th>
                  <th class="text-center">Performance</th>
                  <th class="text-center" v-if="viewMode === 'detailed'">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in sortedTableData" :key="row.id" class="table-row">
                  <td class="name-cell">
                    <div class="name-content">
                      <strong>{{ row.name }}</strong>
                      <small v-if="viewMode === 'detailed'">{{ row.code }}</small>
                      <small v-else>{{ row.count }} accounts</small>
                    </div>
                  </td>
                  <td class="text-right">
                    <span class="amount-badge budgeted">
                      {{ formatCurrency(row.budgeted) }}
                    </span>
                  </td>
                  <td class="text-right">
                    <span v-if="row.actual !== null" class="amount-badge actual">
                      {{ formatCurrency(row.actual) }}
                    </span>
                    <span v-else class="no-data">Not recorded</span>
                  </td>
                  <td class="text-right">
                    <span v-if="row.variance !== null" 
                          class="variance-badge" 
                          :class="row.variance >= 0 ? 'positive' : 'negative'">
                      {{ formatCurrency(Math.abs(row.variance)) }}
                      <small>{{ row.variance >= 0 ? 'Over' : 'Under' }}</small>
                    </span>
                    <span v-else class="no-data">-</span>
                  </td>
                  <td class="text-right">
                    <span v-if="row.percentage !== null" 
                          class="percentage-badge" 
                          :class="getPerformanceClass(row.percentage)">
                      {{ Math.abs(row.percentage) }}%
                    </span>
                    <span v-else class="no-data">-</span>
                  </td>
                  <td class="text-center">
                    <div class="performance-indicator">
                      <div class="performance-bar">
                        <div class="performance-fill" 
                             :class="getPerformanceClass(row.percentage)"
                             :style="{ width: getPerformanceWidth(row.percentage) + '%' }">
                        </div>
                      </div>
                      <span class="performance-text">{{ getPerformanceText(row.percentage) }}</span>
                    </div>
                  </td>
                  <td class="text-center" v-if="viewMode === 'detailed'">
                    <div class="action-buttons">
                      <button @click="viewBudgetDetail(row.id)" class="btn-action view" title="View Details">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button @click="editBudget(row.id)" class="btn-action edit" title="Edit">
                        <i class="fas fa-edit"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Trend Analysis Section -->
      <div class="trend-section" v-if="viewMode === 'trend'">
        <div class="trend-grid">
          <div class="trend-card">
            <div class="card-header">
              <h3><i class="fas fa-trending-up"></i> Budget Trend</h3>
            </div>
            <div class="trend-content">
              <div class="trend-chart">
                <div v-for="(month, index) in trendData" :key="index" class="trend-bar">
                  <div class="trend-month">{{ month.name }}</div>
                  <div class="trend-bars">
                    <div class="trend-bar-item budgeted" 
                         :style="{ height: getTrendHeight(month.budgeted) + '%' }">
                      <span class="trend-value">{{ formatShortCurrency(month.budgeted) }}</span>
                    </div>
                    <div class="trend-bar-item actual" 
                         :style="{ height: getTrendHeight(month.actual) + '%' }">
                      <span class="trend-value">{{ formatShortCurrency(month.actual) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="trend-card">
            <div class="card-header">
              <h3><i class="fas fa-chart-area"></i> Variance Trend</h3>
            </div>
            <div class="trend-content">
              <div class="variance-trend">
                <div v-for="(month, index) in trendData" :key="index" class="variance-item">
                  <div class="variance-month">{{ month.name }}</div>
                  <div class="variance-bar-container">
                    <div class="variance-bar" 
                         :class="month.variance >= 0 ? 'positive' : 'negative'"
                         :style="{ height: Math.abs(getVarianceTrendHeight(month.variance)) + '%' }">
                      <span class="variance-value">{{ formatShortCurrency(Math.abs(month.variance)) }}</span>
                    </div>
                  </div>
                  <div class="variance-indicator" :class="month.variance >= 0 ? 'over' : 'under'">
                    {{ month.variance >= 0 ? 'Over' : 'Under' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Insights Section -->
      <div class="insights-section">
        <div class="insights-grid">
          <div class="insight-card primary">
            <div class="insight-header">
              <h3><i class="fas fa-lightbulb"></i> Key Insights</h3>
            </div>
            <div class="insight-content">
              <div class="insight-list">
                <div v-for="insight in insights" :key="insight.id" class="insight-item" :class="insight.type">
                  <div class="insight-icon">
                    <i :class="insight.icon"></i>
                  </div>
                  <div class="insight-text">
                    <h4>{{ insight.title }}</h4>
                    <p>{{ insight.description }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="insight-card secondary">
            <div class="insight-header">
              <h3><i class="fas fa-exclamation-triangle"></i> Alerts & Recommendations</h3>
            </div>
            <div class="insight-content">
              <div class="alert-list">
                <div v-for="alert in alerts" :key="alert.id" class="alert-item" :class="alert.severity">
                  <div class="alert-indicator"></div>
                  <div class="alert-content">
                    <h4>{{ alert.title }}</h4>
                    <p>{{ alert.message }}</p>
                    <small>{{ alert.category }}</small>
                  </div>
                  <button @click="dismissAlert(alert.id)" class="alert-dismiss">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BudgetVsActual',
  components: {
  },
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const chartType = ref('bar')
    const viewMode = ref('summary')
    const sortField = ref('variance')
    const sortDirection = ref('desc')
    
    const periods = ref([])
    const budgetData = ref([])
    const chartContainer = ref(null)
    
    const filters = reactive({
      period_id: '',
      account_type: ''
    })
    
    const kpis = computed(() => {
      const totalBudgeted = budgetData.value.reduce((sum, item) => sum + parseFloat(item.budgeted_amount || 0), 0)
      const totalActual = budgetData.value.reduce((sum, item) => sum + parseFloat(item.actual_amount || 0), 0)
      const totalVariance = totalActual - totalBudgeted
      const budgetCount = budgetData.value.length
      const actualCount = budgetData.value.filter(item => item.actual_amount !== null).length
      const variancePercentage = totalBudgeted > 0 ? ((totalVariance / totalBudgeted) * 100).toFixed(1) : '0'
      const achievementRate = totalBudgeted > 0 ? ((totalActual / totalBudgeted) * 100).toFixed(1) : '0'
      
      return {
        totalBudgeted,
        totalActual,
        totalVariance,
        budgetCount,
        actualCount,
        variancePercentage,
        achievementRate
      }
    })
    
    const chartData = computed(() => {
      if (viewMode.value === 'summary') {
        return getSummaryChartData()
      } else {
        return getDetailedChartData()
      }
    })
    
    const maxValue = computed(() => {
      const values = chartData.value.flatMap(item => [item.budgeted, item.actual])
      return Math.max(...values, 0)
    })
    
    const tableData = computed(() => {
      if (viewMode.value === 'summary') {
        return getSummaryTableData()
      } else {
        return getDetailedTableData()
      }
    })
    
    const sortedTableData = computed(() => {
      const data = [...tableData.value]
      data.sort((a, b) => {
        let aVal = a[sortField.value]
        let bVal = b[sortField.value]
        
        if (typeof aVal === 'string') {
          aVal = aVal.toLowerCase()
          bVal = bVal.toLowerCase()
        }
        
        if (sortDirection.value === 'asc') {
          return aVal > bVal ? 1 : -1
        } else {
          return aVal < bVal ? 1 : -1
        }
      })
      return data
    })
    
    const trendData = computed(() => {
      // Mock trend data - in real implementation, this would come from API
      return [
        { name: 'Jan', budgeted: 1000000, actual: 950000, variance: -50000 },
        { name: 'Feb', budgeted: 1200000, actual: 1150000, variance: -50000 },
        { name: 'Mar', budgeted: 1100000, actual: 1250000, variance: 150000 },
        { name: 'Apr', budgeted: 1300000, actual: 1280000, variance: -20000 },
        { name: 'May', budgeted: 1250000, actual: 1320000, variance: 70000 },
        { name: 'Jun', budgeted: 1400000, actual: 1350000, variance: -50000 }
      ]
    })
    
    const insights = computed(() => [
      {
        id: 1,
        type: 'positive',
        icon: 'fas fa-thumbs-up',
        title: 'Strong Performance',
        description: 'Revenue accounts are performing 8.5% above budget, indicating strong business performance.'
      },
      {
        id: 2,
        type: 'warning',
        icon: 'fas fa-exclamation-triangle',
        title: 'Cost Overrun',
        description: 'Operating expenses exceeded budget by 12%, requiring attention to cost control measures.'
      },
      {
        id: 3,
        type: 'info',
        icon: 'fas fa-info-circle',
        title: 'Seasonal Variation',
        description: 'Budget variances show typical seasonal patterns consistent with historical trends.'
      }
    ])
    
    const alerts = ref([
      {
        id: 1,
        severity: 'high',
        title: 'Critical Variance Alert',
        message: 'Marketing expenses are 25% over budget. Immediate review recommended.',
        category: 'Budget Overrun'
      },
      {
        id: 2,
        severity: 'medium',
        title: 'Revenue Target Risk',
        message: 'Q2 revenue tracking 5% below target. Consider sales acceleration strategies.',
        category: 'Performance Risk'
      },
      {
        id: 3,
        severity: 'low',
        title: 'Positive Variance',
        message: 'IT expenses are 15% under budget due to delayed projects.',
        category: 'Cost Savings'
      }
    ])

    const getSummaryChartData = () => {
      const grouped = {}
      budgetData.value.forEach(item => {
        const type = item.chart_of_account?.account_type || 'Other'
        if (!grouped[type]) {
          grouped[type] = { budgeted: 0, actual: 0, count: 0 }
        }
        grouped[type].budgeted += parseFloat(item.budgeted_amount || 0)
        grouped[type].actual += parseFloat(item.actual_amount || 0)
        grouped[type].count++
      })
      
      return Object.entries(grouped).map(([type, data]) => ({
        label: type,
        budgeted: data.budgeted,
        actual: data.actual,
        count: data.count
      }))
    }

    const getDetailedChartData = () => {
      return budgetData.value.map(item => ({
        label: item.chart_of_account?.account_code || '',
        budgeted: parseFloat(item.budgeted_amount || 0),
        actual: parseFloat(item.actual_amount || 0)
      })).slice(0, 10) // Limit to top 10 for readability
    }

    const getSummaryTableData = () => {
      const grouped = {}
      budgetData.value.forEach(item => {
        const type = item.chart_of_account?.account_type || 'Other'
        if (!grouped[type]) {
          grouped[type] = { budgeted: 0, actual: 0, count: 0 }
        }
        grouped[type].budgeted += parseFloat(item.budgeted_amount || 0)
        grouped[type].actual += parseFloat(item.actual_amount || 0)
        grouped[type].count++
      })
      
      return Object.entries(grouped).map(([type, data]) => {
        const variance = data.actual - data.budgeted
        const percentage = data.budgeted > 0 ? ((variance / data.budgeted) * 100) : 0
        
        return {
          id: type,
          name: type,
          code: '',
          count: data.count,
          budgeted: data.budgeted,
          actual: data.actual,
          variance,
          percentage
        }
      })
    }

    const getDetailedTableData = () => {
      return budgetData.value.map(item => {
        const budgeted = parseFloat(item.budgeted_amount || 0)
        const actual = parseFloat(item.actual_amount || 0)
        const variance = actual - budgeted
        const percentage = budgeted > 0 ? ((variance / budgeted) * 100) : 0
        
        return {
          id: item.id,
          name: item.chart_of_account?.name || '',
          code: item.chart_of_account?.account_code || '',
          budgeted,
          actual: item.actual_amount !== null ? actual : null,
          variance: item.actual_amount !== null ? variance : null,
          percentage: item.actual_amount !== null ? percentage : null
        }
      })
    }

    const fetchData = async () => {
      try {
        loading.value = true
        
        const params = new URLSearchParams()
        if (filters.period_id) params.append('period_id', filters.period_id)
        if (filters.account_type) params.append('account_type', filters.account_type)
        
        const [budgetResponse, periodResponse] = await Promise.all([
          axios.get(`/accounting/budgets?${params}&per_page=1000`),
          axios.get('/accounting/accounting-periods')
        ])
        
        budgetData.value = budgetResponse.data.data || budgetResponse.data
        periods.value = periodResponse.data.data || periodResponse.data
        
      } catch (error) {
        console.error('Error fetching data:', error)
      } finally {
        loading.value = false
      }
    }

    const applyFilters = () => {
      fetchData()
    }

    const clearFilters = () => {
      Object.assign(filters, {
        period_id: '',
        account_type: ''
      })
      fetchData()
    }

    const toggleChartType = () => {
      chartType.value = chartType.value === 'bar' ? 'line' : 'bar'
    }

    const sortBy = (field) => {
      if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
      } else {
        sortField.value = field
        sortDirection.value = 'desc'
      }
    }

    const toggleSortDirection = () => {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    }

    const getSortIcon = (field) => {
      if (sortField.value !== field) return ''
      return sortDirection.value === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    }

    const getBarHeight = (value, max) => {
      return max > 0 ? (value / max) * 80 : 0
    }

    const getTrendHeight = (value) => {
      const maxTrend = Math.max(...trendData.value.flatMap(item => [item.budgeted, item.actual]))
      return maxTrend > 0 ? (value / maxTrend) * 100 : 0
    }

    const getVarianceTrendHeight = (variance) => {
      const maxVariance = Math.max(...trendData.value.map(item => Math.abs(item.variance)))
      return maxVariance > 0 ? (Math.abs(variance) / maxVariance) * 100 : 0
    }

    const getPerformanceClass = (percentage) => {
      if (percentage === null) return 'neutral'
      const abs = Math.abs(percentage)
      if (abs <= 5) return 'excellent'
      if (abs <= 10) return 'good'
      if (abs <= 20) return 'fair'
      return 'poor'
    }

    const getPerformanceText = (percentage) => {
      if (percentage === null) return 'N/A'
      const abs = Math.abs(percentage)
      if (abs <= 5) return 'Excellent'
      if (abs <= 10) return 'Good'
      if (abs <= 20) return 'Fair'
      return 'Poor'
    }

    const getPerformanceWidth = (percentage) => {
      if (percentage === null) return 0
      const abs = Math.abs(percentage)
      return Math.min(abs * 2, 100)
    }

    const getBudgetLinePoints = () => {
      return chartData.value.map((item, index) => {
        const x = getPointX(index)
        const y = getPointY(item.budgeted, maxValue.value)
        return `${x},${y}`
      }).join(' ')
    }

    const getActualLinePoints = () => {
      return chartData.value.map((item, index) => {
        const x = getPointX(index)
        const y = getPointY(item.actual, maxValue.value)
        return `${x},${y}`
      }).join(' ')
    }

    const getPointX = (index) => {
      const width = 800
      const padding = 50
      const step = (width - 2 * padding) / (chartData.value.length - 1)
      return padding + index * step
    }

    const getPointY = (value, max) => {
      const height = 400
      const padding = 50
      if (max === 0) return height - padding
      return height - padding - ((value / max) * (height - 2 * padding))
    }

    const formatCurrency = (amount) => {
      if (amount === null || amount === undefined) return 'IDR 0'
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount)
    }

    const formatShortCurrency = (amount) => {
      if (amount === null || amount === undefined) return '0'
      if (amount >= 1000000000) return (amount / 1000000000).toFixed(1) + 'B'
      if (amount >= 1000000) return (amount / 1000000).toFixed(1) + 'M'
      if (amount >= 1000) return (amount / 1000).toFixed(1) + 'K'
      return amount.toString()
    }

    const refreshData = () => {
      fetchData()
    }

    const exportReport = () => {
      console.log('Export report functionality')
    }

    const viewBudgetDetail = (id) => {
      router.push(`/budgets/${id}`)
    }

    const editBudget = (id) => {
      router.push(`/budgets/${id}/edit`)
    }

    const dismissAlert = (alertId) => {
      alerts.value = alerts.value.filter(alert => alert.id !== alertId)
    }

    onMounted(() => {
      fetchData()
    })

    return {
      loading,
      chartType,
      viewMode,
      sortField,
      sortDirection,
      periods,
      filters,
      kpis,
      chartData,
      maxValue,
      tableData,
      sortedTableData,
      trendData,
      insights,
      alerts,
      chartContainer,
      fetchData,
      applyFilters,
      clearFilters,
      toggleChartType,
      sortBy,
      toggleSortDirection,
      getSortIcon,
      getBarHeight,
      getTrendHeight,
      getVarianceTrendHeight,
      getPerformanceClass,
      getPerformanceText,
      getPerformanceWidth,
      getBudgetLinePoints,
      getActualLinePoints,
      getPointX,
      getPointY,
      formatCurrency,
      formatShortCurrency,
      refreshData,
      exportReport,
      viewBudgetDetail,
      editBudget,
      dismissAlert
    }
  }
}
</script>

<style scoped>
.budget-vs-actual-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Filters Section */
.filters-section .filters-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
}

.filters-card h3 {
  color: #1e293b;
  margin-bottom: 1rem;
  font-size: 1.1rem;
}

.filters-card h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: end;
}

.filter-group label {
  display: block;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.filter-select {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  background: white;
  transition: all 0.3s ease;
}

.filter-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn-clear {
  padding: 0.75rem 1rem;
  background: #f1f5f9;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  color: #64748b;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.btn-clear:hover {
  background: #e2e8f0;
  color: #475569;
}

/* KPI Section */
.kpi-section {
  margin-bottom: 2rem;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.kpi-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.kpi-card.primary::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.kpi-card.success::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.kpi-card.warning::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.kpi-card.info::before {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.kpi-card.accent::before {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.kpi-content {
  display: flex;
  align-items: center;
  gap: 1rem;
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

.kpi-card.primary .kpi-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.kpi-card.success .kpi-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.kpi-card.warning .kpi-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.kpi-card.info .kpi-icon {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.kpi-card.accent .kpi-icon {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.kpi-content h3 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.kpi-content p {
  color: #64748b;
  font-size: 1rem;
  margin: 0 0 0.25rem 0;
  font-weight: 500;
}

.kpi-content small {
  color: #94a3b8;
  font-size: 0.8rem;
}

.kpi-trend {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.kpi-trend.positive {
  color: #16a34a;
}

.kpi-trend.negative {
  color: #dc2626;
}

.kpi-trend i {
  font-size: 1rem;
}

/* Chart Section */
.chart-section {
  margin-bottom: 2rem;
}

.chart-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.chart-header h3 {
  color: #1e293b;
  font-size: 1.1rem;
  margin: 0;
}

.chart-header h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.chart-legend {
  display: flex;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #64748b;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 3px;
}

.legend-color.budgeted {
  background: #6366f1;
}

.legend-color.actual {
  background: #10b981;
}

.legend-color.variance {
  background: #f59e0b;
}

.chart-container {
  padding: 2rem;
  min-height: 400px;
}

.chart-wrapper {
  width: 100%;
  height: 100%;
}

/* Bar Chart */
.chart-bars {
  display: flex;
  justify-content: space-between;
  align-items: end;
  height: 300px;
  gap: 1rem;
}

.chart-bar-group {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  max-width: 80px;
}

.bar-label {
  font-size: 0.8rem;
  color: #64748b;
  margin-bottom: 0.5rem;
  text-align: center;
  font-weight: 500;
  text-overflow: ellipsis;
  overflow: hidden;
  white-space: nowrap;
  width: 100%;
}

.bars-container {
  display: flex;
  gap: 4px;
  height: 250px;
  align-items: end;
  width: 100%;
}

.bar {
  flex: 1;
  border-radius: 4px 4px 0 0;
  min-height: 10px;
  display: flex;
  flex-direction: column;
  justify-content: end;
  align-items: center;
  padding: 0.25rem;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
}

.bar.budgeted {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.bar.actual {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.bar:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.bar-value {
  color: white;
  font-size: 0.7rem;
  font-weight: 600;
  text-align: center;
  writing-mode: vertical-rl;
  text-orientation: mixed;
}

/* Line Chart */
.line-chart {
  width: 100%;
  height: 400px;
}

.data-point {
  cursor: pointer;
  transition: all 0.3s ease;
}

.data-point:hover {
  r: 6;
  filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.3));
}

/* Loading */
.chart-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  color: #64748b;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Table Section */
.table-section {
  margin-bottom: 2rem;
}

.table-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.table-header h3 {
  color: #1e293b;
  font-size: 1.1rem;
  margin: 0;
}

.table-header h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.btn-sort {
  padding: 0.5rem 1rem;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.85rem;
}

.btn-sort:hover {
  background: #5b5bd6;
}

.table-container {
  overflow-x: auto;
}

.comparison-table {
  width: 100%;
  border-collapse: collapse;
}

.comparison-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.comparison-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: all 0.3s ease;
}

.comparison-table th.sortable:hover {
  background: #e2e8f0;
  color: #6366f1;
}

.comparison-table th.sortable i {
  margin-left: 0.5rem;
  opacity: 0.5;
}

.comparison-table th.text-right,
.comparison-table td.text-right {
  text-align: right;
}

.comparison-table th.text-center,
.comparison-table td.text-center {
  text-align: center;
}

.comparison-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.table-row:hover {
  background: #f8fafc;
}

.name-cell {
  min-width: 200px;
}

.name-content strong {
  display: block;
  color: #1e293b;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.name-content small {
  color: #64748b;
  font-size: 0.8rem;
}

.amount-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
}

.amount-badge.budgeted {
  background: #ede9fe;
  color: #7c3aed;
}

.amount-badge.actual {
  background: #dcfce7;
  color: #16a34a;
}

.variance-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
}

.variance-badge.positive {
  background: #fee2e2;
  color: #dc2626;
}

.variance-badge.negative {
  background: #dcfce7;
  color: #16a34a;
}

.variance-badge small {
  display: block;
  font-size: 0.7rem;
  opacity: 0.8;
}

.percentage-badge {
  font-weight: 600;
  font-size: 0.9rem;
}

.percentage-badge.excellent {
  color: #16a34a;
}

.percentage-badge.good {
  color: #65a30d;
}

.percentage-badge.fair {
  color: #d97706;
}

.percentage-badge.poor {
  color: #dc2626;
}

.percentage-badge.neutral {
  color: #64748b;
}

.no-data {
  color: #94a3b8;
  font-style: italic;
}

.performance-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.performance-bar {
  width: 60px;
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.performance-fill {
  height: 100%;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.performance-fill.excellent {
  background: #16a34a;
}

.performance-fill.good {
  background: #65a30d;
}

.performance-fill.fair {
  background: #d97706;
}

.performance-fill.poor {
  background: #dc2626;
}

.performance-fill.neutral {
  background: #64748b;
}

.performance-text {
  font-size: 0.7rem;
  color: #64748b;
  font-weight: 500;
}

.action-buttons {
  display: flex;
  gap: 0.25rem;
  justify-content: center;
}

.btn-action {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 0.8rem;
}

.btn-action.view {
  background: #ede9fe;
  color: #7c3aed;
}

.btn-action.view:hover {
  background: #ddd6fe;
}

.btn-action.edit {
  background: #fef3c7;
  color: #d97706;
}

.btn-action.edit:hover {
  background: #fde68a;
}

/* Trend Section */
.trend-section {
  margin-bottom: 2rem;
}

.trend-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.trend-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.trend-content {
  padding: 1.5rem;
}

.trend-chart {
  display: flex;
  justify-content: space-between;
  align-items: end;
  height: 200px;
  gap: 0.5rem;
}

.trend-bar {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.trend-month {
  font-size: 0.8rem;
  color: #64748b;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.trend-bars {
  display: flex;
  gap: 2px;
  height: 150px;
  align-items: end;
  width: 100%;
}

.trend-bar-item {
  flex: 1;
  border-radius: 2px 2px 0 0;
  min-height: 10px;
  display: flex;
  flex-direction: column;
  justify-content: end;
  align-items: center;
  padding: 0.25rem;
  transition: all 0.3s ease;
}

.trend-bar-item.budgeted {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.trend-bar-item.actual {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.trend-value {
  color: white;
  font-size: 0.6rem;
  font-weight: 600;
  writing-mode: vertical-rl;
  text-orientation: mixed;
}

.variance-trend {
  display: flex;
  justify-content: space-between;
  align-items: end;
  height: 200px;
  gap: 0.5rem;
}

.variance-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  height: 100%;
}

.variance-month {
  font-size: 0.8rem;
  color: #64748b;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.variance-bar-container {
  flex: 1;
  display: flex;
  align-items: end;
  width: 100%;
  position: relative;
}

.variance-bar {
  width: 100%;
  min-height: 10px;
  border-radius: 2px 2px 0 0;
  display: flex;
  flex-direction: column;
  justify-content: end;
  align-items: center;
  padding: 0.25rem;
  transition: all 0.3s ease;
}

.variance-bar.positive {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.variance-bar.negative {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.variance-value {
  color: white;
  font-size: 0.6rem;
  font-weight: 600;
  writing-mode: vertical-rl;
  text-orientation: mixed;
}

.variance-indicator {
  font-size: 0.7rem;
  font-weight: 500;
  margin-top: 0.25rem;
}

.variance-indicator.over {
  color: #dc2626;
}

.variance-indicator.under {
  color: #16a34a;
}

/* Insights Section */
.insights-section {
  margin-bottom: 2rem;
}

.insights-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.insight-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.insight-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.insight-header h3 {
  color: #1e293b;
  margin: 0;
  font-size: 1rem;
}

.insight-header h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.insight-content {
  padding: 1.5rem;
}

.insight-list,
.alert-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.insight-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid #e2e8f0;
}

.insight-item.positive {
  background: #f0fdf4;
  border-left-color: #22c55e;
}

.insight-item.warning {
  background: #fffbeb;
  border-left-color: #f59e0b;
}

.insight-item.info {
  background: #eff6ff;
  border-left-color: #3b82f6;
}

.insight-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1rem;
  flex-shrink: 0;
}

.insight-item.positive .insight-icon {
  background: #22c55e;
}

.insight-item.warning .insight-icon {
  background: #f59e0b;
}

.insight-item.info .insight-icon {
  background: #3b82f6;
}

.insight-text h4 {
  color: #1e293b;
  font-size: 0.9rem;
  margin: 0 0 0.25rem 0;
  font-weight: 600;
}

.insight-text p {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.5;
  margin: 0;
}

.alert-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid #e2e8f0;
  position: relative;
}

.alert-item.high {
  background: #fef2f2;
  border-left-color: #dc2626;
}

.alert-item.medium {
  background: #fffbeb;
  border-left-color: #f59e0b;
}

.alert-item.low {
  background: #f0fdf4;
  border-left-color: #16a34a;
}

.alert-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 0.25rem;
}

.alert-item.high .alert-indicator {
  background: #dc2626;
}

.alert-item.medium .alert-indicator {
  background: #f59e0b;
}

.alert-item.low .alert-indicator {
  background: #16a34a;
}

.alert-content {
  flex: 1;
}

.alert-content h4 {
  color: #1e293b;
  font-size: 0.9rem;
  margin: 0 0 0.25rem 0;
  font-weight: 600;
}

.alert-content p {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.5;
  margin: 0 0 0.25rem 0;
}

.alert-content small {
  color: #94a3b8;
  font-size: 0.75rem;
}

.alert-dismiss {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.alert-dismiss:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #64748b;
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

.action-button.primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.action-button.primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
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
  transform: none !important;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .filters-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .chart-legend {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .trend-grid,
  .insights-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .kpi-grid {
    grid-template-columns: 1fr;
  }
  
  .kpi-card {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }
  
  .chart-bars {
    flex-direction: column;
    height: auto;
    gap: 0.5rem;
  }
  
  .chart-bar-group {
    flex-direction: row;
    max-width: none;
    height: 60px;
  }
  
  .bars-container {
    height: 40px;
    flex-direction: row;
  }
  
  .bar-value {
    writing-mode: horizontal-tb;
    text-orientation: initial;
  }
  
  .table-container {
    overflow-x: scroll;
  }
  
  .comparison-table {
    min-width: 800px;
  }
  
  .trend-chart {
    height: 150px;
  }
  
  .trend-bars {
    height: 100px;
  }
  
  .variance-trend {
    height: 150px;
  }
}
</style>