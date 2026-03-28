<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import axios from 'axios';

const products = ref([]);
const selectedProduct = ref(null);
const history = ref([]);
const loadingProducts = ref(false);
const loadingHistory = ref(false);
const search = ref('');

const loadProducts = async () => {
    loadingProducts.value = true;
    try {
        const res = await axios.get('/api/products', { params: { query: search.value } });
        products.value = res.data.data;
        if (products.value.length > 0 && !selectedProduct.value) {
            selectedProduct.value = products.value[0];
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingProducts.value = false;
    }
};

const loadHistory = async () => {
    if (!selectedProduct.value) return;
    loadingHistory.value = true;
    try {
        const res = await axios.get(`/api/products/${selectedProduct.value.id}/transactions`);
        history.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

onMounted(loadProducts);

watch(selectedProduct, () => {
    loadHistory();
});

const getTransactionSeverity = (type) => {
    switch (type.toLowerCase()) {
        case 'receipt': return 'success';
        case 'issue': return 'danger';
        case 'transfer': return 'info';
        case 'adjustment': return 'warning';
        default: return 'secondary';
    }
};

const formatCurrency = (val) => {
    return '$' + parseFloat(val).toFixed(2);
};
</script>

<template>
    <AppLayout>
        <Head title="Inventory Center" />

        <div class="center-container">
            <!-- Left Pane: Item Selector -->
            <div class="left-pane sharp-panel">
                <div class="pane-header">
                    <span class="pane-title">Item Catalog</span>
                    <InputText v-model="search" placeholder="Filter items..." @input="loadProducts" class="p-inputtext-sm w-full" />
                </div>
                <div class="list-wrapper">
                    <Listbox v-model="selectedProduct" :options="products" optionLabel="name" scrollHeight="calc(100vh - 280px)" class="sharp-listbox">
                        <template #option="{ option }">
                            <div class="product-item">
                                <span class="sku-hint">{{ option.sku }}</span>
                                <span class="product-name">{{ option.name }}</span>
                            </div>
                        </template>
                    </Listbox>
                </div>
            </div>

            <!-- Right Content Area -->
            <div class="right-pane">
                <!-- Top Right: Item Details -->
                <div class="details-section sharp-panel">
                    <template v-if="selectedProduct">
                        <div class="details-grid">
                            <div class="details-main">
                                <div class="badge-row">
                                    <Tag :value="selectedProduct.category?.name || 'GENERIC'" severity="secondary" />
                                    <Tag :value="selectedProduct.is_active ? 'ACTIVE' : 'INACTIVE'" :severity="selectedProduct.is_active ? 'success' : 'danger'" />
                                </div>
                                <h2 class="product-display-name">{{ selectedProduct.name }}</h2>
                                <p class="product-description">{{ selectedProduct.description || 'No additional technical specifications provided for this asset.' }}</p>
                            </div>
                            <div class="details-stats">
                                <div class="stat-box">
                                    <span class="stat-label">Stock Keeping Unit</span>
                                    <span class="stat-value highlight">{{ selectedProduct.sku }}</span>
                                </div>
                                <div class="stat-box">
                                    <span class="stat-label">Market Value</span>
                                    <span class="stat-value">{{ formatCurrency(selectedProduct.selling_price) }}</span>
                                </div>
                                <div class="stat-box">
                                    <span class="stat-label">UOM</span>
                                    <span class="stat-value">{{ selectedProduct.uom?.name || 'Unit' }}</span>
                                </div>
                                <div class="stat-box">
                                    <span class="stat-label">Costing Logic</span>
                                    <span class="stat-value uppercase">{{ selectedProduct.costing_method }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="empty-state">SELECT AN ASSET TO VIEW PARAMETERS</div>
                </div>

                <!-- Bottom Right: Transactions -->
                <div class="history-section sharp-panel">
                    <div class="pane-header">
                        <span class="pane-title">Movement Log / transaction History</span>
                    </div>
                    <DataTable :value="history" :loading="loadingHistory" scrollable scrollHeight="flex" class="p-datatable-sm sharp-table">
                        <template #empty>
                            <div class="empty-log">NO MOVEMENT HISTORY RECORDED FOR THIS ASSET</div>
                        </template>
                        <Column field="transaction_date" header="DATE" style="width: 120px"></Column>
                        <Column field="reference_number" header="REFERENCE" style="width: 150px">
                            <template #body="{ data }">
                                <span class="ref-num">{{ data.reference_number }}</span>
                            </template>
                        </Column>
                        <Column field="type" header="OPERATION" style="width: 130px">
                            <template #body="{ data }">
                                <Tag :value="data.type" :severity="getTransactionSeverity(data.type)" class="type-tag" />
                            </template>
                        </Column>
                        <Column field="from_location" header="ORIGIN"></Column>
                        <Column field="to_location" header="DESTINATION"></Column>
                        <Column field="status" header="STATUS" style="width: 100px">
                             <template #body="{ data }">
                                <span class="status-indicator" :class="data.status.toLowerCase()">{{ data.status }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.center-container {
    display: flex;
    gap: 1.5rem;
    height: calc(100vh - 120px);
}

.left-pane {
    width: 350px;
    display: flex;
    flex-direction: column;
    padding: 1.5rem !important;
}

.right-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    min-width: 0;
}

.pane-header {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pane-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: var(--text-secondary);
}

.list-wrapper {
    flex: 1;
    overflow: hidden;
}

.sharp-listbox {
    border: none !important;
    background: transparent !important;
}

.product-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.25rem 0;
}

.product-name {
    font-size: 0.9rem;
    font-weight: 500;
}

.sku-hint {
    font-size: 0.65rem;
    font-family: monospace;
    color: var(--accent-primary);
    font-weight: 600;
}

/* Details Section */
.details-section {
    padding: 2rem !important;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 3rem;
}

.badge-row {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.product-display-name {
    font-size: 1.75rem;
    margin: 0 0 1rem 0;
}

.product-description {
    color: var(--text-secondary);
    line-height: 1.6;
    font-size: 0.9rem;
}

.details-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.stat-box {
    background: var(--bg-deep);
    padding: 1rem;
    border: 1px solid var(--bg-panel-border);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-secondary);
}

.stat-value {
    font-size: 0.9rem;
    font-weight: 600;
}

.stat-value.highlight {
    color: var(--accent-primary);
    font-family: monospace;
}

/* History Section */
.history-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 1.5rem !important;
    overflow: hidden;
}

.ref-num {
    font-family: monospace;
    font-weight: 600;
    font-size: 0.8rem;
}

.type-tag {
    font-size: 0.65rem;
    border-radius: 2px;
}

.status-indicator {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.status-indicator.posted { color: var(--accent-primary); }
.status-indicator.draft { color: var(--text-secondary); }

.empty-state, .empty-log {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-secondary);
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    text-align: center;
}

.uppercase { text-transform: uppercase; }

/* Custom Listbox Overrides */
::v-deep(.p-listbox-list) {
    padding: 0 !important;
}

::v-deep(.p-listbox-item) {
    border-radius: 2px !important;
    padding: 0.75rem 1rem !important;
    border-left: 3px solid transparent;
    margin-bottom: 2px;
}

::v-deep(.p-listbox-item.p-highlight) {
    background: rgba(59, 130, 246, 0.1) !important;
    color: var(--text-primary) !important;
    border-left-color: var(--accent-primary);
}
</style>
