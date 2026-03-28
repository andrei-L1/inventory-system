<script setup>
import { ref, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import axios from 'axios';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const locations = ref([]);
const locationTypes = ref([]);
const parentLocations = ref([]);

const loading = ref(true);
const search = ref('');
const dialogVisible = ref(false);
const submitted = ref(false);

const locationForm = ref({
    id: null,
    code: '',
    name: '',
    location_type_id: null,
    parent_id: null,
    default_receive_location_id: null,
    description: '',
    address: '',
    city: '',
    country: '',
    is_active: true
});

const loadLocations = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/locations', {
            params: { query: search.value, per_page: 100 }
        });
        locations.value = response.data.data;
        // Populate parent options (warehouses/regions)
        parentLocations.value = locations.value;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load nodes', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const loadMetadata = async () => {
    try {
        const typeRes = await axios.get('/api/location-types');
        locationTypes.value = typeRes.data.data;
    } catch (e) {
        console.error("Metadata load error", e);
    }
};

onMounted(() => {
    loadLocations();
    loadMetadata();
});

const openNew = () => {
    locationForm.value = {
        id: null,
        code: '',
        name: '',
        location_type_id: null,
        parent_id: null,
        default_receive_location_id: null,
        description: '',
        address: '',
        city: '',
        country: '',
        is_active: true
    };
    submitted.value = false;
    dialogVisible.value = true;
};

const editLocation = (loc) => {
    locationForm.value = { 
        ...loc,
        location_type_id: loc.location_type_id || (loc.location_type ? loc.location_type.id : null)
    };
    dialogVisible.value = true;
};

const saveLocation = async () => {
    submitted.value = true;
    
    if (!locationForm.value.name || !locationForm.value.code || !locationForm.value.location_type_id) {
        return;
    }

    try {
        if (locationForm.value.id) {
            await axios.put(`/api/locations/${locationForm.value.id}`, locationForm.value);
        } else {
            await axios.post('/api/locations', locationForm.value);
        }
        
        toast.add({ severity: 'success', summary: 'Node Established', detail: 'Topology Updated', life: 3000 });
        dialogVisible.value = false;
        loadLocations();
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to update topology';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 });
    }
};

