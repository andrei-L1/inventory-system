<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import Tag from 'primevue/tag';
import axios from 'axios';

const vendors = ref([]);
const selectedVendor = ref(null);
const history = ref([]);
const loadingVendors = ref(false);
const loadingHistory = ref(false);
const search = ref('');

const loadVendors = async () => {
    loadingVendors.value = true;
    try {
        const res = await axios.get('/api/vendors', { params: { query: search.value } });
        vendors.value = res.data.data;
        if (vendors.value.length > 0 && !selectedVendor.value) {
            selectedVendor.value = vendors.value[0];
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingVendors.value = false;
    }
};

const loadHistory = async () => {
    if (!selectedVendor.value) return;
    loadingHistory.value = true;
    try {
        const res = await axios.get(`/api/vendors/${selectedVendor.value.id}/transactions`);
        history.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

onMounted(loadVendors);

watch(selectedVendor, () => {
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
</script>

<template>
    <AppLayout>
        <Head title="Vendor Center" />

        <div class="vendor-grid">
            <!-- Provider Sidebar -->
            <div class="provider-pane sharp-panel">
                <div class="pane-header">
                    <h3 class="pane-title">Provider Registry</h3>
                    <div class="search-container">
                        <i class="pi pi-search search-icon"></i>
                        <InputText v-model="search" placeholder="Filter providers..." @input="loadVendors" class="gh-search-input" />
                    </div>
                </div>
                <div class="provider-list-container">
                    <Listbox v-model="selectedVendor" :options="vendors" optionLabel="name" class="gh-listbox">
                        <template #option="{ option }">
                            <div class="provider-item">
                                <span class="provider-slug">{{ option.vendor_code }}</span>
                                <span class="provider-name">{{ option.name }}</span>
                            </div>
                        </template>
                    </Listbox>
                </div>
            </div>

            <!-- Main Documentation Area -->
            <div class="main-pane">
                <!-- Top Section: Provider Specifications -->
                <div class="specs-section sharp-panel">
                    <template v-if="selectedVendor">
                        <div class="specs-header">
                            <div class="title-workflow">
                                <h1 class="specs-title">{{ selectedVendor.name }}</h1>
                                <div class="specs-badges">
                                    <Tag value="RELIABLE_SOURCE" class="gh-tag-success" />
                                    <Tag value="EXTERNAL_ENTITY" class="gh-tag-secondary" />
                                </div>
                            </div>
                            <p class="specs-desc">
                                <i class="pi pi-at mr-1" style="font-size: 12px;"></i>
                                {{ selectedVendor.email || 'No registry email provided for this entity.' }}
                            </p>
                        </div>
                        
                        <div class="specs-dashboard-grid">
                            <div class="doc-cell">
                                <label>ENTITY IDENTIFIER</label>
                                <code>{{ selectedVendor.vendor_code }}</code>
                            </div>
                            <div class="doc-cell">
                                <label>COMMUNICATION LINK</label>
                                <span>{{ selectedVendor.phone || 'N/A' }}</span>
                            </div>
                            <div class="doc-cell col-span-2">
                                <label>PRIMARY CONTACT LIAISON</label>
                                <span>{{ selectedVendor.contact_person || 'No representative assigned.' }}</span>
                            </div>
                        </div>
                    </template>
                    <div v-else class="empty-placeholder">
                        <i class="pi pi-users mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p>SELECT A PROVIDER TO INITIALIZE PARAMETERS</p>
                    </div>
                </div>

                <!-- Bottom Section: Supply Chain Ledger -->
                <div class="ledger-section sharp-panel">
                    <div class="ledger-header">
                        <h3 class="pane-title">Activity Feed / History Log <span class="gh-count">{{ history.length }}</span></h3>
                    </div>
                    <DataTable :value="history" :loading="loadingHistory" scrollable scrollHeight="flex" class="gh-table">
                        <template #empty>
                            <div class="empty-ledger">NO RECENT TRANSACTIONAL ARTIFACTS DETECTED</div>
                        </template>
                        <Column field="transaction_date" header="Timestamp" style="width: 130px"></Column>
                        <Column field="reference_number" header="Reference" style="width: 180px">
                            <template #body="{ data }">
                                <span class="gh-code">{{ data.reference_number }}</span>
                            </template>
                        </Column>
                        <Column field="type" header="Operation" style="width: 140px">
                            <template #body="{ data }">
                                <Tag :value="data.type" :severity="getTransactionSeverity(data.type)" class="gh-type-tag" />
                            </template>
                        </Column>
                        <Column field="to_location" header="Destination Facility"></Column>
                        <Column field="status" header="Node Status" style="width: 120px">
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
.vendor-grid {
    display: flex;
    gap: 1.5rem;
    height: calc(100vh - 120px);
}

.provider-pane {
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
    background: #161b22;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 12px;
    color: var(--text-secondary);
}

.search-container {
    position: relative;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: var(--text-secondary);
    z-index: 1;
}

.gh-search-input {
    width: 100% !important;
    padding-left: 30px !important;
}

.provider-list-container {
    flex: 1;
    overflow: hidden;
}

.gh-listbox {
    border: none !important;
    background: transparent !important;
}

.provider-item {
    display: flex;
    flex-direction: column;
    padding: 4px 0;
}

.provider-name {
    font-size: 14px;
    font-weight: 500;
}

.provider-slug {
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

.doc-cell.col-span-2 {
    grid-column: span 2;
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
    background: #161b22;
    padding: 2px 4px;
    border-radius: 4px;
    color: var(--accent-primary);
    width: fit-content;
}

/* Ledger Section */
.ledger-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 0 !important;
    overflow: hidden;
}

.ledger-header {
    padding: 12px 16px;
    background: #161b22;
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
    background: #0d1117 !important;
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
    background: #161b22 !important;
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

.gh-status-indicator {
    font-size: 12px;
    font-weight: 600;
}
.gh-status-indicator.posted { color: var(--accent-primary); }
.gh-status-indicator.draft { color: var(--text-secondary); }

.gh-tag-success {
    background: rgba(87, 171, 90, 0.1) !important;
    color: #57ab5a !important;
    font-size: 10px;
    border: 1px solid rgba(87, 171, 90, 0.2);
}

.gh-tag-secondary {
    background: #161b22 !important;
    color: var(--text-secondary) !important;
    font-size: 10px;
    border: 1px solid var(--bg-panel-border);
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

/* Listbox Selection Fix */
::v-deep(.p-listbox-item) {
    border-radius: 6px !important;
    padding: 8px 12px !important;
    margin-bottom: 2px;
}

::v-deep(.p-listbox-item.p-highlight) {
    background: #161b22 !important;
    color: var(--text-primary) !important;
}
</style>
