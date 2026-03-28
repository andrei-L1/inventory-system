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
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();
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

const handleLinkClick = (type, num) => {
    toast.add({ 
        severity: 'info', 
        summary: 'Navigating to Order', 
        detail: `Redirecting to ${type}: ${num}`, 
        life: 3000 
    });
};

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

const getStockStatusClass = (p) => {
    if (p.total_qoh === 0) return 'status-danger';
    if (p.total_qoh < p.reorder_point) return 'status-warning';
    return 'status-success';
};

const getStockStatusLabel = (p) => {
    if (p.total_qoh === 0) return 'CRITICAL: ZERO STOCK';
    if (p.total_qoh < p.reorder_point) return 'LOW STOCK: REPLENISH';
    return 'STOCK BALANCED';
};
</script>

<template>
    <AppLayout>
        <Head title="Inventory Center" />
        <Toast />

        <div class="inventory-grid">
            <!-- Asset Sidebar -->
            <div class="asset-pane sharp-panel">
                <div class="pane-header">
                    <h3 class="pane-title">Asset Catalog</h3>
                    <div class="search-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <InputText v-model="search" placeholder="Filter assets..." @input="loadProducts" class="search-input" />
                    </div>
                </div>
                <div class="asset-list-container">
                    <Listbox v-model="selectedProduct" :options="products" optionLabel="name" class="gh-listbox">
                        <template #option="{ option }">
                            <div class="asset-item">
                                <div class="item-header">
                                    <span class="asset-slug">{{ option.sku }}</span>
                                    <span class="asset-qoh" :class="getStockStatusClass(option)">{{ option.total_qoh }}</span>
                                </div>
                                <span class="asset-name">{{ option.name }}</span>
                            </div>
                        </template>
                    </Listbox>
                </div>
            </div>

            <!-- Main Documentation Area -->
            <div class="main-pane">
                <!-- Top Section: Technical Specifications -->
                <div class="specs-section sharp-panel">
                    <template v-if="selectedProduct">
                        <div class="specs-header">
                            <div class="title-workflow">
                                <h1 class="specs-title">{{ selectedProduct.name }}</h1>
                                <div class="specs-badges">
                                    <Tag :value="selectedProduct.category?.name || 'ASSET'" class="gh-tag-secondary" />
                                    <Tag :value="selectedProduct.is_active ? 'Active' : 'Archived'" class="gh-tag-success" />
                                </div>
                            </div>
                            <p class="specs-desc">{{ selectedProduct.description || 'No system documentation provided for this inventory asset.' }}</p>
                        </div>
                        
                        <div class="specs-dashboard-grid">
                            <div class="doc-cell">
                                <label>SERIAL REGISTRY (SKU)</label>
                                <code>{{ selectedProduct.sku }}</code>
                            </div>
                            <div class="doc-cell">
                                <label>MARKET VALUATION</label>
                                <span>{{ formatCurrency(selectedProduct.selling_price) }}</span>
                            </div>
                            <div class="doc-cell">
                                <label>QUANTUM UNIT (UOM)</label>
                                <span>{{ selectedProduct.uom?.name || 'Unit' }}</span>
                            </div>
                            <div class="doc-cell">
                                <label>COSTING ALGORITHM</label>
                                <span class="costing-text">{{ selectedProduct.costing_method }}</span>
                            </div>
                            <div class="doc-cell highlight-cell" :class="getStockStatusClass(selectedProduct)">
                                <label>STOCK ON HAND</label>
                                <span class="qoh-value">{{ selectedProduct.total_qoh }} {{ selectedProduct.uom?.abbreviation || 'pcs' }}</span>
                                <small class="status-indicator">{{ getStockStatusLabel(selectedProduct) }}</small>
                            </div>
                        </div>
                    </template>
                    <div v-else class="empty-placeholder">
                        <i class="pi pi-box mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p>SELECT AN ASSET TO INITIALIZE PARAMETERS</p>
                    </div>
                </div>

                <!-- Bottom Section: Ledger / Transactions -->
                <div class="ledger-section sharp-panel">
                    <div class="ledger-header">
                        <h3 class="pane-title">Transaction Ledger <span class="gh-count">{{ history.length }}</span></h3>
                    </div>
                    <DataTable :value="history" :loading="loadingHistory" scrollable scrollHeight="flex" class="gh-table">
                        <template #empty>
                            <div class="empty-ledger">NO TRANSACTIONAL ARTIFACTS DETECTED</div>
                        </template>
                        <Column field="transaction_date" header="Timestamp" style="width: 120px"></Column>
                        <Column field="reference_number" header="Reference" style="width: 150px">
                            <template #body="{ data }">
                                <span class="gh-code">{{ data.reference_number }}</span>
                            </template>
                        </Column>
                        <Column field="type" header="Operation" style="width: 130px">
                            <template #body="{ data }">
                                <Tag :value="data.type" :severity="getTransactionSeverity(data.type)" class="gh-type-tag" />
                            </template>
                        </Column>
                        <Column field="quantity" header="Δ Qty" style="width: 80px">
                            <template #body="{ data }">
                                <span class="ledger-qty" :class="data.type.toLowerCase()">
                                    {{ data.type.toLowerCase() === 'issue' || (data.type.toLowerCase() === 'adjustment' && data.quantity < 0) ? '-' : '+' }}{{ Math.abs(data.quantity) }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Associated Entity" style="width: 220px">
                            <template #body="{ data }">
                                <div class="entity-link-wrapper">
                                    <span v-if="data.vendor_name" @click="handleLinkClick('Vendor', data.vendor_name)" class="gh-link">
                                        <i class="pi pi-building mr-1"></i>{{ data.vendor_name }}
                                    </span>
                                    <span v-else-if="data.customer_name" @click="handleLinkClick('Customer', data.customer_name)" class="gh-link">
                                        <i class="pi pi-user mr-1"></i>{{ data.customer_name }}
                                    </span>
                                    <span v-else class="text-xs color-muted">System Internal</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="Relational Order" style="width: 160px">
                            <template #body="{ data }">
                                <div v-if="data.po_number || (data.reference_doc && data.reference_doc.includes('PO'))" 
                                    @click="handleLinkClick('PO', data.po_number || data.reference_doc)" class="gh-link">
                                    <i class="pi pi-receipt mr-1"></i>{{ data.po_number || data.reference_doc }}
                                </div>
                                <div v-else-if="data.so_number || (data.reference_doc && data.reference_doc.includes('SO'))" 
                                    @click="handleLinkClick('SO', data.so_number || data.reference_doc)" class="gh-link">
                                    <i class="pi pi-send mr-1"></i>{{ data.so_number || data.reference_doc }}
                                </div>
                                <span v-else-if="data.reference_doc" class="color-muted">{{ data.reference_doc }}</span>
                                <span v-else>-</span>
                            </template>
                        </Column>
                        <Column field="status" header="Node Status" style="width: 100px">
                             <template #body="{ data }">
                                <span class="gh-status-indicator" :class="data.status.toLowerCase()">{{ data.status }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.inventory-grid {
    display: flex;
    gap: 1.5rem;
    height: calc(100vh - 120px);
}

.asset-pane {
    width: 320px;
    display: flex;
    flex-direction: column;
    padding: 1rem !important;
}

.main-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    min-width: 0;
}

.pane-header {
    margin-bottom: 1rem;
}

.pane-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.gh-count {
    background: var(--bg-panel);
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 12px;
    color: var(--text-secondary);
}


.asset-list-container {
    flex: 1;
    overflow: hidden;
}

.gh-listbox {
    border: none !important;
    background: transparent !important;
}

.asset-item {
    display: flex;
    flex-direction: column;
    padding: 4px 0;
}

.asset-name {
    font-size: 14px;
    font-weight: 500;
}

.asset-slug {
    font-size: 11px;
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
    color: var(--accent-primary);
}

/* Specs Section */
.specs-section {
    padding: 1.5rem !important;
}

.specs-header {
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--bg-panel-border);
    margin-bottom: 1.25rem;
}

