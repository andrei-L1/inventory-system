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

const toast = useToast();
const { can } = usePermissions();

const props = defineProps({
    purchaseOrder: { type: Object, default: null }
});

const isEdit = computed(() => !!props.purchaseOrder);
const loading = ref(false);

const vendors = ref([]);
const products = ref([]);
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
    vendor_id: null,
    expected_delivery_date: null,
    currency: 'PHP',
    notes: '',
    lines: [
        { product_id: null, uom_id: null, prev_uom_id: null, ordered_qty: 1, unit_cost: 0.00 }
    ]
});

const loadLookups = async () => {
    try {
        const [vendRes, prodRes, uomRes, convRes] = await Promise.all([
            axios.get('/api/vendors?limit=1000'),
            axios.get('/api/products?limit=1000'),
            axios.get('/api/uom?limit=1000'),
            axios.get('/api/uom-conversions?limit=1000')
        ]);
        vendors.value = vendRes.data.data;
        products.value = prodRes.data.data;
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load system data', life: 3000 });
    }
};

const filteredProducts = computed(() => {
    if (!form.value.vendor_id) return [];
    // Only show products where no vendor is assigned (global) OR preferred vendor matches
    return products.value.filter(p => !p.preferred_vendor_id || p.preferred_vendor_id === form.value.vendor_id);
});

