<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const stats = ref({
    total_products: 0,
    total_vendors: 0,
    inventory_value: 0,
    low_stock_count: 0
});
const recentTransactions = ref([]);
const systemStatus = ref('FETCHING...');
const loading = ref(true);

const loadDashboard = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/dashboard/stats');
        stats.value = res.data.stats;
        recentTransactions.value = res.data.recent_transactions;
        systemStatus.value = res.data.system_status;
    } catch (e) {
        console.error("Dashboard error", e);
        systemStatus.value = 'ERROR';
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

onMounted(loadDashboard);
</script>

<template>
    <AppLayout>
        <Head title="Terminal Dashboard" />
        
        <div class="dashboard-header">
            <div>
                <span class="sys-badge">SYSTEM.READY</span>
                <h2 class="dashboard-title">Command Overview</h2>
                <div class="dashboard-subtitle">
                    Real-time operational metrics across active nodes
                </div>
            </div>
            <div class="header-actions">
                <button @click="loadDashboard" class="refresh-btn">
                     <i class="pi pi-refresh" :class="{ 'pi-spin': loading }"></i> REFRESH_LOGS
                </button>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Global Valuation -->
            <div class="gh-card sharp-panel highlight-border">
                <div class="card-label">Aggregate Valuation</div>
                <div class="card-value">{{ formatCurrency(stats.inventory_value) }}</div>
                <div class="card-footer">Total value of all on-hand assets.</div>
            </div>

            <!-- Risk Profile -->
            <div class="gh-card sharp-panel" :class="{ 'risk-active': stats.low_stock_count > 0 }">
                <div class="card-label">Risk Profile (Low Stock)</div>
                <div class="card-value">{{ stats.low_stock_count }}</div>
                <div class="card-footer">Assets below safety threshold.</div>
            </div>

            <!-- Stakeholders -->
            <div class="gh-card sharp-panel">
                <div class="card-label">Core Stakeholders</div>
                <div class="card-item gh-code">VENDORS: {{ stats.total_vendors }}</div>
                <div class="card-item gh-code mt-2">PRODUCTS: {{ stats.total_products }}</div>
            </div>
            
            <!-- Network Integrity -->
            <div class="gh-card sharp-panel">
                <div class="card-label">Network Integrity</div>
                <div class="card-value status-online">{{ systemStatus }}</div>
                <div class="card-footer">Global synchronization status.</div>
            </div>

            <!-- Recent Activity -->
            <div class="gh-card sharp-panel col-span-2">
                <div class="card-label">Recent Transaction Stream (Last 5)</div>
                <div v-if="recentTransactions.length > 0" class="activity-feed">
                    <div v-for="t in recentTransactions" :key="t.id" class="feed-item">
                        <span class="feed-type">{{ t.transaction_type_id }}</span>
                        <span class="feed-product">{{ t.product_name }}</span>
                        <span class="feed-qty">qty: {{ t.quantity }}</span>
                        <span class="feed-date">{{ t.transaction_date }}</span>
                    </div>
                </div>
                <div v-else class="status-empty mt-4 text-center py-4 border border-dashed border-white/5 rounded">NO RECENT ACTIVITY LOGGED</div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 1px solid var(--bg-panel-border);
    padding-bottom: 1rem;
    margin-bottom: 2rem;
}

.dashboard-title {
    margin: 0;
    font-size: 20px;
}

.dashboard-subtitle {
    color: var(--text-secondary);
    font-size: 12px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.gh-card {
    padding: 1rem;
}

.card-label {
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.card-value {
    font-size: 24px;
    font-weight: 600;
    line-height: 1.2;
}

.status-online {
    color: var(--accent-primary);
}

.status-empty {
    color: var(--text-secondary);
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
}

.card-item {
    font-size: 14px;
    font-weight: 500;
}

.gh-code {
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
    background: var(--bg-panel-hover);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--accent-primary);
    width: fit-content;
}

.card-footer {
    color: var(--text-secondary);
    font-size: 12px;
    margin-top: 1rem;
}

.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.py-4 { padding-top: 1rem; padding-bottom: 1rem; }
.text-center { text-align: center; }

.sys-badge {
    font-size: 10px;
    font-weight: 700;
    color: var(--accent-subtle);
    display: block;
    margin-bottom: 4px;
    font-family: 'JetBrains Mono', monospace;
}

.refresh-btn {
    background: transparent;
    border: 1px solid var(--bg-panel-border);
    color: var(--text-secondary);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.refresh-btn:hover {
    color: var(--text-primary);
    border-color: var(--text-secondary);
}

.activity-feed {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.feed-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: ui-monospace, monospace;
    font-size: 12px;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--bg-panel-border);
}

.feed-type {
    text-transform: uppercase;
    font-weight: 700;
    width: 100px;
    color: var(--accent-primary);
}

.feed-product {
    flex: 1;
    color: var(--text-primary);
}

.feed-qty {
    width: 60px;
    text-align: right;
    color: var(--text-secondary);
}

.feed-date {
    width: 150px;
    text-align: right;
    color: var(--text-muted);
}

.risk-active {
    border-color: rgba(244, 112, 103, 0.4) !important;
}

.risk-active .card-value {
    color: #f47067;
}

.highlight-border {
    border-color: rgba(56, 189, 248, 0.3) !important;
}

.col-span-2 {
    grid-column: span 2;
}
</style>
