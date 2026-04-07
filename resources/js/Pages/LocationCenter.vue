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
import Toast from 'primevue/toast';
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
const childLocations = ref([]); // Store immediate children of selected node

const loading = ref(true);
const search = ref('');
const dialogVisible = ref(false);
const submitted = ref(false);
const viewMode = ref('grid'); // 'grid' or 'details'

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
        parentLocations.value = locations.value;
        
        // If we have a selection, refresh its children
        if (locationForm.value.id) {
            refreshChildNodes(locationForm.value.id);
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load nodes', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const refreshChildNodes = (parentId) => {
    childLocations.value = locations.value.filter(l => l.parent_id === parentId);
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

const selectLocation = (loc) => {
    locationForm.value = { 
        ...loc,
        location_type_id: loc.location_type_id || (loc.location_type ? loc.location_type.id : null)
    };
    refreshChildNodes(loc.id);
    viewMode.value = 'details';
};

const openEditModal = () => {
    dialogVisible.value = true;
};

const saveLocation = async () => {
    submitted.value = true;
    
    if (!locationForm.value.name || !locationForm.value.code || !locationForm.value.location_type_id) {
        toast.add({ severity: 'warn', summary: 'Missing Information', detail: 'Location Name, Code, and Type are required.', life: 4000 });
        return;
    }

    try {
        if (locationForm.value.id) {
            await axios.put(`/api/locations/${locationForm.value.id}`, locationForm.value);
        } else {
            await axios.post('/api/locations', locationForm.value);
        }
        
        toast.add({ severity: 'success', summary: 'Location Saved', detail: 'Successfully updated', life: 3000 });
        dialogVisible.value = false;
        loadLocations();
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save location';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 });
    }
};

const deleteLocation = (loc) => {
    confirm.require({
        message: 'Are you sure you want to remove this location?',
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/locations/${loc.id}`);
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Location is now inactive.', life: 3000 });
                loadLocations();
                viewMode.value = 'grid';
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Removal failed', life: 3000 });
            }
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Warehouse Locations" />
        <Toast />
        <!-- Global ConfirmDialog is provided by AppLayout -->

        <div class="p-4 bg-zinc-950 min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-6 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Operations Hub</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Location Center</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Manage your warehouse organizational hierarchy, bins, and storage zones across the global infrastructure.</p>
                </div>
                <div class="flex items-center gap-4">
                    <Button v-if="viewMode === 'details'" 
                            label="BACK_TO_LIST" 
                            icon="pi pi-th-large" 
                            class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all" 
                            @click="viewMode = 'grid'" />
                    <div v-if="can('manage-inventory')">
                        <Button label="ADD LOCATION" icon="pi pi-plus-circle" 
                                class="!bg-sky-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" 
                                @click="openNew" />
                    </div>
                </div>
            </div>

            <!-- Primary Workspace Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-4 items-start flex-1 min-h-0">
                 
                 <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-[100px] lg:h-[calc(100vh-120px)] flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                     <div class="p-4 border-b border-zinc-800 bg-zinc-900/60">
                         <div class="flex items-center gap-3 mb-3">
                             <div class="w-2 h-2 rounded-full bg-sky-500"></div>
                             <span class="text-[10px] font-bold text-zinc-300 tracking-[0.25em] uppercase font-mono leading-none">Nodal Hierarchy</span>
                         </div>
                         <div class="relative">
                             <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 text-sm"></i>
                             <InputText 
                                 v-model="search" 
                                 placeholder="Search entities..." 
                                 @input="loadLocations"
                                 class="!w-full !pl-11 !pr-4 !bg-zinc-950 !border-zinc-800 !text-white !h-10 !text-xs !rounded-xl focus:!border-sky-500/30 transition-all font-mono"
                             />
                         </div>
                     </div>

                     <div class="flex-1 overflow-y-auto custom-scrollbar p-4">
                        <div v-if="loading" class="flex items-center justify-center p-10">
                            <i class="pi pi-spin pi-spinner text-sky-500/20 text-2xl"></i>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="loc in locations" :key="loc.id" 
                                 @click="selectLocation(loc)"
                                 class="p-3 rounded-xl border border-transparent hover:bg-zinc-800/50 hover:border-zinc-700 cursor-pointer transition-all flex items-center justify-between group">
                                 <div class="flex flex-col">
                                     <span class="text-xs font-bold text-zinc-300">{{ loc.name }}</span>
                                     <span class="text-[9px] text-zinc-500 font-mono uppercase">{{ loc.code }}</span>
                                 </div>
                                 <i class="pi pi-chevron-right text-[10px] text-zinc-700 group-hover:text-sky-500"></i>
                            </div>
                        </div>
                     </div>
                </aside>

                <!-- Right Sector: Control Plane -->
                 <main class="col-span-12 lg:col-span-8 flex flex-col gap-4 min-h-0">
                     
                     <!-- Selection View -->
                     <section v-if="viewMode === 'details' && selectedLocation" class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-5 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                        <div class="absolute top-0 right-0 w-96 h-96 bg-sky-500/5 blur-[120px] -mr-48 -mt-48 rounded-full opacity-50"></div>
                        
                        <div class="relative z-10">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 pb-10 border-b border-zinc-800/60">
                                <div class="flex flex-col flex-1">
                                    <div class="flex items-center gap-4 mb-3">
                                        <h1 class="text-4xl font-bold text-white tracking-tighter m-0">{{ locationForm.name }}</h1>
                                        <span class="text-[9px] font-bold px-3 py-1 bg-sky-500/10 border border-sky-500/20 rounded-full text-sky-400 uppercase tracking-widest font-mono">{{ locationForm.location_type?.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-zinc-500 text-xs font-mono uppercase tracking-widest">
                                        <i class="pi pi-info-circle text-[10px] text-sky-400"></i>
                                        <span>Location Code // {{ locationForm.code }}</span>
                                        <span class="mx-2 text-zinc-800">|</span>
                                        <i class="pi pi-map-marker text-[10px] text-emerald-400"></i>
                                        <span>{{ locationForm.city || 'Global' }}, {{ locationForm.country || 'N/A' }}</span>
                                    </div>
                                </div>

                                <div v-if="can('manage-inventory')" class="flex gap-3">
                                    <Button icon="pi pi-pencil" label="EDIT"
                                            class="!bg-zinc-950 !border-zinc-800 !text-sky-400 hover:!text-white hover:!border-sky-500/30 !px-6 !h-12 !rounded-xl !text-[10px] !font-bold tracking-widest transition-all" 
                                            @click="openEditModal" />
                                    <Button icon="pi pi-trash" 
                                            class="!bg-zinc-950 !border-zinc-800 !text-red-400 hover:!bg-red-500/10 !w-12 !h-12 !rounded-xl transition-all" 
                                            @click="deleteLocation(locationForm)" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                                <div class="flex flex-col gap-3">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Status</label>
                                    <div class="p-4 bg-zinc-950/50 border border-zinc-800/50 rounded-xl">
                                        <span class="text-zinc-400 text-xs block mb-1">Status</span>
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="locationForm.is_active ? 'bg-sky-500 animate-pulse' : 'bg-zinc-800'"></div>
                                            <span class="text-white font-bold text-sm tracking-tight">{{ locationForm.is_active ? 'ACTIVE' : 'OFFLINE' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Receiving Settings</label>
                                    <div class="p-4 bg-zinc-950/50 border border-zinc-800/50 rounded-xl">
                                        <span class="text-zinc-400 text-xs block mb-1">Default Receive Area</span>
                                        <span class="text-white font-bold text-sm tracking-tight">{{ locationForm.default_receive_location?.name || 'Main Area' }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Address Details</label>
                                    <div class="p-4 bg-zinc-950/50 border border-zinc-800/50 rounded-xl">
                                        <span class="text-zinc-400 text-xs block mb-1">Physical Address</span>
                                        <span class="text-white font-bold text-sm tracking-tight truncate">{{ locationForm.address || 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 bg-zinc-950/50 border border-zinc-800/50 rounded-xl">
                                 <label class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono block mb-4">Notes & Descriptions</label>
                                 <p class="text-zinc-400 text-sm leading-relaxed font-mono">
                                     {{ locationForm.description || 'No additional notes defined for this location.' }}
                                 </p>
                            </div>
                        </div>
                    </section>

                    <!-- Network Load Visualization (Placeholder) -->
                    <section class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 h-64 flex flex-col">
                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono mb-4">Storage Velocity</span>
                            <div class="flex-1 flex items-center justify-center border border-dashed border-zinc-800/50 rounded-xl opacity-20">
                                <i class="pi pi-chart-line text-4xl"></i>
                            </div>
                        </div>
                        <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 h-64 flex flex-col">
                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono mb-4">Available Space</span>
                            <div class="flex-1 flex items-center justify-center border border-dashed border-zinc-800/50 rounded-xl opacity-20">
                                <i class="pi pi-database text-4xl"></i>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
           
            <!-- Monochrome Slate Modal -->
            <Dialog 
                v-model:visible="dialogVisible" 
                :modal="true" 
                class="!bg-transparent !border-none !shadow-none ring-0 outline-none"
                :pt="{
                    root: { class: 'p-0 sm:m-4 max-w-2xl w-full' },
                    content: { class: 'p-0 !bg-transparent' }
                }"
                :showHeader="false"
            >
                <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    <!-- Header -->
                    <div class="px-8 py-6 border-b border-zinc-900 bg-zinc-900/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <div class="text-[9px] font-bold text-sky-500 font-mono tracking-[0.2em] mb-1">LOCATION_DETAILS</div>
                            <h2 class="text-white text-xl font-bold tracking-tight m-0">{{ locationForm.id ? 'Edit Location Details' : 'Add New Location' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-zinc-600 hover:!text-white !bg-transparent !border-none !w-10 !h-10 hover:!bg-zinc-900 transition-colors" @click="dialogVisible = false" />
                    </div>

                    <!-- Body -->
                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.03),transparent_40%)] max-h-[70vh] overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-12 gap-x-6 gap-y-6">
                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Location Name *</label>
                                <InputText v-model="locationForm.name" placeholder="E.g. Main Warehouse" 
                                           class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !font-bold focus:!border-sky-500/40"
                                           :class="{'!border-red-500/50': submitted && !locationForm.name}" />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Location Code *</label>
                                <InputText v-model="locationForm.code" placeholder="L-000" class="!bg-zinc-900/50 !border-zinc-800 !text-sky-400 !h-12 !font-mono focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Location Type *</label>
                                <Select v-model="locationForm.location_type_id" :options="locationTypes" optionLabel="name" optionValue="id" placeholder="Select Type" 
                                        class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" 
                                        :pt="{ 
                                            root: { class: '!bg-zinc-900/50 !border-zinc-800' },
                                            overlay: { class: '!bg-zinc-950 !border-zinc-800 !shadow-2xl' },
                                            item: { class: '!text-zinc-400 hover:!bg-sky-500/10 hover:!text-white !text-xs !font-bold !py-3' }
                                        }" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Parent Location</label>
                                <Select v-model="locationForm.parent_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="None" showClear
                                        class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" 
                                        :pt="{ 
                                            root: { class: '!bg-zinc-900/50 !border-zinc-800' },
                                            overlay: { class: '!bg-zinc-950 !border-zinc-800 !shadow-2xl' },
                                            item: { class: '!text-zinc-400 hover:!bg-sky-500/10 hover:!text-white !text-xs !font-bold !py-3' }
                                        }" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Default Receiving Location</label>
                                <Select v-model="locationForm.default_receive_location_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="Select default inbound location" showClear
                                        class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" 
                                        :pt="{ 
                                            root: { class: '!bg-zinc-900/50 !border-zinc-800' },
                                            overlay: { class: '!bg-zinc-950 !border-zinc-800 !shadow-2xl' },
                                            item: { class: '!text-zinc-400 hover:!bg-sky-500/10 hover:!text-white !text-xs !font-bold !py-3' }
                                        }" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Street Address</label>
                                <InputText v-model="locationForm.address" placeholder="123 Storage Way" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">City</label>
                                <InputText v-model="locationForm.city" placeholder="City Name" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Country</label>
                                <InputText v-model="locationForm.country" placeholder="Country Name" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Additional Notes</label>
                                <Textarea v-model="locationForm.description" rows="3" placeholder="Storage details, dock numbers, or access notes..." 
                                          class="!bg-zinc-900/50 !border-zinc-800 !text-white focus:!border-sky-500/30 !text-sm !font-mono" />
                            </div>

                            <div class="col-span-12 p-5 bg-zinc-900/30 border border-zinc-800/60 rounded-xl flex items-center justify-between mt-2">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-[11px] uppercase tracking-tight">Location Status</span>
                                    <span class="text-zinc-500 text-[9px] font-mono uppercase mt-0.5">Status // {{ locationForm.is_active ? 'active' : 'inactive' }}</span>
                                </div>
                                <ToggleSwitch v-model="locationForm.is_active" 
                                             :pt="{
                                                 slider: ({ props }) => ({
                                                     class: props.modelValue ? '!bg-sky-500' : '!bg-zinc-700'
                                                 })
                                             }" />
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-6 border-t border-zinc-900 bg-zinc-900/50 flex justify-end gap-3 items-center">
                        <span class="text-[9px] font-bold text-zinc-600 font-mono tracking-widest mr-auto uppercase">ID // {{ locationForm.id || 'PENDING' }}</span>
                        <Button label="CANCEL" class="!bg-transparent !border-zinc-800 !text-zinc-500 hover:!text-white hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="dialogVisible = false" />
                        <Button :label="locationForm.id ? 'SAVE CHANGES' : 'ADD LOCATION'" 
                                class="!bg-sky-500 !border-none !text-white !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" 
                                @click="saveLocation" />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes v4 */
</style>
