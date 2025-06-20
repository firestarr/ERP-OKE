<template>
  <div class="reports-configuration">
    <!-- Header Section -->
    <div class="config-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="config-title">
            <i class="fas fa-cogs"></i>
            Reports Configuration
          </h1>
          <p class="config-subtitle">
            Customize report settings, templates, and automation preferences
          </p>
        </div>
        <div class="header-actions">
          <button @click="resetToDefaults" class="reset-btn">
            <i class="fas fa-undo"></i>
            Reset to Defaults
          </button>
          <button @click="saveConfiguration" class="save-btn" :disabled="isSaving">
            <i class="fas" :class="isSaving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
            {{ isSaving ? 'Saving...' : 'Save Configuration' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Configuration Tabs -->
    <div class="config-tabs">
      <div class="tab-navigation">
        <button 
          v-for="tab in tabs" 
          :key="tab.id" 
          @click="activeTab = tab.id"
          class="tab-button"
          :class="{ active: activeTab === tab.id }"
        >
          <i :class="tab.icon"></i>
          {{ tab.label }}
        </button>
      </div>

      <!-- General Settings Tab -->
      <div v-show="activeTab === 'general'" class="tab-content">
        <div class="config-section">
          <h3>
            <i class="fas fa-sliders-h"></i>
            General Report Settings
          </h3>
          
          <div class="settings-grid">
            <div class="setting-group">
              <label for="defaultPeriod">Default Accounting Period</label>
              <select id="defaultPeriod" v-model="config.general.defaultPeriod" class="setting-input">
                <option value="">Select Default Period</option>
                <option v-for="period in accountingPeriods" :key="period.period_id" :value="period.period_id">
                  {{ formatPeriodName(period) }}
                </option>
              </select>
              <p class="setting-description">Default period for new reports</p>
            </div>

            <div class="setting-group">
              <label for="currency">Default Currency</label>
              <select id="currency" v-model="config.general.currency" class="setting-input">
                <option value="IDR">Indonesian Rupiah (IDR)</option>
                <option value="USD">US Dollar (USD)</option>
                <option value="EUR">Euro (EUR)</option>
                <option value="SGD">Singapore Dollar (SGD)</option>
              </select>
              <p class="setting-description">Currency used in all financial reports</p>
            </div>

            <div class="setting-group">
              <label for="numberFormat">Number Format</label>
              <select id="numberFormat" v-model="config.general.numberFormat" class="setting-input">
                <option value="id-ID">Indonesian (1.234.567,89)</option>
                <option value="en-US">US English (1,234,567.89)</option>
                <option value="en-GB">UK English (1,234,567.89)</option>
              </select>
              <p class="setting-description">Number formatting style for reports</p>
            </div>

            <div class="setting-group">
              <label for="fiscalYearStart">Fiscal Year Start</label>
              <select id="fiscalYearStart" v-model="config.general.fiscalYearStart" class="setting-input">
                <option value="1">January</option>
                <option value="4">April</option>
                <option value="7">July</option>
                <option value="10">October</option>
              </select>
              <p class="setting-description">Starting month of your fiscal year</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.general.showZeroBalances">
                <span class="checkbox-custom"></span>
                Show Zero Balance Accounts
              </label>
              <p class="setting-description">Include accounts with zero balances in reports</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.general.useAccountCodes">
                <span class="checkbox-custom"></span>
                Display Account Codes
              </label>
              <p class="setting-description">Show account codes alongside account names</p>
            </div>
          </div>
        </div>

        <div class="config-section">
          <h3>
            <i class="fas fa-palette"></i>
            Report Appearance
          </h3>
          
          <div class="settings-grid">
            <div class="setting-group">
              <label for="reportTheme">Report Theme</label>
              <select id="reportTheme" v-model="config.appearance.theme" class="setting-input">
                <option value="modern">Modern Blue</option>
                <option value="classic">Classic Black & White</option>
                <option value="corporate">Corporate Gray</option>
                <option value="colorful">Colorful</option>
              </select>
              <p class="setting-description">Visual theme for exported reports</p>
            </div>

            <div class="setting-group">
              <label for="fontSize">Font Size</label>
              <select id="fontSize" v-model="config.appearance.fontSize" class="setting-input">
                <option value="small">Small (10pt)</option>
                <option value="medium">Medium (12pt)</option>
                <option value="large">Large (14pt)</option>
              </select>
              <p class="setting-description">Base font size for reports</p>
            </div>

            <div class="setting-group">
              <label for="logoUpload">Company Logo</label>
              <div class="logo-upload">
                <input type="file" id="logoUpload" @change="handleLogoUpload" accept="image/*" class="file-input">
                <div class="logo-preview" v-if="config.appearance.logoUrl">
                  <img :src="config.appearance.logoUrl" alt="Company Logo" class="logo-image">
                  <button @click="removeLogo" class="remove-logo-btn">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div v-else class="logo-placeholder">
                  <i class="fas fa-upload"></i>
                  <span>Upload company logo</span>
                </div>
              </div>
              <p class="setting-description">Logo displayed on report headers</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Email & Notifications Tab -->
      <div v-show="activeTab === 'notifications'" class="tab-content">
        <div class="config-section">
          <h3>
            <i class="fas fa-envelope"></i>
            Email Configuration
          </h3>
          
          <div class="settings-grid">
            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.email.enabled">
                <span class="checkbox-custom"></span>
                Enable Email Reports
              </label>
              <p class="setting-description">Allow automatic email delivery of reports</p>
            </div>

            <div v-if="config.email.enabled" class="email-settings">
              <div class="setting-group">
                <label for="smtpHost">SMTP Host</label>
                <input type="text" id="smtpHost" v-model="config.email.smtpHost" class="setting-input" placeholder="smtp.gmail.com">
                <p class="setting-description">SMTP server hostname</p>
              </div>

              <div class="setting-group">
                <label for="smtpPort">SMTP Port</label>
                <input type="number" id="smtpPort" v-model="config.email.smtpPort" class="setting-input" placeholder="587">
                <p class="setting-description">SMTP server port (usually 587 or 465)</p>
              </div>

              <div class="setting-group">
                <label for="emailUsername">Email Username</label>
                <input type="email" id="emailUsername" v-model="config.email.username" class="setting-input" placeholder="your-email@company.com">
                <p class="setting-description">Email account for sending reports</p>
              </div>

              <div class="setting-group">
                <label for="emailPassword">Email Password</label>
                <input type="password" id="emailPassword" v-model="config.email.password" class="setting-input" placeholder="••••••••">
                <p class="setting-description">Email account password (encrypted)</p>
              </div>

              <div class="setting-group">
                <label for="defaultRecipients">Default Recipients</label>
                <textarea id="defaultRecipients" v-model="config.email.defaultRecipients" class="setting-textarea" placeholder="email1@company.com, email2@company.com"></textarea>
                <p class="setting-description">Comma-separated list of default email recipients</p>
              </div>

              <div class="setting-group">
                <label for="emailSubject">Default Email Subject</label>
                <input type="text" id="emailSubject" v-model="config.email.subjectTemplate" class="setting-input" placeholder="Financial Report - {report_type} - {period}">
                <p class="setting-description">Email subject template (use {report_type}, {period}, {date})</p>
              </div>

              <div class="test-email-section">
                <button @click="testEmailSettings" class="test-btn" :disabled="isTesting">
                  <i class="fas" :class="isTesting ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                  {{ isTesting ? 'Testing...' : 'Test Email Settings' }}
                </button>
                <div v-if="emailTestResult" class="test-result" :class="emailTestResult.success ? 'success' : 'error'">
                  <i class="fas" :class="emailTestResult.success ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
                  {{ emailTestResult.message }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="config-section">
          <h3>
            <i class="fas fa-bell"></i>
            Notification Settings
          </h3>
          
          <div class="notification-list">
            <div class="notification-item">
              <div class="notification-info">
                <h4>Report Generation Complete</h4>
                <p>Notify when reports are successfully generated</p>
              </div>
              <div class="notification-controls">
                <label class="toggle-switch">
                  <input type="checkbox" v-model="config.notifications.reportComplete">
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>

            <div class="notification-item">
              <div class="notification-info">
                <h4>Report Generation Errors</h4>
                <p>Notify when report generation fails</p>
              </div>
              <div class="notification-controls">
                <label class="toggle-switch">
                  <input type="checkbox" v-model="config.notifications.reportError">
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>

            <div class="notification-item">
              <div class="notification-info">
                <h4>Scheduled Report Reminders</h4>
                <p>Remind about upcoming scheduled reports</p>
              </div>
              <div class="notification-controls">
                <label class="toggle-switch">
                  <input type="checkbox" v-model="config.notifications.scheduledReports">
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>

            <div class="notification-item">
              <div class="notification-info">
                <h4>Period End Notifications</h4>
                <p>Notify when accounting periods are closing</p>
              </div>
              <div class="notification-controls">
                <label class="toggle-switch">
                  <input type="checkbox" v-model="config.notifications.periodEnd">
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Automation & Scheduling Tab -->
      <div v-show="activeTab === 'automation'" class="tab-content">
        <div class="config-section">
          <h3>
            <i class="fas fa-clock"></i>
            Scheduled Reports
          </h3>
          
          <div class="scheduled-reports">
            <button @click="showAddScheduleModal = true" class="add-schedule-btn">
              <i class="fas fa-plus"></i>
              Add Scheduled Report
            </button>

            <div class="schedule-list">
              <div v-for="(schedule, index) in config.schedules" :key="index" class="schedule-item">
                <div class="schedule-info">
                  <h4>{{ schedule.reportType }} - {{ schedule.name }}</h4>
                  <p>{{ getScheduleDescription(schedule) }}</p>
                  <div class="schedule-meta">
                    <span class="schedule-frequency">{{ schedule.frequency }}</span>
                    <span class="schedule-recipients">{{ schedule.recipients.length }} recipients</span>
                    <span class="schedule-status" :class="schedule.active ? 'active' : 'inactive'">
                      {{ schedule.active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>
                <div class="schedule-actions">
                  <button @click="editSchedule(index)" class="edit-btn">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button @click="toggleSchedule(index)" class="toggle-btn" :class="schedule.active ? 'active' : 'inactive'">
                    <i class="fas" :class="schedule.active ? 'fa-pause' : 'fa-play'"></i>
                  </button>
                  <button @click="deleteSchedule(index)" class="delete-btn">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>

              <div v-if="config.schedules.length === 0" class="empty-schedules">
                <i class="fas fa-calendar-times"></i>
                <h4>No Scheduled Reports</h4>
                <p>Create automated report schedules to receive regular financial updates</p>
              </div>
            </div>
          </div>
        </div>

        <div class="config-section">
          <h3>
            <i class="fas fa-robot"></i>
            Automation Settings
          </h3>
          
          <div class="automation-settings">
            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.automation.autoGenerate">
                <span class="checkbox-custom"></span>
                Auto-Generate Monthly Reports
              </label>
              <p class="setting-description">Automatically generate key reports at month-end</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.automation.autoEmail">
                <span class="checkbox-custom"></span>
                Auto-Email to Stakeholders
              </label>
              <p class="setting-description">Automatically email reports to configured recipients</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.automation.autoArchive">
                <span class="checkbox-custom"></span>
                Auto-Archive Old Reports
              </label>
              <p class="setting-description">Automatically archive reports older than specified period</p>
            </div>

            <div v-if="config.automation.autoArchive" class="setting-group">
              <label for="archivePeriod">Archive After</label>
              <select id="archivePeriod" v-model="config.automation.archivePeriod" class="setting-input">
                <option value="6">6 months</option>
                <option value="12">1 year</option>
                <option value="24">2 years</option>
                <option value="36">3 years</option>
              </select>
              <p class="setting-description">Archive reports after this period</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Export & Formats Tab -->
      <div v-show="activeTab === 'export'" class="tab-content">
        <div class="config-section">
          <h3>
            <i class="fas fa-file-export"></i>
            Export Settings
          </h3>
          
          <div class="export-settings">
            <div class="setting-group">
              <label for="defaultFormat">Default Export Format</label>
              <select id="defaultFormat" v-model="config.export.defaultFormat" class="setting-input">
                <option value="pdf">PDF</option>
                <option value="excel">Excel (XLSX)</option>
                <option value="csv">CSV</option>
                <option value="html">HTML</option>
              </select>
              <p class="setting-description">Default format for report exports</p>
            </div>

            <div class="setting-group">
              <label for="pdfPageSize">PDF Page Size</label>
              <select id="pdfPageSize" v-model="config.export.pdfPageSize" class="setting-input">
                <option value="A4">A4</option>
                <option value="Letter">Letter</option>
                <option value="Legal">Legal</option>
                <option value="A3">A3</option>
              </select>
              <p class="setting-description">Page size for PDF exports</p>
            </div>

            <div class="setting-group">
              <label for="pdfOrientation">PDF Orientation</label>
              <select id="pdfOrientation" v-model="config.export.pdfOrientation" class="setting-input">
                <option value="portrait">Portrait</option>
                <option value="landscape">Landscape</option>
              </select>
              <p class="setting-description">Page orientation for PDF exports</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.export.includeCharts">
                <span class="checkbox-custom"></span>
                Include Charts in Exports
              </label>
              <p class="setting-description">Include visual charts and graphs in exported reports</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.export.includeMetadata">
                <span class="checkbox-custom"></span>
                Include Metadata
              </label>
              <p class="setting-description">Include generation date, user, and system info</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.export.passwordProtect">
                <span class="checkbox-custom"></span>
                Password Protect PDF Exports
              </label>
              <p class="setting-description">Require password to open exported PDF files</p>
            </div>

            <div v-if="config.export.passwordProtect" class="setting-group">
              <label for="exportPassword">Export Password</label>
              <input type="password" id="exportPassword" v-model="config.export.defaultPassword" class="setting-input" placeholder="Enter default password">
              <p class="setting-description">Default password for protected exports</p>
            </div>
          </div>
        </div>

        <div class="config-section">
          <h3>
            <i class="fas fa-file-alt"></i>
            Report Templates
          </h3>
          
          <div class="template-settings">
            <div class="template-list">
              <div v-for="template in reportTemplates" :key="template.id" class="template-item">
                <div class="template-info">
                  <h4>{{ template.name }}</h4>
                  <p>{{ template.description }}</p>
                  <div class="template-meta">
                    <span class="template-type">{{ template.type }}</span>
                    <span class="template-modified">Modified: {{ formatDate(template.lastModified) }}</span>
                  </div>
                </div>
                <div class="template-actions">
                  <button @click="editTemplate(template)" class="edit-template-btn">
                    <i class="fas fa-edit"></i>
                    Edit
                  </button>
                  <button @click="duplicateTemplate(template)" class="duplicate-template-btn">
                    <i class="fas fa-copy"></i>
                    Duplicate
                  </button>
                  <button @click="deleteTemplate(template)" class="delete-template-btn" :disabled="template.isDefault">
                    <i class="fas fa-trash"></i>
                    Delete
                  </button>
                </div>
              </div>
            </div>

            <button @click="createNewTemplate" class="create-template-btn">
              <i class="fas fa-plus"></i>
              Create New Template
            </button>
          </div>
        </div>
      </div>

      <!-- User Preferences Tab -->
      <div v-show="activeTab === 'preferences'" class="tab-content">
        <div class="config-section">
          <h3>
            <i class="fas fa-user-cog"></i>
            User Preferences
          </h3>
          
          <div class="preference-settings">
            <div class="setting-group">
              <label for="dashboardLayout">Dashboard Layout</label>
              <select id="dashboardLayout" v-model="config.preferences.dashboardLayout" class="setting-input">
                <option value="compact">Compact</option>
                <option value="detailed">Detailed</option>
                <option value="custom">Custom</option>
              </select>
              <p class="setting-description">Layout style for financial dashboard</p>
            </div>

            <div class="setting-group">
              <label for="defaultView">Default Report View</label>
              <select id="defaultView" v-model="config.preferences.defaultView" class="setting-input">
                <option value="summary">Summary View</option>
                <option value="detailed">Detailed View</option>
                <option value="comparative">Comparative View</option>
              </select>
              <p class="setting-description">Default view when opening reports</p>
            </div>

            <div class="setting-group">
              <label for="refreshInterval">Auto Refresh Interval</label>
              <select id="refreshInterval" v-model="config.preferences.refreshInterval" class="setting-input">
                <option value="0">Never</option>
                <option value="300">5 minutes</option>
                <option value="600">10 minutes</option>
                <option value="1800">30 minutes</option>
                <option value="3600">1 hour</option>
              </select>
              <p class="setting-description">Automatically refresh report data</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.preferences.rememberFilters">
                <span class="checkbox-custom"></span>
                Remember Report Filters
              </label>
              <p class="setting-description">Save and restore filter settings between sessions</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.preferences.showTooltips">
                <span class="checkbox-custom"></span>
                Show Help Tooltips
              </label>
              <p class="setting-description">Display helpful tooltips throughout the interface</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.preferences.confirmActions">
                <span class="checkbox-custom"></span>
                Confirm Destructive Actions
              </label>
              <p class="setting-description">Ask for confirmation before deleting or major changes</p>
            </div>
          </div>
        </div>

        <div class="config-section">
          <h3>
            <i class="fas fa-shield-alt"></i>
            Security Settings
          </h3>
          
          <div class="security-settings">
            <div class="setting-group">
              <label for="sessionTimeout">Session Timeout</label>
              <select id="sessionTimeout" v-model="config.security.sessionTimeout" class="setting-input">
                <option value="1800">30 minutes</option>
                <option value="3600">1 hour</option>
                <option value="7200">2 hours</option>
                <option value="14400">4 hours</option>
                <option value="28800">8 hours</option>
              </select>
              <p class="setting-description">Automatically log out after inactivity</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.security.requirePasswordForExport">
                <span class="checkbox-custom"></span>
                Require Password for Exports
              </label>
              <p class="setting-description">Require user password confirmation before exporting reports</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.security.logReportAccess">
                <span class="checkbox-custom"></span>
                Log Report Access
              </label>
              <p class="setting-description">Keep audit trail of report views and exports</p>
            </div>

            <div class="setting-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="config.security.restrictEmailDomains">
                <span class="checkbox-custom"></span>
                Restrict Email Domains
              </label>
              <p class="setting-description">Only allow emails to approved domains</p>
            </div>

            <div v-if="config.security.restrictEmailDomains" class="setting-group">
              <label for="allowedDomains">Allowed Email Domains</label>
              <textarea id="allowedDomains" v-model="config.security.allowedDomains" class="setting-textarea" placeholder="company.com, subsidiary.com"></textarea>
              <p class="setting-description">Comma-separated list of allowed email domains</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Schedule Modal -->
    <div v-if="showAddScheduleModal" class="modal-overlay" @click="showAddScheduleModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Add Scheduled Report</h3>
          <button @click="showAddScheduleModal = false" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="scheduleName">Schedule Name</label>
            <input type="text" id="scheduleName" v-model="newSchedule.name" class="form-input" placeholder="Monthly Financial Report">
          </div>
          <div class="form-group">
            <label for="scheduleReportType">Report Type</label>
            <select id="scheduleReportType" v-model="newSchedule.reportType" class="form-input">
              <option value="trial-balance">Trial Balance</option>
              <option value="income-statement">Income Statement</option>
              <option value="balance-sheet">Balance Sheet</option>
              <option value="cash-flow">Cash Flow Statement</option>
            </select>
          </div>
          <div class="form-group">
            <label for="scheduleFrequency">Frequency</label>
            <select id="scheduleFrequency" v-model="newSchedule.frequency" class="form-input">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="yearly">Yearly</option>
            </select>
          </div>
          <div class="form-group">
            <label for="scheduleRecipients">Recipients</label>
            <textarea id="scheduleRecipients" v-model="newSchedule.recipients" class="form-textarea" placeholder="email1@company.com, email2@company.com"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showAddScheduleModal = false" class="cancel-btn">Cancel</button>
          <button @click="addSchedule" class="save-btn">Add Schedule</button>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="showMessage" class="message-toast" :class="messageType">
      <i class="fas" :class="messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
      {{ message }}
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ReportsConfiguration',
  data() {
    return {
      activeTab: 'general',
      isSaving: false,
      isTesting: false,
      showAddScheduleModal: false,
      showMessage: false,
      message: '',
      messageType: 'success',
      emailTestResult: null,
      accountingPeriods: [],
      tabs: [
        { id: 'general', label: 'General', icon: 'fas fa-sliders-h' },
        { id: 'notifications', label: 'Email & Notifications', icon: 'fas fa-envelope' },
        { id: 'automation', label: 'Automation', icon: 'fas fa-robot' },
        { id: 'export', label: 'Export & Templates', icon: 'fas fa-file-export' },
        { id: 'preferences', label: 'Preferences', icon: 'fas fa-user-cog' }
      ],
      config: {
        general: {
          defaultPeriod: '',
          currency: 'IDR',
          numberFormat: 'id-ID',
          fiscalYearStart: '1',
          showZeroBalances: false,
          useAccountCodes: true
        },
        appearance: {
          theme: 'modern',
          fontSize: 'medium',
          logoUrl: ''
        },
        email: {
          enabled: false,
          smtpHost: '',
          smtpPort: 587,
          username: '',
          password: '',
          defaultRecipients: '',
          subjectTemplate: 'Financial Report - {report_type} - {period}'
        },
        notifications: {
          reportComplete: true,
          reportError: true,
          scheduledReports: true,
          periodEnd: true
        },
        schedules: [],
        automation: {
          autoGenerate: false,
          autoEmail: false,
          autoArchive: false,
          archivePeriod: '12'
        },
        export: {
          defaultFormat: 'pdf',
          pdfPageSize: 'A4',
          pdfOrientation: 'portrait',
          includeCharts: true,
          includeMetadata: true,
          passwordProtect: false,
          defaultPassword: ''
        },
        preferences: {
          dashboardLayout: 'detailed',
          defaultView: 'summary',
          refreshInterval: '0',
          rememberFilters: true,
          showTooltips: true,
          confirmActions: true
        },
        security: {
          sessionTimeout: '3600',
          requirePasswordForExport: false,
          logReportAccess: true,
          restrictEmailDomains: false,
          allowedDomains: ''
        }
      },
      newSchedule: {
        name: '',
        reportType: 'trial-balance',
        frequency: 'monthly',
        recipients: '',
        active: true
      },
      reportTemplates: [
        {
          id: 1,
          name: 'Standard Financial Report',
          description: 'Default template for all financial reports',
          type: 'Standard',
          isDefault: true,
          lastModified: new Date()
        },
        {
          id: 2,
          name: 'Executive Summary',
          description: 'Condensed format for executive reporting',
          type: 'Summary',
          isDefault: false,
          lastModified: new Date()
        },
        {
          id: 3,
          name: 'Detailed Analysis',
          description: 'Comprehensive template with detailed breakdowns',
          type: 'Detailed',
          isDefault: false,
          lastModified: new Date()
        }
      ]
    }
  },
  async mounted() {
    await this.loadConfiguration()
    await this.loadAccountingPeriods()
  },
  methods: {
    async loadAccountingPeriods() {
      try {
        const response = await axios.get('/accounting-periods')
        this.accountingPeriods = response.data.data || []
      } catch (error) {
        console.error('Error loading accounting periods:', error)
      }
    },

    async loadConfiguration() {
      try {
        // Load configuration from backend
        const response = await axios.get('/reports/configuration')
        if (response.data) {
          this.config = { ...this.config, ...response.data }
        }
      } catch (error) {
        console.error('Error loading configuration:', error)
        // Use default configuration if loading fails
      }
    },

    async saveConfiguration() {
      this.isSaving = true
      try {
        await axios.post('/reports/configuration', this.config)
        this.showSuccessMessage('Configuration saved successfully')
      } catch (error) {
        console.error('Error saving configuration:', error)
        this.showErrorMessage('Failed to save configuration')
      } finally {
        this.isSaving = false
      }
    },

    async resetToDefaults() {
      if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
        try {
          await axios.delete('/reports/configuration')
          await this.loadConfiguration()
          this.showSuccessMessage('Settings reset to defaults')
        } catch (error) {
          console.error('Error resetting configuration:', error)
          this.showErrorMessage('Failed to reset settings')
        }
      }
    },

    async testEmailSettings() {
      this.isTesting = true
      this.emailTestResult = null
      
      try {
        const response = await axios.post('/reports/test-email', {
          smtpHost: this.config.email.smtpHost,
          smtpPort: this.config.email.smtpPort,
          username: this.config.email.username,
          password: this.config.email.password
        })
        
        this.emailTestResult = {
          success: true,
          message: 'Email settings are working correctly!'
        }
      } catch (error) {
        this.emailTestResult = {
          success: false,
          message: error.response?.data?.message || 'Email test failed'
        }
      } finally {
        this.isTesting = false
      }
    },

    handleLogoUpload(event) {
      const file = event.target.files[0]
      if (file) {
        const reader = new FileReader()
        reader.onload = (e) => {
          this.config.appearance.logoUrl = e.target.result
        }
        reader.readAsDataURL(file)
      }
    },

    removeLogo() {
      this.config.appearance.logoUrl = ''
    },

    addSchedule() {
      const schedule = {
        ...this.newSchedule,
        recipients: this.newSchedule.recipients.split(',').map(email => email.trim()),
        createdAt: new Date(),
        lastRun: null,
        nextRun: this.calculateNextRun(this.newSchedule.frequency)
      }
      
      this.config.schedules.push(schedule)
      this.showAddScheduleModal = false
      this.resetNewSchedule()
      this.showSuccessMessage('Schedule added successfully')
    },

    editSchedule(index) {
      // Implementation for editing schedules
      console.log('Edit schedule:', index)
    },

    toggleSchedule(index) {
      this.config.schedules[index].active = !this.config.schedules[index].active
      const status = this.config.schedules[index].active ? 'activated' : 'deactivated'
      this.showSuccessMessage(`Schedule ${status}`)
    },

    deleteSchedule(index) {
      if (confirm('Are you sure you want to delete this schedule?')) {
        this.config.schedules.splice(index, 1)
        this.showSuccessMessage('Schedule deleted')
      }
    },

    resetNewSchedule() {
      this.newSchedule = {
        name: '',
        reportType: 'trial-balance',
        frequency: 'monthly',
        recipients: '',
        active: true
      }
    },

    calculateNextRun(frequency) {
      const now = new Date()
      const nextRun = new Date(now)
      
      switch (frequency) {
        case 'daily':
          nextRun.setDate(now.getDate() + 1)
          break
        case 'weekly':
          nextRun.setDate(now.getDate() + 7)
          break
        case 'monthly':
          nextRun.setMonth(now.getMonth() + 1)
          break
        case 'quarterly':
          nextRun.setMonth(now.getMonth() + 3)
          break
        case 'yearly':
          nextRun.setFullYear(now.getFullYear() + 1)
          break
      }
      
      return nextRun
    },

    getScheduleDescription(schedule) {
      const frequency = schedule.frequency.charAt(0).toUpperCase() + schedule.frequency.slice(1)
      return `${frequency} ${schedule.reportType.replace('-', ' ')} report`
    },

    createNewTemplate() {
      console.log('Create new template')
    },

    editTemplate(template) {
      console.log('Edit template:', template)
    },

    duplicateTemplate(template) {
      const newTemplate = {
        ...template,
        id: Date.now(),
        name: `${template.name} (Copy)`,
        isDefault: false,
        lastModified: new Date()
      }
      this.reportTemplates.push(newTemplate)
      this.showSuccessMessage('Template duplicated')
    },

    deleteTemplate(template) {
      if (template.isDefault) return
      
      if (confirm('Are you sure you want to delete this template?')) {
        const index = this.reportTemplates.findIndex(t => t.id === template.id)
        if (index > -1) {
          this.reportTemplates.splice(index, 1)
          this.showSuccessMessage('Template deleted')
        }
      }
    },

    showSuccessMessage(msg) {
      this.message = msg
      this.messageType = 'success'
      this.showMessage = true
      setTimeout(() => { this.showMessage = false }, 3000)
    },

    showErrorMessage(msg) {
      this.message = msg
      this.messageType = 'error'
      this.showMessage = true
      setTimeout(() => { this.showMessage = false }, 5000)
    },

    formatPeriodName(period) {
      return `${period.period_name} (${period.start_date} - ${period.end_date})`
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('id-ID')
    }
  }
}
</script>

<style scoped>
.reports-configuration {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  padding: 2rem;
}

.config-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: white;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.title-section {
  flex: 1;
}

.config-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.config-title i {
  margin-right: 1rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.config-subtitle {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.reset-btn {
  padding: 0.75rem 1.25rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: white;
  color: #64748b;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.reset-btn:hover {
  border-color: #ef4444;
  color: #ef4444;
}

.save-btn {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.save-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.config-tabs {
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.tab-navigation {
  display: flex;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  overflow-x: auto;
}

.tab-button {
  padding: 1rem 1.5rem;
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  border-bottom: 3px solid transparent;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 200px;
  justify-content: center;
}

.tab-button:hover {
  background: rgba(99, 102, 241, 0.05);
  color: #6366f1;
}

.tab-button.active {
  color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
  border-bottom-color: #6366f1;
}

.tab-content {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.config-section {
  margin-bottom: 3rem;
}

.config-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.setting-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.setting-group label {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.9rem;
}

.setting-input, .setting-textarea {
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: all 0.3s ease;
  background: white;
}

.setting-input:focus, .setting-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.setting-textarea {
  min-height: 80px;
  resize: vertical;
}

.setting-description {
  color: #64748b;
  font-size: 0.8rem;
  margin: 0;
  line-height: 1.4;
}

.checkbox-group {
  flex-direction: row;
  align-items: center;
  gap: 1rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  margin: 0;
}

.checkbox-label input[type="checkbox"] {
  display: none;
}

.checkbox-custom {
  width: 20px;
  height: 20px;
  border: 2px solid #e2e8f0;
  border-radius: 4px;
  background: white;
  position: relative;
  transition: all 0.3s ease;
}

.checkbox-label input:checked + .checkbox-custom {
  background: #6366f1;
  border-color: #6366f1;
}

.checkbox-label input:checked + .checkbox-custom::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 12px;
  font-weight: bold;
}

.logo-upload {
  position: relative;
}

.file-input {
  position: absolute;
  opacity: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.logo-preview {
  position: relative;
  width: 200px;
  height: 100px;
  border: 2px dashed #e2e8f0;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}

.logo-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.remove-logo-btn {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ef4444;
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.logo-placeholder {
  width: 200px;
  height: 100px;
  border: 2px dashed #e2e8f0;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.3s ease;
}

.logo-placeholder:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.email-settings {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-top: 1rem;
}

.test-email-section {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: flex-start;
  margin-top: 1rem;
}

.test-btn {
  padding: 0.75rem 1.5rem;
  background: #10b981;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.test-btn:hover:not(:disabled) {
  background: #059669;
}

.test-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.test-result {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
}

.test-result.success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.test-result.error {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.notification-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.notification-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.notification-info h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.25rem 0;
}

.notification-info p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 28px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #e2e8f0;
  transition: 0.3s;
  border-radius: 28px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch input:checked + .toggle-slider {
  background-color: #6366f1;
}

.toggle-switch input:checked + .toggle-slider:before {
  transform: translateX(22px);
}

.scheduled-reports {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.add-schedule-btn {
  padding: 1rem 1.5rem;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  align-self: flex-start;
}

.add-schedule-btn:hover {
  background: #5b21b6;
  transform: translateY(-2px);
}

.schedule-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.schedule-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.schedule-info h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.schedule-info p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0 0 0.75rem 0;
}

.schedule-meta {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.schedule-frequency, .schedule-recipients, .schedule-status {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.schedule-frequency {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.schedule-recipients {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.schedule-status.active {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.schedule-status.inactive {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.schedule-actions {
  display: flex;
  gap: 0.5rem;
}

.edit-btn, .toggle-btn, .delete-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.edit-btn {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.edit-btn:hover {
  background: rgba(99, 102, 241, 0.2);
}

.toggle-btn.active {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.toggle-btn.inactive {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.toggle-btn:hover {
  opacity: 0.8;
}

.delete-btn {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.delete-btn:hover {
  background: rgba(239, 68, 68, 0.2);
}

.empty-schedules {
  text-align: center;
  padding: 3rem;
  color: #94a3b8;
}

.empty-schedules i {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.empty-schedules h4 {
  font-size: 1.25rem;
  color: #64748b;
  margin: 0 0 0.5rem 0;
}

.template-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.template-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.template-info h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.template-info p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0 0 0.75rem 0;
}

.template-meta {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.template-type, .template-modified {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.template-actions {
  display: flex;
  gap: 0.5rem;
}

.edit-template-btn, .duplicate-template-btn, .delete-template-btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  font-size: 0.85rem;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.edit-template-btn {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.duplicate-template-btn {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.delete-template-btn {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.delete-template-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.create-template-btn {
  padding: 1rem 1.5rem;
  background: #6366f1;
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  align-self: flex-start;
}

.create-template-btn:hover {
  background: #5b21b6;
}

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
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.modal-close {
  width: 32px;
  height: 32px;
  border: none;
  background: #f1f5f9;
  color: #64748b;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.modal-body {
  padding: 1.5rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  font-weight: 500;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-textarea {
  min-height: 80px;
  resize: vertical;
}

.modal-footer {
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.cancel-btn {
  padding: 0.75rem 1.5rem;
  border: 2px solid #e2e8f0;
  background: white;
  color: #64748b;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
}

.cancel-btn:hover {
  border-color: #64748b;
  color: #1e293b;
}

.message-toast {
  position: fixed;
  top: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: 12px;
  color: white;
  font-weight: 500;
  z-index: 1001;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  animation: slideIn 0.3s ease;
}

.message-toast.success {
  background: #10b981;
}

.message-toast.error {
  background: #ef4444;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* Responsive Design */
@media (max-width: 1200px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
  
  .email-settings {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .reports-configuration {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1.5rem;
    align-items: stretch;
  }
  
  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }
  
  .config-title {
    font-size: 2rem;
  }
  
  .tab-navigation {
    flex-direction: column;
  }
  
  .tab-button {
    min-width: auto;
    justify-content: flex-start;
  }
  
  .tab-content {
    padding: 1.5rem;
  }
  
  .schedule-item, .template-item, .notification-item {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .schedule-actions, .template-actions {
    justify-content: center;
  }
  
  .checkbox-group {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .modal-content {
    width: 95%;
  }
  
  .modal-footer {
    flex-direction: column;
  }
}

/* Print Styles */
@media print {
  .header-actions,
  .tab-navigation,
  .schedule-actions,
  .template-actions {
    display: none !important;
  }
  
  .reports-configuration {
    background: white !important;
    padding: 0 !important;
  }
  
  .tab-content {
    padding: 1rem !important;
  }
}
</style>