.title-workflow {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.specs-title {
    font-size: 20px;
    margin: 0;
}

.specs-desc {
    color: var(--text-secondary);
    font-size: 14px;
    margin: 0;
}

.specs-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.doc-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.doc-cell label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
}

.doc-cell span, .doc-cell code {
    font-size: 14px;
    font-weight: 500;
}

.doc-cell code {
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
    background: var(--bg-panel);
    padding: 2px 4px;
    border-radius: 4px;
    color: var(--accent-primary);
    width: fit-content;
}

.costing-text {
    text-transform: uppercase;
}

/* Ledger Section */
.ledger-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 0 !important; /* Table occupies header + edge-to-edge content */
    overflow: hidden;
}

.ledger-header {
    padding: 12px 16px;
    background: var(--bg-panel);
    border-bottom: 1px solid var(--bg-panel-border);
    border-radius: 6px 6px 0 0;
}

.gh-table {
    font-size: 13px;
}

::v-deep(.p-datatable-header) {
    display: none;
}

::v-deep(.p-datatable-thead > tr > th) {
    background: var(--bg-panel-hover) !important;
    border-bottom: 1px solid var(--bg-panel-border) !important;
    padding: 12px 16px !important;
    color: var(--text-secondary) !important;
    font-weight: 600 !important;
}

