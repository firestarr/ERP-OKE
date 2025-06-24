<template>
  <div class="payments-list-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-credit-card"></i>
            Receivable Payments
          </h1>
          <p class="page-subtitle">Manage and track customer payments</p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/receivable-payments/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Record Payment
          </router-link>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-card">
      <div class="filters-content">
        <div class="filter-row">
          <div class="filter-group">
            <label class="filter-label">Date Range</label>
            <div class="date-range-inputs">
              <input
                type="date"
                v-model="filters.fromDate"
                class="date-input"
                placeholder="From Date"
              />
              <span class="date-separator">to</span>
              <input
                type="date"
                v-model="filters.toDate"
                class="date-input"
                placeholder="To Date"
              />
            </div>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Customer</label>
            <select v-model="filters.customerId" class="filter-select">
              <option value="">All Customers</option>
              <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                {{ customer.name }}
              </option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Currency</label>
            <select v-model="filters.currency" class="filter-select">
              <option value="">All Currencies</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
              <option value="IDR">IDR</option>
            </select>
          </div>
          
          <div class="filter-actions">
            <button @click="applyFilters" class="btn btn-secondary">
              <i class="fas fa-search"></i>
              Filter
            </button>
            <button @click="clearFilters" class="btn btn-outline">
              <i class="fas fa-times"></i>
              Clear
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ formatCurrency(stats.totalPayments) }}</div>
          <div class="stat-label">Total Payments</div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.paymentsCount }}</div>
          <div class="stat-label">Total Records</div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ formatCurrency(stats.todayPayments) }}</div>
          <div class="stat-label">Today's Payments</div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.uniqueCustomers }}</div>
          <div class="stat-label">Customers</div>
        </div>
      </div>
    </div>

    <!-- Payments Table -->
    <div class="table-card">
      <div class="table-header">
        <h3 class="table-title">Payment Records</h3>
        <div class="table-actions">
          <button @click="exportPayments" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export
          </button>
        </div>
      </div>
      
      <div class="table-container" v-if="!loading">
        <table class="payments-table">
          <thead>
            <tr>
              <th>Payment ID</th>
              <th>Customer</th>
              <th>Payment Date</th>
              <th>Amount</th>
              <th>Currency</th>
              <th>Method</th>
              <th>Reference</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in payments" :key="payment.payment_id" class="table-row">
              <td class="payment-id">
                <span class="id-badge">#{{ payment.payment_id }}</span>
              </td>
              <td class="customer-info">
                <div class="customer-details">
                  <div class="customer-name">{{ payment.customer_receivable?.customer?.name || 'Unknown' }}</div>
                  <div class="customer-code">{{ payment.customer_receivable?.customer?.customer_code || '' }}</div>
                </div>
              </td>
              <td class="payment-date">
                <div class="date-wrapper">
                  <i class="fas fa-calendar"></i>
                  {{ formatDate(payment.payment_date) }}
                </div>
              </td>
              <td class="amount">
                <div class="amount-wrapper">
                  <span class="amount-value">{{ formatCurrency(payment.amount) }}</span>
                  <span v-if="payment.base_currency_amount && payment.base_currency_amount !== payment.amount" 
                        class="base-amount">
                    ({{ formatCurrency(payment.base_currency_amount) }} {{ baseCurrency }})
                  </span>
                </div>
              </td>
              <td class="currency">
                <span class="currency-badge">{{ payment.currency }}</span>
              </td>
              <td class="method">
                <span class="method-badge" :class="getMethodClass(payment.payment_method)">
                  <i :class="getMethodIcon(payment.payment_method)"></i>
                  {{ payment.payment_method }}
                </span>
              </td>
              <td class="reference">
                <span class="reference-text">{{ payment.reference_number || '-' }}</span>
              </td>
              <td class="status">
                <span class="status-badge status-completed">
                  <i class="fas fa-check-circle"></i>
                  Completed
                </span>
              </td>
              <td class="actions">
                <div class="action-buttons">
                  <button 
                    @click="viewPayment(payment.payment_id)" 
                    class="action-btn view-btn"
                    title="View Details"
                  >
                    <i class="fas fa-eye"></i>
                  </button>
                  <button 
                    @click="deletePayment(payment.payment_id)" 
                    class="action-btn delete-btn"
                    title="Delete Payment"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Empty State -->
        <div v-if="payments.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-receipt"></i>
          </div>
          <h3 class="empty-title">No Payments Found</h3>
          <p class="empty-description">There are no payment records matching your criteria.</p>
          <router-link to="/accounting/receivable-payments/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Record First Payment
          </router-link>
        </div>
      </div>
      
      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading payments...</p>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-container" v-if="pagination.total > 0">
      <div class="pagination-info">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} entries
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

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3 class="modal-title">Confirm Delete</h3>
          <button @click="closeDeleteModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <p>Are you sure you want to delete this payment record? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button @click="closeDeleteModal" class="btn btn-outline">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger">
            <i class="fas fa-trash"></i>
            Delete Payment
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted} from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'PaymentsList',
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const payments = ref([])
    const customers = ref([])
    const pagination = ref({})
    const stats = ref({
      totalPayments: 0,
      paymentsCount: 0,
      todayPayments: 0,
      uniqueCustomers: 0
    })
    
    const filters = reactive({
      fromDate: '',
      toDate: '',
      customerId: '',
      currency: ''
    })
    
    const showDeleteModal = ref(false)
    const deletePaymentId = ref(null)
    const baseCurrency = ref('USD')

    const fetchPayments = async (page = 1) => {
      try {
        loading.value = true
        const params = {
          page,
          per_page: 15,
          ...filters
        }
        
        // Remove empty filters
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null) {
            delete params[key]
          }
        })
        
        const response = await axios.get('/accounting/receivable-payments', { params })
        payments.value = response.data.data
        pagination.value = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to
        }
        
        calculateStats()
      } catch (error) {
        console.error('Error fetching payments:', error)
        // Handle error
      } finally {
        loading.value = false
      }
    }

    const fetchCustomers = async () => {
      try {
        const response = await axios.get('/customers')
        customers.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching customers:', error)
      }
    }

    const calculateStats = () => {
      const total = payments.value.reduce((sum, payment) => sum + parseFloat(payment.amount || 0), 0)
      const today = new Date().toISOString().split('T')[0]
      const todayTotal = payments.value
        .filter(payment => payment.payment_date === today)
        .reduce((sum, payment) => sum + parseFloat(payment.amount || 0), 0)
      
      const uniqueCustomerIds = new Set(
        payments.value.map(payment => payment.customer_receivable?.customer?.customer_id)
      )
      
      stats.value = {
        totalPayments: total,
        paymentsCount: payments.value.length,
        todayPayments: todayTotal,
        uniqueCustomers: uniqueCustomerIds.size
      }
    }

    const applyFilters = () => {
      fetchPayments(1)
    }

    const clearFilters = () => {
      Object.keys(filters).forEach(key => {
        filters[key] = ''
      })
      fetchPayments(1)
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        fetchPayments(page)
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

    const viewPayment = (paymentId) => {
      router.push(`/accounting/receivable-payments/${paymentId}`)
    }

    const deletePayment = (paymentId) => {
      deletePaymentId.value = paymentId
      showDeleteModal.value = true
    }

    const confirmDelete = async () => {
      try {
        await axios.delete(`/accounting/receivable-payments/${deletePaymentId.value}`)
        showDeleteModal.value = false
        fetchPayments(pagination.value.current_page)
        // Show success message
      } catch (error) {
        console.error('Error deleting payment:', error)
        // Show error message
      }
    }

    const closeDeleteModal = () => {
      showDeleteModal.value = false
      deletePaymentId.value = null
    }

    const exportPayments = () => {
      // Implement export functionality
      console.log('Export payments')
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

    const getMethodClass = (method) => {
      const methodClasses = {
        'Cash': 'method-cash',
        'Bank Transfer': 'method-transfer',
        'Credit Card': 'method-card',
        'Check': 'method-check'
      }
      return methodClasses[method] || 'method-default'
    }

    const getMethodIcon = (method) => {
      const methodIcons = {
        'Cash': 'fas fa-money-bill',
        'Bank Transfer': 'fas fa-exchange-alt',
        'Credit Card': 'fas fa-credit-card',
        'Check': 'fas fa-money-check'
      }
      return methodIcons[method] || 'fas fa-payment'
    }

    onMounted(() => {
      fetchPayments()
      fetchCustomers()
    })

    return {
      loading,
      payments,
      customers,
      pagination,
      stats,
      filters,
      showDeleteModal,
      deletePaymentId,
      baseCurrency,
      fetchPayments,
      applyFilters,
      clearFilters,
      changePage,
      getPageNumbers,
      viewPayment,
      deletePayment,
      confirmDelete,
      closeDeleteModal,
      exportPayments,
      formatCurrency,
      formatDate,
      getMethodClass,
      getMethodIcon
    }
  }
}
</script>

