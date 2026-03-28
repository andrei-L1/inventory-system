<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import axios from 'axios';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const vendors = ref([]);
const selectedVendor = ref(null);
const history = ref([]);
const loadingVendors = ref(false);
const loadingHistory = ref(false);
const search = ref('');

const dialogVisible = ref(false);
const submitted = ref(false);

const vendorForm = ref({
    id: null,
    vendor_code: '',
    name: '',
    email: '',
    phone: '',
    address: '',
    contact_person: '',
    is_active: true
});

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

const openNew = () => {
    vendorForm.value = { id: null, vendor_code: '', name: '', email: '', phone: '', address: '', contact_person: '', is_active: true };
    submitted.value = false;
    dialogVisible.value = true;
};

const editVendor = () => {
    vendorForm.value = { ...selectedVendor.value };
    submitted.value = false;
    dialogVisible.value = true;
};

const saveVendor = async () => {
    submitted.value = true;
    if (!vendorForm.value.name) return;

    try {
        if (vendorForm.value.id) {
            await axios.put(`/api/vendors/${vendorForm.value.id}`, vendorForm.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Entity properties adjusted.', life: 3000 });
        } else {
            await axios.post('/api/vendors', vendorForm.value);
            toast.add({ severity: 'success', summary: 'Registered', detail: 'New provider initialized.', life: 3000 });
        }
        dialogVisible.value = false;
        loadVendors();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to apply parameters.', life: 3000 });
    }
};

const deleteVendor = () => {
    confirm.require({
        message: 'Are you sure you want to permanently decommission this provider?',
        header: 'Confirm Decommission',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/vendors/${selectedVendor.value.id}`);
                selectedVendor.value = null;
                toast.add({ severity: 'success', summary: 'Decommissioned', detail: 'Provider registry offline.', life: 3000 });
                loadVendors();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Dependency collision. Cannot decommission active entity.', life: 4000 });
            }
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Vendor Center" />

        <div class="vendor-grid">
            <!-- Provider Sidebar -->
            <div class="provider-pane sharp-panel">
                <div class="pane-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h3 class="pane-title" style="margin: 0;">Provider Registry</h3>
                        <Button v-if="can('manage-products')" icon="pi pi-plus" class="p-button-primary p-button-sm p-0 m-0" style="width: 24px; height: 24px;" @click="openNew" />
                    </div>
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
                            <div class="title-workflow" style="width: 100%; display: flex; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <h1 class="specs-title">{{ selectedVendor.name }}</h1>
                                    <div class="specs-badges">
                                        <Tag value="RELIABLE_SOURCE" class="gh-tag-success" />
                                        <Tag value="EXTERNAL_ENTITY" class="gh-tag-secondary" />
                                    </div>
                                </div>
                                <div v-if="can('manage-products')" style="display: flex; gap: 0.5rem;">
                                    <Button icon="pi pi-pencil" class="p-button-secondary p-button-sm p-button-outlined" @click="editVendor" />
                                    <Button icon="pi pi-trash" class="p-button-danger p-button-sm p-button-outlined" @click="deleteVendor" />
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
            
            <Dialog v-model:visible="dialogVisible" :header="vendorForm.id ? 'UPDATE REGISTRY' : 'INITIALIZE PROVIDER'" :modal="true" :style="{ width: '800px' }" class="premium-dialog">
                <div class="grid grid-cols-12 gap-x-6 gap-y-5">
                    <div class="col-span-12 md:col-span-8 flex flex-col gap-1.5">
                        <label class="form-label">Entity Designation (Name) *</label>
                        <InputText v-model="vendorForm.name" required autofocus :class="{'p-invalid': submitted && !vendorForm.name}" />
                        <small class="p-error" v-if="submitted && !vendorForm.name">Designation is required.</small>
                    </div>
                    <div class="col-span-12 md:col-span-4 flex flex-col gap-1.5">
                        <label class="form-label">Identifier Code</label>
                        <InputText v-model="vendorForm.vendor_code" class="font-mono text-sm" />
                    </div>

                    <div class="col-span-12 md:col-span-6 flex flex-col gap-1.5">
                        <label class="form-label">Communication Link (Email)</label>
                        <InputText v-model="vendorForm.email" />
                    </div>
                    <div class="col-span-12 md:col-span-6 flex flex-col gap-1.5">
                        <label class="form-label">Telemetry (Phone)</label>
                        <InputText v-model="vendorForm.phone" />
                    </div>

                    <div class="col-span-12 flex flex-col gap-1.5">
                        <label class="form-label">Physical Interface (Address)</label>
                        <InputText v-model="vendorForm.address" />
                    </div>

                    <div class="col-span-12 md:col-span-6 flex flex-col gap-1.5">
                        <label class="form-label">Primary Liaison</label>
                        <InputText v-model="vendorForm.contact_person" />
                    </div>

                    <div class="col-span-12 flex items-center gap-4 bg-slate-900/40 p-4 rounded-lg border border-white/5 mt-2">
                        <ToggleSwitch v-model="vendorForm.is_active" />
                        <span :class="vendorForm.is_active ? 'text-sky-400 font-bold' : 'text-slate-500 font-medium'" class="text-[11px] tracking-widest uppercase">
                            {{ vendorForm.is_active ? 'SOURCE ACTIVE' : 'SOURCE OFFLINE' }}
                        </span>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-3 w-full">
                        <Button label="Abort Config" icon="pi pi-times" @click="dialogVisible = false" class="p-button-text !text-slate-400 hover:!text-white transition-colors" />
                        <Button label="Apply Parameters" icon="pi pi-check" @click="saveVendor" class="p-button-primary shadow-lg shadow-sky-500/20" />
                    </div>
                </template>
            </Dialog>
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
    color: #f8fafc;
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
    color: #94a3b8;
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
    color: #94a3b8;
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
    color: #38bdf8;
}

/* Specs Section */
.specs-section {
    padding: 1.5rem !important;
}

.specs-header {
    padding-bottom: 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
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
    color: #f8fafc;
}

.specs-desc {
    color: #94a3b8;
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
    color: #94a3b8;
    text-transform: uppercase;
}

.doc-cell span, .doc-cell code {
    font-size: 14px;
    font-weight: 500;
    color: #f8fafc;
}

.doc-cell code {
    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
    background: var(--bg-panel);
    padding: 2px 4px;
    border-radius: 4px;
    color: #38bdf8;
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
    background: var(--bg-panel);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 6px 6px 0 0;
}

.gh-table {
    font-size: 13px;
}

::v-deep(.p-datatable-header) {
    display: none;
}

::v-deep(.p-datatable-thead > tr > th) {
    background: var(--bg-deep) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 12px 16px !important;
    color: #94a3b8 !important;
    font-weight: 600 !important;
}

::v-deep(.p-datatable-tbody > tr) {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
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

.gh-status-indicator {
    font-size: 12px;
    font-weight: 600;
}
.gh-status-indicator.posted { color: #38bdf8; }
.gh-status-indicator.draft { color: #94a3b8; }

.gh-tag-success {
    background: rgba(87, 171, 90, 0.1) !important;
    color: var(--accent-primary) !important;
    font-size: 10px;
    border: 1px solid rgba(87, 171, 90, 0.2);
}

.gh-tag-secondary {
    background: var(--bg-panel) !important;
    color: #94a3b8 !important;
    font-size: 10px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.empty-placeholder, .empty-ledger {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
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
    background: var(--bg-panel) !important;
    color: #f8fafc !important;
}

/* Form Label & Field Styles */
.form-label {
    display: block;
    font-size: 0.65rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    margin-bottom: 0.25rem;
}

/* Premium Dialog Design Overrides */
::v-deep(.p-dialog) {
    background: rgba(13, 17, 23, 0.95) !important;
    backdrop-filter: blur(28px) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 16px !important;
    box-shadow: 0 40px 60px -15px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255,255,255,0.05) !important;
    animation: dialogFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
}

@keyframes dialogFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

::v-deep(.p-dialog .p-dialog-header) {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 1.5rem 2rem !important;
    position: relative !important;
}

::v-deep(.p-dialog .p-dialog-header::before) {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, #38bdf8, #818cf8, transparent);
    opacity: 0.8;
}

::v-deep(.p-dialog .p-dialog-title) {
    font-size: 1.15rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    background: linear-gradient(135deg, #f8fafc, #94a3b8);
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase;
}

::v-deep(.p-dialog .p-dialog-content) {
    background: transparent !important;
    padding: 2.5rem 2rem !important;
}

::v-deep(.p-dialog .p-dialog-footer) {
    background: rgba(0, 0, 0, 0.2) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 1.25rem 2rem !important;
}

/* Premium Input Overrides */
::v-deep(.p-inputtext), 
::v-deep(.p-textarea), 
::v-deep(.p-select) {
    background: rgba(15, 23, 42, 0.6) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: #f8fafc !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.85rem !important;
    transition: all 0.2s ease !important;
}

::v-deep(.p-inputtext:enabled:focus),
::v-deep(.p-textarea:enabled:focus) {
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.15) !important;
    background: rgba(15, 23, 42, 0.8) !important;
}

::v-deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: linear-gradient(135deg, #0ea5e9, #2563eb) !important;
}
</style>
+
