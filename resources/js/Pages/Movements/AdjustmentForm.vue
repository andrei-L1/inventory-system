<template>
    <AppLayout>
        <Head title="Inventory Adjustment" />
        <Toast />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-amber-400 uppercase tracking-[0.2em] block mb-2 font-mono">Stock Reconciliation</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Inventory Adjustment</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Manually update stock levels to match physical counts. Use this for breakage, shrinkage, or audit corrections.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button @click="postAdjustment" :disabled="isSubmitting" class="!bg-amber-500 !border-none !text-zinc-950 !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-amber-500/10 hover:!bg-amber-400 active:scale-95 transition-all rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isSubmitting ? 'PROCESSING...' : 'APPLY ADJUSTMENT' }}
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
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">SUM_ADJUSTMENT</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ totalQty.toFixed(2) }}</div>
                    </div>
                    <div class="bg-zinc-900/30 border border-zinc-800/50 rounded-2xl p-6 border-l-4 border-l-amber-500 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-amber-500/80 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left italic">ACTIVE WAREHOUSE</span>
                        <div class="text-[11px] font-bold text-zinc-300 uppercase truncate text-center lg:text-left tracking-tight">{{ form.location?.name || 'NOT_SELECTED' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Adjustment Info</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Location</label>
                                 <Select 
                                      v-model="form.location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="Warehouse..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reason for Adjustment</label>
                                 <Select 
                                      v-model="form.reason" 
                                      :options="reasons" 
                                      optionLabel="label" 
                                      placeholder="Reason..." 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Remarks / Notes <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <textarea v-model="form.notes" placeholder="Optional notes for audit trail..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-40 resize-none outline-none focus:border-amber-500/30 transition-all"></textarea>
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <!-- Scattered Stock Breakdown Popover -->
            <Popover ref="stockOp" class="!bg-zinc-900 !border-zinc-800 !shadow-2xl">
                <div v-if="selectedLineForStock" class="w-72 p-4 text-white text-left">
                                <div class="text-[9px] font-black text-amber-500 uppercase tracking-widest mb-3 border-b border-zinc-800 pb-2 flex justify-between">
                                    <span>Location Breakdown</span>
                                    <span>{{ getUomAbbr(selectedLineForStock.uom_id) }}</span>
                                </div>
                                <div class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar">
                                    <div v-for="inv in selectedLineForStock.inventories" :key="inv.id" class="flex justify-between items-center text-[10px]">
                                        <span class="text-zinc-400 truncate pr-2 uppercase font-bold" :class="{'text-amber-400': inv.location_id === form.location?.id}">
                                            {{ inv.location_name }}
                                        </span>
                                        <span class="font-mono text-zinc-200">
                                            {{ getScaledQty(selectedLineForStock, inv.quantity_on_hand) }}
                                        </span>
                                    </div>
                                    <div v-if="!selectedLineForStock.inventories?.length" class="text-center py-2 text-zinc-600 text-[10px] italic">
                                        No stock available in any location
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-t border-zinc-800 flex justify-between items-center font-mono">
                                    <span class="text-[9px] font-bold text-zinc-600 uppercase italic">Total Global Stock</span>
                                    <span class="text-[10px] font-black text-white px-2 py-0.5 bg-zinc-800 rounded">
                                        {{ getScaledQty(selectedLineForStock, selectedLineForStock.product?.total_qoh) }}
                                    </span>
                                </div>
                                </div>
                        </Popover>

                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Adjust</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-amber-500 text-zinc-950 text-[10px] font-black uppercase tracking-widest hover:bg-amber-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar">
                            <DataTable :value="form.lines" class="adj-grid border-none" :pt="{
                                header: { class: 'hidden' },
                                bodyrow: { class: 'hover:!bg-white/[0.02] border-b border-zinc-800/50 transition-all duration-200' }
                            }">
                                <Column field="product" header="PRODUCT" class="!py-6 !px-8">
                                    <template #body="{ index }">
                                        <div class="flex flex-col gap-2 min-w-[300px]">
                                            <Select 
                                                v-model="form.lines[index].product" 
                                                :options="products" 
                                                optionLabel="name" 
                                                placeholder="Select product..." 
                                                filter 
                                                @change="onProductSelect(form.lines[index])"
                                                class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                            />
                                        </div>
                                    </template>
                                </Column>

                                <Column field="uom" header="UNIT" class="!py-6 !px-4">
                                     <template #body="{ index }">
                                         <Select 
                                             v-model="form.lines[index].uom_id" 
                                             :options="uoms" 
                                             optionLabel="abbreviation" 
                                             optionValue="id"
                                             placeholder="UOM" 
                                             class="!w-24 !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                         />
                                     </template>
                                 </Column>

                                 <Column field="quantity" header="QUANTITY (+/-)" class="!py-6 !px-4">
                                     <template #body="{ index, data }">
                                         <div class="flex flex-col gap-1 items-center">
                                             <InputNumber 
                                                 v-model="form.lines[index].quantity" 
                                                 :maxFractionDigits="isUomIdDiscrete(form.lines[index].uom_id) ? 0 : 4"
                                                 placeholder="0" 
                                                 class="p-inputtext-sm text-center font-mono font-bold text-white border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                                 :inputStyle="{ 
                                                     background: '#09090b', 
                                                     border: '1px solid ' + (isInsufficient(data) ? '#f43f5e' : '#27272a'), 
                                                     textAlign: 'center', 
                                                     color: isInsufficient(data) ? '#f43f5e' : (form.lines[index].quantity < 0 ? '#f87171' : '#34d399'), 
                                                     width: '100%', 
                                                     borderRadius: '0.75rem', 
                                                     height: '3rem' 
                                                 }"
                                             />
                                             <div class="flex items-center gap-2">
                                                 <span class="text-xs font-bold font-mono" :class="isInsufficient(data) ? 'text-rose-400' : 'text-emerald-400'">
                                                     {{ getScaledQty(data, getLocalStock(data)) }}
                                                 </span>
                                                 <button 
                                                     v-if="data.product"
                                                     @click="toggleStockInfo($event, data)"
                                                     class="w-5 h-5 flex items-center justify-center rounded-full hover:bg-violet-500/20 text-zinc-600 hover:text-violet-400 transition-all border border-zinc-800"
                                                 >
                                                     <i class="pi pi-info-circle text-[10px]" />
                                                 </button>
                                             </div>
                                             <span class="text-[8px] font-bold text-zinc-500 uppercase tracking-widest font-mono">
                                                 {{ form.location?.name || 'Global' }}
                                             </span>
                                         </div>
                                     </template>
                                 </Column>

                                <Column header="EFFECT" class="!py-6 !px-8 text-right">
                                    <template #body="{ index, data }">
                                        <div class="flex items-center justify-end gap-10 font-mono">
                                             <div class="flex flex-col items-end gap-1">
                                                 <span class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest">Adjustment</span>
                                                 <span :class="form.lines[index].quantity < 0 ? 'text-red-500' : 'text-emerald-500'" class="text-xs font-black">
                                                     {{ form.lines[index].quantity > 0 ? '+' : '' }}{{ form.lines[index].quantity || 0 }}
                                                 </span>
                                             </div>
                                             <button @click="removeLine(index)" class="w-10 h-10 rounded-xl hover:bg-red-500/10 text-zinc-700 hover:text-red-400 transition-all border border-transparent hover:border-red-500/20 ml-2">
                                                <i class="pi pi-trash text-[11px]" />
                                             </button>
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                        <i class="pi pi-sliders-h text-5xl mb-4" />
                                        <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No items added to adjustment</p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
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

const continuousUnits = ['KG', 'L', 'M', 'ML', 'G', 'LB', 'OZ', 'CM', 'MM', 'FT', 'IN', 'GRAM', 'KILOGRAM', 'LITER'];

const isDiscrete = (abbr) => {
    return !continuousUnits.includes(abbr?.toUpperCase());
};

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? isDiscrete(uom.abbreviation) : true;
};

