<template>
    <AppLayout>
        <Head title="Inventory Receipt" />
        <Toast />
        
        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">New Stock Entry</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Receive Stock</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">
                        Add new stock items into your warehouse. Verify items and quantities against the vendor's documentation.
                    </p>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.visit('/inventory-center')" class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest transition-all rounded-xl border">
                        CANCEL
                    </button>
                    <button @click="submitForm" :disabled="isSubmitting" class="!bg-sky-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ isSubmitting ? 'PROCESSING...' : 'RECEIVE ITEMS' }}
                    </button>
                </div>
            </div>

            <div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col min-h-0">
                <!-- Summary Bar -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_ITEMS</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ form.lines.length.toString().padStart(2, '0') }}</div>
                    </div>
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_PIECES</span>
                        <div class="text-2xl font-bold text-white tracking-tight text-center lg:text-left">{{ totalQty.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 8 }) }}</div>
                    </div>
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left">TOTAL_VALUE</span>
                        <div class="text-2xl font-bold text-emerald-400 tracking-tight text-center lg:text-left">₱ {{ totalValue.toLocaleString() }}</div>
                    </div>
                    <div class="bg-zinc-900/30 border border-zinc-800/50 rounded-2xl p-6 border-l-4 border-l-sky-500 shadow-xl backdrop-blur-sm">
                        <span class="text-[9px] font-bold text-sky-500/80 uppercase tracking-widest font-mono mb-2 block text-center lg:text-left italic">LOCATION</span>
                        <div class="text-[11px] font-bold text-zinc-300 uppercase truncate text-center lg:text-left tracking-tight">{{ form.to_location?.name || 'NOT_SELECTED' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-8 flex-1 min-h-0">
                    <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-sky-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">General Info</span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-8">
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Supplier <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <Select 
                                      v-model="form.vendor" 
                                      :options="vendors" 
                                      optionLabel="name" 
                                      placeholder="Select Vendor" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>
                             
                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Destination Location</label>
                                 <Select 
                                      v-model="form.to_location" 
                                      :options="locations" 
                                      optionLabel="name" 
                                      placeholder="Select Location" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reference / PO # <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <InputText 
                                      v-model="form.reference_number" 
                                      placeholder="Leave blank to auto-generate" 
                                      class="!w-full !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !px-4 !text-xs !font-mono text-white placeholder:!text-zinc-800"
                                 />
                             </div>

                             <div class="flex flex-col gap-3">
                                 <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Remarks <span class="text-zinc-700 normal-case font-sans tracking-normal">(Optional)</span></label>
                                 <textarea v-model="form.notes" placeholder="Optional notes..." class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 text-xs text-zinc-400 h-32 resize-none outline-none focus:border-sky-500/30 transition-all"></textarea>
                             </div>
                        </div>

                        <div class="p-6 bg-zinc-950/50 border-t border-zinc-800">
                             <div class="flex items-center justify-between">
                                 <div class="flex flex-col">
                                     <span class="text-white font-bold text-[10px] uppercase tracking-tight">Print Barcodes</span>
                                 </div>
                                 <ToggleSwitch v-model="form.print_label" />
                             </div>
                        </div>
                    </aside>

                    <main class="col-span-12 lg:col-span-9 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                        <div class="p-6 border-b border-zinc-800 bg-zinc-900/60 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Items to Receive</span>
                            </div>
                            <button @click="addLine" class="px-6 h-10 rounded-xl bg-sky-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-sky-400 transition-all active:scale-95 flex items-center gap-2">
                                <i class="pi pi-plus text-[10px]" />
                                ADD ITEM
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar">
                            <DataTable :value="form.lines" class="receipt-grid border-none" :pt="{
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
                                                placeholder="Search Catalog..." 
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
                                             :options="getAvailableUoms(form.lines[index].product?.id)" 
                                             optionLabel="abbreviation" 
                                             optionValue="id"
                                             placeholder="UOM" 
                                             @change="onUomChange(form.lines[index])"
                                             class="!w-24 !bg-zinc-950 !border-zinc-800 !h-12 !rounded-xl !text-xs font-mono"
                                         />
                                     </template>
                                 </Column>

                                 <Column field="stock_level" header="STOCK LEVEL" class="!py-6 !px-4">
                                     <template #body="{ data }">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border leading-none cursor-help hover:border-sky-500/50 transition-colors"
                                                  @click="toggleStockInfo($event, data)"
                                                  :class="[
                                                      (data.product?.total_qoh || 0) === 0 ? 'bg-red-500/10 text-red-400 border-red-500/20' : 
                                                      (data.product?.total_qoh || 0) < (data.product?.reorder_point || 0) ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 
                                                      'bg-sky-500/10 text-sky-400 border-sky-500/20'
                                                  ]">
                                                {{ getScaledAvailableStock(data) }}
                                            </span>
                                            <span class="text-[8px] font-bold text-zinc-600 uppercase tracking-tighter">Current Global</span>
                                        </div>
                                     </template>
                                 </Column>

                                 <Column field="quantity" header="QUANTITY" class="!py-6 !px-4">
                                     <template #body="{ index }">
                                         <InputNumber 
                                             v-model="form.lines[index].quantity" 
                                             :min="0"
                                             :maxFractionDigits="isUomIdDiscrete(form.lines[index].uom_id) ? 0 : 8"
                                             placeholder="0" 
                                             class="p-inputtext-sm text-center font-mono font-bold text-white border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                             :inputStyle="{ background: '#09090b', border: '1px solid #27272a', textAlign: 'center', color: 'white', width: '100%', borderRadius: '0.75rem', height: '3rem' }"
                                         />
                                     </template>
                                 </Column>

                                 <Column field="unit_cost" header="COST PER UNIT" class="!py-6 !px-4">
                                     <template #body="{ index }">
                                         <div class="relative w-32">
                                             <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-700 text-[10px] font-mono z-10">₱</span>
                                             <InputNumber 
                                                 v-model="form.lines[index].unit_cost" 
                                                 :min="0"
                                                 :minFractionDigits="2"
                                                 :maxFractionDigits="8"
                                                 placeholder="0.00" 
                                                 class="p-inputtext-sm text-center font-mono font-bold text-white border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                                 :inputStyle="{ background: '#09090b', border: '1px solid #27272a', textAlign: 'right', color: '#34d399', width: '100%', borderRadius: '0.75rem', height: '3rem', paddingLeft: '2rem' }"
                                             />
                                         </div>
                                     </template>
                                 </Column>

                                <Column header="SUBTOTAL" class="!py-6 !px-8 text-right">
                                    <template #body="{ index }">
                                        <div class="flex items-center justify-end gap-6">
                                            <span class="text-xs font-bold font-mono text-white">₱ {{ (form.lines[index].quantity * form.lines[index].unit_cost || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 }) }}</span>
                                            <button @click="removeLine(index)" class="w-8 h-8 rounded-lg hover:bg-red-500/10 text-zinc-700 hover:text-red-400 transition-all border border-transparent hover:border-red-500/20">
                                                <i class="pi pi-trash text-[10px]" />
                                            </button>
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="py-32 flex flex-col items-center justify-center opacity-10 filter grayscale">
                                        <i class="pi pi-inbox text-5xl mb-4" />
                                        <p class="text-[9px] font-black uppercase tracking-[0.4em] font-mono">No Items Added</p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>
                    </main>
                </div>
            </div>
        </div>

        <Popover ref="stockOp" class="!bg-zinc-950 !border-zinc-800 !shadow-2xl !p-0 overflow-hidden">
            <div v-if="selectedLineForStock" class="w-72 p-4 text-white text-left">
                <div class="text-[9px] font-black text-sky-500 uppercase tracking-widest mb-3 border-b border-zinc-800 pb-2 flex justify-between">
                    <span>Location Breakdown</span>
                    <span>{{ uoms.find(u => u.id == selectedLineForStock.uom_id)?.abbreviation }}</span>
                </div>
                <div class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar">
                    <div v-for="loc in selectedLineForStock.inventories" :key="loc.location_id" class="flex justify-between items-center text-[10px]"
                         :class="{'bg-sky-500/10 -mx-2 px-2 py-1 rounded': loc.location_id === form.to_location?.id}">
                        <span class="text-zinc-400 truncate pr-2 uppercase font-bold flex items-center gap-2">
                            {{ loc.location_name }}
                            <Tag v-if="loc.location_id === form.to_location?.id" value="TARGET" class="!text-[7px] !bg-sky-500 !text-white !px-1 !h-3" />
                        </span>
                        <span class="font-mono text-zinc-200">
                            {{ getScaledQty(selectedLineForStock.product.uom_id, loc.quantity_on_hand, selectedLineForStock.uom_id) }}
                        </span>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-t border-zinc-800 flex justify-between items-center font-mono text-[9px]">
                    <span class="font-bold text-zinc-600 uppercase italic">Total Global Stock</span>
                    <span class="font-black text-white px-2 py-0.5 bg-zinc-900 rounded">
                        {{ getScaledAvailableStock(selectedLineForStock) }}
                    </span>
                </div>
            </div>
        </Popover>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ToggleSwitch from 'primevue/toggleswitch';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import Popover from 'primevue/popover';
