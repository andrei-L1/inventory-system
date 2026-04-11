<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Toast from 'primevue/toast';
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import Popover from 'primevue/popover';

const toast = useToast();
const { can } = usePermissions();

const props = defineProps({
    salesOrder: { type: Object, default: null }
});

const isEdit = computed(() => !!props.salesOrder);
const loading = ref(false);

const customers = ref([]);
const products = ref([]);
const locations = ref([]);
const uoms = ref([]);
const uomConversions = ref([]);

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

const form = ref({
    customer_id: null,
    order_date: new Date(),
    currency: 'PHP',
    notes: '',
    lines: [
        { product_id: null, location_id: null, uom_id: null, prev_uom_id: null, ordered_qty: 1, unit_price: 0.00, tax_rate: 0, discount_rate: 0, stock: null, inventories: [], costLayers: [] }
    ]
});

const stockOp = ref(null);
const selectedLineForStock = ref(null);

const toggleStockInfo = (event, line) => {
    selectedLineForStock.value = line;
    stockOp.value.toggle(event);
};

const selectLocation = (loc, line) => {
    line.location_id = loc.location_id;
    checkStock(line);
};

const fetchProductInventory = async (line) => {
    if (!line.product_id) {
        line.inventories = [];
        line.stock = null;
        return;
    }
    try {
        const [invRes, stockRes, layersRes] = await Promise.all([
            axios.get(`/api/inventory/${line.product_id}/locations`),
            line.location_id ? axios.get('/api/inventory/stock-check', {
                params: { product_id: line.product_id, location_id: line.location_id, uom_id: line.uom_id }
            }) : Promise.resolve(null),
            axios.get(`/api/inventory/${line.product_id}/cost-layers`)
        ]);
        line.inventories = invRes.data.data;
        line.stock = stockRes ? stockRes.data : null;
        line.costLayers = layersRes.data.data;
    } catch (e) {
        console.error('Failed to fetch inventory data', e);
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

const getScaledQty = (rawPieces, line) => {
    if (!line.product_id || rawPieces === undefined || rawPieces === null) return '0';
    const { factor } = getFactorToBase(line.uom_id, line.product_id);
    const scaled = (parseFloat(rawPieces) / factor);
    
    return isUomIdDiscrete(line.uom_id) 
        ? Math.floor(scaled + 0.0001).toLocaleString() 
        : scaled.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
};

const calculateEffectiveCost = (line) => {
    if (!line.product_id || !line.costLayers?.length) {
        const product = products.value.find(p => p.id === line.product_id);
        return product?.average_cost || 0;
    }

    const product = products.value.find(p => p.id === line.product_id);
    const costingMethod = product?.costing_method_name?.toLowerCase() || 'average';
    
    // Sort layers based on costing method
    let layers = [...line.costLayers];
    if (costingMethod === 'fifo') {
        layers.sort((a, b) => new Date(a.receipt_date) - new Date(b.receipt_date));
    } else if (costingMethod === 'lifo') {
        layers.sort((a, b) => new Date(b.receipt_date) - new Date(a.receipt_date));
    } else {
        // For 'average', we use the standard average cost
        return getScaledCost(line);
    }

    let totalSimulatedCost = 0;
    // Quantity in ordered UOM needs to be converted to Base for simulation
    const scale = getFactorToBase(line.uom_id, line.product_id).factor;
    let neededBase = line.ordered_qty * scale;
    const initialNeeded = neededBase;

    for (const layer of layers) {
        const taken = Math.min(neededBase, layer.remaining_qty);
        totalSimulatedCost += taken * layer.unit_cost;
        neededBase -= taken;
        if (neededBase <= 0) break;
    }

    // If we sell more than we have, use the last layer price or average for the excess
    if (neededBase > 0 && layers.length > 0) {
        const fallbackCost = layers[layers.length - 1].unit_cost;
        totalSimulatedCost += neededBase * fallbackCost;
    }

    // Convert total cost back to the Order UOM units
    return initialNeeded > 0 ? (totalSimulatedCost / initialNeeded) * scale : 0;
};

const getScaledCost = (line) => {
    const product = products.value.find(p => p.id === line.product_id);
    if (!product) return 0;
    const scale = getFactorToBase(line.uom_id, line.product_id).factor;
    return (Number(product.average_cost) || 0) * scale;
};

const checkStock = async (line) => {
    await fetchProductInventory(line);
};

const getLocalStock = (line) => {
    if (!line.inventories?.length || !line.location_id) return 0;
    const inv = line.inventories.find(i => i.location_id === line.location_id);
    return inv ? inv.quantity_on_hand : 0;
};

const getLocationName = (id) => {
    return locations.value.find(l => l.id === id)?.name || '';
};

const loadLookups = async () => {
    try {
        const [custRes, prodRes, locRes, uomRes, convRes] = await Promise.all([
            axios.get('/api/customers?limit=1000'),
            axios.get('/api/products?limit=1000'),
            axios.get('/api/locations?limit=1000'),
            axios.get('/api/uom?limit=1000'),
            axios.get('/api/uom-conversions?limit=1000')
        ]);
        customers.value = custRes.data.data;
        products.value = prodRes.data.data;
        locations.value = locRes.data.data;
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load system data', life: 3000 });
    }
};

const onProductSelect = (line) => {
    const product = products.value.find(p => p.id === line.product_id);
    if (product) {
        line.uom_id = product.uom_id;
        line.prev_uom_id = product.uom_id;
        if (!line.unit_price || line.unit_price == 0) {
            line.unit_price = product.selling_price || 0;
        }
        fetchProductInventory(line);
    }
};

const getAvailableUoms = (productId) => {
    if (!productId) return [];
    const product = products.value.find(p => p.id === productId);
    if (!product || !product.uom_id) return uoms.value;

    const currentUom = uoms.value.find(u => u.id === product.uom_id);
    if (!currentUom) return [];

    return uoms.value.filter(u => {
        // Must be in the same category
        if (u.category !== currentUom.category) return false;
        
        // Base units of same category are always allowed
        if (u.is_base) return true;

        // If continuous, we assume conversions to base always exist via multiplier
        if (u.category !== 'count') return true;

        // If discrete, it must have a valid rule defined (global or specific to this product)
        return uomConversions.value.some(c => 
            c.from_uom_id === u.id && 
            (c.product_id === null || c.product_id === product.id)
        );
    });
};

const onUomChange = (line) => {
    const product = products.value.find(p => p.id === line.product_id);
    if (!product || !line.uom_id) return;

    const targetInfo = getFactorToBase(line.uom_id, product.id);
    const productBaseInfo = getFactorToBase(product.uom_id, product.id);
    
    if (!line.unit_price || line.unit_price == 0) {
        const basePrice = product.selling_price || 0;
        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            line.unit_price = basePrice * effectiveFactor;
            line.prev_uom_id = line.uom_id;
            return;
        }
    } 
    else if (line.prev_uom_id) {
        const prevInfo = getFactorToBase(line.prev_uom_id, product.id);
        if (targetInfo.baseId === prevInfo.baseId) {
            const ratio = targetInfo.factor / prevInfo.factor;
            line.unit_price = line.unit_price * ratio;
        }
    }

    line.prev_uom_id = line.uom_id;
};

onMounted(async () => {
    if (!can('manage-sales-orders')) {
        toast.add({ severity: 'warn', summary: 'Access denied', detail: 'You do not have permission to manage sales orders.', life: 4000 });
        router.visit('/sales-orders');
        return;
    }
    await loadLookups();
    if (isEdit.value) {
        form.value = {
            customer_id: props.salesOrder.customer_id,
            order_date: props.salesOrder.order_date ? new Date(props.salesOrder.order_date) : new Date(),
            currency: props.salesOrder.currency || 'PHP',
            notes: props.salesOrder.notes || '',
            lines: props.salesOrder.lines.map(l => ({
                product_id: l.product_id,
                location_id: l.location_id,
                uom_id: l.uom_id,
                ordered_qty: l.ordered_qty,
                unit_price: l.unit_price,
                tax_rate: l.tax_rate || 0,
                discount_rate: l.discount_rate || 0
            }))
        };
    }
});

const addLine = () => {
    form.value.lines.push({ product_id: null, location_id: null, uom_id: null, prev_uom_id: null, ordered_qty: 1, unit_price: 0.00, tax_rate: 0, discount_rate: 0, stock: null, inventories: [] });
};

const removeLine = (index) => {
    if (form.value.lines.length > 1) {
        form.value.lines.splice(index, 1);
    }
};

const lineSubtotal = (line) => {
    const base = (Number(line.ordered_qty) || 0) * (Number(line.unit_price) || 0);
    const discount = base * ((Number(line.discount_rate) || 0) / 100);
    const tax = (base - discount) * ((Number(line.tax_rate) || 0) / 100);
    return base - discount + tax;
};

const untaxedSubtotal = computed(() => {
    return form.value.lines.reduce((sum, line) => {
        const base = (Number(line.ordered_qty) || 0) * (Number(line.unit_price) || 0);
        const discount = base * ((Number(line.discount_rate) || 0) / 100);
        return sum + (base - discount);
    }, 0);
});

const totalTax = computed(() => {
    return form.value.lines.reduce((sum, line) => {
        const base = (Number(line.ordered_qty) || 0) * (Number(line.unit_price) || 0);
        const discount = base * ((Number(line.discount_rate) || 0) / 100);
        return sum + ((base - discount) * ((Number(line.tax_rate) || 0) / 100));
    }, 0);
});

const totalDiscount = computed(() => {
    return form.value.lines.reduce((sum, line) => {
        const base = (Number(line.ordered_qty) || 0) * (Number(line.unit_price) || 0);
        return sum + (base * ((Number(line.discount_rate) || 0) / 100));
    }, 0);
});

const grandTotal = computed(() => {
    return untaxedSubtotal.value + totalTax.value;
});

const submit = async () => {
    if(!form.value.customer_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a customer', life: 3000 });
        return;
    }

    loading.value = true;
    try {
        const payload = { ...form.value };
        payload.order_date = payload.order_date.toISOString().split('T')[0];
        
        // Filter out empty lines
        payload.lines = payload.lines.filter(l => l.product_id && l.location_id && l.uom_id);
        
        if (payload.lines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please add at least one complete line item', life: 3000 });
            loading.value = false;
            return;
        }

        const res = isEdit.value 
            ? await axios.put(`/api/sales-orders/${props.salesOrder.id}`, payload)
            : await axios.post('/api/sales-orders', payload);
            
        toast.add({ severity: 'success', summary: 'Success', detail: 'Sales Order drafted successfully', life: 3000 });
        router.visit(`/sales-orders/${res.data.data.id}`);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Submission failed', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const cancel = () => {
    router.visit('/sales-orders');
};
</script>

<template>
    <Head :title="isEdit ? 'Edit SO' : 'New Sales Order'" />
    <AppLayout>
        <div class="h-full max-w-6xl mx-auto flex flex-col gap-6">
            
            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 blur-[100px] pointer-events-none"></div>
                <div class="flex items-center gap-4 z-10">
                    <button @click="cancel" class="w-10 h-10 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-colors hover:border-zinc-600">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <h1 class="text-white text-xl font-bold tracking-tight mb-1">{{ isEdit ? 'Edit Sales Order' : 'New Sales Order' }}</h1>
                        <p class="text-zinc-500 text-[10px] font-bold tracking-[0.2em] uppercase font-mono">Draft Quotation for Customer</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 z-10">
                    <Button label="Discard" icon="pi pi-times" class="p-button-text p-button-sm !text-zinc-400 hover:!text-white" @click="cancel" />
                    <Button 
                        label="Create Quotation" 
                        icon="pi pi-save" 
                        :loading="loading" 
                        @click="submit"
                        class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(20,184,166,0.3)] transition-all"
                    />
                </div>
            </div>

            <!-- Form Body -->
            <div class="grid grid-cols-12 gap-6">
                <!-- Header Info -->
                <div class="col-span-12 lg:col-span-3 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-5">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3">Customer Context</span>
                        
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Customer</label>
                            <Select 
                                v-model="form.customer_id" 
                                :options="customers" 
                                optionLabel="name" 
                                optionValue="id" 
                                placeholder="Select customer" 
                                filter
                                class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Order Date</label>
                            <DatePicker 
                                v-model="form.order_date" 
                                dateFormat="yy-mm-dd" 
                                placeholder="YYYY-MM-DD"
                                class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Notes</label>
                            <Textarea v-model="form.notes" rows="4" class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50 resize-none" placeholder="Special requirements..." />
                        </div>
                    </div>
                </div>

                <!-- Lines Editor -->
                <div class="col-span-12 lg:col-span-9 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-4 flex-1">
                        <div class="flex items-center justify-between border-b border-zinc-800/50 pb-3 mb-2">
                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Invoice Lines</span>
                            <span class="text-[10px] font-bold text-emerald-400 font-mono tracking-widest uppercase bg-emerald-500/10 px-3 py-1 rounded shadow-[0_0_10px_rgba(16,185,129,0.1)]">
                                Grand Total: ₱{{ grandTotal.toFixed(2) }}
                            </span>
                        </div>
                        

                        <div class="flex flex-col gap-4">
                            <div v-for="(line, index) in form.lines" :key="index" class="p-4 bg-zinc-950/30 border border-zinc-800/50 rounded-xl flex flex-col gap-4 relative group transition-all hover:border-zinc-700 hover:shadow-lg hover:bg-zinc-950/50">
                                <div class="grid grid-cols-12 gap-4 items-end">
                                    <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Product Selection</label>
                                        <Select 
                                            v-model="line.product_id" 
                                            :options="products" 
                                            optionLabel="name" 
                                            optionValue="id" 
                                            dataKey="id"
                                            placeholder="Select product" 
                                            filter
                                            @change="() => { onProductSelect(line); checkStock(line); }"
                                            class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-teal-500/50"
                                        >
                                            <template #option="slotProps">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-xs">{{ slotProps.option.name }}</span>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[9px] font-mono text-teal-500 font-bold uppercase tracking-widest">{{ slotProps.option.sku }}</span>
                                                        <span class="text-zinc-700 font-mono text-[9px]">| ₱{{ Number(slotProps.option.selling_price).toFixed(2) }}</span>
                                                    </div>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>

                                    <div class="col-span-6 md:col-span-3 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Storage Source</label>
                                        <Select 
                                            v-model="line.location_id" 
                                            :options="locations" 
                                            optionLabel="name" 
                                            optionValue="id" 
                                            dataKey="id"
                                            placeholder="Location" 
                                            @change="checkStock(line)"
                                            class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-teal-500/50"
                                        />
                                    </div>

                                    <div class="col-span-6 md:col-span-2 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">UOM</label>
                                        <Select 
                                            v-model="line.uom_id" 
                                            :options="getAvailableUoms(line.product_id)" 
                                            optionLabel="abbreviation" 
                                            optionValue="id" 
                                            dataKey="id"
                                            placeholder="UOM" 
                                            @change="onUomChange(line)"
                                            class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-teal-500/50"
                                        >
                                            <template #value="slotProps">
                                                <div v-if="slotProps.value" class="flex items-center gap-2">
                                                    <span class="font-bold text-[11px] uppercase">{{ uoms.find(u => u.id === slotProps.value)?.abbreviation }}</span>
                                                    <span 
                                                        v-if="getConversionDetails(slotProps.value, line.product_id)" 
                                                        class="text-[9px] text-zinc-600 font-mono font-bold tracking-widest hidden 2xl:block uppercase"
                                                    >
                                                        {{ getConversionDetails(slotProps.value, line.product_id).text }}
                                                    </span>
                                                </div>
                                                <span v-else>{{ slotProps.placeholder }}</span>
                                            </template>
                                            <template #option="slotProps">
                                                <div class="flex flex-col">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-bold text-[11px] uppercase">{{ slotProps.option.abbreviation }}</span>
                                                        <span 
                                                            v-if="getConversionDetails(slotProps.option.id, line.product_id)?.isCustom" 
                                                            class="px-1.5 py-[1px] bg-rose-500/20 text-rose-400 text-[8px] font-mono rounded tracking-widest border border-rose-500/30 uppercase"
                                                        >
                                                            Custom
                                                        </span>
                                                    </div>
                                                    <span 
                                                        v-if="getConversionDetails(slotProps.option.id, line.product_id)" 
                                                        class="text-[9px] text-zinc-500 font-mono font-bold mt-0.5 tracking-widest"
                                                    >
                                                        {{ getConversionDetails(slotProps.option.id, line.product_id).text }}
                                                    </span>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>

                                    <div class="col-span-6 md:col-span-2 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Qty</label>
                                        <InputNumber 
                                            v-model="line.ordered_qty" 
                                            :min="0.01" 
                                            :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 4" 
                                            :inputClass="'w-full bg-zinc-950 border text-center text-white p-2 rounded-lg outline-none ' + (line.product_id && line.location_id && getLocalStock(line) < line.ordered_qty ? 'border-red-500/60 focus:border-red-500' : 'border-zinc-800 focus:border-teal-500/50')"
                                        />
                                        <div v-if="line.product_id && line.location_id && getLocalStock(line) < line.ordered_qty" class="text-[7px] text-red-500 font-bold uppercase tracking-tighter text-right animate-pulse">Shortage!</div>
                                    </div>

                                    <div class="col-span-6 md:col-span-1 flex items-center justify-end">
                                        <Button 
                                            icon="pi pi-trash" 
                                            class="p-button-rounded p-button-danger p-button-text !text-zinc-600 hover:!text-red-400" 
                                            @click="removeLine(index)"
                                            v-if="form.lines.length > 1"
                                        />
                                    </div>
                                </div>

                                <!-- Context Indicator Bar -->
                                <div v-if="line.product_id" class="flex flex-wrap items-center gap-2 mt-[-8px]">
                                    <div 
                                        @click="(e) => toggleStockInfo(e, line)"
                                        class="flex items-center gap-2 px-3 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg cursor-pointer hover:border-teal-500/50 transition-all group"
                                    >
                                        <div class="flex flex-col text-left">
                                            <span class="text-[7px] font-bold text-zinc-500 uppercase tracking-widest font-mono group-hover:text-teal-500">At Source</span>
                                            <span class="text-[10px] font-black font-mono" :class="line.location_id && parseFloat(getScaledQty(getLocalStock(line), line)) < line.ordered_qty ? 'text-red-400' : 'text-zinc-200'">{{ getScaledQty(getLocalStock(line), line) }}</span>
                                        </div>
                                        <div class="w-px h-4 bg-zinc-800 mx-1"></div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-[7px] font-bold text-zinc-500 uppercase tracking-widest font-mono group-hover:text-teal-500">Global Pool</span>
                                            <span class="text-[10px] font-black text-zinc-400 font-mono">{{ getScaledQty((line.inventories || []).reduce((s, i) => s + (parseFloat(i.quantity_on_hand) || 0), 0), line) }}</span>
                                        </div>
                                        <i class="pi pi-chevron-down text-[8px] text-zinc-600 group-hover:text-teal-500"></i>
                                    </div>

                                    <div v-if="line.product_id" 
                                        class="flex items-center gap-2 px-3 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg"
                                        :class="line.unit_price < calculateEffectiveCost(line) ? 'border-red-500/30' : ''"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-[7px] font-bold text-zinc-500 uppercase tracking-widest font-mono">EST_COST ({{ (products.find(p => p.id === line.product_id)?.costing_method_name || 'AVG').toUpperCase() }})</span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-black font-mono text-zinc-300">₱{{ calculateEffectiveCost(line).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                <i v-if="line.unit_price < calculateEffectiveCost(line)" class="pi pi-exclamation-triangle text-[10px] text-red-500 animate-pulse" title="Selling below estimated cost!"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-12 gap-4 items-end bg-zinc-900/40 p-3 rounded-lg border border-zinc-800/30">
                                    <div class="col-span-4 md:col-span-3 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Unit Price</label>
                                        <div class="relative">
                                            <InputNumber v-model="line.unit_price" mode="decimal" :minFractionDigits="2" :inputClass="'w-full bg-zinc-950 border text-right text-white p-2 rounded-lg focus:border-teal-500/50 ' + (line.unit_price < calculateEffectiveCost(line) ? 'border-red-500/50 shadow-[0_0_10px_rgba(239,68,68,0.1)]' : 'border-zinc-800')" />
                                            <div v-if="line.unit_price < calculateEffectiveCost(line)" class="absolute -top-1 -right-1">
                                                <i class="pi pi-exclamation-circle text-red-500 text-[10px] bg-zinc-950 rounded-full shadow-lg"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-4 md:col-span-3 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Tax (%)</label>
                                        <InputNumber v-model="line.tax_rate" :min="0" :max="100" suffix="%" inputClass="w-full bg-zinc-950 border border-zinc-800 text-center text-white p-2 rounded-lg focus:border-teal-500/50" />
                                    </div>
                                    <div class="col-span-4 md:col-span-3 flex flex-col gap-2">
                                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Disc (%)</label>
                                        <InputNumber v-model="line.discount_rate" :min="0" :max="100" suffix="%" inputClass="w-full bg-zinc-950 border border-zinc-800 text-center text-white p-2 rounded-lg focus:border-teal-500/50" />
                                    </div>
                                    <div class="col-span-12 md:col-span-3 flex flex-col gap-2 items-end">
                                        <label class="text-[9px] font-bold text-teal-500/70 tracking-[0.2em] font-mono uppercase">Subtotal</label>
                                        <span class="text-sm font-mono font-bold text-white pr-2">₱{{ lineSubtotal(line).toFixed(2) }}</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <Button 
                            icon="pi pi-plus" 
                            label="Append Line Item" 
                            class="p-button-outlined p-button-sm w-full mt-2 !text-teal-400 !border-teal-500/20 hover:!bg-teal-500/10 border-dashed font-bold font-mono tracking-widest uppercase hover:!border-teal-500/50 transition-all border-2" 
                            @click="addLine" 
                        />

                        <!-- Financial Summary Footer -->
                        <div class="mt-6 p-6 bg-zinc-950/50 border border-zinc-800/80 rounded-2xl flex flex-col gap-3 shadow-inner">
                            <div class="flex justify-between items-center text-zinc-500">
                                <span class="text-[10px] font-bold uppercase tracking-widest font-mono">Untaxed Subtotal</span>
                                <span class="text-xs font-mono font-bold">₱{{ untaxedSubtotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-zinc-500">
                                <span class="text-[10px] font-bold uppercase tracking-widest font-mono">Total Discount</span>
                                <span class="text-xs font-mono font-bold text-red-500/70">- ₱{{ totalDiscount.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-zinc-500 border-b border-zinc-800/50 pb-3">
                                <span class="text-[10px] font-bold uppercase tracking-widest font-mono">Estimated Tax</span>
                                <span class="text-xs font-mono font-bold">₱{{ totalTax.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-1">
                                <span class="text-xs font-bold text-white uppercase tracking-widest font-mono">Grand Total</span>
                                <span class="text-lg font-black text-emerald-400 font-mono shadow-emerald-500/20 drop-shadow-md">₱{{ grandTotal.toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <Popover ref="stockOp" class="!bg-zinc-950 !border-zinc-800 !p-0 !shadow-2xl !rounded-2xl overflow-hidden min-w-[280px]">
            <div v-if="selectedLineForStock" class="flex flex-col">
                <div class="px-4 py-3 bg-zinc-900/80 border-b border-zinc-800 flex justify-between items-center">
                    <span class="text-[10px] font-bold text-teal-500 uppercase tracking-widest font-mono">STOCK BREAKDOWN</span>
                    <span class="text-[10px] font-bold text-zinc-600 font-mono">{{ uoms.find(u => u.id === selectedLineForStock.uom_id)?.abbreviation || 'pcs' }}</span>
                </div>
                
                <div class="max-h-[300px] overflow-y-auto">
                    <div v-if="selectedLineForStock.inventories?.length" class="divide-y divide-zinc-900">
                        <div 
                            v-for="inv in selectedLineForStock.inventories" 
                            :key="inv.id"
                            @click="selectLocation(inv, selectedLineForStock)"
                            class="px-4 py-3 flex items-center justify-between hover:bg-teal-500/5 cursor-pointer transition-colors group"
                            :class="inv.location_id === selectedLineForStock.location_id ? 'bg-teal-500/10' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full" :class="inv.location_id === selectedLineForStock.location_id ? 'bg-teal-400 shadow-[0_0_8px_rgba(45,212,191,0.5)]' : 'bg-zinc-800'"></div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold font-mono tracking-tight" :class="inv.location_id === selectedLineForStock.location_id ? 'text-teal-100' : 'text-zinc-400'">{{ inv.location_name }}</span>
                                    <span v-if="inv.location_id === selectedLineForStock.location_id" class="text-[8px] text-teal-500 font-bold uppercase tracking-widest">Selected Source</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="text-xs font-black font-mono text-zinc-200">{{ getScaledQty(inv.quantity_on_hand, selectedLineForStock) }}</span>
                                <span class="text-[8px] text-zinc-600 font-mono font-bold uppercase">{{ uoms.find(u => u.id === selectedLineForStock.uom_id)?.abbreviation || 'pcs' }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-8 text-center text-zinc-600 italic text-xs font-mono">
                         No stock records found
                    </div>
                </div>
                
                <div class="p-3 bg-zinc-950 border-t border-zinc-800 flex items-center justify-between">
                    <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-widest font-mono italic">Click a location to route</span>
                    <div class="flex items-center gap-2">
                         <span class="text-[11px] font-black text-white font-mono">{{ getScaledQty((selectedLineForStock.inventories || []).reduce((s, i) => s + (parseFloat(i.quantity_on_hand) || 0), 0), selectedLineForStock) }}</span>
                         <span class="text-[8px] font-bold text-zinc-600 uppercase font-mono">GLOBAL</span>
                    </div>
                </div>
            </div>
        </Popover>
    </AppLayout>
</template>

<style scoped>
:deep(.p-select), :deep(.p-datepicker), :deep(.p-inputnumber-input), :deep(.p-inputtext) {
    background: #09090b !important;
    border-color: #27272a;
    color: white;
}
:deep(.p-select-panel), :deep(.p-datepicker-panel) {
    background: #18181b;
    border: 1px solid #27272a;
}
:deep(.p-select-item), :deep(.p-datepicker-day) {
    color: #a1a1aa;
}
:deep(.p-select-item.p-highlight) {
    background: rgba(20, 184, 166, 0.1);
    color: #2dd4bf;
}
:deep(.p-select-item:hover) {
    background: #27272a;
    color: white;
}
</style>
