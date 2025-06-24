<template>
  <div class="payables-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-file-invoice-dollar"></i>
            Vendor Payables
          </h1>
          <p class="page-subtitle">Manage outstanding vendor payments and track due dates</p>
        </div>
        <div class="header-actions">
          <button @click="exportData" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export
          </button>
          <button @click="showCreateModal = true" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Payable
          </button>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="filters-grid">
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
          <label>Status</label>
          <select v-model="filters.status" @change="applyFilters" class="form-select">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
          </select>
        </div>
        
        <div class="filter-item">
          <label>From Date</label>
          <input 
            type="date" 
            v-model="filters.from_date" 
            @change="applyFilters"
            class="form-input"
          >
        </div>
        
        <div class="filter-item">
          <label>To Date</label>
          <input 
            type="date" 
            v-model="filters.to_date" 
            @change="applyFilters"
            class="form-input"
          >
        </div>
        
        <div class="filter-item">
          <label>Search</label>
          <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input 
              type="text" 
              v-model="searchQuery" 
              @input="debounceSearch"
              placeholder="Search payables..."
              class="form-input search-input"
            >
          </div>
        </div>
      </div>
      
      <div class="filter-actions">
        <button @click="clearFilters" class="btn btn-ghost">
          <i class="fas fa-times"></i>
          Clear Filters
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon total">
          <i class="fas fa-file-invoice"></i>
        </div>
        <div class="summary-content">
          <h3>{{ formatCurrency(summary.total_amount) }}</h3>
          <p>Total Payables</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="summary-icon pending">
          <i class="fas fa-clock"></i>
        </div>
        <div class="summary-content">
          <h3>{{ formatCurrency(summary.pending_amount) }}</h3>
          <p>Pending Payments</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="summary-icon overdue">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="summary-content">
          <h3>{{ formatCurrency(summary.overdue_amount) }}</h3>
          <p>Overdue Amount</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="summary-icon paid">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="summary-content">
          <h3>{{ summary.paid_count }}</h3>
          <p>Paid This Month</p>
        </div>
      </div>
    </div>

    <!-- Payables Table -->
    <div class="table-section">
      <div class="table-header">
        <h3>Payables List</h3>
        <div class="table-actions">
          <button @click="refreshData" :disabled="loading" class="btn btn-ghost">
            <i class="fas fa-refresh" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
        </div>
      </div>
      
      <div class="table-container">
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading payables...</p>
        </div>
        
        <div v-else-if="payables.length === 0" class="empty-state">
          <i class="fas fa-file-invoice"></i>
          <h3>No Payables Found</h3>
          <p>No payables match your current filters</p>
          <button @click="clearFilters" class="btn btn-primary">Clear Filters</button>
        </div>
        
        <table v-else class="payables-table">
          <thead>
            <tr>
              <th @click="sortBy('payable_id')" class="sortable">
                Payable ID
                <i class="fas fa-sort" :class="getSortIcon('payable_id')"></i>
              </th>
              <th>Vendor</th>
              <th>Invoice</th>
              <th @click="sortBy('amount')" class="sortable">
                Amount
                <i class="fas fa-sort" :class="getSortIcon('amount')"></i>
              </th>
              <th>Paid Amount</th>
              <th>Balance</th>
              <th @click="sortBy('due_date')" class="sortable">
                Due Date
                <i class="fas fa-sort" :class="getSortIcon('due_date')"></i>
              </th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payable in payables" :key="payable.payable_id" class="payable-row">
              <td class="payable-id">
                <router-link :to="`/payables/${payable.payable_id}`" class="id-link">
                  #{{ payable.payable_id }}
                </router-link>
              </td>
              <td>
                <div class="vendor-info">
                  <span class="vendor-name">{{ payable.vendor?.name || 'N/A' }}</span>
                  <small class="vendor-code">{{ payable.vendor?.vendor_code }}</small>
                </div>
              </td>
              <td>
                <span class="invoice-number">{{ payable.vendor_invoice?.invoice_number || 'N/A' }}</span>
              </td>
              <td>
                <span class="amount">{{ formatCurrency(payable.amount) }}</span>
              </td>
              <td>
                <span class="paid-amount">{{ formatCurrency(payable.paid_amount || 0) }}</span>
              </td>
              <td>
                <span class="balance" :class="{ 'text-danger': payable.balance > 0 }">
                  {{ formatCurrency(payable.balance) }}
                </span>
              </td>
              <td>
                <div class="due-date" :class="getDueDateClass(payable.due_date)">
                  <i class="fas fa-calendar-alt"></i>
                  {{ formatDate(payable.due_date) }}
                </div>
              </td>
              <td>
                <span class="status-badge" :class="`status-${payable.status}`">
                  {{ getStatusText(payable.status) }}
                </span>
              </td>
              <td>
                <div class="action-buttons">
                  <button 
                    @click="viewPayable(payable)" 
                    class="btn-action view"
                    title="View Details"
                  >
                    <i class="fas fa-eye"></i>
                  </button>
                  <button 
                    @click="editPayable(payable)" 
                    class="btn-action edit"
                    title="Edit Payable"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button 
                    @click="deletePayable(payable)" 
                    class="btn-action delete"
                    title="Delete Payable"
                    :disabled="payable.paid_amount > 0"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="pagination.total > 0" class="pagination-section">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
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

    <!-- Create/Edit Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>{{ editingPayable ? 'Edit Payable' : 'Create New Payable' }}</h3>
          <button @click="closeModal" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <form @submit.prevent="savePayable" class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>Vendor *</label>
              <select v-model="form.vendor_id" required class="form-select">
                <option value="">Select Vendor</option>
                <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
                  {{ vendor.name }}
                </option>
              </select>
            </div>
            
            <div class="form-group">
              <label>Invoice *</label>
              <select v-model="form.invoice_id" required class="form-select">
                <option value="">Select Invoice</option>
                <option v-for="invoice in invoices" :key="invoice.invoice_id" :value="invoice.invoice_id">
                  {{ invoice.invoice_number }}
                </option>
              </select>
            </div>
            
            <div class="form-group">
              <label>Amount *</label>
              <input 
                type="number" 
                v-model="form.amount" 
                step="0.01"
                min="0"
                required 
                class="form-input"
                placeholder="0.00"
              >
            </div>
            
            <div class="form-group">
              <label>Due Date *</label>
              <input 
                type="date" 
                v-model="form.due_date" 
                required 
                class="form-input"
              >
            </div>
            
            <div class="form-group">
              <label>Status *</label>
              <select v-model="form.status" required class="form-select">
                <option value="pending">Pending</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
                <option value="overdue">Overdue</option>
              </select>
            </div>
          </div>
          
          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn btn-outline">
              Cancel
            </button>
            <button type="submit" :disabled="submitting" class="btn btn-primary">
              <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
              {{ editingPayable ? 'Update' : 'Create' }} Payable
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'PayablesList',
  data() {
    return {
      payables: [],
      vendors: [],
      invoices: [],
      loading: false,
      submitting: false,
      searchQuery: '',
      showCreateModal: false,
      editingPayable: null,
      filters: {
        vendor_id: '',
        status: '',
        from_date: '',
        to_date: ''
      },
      sortField: 'due_date',
      sortDirection: 'asc',
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0
      },
      summary: {
        total_amount: 0,
        pending_amount: 0,
        overdue_amount: 0,
        paid_count: 0
      },
      form: {
        vendor_id: '',
        invoice_id: '',
        amount: '',
        due_date: '',
        status: 'pending'
      }
    }
  },
  created() {
    this.loadData()
    this.loadVendors()
    this.loadInvoices()
  },
  methods: {
async loadData() {
  this.loading = true
  try {
    const params = {
      page: this.pagination.current_page,
      per_page: this.pagination.per_page,
      ...this.filters
    }
    
    if (this.searchQuery) {
      params.search = this.searchQuery
    }
    
    
    const response = await axios.get('/accounting/vendor-payables', { params })
    
    this.payables = response.data.data
    this.pagination = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      per_page: response.data.per_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to
    }
    
    this.calculateSummary()
  } catch (error) {
    console.error('Error loading payables:', error)
    this.$toast?.error('Failed to load payables')
  } finally {
    this.loading = false
  }
},
    
