<template>
    <div class="payment-form-container">
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
                        <i class="fas fa-money-check"></i>
                        {{ isEdit ? 'Edit Payment' : 'Record Payment' }}
                    </h1>
                    <p class="page-subtitle">
                        {{ isEdit ? 'Update payment details' : 'Record a new payment to vendor' }}
                    </p>
                </div>
                <div class="header-actions">
                    <router-link to="/accounting/payable-payments" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="form-container">
            <form @submit.prevent="submitForm" class="payment-form">
                <!-- Left Column -->
                <div class="form-column">
                    <!-- Vendor & Payable Selection -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-building"></i>
                                Vendor & Payable
                            </h3>
                        </div>
                        <div class="card-content">
                            <!-- Vendor Selection -->
                            <div class="form-group">
                                <label class="form-label required">Vendor</label>
                                <div class="vendor-selector">
<select v-model="form.vendor_id" @change="loadPayables" class="form-select" :disabled="isEdit">
    <option value="">Select Vendor</option>
    <template v-if="vendors && vendors.length">
        <option 
            v-for="vendor in vendors" 
            :key="vendor.id" 
            :value="vendor.id"
        >
            {{ vendor.name }}
        </option>
    </template>
</select>
                                    <button type="button" @click="showVendorModal = true" class="vendor-add-btn">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div v-if="errors.vendor_id" class="error-message">{{ errors.vendor_id[0] }}</div>
                            </div>

                            <!-- Payable Selection -->
                            <div class="form-group">
                                <label class="form-label required">Outstanding Payable</label>
                                <div v-if="loadingPayables" class="loading-payables">
                                    <div class="loading-spinner-small"></div>
                                    <span>Loading payables...</span>
                                </div>
                                <div v-else-if="payables.length === 0 && form.vendor_id" class="no-payables">
                                    <i class="fas fa-info-circle"></i>
                                    No outstanding payables for this vendor
                                </div>
                                <select v-else v-model="form.payable_id" @change="selectPayable" class="form-select" :disabled="isEdit">
                                    <option value="">Select Payable</option>
                                    <option v-for="payable in payables" :key="payable.payable_id" :value="payable.payable_id">
                                        Invoice #{{ payable.vendor_invoice?.invoice_number || payable.payable_id }} - 
                                        {{ formatCurrency(payable.balance) }} 
                                        (Due: {{ formatDate(payable.due_date) }})
                                    </option>
                                </select>
                                <div v-if="errors.payable_id" class="error-message">{{ errors.payable_id[0] }}</div>
                            </div>

                            <!-- Selected Payable Info -->
                            <div v-if="selectedPayable" class="payable-info">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Invoice Amount</label>
                                        <span class="amount">{{ formatCurrency(selectedPayable.amount) }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Paid Amount</label>
                                        <span class="amount paid">{{ formatCurrency(selectedPayable.paid_amount) }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Outstanding Balance</label>
                                        <span class="amount outstanding">{{ formatCurrency(selectedPayable.balance) }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Due Date</label>
                                        <span :class="{ 'overdue': isOverdue(selectedPayable.due_date) }">
                                            {{ formatDate(selectedPayable.due_date) }}
                                            <i v-if="isOverdue(selectedPayable.due_date)" class="fas fa-exclamation-triangle"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-credit-card"></i>
                                Payment Details
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Payment Date</label>
                                    <input type="date" v-model="form.payment_date" class="form-input" :max="today">
                                    <div v-if="errors.payment_date" class="error-message">{{ errors.payment_date[0] }}</div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Payment Method</label>
                                    <select v-model="form.payment_method" class="form-select">
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="check">Check</option>
                                        <option value="credit_card">Credit Card</option>
                                    </select>
                                    <div v-if="errors.payment_method" class="error-message">{{ errors.payment_method[0] }}</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Reference Number</label>
                                <input type="text" v-model="form.reference_number" placeholder="e.g., Check #1234, Transfer ID" class="form-input">
                                <div v-if="errors.reference_number" class="error-message">{{ errors.reference_number[0] }}</div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Payment Amount</label>
                                    <div class="amount-input-group">
                                        <span class="currency-symbol">$</span>
                                        <input type="number" v-model="form.amount" step="0.01" min="0" :max="maxPaymentAmount" 
                                               class="form-input amount-input" placeholder="0.00">
                                    </div>
                                    <div class="amount-helpers">
                                        <button type="button" @click="setFullAmount" class="helper-btn" :disabled="!selectedPayable">
                                            Pay Full Amount
                                        </button>
                                        <span v-if="selectedPayable" class="max-amount">Max: {{ formatCurrency(selectedPayable.balance) }}</span>
                                    </div>
                                    <div v-if="errors.amount" class="error-message">{{ errors.amount[0] }}</div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Payment Currency</label>
                                    <select v-model="form.payment_currency" class="form-select">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="IDR">IDR</option>
                                        <option value="SGD">SGD</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Exchange Rate (if different currency) -->
                            <div v-if="form.payment_currency !== 'USD'" class="form-group">
                                <label class="form-label">Exchange Rate ({{ form.payment_currency }} to USD)</label>
                                <input type="number" v-model="form.exchange_rate" step="0.0001" min="0" class="form-input" placeholder="1.0000">
                                <small class="form-hint">Current rate will be fetched automatically</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="form-column">
                    <!-- Bank Account -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-university"></i>
                                Account Details
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label class="form-label required">Cash/Bank Account</label>
                                <select v-model="form.cash_account_id" class="form-select">
                                    <option value="">Select Account</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.account_name }} - {{ account.account_number }}
                                    </option>
                                </select>
                                <div v-if="errors.cash_account_id" class="error-message">{{ errors.cash_account_id[0] }}</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Exchange Gain/Loss Account</label>
                                <select v-model="form.exchange_gain_loss_account_id" class="form-select">
                                    <option value="">Select Account</option>
                                    <option v-for="account in exchangeAccounts" :key="account.id" :value="account.id">
                                        {{ account.account_name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-calculator"></i>
                                Payment Summary
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <label>Payment Amount</label>
                                    <span class="value">{{ formatCurrency(form.amount || 0) }}</span>
                                </div>
                                <div class="summary-item">
                                    <label>Payment Currency</label>
                                    <span class="value">{{ form.payment_currency || 'USD' }}</span>
                                </div>
                                <div v-if="form.payment_currency !== 'USD'" class="summary-item">
                                    <label>Exchange Rate</label>
                                    <span class="value">{{ form.exchange_rate || '1.0000' }}</span>
                                </div>
                                <div v-if="form.payment_currency !== 'USD'" class="summary-item">
                                    <label>USD Equivalent</label>
                                    <span class="value highlight">{{ formatCurrency(usdEquivalent) }}</span>
                                </div>
                                <div v-if="selectedPayable" class="summary-item">
                                    <label>Remaining Balance</label>
                                    <span class="value">{{ formatCurrency(remainingBalance) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-sticky-note"></i>
                                Additional Notes
                            </h3>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <textarea v-model="form.notes" rows="4" class="form-textarea" 
                                         placeholder="Add any additional notes about this payment..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="action-buttons">
                        <router-link to="/accounting/payable-payments" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Cancel
                        </router-link>
                        <button type="button" @click="saveDraft" class="btn btn-secondary" :disabled="saving">
                            <i class="fas fa-save"></i>
                            Save as Draft
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!isFormValid || saving">
                            <i class="fas fa-check"></i>
                            {{ saving ? 'Processing...' : (isEdit ? 'Update Payment' : 'Record Payment') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Vendor Modal -->
        <transition name="fade">
            <div v-if="showVendorModal" class="modal-overlay" @click="showVendorModal = false">
                <div class="modal-content" @click.stop>
                    <div class="modal-header">
                        <h3>Quick Add Vendor</h3>
                        <button @click="showVendorModal = false" class="modal-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Vendor Name</label>
                            <input type="text" v-model="newVendor.name" class="form-input" placeholder="Enter vendor name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" v-model="newVendor.email" class="form-input" placeholder="vendor@example.com">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button @click="showVendorModal = false" class="btn btn-outline">Cancel</button>
                        <button @click="createVendor" class="btn btn-primary" :disabled="!newVendor.name">
                            <i class="fas fa-plus"></i>
                            Add Vendor
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
    name: 'PayablePaymentForm',
    props: {
        paymentId: String
    },
    data() {
        return {
            form: {
                vendor_id: '',
                payable_id: '',
                payment_date: new Date().toISOString().split('T')[0],
                amount: '',
                payment_method: 'bank',
                reference_number: '',
                payment_currency: 'USD',
                exchange_rate: 1.0000,
                cash_account_id: '',
                exchange_gain_loss_account_id: '',
                notes: ''
            },
            vendors: [],
            payables: [],
            accounts: [],
            exchangeAccounts: [],
            selectedPayable: null,
            loadingPayables: false,
            saving: false,
            errors: {},
            showVendorModal: false,
            newVendor: {
                name: '',
                email: ''
            }
        }
    },
    computed: {
        isEdit() {
            return !!this.paymentId
        },
        today() {
            return new Date().toISOString().split('T')[0]
        },
        maxPaymentAmount() {
            return this.selectedPayable ? this.selectedPayable.balance : 999999999
        },
        usdEquivalent() {
            if (this.form.payment_currency === 'USD') {
                return this.form.amount || 0
            }
            return (this.form.amount || 0) * (this.form.exchange_rate || 1)
        },
        remainingBalance() {
            if (!this.selectedPayable || !this.form.amount) return this.selectedPayable?.balance || 0
            return this.selectedPayable.balance - (this.form.amount || 0)
        },
        isFormValid() {
            return this.form.vendor_id && 
                   this.form.payable_id && 
                   this.form.payment_date && 
                   this.form.amount && 
                   this.form.payment_method && 
                   this.form.cash_account_id
        }
    },
    async mounted() {
        await this.loadInitialData()
        if (this.isEdit) {
            await this.loadPayment()
        }
    },
    methods: {
        async loadInitialData() {
            try {
                const [vendorsRes, accountsRes] = await Promise.all([
                    axios.get('/vendors'),
                    axios.get('/accounting/chart-of-accounts')
                ])
                
                this.vendors = vendorsRes.data.data || vendorsRes.data
                this.accounts = accountsRes.data.data || accountsRes.data
                this.exchangeAccounts = this.accounts.filter(acc => 
                    acc.account_type?.toLowerCase().includes('exchange') || 
                    acc.account_name?.toLowerCase().includes('exchange')
                )
            } catch (error) {
                console.error('Error loading initial data:', error)
                this.$toast.error('Failed to load form data')
            }
        },

        async loadPayment() {
            try {
                const response = await axios.get(`/accounting/payable-payments/${this.paymentId}`)
                const payment = response.data.data
                
                this.form = {
                    vendor_id: payment.vendor_payable?.vendor_id || '',
                    payable_id: payment.payable_id,
                    payment_date: payment.payment_date,
                    amount: payment.amount,
                    payment_method: payment.payment_method,
                    reference_number: payment.reference_number || '',
                    payment_currency: payment.payment_currency || 'USD',
                    exchange_rate: payment.exchange_rate || 1.0000,
                    cash_account_id: payment.cash_account_id || '',
                    exchange_gain_loss_account_id: payment.exchange_gain_loss_account_id || '',
                    notes: payment.notes || ''
                }

                if (this.form.vendor_id) {
                    await this.loadPayables()
                    this.selectPayable()
                }
            } catch (error) {
                console.error('Error loading payment:', error)
                this.$toast.error('Failed to load payment data')
            }
        },

        async loadPayables() {
            if (!this.form.vendor_id) {
                this.payables = []
                return
            }

            this.loadingPayables = true
            try {
                const response = await axios.get('/accounting/vendor-payables', {
                    params: {
                        vendor_id: this.form.vendor_id,
                        status: 'Open'
                    }
                })
                this.payables = response.data.data || response.data
            } catch (error) {
                console.error('Error loading payables:', error)
                this.$toast.error('Failed to load payables')
            } finally {
                this.loadingPayables = false
            }
        },

        selectPayable() {
            this.selectedPayable = this.payables.find(p => p.payable_id == this.form.payable_id)
            
            if (this.selectedPayable && !this.isEdit) {
                // Auto-set currency from payable
                this.form.payment_currency = this.selectedPayable.currency_code || 'USD'
                
                // Auto-fetch exchange rate if needed
                if (this.form.payment_currency !== 'USD') {
                    this.fetchExchangeRate()
                }
            }
        },

        async fetchExchangeRate() {
            if (this.form.payment_currency === 'USD') {
                this.form.exchange_rate = 1.0000
                return
            }

            try {
                const response = await axios.get(`/accounting/exchange-rates/${this.form.payment_currency}`, {
                    params: { date: this.form.payment_date }
                })
                this.form.exchange_rate = response.data.rate
            } catch (error) {
                console.warn('Could not fetch exchange rate:', error)
                // Keep manual input
            }
        },

        setFullAmount() {
            if (this.selectedPayable) {
                this.form.amount = this.selectedPayable.balance
            }
        },

        isOverdue(dueDate) {
            return new Date(dueDate) < new Date()
        },

        async createVendor() {
            try {
                const response = await axios.post('/vendors', this.newVendor)
                this.vendors.push(response.data.data)
                this.form.vendor_id = response.data.data.id
                this.newVendor = { name: '', email: '' }
                this.showVendorModal = false
                this.$toast.success('Vendor added successfully')
                await this.loadPayables()
            } catch (error) {
                console.error('Error creating vendor:', error)
                this.$toast.error('Failed to create vendor')
            }
        },

        async saveDraft() {
            // Implementation for saving as draft
            this.$toast.info('Draft functionality coming soon')
        },

        async submitForm() {
            this.saving = true
            this.errors = {}

            try {
                const payload = { ...this.form }
                
                // let response
                if (this.isEdit) {
                    await axios.put(`/accounting/payable-payments/${this.paymentId}`, payload)
                } else {
                    await axios.post('/accounting/payable-payments', payload)
                }

                this.$toast.success(this.isEdit ? 'Payment updated successfully' : 'Payment recorded successfully')
                this.$router.push('/accounting/payable-payments')
            } catch (error) {
                console.error('Error saving payment:', error)
                
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {}
                } else {
                    this.$toast.error('Failed to save payment')
                }
            } finally {
                this.saving = false
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
        }
    },
    watch: {
        'form.vendor_id'() {
            this.form.payable_id = ''
            this.selectedPayable = null
            this.loadPayables()
        },
        'form.payment_currency'() {
            this.fetchExchangeRate()
        },
        'form.payment_date'() {
            if (this.form.payment_currency !== 'USD') {
                this.fetchExchangeRate()
            }
        }
    }
}
</script>

<style scoped>
.payment-form-container {
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
    animation: float 25s infinite linear;
}

.orb-1 {
    width: 100px;
    height: 100px;
    top: 10%;
    left: 5%;
    animation-delay: 0s;
}

.orb-2 {
    width: 80px;
    height: 80px;
    top: 70%;
    right: 10%;
    animation-delay: -8s;
}

.orb-3 {
    width: 120px;
    height: 120px;
    bottom: 30%;
    left: 70%;
    animation-delay: -16s;
}

.orb-4 {
    width: 60px;
    height: 60px;
    top: 40%;
    right: 60%;
    animation-delay: -12s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    25% { transform: translateY(-40px) rotate(90deg); }
    50% { transform: translateY(0px) rotate(180deg); }
    75% { transform: translateY(40px) rotate(270deg); }
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

.btn-primary {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    background: linear-gradient(135deg, #4ecdc4, #44a08d);
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
}

.form-container {
    position: relative;
    z-index: 1;
}

.payment-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    grid-template-rows: auto auto;
}

.form-column {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-actions {
    grid-column: 1 / -1;
    margin-top: 2rem;
}

.form-card {
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

.card-content {
    padding: 2rem;
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
.form-select,
.form-textarea {
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
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: rgba(255, 107, 107, 0.5);
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.vendor-selector {
    display: flex;
    gap: 0.5rem;
}

.vendor-selector .form-select {
    flex: 1;
}

.vendor-add-btn {
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.vendor-add-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.loading-payables,
.no-payables {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.8);
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.loading-spinner-small {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.payable-info {
    margin-top: 1rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
    font-weight: 500;
}

.info-item .amount {
    font-weight: 700;
    font-size: 1.1rem;
}

.info-item .amount.paid {
    color: #4ecdc4;
}

.info-item .amount.outstanding {
    color: #fbbf24;
}

.info-item .overdue {
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 0.25rem;
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

.amount-helpers {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
}

.helper-btn {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    color: white;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.helper-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.2);
}

.helper-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.max-amount {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.form-hint {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
    margin-top: 0.25rem;
    display: block;
}

.summary-grid {
    display: flex;
    flex-direction: column;
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

.summary-item label {
    color: rgba(255, 255, 255, 0.8);
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

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    align-items: center;
}

.error-message {
    color: #ff6b6b;
    font-size: 0.8rem;
    margin-top: 0.25rem;
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
@media (max-width: 1200px) {
    .payment-form {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-form-container {
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
    
    .card-content {
        padding: 1.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        justify-content: center;
    }
}
</style>