import Tag from 'primevue/tag';

const toast = useToast();
const { props, url } = usePage();
const queryParams = computed(() => {
    const searchParams = new URLSearchParams(new URL(url, window.location.origin).search);
    return Object.fromEntries(searchParams.entries());
});

const vendors = ref([]);
const products = ref([]);
const locations = ref([]);
const uoms = ref([]);
const uomConversions = ref([]);
const loadingProducts = ref(false);
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
        factor *= rule.conversion_factor;
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

const getScaledQty = (productUomId, rawPieces, targetUomId, productId = null) => {
    if (!productUomId || rawPieces === undefined || rawPieces === null) return '0';
    
    const targetUom = uoms.value.find(u => u.id == targetUomId);
    const targetAbbr = targetUom ? targetUom.abbreviation : '';

    const targetInfo = getFactorToBase(targetUomId, productId);
    const productBaseInfo = getFactorToBase(productUomId, productId);

    if (targetInfo.baseId !== productBaseInfo.baseId) {
        return `${Number(rawPieces).toFixed(2)} (?)`;
    }

    const scaled = (Number(rawPieces) / targetInfo.factor);
    const formatted = isUomIdDiscrete(targetUomId) ? Math.floor(scaled + 0.0001).toString() : scaled.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 8 });
    return `${formatted} ${targetAbbr}`;
};

