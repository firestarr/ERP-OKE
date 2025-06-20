<template>
  <AppLayout>
    <div class="tax-transactions-container">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-receipt"></i>
              Tax Transactions
            </h1>
            <p class="page-description">Manage and monitor all tax-related transactions</p>
          </div>
          <div class="header-actions">
            <button @click="exportData" class="btn btn-outline">
              <i class="fas fa-download"></i>
              Export
            </button>
            <router-link to="/tax-transactions/create" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              New Tax Transaction
            </router-link>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-content">
            <h3>{{ statistics.completed || 0 }}</h3>
            <p>Completed</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-content">
            <h3>{{ statistics.pending || 0 }}</h3>
            <p>Pending</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon info">
            <i class="fas fa-dollar-sign"></i>
          </div>
          <div class="stat-content">
            <h3>${{ formatCurrency(statistics.totalAmount || 0) }}</h3>
            <p>Total Amount</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon primary">
            <i class="fas fa-file-invoice"></i>
          </div>
          <div class="stat-content">
            <h3>{{ statistics.thisMonth || 0 }}</h3>
            <p>This Month</p>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="filters-card">
        <div class="filters-header">
          <h3>
            <i class="fas fa-filter"></i>
            Filters
          </h3>
          <button @click="clearFilters" class="btn btn-text">
            <i class="fas fa-times"></i>
            Clear All
          </button>
        </div>
        <div class="filters-grid">
          <div class="filter-group">
            <label>Tax Type</label>
            <select v-model="filters.tax_type" @change="applyFilters" class="form-select">
              <option value="">All Types</option>
              <option value="VAT">VAT</option>
              <option value="Income Tax">Income Tax</option>
              <option value="Corporate Tax">Corporate Tax</option>
              <option value="Sales Tax">Sales Tax</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Tax Code</label>
            <input 
              v-model="filters.tax_code" 
              @input="applyFilters"
              type="text" 
              placeholder="Enter tax code"
              class="form-input"
            >
          </div>
          <div class="filter-group">
            <label>Status</label>
            <select v-model="filters.status" @change="applyFilters" class="form-select">
              <option value="">All Status</option>
              <option value="Draft">Draft</option>
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          <div class="filter-group">
            <label>From Date</label>
            <input 
              v-model="filters.from_date" 
              @change="applyFilters"
              type="date" 
              class="form-input"
            >
          </div>
          <div class="filter-group">
            <label>To Date</label>
            <input 
              v-model="filters.to_date" 
              @change="applyFilters"
              type="date" 
              class="form-input"
            >
          </div>
          <div class="filter-group">
            <label>Search</label>
            <div class="search-wrapper">
              <i class="fas fa-search search-icon"></i>
              <input 
                v-model="searchQuery" 
                @input="debouncedSearch"
                type="text" 
                placeholder="Search transactions..."
                class="form-input search-input"
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Transactions Table -->
      <div class="table-card">
        <div class="table-header">
          <h3>
            <i class="fas fa-list"></i>
            Transactions List
            <span class="record-count">({{ pagination.total || 0 }} records)</span>
          </h3>
          <div class="table-actions">
            <select v-model="perPage" @change="fetchTransactions" class="form-select small">
              <option value="15">15 per page</option>
              <option value="25">25 per page</option>
              <option value="50">50 per page</option>
              <option value="100">100 per page</option>
            </select>
          </div>
        </div>

        <div class="table-wrapper" v-if="!loading">
          <table class="transactions-table">
            <thead>
              <tr>
                <th @click="sort('tax_id')" class="sortable">
                  ID
                  <i class="fas fa-sort" :class="getSortIcon('tax_id')"></i>
                </th>
                <th @click="sort('tax_type')" class="sortable">
                  Tax Type
                  <i class="fas fa-sort" :class="getSortIcon('tax_type')"></i>
                </th>
                <th @click="sort('tax_code')" class="sortable">
                  Tax Code
                  <i class="fas fa-sort" :class="getSortIcon('tax_code')"></i>
                </th>
                <th @click="sort('transaction_date')" class="sortable">
                  Date
                  <i class="fas fa-sort" :class="getSortIcon('transaction_date')"></i>
                </th>
                <th @click="sort('tax_amount')" class="sortable">
                  Amount
                  <i class="fas fa-sort" :class="getSortIcon('tax_amount')"></i>
                </th>
                <th>Reference</th>
                <th @click="sort('status')" class="sortable">
                  Status
                  <i class="fas fa-sort" :class="getSortIcon('status')"></i>
                </th>
                <th class="actions-col">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in transactions" :key="transaction.tax_id" class="transaction-row">
                <td class="id-cell">
                  <span class="id-badge">#{{ transaction.tax_id }}</span>
                </td>
                <td>
                  <div class="tax-type-cell">
                    <i class="fas fa-tag"></i>
                    {{ transaction.tax_type }}
                  </div>
                </td>
                <td>
                  <span class="tax-code">{{ transaction.tax_code }}</span>
                </td>
                <td>
                  <div class="date-cell">
                    <i class="fas fa-calendar-alt"></i>
                    {{ formatDate(transaction.transaction_date) }}
                  </div>
                </td>
                <td>
                  <div class="amount-cell">
                    <span class="currency">$</span>
                    <span class="amount">{{ formatCurrency(transaction.tax_amount) }}</span>
                  </div>
                </td>
                <td>
                  <div class="reference-cell">
                    <span class="ref-type">{{ transaction.reference_type }}</span>
                    <span class="ref-id">#{{ transaction.reference_id }}</span>
                  </div>
                </td>
                <td>
                  <span class="status-badge" :class="getStatusClass(transaction.status)">
                    <i class="fas fa-circle"></i>
                    {{ transaction.status }}
                  </span>
                </td>
                <td class="actions-cell">
                  <div class="action-buttons">
                    <router-link 
                      :to="`/tax-transactions/${transaction.tax_id}`"
                      class="btn-icon view"
                      title="View Details"
                    >
                      <i class="fas fa-eye"></i>
                    </router-link>
                    <router-link 
                      :to="`/tax-transactions/${transaction.tax_id}/edit`"
                      class="btn-icon edit"
                      title="Edit Transaction"
                    >
                      <i class="fas fa-edit"></i>
                    </router-link>
                    <button 
                      @click="deleteTransaction(transaction)"
                      class="btn-icon delete"
                      title="Delete Transaction"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="loading" class="loading-state">
          <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
          </div>
          <p>Loading transactions...</p>
        </div>

        <div v-if="!loading && transactions.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-receipt"></i>
          </div>
          <h3>No transactions found</h3>
          <p>Try adjusting your filters or create a new tax transaction</p>
          <router-link to="/tax-transactions/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Transaction
          </router-link>
        </div>

        <!-- Pagination -->
        <div v-if="!loading && transactions.length > 0" class="pagination-wrapper">
          <div class="pagination-info">
            Showing {{ pagination.from || 0 }} to {{ pagination.to || 0 }} of {{ pagination.total || 0 }} results
          </div>
          <div class="pagination-controls">
            <button 
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="btn btn-outline btn-sm"
            >
              <i class="fas fa-chevron-left"></i>
              Previous
            </button>
            
            <div class="page-numbers">
              <button 
                v-for="page in getPageNumbers()"
                :key="page"
                @click="changePage(page)"
                :class="['page-btn', { active: page === pagination.current_page }]"
              >
                {{ page }}
              </button>
            </div>
            
            <button 
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="btn btn-outline btn-sm"
            >
              Next
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h3>
              <i class="fas fa-exclamation-triangle text-danger"></i>
              Confirm Delete
            </h3>
            <button @click="closeDeleteModal" class="btn-close">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete this tax transaction?</p>
            <div class="transaction-details">
              <p><strong>ID:</strong> #{{ transactionToDelete?.tax_id }}</p>
              <p><strong>Type:</strong> {{ transactionToDelete?.tax_type }}</p>
              <p><strong>Amount:</strong> ${{ formatCurrency(transactionToDelete?.tax_amount) }}</p>
            </div>
            <p class="warning-text">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button @click="closeDeleteModal" class="btn btn-outline">Cancel</button>
            <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
              <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-trash"></i>
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'

