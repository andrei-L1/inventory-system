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
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ totalQty.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 8 }) }}</div>
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
                                      dataKey="id"
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
                                      dataKey="id"
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
                            <div class="flex flex-col gap-3">
                                <div v-for="(line, index) in form.lines" :key="index" class="p-4 bg-zinc-900/20 border border-zinc-800/40 rounded-2xl flex flex-col gap-4 relative group transition-all hover:border-violet-500/20 hover:bg-zinc-900/40">
                                    <div class="grid grid-cols-12 gap-4 items-center">
                                        <!-- Product Selection (Col 5) -->
                                        <div class="col-span-12 lg:col-span-5 flex flex-col gap-1">
                                            <span class="text-[9px] font-black text-zinc-700 tracking-widest uppercase font-mono pl-1">Product to Move</span>
                                            <Select 
                                                v-model="line.product" 
                                                :options="products" 
                                                optionLabel="name" 
                                                placeholder="Search products..." 
                                                filter 
                                                @change="onProductSelect(line)"
                                                class="!w-full !bg-zinc-950/80 !border-zinc-800 !h-10 !rounded-xl !text-xs !flex !items-center focus-within:!border-violet-500/30"
                                            />
                                        </div>

                                        <!-- Unit Selection (Col 2) -->
                                        <div class="col-span-6 lg:col-span-2 flex flex-col gap-1">
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">UOM</label>
                                                <Select 
                                                    v-model="line.uom_id" 
                                                    :options="getAvailableUoms(line.product?.id)" 
                                                    optionLabel="abbreviation" 
                                                    optionValue="id" 
                                                    placeholder="UOM" 
                                                    dataKey="id"
                                                    @change="onUomChange(line)"
                                                    class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-violet-500/50"
                                                >
                                                    <template #value="slotProps">
                                                        <div v-if="slotProps.value" class="flex items-center gap-2">
                                                            <span class="font-bold text-[11px] uppercase">{{ getUomAbbr(slotProps.value) }}</span>
                                                            <span 
                                                                v-if="getConversionDetails(slotProps.value, line.product?.id)" 
                                                                class="text-[9px] text-zinc-600 font-mono font-bold tracking-widest hidden 2xl:block uppercase"
                                                            >
                                                                {{ getConversionDetails(slotProps.value, line.product?.id).text }}
                                                            </span>
                                                        </div>
                                                        <span v-else>{{ slotProps.placeholder }}</span>
                                                    </template>
                                                    <template #option="slotProps">
                                                        <div class="flex flex-col">
                                                            <div class="flex items-center gap-2">
                                                                <span class="font-bold text-[11px] uppercase">{{ slotProps.option.abbreviation }}</span>
                                                                <span 
                                                                    v-if="getConversionDetails(slotProps.option.id, line.product?.id)?.isCustom" 
                                                                    class="px-1.5 py-[1px] bg-rose-500/20 text-rose-400 text-[8px] font-mono rounded tracking-widest border border-rose-500/30 uppercase"
                                                                >
                                                                    Custom
                                                                </span>
                                                            </div>
                                                            <span 
                                                                v-if="getConversionDetails(slotProps.option.id, line.product?.id)" 
                                                                class="text-[9px] text-zinc-500 font-mono font-bold mt-0.5 tracking-widest"
                                                            >
                                                                {{ getConversionDetails(slotProps.option.id, line.product?.id).text }}
                                                            </span>
                                                        </div>
                                                    </template>
                                                </Select>
                                            </div>
                                        </div>

                                        <!-- Quantity Input (Col 4 - Unified Bar) -->
                                        <div class="col-span-6 lg:col-span-4 flex flex-col gap-1">
                                            <span class="text-[9px] font-black text-zinc-700 tracking-widest uppercase font-mono pl-1">Quantity</span>
                                            <div class="flex items-center bg-zinc-950/80 border border-zinc-800 rounded-xl focus-within:border-violet-500/50 transition-all overflow-hidden h-10 group/input"
                                                 :class="{'border-red-500/30 bg-red-500/5': isInsufficient(line)}">
                                                <InputNumber 
                                                    v-model="line.quantity" 
                                                    class="w-full h-full"
                                                    :min="0" 
                                                    :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 8" 
                                                    :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#fff', width: '100%', fontWeight: '900', fontSize: '14px', fontFamily: 'monospace' }"
                                                    placeholder="0"
                                                />
                                            </div>
                                        </div>

                                        <!-- Actions (Col 1) -->
                                        <div class="col-span-12 lg:col-span-1 flex items-end justify-center h-10 mt-5 lg:mt-0">
                                            <button 
                                                @click="removeLine(index)" 
                                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-red-500/5 text-red-500/20 hover:text-red-400 hover:bg-red-500/10 transition-all border border-red-500/0 hover:border-red-500/10"
                                            >
                                                <i class="pi pi-trash text-[10px]" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Context Indicator Bar -->
                                    <div v-if="line.product" class="grid grid-cols-1 container border-t border-zinc-800/40 pt-3 mt-1">
                                        <div class="flex items-center gap-6">
                                            <!-- Availability Stat (CLICKABLE) -->
                                            <div 
                                                @click="(e) => toggleStockInfo(e, line)"
                                                class="flex items-center gap-2 px-3 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg cursor-pointer hover:border-violet-500/50 transition-all group/stat"
                                            >
                                                <div class="flex flex-col text-left">
                                                    <span class="text-[7px] font-bold text-zinc-500 uppercase tracking-widest font-mono group-hover/stat:text-violet-500 line-clamp-1">At Source</span>
                                                    <span class="text-[10px] font-black font-mono text-zinc-200">
                                                        {{ getScaledQty(line, getLocalStock(line)) }}
                                                    </span>
                                                </div>
                                                <div class="w-px h-4 bg-zinc-800 mx-1"></div>
                                                <div class="flex flex-col text-left">
                                                    <span class="text-[7px] font-bold text-zinc-500 uppercase tracking-widest font-mono group-hover/stat:text-violet-500 line-clamp-1">Global Pool</span>
                                                    <span class="text-[10px] font-black text-zinc-400 font-mono">
                                                        {{ getScaledQty(line, line.product.total_qoh) }}
                                                    </span>
                                                </div>
                                                <i class="pi pi-chevron-down text-[8px] text-zinc-600 group-hover/stat:text-violet-500"></i>
                                            </div>

                                            <!-- Routing Visual -->
                                            <div class="flex items-center gap-3 px-4 py-2 bg-zinc-950/40 rounded-lg border border-zinc-800/50">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] font-black text-zinc-600 uppercase tracking-tighter">{{ form.from_location?.name || 'SRC' }}</span>
                                                    <i class="pi pi-arrow-right text-[8px] text-zinc-700"></i>
                                                    <span class="text-[9px] font-black text-white uppercase tracking-tighter">{{ form.to_location?.name || 'DEST' }}</span>
                                                </div>
                                            </div>
                                        </div>
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

        <!-- Location Breakdown Popover -->
        <Popover ref="stockOp" class="!bg-zinc-950 !border-zinc-800 !shadow-2xl !p-0 overflow-hidden">
            <div v-if="selectedLineForStock" class="w-72 p-4 text-white text-left">
                <div class="text-[9px] font-black text-violet-500 uppercase tracking-[0.2em] mb-3 border-b border-zinc-900 pb-2 flex justify-between items-center">
                    <span>Stock Availability</span>
                    <span class="bg-zinc-900 px-2 py-0.5 rounded text-zinc-500">{{ getUomAbbr(selectedLineForStock.uom_id) }}</span>
                </div>
                
                <div class="space-y-1 max-h-56 overflow-y-auto custom-scrollbar">
                    <div v-for="loc in selectedLineForStock.inventories" 
                         :key="loc.location_id" 
                         @click="selectLocation(loc)"
                         class="group flex justify-between items-center px-2 py-2 rounded-lg border border-transparent hover:border-violet-500/20 hover:bg-violet-500/5 transition-all cursor-pointer">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold uppercase tracking-tight"
                                  :class="[
                                      loc.location_id === form.from_location?.id ? 'text-violet-400' : 
                                      loc.location_id === form.to_location?.id ? 'text-sky-400' : 'text-zinc-400 group-hover:text-zinc-200'
                                  ]">
                                {{ loc.location_name }}
                            </span>
                            <span class="text-[7px] font-black text-zinc-700 uppercase" v-if="loc.location_id === form.from_location?.id">Current Source</span>
                            <span class="text-[7px] font-black text-zinc-700 uppercase" v-else-if="loc.location_id === form.to_location?.id">Current Target</span>
                        </div>
                        <span class="font-mono text-[10px] font-bold text-zinc-500 group-hover:text-white">
                            {{ getScaledQty(selectedLineForStock, loc.quantity_on_hand) }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-zinc-900">
                    <p class="text-[8px] text-zinc-600 font-bold uppercase italic leading-tight">
                        <i class="pi pi-info-circle text-[7px] mr-1"></i>
                        Click a location to set as Source or Target warehouse.
                    </p>
                </div>
            </div>
        </Popover>
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
import Popover from 'primevue/popover';

const toast = useToast();
const { props } = usePage();

const locations = ref([]);
const products = ref([]);
const uoms = ref([]);
const uomConversions = ref([]);
const loadingData = ref(false);
const stockOp = ref(null);
const selectedLineForStock = ref(null);

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.category === 'count' : true;
};

