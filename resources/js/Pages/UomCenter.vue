<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import Toast from 'primevue/toast';
import Select from 'primevue/select';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import axios from 'axios';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const uoms = ref([]);
const conversions = ref([]);
const loadingUoms = ref(false);
const loadingConversions = ref(false);
const categoryOptions = ref([
    { label: 'Count / Packaging', value: 'count' },
    { label: 'Weight / Mass', value: 'mass' },
    { label: 'Volume (Liquid)', value: 'volume' },
    { label: 'Length / Linear', value: 'length' }
]);

const getUomCategory = (id) => {
    const u = uoms.value.find(x => x.id === id);
    return u ? u.category : 'count';
};

const categoryIcons = {
    count: 'pi pi-box',
    mass: 'pi pi-gauge',
    volume: 'pi pi-tint',
    length: 'pi pi-arrows-h'
};

const groupedUoms = computed(() => {
    const groups = [];
    categoryOptions.value.forEach(cat => {
        const units = uoms.value.filter(u => u.category === cat.value);
        if (units.length > 0) {
            groups.push({
                key: cat.value,
                label: cat.label,
                icon: categoryIcons[cat.value] || 'pi pi-tag',
                units: units
            });
        }
    });
    return groups;
});

const isUnitAChild = (uomId) => {
    return conversions.value.some(c => c.to_uom_id === uomId);
};

// UOM Dialog
const uomDialogVisible = ref(false);
const uomSubmitted = ref(false);
const uomForm = ref({
    name: '',
    abbreviation: '',
    category: 'count',
    is_base: false,
    conversion_factor_to_base: null,
    decimals: 0,
    is_active: true
});

// Conversion Dialog
const convDialogVisible = ref(false);
const convSubmitted = ref(false);
const convForm = ref({
    id: null,
    from_uom_id: null,
    to_uom_id: null,
    conversion_factor: null
});

const loadInitialData = async () => {
    loadingUoms.value = true;
    loadingConversions.value = true;
    try {
        const [uomsRes, convsRes] = await Promise.all([
            axios.get('/api/uom'),
            axios.get('/api/uom-conversions')
        ]);
        uoms.value = uomsRes.data.data;
        conversions.value = convsRes.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingUoms.value = false;
        loadingConversions.value = false;
    }
};

onMounted(loadInitialData);

// Group conversions by their "from_uom_id" (the larger unit typically)
const getConversionsForUom = (uomId) => {
    return conversions.value.filter(c => c.from_uom_id === uomId);
};

// -- UOM CRUD --
const openNewUom = () => {
    uomForm.value = { id: null, name: '', abbreviation: '', category: 'count', is_base: false, conversion_factor_to_base: null, decimals: 0, is_active: true };
    uomSubmitted.value = false;
    uomDialogVisible.value = true;
};

const editUom = (uom) => {
    uomForm.value = { ...uom };
    uomSubmitted.value = false;
    uomDialogVisible.value = true;
};

