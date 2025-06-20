// services/PaymentService.js
import axios from 'axios'

class PaymentService {
  constructor() {
    this.baseURL = '/accounting/receivable-payments'
  }

  // Get all payments with filters
  async getPayments(params = {}) {
    try {
      const response = await axios.get(this.baseURL, { params })
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get single payment details
  async getPayment(id) {
    try {
      const response = await axios.get(`${this.baseURL}/${id}`)
      return response.data.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Create new payment
  async createPayment(paymentData) {
    try {
      const response = await axios.post(this.baseURL, paymentData)
      return response.data.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Delete payment
  async deletePayment(id) {
    try {
      const response = await axios.delete(`${this.baseURL}/${id}`)
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get payment statistics
  async getPaymentStats(params = {}) {
    try {
      const response = await axios.get(`${this.baseURL}/stats`, { params })
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get customer payment summary
  async getCustomerPaymentSummary(customerId, params = {}) {
    try {
      const response = await axios.get(`/customers/${customerId}/payment-summary`, { params })
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get customer payment analytics
  async getCustomerPaymentAnalytics(customerId, params = {}) {
    try {
      const response = await axios.get(`/customers/${customerId}/payment-analytics`, { params })
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get outstanding receivables for customer
  async getOutstandingReceivables(customerId) {
    try {
      const response = await axios.get('/accounting/customer-receivables', {
        params: {
          customer_id: customerId,
          status: 'Open'
        }
      })
      return response.data.data || response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get unapplied payments for customer
  async getUnappliedPayments(customerId) {
    try {
      const response = await axios.get(this.baseURL, {
        params: {
          customer_id: customerId,
          status: 'Unapplied'
        }
      })
      return response.data.data || response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Process payment applications
  async processPaymentApplications(applicationData) {
    try {
      const response = await axios.post('/accounting/payment-applications', applicationData)
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get exchange rates
  async getExchangeRates(fromCurrency, toCurrency, date) {
    try {
      const response = await axios.get('/accounting/exchange-rates', {
        params: {
          from_currency: fromCurrency,
          to_currency: toCurrency,
          date: date
        }
      })
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get bank accounts
  async getBankAccounts() {
    try {
      const response = await axios.get('/accounting/bank-accounts')
      return response.data.data || response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get customers
  async getCustomers(params = {}) {
    try {
      const response = await axios.get('/customers', { params })
      return response.data.data || response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Get journal entries related to payment
  async getPaymentJournalEntries(paymentId) {
    try {
      const response = await axios.get('/accounting/journal-entries', {
        params: {
          reference_type: 'ReceivablePayment',
          reference_id: paymentId
        }
      })
      return response.data.data || response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Export payment history
  async exportPaymentHistory(params = {}) {
    try {
      const response = await axios.get(`${this.baseURL}/export`, {
        params,
        responseType: 'blob'
      })
      return response.data
    } catch (error) {
      this.handleError(error)
      throw error
    }
  }

  // Utility methods
  formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency
    }).format(amount || 0)
  }

  formatDate(date, options = {}) {
    const defaultOptions = {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    }
    return new Date(date).toLocaleDateString('en-US', { ...defaultOptions, ...options })
  }

  formatDateTime(datetime) {
    return new Date(datetime).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  isOverdue(dueDate) {
    return new Date(dueDate) < new Date()
  }

  getDaysOverdue(dueDate) {
    const today = new Date()
    const due = new Date(dueDate)
    const diffTime = today - due
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  }

  getMethodClass(method) {
    const methodClasses = {
      'Cash': 'method-cash',
      'Bank Transfer': 'method-transfer',
      'Credit Card': 'method-card',
      'Check': 'method-check',
      'Wire Transfer': 'method-wire',
      'Online Payment': 'method-online'
    }
    return methodClasses[method] || 'method-default'
  }

  getMethodIcon(method) {
    const methodIcons = {
      'Cash': 'fas fa-money-bill',
      'Bank Transfer': 'fas fa-exchange-alt',
      'Credit Card': 'fas fa-credit-card',
      'Check': 'fas fa-money-check',
      'Wire Transfer': 'fas fa-wire',
      'Online Payment': 'fas fa-globe'
    }
    return methodIcons[method] || 'fas fa-payment'
  }

  // Error handling
  handleError(error) {
    if (error.response) {
      // Server responded with error status
      const status = error.response.status
      const message = error.response.data?.message || 'An error occurred'
      
      switch (status) {
        case 401:
          console.error('Unauthorized: Please log in again')
          // Handle redirect to login
          break
        case 403:
          console.error('Forbidden: You do not have permission to perform this action')
          break
        case 404:
          console.error('Not Found: The requested resource was not found')
          break
        case 422:
          console.error('Validation Error:', error.response.data.errors)
          break
        case 500:
          console.error('Server Error:', message)
          break
        default:
          console.error('Error:', message)
      }
    } else if (error.request) {
      // Network error
      console.error('Network Error: Please check your internet connection')
    } else {
      // Other error
      console.error('Error:', error.message)
    }
  }

  // Validation helpers
  validatePaymentData(data) {
    const errors = {}
    
    if (!data.receivable_id) {
      errors.receivable_id = 'Receivable is required'
    }
    
    if (!data.payment_date) {
      errors.payment_date = 'Payment date is required'
    }
    
    if (!data.payment_method) {
      errors.payment_method = 'Payment method is required'
    }
    
    if (!data.amount || parseFloat(data.amount) <= 0) {
      errors.amount = 'Valid payment amount is required'
    }
    
    if (!data.currency) {
      errors.currency = 'Currency is required'
    }
    
    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }

  // Local storage helpers for form data persistence
  saveFormData(key, data) {
    try {
      localStorage.setItem(`payment_form_${key}`, JSON.stringify(data))
    } catch (error) {
      console.warn('Could not save form data to localStorage:', error)
    }
  }

  getFormData(key) {
    try {
      const data = localStorage.getItem(`payment_form_${key}`)
      return data ? JSON.parse(data) : null
    } catch (error) {
      console.warn('Could not retrieve form data from localStorage:', error)
      return null
    }
  }

  clearFormData(key) {
    try {
      localStorage.removeItem(`payment_form_${key}`)
    } catch (error) {
      console.warn('Could not clear form data from localStorage:', error)
    }
  }
}

// Create singleton instance
const paymentService = new PaymentService()

export default paymentService