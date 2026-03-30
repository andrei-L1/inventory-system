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
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ id: [String, Number] });
const { can } = usePermissions();
const confirm = useConfirm();
const toast = useToast();

const po = ref(null);
const loading = ref(true);

const approveLoading = ref(false);
const sendLoading = ref(false);
const shipLoading = ref(false);
const grnDialog = ref(false);
const grnDetailDialog = ref(false);
const selectedGrn = ref(null);
const shipDialog = ref(false);
const returnDialog = ref(false);
const grnLoading = ref(false);
const returnLoading = ref(false);
const locations = ref([]);
const grnForm = ref({
    location_id: null,
    lines: []
});
const returnForm = ref({
    location_id: null,
    lines: []
});
const shipForm = ref({
    carrier: '',
    tracking_number: ''
});

const loadPO = async () => {
    try {
        const res = await axios.get(`/api/purchase-orders/${props.id}`);
        po.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load PO', life: 3000 });
        router.visit('/purchase-orders');
    } finally {
        loading.value = false;
    }
};

const loadLocations = async () => {
    try {
        const res = await axios.get('/api/locations?limit=1000');
        locations.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
};

onMounted(() => {
    loadPO();
    loadLocations();
});

const getStatusColor = (statusName) => {
    const map = { 
        'draft': 'warning', 
        'open': 'info', 
        'sent': 'info',
        'in_transit': 'help',
        'partially_received': 'help', 
        'closed': 'success', 
        'cancelled': 'danger' 
    };
    return map[statusName] || 'info';
};

const approve = async () => {
    confirm.require({
        message: 'Are you sure you want to approve this Purchase Order? It will be moved to Open status.',
        header: 'Confirm Approval',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                approveLoading.value = true;
                await axios.patch(`/api/purchase-orders/${po.value.id}/approve`);
                toast.add({ severity: 'success', summary: 'Approved', detail: 'PO is now Open', life: 3000 });
                loadPO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Approval failed', life: 3000 });
            } finally {
                approveLoading.value = false;
            }
        }
    });
};

const sendPO = async () => {
    try {
        sendLoading.value = true;
        await axios.patch(`/api/purchase-orders/${po.value.id}/send`);
        toast.add({ severity: 'success', summary: 'Success', detail: 'PO marked as Sent to Vendor', life: 3000 });
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Send failed', life: 3000 });
    } finally {
        sendLoading.value = false;
    }
};

const submitShipment = async () => {
    if (!shipForm.value.carrier) {
        toast.add({ severity: 'warn', summary: 'Warning', detail: 'Carrier is required', life: 3000 });
        return;
    }
    try {
        shipLoading.value = true;
        await axios.post(`/api/purchase-orders/${po.value.id}/ship`, shipForm.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Order marked as In Transit', life: 3000 });
        shipDialog.value = false;
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Shipment record failed', life: 3000 });
    } finally {
        shipLoading.value = false;
    }
};

const openGrnMode = () => {
    grnForm.value.location_id = null;
    grnForm.value.lines = po.value.lines
        .filter(l => l.pending_qty > 0)
        .map(l => ({
            po_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            pending_qty: l.pending_qty,
            received_qty: l.pending_qty // default to receiving all remaining
        }));
    
    if (grnForm.value.lines.length === 0) {
        toast.add({ severity: 'info', summary: 'Completed', detail: 'All lines are fully received', life: 3000 });
        return;
    }
    
    grnDialog.value = true;
};

const viewGrnDetails = (receipt) => {
    selectedGrn.value = receipt;
    grnDetailDialog.value = true;
};

const postReceipt = async () => {
    if (!grnForm.value.location_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Destination Location is required', life: 3000 });
        return;
    }

    const payloadLines = grnForm.value.lines.filter(l => l.received_qty > 0);
    if (payloadLines.length === 0) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'No quantities specified to receive', life: 3000 });
        return;
    }

    grnLoading.value = true;
    try {
        await axios.post(`/api/purchase-orders/${po.value.id}/receive`, {
            location_id: grnForm.value.location_id,
            lines: payloadLines.map(l => ({ po_line_id: l.po_line_id, received_qty: l.received_qty }))
        });
        toast.add({ severity: 'success', summary: 'Success', detail: 'Goods Receipt Note posted!', life: 3000 });
        grnDialog.value = false;
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'GRN failed', life: 3000 });
    } finally {
        grnLoading.value = false;
    }
};