async loadVendors() {
  try {
    const response = await axios.get('/vendors')
    this.vendors = Array.isArray(response.data.data) ? response.data.data : (Array.isArray(response.data) ? response.data : [])
    this.$toast?.success('Vendors loaded successfully')
  } catch (error) {
    console.error('Error loading vendors:', error)
    this.$toast?.error('Failed to load vendors')
    this.vendors = []
  }
},
    
    async loadInvoices() {
      try {
        const response = await axios.get('/vendor-invoices')
        this.invoices = response.data.data || response.data
      } catch (error) {
        console.error('Error loading invoices:', error)
      }
    },
    
    calculateSummary() {
      this.summary = {
        total_amount: this.payables.reduce((sum, p) => sum + (p.amount || 0), 0),
        pending_amount: this.payables.filter(p => p.status === 'pending').reduce((sum, p) => sum + (p.balance || 0), 0),
        overdue_amount: this.payables.filter(p => p.status === 'overdue').reduce((sum, p) => sum + (p.balance || 0), 0),
        paid_count: this.payables.filter(p => p.status === 'paid').length
      }
    },
    
applyFilters() {
  clearTimeout(this.filterTimeout)
  this.filterTimeout = setTimeout(() => {
    this.pagination.current_page = 1
    this.loadData()
  }, 300)
},
    
