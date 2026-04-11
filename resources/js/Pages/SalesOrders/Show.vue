<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Popover from 'primevue/popover';
import Select from 'primevue/select';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ id: [String, Number] });
const { can } = usePermissions();
const confirm = useConfirm();
const toast = useToast();

const so = ref(null);
const loading = ref(true);
const uoms = ref([]);
const uomConversions = ref([]);
const locations = ref([]);

const pickLoading = ref(false);
const packLoading = ref(false);
const approveLoading = ref(false);
const fulfillLoading = ref(false);

const pickDialog = ref(false);
const pickForm = ref({ lines: [] });

const packDialog = ref(false);
const packForm = ref({ lines: [] });

const shipDialog = ref(false);
const shipForm = ref({ 
    lines: [],
    carrier: '', 
    tracking_number: '' 
});

const returnDialog = ref(false);
const returnLoading = ref(false);
const returnForm = ref({ 
    location_id: null,
    lines: [],
    notes: ''
});

// Removed Popover toggle logic in favor of always-visible breakdown

const isQuotation = computed(() => so.value?.status?.name === 'quotation' || so.value?.status?.name === 'quotation_sent');
const canCancel = computed(() => so.value && !['shipped', 'cancelled', 'closed'].includes(so.value.status.name));

const getTotalAvailable = (line) => {
    return Number(line.total_available_qty || 0);
};

const getFormattedTotalAvailable = (line) => {
    return line.formatted_total_available_qty || '0';
};

const getAvailabilityStatus = (line) => {
    const total = getTotalAvailable(line);
    const ordered = Number(line.ordered_qty);
    if (total >= ordered) return { severity: 'success', label: 'In Stock' };
    if (total > 0) return { severity: 'warn', label: 'Limited' };
    return { severity: 'danger', label: 'Out of Stock' };
};

const loadMasterData = async () => {
    try {
        const [uomRes, convRes, locRes] = await Promise.all([
            axios.get('/api/uom'),
            axios.get('/api/uom-conversions'),
            axios.get('/api/locations?limit=1000')
        ]);
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
        locations.value = locRes.data.data;
    } catch (e) {
        console.error("Failed to load metadata", e);
    }
};

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.category === 'count' : true;
};

