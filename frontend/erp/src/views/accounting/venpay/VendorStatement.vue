<template>
  <div class="vendor-statement-container">
    <!-- Loading State -->
    <div v-if="loading" class="loading-section">
      <div class="loading-spinner"></div>
      <p>Loading vendor statement...</p>
    </div>

    <!-- Main Content -->
    <div v-else class="statement-content">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-section">
            <button @click="goBack" class="btn-back">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="title-info">
              <h1 class="page-title">
                <i class="fas fa-file-alt"></i>
                Vendor Statement
              </h1>
              <div class="statement-period">
                Statement Period: {{ formatDate(filters.from_date) }} - {{ formatDate(filters.to_date) }}
              </div>
            </div>
          </div>
          <div class="header-actions">
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
              Print
            </button>
          </div>
        </div>
      </div>

      <!-- Statement Controls -->
      <div class="controls-section">
        <div class="controls-grid">
          <div class="control-item">
            <label>Vendor</label>
            <select v-model="selectedVendorId" @change="onVendorChange" class="form-select">
              <option value="">Select Vendor</option>
              <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
                {{ vendor.name }} ({{ vendor.vendor_code }})
              </option>
            </select>
          </div>
          
          <div class="control-item">
            <label>From Date</label>
            <input 
              type="date" 
              v-model="filters.from_date" 
              @change="loadStatement"
              class="form-input"
            >
          </div>
          
          <div class="control-item">
            <label>To Date</label>
            <input 
              type="date" 
              v-model="filters.to_date" 
              @change="loadStatement"
              class="form-input"
            >
          </div>
          
          <div class="control-item">
            <label>Currency</label>
            <select v-model="filters.currency" @change="loadStatement" class="form-select">
              <option value="">All Currencies</option>
              <option value="IDR">IDR</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
        </div>
        
        <div class="control-actions">
          <button @click="resetFilters" class="btn btn-ghost">
            <i class="fas fa-refresh"></i>
            Reset
          </button>
          <button @click="loadStatement" :disabled="!selectedVendorId" class="btn btn-primary">
            <i class="fas fa-search"></i>
            Generate Statement
          </button>
        </div>
      </div>

      <!-- Vendor Information Card -->
      <div v-if="vendorInfo" class="vendor-info-section">
        <div class="vendor-card">
          <div class="vendor-header">
            <div class="vendor-avatar">
              {{ vendorInfo.name?.charAt(0)?.toUpperCase() }}
            </div>
            <div class="vendor-details">
              <h2>{{ vendorInfo.name }}</h2>
              <p class="vendor-code">{{ vendorInfo.vendor_code }}</p>
              <div class="vendor-contact">
                <div v-if="vendorInfo.email" class="contact-item">
                  <i class="fas fa-envelope"></i>
                  {{ vendorInfo.email }}
                </div>
                <div v-if="vendorInfo.phone" class="contact-item">
                  <i class="fas fa-phone"></i>
                  {{ vendorInfo.phone }}
                </div>
              </div>
            </div>
            <div class="vendor-status">
              <div class="status-badge" :class="`status-${vendorInfo.status}`">
                {{ vendorInfo.status }}
              </div>
              <div class="credit-limit">
                Credit Limit: {{ formatCurrency(vendorInfo.credit_limit || 0) }}
              </div>
            </div>
          </div>
          
          <div class="vendor-address" v-if="vendorInfo.address">
            <h4>Billing Address</h4>
            <p>{{ vendorInfo.address }}</p>
            <p v-if="vendorInfo.city || vendorInfo.country">
              {{ vendorInfo.city }}{{ vendorInfo.city && vendorInfo.country ? ', ' : '' }}{{ vendorInfo.country }}
            </p>
          </div>
        </div>
      </div>

      <!-- Statement Summary -->
      <div v-if="statementData" class="summary-section">
        <div class="summary-grid">
          <div class="summary-card opening-balance">
            <div class="card-icon">
              <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="card-content">
              <h3>{{ formatCurrency(statementData.opening_balance) }}</h3>
              <p>Opening Balance</p>
              <small>As of {{ formatDate(filters.from_date) }}</small>
            </div>
          </div>
          
          <div class="summary-card total-invoiced">
            <div class="card-icon">
              <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="card-content">
              <h3>{{ formatCurrency(statementData.total_invoiced) }}</h3>
              <p>Total Invoiced</p>
              <small>{{ statementData.invoice_count }} invoice(s)</small>
            </div>
          </div>
          
          <div class="summary-card total-paid">
            <div class="card-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
              <h3>{{ formatCurrency(statementData.total_paid) }}</h3>
              <p>Total Paid</p>
              <small>{{ statementData.payment_count }} payment(s)</small>
            </div>
          </div>
          
          <div class="summary-card closing-balance">
            <div class="card-icon">
              <i class="fas fa-balance-scale"></i>
            </div>
            <div class="card-content">
              <h3>{{ formatCurrency(statementData.closing_balance) }}</h3>
              <p>Closing Balance</p>
              <small>As of {{ formatDate(filters.to_date) }}</small>
            </div>
          </div>
        </div>
        
        <!-- Balance Trend Chart -->
        <div class="chart-section">
          <div class="chart-header">
            <h3>Balance Trend</h3>
            <div class="chart-legend">
              <div class="legend-item">
                <span class="legend-color invoices"></span>
                Invoices
              </div>
              <div class="legend-item">
                <span class="legend-color payments"></span>
                Payments
              </div>
              <div class="legend-item">
                <span class="legend-color balance"></span>
                Running Balance
              </div>
            </div>
          </div>
          <div class="chart-container">
            <canvas ref="trendChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Transaction History -->
      <div v-if="statementData" class="transactions-section">
        <div class="section-header">
          <h2>
            <i class="fas fa-list"></i>
            Transaction History
          </h2>
          <div class="section-filters">
            <select v-model="transactionFilter" @change="filterTransactions" class="form-select">
              <option value="all">All Transactions</option>
              <option value="invoices">Invoices Only</option>
              <option value="payments">Payments Only</option>
              <option value="credits">Credits Only</option>
            </select>
          </div>
        </div>
        
        <div class="transactions-table-container">
          <table class="transactions-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Transaction</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="amount-column">Debit</th>
                <th class="amount-column">Credit</th>
                <th class="amount-column">Balance</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <!-- Opening Balance Row -->
              <tr class="opening-balance-row">
                <td>{{ formatDate(filters.from_date) }}</td>
                <td>
                  <div class="transaction-type opening">
                    <i class="fas fa-play"></i>
                    Opening Balance
                  </div>
                </td>
                <td>-</td>
                <td>Balance brought forward</td>
                <td>-</td>
                <td>-</td>
                <td class="balance-amount">
                  {{ formatCurrency(statementData.opening_balance) }}
                </td>
                <td>-</td>
              </tr>
              
              <!-- Transaction Rows -->
              <tr 
                v-for="transaction in filteredTransactions" 
                :key="transaction.id" 
                class="transaction-row"
                :class="transaction.type"
              >
                <td class="transaction-date">
                  {{ formatDate(transaction.date) }}
                </td>
                <td>
                  <div class="transaction-type" :class="transaction.type">
                    <i :class="getTransactionIcon(transaction.type)"></i>
                    {{ getTransactionLabel(transaction.type) }}
                  </div>
                </td>
                <td class="transaction-reference">
                  <a v-if="transaction.link" :href="transaction.link" class="reference-link">
                    {{ transaction.reference }}
                  </a>
                  <span v-else>{{ transaction.reference }}</span>
                </td>
                <td class="transaction-description">
                  {{ transaction.description }}
                </td>
                <td class="debit-amount">
                  <span v-if="transaction.debit" class="amount debit">
                    {{ formatCurrency(transaction.debit) }}
                  </span>
                  <span v-else>-</span>
                </td>
                <td class="credit-amount">
                  <span v-if="transaction.credit" class="amount credit">
                    {{ formatCurrency(transaction.credit) }}
                  </span>
                  <span v-else>-</span>
                </td>
                <td class="running-balance">
                  <span class="balance-amount" :class="{ 'negative': transaction.running_balance < 0 }">
                    {{ formatCurrency(transaction.running_balance) }}
                  </span>
                </td>
                <td>
                  <span class="status-badge" :class="`status-${transaction.status}`">
                    {{ transaction.status }}
                  </span>
                </td>
              </tr>
              
              <!-- Closing Balance Row -->
              <tr class="closing-balance-row">
                <td>{{ formatDate(filters.to_date) }}</td>
                <td>
                  <div class="transaction-type closing">
                    <i class="fas fa-stop"></i>
                    Closing Balance
                  </div>
                </td>
                <td>-</td>
                <td>Balance carried forward</td>
                <td>-</td>
                <td>-</td>
                <td class="balance-amount">
                  {{ formatCurrency(statementData.closing_balance) }}
                </td>
                <td>-</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Outstanding Items -->
      <div v-if="outstandingItems.length > 0" class="outstanding-section">
        <div class="section-header">
          <h2>
            <i class="fas fa-clock"></i>
            Outstanding Items
          </h2>
          <div class="section-info">
            {{ outstandingItems.length }} item(s) - Total: {{ formatCurrency(outstandingTotal) }}
          </div>
        </div>
        
        <div class="outstanding-grid">
          <div 
            v-for="item in outstandingItems" 
            :key="item.id" 
            class="outstanding-card"
            :class="item.urgency"
          >
            <div class="card-header">
              <div class="item-type">
                <i :class="getTransactionIcon(item.type)"></i>
                {{ getTransactionLabel(item.type) }}
              </div>
              <div class="item-amount">
                {{ formatCurrency(item.amount) }}
              </div>
            </div>
            
            <div class="card-content">
              <div class="item-reference">{{ item.reference }}</div>
              <div class="item-description">{{ item.description }}</div>
              <div class="item-date">
                Due: {{ formatDate(item.due_date) }}
                <span class="days-info" :class="item.urgency">
                  ({{ item.days_overdue > 0 ? `${item.days_overdue} days overdue` : `${Math.abs(item.days_overdue)} days remaining` }})
                </span>
              </div>
            </div>
            
            <div class="card-actions">
              <button @click="viewItem(item)" class="btn btn-sm btn-outline">
                <i class="fas fa-eye"></i>
                View
              </button>
              <button v-if="item.type === 'invoice'" @click="recordPayment(item)" class="btn btn-sm btn-primary">
                <i class="fas fa-credit-card"></i>
                Pay
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Statement Footer -->
      <div class="statement-footer">
        <div class="footer-content">
          <div class="company-info">
            <h4>{{ companyInfo.name }}</h4>
            <p>{{ companyInfo.address }}</p>
            <p>{{ companyInfo.contact }}</p>
          </div>
          
          <div class="statement-info">
            <p><strong>Statement Date:</strong> {{ formatDate(new Date()) }}</p>
            <p><strong>Generated By:</strong> {{ currentUser.name }}</p>
            <p><strong>Statement ID:</strong> ST-{{ Date.now() }}</p>
          </div>
        </div>
        
        <div class="payment-terms" v-if="vendorInfo?.payment_terms">
          <h4>Payment Terms</h4>
          <p>{{ vendorInfo.payment_terms }}</p>
        </div>
      </div>
    </div>

    <!-- Email Statement Modal -->
    <div v-if="showEmailModal" class="modal-overlay" @click="closeEmailModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Email Statement</h3>
          <button @click="closeEmailModal" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <form @submit.prevent="sendStatement" class="modal-body">
          <div class="form-group">
            <label>To Email</label>
            <input 
              type="email" 
              v-model="emailForm.to" 
              required 
              class="form-input"
              :placeholder="vendorInfo?.email || 'Enter email address'"
            >
          </div>
          
          <div class="form-group">
            <label>CC (Optional)</label>
            <input 
              type="email" 
              v-model="emailForm.cc" 
              class="form-input"
              placeholder="CC email addresses (comma separated)"
            >
          </div>
          
          <div class="form-group">
            <label>Subject</label>
            <input 
              type="text" 
              v-model="emailForm.subject" 
              required 
              class="form-input"
            >
          </div>
          
          <div class="form-group">
            <label>Message</label>
            <textarea 
              v-model="emailForm.message" 
              rows="6"
              class="form-textarea"
              placeholder="Enter your message..."
            ></textarea>
          </div>
          
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="emailForm.attach_pdf">
              Attach PDF Statement
            </label>
          </div>
          
          <div class="modal-actions">
            <button type="button" @click="closeEmailModal" class="btn btn-outline">
              Cancel
            </button>
            <button type="submit" :disabled="sendingEmail" class="btn btn-primary">
              <i v-if="sendingEmail" class="fas fa-spinner fa-spin"></i>
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
  name: 'VendorStatement',
  data() {
    return {
      loading: false,
      selectedVendorId: '',
      vendorInfo: null,
      statementData: null,
      transactions: [],
      outstandingItems: [],
      vendors: [],
      transactionFilter: 'all',
      showEmailModal: false,
      sendingEmail: false,
      filters: {
        from_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days ago
        to_date: new Date().toISOString().split('T')[0],
        currency: ''
      },
      emailForm: {
        to: '',
        cc: '',
        subject: '',
        message: '',
        attach_pdf: true
      },
      companyInfo: {
        name: 'Your Company Name',
        address: '123 Business St, City, Country',
        contact: 'Phone: +1234567890 | Email: info@company.com'
      },
      currentUser: {
        name: 'Admin User'
      }
    }
  },
  computed: {
    filteredTransactions() {
      if (this.transactionFilter === 'all') {
        return this.transactions
      }
      return this.transactions.filter(t => t.type === this.transactionFilter.slice(0, -1))
    },
    
    outstandingTotal() {
      return this.outstandingItems.reduce((sum, item) => sum + item.amount, 0)
    }
  },
  created() {
    this.selectedVendorId = this.$route.params.vendorId || ''
    this.loadVendors()
    if (this.selectedVendorId) {
      this.loadStatement()
    }
  },
  methods: {
    async loadVendors() {
      try {
        const response = await axios.get('/vendors')
        this.vendors = response.data.data || response.data
      } catch (error) {
        console.error('Error loading vendors:', error)
      }
    },
    
    async loadStatement() {
      if (!this.selectedVendorId) return
      
      this.loading = true
      try {
        // Load vendor information
        const vendorResponse = await axios.get(`/vendors/${this.selectedVendorId}`)
        this.vendorInfo = vendorResponse.data.data || vendorResponse.data
        
        // Load statement data
        const params = {
          vendor_id: this.selectedVendorId,
          from_date: this.filters.from_date,
          to_date: this.filters.to_date,
          currency: this.filters.currency
        }
        
        const statementResponse = await axios.get('/accounting/vendor-statements', { params })
        const data = statementResponse.data
        
        this.statementData = data.summary
        this.transactions = data.transactions || []
        this.outstandingItems = data.outstanding_items || []
        
        // Update email form with vendor email
        this.emailForm.to = this.vendorInfo.email || ''
        this.emailForm.subject = `Account Statement - ${this.vendorInfo.name}`
        this.emailForm.message = this.getDefaultEmailMessage()
        
      } catch (error) {
        console.error('Error loading statement:', error)
        this.$toast?.error('Failed to load vendor statement')
      } finally {
        this.loading = false
      }
    },
    
    onVendorChange() {
      if (this.selectedVendorId) {
        this.loadStatement()
        // Update URL
        this.$router.replace(`/vendor-statements/${this.selectedVendorId}`)
      }
    },
    
    resetFilters() {
      this.filters = {
        from_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        to_date: new Date().toISOString().split('T')[0],
        currency: ''
      }
      if (this.selectedVendorId) {
        this.loadStatement()
      }
    },
    
    filterTransactions() {
      // Filtering is handled by computed property
    },
    
    emailStatement() {
      if (!this.vendorInfo?.email) {
        this.$toast?.warning('No email address found for this vendor')
        return
      }
      this.showEmailModal = true
    },
    
    closeEmailModal() {
      this.showEmailModal = false
    },
    
    async sendStatement() {
      this.sendingEmail = true
      try {
        const payload = {
          vendor_id: this.selectedVendorId,
          ...this.emailForm,
          statement_data: this.statementData,
          from_date: this.filters.from_date,
          to_date: this.filters.to_date
        }
        
        await axios.post('/accounting/vendor-statements/email', payload)
        this.$toast?.success('Statement sent successfully')
        this.closeEmailModal()
      } catch (error) {
        console.error('Error sending statement:', error)
        this.$toast?.error('Failed to send statement')
      } finally {
        this.sendingEmail = false
      }
    },
    
    downloadPDF() {
      // PDF download implementation
      console.log('Download PDF functionality to be implemented')
      window.print()
    },
    
    printStatement() {
      window.print()
    },
    
    viewItem(item) {
      if (item.type === 'invoice') {
        this.$router.push(`/vendor-invoices/${item.id}`)
      } else if (item.type === 'payable') {
        this.$router.push(`/payables/${item.id}`)
      }
    },
    
    recordPayment(item) {
      this.$router.push(`/payables/${item.payable_id}`)
    },
    
    goBack() {
      this.$router.go(-1)
    },
    
    getTransactionIcon(type) {
      const iconMap = {
        invoice: 'fas fa-file-invoice',
        payment: 'fas fa-credit-card',
        credit: 'fas fa-undo',
        debit: 'fas fa-minus-circle',
        adjustment: 'fas fa-edit'
      }
      return iconMap[type] || 'fas fa-circle'
    },
    
    getTransactionLabel(type) {
      const labelMap = {
        invoice: 'Invoice',
        payment: 'Payment',
        credit: 'Credit Note',
        debit: 'Debit Note',
        adjustment: 'Adjustment'
      }
      return labelMap[type] || type
    },
    
    getDefaultEmailMessage() {
      return `Dear ${this.vendorInfo?.name || 'Valued Partner'},

Please find attached your account statement for the period from ${this.formatDate(this.filters.from_date)} to ${this.formatDate(this.filters.to_date)}.

Current outstanding balance: ${this.formatCurrency(this.statementData?.closing_balance || 0)}

If you have any questions regarding this statement, please don't hesitate to contact us.

Thank you for your continued partnership.

Best regards,
${this.companyInfo.name}`
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
      }).format(amount || 0)
    },
    
    formatDate(date) {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }
  }
}
</script>

