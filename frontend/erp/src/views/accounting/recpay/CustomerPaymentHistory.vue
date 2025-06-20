<template>
  <div class="customer-payment-history-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <div class="breadcrumb">
            <router-link to="/accounting/receivable-payments" class="breadcrumb-link">
              <i class="fas fa-credit-card"></i>
              Receivable Payments
            </router-link>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">Payment History</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-history"></i>
            Customer Payment History
          </h1>
          <p class="page-subtitle">Comprehensive payment history and analytics for customers</p>
        </div>
        <div class="header-actions">
          <button @click="exportHistory" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export History
          </button>
          <router-link to="/accounting/receivable-payments/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Record Payment
          </router-link>
        </div>
      </div>
    </div>

    <!-- Customer Selection & Filters -->
    <div class="filters-section">
      <div class="customer-selection-card">
        <div class="card-header">
          <h3 class="card-title">
            <i class="fas fa-user-search"></i>
            Customer Selection
          </h3>
        </div>
        <div class="card-content">
          <div class="selection-grid">
            <div class="form-group">
              <label class="form-label">Select Customer</label>
              <select 
                v-model="selectedCustomerId" 
                @change="loadCustomerHistory"
                class="form-select"
                :disabled="loading"
              >
                <option value="">All Customers</option>
                <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                  {{ customer.name }} ({{ customer.customer_code }})
                </option>
              </select>
            </div>
            
            <div class="date-range-group">
              <div class="form-group">
                <label class="form-label">From Date</label>
                <input 
                  type="date" 
                  v-model="filters.fromDate"
                  class="form-input"
                />
              </div>
              <div class="form-group">
                <label class="form-label">To Date</label>
                <input 
                  type="date" 
                  v-model="filters.toDate"
                  class="form-input"
                />
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Payment Method</label>
              <select v-model="filters.paymentMethod" class="form-select">
                <option value="">All Methods</option>
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Check">Check</option>
                <option value="Wire Transfer">Wire Transfer</option>
                <option value="Online Payment">Online Payment</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Amount Range</label>
              <div class="amount-range">
                <input 
                  type="number" 
                  v-model="filters.minAmount"
                  class="form-input"
                  placeholder="Min Amount"
                  step="0.01"
                />
                <span class="range-separator">to</span>
                <input 
                  type="number" 
                  v-model="filters.maxAmount"
                  class="form-input"
                  placeholder="Max Amount"
                  step="0.01"
                />
              </div>
            </div>
            
            <div class="filter-actions">
              <button @click="applyFilters" class="btn btn-secondary">
                <i class="fas fa-search"></i>
                Apply Filters
              </button>
              <button @click="clearFilters" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Clear
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer Overview (when specific customer selected) -->
    <div v-if="selectedCustomer && customerSummary" class="customer-overview">
      <div class="overview-card">
        <div class="customer-header">
          <div class="customer-info">
            <div class="customer-avatar">
              <i class="fas fa-user-circle"></i>
            </div>
            <div class="customer-details">
              <h3 class="customer-name">{{ selectedCustomer.name }}</h3>
              <p class="customer-code">Customer Code: {{ selectedCustomer.customer_code }}</p>
              <div class="customer-contact">
                <span v-if="selectedCustomer.email" class="contact-item">
                  <i class="fas fa-envelope"></i>
                  {{ selectedCustomer.email }}
                </span>
                <span v-if="selectedCustomer.phone" class="contact-item">
                  <i class="fas fa-phone"></i>
                  {{ selectedCustomer.phone }}
                </span>
              </div>
            </div>
          </div>
          
          <div class="customer-actions">
            <router-link :to="`/customers/${selectedCustomer.customer_id}`" class="btn btn-sm btn-outline">
              <i class="fas fa-external-link-alt"></i>
              View Customer
            </router-link>
          </div>
        </div>
        
        <!-- Payment Statistics -->
        <div class="payment-stats">
          <div class="stat-item">
            <div class="stat-icon total">
              <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ formatCurrency(customerSummary.totalPayments) }}</div>
              <div class="stat-label">Total Payments</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon count">
              <i class="fas fa-hashtag"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ customerSummary.paymentCount }}</div>
              <div class="stat-label">Payment Count</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon average">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ formatCurrency(customerSummary.averagePayment) }}</div>
              <div class="stat-label">Average Payment</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon recent">
              <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ formatDate(customerSummary.lastPaymentDate) }}</div>
              <div class="stat-label">Last Payment</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon outstanding">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ formatCurrency(customerSummary.outstandingBalance) }}</div>
              <div class="stat-label">Outstanding Balance</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Analytics Charts -->
    <div v-if="selectedCustomer && paymentAnalytics" class="analytics-section">
      <div class="analytics-grid">
        <!-- Payment Trend Chart -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">
              <i class="fas fa-chart-area"></i>
              Payment Trend (Last 12 Months)
            </h3>
            <div class="chart-actions">
              <select v-model="chartPeriod" @change="updateCharts" class="chart-select">
                <option value="12">Last 12 Months</option>
                <option value="6">Last 6 Months</option>
                <option value="3">Last 3 Months</option>
              </select>
            </div>
          </div>
          <div class="chart-container">
            <canvas ref="trendChart" class="chart-canvas"></canvas>
          </div>
        </div>

        <!-- Payment Methods Distribution -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">
              <i class="fas fa-chart-pie"></i>
              Payment Methods Distribution
            </h3>
          </div>
          <div class="chart-container">
            <canvas ref="methodsChart" class="chart-canvas"></canvas>
          </div>
        </div>

        <!-- Payment Timing Analysis -->
        <div class="chart-card timing-card">
          <div class="chart-header">
            <h3 class="chart-title">
              <i class="fas fa-calendar-check"></i>
              Payment Timing Analysis
            </h3>
          </div>
          <div class="timing-content">
            <div class="timing-metric">
              <span class="timing-label">Average Days to Pay</span>
              <span class="timing-value">{{ paymentAnalytics.averageDaysToPay }} days</span>
            </div>
            <div class="timing-metric">
              <span class="timing-label">On-Time Payment Rate</span>
              <span class="timing-value success">{{ paymentAnalytics.onTimeRate }}%</span>
            </div>
            <div class="timing-metric">
              <span class="timing-label">Late Payment Rate</span>
              <span class="timing-value warning">{{ paymentAnalytics.lateRate }}%</span>
            </div>
            <div class="timing-metric">
              <span class="timing-label">Early Payment Rate</span>
              <span class="timing-value info">{{ paymentAnalytics.earlyRate }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment History Table -->
    <div class="history-table-section">
      <div class="table-card">
        <div class="table-header">
          <h3 class="table-title">
            <i class="fas fa-list"></i>
            Payment History
            <span v-if="selectedCustomer" class="record-count">({{ payments.length }} records)</span>
          </h3>
          <div class="table-actions">
            <div class="view-options">
              <button 
                @click="viewMode = 'table'"
                :class="['view-btn', { active: viewMode === 'table' }]"
              >
                <i class="fas fa-table"></i>
                Table
              </button>
              <button 
                @click="viewMode = 'timeline'"
                :class="['view-btn', { active: viewMode === 'timeline' }]"
              >
                <i class="fas fa-project-diagram"></i>
                Timeline
              </button>
            </div>
            <button @click="refreshData" class="btn btn-sm btn-outline" :disabled="loading">
              <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
              Refresh
            </button>
          </div>
        </div>

        <!-- Table View -->
        <div v-if="viewMode === 'table'" class="table-container">
          <div v-if="!loading && payments.length > 0" class="payments-table-wrapper">
            <table class="payments-table">
              <thead>
                <tr>
                  <th @click="sortBy('payment_id')" class="sortable">
                    Payment ID
                    <i class="fas fa-sort" v-if="sortField !== 'payment_id'"></i>
                    <i class="fas fa-sort-up" v-if="sortField === 'payment_id' && sortDirection === 'asc'"></i>
                    <i class="fas fa-sort-down" v-if="sortField === 'payment_id' && sortDirection === 'desc'"></i>
                  </th>
                  <th v-if="!selectedCustomer">Customer</th>
                  <th @click="sortBy('payment_date')" class="sortable">
                    Date
                    <i class="fas fa-sort" v-if="sortField !== 'payment_date'"></i>
                    <i class="fas fa-sort-up" v-if="sortField === 'payment_date' && sortDirection === 'asc'"></i>
                    <i class="fas fa-sort-down" v-if="sortField === 'payment_date' && sortDirection === 'desc'"></i>
                  </th>
                  <th>Invoice</th>
                  <th @click="sortBy('amount')" class="sortable">
                    Amount
                    <i class="fas fa-sort" v-if="sortField !== 'amount'"></i>
                    <i class="fas fa-sort-up" v-if="sortField === 'amount' && sortDirection === 'asc'"></i>
                    <i class="fas fa-sort-down" v-if="sortField === 'amount' && sortDirection === 'desc'"></i>
                  </th>
                  <th>Method</th>
                  <th>Reference</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in sortedPayments" :key="payment.payment_id" class="payment-row">
                  <td class="payment-id-cell">
                    <span class="payment-id-badge">#{{ payment.payment_id }}</span>
                  </td>
                  <td v-if="!selectedCustomer" class="customer-cell">
                    <div class="customer-info">
                      <span class="customer-name">{{ payment.customer_receivable?.customer?.name || 'Unknown' }}</span>
                      <span class="customer-code">{{ payment.customer_receivable?.customer?.customer_code || '' }}</span>
                    </div>
                  </td>
                  <td class="date-cell">
                    <div class="date-info">
                      <span class="payment-date">{{ formatDate(payment.payment_date) }}</span>
                      <span class="payment-time">{{ formatTime(payment.created_at) }}</span>
                    </div>
                  </td>
                  <td class="invoice-cell">
                    <span class="invoice-number">#{{ payment.customer_receivable?.invoice_id || '-' }}</span>
                  </td>
                  <td class="amount-cell">
                    <div class="amount-info">
                      <span class="amount-value">{{ formatCurrency(payment.amount) }}</span>
                      <span class="currency-code">{{ payment.currency }}</span>
                    </div>
                  </td>
                  <td class="method-cell">
                    <span class="method-badge" :class="getMethodClass(payment.payment_method)">
                      <i :class="getMethodIcon(payment.payment_method)"></i>
                      {{ payment.payment_method }}
                    </span>
                  </td>
                  <td class="reference-cell">
                    <span class="reference-text">{{ payment.reference_number || '-' }}</span>
                  </td>
                  <td class="status-cell">
                    <span class="status-badge status-completed">
                      <i class="fas fa-check-circle"></i>
                      Completed
                    </span>
                  </td>
                  <td class="actions-cell">
                    <div class="action-buttons">
                      <button 
                        @click="viewPaymentDetail(payment.payment_id)"
                        class="action-btn view-btn"
                        title="View Details"
                      >
                        <i class="fas fa-eye"></i>
                      </button>
                      <button 
                        @click="printPaymentReceipt(payment)"
                        class="action-btn print-btn"
                        title="Print Receipt"
                      >
                        <i class="fas fa-print"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Timeline View -->
          <div v-if="viewMode === 'timeline'" class="timeline-container">
            <div class="timeline-wrapper">
              <div v-for="(group, date) in groupedByDate" :key="date" class="timeline-group">
                <div class="timeline-date">
                  <span class="date-label">{{ formatDateFull(date) }}</span>
                  <span class="payments-count">{{ group.length }} payment(s)</span>
                </div>
                <div class="timeline-payments">
                  <div v-for="payment in group" :key="payment.payment_id" class="timeline-payment">
                    <div class="timeline-marker">
                      <i :class="getMethodIcon(payment.payment_method)"></i>
                    </div>
                    <div class="timeline-content">
                      <div class="payment-header">
                        <span class="payment-id">#{{ payment.payment_id }}</span>
                        <span class="payment-amount">{{ formatCurrency(payment.amount) }}</span>
                      </div>
                      <div class="payment-details">
                        <span class="payment-method">{{ payment.payment_method }}</span>
                        <span v-if="payment.customer_receivable?.invoice_id" class="invoice-link">
                          Invoice #{{ payment.customer_receivable.invoice_id }}
                        </span>
                        <span v-if="payment.reference_number" class="reference">
                          Ref: {{ payment.reference_number }}
                        </span>
                      </div>
                      <div class="payment-actions">
                        <button @click="viewPaymentDetail(payment.payment_id)" class="timeline-action-btn">
                          <i class="fas fa-eye"></i>
                          View Details
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!loading && payments.length === 0" class="empty-state">
            <div class="empty-icon">
              <i class="fas fa-receipt"></i>
            </div>
            <h3 class="empty-title">No Payment History Found</h3>
            <p class="empty-description">
              {{ selectedCustomer 
                ? 'This customer has no payment history for the selected period.' 
                : 'No payments found matching your criteria.' }}
            </p>
            <div class="empty-actions">
              <button @click="clearFilters" class="btn btn-outline">
                <i class="fas fa-filter"></i>
                Clear Filters
              </button>
              <router-link v-if="selectedCustomer" to="/accounting/receivable-payments/create" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Record Payment
              </router-link>
            </div>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading payment history...</p>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > 0" class="pagination-container">
          <div class="pagination-info">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} payments
          </div>
          <div class="pagination-controls">
            <button 
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="pagination-btn"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            
            <template v-for="page in getPageNumbers()" :key="page">
              <button 
                v-if="page !== '...'"
                @click="changePage(page)"
                :class="['pagination-btn', { active: page === pagination.current_page }]"
              >
                {{ page }}
              </button>
              <span v-else class="pagination-ellipsis">...</span>
            </template>
            
            <button 
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="pagination-btn"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'CustomerPaymentHistory',
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const customers = ref([])
    const payments = ref([])
    const selectedCustomerId = ref('')
    const selectedCustomer = ref(null)
    const customerSummary = ref(null)
    const paymentAnalytics = ref(null)
    const viewMode = ref('table')
    const chartPeriod = ref('12')
    
    const filters = reactive({
      fromDate: '',
      toDate: '',
      paymentMethod: '',
      minAmount: '',
      maxAmount: ''
    })
    
    const sortField = ref('payment_date')
    const sortDirection = ref('desc')
    
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: 0,
      to: 0
    })

    const sortedPayments = computed(() => {
      const sorted = [...payments.value].sort((a, b) => {
        let aValue = a[sortField.value]
        let bValue = b[sortField.value]
        
        if (sortField.value === 'amount') {
          aValue = parseFloat(aValue || 0)
          bValue = parseFloat(bValue || 0)
        } else if (sortField.value === 'payment_date') {
          aValue = new Date(aValue)
          bValue = new Date(bValue)
        }
        
        if (sortDirection.value === 'asc') {
          return aValue > bValue ? 1 : -1
        } else {
          return aValue < bValue ? 1 : -1
        }
      })
      
      return sorted
    })

    const groupedByDate = computed(() => {
      const grouped = {}
      
      sortedPayments.value.forEach(payment => {
        const date = payment.payment_date
        if (!grouped[date]) {
          grouped[date] = []
        }
        grouped[date].push(payment)
      })
      
      return grouped
    })

    const fetchCustomers = async () => {
      try {
        const response = await axios.get('/customers')
        customers.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching customers:', error)
      }
    }

    const loadCustomerHistory = async (page = 1) => {
      try {
        loading.value = true
        
        if (selectedCustomerId.value) {
          selectedCustomer.value = customers.value.find(c => c.customer_id == selectedCustomerId.value)
        } else {
          selectedCustomer.value = null
        }
        
        const params = {
          page,
          per_page: pagination.value.per_page,
          ...filters
        }
        
        if (selectedCustomerId.value) {
          params.customer_id = selectedCustomerId.value
        }
        
        // Remove empty filters
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null) {
            delete params[key]
          }
        })
        
        const response = await axios.get('/accounting/receivable-payments', { params })
        payments.value = response.data.data || []
        
        pagination.value = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          per_page: response.data.per_page || 20,
          total: response.data.total || 0,
          from: response.data.from || 0,
          to: response.data.to || 0
        }
        
        if (selectedCustomerId.value) {
          await loadCustomerSummary()
          await loadPaymentAnalytics()
        } else {
          customerSummary.value = null
          paymentAnalytics.value = null
        }
        
      } catch (error) {
        console.error('Error loading payment history:', error)
      } finally {
        loading.value = false
      }
    }

    const loadCustomerSummary = async () => {
      try {
        const response = await axios.get(`/customers/${selectedCustomerId.value}/payment-summary`)
        customerSummary.value = response.data
      } catch (error) {
        console.error('Error loading customer summary:', error)
        // Calculate summary from current data
        calculateSummaryFromData()
      }
    }

    const calculateSummaryFromData = () => {
      const totalPayments = payments.value.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0)
      const paymentCount = payments.value.length
      const averagePayment = paymentCount > 0 ? totalPayments / paymentCount : 0
      const lastPaymentDate = payments.value.length > 0 ? payments.value[0].payment_date : null
      
      customerSummary.value = {
        totalPayments,
        paymentCount,
        averagePayment,
        lastPaymentDate,
        outstandingBalance: 0 // Would need to fetch from receivables
      }
    }

    const loadPaymentAnalytics = async () => {
      try {
        const response = await axios.get(`/customers/${selectedCustomerId.value}/payment-analytics`, {
          params: { period: chartPeriod.value }
        })
        paymentAnalytics.value = response.data
      } catch (error) {
        console.error('Error loading payment analytics:', error)
        // Generate mock analytics
        generateMockAnalytics()
      }
    }

    const generateMockAnalytics = () => {
      paymentAnalytics.value = {
        averageDaysToPay: 28,
        onTimeRate: 75,
        lateRate: 20,
        earlyRate: 5,
        monthlyTrends: [],
        methodDistribution: {}
      }
    }

    const applyFilters = () => {
      loadCustomerHistory(1)
    }

    const clearFilters = () => {
      Object.keys(filters).forEach(key => {
        filters[key] = ''
      })
      selectedCustomerId.value = ''
      loadCustomerHistory(1)
    }

    const refreshData = () => {
      loadCustomerHistory(pagination.value.current_page)
    }

    const sortBy = (field) => {
      if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
      } else {
        sortField.value = field
        sortDirection.value = 'desc'
      }
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        loadCustomerHistory(page)
      }
    }

    const getPageNumbers = () => {
      const current = pagination.value.current_page
      const last = pagination.value.last_page
      const pages = []
      
      if (last <= 7) {
        for (let i = 1; i <= last; i++) {
          pages.push(i)
        }
      } else {
        if (current <= 4) {
          for (let i = 1; i <= 5; i++) pages.push(i)
          pages.push('...')
          pages.push(last)
        } else if (current >= last - 3) {
          pages.push(1)
          pages.push('...')
          for (let i = last - 4; i <= last; i++) pages.push(i)
        } else {
          pages.push(1)
          pages.push('...')
          for (let i = current - 1; i <= current + 1; i++) pages.push(i)
          pages.push('...')
          pages.push(last)
        }
      }
      
      return pages
    }

    const viewPaymentDetail = (paymentId) => {
      router.push(`/accounting/receivable-payments/${paymentId}`)
    }

    const printPaymentReceipt = (payment) => {
      // Implement print receipt functionality
      console.log('Print receipt for payment:', payment.payment_id)
    }

    const exportHistory = () => {
      // Implement export functionality
      console.log('Export payment history')
    }

    const updateCharts = () => {
      if (selectedCustomerId.value) {
        loadPaymentAnalytics()
      }
    }

    const getMethodClass = (method) => {
      const methodClasses = {
        'Cash': 'method-cash',
        'Bank Transfer': 'method-transfer',
        'Credit Card': 'method-card',
        'Check': 'method-check',
        'Wire Transfer': 'method-wire',
        'Online Payment': 'method-online'
      }
      return methodClasses[method] || 'method-default'
    }

    const getMethodIcon = (method) => {
      const methodIcons = {
        'Cash': 'fas fa-money-bill',
        'Bank Transfer': 'fas fa-exchange-alt',
        'Credit Card': 'fas fa-credit-card',
        'Check': 'fas fa-money-check',
        'Wire Transfer': 'fas fa-wire',
        'Online Payment': 'fas fa-globe'
      }
      return methodIcons[method] || 'fas fa-payment'
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }

    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }

    const formatDateFull = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }

    const formatTime = (datetime) => {
      return new Date(datetime).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    // Set default date range (last 3 months)
    const setDefaultDateRange = () => {
      const today = new Date()
      const threeMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate())
      
      filters.fromDate = threeMonthsAgo.toISOString().split('T')[0]
      filters.toDate = today.toISOString().split('T')[0]
    }

    onMounted(() => {
      fetchCustomers()
      setDefaultDateRange()
      loadCustomerHistory()
    })

    return {
      loading,
      customers,
      payments,
      selectedCustomerId,
      selectedCustomer,
      customerSummary,
      paymentAnalytics,
      viewMode,
      chartPeriod,
      filters,
      sortField,
      sortDirection,
      pagination,
      sortedPayments,
      groupedByDate,
      loadCustomerHistory,
      applyFilters,
      clearFilters,
      refreshData,
      sortBy,
      changePage,
      getPageNumbers,
      viewPaymentDetail,
      printPaymentReceipt,
      exportHistory,
      updateCharts,
      getMethodClass,
      getMethodIcon,
      formatCurrency,
      formatDate,
      formatDateFull,
      formatTime
    }
  }
}
</script>