export default {
  name: 'TaxTransactionList',
  components: {
    AppLayout
  },
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const deleting = ref(false)
    const transactions = ref([])
    const searchQuery = ref('')
    const perPage = ref(15)
    const sortBy = ref('transaction_date')
    const sortOrder = ref('desc')
    const showDeleteModal = ref(false)
    const transactionToDelete = ref(null)

    const filters = reactive({
      tax_type: '',
      tax_code: '',
      status: '',
      from_date: '',
      to_date: ''
    })

    const pagination = reactive({
      current_page: 1,
      last_page: 1,
      total: 0,
      from: 0,
      to: 0
    })

    const statistics = reactive({
      completed: 0,
      pending: 0,
      totalAmount: 0,
      thisMonth: 0
    })

    // Computed
    const hasActiveFilters = computed(() => {
      return Object.values(filters).some(value => value !== '')
    })

    // Methods
    const fetchTransactions = async (page = 1) => {
      loading.value = true
      try {
        const params = {
          page,
          per_page: perPage.value,
          sort_by: sortBy.value,
          sort_order: sortOrder.value,
          search: searchQuery.value,
          ...filters
        }

        const response = await axios.get('/accounting/tax-transactions', { params })
        transactions.value = response.data.data
        
        // Update pagination
        Object.assign(pagination, {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to
        })

        // Calculate statistics
        calculateStatistics()
      } catch (error) {
        console.error('Error fetching transactions:', error)
        showNotification('Error loading transactions', 'error')
      } finally {
        loading.value = false
      }
    }

    const calculateStatistics = () => {
      const completed = transactions.value.filter(t => t.status === 'Completed').length
      const pending = transactions.value.filter(t => t.status === 'Pending').length
      const totalAmount = transactions.value.reduce((sum, t) => sum + parseFloat(t.tax_amount || 0), 0)
      
      const currentMonth = new Date().getMonth()
      const currentYear = new Date().getFullYear()
      const thisMonth = transactions.value.filter(t => {
        const date = new Date(t.transaction_date)
        return date.getMonth() === currentMonth && date.getFullYear() === currentYear
      }).length

      Object.assign(statistics, { completed, pending, totalAmount, thisMonth })
    }

    const applyFilters = () => {
      pagination.current_page = 1
      fetchTransactions()
    }

    const clearFilters = () => {
      Object.keys(filters).forEach(key => {
        filters[key] = ''
      })
      searchQuery.value = ''
      applyFilters()
    }

    const sort = (field) => {
      if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
      } else {
        sortBy.value = field
        sortOrder.value = 'asc'
      }
      fetchTransactions()
    }

    const getSortIcon = (field) => {
      if (sortBy.value !== field) return ''
      return sortOrder.value === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.last_page) {
        pagination.current_page = page
        fetchTransactions(page)
      }
    }

    const getPageNumbers = () => {
      const pages = []
      const start = Math.max(1, pagination.current_page - 2)
      const end = Math.min(pagination.last_page, pagination.current_page + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    }

    const deleteTransaction = (transaction) => {
      transactionToDelete.value = transaction
      showDeleteModal.value = true
    }

    const closeDeleteModal = () => {
      showDeleteModal.value = false
      transactionToDelete.value = null
    }

    const confirmDelete = async () => {
      if (!transactionToDelete.value) return
      
      deleting.value = true
      try {
        await axios.delete(`/accounting/tax-transactions/${transactionToDelete.value.tax_id}`)
        showNotification('Transaction deleted successfully', 'success')
        fetchTransactions(pagination.current_page)
        closeDeleteModal()
      } catch (error) {
        console.error('Error deleting transaction:', error)
        showNotification('Error deleting transaction', 'error')
      } finally {
        deleting.value = false
      }
    }

    const exportData = async () => {
      try {
        const params = { ...filters, search: searchQuery.value }
        const response = await axios.get('/accounting/tax-transactions/export', { 
          params,
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', 'tax-transactions.xlsx')
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

    // Utility functions
    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US').format(amount || 0)
    }

    const getStatusClass = (status) => {
      const statusClasses = {
        'Draft': 'draft',
        'Pending': 'pending',
        'Approved': 'approved',
        'Completed': 'completed',
        'Cancelled': 'cancelled'
      }
      return statusClasses[status] || 'draft'
    }

    const showNotification = (message, type = 'info') => {
      // Implementation for notification system
      console.log(`${type}: ${message}`)
    }

    // Debounced search
    let searchTimeout
    const debouncedSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        applyFilters()
      }, 500)
    }

    // Lifecycle
    onMounted(() => {
      fetchTransactions()
    })

    return {
      loading,
      deleting,
      transactions,
      searchQuery,
      perPage,
      sortBy,
      sortOrder,
      showDeleteModal,
      transactionToDelete,
      filters,
      pagination,
      statistics,
      hasActiveFilters,
      fetchTransactions,
      applyFilters,
      clearFilters,
      sort,
      getSortIcon,
      changePage,
      getPageNumbers,
      deleteTransaction,
      closeDeleteModal,
      confirmDelete,
      exportData,
      formatDate,
      formatCurrency,
      getStatusClass,
      debouncedSearch
    }
  }
}
</script>