const getScaledAvailableStock = (line) => {
    if (!line.product || !line.uom_id) return '0';
    return getScaledQty(line.product.uom_id, line.product.total_qoh, line.uom_id, line.product.id);
};

const toggleStockInfo = async (event, line) => {
    if (!line.product) return;
    
    if (!line.inventories) {
        try {
            const res = await axios.get(`/api/inventory/${line.product.id}/locations`);
            line.inventories = res.data.data;
        } catch (e) {
            console.error("Failed to fetch inventory breakdown", e);
        }
    }
    
    selectedLineForStock.value = line;
    stockOp.value.toggle(event);
};

const form = useForm({
    vendor: null,
    reference_number: '',
    to_location: { id: 1, name: 'Secure Vault Alpha' },
    notes: '',
    lines: [],
    print_label: true
});

const isSubmitting = ref(false);

const submitForm = async () => {
    isSubmitting.value = true;
    try {
        const meta = props.transactionMeta;
        const payload = {
            header: {
                transaction_type_id: meta.types['receipt'],
                transaction_status_id: meta.statuses['posted'],
                transaction_date: new Date().toISOString().split('T')[0],
                reference_number: form.reference_number,
                to_location_id: form.to_location?.id,
                vendor_id: form.vendor?.id,
                notes: form.notes,
            },
            lines: form.lines.map(line => ({
                product_id: line.product?.id,
                location_id: form.to_location?.id,
                uom_id: line.uom_id,
                quantity: parseFloat(line.quantity),
                unit_cost: parseFloat(line.unit_cost)
            }))
        };
        
        await axios.post('/api/transactions', payload);
        toast.add({ severity: 'success', summary: 'Stock Received', detail: 'Items added to inventory successfully.', life: 3000 });
        setTimeout(() => router.visit('/inventory-center'), 1000);
    } catch (e) {
        console.error('Submission failed', e);
        toast.add({ severity: 'error', summary: 'Submission Failed', detail: e.response?.data?.message || 'Failed to submit form. Check all fields.', life: 5000 });
    } finally {
        isSubmitting.value = false;
    }
};

