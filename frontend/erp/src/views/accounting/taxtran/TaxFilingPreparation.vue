<template>
  <AppLayout>
    <div class="tax-filing-container">
      <!-- Header Section -->
      <div class="page-header">
        <div class="header-content">
          <div class="breadcrumb">
            <router-link to="/tax-transactions" class="breadcrumb-link">
              <i class="fas fa-receipt"></i>
              Tax Transactions
            </router-link>
            <i class="fas fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current">Tax Filing Preparation</span>
          </div>
          
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-file-upload"></i>
              Tax Filing Preparation
            </h1>
            <p class="page-description">Prepare and submit tax filings with automated compliance checks</p>
          </div>

          <div class="header-actions">
            <button @click="saveDraft" class="btn btn-outline" :disabled="saving">
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              Save Draft
            </button>
            <button @click="previewFiling" class="btn btn-outline">
              <i class="fas fa-eye"></i>
              Preview Filing
            </button>
            <button @click="submitFiling" class="btn btn-primary" :disabled="!canSubmit || submitting">
              <i v-if="submitting" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-paper-plane"></i>
              Submit Filing
            </button>
          </div>
        </div>
      </div>

      <!-- Filing Progress -->
      <div class="progress-section">
        <div class="progress-card">
          <div class="progress-header">
            <h3>
              <i class="fas fa-tasks"></i>
              Filing Progress
            </h3>
            <span class="progress-percentage">{{ overallProgress }}%</span>
          </div>
          
          <div class="progress-steps">
            <div 
              v-for="(step, index) in filingSteps" 
              :key="index"
              class="progress-step"
              :class="{ 
                active: currentStep === index, 
                completed: step.completed,
                error: step.hasError 
              }"
            >
              <div class="step-indicator">
                <i v-if="step.hasError" class="fas fa-exclamation-triangle"></i>
                <i v-else-if="step.completed" class="fas fa-check"></i>
                <span v-else>{{ index + 1 }}</span>
              </div>
              <div class="step-content">
                <h4>{{ step.title }}</h4>
                <p>{{ step.description }}</p>
                <div v-if="step.hasError" class="step-error">
                  {{ step.errorMessage }}
                </div>
              </div>
            </div>
          </div>
          
          <div class="progress-bar">
            <div class="progress-fill" :style="{ width: overallProgress + '%' }"></div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="filing-content">
        <!-- Step 1: Data Collection -->
        <div v-if="currentStep === 0" class="filing-step">
          <div class="step-card">
            <div class="step-header">
              <h2>
                <i class="fas fa-database"></i>
                Data Collection & Validation
              </h2>
              <p>Gather and validate all tax transaction data for the filing period</p>
            </div>

            <div class="collection-section">
              <!-- Period Selection -->
              <div class="period-selection">
                <h3>Filing Period</h3>
                <div class="period-grid">
                  <div class="period-group">
                    <label class="form-label">Tax Year</label>
                    <select v-model="filingData.taxYear" class="form-select" @change="updatePeriod">
                      <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                    </select>
                  </div>
                  
                  <div class="period-group">
                    <label class="form-label">Filing Period</label>
                    <select v-model="filingData.period" class="form-select" @change="updatePeriod">
                      <option value="annual">Annual Filing</option>
                      <option value="quarterly">Quarterly Filing</option>
                      <option value="monthly">Monthly Filing</option>
                    </select>
                  </div>
                  
                  <div v-if="filingData.period !== 'annual'" class="period-group">
                    <label class="form-label">{{ filingData.period === 'quarterly' ? 'Quarter' : 'Month' }}</label>
                    <select v-model="filingData.subPeriod" class="form-select" @change="updatePeriod">
                      <option v-for="option in subPeriodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Data Summary -->
              <div class="data-summary">
                <h3>
                  <i class="fas fa-chart-bar"></i>
                  Transaction Summary
                </h3>
                <div class="summary-grid">
                  <div class="summary-card">
                    <div class="summary-icon vat">
                      <i class="fas fa-percentage"></i>
                    </div>
                    <div class="summary-content">
                      <h4>${{ formatCurrency(taxSummary.vat || 0) }}</h4>
                      <p>VAT Collected</p>
                      <span class="transaction-count">{{ taxSummary.vatCount || 0 }} transactions</span>
                    </div>
                  </div>
                  
                  <div class="summary-card">
                    <div class="summary-icon income">
                      <i class="fas fa-coins"></i>
                    </div>
                    <div class="summary-content">
                      <h4>${{ formatCurrency(taxSummary.income || 0) }}</h4>
                      <p>Income Tax</p>
                      <span class="transaction-count">{{ taxSummary.incomeCount || 0 }} transactions</span>
                    </div>
                  </div>
                  
                  <div class="summary-card">
                    <div class="summary-icon corporate">
                      <i class="fas fa-building"></i>
                    </div>
                    <div class="summary-content">
                      <h4>${{ formatCurrency(taxSummary.corporate || 0) }}</h4>
                      <p>Corporate Tax</p>
                      <span class="transaction-count">{{ taxSummary.corporateCount || 0 }} transactions</span>
                    </div>
                  </div>
                  
                  <div class="summary-card">
                    <div class="summary-icon withholding">
                      <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="summary-content">
                      <h4>${{ formatCurrency(taxSummary.withholding || 0) }}</h4>
                      <p>Withholding Tax</p>
                      <span class="transaction-count">{{ taxSummary.withholdingCount || 0 }} transactions</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Data Validation -->
              <div class="validation-section">
                <h3>
                  <i class="fas fa-shield-alt"></i>
                  Data Validation
                </h3>
                <div class="validation-results">
                  <div 
                    v-for="validation in validationResults" 
                    :key="validation.type"
                    class="validation-item"
                    :class="validation.status"
                  >
                    <div class="validation-icon">
                      <i v-if="validation.status === 'passed'" class="fas fa-check-circle"></i>
                      <i v-else-if="validation.status === 'warning'" class="fas fa-exclamation-triangle"></i>
                      <i v-else class="fas fa-times-circle"></i>
                    </div>
                    <div class="validation-content">
                      <h4>{{ validation.title }}</h4>
                      <p>{{ validation.message }}</p>
                      <div v-if="validation.details" class="validation-details">
                        <button @click="toggleDetails(validation)" class="btn-link">
                          <i :class="validation.showDetails ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                          {{ validation.showDetails ? 'Hide' : 'Show' }} Details
                        </button>
                        <div v-if="validation.showDetails" class="details-content">
                          <ul>
                            <li v-for="detail in validation.details" :key="detail">{{ detail }}</li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Form Generation -->
        <div v-if="currentStep === 1" class="filing-step">
          <div class="step-card">
            <div class="step-header">
              <h2>
                <i class="fas fa-file-alt"></i>
                Tax Form Generation
              </h2>
              <p>Generate required tax forms based on your transaction data</p>
            </div>

            <div class="form-generation-section">
              <!-- Form Selection -->
              <div class="form-selection">
                <h3>Required Forms</h3>
                <div class="forms-grid">
                  <div 
                    v-for="form in requiredForms" 
                    :key="form.id"
                    class="form-card"
                    :class="{ selected: form.selected, generated: form.generated }"
                  >
                    <div class="form-header">
                      <div class="form-checkbox">
                        <input 
                          type="checkbox" 
                          v-model="form.selected" 
                          :id="`form-${form.id}`"
                          @change="updateFormSelection"
                        >
                        <label :for="`form-${form.id}`"></label>
                      </div>
                      <div class="form-status">
                        <i v-if="form.generated" class="fas fa-check-circle text-success"></i>
                        <i v-else-if="form.generating" class="fas fa-spinner fa-spin text-info"></i>
                        <i v-else class="fas fa-file-alt text-muted"></i>
                      </div>
                    </div>
                    
                    <div class="form-content">
                      <h4>{{ form.name }}</h4>
                      <p>{{ form.description }}</p>
                      <div class="form-details">
                        <span class="form-type">{{ form.type }}</span>
                        <span class="form-due-date">Due: {{ formatDate(form.dueDate) }}</span>
                      </div>
                      
                      <div v-if="form.generated" class="form-actions">
                        <button @click="previewForm(form)" class="btn btn-outline btn-sm">
                          <i class="fas fa-eye"></i>
                          Preview
                        </button>
                        <button @click="downloadForm(form)" class="btn btn-outline btn-sm">
                          <i class="fas fa-download"></i>
                          Download
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Generation Controls -->
              <div class="generation-controls">
                <div class="control-buttons">
                  <button 
                    @click="generateSelectedForms" 
                    class="btn btn-primary"
                    :disabled="!hasSelectedForms || generatingForms"
                  >
                    <i v-if="generatingForms" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-cogs"></i>
                    Generate Selected Forms
                  </button>
                  
                  <button @click="generateAllForms" class="btn btn-outline">
                    <i class="fas fa-list"></i>
                    Generate All Forms
                  </button>
                </div>
                
                <div class="generation-options">
                  <label class="checkbox-label">
                    <input type="checkbox" v-model="filingOptions.includeSupporting">
                    Include supporting documents
                  </label>
                  <label class="checkbox-label">
                    <input type="checkbox" v-model="filingOptions.electronicSignature">
                    Apply electronic signature
                  </label>
                  <label class="checkbox-label">
                    <input type="checkbox" v-model="filingOptions.autoSubmit">
                    Auto-submit after generation
                  </label>
                </div>
              </div>

              <!-- Generation Progress -->
              <div v-if="generatingForms" class="generation-progress">
                <div class="progress-header">
                  <h4>Generating Forms...</h4>
                  <span>{{ generationProgress }}%</span>
                </div>
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: generationProgress + '%' }"></div>
                </div>
                <div class="progress-details">
                  <p>{{ currentGenerationTask }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Review & Compliance -->
        <div v-if="currentStep === 2" class="filing-step">
          <div class="step-card">
            <div class="step-header">
              <h2>
                <i class="fas fa-search"></i>
                Review & Compliance Check
              </h2>
              <p>Review generated forms and ensure compliance with tax regulations</p>
            </div>

            <div class="review-section">
              <!-- Compliance Dashboard -->
              <div class="compliance-dashboard">
                <h3>
                  <i class="fas fa-shield-alt"></i>
                  Compliance Status
                </h3>
                <div class="compliance-grid">
                  <div class="compliance-card">
                    <div class="compliance-score" :class="getComplianceClass(complianceScore.overall)">
                      <span class="score-value">{{ complianceScore.overall }}%</span>
                      <span class="score-label">Overall</span>
                    </div>
                    <div class="compliance-details">
                      <div class="detail-item">
                        <span class="detail-label">Accuracy:</span>
                        <span class="detail-value">{{ complianceScore.accuracy }}%</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Completeness:</span>
                        <span class="detail-value">{{ complianceScore.completeness }}%</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Timeliness:</span>
                        <span class="detail-value">{{ complianceScore.timeliness }}%</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Compliance Issues -->
              <div v-if="complianceIssues.length > 0" class="compliance-issues">
                <h3>
                  <i class="fas fa-exclamation-triangle"></i>
                  Compliance Issues
                </h3>
                <div class="issues-list">
                  <div 
                    v-for="issue in complianceIssues" 
                    :key="issue.id"
                    class="issue-item"
                    :class="issue.severity"
                  >
                    <div class="issue-icon">
                      <i v-if="issue.severity === 'critical'" class="fas fa-times-circle"></i>
                      <i v-else-if="issue.severity === 'warning'" class="fas fa-exclamation-triangle"></i>
                      <i v-else class="fas fa-info-circle"></i>
                    </div>
                    <div class="issue-content">
                      <h4>{{ issue.title }}</h4>
                      <p>{{ issue.description }}</p>
                      <div v-if="issue.recommendation" class="issue-recommendation">
                        <strong>Recommendation:</strong> {{ issue.recommendation }}
                      </div>
                    </div>
                    <div class="issue-actions">
                      <button @click="resolveIssue(issue)" class="btn btn-outline btn-sm">
                        <i class="fas fa-wrench"></i>
                        Fix
                      </button>
                      <button @click="dismissIssue(issue)" class="btn btn-text btn-sm">
                        <i class="fas fa-times"></i>
                        Dismiss
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Form Review -->
              <div class="form-review">
                <h3>
                  <i class="fas fa-clipboard-check"></i>
                  Form Review
                </h3>
                <div class="review-forms">
                  <div 
                    v-for="form in generatedForms" 
                    :key="form.id"
                    class="review-form-card"
                  >
                    <div class="form-preview">
                      <div class="preview-header">
                        <h4>{{ form.name }}</h4>
                        <div class="form-status-badge" :class="form.status">
                          {{ form.status }}
                        </div>
                      </div>
                      
                      <div class="preview-content">
                        <iframe 
                          :src="form.previewUrl" 
                          class="form-iframe"
                          @load="onFormPreviewLoad(form)"
                        ></iframe>
                      </div>
                      
                      <div class="preview-actions">
                        <button @click="editForm(form)" class="btn btn-outline btn-sm">
                          <i class="fas fa-edit"></i>
                          Edit
                        </button>
                        <button @click="validateForm(form)" class="btn btn-outline btn-sm">
                          <i class="fas fa-check"></i>
                          Validate
                        </button>
                        <button @click="downloadForm(form)" class="btn btn-outline btn-sm">
                          <i class="fas fa-download"></i>
                          Download
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Digital Signature -->
              <div class="signature-section">
                <h3>
                  <i class="fas fa-signature"></i>
                  Digital Signature
                </h3>
                <div class="signature-card">
                  <div class="signature-info">
                    <h4>Electronic Signature Required</h4>
                    <p>Your digital signature is required to certify the accuracy of the tax filing.</p>
                  </div>
                  
                  <div class="signature-controls">
                    <div class="signature-options">
                      <label class="radio-label">
                        <input type="radio" value="certificate" v-model="signatureMethod">
                        <span>Digital Certificate</span>
                      </label>
                      <label class="radio-label">
                        <input type="radio" value="biometric" v-model="signatureMethod">
                        <span>Biometric Signature</span>
                      </label>
                      <label class="radio-label">
                        <input type="radio" value="pin" v-model="signatureMethod">
                        <span>PIN Authentication</span>
                      </label>
                    </div>
                    
                    <button @click="applySignature" class="btn btn-primary">
                      <i class="fas fa-pen-nib"></i>
                      Apply Signature
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 4: Submission -->
        <div v-if="currentStep === 3" class="filing-step">
          <div class="step-card">
            <div class="step-header">
              <h2>
                <i class="fas fa-paper-plane"></i>
                Tax Filing Submission
              </h2>
              <p>Submit your tax filing to the relevant tax authorities</p>
            </div>

            <div class="submission-section">
              <!-- Pre-submission Checklist -->
              <div class="checklist-section">
                <h3>
                  <i class="fas fa-clipboard-list"></i>
                  Pre-submission Checklist
                </h3>
                <div class="checklist">
                  <div 
                    v-for="item in submissionChecklist" 
                    :key="item.id"
                    class="checklist-item"
                    :class="{ completed: item.completed }"
                  >
                    <div class="checklist-checkbox">
                      <input 
                        type="checkbox" 
                        v-model="item.completed" 
                        :id="`checklist-${item.id}`"
                        @change="updateSubmissionReadiness"
                      >
                      <label :for="`checklist-${item.id}`">
                        <i class="fas fa-check"></i>
                      </label>
                    </div>
                    <div class="checklist-content">
                      <h4>{{ item.title }}</h4>
                      <p>{{ item.description }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Submission Details -->
              <div class="submission-details">
                <h3>
                  <i class="fas fa-info-circle"></i>
                  Submission Details
                </h3>
                <div class="details-grid">
                  <div class="detail-card">
                    <h4>Filing Information</h4>
                    <div class="detail-list">
                      <div class="detail-row">
                        <span class="detail-label">Tax Year:</span>
                        <span class="detail-value">{{ filingData.taxYear }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Filing Period:</span>
                        <span class="detail-value">{{ getFilingPeriodLabel() }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value">{{ formatDate(filingDueDate) }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Submission Method:</span>
                        <span class="detail-value">Electronic Filing</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="detail-card">
                    <h4>Tax Summary</h4>
                    <div class="detail-list">
                      <div class="detail-row">
                        <span class="detail-label">Total Tax Amount:</span>
                        <span class="detail-value amount">${{ formatCurrency(totalTaxAmount) }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Forms Generated:</span>
                        <span class="detail-value">{{ generatedForms.length }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Attachments:</span>
                        <span class="detail-value">{{ attachmentCount }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Compliance Score:</span>
                        <span class="detail-value">{{ complianceScore.overall }}%</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Submission Options -->
              <div class="submission-options">
                <h3>
                  <i class="fas fa-cogs"></i>
                  Submission Options
                </h3>
                <div class="options-grid">
                  <label class="option-card">
                    <input type="checkbox" v-model="submissionOptions.scheduleSubmission">
                    <div class="option-content">
                      <h4>Schedule Submission</h4>
                      <p>Submit automatically at the optimal time</p>
                    </div>
                  </label>
                  
                  <label class="option-card">
                    <input type="checkbox" v-model="submissionOptions.notifyCompletion">
                    <div class="option-content">
                      <h4>Email Notification</h4>
                      <p>Receive confirmation when submission is complete</p>
                    </div>
                  </label>
                  
                  <label class="option-card">
                    <input type="checkbox" v-model="submissionOptions.generateReceipt">
                    <div class="option-content">
                      <h4>Generate Receipt</h4>
                      <p>Create submission receipt for records</p>
                    </div>
                  </label>
                  
                  <label class="option-card">
                    <input type="checkbox" v-model="submissionOptions.backupCopy">
                    <div class="option-content">
                      <h4>Backup Copy</h4>
                      <p>Save backup copy to secure storage</p>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Final Submission -->
              <div class="final-submission">
                <div class="submission-warning">
                  <i class="fas fa-exclamation-triangle"></i>
                  <p>
                    <strong>Important:</strong> Once submitted, changes cannot be made to this filing. 
                    Please ensure all information is accurate before proceeding.
                  </p>
                </div>
                
                <div class="submission-actions">
                  <button @click="submitFinalFiling" class="btn btn-success btn-lg" :disabled="!canSubmitFinal">
                    <i class="fas fa-check-circle"></i>
                    Submit Tax Filing
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation -->
        <div class="step-navigation">
          <div class="nav-left">
            <button 
              v-if="currentStep > 0" 
              @click="previousStep" 
              class="btn btn-outline"
            >
              <i class="fas fa-chevron-left"></i>
              Previous Step
            </button>
          </div>

          <div class="nav-center">
            <div class="step-indicators">
              <button 
                v-for="(step, index) in filingSteps" 
                :key="index"
                @click="goToStep(index)"
                class="step-indicator"
                :class="{ 
                  active: currentStep === index, 
                  completed: step.completed,
                  disabled: !canGoToStep(index)
                }"
                :disabled="!canGoToStep(index)"
              >
                {{ index + 1 }}
              </button>
            </div>
          </div>

          <div class="nav-right">
            <button 
              v-if="currentStep < filingSteps.length - 1" 
              @click="nextStep" 
              class="btn btn-primary"
              :disabled="!canProceedToNext"
            >
              Next Step
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Submission Success Modal -->
      <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
        <div class="modal-content success-modal" @click.stop>
          <div class="modal-header">
            <div class="success-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <h3>Filing Submitted Successfully!</h3>
          </div>
          <div class="modal-body">
            <p>Your tax filing has been successfully submitted to the tax authorities.</p>
            <div class="submission-info">
              <div class="info-item">
                <span class="info-label">Confirmation Number:</span>
                <span class="info-value">{{ submissionConfirmation.number }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Submission Date:</span>
                <span class="info-value">{{ formatDateTime(submissionConfirmation.date) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Processing Time:</span>
                <span class="info-value">{{ submissionConfirmation.processingTime }}</span>
              </div>
            </div>
            <p class="notice">
              You will receive an email confirmation shortly. Keep your confirmation number for your records.
            </p>
          </div>
          <div class="modal-footer">
            <button @click="downloadReceipt" class="btn btn-outline">
              <i class="fas fa-download"></i>
              Download Receipt
            </button>
            <button @click="closeSuccessModal" class="btn btn-primary">
              <i class="fas fa-check"></i>
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'

export default {
  name: 'TaxFilingPreparation',
  components: {
    AppLayout
  },
  setup() {
    const router = useRouter()
    
    const saving = ref(false)
    const submitting = ref(false)
    const generatingForms = ref(false)
    const generationProgress = ref(0)
    const currentGenerationTask = ref('')
    const currentStep = ref(0)
    const showSuccessModal = ref(false)
    const signatureMethod = ref('certificate')

    const filingData = reactive({
      taxYear: new Date().getFullYear() - 1,
      period: 'annual',
      subPeriod: ''
    })

    const filingOptions = reactive({
      includeSupporting: true,
      electronicSignature: true,
      autoSubmit: false
    })

    const submissionOptions = reactive({
      scheduleSubmission: false,
      notifyCompletion: true,
      generateReceipt: true,
      backupCopy: true
    })

    const taxSummary = reactive({
      vat: 45000,
      vatCount: 125,
      income: 38000,
      incomeCount: 89,
      corporate: 52000,
      corporateCount: 67,
      withholding: 15000,
      withholdingCount: 234
    })

    const complianceScore = reactive({
      overall: 92,
      accuracy: 95,
      completeness: 88,
      timeliness: 94
    })

    const submissionConfirmation = reactive({
      number: '',
      date: null,
      processingTime: ''
    })

    const filingSteps = ref([
      {
        title: 'Data Collection',
        description: 'Gather and validate transaction data',
        completed: false,
        hasError: false,
        errorMessage: ''
      },
      {
        title: 'Form Generation',
        description: 'Generate required tax forms',
        completed: false,
        hasError: false,
        errorMessage: ''
      },
      {
        title: 'Review & Compliance',
        description: 'Review forms and check compliance',
        completed: false,
        hasError: false,
        errorMessage: ''
      },
      {
        title: 'Submission',
        description: 'Submit filing to authorities',
        completed: false,
        hasError: false,
        errorMessage: ''
      }
    ])

    const validationResults = ref([
      {
        type: 'data_completeness',
        title: 'Data Completeness',
        message: 'All required transaction data is present',
        status: 'passed',
        details: null,
        showDetails: false
      },
      {
        type: 'calculation_accuracy',
        title: 'Calculation Accuracy',
        message: 'Tax calculations are accurate',
        status: 'passed',
        details: null,
        showDetails: false
      },
      {
        type: 'compliance_check',
        title: 'Regulatory Compliance',
        message: '2 minor compliance issues found',
        status: 'warning',
        details: [
          'Supporting documentation missing for 3 transactions',
          'Late filing penalty may apply for Q2 submissions'
        ],
        showDetails: false
      }
    ])

    const requiredForms = ref([
      {
        id: 'form_941',
        name: 'Form 941',
        description: 'Quarterly Federal Tax Return',
        type: 'Federal',
        dueDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000),
        selected: true,
        generated: false,
        generating: false
      },
      {
        id: 'form_940',
        name: 'Form 940',
        description: 'Federal Unemployment Tax Return',
        type: 'Federal',
        dueDate: new Date(Date.now() + 45 * 24 * 60 * 60 * 1000),
        selected: true,
        generated: false,
        generating: false
      },
      {
        id: 'state_return',
        name: 'State Tax Return',
        description: 'State Income Tax Return',
        type: 'State',
        dueDate: new Date(Date.now() + 60 * 24 * 60 * 60 * 1000),
        selected: false,
        generated: false,
        generating: false
      }
    ])

    const generatedForms = ref([])
    const complianceIssues = ref([])

    const submissionChecklist = ref([
      {
        id: 'forms_complete',
        title: 'All Forms Complete',
        description: 'All required forms have been generated and reviewed',
        completed: false
      },
      {
        id: 'compliance_verified',
        title: 'Compliance Verified',
        description: 'All compliance issues have been resolved',
        completed: false
      },
      {
        id: 'signature_applied',
        title: 'Digital Signature Applied',
        description: 'Forms have been digitally signed',
        completed: false
      },
      {
        id: 'backup_created',
        title: 'Backup Created',
        description: 'Backup copy of filing has been created',
        completed: false
      }
    ])

    // Computed properties
    const availableYears = computed(() => {
      const currentYear = new Date().getFullYear()
      return Array.from({ length: 5 }, (_, i) => currentYear - i)
    })

    const subPeriodOptions = computed(() => {
      if (filingData.period === 'quarterly') {
        return [
          { value: 'q1', label: 'Q1 (Jan-Mar)' },
          { value: 'q2', label: 'Q2 (Apr-Jun)' },
          { value: 'q3', label: 'Q3 (Jul-Sep)' },
          { value: 'q4', label: 'Q4 (Oct-Dec)' }
        ]
      } else if (filingData.period === 'monthly') {
        return [
          { value: '01', label: 'January' },
          { value: '02', label: 'February' },
          { value: '03', label: 'March' },
          { value: '04', label: 'April' },
          { value: '05', label: 'May' },
          { value: '06', label: 'June' },
          { value: '07', label: 'July' },
          { value: '08', label: 'August' },
          { value: '09', label: 'September' },
          { value: '10', label: 'October' },
          { value: '11', label: 'November' },
          { value: '12', label: 'December' }
        ]
      }
      return []
    })

    const overallProgress = computed(() => {
      const completedSteps = filingSteps.value.filter(step => step.completed).length
      return Math.round((completedSteps / filingSteps.value.length) * 100)
    })

    const hasSelectedForms = computed(() => {
      return requiredForms.value.some(form => form.selected)
    })

    const canSubmit = computed(() => {
      return filingSteps.value.every(step => step.completed && !step.hasError)
    })

    const canProceedToNext = computed(() => {
      return filingSteps.value[currentStep.value].completed
    })

    const totalTaxAmount = computed(() => {
      return Object.values(taxSummary).filter(value => typeof value === 'number').reduce((sum, value) => sum + value, 0)
    })

    const attachmentCount = computed(() => {
      return generatedForms.value.length + (filingOptions.includeSupporting ? 5 : 0)
    })

    const filingDueDate = computed(() => {
      // Calculate due date based on period
      const year = filingData.taxYear
      if (filingData.period === 'annual') {
        return new Date(year + 1, 3, 15) // April 15th
      } else if (filingData.period === 'quarterly') {
        const quarter = parseInt(filingData.subPeriod.replace('q', ''))
        return new Date(year, quarter * 3, 15)
      } else {
        const month = parseInt(filingData.subPeriod)
        return new Date(year, month, 15)
      }
    })

    const canSubmitFinal = computed(() => {
      return submissionChecklist.value.every(item => item.completed)
    })

    // Methods
    const updatePeriod = async () => {
      await fetchTaxSummary()
      validateData()
    }

    const fetchTaxSummary = async () => {
      try {
        const params = {
          tax_year: filingData.taxYear,
          period: filingData.period,
          sub_period: filingData.subPeriod
        }
        
        const response = await axios.get('/accounting/tax-transactions/filing-summary', { params })
        Object.assign(taxSummary, response.data)
      } catch (error) {
        console.error('Error fetching tax summary:', error)
        showNotification('Error loading tax summary', 'error')
      }
    }

    const validateData = () => {
      // Simulate data validation
      const hasData = totalTaxAmount.value > 0
      
      filingSteps.value[0].completed = hasData
      if (!hasData) {
        filingSteps.value[0].hasError = true
        filingSteps.value[0].errorMessage = 'No tax data found for selected period'
      } else {
        filingSteps.value[0].hasError = false
        filingSteps.value[0].errorMessage = ''
      }
    }

    const toggleDetails = (validation) => {
      validation.showDetails = !validation.showDetails
    }

    const updateFormSelection = () => {
      // Update form generation readiness
      filingSteps.value[1].completed = hasSelectedForms.value
    }

    const generateSelectedForms = async () => {
      const selectedForms = requiredForms.value.filter(form => form.selected)
      await generateForms(selectedForms)
    }

    const generateAllForms = async () => {
      requiredForms.value.forEach(form => form.selected = true)
      await generateForms(requiredForms.value)
    }

    const generateForms = async (forms) => {
      generatingForms.value = true
      generationProgress.value = 0
      
      try {
        for (let i = 0; i < forms.length; i++) {
          const form = forms[i]
          form.generating = true
          currentGenerationTask.value = `Generating ${form.name}...`
          
          // Simulate form generation
          await new Promise(resolve => setTimeout(resolve, 2000))
          
          form.generated = true
          form.generating = false
          generatedForms.value.push({
            ...form,
            previewUrl: `/api/forms/${form.id}/preview`,
            status: 'ready'
          })
          
          generationProgress.value = Math.round(((i + 1) / forms.length) * 100)
        }
        
        filingSteps.value[1].completed = true
        currentGenerationTask.value = 'Form generation completed'
        
        showNotification('Forms generated successfully', 'success')
      } catch (error) {
        console.error('Error generating forms:', error)
        showNotification('Error generating forms', 'error')
      } finally {
        generatingForms.value = false
      }
    }

    const previewForm = (form) => {
      window.open(form.previewUrl, '_blank')
    }

    const downloadForm = async (form) => {
      try {
        const response = await axios.get(`/api/forms/${form.id}/download`, {
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `${form.name.replace(/\s+/g, '_')}.pdf`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        showNotification('Form downloaded successfully', 'success')
      } catch (error) {
        console.error('Error downloading form:', error)
        showNotification('Error downloading form', 'error')
      }
    }

    const editForm = (form) => {
      // Open form editor
      showNotification('Form editor feature coming soon', 'info')
    }

    const validateForm = async (form) => {
      try {
        await axios.post(`/api/forms/${form.id}/validate`)
        form.status = 'validated'
        showNotification('Form validated successfully', 'success')
      } catch (error) {
        console.error('Error validating form:', error)
        form.status = 'error'
        showNotification('Form validation failed', 'error')
      }
    }

    const onFormPreviewLoad = (form) => {
      // Handle form preview load
      console.log(`Form ${form.name} preview loaded`)
    }

    const applySignature = async () => {
      try {
        await new Promise(resolve => setTimeout(resolve, 1500))
        
        submissionChecklist.value.find(item => item.id === 'signature_applied').completed = true
        updateSubmissionReadiness()
        
        showNotification('Digital signature applied successfully', 'success')
      } catch (error) {
        console.error('Error applying signature:', error)
        showNotification('Error applying digital signature', 'error')
      }
    }

    const resolveIssue = (issue) => {
      // Implement issue resolution
      showNotification(`Resolving ${issue.title}...`, 'info')
    }

    const dismissIssue = (issue) => {
      const index = complianceIssues.value.findIndex(i => i.id === issue.id)
      if (index > -1) {
        complianceIssues.value.splice(index, 1)
      }
    }

    const updateSubmissionReadiness = () => {
      // Update checklist completion
      const allCompleted = submissionChecklist.value.every(item => item.completed)
      filingSteps.value[3].completed = allCompleted
    }

    const submitFinalFiling = async () => {
      submitting.value = true
      
      try {
        const response = await axios.post('/accounting/tax-transactions/submit-filing', {
          filingData,
          forms: generatedForms.value.map(f => f.id),
          options: submissionOptions
        })
        
        Object.assign(submissionConfirmation, {
          number: response.data.confirmationNumber,
          date: new Date(),
          processingTime: response.data.processingTime
        })
        
        showSuccessModal.value = true
        
        showNotification('Tax filing submitted successfully', 'success')
      } catch (error) {
        console.error('Error submitting filing:', error)
        showNotification('Error submitting tax filing', 'error')
      } finally {
        submitting.value = false
      }
    }

    const nextStep = () => {
      if (currentStep.value < filingSteps.value.length - 1) {
        currentStep.value++
      }
    }

    const previousStep = () => {
      if (currentStep.value > 0) {
        currentStep.value--
      }
    }

    const goToStep = (stepIndex) => {
      if (canGoToStep(stepIndex)) {
        currentStep.value = stepIndex
      }
    }

    const canGoToStep = (stepIndex) => {
      // Can go to current step or any completed step
      return stepIndex <= currentStep.value || filingSteps.value[stepIndex].completed
    }

    const saveDraft = async () => {
      saving.value = true
      
      try {
        await axios.post('/accounting/tax-transactions/save-filing-draft', {
          filingData,
          currentStep: currentStep.value,
          formData: requiredForms.value
        })
        
        showNotification('Draft saved successfully', 'success')
      } catch (error) {
        console.error('Error saving draft:', error)
        showNotification('Error saving draft', 'error')
      } finally {
        saving.value = false
      }
    }

    const previewFiling = () => {
      // Open filing preview
      window.open('/tax-filing-preview', '_blank')
    }

    const submitFiling = () => {
      if (currentStep.value === 3) {
        submitFinalFiling()
      } else {
        showNotification('Complete all steps before submitting', 'warning')
      }
    }

    const closeSuccessModal = () => {
      showSuccessModal.value = false
      router.push('/tax-transactions')
    }

    const downloadReceipt = async () => {
      try {
        const response = await axios.get(`/api/filing-receipt/${submissionConfirmation.number}`, {
          responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `filing-receipt-${submissionConfirmation.number}.pdf`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        showNotification('Receipt downloaded successfully', 'success')
      } catch (error) {
        console.error('Error downloading receipt:', error)
        showNotification('Error downloading receipt', 'error')
      }
    }

    // Utility functions
    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US').format(amount || 0)
    }

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

    const getFilingPeriodLabel = () => {
      let label = filingData.period.charAt(0).toUpperCase() + filingData.period.slice(1)
      if (filingData.subPeriod) {
        const option = subPeriodOptions.value.find(opt => opt.value === filingData.subPeriod)
        label += ` - ${option?.label || filingData.subPeriod}`
      }
      return label
    }

    const getComplianceClass = (score) => {
      if (score >= 95) return 'excellent'
      if (score >= 85) return 'good'
      if (score >= 70) return 'warning'
      return 'critical'
    }

    const showNotification = (message, type = 'info') => {
      console.log(`${type}: ${message}`)
    }

    // Lifecycle
    onMounted(() => {
      fetchTaxSummary()
      validateData()
    })

    // Watchers
    watch(currentStep, (newStep) => {
      // Perform step-specific validations
      if (newStep === 2) {
        filingSteps.value[2].completed = complianceScore.overall >= 85
      }
    })

    return {
      saving,
      submitting,
      generatingForms,
      generationProgress,
      currentGenerationTask,
      currentStep,
      showSuccessModal,
      signatureMethod,
      filingData,
      filingOptions,
      submissionOptions,
      taxSummary,
      complianceScore,
      submissionConfirmation,
      filingSteps,
      validationResults,
      requiredForms,
      generatedForms,
      complianceIssues,
      submissionChecklist,
      availableYears,
      subPeriodOptions,
      overallProgress,
      hasSelectedForms,
      canSubmit,
      canProceedToNext,
      totalTaxAmount,
      attachmentCount,
      filingDueDate,
      canSubmitFinal,
      updatePeriod,
      toggleDetails,
      updateFormSelection,
      generateSelectedForms,
      generateAllForms,
      previewForm,
      downloadForm,
      editForm,
      validateForm,
      onFormPreviewLoad,
      applySignature,
      resolveIssue,
      dismissIssue,
      updateSubmissionReadiness,
      submitFinalFiling,
      nextStep,
      previousStep,
      goToStep,
      canGoToStep,
      saveDraft,
      previewFiling,
      submitFiling,
      closeSuccessModal,
      downloadReceipt,
      formatCurrency,
      formatDate,
      formatDateTime,
      getFilingPeriodLabel,
      getComplianceClass
    }
  }
}
</script>

<style scoped>
.tax-filing-container {
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
  text-align: center;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-description {
  font-size: 1.1rem;
  color: var(--text-secondary);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Progress Section */
.progress-section {
  margin-bottom: 2rem;
}

.progress-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.progress-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.progress-percentage {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-color);
}

.progress-steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.progress-step {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 12px;
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.progress-step.active {
  background: rgba(99, 102, 241, 0.05);
  border-color: var(--primary-color);
}

.progress-step.completed {
  background: rgba(16, 185, 129, 0.05);
  border-color: var(--success-color);
}

.progress-step.error {
  background: rgba(239, 68, 68, 0.05);
  border-color: #ef4444;
}

.step-indicator {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  flex-shrink: 0;
  transition: all 0.3s ease;
}

.progress-step.active .step-indicator {
  background: var(--primary-color);
  color: white;
}

.progress-step.completed .step-indicator {
  background: var(--success-color);
  color: white;
}

.progress-step.error .step-indicator {
  background: #ef4444;
  color: white;
}

.step-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.step-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

.step-error {
  font-size: 0.8rem;
  color: #ef4444;
  margin-top: 0.5rem;
  font-weight: 500;
}

.progress-bar {
  height: 8px;
  background: var(--bg-tertiary);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--primary-color), var(--success-color));
  border-radius: 4px;
  transition: width 0.3s ease;
}

/* Filing Content */
.filing-content {
  margin-bottom: 2rem;
}

.filing-step {
  margin-bottom: 2rem;
}

.step-card {
  background: var(--card-bg);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.step-header {
  padding: 2rem 2rem 1rem 2rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-tertiary);
  text-align: center;
}

.step-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.step-header p {
  color: var(--text-secondary);
  margin: 0;
}

/* Collection Section */
.collection-section {
  padding: 2rem;
}

.period-selection {
  margin-bottom: 3rem;
}

.period-selection h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.period-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.period-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* Data Summary */
.data-summary {
  margin-bottom: 3rem;
}

.data-summary h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  background: var(--bg-tertiary);
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
}

.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.summary-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: white;
  flex-shrink: 0;
}

.summary-icon.vat {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.summary-icon.income {
  background: linear-gradient(135deg, #10b981, #059669);
}

.summary-icon.corporate {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.summary-icon.withholding {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.summary-content h4 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.summary-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0.25rem 0;
}

.transaction-count {
  font-size: 0.8rem;
  color: var(--text-muted);
}

/* Validation Section */
.validation-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.validation-results {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.validation-item {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--bg-tertiary);
}

.validation-item.passed {
  background: rgba(16, 185, 129, 0.05);
  border-color: rgba(16, 185, 129, 0.2);
}

.validation-item.warning {
  background: rgba(245, 158, 11, 0.05);
  border-color: rgba(245, 158, 11, 0.2);
}

.validation-item.failed {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.2);
}

.validation-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.validation-item.passed .validation-icon {
  background: var(--success-color);
  color: white;
}

.validation-item.warning .validation-icon {
  background: #f59e0b;
  color: white;
}

.validation-item.failed .validation-icon {
  background: #ef4444;
  color: white;
}

.validation-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.validation-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

.btn-link {
  background: none;
  border: none;
  color: var(--primary-color);
  cursor: pointer;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.5rem;
}

.details-content {
  margin-top: 0.75rem;
  padding: 0.75rem;
  background: var(--card-bg);
  border-radius: 8px;
}

.details-content ul {
  margin: 0;
  padding-left: 1.5rem;
}

.details-content li {
  margin: 0.25rem 0;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

/* Form Generation Section */
.form-generation-section {
  padding: 2rem;
}

.form-selection h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
}

.forms-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.form-card {
  background: var(--bg-tertiary);
  border: 2px solid var(--border-color);
  border-radius: 12px;
  padding: 1.5rem;
  transition: all 0.3s ease;
  cursor: pointer;
}

.form-card.selected {
  border-color: var(--primary-color);
  background: rgba(99, 102, 241, 0.05);
}

.form-card.generated {
  border-color: var(--success-color);
  background: rgba(16, 185, 129, 0.05);
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.form-checkbox {
  position: relative;
}

.form-checkbox input[type="checkbox"] {
  opacity: 0;
  position: absolute;
}

.form-checkbox label {
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-color);
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.form-checkbox input[type="checkbox"]:checked + label {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

.form-checkbox label::after {
  content: '✓';
  opacity: 0;
  transition: opacity 0.2s ease;
}

.form-checkbox input[type="checkbox"]:checked + label::after {
  opacity: 1;
}

.form-status {
  font-size: 1.25rem;
}

.form-content h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.form-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0 0 1rem 0;
}

.form-details {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.form-type {
  background: var(--primary-color);
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 500;
}

.form-due-date {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.form-actions {
  display: flex;
  gap: 0.5rem;
}

/* Generation Controls */
.generation-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
  margin-bottom: 2rem;
}

.control-buttons {
  display: flex;
  gap: 1rem;
}

.generation-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: var(--text-secondary);
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin: 0;
}

/* Generation Progress */
.generation-progress {
  background: var(--bg-tertiary);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
}

.generation-progress .progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.generation-progress h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.progress-details p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0.75rem 0 0 0;
}

/* Review Section */
.review-section {
  padding: 2rem;
}

/* Compliance Dashboard */
.compliance-dashboard {
  margin-bottom: 3rem;
}

.compliance-dashboard h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.compliance-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.compliance-card {
  background: var(--bg-tertiary);
  border-radius: 12px;
  padding: 2rem;
  border: 1px solid var(--border-color);
  display: flex;
  gap: 2rem;
  align-items: center;
}

.compliance-score {
  text-align: center;
  flex-shrink: 0;
}

.score-value {
  display: block;
  font-size: 3rem;
  font-weight: 700;
  line-height: 1;
}

.score-label {
  font-size: 0.9rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 0.5rem;
  display: block;
}

.compliance-score.excellent .score-value {
  color: var(--success-color);
}

.compliance-score.good .score-value {
  color: #3b82f6;
}

.compliance-score.warning .score-value {
  color: #f59e0b;
}

.compliance-score.critical .score-value {
  color: #ef4444;
}

.compliance-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  flex: 1;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.detail-label {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.detail-value {
  font-weight: 600;
  color: var(--text-primary);
}

/* Compliance Issues */
.compliance-issues {
  margin-bottom: 3rem;
}

.compliance-issues h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.issues-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.issue-item {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--bg-tertiary);
}

.issue-item.critical {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.2);
}

.issue-item.warning {
  background: rgba(245, 158, 11, 0.05);
  border-color: rgba(245, 158, 11, 0.2);
}

.issue-item.info {
  background: rgba(59, 130, 246, 0.05);
  border-color: rgba(59, 130, 246, 0.2);
}

.issue-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.issue-item.critical .issue-icon {
  background: #ef4444;
  color: white;
}

.issue-item.warning .issue-icon {
  background: #f59e0b;
  color: white;
}

.issue-item.info .issue-icon {
  background: #3b82f6;
  color: white;
}

.issue-content {
  flex: 1;
}

.issue-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.issue-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0 0 0.75rem 0;
}

.issue-recommendation {
  font-size: 0.85rem;
  color: var(--text-primary);
  background: var(--card-bg);
  padding: 0.75rem;
  border-radius: 8px;
}

.issue-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: flex-end;
}

/* Form Review */
.form-review {
  margin-bottom: 3rem;
}

.form-review h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.review-forms {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.review-form-card {
  background: var(--bg-tertiary);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.preview-header {
  padding: 1rem 1.5rem;
  background: var(--card-bg);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.preview-header h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.form-status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
}

.form-status-badge.ready {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success-color);
}

.form-status-badge.validated {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.form-status-badge.error {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.preview-content {
  height: 300px;
  background: white;
}

.form-iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.preview-actions {
  padding: 1rem 1.5rem;
  background: var(--card-bg);
  border-top: 1px solid var(--border-color);
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

/* Signature Section */
.signature-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.signature-card {
  background: var(--bg-tertiary);
  border-radius: 12px;
  padding: 2rem;
  border: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.signature-info h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.signature-info p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

.signature-controls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: flex-end;
}

.signature-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: var(--text-secondary);
  cursor: pointer;
}

.radio-label input[type="radio"] {
  margin: 0;
}

/* Submission Section */
.submission-section {
  padding: 2rem;
}

/* Checklist */
.checklist-section {
  margin-bottom: 3rem;
}

.checklist-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checklist {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.checklist-item {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--bg-tertiary);
  transition: all 0.3s ease;
}

.checklist-item.completed {
  background: rgba(16, 185, 129, 0.05);
  border-color: rgba(16, 185, 129, 0.2);
}

.checklist-checkbox {
  position: relative;
  flex-shrink: 0;
}

.checklist-checkbox input[type="checkbox"] {
  opacity: 0;
  position: absolute;
}

.checklist-checkbox label {
  width: 24px;
  height: 24px;
  border: 2px solid var(--border-color);
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  background: var(--card-bg);
}

.checklist-checkbox input[type="checkbox"]:checked + label {
  background: var(--success-color);
  border-color: var(--success-color);
  color: white;
}

.checklist-checkbox label i {
  font-size: 0.8rem;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.checklist-checkbox input[type="checkbox"]:checked + label i {
  opacity: 1;
}

.checklist-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.checklist-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

/* Submission Details */
.submission-details {
  margin-bottom: 3rem;
}

.submission-details h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.detail-card {
  background: var(--bg-tertiary);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
}

.detail-card h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
}

.detail-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.detail-value {
  font-weight: 600;
  color: var(--text-primary);
}

.detail-value.amount {
  font-size: 1.1rem;
  color: var(--success-color);
}

/* Submission Options */
.submission-options {
  margin-bottom: 3rem;
}

.submission-options h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.options-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.option-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border: 2px solid var(--border-color);
  border-radius: 12px;
  background: var(--bg-tertiary);
  cursor: pointer;
  transition: all 0.3s ease;
}

.option-card:hover {
  background: var(--card-bg);
  border-color: var(--primary-color);
}

.option-card input[type="checkbox"] {
  margin: 0;
  transform: scale(1.2);
}

.option-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.option-content p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}

/* Final Submission */
.final-submission {
  text-align: center;
}

.submission-warning {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.submission-warning i {
  font-size: 1.5rem;
  color: #f59e0b;
  flex-shrink: 0;
}

.submission-warning p {
  margin: 0;
  color: var(--text-primary);
  text-align: left;
}

.submission-actions {
  display: flex;
  justify-content: center;
}

/* Step Navigation */
.step-navigation {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.nav-left,
.nav-right {
  flex: 1;
}

.nav-center {
  flex: 2;
  display: flex;
  justify-content: center;
}

.nav-right {
  display: flex;
  justify-content: flex-end;
}

.step-indicators {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.step-indicator {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  color: var(--text-muted);
  border: 2px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.step-indicator.active {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.step-indicator.completed {
  background: var(--success-color);
  color: white;
  border-color: var(--success-color);
}

.step-indicator.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.step-indicator:hover:not(.disabled) {
  transform: scale(1.1);
}

/* Form Elements */
.form-label {
  font-weight: 500;
  color: var(--text-primary);
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
  display: block;
}

.form-input,
.form-select {
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-primary);
  font-size: 0.9rem;
  transition: all 0.2s ease;
  width: 100%;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.btn-success:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-outline {
  background: transparent;
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-outline:hover:not(:disabled) {
  background: var(--bg-tertiary);
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.btn-text {
  background: transparent;
  color: var(--text-muted);
  border: none;
  padding: 0.5rem;
}

.btn-text:hover {
  color: var(--text-primary);
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

.btn-lg {
  padding: 1rem 2rem;
  font-size: 1.1rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
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

.success-modal {
  text-align: center;
}

.modal-header {
  padding: 2rem 2rem 1rem 2rem;
  border-bottom: 1px solid var(--border-color);
}

.success-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--success-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem auto;
  font-size: 2rem;
}

.modal-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.modal-body {
  padding: 1.5rem 2rem;
}

.modal-body p {
  font-size: 1rem;
  color: var(--text-secondary);
  margin: 0 0 1.5rem 0;
}

.submission-info {
  background: var(--bg-tertiary);
  border-radius: 8px;
  padding: 1.5rem;
  margin: 1.5rem 0;
  text-align: left;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.info-item:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.info-value {
  font-weight: 600;
  color: var(--text-primary);
}

.notice {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 1.5rem 0 0 0;
  font-style: italic;
}

.modal-footer {
  padding: 1rem 2rem 1.5rem 2rem;
  display: flex;
  justify-content: center;
  gap: 1rem;
}

/* Utility Classes */
.text-success {
  color: var(--success-color);
}

.text-info {
  color: #3b82f6;
}

.text-muted {
  color: var(--text-muted);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .header-content {
    gap: 1rem;
  }

  .progress-steps {
    grid-template-columns: 1fr;
  }

  .period-grid {
    grid-template-columns: 1fr;
  }

  .summary-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }

  .forms-grid {
    grid-template-columns: 1fr;
  }

  .generation-controls {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .compliance-grid {
    grid-template-columns: 1fr;
  }

  .compliance-card {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }

  .review-forms {
    grid-template-columns: 1fr;
  }

  .signature-card {
    flex-direction: column;
    gap: 1.5rem;
    text-align: center;
  }

  .signature-controls {
    align-items: stretch;
  }

  .details-grid {
    grid-template-columns: 1fr;
  }

  .options-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .tax-filing-container {
    padding: 1rem;
  }

  .page-title {
    font-size: 2rem;
  }

  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .progress-card {
    padding: 1.5rem;
  }

  .step-card {
    margin: 0;
  }

  .step-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
  }

  .collection-section,
  .form-generation-section,
  .review-section,
  .submission-section {
    padding: 1.5rem;
  }

  .summary-card {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }

  .validation-item,
  .issue-item {
    flex-direction: column;
    gap: 1rem;
  }

  .issue-actions {
    flex-direction: row;
    justify-content: flex-start;
  }

  .form-card {
    padding: 1rem;
  }

  .step-navigation {
    flex-direction: column;
    gap: 1.5rem;
    text-align: center;
  }

  .nav-left,
  .nav-right {
    flex: none;
  }

  .nav-center {
    order: -1;
  }

  .control-buttons {
    flex-direction: column;
  }

  .preview-actions {
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .checklist-item {
    padding: 1rem;
  }

  .option-card {
    padding: 1rem;
  }
}

@media (max-width: 480px) {
  .progress-steps {
    gap: 1rem;
  }

  .progress-step {
    padding: 0.75rem;
  }

  .step-indicator {
    width: 32px;
    height: 32px;
    font-size: 0.85rem;
  }

  .summary-icon {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }

  .validation-icon,
  .issue-icon {
    width: 32px;
    height: 32px;
    font-size: 1rem;
  }

  .checklist-checkbox label {
    width: 20px;
    height: 20px;
  }

  .step-indicators {
    gap: 0.25rem;
  }

  .step-indicator {
    width: 36px;
    height: 36px;
  }

  .modal-content {
    margin: 1rem;
    width: calc(100% - 2rem);
  }

  .modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
  }

  .modal-body {
    padding: 1rem 1.5rem;
  }

  .modal-footer {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    flex-direction: column;
  }

  .success-icon {
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
  }

  .submission-warning {
    flex-direction: column;
    text-align: center;
  }

  .submission-warning p {
    text-align: center;
  }
}

@media print {
  .header-actions,
  .step-navigation,
  .form-actions,
  .preview-actions,
  .issue-actions,
  .signature-controls,
  .submission-actions {
    display: none;
  }

  .tax-filing-container {
    padding: 0;
  }

  .step-card {
    box-shadow: none;
    border: 1px solid #ccc;
    page-break-inside: avoid;
  }

  .progress-card {
    box-shadow: none;
    border: 1px solid #ccc;
  }
}
</style>