clearFilters() {
  this.filters = {
    vendor_id: '',
    status: '',
    from_date: '',
    to_date: ''
  }
  this.searchQuery = ''
  this.pagination.current_page = 1
  this.loadData()
},
    
    debounceSearch() {
      clearTimeout(this.searchTimeout)
      this.searchTimeout = setTimeout(() => {
        this.applyFilters()
      }, 500)
    },
    
    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortDirection = 'asc'
      }
      this.loadData()
    },
    
    getSortIcon(field) {
      if (this.sortField !== field) return ''
      return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    },
    
    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.pagination.current_page = page
        this.loadData()
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
    
    viewPayable(payable) {
      this.$router.push(`/payables/${payable.payable_id}`)
    },
    
    editPayable(payable) {
      this.editingPayable = payable
      this.form = {
        vendor_id: payable.vendor_id,
        invoice_id: payable.invoice_id,
        amount: payable.amount,
        due_date: payable.due_date,
        status: payable.status
      }
      this.showCreateModal = true
    },
    
    async deletePayable(payable) {
      if (!confirm(`Are you sure you want to delete payable #${payable.payable_id}?`)) {
        return
      }
      
      try {
        await axios.delete(`/accounting/vendor-payables/${payable.payable_id}`)
        this.$toast?.success('Payable deleted successfully')
        this.loadData()
      } catch (error) {
        console.error('Error deleting payable:', error)
        this.$toast?.error('Failed to delete payable')
      }
    },
    
    async savePayable() {
      this.submitting = true
      try {
        if (this.editingPayable) {
          await axios.put(`/accounting/vendor-payables/${this.editingPayable.payable_id}`, this.form)
          this.$toast?.success('Payable updated successfully')
        } else {
          await axios.post('/accounting/vendor-payables', this.form)
          this.$toast?.success('Payable created successfully')
        }
        this.closeModal()
        this.loadData()
      } catch (error) {
        console.error('Error saving payable:', error)
        this.$toast?.error('Failed to save payable')
      } finally {
        this.submitting = false
      }
    },
    
    closeModal() {
      this.showCreateModal = false
      this.editingPayable = null
      this.form = {
        vendor_id: '',
        invoice_id: '',
        amount: '',
        due_date: '',
        status: 'pending'
      }
    },
    
    refreshData() {
      this.loadData()
    },
    
    exportData() {
      // Implementation for data export
      console.log('Export functionality to be implemented')
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
      }).format(amount || 0)
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString('id-ID')
    },
    
    getDueDateClass(dueDate) {
      const today = new Date()
      const due = new Date(dueDate)
      const diffDays = Math.ceil((due - today) / (1000 * 60 * 60 * 24))
      
      if (diffDays < 0) return 'overdue'
      if (diffDays <= 7) return 'due-soon'
      return 'normal'
    },
    
    getStatusText(status) {
      const statusMap = {
        pending: 'Pending',
        partial: 'Partial',
        paid: 'Paid',
        overdue: 'Overdue'
      }
      return statusMap[status] || status
    }
  }
}
</script>

<style scoped>
/* Main Container */
.payables-container {
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

.title-section h1 {
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

.search-input-wrapper {
  position: relative;
}

.search-input-wrapper i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}

.search-input {
  padding-left: 40px;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.summary-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  transition: transform 0.3s ease;
}

.summary-card:hover {
  transform: translateY(-2px);
}

.summary-icon {
  width: 60px;
  height: 60px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
}

.summary-icon.total { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.summary-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
.summary-icon.overdue { background: linear-gradient(135deg, #ef4444, #dc2626); }
.summary-icon.paid { background: linear-gradient(135deg, #10b981, #059669); }

.summary-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
  color: #1f2937;
}

.summary-content p {
  color: #6b7280;
  font-size: 0.9rem;
}

/* Table */
.table-section {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.table-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
}

.table-container {
  overflow-x: auto;
}

.payables-table {
  width: 100%;
  border-collapse: collapse;
}

.payables-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
}

.payables-table th.sortable {
  cursor: pointer;
  user-select: none;
}

.payables-table th.sortable:hover {
  background: #f1f5f9;
}

.payables-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.payable-row:hover {
  background: #f8fafc;
}

.id-link {
  color: #6366f1;
  font-weight: 600;
  text-decoration: none;
}

.id-link:hover {
  text-decoration: underline;
}

.vendor-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.vendor-name {
  font-weight: 500;
}

.vendor-code {
  color: #6b7280;
  font-size: 0.8rem;
}

.due-date {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.due-date.overdue {
  color: #ef4444;
  font-weight: 600;
}

.due-date.due-soon {
  color: #f59e0b;
  font-weight: 600;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-partial {
  background: #dbeafe;
  color: #1e40af;
}

.status-paid {
  background: #d1fae5;
  color: #065f46;
}

.status-overdue {
  background: #fee2e2;
  color: #991b1b;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-action {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.btn-action.view {
  background: #dbeafe;
  color: #1e40af;
}

.btn-action.edit {
  background: #fef3c7;
  color: #92400e;
}

.btn-action.delete {
  background: #fee2e2;
  color: #991b1b;
}

.btn-action:hover {
  transform: translateY(-1px);
  opacity: 0.8;
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
.form-select,
.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: border-color 0.3s ease;
}

.form-select:focus,
.form-input:focus {
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

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
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

.text-danger {
  color: #ef4444;
  font-weight: 600;
}

/* Responsive Design */
@media (max-width: 768px) {
  .payables-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
  
  .table-container {
    overflow-x: scroll;
  }
  
  .payables-table {
    min-width: 800px;
  }
}
</style>