const onProductSelect = (line) => {
    const product = products.value.find(p => p.id === line.product_id);
    if (product) {
        line.uom_id = product.uom_id;
        line.prev_uom_id = product.uom_id;
        // Only suggest cost if current cost is zero or unset
        if (!line.unit_cost || line.unit_cost == 0) {
            line.unit_cost = Number(product.average_cost) > 0 ? Number(product.average_cost) : Number(product.selling_price);
        }
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

const onUomChange = (line) => {
    const product = products.value.find(p => p.id === line.product_id);
    if (!product || !line.uom_id) return;

    const targetInfo = getFactorToBase(line.uom_id, product.id);
    const productBaseInfo = getFactorToBase(product.uom_id, product.id); // The product's reference UOM
    
    // CASE A: Cost is ZERO - Suggest the corresponding cost for this UOM based on the Product's base Average Cost
    if (!line.unit_cost || line.unit_cost == 0) {
        const baseCost = Number(product.average_cost) > 0 ? Number(product.average_cost) : Number(product.selling_price);
        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            line.unit_cost = baseCost * effectiveFactor;
            line.prev_uom_id = line.uom_id;
            return;
        }
    } 
    
    // CASE B: Cost ALREADY EXISTS - Scale the existing cost relative to the PREVIOUS UOM factor to preserve line value
    else if (line.prev_uom_id) {
        const prevInfo = getFactorToBase(line.prev_uom_id, product.id);
        if (targetInfo.baseId === prevInfo.baseId) {
            const ratio = targetInfo.factor / prevInfo.factor;
            line.unit_cost = line.unit_cost * ratio;
        }
    }

    line.prev_uom_id = line.uom_id;
};

onMounted(async () => {
    if (!can('manage-purchase-orders')) {
        toast.add({ severity: 'warn', summary: 'Access denied', detail: 'You do not have permission to create or edit purchase orders.', life: 4000 });
        router.visit('/purchase-orders');

        return;
    }
    await loadLookups();
    if (isEdit.value) {
        form.value = {
            vendor_id: props.purchaseOrder.vendor_id,
            expected_delivery_date: props.purchaseOrder.expected_delivery_date ? new Date(props.purchaseOrder.expected_delivery_date) : null,
            currency: props.purchaseOrder.currency || 'PHP',
            notes: props.purchaseOrder.notes || '',
            lines: props.purchaseOrder.lines.map(l => ({
                product_id: l.product_id,
                uom_id: l.uom_id,
                prev_uom_id: l.uom_id,
                ordered_qty: l.ordered_qty,
                unit_cost: l.unit_cost
            }))
        };
    }
});

const addLine = () => {
    form.value.lines.push({ product_id: null, uom_id: null, prev_uom_id: null, ordered_qty: 1, unit_cost: 0.00 });
};

const removeLine = (index) => {
    if (form.value.lines.length > 1) {
        form.value.lines.splice(index, 1);
    }
};

const grandTotal = computed(() => {
    return form.value.lines.reduce((sum, line) => {
        return sum + (Number(line.ordered_qty) * Number(line.unit_cost));
    }, 0);
});

const submit = async () => {
    if(!form.value.vendor_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a vendor', life: 3000 });
        return;
    }

    loading.value = true;
    try {
        const payload = { ...form.value };
        const res = isEdit.value 
            ? await axios.put(`/api/purchase-orders/${props.purchaseOrder.id}`, payload)
            : await axios.post('/api/purchase-orders', payload);
            
        toast.add({ severity: 'success', summary: 'Success', detail: 'Purchase Order drafted successfully', life: 3000 });
        router.visit(`/purchase-orders/${res.data.data.id}`);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Submission failed', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const cancel = () => {
    router.visit('/purchase-orders');
};
</script>

<template>
    <Head :title="isEdit ? 'Edit PO' : 'Draft Purchase Order'" />
    <AppLayout>
        <div class="h-full max-w-5xl mx-auto flex flex-col gap-6">
            
            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-orange-500/5 blur-[100px] pointer-events-none"></div>
                <div class="flex items-center gap-4 z-10">
                    <button @click="cancel" class="w-10 h-10 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-colors hover:border-zinc-600">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <h1 class="text-white text-xl font-bold tracking-tight mb-1">{{ isEdit ? 'Edit Purchase Order' : 'Draft Purchase Order' }}</h1>
                        <p class="text-zinc-500 text-[10px] font-bold tracking-[0.2em] uppercase font-mono">Create New Procurement Requisition</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 z-10">
                    <Button label="Discard" icon="pi pi-times" class="p-button-text p-button-sm !text-zinc-400 hover:!text-white" @click="cancel" />
                    <Button 
                        label="Save Draft" 
                        icon="pi pi-save" 
                        :loading="loading" 
                        @click="submit"
                        class="p-button-sm !bg-orange-500 hover:!bg-orange-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(249,115,22,0.3)] transition-all"
                    />
                </div>
            </div>

            <!-- Form Body -->
            <div class="grid grid-cols-12 gap-6">
                <!-- Header Info -->
                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-5">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3">Supplier Details</span>
                        
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Vendor (Supplier)</label>
                            <Select 
                                v-model="form.vendor_id" 
                                :options="vendors" 
                                optionLabel="name" 
                                optionValue="id" 
                                placeholder="Select vendor" 
                                filter
                                @change="form.lines = [{ product_id: null, uom_id: null, ordered_qty: 1, unit_cost: 0.00 }]"
                                class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-orange-500/50"
                            />
                        </div>



                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Expected Delivery</label>
                            <DatePicker 
                                v-model="form.expected_delivery_date" 
                                dateFormat="yy-mm-dd" 
                                placeholder="YYYY-MM-DD"
                                class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-orange-500/50"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Document Notes</label>
                            <Textarea v-model="form.notes" rows="4" class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-orange-500/50 resize-none" placeholder="Add terms or instructions..." />
                        </div>
                    </div>
                </div>

                <!-- Lines Editor -->
                <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-4 flex-1">
                        <div class="flex items-center justify-between border-b border-zinc-800/50 pb-3 mb-2">
                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Order Lines</span>
                            <span class="text-[10px] font-bold text-emerald-400 font-mono tracking-widest uppercase bg-emerald-500/10 px-3 py-1 rounded">Total: ₱{{ grandTotal.toFixed(2) }}</span>
                        </div>
                        
                        <!-- Line Items -->
                        <div class="flex flex-col gap-4">
                            <div v-for="(line, index) in form.lines" :key="index" class="p-4 bg-zinc-950/50 border border-zinc-800/50 rounded-xl flex flex-col md:flex-row gap-4 items-end relative group transition-all hover:border-zinc-700">
                                <div class="flex flex-col gap-2 flex-1 w-full relative">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Subject Item</label>
                                    <Select 
                                        v-model="line.product_id" 
                                        :options="filteredProducts" 
                                        optionLabel="name" 
                                        optionValue="id" 
                                        placeholder="Select product" 
                                        filter
                                        @change="onProductSelect(line)"
                                        class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-orange-500/50"
                                    >
                                        <template #option="slotProps">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-xs">{{ slotProps.option.name }}</span>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] font-mono text-sky-500 font-bold uppercase tracking-widest">{{ slotProps.option.sku }}</span>
                                                    <span v-if="slotProps.option.product_code" class="text-[9px] font-mono text-zinc-600 font-bold uppercase tracking-tight">MPN: {{ slotProps.option.product_code }}</span>
                                                    <span class="text-zinc-800 font-mono text-[9px]">| ₱{{ Number(slotProps.option.average_cost || slotProps.option.selling_price).toFixed(2) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </Select>
                                </div>
                                <div class="flex flex-col gap-2 w-full md:w-32 z-0">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">UOM</label>
                                    <Select 
                                        v-model="line.uom_id" 
                                        :options="getAvailableUoms(line.product_id)" 
                                        optionLabel="abbreviation" 
                                        optionValue="id" 
                                        placeholder="UOM" 
                                        @change="onUomChange(line)"
                                        class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-orange-500/50"
                                    >
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                                <span class="font-bold text-[11px]">{{ uoms.find(u => u.id === slotProps.value)?.abbreviation }}</span>
                                                <span 
                                                    v-if="getConversionDetails(slotProps.value, line.product_id)" 
                                                    class="text-[9px] text-zinc-500 font-mono font-bold tracking-widest hidden 2xl:block"
                                                >
                                                    {{ getConversionDetails(slotProps.value, line.product_id).text }}
                                                </span>
                                            </div>
                                            <span v-else>
                                                {{ slotProps.placeholder }}
                                            </span>
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-[11px]">{{ slotProps.option.abbreviation }}</span>
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
                                <div class="flex flex-col gap-2 w-full md:w-32 z-0">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Quantity</label>
                                    <InputNumber 
                                        v-model="line.ordered_qty" 
                                        :min="0.01" 
                                        :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 4" 
                                        class="w-full" 
                                        inputClass="w-full bg-zinc-950 border border-zinc-800 text-center text-white p-2 rounded-lg focus:border-orange-500/50 outline-none transition-colors" 
                                    />
                                </div>
                                <div class="flex flex-col gap-2 w-full md:w-32 z-0">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Unit Cost</label>
                                    <InputNumber v-model="line.unit_cost" mode="decimal" :minFractionDigits="2" class="w-full" inputClass="w-full bg-zinc-950 border border-zinc-800 text-right text-white p-2 rounded-lg focus:border-orange-500/50 outline-none transition-colors" />
                                </div>
                                <Button 
                                    icon="pi pi-trash" 
                                    class="p-button-rounded p-button-danger p-button-text !text-zinc-600 hover:!text-red-400 absolute md:relative top-2 right-2 md:top-0 md:right-0" 
                                    @click="removeLine(index)"
                                    v-if="form.lines.length > 1"
                                />
                            </div>
                        </div>

                        <Button 
                            icon="pi pi-plus" 
                            label="Add Line Item" 
                            class="p-button-outlined p-button-sm w-full mt-2 !text-orange-400 !border-orange-500/20 hover:!bg-orange-500/10 border-dashed font-bold font-mono tracking-widest uppercase hover:!border-orange-500/50 transition-all" 
                            @click="addLine" 
                        />
                    </div>
                </div>
            </div>

        </div>
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
    background: rgba(249, 115, 22, 0.1);
    color: #fb923c;
}
:deep(.p-select-item:hover) {
    background: #27272a;
    color: white;
}
</style>
