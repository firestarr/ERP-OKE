<template>
    <div class="payment-history-container">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="floating-orbs">
                <div class="orb orb-1"></div>
                <div class="orb orb-2"></div>
                <div class="orb orb-3"></div>
                <div class="orb orb-4"></div>
            </div>
        </div>

        <!-- Header Section -->
        <div class="page-header">
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <i class="fas fa-history"></i>
                        Vendor Payment History
                    </h1>
                    <p class="page-subtitle">
                        {{ selectedVendor ? `Payment history for ${selectedVendor.name}` : 'Complete payment history across all vendors' }}
                    </p>
                </div>
                <div class="header-actions">
                    <router-link to="/accounting/payable-payments" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Payments
                    </router-link>
                    <button @click="exportHistory" class="btn btn-secondary" :disabled="exporting">
                        <i class="fas fa-download"></i>
                        {{ exporting ? 'Exporting...' : 'Export' }}
                    </button>
                    <router-link to="/accounting/payable-payments/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        New Payment
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-container">
            <div class="filters-card">
                <div class="filters-header">
                    <h3>
                        <i class="fas fa-filter"></i>
                        Filters & Search
                    </h3>
                    <button @click="resetFilters" class="reset-btn">
                        <i class="fas fa-undo"></i>
                        Reset
                    </button>
                </div>
                
                <div class="filters-grid">
                    <div class="filter-section">
                        <h4>Vendor Selection</h4>
                        <div class="vendor-filter">
                            <select v-model="filters.vendor_id" @change="loadPaymentHistory" class="vendor-select">
                                <option value="">All Vendors</option>
                                <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
                                    {{ vendor.name }}
                                </option>
                            </select>
                            <div v-if="selectedVendor" class="vendor-info">
                                <div class="vendor-avatar">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="vendor-details">
                                    <span class="vendor-name">{{ selectedVendor.name }}</span>
                                    <span class="vendor-email">{{ selectedVendor.email || 'No email' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Date Range</h4>
                        <div class="date-filters">
                            <div class="date-presets">
                                <button v-for="preset in datePresets" :key="preset.key" 
                                        @click="applyDatePreset(preset)"
                                        class="preset-btn"
                                        :class="{ active: activePreset === preset.key }">
                                    {{ preset.label }}
                                </button>
                            </div>
                            <div class="date-inputs">
                                <div class="date-group">
                                    <label>From</label>
                                    <input type="date" v-model="filters.from_date" @change="loadPaymentHistory" class="date-input">
                                </div>
                                <div class="date-group">
                                    <label>To</label>
                                    <input type="date" v-model="filters.to_date" @change="loadPaymentHistory" class="date-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Payment Filters</h4>
                        <div class="payment-filters">
                            <div class="filter-group">
                                <label>Payment Method</label>
                                <select v-model="filters.payment_method" @change="loadPaymentHistory" class="filter-select">
                                    <option value="">All Methods</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="check">Check</option>
                                    <option value="credit_card">Credit Card</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Currency</label>
                                <select v-model="filters.currency" @change="loadPaymentHistory" class="filter-select">
                                    <option value="">All Currencies</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="IDR">IDR</option>
                                    <option value="SGD">SGD</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Amount Range</label>
                                <div class="amount-range">
                                    <input type="number" v-model="filters.min_amount" placeholder="Min" class="amount-input">
                                    <span>to</span>
                                    <input type="number" v-model="filters.max_amount" placeholder="Max" class="amount-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Search</h4>
                        <div class="search-section">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" v-model="searchQuery" placeholder="Search by reference, vendor name, or invoice..." class="search-input">
                            </div>
                            <button @click="loadPaymentHistory" class="search-btn">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stats-grid">
                <div class="stat-card total-payments">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">{{ formatCurrency(statistics.total_amount) }}</div>
                        <div class="stat-label">Total Payments</div>
                        <div class="stat-change" :class="{ positive: statistics.amount_change > 0, negative: statistics.amount_change < 0 }">
                            <i :class="statistics.amount_change > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
                            {{ Math.abs(statistics.amount_change) }}% vs last period
                        </div>
                    </div>
                </div>
                
                <div class="stat-card payment-count">
                    <div class="stat-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">{{ statistics.payment_count }}</div>
                        <div class="stat-label">Total Transactions</div>
                        <div class="stat-meta">{{ statistics.avg_per_day }} avg/day</div>
                    </div>
                </div>

                <div class="stat-card avg-payment">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">{{ formatCurrency(statistics.avg_amount) }}</div>
                        <div class="stat-label">Average Payment</div>
                        <div class="stat-meta">{{ statistics.largest_payment ? `Max: ${formatCurrency(statistics.largest_payment)}` : '' }}</div>
                    </div>
                </div>

                <div class="stat-card vendors-paid">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">{{ statistics.unique_vendors }}</div>
                        <div class="stat-label">Vendors Paid</div>
                        <div class="stat-meta">{{ statistics.most_frequent_vendor || 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Chart -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>
                        <i class="fas fa-chart-pie"></i>
                        Payment Methods Distribution
                    </h3>
                    <div class="chart-period">{{ getChartPeriod() }}</div>
                </div>
                <div class="chart-content">
                    <div class="method-stats">
                        <div v-for="method in paymentMethodStats" :key="method.method" class="method-stat">
                            <div class="method-info">
                                <div class="method-color" :style="{ backgroundColor: method.color }"></div>
                                <span class="method-name">{{ formatPaymentMethod(method.method) }}</span>
                            </div>
                            <div class="method-values">
                                <span class="method-amount">{{ formatCurrency(method.amount) }}</span>
                                <span class="method-percentage">{{ method.percentage }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3>
                        <i class="fas fa-chart-bar"></i>
                        Monthly Payment Trends
                    </h3>
                    <select v-model="trendPeriod" @change="loadTrendData" class="period-select">
                        <option value="6">Last 6 Months</option>
                        <option value="12">Last 12 Months</option>
                        <option value="24">Last 24 Months</option>
                    </select>
                </div>
                <div class="chart-content">
                    <div class="trend-chart">
                        <div v-for="month in monthlyTrends" :key="month.month" class="trend-bar">
                            <div class="bar-container">
                                <div class="bar" :style="{ height: month.percentage + '%' }"></div>
                            </div>
                            <div class="bar-label">{{ month.label }}</div>
                            <div class="bar-value">{{ formatCurrency(month.amount) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="history-table">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Payment History
                </h3>
                <div class="table-controls">
                    <div class="view-options">
                        <button @click="viewMode = 'card'" class="view-btn" :class="{ active: viewMode === 'card' }">
                            <i class="fas fa-th-large"></i>
                            Cards
                        </button>
                        <button @click="viewMode = 'table'" class="view-btn" :class="{ active: viewMode === 'table' }">
                            <i class="fas fa-table"></i>
                            Table
                        </button>
                    </div>
                    <select v-model="sortBy" @change="applySorting" class="sort-select">
                        <option value="payment_date_desc">Latest First</option>
                        <option value="payment_date_asc">Oldest First</option>
                        <option value="amount_desc">Highest Amount</option>
                        <option value="amount_asc">Lowest Amount</option>
                        <option value="vendor_name">Vendor Name</option>
                    </select>
                </div>
            </div>

            <div v-if="loading" class="loading-state">
                <div class="loading-spinner"></div>
                <p>Loading payment history...</p>
            </div>

            <div v-else-if="filteredPayments.length === 0" class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>No payment history found</h3>
                <p>Try adjusting your filters or date range</p>
                <button @click="resetFilters" class="btn btn-primary">
                    <i class="fas fa-undo"></i>
                    Reset Filters
                </button>
            </div>

            <!-- Card View -->
            <div v-else-if="viewMode === 'card'" class="payments-grid">
                <div v-for="payment in paginatedPayments" :key="payment.payment_id" 
                     class="payment-card" @click="viewPayment(payment)">
                    <div class="card-header">
                        <div class="payment-ref">
                            <i class="fas fa-receipt"></i>
                            {{ payment.reference_number || `#${payment.payment_id}` }}
                        </div>
                        <div class="payment-date">{{ formatDate(payment.payment_date) }}</div>
                    </div>

                    <div class="card-vendor">
                        <div class="vendor-avatar">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="vendor-info">
                            <h4>{{ payment.vendor_payable?.vendor?.name || 'Unknown Vendor' }}</h4>
                            <p>{{ payment.vendor_payable?.vendor?.email || 'No email' }}</p>
                        </div>
                    </div>

                    <div class="card-amount">
                        <div class="amount-main">{{ formatCurrency(payment.amount) }}</div>
                        <div class="amount-meta">
                            <span class="currency">{{ payment.payment_currency || 'USD' }}</span>
                            <span class="method">{{ formatPaymentMethod(payment.payment_method) }}</span>
                        </div>
                    </div>

                    <div class="card-invoice">
                        <div class="invoice-info">
                            <span class="label">Invoice:</span>
                            <span class="value">{{ payment.vendor_payable?.vendor_invoice?.invoice_number || 'N/A' }}</span>
                        </div>
                        <div class="invoice-due">
                            <span class="label">Due:</span>
                            <span class="value" :class="{ overdue: isOverdue(payment.vendor_payable?.due_date) }">
                                {{ formatDate(payment.vendor_payable?.due_date) }}
                            </span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <button @click.stop="viewPayment(payment)" class="action-btn view">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button @click.stop="downloadReceipt(payment)" class="action-btn download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button @click.stop="showPaymentDetails(payment)" class="action-btn info">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div v-else class="payments-table">
                <table>
                    <thead>
                        <tr>
                            <th @click="sortTable('payment_date')" class="sortable">
                                Date
                                <i class="fas fa-sort"></i>
                            </th>
                            <th @click="sortTable('reference_number')" class="sortable">
                                Reference
                                <i class="fas fa-sort"></i>
                            </th>
                            <th @click="sortTable('vendor_name')" class="sortable">
                                Vendor
                                <i class="fas fa-sort"></i>
                            </th>
                            <th>Invoice</th>
                            <th @click="sortTable('amount')" class="sortable">
                                Amount
                                <i class="fas fa-sort"></i>
                            </th>
                            <th>Method</th>
                            <th>Currency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in paginatedPayments" :key="payment.payment_id" class="payment-row">
                            <td class="date-cell">{{ formatDate(payment.payment_date) }}</td>
                            <td class="ref-cell">
                                <span class="ref-number">{{ payment.reference_number || `#${payment.payment_id}` }}</span>
                            </td>
                            <td class="vendor-cell">
                                <div class="vendor-info">
                                    <span class="vendor-name">{{ payment.vendor_payable?.vendor?.name || 'Unknown' }}</span>
                                    <span class="vendor-email">{{ payment.vendor_payable?.vendor?.email || '' }}</span>
                                </div>
                            </td>
                            <td class="invoice-cell">
                                <span class="invoice-number">{{ payment.vendor_payable?.vendor_invoice?.invoice_number || 'N/A' }}</span>
                                <span class="due-date" :class="{ overdue: isOverdue(payment.vendor_payable?.due_date) }">
                                    Due: {{ formatDate(payment.vendor_payable?.due_date) }}
                                </span>
                            </td>
                            <td class="amount-cell">
                                <span class="amount">{{ formatCurrency(payment.amount) }}</span>
                            </td>
                            <td class="method-cell">
                                <div class="method-badge" :class="payment.payment_method">
                                    <i :class="getMethodIcon(payment.payment_method)"></i>
                                    {{ formatPaymentMethod(payment.payment_method) }}
                                </div>
                            </td>
                            <td class="currency-cell">{{ payment.payment_currency || 'USD' }}</td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button @click="viewPayment(payment)" class="action-btn view">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button @click="downloadReceipt(payment)" class="action-btn download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button @click="showPaymentDetails(payment)" class="action-btn info">
                                        <i class="fas fa-info"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="pagination-container">
                <div class="pagination-info">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} payments
                </div>
                <div class="pagination-controls">
                    <button @click="changePage(pagination.current_page - 1)" 
                            :disabled="pagination.current_page === 1" class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="page-numbers">
                        <button v-for="page in getPageNumbers()" :key="page" 
                                @click="changePage(page)" 
                                class="page-number" 
                                :class="{ active: page === pagination.current_page }">
                            {{ page }}
                        </button>
                    </span>
                    <button @click="changePage(pagination.current_page + 1)" 
                            :disabled="pagination.current_page === pagination.last_page" class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Details Modal -->
        <transition name="fade">
            <div v-if="showDetailsModal" class="modal-overlay" @click="showDetailsModal = false">
                <div class="modal-content payment-details-modal" @click.stop>
                    <div class="modal-header">
                        <h3>Payment Details</h3>
                        <button @click="showDetailsModal = false" class="modal-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div v-if="selectedPaymentDetails" class="modal-body">
                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="label">Payment ID:</span>
                                <span class="value">{{ selectedPaymentDetails.payment_id }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Reference:</span>
                                <span class="value">{{ selectedPaymentDetails.reference_number || 'N/A' }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Date:</span>
                                <span class="value">{{ formatDate(selectedPaymentDetails.payment_date) }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Amount:</span>
                                <span class="value highlight">{{ formatCurrency(selectedPaymentDetails.amount) }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Currency:</span>
                                <span class="value">{{ selectedPaymentDetails.payment_currency || 'USD' }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Method:</span>
                                <span class="value">{{ formatPaymentMethod(selectedPaymentDetails.payment_method) }}</span>
                            </div>
                            <div v-if="selectedPaymentDetails.exchange_rate && selectedPaymentDetails.exchange_rate !== 1" class="detail-item">
                                <span class="label">Exchange Rate:</span>
                                <span class="value">{{ selectedPaymentDetails.exchange_rate }}</span>
                            </div>
                            <div v-if="selectedPaymentDetails.notes" class="detail-item full-width">
                                <span class="label">Notes:</span>
                                <span class="value">{{ selectedPaymentDetails.notes }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button @click="viewPayment(selectedPaymentDetails)" class="btn btn-primary">
                            <i class="fas fa-eye"></i>
                            View Full Details
                        </button>
                        <button @click="downloadReceipt(selectedPaymentDetails)" class="btn btn-secondary">
                            <i class="fas fa-download"></i>
                            Download Receipt
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
    name: 'VendorPaymentHistory',
    props: {
        vendorId: String
    },
    data() {
        return {
            payments: [],
            vendors: [],
            pagination: {},
            loading: false,
            exporting: false,
            viewMode: 'card',
            sortBy: 'payment_date_desc',
            searchQuery: '',
            activePreset: 'all',
            trendPeriod: '6',
            showDetailsModal: false,
            selectedPaymentDetails: null,
            filters: {
                vendor_id: this.vendorId || '',
                from_date: '',
                to_date: '',
                payment_method: '',
                currency: '',
                min_amount: '',
                max_amount: ''
            },
            statistics: {
                total_amount: 0,
                payment_count: 0,
                avg_amount: 0,
                unique_vendors: 0,
                largest_payment: 0,
                amount_change: 0,
                avg_per_day: 0,
                most_frequent_vendor: ''
            },
            paymentMethodStats: [],
            monthlyTrends: [],
            datePresets: [
                { key: 'today', label: 'Today', days: 0 },
                { key: 'week', label: 'This Week', days: 7 },
                { key: 'month', label: 'This Month', days: 30 },
                { key: 'quarter', label: 'This Quarter', days: 90 },
                { key: 'year', label: 'This Year', days: 365 },
                { key: 'all', label: 'All Time', days: null }
            ]
        }
    },
    computed: {
        selectedVendor() {
            return this.vendors.find(v => v.id == this.filters.vendor_id)
        },
        filteredPayments() {
            let filtered = [...this.payments]
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase()
                filtered = filtered.filter(payment => 
                    payment.reference_number?.toLowerCase().includes(query) ||
                    payment.vendor_payable?.vendor?.name?.toLowerCase().includes(query) ||
                    payment.vendor_payable?.vendor_invoice?.invoice_number?.toLowerCase().includes(query)
                )
            }
            
            if (this.filters.min_amount) {
                filtered = filtered.filter(payment => payment.amount >= this.filters.min_amount)
            }
            
            if (this.filters.max_amount) {
                filtered = filtered.filter(payment => payment.amount <= this.filters.max_amount)
            }
            
            return filtered
        },
        paginatedPayments() {
            const start = (this.pagination.current_page - 1) * 15
            const end = start + 15
            return this.filteredPayments.slice(start, end)
        }
    },
    async mounted() {
        await this.loadInitialData()
        await this.loadPaymentHistory()
        await this.loadStatistics()
        await this.loadChartData()
    },
    methods: {
        async loadInitialData() {
            try {
                const response = await axios.get('/api/vendors')
                this.vendors = response.data.data || response.data
            } catch (error) {
                console.error('Error loading vendors:', error)
                this.$toast.error('Failed to load vendors')
            }
        },

        async loadPaymentHistory() {
            this.loading = true
            try {
                const params = {
                    per_page: 50,
                    ...this.filters
                }
                
                const response = await axios.get('/api/accounting/payable-payments', { params })
                this.payments = response.data.data || response.data
                this.pagination = {
                    current_page: 1,
                    last_page: Math.ceil(this.payments.length / 15),
                    from: 1,
                    to: Math.min(15, this.payments.length),
                    total: this.payments.length
                }
                this.applySorting()
            } catch (error) {
                console.error('Error loading payment history:', error)
                this.$toast.error('Failed to load payment history')
            } finally {
                this.loading = false
            }
        },

        async loadStatistics() {
            try {
                const payments = this.filteredPayments
                
                this.statistics = {
                    total_amount: payments.reduce((sum, p) => sum + p.amount, 0),
                    payment_count: payments.length,
                    avg_amount: payments.length ? payments.reduce((sum, p) => sum + p.amount, 0) / payments.length : 0,
                    unique_vendors: new Set(payments.map(p => p.vendor_payable?.vendor?.id)).size,
                    largest_payment: Math.max(...payments.map(p => p.amount), 0),
                    amount_change: Math.random() * 20 - 10, // Mock data
                    avg_per_day: payments.length / 30, // Mock calculation
                    most_frequent_vendor: this.getMostFrequentVendor()
                }
            } catch (error) {
                console.error('Error calculating statistics:', error)
            }
        },

        async loadChartData() {
            try {
                // Payment method distribution
                const methodGroups = this.groupBy(this.filteredPayments, 'payment_method')
                const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#6c5ce7']
                
                this.paymentMethodStats = Object.entries(methodGroups).map(([method, payments], index) => {
                    const amount = payments.reduce((sum, p) => sum + p.amount, 0)
                    return {
                        method,
                        amount,
                        count: payments.length,
                        percentage: Math.round((amount / this.statistics.total_amount) * 100),
                        color: colors[index % colors.length]
                    }
                }).sort((a, b) => b.amount - a.amount)

                // Monthly trends
                await this.loadTrendData()
            } catch (error) {
                console.error('Error loading chart data:', error)
            }
        },

        async loadTrendData() {
            try {
                const months = parseInt(this.trendPeriod)
                const monthlyData = {}
                
                // Group payments by month
                this.filteredPayments.forEach(payment => {
                    const date = new Date(payment.payment_date)
                    const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
                    
                    if (!monthlyData[monthKey]) {
                        monthlyData[monthKey] = { amount: 0, count: 0 }
                    }
                    monthlyData[monthKey].amount += payment.amount
                    monthlyData[monthKey].count++
                })

                // Create trend data for the last N months
                const trends = []
                const maxAmount = Math.max(...Object.values(monthlyData).map(d => d.amount), 1)
                
                for (let i = months - 1; i >= 0; i--) {
                    const date = new Date()
                    date.setMonth(date.getMonth() - i)
                    const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
                    const data = monthlyData[monthKey] || { amount: 0, count: 0 }
                    
                    trends.push({
                        month: monthKey,
                        label: date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }),
                        amount: data.amount,
                        count: data.count,
                        percentage: (data.amount / maxAmount) * 100
                    })
                }
                
                this.monthlyTrends = trends
            } catch (error) {
                console.error('Error loading trend data:', error)
            }
        },

        applyDatePreset(preset) {
            this.activePreset = preset.key
            
            if (preset.days === null) {
                this.filters.from_date = ''
                this.filters.to_date = ''
            } else {
                const today = new Date()
                const fromDate = new Date(today)
                fromDate.setDate(today.getDate() - preset.days)
                
                this.filters.from_date = fromDate.toISOString().split('T')[0]
                this.filters.to_date = today.toISOString().split('T')[0]
            }
            
            this.loadPaymentHistory()
        },

        resetFilters() {
            this.filters = {
                vendor_id: this.vendorId || '',
                from_date: '',
                to_date: '',
                payment_method: '',
                currency: '',
                min_amount: '',
                max_amount: ''
            }
            this.searchQuery = ''
            this.activePreset = 'all'
            this.loadPaymentHistory()
        },

        applySorting() {
            const [field, direction] = this.sortBy.split('_')
            const isDesc = direction === 'desc'
            
            this.payments.sort((a, b) => {
                let aVal, bVal
                
                switch (field) {
                    case 'payment':
                        aVal = new Date(a.payment_date)
                        bVal = new Date(b.payment_date)
                        break
                    case 'amount':
                        aVal = a.amount
                        bVal = b.amount
                        break
                    case 'vendor':
                        aVal = a.vendor_payable?.vendor?.name || ''
                        bVal = b.vendor_payable?.vendor?.name || ''
                        break
                    default:
                        return 0
                }
                
                if (aVal < bVal) return isDesc ? 1 : -1
                if (aVal > bVal) return isDesc ? -1 : 1
                return 0
            })
        },

        sortTable(field) {
            const currentField = this.sortBy.split('_')[0]
            const currentDirection = this.sortBy.split('_')[1]
            
            if (currentField === field) {
                this.sortBy = `${field}_${currentDirection === 'desc' ? 'asc' : 'desc'}`
            } else {
                this.sortBy = `${field}_desc`
            }
            
            this.applySorting()
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.last_page) {
                this.pagination.current_page = page
                this.pagination.from = (page - 1) * 15 + 1
                this.pagination.to = Math.min(page * 15, this.pagination.total)
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

        showPaymentDetails(payment) {
            this.selectedPaymentDetails = payment
            this.showDetailsModal = true
        },

        async downloadReceipt(payment) {
            try {
                // Mock download - implement actual download logic
                this.$toast.success('Receipt downloaded successfully')
            } catch (error) {
                console.error('Error downloading receipt:', error)
                this.$toast.error('Failed to download receipt')
            }
        },

        async exportHistory() {
            this.exporting = true
            try {
                // Mock export - implement actual export logic
                await new Promise(resolve => setTimeout(resolve, 2000))
                this.$toast.success('Payment history exported successfully')
            } catch (error) {
                console.error('Error exporting history:', error)
                this.$toast.error('Failed to export payment history')
            } finally {
                this.exporting = false
            }
        },

        getMostFrequentVendor() {
            const vendorCounts = {}
            this.filteredPayments.forEach(payment => {
                const vendorName = payment.vendor_payable?.vendor?.name
                if (vendorName) {
                    vendorCounts[vendorName] = (vendorCounts[vendorName] || 0) + 1
                }
            })
            
            return Object.entries(vendorCounts).sort((a, b) => b[1] - a[1])[0]?.[0] || 'N/A'
        },

        getChartPeriod() {
            if (this.filters.from_date && this.filters.to_date) {
                return `${this.formatDate(this.filters.from_date)} - ${this.formatDate(this.filters.to_date)}`
            }
            return 'All Time'
        },

        groupBy(array, key) {
            return array.reduce((groups, item) => {
                const group = item[key] || 'unknown'
                groups[group] = groups[group] || []
                groups[group].push(item)
                return groups
            }, {})
        },

        isOverdue(dueDate) {
            return dueDate && new Date(dueDate) < new Date()
        },

        formatPaymentMethod(method) {
            const methods = {
                'cash': 'Cash',
                'bank': 'Bank Transfer',
                'check': 'Check',
                'credit_card': 'Credit Card',
                'transfer': 'Wire Transfer'
            }
            return methods[method?.toLowerCase()] || method || 'Unknown'
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
        }
    },
    watch: {
        filteredPayments() {
            this.loadStatistics()
            this.loadChartData()
        }
    }
}
</script>

<style scoped>
.payment-history-container {
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
    animation: float 40s infinite linear;
}

.orb-1 {
    width: 160px;
    height: 160px;
    top: 8%;
    left: 3%;
    animation-delay: 0s;
}

.orb-2 {
    width: 120px;
    height: 120px;
    top: 65%;
    right: 8%;
    animation-delay: -15s;
}

.orb-3 {
    width: 90px;
    height: 90px;
    bottom: 15%;
    left: 65%;
    animation-delay: -30s;
}

.orb-4 {
    width: 140px;
    height: 140px;
    top: 35%;
    right: 45%;
    animation-delay: -22s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    25% { transform: translateY(-70px) rotate(90deg); }
    50% { transform: translateY(0px) rotate(180deg); }
    75% { transform: translateY(70px) rotate(270deg); }
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

.btn-outline {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.btn-outline:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.btn-secondary {
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.filters-container {
    position: relative;
    z-index: 1;
    margin-bottom: 2rem;
}

.filters-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.filters-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filters-header h3 {
    color: white;
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.reset-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: white;
    cursor: pointer;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.reset-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.filters-grid {
    padding: 2rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.filter-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.filter-section h4 {
    color: white;
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.vendor-filter {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.vendor-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.vendor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.vendor-details {
    display: flex;
    flex-direction: column;
}

.vendor-name {
    color: white;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.vendor-email {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.date-filters {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.date-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.preset-btn {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: white;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.preset-btn:hover,
.preset-btn.active {
    background: rgba(255, 107, 107, 0.3);
    border-color: #ff6b6b;
}

.date-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.date-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.date-group label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    font-weight: 500;
}

.date-input {
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.payment-filters {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    font-weight: 500;
}

.filter-select {
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.amount-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.amount-input {
    flex: 1;
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.amount-range span {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.search-section {
    display: flex;
    gap: 1rem;
}

.search-box {
    flex: 1;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.6);
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.search-btn {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    border: none;
    border-radius: 8px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
}

.stats-overview {
    position: relative;
    z-index: 1;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
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
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-card.total-payments .stat-icon {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
}

.stat-card.payment-count .stat-icon {
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
}

.stat-card.avg-payment .stat-icon {
    background: linear-gradient(135deg, #45b7d1, #3498db);
}

.stat-card.vendors-paid .stat-icon {
    background: linear-gradient(135deg, #f9ca24, #f0932b);
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
    margin-bottom: 0.25rem;
}

.stat-change {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.stat-change.positive {
    color: #4ecdc4;
}

.stat-change.negative {
    color: #ff6b6b;
}

.stat-meta {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
}

.charts-section {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.chart-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-header h3 {
    color: white;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-period {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.period-select {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
}

.chart-content {
    padding: 2rem;
}

.method-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.method-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.method-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.method-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.method-name {
    color: white;
    font-weight: 600;
}

.method-values {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.method-amount {
    color: white;
    font-weight: 600;
}

.method-percentage {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.trend-chart {
    display: flex;
    align-items: end;
    gap: 1rem;
    height: 200px;
}

.trend-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.bar-container {
    height: 150px;
    width: 100%;
    display: flex;
    align-items: end;
    justify-content: center;
}

.bar {
    width: 30px;
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    transition: all 0.3s ease;
}

.bar:hover {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
}

.bar-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.7rem;
    text-align: center;
}

.bar-value {
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
}

.history-table {
    position: relative;
    z-index: 1;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
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

.view-options {
    display: flex;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 0.25rem;
}

.view-btn {
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.view-btn.active,
.view-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.sort-select {
    padding: 0.75rem 1rem;
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

.payments-grid {
    padding: 2rem;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

.payment-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.payment-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.1);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.payment-ref {
    color: white;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-date {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.card-vendor {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.vendor-avatar {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
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

.card-amount {
    margin-bottom: 1rem;
    text-align: center;
}

.amount-main {
    font-size: 1.8rem;
    font-weight: 700;
    color: #4ecdc4;
    margin-bottom: 0.5rem;
}

.amount-meta {
    display: flex;
    justify-content: center;
    gap: 1rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.card-invoice {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.invoice-info,
.invoice-due {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.invoice-info .label,
.invoice-due .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.invoice-info .value,
.invoice-due .value {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.invoice-due .value.overdue {
    color: #ff6b6b;
}

.card-actions {
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
    font-size: 0.9rem;
}

.action-btn.view {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.action-btn.download {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.action-btn.info {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.action-btn:hover {
    transform: scale(1.1);
}

.payments-table {
    padding: 2rem;
    overflow-x: auto;
}

.payments-table table {
    width: 100%;
    border-collapse: collapse;
}

.payments-table th {
    padding: 1rem;
    text-align: left;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    position: relative;
}

.payments-table th.sortable {
    cursor: pointer;
    user-select: none;
    transition: all 0.3s ease;
}

.payments-table th.sortable:hover {
    background: rgba(255, 255, 255, 0.05);
}

.payments-table th i {
    margin-left: 0.5rem;
    opacity: 0.5;
}

.payments-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.payment-row:hover {
    background: rgba(255, 255, 255, 0.05);
}

.vendor-cell .vendor-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.vendor-name {
    color: white;
    font-weight: 600;
}

.vendor-email {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
}

.invoice-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.invoice-number {
    color: white;
    font-weight: 600;
}

.due-date {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.due-date.overdue {
    color: #ff6b6b;
    font-weight: 600;
}

.amount-cell .amount {
    color: #4ecdc4;
    font-weight: 700;
    font-size: 1.1rem;
}

.method-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.method-badge.cash {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border-color: rgba(34, 197, 94, 0.3);
}

.method-badge.bank {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border-color: rgba(59, 130, 246, 0.3);
}

.method-badge.check {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border-color: rgba(251, 191, 36, 0.3);
}

.method-badge.credit_card {
    background: rgba(168, 85, 247, 0.2);
    color: #a855f7;
    border-color: rgba(168, 85, 247, 0.3);
}

.actions-cell .action-buttons {
    display: flex;
    gap: 0.5rem;
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

.payment-details-modal {
    max-width: 600px;
    width: 90%;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 2rem;
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
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.detail-item.full-width {
    grid-column: 1 / -1;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
}

.detail-item .label {
    color: #6b7280;
    font-weight: 500;
}

.detail-item .value {
    color: #1f2937;
    font-weight: 600;
}

.detail-item .value.highlight {
    color: #ef4444;
    font-size: 1.1rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.modal-actions .btn {
    color: #374151;
}

.modal-actions .btn-primary {
    color: white;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Responsive Design */
@media (max-width: 1400px) {
    .filters-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .charts-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1024px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .payments-grid {
        grid-template-columns: 1fr;
    }
    
    .date-inputs {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-history-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .header-actions {
        flex-wrap: wrap;
    }
    
    .title-section h1 {
        font-size: 2rem;
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
    
    .view-options {
        justify-content: center;
    }
    
    .payments-table {
        padding: 1rem;
    }
    
    .payments-table table {
        min-width: 800px;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .modal-actions .btn {
        justify-content: center;
    }
}
</style>