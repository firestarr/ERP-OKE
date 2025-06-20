<template>
  <div class="receivables-list">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <h1 class="page-title">
            <i class="fas fa-receipt"></i>
            Customer Receivables
          </h1>
          <p class="page-subtitle">Manage and track customer outstanding payments</p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/receivables/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Receivable
          </router-link>
          <button @click="toggleAgingReport" class="btn btn-outline">
            <i class="fas fa-chart-bar"></i>
            Aging Report
          </button>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="filters-grid">
        <div class="filter-group">
          <label>Customer</label>
          <select v-model="filters.customer_id" @change="applyFilters">
            <option value="">All Customers</option>
            <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
              {{ customer.name }}
            </option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filters.status" @change="applyFilters">
            <option value="">All Status</option>
            <option value="Outstanding">Outstanding</option>
            <option value="Overdue">Overdue</option>
            <option value="Paid">Paid</option>
            <option value="Partial">Partial</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>From Date</label>
          <input type="date" v-model="filters.from_date" @change="applyFilters">
        </div>
        
        <div class="filter-group">
          <label>To Date</label>
          <input type="date" v-model="filters.to_date" @change="applyFilters">
        </div>
        
        <div class="filter-actions">
          <button @click="clearFilters" class="btn btn-ghost">
            <i class="fas fa-times"></i>
            Clear
          </button>
          <button @click="exportData" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export
          </button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card">
        <div class="card-icon total">
          <i class="fas fa-receipt"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.totalAmount) }}</h3>
          <p>Total Receivables</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="card-icon outstanding">
          <i class="fas fa-clock"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.outstandingAmount) }}</h3>
          <p>Outstanding</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="card-icon overdue">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.overdueAmount) }}</h3>
          <p>Overdue</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="card-icon paid">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summary.paidAmount) }}</h3>
          <p>Paid This Month</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading receivables...</p>
    </div>

    <!-- Receivables Grid -->
    <div v-else class="receivables-grid">
      <div v-if="receivables.length === 0" class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No receivables found</h3>
        <p>No receivables match your current filters</p>
        <button @click="clearFilters" class="btn btn-primary">Clear Filters</button>
      </div>
      
      <div v-else class="receivables-cards">
        <div 
          v-for="receivable in receivables" 
          :key="receivable.receivable_id"
          class="receivable-card"
          :class="getStatusClass(receivable.status)"
          @click="viewReceivable(receivable.receivable_id)"
        >
          <div class="card-header">
            <div class="customer-info">
              <h3>{{ receivable.customer?.name || 'Unknown Customer' }}</h3>
              <p class="invoice-ref">Invoice #{{ receivable.sales_invoice?.invoice_number || receivable.invoice_id }}</p>
            </div>
            <div class="status-badge" :class="receivable.status.toLowerCase()">
              {{ receivable.status }}
            </div>
          </div>
          
          <div class="card-body">
            <div class="amount-section">
              <div class="amount-item">
                <span class="label">Total Amount</span>
                <span class="value">{{ formatCurrency(receivable.amount) }}</span>
              </div>
              <div class="amount-item">
                <span class="label">Paid Amount</span>
                <span class="value paid">{{ formatCurrency(receivable.paid_amount) }}</span>
              </div>
              <div class="amount-item">
                <span class="label">Balance</span>
                <span class="value balance">{{ formatCurrency(receivable.balance) }}</span>
              </div>
            </div>
            
            <div class="date-section">
              <div class="date-item">
                <i class="fas fa-calendar"></i>
                <span>Due: {{ formatDate(receivable.due_date) }}</span>
              </div>
              <div class="date-item" :class="{ overdue: isOverdue(receivable.due_date) }">
                <i class="fas fa-clock"></i>
                <span>{{ getDaysFromDue(receivable.due_date) }}</span>
              </div>
            </div>
          </div>
          
          <div class="card-actions">
            <button @click.stop="editReceivable(receivable.receivable_id)" class="btn-icon" title="Edit">
              <i class="fas fa-edit"></i>
            </button>
            <button @click.stop="addPayment(receivable.receivable_id)" class="btn-icon" title="Add Payment">
              <i class="fas fa-dollar-sign"></i>
            </button>
            <button @click.stop="printStatement(receivable.receivable_id)" class="btn-icon" title="Print Statement">
              <i class="fas fa-print"></i>
            </button>
            <button @click.stop="deleteReceivable(receivable.receivable_id)" class="btn-icon danger" title="Delete">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > pagination.per_page" class="pagination-section">
      <div class="pagination-info">
        Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} receivables
      </div>
      <div class="pagination-controls">
        <button 
          @click="changePage(pagination.current_page - 1)" 
          :disabled="!pagination.prev_page_url"
          class="btn btn-ghost"
        >
          <i class="fas fa-chevron-left"></i>
          Previous
        </button>
        
        <div class="page-numbers">
          <button 
            v-for="page in visiblePages" 
            :key="page"
            @click="changePage(page)"
            :class="{ active: page === pagination.current_page }"
            class="page-btn"
          >
            {{ page }}
          </button>
        </div>
        
        <button 
          @click="changePage(pagination.current_page + 1)" 
          :disabled="!pagination.next_page_url"
          class="btn btn-ghost"
        >
          Next
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ReceivablesList',
  data() {
    return {
      receivables: [],
      customers: [],
      loading: true,
      filters: {
        customer_id: '',
        status: '',
        from_date: '',
        to_date: ''
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
        prev_page_url: null,
        next_page_url: null
      },
      summary: {
        totalAmount: 0,
        outstandingAmount: 0,
        overdueAmount: 0,
        paidAmount: 0
      }
    }
  },
  computed: {
    visiblePages() {
      const pages = []
      const start = Math.max(1, this.pagination.current_page - 2)
      const end = Math.min(this.pagination.last_page, this.pagination.current_page + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    }
  },
  async mounted() {
    await this.loadCustomers()
    await this.loadReceivables()
    await this.loadSummary()
  },
  methods: {
    async loadReceivables(page = 1) {
      try {
        this.loading = true
        const params = {
          page,
          per_page: this.pagination.per_page,
          ...this.filters
        }
        
        const response = await axios.get('/api/accounting/customer-receivables', { params })
        this.receivables = response.data.data
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to,
          prev_page_url: response.data.prev_page_url,
          next_page_url: response.data.next_page_url
        }
      } catch (error) {
        console.error('Error loading receivables:', error)
        this.$toast?.error('Failed to load receivables')
      } finally {
        this.loading = false
      }
    },
    
    async loadCustomers() {
      try {
        const response = await axios.get('/api/customers')
        this.customers = response.data.data || response.data
      } catch (error) {
        console.error('Error loading customers:', error)
      }
    },
    
    async loadSummary() {
      try {
        // Calculate summary from current receivables
        this.summary = this.receivables.reduce((acc, receivable) => {
          acc.totalAmount += parseFloat(receivable.amount) || 0
          acc.outstandingAmount += parseFloat(receivable.balance) || 0
          
          if (this.isOverdue(receivable.due_date)) {
            acc.overdueAmount += parseFloat(receivable.balance) || 0
          }
          
          if (receivable.status === 'Paid') {
            acc.paidAmount += parseFloat(receivable.paid_amount) || 0
          }
          
          return acc
        }, {
          totalAmount: 0,
          outstandingAmount: 0,
          overdueAmount: 0,
          paidAmount: 0
        })
      } catch (error) {
        console.error('Error calculating summary:', error)
      }
    },
    
    applyFilters() {
      this.loadReceivables(1)
    },
    
    clearFilters() {
      this.filters = {
        customer_id: '',
        status: '',
        from_date: '',
        to_date: ''
      }
      this.loadReceivables(1)
    },
    
    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.loadReceivables(page)
      }
    },
    
    viewReceivable(id) {
      this.$router.push(`/accounting/receivables/${id}`)
    },
    
    editReceivable(id) {
      this.$router.push(`/accounting/receivables/${id}/edit`)
    },
    
    addPayment(id) {
      this.$router.push(`/accounting/receivables/${id}/payment`)
    },
    
    printStatement(id) {
      this.$router.push(`/accounting/receivables/${id}/statement`)
    },
    
    async deleteReceivable(id) {
      if (!confirm('Are you sure you want to delete this receivable?')) return
      
      try {
        await axios.delete(`/api/accounting/customer-receivables/${id}`)
        this.$toast?.success('Receivable deleted successfully')
        this.loadReceivables()
      } catch (error) {
        console.error('Error deleting receivable:', error)
        this.$toast?.error('Failed to delete receivable')
      }
    },
    
    toggleAgingReport() {
      this.$router.push('/accounting/receivables/aging')
    },
    
    exportData() {
      // Implementation for export functionality
      console.log('Export functionality to be implemented')
    },
    
    getStatusClass(status) {
      return `status-${status.toLowerCase()}`
    },
    
    isOverdue(dueDate) {
      return new Date(dueDate) < new Date()
    },
    
    getDaysFromDue(dueDate) {
      const today = new Date()
      const due = new Date(dueDate)
      const diffTime = due - today
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      
      if (diffDays > 0) {
        return `${diffDays} days remaining`
      } else if (diffDays === 0) {
        return 'Due today'
      } else {
        return `${Math.abs(diffDays)} days overdue`
      }
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
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
.receivables-list {
  max-width: 1400px;
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

.filter-group select,
.filter-group input {
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.filter-group select:focus,
.filter-group input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

.card-icon.total { background: var(--primary-color); }
.card-icon.outstanding { background: var(--warning-color); }
.card-icon.overdue { background: var(--danger-color); }
.card-icon.paid { background: var(--success-color); }

.card-content h3 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.card-content p {
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

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
  margin-bottom: 2rem;
}

/* Receivables Cards */
.receivables-cards {
  display: grid;
  gap: 1.5rem;
}

.receivable-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  cursor: pointer;
  transition: all 0.2s;
  border-left: 4px solid var(--gray-300);
}

.receivable-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.receivable-card.status-outstanding {
  border-left-color: var(--warning-color);
}

.receivable-card.status-overdue {
  border-left-color: var(--danger-color);
}

.receivable-card.status-paid {
  border-left-color: var(--success-color);
}

.receivable-card.status-partial {
  border-left-color: var(--primary-color);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 1rem;
}

.customer-info h3 {
  font-size: 1.125rem;
  margin-bottom: 0.25rem;
}

.invoice-ref {
  color: var(--gray-600);
  font-size: 0.875rem;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.outstanding {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.overdue {
  background: #fee2e2;
  color: #dc2626;
}

.status-badge.paid {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.partial {
  background: #dbeafe;
  color: #1d4ed8;
}

.card-body {
  margin-bottom: 1rem;
}

.amount-section {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1rem;
  background: var(--gray-50);
  border-radius: 8px;
}

.amount-item {
  text-align: center;
}

.amount-item .label {
  display: block;
  font-size: 0.75rem;
  color: var(--gray-600);
  text-transform: uppercase;
  font-weight: 500;
  margin-bottom: 0.25rem;
}

.amount-item .value {
  display: block;
  font-size: 1rem;
  font-weight: 600;
}

.amount-item .value.paid {
  color: var(--success-color);
}

.amount-item .value.balance {
  color: var(--warning-color);
}

.date-section {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  background: var(--gray-50);
  border-radius: 8px;
}

.date-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--gray-600);
}

.date-item.overdue {
  color: var(--danger-color);
  font-weight: 500;
}

.card-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--gray-200);
}

.btn-icon {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  background: var(--gray-100);
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover {
  background: var(--primary-color);
  color: white;
}

.btn-icon.danger:hover {
  background: var(--danger-color);
}

/* Pagination */
.pagination-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 2rem;
  padding: 1.5rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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

.btn-ghost:hover {
  background: var(--gray-100);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 768px) {
  .receivables-list {
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
  
  .summary-cards {
    grid-template-columns: 1fr;
  }
  
  .amount-section {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  
  .date-section {
    flex-direction: column;
    gap: 0.5rem;
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
</style>