<style scoped>
.tax-transactions-container {
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
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.title-section {
  flex: 1;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
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
  align-items: center;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.stat-icon.success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.stat-icon.warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.stat-icon.info {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.stat-icon.primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.stat-content h3 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.stat-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

/* Filters Card */
.filters-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.filters-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.filters-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-group label {
  font-weight: 500;
  color: var(--text-primary);
  font-size: 0.9rem;
}

.search-wrapper {
  position: relative;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 0.9rem;
}

.search-input {
  padding-left: 2.5rem !important;
}

/* Table Card */
.table-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.table-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--bg-tertiary);
}

.table-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.record-count {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-weight: 400;
}

.table-wrapper {
  overflow-x: auto;
}

.transactions-table {
  width: 100%;
  border-collapse: collapse;
}

.transactions-table th {
  background: var(--bg-tertiary);
  padding: 1rem 1.5rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
  font-size: 0.9rem;
  white-space: nowrap;
}

.transactions-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: background-color 0.2s ease;
}

.transactions-table th.sortable:hover {
  background: var(--border-color);
}

.transactions-table th i {
  margin-left: 0.5rem;
  color: var(--text-muted);
}

.transactions-table td {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.transaction-row {
  transition: background-color 0.2s ease;
}

.transaction-row:hover {
  background: var(--bg-tertiary);
}

.id-badge {
  background: var(--primary-color);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.tax-type-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
}

.tax-code {
  background: var(--bg-tertiary);
  padding: 0.25rem 0.75rem;
  border-radius: 8px;
  font-family: monospace;
  font-size: 0.9rem;
}

.date-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-secondary);
}

