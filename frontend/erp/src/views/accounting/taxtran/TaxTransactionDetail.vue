<template>
  <AppLayout>
    <div class="tax-detail-container">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="breadcrumb">
            <router-link to="/tax-transactions" class="breadcrumb-link">
              <i class="fas fa-receipt"></i>
              Tax Transactions
            </router-link>
            <i class="fas fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current">Transaction #{{ transaction?.tax_id }}</span>
          </div>
          
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-file-invoice-dollar"></i>
              Tax Transaction Details
            </h1>
            <div class="title-actions">
              <span class="status-badge" :class="getStatusClass(transaction?.status)">
                <i class="fas fa-circle"></i>
                {{ transaction?.status }}
              </span>
              <span class="transaction-id">#{{ transaction?.tax_id }}</span>
            </div>
          </div>

          <div class="header-actions">
            <button @click="printTransaction" class="btn btn-outline">
              <i class="fas fa-print"></i>
              Print
            </button>
            <button @click="exportPDF" class="btn btn-outline">
              <i class="fas fa-file-pdf"></i>
              Export PDF
            </button>
            <router-link 
              :to="`/tax-transactions/${transaction?.tax_id}/edit`" 
              class="btn btn-primary"
              v-if="canEdit"
            >
              <i class="fas fa-edit"></i>
              Edit Transaction
            </router-link>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading transaction details...</p>
      </div>

      <!-- Main Content -->
      <div v-else-if="transaction" class="detail-content">
        <!-- Quick Info Cards -->
        <div class="quick-info-grid">
          <div class="info-card amount">
            <div class="info-icon">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="info-content">
              <h3>${{ formatCurrency(transaction.tax_amount) }}</h3>
              <p>Tax Amount</p>
            </div>
          </div>
          
          <div class="info-card date">
            <div class="info-icon">
              <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="info-content">
              <h3>{{ formatDate(transaction.transaction_date) }}</h3>
              <p>Transaction Date</p>
            </div>
          </div>
          
          <div class="info-card type">
            <div class="info-icon">
              <i class="fas fa-tag"></i>
            </div>
            <div class="info-content">
              <h3>{{ transaction.tax_type }}</h3>
              <p>Tax Type</p>
            </div>
          </div>
          
          <div class="info-card reference">
            <div class="info-icon">
              <i class="fas fa-link"></i>
            </div>
            <div class="info-content">
              <h3>{{ transaction.reference_type }}</h3>
              <p>Reference Type</p>
            </div>
          </div>
        </div>

        <!-- Main Details -->
        <div class="details-grid">
          <!-- Transaction Information -->
          <div class="detail-card">
            <div class="card-header">
              <h2>
                <i class="fas fa-info-circle"></i>
                Transaction Information
              </h2>
              <div class="card-actions">
                <button @click="copyToClipboard(transaction.tax_id)" class="btn-icon" title="Copy Transaction ID">
                  <i class="fas fa-copy"></i>
                </button>
              </div>
            </div>
            
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Transaction ID</span>
                <span class="detail-value">#{{ transaction.tax_id }}</span>
              </div>
              
              <div class="detail-item">
                <span class="detail-label">Tax Type</span>
                <span class="detail-value tax-type">
                  <i class="fas fa-tag"></i>
                  {{ transaction.tax_type }}
                </span>
              </div>
              
              <div class="detail-item">
                <span class="detail-label">Tax Code</span>
                <span class="detail-value tax-code">{{ transaction.tax_code }}</span>
              </div>
              
              <div class="detail-item">
                <span class="detail-label">Transaction Date</span>
                <span class="detail-value">{{ formatDate(transaction.transaction_date) }}</span>
              </div>
              
              <div class="detail-item">
                <span class="detail-label">Tax Amount</span>
                <span class="detail-value amount">${{ formatCurrency(transaction.tax_amount) }}</span>
              </div>
              
              <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                  <span class="status-badge" :class="getStatusClass(transaction.status)">
                    <i class="fas fa-circle"></i>
                    {{ transaction.status }}
                  </span>
                </span>
              </div>
            </div>
          </div>

          <!-- Reference Information -->
          <div class="detail-card">
            <div class="card-header">
              <h2>
                <i class="fas fa-external-link-alt"></i>
                Reference Information
              </h2>
              <div class="card-actions">
                <button 
                  @click="viewReference" 
                  class="btn-small btn-primary"
                  v-if="transaction.reference_id"
                >
                  <i class="fas fa-eye"></i>
                  View Reference
                </button>
              </div>
            </div>
            
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Reference Type</span>
                <span class="detail-value">{{ transaction.reference_type }}</span>
              </div>
              
              <div class="detail-item">
                <span class="detail-label">Reference ID</span>
                <span class="detail-value">#{{ transaction.reference_id }}</span>
              </div>
              
              <div v-if="referenceDetails" class="reference-details">
                <div class="detail-item">
                  <span class="detail-label">Reference Description</span>
                  <span class="detail-value">{{ referenceDetails.description }}</span>
                </div>
                
                <div class="detail-item" v-if="referenceDetails.amount">
                  <span class="detail-label">Reference Amount</span>
                  <span class="detail-value amount">${{ formatCurrency(referenceDetails.amount) }}</span>
                </div>
                
                <div class="detail-item" v-if="referenceDetails.date">
                  <span class="detail-label">Reference Date</span>
                  <span class="detail-value">{{ formatDate(referenceDetails.date) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tax Calculations -->
        <div class="calculation-section">
          <div class="calculation-card">
            <div class="card-header">
              <h2>
                <i class="fas fa-calculator"></i>
                Tax Calculations
              </h2>
            </div>
            
            <div class="calculation-content">
              <div class="calc-breakdown">
                <div class="calc-item">
                  <span class="calc-label">Base Amount</span>
                  <span class="calc-value">${{ formatCurrency(baseAmount) }}</span>
                </div>
                
                <div class="calc-item">
                  <span class="calc-label">Tax Rate</span>
                  <span class="calc-value">{{ taxRate }}%</span>
                </div>
                
                <div class="calc-item">
                  <span class="calc-label">Tax Amount</span>
                  <span class="calc-value highlight">${{ formatCurrency(transaction.tax_amount) }}</span>
                </div>
                
                <div class="calc-divider"></div>
                
                <div class="calc-item total">
                  <span class="calc-label">Total Amount</span>
                  <span class="calc-value">${{ formatCurrency(totalAmount) }}</span>
                </div>
              </div>

              <div class="calc-visual">
                <div class="tax-breakdown-chart">
                  <div class="chart-segment base" :style="{ width: basePercentage + '%' }">
                    <span class="segment-label">Base</span>
                  </div>
                  <div class="chart-segment tax" :style="{ width: taxPercentage + '%' }">
                    <span class="segment-label">Tax</span>
                  </div>
                </div>
                <div class="chart-legend">
                  <div class="legend-item">
                    <div class="legend-color base"></div>
                    <span>Base Amount ({{ basePercentage.toFixed(1) }}%)</span>
                  </div>
                  <div class="legend-item">
                    <div class="legend-color tax"></div>
                    <span>Tax Amount ({{ taxPercentage.toFixed(1) }}%)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Timeline -->
        <div class="timeline-section">
          <div class="timeline-card">
            <div class="card-header">
              <h2>
                <i class="fas fa-history"></i>
                Transaction Timeline
              </h2>
              <button @click="refreshTimeline" class="btn-icon" title="Refresh Timeline">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>
            
            <div class="timeline-content">
              <div v-for="(event, index) in timeline" :key="index" class="timeline-item">
                <div class="timeline-marker" :class="event.type">
                  <i :class="event.icon"></i>
                </div>
                <div class="timeline-content-item">
                  <div class="timeline-header">
                    <h4>{{ event.title }}</h4>
                    <span class="timeline-date">{{ formatDateTime(event.date) }}</span>
                  </div>
                  <p>{{ event.description }}</p>
                  <div v-if="event.user" class="timeline-user">
                    <i class="fas fa-user"></i>
                    {{ event.user }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Related Documents -->
        <div class="documents-section">
          <div class="documents-card">
            <div class="card-header">
              <h2>
                <i class="fas fa-paperclip"></i>
                Related Documents
              </h2>
              <button @click="uploadDocument" class="btn-small btn-outline">
                <i class="fas fa-upload"></i>
                Upload Document
              </button>
            </div>
            
            <div v-if="documents.length > 0" class="documents-grid">
              <div v-for="doc in documents" :key="doc.id" class="document-item">
                <div class="document-icon" :class="getDocumentIconClass(doc.type)">
                  <i :class="getDocumentIcon(doc.type)"></i>
                </div>
                <div class="document-info">
                  <h4>{{ doc.name }}</h4>
                  <p>{{ doc.size }} • {{ formatDate(doc.uploaded_at) }}</p>
                </div>
                <div class="document-actions">
                  <button @click="downloadDocument(doc)" class="btn-icon">
                    <i class="fas fa-download"></i>
                  </button>
                  <button @click="previewDocument(doc)" class="btn-icon">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>
            
            <div v-else class="empty-documents">
              <div class="empty-icon">
                <i class="fas fa-file-alt"></i>
              </div>
              <h3>No documents attached</h3>
              <p>Upload relevant documents for this tax transaction</p>
              <button @click="uploadDocument" class="btn btn-primary">
                <i class="fas fa-upload"></i>
                Upload First Document
              </button>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-section">
          <div class="action-buttons">
            <button @click="duplicateTransaction" class="btn btn-outline">
              <i class="fas fa-copy"></i>
              Duplicate Transaction
            </button>
            
            <button @click="archiveTransaction" class="btn btn-outline" v-if="canArchive">
              <i class="fas fa-archive"></i>
              Archive
            </button>
            
            <button @click="deleteTransaction" class="btn btn-danger" v-if="canDelete">
              <i class="fas fa-trash"></i>
              Delete Transaction
            </button>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else class="error-container">
        <div class="error-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2>Transaction Not Found</h2>
        <p>The requested tax transaction could not be found or you don't have permission to view it.</p>
        <router-link to="/tax-transactions" class="btn btn-primary">
          <i class="fas fa-arrow-left"></i>
          Back to Transactions
        </router-link>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <h3>
              <i class="fas fa-exclamation-triangle text-danger"></i>
              Confirm Delete
            </h3>
            <button @click="closeDeleteModal" class="btn-close">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete this tax transaction?</p>
            <div class="transaction-summary">
              <p><strong>ID:</strong> #{{ transaction?.tax_id }}</p>
              <p><strong>Type:</strong> {{ transaction?.tax_type }}</p>
              <p><strong>Amount:</strong> ${{ formatCurrency(transaction?.tax_amount) }}</p>
            </div>
            <p class="warning-text">This action cannot be undone and all related data will be permanently removed.</p>
          </div>
          <div class="modal-footer">
            <button @click="closeDeleteModal" class="btn btn-outline">Cancel</button>
            <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
              <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-trash"></i>
              {{ deleting ? 'Deleting...' : 'Delete Transaction' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'

export default {
  name: 'TaxTransactionDetail',
  components: {
    AppLayout
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    
    const loading = ref(true)
    const deleting = ref(false)
    const showDeleteModal = ref(false)
    const transaction = ref(null)
    const referenceDetails = ref(null)
    const documents = ref([])
    
    const timeline = ref([
      {
        title: 'Transaction Created',
        description: 'Tax transaction was created in the system',
        date: new Date(),
        user: 'John Doe',
        type: 'success',
        icon: 'fas fa-plus-circle'
      },
      {
        title: 'Status Updated',
        description: 'Transaction status changed to Pending',
        date: new Date(Date.now() - 86400000),
        user: 'Jane Smith',
        type: 'info',
        icon: 'fas fa-edit'
      },
      {
        title: 'Document Attached',
        description: 'Supporting document was uploaded',
        date: new Date(Date.now() - 172800000),
        user: 'Mike Johnson',
        type: 'info',
        icon: 'fas fa-paperclip'
      }
    ])

    // Computed properties
    const canEdit = computed(() => {
      return transaction.value && ['Draft', 'Pending'].includes(transaction.value.status)
    })

    const canDelete = computed(() => {
      return transaction.value && transaction.value.status === 'Draft'
    })

    const canArchive = computed(() => {
      return transaction.value && transaction.value.status === 'Completed'
    })

    const baseAmount = computed(() => {
      if (!transaction.value) return 0
      // Calculate based on typical tax rates
      const taxRates = {
        'VAT': 0.1,
        'Sales Tax': 0.08,
        'Income Tax': 0.15,
        'Corporate Tax': 0.21
      }
      const rate = taxRates[transaction.value.tax_type] || 0.1
      return transaction.value.tax_amount / rate
    })

    const taxRate = computed(() => {
      const taxRates = {
        'VAT': 10,
        'Sales Tax': 8,
        'Income Tax': 15,
        'Corporate Tax': 21
      }
      return taxRates[transaction.value?.tax_type] || 10
    })

    const totalAmount = computed(() => {
      return baseAmount.value + parseFloat(transaction.value?.tax_amount || 0)
    })

    const basePercentage = computed(() => {
      return (baseAmount.value / totalAmount.value) * 100
    })

    const taxPercentage = computed(() => {
      return (parseFloat(transaction.value?.tax_amount || 0) / totalAmount.value) * 100
    })

    // Methods
    const fetchTransaction = async () => {
      loading.value = true
      try {
        const response = await axios.get(`/accounting/tax-transactions/${route.params.id}`)
        transaction.value = response.data.data
        
        // Fetch reference details if available
        if (transaction.value.reference_id) {
          await fetchReferenceDetails()
        }
        
        // Fetch related documents
        await fetchDocuments()
      } catch (error) {
        console.error('Error fetching transaction:', error)
        showNotification('Error loading transaction details', 'error')
      } finally {
        loading.value = false
      }
    }

    const fetchReferenceDetails = async () => {
      try {
        // Mock reference details - replace with actual API call
        referenceDetails.value = {
          description: `${transaction.value.reference_type} details`,
          amount: Math.random() * 10000,
          date: new Date().toISOString().split('T')[0]
        }
      } catch (error) {
        console.error('Error fetching reference details:', error)
      }
    }

    const fetchDocuments = async () => {
      try {
        // Mock documents - replace with actual API call
        documents.value = [
          {
            id: 1,
            name: 'tax-receipt.pdf',
            type: 'pdf',
            size: '2.4 MB',
            uploaded_at: new Date().toISOString()
          },
          {
            id: 2,
            name: 'supporting-document.xlsx',
            type: 'excel',
            size: '1.8 MB',
            uploaded_at: new Date(Date.now() - 86400000).toISOString()
          }
        ]
      } catch (error) {
        console.error('Error fetching documents:', error)
      }
    }

    const printTransaction = () => {
      window.print()
    }

    const exportPDF = async () => {
      try {
        const response = await axios.get(`/accounting/tax-transactions/${route.params.id}/pdf`, {
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `tax-transaction-${transaction.value.tax_id}.pdf`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        showNotification('PDF exported successfully', 'success')
      } catch (error) {
        console.error('Error exporting PDF:', error)
        showNotification('Error exporting PDF', 'error')
      }
    }

    const viewReference = () => {
      const referenceRoutes = {
        'Sales Invoice': `/sales/invoices/${transaction.value.reference_id}`,
        'Purchase Invoice': `/purchase/invoices/${transaction.value.reference_id}`,
        'Sales Order': `/sales/orders/${transaction.value.reference_id}`,
        'Purchase Order': `/purchase/orders/${transaction.value.reference_id}`,
        'Journal Entry': `/accounting/journal-entries/${transaction.value.reference_id}`
      }
      
      const route = referenceRoutes[transaction.value.reference_type]
      if (route) {
        router.push(route)
      }
    }

    const duplicateTransaction = () => {
      router.push({
        path: '/tax-transactions/create',
        query: { duplicate: transaction.value.tax_id }
      })
    }

    const archiveTransaction = async () => {
      try {
        await axios.post(`/accounting/tax-transactions/${transaction.value.tax_id}/archive`)
        showNotification('Transaction archived successfully', 'success')
        router.push('/tax-transactions')
      } catch (error) {
        console.error('Error archiving transaction:', error)
        showNotification('Error archiving transaction', 'error')
      }
    }

    const deleteTransaction = () => {
      showDeleteModal.value = true
    }

    const closeDeleteModal = () => {
      showDeleteModal.value = false
    }

    const confirmDelete = async () => {
      deleting.value = true
      try {
        await axios.delete(`/accounting/tax-transactions/${transaction.value.tax_id}`)
        showNotification('Transaction deleted successfully', 'success')
        router.push('/tax-transactions')
      } catch (error) {
        console.error('Error deleting transaction:', error)
        showNotification('Error deleting transaction', 'error')
      } finally {
        deleting.value = false
        closeDeleteModal()
      }
    }

    const refreshTimeline = () => {
      showNotification('Timeline refreshed', 'info')
    }

    const uploadDocument = () => {
      // Implement file upload functionality
      showNotification('Document upload functionality coming soon', 'info')
    }

    const downloadDocument = (doc) => {
      showNotification(`Downloading ${doc.name}`, 'info')
    }

    const previewDocument = (doc) => {
      showNotification(`Previewing ${doc.name}`, 'info')
    }

    const copyToClipboard = async (text) => {
      try {
        await navigator.clipboard.writeText(text)
        showNotification('Copied to clipboard', 'success')
      } catch (error) {
        console.error('Error copying to clipboard:', error)
        showNotification('Error copying to clipboard', 'error')
      }
    }

    // Utility functions
    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }

    const formatDateTime = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US').format(amount || 0)
    }

    const getStatusClass = (status) => {
      const statusClasses = {
        'Draft': 'draft',
        'Pending': 'pending',
        'Approved': 'approved',
        'Completed': 'completed',
        'Cancelled': 'cancelled'
      }
      return statusClasses[status] || 'draft'
    }

    const getDocumentIcon = (type) => {
      const iconMap = {
        'pdf': 'fas fa-file-pdf',
        'excel': 'fas fa-file-excel',
        'word': 'fas fa-file-word',
        'image': 'fas fa-file-image',
        'default': 'fas fa-file'
      }
      return iconMap[type] || iconMap.default
    }

    const getDocumentIconClass = (type) => {
      const classMap = {
        'pdf': 'pdf',
        'excel': 'excel',
        'word': 'word',
        'image': 'image',
        'default': 'default'
      }
      return classMap[type] || classMap.default
    }

    const showNotification = (message, type = 'info') => {
      console.log(`${type}: ${message}`)
    }

    // Lifecycle
    onMounted(() => {
      fetchTransaction()
    })

    return {
      loading,
      deleting,
      showDeleteModal,
      transaction,
      referenceDetails,
      documents,
      timeline,
      canEdit,
      canDelete,
      canArchive,
      baseAmount,
      taxRate,
      totalAmount,
      basePercentage,
      taxPercentage,
      printTransaction,
      exportPDF,
      viewReference,
      duplicateTransaction,
      archiveTransaction,
      deleteTransaction,
      closeDeleteModal,
      confirmDelete,
      refreshTimeline,
      uploadDocument,
      downloadDocument,
      previewDocument,
      copyToClipboard,
      formatDate,
      formatDateTime,
      formatCurrency,
      getStatusClass,
      getDocumentIcon,
      getDocumentIconClass
    }
  }
}
</script>

<style scoped>
.tax-detail-container {
  padding: 2rem;
  background: var(--bg-secondary);
  min-height: 100vh;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.breadcrumb-link {
  color: var(--primary-color);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.breadcrumb-separator {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.breadcrumb-current {
  color: var(--text-secondary);
}

.title-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title i {
  color: var(--primary-color);
}

.title-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.transaction-id {
  background: var(--bg-tertiary);
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-family: monospace;
  font-weight: 600;
  color: var(--text-primary);
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-top: 1rem;
}

/* Loading and Error States */
.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  background: var(--card-bg);
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.loading-spinner {
  font-size: 3rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

.error-icon {
  font-size: 4rem;
  color: #ef4444;
  margin-bottom: 1rem;
}

/* Quick Info Grid */
.quick-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.info-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
}

.info-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.info-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.info-card.amount .info-icon {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.info-card.date .info-icon {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.info-card.type .info-icon {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.info-card.reference .info-icon {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.info-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.info-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

/* Details Grid */
.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.detail-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.card-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-tertiary);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-actions {
  display: flex;
  gap: 0.5rem;
}

.detail-grid {
  padding: 2rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.detail-label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 1rem;
  color: var(--text-primary);
  font-weight: 500;
}

.detail-value.amount {
  font-size: 1.25rem;
  color: var(--primary-color);
  font-weight: 700;
}

.detail-value.tax-type {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.detail-value.tax-code {
  font-family: monospace;
  background: var(--bg-tertiary);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  display: inline-block;
}

/* Status Badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-badge.draft {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.status-badge.pending {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.status-badge.approved {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-badge.completed {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge.cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.status-badge i {
  font-size: 0.6rem;
}

/* Calculation Section */
.calculation-section {
  margin-bottom: 2rem;
}

.calculation-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.calculation-content {
  padding: 2rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.calc-breakdown {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.calc-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-color);
}

.calc-item.total {
  border-top: 2px solid var(--border-color);
  border-bottom: none;
  font-weight: 600;
  font-size: 1.1rem;
  color: var(--primary-color);
}

.calc-label {
  color: var(--text-secondary);
}

.calc-value {
  font-weight: 600;
  color: var(--text-primary);
}

.calc-value.highlight {
  color: var(--primary-color);
  font-size: 1.1rem;
}

.calc-divider {
  height: 2px;
  background: var(--border-color);
  margin: 0.5rem 0;
}

/* Tax Breakdown Chart */
.calc-visual {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.tax-breakdown-chart {
  display: flex;
  height: 40px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--bg-tertiary);
}

.chart-segment {
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.3s ease;
}

.chart-segment.base {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.chart-segment.tax {
  background: linear-gradient(135deg, #10b981, #059669);
}

.segment-label {
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.chart-legend {
  display: flex;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 2px;
}

.legend-color.base {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.legend-color.tax {
  background: linear-gradient(135deg, #10b981, #059669);
}

/* Timeline Section */
.timeline-section {
  margin-bottom: 2rem;
}

.timeline-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.timeline-content {
  padding: 2rem;
}

.timeline-item {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  position: relative;
}

.timeline-item:not(:last-child)::after {
  content: '';
  position: absolute;
  left: 20px;
  top: 40px;
  bottom: -24px;
  width: 2px;
  background: var(--border-color);
}

.timeline-marker {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 2px solid var(--border-color);
  background: var(--card-bg);
}

.timeline-marker.success {
  background: var(--success-color);
  border-color: var(--success-color);
  color: white;
}

.timeline-marker.info {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.timeline-content-item {
  flex: 1;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.5rem;
}

.timeline-header h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.timeline-date {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.timeline-content-item p {
  color: var(--text-secondary);
  margin: 0 0 0.5rem 0;
  font-size: 0.9rem;
}

.timeline-user {
  font-size: 0.8rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Documents Section */
.documents-section {
  margin-bottom: 2rem;
}

.documents-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.documents-grid {
  padding: 2rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-tertiary);
  transition: all 0.3s ease;
}

.document-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.document-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.document-icon.pdf {
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
}

.document-icon.excel {
  background: rgba(34, 197, 94, 0.1);
  color: #22c55e;
}

.document-icon.word {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.document-icon.image {
  background: rgba(168, 85, 247, 0.1);
  color: #a855f7;
}

.document-icon.default {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.document-info {
  flex: 1;
}

.document-info h4 {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.document-info p {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0;
}

.document-actions {
  display: flex;
  gap: 0.25rem;
}

.empty-documents {
  padding: 3rem 2rem;
  text-align: center;
  color: var(--text-secondary);
}

.empty-icon {
  font-size: 3rem;
  color: var(--text-muted);
  margin-bottom: 1rem;
}

.empty-documents h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.empty-documents p {
  margin: 0 0 2rem 0;
}

/* Action Section */
.action-section {
  margin-bottom: 2rem;
}

.action-buttons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Buttons */
.btn {
  padding: 0.875rem 1.5rem;
  border-radius: 12px;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.btn-outline {
  background: transparent;
  color: var(--text-primary);
  border: 2px solid var(--border-color);
}

.btn-outline:hover {
  background: var(--bg-tertiary);
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.btn-small {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  transform: scale(1.05);
}

/* Modal */
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
  background: var(--card-bg);
  border-radius: 16px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 1.5rem 2rem 1rem 2rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-close {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.5rem;
  border-radius: 4px;
}

.btn-close:hover {
  color: var(--text-primary);
  background: var(--bg-tertiary);
}

.modal-body {
  padding: 1.5rem 2rem;
}

.transaction-summary {
  background: var(--bg-tertiary);
  padding: 1rem;
  border-radius: 8px;
  margin: 1rem 0;
}

.transaction-summary p {
  margin: 0.25rem 0;
}

.warning-text {
  color: #ef4444;
  font-weight: 500;
  margin-top: 1rem;
}

.modal-footer {
  padding: 1rem 2rem 1.5rem 2rem;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.text-danger {
  color: #ef4444;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .title-section {
    flex-direction: column;
    gap: 1rem;
  }

  .title-actions {
    justify-content: flex-start;
  }

  .details-grid {
    grid-template-columns: 1fr;
  }

  .calculation-content {
    grid-template-columns: 1fr;
  }

  .quick-info-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
}

@media (max-width: 768px) {
  .tax-detail-container {
    padding: 1rem;
  }

  .page-title {
    font-size: 2rem;
  }

  .header-actions {
    flex-wrap: wrap;
  }

  .quick-info-grid {
    grid-template-columns: 1fr;
  }

  .info-card {
    padding: 1rem;
  }

  .detail-grid {
    padding: 1.5rem;
    grid-template-columns: 1fr;
  }

  .documents-grid {
    grid-template-columns: 1fr;
    padding: 1.5rem;
  }

  .action-buttons {
    flex-direction: column;
    align-items: stretch;
  }

  .chart-legend {
    flex-direction: column;
    gap: 0.5rem;
  }
}

@media (max-width: 480px) {
  .header-content {
    gap: 1rem;
  }

  .breadcrumb {
    flex-wrap: wrap;
  }

  .timeline-item {
    gap: 0.75rem;
  }

  .timeline-marker {
    width: 32px;
    height: 32px;
  }

  .timeline-item:not(:last-child)::after {
    left: 16px;
  }

  .document-item {
    flex-direction: column;
    text-align: center;
    gap: 0.75rem;
  }

  .document-actions {
    justify-content: center;
  }
}
</style>