const openReturnMode = () => {
    returnForm.value.location_id = null;
    returnForm.value.lines = po.value.lines
        .filter(l => l.received_qty > 0)
        .map(l => ({
            po_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            received_qty: l.received_qty,
            return_qty: 0,
            resolution: 'replacement',
            reason: ''
        }));
    
    if (returnForm.value.lines.length === 0) {
        toast.add({ severity: 'warn', summary: 'Cannot Return', detail: 'No items have been received for this PO.', life: 3000 });
        return;
    }
    
    returnDialog.value = true;
};

const postReturn = async () => {
    if (!returnForm.value.location_id) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Return Location is required', life: 3000 });
        return;
    }

    const payloadLines = returnForm.value.lines.filter(l => l.return_qty > 0);
    if (payloadLines.length === 0) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please specify items and quantities to return.', life: 3000 });
        return;
    }

    returnLoading.value = true;
    try {
        await axios.post(`/api/purchase-orders/${po.value.id}/return`, {
            location_id: returnForm.value.location_id,
            lines: payloadLines.map(l => ({ 
                po_line_id: l.po_line_id, 
                return_qty: l.return_qty,
                resolution: l.resolution,
                reason: l.reason
            }))
        });
        toast.add({ severity: 'success', summary: 'RTV Success', detail: 'Purchase Return processed successfully.', life: 3000 });
        returnDialog.value = false;
        loadPO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Return failed', life: 3000 });
    } finally {
        returnLoading.value = false;
    }
};

const deletePO = async () => {
    confirm.require({
        message: 'Permanently delete this draft PO?',
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/purchase-orders/${po.value.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'PO removed', life: 3000 });
                router.visit('/purchase-orders');
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Deletion failed', life: 3000 });
            }
        }
    });
};
</script>

