<template>
  <AppLayout>
    <template #page-title>Budget Management</template>
    <template #page-subtitle>Manage organizational budgets and track financial performance</template>
    
    <template #page-actions>
      <button @click="navigateToCreate" class="action-button primary">
        <i class="fas fa-plus"></i>
        Create Budget
      </button>
      <button @click="refreshData" class="action-button secondary" :disabled="loading">
        <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
        Refresh
      </button>
    </template>

    <div class="budget-list-container">
      <!-- Filters Section -->
      <div class="filters-section">
        <div class="filters-card">
          <h3><i class="fas fa-filter"></i> Filters</h3>
          <div class="filters-grid">
            <div class="filter-group">
              <label>Account</label>
              <select v-model="filters.account_id" @change="applyFilters" class="filter-select">
                <option value="">All Accounts</option>
                <option v-for="account in accounts" :key="account.account_id" :value="account.account_id">
                  {{ account.account_code }} - {{ account.name }}
                </option>
              </select>
            </div>
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
              <label>Per Page</label>
              <select v-model="pagination.per_page" @change="applyFilters" class="filter-select">
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
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

      <!-- Stats Cards -->
      <div class="stats-section">
        <div class="stats-grid">
          <div class="stat-card revenue">
            <div class="stat-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
              <h3>{{ formatCurrency(stats.totalBudgeted) }}</h3>
              <p>Total Budgeted</p>
              <small>{{ stats.budgetCount }} budgets</small>
            </div>
          </div>
          <div class="stat-card expenses">
            <div class="stat-icon">
              <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
              <h3>{{ formatCurrency(stats.totalActual) }}</h3>
              <p>Total Actual</p>
              <small>{{ stats.actualCount }} recorded</small>
            </div>
          </div>
          <div class="stat-card variance" :class="stats.totalVariance >= 0 ? 'positive' : 'negative'">
            <div class="stat-icon">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="stat-content">
              <h3>{{ formatCurrency(stats.totalVariance) }}</h3>
              <p>Total Variance</p>
              <small>{{ stats.variancePercentage }}% difference</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Budgets Table -->
      <div class="table-section">
        <div class="table-card">
          <div class="table-header">
            <h3><i class="fas fa-list"></i> Budget Records</h3>
            <div class="table-actions">
              <button @click="exportData" class="btn-export">
                <i class="fas fa-download"></i>
                Export
              </button>
            </div>
          </div>
          
          <div class="table-container">
            <div v-if="loading" class="loading-state">
              <div class="loading-spinner"></div>
              <p>Loading budgets...</p>
            </div>
            
            <div v-else-if="budgets.length === 0" class="empty-state">
              <i class="fas fa-inbox"></i>
              <h3>No budgets found</h3>
              <p>Start by creating your first budget or adjust your filters.</p>
              <button @click="navigateToCreate" class="btn-create">
                <i class="fas fa-plus"></i>
                Create First Budget
              </button>
            </div>
            
            <table v-else class="budget-table">
              <thead>
                <tr>
                  <th @click="sortBy('account_code')" class="sortable">
                    Account Code
                    <i class="fas fa-sort" :class="getSortIcon('account_code')"></i>
                  </th>
                  <th @click="sortBy('account_name')" class="sortable">
                    Account Name
                    <i class="fas fa-sort" :class="getSortIcon('account_name')"></i>
                  </th>
                  <th @click="sortBy('period_name')" class="sortable">
                    Period
                    <i class="fas fa-sort" :class="getSortIcon('period_name')"></i>
                  </th>
                  <th @click="sortBy('budgeted_amount')" class="sortable text-right">
                    Budgeted Amount
                    <i class="fas fa-sort" :class="getSortIcon('budgeted_amount')"></i>
                  </th>
                  <th @click="sortBy('actual_amount')" class="sortable text-right">
                    Actual Amount
                    <i class="fas fa-sort" :class="getSortIcon('actual_amount')"></i>
                  </th>
                  <th @click="sortBy('variance')" class="sortable text-right">
                    Variance
                    <i class="fas fa-sort" :class="getSortIcon('variance')"></i>
                  </th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="budget in budgets" :key="budget.id" class="budget-row">
                  <td class="account-code">{{ budget.chart_of_account?.account_code }}</td>
                  <td class="account-name">
                    <div class="account-info">
                      <strong>{{ budget.chart_of_account?.name }}</strong>
                      <small>{{ budget.chart_of_account?.account_type }}</small>
                    </div>
                  </td>
                  <td class="period">{{ budget.accounting_period?.name }}</td>
                  <td class="budgeted-amount text-right">
                    <span class="amount-badge budgeted">
                      {{ formatCurrency(budget.budgeted_amount) }}
                    </span>
                  </td>
                  <td class="actual-amount text-right">
                    <span v-if="budget.actual_amount !== null" class="amount-badge actual">
                      {{ formatCurrency(budget.actual_amount) }}
                    </span>
                    <span v-else class="no-data">Not recorded</span>
                  </td>
                  <td class="variance text-right">
                    <span v-if="budget.variance !== null" 
                          class="variance-badge" 
                          :class="budget.variance >= 0 ? 'positive' : 'negative'">
                      {{ formatCurrency(budget.variance) }}
                      <small>({{ formatPercentage(budget.variance, budget.budgeted_amount) }}%)</small>
                    </span>
                    <span v-else class="no-data">-</span>
                  </td>
                  <td class="actions text-center">
                    <div class="action-buttons">
                      <button @click="viewDetail(budget.id)" class="btn-action view" title="View Details">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button @click="editBudget(budget.id)" class="btn-action edit" title="Edit">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button @click="confirmDelete(budget)" class="btn-action delete" title="Delete">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="pagination-section">
            <div class="pagination-info">
              Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
            </div>
            <div class="pagination-controls">
              <button @click="changePage(pagination.current_page - 1)" 
                      :disabled="pagination.current_page === 1" 
                      class="page-btn">
                <i class="fas fa-chevron-left"></i>
              </button>
              
              <button v-for="page in visiblePages" 
                      :key="page" 
                      @click="changePage(page)"
                      :class="['page-btn', { active: page === pagination.current_page }]">
                {{ page }}
              </button>
              
              <button @click="changePage(pagination.current_page + 1)" 
                      :disabled="pagination.current_page === pagination.last_page" 
                      class="page-btn">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
          <button @click="closeDeleteModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this budget?</p>
          <div class="budget-info" v-if="budgetToDelete">
            <strong>{{ budgetToDelete.chart_of_account?.name }}</strong><br>
            <small>{{ budgetToDelete.accounting_period?.name }} - {{ formatCurrency(budgetToDelete.budgeted_amount) }}</small>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeDeleteModal" class="btn-cancel">Cancel</button>
          <button @click="deleteBudget" class="btn-delete" :disabled="deleting">
            <i class="fas fa-trash" :class="{ 'fa-spin': deleting }"></i>
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BudgetsList',
  components: {
  },
  setup() {
    const router = useRouter()
    const loading = ref(false)
    const deleting = ref(false)
    const budgets = ref([])
    const accounts = ref([])
    const periods = ref([])
    const showDeleteModal = ref(false)
    const budgetToDelete = ref(null)
    
    const filters = reactive({
      account_id: '',
      period_id: ''
    })
    
    const pagination = reactive({
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0
    })
    
    const sortField = ref('')
    const sortDirection = ref('asc')
    
    const stats = computed(() => {
      const totalBudgeted = budgets.value.reduce((sum, budget) => sum + parseFloat(budget.budgeted_amount || 0), 0)
      const totalActual = budgets.value.reduce((sum, budget) => sum + parseFloat(budget.actual_amount || 0), 0)
      const totalVariance = totalActual - totalBudgeted
      const budgetCount = budgets.value.length
      const actualCount = budgets.value.filter(b => b.actual_amount !== null).length
      const variancePercentage = totalBudgeted > 0 ? ((totalVariance / totalBudgeted) * 100).toFixed(2) : 0
      
      return {
        totalBudgeted,
        totalActual,
        totalVariance,
        budgetCount,
        actualCount,
        variancePercentage
      }
    })
    
    const visiblePages = computed(() => {
      const pages = []
      const current = pagination.current_page
      const last = pagination.last_page
      const delta = 2
      
      for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i)
      }
      
      return pages
    })

    const fetchBudgets = async () => {
      try {
        loading.value = true
        const params = new URLSearchParams({
          page: pagination.current_page,
          per_page: pagination.per_page
        })
        
        if (filters.account_id) params.append('account_id', filters.account_id)
        if (filters.period_id) params.append('period_id', filters.period_id)

        const response = await axios.get(`/accounting/budgets?${params}`)
        budgets.value = response.data.data
        
        Object.assign(pagination, {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to
        })
      } catch (error) {
        console.error('Error fetching budgets:', error)
        // Handle error notification
      } finally {
        loading.value = false
      }
    }

    const fetchDropdownData = async () => {
      try {
        const [accountsResponse, periodsResponse] = await Promise.all([
          axios.get('/accounting/chart-of-accounts'),
          axios.get('/accounting/accounting-periods')
        ])
        
        accounts.value = accountsResponse.data.data || accountsResponse.data
        periods.value = periodsResponse.data.data || periodsResponse.data
      } catch (error) {
        console.error('Error fetching dropdown data:', error)
      }
    }

    const applyFilters = () => {
      pagination.current_page = 1
      fetchBudgets()
    }

    const clearFilters = () => {
      Object.assign(filters, {
        account_id: '',
        period_id: ''
      })
      pagination.per_page = 15
      applyFilters()
    }

    const sortBy = (field) => {
      if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
      } else {
        sortField.value = field
        sortDirection.value = 'asc'
      }
      
      budgets.value.sort((a, b) => {
        let aVal, bVal
        
        switch (field) {
          case 'account_code':
            aVal = a.chart_of_account?.account_code || ''
            bVal = b.chart_of_account?.account_code || ''
            break
          case 'account_name':
            aVal = a.chart_of_account?.name || ''
            bVal = b.chart_of_account?.name || ''
            break
          case 'period_name':
            aVal = a.accounting_period?.name || ''
            bVal = b.accounting_period?.name || ''
            break
          default:
            aVal = a[field] || 0
            bVal = b[field] || 0
        }
        
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
    }

    const getSortIcon = (field) => {
      if (sortField.value !== field) return ''
      return sortDirection.value === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.last_page) {
        pagination.current_page = page
        fetchBudgets()
      }
    }

    const navigateToCreate = () => {
      router.push('/budgets/create')
    }

    const editBudget = (id) => {
      router.push(`/budgets/${id}/edit`)
    }

    const viewDetail = (id) => {
      router.push(`/budgets/${id}`)
    }

    const confirmDelete = (budget) => {
      budgetToDelete.value = budget
      showDeleteModal.value = true
    }

    const closeDeleteModal = () => {
      showDeleteModal.value = false
      budgetToDelete.value = null
    }

    const deleteBudget = async () => {
      if (!budgetToDelete.value) return
      
      try {
        deleting.value = true
        await axios.delete(`/accounting/budgets/${budgetToDelete.value.id}`)
        budgets.value = budgets.value.filter(b => b.id !== budgetToDelete.value.id)
        closeDeleteModal()
        // Show success message
      } catch (error) {
        console.error('Error deleting budget:', error)
        // Show error message
      } finally {
        deleting.value = false
      }
    }

    const refreshData = () => {
      fetchBudgets()
    }

    const exportData = async () => {
      // Implementation for export functionality
      console.log('Export data functionality')
    }

    const formatCurrency = (amount) => {
      if (amount === null || amount === undefined) return '-'
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount)
    }

    const formatPercentage = (variance, budgeted) => {
      if (!variance || !budgeted) return '0'
      return ((variance / budgeted) * 100).toFixed(1)
    }

    onMounted(() => {
      fetchDropdownData()
      fetchBudgets()
    })

    return {
      loading,
      deleting,
      budgets,
      accounts,
      periods,
      filters,
      pagination,
      stats,
      visiblePages,
      showDeleteModal,
      budgetToDelete,
      sortField,
      sortDirection,
      fetchBudgets,
      applyFilters,
      clearFilters,
      sortBy,
      getSortIcon,
      changePage,
      navigateToCreate,
      editBudget,
      viewDetail,
      confirmDelete,
      closeDeleteModal,
      deleteBudget,
      refreshData,
      exportData,
      formatCurrency,
      formatPercentage
    }
  }
}
</script>