const loadSO = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/sales-orders/${props.id}`);
        so.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load Sales Order', life: 3000 });
        router.visit('/sales-orders');
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await Promise.all([loadSO(), loadMasterData()]);
});

const getStatusColor = (statusName) => {
    const map = {
        'quotation': 'warning',
        'quotation_sent': 'info',
        'confirmed': 'info',
        'partially_picked': 'help',
        'picked': 'help',
        'partially_packed': 'help',
        'packed': 'help',
        'partially_shipped': 'help',
        'shipped': 'success',
        'closed': 'success',
        'cancelled': 'danger'
    };
    return map[statusName] || 'info';
};

const approve = async () => {
    confirm.require({
        message: 'Confirm this order? This will reserve stock at the designated locations.',
        header: 'Confirm Sales Order',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                approveLoading.value = true;
                await axios.patch(`/api/sales-orders/${so.value.id}/approve`);
                toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Stock has been reserved', life: 3000 });
                loadSO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Confirmation failed', life: 3000 });
            } finally {
                approveLoading.value = false;
            }
        }
    });
};

const cancelOrder = () => {
    confirm.require({
        message: 'Are you sure you want to cancel this order? All reserved stock will be released.',
        header: 'Cancel Sales Order',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.patch(`/api/sales-orders/${so.value.id}/cancel`);
                toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Order cancelled and stock released', life: 3000 });
                loadSO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Cancellation failed', life: 3000 });
            }
        }
    });
};

const deleteOrder = () => {
    confirm.require({
        message: 'Permanently delete this quotation? This action cannot be undone.',
        header: 'Delete Quotation',
        icon: 'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/sales-orders/${so.value.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Quotation removed', life: 3000 });
                router.visit('/sales-orders');
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Delete failed', life: 3000 });
            }
        }
    });
};

const editOrder = () => {
    router.visit(`/sales-orders/${so.value.id}/edit`);
};

const openPickDialog = () => {
    pickForm.value.lines = so.value.lines
        .filter(l => Number(l.picked_qty) < Number(l.ordered_qty))
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            ordered_qty: Number(l.ordered_qty),
            picked_qty: Number(l.picked_qty), // already picked
            to_pick: Number(l.remaining_pick_qty), // from accessor
            uom: l.uom?.abbreviation
        }));
    pickDialog.value = true;
};

const submitPick = async () => {
    try {
        pickLoading.value = true;
        const payload = {
            lines: pickForm.value.lines.filter(l => Number(l.to_pick) > 0).map(l => ({
                so_line_id: l.so_line_id,
                picked_qty: l.to_pick
            }))
        };
        if (payload.lines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Input Required', detail: 'Specify at least one item to pick', life: 3000 });
            return;
        }
        await axios.patch(`/api/sales-orders/${so.value.id}/pick`, payload);
        toast.add({ severity: 'success', summary: 'Pick Registered', detail: 'Items moved to staging', life: 3000 });
        pickDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Pick failed', life: 3000 });
    } finally {
        pickLoading.value = false;
    }
};

const openPackDialog = () => {
    packForm.value.lines = so.value.lines
        .filter(l => Number(l.packed_qty) < Number(l.picked_qty))
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            picked_qty: Number(l.picked_qty),
            packed_qty: Number(l.packed_qty),
            to_pack: Number(l.remaining_pack_qty),
            uom: l.uom?.abbreviation
        }));
    packDialog.value = true;
};

const submitPack = async () => {
    try {
        packLoading.value = true;
        const payload = {
            lines: packForm.value.lines.filter(l => Number(l.to_pack) > 0).map(l => ({
                so_line_id: l.so_line_id,
                packed_qty: l.to_pack
            }))
        };
        await axios.patch(`/api/sales-orders/${so.value.id}/pack`, payload);
        toast.add({ severity: 'success', summary: 'Pack Registered', detail: 'Items verified and boxed', life: 3000 });
        packDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Pack failed', life: 3000 });
    } finally {
        packLoading.value = false;
    }
};

const printVoucher = () => {
    window.open(`/sales-orders/${so.value.id}/print`, '_blank');
};

const openShipDialog = () => {
    shipForm.value.lines = so.value.lines
        .filter(l => Number(l.shipped_qty) < Number(l.packed_qty))
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            packed_qty: Number(l.packed_qty),
            shipped_qty: Number(l.shipped_qty),
            to_ship: Number(l.remaining_ship_qty),
            uom: l.uom?.abbreviation
        }));
    shipDialog.value = true;
};

const fulfill = async () => {
    if (!shipForm.value.carrier) {
        toast.add({ severity: 'warn', summary: 'Required', detail: 'Please specify a carrier', life: 3000 });
        return;
    }

    try {
        fulfillLoading.value = true;
        const payload = {
            carrier: shipForm.value.carrier,
            tracking_number: shipForm.value.tracking_number,
            lines: shipForm.value.lines.filter(l => Number(l.to_ship) > 0).map(l => ({
                so_line_id: l.so_line_id,
                shipped_qty: l.to_ship
            }))
        };
        await axios.post(`/api/sales-orders/${so.value.id}/ship`, payload);
        toast.add({ severity: 'success', summary: 'Shipped', detail: 'Order fulfilled and stock movements recorded', life: 3000 });
        shipDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Fulfillment failed', life: 3000 });
    } finally {
        fulfillLoading.value = false;
    }
};

const openReturnDialog = () => {
    returnForm.value.lines = so.value.lines
        .filter(l => Number(l.shipped_qty) > 0)
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            shipped_qty: Number(l.shipped_qty),
            returned_qty: Number(l.returned_qty), // already returned
            to_return: 0,
            uom: l.uom?.abbreviation,
            resolution: 'replacement',
            reason: ''
        }));
    returnForm.value.notes = '';
    returnForm.value.location_id = null;
    returnDialog.value = true;
};

const submitReturn = async () => {
    if (!returnForm.value.location_id) {
        toast.add({ severity: 'warn', summary: 'Input Required', detail: 'Specify a destination location for the return', life: 3000 });
        return;
    }

    try {
        const payload = {
            location_id: returnForm.value.location_id,
            notes: returnForm.value.notes,
            lines: returnForm.value.lines.filter(l => Number(l.to_return) > 0).map(l => ({
                so_line_id: l.so_line_id,
                returned_qty: l.to_return,
                resolution: l.resolution,
                reason: l.reason
            }))
        };

        if (payload.lines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Input Required', detail: 'Specify at least one item to return', life: 3000 });
            return;
        }

        returnLoading.value = true;
        await axios.post(`/api/sales-orders/${so.value.id}/return`, payload);
        toast.add({ severity: 'success', summary: 'Return Processed', detail: 'Stock updated and Credit Note generated', life: 3000 });
        returnDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Return failed', life: 3000 });
    } finally {
        returnLoading.value = false;
    }
};

const canApprove = computed(() => so.value?.status?.name === 'quotation' || so.value?.status?.name === 'quotation_sent');
const canPick = computed(() => {
    if (!so.value || ['quotation', 'quotation_sent', 'shipped', 'cancelled', 'closed'].includes(so.value.status.name)) return false;
    return so.value.lines?.some(l => Number(l.picked_qty) < Number(l.ordered_qty));
});

const canPack = computed(() => {
    if (!so.value || ['quotation', 'quotation_sent', 'shipped', 'cancelled', 'closed'].includes(so.value.status.name)) return false;
    return so.value.lines?.some(l => Number(l.packed_qty) < Number(l.picked_qty));
});

const canShip = computed(() => {
    if (!so.value || ['quotation', 'quotation_sent', 'shipped', 'cancelled', 'closed'].includes(so.value.status.name)) return false;
    return so.value.lines?.some(l => Number(l.shipped_qty) < Number(l.packed_qty));
});

const canReturn = computed(() => so.value?.lines?.some(l => Number(l.shipped_qty) > 0));

const subtotal = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + (Number(line.ordered_qty) * Number(line.unit_price)), 0) || 0;
});

const totalTax = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + Number(line.tax_amount || 0), 0) || 0;
});

const totalDiscount = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + Number(line.discount_amount || 0), 0) || 0;
});




</script>

<template>
    <Head :title="so ? so.so_number : 'Loading...'" />
    <AppLayout v-if="so">
        <div class="h-full flex flex-col gap-4">

            <!-- Header Panel -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-zinc-950 border border-teal-900/30 rounded-3xl shadow-2xl relative overflow-hidden ring-1 ring-white/5">
                <!-- Teal Ambient Glow -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-teal-500/10 blur-[100px] pointer-events-none"></div>

                <div class="flex items-center gap-5 z-10">
                    <button @click="router.visit('/sales-orders')" class="w-12 h-12 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-all hover:scale-105 hover:border-teal-500/30 active:scale-95 group">
                        <i class="pi pi-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-4 mb-1.5">
                            <h1 class="text-white text-3xl font-black tracking-tighter font-mono">{{ so.so_number }}</h1>
                            <Tag 
                                :severity="getStatusColor(so.status.name)" 
                                :value="so.status.name.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-black tracking-[0.2em] font-mono uppercase px-3 py-1 rounded-lg shadow-inner bg-zinc-800/50 border border-zinc-700/50"
                            />
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono flex items-center gap-3">
                            <span @click="router.visit(`/customer-center?customer_id=${so.customer_id}`)" class="text-teal-400 hover:text-teal-300 cursor-pointer transition-colors">{{ so.customer_name }}</span>
                            <span class="w-1 h-1 rounded-full bg-zinc-700"></span>
                            <span class="text-zinc-500">₱{{ so.formatted_total_amount }} Revenue</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10 no-print">
                    <Button 
                        v-if="canApprove && can('manage-sales-orders')" 
                        label="Confirm Order" 
                        icon="pi pi-check-circle" 
                        :loading="approveLoading"
                        class="p-button-sm !bg-teal-500/20 hover:!bg-teal-500/30 !text-teal-400 !border-teal-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="approve"
                    />

                    <Button 
                        v-if="isQuotation && can('manage-sales-orders')" 
                        label="Edit" 
                        icon="pi pi-pencil" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="editOrder"
                    />

                    <Button 
                        v-if="canCancel && can('manage-sales-orders')" 
                        label="Cancel Order" 
                        icon="pi pi-times-circle" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-red-400 !border-red-900/30 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="cancelOrder"
                    />

                    <Button 
                        v-if="isQuotation && can('manage-sales-orders')" 
                        label="Delete" 
                        icon="pi pi-trash" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-zinc-500 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="deleteOrder"
                    />

                    <Button 
                        v-if="so.status?.name !== 'quotation' && so.status?.name !== 'cancelled'"
                        label="Print" 
                        icon="pi pi-print" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="printVoucher"
                    />

                    <Button 
                        v-if="canPick && can('manage-sales-orders')" 
                        label="Pick Lines" 
                        icon="pi pi-box" 
                        :loading="pickLoading"
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-help-400 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openPickDialog"
                    />

                    <Button 
                        v-if="canPack && can('manage-sales-orders')" 
                        label="Pack Lines" 
                        icon="pi pi-gift" 
                        :loading="packLoading"
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-help-400 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openPackDialog"
                    />

                    <Button 
                        v-if="canShip && can('manage-sales-orders')" 
                        label="Ship / Fulfill" 
                        icon="pi pi-truck" 
                        class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(20,184,166,0.3)] tracking-widest uppercase font-mono transition-all" 
                        @click="openShipDialog"
                    />

                    <Button 
                        v-if="canReturn && can('manage-sales-orders')" 
                        label="Return Items" 
                        icon="pi pi-backward" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-amber-400 !border-amber-900/30 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openReturnDialog"
                    />
                </div>
            </div>

            <!-- Details Board -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Meta Info Side -->
                <div class="col-span-12 lg:col-span-3 flex flex-col gap-4">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3">Sales Metadata</span>
                        
                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Order Date</span>
                            <span class="text-xs font-bold text-white">{{ so.order_date }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Customer Code</span>
                            <span class="text-xs font-bold text-teal-400">{{ so.customer_code }}</span>
                        </div>
                        <div v-if="so.shipped_at" class="flex flex-col gap-2 py-2 border-b border-zinc-800/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Shipped On</span>
                                <span class="text-xs font-bold text-teal-400">{{ so.shipped_at }}</span>
                            </div>
                            <div class="mt-1 p-2 bg-zinc-950 rounded border border-zinc-800">
                                <div class="flex justify-between text-[10px] mb-1">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Carrier:</span>
                                    <span class="text-zinc-300 font-bold">{{ so.carrier }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Tracking:</span>
                                    <span class="text-sky-500 font-mono">{{ so.tracking_number }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="so.notes" class="flex flex-col gap-2 pt-2">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Notes</span>
                            <p class="text-xs text-zinc-400 leading-relaxed bg-zinc-950/50 p-3 rounded-lg border border-zinc-800/50">{{ so.notes }}</p>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="flex flex-col gap-5 p-6 bg-zinc-950/50 rounded-2xl border border-zinc-800/50 shadow-inner">
                        <div class="flex justify-between items-center text-zinc-400">
                            <span class="text-[10px] font-bold tracking-widest uppercase font-mono">Subtotal</span>
                            <span class="text-xs font-bold text-white">₱{{ so.formatted_subtotal }}</span>
                        </div>
                        <div class="flex justify-between items-center text-zinc-400">
                            <span class="text-[10px] font-bold tracking-widest uppercase font-mono">Tax Amount</span>
                            <span class="text-xs font-bold text-white">₱{{ so.formatted_total_tax }}</span>
                        </div>
                        <div class="flex justify-between items-center text-zinc-400">
                            <span class="text-[10px] font-bold tracking-widest uppercase font-mono">Discounts</span>
                            <span class="text-xs font-bold text-white">₱{{ so.formatted_total_discount }}</span>
                        </div>
                        <div class="h-px bg-zinc-800/50 my-1"></div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-[11px] font-black text-white tracking-widest uppercase font-mono">Grand Total</span>
                            <span class="text-lg font-black text-teal-400 font-mono shadow-teal-500/20 drop-shadow-md">₱{{ so.formatted_total_amount }}</span>
                        </div>
                    </div>

                    <!-- Fulfillment History -->
                    <div v-if="so.transactions && so.transactions.length > 0" class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-teal-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-2 text-center">Fulfillment History</span>
                        
                        <div v-for="tx in so.transactions" :key="tx.id" class="flex flex-col gap-1.5 p-3 bg-zinc-950/50 rounded-xl border border-zinc-800/50 group transition-all hover:border-teal-500/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-mono text-teal-400 font-bold uppercase tracking-tight">{{ tx.reference_number }}</span>
                                <span class="text-[9px] font-mono text-zinc-600">{{ tx.transaction_date }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[9px] font-black text-zinc-600 uppercase tracking-widest">Status</span>
                                <Tag :severity="tx.status.name === 'posted' ? 'success' : 'warning'" :value="tx.status.name.toUpperCase()" class="text-[8px] font-black px-1.5 py-0.5 rounded-md" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lines Data -->
                <div class="col-span-12 lg:col-span-9 flex flex-col gap-4">
                    <div class="flex-1 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl flex flex-col overflow-hidden shadow-xl p-4">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-2 mb-3 block">Fulfillment Lines</span>
                        
                        <DataTable :value="so.lines" class="p-datatable-sm w-full" stripedRows>
                            <Column field="product_name" header="PRODUCT/ID">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[9px] font-bold text-teal-500/70 font-mono tracking-widest uppercase">{{ data.sku }}</span>
                                            <span class="text-[8px] text-zinc-600 font-mono">/ {{ data.uom?.abbreviation }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column field="location_name" header="SOURCE">
                                <template #body="{ data }">
                                    <span class="text-zinc-500 text-[10px] font-bold uppercase font-mono tracking-tighter">{{ data.location?.name || 'N/A' }}</span>
                                </template>
                            </Column>

                            <!-- Stock Visibility for Quotations -->
                            <Column v-if="isQuotation" header="AVAILABILITY">
                                <template #body="{ data }">
                                    <div class="flex flex-col gap-1.5 py-1 min-w-[150px]">
                                        <div v-if="data.availability && data.availability.some(l => l.available_qty > 0 || l.reserved_qty > 0)" class="bg-zinc-950/50 rounded-lg p-2 border border-zinc-800/50 flex flex-col gap-1">
                                            <div v-for="loc in data.availability.filter(l => l.available_qty > 0 || l.reserved_qty > 0)" :key="loc.location_name" 
                                                 class="flex items-center justify-between px-0.5 border-b border-zinc-800/30 last:border-0 pb-0.5 mb-0.5 last:pb-0 last:mb-0">
                                                <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-tighter">{{ loc.location_name }}</span>
                                                <div class="flex items-center gap-2">
                                                    <span :class="Number(loc.available_qty) > 0 ? 'text-teal-400' : 'text-zinc-600'" class="text-[10px] font-mono font-bold">{{ loc.formatted_available_qty }}</span>
                                                    <span v-if="Number(loc.reserved_qty) > 0" class="text-[8px] text-amber-500/60 font-mono">({{ loc.formatted_reserved_qty }} RSV)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-2 bg-rose-500/5 border border-rose-500/10 rounded-lg">
                                            <span class="text-[8px] font-bold text-rose-400 uppercase tracking-widest">OUT OF STOCK</span>
                                        </div>
                                        
                                        <div class="flex justify-between px-1">
                                            <span class="text-[9px] font-bold text-zinc-600 uppercase font-mono tracking-widest">Total</span>
                                            <span class="text-[10px] font-black" :class="getTotalAvailable(data) > 0 ? 'text-white' : 'text-red-500/50'">{{ getFormattedTotalAvailable(data) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column header="LIFECYCLE STATUS">
                                <template #body="{ data }">
                                    <div class="flex flex-col gap-2 w-36">
                                        <div class="h-2 bg-zinc-950 rounded-full overflow-hidden relative border border-zinc-800/80 p-[1px] shadow-inner">
                                            <!-- Picked Bar (Background layer) -->
                                            <div :style="{ width: (Number(data.picked_qty) / Number(data.ordered_qty) * 100) + '%' }" class="absolute top-0 left-0 h-full bg-teal-500/30 transition-all duration-500 rounded-full"></div>
                                            <!-- Shipped Bar (Foreground layer) -->
                                            <div :style="{ width: (Number(data.shipped_qty) / Number(data.ordered_qty) * 100) + '%' }" class="absolute top-0 left-0 h-full bg-emerald-500 transition-all duration-700 shadow-[0_0_10px_rgba(16,185,129,0.5)] rounded-full"></div>
                                        </div>
                                        <div class="flex justify-between items-center px-1">
                                            <span class="text-[8px] font-black text-zinc-600 font-mono uppercase tracking-widest">Fulfillment</span>
                                            <span class="text-[10px] font-black text-emerald-400 font-mono">{{ Math.round(Number(data.shipped_qty) / Number(data.ordered_qty) * 100) }}%</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column header="MANIFEST">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-mono font-black text-white">{{ data.formatted_ordered_qty }}</span>
                                        </div>
                                        <span class="text-[9px] font-black text-zinc-600 uppercase tracking-widest">Ordered</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="PICK/PACK">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-mono font-bold text-zinc-300">{{ data.formatted_picked_qty }} Picked</span>
                                        <span class="text-[10px] font-mono font-bold text-zinc-300">{{ data.formatted_packed_qty }} Packed</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="SHIPPED">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-mono font-black text-emerald-400">{{ data.formatted_shipped_qty }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="PRICE">
                                <template #body="{ data }">
                                    <span class="text-zinc-400 font-mono text-[10px] font-bold">{{ data.formatted_unit_price }}</span>
                                </template>
                            </Column>
                            <Column field="subtotal" header="LINE TOTAL">
                                <template #body="{ data }">
                                    <span class="text-white font-mono text-xs font-bold">₱{{ Number(data.subtotal || 0).toFixed(2) }}</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>

            <!-- Linked Transactions (COGS) -->
            <div v-if="so.transactions && so.transactions.length > 0" class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-zinc-800"></div>
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-[0.3em] font-mono whitespace-nowrap">Audit Ledger & Stock Movements</span>
                    <div class="h-px flex-1 bg-zinc-800"></div>
                </div>

                <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-xl">
                    <DataTable :value="so.transactions" class="p-datatable-sm w-full audit-ledger-grid" :pt="{
                        thead: { class: 'bg-zinc-950' },
                        bodyrow: { class: 'hover:bg-teal-500/[0.02] transition-colors border-b border-zinc-900/50' }
                    }">
                        <Column field="reference_number" header="MOVEMENT REF">
                            <template #body="{ data }">
                                <span class="text-teal-500 font-mono text-[10px] font-bold uppercase tracking-tight">{{ data.reference_number }}</span>
                            </template>
                        </Column>
                        <Column field="transaction_date" header="DATE">
                             <template #body="{ data }">
                                <span class="text-[10px] font-mono text-zinc-400">{{ data.transaction_date }}</span>
                            </template>
                        </Column>
                        <Column field="display_type" header="TYPE">
                            <template #body="{ data }">
                                <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest">{{ data.display_type }}</span>
                            </template>
                        </Column>
                        <Column field="from_location_name" header="EXPORT FROM">
                            <template #body="{ data }">
                                <span class="text-[10px] font-bold text-zinc-500 uppercase font-mono tracking-tight">{{ data.from_location_name }}</span>
                            </template>
                        </Column>
                        <Column field="to_location_name" header="DESTINATION">
                            <template #body="{ data }">
                                <span class="text-[10px] font-bold text-zinc-500 uppercase font-mono tracking-tight">{{ data.to_location_name }}</span>
                            </template>
                        </Column>
                        <Column field="formatted_quantity" header="QUANTITY">
                             <template #body="{ data }">
                                <span class="text-[11px] font-mono font-black text-white" :class="{ 'text-amber-500': Number(data.quantity) < 0 }">{{ data.formatted_quantity }}</span>
                            </template>
                        </Column>
                        <Column header="COGS AUDIT">
                            <template #body>
                                <span class="text-[10px] text-zinc-600 font-mono font-bold tracking-tighter uppercase px-2 py-0.5 bg-zinc-950 rounded border border-zinc-800/50">Ledger Recorded</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Pick Dialog -->
        <Dialog v-model:visible="pickDialog" modal :header="null" :closable="false" :style="{ width: '55rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-zinc-950 border border-teal-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-teal-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-teal-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center">
                            <i class="pi pi-box text-teal-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-white tracking-tight font-mono uppercase">Inventory Picking Control</h2>
                            <p class="text-[9px] text-zinc-500 font-black uppercase tracking-widest mt-0.5">Stage items for order fulfillment</p>
                        </div>
                    </div>
                    <button @click="pickDialog = false" class="w-8 h-8 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white hover:bg-zinc-800 transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-teal-500/5 border border-teal-500/10 p-4 rounded-2xl flex items-start gap-3">
                        <i class="pi pi-info-circle text-teal-500 mt-0.5"></i>
                        <p class="text-[11px] text-zinc-400 font-medium leading-relaxed font-mono uppercase tracking-tight">
                            Protocol: Move reserved stock from storage bins to the staging area. Registering picks decreases available inventory and increments the staging ledger.
                        </p>
                    </div>
                
                    <DataTable :value="pickForm.lines" class="p-datatable-sm" scrollable scrollHeight="300px">
                        <Column field="product_name" header="PRODUCT/SKU">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-teal-500/70 font-bold uppercase tracking-widest">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="PROGRESS">
                            <template #body="{ data }">
                                <div class="flex flex-col gap-1 items-center">
                                    <span class="text-[10px] font-mono font-bold text-zinc-400 bg-zinc-900 px-3 py-1 rounded-lg border border-zinc-800 shadow-inner">
                                        {{ Number(data.picked_qty) }} / {{ Number(data.ordered_qty) }}
                                    </span>
                                    <span class="text-[8px] font-black text-zinc-600 uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QUANTITY TO STAGE" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-zinc-950 border border-zinc-800 rounded-xl focus-within:border-teal-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_pick" 
                                        :min="0" 
                                        :max="Number(data.ordered_qty) - Number(data.picked_qty)" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-teal-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#14b8a6', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                    <div class="px-3 border-l border-zinc-800 bg-zinc-900/50 h-full flex items-center">
                                        <span class="text-[9px] font-black text-zinc-500 uppercase">{{ data.uom }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-teal-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="pickDialog = false" class="p-button-text !text-zinc-500 hover:!text-white uppercase font-mono font-black tracking-widest text-[11px]" />
                    <Button 
                        label="Complete Pick Selection" 
                        :loading="pickLoading" 
                        @click="submitPick"
                        class="!px-8 !h-11 !bg-teal-500 hover:!bg-teal-400 !text-zinc-950 font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_20px_rgba(20,184,166,0.3)]"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Pack Dialog -->
        <Dialog v-model:visible="packDialog" modal :header="null" :closable="false" :style="{ width: '55rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-zinc-950 border border-indigo-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5 shadow-[0_0_50px_rgba(79,70,229,0.1)]">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-indigo-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-indigo-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                            <i class="pi pi-gift text-indigo-400 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-white tracking-tight font-mono uppercase">Packing & Quality Assurance</h2>
                            <p class="text-[9px] text-zinc-500 font-black uppercase tracking-widest mt-0.5">Verify and box items for dispatch</p>
                        </div>
                    </div>
                    <button @click="packDialog = false" class="w-8 h-8 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white hover:bg-zinc-800 transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-indigo-500/5 border border-indigo-500/10 p-4 rounded-2xl flex items-start gap-3">
                        <i class="pi pi-verified text-indigo-400 mt-0.5"></i>
                        <p class="text-[11px] text-zinc-400 font-medium leading-relaxed font-mono uppercase tracking-tight">
                            Protocol: Audit staged items against packing manifest. Once "Packed", stock is considered ready for fulfillment and cannot be modified without a reversal.
                        </p>
                    </div>
                
                    <DataTable :value="packForm.lines" class="p-datatable-sm" scrollable scrollHeight="300px">
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-indigo-400/70 font-bold uppercase tracking-widest font-mono uppercase">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="STAGED (PICKED)">
                            <template #body="{ data }">
                                <div class="flex flex-col items-center">
                                    <span class="text-[10px] font-mono font-black text-zinc-300">{{ data.picked_qty }}</span>
                                    <span class="text-[8px] font-black text-zinc-600 uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QUANTITY TO PACK" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-zinc-950 border border-zinc-800 rounded-xl focus-within:border-indigo-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_pack" 
                                        :min="0" 
                                        :max="data.picked_qty - data.packed_qty" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-indigo-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#818cf8', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                    <div class="px-3 border-l border-zinc-800 bg-zinc-900/50 h-full flex items-center">
                                        <span class="text-[9px] font-black text-zinc-500 uppercase">{{ data.uom }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-indigo-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="packDialog = false" class="p-button-text !text-zinc-500 hover:!text-white uppercase font-mono font-black tracking-widest text-[11px]" />
                    <div class="flex items-center gap-4">
                        <span v-if="packForm.lines.some(l => l.to_pack > 0)" class="text-[10px] font-mono text-zinc-500 font-bold uppercase">{{ packForm.lines.filter(l => l.to_pack > 0).length }} Items Prepared</span>
                        <Button 
                            label="Verify & Pack" 
                            :loading="packLoading" 
                            @click="submitPack"
                            class="!px-8 !h-11 !bg-indigo-600 hover:!bg-indigo-500 !text-white font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_20px_rgba(79,70,229,0.3)]"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Ship Dialog -->
        <Dialog v-model:visible="shipDialog" modal :header="null" :closable="false" :style="{ width: '60rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-zinc-950 border border-emerald-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5 shadow-[0_0_50px_rgba(16,185,129,0.1)]">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-emerald-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-emerald-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                            <i class="pi pi-truck text-emerald-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-white tracking-tight font-mono uppercase">Logistics & Dispatch Control</h2>
                            <p class="text-[9px] text-zinc-500 font-black uppercase tracking-widest mt-0.5">Final fulfillment and courier handover</p>
                        </div>
                    </div>
                    <button @click="shipDialog = false" class="w-8 h-8 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white hover:bg-zinc-800 transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-emerald-500/5 border border-emerald-500/10 p-4 rounded-2xl flex items-start gap-4">
                        <i class="pi pi-map-marker text-emerald-500 mt-0.5"></i>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest font-mono mb-1">Carrier Manifest Protocol</span>
                            <p class="text-[11px] text-zinc-400 font-medium leading-relaxed font-mono uppercase tracking-tight">
                                Executing dispatch finalizes the stock issue ledger. Please verify tracking ID accuracy for customer-side traceability. Handover recorded units to <span class="text-white font-bold bg-zinc-900 px-2 py-0.5 rounded">SHIPMENT-EXPORT</span>.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 bg-zinc-900/40 p-4 rounded-2xl border border-zinc-800/50">
                        <div class="flex flex-col gap-2">
                            <label class="text-[9px] font-black text-zinc-600 tracking-[0.2em] uppercase font-mono">Carrier Service Name</label>
                            <InputText v-model="shipForm.carrier" placeholder="e.g. FedEx, Internal Runner" class="!w-full !bg-zinc-950 !border-zinc-800 !rounded-xl !h-11 !px-4 !text-xs !font-bold text-white focus:!border-emerald-500/50 transition-all shadow-inner" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[9px] font-black text-zinc-600 tracking-[0.2em] uppercase font-mono">Tracking Reference / AWB</label>
                            <InputText v-model="shipForm.tracking_number" placeholder="Enter Ref #" class="!w-full !bg-zinc-950 !border-zinc-800 !rounded-xl !h-11 !px-4 !text-xs !font-bold text-white focus:!border-emerald-500/50 transition-all shadow-inner" />
                        </div>
                    </div>
                
                    <DataTable :value="shipForm.lines" class="p-datatable-sm" scrollable scrollHeight="250px">
                        <Column field="product_name" header="PRODUCT/SKU">
                             <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-emerald-500/70 font-bold uppercase tracking-widest">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="BOXED (PACKED)">
                            <template #body="{ data }">
                                <div class="flex flex-col items-center">
                                    <span class="text-[10px] font-mono font-black text-zinc-300">{{ data.packed_qty }}</span>
                                    <span class="text-[8px] font-black text-zinc-600 uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QUANTITY TO SHIP" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-zinc-950 border border-zinc-800 rounded-xl focus-within:border-emerald-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_ship" 
                                        :min="0" 
                                        :max="data.packed_qty - data.shipped_qty" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-emerald-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#10b981', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                    <div class="px-3 border-l border-zinc-800 bg-zinc-900/50 h-full flex items-center">
                                        <span class="text-[9px] font-black text-zinc-500 uppercase">{{ data.uom }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-emerald-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="shipDialog = false" class="p-button-text !text-zinc-500 hover:!text-white uppercase font-mono font-black tracking-widest text-[11px]" />
                    <Button 
                        label="Dispatch Shipment" 
                        :loading="fulfillLoading" 
                        @click="fulfill"
                        class="!px-10 !h-12 !bg-white hover:!bg-zinc-200 !text-zinc-950 font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_30px_rgba(255,255,255,0.1)] transition-all"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Return Dialog -->
        <Dialog v-model:visible="returnDialog" modal :header="null" :closable="false" :style="{ width: '65rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-zinc-950 border border-amber-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5 shadow-[0_0_50px_rgba(245,158,11,0.1)]">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-amber-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-amber-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                            <i class="pi pi-backward text-amber-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-white tracking-tight font-mono uppercase">Sales Return (RMA) Core</h2>
                            <p class="text-[9px] text-zinc-500 font-black uppercase tracking-widest mt-0.5">Reverse fulfilled orders and issue credits</p>
                        </div>
                    </div>
                    <button @click="returnDialog = false" class="w-8 h-8 rounded-full bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white hover:bg-zinc-800 transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-amber-500/5 border border-amber-500/20 p-4 rounded-2xl flex items-start gap-3">
                        <i class="pi pi-exclamation-triangle text-amber-500 mt-0.5"></i>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-amber-400 uppercase tracking-widest font-mono mb-1">RMA Protocol Enforcement</span>
                            <p class="text-[11px] text-zinc-400 font-medium leading-relaxed font-mono uppercase tracking-tight">
                                Processing a return creates an <span class="text-white font-bold bg-zinc-900 px-2 py-0.5 rounded">SRMA</span> reversal movement. System will automatically restore inventory to picking bins and calculate credit note liabilities.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 bg-zinc-900/40 p-4 rounded-2xl border border-zinc-800/50">
                        <div class="flex flex-col gap-2">
                            <label class="text-[9px] font-black text-zinc-600 tracking-[0.2em] uppercase font-mono">Receiving Location (Destination Bin)</label>
                            <Select 
                                v-model="returnForm.location_id" 
                                :options="locations" 
                                optionLabel="name" 
                                optionValue="id" 
                                placeholder="Select active bin" 
                                filter 
                                class="!w-full !bg-zinc-950 !border-zinc-800 !rounded-xl !h-11 !text-xs !font-bold text-white shadow-inner"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[9px] font-black text-zinc-600 tracking-[0.2em] uppercase font-mono">Return Notes / Memo</label>
                            <InputText v-model="returnForm.notes" placeholder="Detailed reason for global return..." class="!w-full !bg-zinc-950 !border-zinc-800 !rounded-xl !h-11 !px-4 !text-xs !font-bold text-white focus:!border-amber-500/50 transition-all shadow-inner" />
                        </div>
                    </div>
                
                    <DataTable :value="returnForm.lines" class="p-datatable-sm" scrollable scrollHeight="300px">
                        <Column field="product_name" header="PRODUCT/ID">
                             <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-amber-500/70 font-bold uppercase tracking-widest">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="FULFILLED">
                            <template #body="{ data }">
                                <div class="flex flex-col items-center">
                                    <span class="text-[11px] font-mono font-black text-zinc-200">{{ data.shipped_qty }}</span>
                                    <span class="text-[8px] font-black text-zinc-600 uppercase tracking-tighter">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QTY TO RETURN" style="width: 140px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-zinc-950 border border-zinc-800 rounded-xl focus-within:border-amber-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_return" 
                                        :min="0" 
                                        :max="data.shipped_qty" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-amber-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#f59e0b', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                </div>
                            </template>
                        </Column>
                        <Column header="RESOLUTION" style="width: 170px">
                            <template #body="{ data }">
                                <Select v-model="data.resolution" :options="[{label:'Replacement',value:'replacement'},{label:'Refund / Credit',value:'refund'}]" optionLabel="label" optionValue="value" class="!w-full !bg-zinc-950 !border-zinc-800 !rounded-xl !text-[10px] !h-10 shadow-inner focus:!border-amber-500/50 transition-all font-bold text-white uppercase tracking-widest" />
                            </template>
                        </Column>
                        <Column header="REASON / CONDITION" style="width: 200px">
                            <template #body="{ data }">
                                <InputText v-model="data.reason" placeholder="e.g. Defective, Wrong Item" class="!bg-zinc-950 !border-zinc-800 !rounded-xl !text-[10px] !h-10 w-full !px-3 shadow-inner focus:!border-amber-500/50 transition-all" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-amber-900/10 bg-zinc-950/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="returnDialog = false" class="p-button-text !text-zinc-500 hover:!text-white uppercase font-mono font-black tracking-widest text-[11px]" />
                    <Button 
                        label="Execute Return" 
                        :loading="returnLoading" 
                        @click="submitReturn"
                        class="!px-10 !h-12 !bg-amber-500 hover:!bg-amber-400 !text-zinc-950 font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_20px_rgba(245,158,11,0.3)]"
                    />
                </div>
            </div>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: transparent;
    border-bottom: 1px solid rgba(20, 184, 166, 0.1);
    color: #52525b;
    font-size: 8px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.25em;
    padding: 1rem 0.75rem;
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid rgba(39, 39, 42, 0.5);
    padding: 1rem 0.75rem;
}
:deep(.p-dialog) {
    background: #09090b;
    border: 1px solid #27272a;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
:deep(.p-dialog-header) {
    background: #09090b;
    color: white;
    padding: 1.5rem 1.5rem 1rem 1.5rem;
}
:deep(.p-dialog-content) {
    background: #09090b;
    padding: 0 1.5rem 1.5rem 1.5rem;
}
.no-print {
    @media print {
        display: none !important;
    }
}
</style>
