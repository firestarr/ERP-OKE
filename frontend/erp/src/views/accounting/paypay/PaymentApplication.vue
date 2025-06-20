<template>
    <div class="payment-application-container">
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
                        <i class="fas fa-money-check-alt"></i>
                        Payment Application
                    </h1>
                    <p class="page-subtitle">Apply payments to multiple payables and manage allocations</p>
                </div>
                <div class="header-actions">
                    <router-link to="/accounting/payable-payments" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Payments
                    </router-link>
                    <button @click="resetApplication" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Application Process -->
        <div class="application-container">
            <!-- Step Navigation -->
            <div class="step-navigation">
                <div class="step" :class="{ active: currentStep === 1, completed: currentStep > 1 }">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <h4>Select Vendor</h4>
                        <p>Choose vendor for payment application</p>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="step" :class="{ active: currentStep === 2, completed: currentStep > 2 }">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <h4>Configure Payment</h4>
                        <p>Set payment details and method</p>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="step" :class="{ active: currentStep === 3, completed: currentStep > 3 }">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <h4>Allocate to Payables</h4>
                        <p>Distribute payment across invoices</p>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="step" :class="{ active: currentStep === 4 }">
                    <div class="step-number">4</div>
                    <div class="step-info">
                        <h4>Review & Apply</h4>
                        <p>Confirm and process payment</p>
                    </div>
                </div>
            </div>

            <!-- Step Content -->
            <div class="step-content">
                <!-- Step 1: Vendor Selection -->
                <div v-if="currentStep === 1" class="step-panel">
                    <div class="panel-header">
                        <h3>
                            <i class="fas fa-building"></i>
                            Select Vendor
                        </h3>
                        <p>Choose the vendor you want to make payment to</p>
                    </div>
                    
                    <div class="vendor-selection">
                        <div class="search-section">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" v-model="vendorSearch" placeholder="Search vendors..." class="search-input">
                            </div>
                            <div class="filters">
                                <select v-model="vendorFilter" class="filter-select">
                                    <option value="">All Vendors</option>
                                    <option value="active">Active Only</option>
                                    <option value="with_payables">With Outstanding Payables</option>
                                </select>
                            </div>
                        </div>

                        <div class="vendor-grid">
                            <div v-for="vendor in filteredVendors" :key="vendor.id" 
                                 class="vendor-card" 
                                 :class="{ selected: selectedVendor?.id === vendor.id }"
                                 @click="selectVendor(vendor)">
                                <div class="vendor-info">
                                    <div class="vendor-avatar">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div class="vendor-details">
                                        <h4>{{ vendor.name }}</h4>
                                        <p>{{ vendor.email || 'No email' }}</p>
                                        <div class="vendor-stats">
                                            <span class="stat">
                                                <i class="fas fa-file-invoice"></i>
                                                {{ vendor.outstanding_payables || 0 }} Outstanding
                                            </span>
                                            <span class="stat">
                                                <i class="fas fa-dollar-sign"></i>
                                                {{ formatCurrency(vendor.total_outstanding || 0) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="selection-indicator">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button @click="nextStep" :disabled="!selectedVendor" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i>
                                Continue to Payment Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Payment Configuration -->
                <div v-if="currentStep === 2" class="step-panel">
                    <div class="panel-header">
                        <h3>
                            <i class="fas fa-credit-card"></i>
                            Payment Configuration
                        </h3>
                        <p>Configure payment details for {{ selectedVendor?.name }}</p>
                    </div>

                    <div class="payment-config">
                        <div class="config-grid">
                            <div class="config-section">
                                <h4>Payment Information</h4>
                                <div class="form-group">
                                    <label class="form-label required">Payment Date</label>
                                    <input type="date" v-model="paymentConfig.payment_date" class="form-input" :max="today">
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Payment Method</label>
                                    <select v-model="paymentConfig.payment_method" class="form-select">
                                        <option value="bank">Bank Transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="credit_card">Credit Card</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" v-model="paymentConfig.reference_number" 
                                           placeholder="Transaction ID, Check number, etc." class="form-input">
                                </div>
                            </div>

                            <div class="config-section">
                                <h4>Amount & Currency</h4>
                                <div class="form-group">
                                    <label class="form-label required">Total Payment Amount</label>
                                    <div class="amount-input-group">
                                        <span class="currency-symbol">$</span>
                                        <input type="number" v-model="paymentConfig.total_amount" 
                                               step="0.01" min="0" class="form-input amount-input" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Payment Currency</label>
                                    <select v-model="paymentConfig.currency" class="form-select">
                                        <option value="USD">USD - US Dollar</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="IDR">IDR - Indonesian Rupiah</option>
                                        <option value="SGD">SGD - Singapore Dollar</option>
                                    </select>
                                </div>
                                <div v-if="paymentConfig.currency !== 'USD'" class="form-group">
                                    <label class="form-label">Exchange Rate ({{ paymentConfig.currency }} to USD)</label>
                                    <input type="number" v-model="paymentConfig.exchange_rate" 
                                           step="0.0001" min="0" class="form-input" placeholder="1.0000">
                                </div>
                            </div>

                            <div class="config-section">
                                <h4>Account Details</h4>
                                <div class="form-group">
                                    <label class="form-label required">Cash/Bank Account</label>
                                    <select v-model="paymentConfig.cash_account_id" class="form-select">
                                        <option value="">Select Account</option>
                                        <option v-for="account in cashAccounts" :key="account.id" :value="account.id">
                                            {{ account.account_name }} - {{ account.account_number }}
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Exchange Gain/Loss Account</label>
                                    <select v-model="paymentConfig.exchange_account_id" class="form-select">
                                        <option value="">Select Account (Optional)</option>
                                        <option v-for="account in exchangeAccounts" :key="account.id" :value="account.id">
                                            {{ account.account_name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button @click="prevStep" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i>
                                Back to Vendor
                            </button>
                            <button @click="nextStep" :disabled="!isPaymentConfigValid" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i>
                                Continue to Allocation
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Payment Allocation -->
                <div v-if="currentStep === 3" class="step-panel">
                    <div class="panel-header">
                        <h3>
                            <i class="fas fa-distribute-spacing"></i>
                            Payment Allocation
                        </h3>
                        <p>Distribute {{ formatCurrency(paymentConfig.total_amount) }} across outstanding payables</p>
                    </div>

                    <div class="allocation-section">
                        <!-- Allocation Summary -->
                        <div class="allocation-summary">
                            <div class="summary-cards">
                                <div class="summary-card">
                                    <div class="card-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-value">{{ formatCurrency(paymentConfig.total_amount) }}</div>
                                        <div class="card-label">Total Payment</div>
                                    </div>
                                </div>
                                <div class="summary-card">
                                    <div class="card-icon">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-value">{{ formatCurrency(totalAllocated) }}</div>
                                        <div class="card-label">Allocated</div>
                                    </div>
                                </div>
                                <div class="summary-card">
                                    <div class="card-icon">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-value" :class="{ 'negative': remainingAmount < 0 }">
                                            {{ formatCurrency(remainingAmount) }}
                                        </div>
                                        <div class="card-label">Remaining</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="allocation-actions">
                                <button @click="autoAllocate" class="btn btn-secondary">
                                    <i class="fas fa-magic"></i>
                                    Auto Allocate
                                </button>
                                <button @click="clearAllocations" class="btn btn-outline">
                                    <i class="fas fa-eraser"></i>
                                    Clear All
                                </button>
                            </div>
                        </div>

                        <!-- Payables List -->
                        <div class="payables-list">
                            <div class="list-header">
                                <h4>Outstanding Payables</h4>
                                <div class="list-controls">
                                    <select v-model="sortBy" class="sort-select">
                                        <option value="due_date">Sort by Due Date</option>
                                        <option value="amount">Sort by Amount</option>
                                        <option value="balance">Sort by Balance</option>
                                        <option value="invoice_date">Sort by Invoice Date</option>
                                    </select>
                                </div>
                            </div>

                            <div class="payables-grid">
                                <div v-for="payable in sortedPayables" :key="payable.payable_id" class="payable-card">
                                    <div class="payable-header">
                                        <div class="invoice-info">
                                            <h5>Invoice #{{ payable.vendor_invoice?.invoice_number || payable.payable_id }}</h5>
                                            <span class="invoice-date">{{ formatDate(payable.vendor_invoice?.invoice_date) }}</span>
                                        </div>
                                        <div class="due-status" :class="{ overdue: isOverdue(payable.due_date) }">
                                            <i :class="isOverdue(payable.due_date) ? 'fas fa-exclamation-triangle' : 'fas fa-calendar'"></i>
                                            <span>{{ formatDate(payable.due_date) }}</span>
                                        </div>
                                    </div>

                                    <div class="payable-amounts">
                                        <div class="amount-info">
                                            <div class="amount-item">
                                                <span class="label">Total:</span>
                                                <span class="value">{{ formatCurrency(payable.amount) }}</span>
                                            </div>
                                            <div class="amount-item">
                                                <span class="label">Paid:</span>
                                                <span class="value paid">{{ formatCurrency(payable.paid_amount) }}</span>
                                            </div>
                                            <div class="amount-item">
                                                <span class="label">Balance:</span>
                                                <span class="value outstanding">{{ formatCurrency(payable.balance) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="allocation-controls">
                                        <div class="allocation-input">
                                            <label>Allocate Amount:</label>
                                            <div class="input-group">
                                                <input type="number" 
                                                       v-model="allocations[payable.payable_id]" 
                                                       :max="payable.balance"
                                                       step="0.01" 
                                                       min="0"
                                                       class="allocation-amount"
                                                       @input="updateAllocation(payable.payable_id)">
                                                <button @click="allocateFullAmount(payable)" class="full-btn">
                                                    Full
                                                </button>
                                            </div>
                                        </div>
                                        <div class="allocation-percentage">
                                            {{ getAllocationPercentage(payable.payable_id) }}% of payment
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button @click="prevStep" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i>
                                Back to Payment Config
                            </button>
                            <button @click="nextStep" :disabled="!isAllocationValid" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i>
                                Review & Apply
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Review & Apply -->
                <div v-if="currentStep === 4" class="step-panel">
                    <div class="panel-header">
                        <h3>
                            <i class="fas fa-check-circle"></i>
                            Review & Apply Payment
                        </h3>
                        <p>Review all details before processing the payment</p>
                    </div>

                    <div class="review-section">
                        <!-- Payment Summary -->
                        <div class="review-card">
                            <h4>Payment Summary</h4>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="label">Vendor:</span>
                                    <span class="value">{{ selectedVendor?.name }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Payment Date:</span>
                                    <span class="value">{{ formatDate(paymentConfig.payment_date) }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Payment Method:</span>
                                    <span class="value">{{ formatPaymentMethod(paymentConfig.payment_method) }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Reference:</span>
                                    <span class="value">{{ paymentConfig.reference_number || 'N/A' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Total Amount:</span>
                                    <span class="value highlight">{{ formatCurrency(paymentConfig.total_amount) }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Currency:</span>
                                    <span class="value">{{ paymentConfig.currency }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Allocation Review -->
                        <div class="review-card">
                            <h4>Payment Allocation</h4>
                            <div class="allocation-review">
                                <div v-for="(amount, payableId) in allocations" :key="payableId" class="allocation-item">
                                    <div class="allocation-info">
                                        <span class="invoice-ref">
                                            Invoice #{{ getPayableInvoiceNumber(payableId) }}
                                        </span>
                                        <span class="allocation-amount">{{ formatCurrency(amount) }}</span>
                                    </div>
                                    <div class="allocation-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" :style="{ width: getAllocationPercentage(payableId) + '%' }"></div>
                                        </div>
                                        <span class="percentage">{{ getAllocationPercentage(payableId) }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Final Confirmation -->
                        <div class="confirmation-section">
                            <div class="confirmation-checkbox">
                                <input type="checkbox" id="confirm-payment" v-model="confirmPayment">
                                <label for="confirm-payment">
                                    I confirm that all payment details are correct and authorize this payment to be processed.
                                </label>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button @click="prevStep" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i>
                                Back to Allocation
                            </button>
                            <button @click="processPayment" :disabled="!confirmPayment || processing" class="btn btn-primary">
                                <i class="fas fa-check"></i>
                                {{ processing ? 'Processing...' : 'Process Payment' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Processing Modal -->
        <transition name="fade">
            <div v-if="processing" class="processing-modal">
                <div class="processing-content">
                    <div class="processing-spinner"></div>
                    <h3>Processing Payment...</h3>
                    <p>Please wait while we process your payment application.</p>
                    <div class="processing-steps">
                        <div v-for="step in processingSteps" :key="step.id" 
                             class="processing-step" :class="{ completed: step.completed, active: step.active }">
                            <i :class="step.completed ? 'fas fa-check' : 'fas fa-circle'"></i>
                            <span>{{ step.text }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios'

export default {
    name: 'PaymentApplication',
    data() {
        return {
            currentStep: 1,
            selectedVendor: null,
            vendors: [],
            payables: [],
            cashAccounts: [],
            exchangeAccounts: [],
            vendorSearch: '',
            vendorFilter: '',
            sortBy: 'due_date',
            allocations: {},
            confirmPayment: false,
            processing: false,
            processingSteps: [
                { id: 1, text: 'Validating payment details', completed: false, active: false },
                { id: 2, text: 'Creating payment records', completed: false, active: false },
                { id: 3, text: 'Updating payable balances', completed: false, active: false },
                { id: 4, text: 'Generating journal entries', completed: false, active: false },
                { id: 5, text: 'Finalizing payment', completed: false, active: false }
            ],
            paymentConfig: {
                payment_date: new Date().toISOString().split('T')[0],
                payment_method: 'bank',
                reference_number: '',
                total_amount: '',
                currency: 'USD',
                exchange_rate: 1.0000,
                cash_account_id: '',
                exchange_account_id: ''
            }
        }
    },
    computed: {
        today() {
            return new Date().toISOString().split('T')[0]
        },
        filteredVendors() {
            let filtered = this.vendors
            
            if (this.vendorSearch) {
                filtered = filtered.filter(vendor => 
                    vendor.name.toLowerCase().includes(this.vendorSearch.toLowerCase()) ||
                    vendor.email?.toLowerCase().includes(this.vendorSearch.toLowerCase())
                )
            }
            
            if (this.vendorFilter === 'active') {
                filtered = filtered.filter(vendor => vendor.status === 'active')
            } else if (this.vendorFilter === 'with_payables') {
                filtered = filtered.filter(vendor => vendor.outstanding_payables > 0)
            }
            
            return filtered
        },
        sortedPayables() {
            if (!this.payables.length) return []
            
            return [...this.payables].sort((a, b) => {
                switch (this.sortBy) {
                    case 'due_date':
                        return new Date(a.due_date) - new Date(b.due_date)
                    case 'amount':
                        return b.amount - a.amount
                    case 'balance':
                        return b.balance - a.balance
                    case 'invoice_date':
                        return new Date(b.vendor_invoice?.invoice_date || b.created_at) - 
                               new Date(a.vendor_invoice?.invoice_date || a.created_at)
                    default:
                        return 0
                }
            })
        },
        totalAllocated() {
            return Object.values(this.allocations).reduce((sum, amount) => sum + (parseFloat(amount) || 0), 0)
        },
        remainingAmount() {
            return (this.paymentConfig.total_amount || 0) - this.totalAllocated
        },
        isPaymentConfigValid() {
            return this.paymentConfig.payment_date &&
                   this.paymentConfig.payment_method &&
                   this.paymentConfig.total_amount > 0 &&
                   this.paymentConfig.cash_account_id
        },
        isAllocationValid() {
            return this.totalAllocated > 0 && 
                   Math.abs(this.remainingAmount) < 0.01 // Allow for rounding differences
        }
    },
    async mounted() {
        await this.loadInitialData()
    },
    methods: {
        async loadInitialData() {
            try {
                const [vendorsRes, accountsRes] = await Promise.all([
                    axios.get('/api/vendors'),
                    axios.get('/api/accounting/chart-of-accounts')
                ])
                
                this.vendors = vendorsRes.data.data || vendorsRes.data
                const accounts = accountsRes.data.data || accountsRes.data
                
                this.cashAccounts = accounts.filter(acc => 
                    acc.account_type?.toLowerCase().includes('cash') || 
                    acc.account_type?.toLowerCase().includes('bank')
                )
                
                this.exchangeAccounts = accounts.filter(acc => 
                    acc.account_type?.toLowerCase().includes('exchange') || 
                    acc.account_name?.toLowerCase().includes('exchange')
                )
                
                // Load vendor stats
                await this.loadVendorStats()
            } catch (error) {
                console.error('Error loading initial data:', error)
                this.$toast.error('Failed to load data')
            }
        },

        async loadVendorStats() {
            try {
                for (const vendor of this.vendors) {
                    const response = await axios.get('/api/accounting/vendor-payables', {
                        params: { vendor_id: vendor.id, status: 'Open' }
                    })
                    const payables = response.data.data || response.data
                    vendor.outstanding_payables = payables.length
                    vendor.total_outstanding = payables.reduce((sum, p) => sum + p.balance, 0)
                }
            } catch (error) {
                console.warn('Could not load vendor stats:', error)
            }
        },

        async selectVendor(vendor) {
            this.selectedVendor = vendor
            await this.loadVendorPayables()
        },

        async loadVendorPayables() {
            if (!this.selectedVendor) return
            
            try {
                const response = await axios.get('/api/accounting/vendor-payables', {
                    params: {
                        vendor_id: this.selectedVendor.id,
                        status: 'Open'
                    }
                })
                this.payables = response.data.data || response.data
                this.resetAllocations()
            } catch (error) {
                console.error('Error loading payables:', error)
                this.$toast.error('Failed to load vendor payables')
            }
        },

        nextStep() {
            if (this.currentStep < 4) {
                this.currentStep++
            }
        },

        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--
            }
        },

        resetApplication() {
            this.currentStep = 1
            this.selectedVendor = null
            this.payables = []
            this.allocations = {}
            this.confirmPayment = false
            this.paymentConfig = {
                payment_date: new Date().toISOString().split('T')[0],
                payment_method: 'bank',
                reference_number: '',
                total_amount: '',
                currency: 'USD',
                exchange_rate: 1.0000,
                cash_account_id: '',
                exchange_account_id: ''
            }
        },

        resetAllocations() {
            this.allocations = {}
            this.payables.forEach(payable => {
                this.$set(this.allocations, payable.payable_id, 0)
            })
        },

        updateAllocation(payableId) {
            const amount = parseFloat(this.allocations[payableId]) || 0
            const payable = this.payables.find(p => p.payable_id == payableId)
            
            if (amount > payable.balance) {
                this.$set(this.allocations, payableId, payable.balance)
                this.$toast.warning('Amount cannot exceed payable balance')
            }
        },

        allocateFullAmount(payable) {
            const remaining = this.remainingAmount
            const maxAllocation = Math.min(payable.balance, remaining)
            this.$set(this.allocations, payable.payable_id, maxAllocation)
        },

        autoAllocate() {
            this.resetAllocations()
            let remainingAmount = this.paymentConfig.total_amount
            
            // Sort by due date (oldest first) for auto allocation
            const sortedByDue = [...this.payables].sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
            
            for (const payable of sortedByDue) {
                if (remainingAmount <= 0) break
                
                const allocation = Math.min(payable.balance, remainingAmount)
                this.$set(this.allocations, payable.payable_id, allocation)
                remainingAmount -= allocation
            }
        },

        clearAllocations() {
            this.resetAllocations()
        },

        getAllocationPercentage(payableId) {
            const amount = this.allocations[payableId] || 0
            const total = this.paymentConfig.total_amount || 1
            return Math.round((amount / total) * 100)
        },

        getPayableInvoiceNumber(payableId) {
            const payable = this.payables.find(p => p.payable_id == payableId)
            return payable?.vendor_invoice?.invoice_number || payableId
        },

        async processPayment() {
            this.processing = true
            
            try {
                // Simulate processing steps
                for (let i = 0; i < this.processingSteps.length; i++) {
                    this.processingSteps[i].active = true
                    await new Promise(resolve => setTimeout(resolve, 1000))
                    this.processingSteps[i].completed = true
                    this.processingSteps[i].active = false
                }

                // Create payment applications
                const applications = Object.entries(this.allocations)
                    .filter(([_, amount]) => amount > 0)
                    .map(([payableId, amount]) => ({
                        payable_id: payableId,
                        amount: amount,
                        ...this.paymentConfig
                    }))

                const promises = applications.map(application => 
                    axios.post('/api/accounting/payable-payments', application)
                )

                await Promise.all(promises)

                this.$toast.success('Payment application processed successfully!')
                this.$router.push('/accounting/payable-payments')
            } catch (error) {
                console.error('Error processing payment:', error)
                this.$toast.error('Failed to process payment application')
            } finally {
                this.processing = false
            }
        },

        isOverdue(dueDate) {
            return new Date(dueDate) < new Date()
        },

        formatPaymentMethod(method) {
            const methods = {
                'cash': 'Cash Payment',
                'bank': 'Bank Transfer',
                'check': 'Check Payment',
                'credit_card': 'Credit Card'
            }
            return methods[method] || method
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
        'paymentConfig.currency'() {
            if (this.paymentConfig.currency !== 'USD') {
                // Fetch exchange rate
                this.fetchExchangeRate()
            } else {
                this.paymentConfig.exchange_rate = 1.0000
            }
        }
    }
}
</script>

<style scoped>
.payment-application-container {
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
    animation: float 35s infinite linear;
}

.orb-1 {
    width: 150px;
    height: 150px;
    top: 10%;
    left: 5%;
    animation-delay: 0s;
}

.orb-2 {
    width: 100px;
    height: 100px;
    top: 70%;
    right: 10%;
    animation-delay: -12s;
}

.orb-3 {
    width: 80px;
    height: 80px;
    bottom: 20%;
    left: 70%;
    animation-delay: -24s;
}

.orb-4 {
    width: 120px;
    height: 120px;
    top: 40%;
    right: 50%;
    animation-delay: -18s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    25% { transform: translateY(-60px) rotate(90deg); }
    50% { transform: translateY(0px) rotate(180deg); }
    75% { transform: translateY(60px) rotate(270deg); }
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

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.application-container {
    position: relative;
    z-index: 1;
}

.step-navigation {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 3rem;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.step {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    max-width: 200px;
}

.step-number {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.7);
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
}

.step.completed .step-number {
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    color: white;
}

.step-info h4 {
    color: white;
    font-size: 1rem;
    margin: 0 0 0.25rem 0;
}

.step-info p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
    margin: 0;
}

.step-connector {
    flex: 1;
    height: 2px;
    background: rgba(255, 255, 255, 0.2);
    margin: 0 1rem;
}

.step-content {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.step-panel {
    padding: 2rem;
}

.panel-header {
    text-align: center;
    margin-bottom: 2rem;
}

.panel-header h3 {
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.panel-header p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.vendor-selection {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.search-section {
    display: flex;
    gap: 1rem;
    align-items: center;
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

.filter-select {
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.vendor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.vendor-card {
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.vendor-card:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-5px);
}

.vendor-card.selected {
    background: rgba(255, 107, 107, 0.1);
    border-color: #ff6b6b;
    box-shadow: 0 5px 20px rgba(255, 107, 107, 0.2);
}

.vendor-info {
    display: flex;
    gap: 1rem;
    align-items: center;
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

.vendor-details h4 {
    color: white;
    font-size: 1.1rem;
    margin: 0 0 0.25rem 0;
}

.vendor-details p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    margin: 0 0 0.5rem 0;
}

.vendor-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.stat {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
}

.selection-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #4ecdc4;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: all 0.3s ease;
}

.vendor-card.selected .selection-indicator {
    opacity: 1;
}

.step-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.payment-config {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.config-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.config-section h4 {
    color: white;
    font-size: 1.1rem;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: white;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-label.required::after {
    content: '*';
    color: #ff6b6b;
    margin-left: 0.25rem;
}

.form-input,
.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: rgba(255, 107, 107, 0.5);
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
}

.form-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.amount-input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.currency-symbol {
    position: absolute;
    left: 1rem;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
    z-index: 1;
}

.amount-input {
    padding-left: 2.5rem !important;
}

.allocation-section {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.allocation-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.summary-cards {
    display: flex;
    gap: 2rem;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-icon {
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

.card-content {
    display: flex;
    flex-direction: column;
}

.card-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    margin-bottom: 0.25rem;
}

.card-value.negative {
    color: #ff6b6b;
}

.card-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.allocation-actions {
    display: flex;
    gap: 1rem;
}

.payables-list {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.list-header h4 {
    color: white;
    font-size: 1.2rem;
    margin: 0;
}

.sort-select {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.payables-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.payable-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.payable-card:hover {
    background: rgba(255, 255, 255, 0.08);
}

.payable-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.invoice-info h5 {
    color: white;
    font-size: 1rem;
    margin: 0 0 0.25rem 0;
}

.invoice-date {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.due-status {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
}

.due-status.overdue {
    color: #ff6b6b;
}

.payable-amounts {
    margin-bottom: 1rem;
}

.amount-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.amount-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.amount-item .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.amount-item .value {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.amount-item .value.paid {
    color: #4ecdc4;
}

.amount-item .value.outstanding {
    color: #fbbf24;
}

.allocation-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.allocation-input {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.allocation-input label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
}

.input-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.allocation-amount {
    width: 120px;
    padding: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    text-align: right;
}

.full-btn {
    padding: 0.5rem 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    color: white;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.full-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.allocation-percentage {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.review-section {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.review-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.review-card h4 {
    color: white;
    font-size: 1.1rem;
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.summary-item .value {
    color: white;
    font-weight: 600;
}

.summary-item .value.highlight {
    color: #4ecdc4;
    font-size: 1.1rem;
    font-weight: 700;
}

.allocation-review {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.allocation-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.allocation-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.invoice-ref {
    color: white;
    font-weight: 600;
}

.allocation-amount {
    color: #4ecdc4;
    font-weight: 700;
}

.allocation-progress {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar {
    flex: 1;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.percentage {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
    font-weight: 600;
    min-width: 40px;
    text-align: right;
}

.confirmation-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.confirmation-checkbox {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.confirmation-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #4ecdc4;
}

.confirmation-checkbox label {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
    line-height: 1.4;
    cursor: pointer;
}

.processing-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.processing-content {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 3rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-width: 500px;
    width: 90%;
}

.processing-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid #4ecdc4;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 2rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.processing-content h3 {
    color: white;
    font-size: 1.5rem;
    margin: 0 0 1rem 0;
}

.processing-content p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0 0 2rem 0;
}

.processing-steps {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    text-align: left;
}

.processing-step {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    transition: all 0.3s ease;
}

.processing-step.active {
    color: #4ecdc4;
}

.processing-step.completed {
    color: white;
}

.processing-step i {
    width: 16px;
    text-align: center;
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
@media (max-width: 1200px) {
    .step-navigation {
        flex-direction: column;
        gap: 1rem;
    }
    
    .step-connector {
        width: 2px;
        height: 20px;
        margin: 0;
    }
    
    .config-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        flex-direction: column;
        gap: 1rem;
    }
    
    .allocation-summary {
        flex-direction: column;
        gap: 1.5rem;
        align-items: stretch;
    }
}

@media (max-width: 768px) {
    .payment-application-container {
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
    
    .vendor-grid {
        grid-template-columns: 1fr;
    }
    
    .search-section {
        flex-direction: column;
        gap: 1rem;
    }
    
    .step {
        max-width: none;
    }
    
    .step-info {
        text-align: center;
    }
    
    .amount-info {
        grid-template-columns: 1fr;
    }
    
    .allocation-controls {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .input-group {
        justify-content: space-between;
    }
    
    .allocation-amount {
        width: auto;
        flex: 1;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .step-actions {
        flex-direction: column;
    }
    
    .step-actions .btn {
        justify-content: center;
    }
}
</style>