<style scoped>
.payments-list-container {
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
  align-items: center;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.title-section {
  flex: 1;
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

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
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

.btn-outline:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.filters-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
}

.filter-row {
  display: flex;
  align-items: end;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 180px;
}

.filter-label {
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.date-range-inputs {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-input,
.filter-select {
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.date-input:focus,
.filter-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.date-separator {
  color: #64748b;
  font-weight: 500;
}

.filter-actions {
  display: flex;
  gap: 0.75rem;
  align-items: end;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 1.875rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1;
}

.stat-label {
  color: #64748b;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.table-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  border: 1px solid #f1f5f9;
  margin-bottom: 2rem;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.table-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.table-container {
  overflow-x: auto;
}

.payments-table {
  width: 100%;
  border-collapse: collapse;
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

.payments-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.table-row {
  transition: all 0.3s ease;
}

.table-row:hover {
  background: #f8fafc;
}

.id-badge {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
}

.customer-details {
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

.date-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #64748b;
}

.amount-wrapper {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.amount-value {
  font-weight: 600;
  color: #059669;
  font-size: 1.1rem;
}

.base-amount {
  font-size: 0.75rem;
  color: #64748b;
}

.currency-badge {
  background: #f1f5f9;
  color: #64748b;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
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

.method-default {
  background: #f1f5f9;
  color: #64748b;
}

.reference-text {
  color: #64748b;
  font-family: monospace;
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

.delete-btn {
  background: #fecaca;
  color: #dc2626;
}

.delete-btn:hover {
  background: #dc2626;
  color: white;
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

.loading-state {
  text-align: center;
  padding: 4rem 2rem;
}

.loading-spinner {
  width: 40px;
  height: 40px;
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

.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
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
  max-width: 400px;
  width: 100%;
  margin: 1rem;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 0.25rem;
}

.modal-body {
  padding: 1.5rem;
  text-align: center;
}

.warning-icon {
  font-size: 3rem;
  color: #f59e0b;
  margin-bottom: 1rem;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #f1f5f9;
}

@media (max-width: 768px) {
  .payments-list-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
  
  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .pagination-container {
    flex-direction: column;
    gap: 1rem;
  }
  
  .table-container {
    font-size: 0.875rem;
  }
}
</style>