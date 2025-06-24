<template>
  <div class="aging-report-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-chart-bar"></i>
            Vendor Payables Aging Report
          </h1>
          <p class="page-subtitle">Track outstanding payables by age and manage payment priorities</p>
        </div>
        <div class="header-actions">
          <button @click="exportReport" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export Report
          </button>
          <button @click="printReport" class="btn btn-outline">
            <i class="fas fa-print"></i>
            Print
          </button>
          <button @click="refreshData" :disabled="loading" class="btn btn-primary">
            <i class="fas fa-refresh" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="filters-grid">
        <div class="filter-item">
          <label>Report Date</label>
          <input 
            type="date" 
            v-model="filters.as_of_date" 
            @change="applyFilters"
            class="form-input"
          >
        </div>
        
        <div class="filter-item">
          <label>Vendor</label>
          <select v-model="filters.vendor_id" @change="applyFilters" class="form-select">
            <option value="">All Vendors</option>
            <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
              {{ vendor.name }}
            </option>
          </select>
        </div>
        
        <div class="filter-item">
          <label>Minimum Amount</label>
          <div class="currency-input">
            <span class="currency-symbol">IDR</span>
            <input 
              type="number" 
              v-model="filters.min_amount" 
              @input="debounceFilter"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="form-input"
            >
          </div>
        </div>
        
        <div class="filter-item">
          <label>Group By</label>
          <select v-model="filters.group_by" @change="applyFilters" class="form-select">
            <option value="vendor">Vendor</option>
            <option value="currency">Currency</option>
            <option value="department">Department</option>
          </select>
        </div>
      </div>
      
      <div class="filter-actions">
        <button @click="clearFilters" class="btn btn-ghost">
          <i class="fas fa-times"></i>
          Clear Filters
        </button>
        <button @click="saveReportSettings" class="btn btn-ghost">
          <i class="fas fa-bookmark"></i>
          Save Settings
        </button>
      </div>
    </div>

    <!-- Summary Dashboard -->
    <div class="summary-dashboard">
      <div class="summary-cards">
        <div class="summary-card total">
          <div class="card-header">
            <h3>Total Outstanding</h3>
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div class="card-value">{{ formatCurrency(summary.total_outstanding) }}</div>
          <div class="card-footer">
            <span class="trend" :class="summary.trend_class">
              <i :class="summary.trend_icon"></i>
              {{ summary.trend_text }}
            </span>
          </div>
        </div>
        
        <div class="summary-card current">
          <div class="card-header">
            <h3>Current (0-30 days)</h3>
            <i class="fas fa-clock"></i>
          </div>
          <div class="card-value">{{ formatCurrency(summary.current) }}</div>
          <div class="card-percentage">{{ getPercentage(summary.current) }}%</div>
        </div>
        
        <div class="summary-card aging-30">
          <div class="card-header">
            <h3>31-60 Days</h3>
            <i class="fas fa-hourglass-half"></i>
          </div>
          <div class="card-value">{{ formatCurrency(summary.aging_30_60) }}</div>
          <div class="card-percentage">{{ getPercentage(summary.aging_30_60) }}%</div>
        </div>
        
        <div class="summary-card aging-60">
          <div class="card-header">
            <h3>61-90 Days</h3>
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div class="card-value">{{ formatCurrency(summary.aging_60_90) }}</div>
          <div class="card-percentage">{{ getPercentage(summary.aging_60_90) }}%</div>
        </div>
        
        <div class="summary-card overdue">
          <div class="card-header">
            <h3>Over 90 Days</h3>
            <i class="fas fa-fire"></i>
          </div>
          <div class="card-value">{{ formatCurrency(summary.over_90) }}</div>
          <div class="card-percentage">{{ getPercentage(summary.over_90) }}%</div>
        </div>
      </div>
      
      <!-- Aging Chart -->
      <div class="chart-section">
        <div class="chart-header">
          <h3>Aging Distribution</h3>
          <div class="chart-controls">
            <button 
              v-for="chartType in chartTypes" 
              :key="chartType.value"
              @click="selectedChartType = chartType.value"
              :class="['btn', 'btn-sm', selectedChartType === chartType.value ? 'btn-primary' : 'btn-ghost']"
            >
              <i :class="chartType.icon"></i>
              {{ chartType.label }}
            </button>
          </div>
        </div>
        <div class="chart-container">
          <canvas ref="agingChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Aging Report Table -->
    <div class="report-section">
      <div class="section-header">
        <h2>
          <i class="fas fa-table"></i>
          Detailed Aging Report
        </h2>
        <div class="section-actions">
          <button @click="expandAll" class="btn btn-sm btn-ghost">
            <i class="fas fa-expand-alt"></i>
            Expand All
          </button>
          <button @click="collapseAll" class="btn btn-sm btn-ghost">
            <i class="fas fa-compress-alt"></i>
            Collapse All
          </button>
        </div>
      </div>
      
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading aging report...</p>
      </div>
      
      <div v-else-if="agingData.length === 0" class="empty-state">
        <i class="fas fa-chart-bar"></i>
        <h3>No Data Available</h3>
        <p>No payables found for the selected criteria</p>
        <button @click="clearFilters" class="btn btn-primary">Clear Filters</button>
      </div>
      
      <div v-else class="aging-table-container">
        <table class="aging-table">
          <thead>
            <tr>
              <th class="vendor-column">
                <button @click="sortBy('vendor_name')" class="sort-header">
                  Vendor
                  <i class="fas fa-sort" :class="getSortIcon('vendor_name')"></i>
                </button>
              </th>
              <th class="amount-column">
                <button @click="sortBy('total')" class="sort-header">
                  Total Outstanding
                  <i class="fas fa-sort" :class="getSortIcon('total')"></i>
                </button>
              </th>
              <th class="aging-column">Current (0-30)</th>
              <th class="aging-column">31-60 Days</th>
              <th class="aging-column">61-90 Days</th>
              <th class="aging-column">Over 90 Days</th>
              <th class="action-column">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in paginatedData" :key="group.vendor_id">
              <!-- Vendor Group Row -->
              <tr class="vendor-group-row" @click="toggleGroup(group.vendor_id)">
                <td class="vendor-info">
                  <div class="vendor-toggle">
                    <i 
                      class="fas fa-chevron-right toggle-icon" 
                      :class="{ 'expanded': expandedGroups.includes(group.vendor_id) }"
                    ></i>
                    <div class="vendor-details">
                      <div class="vendor-avatar">
                        {{ group.vendor_name.charAt(0).toUpperCase() }}
                      </div>
                      <div class="vendor-text">
                        <span class="vendor-name">{{ group.vendor_name }}</span>
                        <small class="vendor-code">{{ group.vendor_code }}</small>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="total-amount">
                  <span class="amount-value">{{ formatCurrency(group.total) }}</span>
                  <div class="payable-count">{{ group.payables.length }} payable(s)</div>
                </td>
                <td class="aging-amount current">
                  <span class="amount">{{ formatCurrency(group.days_1_30) }}</span>
                  <div class="percentage">{{ getPercentage(group.days_1_30, group.total_balance) }}%</div>
                </td>
                <td class="aging-amount aging-30">
                  <span class="amount">{{ formatCurrency(group.days_31_60) }}</span>
                  <div class="percentage">{{ getPercentage(group.days_31_60, group.total_balance) }}%</div>
                </td>
                <td class="aging-amount aging-60">
                  <span class="amount">{{ formatCurrency(group.days_61_90) }}</span>
                  <div class="percentage">{{ getPercentage(group.days_61_90, group.total_balance) }}%</div>
                </td>
                <td class="aging-amount overdue">
                  <span class="amount">{{ formatCurrency(group.days_over_90) }}</span>
                  <div class="percentage">{{ getPercentage(group.days_over_90, group.total_balance) }}%</div>
                </td>
                <td class="actions">
                  <button @click.stop="viewVendorStatement(group.vendor_id)" class="btn-action">
                    <i class="fas fa-file-alt" title="View Statement"></i>
                  </button>
                  <button @click.stop="contactVendor(group)" class="btn-action">
                    <i class="fas fa-envelope" title="Contact Vendor"></i>
                  </button>
                </td>
              </tr>
              
              <!-- Payable Detail Rows -->
              <template v-if="expandedGroups.includes(group.vendor_id)">
                <tr 
                  v-for="payable in group.payables" 
                  :key="payable.payable_id" 
                  class="payable-detail-row"
                >
                  <td class="payable-info">
                    <div class="payable-details">
                      <span class="payable-id">#{{ payable.payable_id }}</span>
                      <span class="invoice-number">{{ payable.invoice_number }}</span>
                    </div>
                  </td>
                  <td class="payable-amount">
                    <span class="amount">{{ formatCurrency(payable.balance) }}</span>
                    <div class="due-date" :class="getDueDateClass(payable.days_overdue)">
                      Due: {{ formatDate(payable.due_date) }}
                    </div>
                  </td>
                  <td class="aging-cell" :class="{ 'has-amount': payable.current > 0 }">
                    {{ payable.current > 0 ? formatCurrency(payable.current) : '-' }}
                  </td>
                  <td class="aging-cell" :class="{ 'has-amount': payable.aging_30_60 > 0 }">
                    {{ payable.aging_30_60 > 0 ? formatCurrency(payable.aging_30_60) : '-' }}
                  </td>
                  <td class="aging-cell" :class="{ 'has-amount': payable.aging_60_90 > 0 }">
                    {{ payable.aging_60_90 > 0 ? formatCurrency(payable.aging_60_90) : '-' }}
                  </td>
                  <td class="aging-cell" :class="{ 'has-amount': payable.over_90 > 0 }">
                    {{ payable.over_90 > 0 ? formatCurrency(payable.over_90) : '-' }}
                  </td>
                  <td class="actions">
                    <button @click="viewPayable(payable.payable_id)" class="btn-action">
                      <i class="fas fa-eye" title="View Payable"></i>
                    </button>
                    <button @click="recordPayment(payable)" class="btn-action">
                      <i class="fas fa-credit-card" title="Record Payment"></i>
                    </button>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
        
        <!-- Pagination -->
        <div v-if="pagination.total > 0" class="pagination-section">
          <div class="pagination-info">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} vendors
          </div>
          <div class="pagination-controls">
            <button 
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="btn btn-outline btn-sm"
            >
              <i class="fas fa-chevron-left"></i>
              Previous
            </button>
            
            <span class="page-numbers">
              <button 
                v-for="page in getPageNumbers()" 
                :key="page"
                @click="changePage(page)"
                :class="['btn', 'btn-sm', page === pagination.current_page ? 'btn-primary' : 'btn-ghost']"
              >
                {{ page }}
              </button>
            </span>
            
            <button 
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
              class="btn btn-outline btn-sm"
            >
              Next
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="quick-actions-panel">
      <div class="panel-header">
        <h3>Quick Actions</h3>
        <button @click="toggleQuickActions" class="btn-toggle">
          <i class="fas fa-chevron-up" :class="{ 'rotated': !showQuickActions }"></i>
        </button>
      </div>
      
      <div v-if="showQuickActions" class="panel-content">
        <div class="action-cards">
          <div class="action-card">
            <div class="action-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="action-content">
              <h4>Overdue Payables</h4>
              <p>{{ overdueCount }} payables are overdue</p>
              <button @click="showOverdueOnly" class="btn btn-sm btn-danger">
                View Overdue
              </button>
            </div>
          </div>
          
          <div class="action-card">
            <div class="action-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="action-content">
              <h4>Due This Week</h4>
              <p>{{ dueThisWeekCount }} payables due this week</p>
              <button @click="showDueThisWeek" class="btn btn-sm btn-warning">
                View Due Soon
              </button>
            </div>
          </div>
          
          <div class="action-card">
            <div class="action-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="action-content">
              <h4>Trend Analysis</h4>
              <p>Compare with previous period</p>
              <button @click="showTrendAnalysis" class="btn btn-sm btn-info">
                View Trends
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact Vendor Modal -->
    <div v-if="showContactModal" class="modal-overlay" @click="closeContactModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Contact Vendor</h3>
          <button @click="closeContactModal" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="vendor-summary">
            <h4>{{ selectedVendorForContact?.vendor_name }}</h4>
            <p>Total Outstanding: {{ formatCurrency(selectedVendorForContact?.total) }}</p>
          </div>
          
          <form @submit.prevent="sendVendorReminder">
            <div class="form-group">
              <label>Email Template</label>
              <select v-model="contactForm.template" class="form-select">
                <option value="payment_reminder">Payment Reminder</option>
                <option value="overdue_notice">Overdue Notice</option>
                <option value="account_statement">Account Statement</option>
              </select>
            </div>
            
            <div class="form-group">
              <label>Subject</label>
              <input 
                type="text" 
                v-model="contactForm.subject" 
                class="form-input"
                placeholder="Email subject"
              >
            </div>
            
            <div class="form-group">
              <label>Message</label>
              <textarea 
                v-model="contactForm.message" 
                rows="6"
                class="form-textarea"
                placeholder="Enter your message..."
              ></textarea>
            </div>
            
            <div class="modal-actions">
              <button type="button" @click="closeContactModal" class="btn btn-outline">
                Cancel
              </button>
              <button type="submit" :disabled="sendingEmail" class="btn btn-primary">
                <i v-if="sendingEmail" class="fas fa-spinner fa-spin"></i>
                Send Email
              </button>
            </div>
          </form>
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
      loading: false,
      agingData: [],
      vendors: [],
      expandedGroups: [],
      showQuickActions: true,
      showContactModal: false,
      sendingEmail: false,
      selectedVendorForContact: null,
      selectedChartType: 'doughnut',
      sortField: 'total',
      sortDirection: 'desc',
      filters: {
        as_of_date: new Date().toISOString().split('T')[0],
        vendor_id: '',
        min_amount: '',
        group_by: 'vendor'
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
        from: 0,
        to: 0
      },
      summary: {
        total_outstanding: 0,
        current: 0,
        aging_30_60: 0,
        aging_60_90: 0,
        over_90: 0,
        trend_class: 'positive',
        trend_icon: 'fas fa-arrow-up',
        trend_text: '+5.2% from last month'
      },
      chartTypes: [
        { value: 'doughnut', label: 'Doughnut', icon: 'fas fa-chart-pie' },
        { value: 'bar', label: 'Bar', icon: 'fas fa-chart-bar' },
        { value: 'line', label: 'Trend', icon: 'fas fa-chart-line' }
      ],
      contactForm: {
        template: 'payment_reminder',
        subject: 'Payment Reminder',
        message: ''
      },
      chart: null
    }
  },
  computed: {
    paginatedData() {
      const start = (this.pagination.current_page - 1) * this.pagination.per_page
      const end = start + this.pagination.per_page
      return this.agingData.slice(start, end)
    },
    
    overdueCount() {
      return this.agingData.reduce((count, group) => {
        return count + group.payables.filter(p => p.over_90 > 0 || p.aging_60_90 > 0).length
      }, 0)
    },
    
    dueThisWeekCount() {
      const weekFromNow = new Date()
      weekFromNow.setDate(weekFromNow.getDate() + 7)
      
      return this.agingData.reduce((count, group) => {
        return count + group.payables.filter(p => {
          const dueDate = new Date(p.due_date)
          return dueDate <= weekFromNow && p.current > 0
        }).length
      }, 0)
    }
  },
  created() {
    this.loadVendors()
    this.loadAgingData()
  },
  mounted() {
    this.initChart()
  },
  methods: {
    async loadVendors() {
      try {
        const response = await axios.get('/vendors')
        this.vendors = response.data.data || response.data
      } catch (error) {
        console.error('Error loading vendors:', error)
      }
    },
    
async loadAgingData() {
      this.loading = true
      try {
        const params = {
          ...this.filters,
          page: this.pagination.current_page,
          per_page: this.pagination.per_page
        }
        
        const response = await axios.get('/accounting/vendor-payables/aging', { params })
        
        // Normalize agingData to ensure payables is always an array
        const data = response.data.data || []
        data.forEach(group => {
          if (!Array.isArray(group.payables)) {
            group.payables = []
          }
        })
        this.agingData = data
        this.summary = response.data.summary || this.summary
        
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          per_page: response.data.per_page || 20,
          total: response.data.total || 0,
          from: response.data.from || 0,
          to: response.data.to || 0
        }
        
        this.updateChart()
      } catch (error) {
        console.error('Error loading aging data:', error)
        this.$toast?.error('Failed to load aging report')
      } finally {
        this.loading = false
      }
    },
    
    initChart() {
      // Chart initialization would go here
      // Using Chart.js or similar library
      console.log('Chart initialization')
    },
    
    updateChart() {
      // Chart update logic
      console.log('Chart update')
    },
    
    applyFilters() {
      this.pagination.current_page = 1
      this.loadAgingData()
    },
    
    debounceFilter() {
      clearTimeout(this.filterTimeout)
      this.filterTimeout = setTimeout(() => {
        this.applyFilters()
      }, 500)
    },
    
    clearFilters() {
      this.filters = {
        as_of_date: new Date().toISOString().split('T')[0],
        vendor_id: '',
        min_amount: '',
        group_by: 'vendor'
      }
      this.applyFilters()
    },
    
    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortDirection = 'desc'
      }
      this.loadAgingData()
    },
    
    getSortIcon(field) {
      if (this.sortField !== field) return ''
      return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    },
    
    toggleGroup(vendorId) {
      const index = this.expandedGroups.indexOf(vendorId)
      if (index > -1) {
        this.expandedGroups.splice(index, 1)
      } else {
        this.expandedGroups.push(vendorId)
      }
    },
    
    expandAll() {
      this.expandedGroups = this.agingData.map(group => group.vendor_id)
    },
    
    collapseAll() {
      this.expandedGroups = []
    },
    
    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.pagination.current_page = page
        this.loadAgingData()
      }
    },
    
    getPageNumbers() {
      const pages = []
      const start = Math.max(1, this.pagination.current_page - 2)
      const end = Math.min(this.pagination.last_page, this.pagination.current_page + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    },
    
    toggleQuickActions() {
      this.showQuickActions = !this.showQuickActions
    },
    
    viewVendorStatement(vendorId) {
      this.$router.push(`/vendor-statements/${vendorId}`)
    },
    
    viewPayable(payableId) {
      this.$router.push(`/payables/${payableId}`)
    },
    
    recordPayment(payable) {
      this.$router.push(`/payables/${payable.payable_id}`)
    },
    
    contactVendor(group) {
      this.selectedVendorForContact = group
      this.contactForm = {
        template: 'payment_reminder',
        subject: `Payment Reminder - Outstanding Balance ${this.formatCurrency(group.total)}`,
        message: `Dear ${group.vendor_name},\n\nWe hope this message finds you well. We wanted to reach out regarding your outstanding balance of ${this.formatCurrency(group.total)} with our company.\n\nPlease review the attached statement and let us know if you have any questions.\n\nThank you for your attention to this matter.`
      }
      this.showContactModal = true
    },
    
    closeContactModal() {
      this.showContactModal = false
      this.selectedVendorForContact = null
    },
    
    async sendVendorReminder() {
      this.sendingEmail = true
      try {
        await axios.post('/vendors/send-reminder', {
          vendor_id: this.selectedVendorForContact.vendor_id,
          ...this.contactForm
        })
        this.$toast?.success('Email sent successfully')
        this.closeContactModal()
      } catch (error) {
        console.error('Error sending email:', error)
        this.$toast?.error('Failed to send email')
      } finally {
        this.sendingEmail = false
      }
    },
    
    showOverdueOnly() {
      this.filters.min_amount = ''
      this.filters.vendor_id = ''
      // Add overdue filter logic
      this.applyFilters()
    },
    
    showDueThisWeek() {
      // Add due this week filter logic
      this.applyFilters()
    },
    
    showTrendAnalysis() {
      // Navigate to trend analysis or show modal
      console.log('Show trend analysis')
    },
    
    refreshData() {
      this.loadAgingData()
    },
    
    exportReport() {
      // Export functionality
      console.log('Export report')
    },
    
    printReport() {
      window.print()
    },
    
    saveReportSettings() {
      // Save current filter settings
      localStorage.setItem('aging_report_settings', JSON.stringify(this.filters))
      this.$toast?.success('Report settings saved')
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
      }).format(amount || 0)
    },
    
    formatDate(date) {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('id-ID')
    },
    
    getPercentage(amount, total = this.summary.total_outstanding) {
      if (!total || total === 0) return 0
      return Math.round((amount / total) * 100)
    },
    
    getDueDateClass(daysOverdue) {
      if (daysOverdue > 90) return 'severely-overdue'
      if (daysOverdue > 60) return 'very-overdue'
      if (daysOverdue > 30) return 'overdue'
      if (daysOverdue > 0) return 'past-due'
      return 'current'
    }
  }
}
</script>

