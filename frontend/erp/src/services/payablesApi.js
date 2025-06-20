// services/payablesApi.js
import axios from 'axios'

const API_BASE = '/accounting'

class PayablesApiService {
  // Payables CRUD Operations
  async getPayables(params = {}) {
    try {
      const response = await axios.get(`${API_BASE}/vendor-payables`, { params })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async getPayable(id) {
    try {
      const response = await axios.get(`${API_BASE}/vendor-payables/${id}`)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async createPayable(data) {
    try {
      const response = await axios.post(`${API_BASE}/vendor-payables`, data)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async updatePayable(id, data) {
    try {
      const response = await axios.put(`${API_BASE}/vendor-payables/${id}`, data)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async deletePayable(id) {
    try {
      const response = await axios.delete(`${API_BASE}/vendor-payables/${id}`)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Aging Report
  async getAgingReport(params = {}) {
    try {
      const response = await axios.get(`${API_BASE}/vendor-payables/aging`, { params })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Vendor Statements
  async getVendorStatement(params = {}) {
    try {
      const response = await axios.get(`${API_BASE}/vendor-statements`, { params })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async emailVendorStatement(data) {
    try {
      const response = await axios.post(`${API_BASE}/vendor-statements/email`, data)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Payment Operations
  async getPayments(params = {}) {
    try {
      const response = await axios.get(`${API_BASE}/payable-payments`, { params })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async createPayment(data) {
    try {
      const response = await axios.post(`${API_BASE}/payable-payments`, data)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Vendor Operations
  async getVendors() {
    try {
      const response = await axios.get('/vendors')
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async getVendor(id) {
    try {
      const response = await axios.get(`/vendors/${id}`)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Invoice Operations
  async getVendorInvoices(params = {}) {
    try {
      const response = await axios.get('/vendor-invoices', { params })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Export Operations
  async exportPayables(format = 'excel', params = {}) {
    try {
      const response = await axios.get(`${API_BASE}/vendor-payables/export`, {
        params: { ...params, format },
        responseType: 'blob'
      })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async exportAgingReport(format = 'excel', params = {}) {
    try {
      const response = await axios.get(`${API_BASE}/vendor-payables/aging/export`, {
        params: { ...params, format },
        responseType: 'blob'
      })
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Utility Methods
  downloadFile(blob, filename) {
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  }

  handleError(error) {
    if (error.response) {
      // Server responded with error status
      const { status, data } = error.response
      
      switch (status) {
        case 400:
          return { message: 'Bad Request', errors: data.errors || {} }
        case 401:
          return { message: 'Unauthorized access' }
        case 403:
          return { message: 'Access forbidden' }
        case 404:
          return { message: 'Resource not found' }
        case 422:
          return { message: 'Validation failed', errors: data.errors || {} }
        case 500:
          return { message: 'Server error occurred' }
        default:
          return { message: data.message || 'An error occurred' }
      }
    } else if (error.request) {
      // Network error
      return { message: 'Network error - please check your connection' }
    } else {
      // Other error
      return { message: error.message || 'An unexpected error occurred' }
    }
  }
}

// Create singleton instance
const payablesApi = new PayablesApiService()

export default payablesApi

// Named exports for specific use cases
export const {
  getPayables,
  getPayable,
  createPayable,
  updatePayable,
  deletePayable,
  getAgingReport,
  getVendorStatement,
  emailVendorStatement,
  getPayments,
  createPayment,
  getVendors,
  getVendor,
  getVendorInvoices,
  exportPayables,
  exportAgingReport
} = payablesApi