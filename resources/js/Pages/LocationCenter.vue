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
import ConfirmDialog from 'primevue/confirmdialog';
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
        <ConfirmDialog />

        <div class="p-8 bg-deep min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Manage Storage & Warehouses</span>
                    <h1 class="text-3xl font-bold text-primary tracking-tight m-0 mb-2">Location Center</h1>
                    <p class="text-secondary text-sm max-w-2xl leading-relaxed">
                        {{ viewMode === 'grid' 
                            ? 'Overview of physical warehouses, sections, and storage zones.' 
                            : 'Detailed view and settings for this location.' 
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <Button v-if="viewMode === 'details'" 
                            label="BACK_TO_LIST" 
                            icon="pi pi-th-large" 
                            class="!bg-panel !border-panel-border !text-secondary hover:!text-primary !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all" 
                            @click="viewMode = 'grid'" />
                    <div v-if="can('manage-inventory')">
                        <Button label="ADD LOCATION" icon="pi pi-plus-circle" 
                                class="!bg-sky-500 !border-none !text-primary !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" 
                                @click="openNew" />
                    </div>
                </div>
            </div>

            <!-- View Mode: GRID OVERVIEW -->
            <div v-if="viewMode === 'grid'" class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <div class="flex justify-between items-center mb-6">
                    <div class="relative w-full max-w-md">
                        <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-secondary text-sm"></i>
                        <InputText 
                            v-model="search" 
                            placeholder="Filter by name or code..." 
                            @input="loadLocations" 
                            class="!w-full !pl-11 !pr-4 !bg-panel/40 !border-panel-border !text-primary !h-12 !text-xs !rounded-xl focus:!border-sky-500/30 transition-all font-mono"
                        />
                    </div>
                </div>

                <div v-if="loading" class="flex-1 flex items-center justify-center">
                    <i class="pi pi-spin pi-spinner text-sky-500/20 text-5xl"></i>
                </div>
                
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 overflow-y-auto custom-scrollbar pr-2 pb-10">
                    <div v-for="loc in locations" :key="loc.id" 
                         @click="selectLocation(loc)"
                         class="group relative bg-panel/40 border border-panel-border/80 rounded-2xl p-6 hover:border-sky-500/40 hover:bg-panel-hover/40 cursor-pointer transition-all duration-500 overflow-hidden shadow-xl">
                        
                        <!-- Background Accent -->
                        <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-sky-500/5 blur-3xl rounded-full group-hover:bg-sky-500/10 transition-colors"></div>
                        
                        <div class="relative z-10">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-10 h-10 rounded-xl bg-deep flex items-center justify-center border border-panel-border group-hover:border-sky-500/30 transition-colors">
                                    <i :class="[
                                        loc.location_type?.name?.toLowerCase().includes('warehouse') ? 'pi-home' : 
                                        loc.location_type?.name?.toLowerCase().includes('store') ? 'pi-shopping-cart' : 'pi-box'
                                    ]" class="pi text-secondary group-hover:text-sky-400 text-sm transition-colors"></i>
                                </div>
                                <span class="text-[9px] font-bold text-muted font-mono tracking-widest uppercase">{{ loc.code }}</span>
                            </div>
                            
                            <h3 class="text-primary font-bold text-lg mb-1 group-hover:text-sky-400 transition-colors tracking-tight">{{ loc.name }}</h3>
                            <div class="flex items-center gap-2 mb-8">
                                <span class="text-[9px] font-bold text-secondary uppercase tracking-widest font-mono">{{ loc.location_type?.name }}</span>
                                <div class="w-1 h-1 rounded-full bg-panel-hover"></div>
                                <span class="text-[9px] font-bold text-secondary uppercase tracking-widest font-mono">{{ loc.city || 'GLOBAL' }}</span>
                            </div>
                            
                            <div class="pt-6 border-t border-panel-border/50 flex justify-between items-center">
                                <div class="flex flex-col">
                                    <span class="text-[8px] font-bold text-muted uppercase tracking-[0.2em] mb-1">Sub-locations</span>
                                    <span class="text-xs font-bold text-primary">{{ locations.filter(l => l.parent_id === loc.id).length }} Areas</span>
                                </div>
                                <div class="w-8 h-8 rounded-full border border-panel-border flex items-center justify-center group-hover:bg-sky-500 group-hover:border-sky-500 transition-all">
                                    <i class="pi pi-arrow-up-right text-[10px] text-muted group-hover:text-primary transition-colors"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Mode: DEEP TOPOLOGY DETAILS -->
            <div v-if="viewMode === 'details'" class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-8 flex-1 min-h-0">
                
                <!-- Left Sector: Nodal Hierarchy Path (Anchor) -->
                <aside class="col-span-12 lg:col-span-4 xl:col-span-3 flex flex-col min-h-0 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-6 border-b border-panel-border bg-panel/60 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-sky-500 shadow-[0_0_8px_rgba(14,165,233,0.8)]"></div>
                            <span class="text-[10px] font-bold text-primary tracking-[0.2em] uppercase font-mono leading-none">Location Tree</span>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
                        <!-- Parent Location -->
                        <div v-if="locationForm.parent" class="flex items-start gap-4 mb-4 group/parent">
                            <div class="flex flex-col items-center pt-1.5 min-w-[12px]">
                                <div @click="selectLocation(locationForm.parent)" 
                                     class="w-3 h-3 rounded-full border-2 border-zinc-700 bg-deep hover:border-sky-500 cursor-pointer transition-all z-10 shadow-lg group-hover/parent:scale-125"></div>
                                <div class="w-0.5 h-12 bg-panel-hover"></div>
                            </div>
                            <div class="flex flex-col gap-0.5 opacity-40 hover:opacity-100 transition-opacity cursor-pointer mb-6" @click="selectLocation(locationForm.parent)">
                                <span class="text-[8px] font-bold text-muted font-mono tracking-widest uppercase leading-none">PARENT_LOCATION</span>
                                <span class="text-secondary font-bold text-xs leading-none group-hover/parent:text-primary transition-colors">{{ locationForm.parent.name }}</span>
                            </div>
                        </div>

                        <!-- Selected Node Root (Current Context) -->
                        <div class="flex items-start gap-4 mb-2">
                            <div class="flex flex-col items-center pt-1 min-w-[20px]">
                                <div class="w-5 h-5 rounded-full bg-sky-500 shadow-[0_0_15px_rgba(14,165,233,0.6)] ring-4 ring-sky-500/10 z-20 flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></div>
                                </div>
                                <div v-if="childLocations.length > 0" class="w-0.5 h-10 bg-gradient-to-b from-sky-500 to-zinc-800"></div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-[9px] font-bold text-sky-400 font-mono tracking-[0.2em] uppercase leading-none">CURRENT_VIEW</span>
                                <span class="text-primary font-bold text-base leading-none tracking-tight">{{ locationForm.name }}</span>
                            </div>
                        </div>

                        <!-- Sub-areas -->
                        <div v-if="childLocations.length > 0" class="flex flex-col">
                            <div v-for="(child, index) in childLocations" :key="child.id" class="flex items-start gap-4 group">
                                <div class="flex flex-col items-center min-w-[20px]">
                                    <div class="flex relative">
                                        <div class="absolute -left-0 top-3 w-4 h-0.5 bg-panel-hover group-hover:bg-sky-500/40 transition-colors"></div>
                                        <div class="absolute left-4 top-[0.7rem] w-1 h-1 rounded-full bg-panel-hover group-hover:bg-sky-500 z-10 transition-all"></div>
                                        <div class="w-0.5 h-14 bg-panel-hover" :class="{'bg-transparent': index === childLocations.length - 1}"></div>
                                    </div>
                                </div>
                                <div @click="selectLocation(child)" 
                                     class="flex-1 mt-0.5 p-3 bg-deep/50 border border-panel-border/80 rounded-xl hover:border-sky-500/40 hover:bg-panel/80 cursor-pointer transition-all duration-300 flex justify-between items-center group/card ml-2">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-[10px] font-bold text-secondary group-hover/card:text-primary transition-colors tracking-tight">{{ child.name }}</span>
                                        <span class="text-[8px] text-muted font-mono uppercase tracking-widest">{{ child.code }}</span>
                                    </div>
                                    <i class="pi pi-chevron-right text-[8px] text-muted group-hover/card:text-sky-500 transition-all transform group-hover/card:translate-x-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Right Sector: Main Data Area -->
                <main class="col-span-12 lg:col-span-8 xl:col-span-9 flex flex-col gap-8 min-h-0 overflow-y-auto custom-scrollbar pr-2">
                    
                    <!-- Information Manifest -->
                    <section class="bg-panel/40 border border-panel-border/80 rounded-2xl p-8 backdrop-blur-sm shadow-2xl relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-96 h-96 bg-sky-500/5 blur-[120px] -mr-48 -mt-48 rounded-full opacity-50"></div>
                        
                        <div class="relative z-10">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 pb-10 border-b border-panel-border/60">
                                <div class="flex flex-col flex-1">
                                    <div class="flex items-center gap-4 mb-3">
                                        <h1 class="text-4xl font-bold text-primary tracking-tighter m-0">{{ locationForm.name }}</h1>
                                        <span class="text-[9px] font-bold px-3 py-1 bg-sky-500/10 border border-sky-500/20 rounded-full text-sky-400 uppercase tracking-widest font-mono">{{ locationForm.location_type?.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-secondary text-xs font-mono uppercase tracking-widest">
                                        <i class="pi pi-info-circle text-[10px] text-sky-400"></i>
                                        <span>Location Code // {{ locationForm.code }}</span>
                                        <span class="mx-2 border-panel-border">|</span>
                                        <i class="pi pi-map-marker text-[10px] text-emerald-400"></i>
                                        <span>{{ locationForm.city || 'Global' }}, {{ locationForm.country || 'N/A' }}</span>
                                    </div>
                                </div>

                                <div v-if="can('manage-inventory')" class="flex gap-3">
                                    <Button icon="pi pi-pencil" label="EDIT"
                                            class="!bg-deep !border-panel-border !text-sky-400 hover:!text-primary hover:!border-sky-500/30 !px-6 !h-12 !rounded-xl !text-[10px] !font-bold tracking-widest transition-all" 
                                            @click="openEditModal" />
                                    <Button icon="pi pi-trash" 
                                            class="!bg-deep !border-panel-border !text-red-400 hover:!bg-red-500/10 !w-12 !h-12 !rounded-xl transition-all" 
                                            @click="deleteLocation(locationForm)" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                                <div class="flex flex-col gap-3">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Status</label>
                                    <div class="p-4 bg-deep/50 border border-panel-border/50 rounded-xl">
                                        <span class="text-secondary text-xs block mb-1">Status</span>
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="locationForm.is_active ? 'bg-sky-500 animate-pulse' : 'bg-panel-hover'"></div>
                                            <span class="text-primary font-bold text-sm tracking-tight">{{ locationForm.is_active ? 'ACTIVE' : 'OFFLINE' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Receiving Settings</label>
                                    <div class="p-4 bg-deep/50 border border-panel-border/50 rounded-xl">
                                        <span class="text-secondary text-xs block mb-1">Default Receive Area</span>
                                        <span class="text-primary font-bold text-sm tracking-tight">{{ locationForm.default_receive_location?.name || 'Main Area' }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-3">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Address Details</label>
                                    <div class="p-4 bg-deep/50 border border-panel-border/50 rounded-xl">
                                        <span class="text-secondary text-xs block mb-1">Physical Address</span>
                                        <span class="text-primary font-bold text-sm tracking-tight truncate">{{ locationForm.address || 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 bg-deep/50 border border-panel-border/50 rounded-xl">
                                 <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono block mb-4">Notes & Descriptions</label>
                                 <p class="text-secondary text-sm leading-relaxed font-mono">
                                     {{ locationForm.description || 'No additional notes defined for this location.' }}
                                 </p>
                            </div>
                        </div>
                    </section>

                    <!-- Network Load Visualization (Placeholder) -->
                    <section class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="bg-panel/40 border border-panel-border/80 rounded-2xl p-6 h-64 flex flex-col">
                            <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono mb-4">Storage Velocity</span>
                            <div class="flex-1 flex items-center justify-center border border-dashed border-panel-border/50 rounded-xl opacity-20">
                                <i class="pi pi-chart-line text-4xl"></i>
                            </div>
                        </div>
                        <div class="bg-panel/40 border border-panel-border/80 rounded-2xl p-6 h-64 flex flex-col">
                            <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono mb-4">Available Space</span>
                            <div class="flex-1 flex items-center justify-center border border-dashed border-panel-border/50 rounded-xl opacity-20">
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
                <div class="bg-deep border border-panel-border rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    <!-- Header -->
                    <div class="px-8 py-6 border-b border-panel-border bg-panel/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <div class="text-[9px] font-bold text-sky-500 font-mono tracking-[0.2em] mb-1">LOCATION_DETAILS</div>
                            <h2 class="text-primary text-xl font-bold tracking-tight m-0">{{ locationForm.id ? 'Edit Location Details' : 'Add New Location' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-muted hover:!text-primary !bg-transparent !border-none !w-10 !h-10 hover:!bg-panel transition-colors" @click="dialogVisible = false" />
                    </div>

                    <!-- Body -->
                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.03),transparent_40%)] max-h-[70vh] overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-12 gap-x-6 gap-y-6">
                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Location Name *</label>
                                <InputText v-model="locationForm.name" placeholder="E.g. Main Warehouse" 
                                           class="!bg-panel/50 !border-panel-border !text-primary !h-12 !font-bold focus:!border-sky-500/40"
                                           :class="{'!border-red-500/50': submitted && !locationForm.name}" />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Location Code *</label>
                                <InputText v-model="locationForm.code" placeholder="L-000" class="!bg-panel/50 !border-panel-border !text-sky-400 !h-12 !font-mono focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Location Type *</label>
                                <Select v-model="locationForm.location_type_id" :options="locationTypes" optionLabel="name" optionValue="id" placeholder="Select Type" 
                                        class="!bg-panel/50 !border-panel-border !text-primary !h-12 focus:!border-sky-500/30" 
                                        :pt="{ 
                                            root: { class: '!bg-panel/50 !border-panel-border' },
                                            overlay: { class: '!bg-deep !border-panel-border !shadow-2xl' },
                                            item: { class: '!text-secondary hover:!bg-sky-500/10 hover:!text-primary !text-xs !font-bold !py-3' }
                                        }" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Parent Location</label>
                                <Select v-model="locationForm.parent_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="None" showClear
                                        class="!bg-panel/50 !border-panel-border !text-primary !h-12 focus:!border-sky-500/30" 
                                        :pt="{ 
                                            root: { class: '!bg-panel/50 !border-panel-border' },
                                            overlay: { class: '!bg-deep !border-panel-border !shadow-2xl' },
                                            item: { class: '!text-secondary hover:!bg-sky-500/10 hover:!text-primary !text-xs !font-bold !py-3' }
                                        }" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Default Receiving Location</label>
                                <Select v-model="locationForm.default_receive_location_id" :options="parentLocations.filter(l => l.id !== locationForm.id)" optionLabel="name" optionValue="id" placeholder="Select default inbound location" showClear
                                        class="!bg-panel/50 !border-panel-border !text-primary !h-12 focus:!border-sky-500/30" 
                                        :pt="{ 
                                            root: { class: '!bg-panel/50 !border-panel-border' },
                                            overlay: { class: '!bg-deep !border-panel-border !shadow-2xl' },
                                            item: { class: '!text-secondary hover:!bg-sky-500/10 hover:!text-primary !text-xs !font-bold !py-3' }
                                        }" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Street Address</label>
                                <InputText v-model="locationForm.address" placeholder="123 Storage Way" class="!bg-panel/50 !border-panel-border !text-primary !h-12 focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">City</label>
                                <InputText v-model="locationForm.city" placeholder="City Name" class="!bg-panel/50 !border-panel-border !text-primary !h-12 focus:!border-sky-500/30" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Country</label>
                                <InputText v-model="locationForm.country" placeholder="Country Name" class="!bg-panel/50 !border-panel-border !text-primary !h-12 focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Additional Notes</label>
                                <Textarea v-model="locationForm.description" rows="3" placeholder="Storage details, dock numbers, or access notes..." 
                                          class="!bg-panel/50 !border-panel-border !text-primary focus:!border-sky-500/30 !text-sm !font-mono" />
                            </div>

                            <div class="col-span-12 p-5 bg-panel/30 border border-panel-border/60 rounded-xl flex items-center justify-between mt-2">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-[11px] uppercase tracking-tight">Location Status</span>
                                    <span class="text-secondary text-[9px] font-mono uppercase mt-0.5">Status // {{ locationForm.is_active ? 'active' : 'inactive' }}</span>
                                </div>
                                <ToggleSwitch v-model="locationForm.is_active" 
                                             :pt="{
                                                 slider: ({ props }) => ({
                                                     class: props.modelValue ? '!bg-sky-500' : '!bg-panel-hover'
                                                 })
                                             }" />
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-6 border-t border-panel-border bg-panel/50 flex justify-end gap-3 items-center">
                        <span class="text-[9px] font-bold text-muted font-mono tracking-widest mr-auto uppercase">ID // {{ locationForm.id || 'PENDING' }}</span>
                        <Button label="CANCEL" class="!bg-transparent !border-panel-border !text-secondary hover:!text-primary hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="dialogVisible = false" />
                        <Button :label="locationForm.id ? 'SAVE CHANGES' : 'ADD LOCATION'" 
                                class="!bg-sky-500 !border-none !text-primary !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" 
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