const getFactorToBase = (uomId, productId = null) => {
    let factor = 1.0;
    let current = uomId;
    let processed = [current];
    while (true) {
        let rule = null;
        if (productId) {
            rule = uomConversions.value.find(c => c.from_uom_id === current && c.product_id === productId);
        }
        if (!rule) {
            rule = uomConversions.value.find(c => c.from_uom_id === current && c.product_id === null);
        }
        if (!rule || processed.includes(rule.to_uom_id)) break;
        factor *= Number(rule.conversion_factor);
        current = rule.to_uom_id;
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
            c.from_uom_id === u.id && 
            (c.product_id === null || c.product_id === product.id)
        );
    });
};

const toggleStockInfo = async (event, line) => {
    if (!line.product) return;
    
    if (!line.inventories || line.inventories.length === 0) {
        await fetchProductInventory(line);
    }
    
    selectedLineForStock.value = line;
    stockOp.value.toggle(event);
};

const selectLocation = (loc) => {
    // If from_location is same as clicked, do nothing
    if (form.from_location?.id === loc.location_id) return;

    // Smart Routing:
    // 1. If to_location is same as clicked, clear it and set as from
    if (form.to_location?.id === loc.location_id) {
        form.to_location = null;
    }
    
    // 2. Set as from_location if it's empty or we want to change it
    if (!form.from_location || form.from_location.id !== loc.location_id) {
        form.from_location = { id: loc.location_id, name: loc.location_name };
        toast.add({ severity: 'info', summary: 'Source Selected', detail: `Moving items FROM ${loc.location_name}`, life: 2000 });
        return;
    }

    // 3. Otherwise set as to_location
    form.to_location = { id: loc.location_id, name: loc.location_name };
    toast.add({ severity: 'info', summary: 'Target Selected', detail: `Moving items TO ${loc.location_name}`, life: 2000 });
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

const getConversionDetails = (uomId, productId) => {
    if (!productId || !uomId) return null;
    const baseAuth = getFactorToBase(uomId, productId);
    if (baseAuth.factor === 1) return null;

    const directRule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(uomId) && c.product_id === productId);
    const baseUom = uoms.value.find(u => u.id === baseAuth.baseId);
    const baseAbbr = baseUom ? baseUom.abbreviation : '';

    return {
        text: `= ${baseAuth.factor} ${baseAbbr}`,
        isCustom: !!directRule
    };
};

