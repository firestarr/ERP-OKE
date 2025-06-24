<template>
    <div class="payments-list-container">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="floating-orbs">
                <div class="orb orb-1"></div>
                <div class="orb orb-2"></div>
                <div class="orb orb-3"></div>
            </div>
        </div>

        <!-- Header Section -->
        <div class="page-header">
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <i class="fas fa-money-check-alt"></i>
                        Payable Payments
                    </h1>
                    <p class="page-subtitle">Manage vendor payment transactions</p>
                </div>
                <div class="header-actions">
                    <button @click="showFilters = !showFilters" class="filter-btn" :class="{ active: showFilters }">
                        <i class="fas fa-filter"></i>
                        Filters
                    </button>
                    <router-link to="/accounting/payable-payments/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Record Payment
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <transition name="slide-down">
            <div v-if="showFilters" class="filter-section">
                <div class="filter-card">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>Date Range</label>
                            <div class="date-range">
                                <input type="date" v-model="filters.from_date" class="date-input">
                                <span class="date-separator">to</span>
                                <input type="date" v-model="filters.to_date" class="date-input">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Currency</label>
                            <select v-model="filters.currency" class="filter-select">
                                <option value="">All Currencies</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="IDR">IDR</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Vendor</label>
                            <input type="text" v-model="filters.vendor_search" placeholder="Search vendor..." class="filter-input">
                        </div>
                        <div class="filter-actions">
                            <button @click="applyFilters" class="btn btn-secondary">
                                <i class="fas fa-search"></i>
                                Apply
                            </button>
                            <button @click="clearFilters" class="btn btn-outline">
                                <i class="fas fa-times"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ formatCurrency(totalPayments) }}</div>
                    <div class="stat-label">Total Payments</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ paymentsThisMonth }}</div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ uniqueVendors }}</div>
                    <div class="stat-label">Vendors Paid</div>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Payment Transactions
                </h3>
                <div class="table-controls">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" v-model="searchQuery" placeholder="Search payments..." class="search-input">
                    </div>
                    <select v-model="perPage" @change="fetchPayments" class="per-page-select">
                        <option value="15">15 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
            </div>

            <div v-if="loading" class="loading-state">
                <div class="loading-spinner"></div>
                <p>Loading payments...</p>
            </div>

            <div v-else-if="payments.length === 0" class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                <h3>No payments found</h3>
                <p>Start by recording your first payment</p>
                <router-link to="/accounting/payable-payments/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Record Payment
                </router-link>
            </div>

            <div v-else class="payment-grid">
                <div v-for="payment in payments" :key="payment.payment_id" class="payment-card" @click="viewPayment(payment)">
                    <div class="payment-header">
                        <div class="payment-number">
                            <i class="fas fa-receipt"></i>
                            #{{ payment.reference_number || payment.payment_id }}
                        </div>
                        <div class="payment-status" :class="getStatusClass(payment)">
                            {{ payment.status || 'Completed' }}
                        </div>
                    </div>
                    
                    <div class="payment-vendor">
                        <div class="vendor-info">
                            <h4>{{ payment.vendor_payable?.vendor?.name || 'N/A' }}</h4>
                            <p>{{ formatDate(payment.payment_date) }}</p>
                        </div>
                        <div class="payment-method">
                            <i :class="getMethodIcon(payment.payment_method)"></i>
                            {{ payment.payment_method || 'Cash' }}
                        </div>
                    </div>

                    <div class="payment-amount">
                        <div class="amount-main">{{ formatCurrency(payment.amount) }}</div>
                        <div class="amount-currency">{{ payment.payment_currency || 'USD' }}</div>
                    </div>

                    <div class="payment-actions">
                        <button @click.stop="viewPayment(payment)" class="action-btn view">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button @click.stop="editPayment(payment)" class="action-btn edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button @click.stop="deletePayment(payment)" class="action-btn delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="pagination-container">
                <div class="pagination-info">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} payments
                </div>
                <div class="pagination-controls">
                    <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="page-numbers">
                        <button v-for="page in getPageNumbers()" :key="page" @click="changePage(page)" 
                                class="page-number" :class="{ active: page === pagination.current_page }">
                            {{ page }}
                        </button>
                    </span>
                    <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <transition name="fade">
            <div v-if="showDeleteModal" class="modal-overlay" @click="showDeleteModal = false">
                <div class="modal-content" @click.stop>
                    <div class="modal-header">
                        <h3>Confirm Delete</h3>
                        <button @click="showDeleteModal = false" class="modal-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this payment?</p>
                        <p><strong>Payment #{{ paymentToDelete?.reference_number || paymentToDelete?.payment_id }}</strong></p>
                        <p>Amount: <strong>{{ formatCurrency(paymentToDelete?.amount) }}</strong></p>
                    </div>
                    <div class="modal-actions">
                        <button @click="showDeleteModal = false" class="btn btn-outline">Cancel</button>
                        <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
                            <i class="fas fa-trash"></i>
                            {{ deleting ? 'Deleting...' : 'Delete' }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios'

export default {
    name: 'PayablePaymentsList',
    data() {
        return {
            payments: [],
            pagination: {},
            loading: false,
            searchQuery: '',
            perPage: 15,
            showFilters: false,
            showDeleteModal: false,
            paymentToDelete: null,
            deleting: false,
            filters: {
                from_date: '',
                to_date: '',
                currency: '',
                vendor_search: ''
            },
            totalPayments: 0,
            paymentsThisMonth: 0,
            uniqueVendors: 0
        }
    },
    computed: {
        filteredPayments() {
            if (!this.searchQuery) return this.payments
            
            return this.payments.filter(payment => 
                payment.reference_number?.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                payment.vendor_payable?.vendor?.name?.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                payment.payment_method?.toLowerCase().includes(this.searchQuery.toLowerCase())
            )
        }
    },
    mounted() {
        this.fetchPayments()
        this.fetchStatistics()
    },
    methods: {
        async fetchPayments(page = 1) {
            this.loading = true
            try {
                const params = {
                    page,
                    per_page: this.perPage,
                    ...this.filters
                }
                
                const response = await axios.get('/accounting/payable-payments', { params })
                this.payments = response.data.data
                this.pagination = {
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    from: response.data.from,
                    to: response.data.to,
                    total: response.data.total
                }
            } catch (error) {
                console.error('Error fetching payments:', error)
                this.$toast.error('Failed to load payments')
            } finally {
                this.loading = false
            }
        },

        async fetchStatistics() {
            try {
                // Calculate statistics from current data
                this.totalPayments = this.payments.reduce((sum, payment) => sum + payment.amount, 0)
                
                const thisMonth = new Date().getMonth()
                this.paymentsThisMonth = this.payments.filter(payment => 
                    new Date(payment.payment_date).getMonth() === thisMonth
                ).length

                this.uniqueVendors = new Set(this.payments.map(payment => payment.vendor_payable?.vendor?.id)).size
            } catch (error) {
                console.error('Error calculating statistics:', error)
            }
        },

        applyFilters() {
            this.fetchPayments(1)
        },

        clearFilters() {
            this.filters = {
                from_date: '',
                to_date: '',
                currency: '',
                vendor_search: ''
            }
            this.fetchPayments(1)
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.last_page) {
                this.fetchPayments(page)
            }
        },

        getPageNumbers() {
            const current = this.pagination.current_page
            const last = this.pagination.last_page
            const delta = 2
            const range = []

            for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
                range.push(i)
            }

            if (current - delta > 2) range.unshift('...')
            if (current + delta < last - 1) range.push('...')

            range.unshift(1)
            if (last !== 1) range.push(last)

            return range
        },

        viewPayment(payment) {
            this.$router.push(`/accounting/payable-payments/${payment.payment_id}`)
        },

        editPayment(payment) {
            this.$router.push(`/accounting/payable-payments/${payment.payment_id}/edit`)
        },

        deletePayment(payment) {
            this.paymentToDelete = payment
            this.showDeleteModal = true
        },

        async confirmDelete() {
            if (!this.paymentToDelete) return
            
            this.deleting = true
            try {
                await axios.delete(`/accounting/payable-payments/${this.paymentToDelete.payment_id}`)
                this.$toast.success('Payment deleted successfully')
                this.showDeleteModal = false
                this.paymentToDelete = null
                this.fetchPayments()
            } catch (error) {
                console.error('Error deleting payment:', error)
                this.$toast.error('Failed to delete payment')
            } finally {
                this.deleting = false
            }
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount || 0)
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            })
        },

        getStatusClass(payment) {
            const status = payment.status?.toLowerCase() || 'completed'
            return {
                'completed': 'status-success',
                'pending': 'status-warning',
                'failed': 'status-error'
            }[status] || 'status-success'
        },

        getMethodIcon(method) {
            const icons = {
                'cash': 'fas fa-money-bill',
                'bank': 'fas fa-university',
                'check': 'fas fa-check',
                'credit_card': 'fas fa-credit-card',
                'transfer': 'fas fa-exchange-alt'
            }
            return icons[method?.toLowerCase()] || 'fas fa-money-bill'
        }
    }
}
</script>

