<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <Head title="Terminal Dashboard" />
        
        <div class="dashboard-header">
            <div>
                <h2 class="dashboard-title">Command Overview</h2>
                <div class="dashboard-subtitle">
                    Real-time network status
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Status Card -->
            <div class="gh-card sharp-panel">
                <div class="card-label">Network Status</div>
                <div class="card-value status-online">ONLINE</div>
                <div class="card-footer">All datacenters connected.</div>
            </div>

            <!-- Active Modules Card -->
            <div class="gh-card sharp-panel">
                <div class="card-label">Active Systems</div>
                <div class="card-item gh-code">CATALOG_API</div>
                <div class="card-item gh-code mt-2">STOCK_ENGINE</div>
            </div>
            
            <!-- Global Inventory Card -->
            <div class="gh-card sharp-panel">
                <div class="card-label">Transaction Queue</div>
                <div class="card-value status-empty">EMPTY</div>
                <div class="card-footer">Awaiting stock movements.</div>
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

.card-value.status-online {
    color: var(--accent-primary);
}

.card-value.status-empty {
    color: var(--text-secondary);
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
</style>
