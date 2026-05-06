<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import axios from 'axios';

const props = defineProps({
    id: { type: [String, Number], required: true }
});

const transaction = ref(null);
const loading = ref(true);

const loadTransaction = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/transactions/${props.id}`);
        transaction.value = res.data.data;
    } catch (e) {
        console.error("Movement load error", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadTransaction();
});

const printSlip = () => {
    window.open(`/movements/${transaction.value.id}/print`, '_blank');
};

const getStatusSeverity = (status) => {
    switch (status?.toLowerCase()) {
        case 'posted': return 'success';
        case 'draft': return 'warn';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
};

const getTypeLabel = (type) => {
    switch (type?.toLowerCase()) {
        case 'receipt':
        case 'good_receipt': return 'STOCK RECEIPT';
        case 'issue': return 'STOCK ISSUANCE';
        case 'transfer': return 'LOCATION TRANSFER';
        case 'adjustment': return 'INVENTORY ADJUSTMENT';
        default: return 'STOCK MOVEMENT';
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val ?? 0);
};

const goBack = () => { window.history.back(); };
const jumpToPo = (poId) => { router.visit(`/purchase-orders/${poId}`); };
</script>

<template>
    <AppLayout>
        <Head title="Stock Movement Receipt" />

        <div class="main-content-wrapper p-8 bg-deep min-h-screen">
            <div class="max-w-6xl mx-auto">

                <!-- Toolbar -->
                <div class="flex justify-between items-center mb-10 pb-6 border-b border-panel-border">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="text-[9px] font-bold text-sky-400 uppercase tracking-[0.3em] font-mono">Ledger Document</span>
                            <div v-if="transaction" class="inline-flex items-center gap-2 px-2 py-0.5 bg-emerald-500/5 border border-emerald-500/20 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest font-mono">AUDIT: VERIFIED</span>
                            </div>
                        </div>
                        <h1 class="text-3xl font-bold text-primary tracking-tighter m-0 flex items-center gap-4">
                            Stock Movement Receipt
                            <span v-if="transaction" class="bg-deep px-3 py-1 rounded-lg border border-panel-border text-sky-400 text-xl font-mono tracking-widest">{{ transaction.reference_number }}</span>
                        </h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <button @click="goBack" class="px-6 h-12 rounded-xl bg-deep border border-panel-border text-secondary hover:text-primary transition-all font-bold text-[11px] uppercase tracking-widest flex items-center gap-2">
                            <i class="pi pi-arrow-left" /> Go Back
                        </button>
                        <button @click="printSlip" class="px-6 h-12 rounded-xl bg-sky-500 text-zinc-950 hover:bg-sky-400 transition-all font-bold text-[11px] uppercase tracking-widest flex items-center gap-2 shadow-lg shadow-sky-500/10 active:scale-95">
                            <i class="pi pi-print" /> Print Slip
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="h-96 flex flex-col items-center justify-center opacity-30 grayscale">
                    <i class="pi pi-spin pi-spinner text-5xl mb-4"></i>
                    <p class="font-mono text-xs tracking-widest uppercase">Retrieving Audit Trail...</p>
                </div>

                <!-- Content -->
                <div v-else-if="transaction" class="grid grid-cols-12 gap-10 animate-in fade-in slide-in-from-bottom-4 duration-700">

                    <!-- Left: Document Info -->
                    <div class="col-span-12 lg:col-span-4 flex flex-col gap-8">
                        <div class="bg-panel/60 border border-panel-border rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                            <div class="px-8 py-4 bg-panel border-b border-panel-border flex justify-between items-center">
                                <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Document Summary</span>
                                <Tag :value="(transaction.status?.name || 'loading').toUpperCase()" :severity="getStatusSeverity(transaction.status?.name)" class="!bg-transparent !border !px-3 font-mono" />
                            </div>
                            <div class="p-8 flex flex-col gap-8">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Movement Type</label>
                                    <span class="text-primary font-bold tracking-tight text-lg">{{ getTypeLabel(transaction.type?.name) }}</span>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Transaction Date</label>
                                    <span class="text-secondary font-mono text-sm">{{ transaction.transaction_date }}</span>
                                </div>
                                <div class="h-px bg-panel-hover"></div>
                                <div v-if="transaction.from_location_name" class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Origin (Source)</label>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                                        <span class="text-primary font-bold text-sm tracking-tight">{{ transaction.from_location_name }}</span>
                                    </div>
                                </div>
                                <div v-if="transaction.to_location_name" class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Destination (Target)</label>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <span class="text-primary font-bold text-sm tracking-tight">{{ transaction.to_location_name }}</span>
                                    </div>
                                </div>
                                <div v-if="transaction.vendor_name" class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Affiliated Entity</label>
                                    <div class="flex items-center gap-3">
                                        <i class="pi pi-building text-sky-400 text-xs" />
                                        <span class="text-sky-400 font-bold text-sm">{{ transaction.vendor_name }}</span>
                                    </div>
                                </div>
                                <div v-if="transaction.purchase_order_number" class="mt-4 p-4 bg-sky-500/5 border border-sky-500/10 rounded-xl flex items-center justify-between group cursor-pointer hover:border-sky-500/30 transition-all" @click="jumpToPo(transaction.purchase_order_id)">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-bold text-sky-500/60 uppercase tracking-widest font-mono mb-1">Related PO</span>
                                        <span class="text-sky-400 font-mono font-bold text-xs">{{ transaction.purchase_order_number }}</span>
                                    </div>
                                    <i class="pi pi-external-link text-sky-500/40 group-hover:text-sky-400 text-xs transition-colors" />
                                </div>
                            </div>
                        </div>
                        <div class="bg-panel/40 border border-panel-border/80 rounded-2xl p-8 backdrop-blur-sm shadow-xl">
                            <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono block mb-4">Internal Notes // Paperwork Ref</label>
                            <p class="text-secondary text-sm leading-relaxed italic m-0">{{ transaction.reference_doc || '-- No manual reference provided --' }}</p>
                        </div>
                    </div>

                    <!-- Right: Line Items -->
                    <div class="col-span-12 lg:col-span-8 flex flex-col gap-8">
                        <section class="bg-panel/60 border border-panel-border rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                            <div class="px-8 py-5 border-b border-panel-border flex justify-between items-center bg-deep/40">
                                <span class="text-[10px] font-bold text-secondary tracking-[0.2em] uppercase font-mono">Consigned Items Manifest</span>
                                <span class="bg-panel border border-panel-border text-secondary px-3 py-1 rounded text-[10px] font-bold font-mono tracking-tighter">{{ transaction.lines?.length || 0 }} ITEMS FOUND</span>
                            </div>
                            <DataTable :value="transaction.lines" class="gh-table" :pt="{ 
                                root: { class: '!bg-transparent' }, 
                                headercell: { class: '!bg-panel-hover !border-panel-border !text-primary !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' },
                                bodyrow: { class: 'hover:!bg-panel-hover !transition-all duration-200' } 
                            }">
                                <Column header="S/N" style="width: 60px">
                                    <template #body="slotProps">
                                        <span class="text-[10px] font-bold text-muted font-mono">{{ slotProps.index + 1 }}</span>
                                    </template>
                                </Column>
                                <Column header="Product / Identifier">
                                    <template #body="{ data }">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-primary font-bold truncate max-w-[350px] tracking-tight leading-tight">{{ data.product_name }}</span>
                                            <div class="flex items-center gap-3">
                                                <span class="text-[9px] font-bold text-sky-400 font-mono tracking-widest uppercase bg-sky-500/5 px-1.5 border border-sky-500/10 rounded">{{ data.product?.sku }}</span>
                                                <span v-if="data.product?.product_code" class="text-[9px] font-bold text-muted font-mono tracking-tighter uppercase">{{ data.product?.product_code }}</span>
                                            </div>
                                        </div>
                                    </template>
                                </Column>
                                <Column header="Quantity" style="width: 140px">
                                    <template #body="{ data }">
                                        <span class="text-lg font-bold font-mono" :class="data.quantity < 0 ? 'text-rose-400' : 'text-emerald-400'">
                                            {{ data.quantity < 0 ? '-' : '+' }}{{ Math.abs(data.quantity) }}
                                        </span>
                                    </template>
                                </Column>
                                <Column header="Unit" style="width: 100px">
                                    <template #body="{ data }">
                                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border border-panel-border bg-deep text-secondary uppercase tracking-widest">
                                            {{ data.base_uom?.abbreviation ?? data.uom_abbreviation ?? '???' }}
                                        </span>
                                    </template>
                                </Column>
                                <Column header="Stock Value" style="width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-secondary font-bold text-[11px]">{{ formatCurrency(data.unit_cost * Math.abs(data.quantity)) }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </section>
                        <!-- Signature (screen) -->
                        <div class="grid grid-cols-2 gap-8 mt-4 opacity-40">
                            <div class="p-8 border border-panel-border border-dashed rounded-2xl flex flex-col items-center gap-6">
                                <div class="w-full h-px bg-panel-hover mt-10"></div>
                                <span class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Handled By Inventory Personnel</span>
                            </div>
                            <div class="p-8 border border-panel-border border-dashed rounded-2xl flex flex-col items-center gap-6">
                                <div class="w-full h-px bg-panel-hover mt-10"></div>
                                <span class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Approving Officer Signature</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles for screen display only */
</style>