<style scoped>
.budget-list-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0;
  space-y: 2rem;
}

/* Filters Section */
.filters-section {
  margin-bottom: 2rem;
}

.filters-card {
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

.filter-actions {
  display: flex;
  gap: 0.5rem;
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

/* Stats Section */
.stats-section {
  margin-bottom: 2rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.stat-card.revenue::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.stat-card.expenses::before {
  background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
}

.stat-card.variance.positive::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.variance.negative::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon {
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

.stat-card.revenue .stat-icon {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.stat-card.expenses .stat-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
}

.stat-card.variance.positive .stat-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.variance.negative .stat-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-content h3 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.stat-content p {
  color: #64748b;
  font-size: 1rem;
  margin: 0 0 0.25rem 0;
  font-weight: 500;
}

.stat-content small {
  color: #94a3b8;
  font-size: 0.8rem;
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

.table-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-export {
  padding: 0.5rem 1rem;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.85rem;
}

.btn-export:hover {
  background: #5b5bd6;
  transform: translateY(-1px);
}

.table-container {
  overflow-x: auto;
}

.budget-table {
  width: 100%;
  border-collapse: collapse;
}

.budget-table th {
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

.budget-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: all 0.3s ease;
}

.budget-table th.sortable:hover {
  background: #e2e8f0;
  color: #6366f1;
}

.budget-table th.sortable i {
  margin-left: 0.5rem;
  opacity: 0.5;
}

.budget-table th.text-right,
.budget-table td.text-right {
  text-align: right;
}

.budget-table th.text-center,
.budget-table td.text-center {
  text-align: center;
}

.budget-table td {
  padding: 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.budget-row:hover {
  background: #f8fafc;
}

.account-info strong {
  display: block;
  color: #1e293b;
  font-weight: 600;
}

.account-info small {
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
  background: #dcfce7;
  color: #16a34a;
}

.variance-badge.negative {
  background: #fee2e2;
  color: #dc2626;
}

.variance-badge small {
  display: block;
  font-size: 0.7rem;
  opacity: 0.8;
}

.no-data {
  color: #94a3b8;
  font-style: italic;
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

.btn-action.delete {
  background: #fee2e2;
  color: #dc2626;
}

.btn-action.delete:hover {
  background: #fecaca;
}

/* Loading and Empty States */
.loading-state,
.empty-state {
  padding: 3rem;
  text-align: center;
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

.empty-state i {
  font-size: 3rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: #64748b;
  margin-bottom: 0.5rem;
}

.btn-create {
  padding: 0.75rem 1.5rem;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 1rem;
}

.btn-create:hover {
  background: #5b5bd6;
  transform: translateY(-1px);
}

/* Pagination */
.pagination-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.pagination-info {
  color: #64748b;
  font-size: 0.9rem;
}

.pagination-controls {
  display: flex;
  gap: 0.25rem;
}

.page-btn {
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  min-width: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.page-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #6366f1;
  color: #6366f1;
}

.page-btn.active {
  background: #6366f1;
  border-color: #6366f1;
  color: white;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
  max-width: 400px;
  width: 90%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  margin: 0;
  color: #dc2626;
}

.modal-header h3 i {
  margin-right: 0.5rem;
}

.close-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.close-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.modal-body {
  padding: 1.5rem;
}

.budget-info {
  margin-top: 1rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 4px solid #6366f1;
}

.modal-footer {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.btn-cancel {
  padding: 0.75rem 1.5rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #64748b;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-cancel:hover {
  background: #e2e8f0;
}

.btn-delete {
  padding: 0.75rem 1.5rem;
  background: #dc2626;
  border: 1px solid #dc2626;
  color: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-delete:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-delete:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
@media (max-width: 768px) {
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .table-container {
    overflow-x: scroll;
  }
  
  .budget-table {
    min-width: 800px;
  }
  
  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
  
  .table-header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
}
</style>