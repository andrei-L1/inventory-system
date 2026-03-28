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
    locationForm.value = { ...loc };
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
                    <InputText v-model="search" placeholder="Filter node ID or name..." @input="loadLocations" style="width: 300px;" />
                    <!-- Use manage-inventory instead of manage-products for locations -->
                    <Button v-if="can('manage-inventory')" label="Initialize Node" icon="pi pi-plus" class="p-button-primary" @click="openNew" />
                </div>
            </div>

            <DataTable :value="locations" :loading="loading" responsiveLayout="scroll" :paginator="true" :rows="20"
                       class="p-datatable-sm sharp-table">
                
                <template #empty>
                    <div style="text-align: center; padding: 3rem; color: var(--text-secondary); letter-spacing: 0.05em;">
                        NO NETWORK NODES FOUND
                    </div>
                </template>

                <Column field="code" header="NODE ID" style="min-width: 120px">
                    <template #body="{ data }">
                        <span style="font-family: monospace; font-size: 0.85rem; color: var(--accent-primary);">{{ data.code }}</span>
                    </template>
                </Column>

                <Column field="name" header="DESIGNATION" style="min-width: 200px; font-weight: 600;"></Column>
                
                <Column field="location_type" header="CLASSIFICATION" style="min-width: 150px">
                    <template #body="{ data }">
                        <Tag :value="data.location_type || 'Unknown'" :severity="getSeverity(data.location_type)" style="border-radius: 2px; text-transform: uppercase; font-size: 0.65rem;" />
                    </template>
                </Column>

                <Column field="parent.name" header="PARENT NODE" style="min-width: 150px">
                    <template #body="{ data }">
                        <span v-if="data.parent" style="color: var(--text-secondary); font-size: 0.85rem;">{{ data.parent.name }}</span>
                        <span v-else style="color: #4b5563; font-style: italic; font-size: 0.8rem;">Root Node</span>
                    </template>
                </Column>

                <Column header="STATUS" style="width: 120px;">
                    <template #body="{ data }">
                        <span :style="{ color: data.is_active ? '#4ade80' : '#f87171', fontSize: '0.8rem', fontWeight: 'bold' }">
                            <i class="pi pi-circle-fill" style="font-size: 0.5rem; margin-right: 4px; vertical-align: middle;"></i>
                            {{ data.is_active ? 'ONLINE' : 'OFFLINE' }}
                        </span>
                    </template>
                </Column>
                
                <Column header="ACTIONS" style="width: 120px;" v-if="can('manage-inventory')">
                    <template #body="{ data }">
                        <Button icon="pi pi-pencil" class="p-button-text p-button-sm p-button-secondary" @click="editLocation(data)" />
                        <Button icon="pi pi-power-off" class="p-button-text p-button-sm p-button-danger" @click="deleteLocation(data)" />
                    </template>
                </Column>
            </DataTable>
            
            <Dialog v-model:visible="dialogVisible" :header="locationForm.id ? 'CONFIGURE NODE' : 'INITIALIZE NETWORK NODE'" :modal="true" :style="{ width: '800px' }" class="premium-dialog">
                <div class="grid formgrid p-fluid">
                    <!-- Identity Section -->
                    <div class="field col-12 md:col-6">
                        <label class="form-label">Node Designation *</label>
                        <InputText v-model="locationForm.name" required autofocus :class="{'p-invalid': submitted && !locationForm.name}" />
                        <small class="p-error" v-if="submitted && !locationForm.name">Name is required.</small>
                    </div>
                    <div class="field col-12 md:col-6">
                        <label class="form-label">Node ID (Code) *</label>
                        <InputText v-model="locationForm.code" required :class="{'p-invalid': submitted && !locationForm.code}" style="font-family: monospace;" />
                    </div>

                    <!-- Classifications -->
                    <div class="field col-12 md:col-6">
                        <label class="form-label">Classification *</label>
                        <Select v-model="locationForm.location_type_id" :options="locationTypes" optionLabel="name" optionValue="id" placeholder="Select Type" :class="{'p-invalid': submitted && !locationForm.location_type_id}" />
                    </div>
                    <div class="field col-12 md:col-6">
                        <label class="form-label">Parent Node</label>
                        <Select v-model="locationForm.parent_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="Top-Level Node (Root)" showClear />
                    </div>

                    <!-- Geography -->
                    <div class="field col-12">
                        <label class="form-label">Physical Address</label>
                        <InputText v-model="locationForm.address" />
                    </div>
                    <div class="field col-12 md:col-6">
                        <label class="form-label">City / Sector</label>
                        <InputText v-model="locationForm.city" />
                    </div>
                    <div class="field col-12 md:col-6">
                        <label class="form-label">Region / Country</label>
                        <InputText v-model="locationForm.country" />
                    </div>

                    <!-- Description -->
                    <div class="field col-12">
                        <label class="form-label">Technical Notes</label>
                        <Textarea v-model="locationForm.description" rows="2" />
                    </div>

                    <!-- Status -->
                    <div class="field col-12 flex align-items-center gap-3 mt-2">
                        <ToggleSwitch v-model="locationForm.is_active" />
                        <span :style="{ fontWeight: '600', letterSpacing: '0.05em', color: locationForm.is_active ? '#38bdf8' : '#94a3b8' }">
                            {{ locationForm.is_active ? 'NODE ACTIVE' : 'NODE OFFLINE' }}
                        </span>
                    </div>
                </div>

                <template #footer>
                    <Button label="Abort Config" icon="pi pi-times" @click="dialogVisible = false" class="p-button-secondary" />
                    <Button label="Execute Commitment" icon="pi pi-check" @click="saveLocation" class="p-button-primary" />
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Exact Premium Styles from Catalog.vue applied to ensure consistent design language */

