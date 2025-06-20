<template>
  <div class="accounting-navigation">
    <div class="nav-section">
      <h3 class="nav-section-title">
        <i class="fas fa-credit-card"></i>
        Receivable Payments
      </h3>
      <ul class="nav-menu">
        <li class="nav-item">
          <router-link 
            to="/accounting/receivable-payments" 
            class="nav-link"
            :class="{ active: $route.path === '/accounting/receivable-payments' }"
          >
            <i class="fas fa-list"></i>
            <span>Payments List</span>
          </router-link>
        </li>
        <li class="nav-item">
          <router-link 
            to="/accounting/receivable-payments/create" 
            class="nav-link"
            :class="{ active: $route.path === '/accounting/receivable-payments/create' }"
          >
            <i class="fas fa-plus-circle"></i>
            <span>Record Payment</span>
          </router-link>
        </li>
        <li class="nav-item">
          <router-link 
            to="/accounting/receivable-payments/application" 
            class="nav-link"
            :class="{ active: $route.path === '/accounting/receivable-payments/application' }"
          >
            <i class="fas fa-link"></i>
            <span>Payment Application</span>
          </router-link>
        </li>
        <li class="nav-item">
          <router-link 
            to="/accounting/receivable-payments/history" 
            class="nav-link"
            :class="{ active: $route.path === '/accounting/receivable-payments/history' }"
          >
            <i class="fas fa-history"></i>
            <span>Payment History</span>
          </router-link>
        </li>
      </ul>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h4 class="quick-actions-title">Quick Actions</h4>
      <div class="quick-action-buttons">
        <router-link to="/accounting/receivable-payments/create" class="quick-action-btn primary">
          <i class="fas fa-plus"></i>
          <span>Record Payment</span>
        </router-link>
        <router-link to="/accounting/receivable-payments/application" class="quick-action-btn secondary">
          <i class="fas fa-link"></i>
          <span>Apply Payments</span>
        </router-link>
      </div>
    </div>

    <!-- Statistics Card -->
    <div class="nav-stats-card" v-if="stats">
      <h4 class="stats-title">Today's Overview</h4>
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value">{{ formatCurrency(stats.todayPayments) }}</div>
          <div class="stat-label">Today's Payments</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">{{ stats.paymentCount }}</div>
          <div class="stat-label">Payment Count</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">{{ formatCurrency(stats.pendingApplications) }}</div>
          <div class="stat-label">Pending Applications</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'AccountingNavigation',
  setup() {
    const stats = ref(null)

    const fetchStats = async () => {
      try {
        const response = await axios.get('/accounting/receivable-payments/stats')
        stats.value = response.data
      } catch (error) {
        console.error('Error fetching payment stats:', error)
      }
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }

    onMounted(() => {
      fetchStats()
    })

    return {
      stats,
      formatCurrency
    }
  }
}
</script>

<style scoped>
.accounting-navigation {
  padding: 1rem;
  background: #f8fafc;
  border-radius: 12px;
  height: 100%;
  overflow-y: auto;
}

.nav-section {
  margin-bottom: 2rem;
}

.nav-section-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: white;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.nav-section-title i {
  color: #6366f1;
}

.nav-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.nav-item {
  margin-bottom: 0.25rem;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  color: #64748b;
  text-decoration: none;
  border-radius: 8px;
  transition: all 0.3s ease;
  font-weight: 500;
}

.nav-link:hover {
  background: white;
  color: #6366f1;
  transform: translateX(4px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.nav-link.active {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.nav-link i {
  width: 20px;
  text-align: center;
}

.quick-actions {
  margin-bottom: 2rem;
  padding: 1rem;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.quick-actions-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.quick-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.875rem;
  transition: all 0.3s ease;
  text-align: left;
}

.quick-action-btn.primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.quick-action-btn.primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.quick-action-btn.secondary {
  background: #f1f5f9;
  color: #64748b;
  border: 2px solid #e2e8f0;
}

.quick-action-btn.secondary:hover {
  background: #64748b;
  color: white;
  border-color: #64748b;
}

.nav-stats-card {
  padding: 1rem;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.stats-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stats-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.stat-item {
  text-align: center;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.stat-value {
  font-size: 1rem;
  font-weight: 700;
  color: #6366f1;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.75rem;
  color: #64748b;
  line-height: 1.2;
}

@media (max-width: 768px) {
  .accounting-navigation {
    padding: 0.75rem;
  }
  
  .nav-section-title {
    font-size: 0.875rem;
    padding: 0.5rem;
  }
  
  .nav-link {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
  }
  
  .quick-actions {
    padding: 0.75rem;
  }
  
  .nav-stats-card {
    padding: 0.75rem;
  }
}
</style>