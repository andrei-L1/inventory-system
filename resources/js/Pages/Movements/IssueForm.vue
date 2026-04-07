<template>
    <AppLayout>
        <Head title="Stock Issue" />
        <Toast />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-rose-400 uppercase tracking-[0.2em] block mb-2 font-mono">Stock Issuance</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Issue Items</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Remove stock for sales, internal use, or disposal. Quantities will be deducted from your inventory records.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button @click="submitForm" :disabled="isSubmitting" class="!bg-rose-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-rose-500/10 hover:!bg-rose-400 active:scale-95 transition-all rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isSubmitting ? 'PROCESSING...' : 'ISSUE ITEMS' }}
                    </button>
                </div>
            </div>

            <div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_ITEMS</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ form.lines.length.toString().padStart(2, '0') }}</div>
                    </div>
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_PIECES</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ totalQty.toFixed(2) }}</div>
                    </div>
                    <div class="bg-zinc-900/30 border border-zinc-800/50 rounded-2xl p-6 border-l-4 border-l-rose-500 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-rose-500/80 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left italic">SOURCE LOCATION</span>
                        <div class="text-[11px] font-bold text-zinc-300 uppercase truncate text-center lg:text-left tracking-tight">{{ form.from_location?.name || 'NOT_SELECTED' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">General Info</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">From Location</label>
                                 <Select 
                                      v-model="form.from_location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="Select Warehouse" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reference / Invoice # <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <InputText 
                                      v-model="form.reference_number" 
                                      placeholder="Leave blank to auto-generate" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-xs !font-mono text-white placeholder:!text-zinc-800"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Notes <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <textarea v-model="form.notes" placeholder="Optional delivery notes..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-32 resize-none outline-none focus:border-rose-500/30 transition-all"></textarea>
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center sticky top-0 z-20 backdrop-blur-md">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Issue</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-rose-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-rose-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
                            <div class="flex flex-col gap-4">
                                <div v-for="(line, index) in form.lines" :key="index" class="p-6 bg-zinc-950/40 border border-zinc-800/80 rounded-2xl flex flex-col gap-5 relative group transition-all hover:border-zinc-700/50 hover:shadow-2xl hover:bg-zinc-950/60">
                                    <div class="grid grid-cols-12 gap-5 items-end">
                                        <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                            <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Product</label>
                                            <Select 
                                                v-model="line.product" 
                                                :options="products" 
                                                optionLabel="name" 
                                                placeholder="Search products..." 
                                                filter 
                                                @change="onProductSelect(line)"
                                                class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-rose-500/50"
                                            />
                                        </div>

                                        <div class="col-span-6 md:col-span-3 flex flex-col gap-2">
                                            <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Unit</label>
                                            <Select 
                                                v-model="line.uom_id" 
                                                :options="uoms" 
                                                optionLabel="abbreviation" 
                                                optionValue="id"
                                                placeholder="UOM" 
                                                class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-rose-500/50"
                                            />
                                        </div>

                                        <div class="col-span-6 md:col-span-2 flex flex-col gap-2">
                                            <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Qty to Issue</label>
                                            <InputNumber 
                                                v-model="line.quantity" 
                                                :min="0" 
                                                :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 4" 
                                                :inputClass="'w-full bg-zinc-950 border text-center text-white p-2 rounded-lg outline-none ' + (isInsufficient(line) ? 'border-red-500/60 focus:border-red-500' : 'border-zinc-800 focus:border-rose-500/50')"
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
                                            <span class="text-[9px] font-bold text-rose-400 uppercase tracking-[0.2em] font-mono">Location Availability</span>
                                            <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-[0.2em] font-mono whitespace-nowrap ml-4">Qty on Hand</span>
                                        </div>
                                        <div v-if="line.inventories && line.inventories.length" class="divide-y divide-zinc-800/40 max-h-48 overflow-y-auto custom-scrollbar">
                                            <div 
                                                v-for="inv in line.inventories" 
                                                :key="inv.id" 
                                                class="flex items-center justify-between px-4 py-2 transition-colors"
                                                :class="inv.location_id === form.from_location?.id ? 'bg-rose-500/10' : 'hover:bg-white/[0.01]'"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="w-1 h-1 rounded-full" :class="inv.location_id === form.from_location?.id ? 'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.5)]' : 'bg-zinc-700'"></div>
                                                    <span 
                                                        class="text-[11px] font-bold uppercase tracking-wide font-mono"
                                                        :class="inv.location_id === form.from_location?.id ? 'text-rose-300' : 'text-zinc-500'"
                                                    >{{ inv.location_name }}</span>
                                                </div>
                                                <span 
                                                    class="text-[11px] font-black font-mono"
                                                    :class="inv.location_id === form.from_location?.id ? 'text-rose-400' : 'text-zinc-500'"
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

                                    <div v-if="isInsufficient(line)" class="text-[8px] text-rose-500 font-bold uppercase animate-pulse absolute top-4 right-16">
                                        Shortage in selected location!
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.lines.length === 0" class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                <i class="pi pi-truck text-5xl mb-4" />
                                <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No items added to issue</p>
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

const getFactorToBase = (uomId) => {
    let factor = 1.0;
    let current = uomId;
    let processed = [current];
    while (true) {
        const rule = uomConversions.value.find(c => c.from_uom_id === current);
        if (!rule || processed.includes(rule.to_uom_id)) break;
        factor *= rule.conversion_factor;
        current = rule.to_uom_id;
        processed.push(current);
    }
    return { factor, baseId: current };
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
    reference_number: '',
    notes: '',
    lines: []
});

const isSubmitting = ref(false);

const submitForm = async () => {
    isSubmitting.value = true;
    
    // Frontend validation for real-time stock checks
    for (const line of form.lines) {
        if (!line.product) continue;
        
        let qtyInBase = parseFloat(line.quantity) || 0;
        
        const targetInfo = getFactorToBase(line.uom_id);
        const productBaseInfo = getFactorToBase(line.product.uom_id);

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
            toast.add({ severity: 'warn', summary: 'Insufficient Local Stock', detail: `Cannot issue equivalent of ${qtyInBase} ${line.product.uom?.abbreviation || 'pcs'} of ${line.product.name} from ${form.from_location?.name || 'selected location'}. Available in location: ${availableQty}.`, life: 5000 });
            isSubmitting.value = false;
            return;
        }
    }
    
    try {
        const meta = props.transactionMeta;
        const payload = {
            header: {
                transaction_type_id: meta.types['issue'],
                transaction_status_id: meta.statuses['posted'],
                transaction_date: new Date().toISOString().split('T')[0],
                reference_number: form.reference_number,
                from_location_id: form.from_location?.id,
                notes: form.notes,
            },
            lines: form.lines.map(line => ({
                product_id: line.product?.id,
                location_id: form.from_location?.id,
                uom_id: line.uom_id,
                quantity: parseFloat(line.quantity),
                unit_cost: parseFloat(line.product?.average_cost || 0)
            }))
        };
        
        await axios.post('/api/transactions', payload);
        toast.add({ severity: 'success', summary: 'Items Issued', detail: 'Stock deducted from inventory successfully.', life: 3000 });
        setTimeout(() => router.visit('/inventory-center'), 1000);
    } catch (e) {
        console.error('Submission failed', e);
        toast.add({ severity: 'error', summary: 'Submission Failed', detail: e.response?.data?.message || 'Failed to submit form.', life: 5000 });
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
    const factor = getFactorToBase(line.uom_id).factor;
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
    const targetInfo = getFactorToBase(line.uom_id);
    const productBaseInfo = getFactorToBase(line.product.uom_id);

    if (targetInfo.baseId === productBaseInfo.baseId) {
        const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
        qtyInBase = (parseFloat(line.quantity) || 0) * effectiveFactor;
    }
    
    return qtyInBase > getLocalStock(line);
};

const addLine = () => {
    form.lines.push({ product: null, uom_id: null, quantity: 0, inventories: [] });
};

const removeLine = (index) => {
    form.lines.splice(index, 1);
};

const totalQty = computed(() => form.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0));
</script>