.form-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
    transition: color 0.3s ease;
}

.field:focus-within .form-label {
    color: #38bdf8;
}

/* Premium Dialog Design */
::v-deep(.p-dialog) {
    background: rgba(13, 17, 23, 0.95) !important;
    backdrop-filter: blur(24px) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 16px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255,255,255,0.05) !important;
    overflow: hidden !important;
    transform-origin: center !important;
    animation: dialogFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
}

@keyframes dialogFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
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
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    background: linear-gradient(135deg, #ffffff, #94a3b8);
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    letter-spacing: 0.02em !important;
}

::v-deep(.p-dialog .p-dialog-content) {
    background: transparent !important;
    padding: 2rem !important;
}

::v-deep(.p-dialog .p-dialog-footer) {
    background: rgba(0, 0, 0, 0.2) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 1.25rem 2rem !important;
}

/* Premium Inputs */
::v-deep(.p-inputtext), 
::v-deep(.p-inputnumber-input), 
::v-deep(.p-textarea), 
::v-deep(.p-select) {
    background: rgba(15, 23, 42, 0.6) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: #f8fafc !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) inset !important;
    font-size: 0.9rem !important;
}

::v-deep(.p-inputtext:enabled:hover),
::v-deep(.p-inputnumber-input:enabled:hover),
::v-deep(.p-textarea:enabled:hover),
::v-deep(.p-select:not(.p-disabled):hover) {
    border-color: rgba(56, 189, 248, 0.4) !important;
    background: rgba(15, 23, 42, 0.8) !important;
}

::v-deep(.p-inputtext:enabled:focus),
::v-deep(.p-inputnumber-input:enabled:focus),
::v-deep(.p-textarea:enabled:focus),
::v-deep(.p-select.p-focus) {
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15), 0 2px 4px rgba(0,0,0,0.1) inset !important;
    background: rgba(15, 23, 42, 0.95) !important;
    outline: none !important;
}

::v-deep(.p-select-label) {
    padding: 0 !important;
}

::v-deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: linear-gradient(135deg, #0ea5e9, #2563eb) !important;
    box-shadow: 0 0 10px rgba(37, 99, 235, 0.5) !important;
}

::v-deep(.p-toggleswitch .p-toggleswitch-slider) {
    background: rgba(15, 23, 42, 0.8) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    transition: all 0.3s ease !important;
}

/* Dialog Footer Buttons */
::v-deep(.p-dialog-footer .p-button-secondary) {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #94a3b8 !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

::v-deep(.p-dialog-footer .p-button-secondary:hover) {
    background: rgba(255, 255, 255, 0.05) !important;
    color: #f8fafc !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
}

::v-deep(.p-dialog-footer .p-button-primary) {
    background: linear-gradient(135deg, #0ea5e9, #2563eb) !important;
    border: none !important;
    color: white !important;
    border-radius: 8px !important;
    padding: 0.75rem 1.5rem !important;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    font-weight: 600 !important;
}

::v-deep(.p-dialog-footer .p-button-primary:hover) {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.6) !important;
    background: linear-gradient(135deg, #38bdf8, #3b82f6) !important;
}

/* Sharp Table overrides */
::v-deep(.sharp-table.p-datatable .p-datatable-header) { background: transparent; border: none; }
::v-deep(.sharp-table.p-datatable .p-datatable-thead > tr > th) {
    background-color: transparent !important;
    color: var(--text-secondary) !important;
    border-bottom: 2px solid var(--bg-panel-border) !important;
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    font-weight: 700;
    text-transform: uppercase;
}
::v-deep(.sharp-table.p-datatable .p-datatable-tbody > tr) { background: transparent !important; color: var(--text-primary) !important; }
::v-deep(.sharp-table.p-datatable .p-datatable-tbody > tr:hover) { background: rgba(255, 255, 255, 0.03) !important; }
::v-deep(.sharp-table.p-datatable .p-datatable-tbody > tr > td) { border-bottom: 1px solid var(--bg-panel-border) !important; padding: 1rem !important; }
</style>