const saveUom = async () => {
    uomSubmitted.value = true;
    if (!uomForm.value.name || !uomForm.value.abbreviation) {
        toast.add({ severity: 'warn', summary: 'Missing Information', detail: 'Unit Name and Short Name are required.', life: 4000 });
        return;
    }

    // Contract 6: multiplier must be > 0 if provided
    const factor = uomForm.value.conversion_factor_to_base;
    if (factor !== null && factor !== '' && (isNaN(Number(factor)) || Number(factor) <= 0)) {
        toast.add({ severity: 'error', summary: 'Invalid Multiplier', detail: 'Multiplier to Base Unit must be a positive number greater than zero.', life: 5000 });
        return;
    }

    try {
        if (uomForm.value.id) {
            await axios.put(`/api/uom/${uomForm.value.id}`, uomForm.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Unit updated successfully.', life: 3000 });
        } else {
            await axios.post('/api/uom', uomForm.value);
            toast.add({ severity: 'success', summary: 'Registered', detail: 'New unit added.', life: 3000 });
        }
        uomDialogVisible.value = false;
        loadInitialData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to save unit.', life: 3000 });
    }
};

const deleteUom = (uom) => {
    confirm.require({
        message: `Delete the ${uom.name} unit? All associated data must be cleared first.`,
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/uom/${uom.id}`);
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Unit removed.', life: 3000 });
                loadInitialData();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Cannot remove unit with active transactions.', life: 4000 });
            }
        }
    });
};

// -- Conversion CRUD --
const openNewConversion = (prefillFromUomId = null) => {
    convForm.value = { id: null, from_uom_id: prefillFromUomId, to_uom_id: null, conversion_factor: null };
    convSubmitted.value = false;
    convDialogVisible.value = true;
};

const saveConversion = async () => {
    convSubmitted.value = true;
    if (!convForm.value.from_uom_id || !convForm.value.to_uom_id || !convForm.value.conversion_factor) {
        toast.add({ severity: 'warn', summary: 'Missing Information', detail: 'Please select both units and enter the amount.', life: 4000 });
        return;
    }

    try {
        if (convForm.value.id) {
            await axios.put(`/api/uom-conversions/${convForm.value.id}`, convForm.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Conversion updated.', life: 3000 });
        } else {
            await axios.post('/api/uom-conversions', convForm.value);
            toast.add({ severity: 'success', summary: 'Mapped', detail: 'Conversion rule added.', life: 3000 });
        }
        convDialogVisible.value = false;
        loadInitialData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to save conversion.', life: 3000 });
    }
};

const deleteConversion = (id) => {
    confirm.require({
        message: 'Delete this conversion calculation?',
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/uom-conversions/${id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Conversion rule removed.', life: 3000 });
                loadInitialData();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to remove conversion.', life: 3000 });
            }
        }
    });
};

const getUomAbbr = (id) => {
    const u = uoms.value.find(x => x.id === id);
    return u ? u.abbreviation : '???';
};
</script>

<template>
    <AppLayout>
        <Head title="UOM Settings" />
        <Toast />

        <div class="p-8 bg-deep min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10 pb-8 border-b border-zinc-900 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-fuchsia-400 uppercase tracking-[0.2em] block mb-2 font-mono">Unit Management</span>
                    <h1 class="text-3xl font-bold text-primary tracking-tight m-0 mb-2">Unit Measurements & Conversions</h1>
                    <p class="text-secondary text-sm max-w-2xl leading-relaxed">Set up your standard units (like pieces, boxes, or pallets) and how they convert to each other.</p>
                </div>
                <div v-if="can('manage-products')" class="flex gap-4">
                    <Button label="ADD CONVERSION" icon="pi pi-link" 
                            class="!bg-panel !border-panel-border !text-zinc-300 !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest hover:!bg-panel-hover hover:!text-primary active:scale-95 transition-all" 
                            @click="openNewConversion(null)" />
                    <Button label="ADD NEW UNIT" icon="pi pi-plus" 
                            class="!bg-fuchsia-500 !border-none !text-primary !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-[0_0_20px_rgba(217,70,239,0.2)] hover:!bg-fuchsia-400 active:scale-95 transition-all" 
                            @click="openNewUom" />
                </div>
            </div>

            <!-- Unit Matrix Grid segmented by Category -->
            <div class="max-w-[1600px] w-full mx-auto flex-1 overflow-y-auto custom-scrollbar pb-20">
                <div v-if="loadingUoms" class="flex justify-center items-center py-32">
                    <i class="pi pi-spin pi-spinner text-fuchsia-400 text-4xl"></i>
                </div>
                
                <div v-else class="flex flex-col gap-16">
                    <!-- Category Section -->
                    <div v-for="group in groupedUoms" :key="group.key" class="animate-in fade-in slide-in-from-bottom-4 duration-700">
                        <!-- Category Header -->
                        <div class="flex items-center gap-4 mb-8 sticky top-0 bg-deep/80 backdrop-blur-md py-4 z-20 border-b border-zinc-900/50">
                            <div class="w-10 h-10 rounded-lg bg-panel border border-panel-border flex items-center justify-center text-fuchsia-400 shadow-lg">
                                <i :class="group.icon" class="text-lg"></i>
                            </div>
                            <div class="flex flex-col">
                                <h2 class="text-xl font-bold text-primary tracking-tight m-0 uppercase flex items-center gap-3">
                                    {{ group.label }}
                                    <span class="text-[10px] bg-panel text-secondary px-2 py-0.5 rounded border border-panel-border font-mono tracking-tighter">{{ group.units.length }} UNITS</span>
                                </h2>
                                <p class="text-[9px] text-muted font-mono uppercase tracking-widest mt-1">Consolidated Registry // System Measurement Tier</p>
                            </div>
                            <div class="flex-1 h-[1px] bg-gradient-to-r from-zinc-800 to-transparent ml-4"></div>
                        </div>

                        <!-- Grid of Units -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6">
                            <!-- UOM Cards -->
                            <div v-for="uom in group.units" :key="uom.id" 
                                 class="bg-panel/40 border rounded-2xl overflow-hidden flex flex-col transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 relative"
                                 :class="uom.is_active ? 'border-panel-border/80 shadow-[0_5px_30px_rgba(0,0,0,0.5)]' : 'border-zinc-900 opacity-60 grayscale'">
                                
                                <!-- Glow Accent -->
                                <div class="absolute top-0 right-0 w-32 h-32 bg-fuchsia-500/10 blur-[50px] -mr-16 -mt-16 rounded-full"></div>

                                <!-- Card Header -->
                                <div class="p-6 border-b border-panel-border/60 flex justify-between items-start bg-panel/60 relative z-10">
                                    <div class="flex flex-col gap-2">
                                        <div class="w-12 h-12 rounded-xl bg-deep border border-fuchsia-500/20 flex items-center justify-center shadow-inner">
                                            <span class="text-sm font-black text-fuchsia-400 font-mono tracking-tighter">{{ uom.abbreviation }}</span>
                                        </div>
                                        <h3 class="text-lg font-bold text-primary tracking-tight m-0 mt-2 flex items-center gap-2">
                                            {{ uom.name }}
                                            <span v-if="uom.is_base" class="text-[9px] bg-sky-500/20 text-sky-400 px-1.5 py-0.5 rounded font-black tracking-widest border border-sky-400/30">BASE UNIT</span>
                                        </h3>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[9px] text-muted font-mono ml-0.5">{{ uom.decimals }} Decimals Allowed</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="uom.is_active ? 'bg-emerald-500' : 'bg-zinc-600'"></span>
                                            <span class="text-[9px] font-bold tracking-widest uppercase font-mono" :class="uom.is_active ? 'text-secondary' : 'text-muted'">{{ uom.is_active ? 'ACTIVE' : 'DISABLED' }}</span>
                                        </div>
                                    </div>
                                    <div v-if="can('manage-products')" class="flex gap-2">
                                        <button @click="editUom(uom)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none outline-none text-secondary hover:text-primary hover:bg-panel-hover transition-colors cursor-pointer">
                                            <i class="pi pi-pencil text-xs"></i>
                                        </button>
                                        <button @click="deleteUom(uom)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none outline-none text-secondary hover:text-red-400 hover:bg-red-500/10 transition-colors cursor-pointer">
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Card Body (Conversions) -->
                                <div class="p-6 flex-1 flex flex-col bg-deep/30 relative z-10">
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="text-[9px] font-bold text-muted uppercase tracking-[0.2em] font-mono">Conversion Rules</span>
                                        <div class="flex flex-col items-end">
                                            <button 
                                                v-if="can('manage-products')" 
                                                @click="openNewConversion(uom.id)" 
                                                :disabled="isUnitAChild(uom.id)"
                                                class="text-[10px] bg-transparent border-none outline-none font-bold uppercase tracking-widest font-mono flex items-center gap-1 transition-colors"
                                                :class="isUnitAChild(uom.id) ? 'text-muted cursor-not-allowed' : 'text-sky-400 hover:text-sky-300 cursor-pointer'"
                                            >
                                                <i class="pi pi-plus text-[8px]"></i> Rule
                                            </button>
                                            <span v-if="isUnitAChild(uom.id)" class="text-[8px] font-bold text-amber-600/60 uppercase mt-1 text-right max-w-[120px] leading-tight">
                                                Nesting Restricted: This is already a child unit.
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <template v-if="getConversionsForUom(uom.id).length > 0">
                                            <div v-for="conv in getConversionsForUom(uom.id)" :key="conv.id" 
                                                 class="group flex flex-col gap-1.5 p-3 rounded-xl bg-deep border border-panel-border/80 hover:border-zinc-700 transition-colors">
                                                
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-xs font-black text-primary font-mono bg-panel px-2 py-1 rounded">1 {{ uom.abbreviation }}</span>
                                                        <span class="text-[10px] text-muted font-mono">=</span>
                                                        <span class="text-xs font-black text-fuchsia-400 font-mono">{{ conv.conversion_factor }} <span class="text-secondary font-bold ml-0.5">{{ getUomAbbr(conv.to_uom_id) }}</span></span>
                                                    </div>
                                                    <button v-if="can('manage-products')" @click="deleteConversion(conv.id)" class="opacity-0 group-hover:opacity-100 w-6 h-6 flex items-center justify-center rounded bg-transparent border-none outline-none text-red-500 hover:bg-red-500/20 transition-all cursor-pointer">
                                                        <i class="pi pi-times text-[10px]"></i>
                                                    </button>
                                                </div>

                                                <!-- Product Label if specific -->
                                                <div v-if="conv.product_id" class="flex items-center gap-1.5 mt-0.5">
                                                    <span class="text-[8px] bg-sky-500/10 text-sky-400 border border-sky-500/20 px-1.5 py-0.5 rounded-full font-black tracking-widest uppercase font-mono">Product Specific</span>
                                                    <span class="text-[9px] text-secondary font-bold font-mono truncate max-w-[150px]">{{ conv.product_sku || 'Unnamed' }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <div v-else class="py-6 flex flex-col items-center justify-center opacity-40 grayscale border border-dashed border-panel-border rounded-xl">
                                            <i class="pi pi-calculator text-lg text-muted mb-2"></i>
                                            <p class="text-[9px] font-mono uppercase tracking-widest text-secondary m-0">No conversions set</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Global Empty State -->
                    <div v-if="groupedUoms.length === 0 && !loadingUoms" class="py-48 text-center flex flex-col items-center justify-center opacity-20">
                        <i class="pi pi-database text-6xl mb-6"></i>
                        <p class="font-mono text-sm tracking-[0.3em] uppercase">No measurements registry found</p>
                    </div>
                </div>
            </div>

            <!-- UOM Config Dialog -->
            <Dialog 
                v-model:visible="uomDialogVisible" 
                :modal="true" 
                class="!bg-transparent !border-none !shadow-none ring-0 outline-none"
                :pt="{
                    root: { class: 'p-0 sm:m-4 max-w-lg w-full' },
                    content: { class: 'p-0 !bg-transparent' }
                }"
                :showHeader="false"
            >
                <div class="bg-deep border border-panel-border rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    <div class="px-8 py-6 border-b border-zinc-900 bg-panel/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <div class="text-[9px] font-bold text-fuchsia-500 font-mono tracking-[0.2em] mb-1">UNIT_DETAILS</div>
                            <h2 class="text-primary text-xl font-bold tracking-tight m-0">{{ uomForm.id ? 'Edit Unit Identifier' : 'Add New Unit' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-muted hover:!text-primary !bg-transparent !border-none !w-10 !h-10 hover:!bg-panel transition-colors" @click="uomDialogVisible = false" />
                    </div>

                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(217,70,239,0.03),transparent_40%)]">
                        <div class="grid grid-cols-12 gap-x-6 gap-y-6">
                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Unit Name *</label>
                                <InputText v-model="uomForm.name" placeholder="Piece, Box, Pallet" 
                                           class="!bg-panel/50 !border-panel-border !text-primary !h-12 !font-bold focus:!border-fuchsia-500/40"
                                           :class="{'!border-red-500/50': uomSubmitted && !uomForm.name}" />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Short Name / Symbol *</label>
                                <InputText v-model="uomForm.abbreviation" placeholder="pcs" class="!bg-panel/50 !border-panel-border !text-fuchsia-400 !h-12 !font-mono font-bold focus:!border-fuchsia-500/30 uppercase"
                                           :class="{'!border-red-500/50': uomSubmitted && !uomForm.abbreviation}" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Measurement Category</label>
                                <Select v-model="uomForm.category" :options="categoryOptions" optionLabel="label" optionValue="value" 
                                        class="!bg-panel/50 !border-panel-border !text-primary !h-12 !font-bold" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Allowed Decimals</label>
                                <InputText v-model="uomForm.decimals" type="number" min="0" max="6" 
                                           class="!bg-panel/50 !border-panel-border !text-primary !h-12 !font-bold"
                                           placeholder="e.g. 0 for Pcs, 3 for Kg" />
                            </div>

                            <!-- Contextual Physics / Packaging Multiplier Fields -->
                            <div class="col-span-12 flex flex-col gap-4 mt-2 p-4 bg-panel/30 border border-panel-border rounded-xl">
                                <div class="flex items-center justify-between" v-if="uomForm.category !== 'count'">
                                    <div class="flex flex-col">
                                        <span class="text-primary font-bold text-[11px] uppercase tracking-tight text-sky-400">Universal Base Unit</span>
                                        <span class="text-secondary text-[9px] font-mono uppercase mt-0.5">Is this the absolute smallest unit for {{ uomForm.category }}?</span>
                                    </div>
                                    <ToggleSwitch v-model="uomForm.is_base" 
                                                 :pt="{ slider: ({ props }) => ({ class: props.modelValue ? '!bg-sky-500' : '!bg-zinc-700' }) }" />
                                </div>

                                <div v-if="!uomForm.is_base" class="flex flex-col gap-2" :class="uomForm.category !== 'count' ? 'mt-2 pt-4 border-t border-panel-border/60' : ''">
                                    <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Multiplier to Base Unit</label>
                                    <div class="flex gap-2 items-center">
                                        <span class="text-xs text-secondary font-mono font-bold whitespace-nowrap">1 {{ uomForm.abbreviation || 'X' }} = </span>
                                        <InputText v-model="uomForm.conversion_factor_to_base" type="number" step="0.00001" min="0.00001"
                                                   class="!bg-deep !border-sky-500/30 !text-sky-400 !font-mono !font-bold !h-12 w-full focus:!border-sky-500/70"
                                                   placeholder="e.g. 1000" />
                                    </div>
                                    <p class="text-[9px] text-muted font-mono uppercase tracking-tight m-0 leading-relaxed">
                                        <i class="pi pi-info-circle mr-1 text-secondary"></i>
                                        <span v-if="uomForm.category === 'count'">Global default (e.g. 1 Box = 12 pcs). Product-specific rules defined in the Catalog will always override this.</span>
                                        <span v-else>How many base units equal 1 of this unit (e.g. 1 kg = 1000 g).</span>
                                    </p>
                                </div>
                            </div>

                            <div class="col-span-12 pt-2 flex items-center justify-between p-4 bg-panel/30 rounded-xl border border-panel-border/80">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-[11px] uppercase tracking-tight">Active Status</span>
                                    <span class="text-secondary text-[9px] font-mono uppercase mt-0.5">Turn on to allow using this unit</span>
                                </div>
                                <ToggleSwitch v-model="uomForm.is_active" 
                                             :pt="{ slider: ({ props }) => ({ class: props.modelValue ? '!bg-fuchsia-500' : '!bg-zinc-700' }) }" />
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-6 border-t border-zinc-900 bg-panel/50 flex justify-end gap-3">
                        <Button label="CANCEL" class="!bg-transparent !border-panel-border !text-secondary hover:!text-primary hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="uomDialogVisible = false" />
                        <Button label="SAVE UNIT" class="!bg-fuchsia-500 !border-none !text-primary !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-fuchsia-500/10 hover:!bg-fuchsia-400 active:scale-95 transition-all" @click="saveUom" />
                    </div>
                </div>
            </Dialog>

            <!-- Conversion Rule Dialog -->
            <Dialog 
                v-model:visible="convDialogVisible" 
                :modal="true" 
                class="!bg-transparent !border-none !shadow-none ring-0 outline-none"
                :pt="{
                    root: { class: 'p-0 sm:m-4 max-w-lg w-full' },
                    content: { class: 'p-0 !bg-transparent' }
                }"
                :showHeader="false"
            >
                <div class="bg-deep border border-panel-border rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    <div class="px-8 py-6 border-b border-zinc-900 bg-panel/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <div class="text-[9px] font-bold text-sky-500 font-mono tracking-[0.2em] mb-1">CONVERSION_RULE</div>
                            <h2 class="text-primary text-xl font-bold tracking-tight m-0">Unit Conversion Rule</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-muted hover:!text-primary !bg-transparent !border-none !w-10 !h-10 hover:!bg-panel transition-colors" @click="convDialogVisible = false" />
                    </div>

                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.03),transparent_40%)]">
                        
                        <div class="flex items-center gap-4 mb-2">
                            <div class="flex flex-col gap-2 w-32">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Base Amount</label>
                                <div class="h-12 bg-panel/80 border border-panel-border rounded-xl flex items-center justify-center">
                                    <span class="text-primary font-mono font-black text-lg">1</span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 flex-1">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">From Unit (e.g. Box)</label>
                                <Select v-model="convForm.from_uom_id" :options="uoms" optionLabel="abbreviation" optionValue="id" placeholder="Select"
                                        class="!bg-panel/50 !border-panel-border !text-primary w-full !h-12 !font-bold"
                                        :class="{'!border-red-500/50': convSubmitted && !convForm.from_uom_id}" />
                            </div>
                        </div>

                        <div class="flex justify-center my-4 py-2 border-y border-panel-border/40 relative">
                            <div class="absolute inset-y-0 flex items-center justify-center">
                                <div class="bg-deep px-3">
                                    <i class="pi pi-arrow-down text-sky-400 text-xs shadow-[0_0_10px_rgba(14,165,233,0.4)] rounded-full"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mt-2">
                            <div class="flex flex-col gap-2 w-32">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Amount</label>
                                <InputText v-model="convForm.conversion_factor" type="number" step="0.001" placeholder="e.g. 12"
                                        class="!bg-panel/50 !border-panel-border !text-fuchsia-400 !font-mono font-bold w-full !h-12 !text-lg text-center focus:!border-fuchsia-500/30"
                                        :class="{'!border-red-500/50': convSubmitted && !convForm.conversion_factor}" />
                            </div>
                            <div class="flex flex-col gap-2 flex-1">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">To Base Unit (Star Schema)</label>
                                <Select v-model="convForm.to_uom_id" 
                                        :options="uoms.filter(u => {
                                            const fromUom = uoms.find(x => x.id === convForm.from_uom_id);
                                            if (!fromUom) return u.is_base;
                                            
                                            // Must be a base unit of the SAME category to prevent infinite daisy-chains
                                            return u.is_base && u.category === fromUom.category && u.id !== convForm.from_uom_id;
                                        })" 
                                        optionLabel="abbreviation" optionValue="id" placeholder="Select Base"
                                        class="!bg-panel/50 !border-panel-border !text-primary w-full !h-12 !font-bold"
                                        :class="{'!border-red-500/50': convSubmitted && !convForm.to_uom_id}" />
                            </div>
                        </div>

                        <div v-if="convForm.from_uom_id && convForm.to_uom_id && convForm.conversion_factor" 
                             class="mt-8 p-4 bg-panel/30 border border-panel-border/80 rounded-xl flex items-center justify-center gap-3">
                            <span class="text-xs font-mono text-secondary">Preview:</span>
                            <div class="bg-deep px-3 py-1.5 rounded border border-panel-border font-mono text-xs font-black tracking-tight">
                                <span class="text-primary">1 {{ getUomAbbr(convForm.from_uom_id) }}</span>
                                <span class="text-sky-400 mx-2">=</span>
                                <span class="text-fuchsia-400">{{ convForm.conversion_factor }} {{ getUomAbbr(convForm.to_uom_id) }}</span>
                            </div>
                        </div>

                    </div>

                    <div class="px-8 py-6 border-t border-zinc-900 bg-panel/50 flex justify-end gap-3">
                        <Button label="CANCEL" class="!bg-transparent !border-panel-border !text-secondary hover:!text-primary hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="convDialogVisible = false" />
                        <Button label="SAVE CONVERSION" class="!bg-sky-500 !border-none !text-primary !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" @click="saveConversion" />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles applied via Tailwind directives */
</style>