<style scoped>
.customer-payment-history-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  padding: 2rem;
}

.page-header {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.title-section {
  flex: 1;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.breadcrumb-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6366f1;
  text-decoration: none;
  transition: all 0.3s ease;
}

.breadcrumb-link:hover {
  color: #4f46e5;
}

.breadcrumb-separator {
  color: #94a3b8;
}

.breadcrumb-current {
  color: #64748b;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title i {
  color: #6366f1;
  font-size: 2rem;
}

.page-subtitle {
  color: #64748b;
  font-size: 1.125rem;
  font-weight: 400;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
  background: linear-gradient(135deg, #64748b 0%, #475569 100%);
  color: white;
}

.btn-outline {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.btn-outline:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.filters-section {
  margin-bottom: 2rem;
}

.customer-selection-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.card-title i {
  color: #6366f1;
}

.card-content {
  padding: 1.5rem;
}

.selection-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  align-items: end;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  font-weight: 600;
  color: #374151;
  font-size: 0.95rem;
}

.form-select,
.form-input {
  padding: 0.875rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.form-select:focus,
.form-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.date-range-group {
  display: flex;
  gap: 1rem;
}

.amount-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.range-separator {
  color: #64748b;
  font-weight: 500;
}

.filter-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.customer-overview {
  margin-bottom: 2rem;
}

.overview-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.customer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.customer-info {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.customer-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem;
}

.customer-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.customer-name {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.customer-code {
  color: #64748b;
  font-size: 0.875rem;
}

.customer-contact {
  display: flex;
  gap: 1rem;
  margin-top: 0.25rem;
}

.contact-item {
  color: #64748b;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.payment-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  transition: all 0.3s ease;
}

.stat-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.stat-icon.total {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
}

.stat-icon.count {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.stat-icon.average {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-icon.recent {
  background: linear-gradient(135deg, #64748b 0%, #475569 100%);
}

.stat-icon.outstanding {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.stat-label {
  color: #64748b;
  font-size: 0.875rem;
}

.analytics-section {
  margin-bottom: 2rem;
}

.analytics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 2rem;
}

.chart-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.chart-title {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.chart-title i {
  color: #6366f1;
}

.chart-select {
  padding: 0.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  background: white;
}

.chart-container {
  padding: 1.5rem;
  height: 300px;
}

.chart-canvas {
  width: 100% !important;
  height: 100% !important;
}

.timing-card {
  grid-column: 1 / -1;
}

.timing-content {
  padding: 1.5rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.timing-metric {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.timing-label {
  color: #64748b;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.timing-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
}

.timing-value.success {
  color: #059669;
}

.timing-value.warning {
  color: #f59e0b;
}

.timing-value.info {
  color: #6366f1;
}

.history-table-section {
  margin-bottom: 2rem;
}

.table-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  flex-wrap: wrap;
  gap: 1rem;
}

.table-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.table-title i {
  color: #6366f1;
}

.record-count {
  color: #64748b;
  font-weight: 400;
  font-size: 0.875rem;
}

.table-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.view-options {
  display: flex;
  gap: 0.25rem;
  background: #f1f5f9;
  border-radius: 8px;
  padding: 0.25rem;
}

.view-btn {
  padding: 0.5rem 1rem;
  border: none;
  background: transparent;
  color: #64748b;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.view-btn.active {
  background: white;
  color: #6366f1;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.table-container {
  overflow-x: auto;
}

.payments-table-wrapper {
  min-width: 100%;
}

.payments-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.payments-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
  border-bottom: 1px solid #e2e8f0;
}

.payments-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: all 0.3s ease;
}

.payments-table th.sortable:hover {
  background: #f1f5f9;
}

.payments-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.payment-row {
  transition: all 0.3s ease;
}

.payment-row:hover {
  background: #f8fafc;
}

.payment-id-badge {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.customer-name {
  font-weight: 600;
  color: #1e293b;
}

.customer-code {
  font-size: 0.75rem;
  color: #64748b;
}

.date-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.payment-date {
  font-weight: 600;
  color: #1e293b;
}

.payment-time {
  font-size: 0.75rem;
  color: #64748b;
}

.invoice-number {
  font-family: monospace;
  background: #6366f1;
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.amount-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.amount-value {
  font-weight: 700;
  color: #059669;
  font-size: 1.1rem;
}

.currency-code {
  font-size: 0.75rem;
  color: #64748b;
}

.method-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
}

.method-cash {
  background: #dcfdf7;
  color: #059669;
}

.method-transfer {
  background: #dbeafe;
  color: #2563eb;
}

.method-card {
  background: #fef3c7;
  color: #d97706;
}

.method-check {
  background: #f3e8ff;
  color: #8b5cf6;
}

.method-wire {
  background: #fecaca;
  color: #dc2626;
}

.method-online {
  background: #dcfce7;
  color: #16a34a;
}

.method-default {
  background: #f1f5f9;
  color: #64748b;
}

.reference-text {
  font-family: monospace;
  color: #64748b;
  font-size: 0.875rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-completed {
  background: #dcfdf7;
  color: #059669;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.action-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.875rem;
}

.view-btn {
  background: #dbeafe;
  color: #2563eb;
}

.view-btn:hover {
  background: #2563eb;
  color: white;
}

.print-btn {
  background: #f3e8ff;
  color: #8b5cf6;
}

.print-btn:hover {
  background: #8b5cf6;
  color: white;
}

.timeline-container {
  padding: 1.5rem;
}

.timeline-wrapper {
  position: relative;
  padding-left: 2rem;
}

.timeline-wrapper::before {
  content: '';
  position: absolute;
  left: 1rem;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}

.timeline-group {
  margin-bottom: 2rem;
  position: relative;
}

.timeline-date {
  position: sticky;
  top: 0;
  background: white;
  padding: 1rem 0;
  margin-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.date-label {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1e293b;
}

.payments-count {
  font-size: 0.875rem;
  color: #64748b;
  background: #f1f5f9;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
}

.timeline-payments {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.timeline-payment {
  position: relative;
  display: flex;
  gap: 1rem;
  padding-left: 1rem;
}

.timeline-marker {
  position: absolute;
  left: -2rem;
  top: 0;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: white;
  border: 3px solid #6366f1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6366f1;
  font-size: 0.875rem;
}

.timeline-content {
  flex: 1;
  background: #f8fafc;
  border-radius: 12px;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.payment-id {
  font-weight: 600;
  color: #6366f1;
}

.payment-amount {
  font-weight: 700;
  color: #059669;
  font-size: 1.1rem;
}

.payment-details {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}

.payment-method,
.invoice-link,
.reference {
  font-size: 0.875rem;
  color: #64748b;
}

.invoice-link {
  color: #6366f1;
  cursor: pointer;
}

.timeline-action-btn {
  background: #6366f1;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.timeline-action-btn:hover {
  background: #4f46e5;
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
}

.empty-icon {
  font-size: 4rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-description {
  color: #6b7280;
  margin-bottom: 2rem;
}

.empty-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.loading-state {
  text-align: center;
  padding: 4rem 2rem;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f4f6;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  color: #64748b;
  font-size: 1.1rem;
}

.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
}

.pagination-info {
  color: #64748b;
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.pagination-btn {
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.875rem;
}

.pagination-btn:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.pagination-btn.active {
  background: #6366f1;
  color: white;
  border-color: #6366f1;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-ellipsis {
  padding: 0.5rem;
  color: #64748b;
}

@media (max-width: 768px) {
  .customer-payment-history-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
  
  .selection-grid {
    grid-template-columns: 1fr;
  }
  
  .date-range-group {
    flex-direction: column;
  }
  
  .amount-range {
    flex-direction: column;
    align-items: stretch;
  }
  
  .customer-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .customer-info {
    justify-content: center;
  }
  
  .payment-stats {
    grid-template-columns: 1fr;
  }
  
  .analytics-grid {
    grid-template-columns: 1fr;
  }
  
  .timing-content {
    grid-template-columns: 1fr;
  }
  
  .table-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .table-actions {
    justify-content: center;
  }
  
  .pagination-container {
    flex-direction: column;
    gap: 1rem;
  }
  
  .timeline-wrapper {
    padding-left: 1rem;
  }
  
  .timeline-wrapper::before {
    left: 0.5rem;
  }
  
  .timeline-marker {
    left: -1.5rem;
    width: 1.5rem;
    height: 1.5rem;
  }
  
  .payment-details {
    flex-direction: column;
    gap: 0.5rem;
  }
}
</style>