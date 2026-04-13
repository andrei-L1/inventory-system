<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import axios from 'axios';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { onMounted, watch } from 'vue';

const props = defineProps({
    from_po: { type: [String, Number], default: null }
});

const toast = useToast();

const isPOLinked = ref(false);
const linkedPO = ref(null);
const purchaseOrders = ref([]);
const selectedPo = ref(null);
const loadingPOs = ref(false);
const vendors = ref([]);
const selectedVendor = ref(null);
const loading = ref(false);
const uomConversions = ref([]);
const loadingConversions = ref(false);

const form = ref({
    vendor_id: null,
    purchase_order_id: null,
    bill_number: '',
    bill_date: new Date(),
    due_date: new Date(new Date().setDate(new Date().getDate() + 30)),
    notes: '',
    lines: [
        { description: '', quantity: 1, unit_price: 0, po_line_id: null, transaction_line_id: null }
    ]
});

// Hierarchical State for Linked PO Flow
const groupedLines = ref([]);

// Methods
const loadVendors = async () => {
    const res = await axios.get('/api/vendors');
    vendors.value = res.data.data;
};

const loadPurchaseOrders = async () => {
    loadingPOs.value = true;
    try {
        const res = await axios.get('/api/purchase-orders?limit=100');
        // Include 'closed' because fully received POs are marked as closed, but still need billing.
        // Also include 'open' in case of prepayments, though receipts are the primary driver.
        const billableStatuses = ['sent', 'partially_received', 'closed', 'in_transit', 'open'];
        
        purchaseOrders.value = res.data.data.filter(po => {
            const statusMatches = po.status && billableStatuses.includes(po.status.toLowerCase());
            const needsBilling = po.billing_status !== 'BILLED';
            return statusMatches && needsBilling;
        });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load Purchase Orders' });
    } finally {
        loadingPOs.value = false;
    }
};

const onPoSelect = async () => {
    if (!selectedPo.value) {
        isPOLinked.value = false;
        linkedPO.value = null;
        form.value.purchase_order_id = null;
        groupedLines.value = [];
        return;
    }
    
    await fetchPOData(selectedPo.value.id);
};

const loadConversions = async () => {
    loadingConversions.value = true;
    try {
        const res = await axios.get('/api/uom-conversions?limit=1000');
        uomConversions.value = res.data.data;
    } catch (e) {
        console.error('Failed to load conversions', e);
    } finally {
        loadingConversions.value = false;
    }
};

const getConversionFactor = (fromUomId, productId) => {
    const rule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(fromUomId) && Number(c.product_id) === Number(productId));
    if (rule) return Number(rule.conversion_factor);
    
    const globalRule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(fromUomId) && c.product_id === null);
    return globalRule ? Number(globalRule.conversion_factor) : 1;
};