.amount-cell {
  display: flex;
  align-items: center;
  font-weight: 600;
  color: var(--text-primary);
}

.currency {
  color: var(--text-muted);
  margin-right: 0.25rem;
}

.reference-cell {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.ref-type {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.ref-id {
  font-weight: 500;
  color: var(--text-primary);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-badge.draft {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.status-badge.pending {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-badge.approved {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-badge.completed {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge.cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.status-badge i {
  font-size: 0.6rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.85rem;
}

.btn-icon.view {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.btn-icon.edit {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.btn-icon.delete {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.btn-icon:hover {
  transform: scale(1.1);
}

/* Loading and Empty States */
.loading-state, .empty-state {
  padding: 4rem 2rem;
  text-align: center;
  color: var(--text-secondary);
}

.loading-spinner {
  font-size: 2rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

.empty-icon {
  font-size: 4rem;
  color: var(--text-muted);
  margin-bottom: 1rem;
}

/* Pagination */
.pagination-wrapper {
  padding: 1.5rem 2rem;
  border-top: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--bg-tertiary);
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
  width: 40px;
  height: 40px;
  border: 1px solid var(--border-color);
  background: var(--card-bg);
  color: var(--text-primary);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
}

.page-btn:hover {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.page-btn.active {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

/* Form Elements */
.form-input, .form-select {
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 0.9rem;
  transition: all 0.2s ease;
  width: 100%;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-select.small {
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

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.btn-outline {
  background: transparent;
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-tertiary);
}

.btn-text {
  background: transparent;
  color: var(--text-muted);
  padding: 0.5rem;
}

.btn-text:hover {
  color: var(--text-primary);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.85rem;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: var(--card-bg);
  border-radius: 16px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 1.5rem 2rem 1rem 2rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-close {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.5rem;
  border-radius: 4px;
}

.btn-close:hover {
  color: var(--text-primary);
  background: var(--bg-tertiary);
}

.modal-body {
  padding: 1.5rem 2rem;
}

.transaction-details {
  background: var(--bg-tertiary);
  padding: 1rem;
  border-radius: 8px;
  margin: 1rem 0;
}

.transaction-details p {
  margin: 0.25rem 0;
}

.warning-text {
  color: #ef4444;
  font-weight: 500;
  margin-top: 1rem;
}

.modal-footer {
  padding: 1rem 2rem 1.5rem 2rem;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.text-danger {
  color: #ef4444;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .filters-grid {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
}

@media (max-width: 768px) {
  .tax-transactions-container {
    padding: 1rem;
  }

  .page-title {
    font-size: 2rem;
  }

  .filters-grid {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .table-card {
    border-radius: 12px;
  }

  .transactions-table th,
  .transactions-table td {
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
  }

  .pagination-wrapper {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>