const getFactorToBase = (uomId) => {
    if (!uomId) return { factor: 1, baseId: null };
    let factor = 1.0;
    let current = Number(uomId);
    let processed = [current];
    while (true) {
        const rule = uomConversions.value.find(c => Number(c.from_uom_id) === current);
        if (!rule || processed.includes(Number(rule.to_uom_id))) break;
        factor *= Number(rule.conversion_factor);
        current = Number(rule.to_uom_id);
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
                const line = { product: preselected, uom_id: preselected.uom_id, quantity: 0, inventories: [] };
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

const reasons = ref([
    { label: 'Physical Count Difference', value: 'disc' },
    { label: 'Damaged Items', value: 'dmg' },
    { label: 'Lost / Stolen', value: 'loss' },
    { label: 'System Correction', value: 'err' }
]);

const form = useForm({
    location: null,
    reason: { label: 'Physical Count Difference', id: 1 },
    notes: '',
    lines: []
});

const isSubmitting = ref(false);

const stockOp = ref(null);
const selectedLineForStock = ref(null);

const fetchProductInventory = async (line) => {
    if (!line.product) return;
    try {
        const res = await axios.get(`/api/inventory/${line.product.id}/locations`);
        line.inventories = res.data.data;
    } catch (e) {
        console.error('Failed to fetch inventories', e);
    }
};

const toggleStockInfo = (event, line) => {
    selectedLineForStock.value = line;
    stockOp.value.toggle(event);
};

const getScaledQty = (line, rawPieces) => {
    if (!line.product || rawPieces === undefined || rawPieces === null) return '0';
    const factor = getFactorToBase(line.uom_id).factor;
    const scaled = (parseFloat(rawPieces) / factor);
    return isUomIdDiscrete(line.uom_id) ? Math.floor(scaled + 0.0001).toString() : scaled.toFixed(2);
};

const getLocalStock = (line) => {
    if (!line.inventories || !form.location) return 0;
    const inv = line.inventories.find(i => i.location_id === form.location.id);
    return inv ? inv.quantity_on_hand : 0;
};

const getUomAbbr = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.abbreviation : '';
};

const isInsufficient = (line) => {
    if (!line.product || !form.location) return false;
    let qtyInBase = parseFloat(line.quantity) || 0;
    const targetInfo = getFactorToBase(line.uom_id);
    const productBaseInfo = getFactorToBase(line.product.uom_id);

    if (targetInfo.baseId === productBaseInfo.baseId) {
        const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
        qtyInBase = (parseFloat(line.quantity) || 0) * effectiveFactor;
    }
    
    // For adjustments, we only care if it's a negative adjustment (deduction)
    return qtyInBase < 0 && Math.abs(qtyInBase) > getLocalStock(line);
};

// Monitor each line's product and uom for scaling needs
// Note: fetchProductInventory is explicitly called on product update in onProductSelect

const postAdjustment = async () => {
    isSubmitting.value = true;
    
    // Frontend validation for real-time stock checks (if adjusting down)
    for (const line of form.lines) {
        if (!line.product) continue;
        
        let adjustmentQtyInBase = parseFloat(line.quantity) || 0;
        
        const targetInfo = getFactorToBase(line.uom_id);
        const productBaseInfo = getFactorToBase(line.product.uom_id);

        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            adjustmentQtyInBase = (parseFloat(line.quantity) || 0) * effectiveFactor;
        } else {
            toast.add({ severity: 'error', summary: 'UOM Error', detail: `No conversion path from ${line.uom_id} to product base.`, life: 5000 });
            isSubmitting.value = false;
            return;
        }

        const availableQty = getLocalStock(line);
        
        // If we are deducting more than we have
        if (adjustmentQtyInBase < 0 && Math.abs(adjustmentQtyInBase) > availableQty) {
            toast.add({ severity: 'warn', summary: 'Insufficient Stock', detail: `Cannot deduct equivalent of ${Math.abs(adjustmentQtyInBase)} ${line.product.uom?.abbreviation || 'pcs'} at ${form.location.name}. Available: ${availableQty}.`, life: 5000 });
            isSubmitting.value = false;
            return;
        }
    }
    
    try {
        const meta = props.transactionMeta;
        const payload = {
            header: {
                transaction_type_id: meta.types['adjustment'],
                transaction_status_id: meta.statuses['posted'],
                transaction_date: new Date().toISOString().split('T')[0],
                adjustment_reason_id: form.reason?.id || null,
                from_location_id: form.location?.id,
                to_location_id: form.location?.id,
                notes: form.notes,
            },
            lines: form.lines.map(line => ({
                product_id: line.product?.id,
                location_id: form.location?.id,
                uom_id: line.uom_id,
                quantity: parseFloat(line.quantity),
                unit_cost: parseFloat(line.product?.average_cost || 0)
            }))
        };
        
        await axios.post('/api/adjustments', payload);
        toast.add({ severity: 'success', summary: 'Adjustment Applied', detail: 'Inventory levels updated successfully.', life: 3000 });
        setTimeout(() => router.visit('/inventory-center'), 1000);
    } catch (e) {
        console.error('Submission failed', e);
        toast.add({ severity: 'error', summary: 'Adjustment Failed', detail: e.response?.data?.message || 'Failed to submit adjustment.', life: 5000 });
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
    form.lines.push({ product: null, uom_id: null, quantity: 0, inventories: [] });
};

const removeLine = (index) => {
    form.lines.splice(index, 1);
};

const totalQty = computed(() => form.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0));
</script>

<style scoped>
.adj-grid :deep(.p-datatable-thead) {
    display: table-header-group !important;
}
.adj-grid :deep(.p-datatable-thead > tr > th) {
    background: rgba(24, 24, 27, 0.4) !important;
    border-color: rgba(39, 39, 42, 0.5) !important;
    color: rgba(113, 113, 122, 1) !important;
    font-size: 8px !important;
    text-transform: uppercase !important;
    font-weight: 900 !important;
    letter-spacing: 0.3em !important;
    padding: 1rem 2rem !important;
}
</style>
