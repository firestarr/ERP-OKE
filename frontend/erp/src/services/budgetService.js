// src/services/budgetService.js
import axios from 'axios'

class BudgetService {
  constructor() {
    this.baseURL = '/budgets'
  }

  // Budget CRUD Operations
  async getBudgets(params = {}) {
    try {
      const queryString = new URLSearchParams(params).toString()
      const url = queryString ? `${this.baseURL}?${queryString}` : this.baseURL
      const response = await axios.get(url)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async getBudget(id) {
    try {
      const response = await axios.get(`${this.baseURL}/${id}`)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async createBudget(budgetData) {
    try {
      const response = await axios.post(this.baseURL, budgetData)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async updateBudget(id, budgetData) {
    try {
      const response = await axios.put(`${this.baseURL}/${id}`, budgetData)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async deleteBudget(id) {
    try {
      const response = await axios.delete(`${this.baseURL}/${id}`)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Variance Analysis
  async getVarianceReport(params = {}) {
    try {
      const queryString = new URLSearchParams(params).toString()
      const url = `${this.baseURL}/variance-report${queryString ? '?' + queryString : ''}`
      const response = await axios.get(url)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Budget vs Actual Analysis
  async getBudgetVsActualAnalysis(params = {}) {
    try {
      const response = await this.getBudgets({
        ...params,
        include_analysis: true
      })
      return this.processBudgetVsActualData(response)
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Advanced Analytics
  async getStatisticalAnalysis(params = {}) {
    try {
      // This would call a custom endpoint for statistical analysis
      // For now, we'll process the variance report data
      const varianceData = await this.getVarianceReport(params)
      return this.calculateStatistics(varianceData)
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async getAnomalyDetection(params = {}) {
    try {
      const budgets = await this.getBudgets(params)
      return this.detectAnomalies(budgets.data || budgets)
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async getForecastData(params = {}) {
    try {
      const historicalData = await this.getBudgets({
        ...params,
        include_historical: true
      })
      return this.generateForecast(historicalData)
    } catch (error) {
      throw this.handleError(error)
    }
  }

  // Data Processing Methods
  processBudgetVsActualData(response) {
    const budgets = response.data || response
    
    const summary = budgets.reduce((acc, budget) => {
      const budgeted = parseFloat(budget.budgeted_amount || 0)
      const actual = parseFloat(budget.actual_amount || 0)
      const variance = actual - budgeted
      
      acc.totalBudgeted += budgeted
      acc.totalActual += actual
      acc.totalVariance += variance
      acc.count += 1
      
      if (budget.actual_amount !== null) {
        acc.actualCount += 1
      }
      
      return acc
    }, {
      totalBudgeted: 0,
      totalActual: 0,
      totalVariance: 0,
      count: 0,
      actualCount: 0
    })

    // Group by account type for analysis
    const groupedData = this.groupByAccountType(budgets)
    
    return {
      summary,
      groupedData,
      budgets,
      kpis: this.calculateKPIs(summary),
      chartData: this.prepareChartData(groupedData)
    }
  }

  calculateStatistics(varianceData) {
    const budgets = varianceData.budgets || []
    const variances = budgets
      .filter(b => b.variance !== null)
      .map(b => parseFloat(b.variance))

    if (variances.length === 0) {
      return {
        mean: 0,
        standardDeviation: 0,
        variance: 0,
        skewness: 0,
        kurtosis: 0,
        confidence: { lower: 0, upper: 0 }
      }
    }

    const mean = variances.reduce((sum, val) => sum + val, 0) / variances.length
    const variance = variances.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / variances.length
    const standardDeviation = Math.sqrt(variance)
    
    // Calculate skewness and kurtosis
    const skewness = this.calculateSkewness(variances, mean, standardDeviation)
    const kurtosis = this.calculateKurtosis(variances, mean, standardDeviation)
    
    // 95% confidence interval
    const marginOfError = 1.96 * (standardDeviation / Math.sqrt(variances.length))
    const confidence = {
      lower: mean - marginOfError,
      upper: mean + marginOfError
    }

    return {
      mean,
      standardDeviation,
      variance,
      skewness,
      kurtosis,
      confidence,
      sampleSize: variances.length
    }
  }

  detectAnomalies(budgets) {
    const variances = budgets
      .filter(b => b.variance !== null)
      .map(b => ({ ...b, variance: parseFloat(b.variance) }))

    if (variances.length === 0) return []

    const mean = variances.reduce((sum, b) => sum + b.variance, 0) / variances.length
    const stdDev = Math.sqrt(
      variances.reduce((sum, b) => sum + Math.pow(b.variance - mean, 2), 0) / variances.length
    )

    return variances
      .map(budget => {
        const zScore = Math.abs((budget.variance - mean) / stdDev)
        return {
          ...budget,
          zScore,
          isAnomaly: zScore > 2, // 95% confidence level
          severity: this.getAnomalySeverity(zScore)
        }
      })
      .filter(budget => budget.isAnomaly)
      .sort((a, b) => b.zScore - a.zScore)
  }

  generateForecast(historicalData) {
    // Simple linear regression forecast
    // In a real implementation, you might use more sophisticated algorithms
    const budgets = historicalData.data || historicalData
    const timeSeries = this.prepareTimeSeries(budgets)
    
    if (timeSeries.length < 3) {
      return {
        forecast: [],
        trend: 'insufficient_data',
        confidence: 0
      }
    }

    const { slope, intercept, rSquared } = this.linearRegression(timeSeries)
    const forecast = this.projectFuture(slope, intercept, timeSeries.length, 6)
    
    return {
      historical: timeSeries,
      forecast,
      trend: slope > 0 ? 'increasing' : slope < 0 ? 'decreasing' : 'stable',
      confidence: Math.round(rSquared * 100),
      slope,
      intercept
    }
  }

  // Utility Methods
  groupByAccountType(budgets) {
    return budgets.reduce((groups, budget) => {
      const type = budget.chart_of_account?.account_type || 'Other'
      if (!groups[type]) {
        groups[type] = []
      }
      groups[type].push(budget)
      return groups
    }, {})
  }

  calculateKPIs(summary) {
    const variancePercentage = summary.totalBudgeted > 0 
      ? ((summary.totalVariance / summary.totalBudgeted) * 100).toFixed(1)
      : '0'
    
    const achievementRate = summary.totalBudgeted > 0
      ? ((summary.totalActual / summary.totalBudgeted) * 100).toFixed(1)
      : '0'

    return {
      ...summary,
      variancePercentage,
      achievementRate,
      accuracy: summary.actualCount > 0 ? Math.round((summary.actualCount / summary.count) * 100) : 0
    }
  }

  prepareChartData(groupedData) {
    return Object.entries(groupedData).map(([type, budgets]) => {
      const totals = budgets.reduce((acc, budget) => {
        acc.budgeted += parseFloat(budget.budgeted_amount || 0)
        acc.actual += parseFloat(budget.actual_amount || 0)
        return acc
      }, { budgeted: 0, actual: 0 })

      return {
        label: type,
        budgeted: totals.budgeted,
        actual: totals.actual,
        variance: totals.actual - totals.budgeted,
        count: budgets.length
      }
    })
  }

  calculateSkewness(values, mean, stdDev) {
    const n = values.length
    const sum = values.reduce((acc, val) => acc + Math.pow((val - mean) / stdDev, 3), 0)
    return (n / ((n - 1) * (n - 2))) * sum
  }

  calculateKurtosis(values, mean, stdDev) {
    const n = values.length
    const sum = values.reduce((acc, val) => acc + Math.pow((val - mean) / stdDev, 4), 0)
    return ((n * (n + 1)) / ((n - 1) * (n - 2) * (n - 3))) * sum - (3 * Math.pow(n - 1, 2)) / ((n - 2) * (n - 3))
  }

  getAnomalySeverity(zScore) {
    if (zScore > 3) return 'high'
    if (zScore > 2.5) return 'medium'
    return 'low'
  }

  prepareTimeSeries(budgets) {
    // Group budgets by period and calculate totals
    const periodTotals = budgets.reduce((acc, budget) => {
      const period = budget.accounting_period?.name || 'Unknown'
      if (!acc[period]) {
        acc[period] = { budgeted: 0, actual: 0, variance: 0 }
      }
      acc[period].budgeted += parseFloat(budget.budgeted_amount || 0)
      acc[period].actual += parseFloat(budget.actual_amount || 0)
      acc[period].variance += parseFloat(budget.variance || 0)
      return acc
    }, {})

    return Object.entries(periodTotals).map(([period, data], index) => ({
      period,
      index,
      ...data
    }))
  }

  linearRegression(data) {
    const n = data.length
    const sumX = data.reduce((sum, point) => sum + point.index, 0)
    const sumY = data.reduce((sum, point) => sum + point.variance, 0)
    const sumXY = data.reduce((sum, point) => sum + (point.index * point.variance), 0)
    const sumXX = data.reduce((sum, point) => sum + (point.index * point.index), 0)
    const sumYY = data.reduce((sum, point) => sum + (point.variance * point.variance), 0)

    const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX)
    const intercept = (sumY - slope * sumX) / n

    // Calculate R-squared
    const meanY = sumY / n
    const ssRes = data.reduce((sum, point) => {
      const predicted = slope * point.index + intercept
      return sum + Math.pow(point.variance - predicted, 2)
    }, 0)
    const ssTot = data.reduce((sum, point) => sum + Math.pow(point.variance - meanY, 2), 0)
    const rSquared = 1 - (ssRes / ssTot)

    return { slope, intercept, rSquared }
  }

  projectFuture(slope, intercept, startIndex, periods) {
    const forecast = []
    for (let i = 0; i < periods; i++) {
      const index = startIndex + i
      const value = slope * index + intercept
      forecast.push({
        period: `Forecast ${i + 1}`,
        index,
        variance: value,
        confidence: Math.max(0, 100 - (i * 10)) // Decreasing confidence over time
      })
    }
    return forecast
  }

  handleError(error) {
    console.error('Budget Service Error:', error)
    
    if (error.response) {
      // Server responded with error status
      const { status, data } = error.response
      return {
        status,
        message: data.message || 'An error occurred',
        errors: data.errors || null
      }
    } else if (error.request) {
      // Request was made but no response received
      return {
        status: 0,
        message: 'Network error - unable to connect to server',
        errors: null
      }
    } else {
      // Something else happened
      return {
        status: -1,
        message: error.message || 'An unexpected error occurred',
        errors: null
      }
    }
  }
}

// Supporting services
class ChartOfAccountService {
  async getAccounts(params = {}) {
    try {
      const queryString = new URLSearchParams(params).toString()
      const url = queryString ? `/chart-of-accounts?${queryString}` : '/chart-of-accounts'
      const response = await axios.get(url)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  handleError(error) {
    console.error('Chart of Account Service Error:', error)
    return error.response?.data || { message: 'An error occurred' }
  }
}

class AccountingPeriodService {
  async getPeriods(params = {}) {
    try {
      const queryString = new URLSearchParams(params).toString()
      const url = queryString ? `/accounting-periods?${queryString}` : '/accounting-periods'
      const response = await axios.get(url)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  async getPeriod(id) {
    try {
      const response = await axios.get(`/accounting-periods/${id}`)
      return response.data
    } catch (error) {
      throw this.handleError(error)
    }
  }

  handleError(error) {
    console.error('Accounting Period Service Error:', error)
    return error.response?.data || { message: 'An error occurred' }
  }
}

// Export service instances
export const budgetService = new BudgetService()
export const chartOfAccountService = new ChartOfAccountService()
export const accountingPeriodService = new AccountingPeriodService()

export default budgetService