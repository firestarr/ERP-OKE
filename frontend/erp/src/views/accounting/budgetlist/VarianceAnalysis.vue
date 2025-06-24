<template>
  <AppLayout>
    <template #page-title>Variance Analysis Dashboard</template>
    <template #page-subtitle>Advanced statistical analysis of budget variances with predictive insights</template>
    
    <template #page-actions>
      <button @click="generateReport" class="action-button secondary" :disabled="loading">
        <i class="fas fa-file-pdf"></i>
        Generate Report
      </button>
      <button @click="scheduleAnalysis" class="action-button secondary">
        <i class="fas fa-clock"></i>
        Schedule Analysis
      </button>
      <button @click="refreshAnalysis" class="action-button primary" :disabled="loading">
        <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
        Refresh Analysis
      </button>
    </template>

    <div class="variance-analysis-container">
      <!-- Analysis Controls -->
      <div class="controls-section">
        <div class="controls-card">
          <h3><i class="fas fa-sliders-h"></i> Analysis Configuration</h3>
          <div class="controls-grid">
            <div class="control-group">
              <label>Analysis Period</label>
              <select v-model="analysisConfig.period_id" @change="runAnalysis" class="control-select">
                <option value="">Select Period</option>
                <option v-for="period in periods" :key="period.period_id" :value="period.period_id">
                  {{ period.name }}
                </option>
              </select>
            </div>
            <div class="control-group">
              <label>Account Filter</label>
              <select v-model="analysisConfig.account_type" @change="runAnalysis" class="control-select">
                <option value="">All Account Types</option>
                <option value="Revenue">Revenue</option>
                <option value="Expenses">Expenses</option>
                <option value="Assets">Assets</option>
                <option value="Liabilities">Liabilities</option>
              </select>
            </div>
            <div class="control-group">
              <label>Analysis Method</label>
              <select v-model="analysisConfig.method" @change="runAnalysis" class="control-select">
                <option value="absolute">Absolute Variance</option>
                <option value="percentage">Percentage Variance</option>
                <option value="statistical">Statistical Analysis</option>
                <option value="trend">Trend Analysis</option>
              </select>
            </div>
            <div class="control-group">
              <label>Significance Level</label>
              <select v-model="analysisConfig.significance" @change="runAnalysis" class="control-select">
                <option value="5">5% (High)</option>
                <option value="10">10% (Medium)</option>
                <option value="20">20% (Low)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistical Summary -->
      <div class="statistics-section">
        <div class="stats-grid">
          <div class="stat-card primary">
            <div class="stat-header">
              <h4>Mean Variance</h4>
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-body">
              <div class="stat-value">{{ formatCurrency(statistics.meanVariance) }}</div>
              <div class="stat-meta">
                <span class="stat-change" :class="statistics.meanTrend">
                  <i :class="statistics.meanTrend === 'positive' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
                  {{ statistics.meanChange }}%
                </span>
                <span class="stat-label">vs last period</span>
              </div>
            </div>
          </div>
          
          <div class="stat-card secondary">
            <div class="stat-header">
              <h4>Standard Deviation</h4>
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="stat-body">
              <div class="stat-value">{{ formatCurrency(statistics.standardDeviation) }}</div>
              <div class="stat-meta">
                <span class="volatility-indicator" :class="getVolatilityClass(statistics.volatility)">
                  {{ statistics.volatility }}
                </span>
                <span class="stat-label">volatility level</span>
              </div>
            </div>
          </div>
          
          <div class="stat-card accent">
            <div class="stat-header">
              <h4>R² Correlation</h4>
              <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-body">
              <div class="stat-value">{{ statistics.correlation }}%</div>
              <div class="stat-meta">
                <span class="correlation-strength" :class="getCorrelationClass(statistics.correlation)">
                  {{ getCorrelationStrength(statistics.correlation) }}
                </span>
                <span class="stat-label">correlation strength</span>
              </div>
            </div>
          </div>
          
          <div class="stat-card info">
            <div class="stat-header">
              <h4>Prediction Accuracy</h4>
              <i class="fas fa-target"></i>
            </div>
            <div class="stat-body">
              <div class="stat-value">{{ statistics.accuracy }}%</div>
              <div class="stat-meta">
                <span class="accuracy-indicator" :class="getAccuracyClass(statistics.accuracy)">
                  {{ getAccuracyLevel(statistics.accuracy) }}
                </span>
                <span class="stat-label">model accuracy</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Analysis Grid -->
      <div class="analysis-grid">
        <!-- Variance Distribution -->
        <div class="analysis-card">
          <div class="card-header">
            <h3><i class="fas fa-chart-area"></i> Variance Distribution</h3>
            <div class="card-controls">
              <button @click="toggleDistributionView" class="toggle-btn">
                <i class="fas fa-exchange-alt"></i>
                {{ distributionView }}
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="distribution-chart" v-if="!loading">
              <div class="chart-container">
                <!-- Histogram representation -->
                <div class="histogram" v-if="distributionView === 'histogram'">
                  <div class="histogram-bars">
                    <div v-for="(bin, index) in distributionData.histogram" 
                         :key="index" 
                         class="histogram-bar"
                         :style="{ height: getHistogramHeight(bin.count) + '%' }"
                         :title="`Range: ${bin.range}, Count: ${bin.count}`">
                      <span class="bar-count">{{ bin.count }}</span>
                    </div>
                  </div>
                  <div class="histogram-labels">
                    <div v-for="(bin, index) in distributionData.histogram" 
                         :key="index" 
                         class="histogram-label">
                      {{ bin.range }}
                    </div>
                  </div>
                </div>
                
                <!-- Box plot representation -->
                <div class="boxplot" v-else>
                  <div class="boxplot-container">
                    <div class="boxplot-whisker left" :style="{ left: getBoxplotPosition(distributionData.boxplot.min) + '%' }"></div>
                    <div class="boxplot-box" 
                         :style="{ 
                           left: getBoxplotPosition(distributionData.boxplot.q1) + '%',
                           width: (getBoxplotPosition(distributionData.boxplot.q3) - getBoxplotPosition(distributionData.boxplot.q1)) + '%'
                         }">
                      <div class="boxplot-median" :style="{ left: getMedianPosition() + '%' }"></div>
                    </div>
                    <div class="boxplot-whisker right" :style="{ left: getBoxplotPosition(distributionData.boxplot.max) + '%' }"></div>
                    
                    <!-- Outliers -->
                    <div v-for="(outlier, index) in distributionData.boxplot.outliers" 
                         :key="index"
                         class="boxplot-outlier"
                         :style="{ left: getBoxplotPosition(outlier) + '%' }"
                         :title="`Outlier: ${formatCurrency(outlier)}`">
                    </div>
                  </div>
                  
                  <div class="boxplot-stats">
                    <div class="stat-item">
                      <label>Min</label>
                      <span>{{ formatCurrency(distributionData.boxplot.min) }}</span>
                    </div>
                    <div class="stat-item">
                      <label>Q1</label>
                      <span>{{ formatCurrency(distributionData.boxplot.q1) }}</span>
                    </div>
                    <div class="stat-item">
                      <label>Median</label>
                      <span>{{ formatCurrency(distributionData.boxplot.median) }}</span>
                    </div>
                    <div class="stat-item">
                      <label>Q3</label>
                      <span>{{ formatCurrency(distributionData.boxplot.q3) }}</span>
                    </div>
                    <div class="stat-item">
                      <label>Max</label>
                      <span>{{ formatCurrency(distributionData.boxplot.max) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div v-else class="chart-loading">
              <div class="loading-spinner"></div>
              <p>Analyzing variance distribution...</p>
            </div>
          </div>
        </div>

        <!-- Correlation Matrix -->
        <div class="analysis-card">
          <div class="card-header">
            <h3><i class="fas fa-th"></i> Correlation Matrix</h3>
            <div class="card-controls">
              <select v-model="correlationMetric" @change="updateCorrelation" class="metric-select">
                <option value="pearson">Pearson</option>
                <option value="spearman">Spearman</option>
                <option value="kendall">Kendall</option>
              </select>
            </div>
          </div>
          <div class="card-body">
            <div class="correlation-matrix">
              <div class="matrix-grid">
                <div class="matrix-header">
                  <div class="matrix-cell empty"></div>
                  <div v-for="variable in correlationData.variables" 
                       :key="variable" 
                       class="matrix-cell header">
                    {{ variable }}
                  </div>
                </div>
                <div v-for="(row, rowIndex) in correlationData.matrix" 
                     :key="rowIndex" 
                     class="matrix-row">
                  <div class="matrix-cell header">{{ correlationData.variables[rowIndex] }}</div>
                  <div v-for="(value, colIndex) in row" 
                       :key="colIndex" 
                       class="matrix-cell value"
                       :class="getCorrelationCellClass(value)"
                       :title="`Correlation: ${value.toFixed(3)}`">
                    {{ value.toFixed(2) }}
                  </div>
                </div>
              </div>
              
              <div class="correlation-legend">
                <div class="legend-scale">
                  <div class="scale-item negative-strong">-1.0</div>
                  <div class="scale-item negative-moderate">-0.5</div>
                  <div class="scale-item neutral">0.0</div>
                  <div class="scale-item positive-moderate">0.5</div>
                  <div class="scale-item positive-strong">1.0</div>
                </div>
                <div class="legend-labels">
                  <span>Negative</span>
                  <span>No Correlation</span>
                  <span>Positive</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Advanced Analytics -->
      <div class="advanced-section">
        <div class="advanced-grid">
          <!-- Regression Analysis -->
          <div class="analysis-card">
            <div class="card-header">
              <h3><i class="fas fa-chart-line"></i> Regression Analysis</h3>
            </div>
            <div class="card-body">
              <div class="regression-chart">
                <svg class="regression-svg" viewBox="0 0 400 300">
                  <!-- Grid -->
                  <defs>
                    <pattern id="regressionGrid" width="40" height="30" patternUnits="userSpaceOnUse">
                      <path d="M 40 0 L 0 0 0 30" fill="none" stroke="#e2e8f0" stroke-width="1"/>
                    </pattern>
                  </defs>
                  <rect width="400" height="300" fill="url(#regressionGrid)" />
                  
                  <!-- Data points -->
                  <g v-for="(point, index) in regressionData.points" :key="index">
                    <circle
                      :cx="getRegressionX(point.x)"
                      :cy="getRegressionY(point.y)"
                      r="3"
                      :fill="getPointColor(point.variance)"
                      class="regression-point"
                      :title="`Budget: ${formatCurrency(point.x)}, Actual: ${formatCurrency(point.y)}`"
                    />
                  </g>
                  
                  <!-- Regression line -->
                  <line
                    :x1="50"
                    :y1="getRegressionY(regressionData.line.start)"
                    :x2="350"
                    :y2="getRegressionY(regressionData.line.end)"
                    stroke="#6366f1"
                    stroke-width="2"
                    stroke-dasharray="5,5"
                  />
                  
                  <!-- Confidence interval -->
                  <polygon
                    :points="getConfidencePolygon()"
                    fill="rgba(99, 102, 241, 0.1)"
                    stroke="rgba(99, 102, 241, 0.3)"
                    stroke-width="1"
                  />
                </svg>
                
                <div class="regression-stats">
                  <div class="stat-row">
                    <label>R²:</label>
                    <span>{{ regressionData.rsquared.toFixed(4) }}</span>
                  </div>
                  <div class="stat-row">
                    <label>Slope:</label>
                    <span>{{ regressionData.slope.toFixed(4) }}</span>
                  </div>
                  <div class="stat-row">
                    <label>Intercept:</label>
                    <span>{{ formatCurrency(regressionData.intercept) }}</span>
                  </div>
                  <div class="stat-row">
                    <label>P-value:</label>
                    <span :class="getPValueClass(regressionData.pvalue)">
                      {{ regressionData.pvalue.toFixed(6) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Anomaly Detection -->
          <div class="analysis-card">
            <div class="card-header">
              <h3><i class="fas fa-exclamation-triangle"></i> Anomaly Detection</h3>
              <div class="card-controls">
                <span class="anomaly-count">{{ anomalies.length }} anomalies detected</span>
              </div>
            </div>
            <div class="card-body">
              <div class="anomaly-list">
                <div v-for="anomaly in anomalies" 
                     :key="anomaly.id" 
                     class="anomaly-item"
                     :class="anomaly.severity">
                  <div class="anomaly-indicator">
                    <i :class="getAnomalyIcon(anomaly.severity)"></i>
                  </div>
                  <div class="anomaly-content">
                    <h4>{{ anomaly.account_name }}</h4>
                    <p>{{ anomaly.description }}</p>
                    <div class="anomaly-metrics">
                      <span class="metric">
                        <label>Variance:</label>
                        <strong>{{ formatCurrency(anomaly.variance) }}</strong>
                      </span>
                      <span class="metric">
                        <label>Z-Score:</label>
                        <strong>{{ anomaly.zscore.toFixed(2) }}</strong>
                      </span>
                      <span class="metric">
                        <label>Probability:</label>
                        <strong>{{ (anomaly.probability * 100).toFixed(1) }}%</strong>
                      </span>
                    </div>
                  </div>
                  <div class="anomaly-actions">
                    <button @click="investigateAnomaly(anomaly)" class="btn-investigate">
                      <i class="fas fa-search"></i>
                      Investigate
                    </button>
                    <button @click="dismissAnomaly(anomaly.id)" class="btn-dismiss">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
              </div>
              
              <div v-if="anomalies.length === 0" class="no-anomalies">
                <i class="fas fa-check-circle"></i>
                <p>No significant anomalies detected</p>
                <small>All variances are within expected ranges</small>
              </div>
            </div>
          </div>

          <!-- Forecasting -->
          <div class="analysis-card">
            <div class="card-header">
              <h3><i class="fas fa-crystal-ball"></i> Variance Forecasting</h3>
              <div class="card-controls">
                <select v-model="forecastPeriods" @change="updateForecast" class="period-select">
                  <option value="3">3 Months</option>
                  <option value="6">6 Months</option>
                  <option value="12">12 Months</option>
                </select>
              </div>
            </div>
            <div class="card-body">
              <div class="forecast-chart">
                <svg class="forecast-svg" viewBox="0 0 600 200">
                  <!-- Grid -->
                  <defs>
                    <pattern id="forecastGrid" width="60" height="20" patternUnits="userSpaceOnUse">
                      <path d="M 60 0 L 0 0 0 20" fill="none" stroke="#e2e8f0" stroke-width="1"/>
                    </pattern>
                  </defs>
                  <rect width="600" height="200" fill="url(#forecastGrid)" />
                  
                  <!-- Historical data -->
                  <polyline
                    :points="getHistoricalPoints()"
                    fill="none"
                    stroke="#10b981"
                    stroke-width="2"
                  />
                  
                  <!-- Forecast line -->
                  <polyline
                    :points="getForecastPoints()"
                    fill="none"
                    stroke="#6366f1"
                    stroke-width="2"
                    stroke-dasharray="5,5"
                  />
                  
                  <!-- Confidence band -->
                  <polygon
                    :points="getConfidenceBand()"
                    fill="rgba(99, 102, 241, 0.1)"
                    stroke="rgba(99, 102, 241, 0.2)"
                    stroke-width="1"
                  />
                  
                  <!-- Data points -->
                  <g v-for="(point, index) in forecastData.historical" :key="'hist-' + index">
                    <circle
                      :cx="getForecastX(index)"
                      :cy="getForecastY(point.value)"
                      r="3"
                      fill="#10b981"
                      class="forecast-point"
                    />
                  </g>
                  
                  <g v-for="(point, index) in forecastData.forecast" :key="'forecast-' + index">
                    <circle
                      :cx="getForecastX(forecastData.historical.length + index)"
                      :cy="getForecastY(point.value)"
                      r="3"
                      fill="#6366f1"
                      class="forecast-point"
                    />
                  </g>
                </svg>
                
                <div class="forecast-legend">
                  <div class="legend-item">
                    <div class="legend-line historical"></div>
                    <span>Historical</span>
                  </div>
                  <div class="legend-item">
                    <div class="legend-line forecast"></div>
                    <span>Forecast</span>
                  </div>
                  <div class="legend-item">
                    <div class="legend-area confidence"></div>
                    <span>95% Confidence</span>
                  </div>
                </div>
              </div>
              
              <div class="forecast-summary">
                <div class="summary-item">
                  <label>Next Period Forecast:</label>
                  <span class="forecast-value">{{ formatCurrency(forecastData.nextPeriod) }}</span>
                </div>
                <div class="summary-item">
                  <label>Trend Direction:</label>
                  <span class="trend-indicator" :class="forecastData.trend">
                    <i :class="getTrendIcon(forecastData.trend)"></i>
                    {{ forecastData.trend }}
                  </span>
                </div>
                <div class="summary-item">
                  <label>Confidence Level:</label>
                  <span class="confidence-level" :class="getConfidenceClass(forecastData.confidence)">
                    {{ forecastData.confidence }}%
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Actionable Insights -->
      <div class="insights-section">
        <div class="insights-card">
          <div class="card-header">
            <h3><i class="fas fa-lightbulb"></i> AI-Powered Insights & Recommendations</h3>
            <div class="card-controls">
              <button @click="refreshInsights" class="refresh-insights-btn">
                <i class="fas fa-brain"></i>
                Regenerate Insights
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="insights-grid">
              <div v-for="insight in aiInsights" 
                   :key="insight.id" 
                   class="insight-card"
                   :class="insight.priority">
                <div class="insight-header">
                  <div class="insight-icon">
                    <i :class="insight.icon"></i>
                  </div>
                  <div class="insight-meta">
                    <h4>{{ insight.title }}</h4>
                    <span class="insight-category">{{ insight.category }}</span>
                  </div>
                  <div class="insight-priority">
                    <span class="priority-badge" :class="insight.priority">
                      {{ insight.priority }}
                    </span>
                  </div>
                </div>
                
                <div class="insight-content">
                  <p>{{ insight.description }}</p>
                  
                  <div class="insight-metrics" v-if="insight.metrics">
                    <div v-for="metric in insight.metrics" 
                         :key="metric.label" 
                         class="metric-item">
                      <label>{{ metric.label }}:</label>
                      <span>{{ metric.value }}</span>
                    </div>
                  </div>
                  
                  <div class="insight-actions" v-if="insight.actions">
                    <h5>Recommended Actions:</h5>
                    <ul class="action-list">
                      <li v-for="action in insight.actions" :key="action">{{ action }}</li>
                    </ul>
                  </div>
                  
                  <div class="insight-impact" v-if="insight.impact">
                    <div class="impact-indicator">
                      <label>Potential Impact:</label>
                      <div class="impact-bar">
                        <div class="impact-fill" 
                             :style="{ width: insight.impact + '%' }"
                             :class="getImpactClass(insight.impact)">
                        </div>
                      </div>
                      <span class="impact-value">{{ insight.impact }}%</span>
                    </div>
                  </div>
                </div>
                
                <div class="insight-footer">
                  <button @click="implementRecommendation(insight)" class="btn-implement">
                    <i class="fas fa-play"></i>
                    Implement
                  </button>
                  <button @click="saveInsight(insight.id)" class="btn-save">
                    <i class="fas fa-bookmark"></i>
                    Save
                  </button>
                  <button @click="shareInsight(insight.id)" class="btn-share">
                    <i class="fas fa-share"></i>
                    Share
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Analysis Details Modal -->
    <div v-if="showAnalysisModal" class="modal-overlay" @click="closeAnalysisModal">
      <div class="modal-content large" @click.stop>
        <div class="modal-header">
          <h3><i class="fas fa-chart-line"></i> Detailed Analysis Report</h3>
          <button @click="closeAnalysisModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="analysis-report">
            <!-- Detailed analysis content would go here -->
            <div class="report-section">
              <h4>Executive Summary</h4>
              <p>{{ selectedAnalysis.summary }}</p>
            </div>
            <div class="report-section">
              <h4>Key Findings</h4>
              <ul>
                <li v-for="finding in selectedAnalysis.findings" :key="finding">{{ finding }}</li>
              </ul>
            </div>
            <div class="report-section">
              <h4>Statistical Analysis</h4>
              <div class="stats-table">
                <!-- Statistical data table -->
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeAnalysisModal" class="btn-cancel">Close</button>
          <button @click="exportAnalysis" class="btn-export">
            <i class="fas fa-download"></i>
            Export Report
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'VarianceAnalysis',
  components: {
  },
  setup() {
    const loading = ref(false)
    const distributionView = ref('histogram')
    const correlationMetric = ref('pearson')
    const forecastPeriods = ref(6)
    const showAnalysisModal = ref(false)
    const selectedAnalysis = ref({})
    
    const periods = ref([])
    const varianceData = ref([])
    
    const analysisConfig = reactive({
      period_id: '',
      account_type: '',
      method: 'absolute',
      significance: 10
    })
    
    const statistics = ref({
      meanVariance: 0,
      standardDeviation: 0,
      correlation: 85,
      accuracy: 92,
      meanTrend: 'positive',
      meanChange: 12.5,
      volatility: 'Medium'
    })
    
    const distributionData = ref({
      histogram: [
        { range: '-50K+', count: 2 },
        { range: '-25K', count: 8 },
        { range: '-10K', count: 15 },
        { range: '0', count: 25 },
        { range: '+10K', count: 18 },
        { range: '+25K', count: 12 },
        { range: '+50K+', count: 5 }
      ],
      boxplot: {
        min: -45000,
        q1: -8000,
        median: 2000,
        q3: 15000,
        max: 62000,
        outliers: [-75000, 85000, -60000]
      }
    })
    
    const correlationData = ref({
      variables: ['Budget', 'Actual', 'Revenue', 'Expenses'],
      matrix: [
        [1.00, 0.85, 0.72, -0.45],
        [0.85, 1.00, 0.68, -0.52],
        [0.72, 0.68, 1.00, -0.35],
        [-0.45, -0.52, -0.35, 1.00]
      ]
    })
    
    const regressionData = ref({
      points: generateRegressionPoints(),
      line: { start: 500000, end: 1800000 },
      rsquared: 0.8247,
      slope: 0.9156,
      intercept: 25000,
      pvalue: 0.000012
    })
    
    const anomalies = ref([
      {
        id: 1,
        account_name: 'Marketing Expenses',
        description: 'Variance significantly exceeds normal distribution (3.2σ)',
        variance: 125000,
        zscore: 3.24,
        probability: 0.12,
        severity: 'high'
      },
      {
        id: 2,
        account_name: 'IT Infrastructure',
        description: 'Unusual spending pattern detected',
        variance: -45000,
        zscore: -2.8,
        probability: 0.05,
        severity: 'medium'
      },
      {
        id: 3,
        account_name: 'Travel & Entertainment',
        description: 'Spending spike outside confidence interval',
        variance: 78000,
        zscore: 2.1,
        probability: 0.18,
        severity: 'low'
      }
    ])
    
    const forecastData = ref({
      historical: [
        { value: 15000 }, { value: -8000 }, { value: 22000 }, 
        { value: 12000 }, { value: -5000 }, { value: 18000 }
      ],
      forecast: [
        { value: 25000 }, { value: 28000 }, { value: 31000 },
        { value: 27000 }, { value: 35000 }, { value: 38000 }
      ],
      nextPeriod: 25000,
      trend: 'increasing',
      confidence: 87
    })
    
    const aiInsights = ref([
      {
        id: 1,
        title: 'Revenue Optimization Opportunity',
        category: 'Revenue Analysis',
        priority: 'high',
        icon: 'fas fa-chart-line',
        description: 'Marketing spend efficiency is declining. Current cost per acquisition increased 23% while conversion rates dropped 8%.',
        metrics: [
          { label: 'Potential Savings', value: 'IDR 125M' },
          { label: 'ROI Improvement', value: '+18%' },
          { label: 'Implementation Time', value: '2-3 weeks' }
        ],
        actions: [
          'Reallocate 30% of digital marketing budget to high-performing channels',
          'Implement A/B testing for campaign optimization',
          'Review vendor contracts for better rate negotiations'
        ],
        impact: 75
      },
      {
        id: 2,
        title: 'Operational Cost Pattern Analysis',
        category: 'Cost Management',
        priority: 'medium',
        icon: 'fas fa-cogs',
        description: 'Recurring operational costs show seasonal variance that can be better managed through predictive budgeting.',
        metrics: [
          { label: 'Cost Reduction', value: 'IDR 85M' },
          { label: 'Accuracy Improvement', value: '+12%' }
        ],
        actions: [
          'Implement dynamic budgeting based on seasonal patterns',
          'Negotiate flexible contracts with key suppliers'
        ],
        impact: 45
      },
      {
        id: 3,
        title: 'Cash Flow Optimization',
        category: 'Financial Planning',
        priority: 'low',
        icon: 'fas fa-water',
        description: 'Working capital efficiency can be improved by optimizing payment terms and collection cycles.',
        metrics: [
          { label: 'Cash Flow Improvement', value: 'IDR 200M' },
          { label: 'Collection Cycle', value: '-5 days' }
        ],
        actions: [
          'Implement early payment discounts',
          'Optimize invoice processing automation'
        ],
        impact: 30
      }
    ])

    function generateRegressionPoints() {
      const points = []
      for (let i = 0; i < 50; i++) {
        const budget = 500000 + Math.random() * 1500000
        const actual = budget * (0.9 + Math.random() * 0.3) + (Math.random() - 0.5) * 200000
        points.push({
          x: budget,
          y: actual,
          variance: actual - budget
        })
      }
      return points
    }

    const fetchVarianceData = async () => {
      try {
        loading.value = true
        
        const params = new URLSearchParams()
        if (analysisConfig.period_id) params.append('period_id', analysisConfig.period_id)
        if (analysisConfig.account_type) params.append('account_type', analysisConfig.account_type)
        
        const response = await axios.get(`/accounting/budgets/variance-report?${params}`)
        
        // Process the variance data for statistical analysis
        varianceData.value = response.data.budgets || []
        
        // Update statistics based on real data
        updateStatistics()
        
      } catch (error) {
        console.error('Error fetching variance data:', error)
      } finally {
        loading.value = false
      }
    }

    const fetchPeriods = async () => {
      try {
        const response = await axios.get('/accounting/accounting-periods')
        periods.value = response.data.data || response.data
      } catch (error) {
        console.error('Error fetching periods:', error)
      }
    }

    const updateStatistics = () => {
      if (varianceData.value.length === 0) return
      
      const variances = varianceData.value
        .filter(item => item.variance !== null)
        .map(item => parseFloat(item.variance))
      
      if (variances.length === 0) return
      
      const mean = variances.reduce((sum, val) => sum + val, 0) / variances.length
      const squaredDiffs = variances.map(val => Math.pow(val - mean, 2))
      const variance = squaredDiffs.reduce((sum, val) => sum + val, 0) / variances.length
      const stdDev = Math.sqrt(variance)
      
      statistics.value = {
        ...statistics.value,
        meanVariance: mean,
        standardDeviation: stdDev
      }
    }

    const runAnalysis = () => {
      fetchVarianceData()
    }

    const toggleDistributionView = () => {
      distributionView.value = distributionView.value === 'histogram' ? 'boxplot' : 'histogram'
    }

    const updateCorrelation = () => {
      // Update correlation matrix based on selected metric
      console.log('Updating correlation with metric:', correlationMetric.value)
    }

    const updateForecast = () => {
      // Update forecast based on selected periods
      console.log('Updating forecast for periods:', forecastPeriods.value)
    }

    // Chart helper functions
    const getHistogramHeight = (count) => {
      const maxCount = Math.max(...distributionData.value.histogram.map(bin => bin.count))
      return (count / maxCount) * 100
    }

    const getBoxplotPosition = (value) => {
      const min = distributionData.value.boxplot.min
      const max = distributionData.value.boxplot.max
      return ((value - min) / (max - min)) * 80 + 10
    }

    const getMedianPosition = () => {
      const q1 = distributionData.value.boxplot.q1
      const q3 = distributionData.value.boxplot.q3
      const median = distributionData.value.boxplot.median
      return ((median - q1) / (q3 - q1)) * 100
    }

    const getCorrelationCellClass = (value) => {
      const abs = Math.abs(value)
      if (abs >= 0.8) return 'correlation-strong'
      if (abs >= 0.5) return 'correlation-moderate'
      if (abs >= 0.3) return 'correlation-weak'
      return 'correlation-none'
    }

    const getRegressionX = (value) => {
      const minX = Math.min(...regressionData.value.points.map(p => p.x))
      const maxX = Math.max(...regressionData.value.points.map(p => p.x))
      return 50 + ((value - minX) / (maxX - minX)) * 300
    }

    const getRegressionY = (value) => {
      const minY = Math.min(...regressionData.value.points.map(p => p.y))
      const maxY = Math.max(...regressionData.value.points.map(p => p.y))
      return 250 - ((value - minY) / (maxY - minY)) * 200
    }

    const getPointColor = (variance) => {
      if (variance > 0) return '#ef4444'
      if (variance < 0) return '#10b981'
      return '#64748b'
    }

    const getConfidencePolygon = () => {
      // Generate confidence interval polygon points
      return '50,50 350,50 350,250 50,250'
    }

    const getForecastX = (index) => {
      const totalPoints = forecastData.value.historical.length + forecastData.value.forecast.length
      return 50 + (index / (totalPoints - 1)) * 500
    }

    const getForecastY = (value) => {
      const allValues = [
        ...forecastData.value.historical.map(p => p.value),
        ...forecastData.value.forecast.map(p => p.value)
      ]
      const minY = Math.min(...allValues)
      const maxY = Math.max(...allValues)
      return 180 - ((value - minY) / (maxY - minY)) * 160
    }

    const getHistoricalPoints = () => {
      return forecastData.value.historical.map((point, index) => {
        const x = getForecastX(index)
        const y = getForecastY(point.value)
        return `${x},${y}`
      }).join(' ')
    }

    const getForecastPoints = () => {
      const startIndex = forecastData.value.historical.length - 1
      const points = [
        `${getForecastX(startIndex)},${getForecastY(forecastData.value.historical[startIndex].value)}`
      ]
      
      forecastData.value.forecast.forEach((point, index) => {
        const x = getForecastX(startIndex + 1 + index)
        const y = getForecastY(point.value)
        points.push(`${x},${y}`)
      })
      
      return points.join(' ')
    }

    const getConfidenceBand = () => {
      // Generate confidence band polygon
      return '300,50 550,80 550,150 300,120'
    }

    // Utility functions
    const formatCurrency = (amount) => {
      if (amount === null || amount === undefined) return 'IDR 0'
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount)
    }

    const getVolatilityClass = (volatility) => {
      switch (volatility.toLowerCase()) {
        case 'low': return 'volatility-low'
        case 'medium': return 'volatility-medium'
        case 'high': return 'volatility-high'
        default: return 'volatility-medium'
      }
    }

    const getCorrelationClass = (correlation) => {
      if (correlation >= 80) return 'correlation-excellent'
      if (correlation >= 60) return 'correlation-good'
      if (correlation >= 40) return 'correlation-fair'
      return 'correlation-poor'
    }

    const getCorrelationStrength = (correlation) => {
      if (correlation >= 80) return 'Strong'
      if (correlation >= 60) return 'Moderate'
      if (correlation >= 40) return 'Weak'
      return 'Very Weak'
    }

    const getAccuracyClass = (accuracy) => {
      if (accuracy >= 90) return 'accuracy-excellent'
      if (accuracy >= 80) return 'accuracy-good'
      if (accuracy >= 70) return 'accuracy-fair'
      return 'accuracy-poor'
    }

    const getAccuracyLevel = (accuracy) => {
      if (accuracy >= 90) return 'Excellent'
      if (accuracy >= 80) return 'Good'
      if (accuracy >= 70) return 'Fair'
      return 'Poor'
    }

    const getPValueClass = (pvalue) => {
      if (pvalue < 0.01) return 'significant-high'
      if (pvalue < 0.05) return 'significant-medium'
      if (pvalue < 0.1) return 'significant-low'
      return 'not-significant'
    }

    const getAnomalyIcon = (severity) => {
      switch (severity) {
        case 'high': return 'fas fa-exclamation-triangle'
        case 'medium': return 'fas fa-exclamation-circle'
        case 'low': return 'fas fa-info-circle'
        default: return 'fas fa-question-circle'
      }
    }

    const getTrendIcon = (trend) => {
      switch (trend) {
        case 'increasing': return 'fas fa-arrow-up'
        case 'decreasing': return 'fas fa-arrow-down'
        case 'stable': return 'fas fa-minus'
        default: return 'fas fa-question'
      }
    }

    const getConfidenceClass = (confidence) => {
      if (confidence >= 90) return 'confidence-high'
      if (confidence >= 70) return 'confidence-medium'
      return 'confidence-low'
    }

    const getImpactClass = (impact) => {
      if (impact >= 70) return 'impact-high'
      if (impact >= 40) return 'impact-medium'
      return 'impact-low'
    }

    // Action functions
    const generateReport = () => {
      console.log('Generating comprehensive analysis report')
    }

    const scheduleAnalysis = () => {
      console.log('Scheduling automated analysis')
    }

    const refreshAnalysis = () => {
      runAnalysis()
    }

    const refreshInsights = () => {
      console.log('Regenerating AI insights')
    }

    const investigateAnomaly = (anomaly) => {
      selectedAnalysis.value = {
        summary: `Detailed investigation of ${anomaly.account_name} anomaly`,
        findings: [
          'Variance significantly exceeds normal distribution',
          'Pattern suggests systematic budget miscalculation',
          'Recommend immediate budget revision'
        ]
      }
      showAnalysisModal.value = true
    }

    const dismissAnomaly = (id) => {
      anomalies.value = anomalies.value.filter(anomaly => anomaly.id !== id)
    }

    const implementRecommendation = (insight) => {
      console.log('Implementing recommendation:', insight.title)
    }

    const saveInsight = (id) => {
      console.log('Saving insight:', id)
    }

    const shareInsight = (id) => {
      console.log('Sharing insight:', id)
    }

    const closeAnalysisModal = () => {
      showAnalysisModal.value = false
    }

    const exportAnalysis = () => {
      console.log('Exporting detailed analysis')
    }

    onMounted(() => {
      fetchPeriods()
      runAnalysis()
    })

    return {
      loading,
      distributionView,
      correlationMetric,
      forecastPeriods,
      showAnalysisModal,
      selectedAnalysis,
      periods,
      analysisConfig,
      statistics,
      distributionData,
      correlationData,
      regressionData,
      anomalies,
      forecastData,
      aiInsights,
      runAnalysis,
      toggleDistributionView,
      updateCorrelation,
      updateForecast,
      getHistogramHeight,
      getBoxplotPosition,
      getMedianPosition,
      getCorrelationCellClass,
      getRegressionX,
      getRegressionY,
      getPointColor,
      getConfidencePolygon,
      getForecastX,
      getForecastY,
      getHistoricalPoints,
      getForecastPoints,
      getConfidenceBand,
      formatCurrency,
      getVolatilityClass,
      getCorrelationClass,
      getCorrelationStrength,
      getAccuracyClass,
      getAccuracyLevel,
      getPValueClass,
      getAnomalyIcon,
      getTrendIcon,
      getConfidenceClass,
      getImpactClass,
      generateReport,
      scheduleAnalysis,
      refreshAnalysis,
      refreshInsights,
      investigateAnomaly,
      dismissAnomaly,
      implementRecommendation,
      saveInsight,
      shareInsight,
      closeAnalysisModal,
      exportAnalysis
    }
  }
}
</script>

<style scoped>
.variance-analysis-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* Controls Section */
.controls-section .controls-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
}

.controls-card h3 {
  color: #1e293b;
  margin-bottom: 1rem;
  font-size: 1.1rem;
}

.controls-card h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.controls-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.control-group label {
  display: block;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.control-select {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  background: white;
  transition: all 0.3s ease;
}

.control-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Statistics Section */
.statistics-section {
  margin-bottom: 2rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.stat-card.primary::before {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.stat-card.secondary::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.accent::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card.info::before {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.stat-header h4 {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-header i {
  color: #6366f1;
  font-size: 1.2rem;
}

.stat-body .stat-value {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.stat-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-weight: 600;
}

.stat-change.positive {
  color: #16a34a;
}

.stat-change.negative {
  color: #dc2626;
}

.stat-label {
  color: #94a3b8;
}

.volatility-indicator,
.correlation-strength,
.accuracy-indicator {
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
}

.volatility-low,
.correlation-excellent,
.accuracy-excellent {
  background: #dcfce7;
  color: #16a34a;
}

.volatility-medium,
.correlation-good,
.accuracy-good {
  background: #fef3c7;
  color: #d97706;
}

.volatility-high,
.correlation-fair,
.accuracy-fair {
  background: #fee2e2;
  color: #dc2626;
}

.correlation-poor,
.accuracy-poor {
  background: #f1f5f9;
  color: #64748b;
}

/* Analysis Grid */
.analysis-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.analysis-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.card-header h3 {
  color: #1e293b;
  margin: 0;
  font-size: 1rem;
}

.card-header h3 i {
  color: #6366f1;
  margin-right: 0.5rem;
}

.card-controls {
  display: flex;
  gap: 0.5rem;
}

.toggle-btn,
.metric-select,
.period-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: white;
  color: #64748b;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.8rem;
}

.toggle-btn:hover,
.metric-select:focus,
.period-select:focus {
  border-color: #6366f1;
  color: #6366f1;
}

.card-body {
  padding: 1.5rem;
}

/* Distribution Chart */
.distribution-chart .chart-container {
  min-height: 300px;
}

.histogram {
  display: flex;
  flex-direction: column;
  height: 250px;
}

.histogram-bars {
  display: flex;
  justify-content: space-between;
  align-items: end;
  flex: 1;
  gap: 0.5rem;
}

.histogram-bar {
  flex: 1;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 4px 4px 0 0;
  min-height: 10px;
  display: flex;
  align-items: end;
  justify-content: center;
  padding: 0.25rem;
  transition: all 0.3s ease;
  cursor: pointer;
}

.histogram-bar:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.bar-count {
  color: white;
  font-size: 0.8rem;
  font-weight: 600;
}

.histogram-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 0.5rem;
}

.histogram-label {
  font-size: 0.8rem;
  color: #64748b;
  text-align: center;
  flex: 1;
}

/* Box Plot */
.boxplot {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.boxplot-container {
  height: 80px;
  position: relative;
  margin: 2rem 0;
}

.boxplot-whisker {
  position: absolute;
  top: 35px;
  width: 2px;
  height: 10px;
  background: #64748b;
}

.boxplot-box {
  position: absolute;
  top: 20px;
  height: 40px;
  background: rgba(99, 102, 241, 0.2);
  border: 2px solid #6366f1;
  border-radius: 4px;
}

.boxplot-median {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #1e293b;
}

.boxplot-outlier {
  position: absolute;
  top: 35px;
  width: 8px;
  height: 8px;
  background: #ef4444;
  border-radius: 50%;
  margin-left: -4px;
  cursor: pointer;
}

.boxplot-stats {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
}

.stat-item {
  text-align: center;
}

.stat-item label {
  display: block;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.stat-item span {
  color: #1e293b;
  font-weight: 600;
}

/* Correlation Matrix */
.correlation-matrix {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.matrix-grid {
  display: flex;
  flex-direction: column;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.matrix-header,
.matrix-row {
  display: flex;
}

.matrix-cell {
  flex: 1;
  padding: 0.75rem;
  text-align: center;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  font-size: 0.8rem;
}

.matrix-cell.empty {
  background: #f8fafc;
}

.matrix-cell.header {
  background: #f1f5f9;
  font-weight: 600;
  color: #374151;
}

.matrix-cell.value {
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.matrix-cell.value:hover {
  background: #f8fafc;
}

.correlation-strong {
  background: #dcfce7;
  color: #16a34a;
}

.correlation-moderate {
  background: #fef3c7;
  color: #d97706;
}

.correlation-weak {
  background: #fee2e2;
  color: #dc2626;
}

.correlation-none {
  background: #f1f5f9;
  color: #64748b;
}

.correlation-legend {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.legend-scale {
  display: flex;
  height: 20px;
  border-radius: 4px;
  overflow: hidden;
}

.scale-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  color: white;
  font-weight: 600;
}

.negative-strong {
  background: #dc2626;
}

.negative-moderate {
  background: #f59e0b;
}

.neutral {
  background: #64748b;
}

.positive-moderate {
  background: #d97706;
}

.positive-strong {
  background: #16a34a;
}

.legend-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  color: #64748b;
}

/* Regression Analysis */
.regression-chart {
  display: flex;
  gap: 1rem;
}

.regression-svg {
  flex: 1;
  min-height: 300px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.regression-point {
  cursor: pointer;
  transition: all 0.3s ease;
}

.regression-point:hover {
  r: 5;
  filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.3));
}

.regression-stats {
  min-width: 150px;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  border-radius: 6px;
  font-size: 0.85rem;
}

.stat-row label {
  color: #64748b;
  font-weight: 500;
}

.stat-row span {
  color: #1e293b;
  font-weight: 600;
}

.significant-high {
  color: #16a34a;
}

.significant-medium {
  color: #d97706;
}

.significant-low {
  color: #f59e0b;
}

.not-significant {
  color: #dc2626;
}

/* Anomaly Detection */
.anomaly-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 400px;
  overflow-y: auto;
}

.anomaly-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  border-left: 4px solid #e2e8f0;
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.anomaly-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.anomaly-item.high {
  border-left-color: #dc2626;
  background: #fef2f2;
}

.anomaly-item.medium {
  border-left-color: #f59e0b;
  background: #fffbeb;
}

.anomaly-item.low {
  border-left-color: #3b82f6;
  background: #eff6ff;
}

.anomaly-indicator {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1rem;
  flex-shrink: 0;
}

.anomaly-item.high .anomaly-indicator {
  background: #dc2626;
}

.anomaly-item.medium .anomaly-indicator {
  background: #f59e0b;
}

.anomaly-item.low .anomaly-indicator {
  background: #3b82f6;
}

.anomaly-content {
  flex: 1;
}

.anomaly-content h4 {
  color: #1e293b;
  font-size: 0.95rem;
  margin: 0 0 0.25rem 0;
  font-weight: 600;
}

.anomaly-content p {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.4;
  margin: 0 0 0.75rem 0;
}

.anomaly-metrics {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.metric {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.metric label {
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.metric strong {
  color: #1e293b;
  font-size: 0.85rem;
}

.anomaly-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: flex-end;
}

.btn-investigate,
.btn-dismiss {
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.btn-investigate {
  background: #6366f1;
  color: white;
}

.btn-investigate:hover {
  background: #5b5bd6;
}

.btn-dismiss {
  background: #f1f5f9;
  color: #64748b;
}

.btn-dismiss:hover {
  background: #e2e8f0;
}

.no-anomalies {
  text-align: center;
  padding: 2rem;
  color: #64748b;
}

.no-anomalies i {
  font-size: 2rem;
  color: #16a34a;
  margin-bottom: 0.5rem;
  display: block;
}

/* Forecasting */
.forecast-chart {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.forecast-svg {
  width: 100%;
  height: 200px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.forecast-point {
  cursor: pointer;
  transition: all 0.3s ease;
}

.forecast-point:hover {
  r: 5;
  filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.3));
}

.forecast-legend {
  display: flex;
  gap: 1rem;
  justify-content: center;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: #64748b;
}

.legend-line {
  width: 20px;
  height: 2px;
}

.legend-line.historical {
  background: #10b981;
}

.legend-line.forecast {
  background: #6366f1;
  background-image: repeating-linear-gradient(
    90deg,
    #6366f1,
    #6366f1 5px,
    transparent 5px,
    transparent 10px
  );
}

.legend-area {
  width: 20px;
  height: 10px;
  border-radius: 2px;
}

.legend-area.confidence {
  background: rgba(99, 102, 241, 0.2);
  border: 1px solid rgba(99, 102, 241, 0.3);
}

.forecast-summary {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  text-align: center;
}

.summary-item label {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
}

.forecast-value {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
}

.trend-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  font-weight: 600;
  text-transform: capitalize;
}

.trend-indicator.increasing {
  color: #16a34a;
}

.trend-indicator.decreasing {
  color: #dc2626;
}

.trend-indicator.stable {
  color: #64748b;
}

.confidence-level {
  font-weight: 600;
}

.confidence-high {
  color: #16a34a;
}

.confidence-medium {
  color: #d97706;
}

.confidence-low {
  color: #dc2626;
}

/* Advanced Section */
.advanced-section {
  margin-bottom: 2rem;
}

.advanced-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

/* AI Insights */
.insights-section {
  margin-bottom: 2rem;
}

.insights-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.refresh-insights-btn {
  padding: 0.5rem 1rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.refresh-insights-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.insights-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.insight-card {
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  transition: all 0.3s ease;
}

.insight-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.insight-card.high {
  border-left: 4px solid #dc2626;
}

.insight-card.medium {
  border-left: 4px solid #f59e0b;
}

.insight-card.low {
  border-left: 4px solid #3b82f6;
}

.insight-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.insight-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.insight-card.high .insight-icon {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}

.insight-card.medium .insight-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.insight-card.low .insight-icon {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.insight-meta {
  flex: 1;
}

.insight-meta h4 {
  color: #1e293b;
  font-size: 1rem;
  margin: 0 0 0.25rem 0;
  font-weight: 600;
}

.insight-category {
  color: #64748b;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.insight-priority {
  flex-shrink: 0;
}

.priority-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.priority-badge.high {
  background: #fee2e2;
  color: #dc2626;
}

.priority-badge.medium {
  background: #fef3c7;
  color: #d97706;
}

.priority-badge.low {
  background: #dbeafe;
  color: #2563eb;
}

.insight-content {
  padding: 1.5rem;
}

.insight-content p {
  color: #64748b;
  line-height: 1.6;
  margin: 0 0 1rem 0;
}

.insight-metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.metric-item {
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  text-align: center;
}

.metric-item label {
  display: block;
  font-size: 0.75rem;
  color: #64748b;
  margin-bottom: 0.25rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.metric-item span {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
}

.insight-actions {
  margin-bottom: 1rem;
}

.insight-actions h5 {
  color: #1e293b;
  font-size: 0.9rem;
  margin: 0 0 0.5rem 0;
  font-weight: 600;
}

.action-list {
  margin: 0;
  padding-left: 1rem;
  color: #64748b;
}

.action-list li {
  margin-bottom: 0.25rem;
  font-size: 0.85rem;
  line-height: 1.4;
}

.insight-impact {
  margin-bottom: 1rem;
}

.impact-indicator {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.impact-indicator label {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
  min-width: 100px;
}

.impact-bar {
  flex: 1;
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.impact-fill {
  height: 100%;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.impact-high {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}

.impact-medium {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.impact-low {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.impact-value {
  font-weight: 600;
  color: #1e293b;
  min-width: 40px;
  text-align: right;
}

.insight-footer {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.btn-implement,
.btn-save,
.btn-share {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-implement {
  background: #6366f1;
  color: white;
}

.btn-implement:hover {
  background: #5b5bd6;
}

.btn-save {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-save:hover {
  background: #e2e8f0;
}

.btn-share {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-share:hover {
  background: #e2e8f0;
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
  max-width: 600px;
  width: 90%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content.large {
  max-width: 800px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  margin: 0;
  color: #1e293b;
}

.modal-header h3 i {
  margin-right: 0.5rem;
  color: #6366f1;
}

.close-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.close-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.modal-body {
  padding: 1.5rem;
}

.analysis-report {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.report-section h4 {
  color: #1e293b;
  margin: 0 0 0.5rem 0;
  font-weight: 600;
}

.report-section p {
  color: #64748b;
  line-height: 1.6;
  margin: 0;
}

.report-section ul {
  margin: 0;
  padding-left: 1rem;
  color: #64748b;
}

.report-section li {
  margin-bottom: 0.25rem;
}

.modal-footer {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.btn-cancel,
.btn-export {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-cancel {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
  background: #e2e8f0;
}

.btn-export {
  background: #6366f1;
  color: white;
}

.btn-export:hover {
  background: #5b5bd6;
}

/* Loading */
.chart-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 200px;
  color: #64748b;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top: 4px solid #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Action Button Styles */
.action-button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  border-radius: 12px;
  border: none;
  cursor: pointer;
  font-weight: 500;
  font-size: 0.85rem;
  transition: all 0.3s ease;
}

.action-button.primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.action-button.primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.action-button.secondary {
  background: white;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.action-button.secondary:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
}

.anomaly-count {
  background: #fee2e2;
  color: #dc2626;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .controls-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .analysis-grid,
  .advanced-grid {
    grid-template-columns: 1fr;
  }
  
  .insights-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .controls-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .forecast-summary {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .modal-content {
    margin: 1rem;
    width: calc(100% - 2rem);
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .btn-cancel,
  .btn-export {
    width: 100%;
    justify-content: center;
  }
  
  .histogram-bars {
    gap: 0.25rem;
  }
  
  .anomaly-metrics {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .insight-footer {
    flex-direction: column;
  }
  
  .btn-implement,
  .btn-save,
  .btn-share {
    width: 100%;
    justify-content: center;
  }
}
</style>