<style scoped>
/* Main Container */
.vendor-statement-container {
  padding: 2rem;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  min-height: 100vh;
}

/* Loading State */
.loading-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 60vh;
  color: #6b7280;
}

.loading-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
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

.title-section {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.btn-back {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  border-radius: 12px;
  width: 48px;
  height: 48px;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.btn-back:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateX(-2px);
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.statement-period {
  opacity: 0.9;
  font-size: 1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

/* Controls Section */
.controls-section {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.controls-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.control-item label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.control-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Vendor Information */
.vendor-info-section {
  margin-bottom: 2rem;
}

.vendor-card {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.vendor-header {
  display: flex;
  align-items: flex-start;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.vendor-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 2rem;
  flex-shrink: 0;
}

.vendor-details {
  flex: 1;
}

.vendor-details h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.vendor-code {
  color: #6b7280;
  font-size: 1rem;
  margin-bottom: 1rem;
}

.vendor-contact {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6b7280;
  font-size: 0.9rem;
}

.vendor-status {
  text-align: right;
}

.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  display: inline-block;
}

.status-active { background: #d1fae5; color: #065f46; }
.status-inactive { background: #fee2e2; color: #991b1b; }

.credit-limit {
  color: #6b7280;
  font-size: 0.9rem;
}

.vendor-address {
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.vendor-address h4 {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.vendor-address p {
  color: #6b7280;
  line-height: 1.5;
  margin-bottom: 0.25rem;
}

/* Summary Section */
.summary-section {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
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

.card-icon {
  width: 60px;
  height: 60px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
}

.opening-balance .card-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.total-invoiced .card-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.total-paid .card-icon { background: linear-gradient(135deg, #10b981, #059669); }
.closing-balance .card-icon { background: linear-gradient(135deg, #6366f1, #8b5cf6); }

.card-content h3 {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
  color: #1f2937;
}

.card-content p {
  color: #6b7280;
  font-size: 0.9rem;
  margin-bottom: 0.25rem;
}

.card-content small {
  color: #9ca3af;
  font-size: 0.8rem;
}

/* Chart Section */
.chart-section {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.chart-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
}

.chart-legend {
  display: flex;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: #6b7280;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 2px;
}

.legend-color.invoices { background: #f59e0b; }
.legend-color.payments { background: #10b981; }
.legend-color.balance { background: #6366f1; }

.chart-container {
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
}

/* Transactions Section */
.transactions-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.section-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-filters {
  display: flex;
  gap: 1rem;
}

.transactions-table-container {
  overflow-x: auto;
}

.transactions-table {
  width: 100%;
  border-collapse: collapse;
}

.transactions-table th {
  background: #f8fafc;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
  white-space: nowrap;
}

.transactions-table th.amount-column {
  text-align: right;
}

.transactions-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: top;
}

.transaction-row:hover {
  background: #f8fafc;
}

.opening-balance-row,
.closing-balance-row {
  background: #f1f5f9;
  font-weight: 600;
}

.transaction-type {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
}

.transaction-type.invoice { color: #f59e0b; }
.transaction-type.payment { color: #10b981; }
.transaction-type.credit { color: #3b82f6; }
.transaction-type.opening,
.transaction-type.closing { color: #6b7280; }

.reference-link {
  color: #6366f1;
  text-decoration: none;
}

.reference-link:hover {
  text-decoration: underline;
}

.transaction-description {
  color: #6b7280;
  font-size: 0.9rem;
}

.debit-amount,
.credit-amount,
.running-balance {
  text-align: right;
}

.amount.debit {
  color: #ef4444;
  font-weight: 600;
}

.amount.credit {
  color: #10b981;
  font-weight: 600;
}

.balance-amount {
  font-weight: 600;
  color: #1f2937;
}

.balance-amount.negative {
  color: #ef4444;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}

.status-paid { background: #d1fae5; color: #065f46; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-overdue { background: #fee2e2; color: #991b1b; }

/* Outstanding Section */
.outstanding-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 2rem;
}

.section-info {
  color: #6b7280;
  font-size: 0.9rem;
}

.outstanding-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.outstanding-card {
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  transition: all 0.3s ease;
}

.outstanding-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.outstanding-card.urgent {
  border-color: #ef4444;
  background: #fef2f2;
}

.outstanding-card.warning {
  border-color: #f59e0b;
  background: #fffbeb;
}

.outstanding-card.normal {
  border-color: #10b981;
  background: #f0fdf4;
}

.outstanding-card .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.item-type {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: #374151;
}

.item-amount {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1f2937;
}

.outstanding-card .card-content {
  margin-bottom: 1rem;
}

.item-reference {
  font-weight: 600;
  color: #6366f1;
  margin-bottom: 0.5rem;
}

.item-description {
  color: #6b7280;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.item-date {
  font-size: 0.8rem;
  color: #374151;
}

.days-info {
  font-weight: 600;
}

.days-info.urgent { color: #ef4444; }
.days-info.warning { color: #f59e0b; }
.days-info.normal { color: #10b981; }

.outstanding-card .card-actions {
  display: flex;
  gap: 0.5rem;
}

/* Statement Footer */
.statement-footer {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.footer-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 1.5rem;
}

.company-info h4,
.statement-info p {
  margin-bottom: 0.5rem;
}

.company-info h4 {
  font-weight: 700;
  color: #1f2937;
}

.company-info p,
.statement-info p {
  color: #6b7280;
  font-size: 0.9rem;
}

.payment-terms {
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.payment-terms h4 {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.payment-terms p {
  color: #6b7280;
  line-height: 1.6;
}

/* Form Elements */
.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: border-color 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
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

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
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

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #374151;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .summary-section {
    grid-template-columns: 1fr;
  }
  
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .vendor-statement-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .controls-grid {
    grid-template-columns: 1fr;
  }
  
  .vendor-header {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .outstanding-grid {
    grid-template-columns: 1fr;
  }
  
  .footer-content {
    grid-template-columns: 1fr;
  }
  
  .transactions-table {
    min-width: 800px;
  }
}

/* Print Styles */
@media print {
  .vendor-statement-container {
    padding: 0;
    background: white;
  }
  
  .page-header {
    background: white;
    color: black;
    border: 1px solid #000;
  }
  
  .controls-section {
    display: none;
  }
  
  .btn {
    display: none;
  }
  
  .outstanding-section {
    page-break-before: auto;
  }
  
  .statement-footer {
    page-break-inside: avoid;
  }
}
</style>