const onProductSelect = async (line) => {
    const product = line.product;
    if (product) {
        line.uom_id = product.uom_id;
        line.prev_uom_id = product.uom_id;
        line.unit_cost = product.average_cost > 0 ? product.average_cost : product.selling_price;
        
        // Fetch inventory breakdown immediately
        try {
            const res = await axios.get(`/api/inventory/${product.id}/locations`);
            line.inventories = res.data.data;
        } catch (e) {
            console.error("Failed to fetch inventory breakdown", e);
        }
    }
};

const onUomChange = (line) => {
    const product = line.product;
    if (!product || !line.uom_id) return;

    const targetInfo = getFactorToBase(line.uom_id, product.id);
    const productBaseInfo = getFactorToBase(product.uom_id, product.id);

    // CASE A: Cost is ZERO - Suggest base cost scaled to this UOM
    if (!line.unit_cost || line.unit_cost == 0) {
        const baseCost = product.average_cost > 0 ? product.average_cost : product.selling_price;
        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            line.unit_cost = baseCost * effectiveFactor;
            line.prev_uom_id = line.uom_id;
            return;
        }
    } 
    
    // CASE B: Cost exists - Scale relative to previous UOM
    else if (line.prev_uom_id) {
        const prevInfo = getFactorToBase(line.prev_uom_id, product.id);
        if (targetInfo.baseId === prevInfo.baseId) {
            const ratio = targetInfo.factor / prevInfo.factor;
            line.unit_cost = line.unit_cost * ratio;
        }
    }

    line.prev_uom_id = line.uom_id;
};

const addLine = () => {
    form.lines.push({ product: null, uom_id: null, prev_uom_id: null, quantity: 1, unit_cost: 0 });
};

const removeLine = (index) => {
    form.lines.splice(index, 1);
};

const totalQty = computed(() => form.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0));
const totalValue = computed(() => form.lines.reduce((s, l) => s + ((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0)), 0));

const loadData = async () => {
    loadingProducts.value = true;
    try {
        const [prodRes, locRes, vendRes, uomRes, convRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/locations'),
            axios.get('/api/vendors'),
            axios.get('/api/uom'),
            axios.get('/api/uom-conversions')
        ]);
        
        products.value = prodRes.data.data;
        locations.value = locRes.data.data;
        vendors.value = vendRes.data.data;
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
        
        if (queryParams.value.product_id && products.value.length > 0) {
            const preselected = products.value.find(p => p.id == queryParams.value.product_id);
            if (preselected) {
                const line = { product: preselected, quantity: 1, unit_cost: 0 };
                onProductSelect(line);
                form.lines.push(line);
            }
        }
    } catch (e) {
        console.error('Failed to load data', e);
    } finally {
        loadingProducts.value = false;
    }
};

onMounted(() => {
    loadData();
});
</script>

<style scoped>
.receipt-grid :deep(.p-datatable-thead) {
    display: table-header-group !important;
}
.receipt-grid :deep(.p-datatable-thead > tr > th) {
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
