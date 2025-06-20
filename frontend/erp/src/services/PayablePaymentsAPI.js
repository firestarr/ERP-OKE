// src/services/api/PayablePaymentsAPI.js
import axios from 'axios'

class PayablePaymentsAPI {
    constructor() {
        this.baseURL = '/api/accounting/payable-payments'
    }

    /**
     * Get all payable payments with filters
     * @param {Object} params - Filter parameters
     * @returns {Promise}
     */
    async getPayments(params = {}) {
        try {
            const response = await axios.get(this.baseURL, { params })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch payments'
            }
        }
    }

    /**
     * Get a specific payment by ID
     * @param {string|number} id - Payment ID
     * @returns {Promise}
     */
    async getPayment(id) {
        try {
            const response = await axios.get(`${this.baseURL}/${id}`)
            return {
                success: true,
                data: response.data.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch payment'
            }
        }
    }

    /**
     * Create a new payment
     * @param {Object} paymentData - Payment data
     * @returns {Promise}
     */
    async createPayment(paymentData) {
        try {
            const response = await axios.post(this.baseURL, paymentData)
            return {
                success: true,
                data: response.data.data,
                message: response.data.message
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to create payment',
                errors: error.response?.data?.errors || {}
            }
        }
    }

    /**
     * Update an existing payment
     * @param {string|number} id - Payment ID
     * @param {Object} paymentData - Updated payment data
     * @returns {Promise}
     */
    async updatePayment(id, paymentData) {
        try {
            const response = await axios.put(`${this.baseURL}/${id}`, paymentData)
            return {
                success: true,
                data: response.data.data,
                message: response.data.message
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to update payment',
                errors: error.response?.data?.errors || {}
            }
        }
    }

    /**
     * Delete a payment
     * @param {string|number} id - Payment ID
     * @returns {Promise}
     */
    async deletePayment(id) {
        try {
            const response = await axios.delete(`${this.baseURL}/${id}`)
            return {
                success: true,
                message: response.data.message
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to delete payment'
            }
        }
    }

    /**
     * Get exchange rates for a specific date and currency
     * @param {string} currency - Currency code
     * @param {string} date - Date in YYYY-MM-DD format
     * @returns {Promise}
     */
    async getExchangeRates(currency, date) {
        try {
            const response = await axios.get('/api/accounting/exchange-rates', {
                params: { currency, date }
            })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch exchange rates'
            }
        }
    }

    /**
     * Get vendor payables
     * @param {Object} params - Filter parameters
     * @returns {Promise}
     */
    async getVendorPayables(params = {}) {
        try {
            const response = await axios.get('/api/accounting/vendor-payables', { params })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch payables'
            }
        }
    }

    /**
     * Get chart of accounts
     * @param {Object} params - Filter parameters
     * @returns {Promise}
     */
    async getAccounts(params = {}) {
        try {
            const response = await axios.get('/api/accounting/chart-of-accounts', { params })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch accounts'
            }
        }
    }

    /**
     * Get vendors
     * @param {Object} params - Filter parameters
     * @returns {Promise}
     */
    async getVendors(params = {}) {
        try {
            const response = await axios.get('/api/vendors', { params })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch vendors'
            }
        }
    }

    /**
     * Create a new vendor (quick add)
     * @param {Object} vendorData - Vendor data
     * @returns {Promise}
     */
    async createVendor(vendorData) {
        try {
            const response = await axios.post('/api/vendors', vendorData)
            return {
                success: true,
                data: response.data.data,
                message: response.data.message
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to create vendor',
                errors: error.response?.data?.errors || {}
            }
        }
    }

    /**
     * Get journal entries for a payment
     * @param {string|number} paymentId - Payment ID
     * @returns {Promise}
     */
    async getJournalEntries(paymentId) {
        try {
            const response = await axios.get('/api/accounting/journal-entries', {
                params: {
                    reference_type: 'PayablePayment',
                    reference_id: paymentId
                }
            })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch journal entries'
            }
        }
    }

    /**
     * Export payment history
     * @param {Object} filters - Export filters
     * @param {string} format - Export format (csv, xlsx, pdf)
     * @returns {Promise}
     */
    async exportPaymentHistory(filters = {}, format = 'csv') {
        try {
            const response = await axios.get(`${this.baseURL}/export`, {
                params: { ...filters, format },
                responseType: 'blob'
            })
            return {
                success: true,
                data: response.data,
                headers: response.headers
            }
        } catch (error) {
            return {
                success: false,
                error: 'Failed to export payment history'
            }
        }
    }

    /**
     * Get payment statistics
     * @param {Object} filters - Filter parameters
     * @returns {Promise}
     */
    async getPaymentStatistics(filters = {}) {
        try {
            const response = await axios.get(`${this.baseURL}/statistics`, { params: filters })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch statistics'
            }
        }
    }

    /**
     * Get payment trends data
     * @param {Object} params - Parameters (period, vendor_id, etc.)
     * @returns {Promise}
     */
    async getPaymentTrends(params = {}) {
        try {
            const response = await axios.get(`${this.baseURL}/trends`, { params })
            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to fetch payment trends'
            }
        }
    }

    /**
     * Process bulk payment application
     * @param {Array} applications - Array of payment applications
     * @returns {Promise}
     */
    async processBulkPayments(applications) {
        try {
            const response = await axios.post(`${this.baseURL}/bulk`, { applications })
            return {
                success: true,
                data: response.data.data,
                message: response.data.message
            }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'Failed to process bulk payments',
                errors: error.response?.data?.errors || {}
            }
        }
    }

    /**
     * Download payment receipt
     * @param {string|number} paymentId - Payment ID
     * @param {string} format - Format (pdf, html)
     * @returns {Promise}
     */
    async downloadReceipt(paymentId, format = 'pdf') {
        try {
            const response = await axios.get(`${this.baseURL}/${paymentId}/receipt`, {
                params: { format },
                responseType: 'blob'
            })
            return {
                success: true,
                data: response.data,
                headers: response.headers
            }
        } catch (error) {
            return {
                success: false,
                error: 'Failed to download receipt'
            }
        }
    }
}

// Create and export a singleton instance
const payablePaymentsAPI = new PayablePaymentsAPI()
export default payablePaymentsAPI

// Also export the class for testing purposes
export { PayablePaymentsAPI }