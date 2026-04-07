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

// Removed Popover toggle logic in favor of always-visible breakdown

const isQuotation = computed(() => so.value?.status?.name === 'quotation' || so.value?.status?.name === 'quotation_sent');
const canCancel = computed(() => so.value && !['shipped', 'cancelled', 'closed'].includes(so.value.status.name));

const getTotalAvailable = (line) => {
    return line.availability?.reduce((sum, loc) => sum + loc.available_qty, 0) || 0;
};

const getAvailabilityStatus = (line) => {
    const total = getTotalAvailable(line);
    if (total >= line.ordered_qty) return { severity: 'success', label: 'In Stock' };
    if (total > 0) return { severity: 'warn', label: 'Limited' };
    return { severity: 'danger', label: 'Out of Stock' };
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

onMounted(() => {
    loadSO();
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
        .filter(l => l.picked_qty < l.ordered_qty)
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            ordered_qty: l.ordered_qty,
            picked_qty: l.picked_qty, // already picked
            to_pick: l.remaining_pick_qty, // from accessor
            uom: l.uom?.abbreviation
        }));
    pickDialog.value = true;
};

const submitPick = async () => {
    try {
        pickLoading.value = true;
        const payload = {
            lines: pickForm.value.lines.filter(l => l.to_pick > 0).map(l => ({
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
        .filter(l => l.packed_qty < l.picked_qty)
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            picked_qty: l.picked_qty,
            packed_qty: l.packed_qty,
            to_pack: l.remaining_pack_qty,
            uom: l.uom?.abbreviation
        }));
    packDialog.value = true;
};

const submitPack = async () => {
    try {
        packLoading.value = true;
        const payload = {
            lines: packForm.value.lines.filter(l => l.to_pack > 0).map(l => ({
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
        .filter(l => l.shipped_qty < l.packed_qty)
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            packed_qty: l.packed_qty,
            shipped_qty: l.shipped_qty,
            to_ship: l.remaining_ship_qty,
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
            lines: shipForm.value.lines.filter(l => l.to_ship > 0).map(l => ({
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

const canApprove = computed(() => so.value?.status?.name === 'quotation' || so.value?.status?.name === 'quotation_sent');
const canPick = computed(() => so.value?.status?.name === 'confirmed' || so.value?.status?.name === 'partially_picked');
const canPack = computed(() => ['picked', 'partially_picked', 'partially_packed'].includes(so.value?.status?.name));
const canShip = computed(() => ['packed', 'partially_packed', 'partially_shipped'].includes(so.value?.status?.name));

const subtotal = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + (line.ordered_qty * line.unit_price), 0) || 0;
});

const totalTax = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + (line.tax_amount || 0), 0) || 0;
});

const totalDiscount = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + (line.discount_amount || 0), 0) || 0;
});


</script>

<template>
    <Head :title="so ? so.so_number : 'Loading...'" />
    <AppLayout v-if="so">
        <div class="h-full flex flex-col gap-4">

            <!-- Header Panel -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 blur-[100px] pointer-events-none"></div>

                <div class="flex items-center gap-4 z-10">
                    <button @click="router.visit('/sales-orders')" class="w-10 h-10 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-colors hover:border-zinc-600">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-white text-2xl font-black tracking-tight font-mono">{{ so.so_number }}</h1>
                            <Tag 
                                :severity="getStatusColor(so.status.name)" 
                                :value="so.status.name.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-bold tracking-widest font-mono uppercase px-2 py-0.5 rounded shadow-[inset_0_1px_4px_rgba(0,0,0,0.5)]"
                            />
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono">
                            <span @click="router.visit(`/customer-center?customer_id=${so.customer_id}`)" class="text-zinc-500 hover:text-teal-400 cursor-pointer transition-colors">{{ so.customer_name }}</span>
                            <span class="text-zinc-700 mx-2">&bull;</span>
                            <span class="text-zinc-500">₱{{ Number(so.total_amount).toFixed(2) }}</span>
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
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-teal-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-2">Financial Summary</span>
                        
                        <div class="flex justify-between items-center py-0.5">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono uppercase">Untaxed Amount</span>
                            <span class="text-xs font-bold text-white">₱{{ Number(so.subtotal || 0).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono uppercase">Tax Amount</span>
                            <span class="text-xs font-bold text-white">₱{{ Number(so.total_tax || 0).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 text-emerald-400">
                            <span class="text-[10px] font-bold font-mono uppercase">Total</span>
                            <span class="text-sm font-black">₱{{ Number(so.total_amount || 0).toFixed(2) }}</span>
                        </div>
                    </div>

                    <!-- Fulfillment History -->
                    <div v-if="so.transactions && so.transactions.length > 0" class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-sky-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-2 text-center">Fulfillment History</span>
                        
                        <div v-for="tx in so.transactions" :key="tx.id" class="flex flex-col gap-1.5 p-3 bg-zinc-950/50 rounded-xl border border-zinc-800/50 group transition-all hover:border-sky-500/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-mono text-sky-400 font-bold uppercase">{{ tx.reference_number }}</span>
                                <span class="text-[9px] font-mono text-zinc-600">{{ tx.transaction_date }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[9px] font-bold text-zinc-500 uppercase">Status</span>
                                <Tag :severity="tx.status.name === 'posted' ? 'success' : 'warning'" :value="tx.status.name.toUpperCase()" class="text-[8px] font-bold px-1.5 py-0.5" />
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
                                                    <span :class="loc.available_qty > 0 ? 'text-teal-400' : 'text-zinc-600'" class="text-[10px] font-mono font-bold">{{ loc.available_qty }}</span>
                                                    <span v-if="loc.reserved_qty > 0" class="text-[8px] text-amber-500/60 font-mono">({{ loc.reserved_qty }} RSV)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-2 bg-rose-500/5 border border-rose-500/10 rounded-lg">
                                            <span class="text-[8px] font-bold text-rose-400 uppercase tracking-widest">OUT OF STOCK</span>
                                        </div>
                                        
                                        <div class="flex justify-between px-1">
                                            <span class="text-[9px] font-bold text-zinc-600 uppercase font-mono tracking-widest">Total</span>
                                            <span class="text-[10px] font-black" :class="getTotalAvailable(data) > 0 ? 'text-white' : 'text-red-500/50'">{{ getTotalAvailable(data) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column header="LIFECYCLE STATUS">
                                <template #body="{ data }">
                                    <div class="flex flex-col gap-1.5 w-32">
                                        <div class="h-1.5 bg-zinc-950 rounded-full overflow-hidden flex border border-zinc-800/50">
                                            <div :style="{ width: (data.picked_qty / data.ordered_qty * 100) + '%' }" class="h-full bg-help-500/30 transition-all duration-500"></div>
                                            <div :style="{ width: (data.shipped_qty / data.ordered_qty * 100) + '%' }" class="h-full bg-teal-500/70 transition-all duration-700 shadow-[0_0_5px_rgba(20,184,166,0.5)]"></div>
                                        </div>
                                        <div class="flex justify-between items-center px-0.5">
                                            <span class="text-[8px] font-bold text-zinc-500 font-mono uppercase tracking-tighter">Processed</span>
                                            <span class="text-[9px] font-black text-zinc-300 font-mono">{{ Math.round(data.shipped_qty / data.ordered_qty * 100) }}%</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column field="ordered_qty" header="ORDERED">
                                <template #body="{ data }">
                                    <span class="text-zinc-300 font-mono text-xs font-bold">{{ data.ordered_qty }}</span>
                                </template>
                            </Column>
                            <Column field="picked_qty" header="PICKED">
                                <template #body="{ data }">
                                    <span :class="data.picked_qty >= data.ordered_qty ? 'text-help-400' : 'text-zinc-500'" class="font-mono text-xs font-bold">
                                        {{ data.picked_qty }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="shipped_qty" header="FULFILLED">
                                <template #body="{ data }">
                                    <span :class="data.shipped_qty >= data.ordered_qty ? 'text-emerald-400' : 'text-amber-400'" class="font-mono text-xs font-bold">
                                        {{ data.shipped_qty }}
                                    </span>
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
                    <DataTable :value="so.transactions" class="p-datatable-sm w-full">
                        <Column field="reference_number" header="MOVEMENT REF">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold uppercase">{{ data.reference_number }}</span>
                            </template>
                        </Column>
                        <Column field="transaction_date" header="DATE" />
                        <Column field="display_type" header="TYPE">
                            <template #body="{ data }">
                                <span class="text-[10px] font-bold text-zinc-500 uppercase">{{ data.display_type }}</span>
                            </template>
                        </Column>
                        <Column field="from_location_name" header="EXPORT FROM" />
                        <Column field="notes" header="MEMO">
                            <template #body="{ data }">
                                <span class="text-[10px] text-zinc-500 italic">{{ data.notes }}</span>
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
        <Dialog v-model:visible="pickDialog" modal header="Inventory Picking Control" :style="{ width: '50rem' }">
            <div class="flex flex-col gap-4 py-4">
                <div class="p-4 bg-help-500/10 border border-help-500/20 rounded-xl mb-2">
                    <p class="text-xs text-help-400 font-medium leading-relaxed font-mono uppercase tracking-wider">
                        Mission: Move items from storage locations to the staging area for packing.
                    </p>
                </div>
                
                <DataTable :value="pickForm.lines" class="p-datatable-sm overflow-hidden" scrollable scrollHeight="300px">
                    <Column field="product_name" header="PRODUCT">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="text-white font-bold text-[11px]">{{ data.product_name }}</span>
                                <span class="text-[9px] font-mono text-zinc-500 uppercase">{{ data.sku }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="PROGRESS">
                        <template #body="{ data }">
                            <span class="text-[10px] font-mono text-zinc-500 bg-zinc-950 px-2 py-0.5 rounded border border-zinc-800">
                                {{ data.picked_qty }} / {{ data.ordered_qty }}
                            </span>
                        </template>
                    </Column>
                    <Column header="QTY TO PICK">
                        <template #body="{ data }">
                            <InputNumber v-model="data.to_pick" :min="0" :max="data.ordered_qty - data.picked_qty" :suffix="' ' + data.uom" class="w-24 p-inputtext-sm text-xs font-mono" inputClass="!bg-zinc-950 !border-zinc-800 !text-white text-center" />
                        </template>
                    </Column>
                </DataTable>

                <div class="flex justify-end gap-3 mt-4 border-t border-zinc-800 pt-4">
                    <Button label="Cancel" class="p-button-text !text-zinc-500 uppercase font-mono font-bold tracking-widest text-[10px]" @click="pickDialog = false" />
                    <Button label="Complete Pick Selection" :loading="pickLoading" class="!bg-teal-500 !border-none !text-zinc-950 font-bold uppercase font-mono tracking-widest text-[10px]" @click="submitPick" />
                </div>
            </div>
        </Dialog>

        <!-- Pack Dialog -->
        <Dialog v-model:visible="packDialog" modal header="Packing & Quality Verification" :style="{ width: '50rem' }">
            <div class="flex flex-col gap-4 py-4">
                <div class="p-4 bg-indigo-500/10 border border-indigo-500/20 rounded-xl mb-2">
                    <p class="text-xs text-indigo-400 font-medium leading-relaxed font-mono uppercase tracking-wider">
                        Mission: Verify picked items and box them for final shipment.
                    </p>
                </div>
                
                <DataTable :value="packForm.lines" class="p-datatable-sm" scrollable scrollHeight="300px">
                    <Column field="product_name" header="PRODUCT">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="text-white font-bold text-[11px]">{{ data.product_name }}</span>
                                <span class="text-[9px] font-mono text-zinc-500 uppercase">{{ data.sku }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="PICKED">
                        <template #body="{ data }">
                            <span class="text-[10px] font-mono text-zinc-400">{{ data.picked_qty }} {{ data.uom }}</span>
                        </template>
                    </Column>
                    <Column header="QTY TO PACK">
                        <template #body="{ data }">
                            <InputNumber v-model="data.to_pack" :min="0" :max="data.picked_qty - data.packed_qty" :suffix="' ' + data.uom" class="w-24 p-inputtext-sm text-xs font-mono" inputClass="!bg-zinc-950 !border-zinc-800 !text-white text-center" />
                        </template>
                    </Column>
                </DataTable>

                <div class="flex justify-end gap-3 mt-4 border-t border-zinc-800 pt-4">
                    <Button label="Cancel" class="p-button-text !text-zinc-500 uppercase font-mono font-bold tracking-widest text-[10px]" @click="packDialog = false" />
                    <Button label="Verify & Pack" :loading="packLoading" class="!bg-teal-500 !border-none !text-zinc-950 font-bold uppercase font-mono tracking-widest text-[10px]" @click="submitPack" />
                </div>
            </div>
        </Dialog>

        <!-- Ship Dialog -->
        <Dialog v-model:visible="shipDialog" modal header="Final Fulfillment & Logistics" :style="{ width: '55rem' }">
            <div class="flex flex-col gap-4 py-4">
                <div class="p-4 bg-teal-500/10 border border-teal-500/20 rounded-xl mb-2">
                    <p class="text-xs text-teal-400 font-medium leading-relaxed font-mono uppercase tracking-wider">
                        Mission: Handover packed boxes to carrier and finalize stock issue.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] uppercase font-mono">Carrier Service</label>
                        <InputText v-model="shipForm.carrier" placeholder="e.g. FedEx, Internal" class="w-full bg-zinc-950 border-zinc-800 text-xs text-white" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-bold text-zinc-500 tracking-[0.2em] uppercase font-mono">Tracking Reference</label>
                        <InputText v-model="shipForm.tracking_number" placeholder="Optional tracking #" class="w-full bg-zinc-950 border-zinc-800 text-xs text-white" />
                    </div>
                </div>
                
                <DataTable :value="shipForm.lines" class="p-datatable-sm" scrollable scrollHeight="250px">
                    <Column field="product_name" header="PRODUCT" />
                    <Column header="PACKED">
                        <template #body="{ data }">
                            <span class="text-[10px] font-mono text-zinc-400">{{ data.packed_qty }} {{ data.uom }}</span>
                        </template>
                    </Column>
                    <Column header="QTY TO SHIP">
                        <template #body="{ data }">
                            <InputNumber v-model="data.to_ship" :min="0" :max="data.packed_qty - data.shipped_qty" :suffix="' ' + data.uom" class="w-24 p-inputtext-sm text-xs font-mono" inputClass="!bg-zinc-950 !border-zinc-800 !text-white text-center" />
                        </template>
                    </Column>
                </DataTable>

                <div class="flex justify-end gap-3 mt-4 border-t border-zinc-800 pt-4">
                    <Button label="Cancel" class="p-button-text !text-zinc-500 uppercase font-mono font-bold tracking-widest text-[10px]" @click="shipDialog = false" />
                    <Button label="Dispatch Shipment" :loading="fulfillLoading" class="!bg-teal-500 !border-none !text-zinc-950 font-bold uppercase font-mono tracking-widest text-[10px]" @click="fulfill" />
                </div>
            </div>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: #09090b;
    border-bottom: 1px solid rgba(39, 39, 42, 0.8);
    color: #52525b;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid rgba(39, 39, 42, 0.5);
    padding: 0.5rem 0.75rem;
}
:deep(.p-dialog) {
    background: #18181b;
    border: 1px solid #27272a;
    border-radius: 20px;
}
:deep(.p-dialog-header) {
    background: #18181b;
    color: white;
    border-bottom: 1px solid #27272a;
}
:deep(.p-dialog-content) {
    background: #18181b;
}
</style>