const deleteLocation = (loc) => {
    confirm.require({
        message: 'Are you sure you want to decommission this network node?',
        header: 'Confirm Decommission',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/locations/${loc.id}`);
                toast.add({ severity: 'success', summary: 'Decommissioned', detail: 'Node offline.', life: 3000 });
                loadLocations();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Decommission failed', life: 3000 });
            }
        }
    });
};

const getSeverity = (typeName) => {
    if (!typeName) return 'info';
    typeName = typeName.toLowerCase();
    if (typeName.includes('warehouse')) return 'warn';
    if (typeName.includes('store')) return 'success';
    if (typeName.includes('transit')) return 'info';
    if (typeName.includes('virtual')) return 'secondary';
    return 'info';
};
</script>

<template>
    <AppLayout>
        <Head title="Location Topology" />
        
        <div class="sharp-panel" style="display: flex; flex-direction: column; height: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h2 class="brand-title" style="margin: 0; font-size: 1.5rem;">Network Topology</h2>
                    <div style="color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.25rem;">Warehouse & Zone Grid</div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <div class="search-wrapper" style="width: 300px;">
                        <i class="pi pi-search search-icon"></i>
                        <InputText v-model="search" placeholder="Filter node ID or name..." @input="loadLocations" class="search-input" />
                    </div>
                    <!-- Use manage-inventory instead of manage-products for locations -->
                    <Button v-if="can('manage-inventory')" label="Initialize Node" icon="pi pi-plus" class="p-button-primary" @click="openNew" />
                </div>
            </div>

            <!-- Grid of Clickable Cards -->
            <div v-if="loading" class="loading-state">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: var(--accent-primary);"></i>
            </div>
            <div v-else-if="locations.length === 0" class="empty-state">
                 NO NETWORK NODES FOUND
            </div>
            <div v-else class="slate-card-grid">
                <div v-for="loc in locations" :key="loc.id" class="slate-card" @click="can('manage-inventory') ? editLocation(loc) : null">
                    <div class="card-header">
                        <span class="node-id">{{ loc.code }}</span>
                        <div class="status-indicator">
                            <span class="dot" :class="loc.is_active ? 'active' : 'inactive'"></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="node-name">{{ loc.name }}</h3>
                        <span class="node-type">{{ loc.location_type?.name || loc.location_type || 'Unclassified' }}</span>
                        
                        <div class="node-meta">
                            <div class="meta-row">
                                <i class="pi pi-sitemap"></i>
                                <span>{{ loc.parent ? loc.parent.name : 'Root Node' }}</span>
                            </div>
                            <div class="meta-row" v-if="loc.city || loc.country">
                                <i class="pi pi-map-marker"></i>
                                <span>{{ [loc.city, loc.country].filter(Boolean).join(', ') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer" v-if="can('manage-inventory')">
                        <Button icon="pi pi-pencil" class="p-button-text action-btn" @click.stop="editLocation(loc)" />
                        <Button icon="pi pi-power-off" class="p-button-text action-btn delete-btn" @click.stop="deleteLocation(loc)" />
                    </div>
                </div>
            </div>
            
            <!-- Monochrome Slate Modal -->
            <Dialog 
                v-model:visible="dialogVisible" 
                :modal="true" 
                :style="{ width: '750px', margin: '0 1rem' }" 
                class="slate-modal"
                :closable="!submitted"
                :showHeader="false"
            >
                <div class="slate-modal-inner">
                    <div class="slate-modal-header">
                        <div class="header-left">
                            <div class="slate-badge">{{ locationForm.id ? 'NODE.UPDATE' : 'NODE.INIT' }}</div>
                            <h2>{{ locationForm.id ? 'Configure Node' : 'Initialize Network Node' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="p-button-text close-trigger" @click="dialogVisible = false" />
                    </div>

                    <div class="slate-modal-body">
                        <div class="slate-form-grid">
                            <div class="p-field col-span-2">
                                <label>Node Designation *</label>
                                <InputText v-model="locationForm.name" required autofocus :class="{'p-invalid': submitted && !locationForm.name}" placeholder="E.g. Primary Alpha Hub" />
                            </div>
                            <div class="p-field">
                                <label>Node ID (Code) *</label>
                                <InputText v-model="locationForm.code" required :class="{'p-invalid': submitted && !locationForm.code}" placeholder="N-0001" style="font-family: 'JetBrains Mono', monospace;" />
                            </div>
                            <div class="p-field">
                                <label>Classification *</label>
                                <Select v-model="locationForm.location_type_id" :options="locationTypes" optionLabel="name" optionValue="id" placeholder="Select Type" :class="{'p-invalid': submitted && !locationForm.location_type_id}" />
                            </div>
                            <div class="p-field col-span-2">
                                <label>Parent Topology Node</label>
                                <Select v-model="locationForm.parent_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="Top-Level Node (Root)" showClear />
                            </div>

                            <div class="p-field col-span-2">
                                <label>Default Receive Location</label>
                                <Select v-model="locationForm.default_receive_location_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="Standard Inbound Target" showClear />
                            </div>
                            
                            <div class="p-field col-span-2">
                                <label>Physical Coordinates / Address</label>
                                <InputText v-model="locationForm.address" placeholder="Sector line..." />
                            </div>
                            <div class="p-field">
                                <label>Region / City</label>
                                <InputText v-model="locationForm.city" placeholder="Optional" />
                            </div>
                            <div class="p-field">
                                <label>Country ID</label>
                                <InputText v-model="locationForm.country" placeholder="Optional" />
                            </div>

                            <div class="p-field col-span-2">
                                <label>Technical Notes</label>
                                <Textarea v-model="locationForm.description" rows="2" class="resize-none" placeholder="Add operational parameters..." />
                            </div>
                            
                            <div class="p-field col-span-2 status-control">
                                <div class="control-info">
                                    <h4>Operational Status</h4>
                                    <p>Toggle system availability for this network node.</p>
                                </div>
                                <ToggleSwitch v-model="locationForm.is_active" />
                            </div>
                        </div>
                    </div>

                    <div class="slate-modal-footer">
                        <div class="sys-id">UID // {{ locationForm.id || 'PENDING' }}</div>
                        <div class="action-buttons-group">
                            <Button label="Abort" class="p-button-secondary" @click="dialogVisible = false" />
                            <Button :label="locationForm.id ? 'COMMIT UPDATE' : 'EXECUTE INIT'" class="p-button-primary execute-btn" @click="saveLocation" />
                        </div>
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Core Page Layout Override */
.sharp-panel {
    background: var(--bg-deep);
    min-height: 100vh;
    padding: 1.5rem;
}

.brand-title {
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

/* Card Grid Layout */
.slate-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.slate-card {
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 6px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.slate-card:hover {
    border-color: var(--text-secondary);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.node-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    background: var(--bg-deep);
    color: var(--text-secondary);
    padding: 4px 8px;
    border: 1px solid var(--bg-panel-border);
    border-radius: 4px;
    letter-spacing: 0.05em;
}

.status-indicator .dot {
    display: block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.status-indicator .dot.active { background: var(--accent-primary); box-shadow: 0 0 8px rgba(250,250,250,0.4); }
.status-indicator .dot.inactive { background: var(--text-secondary); opacity: 0.5; }

.card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.card-body h3.node-name {
    margin: 0;
    font-size: 18px;
    color: var(--text-primary);
    font-weight: 600;
}

.node-type {
    display: inline-block;
    margin-top: 0.25rem;
    font-size: 10px;
    text-transform: uppercase;
    color: var(--text-secondary);
    font-weight: 700;
    letter-spacing: 0.05em;
}

.node-meta {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-secondary);
}

.meta-row i {
    color: var(--text-muted);
}

.card-footer {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--bg-panel-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.action-btn {
    color: var(--text-secondary) !important;
    padding: 0.4rem !important;
    width: 32px !important;
    height: 32px !important;
}

.action-btn:hover {
    color: var(--text-primary) !important;
    background: var(--bg-panel-hover) !important;
}

.delete-btn:hover {
    color: var(--text-primary) !important;
    background: var(--bg-panel-hover) !important;
}

.empty-state {
    text-align: center;
    padding: 4rem;
    color: var(--text-secondary);
    letter-spacing: 0.1em;
    font-size: 12px;
    border: 1px dashed var(--bg-panel-border);
    border-radius: 6px;
    margin-top: 2rem;
}

.loading-state {
    display: flex;
    justify-content: center;
    padding: 4rem;
}

/* --- Slate Modal Redesign (Mirrored from Catalog.vue) --- */
::v-deep(.slate-modal) {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
}

::v-deep(.p-dialog-content) {
    padding: 0 !important;
    background: transparent !important;
    outline: none !important;
    border: none !important;
}

.slate-modal-inner {
    background: var(--bg-deep); /* Absolute black */
    border: 1px solid var(--bg-panel-border);
    border-radius: 8px; /* Slightly softer for a modern look */
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    max-height: 85vh;
    overflow: hidden;
}

/* Header */
.slate-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2rem 2.5rem 1.5rem;
    border-bottom: 1px solid var(--bg-panel-border);
    background: var(--bg-panel);
}

.header-left .slate-badge {
    font-size: 10px;
    font-weight: 700;
    color: var(--accent-subtle);
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
    font-family: 'JetBrains Mono', monospace;
}

.header-left h2 {
    font-size: 22px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    letter-spacing: -0.02em;
}

.close-trigger {
    color: var(--text-secondary) !important;
    width: 32px !important;
    height: 32px !important;
}

/* Modal Body */
.slate-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2.5rem;
    background: var(--bg-deep);
}

.slate-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem 2rem;
}

.col-span-2 {
    grid-column: span 2;
}

.p-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.p-field label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Clean Form Inputs */
::v-deep(.p-inputtext), ::v-deep(.p-select), ::v-deep(.p-inputnumber-input), ::v-deep(.p-textarea) {
    background: var(--bg-panel) !important;
    border: 1px solid var(--bg-panel-border) !important;
    color: var(--text-primary) !important;
    border-radius: 4px !important;
    padding: 10px 14px !important;
    font-size: 14px !important;
    box-shadow: none !important;
    transition: all 0.2s ease !important;
}

::v-deep(.p-inputtext:focus), ::v-deep(.p-select:focus), ::v-deep(.p-inputnumber-input:focus), ::v-deep(.p-textarea:focus) {
    border-color: var(--accent-primary) !important;
    outline: 0 !important;
}

/* Status Control Box */
.status-control {
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 4px;
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.control-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.control-info p {
    margin: 0;
    font-size: 12px;
    color: var(--text-secondary);
}

/* Footer */
.slate-modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2.5rem;
    background: var(--bg-panel);
    border-top: 1px solid var(--bg-panel-border);
}

.sys-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: var(--text-secondary);
}

.action-buttons-group {
    display: flex;
    gap: 1rem;
}

.execute-btn {
    background: var(--bg-panel-hover) !important;
    border: none !important;
    padding: 10px 24px !important;
    font-weight: 600 !important;
    letter-spacing: 0.02em;
}

@media (max-width: 768px) {
    .slate-card-grid { grid-template-columns: 1fr; }
    ::v-deep(.slate-modal) { width: 95vw !important; margin: 0 !important; }
    .slate-form-grid { grid-template-columns: 1fr; }
    .col-span-2 { grid-column: span 1; }
    .slate-modal-header, .slate-modal-body, .slate-modal-footer { padding: 1.5rem; }
}
</style>
