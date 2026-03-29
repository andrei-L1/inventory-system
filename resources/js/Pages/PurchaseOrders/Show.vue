<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
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

const grnDialog = ref(false);
const grnLoading = ref(false);
const locations = ref([]);
const grnForm = ref({
    location_id: null,
    lines: []
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
    const map = { 'draft': 'warning', 'open': 'info', 'partially_received': 'help', 'closed': 'success', 'cancelled': 'danger' };
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
                await axios.patch(`/api/purchase-orders/${po.value.id}/approve`);
                toast.add({ severity: 'success', summary: 'Approved', detail: 'PO is now Open', life: 3000 });
                loadPO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Approval failed', life: 3000 });
            }
        }
    });
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
                        <p class="text-zinc-500 text-[10px] font-bold tracking-[0.2em] uppercase font-mono">
                            {{ po.vendor_name }} &bull; {{ po.currency }} {{ Number(po.total_amount).toFixed(2) }}
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
                        class="p-button-sm !bg-emerald-500/20 hover:!bg-emerald-500/30 !text-emerald-400 !border-emerald-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="approve"
                    />

                    <Button 
                        v-if="['open', 'partially_received'].includes(po.status) && can('manage-inventory')" 
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
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Order Date</span>
                            <span class="text-xs font-bold text-sky-400 font-mono">{{ po.order_date || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-zinc-800/30">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Expected Del.</span>
                            <span class="text-xs font-bold text-amber-400 font-mono">{{ po.expected_delivery_date || 'N/A' }}</span>
                        </div>
                        <div v-if="po.notes" class="flex flex-col gap-2 pt-2">
                            <span class="text-[10px] font-bold text-zinc-500 font-mono tracking-widest uppercase">Notes</span>
                            <p class="text-xs text-zinc-400 leading-relaxed bg-zinc-950/50 p-3 rounded-lg border border-zinc-800/50">{{ po.notes }}</p>
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
                                    <span class="text-zinc-400 font-mono text-xs">{{ po.currency }} {{ Number(data.unit_cost).toFixed(2) }}</span>
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
                                        {{ data.received_qty }} {{ data.uom }}
                                    </span>
                                </template>
                            </Column>

                            <Column field="total_line_cost" header="TOTAL">
                                <template #body="{ data }">
                                    <span class="text-white font-mono text-xs font-bold">{{ po.currency }} {{ Number(data.total_line_cost).toFixed(2) }}</span>
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
                    <Dropdown 
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
                    </Dropdown>
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
:deep(.p-dropdown), :deep(.p-inputnumber-input) { background: #09090b !important; border-color: #27272a; color: white; }
</style>
