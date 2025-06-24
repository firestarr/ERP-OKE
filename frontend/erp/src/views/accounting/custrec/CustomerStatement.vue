<template>
  <div class="customer-statement">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading customer statement...</p>
    </div>

    <!-- Main Content -->
    <div v-else-if="customer" class="statement-content">
      <!-- Header Section -->
      <div class="page-header no-print">
        <div class="header-content">
          <div class="header-left">
            <h1 class="page-title">
              <i class="fas fa-file-alt"></i>
              Customer Statement
            </h1>
            <p class="page-subtitle">{{ customer.name }} - {{ formatDateRange() }}</p>
          </div>
          <div class="header-actions">
            <router-link to="/accounting/receivables" class="btn btn-ghost">
              <i class="fas fa-arrow-left"></i>
              Back to Receivables
            </router-link>
            <button @click="emailStatement" class="btn btn-outline">
              <i class="fas fa-envelope"></i>
              Email Statement
            </button>
            <button @click="downloadPDF" class="btn btn-outline">
              <i class="fas fa-download"></i>
              Download PDF
            </button>
            <button @click="printStatement" class="btn btn-primary">
              <i class="fas fa-print"></i>
              Print Statement
            </button>
          </div>
        </div>
      </div>

      <!-- Statement Filters -->
      <div class="filters-section no-print">
        <div class="filters-grid">
          <div class="filter-group">
            <label>Statement Date Range</label>
            <div class="date-range">
              <input 
                type="date" 
                v-model="dateRange.from" 
                @change="loadStatementData"
              >
              <span>to</span>
              <input 
                type="date" 
                v-model="dateRange.to" 
                @change="loadStatementData"
              >
            </div>
          </div>
          
          <div class="filter-group">
            <label>Statement Type</label>
            <select v-model="statementType" @change="loadStatementData">
              <option value="detailed">Detailed Statement</option>
              <option value="summary">Summary Statement</option>
              <option value="aging">Aging Statement</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label>Include Paid Items</label>
            <label class="toggle-switch">
              <input 
                type="checkbox" 
                v-model="includePaid" 
                @change="loadStatementData"
              >
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- Statement Document -->
      <div class="statement-document">
        <!-- Statement Header -->
        <div class="statement-header">
          <div class="company-info">
            <h2>{{ companyInfo.name }}</h2>
            <p>{{ companyInfo.address }}</p>
            <p>{{ companyInfo.city }}, {{ companyInfo.state }} {{ companyInfo.zip }}</p>
            <p>Phone: {{ companyInfo.phone }} | Email: {{ companyInfo.email }}</p>
          </div>
          
          <div class="statement-title">
            <h1>CUSTOMER STATEMENT</h1>
            <div class="statement-meta">
              <div class="meta-item">
                <span class="label">Statement Date:</span>
                <span class="value">{{ formatDate(new Date()) }}</span>
              </div>
              <div class="meta-item">
                <span class="label">Statement Period:</span>
                <span class="value">{{ formatDateRange() }}</span>
              </div>
              <div class="meta-item">
                <span class="label">Account Number:</span>
                <span class="value">#{{ customer.customer_code }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer Information -->
        <div class="customer-section">
          <div class="section-title">
            <h3>BILL TO:</h3>
          </div>
          <div class="customer-details">
            <h4>{{ customer.name }}</h4>
            <p v-if="customer.company">{{ customer.company }}</p>
            <p v-if="customer.address">{{ customer.address }}</p>
            <p v-if="customer.city">{{ customer.city }}, {{ customer.state }} {{ customer.zip }}</p>
            <p v-if="customer.phone">Phone: {{ customer.phone }}</p>
            <p v-if="customer.email">Email: {{ customer.email }}</p>
          </div>
        </div>

        <!-- Account Summary -->
        <div class="summary-section">
          <div class="section-title">
            <h3>ACCOUNT SUMMARY</h3>
          </div>
          <div class="summary-grid">
            <div class="summary-item">
              <span class="label">Previous Balance:</span>
              <span class="value">{{ formatCurrency(summary.previousBalance) }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Charges This Period:</span>
              <span class="value">{{ formatCurrency(summary.chargesThisPeriod) }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Payments This Period:</span>
              <span class="value credit">{{ formatCurrency(summary.paymentsThisPeriod) }}</span>
            </div>
            <div class="summary-item highlight">
              <span class="label">Current Balance:</span>
              <span class="value">{{ formatCurrency(summary.currentBalance) }}</span>
            </div>
          </div>
        </div>

        <!-- Aging Summary (if aging statement type) -->
        <div v-if="statementType === 'aging'" class="aging-summary">
          <div class="section-title">
            <h3>AGING SUMMARY</h3>
          </div>
          <div class="aging-grid">
            <div class="aging-column">
              <h4>Current</h4>
              <p>{{ formatCurrency(aging.current) }}</p>
            </div>
            <div class="aging-column">
              <h4>1-30 Days</h4>
              <p>{{ formatCurrency(aging.days_1_30) }}</p>
            </div>
            <div class="aging-column">
              <h4>31-60 Days</h4>
              <p>{{ formatCurrency(aging.days_31_60) }}</p>
            </div>
            <div class="aging-column">
              <h4>61-90 Days</h4>
              <p>{{ formatCurrency(aging.days_61_90) }}</p>
            </div>
            <div class="aging-column">
              <h4>Over 90 Days</h4>
              <p>{{ formatCurrency(aging.over_90) }}</p>
            </div>
          </div>
        </div>

        <!-- Transaction Details -->
        <div v-if="statementType === 'detailed'" class="transactions-section">
          <div class="section-title">
            <h3>TRANSACTION DETAILS</h3>
          </div>
          
          <div v-if="transactions.length === 0" class="no-transactions">
            <p>No transactions found for the selected period.</p>
          </div>
          
          <div v-else class="transactions-table">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Description</th>
                  <th>Reference</th>
                  <th class="amount-col">Charges</th>
                  <th class="amount-col">Payments</th>
                  <th class="amount-col">Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="transaction in transactions" 
                  :key="transaction.id"
                  :class="transaction.type"
                >
                  <td>{{ formatDate(transaction.date) }}</td>
                  <td>{{ transaction.description }}</td>
                  <td>{{ transaction.reference }}</td>
                  <td class="amount-col">
                    {{ transaction.type === 'charge' ? formatCurrency(transaction.amount) : '' }}
                  </td>
                  <td class="amount-col">
                    {{ transaction.type === 'payment' ? formatCurrency(transaction.amount) : '' }}
                  </td>
                  <td class="amount-col">{{ formatCurrency(transaction.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Outstanding Invoices -->
        <div v-if="statementType !== 'aging'" class="outstanding-section">
          <div class="section-title">
            <h3>OUTSTANDING INVOICES</h3>
          </div>
          
          <div v-if="outstandingInvoices.length === 0" class="no-outstanding">
            <p>No outstanding invoices.</p>
          </div>
          
          <div v-else class="outstanding-table">
            <table>
              <thead>
                <tr>
                  <th>Invoice #</th>
                  <th>Invoice Date</th>
                  <th>Due Date</th>
                  <th class="amount-col">Original Amount</th>
                  <th class="amount-col">Paid Amount</th>
                  <th class="amount-col">Balance Due</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="invoice in outstandingInvoices" 
                  :key="invoice.receivable_id"
                  :class="{ overdue: isOverdue(invoice.due_date) }"
                >
                  <td>#{{ invoice.sales_invoice?.invoice_number }}</td>
                  <td>{{ formatDate(invoice.sales_invoice?.invoice_date) }}</td>
                  <td>{{ formatDate(invoice.due_date) }}</td>
                  <td class="amount-col">{{ formatCurrency(invoice.amount) }}</td>
                  <td class="amount-col">{{ formatCurrency(invoice.paid_amount) }}</td>
                  <td class="amount-col">{{ formatCurrency(invoice.balance) }}</td>
                  <td>
                    <span class="status-badge" :class="invoice.status.toLowerCase()">
                      {{ invoice.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td colspan="5"><strong>Total Outstanding:</strong></td>
                  <td class="amount-col"><strong>{{ formatCurrency(totalOutstanding) }}</strong></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Recent Payments -->
        <div v-if="recentPayments.length > 0" class="payments-section">
          <div class="section-title">
            <h3>RECENT PAYMENTS</h3>
          </div>
          
          <div class="payments-table">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Payment Method</th>
                  <th>Reference</th>
                  <th>Invoice #</th>
                  <th class="amount-col">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in recentPayments" :key="payment.payment_id">
                  <td>{{ formatDate(payment.payment_date) }}</td>
                  <td>{{ payment.payment_method || 'Cash' }}</td>
                  <td>{{ payment.reference || '-' }}</td>
                  <td>#{{ payment.receivable?.sales_invoice?.invoice_number }}</td>
                  <td class="amount-col">{{ formatCurrency(payment.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Statement Footer -->
        <div class="statement-footer">
          <div class="payment-info">
            <h4>Payment Information:</h4>
            <p><strong>Payment Terms:</strong> {{ customer.payment_terms || 'Net 30 days' }}</p>
            <p><strong>Remit Payment To:</strong></p>
            <p>{{ companyInfo.name }}</p>
            <p>{{ companyInfo.address }}</p>
            <p>{{ companyInfo.city }}, {{ companyInfo.state }} {{ companyInfo.zip }}</p>
          </div>
          
          <div class="contact-info">
            <h4>Questions?</h4>
            <p>Contact our Accounts Receivable department:</p>
            <p>Phone: {{ companyInfo.phone }}</p>
            <p>Email: {{ companyInfo.email }}</p>
          </div>
          
          <div v-if="summary.currentBalance > 0" class="payment-notice">
            <div class="notice-box" :class="getPaymentNoticeClass()">
              <h4>{{ getPaymentNoticeTitle() }}</h4>
              <p>{{ getPaymentNoticeMessage() }}</p>
            </div>
          </div>
        </div>

        <!-- Statement Date -->
        <div class="statement-date">
          <p>Statement generated on {{ formatDateTime(new Date()) }}</p>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="error-state">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Customer Not Found</h3>
      <p>The requested customer could not be found.</p>
      <router-link to="/accounting/receivables" class="btn btn-primary">
        Back to Receivables
      </router-link>
    </div>

    <!-- Email Modal -->
    <div v-if="showEmailModal" class="modal-overlay" @click="closeEmailModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h3>Email Statement</h3>
          <button @click="closeEmailModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <form @submit.prevent="sendEmail" class="modal-content">
          <div class="form-group">
            <label>To Email Address</label>
            <input 
              type="email" 
              v-model="emailForm.to"
              required
              placeholder="customer@example.com"
            >
          </div>
          
          <div class="form-group">
            <label>CC Email (Optional)</label>
            <input 
              type="email" 
              v-model="emailForm.cc"
              placeholder="accounting@yourcompany.com"
            >
          </div>
          
          <div class="form-group">
            <label>Subject</label>
            <input 
              type="text" 
              v-model="emailForm.subject"
              required
            >
          </div>
          
          <div class="form-group">
            <label>Message</label>
            <textarea 
              v-model="emailForm.message"
              rows="5"
              placeholder="Please find your account statement attached..."
            ></textarea>
          </div>
          
          <div class="modal-actions">
            <button type="button" @click="closeEmailModal" class="btn btn-ghost">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="emailLoading">
              <i v-if="emailLoading" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-envelope"></i>
              Send Statement
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
  name: 'CustomerStatement',
  props: {
    receivableId: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      customerId: null,
      loading: true,
      customer: null,
      transactions: [],
      outstandingInvoices: [],
      recentPayments: [],
      dateRange: {
        from: '',
        to: ''
      },
      statementType: 'detailed',
      includePaid: false,
      showEmailModal: false,
      emailLoading: false,
      emailForm: {
        to: '',
        cc: '',
        subject: '',
        message: ''
      },
      summary: {
        previousBalance: 0,
        chargesThisPeriod: 0,
        paymentsThisPeriod: 0,
        currentBalance: 0
      },
      aging: {
        current: 0,
        days_1_30: 0,
        days_31_60: 0,
        days_61_90: 0,
        over_90: 0
      },
      companyInfo: {
        name: 'Your Company Name',
        address: '123 Business Street',
        city: 'Business City',
        state: 'BC',
        zip: '12345',
        phone: '(555) 123-4567',
        email: 'accounts@yourcompany.com'
      }
    }
  },
  computed: {
    totalOutstanding() {
      return this.outstandingInvoices.reduce((total, invoice) => total + parseFloat(invoice.balance || 0), 0)
    }
  },
  async mounted() {
    this.initializeDateRange()
    await this.loadReceivable()
    if (this.customerId) {
      await this.loadCustomer()
      await this.loadStatementData()
    }
  },
  methods: {
    initializeDateRange() {
      const today = new Date()
      const threeMonthsAgo = new Date()
      threeMonthsAgo.setMonth(today.getMonth() - 3)
      
      this.dateRange.from = threeMonthsAgo.toISOString().split('T')[0]
      this.dateRange.to = today.toISOString().split('T')[0]
    },
    
    async loadReceivable() {
      try {
        const response = await axios.get(`/accounting/customer-receivables/${this.receivableId}`)
        const receivable = response.data.data || response.data
        this.customerId = receivable.customer_id
      } catch (error) {
        console.error('Error loading receivable:', error)
        this.$toast?.error('Failed to load receivable information')
      }
    },
    
    async loadCustomer() {
      try {
        const response = await axios.get(`/customers/${this.customerId}`)
        this.customer = response.data.data || response.data
        
        // Set default email
        this.emailForm = {
          to: this.customer.email || '',
          cc: '',
          subject: `Account Statement - ${this.customer.name}`,
          message: `Dear ${this.customer.name},\n\nPlease find your account statement attached for the period ${this.formatDateRange()}.\n\nIf you have any questions regarding this statement, please don't hesitate to contact us.\n\nThank you for your business.`
        }
      } catch (error) {
        console.error('Error loading customer:', error)
        this.$toast?.error('Failed to load customer information')
      }
    },
    
    async loadStatementData() {
      try {
        this.loading = true
        
        await Promise.all([
          this.loadTransactions(),
          this.loadOutstandingInvoices(),
          this.loadRecentPayments(),
          this.loadAgingData()
        ])
        
        this.calculateSummary()
        
      } catch (error) {
        console.error('Error loading statement data:', error)
        this.$toast?.error('Failed to load statement data')
      } finally {
        this.loading = false
      }
    },
    
    async loadTransactions() {
      try {
        const params = {
          customer_id: this.customerId,
          from_date: this.dateRange.from,
          to_date: this.dateRange.to,
          include_paid: this.includePaid
        }
        
        const response = await axios.get('/accounting/customer-transactions', { params })
        this.transactions = response.data.data || []
        
        // Calculate running balance
        let balance = this.summary.previousBalance
        this.transactions.forEach(transaction => {
          if (transaction.type === 'charge') {
            balance += parseFloat(transaction.amount)
          } else {
            balance -= parseFloat(transaction.amount)
          }
          transaction.balance = balance
        })
        
      } catch (error) {
        console.error('Error loading transactions:', error)
        this.transactions = []
      }
    },
    
    async loadOutstandingInvoices() {
      try {
        const params = {
          customer_id: this.customerId,
          status: this.includePaid ? '' : 'Outstanding,Overdue,Partial'
        }
        
        const response = await axios.get('/accounting/customer-receivables', { params })
        this.outstandingInvoices = response.data.data || []
      } catch (error) {
        console.error('Error loading outstanding invoices:', error)
        this.outstandingInvoices = []
      }
    },
    
    async loadRecentPayments() {
      try {
        const params = {
          customer_id: this.customerId,
          from_date: this.dateRange.from,
          to_date: this.dateRange.to,
          limit: 10
        }
        
        const response = await axios.get('/accounting/receivable-payments', { params })
        this.recentPayments = response.data.data || []
      } catch (error) {
        console.error('Error loading recent payments:', error)
        this.recentPayments = []
      }
    },
    
    async loadAgingData() {
      if (this.statementType !== 'aging') return
      
      try {
        const params = {
          customer_id: this.customerId,
          as_of_date: this.dateRange.to
        }
        
        const response = await axios.get('/accounting/customer-receivables/aging', { params })
        const agingData = response.data.data || []
        
        if (agingData.length > 0) {
          const customerAging = agingData[0]
          this.aging = {
            current: customerAging.current_amount || 0,
            days_1_30: customerAging.days_1_30 || 0,
            days_31_60: customerAging.days_31_60 || 0,
            days_61_90: customerAging.days_61_90 || 0,
            over_90: customerAging.over_90 || 0
          }
        }
      } catch (error) {
        console.error('Error loading aging data:', error)
      }
    },
    
    calculateSummary() {
      // Calculate period totals
      this.summary.chargesThisPeriod = this.transactions
        .filter(t => t.type === 'charge')
        .reduce((sum, t) => sum + parseFloat(t.amount), 0)
      
      this.summary.paymentsThisPeriod = this.transactions
        .filter(t => t.type === 'payment')
        .reduce((sum, t) => sum + parseFloat(t.amount), 0)
      
      this.summary.currentBalance = this.totalOutstanding
    },
    
    async emailStatement() {
      this.showEmailModal = true
    },
    
    async sendEmail() {
      this.emailLoading = true
      
      try {
        const emailData = {
          customer_id: this.customerId,
          ...this.emailForm,
          statement_type: this.statementType,
          date_range: this.dateRange,
          include_paid: this.includePaid
        }
        
        await axios.post('/accounting/customer-receivables/email-statement', emailData)
        
        this.$toast?.success('Statement emailed successfully')
        this.closeEmailModal()
        
      } catch (error) {
        console.error('Error sending email:', error)
        this.$toast?.error('Failed to send statement email')
      } finally {
        this.emailLoading = false
      }
    },
    
    closeEmailModal() {
      this.showEmailModal = false
    },
    
    async downloadPDF() {
      try {
        const params = {
          customer_id: this.customerId,
          statement_type: this.statementType,
          from_date: this.dateRange.from,
          to_date: this.dateRange.to,
          include_paid: this.includePaid
        }
        
        const response = await axios.get('/accounting/customer-receivables/statement-pdf', {
          params,
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `statement-${this.customer.name}-${this.dateRange.from}-${this.dateRange.to}.pdf`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
      } catch (error) {
        console.error('Error downloading PDF:', error)
        this.$toast?.error('Failed to download PDF')
      }
    },
    
    printStatement() {
      window.print()
    },
    
    isOverdue(dueDate) {
      return new Date(dueDate) < new Date()
    },
    
    getPaymentNoticeClass() {
      const overdueAmount = this.outstandingInvoices
        .filter(invoice => this.isOverdue(invoice.due_date))
        .reduce((sum, invoice) => sum + parseFloat(invoice.balance), 0)
      
      if (overdueAmount > 0) return 'urgent'
      if (this.summary.currentBalance > 1000) return 'reminder'
      return 'standard'
    },
    
    getPaymentNoticeTitle() {
      const overdueAmount = this.outstandingInvoices
        .filter(invoice => this.isOverdue(invoice.due_date))
        .reduce((sum, invoice) => sum + parseFloat(invoice.balance), 0)
      
      if (overdueAmount > 0) return 'PAYMENT OVERDUE'
      return 'PAYMENT REMINDER'
    },
    
    getPaymentNoticeMessage() {
      const overdueAmount = this.outstandingInvoices
        .filter(invoice => this.isOverdue(invoice.due_date))
        .reduce((sum, invoice) => sum + parseFloat(invoice.balance), 0)
      
      if (overdueAmount > 0) {
        return `You have ${this.formatCurrency(overdueAmount)} in overdue payments. Please remit payment immediately to avoid additional charges.`
      }
      return 'Thank you for your business. Please remit payment by the due date to maintain your account in good standing.'
    },
    
    formatDateRange() {
      return `${this.formatDate(this.dateRange.from)} - ${this.formatDate(this.dateRange.to)}`
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
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
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
.customer-statement {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
  background: var(--gray-50);
  min-height: 100vh;
}

/* Hide elements when printing */
@media print {
  .no-print {
    display: none !important;
  }
  
  .customer-statement {
    padding: 0;
    background: white;
  }
  
  .statement-document {
    box-shadow: none;
    margin: 0;
  }
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

/* Error State */
.error-state {
  text-align: center;
  padding: 4rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.error-state i {
  font-size: 3rem;
  color: var(--danger-color);
  margin-bottom: 1rem;
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
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

.date-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-range input {
  flex: 1;
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
}

.date-range span {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.filter-group select {
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.filter-group input:focus,
.filter-group select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--gray-300);
  transition: 0.4s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: var(--primary-color);
}

input:checked + .slider:before {
  transform: translateX(26px);
}

/* Statement Document */
.statement-document {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  margin-bottom: 2rem;
  overflow: hidden;
}

/* Statement Header */
.statement-header {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  padding: 2rem;
  border-bottom: 3px solid var(--primary-color);
}

.company-info h2 {
  color: var(--primary-color);
  margin-bottom: 0.5rem;
  font-size: 1.5rem;
}

.company-info p {
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.statement-title {
  text-align: right;
}

.statement-title h1 {
  color: var(--gray-800);
  font-size: 2rem;
  margin-bottom: 1rem;
  letter-spacing: 1px;
}

.statement-meta {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.meta-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.meta-item .label {
  color: var(--gray-600);
  font-weight: 500;
}

.meta-item .value {
  color: var(--gray-800);
  font-weight: 600;
}

/* Section Styles */
.customer-section,
.summary-section,
.aging-summary,
.transactions-section,
.outstanding-section,
.payments-section {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--gray-200);
}

.section-title {
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid var(--gray-300);
}

.section-title h3 {
  color: var(--gray-800);
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0;
}

/* Customer Details */
.customer-details h4 {
  color: var(--gray-800);
  margin-bottom: 0.5rem;
  font-size: 1.125rem;
}

.customer-details p {
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

/* Summary Grid */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--gray-50);
  border-radius: 8px;
}

.summary-item.highlight {
  background: var(--primary-color);
  color: white;
}

.summary-item .label {
  font-weight: 500;
}

.summary-item .value {
  font-weight: 600;
  font-size: 1.125rem;
}

.summary-item .value.credit {
  color: var(--success-color);
}

.summary-item.highlight .value.credit {
  color: #d1fae5;
}

/* Aging Grid */
.aging-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
}

.aging-column {
  text-align: center;
  padding: 1rem;
  background: var(--gray-50);
  border-radius: 8px;
}

.aging-column h4 {
  color: var(--gray-700);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  text-transform: uppercase;
}

.aging-column p {
  color: var(--gray-800);
  font-weight: 600;
  font-size: 1.125rem;
}

/* Tables */
.transactions-table,
.outstanding-table,
.payments-table {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

th {
  background: var(--gray-50);
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  color: var(--gray-700);
  border-bottom: 2px solid var(--gray-300);
  font-size: 0.875rem;
}

.amount-col {
  text-align: right;
  width: 120px;
}

td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--gray-200);
  vertical-align: middle;
}

.amount-col {
  text-align: right;
  font-weight: 500;
}

.transactions-table tr.charge {
  background: rgba(239, 68, 68, 0.05);
}

.transactions-table tr.payment {
  background: rgba(16, 185, 129, 0.05);
}

.outstanding-table tr.overdue {
  background: rgba(239, 68, 68, 0.05);
}

.total-row {
  background: var(--gray-50);
  font-weight: 600;
}

.total-row td {
  border-top: 2px solid var(--gray-300);
  border-bottom: 2px solid var(--gray-300);
}

/* Status Badge */
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

/* Empty States */
.no-transactions,
.no-outstanding {
  text-align: center;
  padding: 2rem;
  color: var(--gray-500);
  font-style: italic;
}

/* Statement Footer */
.statement-footer {
  padding: 2rem;
  background: var(--gray-50);
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 2rem;
}

.payment-info,
.contact-info {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid var(--gray-200);
}

.payment-info h4,
.contact-info h4 {
  color: var(--gray-800);
  margin-bottom: 1rem;
  font-size: 1rem;
}

.payment-info p,
.contact-info p {
  color: var(--gray-600);
  margin-bottom: 0.25rem;
  font-size: 0.875rem;
}

.payment-notice {
  grid-column: 1 / -1;
}

.notice-box {
  padding: 1.5rem;
  border-radius: 8px;
  border-left: 4px solid;
}

.notice-box.urgent {
  background: #fee2e2;
  border-color: #dc2626;
  color: #dc2626;
}

.notice-box.reminder {
  background: #fef3c7;
  border-color: #f59e0b;
  color: #92400e;
}

.notice-box.standard {
  background: #dbeafe;
  border-color: #3b82f6;
  color: #1d4ed8;
}

.notice-box h4 {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.notice-box p {
  margin: 0;
  line-height: 1.5;
}

/* Statement Date */
.statement-date {
  padding: 1rem 2rem;
  background: var(--gray-100);
  text-align: center;
  border-top: 1px solid var(--gray-200);
}

.statement-date p {
  color: var(--gray-500);
  font-size: 0.875rem;
  margin: 0;
  font-style: italic;
}

/* Modal Styles */
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

.modal {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-800);
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: none;
  color: var(--gray-400);
  cursor: pointer;
  border-radius: 50%;
  transition: all 0.2s;
}

.close-btn:hover {
  background: var(--gray-100);
  color: var(--gray-600);
}

.modal-content {
  padding: 1.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

/* Form Styles */
.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-weight: 500;
  margin-bottom: 0.5rem;
  color: var(--gray-700);
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--gray-300);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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

.btn-primary:hover:not(:disabled) {
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

.btn-ghost:hover:not(:disabled) {
  background: var(--gray-100);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .statement-header {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .statement-title {
    text-align: left;
  }
  
  .statement-title h1 {
    font-size: 1.5rem;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .aging-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .customer-statement {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: center;
    flex-wrap: wrap;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .date-range {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .statement-header {
    padding: 1rem;
  }
  
  .customer-section,
  .summary-section,
  .aging-summary,
  .transactions-section,
  .outstanding-section,
  .payments-section {
    padding: 1rem;
  }
  
  .statement-footer {
    grid-template-columns: 1fr;
    padding: 1rem;
  }
  
  .aging-grid {
    grid-template-columns: 1fr;
  }
  
  .modal {
    width: 95%;
    margin: 1rem;
  }
  
  .modal-actions {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .header-actions {
    flex-direction: column;
  }
  
  .statement-title h1 {
    font-size: 1.25rem;
  }
  
  .meta-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
  
  .summary-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .modal-header {
    padding: 1rem;
  }
  
  .modal-content {
    padding: 1rem;
  }
}
</style>