const getScaledQty = (line, rawPieces) => {
    if (!line.product || rawPieces === undefined || rawPieces === null) return '0';
    const { factor } = getFactorToBase(line.uom_id, line.product?.id);
    const scaled = (Number(rawPieces) / factor);
    
    return isUomIdDiscrete(line.uom_id) 
        ? Math.floor(scaled + 0.0001).toLocaleString() 
        : scaled.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
};

const getLocalStock = (line) => {
    if (!line.inventories || !form.from_location) return 0;
    const inv = line.inventories.find(i => i.location_id === form.from_location.id);
    return Number(inv?.quantity_on_hand) || 0;
};

const getUomAbbr = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.abbreviation : '';
};

const isInsufficient = (line) => {
    if (!line.product || !form.from_location) return false;
    let qtyInBase = Number(line.quantity) || 0;
    const targetInfo = getFactorToBase(line.uom_id, line.product?.id);
    const productBaseInfo = getFactorToBase(line.product.uom_id, line.product?.id);

    if (targetInfo.baseId === productBaseInfo.baseId) {
        const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
        qtyInBase = (Number(line.quantity) || 0) * effectiveFactor;
    }
    
    return qtyInBase > getLocalStock(line);
};

const onUomChange = (line) => {
    // Optional scaling logic if needed
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

        let qtyInBase = Number(line.quantity) || 0;
        
        const targetInfo = getFactorToBase(line.uom_id, line.product?.id);
        const productBaseInfo = getFactorToBase(line.product.uom_id, line.product?.id);

        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            qtyInBase = (Number(line.quantity) || 0) * effectiveFactor;
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
                quantity: Number(line.quantity),
                unit_cost: Number(line.product?.average_cost || 0)
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

const totalQty = computed(() => form.lines.reduce((s, l) => s + (Number(l.quantity) || 0), 0));

onMounted(() => {
    loadData();
});
</script>
