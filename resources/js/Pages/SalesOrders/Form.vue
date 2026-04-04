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
import { useToast } from "primevue/usetoast";
import axios from 'axios';

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

const form = ref({
    customer_id: null,
    requested_delivery_date: null,
    currency: 'PHP',
    notes: '',
    lines: [
        { product_id: null, location_id: null, uom_id: null, prev_uom_id: null, ordered_qty: 1, unit_price: 0.00, tax_rate: 0, discount_rate: 0 }
    ]
});

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
            line.unit_price = product.selling_price;
        }
        // Auto-select first location for simplicity if not already set
        if (!line.location_id && locations.value.length > 0) {
            line.location_id = locations.value[0].id;
        }
    }
};

const onUomChange = (line) => {
    const product = products.value.find(p => p.id === line.product_id);
    if (!product || !line.uom_id) return;

    const targetInfo = getFactorToBase(line.uom_id);
    const productBaseInfo = getFactorToBase(product.uom_id);

    // CASE A: Price is ZERO or Unset - Suggest the corresponding price for this UOM based on the Product's base Selling Price
    if (!line.unit_price || line.unit_price == 0) {
        const basePrice = product.selling_price;
        if (targetInfo.baseId === productBaseInfo.baseId) {
            const effectiveFactor = targetInfo.factor / productBaseInfo.factor;
            line.unit_price = basePrice * effectiveFactor;
            line.prev_uom_id = line.uom_id;
            return;
        }
    } 
    
    // CASE B: Price ALREADY EXISTS - Scale the existing price relative to the PREVIOUS UOM factor to preserve line value
    else if (line.prev_uom_id) {
        const prevInfo = getFactorToBase(line.prev_uom_id);
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
            requested_delivery_date: props.salesOrder.requested_delivery_date ? new Date(props.salesOrder.requested_delivery_date) : null,
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
    form.value.lines.push({ product_id: null, location_id: locations.value[0]?.id || null, uom_id: null, prev_uom_id: null, ordered_qty: 1, unit_price: 0.00, tax_rate: 0, discount_rate: 0 });
};

const removeLine = (index) => {
    if (form.value.lines.length > 1) {
        form.value.lines.splice(index, 1);
    }
};

const calculateLineSubtotal = (line) => {
    return line.ordered_qty * line.unit_price;
};

const calculateLineTotal = (line) => {
    const subtotal = calculateLineSubtotal(line);
    const tax = (subtotal * (line.tax_rate || 0)) / 100;
    const discount = (subtotal * (line.discount_rate || 0)) / 100;
    return subtotal + tax - discount;
};

const grandTotal = computed(() => {
    return form.value.lines.reduce((sum, line) => {
        return sum + calculateLineTotal(line);
    }, 0);
});

const submit = async () => {
    if(!form.value.customer_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a customer', life: 3000 });
        return;
    }

    loading.value = true;
    try {
        const payload = { ...form.value };
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
    <Head :title="isEdit ? 'Edit SO' : 'Draft Sales Order'" />
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
                        <h1 class="text-white text-xl font-bold tracking-tight mb-1">{{ isEdit ? 'Edit Sales Order' : 'Draft Sales Order' }}</h1>
                        <p class="text-zinc-500 text-[10px] font-bold tracking-[0.2em] uppercase font-mono">Create New Quotation / Order</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 z-10">
                    <Button label="Discard" icon="pi pi-times" class="p-button-text p-button-sm !text-zinc-400 hover:!text-white" @click="cancel" />
                    <Button 
                        label="Save Draft" 
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
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3">Client Details</span>
                        
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
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Delivery Date</label>
                            <DatePicker 
                                v-model="form.requested_delivery_date" 
                                dateFormat="yy-mm-dd" 
                                placeholder="YYYY-MM-DD"
                                class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Currency</label>
                            <InputText v-model="form.currency" class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Notes</label>
                            <Textarea v-model="form.notes" rows="4" class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50 resize-none" placeholder="Internal or client notes..." />
                        </div>
                    </div>
                </div>

                <!-- Lines Editor -->
                <div class="col-span-12 lg:col-span-9 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-4 flex-1">
                        <div class="flex items-center justify-between border-b border-zinc-800/50 pb-3 mb-2">
                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Invoiceable Items</span>
                            <span class="text-[10px] font-bold text-emerald-400 font-mono tracking-widest uppercase bg-emerald-500/10 px-3 py-1 rounded border border-emerald-500/10">Order Total: ₱{{ grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        </div>
                        
                        <!-- Line Items -->
                        <div class="flex flex-col gap-4">
                            <div v-for="(line, index) in form.lines" :key="index" class="p-5 bg-zinc-950/50 border border-zinc-800/50 rounded-xl grid grid-cols-12 gap-4 relative group transition-all hover:border-zinc-700">
                                <div class="col-span-12 md:col-span-4 flex flex-col gap-2 relative">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Product Selection</label>
                                    <Select 
                                        v-model="line.product_id" 
                                        :options="products" 
                                        optionLabel="name" 
                                        optionValue="id" 
                                        placeholder="Select product" 
                                        filter
                                        @change="onProductSelect(line)"
                                        class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-teal-500/50"
                                    >
                                        <template #option="slotProps">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-xs">{{ slotProps.option.name }}</span>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] font-mono text-teal-500 font-bold uppercase tracking-widest">{{ slotProps.option.sku }}</span>
                                                    <span class="text-zinc-600 font-mono text-[9px]">| ₱{{ Number(slotProps.option.selling_price).toFixed(2) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </Select>
                                </div>

                                <div class="col-span-12 md:col-span-3 flex flex-col gap-2">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">Reservation Loc.</label>
                                    <Select 
                                        v-model="line.location_id" 
                                        :options="locations" 
                                        optionLabel="name" 
                                        optionValue="id" 
                                        placeholder="Location" 
                                        class="w-full bg-zinc-950 border-zinc-800 text-white focus:border-teal-500/50"
                                    />
                                </div>

                                <div class="col-span-6 md:col-span-2 flex flex-col gap-2">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase">UOM / Qty</label>
                                    <div class="flex items-center gap-2">
                                        <Select 
                                            v-model="line.uom_id" 
                                            :options="uoms" 
                                            optionLabel="abbreviation" 
                                            optionValue="id" 
                                            placeholder="U" 
                                            @change="onUomChange(line)"
                                            class="w-20 bg-zinc-950 border-zinc-800 text-white focus:border-teal-500/50"
                                        />
                                        <InputNumber 
                                            v-model="line.ordered_qty" 
                                            :min="0.01" 
                                            :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 4" 
                                            class="flex-1" 
                                            inputClass="w-full bg-zinc-950 border border-zinc-800 text-center text-white p-2 rounded-lg focus:border-teal-500/50 outline-none transition-colors" 
                                        />
                                    </div>
                                </div>

                                <div class="col-span-6 md:col-span-3 flex flex-col gap-2">
                                    <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] font-mono uppercase text-right">Unit Price (PHP)</label>
                                    <InputNumber v-model="line.unit_price" mode="decimal" :minFractionDigits="2" class="w-full" inputClass="w-full bg-zinc-950 border border-zinc-800 text-right text-white p-2 rounded-lg focus:border-teal-500/50 outline-none transition-colors" />
                                </div>

                                <!-- Tax / Discount Row -->
                                <div class="col-span-4 flex flex-col gap-2">
                                    <label class="text-[9px] font-bold text-zinc-600 tracking-[0.2em] font-mono uppercase">Tax (%)</label>
                                    <InputNumber v-model="line.tax_rate" :min="0" :max="100" class="w-full" inputClass="w-full bg-zinc-900 border border-zinc-800 text-center text-zinc-400 p-1.5 rounded focus:border-teal-500/50 outline-none transition-colors text-xs" />
                                </div>
                                <div class="col-span-4 flex flex-col gap-2">
                                    <label class="text-[9px] font-bold text-zinc-600 tracking-[0.2em] font-mono uppercase">Discount (%)</label>
                                    <InputNumber v-model="line.discount_rate" :min="0" :max="100" class="w-full" inputClass="w-full bg-zinc-900 border border-zinc-800 text-center text-zinc-400 p-1.5 rounded focus:border-teal-500/50 outline-none transition-colors text-xs" />
                                </div>
                                <div class="col-span-4 flex flex-col gap-2 text-right">
                                    <label class="text-[9px] font-bold text-zinc-600 tracking-[0.2em] font-mono uppercase">Line Total</label>
                                    <span class="text-zinc-300 font-mono text-sm font-bold pt-1">₱{{ calculateLineTotal(line).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                                </div>

                                <Button 
                                    icon="pi pi-trash" 
                                    class="p-button-rounded p-button-danger p-button-text !text-zinc-600 hover:!text-red-400 absolute top-2 right-2" 
                                    @click="removeLine(index)"
                                    v-if="form.lines.length > 1"
                                />
                            </div>
                        </div>

                        <Button 
                            icon="pi pi-plus" 
                            label="Add Line Item" 
                            class="p-button-outlined p-button-sm w-full mt-2 !text-teal-400 !border-teal-500/20 hover:!bg-teal-500/10 border-dashed font-bold font-mono tracking-widest uppercase hover:!border-teal-500/50 transition-all" 
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
    background: rgba(20, 184, 166, 0.1);
    color: #2dd4bf;
}
:deep(.p-select-item:hover) {
    background: #27272a;
    color: white;
}
</style>
