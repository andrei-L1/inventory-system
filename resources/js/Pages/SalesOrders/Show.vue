<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
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

const approveLoading = ref(false);
const sendLoading = ref(false);
const pickLoading = ref(false);
const packLoading = ref(false);
const shipLoading = ref(false);

const shipDialog = ref(false);
const shipForm = ref({
    carrier: '',
    tracking_number: '',
    lines: []
});

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
        'quotation_sent': 'warning',
        'confirmed': 'info',
        'picked': 'help',
        'packed': 'help',
        'partially_shipped': 'help',
        'shipped': 'success',
        'closed': 'success',
        'cancelled': 'danger'
    };
    return map[statusName] || 'info';
};

const formatStatus = (status) => {
    return status ? status.replace(/_/g, ' ').toUpperCase() : 'UNKNOWN';
}

const approve = async () => {
    confirm.require({
        message: 'Confirming this order will RESERVE the required stock from inventory. Proceed?',
        header: 'Confirm Order',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                approveLoading.value = true;
                await axios.patch(`/api/sales-orders/${so.value.id}/approve`);
                toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Stock has been reserved.', life: 3000 });
                loadSO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Confirmation failed', life: 3000 });
            } finally {
                approveLoading.value = false;
            }
        }
    });
};

const sendQuote = async () => {
    try {
        sendLoading.value = true;
        await axios.patch(`/api/sales-orders/${so.value.id}/send`);
        toast.add({ severity: 'success', summary: 'Sent', detail: 'Quotation marked as sent.', life: 3000 });
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to mark as sent.', life: 3000 });
    } finally {
        sendLoading.value = false;
    }
};

const markPicked = async () => {
    try {
        pickLoading.value = true;
        await axios.patch(`/api/sales-orders/${so.value.id}/pick`);
        toast.add({ severity: 'success', summary: 'Picked', detail: 'Items marked as picked.', life: 3000 });
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Pick failed', life: 3000 });
    } finally {
        pickLoading.value = false;
    }
};

const markPacked = async () => {
    try {
        packLoading.value = true;
        await axios.patch(`/api/sales-orders/${so.value.id}/pack`);
        toast.add({ severity: 'success', summary: 'Packed', detail: 'Items marked as packed.', life: 3000 });
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Pack failed', life: 3000 });
    } finally {
        packLoading.value = false;
    }
};

const openShipDialog = () => {
    shipForm.value.lines = so.value.lines
        .filter(l => l.pending_qty > 0)
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            pending_qty: l.pending_qty,
            fulfill_qty: l.pending_qty,
            uom: l.uom
        }));
    shipDialog.value = true;
};

const submitShipment = async () => {
    if (!shipForm.value.carrier) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Carrier is required', life: 3000 });
        return;
    }

    shipLoading.value = true;
    try {
        await axios.post(`/api/sales-orders/${so.value.id}/ship`, shipForm.value);
        toast.add({ severity: 'success', summary: 'Shipped', detail: 'Order fulfilled and shipped.', life: 3000 });
        shipDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Fulfillment failed', life: 5000 });
    } finally {
        shipLoading.value = false;
    }
};

const cancelOrder = async () => {
    confirm.require({
        message: 'Are you sure you want to cancel this order? All active stock reservations will be released.',
        header: 'Cancel Order',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.patch(`/api/sales-orders/${so.value.id}/cancel`);
                toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Order cancelled.', life: 3000 });
                loadSO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Cancellation failed.', life: 3000 });
            }
        }
    });
};
</script>

