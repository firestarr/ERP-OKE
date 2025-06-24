<template>
  <div class="aging-report">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <h1 class="page-title">
            <i class="fas fa-chart-bar"></i>
            Accounts Receivable Aging Report
          </h1>
          <p class="page-subtitle">Track overdue receivables and customer payment patterns</p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/receivables" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i>
            Back to Receivables
          </router-link>
          <button @click="exportReport" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export Report
          </button>
          <button @click="printReport" class="btn btn-primary">
            <i class="fas fa-print"></i>
            Print Report
          </button>
        </div>
      </div>
    </div>

    <!-- Report Filters -->
    <div class="filters-section">
      <div class="filters-grid">
        <div class="filter-group">
          <label>Report Date</label>
          <input 
            type="date" 
            v-model="reportDate" 
            @change="loadAgingData"
          >
        </div>
        
        <div class="filter-group">
          <label>Customer</label>
          <select v-model="selectedCustomer" @change="loadAgingData">
            <option value="">All Customers</option>
            <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
              {{ customer.name }}
            </option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Minimum Balance</label>
          <div class="input-group">
            <span class="input-prefix">$</span>
            <input 
              type="number" 
              v-model.number="minBalance" 
              @change="loadAgingData"
              placeholder="0.00"
              step="0.01"
              min="0"
            >
          </div>
        </div>
        
        <div class="filter-group">
          <label>Show Zero Balances</label>
          <label class="toggle-switch">
            <input 
              type="checkbox" 
              v-model="showZeroBalances" 
              @change="loadAgingData"
            >
            <span class="slider"></span>
          </label>
        </div>
      </div>
    </div>

    <!-- Summary Dashboard -->
    <div class="summary-dashboard">
      <div class="summary-card current">
        <div class="card-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.current) }}</h3>
          <p>Current (0-30 days)</p>
          <span class="percentage">{{ getPercentage(summary.current) }}%</span>
        </div>
      </div>
      
      <div class="summary-card days-30">
        <div class="card-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.days_1_30) }}</h3>
          <p>1-30 Days Past Due</p>
          <span class="percentage">{{ getPercentage(summary.days_1_30) }}%</span>
        </div>
      </div>
      
      <div class="summary-card days-60">
        <div class="card-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.days_31_60) }}</h3>
          <p>31-60 Days Past Due</p>
          <span class="percentage">{{ getPercentage(summary.days_31_60) }}%</span>
        </div>
      </div>
      
      <div class="summary-card days-90">
        <div class="card-icon">
          <i class="fas fa-times-circle"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.days_61_90) }}</h3>
          <p>61-90 Days Past Due</p>
          <span class="percentage">{{ getPercentage(summary.days_61_90) }}%</span>
        </div>
      </div>
      
      <div class="summary-card over-90">
        <div class="card-icon">
          <i class="fas fa-ban"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.over_90) }}</h3>
          <p>Over 90 Days Past Due</p>
          <span class="percentage">{{ getPercentage(summary.over_90) }}%</span>
        </div>
      </div>
      
      <div class="summary-card total">
        <div class="card-icon">
          <i class="fas fa-calculator"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.total) }}</h3>
          <p>Total Outstanding</p>
          <span class="customer-count">{{ agingData.length }} customers</span>
        </div>
      </div>
    </div>

    <!-- Visual Chart -->
    <div class="chart-section">
      <div class="chart-container">
        <h3>
          <i class="fas fa-pie-chart"></i>
          Aging Distribution
        </h3>
        <div class="chart-wrapper">
          <canvas ref="agingChart" width="400" height="200"></canvas>
        </div>
      </div>
      
      <div class="risk-analysis">
        <h3>
          <i class="fas fa-shield-alt"></i>
          Risk Analysis
        </h3>
        <div class="risk-metrics">
          <div class="risk-item">
            <div class="risk-indicator low"></div>
            <div class="risk-content">
              <h4>Low Risk</h4>
              <p>{{ formatCurrency(summary.current + summary.days_1_30) }}</p>
              <span>{{ ((summary.current + summary.days_1_30) / summary.total * 100).toFixed(1) }}%</span>
            </div>
          </div>
          
          <div class="risk-item">
            <div class="risk-indicator medium"></div>
            <div class="risk-content">
              <h4>Medium Risk</h4>
              <p>{{ formatCurrency(summary.days_31_60) }}</p>
              <span>{{ (summary.days_31_60 / summary.total * 100).toFixed(1) }}%</span>
            </div>
          </div>
          
          <div class="risk-item">
            <div class="risk-indicator high"></div>
            <div class="risk-content">
              <h4>High Risk</h4>
              <p>{{ formatCurrency(summary.days_61_90 + summary.over_90) }}</p>
              <span>{{ ((summary.days_61_90 + summary.over_90) / summary.total * 100).toFixed(1) }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading aging report...</p>
    </div>

    <!-- Aging Data Table -->
    <div v-else class="aging-table-section">
      <div class="table-header">
        <h3>
          <i class="fas fa-table"></i>
          Detailed Aging Report
        </h3>
        <div class="table-actions">
          <button @click="sortBy('customer_name')" class="sort-btn" :class="{ active: sortField === 'customer_name' }">
            Customer {{ getSortIcon('customer_name') }}
          </button>
          <button @click="sortBy('total_balance')" class="sort-btn" :class="{ active: sortField === 'total_balance' }">
            Total {{ getSortIcon('total_balance') }}
          </button>
        </div>
      </div>
      
      <div v-if="filteredAgingData.length === 0" class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No data available</h3>
        <p>No receivables match your current filters</p>
      </div>
      
      <div v-else class="aging-table">
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th class="customer-col">Customer</th>
                <th class="amount-col">Current</th>
                <th class="amount-col">1-30 Days</th>
                <th class="amount-col">31-60 Days</th>
                <th class="amount-col">61-90 Days</th>
                <th class="amount-col">Over 90 Days</th>
                <th class="amount-col total-col">Total Balance</th>
                <th class="actions-col">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="customer in paginatedData" 
                :key="customer.customer_id"
                class="aging-row"
                :class="getRiskClass(customer)"
              >
                <td class="customer-cell">
                  <div class="customer-info">
                    <h4>{{ customer.customer_name }}</h4>
                    <span class="customer-id">#{{ customer.customer_id }}</span>
                  </div>
                </td>
                
                <td class="amount-cell current">
                  {{ formatCurrency(customer.current_amount) }}
                </td>
                
                <td class="amount-cell days-30">
                  {{ formatCurrency(customer.days_1_30) }}
                </td>
                
                <td class="amount-cell days-60">
                  {{ formatCurrency(customer.days_31_60) }}
                </td>
                
                <td class="amount-cell days-90">
                  {{ formatCurrency(customer.days_61_90) }}
                </td>
                
                <td class="amount-cell over-90">
                  {{ formatCurrency(customer.over_90) }}
                </td>
                
                <td class="amount-cell total">
                  <strong>{{ formatCurrency(customer.total_balance) }}</strong>
                </td>
                
                <td class="actions-cell">
                  <div class="action-buttons">
                    <button 
                      @click="viewCustomerReceivables(customer.customer_id)"
                      class="btn-icon"
                      title="View Receivables"
                    >
                      <i class="fas fa-eye"></i>
                    </button>
                    
                    <button 
                      @click="generateStatement(customer.customer_id)"
                      class="btn-icon"
                      title="Generate Statement"
                    >
                      <i class="fas fa-file-alt"></i>
                    </button>
                    
                    <button 
                      @click="sendReminder(customer.customer_id)"
                      class="btn-icon"
                      title="Send Reminder"
                      :disabled="customer.total_balance <= 0"
                    >
                      <i class="fas fa-envelope"></i>
                    </button>
                    
                    <button 
                      @click="viewCustomerDetail(customer.customer_id)"
                      class="btn-icon"
                      title="Customer Details"
                    >
                      <i class="fas fa-user"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination-section">
        <div class="pagination-info">
          Showing {{ (currentPage - 1) * pageSize + 1 }}-{{ Math.min(currentPage * pageSize, filteredAgingData.length) }} 
          of {{ filteredAgingData.length }} customers
        </div>
        <div class="pagination-controls">
          <button 
            @click="currentPage--" 
            :disabled="currentPage <= 1"
            class="btn btn-ghost"
          >
            <i class="fas fa-chevron-left"></i>
            Previous
          </button>
          
          <div class="page-numbers">
            <button 
              v-for="page in visiblePages" 
              :key="page"
              @click="currentPage = page"
              :class="{ active: page === currentPage }"
              class="page-btn"
            >
              {{ page }}
            </button>
          </div>
          
          <button 
            @click="currentPage++" 
            :disabled="currentPage >= totalPages"
            class="btn btn-ghost"
          >
            Next
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AgingReport',
  data() {
    return {
      loading: true,
      agingData: [],
      customers: [],
      reportDate: new Date().toISOString().split('T')[0],
      selectedCustomer: '',
      minBalance: 0,
      showZeroBalances: false,
      sortField: 'total_balance',
      sortDirection: 'desc',
      currentPage: 1,
      pageSize: 20,
      summary: {
        current: 0,
        days_1_30: 0,
        days_31_60: 0,
        days_61_90: 0,
        over_90: 0,
        total: 0
      },
      chart: null
    }
  },
  computed: {
    filteredAgingData() {
      let data = this.agingData
      
      // Filter by customer
      if (this.selectedCustomer) {
        data = data.filter(item => item.customer_id == this.selectedCustomer)
      }
      
      // Filter by minimum balance
      if (this.minBalance > 0) {
        data = data.filter(item => item.total_balance >= this.minBalance)
      }
      
      // Filter zero balances
      if (!this.showZeroBalances) {
        data = data.filter(item => item.total_balance > 0)
      }
      
      // Sort data
      data.sort((a, b) => {
        let aVal = a[this.sortField]
        let bVal = b[this.sortField]
        
        if (typeof aVal === 'string') {
          aVal = aVal.toLowerCase()
          bVal = bVal.toLowerCase()
        }
        
        if (this.sortDirection === 'asc') {
          return aVal > bVal ? 1 : -1
        } else {
          return aVal < bVal ? 1 : -1
        }
      })
      
      return data
    },
    
    paginatedData() {
      const start = (this.currentPage - 1) * this.pageSize
      const end = start + this.pageSize
      return this.filteredAgingData.slice(start, end)
    },
    
    totalPages() {
      return Math.ceil(this.filteredAgingData.length / this.pageSize)
    },
    
    visiblePages() {
      const pages = []
      const start = Math.max(1, this.currentPage - 2)
      const end = Math.min(this.totalPages, this.currentPage + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    }
  },
  async mounted() {
    await this.loadCustomers()
    await this.loadAgingData()
    this.renderChart()
  },
  beforeUnmount() {
    if (this.chart) {
      this.chart.destroy()
    }
  },
  methods: {
    async loadCustomers() {
      try {
        const response = await axios.get('/customers')
        this.customers = response.data.data || response.data
      } catch (error) {
        console.error('Error loading customers:', error)
      }
    },
    
    async loadAgingData() {
      try {
        this.loading = true
        
        const params = {
          as_of_date: this.reportDate
        }
        
        const response = await axios.get('/accounting/customer-receivables/aging', { params })
        this.agingData = response.data.data || response.data
        
        this.calculateSummary()
        this.updateChart()
        
      } catch (error) {
        console.error('Error loading aging data:', error)
        this.$toast?.error('Failed to load aging report')
      } finally {
        this.loading = false
      }
    },
    
    calculateSummary() {
      this.summary = this.filteredAgingData.reduce((acc, customer) => {
        acc.current += parseFloat(customer.current_amount) || 0
        acc.days_1_30 += parseFloat(customer.days_1_30) || 0
        acc.days_31_60 += parseFloat(customer.days_31_60) || 0
        acc.days_61_90 += parseFloat(customer.days_61_90) || 0
        acc.over_90 += parseFloat(customer.over_90) || 0
        acc.total += parseFloat(customer.total_balance) || 0
        return acc
      }, {
        current: 0,
        days_1_30: 0,
        days_31_60: 0,
        days_61_90: 0,
        over_90: 0,
        total: 0
      })
    },
    
    renderChart() {
      const ctx = this.$refs.agingChart?.getContext('2d')
      if (!ctx) return
      
      // Simple chart implementation without external libraries
      this.drawPieChart(ctx)
    },
    
    drawPieChart(ctx) {
      const data = [
        { label: 'Current', value: this.summary.current, color: '#10b981' },
        { label: '1-30 Days', value: this.summary.days_1_30, color: '#f59e0b' },
        { label: '31-60 Days', value: this.summary.days_31_60, color: '#ef4444' },
        { label: '61-90 Days', value: this.summary.days_61_90, color: '#8b5cf6' },
        { label: 'Over 90 Days', value: this.summary.over_90, color: '#6b7280' }
      ]
      
      const canvas = ctx.canvas
      const centerX = canvas.width / 2
      const centerY = canvas.height / 2
      const radius = Math.min(centerX, centerY) - 20
      
      let total = data.reduce((sum, item) => sum + item.value, 0)
      if (total === 0) return
      
      let currentAngle = -Math.PI / 2
      
      ctx.clearRect(0, 0, canvas.width, canvas.height)
      
      data.forEach(segment => {
        const sliceAngle = (segment.value / total) * 2 * Math.PI
        
        ctx.beginPath()
        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle)
        ctx.lineTo(centerX, centerY)
        ctx.fillStyle = segment.color
        ctx.fill()
        
        currentAngle += sliceAngle
      })
    },
    
    updateChart() {
      this.$nextTick(() => {
        this.renderChart()
      })
    },
    
    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortDirection = 'desc'
      }
      this.currentPage = 1
    },
    
    getSortIcon(field) {
      if (this.sortField !== field) return ''
      return this.sortDirection === 'asc' ? '↑' : '↓'
    },
    
    getRiskClass(customer) {
      const total = customer.total_balance
      const highRisk = customer.days_61_90 + customer.over_90
      const mediumRisk = customer.days_31_60
      
      if (highRisk / total > 0.3) return 'risk-high'
      if (mediumRisk / total > 0.3) return 'risk-medium'
      return 'risk-low'
    },
    
    getPercentage(amount) {
      return this.summary.total > 0 ? (amount / this.summary.total * 100).toFixed(1) : '0.0'
    },
    
    viewCustomerReceivables(customerId) {
      this.$router.push(`/accounting/receivables?customer_id=${customerId}`)
    },
    
    generateStatement(customerId) {
      this.$router.push(`/accounting/receivables/statement/${customerId}`)
    },
    
    async sendReminder(customerId) {
      try {
        await axios.post(`/customers/${customerId}/send-payment-reminder`)
        this.$toast?.success('Payment reminder sent successfully')
      } catch (error) {
        console.error('Error sending reminder:', error)
        this.$toast?.error('Failed to send payment reminder')
      }
    },
    
    viewCustomerDetail(customerId) {
      this.$router.push(`/customers/${customerId}`)
    },
    
    exportReport() {
      // Implementation for CSV/Excel export
      const csvContent = this.generateCSV()
      this.downloadCSV(csvContent, `aging-report-${this.reportDate}.csv`)
    },
    
    generateCSV() {
      const headers = ['Customer', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90 Days', 'Total Balance']
      const rows = this.filteredAgingData.map(customer => [
        customer.customer_name,
        customer.current_amount,
        customer.days_1_30,
        customer.days_31_60,
        customer.days_61_90,
        customer.over_90,
        customer.total_balance
      ])
      
      return [headers, ...rows].map(row => row.join(',')).join('\n')
    },
    
    downloadCSV(content, filename) {
      const blob = new Blob([content], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    },
    
    printReport() {
      window.print()
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }
  }
}
</script>