<style scoped>
/* Main Container */
.aging-report-container {
  padding: 2rem;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  min-height: 100vh;
}

/* Header */
.page-header {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 24px;
  padding: 2rem;
  margin-bottom: 2rem;
  color: white;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-subtitle {
  opacity: 0.9;
  font-size: 1.1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

/* Filters */
.filters-section {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.filter-item label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.currency-input {
  position: relative;
}

.currency-symbol {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  font-weight: 600;
  z-index: 1;
}

.currency-input .form-input {
  padding-left: 48px;
}

.filter-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Summary Dashboard */
.summary-dashboard {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.summary-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  transition: transform 0.3s ease;
}

.summary-card:hover {
  transform: translateY(-2px);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card-header h3 {
  font-size: 0.9rem;
  font-weight: 600;
  color: #6b7280;
  margin: 0;
}

.card-header i {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.summary-card.total .card-header i { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.summary-card.current .card-header i { background: linear-gradient(135deg, #10b981, #059669); }
.summary-card.aging-30 .card-header i { background: linear-gradient(135deg, #f59e0b, #d97706); }
.summary-card.aging-60 .card-header i { background: linear-gradient(135deg, #ef4444, #dc2626); }
.summary-card.overdue .card-header i { background: linear-gradient(135deg, #7c2d12, #991b1b); }

.card-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.card-percentage {
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: 600;
}

.card-footer {
  margin-top: 0.5rem;
}

.trend {
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.trend.positive { color: #10b981; }
.trend.negative { color: #ef4444; }

/* Chart Section */
.chart-section {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.chart-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
}

.chart-controls {
  display: flex;
  gap: 0.5rem;
}

.chart-container {
  height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Report Section */
.report-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.section-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-actions {
  display: flex;
  gap: 0.5rem;
}

/* Aging Table */
.aging-table-container {
  overflow-x: auto;
}

.aging-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1.5rem;
}

.aging-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
  white-space: nowrap;
}

.sort-header {
  background: none;
  border: none;
  padding: 0;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.sort-header:hover {
  color: #6366f1;
}

.aging-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: top;
}

/* Vendor Group Row */
.vendor-group-row {
  background: #f8fafc;
  cursor: pointer;
  transition: background 0.3s ease;
}

.vendor-group-row:hover {
  background: #f1f5f9;
}

.vendor-toggle {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.toggle-icon {
  transition: transform 0.3s ease;
  color: #6b7280;
}

.toggle-icon.expanded {
  transform: rotate(90deg);
}

.vendor-details {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.vendor-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.vendor-text {
  display: flex;
  flex-direction: column;
}

.vendor-name {
  font-weight: 600;
  color: #1f2937;
}

.vendor-code {
  color: #6b7280;
  font-size: 0.8rem;
}

.total-amount {
  text-align: right;
}

.amount-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1f2937;
  display: block;
}

.payable-count {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.aging-amount {
  text-align: right;
}

.aging-amount .amount {
  font-weight: 600;
  display: block;
}

.aging-amount .percentage {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.aging-amount.current .amount { color: #10b981; }
.aging-amount.aging-30 .amount { color: #f59e0b; }
.aging-amount.aging-60 .amount { color: #ef4444; }
.aging-amount.overdue .amount { color: #991b1b; }

/* Payable Detail Row */
.payable-detail-row {
  background: white;
  border-left: 4px solid #e5e7eb;
}

.payable-detail-row:hover {
  background: #f9fafb;
  border-left-color: #6366f1;
}

.payable-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-left: 3rem;
}

.payable-id {
  font-weight: 600;
  color: #6366f1;
  font-family: monospace;
}

.invoice-number {
  color: #6b7280;
  font-size: 0.9rem;
}

.payable-amount {
  text-align: right;
}

.payable-amount .amount {
  font-weight: 600;
  color: #1f2937;
  display: block;
}

.due-date {
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

.due-date.current { color: #10b981; }
.due-date.past-due { color: #f59e0b; }
.due-date.overdue { color: #ef4444; }
.due-date.very-overdue { color: #dc2626; }
.due-date.severely-overdue { color: #991b1b; font-weight: 600; }

.aging-cell {
  text-align: right;
  color: #6b7280;
}

.aging-cell.has-amount {
  font-weight: 600;
  color: #1f2937;
}

/* Actions */
.actions {
  text-align: center;
}

.btn-action {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: none;
  background: #f3f4f6;
  color: #6b7280;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 0.25rem;
  transition: all 0.3s ease;
}

.btn-action:hover {
  background: #e5e7eb;
  color: #374151;
  transform: translateY(-1px);
}

/* Quick Actions Panel */
.quick-actions-panel {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 2rem;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.panel-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
}

.btn-toggle {
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  transition: transform 0.3s ease;
}

.btn-toggle .fas.rotated {
  transform: rotate(180deg);
}

.panel-content {
  padding: 1.5rem;
}

.action-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.action-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
}

.action-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
}

.action-card:nth-child(1) .action-icon { background: #ef4444; }
.action-card:nth-child(2) .action-icon { background: #f59e0b; }
.action-card:nth-child(3) .action-icon { background: #3b82f6; }

.action-content {
  flex: 1;
}

.action-content h4 {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
  color: #1f2937;
}

.action-content p {
  font-size: 0.8rem;
  color: #6b7280;
  margin-bottom: 0.75rem;
}

/* Pagination */
.pagination-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-numbers {
  display: flex;
  gap: 0.25rem;
}

/* Form Elements */
.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: border-color 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  font-size: 0.9rem;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.btn-outline {
  background: white;
  color: #6366f1;
  border: 1px solid #6366f1;
}

.btn-ghost {
  background: transparent;
  color: #6b7280;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-info {
  background: #3b82f6;
  color: white;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Modal */
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
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #6b7280;
  cursor: pointer;
}

.modal-body {
  padding: 1.5rem;
}

.vendor-summary {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
}

.vendor-summary h4 {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #1f2937;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
}

/* Loading and Empty States */
.loading-state,
.empty-state {
  text-align: center;
  padding: 3rem;
  color: #6b7280;
}

.loading-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.empty-state i {
  font-size: 3rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .summary-dashboard {
    grid-template-columns: 1fr;
  }
  
  .chart-section {
    order: -1;
  }
}

@media (max-width: 768px) {
  .aging-report-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-cards {
    grid-template-columns: 1fr;
  }
  
  .action-cards {
    grid-template-columns: 1fr;
  }
  
  .aging-table {
    min-width: 800px;
  }
  
  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
}

/* Print Styles */
@media print {
  .page-header,
  .filters-section,
  .quick-actions-panel {
    display: none;
  }
  
  .aging-report-container {
    padding: 0;
    background: white;
  }
  
  .summary-dashboard,
  .report-section {
    box-shadow: none;
    border: 1px solid #e5e7eb;
  }
}
</style>