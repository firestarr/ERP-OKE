<template>
    <div class="payment-detail-container">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="floating-orbs">
                <div class="orb orb-1"></div>
                <div class="orb orb-2"></div>
                <div class="orb orb-3"></div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
            <div class="loading-spinner"></div>
            <p>Loading payment details...</p>
        </div>

        <!-- Payment Detail Content -->
        <div v-else-if="payment" class="detail-content">
            <!-- Header Section -->
            <div class="page-header">
                <div class="header-content">
                    <div class="title-section">
                        <div class="payment-status-badge" :class="getStatusClass(payment)">
                            <i :class="getStatusIcon(payment)"></i>
                            {{ payment.status || 'Completed' }}
                        </div>
                        <h1 class="page-title">
                            Payment #{{ payment.reference_number || payment.payment_id }}
                        </h1>
                        <p class="page-subtitle">
                            Payment to {{ payment.vendor_payable?.vendor?.name }}
                        </p>
                    </div>
                    <div class="header-actions">
                        <router-link to="/accounting/payable-payments" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i>
                            Back to List
                        </router-link>
                        <router-link :to="`/accounting/payable-payments/${payment.payment_id}/edit`" class="btn btn-secondary">
                            <i class="fas fa-edit"></i>
                            Edit Payment
                        </router-link>
                        <button @click="printPayment" class="btn btn-primary">
                            <i class="fas fa-print"></i>
                            Print Receipt
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Left Column -->
                <div class="left-column">
                    <!-- Payment Overview -->
                    <div class="detail-card payment-overview">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-money-check-alt"></i>
                                Payment Overview
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="overview-amount">
                                <div class="amount-display">
                                    <span class="currency">{{ payment.payment_currency || 'USD' }}</span>
                                    <span class="amount">{{ formatCurrency(payment.amount) }}</span>
                                </div>
                                <div class="amount-details">
                                    <div v-if="payment.payment_currency !== 'USD'" class="exchange-info">
                                        <span class="label">Exchange Rate:</span>
                                        <span class="value">{{ payment.exchange_rate || '1.0000' }}</span>
                                    </div>
                                    <div v-if="payment.payment_currency !== 'USD'" class="usd-equivalent">
                                        <span class="label">USD Equivalent:</span>
                                        <span class="value">{{ formatCurrency(getUSDEquivalent()) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-method-display">
                                <div class="method-icon">
                                    <i :class="getMethodIcon(payment.payment_method)"></i>
                                </div>
                                <div class="method-details">
                                    <h4>{{ formatPaymentMethod(payment.payment_method) }}</h4>
                                    <p v-if="payment.reference_number">Ref: {{ payment.reference_number }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vendor Information -->
                    <div class="detail-card vendor-info">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-building"></i>
                                Vendor Information
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="vendor-details">
                                <div class="vendor-avatar">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="vendor-data">
                                    <h4>{{ payment.vendor_payable?.vendor?.name }}</h4>
                                    <div class="vendor-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-envelope"></i>
                                            <span>{{ payment.vendor_payable?.vendor?.email || 'N/A' }}</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-phone"></i>
                                            <span>{{ payment.vendor_payable?.vendor?.phone || 'N/A' }}</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>{{ formatAddress(payment.vendor_payable?.vendor) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Timeline -->
                    <div class="detail-card payment-timeline">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-history"></i>
                                Payment Timeline
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="timeline">
                                <div class="timeline-item completed">
                                    <div class="timeline-marker">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h4>Payment Recorded</h4>
                                        <p>{{ formatDateTime(payment.created_at || payment.payment_date) }}</p>
                                        <small>Payment entry created in system</small>
                                    </div>
                                </div>
                                <div class="timeline-item completed">
                                    <div class="timeline-marker">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h4>Payment Processed</h4>
                                        <p>{{ formatDateTime(payment.payment_date) }}</p>
                                        <small>{{ formatPaymentMethod(payment.payment_method) }} payment executed</small>
                                    </div>
                                </div>
                                <div class="timeline-item completed">
                                    <div class="timeline-marker">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h4>Payable Updated</h4>
                                        <p>{{ formatDateTime(payment.payment_date) }}</p>
                                        <small>Outstanding balance reduced</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-column">
                    <!-- Related Invoice -->
                    <div class="detail-card invoice-details">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-file-invoice"></i>
                                Related Invoice
                            </h3>
                            <router-link v-if="payment.vendor_payable?.vendor_invoice" 
                                       :to="`/purchasing/vendor-invoices/${payment.vendor_payable.vendor_invoice.id}`" 
                                       class="view-link">
                                <i class="fas fa-external-link-alt"></i>
                                View Invoice
                            </router-link>
                        </div>
                        <div class="card-content">
                            <div v-if="payment.vendor_payable?.vendor_invoice" class="invoice-summary">
                                <div class="invoice-header">
                                    <div class="invoice-number">
                                        <strong>Invoice #{{ payment.vendor_payable.vendor_invoice.invoice_number }}</strong>
                                    </div>
                                    <div class="invoice-date">
                                        {{ formatDate(payment.vendor_payable.vendor_invoice.invoice_date) }}
                                    </div>
                                </div>
                                
                                <div class="invoice-amounts">
                                    <div class="amount-row">
                                        <span class="label">Total Amount:</span>
                                        <span class="value">{{ formatCurrency(payment.vendor_payable.amount) }}</span>
                                    </div>
                                    <div class="amount-row">
                                        <span class="label">Paid Amount:</span>
                                        <span class="value paid">{{ formatCurrency(payment.vendor_payable.paid_amount) }}</span>
                                    </div>
                                    <div class="amount-row total">
                                        <span class="label">Outstanding:</span>
                                        <span class="value">{{ formatCurrency(payment.vendor_payable.balance) }}</span>
                                    </div>
                                </div>

                                <div class="due-date-info" :class="{ overdue: isOverdue(payment.vendor_payable.due_date) }">
                                    <i :class="isOverdue(payment.vendor_payable.due_date) ? 'fas fa-exclamation-triangle' : 'fas fa-calendar'"></i>
                                    <span>Due: {{ formatDate(payment.vendor_payable.due_date) }}</span>
                                    <span v-if="isOverdue(payment.vendor_payable.due_date)" class="overdue-label">OVERDUE</span>
                                </div>
                            </div>
                            <div v-else class="no-invoice">
                                <i class="fas fa-info-circle"></i>
                                <p>No related invoice information available</p>
                            </div>
                        </div>
                    </div>

                    <!-- Accounting Information -->
                    <div class="detail-card accounting-info">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-calculator"></i>
                                Accounting Details
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="accounting-grid">
                                <div class="account-item">
                                    <label>Payment Date</label>
                                    <span>{{ formatDate(payment.payment_date) }}</span>
                                </div>
                                <div class="account-item">
                                    <label>Accounting Period</label>
                                    <span>{{ getAccountingPeriod(payment.payment_date) }}</span>
                                </div>
                                <div class="account-item">
                                    <label>Cash Account</label>
                                    <span>{{ payment.cash_account?.account_name || 'N/A' }}</span>
                                </div>
                                <div class="account-item">
                                    <label>Exchange Account</label>
                                    <span>{{ payment.exchange_account?.account_name || 'N/A' }}</span>
                                </div>
                                <div v-if="payment.exchange_difference" class="account-item">
                                    <label>Exchange Difference</label>
                                    <span :class="{ 'gain': payment.exchange_difference > 0, 'loss': payment.exchange_difference < 0 }">
                                        {{ formatCurrency(Math.abs(payment.exchange_difference)) }}
                                        {{ payment.exchange_difference > 0 ? '(Gain)' : '(Loss)' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Journal Entry -->
                    <div class="detail-card journal-entry">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-book"></i>
                                Journal Entry
                            </h3>
                            <button @click="loadJournalEntry" class="refresh-btn" :disabled="loadingJournal">
                                <i class="fas fa-sync-alt" :class="{ 'fa-spin': loadingJournal }"></i>
                            </button>
                        </div>
                        <div class="card-content">
                            <div v-if="loadingJournal" class="loading-journal">
                                <div class="loading-spinner-small"></div>
                                <span>Loading journal entry...</span>
                            </div>
                            <div v-else-if="journalEntry" class="journal-display">
                                <div class="journal-header">
                                    <div class="journal-number">JE #{{ journalEntry.journal_number }}</div>
                                    <div class="journal-date">{{ formatDate(journalEntry.entry_date) }}</div>
                                </div>
                                <div class="journal-lines">
                                    <div class="line-header">
                                        <span class="account-col">Account</span>
                                        <span class="debit-col">Debit</span>
                                        <span class="credit-col">Credit</span>
                                    </div>
                                    <div v-for="line in journalEntry.lines" :key="line.id" class="journal-line">
                                        <span class="account-name">{{ line.account?.account_name }}</span>
                                        <span class="debit-amount">{{ line.debit_amount ? formatCurrency(line.debit_amount) : '-' }}</span>
                                        <span class="credit-amount">{{ line.credit_amount ? formatCurrency(line.credit_amount) : '-' }}</span>
                                    </div>
                                    <div class="journal-totals">
                                        <span class="total-label">Totals:</span>
                                        <span class="total-debit">{{ formatCurrency(getTotalDebits()) }}</span>
                                        <span class="total-credit">{{ formatCurrency(getTotalCredits()) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="no-journal">
                                <i class="fas fa-info-circle"></i>
                                <p>No journal entry found for this payment</p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div v-if="payment.notes" class="detail-card payment-notes">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-sticky-note"></i>
                                Notes
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="notes-content">
                                {{ payment.notes }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Payment Not Found</h3>
            <p>The payment you're looking for doesn't exist or has been deleted.</p>
            <router-link to="/accounting/payable-payments" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Back to Payments
            </router-link>
        </div>
    </div>
</template>

<script>
import axios from 'axios'

export default {
    name: 'PayablePaymentDetail',
    props: {
        paymentId: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            payment: null,
            journalEntry: null,
            loading: true,
            loadingJournal: false
        }
    },
    async mounted() {
        await this.loadPayment()
        await this.loadJournalEntry()
    },
    methods: {
        async loadPayment() {
            this.loading = true
            try {
                const response = await axios.get(`/accounting/payable-payments/${this.paymentId}`)
                this.payment = response.data.data
            } catch (error) {
                console.error('Error loading payment:', error)
                this.$toast.error('Failed to load payment details')
            } finally {
                this.loading = false
            }
        },

        async loadJournalEntry() {
            if (!this.payment) return
            
            this.loadingJournal = true
            try {
                const response = await axios.get('/accounting/journal-entries', {
                    params: {
                        reference_type: 'PayablePayment',
                        reference_id: this.payment.payment_id
                    }
                })
                this.journalEntry = response.data.data?.[0] || null
            } catch (error) {
                console.error('Error loading journal entry:', error)
            } finally {
                this.loadingJournal = false
            }
        },

        printPayment() {
            window.print()
        },

        getUSDEquivalent() {
            if (this.payment.payment_currency === 'USD') {
                return this.payment.amount
            }
            return this.payment.amount * (this.payment.exchange_rate || 1)
        },

        getTotalDebits() {
            if (!this.journalEntry?.lines) return 0
            return this.journalEntry.lines.reduce((sum, line) => sum + (line.debit_amount || 0), 0)
        },

        getTotalCredits() {
            if (!this.journalEntry?.lines) return 0
            return this.journalEntry.lines.reduce((sum, line) => sum + (line.credit_amount || 0), 0)
        },

        getAccountingPeriod(date) {
            const paymentDate = new Date(date)
            const month = paymentDate.toLocaleDateString('en-US', { month: 'long' })
            const year = paymentDate.getFullYear()
            return `${month} ${year}`
        },

        isOverdue(dueDate) {
            return new Date(dueDate) < new Date()
        },

        formatAddress(vendor) {
            if (!vendor) return 'N/A'
            
            const parts = [
                vendor.address,
                vendor.city,
                vendor.state,
                vendor.country
            ].filter(Boolean)
            
            return parts.length > 0 ? parts.join(', ') : 'N/A'
        },

        formatPaymentMethod(method) {
            const methods = {
                'cash': 'Cash Payment',
                'bank': 'Bank Transfer',
                'check': 'Check Payment',
                'credit_card': 'Credit Card',
                'transfer': 'Wire Transfer'
            }
            return methods[method?.toLowerCase()] || method || 'Unknown'
        },

        getStatusClass(payment) {
            const status = payment.status?.toLowerCase() || 'completed'
            return {
                'completed': 'status-success',
                'pending': 'status-warning',
                'failed': 'status-error',
                'cancelled': 'status-cancelled'
            }[status] || 'status-success'
        },

        getStatusIcon(payment) {
            const status = payment.status?.toLowerCase() || 'completed'
            return {
                'completed': 'fas fa-check-circle',
                'pending': 'fas fa-clock',
                'failed': 'fas fa-times-circle',
                'cancelled': 'fas fa-ban'
            }[status] || 'fas fa-check-circle'
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
                month: 'long',
                day: 'numeric'
            })
        },

        formatDateTime(date) {
            return new Date(date).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
        }
    }
}
</script>

<style scoped>
.payment-detail-container {
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
    animation: float 30s infinite linear;
}

.orb-1 {
    width: 120px;
    height: 120px;
    top: 15%;
    left: 10%;
    animation-delay: 0s;
}

.orb-2 {
    width: 80px;
    height: 80px;
    top: 60%;
    right: 15%;
    animation-delay: -10s;
}

.orb-3 {
    width: 100px;
    height: 100px;
    bottom: 25%;
    left: 60%;
    animation-delay: -20s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    25% { transform: translateY(-50px) rotate(90deg); }
    50% { transform: translateY(0px) rotate(180deg); }
    75% { transform: translateY(50px) rotate(270deg); }
}

.loading-container,
.error-container {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    color: white;
    text-align: center;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.error-icon {
    font-size: 4rem;
    color: #ff6b6b;
    margin-bottom: 1rem;
}

.detail-content {
    position: relative;
    z-index: 1;
}

.page-header {
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
    align-items: flex-start;
    gap: 2rem;
}

.title-section {
    flex: 1;
}

.payment-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 1rem;
}

.status-success {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-warning {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.status-error {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-cancelled {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    margin: 0 0 0.5rem 0;
}

.page-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
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

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.left-column,
.right-column {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.detail-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    color: white;
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-link {
    color: #4ecdc4;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.3s ease;
}

.view-link:hover {
    color: white;
}

.refresh-btn {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.refresh-btn:hover:not(:disabled) {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}

.card-content {
    padding: 2rem;
}

.payment-overview .overview-amount {
    text-align: center;
    margin-bottom: 2rem;
}

.amount-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.amount-display .currency {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
}

.amount-display .amount {
    font-size: 3rem;
    font-weight: 700;
    color: #4ecdc4;
}

.amount-details {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.amount-details div {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: center;
}

.amount-details .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.amount-details .value {
    color: white;
    font-weight: 600;
}

.payment-method-display {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.method-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.method-details h4 {
    color: white;
    font-size: 1.1rem;
    margin: 0 0 0.25rem 0;
}

.method-details p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    margin: 0;
}

.vendor-details {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.vendor-avatar {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.vendor-data h4 {
    color: white;
    font-size: 1.3rem;
    margin: 0 0 1rem 0;
}

.vendor-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.meta-item i {
    width: 16px;
    color: rgba(255, 255, 255, 0.6);
}

.timeline {
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(255, 255, 255, 0.2);
}

.timeline-item {
    position: relative;
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #4ecdc4;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    z-index: 1;
}

.timeline-content h4 {
    color: white;
    font-size: 1rem;
    margin: 0 0 0.25rem 0;
}

.timeline-content p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    margin: 0 0 0.25rem 0;
}

.timeline-content small {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
}

.invoice-summary {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.invoice-number {
    color: white;
    font-size: 1.1rem;
}

.invoice-date {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.invoice-amounts {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.amount-row:last-child {
    border-bottom: none;
}

.amount-row.total {
    font-weight: 600;
    border-top: 2px solid rgba(255, 255, 255, 0.2);
    margin-top: 0.5rem;
    padding-top: 1rem;
}

.amount-row .label {
    color: rgba(255, 255, 255, 0.8);
}

.amount-row .value {
    color: white;
    font-weight: 600;
}

.amount-row .value.paid {
    color: #4ecdc4;
}

.due-date-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
}

.due-date-info.overdue {
    background: rgba(239, 68, 68, 0.1);
    color: #ff6b6b;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.overdue-label {
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
}

.no-invoice,
.no-journal {
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
    padding: 2rem;
}

.no-invoice i,
.no-journal i {
    font-size: 2rem;
    margin-bottom: 1rem;
    display: block;
}

.accounting-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.account-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.account-item label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
    font-weight: 500;
}

.account-item span {
    color: white;
    font-weight: 600;
}

.account-item .gain {
    color: #4ecdc4;
}

.account-item .loss {
    color: #ff6b6b;
}

.loading-journal {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    justify-content: center;
    padding: 2rem;
}

.loading-spinner-small {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.journal-display {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.journal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.journal-number {
    color: white;
    font-weight: 600;
}

.journal-date {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.journal-lines {
    display: flex;
    flex-direction: column;
}

.line-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
}

.journal-line {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.journal-totals {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 1rem;
    padding: 0.75rem 0;
    border-top: 2px solid rgba(255, 255, 255, 0.2);
    margin-top: 0.5rem;
    font-weight: 600;
    color: white;
}

.account-name {
    font-size: 0.9rem;
}

.debit-amount,
.credit-amount,
.total-debit,
.total-credit {
    text-align: right;
    font-family: monospace;
}

.notes-content {
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.6;
    background: rgba(255, 255, 255, 0.05);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .accounting-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-detail-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .header-actions {
        flex-wrap: wrap;
        justify-content: stretch;
    }
    
    .header-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .amount-display {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .amount-display .amount {
        font-size: 2.5rem;
    }
    
    .amount-details {
        flex-direction: column;
        gap: 1rem;
    }
    
    .payment-method-display {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .vendor-details {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .invoice-header {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
    
    .line-header,
    .journal-line,
    .journal-totals {
        grid-template-columns: 1fr;
        text-align: left;
    }
    
    .debit-amount,
    .credit-amount,
    .total-debit,
    .total-credit {
        text-align: left;
    }
    
    .line-header .debit-col::before {
        content: 'Debit: ';
        font-weight: normal;
    }
    
    .line-header .credit-col::before {
        content: 'Credit: ';
        font-weight: normal;
    }
}

/* Print Styles */
@media print {
    .payment-detail-container {
        background: white !important;
        padding: 1rem;
    }
    
    .background-animation,
    .header-actions {
        display: none !important;
    }
    
    .detail-card {
        background: white !important;
        border: 1px solid #ddd !important;
        backdrop-filter: none !important;
        break-inside: avoid;
    }
    
    .card-header,
    .page-title,
    .page-subtitle,
    h3, h4, p, span, div {
        color: #000 !important;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
}
</style>