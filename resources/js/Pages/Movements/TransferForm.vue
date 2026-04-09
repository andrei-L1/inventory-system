<template>
    <AppLayout>
        <Head title="Inventory Transfer" />
        <Toast />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-violet-400 uppercase tracking-[0.2em] block mb-2 font-mono">Internal Movement</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Move Items</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Move stock between warehouses or locations. Both locations will be updated simultaneously upon posting.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button @click="submitForm" :disabled="isSubmitting" class="!bg-violet-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-violet-500/10 hover:!bg-violet-400 active:scale-95 transition-all rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isSubmitting ? 'PROCESSING...' : 'TRANSFER ITEMS' }}
                    </button>
                </div>
            </div>

            <div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 mt-2">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_LINES</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ form.lines.length.toString().padStart(2, '0') }}</div>
                    </div>
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">SUM_TRANSFER</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ totalQty.toFixed(2) }}</div>
                    </div>
                    <div class="bg-zinc-900/30 border border-zinc-800/50 rounded-2xl p-6 border-l-4 border-l-violet-500 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-violet-500/80 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left italic">SOURCE ROUTE</span>
                        <div class="text-[11px] font-bold text-zinc-300 uppercase truncate text-center lg:text-left tracking-tight">{{ form.from_location?.name || 'NOT_SELECTED' }}</div>
                    </div>
                </div>

                <!-- Transfer Route Map -->
                <div class="mb-10 p-10 rounded-3xl bg-zinc-900/20 border border-zinc-900 flex items-center justify-between gap-12 relative overflow-hidden group shadow-2xl backdrop-blur-sm">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(139,92,246,0.05),transparent)] pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    
                    <!-- Source Location -->
                    <div class="z-10 flex flex-col items-start gap-4 flex-1">
                        <div class="w-16 h-16 rounded-2xl bg-zinc-950 border-2 border-zinc-900 flex items-center justify-center shadow-2xl relative transition-all group-hover:border-zinc-700">
                            <i class="pi pi-home text-zinc-700 text-xl" />
                        </div>
                        <div class="flex flex-col items-start">
                            <span class="text-[9px] font-black text-zinc-600 uppercase tracking-[0.2em] font-mono mb-1 leading-none">Source Location</span>
                            <span class="text-lg font-bold text-zinc-400 uppercase tracking-tight">{{ form.from_location?.name || 'SELECT SOURCE' }}</span>
                        </div>
                    </div>

                    <!-- Transfer Path -->
                    <div class="flex-[2] h-px bg-zinc-800 relative z-10 max-w-[600px] mx-10">
                        <div class="absolute -top-5 left-1/2 -translate-x-1/2 flex flex-col items-center">
                             <div class="px-6 py-2 rounded-full bg-zinc-950 border border-zinc-800 flex items-center gap-3 shadow-lg group-hover:border-violet-500/30 transition-all">
                                 <div class="w-2 h-2 rounded-full bg-violet-500 animate-pulse"></div>
                                 <span class="text-[9px] font-black text-violet-400 uppercase tracking-[0.4em] font-mono leading-none">READY TO MOVE</span>
                             </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
                    </div>

                    <!-- Destination Location -->
                    <div class="z-10 flex flex-col items-end gap-4 flex-1 text-right">
                        <div class="w-16 h-16 rounded-2xl bg-zinc-950 border-2 border-violet-500/20 flex items-center justify-center shadow-[0_0_40px_rgba(139,92,246,0.1)] relative transition-all group-hover:border-violet-500/40">
                            <i class="pi pi-map-marker text-violet-400 text-xl" />
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-[9px] font-black text-zinc-600 uppercase tracking-[0.2em] font-mono mb-1 leading-none">Destination</span>
                            <span class="text-lg font-bold text-violet-300 uppercase tracking-tight">{{ form.to_location?.name || 'SELECT TARGET' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-violet-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Transfer Details</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">From Location</label>
                                 <Select 
                                      v-model="form.from_location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="From..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">To Location</label>
                                 <Select 
                                      v-model="form.to_location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="To..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reference # <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <InputText 
                                      v-model="form.reference_number" 
                                      placeholder="Leave blank to auto-generate" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-[10px] !font-mono text-white placeholder:!text-zinc-800"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Notes <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <textarea v-model="form.notes" placeholder="Optional transfer notes..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-24 resize-none outline-none focus:border-violet-500/30 transition-all"></textarea>
                             </div>

                             <div class="p-5 bg-violet-500/5 border border-violet-500/10 rounded-xl">
                                 <p class="text-[9px] text-zinc-600 leading-relaxed uppercase tracking-wider font-bold">Both locations will be updated instantly upon posting.</p>
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center sticky top-0 z-20 backdrop-blur-md">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-violet-400"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Move</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-violet-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-violet-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
                            <div class="flex flex-col gap-4">
                                <div v-for="(line, index) in form.lines" :key="index" class="p-6 bg-zinc-950/40 border border-zinc-800/80 rounded-2xl flex flex-col gap-5 relative group transition-all hover:border-zinc-700/50 hover:shadow-2xl hover:bg-zinc-950/60">
                                    <div class="grid grid-cols-12 gap-5 items-end">
                                        <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                            <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Product to Move</label>
                                            <Select 
                                                v-model="line.product" 
                                                :options="products" 
                                                optionLabel="name" 
                                                placeholder="Search products..." 
                                                filter 
                                                @change="onProductSelect(line)"
                                                class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-violet-500/50"
                                            />
                                        </div>

                                        <div class="col-span-6 md:col-span-3 flex flex-col gap-2">
                                            <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Unit</label>
                                            <Select 
                                                v-model="line.uom_id" 
                                                :options="getAvailableUoms(line.product?.id)" 
                                                optionLabel="abbreviation" 
                                                optionValue="id"
                                                placeholder="UOM" 
                                                class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-violet-500/50"
                                            />
                                        </div>

                                        <div class="col-span-6 md:col-span-2 flex flex-col gap-2">
                                            <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Quantity</label>
                                            <InputNumber 
                                                v-model="line.quantity" 
                                                :min="0" 
                                                :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 4" 
                                                :inputClass="'w-full bg-zinc-950 border text-center text-white p-2 rounded-lg outline-none ' + (isInsufficient(line) ? 'border-red-500/60 focus:border-red-500' : 'border-zinc-800 focus:border-violet-500/50')"
                                            />
                                        </div>

                                        <div class="col-span-12 md:col-span-1 flex items-center justify-end">
                                            <button 
                                                @click="removeLine(index)" 
                                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-red-500/10 text-red-500/40 hover:text-red-400 hover:bg-red-500/20 transition-all border border-red-500/0 hover:border-red-500/20"
                                                title="Remove Line"
                                            >
                                                <i class="pi pi-trash text-xs" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Always-visible location breakdown -->
                                    <div v-if="line.product" class="rounded-xl border border-zinc-800 bg-zinc-900/20 overflow-hidden">
                                        <div class="flex items-center justify-between px-4 py-2 bg-zinc-900/40 border-b border-zinc-800">
                                            <span class="text-[9px] font-bold text-violet-400 uppercase tracking-[0.2em] font-mono">Location Breakdown</span>
                                            <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-[0.2em] font-mono whitespace-nowrap ml-4">Qty on Hand</span>
                                        </div>
                                        <div v-if="line.inventories && line.inventories.length" class="divide-y divide-zinc-800/40 max-h-48 overflow-y-auto custom-scrollbar">
                                            <div 
                                                v-for="inv in line.inventories" 
                                                :key="inv.id" 
                                                class="flex items-center justify-between px-4 py-2 transition-colors"
                                                :class="inv.location_id === form.from_location?.id ? 'bg-violet-500/10' : 'hover:bg-white/[0.01]'"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="w-1 h-1 rounded-full" :class="inv.location_id === form.from_location?.id ? 'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.5)]' : 'bg-zinc-700'"></div>
                                                    <span 
                                                        class="text-[11px] font-bold uppercase tracking-wide font-mono"
                                                        :class="inv.location_id === form.from_location?.id ? 'text-violet-300' : 'text-zinc-500'"
                                                    >{{ inv.location_name }}</span>
                                                </div>
                                                <span 
                                                    class="text-[11px] font-black font-mono"
                                                    :class="inv.location_id === form.from_location?.id ? 'text-violet-400' : 'text-zinc-500'"
                                                >{{ getScaledQty(line, inv.quantity_on_hand) }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="px-4 py-4 text-center text-[10px] text-zinc-600 italic font-mono bg-zinc-950/10">
                                            No stock records found for this product
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-2 bg-zinc-900/60 border-t border-zinc-800">
                                            <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-[0.15em] font-mono italic">Total Global Stock</span>
                                            <span class="text-[10px] font-black text-white font-mono px-2 py-0.5 bg-zinc-800 rounded border border-zinc-700/50 shadow-inner">
                                                {{ getScaledQty(line, line.product?.total_qoh) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Post-Transfer Summary (Transfer-specific) -->
                                    <div v-if="line.product && form.from_location && form.to_location" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="flex items-center justify-between bg-zinc-900/60 p-3 rounded-xl border border-zinc-800 text-[10px]">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-zinc-600 font-bold uppercase tracking-widest leading-none mb-1">Source Impact</span>
                                                <span class="text-zinc-400 uppercase font-mono truncate max-w-[150px]">{{ form.from_location.name }}</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-zinc-500 font-mono">{{ getScaledQty(line, getLocalStock(line)) }}</span>
                                                <i class="pi pi-arrow-right text-[8px] text-zinc-800"></i>
                                                <span class="font-black font-mono" :class="isInsufficient(line) ? 'text-rose-500' : 'text-violet-400'">
                                                    {{ (parseFloat(getScaledQty(line, getLocalStock(line))) - (parseFloat(line.quantity) || 0)).toFixed(2) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between bg-emerald-500/5 p-3 rounded-xl border border-emerald-500/10 text-[10px]">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-emerald-500/60 font-bold uppercase tracking-widest leading-none mb-1">Target Gain</span>
                                                <span class="text-emerald-400/80 uppercase font-mono truncate max-w-[150px]">{{ form.to_location.name }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-black text-emerald-400 font-mono">
                                                    +{{ line.quantity || 0 }}
                                                </span>
                                                <span class="text-[9px] text-emerald-500/40 font-bold uppercase">{{ getUomAbbr(line.uom_id) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="isInsufficient(line)" class="text-[8px] text-rose-500 font-bold uppercase animate-pulse absolute top-4 right-16">
                                        Insufficient stock at source!
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="form.lines.length === 0" class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                <i class="pi pi-arrow-right-arrow-left text-5xl mb-4" />
                                <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No items added to transfer</p>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const { props } = usePage();

const locations = ref([]);
const products = ref([]);
const uoms = ref([]);
const uomConversions = ref([]);
const loadingData = ref(false);

const continuousUnits = ['KG', 'L', 'M', 'ML', 'G', 'LB', 'OZ', 'CM', 'MM', 'FT', 'IN', 'GRAM', 'KILOGRAM', 'LITER'];

const isDiscrete = (abbr) => {
    return !continuousUnits.includes(abbr?.toUpperCase());
};

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? isDiscrete(uom.abbreviation) : true;
};

const getFactorToBase = (uomId, productId = null) => {
    if (!uomId) return { factor: 1, baseId: null };
    let factor = 1.0;
    let current = Number(uomId);
    let processed = [current];
    while (true) {
        let rule = null;
        if (productId) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === current && c.product_id === productId);
        }
        if (!rule) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === current && c.product_id === null);
        }
        if (!rule || processed.includes(Number(rule.to_uom_id))) break;
        factor *= Number(rule.conversion_factor);
        current = Number(rule.to_uom_id);
        processed.push(current);
    }
    return { factor, baseId: current };
};

const getAvailableUoms = (productId) => {
    if (!productId) return [];
    const product = products.value.find(p => p.id === productId);
    if (!product || !product.uom_id) return uoms.value;

    const currentUom = uoms.value.find(u => u.id === product.uom_id);
    if (!currentUom) return [];

    return uoms.value.filter(u => {
        if (u.category !== currentUom.category) return false;
        if (u.is_base) return true;
        if (u.category !== 'count') return true;
        return uomConversions.value.some(c => 
            Number(c.from_uom_id) === u.id && 
            (c.product_id === null || c.product_id === product.id)
        );
    });
};

const loadData = async () => {
    loadingData.value = true;
    try {
        const [prodRes, locRes, uomRes, convRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/locations'),
            axios.get('/api/uom'),
            axios.get('/api/uom-conversions')
        ]);
        
        products.value = prodRes.data.data;
        locations.value = locRes.data.data;
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
        
        const { url } = usePage();
        const searchParams = new URLSearchParams(new URL(url, window.location.origin).search);
        const productId = searchParams.get('product_id');
        
        if (productId && products.value.length > 0) {
            const preselected = products.value.find(p => p.id == productId);
            if (preselected) {
                const line = { product: preselected, uom_id: preselected.uom_id, quantity: 1, inventories: [] };
                form.lines.push(line);
                fetchProductInventory(line);
            }
        }
    } catch (e) {
        console.error('Failed to load data', e);
    } finally {
        loadingData.value = false;
    }
};

onMounted(() => {
    loadData();
});

const form = useForm({
    from_location: null,
    to_location: null,
    reference_number: '',
    notes: '',
    lines: []
});

const isSubmitting = ref(false);

const fetchProductInventory = async (line) => {
    if (!line.product) return;
    try {
        const res = await axios.get(`/api/inventory/${line.product.id}/locations`);
        line.inventories = res.data.data;
    } catch (e) {
        console.error('Failed to fetch inventories', e);
    }
};

const getScaledQty = (line, rawPieces) => {
    if (!line.product || rawPieces === undefined || rawPieces === null) return '0';
    const factor = getFactorToBase(line.uom_id, line.product?.id).factor;
    const scaled = (parseFloat(rawPieces) / factor);
    return isUomIdDiscrete(line.uom_id) ? Math.floor(scaled + 0.0001).toString() : scaled.toFixed(2);
};

const getLocalStock = (line) => {
    if (!line.inventories || !form.from_location) return 0;
    const inv = line.inventories.find(i => i.location_id === form.from_location.id);
    return inv ? inv.quantity_on_hand : 0;
};

const getUomAbbr = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.abbreviation : '';
};

const isInsufficient = (line) => {
    if (!line.product || !form.from_location) return false;
    let qtyInBase = parseFloat(line.quantity) || 0;
    const targetInfo = getFactorToBase(line.uom_id, line.product?.id);
    const productBaseInfo = getFactorToBase(line.product.uom_id, line.product?.id);

    if (targetInfo.baseId === productBaseInfo.baseId) {
        const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
        qtyInBase = (parseFloat(line.quantity) || 0) * effectiveFactor;
    }
    
    return qtyInBase > getLocalStock(line);
};

const submitForm = async () => {
    if (!form.from_location || !form.to_location) {
        toast.add({ severity: 'warn', summary: 'Missing Info', detail: 'Please select both source and destination locations.', life: 4000 });
        return;
    }
    
    isSubmitting.value = true;
    
    // Frontend validation for real-time stock checks
    for (const line of form.lines) {
        if (!line.product) continue;

        let qtyInBase = parseFloat(line.quantity) || 0;
        
        const targetInfo = getFactorToBase(line.uom_id, line.product?.id);
        const productBaseInfo = getFactorToBase(line.product.uom_id, line.product?.id);

        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            qtyInBase = (parseFloat(line.quantity) || 0) * effectiveFactor;
        } else {
            toast.add({ severity: 'error', summary: 'UOM Error', detail: `No conversion path from ${line.uom_id} to product base.`, life: 5000 });
            isSubmitting.value = false;
            return;
        }

        const availableQty = getLocalStock(line);
        if (qtyInBase > availableQty) {
            toast.add({ severity: 'warn', summary: 'Insufficient Stock', detail: `Cannot transfer equivalent of ${qtyInBase} ${line.product.uom?.abbreviation || 'pcs'} at ${form.from_location.name}. Available: ${availableQty}.`, life: 5000 });
            isSubmitting.value = false;
            return;
        }
    }
    
    try {
        const meta = props.transactionMeta;
        const payload = {
            header: {
                transaction_type_id: meta.types['transfer'],
                transaction_status_id: meta.statuses['posted'],
                transaction_date: new Date().toISOString().split('T')[0],
                reference_number: form.reference_number,
                notes: form.notes || 'Internal Transfer',
            },
            from_location_id: form.from_location?.id,
            to_location_id: form.to_location?.id,
            lines: form.lines.map(line => ({
                product_id: line.product?.id,
                uom_id: line.uom_id,
                quantity: parseFloat(line.quantity),
                unit_cost: parseFloat(line.product?.average_cost || 0)
            }))
        };
        
        await axios.post('/api/transfers', payload);
        toast.add({ severity: 'success', summary: 'Transfer Complete', detail: 'Items moved between locations successfully.', life: 3000 });
        setTimeout(() => router.visit('/inventory-center'), 1000);
    } catch (e) {
        console.error('Submission failed', e);
        toast.add({ severity: 'error', summary: 'Transfer Failed', detail: e.response?.data?.message || 'Failed to submit transfer.', life: 5000 });
    } finally {
        isSubmitting.value = false;
    }
};

const onProductSelect = (line) => {
    if (line.product) {
        line.uom_id = line.product.uom_id;
        fetchProductInventory(line);
    }
};

const addLine = () => {
    form.lines.push({ product: null, uom_id: null, quantity: 1, inventories: [] });
};

const removeLine = (index) => {
    form.lines.splice(index, 1);
};

const totalQty = computed(() => form.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0));
</script>