<template>
    <Head :title="po ? po.po_number : 'Loading...'" />
    <AppLayout v-if="po">
        <div class="h-full flex flex-col gap-6">

            <!-- Header Panel -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-orange-500/5 blur-[100px] pointer-events-none"></div>

                <div class="flex items-center gap-4 z-10">
                    <button @click="router.visit('/purchase-orders')" class="w-10 h-10 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-colors hover:border-zinc-600">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-white text-2xl font-black tracking-tight font-mono">{{ po.po_number }}</h1>
                            <Tag 
                                :severity="getStatusColor(po.status)" 
                                :value="po.status.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-bold tracking-widest font-mono uppercase px-2 py-0.5 rounded shadow-[inset_0_1px_4px_rgba(0,0,0,0.5)]"
                            />
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono">
                            <span @click="router.visit(`/vendor-center?vendor_id=${po.vendor_id}`)" class="text-zinc-500 hover:text-sky-400 cursor-pointer transition-colors">{{ po.vendor_name }}</span>
                            <span class="text-zinc-700 mx-2">&bull;</span>
                            <span class="text-zinc-500">₱{{ Number(po.total_amount).toFixed(2) }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10">
                    <!-- Actions based on status -->
                    <Button 
                        v-if="po.status === 'draft' && can('manage-inventory')" 
                        icon="pi pi-trash" 
                        class="p-button-danger p-button-text p-button-sm !font-bold" 
                        @click="deletePO"
                    />
                    
                    <Button 
                        v-if="po.status === 'draft' && can('manage-inventory')" 
                        label="Approve Order" 
                        icon="pi pi-check" 
                        :loading="approveLoading"
                        class="p-button-sm !bg-emerald-500/20 hover:!bg-emerald-500/30 !text-emerald-400 !border-emerald-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="approve"
                    />

                    <Button 
                        v-if="po.status === 'open' && can('manage-inventory')" 
                        label="Send to Vendor" 
                        icon="pi pi-send" 
                        :loading="sendLoading"
                        class="p-button-sm !bg-sky-500/20 hover:!bg-sky-500/30 !text-sky-400 !border-sky-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="sendPO"
                    />

                    <Button 
                        v-if="['open', 'sent'].includes(po.status) && can('manage-inventory')" 
                        label="Mark Shipped" 
                        icon="pi pi-truck" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="shipDialog = true"
                    />

                    <Button 
                        v-if="['open', 'sent', 'in_transit', 'partially_received', 'closed'].some(s => po.status === s || po.status.name === s) && po.lines.some(l => l.received_qty > 0) && can('manage-inventory')" 
                        label="Return Items (RTV)" 
                        icon="pi pi-replay" 
                        class="p-button-sm !bg-zinc-800 hover:!bg-red-900/40 !text-red-400 !border-red-500/30 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openReturnMode"
                    />

                    <Button 
                        v-if="['open', 'sent', 'in_transit', 'partially_received'].includes(po.status) && can('manage-inventory')" 
                        label="Receive Stock (GRN)" 
                        icon="pi pi-download" 
                        class="p-button-sm !bg-orange-500 hover:!bg-orange-600 !border-none !text-zinc-950 font-bold shadow-[0_0_15px_rgba(249,115,22,0.3)] tracking-widest uppercase font-mono transition-all" 
                        @click="openGrnMode"
                    />
                </div>
            </div>

            <!-- Details Board -->
            <div class="grid grid-cols-12 gap-6">
                <!-- Meta Info Side -->
                <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                    <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-5">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3">Order Metadata</span>
                        
                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Drafted By</span>
                            <span class="text-xs font-bold text-white">{{ po.created_by }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Created On</span>
                            <span class="text-xs font-bold text-zinc-400">{{ po.created_at }}</span>
                        </div>
                        <div v-if="po.approved_by" class="flex flex-col gap-2 py-2 border-b border-zinc-800/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Approved By</span>
                                <span class="text-xs font-bold text-emerald-400">{{ po.approved_by }}</span>
                            </div>
                            <div v-if="po.approved_at" class="flex justify-between items-center mt-1">
                                <span class="text-[9px] font-bold text-zinc-600 font-mono tracking-tighter uppercase">Approved At</span>
                                <span class="text-[10px] font-bold text-zinc-500 uppercase">{{ po.approved_at }}</span>
                            </div>
                        </div>

                        <!-- Workflow Timestamps -->
                        <div v-if="po.sent_at" class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Sent To Vendor</span>
                            <span class="text-xs font-bold text-sky-400">{{ po.sent_at }}</span>
                        </div>

                        <div v-if="po.shipped_at" class="flex flex-col gap-2 py-2 border-b border-zinc-800/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Shipped On</span>
                                <span class="text-xs font-bold text-orange-400">{{ po.shipped_at }}</span>
                            </div>
                            <div class="flex flex-col gap-1 mt-1 p-2 bg-zinc-950 rounded border border-zinc-800">
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Carrier:</span>
                                    <span class="text-zinc-300 font-bold">{{ po.carrier }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Tracking:</span>
                                    <span class="text-sky-500 font-mono">{{ po.tracking_number || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Latest Receiver</span>
                            <span class="text-xs font-bold" :class="po.receipts.length > 0 ? 'text-orange-400' : 'text-zinc-600'">
                                {{ po.receipts.length > 0 ? po.receipts[po.receipts.length - 1].received_by : 'N/A' }}
                            </span>
                        </div>
                        <div v-if="po.notes" class="flex flex-col gap-2 pt-2">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Notes</span>
                            <p class="text-xs text-zinc-400 leading-relaxed bg-zinc-950/50 p-3 rounded-lg border border-zinc-800/50">{{ po.notes }}</p>
                        </div>
                    </div>

                    <!-- Receipt History -->
                    <div v-if="po.receipts.length > 0" class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-6 shadow-xl flex flex-col gap-5 animate-in fade-in slide-in-from-left duration-700">
                        <span class="text-[10px] font-bold text-orange-500 uppercase tracking-widest font-mono border-b border-orange-500/20 pb-3">Goods Receipt History</span>
                        
                        <div v-for="receipt in po.receipts" :key="receipt.id" class="flex flex-col gap-3 p-4 bg-zinc-950/50 rounded-xl border border-zinc-800/50 group hover:border-orange-500/20 transition-all">
                            <div class="flex justify-between items-center">
                                <span @click="viewGrnDetails(receipt)" 
                                      class="text-[10px] font-mono text-sky-400 font-bold cursor-pointer hover:underline">{{ receipt.reference_number }}</span>
                                <span class="text-[9px] font-mono text-zinc-500">{{ receipt.received_at }}</span>
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Receiver:</span>
                                    <span class="text-zinc-300 font-bold">{{ receipt.received_by }}</span>
                                </div>
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-zinc-600 font-bold uppercase tracking-tighter">Location:</span>
                                    <span class="text-zinc-400">{{ receipt.to_location }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lines Data -->
                <div class="col-span-12 lg:col-span-8 flex flex-col gap-6">
                    <div class="flex-1 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl flex flex-col overflow-hidden shadow-xl p-6">
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono border-b border-zinc-800/50 pb-3 mb-4 block">Line Items</span>
                        
                        <DataTable 
                            :value="po.lines" 
                            class="p-datatable-sm w-full"
                            stripedRows
                        >
                            <Column field="sku" header="SKU">
                                <template #body="{ data }">
                                    <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                                </template>
                            </Column>
                            
                            <Column field="product_name" header="PRODUCT">
                                <template #body="{ data }">
                                    <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                                </template>
                            </Column>

                            <Column field="unit_cost" header="UNIT COST">
                                <template #body="{ data }">
                                    <span class="text-zinc-400 font-mono text-xs">₱{{ Number(data.unit_cost).toFixed(2) }}</span>
                                </template>
                            </Column>

                            <Column field="ordered_qty" header="REQ QTY">
                                <template #body="{ data }">
                                    <span class="text-white font-mono text-xs font-bold">{{ data.ordered_qty }} {{ data.uom }}</span>
                                </template>
                            </Column>

                            <Column field="received_qty" header="RCV QTY">
                                <template #body="{ data }">
                                    <span :class="[data.received_qty >= data.ordered_qty ? 'text-emerald-400' : 'text-amber-400', 'font-mono text-xs font-bold']">
                                        {{ data.received_qty }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="returned_qty" header="RET QTY">
                                <template #body="{ data }">
                                    <span :class="[data.returned_qty > 0 ? 'text-red-400 font-black' : 'text-zinc-700', 'font-mono text-xs']">
                                        {{ data.returned_qty }}
                                    </span>
                                </template>
                            </Column>
                            
                            <Column field="pending_qty" header="REM QTY">
                                <template #body="{ data }">
                                    <span :class="[data.pending_qty === 0 ? 'text-zinc-600' : 'text-orange-500', 'font-mono text-xs font-bold']">
                                        {{ data.pending_qty }} {{ data.uom }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="total_line_cost" header="TOTAL">
                                <template #body="{ data }">
                                    <span class="text-white font-mono text-xs font-bold">₱{{ Number(data.total_line_cost).toFixed(2) }}</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRN Dialog -->
        <Dialog v-model:visible="grnDialog" modal header="Post Goods Receipt Note (GRN)" :style="{ width: '50rem' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <div class="flex flex-col gap-6 py-2">
                <div class="bg-amber-500/10 border border-amber-500/20 p-4 rounded-xl flex items-start gap-4">
                    <i class="pi pi-exclamation-triangle text-amber-500 mt-0.5"></i>
                    <p class="text-xs text-amber-400 font-bold leading-relaxed">
                        Posting a Goods Receipt Note will instantly bring this stock into your physical inventory and update your FIFO cost layers. This action cannot be natively undone.
                    </p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Receiving Location (Destination Bin)</label>
                    <Select 
                        v-model="grnForm.location_id" 
                        :options="locations" 
                        optionLabel="name" 
                        optionValue="id" 
                        placeholder="Select active bin" 
                        filter
                        class="w-full bg-zinc-950 border-zinc-700 text-sm focus:border-orange-500/50"
                    >
                        <template #option="slotProps">
                            <span class="font-bold text-xs">{{ slotProps.option.code }} — {{ slotProps.option.name }}</span>
                        </template>
                    </Select>
                </div>

                <div class="border border-zinc-800 rounded-xl overflow-hidden mt-2">
                    <DataTable :value="grnForm.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT"></Column>
                        <Column field="pending_qty" header="PENDING">
                            <template #body="{ data }">
                                <span class="text-amber-400 font-mono text-xs font-bold">{{ data.pending_qty }}</span>
                            </template>
                        </Column>
                        <Column field="received_qty" header="RECEIVE QTY">
                            <template #body="{ data }">
                                <InputNumber v-model="data.received_qty" :min="0" :max="data.pending_qty" class="w-24 bg-zinc-900 border-zinc-700 p-inputtext-sm text-center" />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="grnDialog = false" class="p-button-text !text-zinc-400 hover:!text-white" />
                <Button label="Post Receipt" icon="pi pi-check" @click="postReceipt" :loading="grnLoading" class="p-button-sm !bg-orange-500 hover:!bg-orange-600 !border-none !text-zinc-950 font-bold tracking-widest uppercase font-mono shadow-[0_0_15px_rgba(249,115,22,0.3)]" />
            </template>
        </Dialog>

        <!-- Return Items (RTV) Dialog -->
        <Dialog v-model:visible="returnDialog" modal header="Process Purchase Return (RTV / RMA)" :style="{ width: '60rem' }" :breakpoints="{ '1199px': '85vw', '575px': '95vw' }">
            <div class="flex flex-col gap-6 py-2">
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl flex items-start gap-4">
                    <i class="pi pi-exclamation-circle text-red-500 mt-0.5"></i>
                    <p class="text-[11px] text-red-400 font-bold leading-relaxed">
                        Processing a return will issue items out of your selected location as a 'PRET' transaction. <br/>
                        <b>Replacement</b>: Will reduce 'Received Qty' on this PO, allowing you to receive them again later. <br/>
                        <b>Credit</b>: Will keep 'Received Qty' high but increment 'Returned Qty' for financial reconciliation.
                    </p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Return From Location (Picking Bin)</label>
                    <Select 
                        v-model="returnForm.location_id" 
                        :options="locations" 
                        optionLabel="name" 
                        optionValue="id" 
                        placeholder="Location to pick from" 
                        filter
                        class="w-full bg-zinc-950 border-zinc-700 text-sm"
                    >
                        <template #option="slotProps">
                            <span class="font-bold text-xs">{{ slotProps.option.code }} — {{ slotProps.option.name }}</span>
                        </template>
                    </Select>
                </div>

                <div class="border border-zinc-800 rounded-xl overflow-hidden mt-2">
                    <DataTable :value="returnForm.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <span class="text-white font-bold text-xs">{{ data.product_name }}</span>
                            </template>
                        </Column>
                        <Column field="received_qty" header="MAX RETURNABLE">
                            <template #body="{ data }">
                                <span class="text-zinc-500 font-mono text-xs font-bold">{{ data.received_qty }}</span>
                            </template>
                        </Column>
                        <Column field="return_qty" header="QTY TO RETURN">
                            <template #body="{ data }">
                                <InputNumber v-model="data.return_qty" :min="0" :max="data.received_qty" class="w-24 bg-zinc-900 border-zinc-700 p-inputtext-sm text-center" />
                            </template>
                        </Column>
                        <Column field="resolution" header="RESOLUTION">
                            <template #body="{ data }">
                                <Select v-model="data.resolution" :options="[{label: 'Replacement', value: 'replacement'}, {label: 'Credit/Refund', value: 'credit'}]" optionLabel="label" optionValue="value" class="w-full min-w-[120px] bg-zinc-900 border-zinc-700 p-inputtext-sm" />
                            </template>
                        </Column>
                        <Column field="reason" header="REASON / NOTES">
                            <template #body="{ data }">
                                <InputText v-model="data.reason" placeholder="e.g. Damaged" class="w-full bg-zinc-900 border-zinc-700 p-inputtext-sm" />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="returnDialog = false" class="p-button-text !text-zinc-400 hover:!text-white" />
                <Button label="Process Return" icon="pi pi-replay" @click="postReturn" :loading="returnLoading" class="p-button-sm !bg-red-600 hover:!bg-red-700 !border-none !text-white font-bold tracking-widest uppercase font-mono shadow-[0_0_15px_rgba(220,38,38,0.3)]" />
            </template>
        </Dialog>

        <!-- Ship Dialog -->
        <Dialog v-model:visible="shipDialog" modal header="Log Vendor Shipment" :style="{ width: '30rem' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Carrier Name</label>
                    <InputText v-model="shipForm.carrier" placeholder="e.g. FedEx, DHL, LBC" class="w-full !bg-zinc-950 !border-zinc-700 !text-zinc-500 !text-sm" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-400 tracking-widest font-mono uppercase">Tracking Number</label>
                    <InputText v-model="shipForm.tracking_number" placeholder="Optional tracking #" class="w-full !bg-zinc-950 !border-zinc-700 !text-zinc-500 !text-sm" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="shipDialog = false" class="p-button-text !text-zinc-400 hover:!text-white" />
                <Button label="Confirm Shipment" icon="pi pi-truck" @click="submitShipment" :loading="shipLoading" class="p-button-sm !bg-white hover:!bg-zinc-200 !border-none !text-zinc-950 font-bold uppercase tracking-widest transition-all" />
            </template>
        </Dialog>
        
        <!-- GRN Detail View Modal -->
        <Dialog v-model:visible="grnDetailDialog" modal header="Goods Receipt Note Details" :style="{ width: '45rem' }">
            <div v-if="selectedGrn" class="flex flex-col gap-6">
                <!-- Receipt Header Info -->
                <div class="grid grid-cols-2 gap-4 p-4 bg-zinc-950 rounded-xl border border-zinc-800">
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Reference Number</label>
                        <span class="text-sm font-black text-sky-400 font-mono">{{ selectedGrn.reference_number }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Received At</label>
                        <span class="text-sm font-bold text-white">{{ selectedGrn.received_at }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Received By</label>
                        <span class="text-sm font-bold text-orange-400">{{ selectedGrn.received_by }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Storage Location</label>
                        <span class="text-sm font-bold text-white">{{ selectedGrn.to_location }}</span>
                    </div>
                </div>

                <!-- Receipt Line Items -->
                <div class="border border-zinc-800 rounded-xl overflow-hidden">
                    <DataTable :value="selectedGrn.lines" class="p-datatable-sm w-full">
                        <Column field="sku" header="SKU">
                            <template #body="{ data }">
                                <span class="text-sky-400 font-mono text-[10px] font-bold">{{ data.sku }}</span>
                            </template>
                        </Column>
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <span class="text-xs font-bold">{{ data.product_name }}</span>
                            </template>
                        </Column>
                        <Column field="quantity" header="RECEIVED">
                            <template #body="{ data }">
                                <span class="text-white font-mono text-xs font-bold">{{ data.quantity }} {{ data.uom }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Close Audit Trail" icon="pi pi-times" @click="grnDetailDialog = false" class="p-button-sm !bg-zinc-800 hover:!bg-zinc-700 !text-zinc-300 !border-none font-bold tracking-widest uppercase font-mono transition-all" />
            </template>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: #18181b; /* zinc-950 */
    border-bottom: 1px solid rgba(39, 39, 42, 0.8); /* zinc-800 */
    color: #a1a1aa; /* zinc-400 */
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 1rem;
}
:deep(.p-datatable .p-datatable-tbody > tr) {
    background: transparent;
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid rgba(39, 39, 42, 0.5); /* zinc-800/50 */
    color: #e4e4e7; /* zinc-200 */
    padding: 1rem;
}
:deep(.p-tag.p-tag-warning) { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
:deep(.p-tag.p-tag-info) { background: rgba(14, 165, 233, 0.1); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.2); }
:deep(.p-tag.p-tag-success) { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
:deep(.p-tag.p-tag-danger) { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
:deep(.p-tag.p-tag-help) { background: rgba(139, 92, 246, 0.1); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.2); }

:deep(.p-dialog) {
    background: #09090b;
    border: 1px solid #27272a;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
:deep(.p-dialog-header) {
    background: rgba(24, 24, 27, 0.8);
    border-bottom: 1px solid rgba(39, 39, 42, 0.8);
    color: white;
    padding: 1.5rem;
}
:deep(.p-dialog-content) { background: transparent; padding: 1.5rem; color: #a1a1aa; }
:deep(.p-dialog-footer) { background: rgba(24, 24, 27, 0.8); border-top: 1px solid rgba(39, 39, 42, 0.8); padding: 1.25rem; }
:deep(.p-select), :deep(.p-inputnumber-input) { background: #09090b !important; border-color: #27272a; color: white; }
</style>