<template>
    <Head :title="so ? so.so_number : 'Loading...'" />
    <AppLayout v-if="so">
        <div class="h-full flex flex-col gap-6">

            <!-- Header Panel -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 blur-[100px] pointer-events-none"></div>

                <div class="flex items-center gap-4 z-10">
                    <button @click="router.visit('/sales-orders')" class="w-10 h-10 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-colors hover:border-zinc-600">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-white text-2xl font-black tracking-tight font-mono">{{ so.so_number }}</h1>
                            <Tag 
                                :severity="getStatusColor(so.status)" 
                                :value="formatStatus(so.status)" 
                                class="text-[9px] font-bold tracking-widest font-mono uppercase px-2 py-0.5 rounded shadow-[inset_0_1px_4px_rgba(0,0,0,0.5)]"
                            />
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono">
                            <span @click="router.visit(`/customer-center?customer_id=${so.customer_id}`)" class="text-zinc-500 hover:text-teal-400 cursor-pointer transition-colors">{{ so.customer_name }}</span>
                            <span class="text-zinc-700 mx-2">&bull;</span>
                            <span class="text-zinc-500">Total: ₱{{ Number(so.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10">
                    <!-- Workflow Actions -->
                    <Button 
                        v-if="so.status === 'quotation' && can('manage-sales-orders')" 
                        label="Send Quotation" 
                        icon="pi pi-envelope" 
                        :loading="sendLoading"
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="sendQuote"
                    />

                    <Button 
                        v-if="['quotation', 'quotation_sent'].includes(so.status) && can('manage-sales-orders')" 
                        label="Confirm Order" 
                        icon="pi pi-check" 
                        :loading="approveLoading"
                        class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(20,184,166,0.3)] tracking-widest uppercase font-mono transition-all" 
                        @click="approve"
                    />

                    <Button 
                        v-if="so.status === 'confirmed' && can('manage-sales-orders')" 
                        label="Mark Picked" 
                        icon="pi pi-box" 
                        :loading="pickLoading"
                        class="p-button-sm !bg-zinc-800 hover:!bg-teal-900/40 !text-teal-400 !border-teal-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="markPicked"
                    />

                    <Button 
                        v-if="so.status === 'picked' && can('manage-sales-orders')" 
                        label="Mark Packed" 
                        icon="pi pi-shopping-bag" 
                        :loading="packLoading"
                        class="p-button-sm !bg-zinc-800 hover:!bg-teal-900/40 !text-teal-400 !border-teal-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="markPacked"
                    />

                    <Button 
                        v-if="['confirmed', 'picked', 'packed', 'partially_shipped'].includes(so.status) && can('manage-sales-orders')" 
                        label="Ship / Fulfill" 
                        icon="pi pi-truck" 
                        class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(20,184,166,0.3)] tracking-widest uppercase font-mono transition-all" 
                        @click="openShipDialog"
                    />

                    <Button 
                        v-if="!['shipped', 'closed', 'cancelled'].includes(so.status) && can('manage-sales-orders')" 
                        icon="pi pi-times" 
                        class="p-button-danger p-button-text p-button-sm !font-bold" 
                        @click="cancelOrder"
                        title="Cancel Order"
                    />
                </div>
            </div>

            <!-- Dashboard / Details -->
            <div class="grid grid-cols-12 gap-6">
                <!-- Sidebar Info -->
                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-5">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3">Logistics & Identity</span>
                        
                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Created By</span>
                            <span class="text-xs font-bold text-white">{{ so.created_by_name || 'System' }}</span>
                        </div>
                        <div v-if="so.confirmed_at" class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Confirmed At</span>
                            <span class="text-xs font-bold text-teal-400">{{ so.confirmed_at }}</span>
                        </div>
                        <div v-if="so.shipped_at" class="flex flex-col gap-2 py-2 border-b border-zinc-800/30">
                            <div class="flex justify-between items-center text-orange-400 font-bold">
                                <span class="text-[10px] font-mono tracking-widest uppercase">Latest Shipment</span>
                                <span class="text-xs">{{ so.shipped_at }}</span>
                            </div>
                            <div class="flex flex-col gap-1 p-2 bg-zinc-950 rounded border border-zinc-800">
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-zinc-600 uppercase tracking-tighter">Carrier:</span>
                                    <span class="text-zinc-300">{{ so.carrier }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-zinc-600 uppercase tracking-tighter">Tracking:</span>
                                    <span class="text-sky-500 font-mono">{{ so.tracking_number }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="so.notes" class="flex flex-col gap-2 pt-2">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Notes / Instructions</span>
                            <p class="text-xs text-zinc-400 leading-relaxed bg-zinc-950/50 p-3 rounded-lg border border-zinc-800/50">{{ so.notes }}</p>
                        </div>
                    </div>

                    <!-- Shipment History -->
                    <div v-if="so.shipments && so.shipments.length > 0" class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-5">
                        <span class="text-[10px] font-bold text-teal-500 uppercase tracking-widest font-mono border-b border-white/5 pb-3">Shipment Log</span>
                        <div v-for="shipment in so.shipments" :key="shipment.id" class="p-3 bg-zinc-950 border border-zinc-800 rounded-xl relative group overflow-hidden">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[10px] font-mono font-bold text-teal-400">{{ shipment.tracking_number || 'N/A' }}</span>
                                <Tag severity="info" :value="shipment.status.toUpperCase()" class="text-[8px] font-mono" />
                            </div>
                            <div class="flex flex-col gap-1 text-[10px]">
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Carrier:</span>
                                    <span class="text-zinc-400">{{ shipment.carrier }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Date:</span>
                                    <span class="text-zinc-400">{{ shipment.shipped_at }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mission Control (Lines) -->
                <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl flex flex-col overflow-hidden shadow-xl p-6">
                        <div class="flex items-center justify-between border-b border-zinc-800/50 pb-3 mb-4">
                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Fulfillment Status</span>
                            <div class="flex gap-4">
                                <div class="flex flex-col items-end">
                                    <span class="text-[9px] font-bold text-zinc-600 uppercase font-mono">Picked</span>
                                    <span class="text-xs font-black text-white">{{ so.lines.reduce((acc, l) => acc + Number(l.picked_qty), 0) }} / {{ so.lines.reduce((acc, l) => acc + Number(l.ordered_qty), 0) }}</span>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="text-[9px] font-bold text-zinc-600 uppercase font-mono">Packed</span>
                                    <span class="text-xs font-black text-white">{{ so.lines.reduce((acc, l) => acc + Number(l.packed_qty), 0) }} / {{ so.lines.reduce((acc, l) => acc + Number(l.ordered_qty), 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <DataTable :value="so.lines" class="p-datatable-sm w-full" stripedRows>
                            <Column field="sku" header="SKU" style="width: 10rem">
                                <template #body="{ data }">
                                    <span class="text-teal-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                                </template>
                            </Column>
                            <Column field="product_name" header="PRODUCT">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                        <span class="text-[9px] font-bold text-zinc-600 font-mono tracking-widest uppercase">Target: {{ data.location_name }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="PLAN">
                                <template #body="{ data }">
                                    <span class="text-white font-mono text-xs font-bold">{{ data.formatted_ordered_qty }}</span>
                                </template>
                            </Column>
                            <Column header="PICKED">
                                <template #body="{ data }">
                                    <span :class="[data.picked_qty >= data.ordered_qty ? 'text-teal-400' : 'text-amber-500', 'font-mono text-xs font-bold']">{{ data.picked_qty }}</span>
                                </template>
                            </Column>
                            <Column header="PACKED">
                                <template #body="{ data }">
                                    <span :class="[data.packed_qty >= data.ordered_qty ? 'text-teal-400' : 'text-amber-500', 'font-mono text-xs font-bold']">{{ data.packed_qty }}</span>
                                </template>
                            </Column>
                            <Column header="SHIPPED">
                                <template #body="{ data }">
                                    <span :class="[data.shipped_qty >= data.ordered_qty ? 'text-emerald-400' : 'text-zinc-600', 'font-mono text-xs font-bold']">{{ data.shipped_qty }}</span>
                                </template>
                            </Column>
                            <Column header="TOTAL VALUE">
                                <template #body="{ data }">
                                    <span class="text-zinc-300 font-mono text-xs font-bold">₱{{ Number(data.total_line_amount).toFixed(2) }}</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <!-- Issue History (ISS) -->
                    <div v-if="so.transactions && so.transactions.length > 0" class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3 mb-4 block">Stock Issue Details (ISS)</span>
                        <div class="flex flex-col gap-4">
                            <div v-for="tx in so.transactions" :key="tx.id" class="p-4 bg-zinc-950 border border-zinc-900 rounded-xl">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-[10px] font-mono font-bold text-sky-400">{{ tx.reference_number }}</span>
                                    <span class="text-[9px] font-mono text-zinc-600">{{ tx.transaction_date }}</span>
                                </div>
                                <DataTable :value="tx.lines" class="p-datatable-sm w-full" :size="'small'">
                                    <Column field="product_name" header="PRODUCT" headerClass="!text-[8px] !p-2" bodyClass="!text-[10px] !p-2"></Column>
                                    <Column field="quantity" header="QTY" headerClass="!text-[8px] !p-2" bodyClass="!text-[10px] !p-2">
                                        <template #body="{ data }">
                                            <span class="text-orange-400 font-bold">{{ Math.abs(data.quantity) }}</span>
                                        </template>
                                    </Column>
                                    <Column field="uom" header="UOM" headerClass="!text-[8px] !p-2" bodyClass="!text-[10px] !p-2"></Column>
                                </DataTable>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ship Dialog -->
        <Dialog v-model:visible="shipDialog" modal header="Fulfill & Ship Order" :style="{ width: '50rem' }">
            <div class="flex flex-col gap-6 py-2">
                <div class="bg-teal-500/10 border border-teal-500/20 p-4 rounded-xl flex items-start gap-4">
                    <i class="pi pi-info-circle text-teal-400 mt-0.5"></i>
                    <p class="text-[11px] text-teal-400 leading-relaxed font-bold">
                        Fulfilling items will RELEASE the stock reservation and issue an 'ISS' movement from your inventory. This action calculates COGS and updates stock integrity.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Carrier</label>
                        <InputText v-model="shipForm.carrier" placeholder="DHL, FedEx, Lalamove..." class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50 h-10" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Tracking Number</label>
                        <InputText v-model="shipForm.tracking_number" placeholder="Enter tracking ID" class="w-full bg-zinc-950 border-zinc-800 text-sm focus:border-teal-500/50 h-10" />
                    </div>
                </div>

                <div class="border border-zinc-800 rounded-xl overflow-hidden">
                    <DataTable :value="shipForm.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-teal-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <span class="text-white font-bold text-[11px]">{{ data.product_name }}</span>
                            </template>
                        </Column>
                        <Column field="pending_qty" header="REMAINING">
                            <template #body="{ data }">
                                <span class="text-zinc-500 font-mono text-xs font-bold">{{ data.pending_qty }}</span>
                            </template>
                        </Column>
                        <Column header="FULFILL QTY" style="width: 12rem">
                            <template #body="{ data }">
                                <div class="flex items-center bg-zinc-950 border border-zinc-800 rounded-lg overflow-hidden focus-within:border-teal-500/50">
                                    <InputNumber v-model="data.fulfill_qty" :min="0" :max="data.pending_qty" class="w-full" inputClass="w-full bg-transparent border-none text-center text-white p-2 text-xs outline-none" />
                                    <span class="px-3 text-[10px] bg-zinc-900 text-zinc-500 border-l border-zinc-800 font-bold uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button label="Cancel" class="p-button-text !text-zinc-500" @click="shipDialog = false" />
                    <Button label="Post Fulfillment" icon="pi pi-truck" :loading="shipLoading" @click="submitShipment" class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-zinc-950 font-bold tracking-widest uppercase" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: #18181b;
    border-bottom: 1px solid rgba(39, 39, 42, 0.8);
    color: #a1a1aa;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0.75rem 1rem;
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid rgba(39, 39, 42, 0.5);
    color: #e4e4e7;
    padding: 0.75rem 1rem;
}
:deep(.p-dialog-header), :deep(.p-dialog-content) {
    background: #18181b;
    color: white;
}
:deep(.p-dialog-header-title) {
    font-size: 1.1rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-family: monospace;
}
:deep(.p-tag) {
    background: rgba(39, 39, 42, 0.8);
}
:deep(.p-tag.p-tag-warning) { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
:deep(.p-tag.p-tag-info) { background: rgba(14, 165, 233, 0.1); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.2); }
:deep(.p-tag.p-tag-success) { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
:deep(.p-tag.p-tag-danger) { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
:deep(.p-tag.p-tag-help) { background: rgba(139, 92, 246, 0.1); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.2); }
</style>