const fetchPOData = async (poId) => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/purchase-orders/${poId}`);
        const po = res.data.data;
        linkedPO.value = po;
        isPOLinked.value = true;

        selectedVendor.value = vendors.value.find(v => v.id === po.vendor_id);
        form.value.purchase_order_id = po.id;

        // TRACKERS: Sequential consumption of the billable pool
        const poLinePoolRemainders = {};

        // Grouping Logic: Anchor everything to PO Lines
        const groups = po.lines.map(poLine => {
            const productId = poLine.product_id;
            const poUomFactor = getConversionFactor(poLine.uom_id, productId);
            
            // Initialize pool for this specific PO line
            poLinePoolRemainders[poLine.id] = Number(poLine.billable_qty) * poUomFactor;
            const poUnitFactor = getConversionFactor(poLine.uom_id, productId);
            
            poLinePoolRemainders[poLine.id] = Number(poLine.billable_qty);
            
            const receipts = [];
            po.receipts?.forEach(rcpt => {
                rcpt.lines.forEach(l => {
                    if (Number(l.product_id) === Number(productId)) {
                        const receiptFactor = getConversionFactor(l.uom_id, productId);
                        const qtyInPoUnit = Number(l.quantity) / poUnitFactor;
                        const billedInPoUnit = Number(l.billed_qty || 0) / poUnitFactor;
                        
                        if (qtyInPoUnit > billedInPoUnit) {
                            const available = qtyInPoUnit - billedInPoUnit;
                            const suggested = Math.max(0, Math.min(available, poLinePoolRemainders[poLine.id]));
                            
                            poLinePoolRemainders[poLine.id] -= suggested;
                            
                            receipts.push({
                                type: 'RECEIPT',
                                reference: rcpt.reference_number,
                                date: rcpt.received_at,
                                transaction_line_id: l.id,
                                quantity_in_po_unit: qtyInPoUnit,
                                pieces: Number(l.quantity),
                                base_uom: l.base_uom?.abbreviation ?? l.base_uom_abbreviation ?? '???',
                                billed_in_po_unit: billedInPoUnit,
                                available_to_bill: available,
                                bill_qty: suggested,
                                factor: poUnitFactor,
                                unit_price: Number(poLine.unit_cost),
                                uom: poLine.base_uom?.abbreviation ?? poLine.uom_abbreviation ?? '???',
                                subtotal: Number(poLine.unit_cost) * suggested
                            });
                        }
                    }
                });
            });

            const returns = [];
            po.returns?.forEach(rtn => {
                rtn.lines.forEach(l => {
                    if (l.product_name === poLine.product_name || l.sku === poLine.sku) {
                        returns.push({
                            type: 'RETURN',
                            reference: rtn.reference_number,
                            date: rtn.returned_at,
                            quantity_in_base: Number(l.quantity), 
                            notes: l.notes,
                            uom: l.uom_abbreviation
                        });
                    }
                });
            });

            return {
                po_line_id: poLine.id,
                product_name: poLine.product_name,
                sku: poLine.sku,
                ordered_qty: Number(poLine.ordered_qty),
                received_qty: Number(poLine.received_qty),
                returned_qty: Number(poLine.returned_qty || 0),
                billable_qty: Number(poLine.billable_qty),
                uom: poLine.base_uom?.abbreviation ?? poLine.uom_abbreviation ?? '???',
                receipts,
                returns
            };
        }).filter(g => g.receipts.length > 0 || g.returns.length > 0);

        groupedLines.value = groups;
        
        if (groups.length === 0) {
            toast.add({ severity: 'info', summary: 'Notice', detail: 'This PO has no unbilled items or history.' });
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch PO details.' });
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const subtotal = (line) => Number(line.bill_qty || 0) * Number(line.unit_price || 0);

const grandTotal = computed(() => {
    if (isPOLinked.value) {
        return groupedLines.value.reduce((acc, group) => {
            return acc + group.receipts.reduce((gAcc, r) => gAcc + (r.bill_qty * r.unit_price), 0);
        }, 0);
    }
    return form.value.lines.reduce((acc, line) => acc + (Number(line.quantity || 0) * Number(line.unit_price || 0)), 0);
});

const submit = async () => {
    if (!selectedVendor.value) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Please select a vendor.' });
        return;
    }
    
    loading.value = true;
    try {
        let finalLines = [];
        
        if (isPOLinked.value) {
            groupedLines.value.forEach(group => {
                group.receipts.forEach(r => {
                    if (Number(r.bill_qty) > 0.00000001) {
                        finalLines.push({
                            po_line_id: group.po_line_id,
                            transaction_line_id: r.transaction_line_id,
                            quantity: r.bill_qty * r.factor,
                            unit_price: r.unit_price / r.factor,
                            description: `[${r.reference}] ${group.product_name}`
                        });
                    }
                });
            });
        } else {
            finalLines = form.value.lines.filter(l => Number(l.quantity) > 0);
        }

        if (finalLines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Validation', detail: 'No billing quantities entered.' });
            loading.value = false;
            return;
        }

        const payload = {
            ...form.value,
            vendor_id: selectedVendor.value.id,
            bill_date: form.value.bill_date.toISOString().split('T')[0],
            due_date: form.value.due_date ? form.value.due_date.toISOString().split('T')[0] : null,
            lines: finalLines
        };

        const targetUrl = isPOLinked.value 
            ? `/api/purchase-orders/${form.value.purchase_order_id}/bill` 
            : '/api/bills';

        await axios.post(targetUrl, payload);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Bill posted to ledger.' });
        router.visit('/finance-center');
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Submission failed.' });
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await loadVendors();
    await loadConversions();
    await loadPurchaseOrders();

    const params = new URLSearchParams(window.location.search);
    const poParam = params.get('from_po');
    if (poParam) {
        const matchingPo = purchaseOrders.value.find(p => Number(p.id) === Number(poParam));
        if (matchingPo) {
            selectedPo.value = matchingPo;
            onPoSelect();
        } else {
            fetchPOData(poParam);
        }
    }
});
</script>

<template>
    <AppLayout>
        <Head title="Convert PO to Bill" />
        <Toast />
        
        <div class="p-8 max-w-7xl mx-auto font-sans">
            <!-- Header Section -->
            <div class="mb-10 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <button @click="router.visit('/finance-center?mode=PAYABLE')" 
                            class="w-12 h-12 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white hover:border-zinc-700 transition-all shadow-lg">
                        <i class="pi pi-arrow-left"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-3xl font-black text-white tracking-tight uppercase">
                                {{ isPOLinked ? 'Purchase Order Bill' : 'Draft Vendor Bill' }}
                            </h1>
                            <div v-if="isPOLinked" class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full">
                                <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest">{{ linkedPO?.po_number }}</span>
                            </div>
                        </div>
                        <p class="text-zinc-500 text-sm font-medium">
                            {{ isPOLinked ? 'Reconcile warehouse receipts against procurement obligations.' : 'Record direct expenses or non-PO procurement in the accounts payable ledger.' }}
                        </p>
                    </div>
                </div>
                
                <button @click="submit" :disabled="loading || !selectedVendor" 
                        class="bg-amber-500 hover:bg-amber-400 disabled:opacity-20 disabled:grayscale text-zinc-950 px-10 h-14 font-black text-[13px] uppercase tracking-[0.2em] transition-all rounded-2xl shadow-[0_0_30px_rgba(245,158,11,0.2)] flex items-center gap-4 group">
                    <i class="pi pi-check-circle text-lg group-hover:scale-110 transition-transform" v-if="!loading"></i>
                    <i class="pi pi-spin pi-spinner text-lg" v-else></i>
                    <span>Post to Ledger</span>
                </button>
            </div>

            <div class="grid grid-cols-12 gap-10">
                <!-- Sidebar: Control Panel -->
                <div class="col-span-12 lg:col-span-4 space-y-8">
                    <div class="bg-zinc-900/40 border border-zinc-800 rounded-3xl p-8 space-y-8 backdrop-blur-xl shadow-2xl relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 blur-3xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        
                        <!-- PO Selection (Automated Workflow) -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-black text-zinc-500 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
                                <i class="pi pi-file-import text-[10px] text-amber-500"></i>
                                Select Purchase Order
                            </label>
                            <Select v-model="selectedPo" :options="purchaseOrders" optionLabel="po_number" placeholder="Optional: Select PO to Bill" 
                                    class="w-full bg-zinc-950/80 border-zinc-800 text-white rounded-xl h-12" 
                                    :loading="loadingPOs" filter @change="onPoSelect" showClear />
                            <div v-if="!isPOLinked" class="px-1 text-[9px] text-zinc-600 font-bold uppercase tracking-tight">
                                Leave clear for manual expense entry
                            </div>
                        </div>

                        <!-- Vendor Lock -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-black text-zinc-500 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
                                <i class="pi pi-building text-[10px]"></i>
                                Vendor Entity
                            </label>
                            <Select v-model="selectedVendor" :options="vendors" optionLabel="name" placeholder="Select Vendor" 
                                    :disabled="isPOLinked"
                                    class="w-full bg-zinc-950/80 border-zinc-800 text-white rounded-xl h-12" filter />
                            <div v-if="isPOLinked" class="flex items-center gap-2 px-1">
                                <i class="pi pi-lock text-[9px] text-zinc-600"></i>
                                <span class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest italic">Locked to Order Source</span>
                            </div>
                        </div>

                        <!-- Invoice Reference -->
                        <div class="space-y-3">
                            <label class="text-[11px] font-black text-zinc-500 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
                                <i class="pi pi-file text-[10px]"></i>
                                Official Receipt / Inv #
                            </label>
                            <input v-model="form.bill_number" type="text" 
                                   class="w-full bg-zinc-950/80 border border-zinc-800 rounded-xl h-12 px-5 text-white text-sm font-mono focus:border-amber-500/50 focus:ring-4 focus:ring-amber-500/10 outline-none transition-all placeholder:text-zinc-700"
                                   placeholder="e.g. INV-99221-A">
                        </div>

                        <!-- Dates -->
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-zinc-500 uppercase tracking-[0.2em] px-1">Issue Date</label>
                                <DatePicker v-model="form.bill_date" class="w-full" dateFormat="yy-mm-dd" 
                                            inputClass="!bg-zinc-950/80 !border-zinc-800 !text-white !font-mono !text-xs !rounded-xl !h-12 !px-4" />
                            </div>
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-zinc-500 uppercase tracking-[0.2em] px-1">Due Date</label>
                                <DatePicker v-model="form.due_date" class="w-full" dateFormat="yy-mm-dd" 
                                            inputClass="!bg-zinc-950/80 !border-zinc-800 !text-white !font-mono !text-xs !rounded-xl !h-12 !px-4" />
                            </div>
                        </div>

                        <!-- Financial Summary -->
                        <div class="pt-4 border-t border-zinc-800/50">
                            <div class="bg-zinc-950 p-6 rounded-2xl border border-zinc-800 shadow-inner">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Grand Liability Total</span>
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                                        <span class="text-[10px] font-bold text-amber-500 uppercase">PHP Balance</span>
                                    </div>
                                </div>
                                <div class="text-4xl font-black text-white font-mono tracking-tighter">
                                    {{ formatCurrency(grandTotal) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Trail Sidebar -->
                    <div v-if="isPOLinked" class="bg-zinc-900/20 border border-zinc-800/50 rounded-3xl p-8 space-y-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-zinc-600 uppercase tracking-[0.2em]">Transaction History</span>
                            <span class="text-[9px] font-bold text-zinc-700 uppercase tracking-widest">Audit Trail</span>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Receipts -->
                            <div v-for="rcpt in linkedPO?.receipts" :key="rcpt.id" class="flex items-start gap-4 group">
                                <div class="mt-1 w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.5)]"></div>
                                <div>
                                    <div class="text-[11px] font-black text-zinc-300 group-hover:text-blue-400 transition-colors uppercase">{{ rcpt.reference_number }}</div>
                                    <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-tighter">Received on {{ rcpt.received_at }}</div>
                                </div>
                            </div>
                            <!-- Returns -->
                            <div v-for="rtn in linkedPO?.returns" :key="rtn.id" class="flex items-start gap-4 group">
                                <div class="mt-1 w-1.5 h-1.5 rounded-full bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.5)]"></div>
                                <div>
                                    <div class="text-[11px] font-black text-zinc-300 group-hover:text-rose-400 transition-colors uppercase">{{ rtn.reference_number }}</div>
                                    <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-tighter">Stock RTV on {{ rtn.returned_at }}</div>
                                </div>
                            </div>
                        </div>
                        <p class="text-[9px] text-zinc-600 italic font-medium pt-2 border-t border-zinc-800/30">
                            Billing is context-anchored to the specific receipt lines listed above.
                        </p>
                    </div>
                </div>

                <!-- Main Section: Grouped Ledger Breakdown -->
                <div class="col-span-12 lg:col-span-8">
                    <div class="bg-zinc-900 border border-zinc-800 rounded-[2.5rem] overflow-hidden shadow-2xl flex flex-col min-h-[700px]">
                        <!-- Table Header -->
                        <div class="p-8 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/50 backdrop-blur-md sticky top-0 z-20">
                            <div>
                                <h3 class="text-white font-black text-lg tracking-tight flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center text-zinc-950 overflow-hidden shadow-lg shadow-amber-500/10">
                                        <i class="pi pi-list text-xs"></i>
                                    </div>
                                    Detailed Expense Breakdown
                                </h3>
                                <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mt-1 ml-11 italic">Matching Warehouse Artifacts to Ledger Entries</p>
                            </div>
                            
                            <div v-if="isPOLinked" class="px-4 py-2 bg-zinc-950 border border-zinc-800 rounded-xl flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div>
                                    <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest">Receipt</span>
                                </div>
                                <div class="w-px h-3 bg-zinc-800"></div>
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                                    <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest">Return</span>
                                </div>
                            </div>
                        </div>

                        <!-- Grouped Content -->
                        <div class="flex-1 overflow-y-auto p-8 space-y-12 bg-zinc-950/20">
                            <div v-for="group in groupedLines" :key="group.po_line_id" class="space-y-4">
                                <!-- PO Line Header -->
                                <div class="flex flex-col gap-2 relative">
                                    <div class="flex items-end justify-between px-2">
                                        <div class="flex flex-col">
                                            <span class="text-[9px] font-black text-zinc-600 uppercase tracking-[0.3em] mb-1">Product Origin</span>
                                            <h4 class="text-white font-black text-base tracking-tight leading-none">{{ group.product_name }}</h4>
                                            <span class="text-[10px] font-mono text-zinc-500 font-bold mt-1 uppercase opacity-60 tracking-tighter">{{ group.sku }}</span>
                                        </div>
                                        <div class="flex items-center gap-6 pb-1">
                                            <div class="flex flex-col items-end">
                                                <span class="text-[9px] font-black text-zinc-600 uppercase tracking-widest">Ordered</span>
                                                <span class="text-xs font-bold text-zinc-400 font-mono">{{ group.ordered_qty }} {{ group.uom }}</span>
                                            </div>
                                            <div class="w-px h-6 bg-zinc-800/50"></div>
                                            <div class="flex flex-col items-end">
                                                <span class="text-[9px] font-black text-zinc-600 uppercase tracking-widest">Net Billable</span>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-xs font-black text-amber-500 font-mono">{{ group.billable_qty.toLocaleString() }}</span>
                                                    <span class="text-[9px] font-bold text-zinc-600 uppercase">{{ group.uom }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="h-px w-full bg-gradient-to-r from-zinc-800 via-zinc-800 to-transparent"></div>
                                </div>

                                <!-- Transaction Table -->
                                <div class="bg-zinc-900/30 rounded-2xl border border-zinc-800/50 overflow-hidden">
                                    <table class="w-full border-collapse">
                                        <thead class="bg-zinc-950/40">
                                            <tr class="text-[9px] font-black text-zinc-600 uppercase tracking-widest border-b border-zinc-800">
                                                <th class="py-3 px-4 text-left" style="width: 140px">Artifact #</th>
                                                <th class="py-3 px-4 text-left">Activity</th>
                                                <th class="py-3 px-4 text-right" style="width: 140px">Impact Qty</th>
                                                <th class="py-3 px-4 text-right" style="width: 150px">Price</th>
                                                <th class="py-3 px-6 text-right" style="width: 150px">Amount to Bill</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Sub-Rows for Receipts -->
                                            <tr v-for="r in group.receipts" :key="r.transaction_line_id" class="border-b border-zinc-900/50 group/row hover:bg-blue-500/[0.02] transition-colors">
                                                <td class="py-4 px-6">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.3)]"></div>
                                                        <span class="text-[11px] font-black font-mono text-zinc-300 group-hover/row:text-blue-400">{{ r.reference }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] font-black text-blue-500/80 uppercase tracking-widest">Received Good Stock</span>
                                                        <span class="text-[8px] text-zinc-600 font-bold uppercase tracking-tighter">{{ r.date }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 text-right">
                                                    <div class="flex flex-col items-end">
                                                        <div class="flex items-baseline gap-1">
                                                            <span class="text-xs font-bold text-zinc-200 font-mono">{{ r.quantity_in_po_unit.toLocaleString() }} {{ r.uom }}</span>
                                                            <span class="text-[9px] text-zinc-600 font-bold font-mono">[{{ r.pieces.toLocaleString() }} {{ r.base_uom }}]</span>
                                                        </div>
                                                        <span v-if="r.billed_in_po_unit > 0" class="text-[8px] text-zinc-600 font-bold italic">({{ r.billed_in_po_unit }} {{ r.uom }} already billed)</span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 text-right">
                                                     <span class="text-[11px] font-mono text-white">{{ formatCurrency(r.unit_price) }}</span>
                                                </td>
                                                <td class="py-4 px-6 text-right">
                                                    <div class="relative group/input">
                                                        <InputNumber v-model="r.bill_qty" :min="0" :max="r.available_to_bill" :step="0.01" @update:modelValue="r.subtotal = r.bill_qty * r.unit_price" fluid
                                                                    inputClass="!bg-zinc-950 !border-amber-500/20 focus:!border-amber-500 !text-white !font-black !text-xs !text-right !py-2 !rounded-lg !shadow-inner transition-all" />
                                                        <div class="flex flex-col items-end mt-1 px-1">
                                                            <span class="text-white font-mono text-xs font-black">{{ formatCurrency(r.subtotal) }}</span>
                                                            <span class="text-[8px] text-zinc-600 font-bold font-mono uppercase tracking-tighter">Matches [{{ (r.bill_qty * r.factor).toLocaleString() }} {{ r.base_uom }}]</span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Sub-Rows for Returns -->
                                            <tr v-for="rtn in group.returns" :key="rtn.reference" class="border-b border-zinc-900/50 bg-rose-500/[0.01] hover:bg-rose-500/[0.03] transition-colors">
                                                <td class="py-4 px-6">
                                                    <div class="flex items-center gap-3 opacity-60">
                                                        <div class="w-1.5 h-1.5 rounded-full bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.3)]"></div>
                                                        <span class="text-[11px] font-black font-mono text-zinc-400">{{ rtn.reference }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="flex flex-col opacity-60">
                                                        <span class="text-[10px] font-black text-rose-500/80 uppercase tracking-widest">{{ rtn.notes || 'Purchased Return' }}</span>
                                                        <span class="text-[8px] text-zinc-600 font-bold uppercase tracking-tighter">{{ rtn.date }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4 text-right font-mono text-xs font-black text-rose-500/40 italic">
                                                    -{{ rtn.quantity_in_base.toLocaleString() }} {{ rtn.uom }}
                                                </td>
                                                <td class="py-4 px-4 opacity-30 text-right text-[10px] font-bold text-zinc-700 uppercase italic">Non-Billable</td>
                                                <td class="py-4 px-6 text-right opacity-30 pointer-events-none grayscale">
                                                    <div class="h-8 w-full bg-zinc-950/50 rounded-lg flex items-center justify-center">
                                                        <i class="pi pi-ban text-[8px] text-zinc-700"></i>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Manual Line Items (Visible if not PO linked) -->
                            <div v-if="!isPOLinked" class="space-y-6">
                                <div v-for="(line, idx) in form.lines" :key="idx" class="bg-zinc-900/40 p-6 rounded-3xl border border-zinc-800 grid grid-cols-12 gap-6 items-center group">
                                    <div class="col-span-12 lg:col-span-6 space-y-2">
                                        <label class="text-[9px] font-black text-zinc-600 uppercase tracking-widest px-1">Description of Expense</label>
                                        <input v-model="line.description" type="text" 
                                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl h-11 px-4 text-white text-xs focus:border-amber-500 outline-none transition-all">
                                    </div>
                                    <div class="col-span-4 lg:col-span-2 space-y-2">
                                        <label class="text-[9px] font-black text-zinc-600 uppercase tracking-widest px-1">Quantity</label>
                                        <InputNumber v-model="line.quantity" :min="0" fluid 
                                                     inputClass="!bg-zinc-950 !border-zinc-800 !text-white !font-mono !text-xs !text-right !h-11 !rounded-xl" />
                                    </div>
                                    <div class="col-span-4 lg:col-span-2 space-y-2">
                                        <label class="text-[9px] font-black text-zinc-600 uppercase tracking-widest px-1">Unit Price</label>
                                        <InputNumber v-model="line.unit_price" mode="currency" currency="PHP" locale="en-PH" fluid 
                                                     inputClass="!bg-zinc-950 !border-zinc-800 !text-white !font-mono !text-xs !text-right !h-11 !rounded-xl" />
                                    </div>
                                    <div class="col-span-3 lg:col-span-1 text-right pt-5">
                                        <div class="text-[10px] font-black text-white font-mono opacity-50">{{ formatCurrency(subtotal(line)) }}</div>
                                    </div>
                                    <div class="col-span-1 pt-5">
                                        <button @click="form.lines.splice(idx, 1)" v-if="form.lines.length > 1"
                                                class="w-9 h-9 rounded-xl hover:bg-rose-500/10 text-zinc-700 hover:text-rose-500 transition-all flex items-center justify-center">
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <button @click="form.lines.push({ description: '', quantity: 1, unit_price: 0 })" 
                                        class="w-full h-12 border-2 border-dashed border-zinc-800 rounded-2xl text-zinc-600 hover:text-amber-500 hover:border-amber-500/50 hover:bg-amber-500/5 transition-all text-[10px] font-black uppercase tracking-[0.2em] flex items-center justify-center gap-3">
                                    <i class="pi pi-plus"></i>
                                    Add Manual Entry
                                </button>
                            </div>

                            <div v-if="isPOLinked && groupedLines.length === 0" class="flex flex-col items-center justify-center py-24 opacity-20">
                                <i class="pi pi-inbox text-5xl mb-4"></i>
                                <span class="text-xs font-black uppercase tracking-widest">No transaction history detected for this order</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "tailwindcss";

:deep(.p-inputnumber-input) {
    @apply focus:ring-0 focus:outline-none transition-all;
}

:deep(.p-datepicker) {
    @apply bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl;
}

::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    @apply bg-zinc-800 rounded-full hover:bg-zinc-700 transition-colors;
}
</style>