::v-deep(.p-datatable-tbody > tr) {
    background: transparent !important;
    border-bottom: 1px solid var(--bg-panel-border) !important;
}

::v-deep(.p-datatable-tbody > tr:hover) {
    background: var(--bg-panel) !important;
}

.gh-code {
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
    font-size: 12px;
}

.gh-type-tag {
    font-size: 11px;
    border-radius: 12px;
    padding: 2px 10px;
}

.ledger-qty {
    font-weight: 600;
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
}
.ledger-qty.receipt { color: var(--accent-primary); } /* GitHub Green */
.ledger-qty.issue { color: #f47067; } /* GitHub Red */

.gh-link {
    color: var(--accent-primary);
    cursor: pointer;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
}

.gh-link:hover {
    text-decoration: underline;
}

.gh-status-indicator {
    font-size: 12px;
    font-weight: 600;
}
.gh-status-indicator.posted { color: var(--accent-primary); }
.gh-status-indicator.draft { color: var(--text-secondary); }

.color-muted {
    color: var(--text-secondary);
    font-size: 12px;
}

.empty-placeholder, .empty-ledger {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--text-secondary);
    font-size: 14px;
    text-align: center;
}

.gh-tag-success {
    background: rgba(87, 171, 90, 0.1) !important;
    color: var(--accent-primary) !important;
    font-size: 10px;
    border: 1px solid rgba(87, 171, 90, 0.2);
}

.gh-tag-secondary {
    background: var(--bg-panel) !important;
    color: var(--text-secondary) !important;
    font-size: 10px;
    border: 1px solid var(--bg-panel-border);
}

/* Listbox Selection Fix */
::v-deep(.p-listbox-item) {
    border-radius: 6px !important;
    padding: 8px 12px !important;
    margin-bottom: 2px;
}

::v-deep(.p-listbox-item.p-highlight) {
    background: var(--bg-panel-hover) !important;
    color: var(--text-primary) !important;
}

/* Custom Inventory Center Additions */
.highlight-cell {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--bg-panel-border);
    border-radius: 4px;
    padding: 10px;
    text-align: center;
}

.qoh-value {
    font-size: 20px !important;
    font-weight: 700 !important;
    font-family: 'JetBrains Mono', monospace;
}

.status-indicator {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.status-success .qoh-value, .status-success .status-indicator, .status-success.asset-qoh { color: var(--accent-primary); }
.status-warning .qoh-value, .status-warning .status-indicator, .status-warning.asset-qoh { color: #e3b341; }
.status-danger .qoh-value, .status-danger .status-indicator, .status-danger.asset-qoh { color: #f47067; }

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.asset-qoh {
    font-size: 11px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, monospace;
    background: rgba(0,0,0,0.2);
    padding: 1px 6px;
    border-radius: 4px;
}
</style>