<style scoped>
.aging-report {
  max-width: 1600px;
  margin: 0 auto;
  padding: 2rem;
  background: var(--gray-50);
  min-height: 100vh;
}

/* Header Section */
.page-header {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: var(--gray-800);
  margin-bottom: 0.5rem;
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

/* Filters Section */
.filters-section {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: end;
}

.filter-group {
  display: flex;
  flex-direction: column;
}

.filter-group label {
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: var(--gray-700);
}

.filter-group input,
.filter-group select {
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.filter-group input:focus,
.filter-group select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.input-group {
  position: relative;
  display: flex;
  align-items: center;
}

.input-prefix {
  position: absolute;
  left: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
  z-index: 1;
}

.input-group input {
  padding-left: 2rem;
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--gray-300);
  transition: 0.4s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: var(--primary-color);
}

input:checked + .slider:before {
  transform: translateX(26px);
}

/* Summary Dashboard */
.summary-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition: transform 0.2s;
}

.summary-card:hover {
  transform: translateY(-2px);
}

.card-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: white;
}

.summary-card.current .card-icon { background: #10b981; }
.summary-card.days-30 .card-icon { background: #f59e0b; }
.summary-card.days-60 .card-icon { background: #ef4444; }
.summary-card.days-90 .card-icon { background: #8b5cf6; }
.summary-card.over-90 .card-icon { background: #6b7280; }
.summary-card.total .card-icon { background: var(--primary-color); }

.card-content h3 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.card-content p {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.percentage {
  background: var(--gray-100);
  color: var(--gray-700);
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.customer-count {
  background: var(--primary-color);
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

/* Chart Section */
.chart-section {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

.chart-container,
.risk-analysis {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.chart-container h3,
.risk-analysis h3 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  color: var(--gray-800);
}

.chart-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}

.chart-wrapper canvas {
  max-width: 100%;
  height: auto;
}

/* Risk Analysis */
.risk-metrics {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.risk-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--gray-50);
  border-radius: 8px;
}

.risk-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.risk-indicator.low { background: #10b981; }
.risk-indicator.medium { background: #f59e0b; }
.risk-indicator.high { background: #ef4444; }

.risk-content h4 {
  margin-bottom: 0.25rem;
  color: var(--gray-800);
}

.risk-content p {
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 0.25rem;
}

.risk-content span {
  color: var(--gray-600);
  font-size: 0.875rem;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--gray-200);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Aging Table */
.aging-table-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.table-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h3 {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
  color: var(--gray-800);
}

.table-actions {
  display: flex;
  gap: 0.5rem;
}

.sort-btn {
  padding: 0.5rem 1rem;
  border: 1px solid var(--gray-300);
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.sort-btn:hover,
.sort-btn.active {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem;
}

.empty-state i {
  font-size: 3rem;
  color: var(--gray-400);
  margin-bottom: 1rem;
}

.empty-state h3 {
  margin-bottom: 0.5rem;
  color: var(--gray-700);
}

.empty-state p {
  color: var(--gray-600);
}

/* Table */
.aging-table {
  overflow: hidden;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  background: var(--gray-50);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--gray-700);
  border-bottom: 1px solid var(--gray-200);
  white-space: nowrap;
}

.customer-col { min-width: 200px; }
.amount-col { min-width: 120px; text-align: right; }
.total-col { min-width: 140px; }
.actions-col { min-width: 160px; text-align: center; }

.aging-row {
  transition: background-color 0.2s;
}

.aging-row:hover {
  background: var(--gray-50);
}

.aging-row.risk-high {
  border-left: 4px solid #ef4444;
}

.aging-row.risk-medium {
  border-left: 4px solid #f59e0b;
}

.aging-row.risk-low {
  border-left: 4px solid #10b981;
}

td {
  padding: 1rem;
  border-bottom: 1px solid var(--gray-200);
  vertical-align: middle;
}

.customer-cell {
  padding-left: 1.5rem;
}

.customer-info h4 {
  margin-bottom: 0.25rem;
  color: var(--gray-800);
}

.customer-id {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.amount-cell {
  text-align: right;
  font-weight: 500;
}

.amount-cell.current { color: #10b981; }
.amount-cell.days-30 { color: #f59e0b; }
.amount-cell.days-60 { color: #ef4444; }
.amount-cell.days-90 { color: #8b5cf6; }
.amount-cell.over-90 { color: #6b7280; }
.amount-cell.total { color: var(--gray-800); font-size: 1.1em; }

.actions-cell {
  text-align: center;
}

.action-buttons {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  background: var(--gray-100);
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover:not(:disabled) {
  background: var(--primary-color);
  color: white;
}

.btn-icon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Pagination */
.pagination-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

.pagination-info {
  color: var(--gray-600);
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-numbers {
  display: flex;
  gap: 0.25rem;
}

.page-btn {
  width: 36px;
  height: 36px;
  border: 1px solid var(--gray-300);
  background: white;
  color: var(--gray-600);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
}

.page-btn:hover,
.page-btn.active {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

/* Button Styles */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
}

.btn-outline {
  background: white;
  color: var(--primary-color);
  border: 1px solid var(--primary-color);
}

.btn-outline:hover {
  background: var(--primary-color);
  color: white;
}

.btn-ghost {
  background: transparent;
  color: var(--gray-600);
  border: 1px solid var(--gray-300);
}

.btn-ghost:hover:not(:disabled) {
  background: var(--gray-100);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .chart-section {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .aging-report {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: center;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-dashboard {
    grid-template-columns: 1fr;
  }
  
  .table-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .table-actions {
    justify-content: center;
  }
  
  .action-buttons {
    flex-wrap: wrap;
  }
  
  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
  
  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .summary-card {
    flex-direction: column;
    text-align: center;
    gap: 0.75rem;
  }
  
  .card-content {
    order: 2;
  }
  
  .card-icon {
    order: 1;
    margin: 0 auto;
  }
}
</style>