<style scoped>
.payments-list-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    padding: 2rem;
}

.background-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.floating-orbs {
    position: relative;
    width: 100%;
    height: 100%;
}

.orb {
    position: absolute;
    border-radius: 50%;
    background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    backdrop-filter: blur(10px);
    animation: float 20s infinite linear;
}

.orb-1 {
    width: 80px;
    height: 80px;
    top: 20%;
    left: 10%;
    animation-delay: 0s;
}

.orb-2 {
    width: 60px;
    height: 60px;
    top: 60%;
    right: 20%;
    animation-delay: -7s;
}

.orb-3 {
    width: 100px;
    height: 100px;
    bottom: 20%;
    left: 60%;
    animation-delay: -14s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-30px) rotate(120deg); }
    66% { transform: translateY(30px) rotate(240deg); }
}

.page-header {
    position: relative;
    z-index: 1;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.title-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.title-section p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.filter-btn {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn:hover,
.filter-btn.active {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
}

.btn-secondary {
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    color: white;
}

.btn-outline {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #ff4757, #c44569);
    color: white;
}

.filter-section {
    position: relative;
    z-index: 1;
    margin-bottom: 2rem;
}

.filter-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.date-input,
.filter-select,
.filter-input {
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.date-input::placeholder,
.filter-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.date-separator {
    color: white;
    font-weight: 600;
}

.filter-actions {
    display: flex;
    gap: 1rem;
}

.stats-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.table-container {
    position: relative;
    z-index: 1;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.table-header {
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 1rem;
    color: rgba(255, 255, 255, 0.6);
}

.search-input {
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
    width: 250px;
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.per-page-select {
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.loading-state,
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
    color: white;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top: 3px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.empty-icon {
    font-size: 4rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1rem;
}

.payment-grid {
    padding: 2rem;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.payment-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.1);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.payment-number {
    color: white;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.status-warning {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.status-error {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.payment-vendor {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.vendor-info h4 {
    color: white;
    font-size: 1.1rem;
    margin: 0 0 0.25rem 0;
}

.vendor-info p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    margin: 0;
}

.payment-method {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.payment-amount {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.amount-main {
    font-size: 1.5rem;
    font-weight: 700;
    color: #4ecdc4;
}

.amount-currency {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.payment-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.action-btn {
    width: 35px;
    height: 35px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.action-btn.view {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.action-btn.edit {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.action-btn.delete {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.action-btn:hover {
    transform: scale(1.1);
}

.pagination-container {
    padding: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-info {
    color: rgba(255, 255, 255, 0.8);
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-btn,
.page-number {
    width: 40px;
    height: 40px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-number.active {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
}

.page-numbers {
    display: flex;
    gap: 0.25rem;
}

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
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    margin-bottom: 2rem;
    color: #374151;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* Transitions */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .payments-list-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .title-section h1 {
        font-size: 2rem;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .table-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input {
        width: 100%;
    }
    
    .payment-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>