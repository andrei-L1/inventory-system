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
    const slipEl = document.querySelector('.print-slip');
    if (!slipEl) return;

    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Stock Movement Receipt</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                html, body {
                    background: #ffffff !important;
                    color: #000 !important;
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                }
                body {
                    padding: 48px 56px;
                    max-width: 860px;
                    margin: 0 auto;
                }
                table { border-collapse: collapse; }
                @page { margin: 12mm 10mm; }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            ${slipEl.innerHTML}
        </body>
        </html>
    `);
    win.document.close();
    win.focus();
    setTimeout(() => {
        win.print();
        win.close();
    }, 400);
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

        <!-- ═══════════ SCREEN UI ═══════════ -->
        <div class="main-content-wrapper p-8 bg-zinc-950 min-h-screen">
            <div class="max-w-6xl mx-auto">

                <!-- Toolbar -->
                <div class="flex justify-between items-center mb-10 pb-6 border-b border-zinc-900">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="text-[9px] font-bold text-sky-400 uppercase tracking-[0.3em] font-mono">Ledger Document</span>
                            <div v-if="transaction" class="inline-flex items-center gap-2 px-2 py-0.5 bg-emerald-500/5 border border-emerald-500/20 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest font-mono">AUDIT: VERIFIED</span>
                            </div>
                        </div>
                        <h1 class="text-3xl font-bold text-white tracking-tighter m-0 flex items-center gap-4">
                            Stock Movement Receipt
                            <span v-if="transaction" class="bg-zinc-950 px-3 py-1 rounded-lg border border-zinc-800 text-sky-400 text-xl font-mono tracking-widest">{{ transaction.reference_number }}</span>
                        </h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <button @click="goBack" class="px-6 h-12 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-500 hover:text-zinc-200 transition-all font-bold text-[11px] uppercase tracking-widest flex items-center gap-2">
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
                        <div class="bg-zinc-900/60 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                            <div class="px-8 py-4 bg-zinc-900 border-b border-zinc-800 flex justify-between items-center">
                                <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest font-mono">Document Summary</span>
                                <Tag :value="(transaction.status?.name || 'loading').toUpperCase()" :severity="getStatusSeverity(transaction.status?.name)" class="!bg-transparent !border !px-3 font-mono" />
                            </div>
                            <div class="p-8 flex flex-col gap-8">
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Movement Type</label>
                                    <span class="text-white font-bold tracking-tight text-lg">{{ getTypeLabel(transaction.type?.name) }}</span>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Transaction Date</label>
                                    <span class="text-zinc-300 font-mono text-sm">{{ transaction.transaction_date }}</span>
                                </div>
                                <div class="h-px bg-zinc-800"></div>
                                <div v-if="transaction.from_location_name" class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Origin (Source)</label>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                                        <span class="text-zinc-200 font-bold text-sm tracking-tight">{{ transaction.from_location_name }}</span>
                                    </div>
                                </div>
                                <div v-if="transaction.to_location_name" class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Destination (Target)</label>
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <span class="text-zinc-200 font-bold text-sm tracking-tight">{{ transaction.to_location_name }}</span>
                                    </div>
                                </div>
                                <div v-if="transaction.vendor_name" class="flex flex-col gap-2">
                                    <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Affiliated Entity</label>
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
                        <div class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-8 backdrop-blur-sm shadow-xl">
                            <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono block mb-4">Internal Notes // Paperwork Ref</label>
                            <p class="text-zinc-400 text-sm leading-relaxed italic m-0">{{ transaction.reference_doc || '-- No manual reference provided --' }}</p>
                        </div>
                    </div>

                    <!-- Right: Line Items -->
                    <div class="col-span-12 lg:col-span-8 flex flex-col gap-8">
                        <section class="bg-zinc-900/60 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                            <div class="px-8 py-5 border-b border-zinc-800 flex justify-between items-center bg-zinc-950/40">
                                <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono">Consigned Items Manifest</span>
                                <span class="bg-zinc-900 border border-zinc-800 text-zinc-500 px-3 py-1 rounded text-[10px] font-bold font-mono tracking-tighter">{{ transaction.lines?.length || 0 }} ITEMS FOUND</span>
                            </div>
                            <DataTable :value="transaction.lines" class="gh-table" :pt="{ root: { class: '!bg-transparent' }, bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200' } }">
                                <Column header="S/N" style="width: 60px">
                                    <template #body="slotProps">
                                        <span class="text-[10px] font-bold text-zinc-600 font-mono">{{ slotProps.index + 1 }}</span>
                                    </template>
                                </Column>
                                <Column header="Product / Identifier">
                                    <template #body="{ data }">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-white font-bold truncate max-w-[350px] tracking-tight leading-tight">{{ data.product_name }}</span>
                                            <div class="flex items-center gap-3">
                                                <span class="text-[9px] font-bold text-sky-400 font-mono tracking-widest uppercase bg-sky-500/5 px-1.5 border border-sky-500/10 rounded">{{ data.product?.sku }}</span>
                                                <span v-if="data.product?.product_code" class="text-[9px] font-bold text-zinc-600 font-mono tracking-tighter uppercase">{{ data.product?.product_code }}</span>
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
                                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border border-zinc-800 bg-zinc-950 text-zinc-400 uppercase tracking-widest">
                                            {{ data.uom_abbreviation || 'PCS' }}
                                        </span>
                                    </template>
                                </Column>
                                <Column header="Stock Value" style="width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-zinc-300 font-bold text-[11px]">{{ formatCurrency(data.unit_cost * Math.abs(data.quantity)) }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </section>
                        <!-- Signature (screen) -->
                        <div class="grid grid-cols-2 gap-8 mt-4 opacity-40">
                            <div class="p-8 border border-zinc-800 border-dashed rounded-2xl flex flex-col items-center gap-6">
                                <div class="w-full h-px bg-zinc-800 mt-10"></div>
                                <span class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Handled By Inventory Personnel</span>
                            </div>
                            <div class="p-8 border border-zinc-800 border-dashed rounded-2xl flex flex-col items-center gap-6">
                                <div class="w-full h-px bg-zinc-800 mt-10"></div>
                                <span class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Approving Officer Signature</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ PRINT-ONLY RECEIPT ═══════════ -->
        <div v-if="transaction" class="print-slip">

            <!-- HEADER -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #111; padding-bottom:18px; margin-bottom:22px;">
                <div>
                    <div style="font-size:22px; font-weight:900; letter-spacing:-0.5px; text-transform:uppercase; color:#111; line-height:1.1; margin-bottom:4px;">
                        Inventory Management System
                    </div>
                    <div style="font-size:9px; font-weight:700; color:#777; text-transform:uppercase; letter-spacing:2.5px;">
                        Warehouse Operations &amp; Logistics Division
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#555; margin-bottom:8px;">
                        {{ getTypeLabel(transaction.type?.name) }}
                    </div>
                    <div style="border:2px solid #111; display:inline-block; padding:8px 16px;">
                        <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:#888; margin-bottom:3px;">Reference No.</div>
                        <div style="font-size:13px; font-weight:900; font-family:monospace; color:#111; letter-spacing:0.5px;">{{ transaction.reference_number }}</div>
                    </div>
                </div>
            </div>

            <!-- DOCUMENT INFO GRID -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:22px; font-size:11px;">
                <tbody>
                    <tr>
                        <td style="border:1px solid #ddd; padding:9px 12px; width:22%; background:#f7f7f7; vertical-align:top;">
                            <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#999; margin-bottom:4px;">Transaction Date</div>
                            <div style="font-weight:700; font-family:monospace; font-size:12px;">{{ transaction.transaction_date }}</div>
                        </td>
                        <td style="border:1px solid #ddd; padding:9px 12px; width:18%; background:#f7f7f7; vertical-align:top;">
                            <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#999; margin-bottom:4px;">Status</div>
                            <div style="font-weight:900; text-transform:uppercase; font-size:11px;">✓ {{ (transaction.status?.name || 'POSTED').toUpperCase() }}</div>
                        </td>
                        <td style="border:1px solid #ddd; padding:9px 12px; width:30%; background:#f7f7f7; vertical-align:top;">
                            <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#999; margin-bottom:4px;">From / Origin</div>
                            <div style="font-weight:700;">{{ transaction.from_location_name || '—' }}</div>
                        </td>
                        <td style="border:1px solid #ddd; padding:9px 12px; width:30%; background:#f7f7f7; vertical-align:top;">
                            <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#999; margin-bottom:4px;">To / Destination</div>
                            <div style="font-weight:700;">{{ transaction.to_location_name || '—' }}</div>
                        </td>
                    </tr>
                    <tr v-if="transaction.vendor_name || transaction.purchase_order_number">
                        <td colspan="2" style="border:1px solid #ddd; padding:9px 12px; background:#f7f7f7; vertical-align:top;">
                            <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#999; margin-bottom:4px;">Affiliated Vendor</div>
                            <div style="font-weight:700;">{{ transaction.vendor_name || '—' }}</div>
                        </td>
                        <td colspan="2" style="border:1px solid #ddd; padding:9px 12px; background:#f7f7f7; vertical-align:top;">
                            <div style="font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#999; margin-bottom:4px;">Related Purchase Order</div>
                            <div style="font-weight:700; font-family:monospace;">{{ transaction.purchase_order_number || '—' }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- SECTION LABEL -->
            <div style="font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#444; margin-bottom:6px; padding-left:10px; border-left:4px solid #111;">
                Items Manifest — {{ transaction.lines?.length || 0 }} Line(s)
            </div>

            <!-- ITEMS TABLE -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:28px; font-size:11px;">
                <thead>
                    <tr style="background:#111; color:#fff;">
                        <th style="padding:10px; text-align:left; font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; width:36px;">#</th>
                        <th style="padding:10px; text-align:left; font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px;">Product Description</th>
                        <th style="padding:10px; text-align:center; font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; width:110px;">SKU</th>
                        <th style="padding:10px; text-align:right; font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; width:80px;">Qty</th>
                        <th style="padding:10px; text-align:center; font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; width:65px;">Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, i) in transaction.lines" :key="line.id"
                        :style="{ background: i % 2 === 0 ? '#ffffff' : '#f9f9f9', borderBottom: '1px solid #e5e7eb' }">
                        <td style="padding:11px 10px; color:#bbb; font-family:monospace; font-size:10px;">{{ i + 1 }}</td>
                        <td style="padding:11px 10px; font-weight:700; color:#111;">
                            {{ line.product_name }}
                            <span v-if="line.product?.product_code" style="display:block; font-size:9px; color:#aaa; font-weight:400; margin-top:1px;">{{ line.product.product_code }}</span>
                        </td>
                        <td style="padding:11px 10px; text-align:center; font-family:monospace; font-size:10px; color:#666;">{{ line.product?.sku || '—' }}</td>
                        <td style="padding:11px 10px; text-align:right; font-family:monospace; font-weight:900; font-size:15px;"
                            :style="{ color: line.quantity < 0 ? '#b91c1c' : '#166534' }">
                            {{ line.quantity < 0 ? '−' : '+' }}{{ Math.abs(line.quantity) }}
                        </td>
                        <td style="padding:11px 10px; text-align:center; font-weight:700; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#555;">
                            {{ line.uom_abbreviation || 'PCS' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- NOTES -->
            <div v-if="transaction.reference_doc" style="border:1px dashed #ccc; padding:12px 16px; margin-bottom:28px; font-size:11px; color:#555; line-height:1.7;">
                <span style="display:block; font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#aaa; margin-bottom:5px;">Document Reference / Notes</span>
                {{ transaction.reference_doc }}
            </div>

            <!-- SIGNATURES -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:36px; margin-top:56px;">
                <div style="text-align:center;">
                    <div style="height:72px; border-bottom:1.5px solid #222; margin-bottom:10px;"></div>
                    <div style="font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#333;">Prepared By</div>
                    <div style="font-size:7.5px; color:#bbb; margin-top:3px;">Name / Signature / Date</div>
                </div>
                <div style="text-align:center;">
                    <div style="height:72px; border-bottom:1.5px solid #222; margin-bottom:10px;"></div>
                    <div style="font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#333;">Authorized By</div>
                    <div style="font-size:7.5px; color:#bbb; margin-top:3px;">Name / Signature / Date</div>
                </div>
                <div style="text-align:center;">
                    <div style="height:72px; border-bottom:1.5px solid #222; margin-bottom:10px;"></div>
                    <div style="font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#333;">Received By</div>
                    <div style="font-size:7.5px; color:#bbb; margin-top:3px;">Name / Signature / Date</div>
                </div>
            </div>

            <!-- FOOTER -->
            <div style="margin-top:36px; padding-top:10px; border-top:1px solid #eee; display:flex; justify-content:space-between; font-size:8px; font-family:monospace; color:#ccc; text-transform:uppercase; letter-spacing:1px;">
                <span>System Automated Audit &bull; Doc ID: {{ transaction.id }}</span>
                <span>Printed: {{ new Date().toLocaleString() }}</span>
            </div>
        </div>

    </AppLayout>
</template>

<style scoped>
/* Screen: hide print slip */
.print-slip {
    display: none;
}

@media print {
    /* ── Kill ALL dark backgrounds from AppLayout ── */
    :deep(body),
    :deep(html),
    :deep(#app),
    :deep([class*="bg-zinc"]),
    :deep([class*="bg-gray"]),
    :deep(aside),
    :deep(nav),
    :deep(header),
    :deep(.p-breadcrumb) {
        display: none !important;
    }

    /* Force the root document white */
    html, body {
        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #000 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Hide the entire dark screen UI wrapper */
    .main-content-wrapper {
        display: none !important;
    }

    /* Show and center the receipt */
    .print-slip {
        display: block !important;
        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #000 !important;
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.5;
        /* Center on page */
        width: 740px;
        max-width: 100%;
        margin: 0 auto;
        padding: 48px 56px;
        box-sizing: border-box;
    }

    /* Ensure ALL elements print with their backgrounds */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Page margins */
    @page {
        margin: 12mm 10mm;
        background: white;
    }
}
</style>
