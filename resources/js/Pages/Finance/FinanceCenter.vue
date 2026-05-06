<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();

const financeMode = ref('RECEIVABLE'); // RECEIVABLE, PAYABLE
const activeTab = ref('invoices'); // invoices, payments, statements

// Data State - A/R
const invoices = ref([]);
const payments = ref([]);
const customers = ref([]);

// Data State - A/P
const bills = ref([]);
const vendorPayments = ref([]);
const vendors = ref([]);

// Loading States
const loadingInvoices = ref(false);
const loadingPayments = ref(false);
const loadingBills = ref(false);
const loadingVendorPayments = ref(false);

const invoiceFilters = ref({ status: null, type: null, search: '' });

// Statements State
const selectedStatementCustomer = ref(null);
const selectedStatementVendor = ref(null);
const statementData = ref(null);
const loadingStatement = ref(false);

onMounted(async () => {
    // Initialize mode from URL if present
    const params = new URLSearchParams(window.location.search);
    const mode = params.get('mode');
    if (mode && ['RECEIVABLE', 'PAYABLE'].includes(mode)) {
        financeMode.value = mode;
    }
    
    await loadInitialData();
});

const loadInitialData = async () => {
    try {
        if (financeMode.value === 'RECEIVABLE') {
            loadInvoices();
            loadPayments();
            loadCustomers();
        } else {
            loadBills();
            loadVendorPayments();
            loadVendors();
        }
    } catch (e) {
        console.error(e);
    }
};

// Reset tab and sync URL when switching modes
watch(financeMode, (newMode) => {
    activeTab.value = 'invoices';
    statementData.value = null;
    selectedStatementCustomer.value = null;
    selectedStatementVendor.value = null;
    
    // Persist to URL
    router.replace({
        url: window.location.pathname + '?mode=' + newMode,
        preserveState: true,
        preserveScroll: true
    });

    loadInitialData();
});

const loadCustomers = async () => {
    try {
        const res = await axios.get('/api/customers');
        customers.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load customers', life: 3000 });
    }
};

const loadVendors = async () => {
    try {
        const res = await axios.get('/api/vendors');
        vendors.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load vendors', life: 3000 });
    }
};

watch(selectedStatementCustomer, async (newVal) => {
    if (!newVal || financeMode.value !== 'RECEIVABLE') {
        statementData.value = null;
        return;
    }

    loadingStatement.value = true;
    try {
        const res = await axios.get(`/api/customers/${newVal.id}/statement`);
        statementData.value = res.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load customer statement', life: 3000 });
    } finally {
        loadingStatement.value = false;
    }
});

watch(selectedStatementVendor, async (newVal) => {
    if (!newVal || financeMode.value !== 'PAYABLE') {
        statementData.value = null;
        return;
    }

    loadingStatement.value = true;
    try {
        const res = await axios.get(`/api/vendors/${newVal.id}/statement`);
        statementData.value = res.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load vendor statement', life: 3000 });
    } finally {
        loadingStatement.value = false;
    }
});

const loadInvoices = async () => {
    loadingInvoices.value = true;
    try {
        const params = {};
        if (invoiceFilters.value.status) params.status = invoiceFilters.value.status;
        if (invoiceFilters.value.type) params.type = invoiceFilters.value.type;
        const res = await axios.get('/api/invoices', { params });
        invoices.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load invoices', life: 3000 });
    } finally {
        loadingInvoices.value = false;
    }
};

const loadPayments = async () => {
    loadingPayments.value = true;
    try {
        const res = await axios.get('/api/payments');
        payments.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load payments', life: 3000 });
    } finally {
        loadingPayments.value = false;
    }
};

const loadBills = async () => {
    loadingBills.value = true;
    try {
        const res = await axios.get('/api/bills');
        bills.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load bills', life: 3000 });
    } finally {
        loadingBills.value = false;
    }
};

const loadVendorPayments = async () => {
    loadingVendorPayments.value = true;
    try {
        const res = await axios.get('/api/vendor-payments');
        vendorPayments.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load vendor payments', life: 3000 });
    } finally {
        loadingVendorPayments.value = false;
    }
};

const postInvoice = async (invoice) => {
    if (!confirm(`Are you sure you want to officially post invoice ${invoice.invoice_number}? This cannot be undone.`)) return;
    try {
        await axios.patch(`/api/invoices/${invoice.id}/post`);
        toast.add({ severity: 'success', summary: 'Posted', detail: 'Invoice officially posted to ledger.', life: 3000 });
        loadInvoices();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to post invoice', life: 3000 });
    }
};

const postBill = async (bill) => {
    if (!confirm(`Are you sure you want to officially post bill ${bill.bill_number}?`)) return;
    try {
        await axios.patch(`/api/bills/${bill.id}/post`);
        toast.add({ severity: 'success', summary: 'Posted', detail: 'Bill officially posted to ledger.', life: 3000 });
        loadBills();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to post bill', life: 3000 });
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const getStatusColor = (status) => {
    switch (status) {
        case 'DRAFT': return 'bg-panel-hover text-secondary border-panel-border';
        case 'OPEN': 
        case 'POSTED': return 'bg-sky-500/10 text-sky-400 border-sky-500/20';
        case 'PAID': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'VOID': return 'bg-red-500/10 text-red-400 border-red-500/20';
        default: return 'bg-panel-hover text-secondary border-panel-border';
    }
};

const tablePt = {
    root: { class: '!bg-transparent' },
    bodyrow: { class: 'hover:!bg-panel-hover !transition-all duration-200 cursor-pointer' },
    header: { class: '!bg-panel-hover !border-panel-border !text-primary !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' }
};

const handleAction = async (type, id, action) => {
    const verb = action === 'post' ? 'Officially post' : 'Are you sure you want to VOID';
    if (!confirm(`${verb} this document?`)) return;

    try {
        await axios.patch(`/api/${type}/${id}/${action}`);
        toast.add({ severity: 'success', summary: 'Success', detail: `Document ${action}ed.` });
        if (financeMode.value === 'RECEIVABLE') {
            loadInvoices();
        } else {
            loadBills();
            loadVendorPayments();
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || `Could not ${action} document.` });
    }
};

</script>

<template>
    <AppLayout>
        <Head title="Finance Center" />
        <Toast />

        <div class="p-4 bg-deep min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="w-full mb-6 flex justify-between items-end">
                <div class="flex flex-col">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] font-mono">
                            {{ financeMode === 'RECEIVABLE' ? 'Accounts Receivable' : 'Accounts Payable' }}
                        </span>
                        <div class="h-px w-8 bg-panel-hover"></div>
                        <div class="flex p-1 rounded-xl border border-panel-border bg-deep/40 backdrop-blur-xl shadow-inner">
                            <button @click="financeMode = 'RECEIVABLE'" 
                                    :class="financeMode === 'RECEIVABLE' 
                                        ? 'bg-sky-500/10 border-sky-500/40 text-sky-400 shadow-[0_0_15px_rgba(14,165,233,0.1)]' 
                                        : 'text-muted hover:text-secondary border-transparent bg-transparent'"
                                    class="px-6 py-2 text-[10px] uppercase tracking-[0.2em] rounded-lg transition-all duration-500 font-black border active:scale-95 flex items-center gap-2">
                                <div v-if="financeMode === 'RECEIVABLE'" class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></div>
                                Receivables
                            </button>
                            <button @click="financeMode = 'PAYABLE'" 
                                    :class="financeMode === 'PAYABLE' 
                                        ? 'bg-amber-500/10 border-amber-500/40 text-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.1)]' 
                                        : 'text-muted hover:text-secondary border-transparent bg-transparent'"
                                    class="px-6 py-2 text-[10px] uppercase tracking-[0.2em] rounded-lg transition-all duration-500 font-black border active:scale-95 flex items-center gap-2">
                                <div v-if="financeMode === 'PAYABLE'" class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                                Payables
                            </button>
                        </div>
                    </div>
                    <h1 class="text-3xl font-bold text-primary tracking-tight m-0 mb-2">
                        {{ financeMode === 'RECEIVABLE' ? 'Receivable Management' : 'Vendor Settle Center' }}
                    </h1>
                    <p class="text-secondary text-sm max-w-2xl leading-relaxed">
                        {{ financeMode === 'RECEIVABLE' 
                            ? 'Manage customer invoices, record incoming payments, and generate unified running-balance statements.' 
                            : 'Track vendor bills, record payments/disbursements, and monitor your accounts payable balance.' }}
                    </p>
                </div>
            </div>

            <!-- Actions Bar (Sharp Design) -->
            <div class="mb-8 p-6 bg-panel/40 border border-panel-border rounded-2xl backdrop-blur-md flex items-center gap-8 shadow-2xl animate-in fade-in slide-in-from-top-4 duration-700">
                <div class="flex flex-col border-r border-panel-border pr-8">
                    <span class="text-[9px] font-bold text-muted uppercase tracking-[0.3em] font-mono leading-none mb-1">Actions</span>
                    <span class="text-[11px] font-bold text-secondary uppercase tracking-tight">
                        {{ financeMode === 'RECEIVABLE' ? 'Receivable Management' : 'Payable Settlements' }}
                    </span>
                </div>

                <!-- Receivable Actions -->
                <div v-if="financeMode === 'RECEIVABLE'" class="flex gap-4">
                    <button @click="router.visit('/finance/invoices/create')" 
                            class="px-6 h-11 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400 text-[10px] font-bold uppercase tracking-widest hover:bg-sky-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                        <i class="pi pi-file-invoice" /> New Invoice
                    </button>
                    <button @click="router.visit('/finance/payments/create')" 
                            class="px-6 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-bold uppercase tracking-widest hover:bg-emerald-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                        <i class="pi pi-credit-card" /> Record Payment
                    </button>
                    <button @click="activeTab = 'statements'" 
                            class="px-6 h-11 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-bold uppercase tracking-widest hover:bg-indigo-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                        <i class="pi pi-book" /> Audit Statement
                    </button>
                </div>

                <!-- Payable Actions -->
                <div v-if="financeMode === 'PAYABLE'" class="flex gap-4">
                    <button @click="router.visit('/finance/bills/create')" 
                            class="px-6 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 text-[10px] font-bold uppercase tracking-widest hover:bg-amber-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                        <i class="pi pi-file-plus" /> Generate Bill
                    </button>
                    <button @click="router.visit('/finance/vendor-payments/create')" 
                            class="px-6 h-11 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[10px] font-bold uppercase tracking-widest hover:bg-rose-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                        <i class="pi pi-money-bill" /> Record Disbursement
                    </button>
                    <button @click="router.visit('/purchase-orders')" 
                            class="px-6 h-11 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-bold uppercase tracking-widest hover:bg-indigo-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                        <i class="pi pi-search" /> View Receipts (GRN)
                    </button>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="w-full mb-6 flex items-center gap-6 border-b border-panel-border pb-0">
                <div @click="activeTab = 'invoices'" 
                     class="pb-4 px-2 cursor-pointer font-bold text-[11px] uppercase tracking-widest transition-all border-b-2"
                     :class="activeTab === 'invoices' ? (financeMode === 'RECEIVABLE' ? 'text-sky-400 border-sky-400' : 'text-amber-500 border-amber-500') : 'text-secondary border-transparent hover:text-primary'">
                    {{ financeMode === 'RECEIVABLE' ? 'Invoices & Credit Notes' : 'Bills & Debit Notes' }}
                </div>
                <div @click="activeTab = 'payments'" 
                     class="pb-4 px-2 cursor-pointer font-bold text-[11px] uppercase tracking-widest transition-all border-b-2"
                     :class="activeTab === 'payments' ? (financeMode === 'RECEIVABLE' ? 'text-sky-400 border-sky-400' : 'text-amber-500 border-amber-500') : 'text-secondary border-transparent hover:text-primary'">
                    Payments
                </div>
                <div @click="activeTab = 'statements'" 
                     class="pb-4 px-2 cursor-pointer font-bold text-[11px] uppercase tracking-widest transition-all border-b-2"
                     :class="activeTab === 'statements' ? (financeMode === 'RECEIVABLE' ? 'text-sky-400 border-sky-400' : 'text-amber-500 border-amber-500') : 'text-secondary border-transparent hover:text-primary'">
                    {{ financeMode === 'RECEIVABLE' ? 'Customer Statements' : 'Vendor Statements' }}
                </div>
            </div>

            <!-- Workspace -->
            <div class="w-full flex-1 min-h-0 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm flex flex-col relative group">
                <!-- Background Accent -->
                <div class="absolute top-0 right-0 w-96 h-96 blur-[120px] -mr-48 -mt-48 rounded-full pointer-events-none opacity-50"
                     :class="financeMode === 'RECEIVABLE' ? 'bg-sky-500/5' : 'bg-amber-500/5'"></div>

                <template v-if="activeTab === 'invoices'">
                    <div class="flex-1 min-h-0 flex flex-col relative z-10 p-0">
                        <!-- AR INVOICES -->
                        <DataTable v-if="financeMode === 'RECEIVABLE'"
                            :value="invoices" 
                            :loading="loadingInvoices"
                            scrollable scrollHeight="flex"
                            class="gh-table border-none" :pt="tablePt"
                            @row-click="(e) => router.visit('/finance/invoices/' + e.data.id)"
                        >
                            <template #empty>
                                <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                    <i class="pi pi-file-invoice text-5xl mb-6"></i>
                                    <p class="font-mono text-xs tracking-[0.2em] uppercase">No invoices found in ledger</p>
                                </div>
                            </template>
                            <Column field="invoice_number" header="Invoice #" style="width: 200px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] bg-deep text-primary px-2 py-0.5 border border-panel-border rounded tracking-widest">{{ data.invoice_number }}</span>
                                    <span v-if="data.type === 'CREDIT_NOTE'" class="ml-2 text-[8px] bg-rose-500/10 text-rose-400 px-1 py-0.5 rounded border border-rose-500/20 font-bold uppercase tracking-widest">CREDIT NOTE</span>
                                </template>
                            </Column>
                            <Column field="invoice_date" header="Date" style="width: 130px">
                                <template #body="{ data }"><span class="font-mono text-[11px] text-secondary">{{ data.invoice_date }}</span></template>
                            </Column>
                            <Column header="Customer">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-xs text-primary tracking-tight">{{ data.customer?.name }}</span>
                                        <span class="font-mono text-[9px] text-muted tracking-widest">{{ data.customer?.customer_code }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Sales Order" style="width: 150px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] text-amber-400/80 cursor-pointer hover:text-amber-400 hover:underline transition-colors" @click.stop="router.visit(`/sales-orders/${data.sales_order_id}`)">
                                        {{ data.sales_order?.so_number }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Status" style="width: 120px">
                                <template #body="{ data }">
                                    <div class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold tracking-[0.1em] font-mono" :class="getStatusColor(data.status)">{{ data.status }}</div>
                                </template>
                            </Column>
                            <Column header="Total Amount" style="width: 150px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-primary">{{ formatCurrency(data.total_amount) }}</span></template>
                            </Column>
                             <Column header="Balance Due" style="width: 150px">
                                 <template #body="{ data }">
                                     <span class="font-mono font-bold tracking-tight text-[11px]" :class="Number(data.balance_due) > 0 ? 'text-rose-400' : 'text-emerald-400'">{{ formatCurrency(data.balance_due) }}</span>
                                 </template>
                             </Column>
                             <Column header="Actions" style="width: 140px">
                                 <template #body="{ data }">
                                     <div class="flex items-center gap-2">
                                         <button @click.stop="data.status === 'DRAFT' ? handleAction('invoices', data.id, 'post') : handleAction('invoices', data.id, 'void')" 
                                                 :class="data.status === 'DRAFT' ? 'bg-sky-500/10 border-sky-500/20 text-sky-400 hover:bg-sky-500' : 'bg-rose-500/10 border-rose-500/20 text-rose-400 hover:bg-rose-500'"
                                                 class="px-2 h-7 border rounded text-[9px] font-bold uppercase tracking-widest transition-colors font-mono hover:text-primary"
                                                 v-if="['DRAFT', 'OPEN'].includes(data.status)">
                                             {{ data.status === 'DRAFT' ? 'Post' : 'Void' }}
                                         </button>
                                         <button @click.stop="router.visit('/finance/invoices/' + data.id)" class="px-2 h-7 bg-panel-hover border border-zinc-700 text-secondary hover:text-primary rounded text-[9px] font-bold uppercase tracking-widest transition-colors font-mono">
                                             View
                                         </button>
                                     </div>
                                 </template>
                             </Column>
                        </DataTable>

                        <!-- AP BILLS -->
                        <DataTable v-else
                            :value="bills" 
                            :loading="loadingBills"
                            scrollable scrollHeight="flex"
                            class="gh-table border-none" :pt="tablePt"
                            @row-click="(e) => router.visit('/finance/bills/' + e.data.id)"
                        >
                            <template #empty>
                                <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                    <i class="pi pi-file-excel text-5xl mb-6"></i>
                                    <p class="font-mono text-xs tracking-[0.2em] uppercase">No vendor bills recorded</p>
                                </div>
                            </template>
                            <Column field="bill_number" header="Doc #" style="width: 200px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] bg-deep text-primary px-2 py-0.5 border border-panel-border rounded tracking-widest">{{ data.bill_number }}</span>
                                    <span v-if="data.type === 'DEBIT_NOTE'" class="ml-2 text-[8px] bg-rose-500/10 text-rose-400 px-1 py-0.5 rounded border border-rose-500/20 font-bold uppercase tracking-widest">DEBIT NOTE</span>
                                </template>
                            </Column>
                            <Column field="bill_date" header="Date" style="width: 130px">
                                <template #body="{ data }"><span class="font-mono text-[11px] text-secondary">{{ data.bill_date }}</span></template>
                            </Column>
                            <Column header="Vendor">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-xs text-primary tracking-tight">{{ data.vendor?.name }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Purchase Order" style="width: 150px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] text-sky-400/80 cursor-pointer hover:text-sky-400 hover:underline transition-colors" @click.stop="router.visit(`/purchase-orders/${data.purchase_order_id}`)">
                                        {{ data.purchase_order?.po_number }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Status" style="width: 120px">
                                <template #body="{ data }">
                                    <div class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold tracking-[0.1em] font-mono" :class="getStatusColor(data.status)">{{ data.status }}</div>
                                </template>
                            </Column>
                            <Column header="Total Amount" style="width: 150px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-primary">{{ formatCurrency(data.total_amount) }}</span></template>
                            </Column>
                            <Column header="Balance Due" style="width: 150px">
                                <template #body="{ data }">
                                    <span class="font-mono font-bold tracking-tight text-[11px]" :class="Number(data.balance_due) > 0 ? 'text-rose-400' : 'text-emerald-400'">{{ formatCurrency(data.balance_due) }}</span>
                                </template>
                            </Column>
                            <Column header="Actions" style="width: 140px">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <button @click.stop="data.status === 'DRAFT' ? handleAction('bills', data.id, 'post') : handleAction('bills', data.id, 'void')" 
                                                :class="data.status === 'DRAFT' ? 'bg-amber-500/10 border-amber-500/20 text-amber-500 hover:bg-amber-500' : 'bg-rose-500/10 border-rose-500/20 text-rose-400 hover:bg-rose-500'"
                                                class="px-2 h-7 border rounded text-[9px] font-bold uppercase tracking-widest transition-colors font-mono hover:text-primary"
                                                v-if="['DRAFT', 'POSTED'].includes(data.status)">
                                            {{ data.status === 'DRAFT' ? 'Post' : 'Void' }}
                                        </button>
                                        <button @click.stop="router.visit('/finance/bills/' + data.id)" class="px-2 h-7 bg-panel-hover border border-zinc-700 text-secondary hover:text-primary rounded text-[9px] font-bold uppercase tracking-widest transition-colors font-mono">
                                            View
                                        </button>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>

                <template v-if="activeTab === 'payments'">
                    <div class="flex-1 min-h-0 flex flex-col relative z-10">
                        <!-- AR PAYMENTS -->
                        <DataTable v-if="financeMode === 'RECEIVABLE'"
                            :value="payments" :loading="loadingPayments"
                            scrollable scrollHeight="flex"
                            class="gh-table border-none" :pt="tablePt"
                            @row-click="(e) => router.visit('/finance/payments/' + e.data.id)"
                        >
                            <template #empty>
                                <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                    <i class="pi pi-credit-card text-5xl mb-6"></i>
                                    <p class="font-mono text-xs tracking-[0.2em] uppercase">No incoming payments recorded</p>
                                </div>
                            </template>
                            <Column field="payment_number" header="Receipt #" style="width: 200px">
                                <template #body="{ data }"><span class="font-mono text-[11px] bg-deep text-primary px-2 py-0.5 border border-panel-border rounded tracking-widest">{{ data.payment_number }}</span></template>
                            </Column>
                            <Column field="payment_date" header="Date" style="width: 130px">
                                <template #body="{ data }"><span class="font-mono text-[11px] text-secondary">{{ data.payment_date }}</span></template>
                            </Column>
                            <Column header="Customer">
                                <template #body="{ data }">
                                    <div class="flex flex-col"><span class="font-bold text-xs text-primary tracking-tight">{{ data.customer?.name }}</span></div>
                                </template>
                            </Column>
                            <Column header="Total Received" style="width: 140px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-emerald-400">{{ formatCurrency(data.amount) }}</span></template>
                            </Column>
                            <Column header="Refunded" style="width: 130px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-rose-400" v-if="Number(data.refunded_amount) > 0">{{ formatCurrency(data.refunded_amount) }}</span><span v-else class="text-muted font-mono text-[11px]">-</span></template>
                            </Column>
                            <Column header="Remaining Balance" style="width: 160px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-sky-400">{{ formatCurrency(data.unallocated_amount) }}</span></template>
                            </Column>
                        </DataTable>

                        <!-- AP PAYMENTS -->
                        <DataTable v-else
                            :value="vendorPayments" :loading="loadingVendorPayments"
                            scrollable scrollHeight="flex"
                            class="gh-table border-none" :pt="tablePt"
                            @row-click="(e) => router.visit('/finance/vendor-payments/' + e.data.id)"
                        >
                            <template #empty>
                                <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                    <i class="pi pi-send text-5xl mb-6"></i>
                                    <p class="font-mono text-xs tracking-[0.2em] uppercase">No vendor disbursements recorded</p>
                                </div>
                            </template>
                            <Column field="payment_number" header="Disbursement #" style="width: 200px">
                                <template #body="{ data }"><span class="font-mono text-[11px] bg-deep text-primary px-2 py-0.5 border border-panel-border rounded tracking-widest">{{ data.payment_number }}</span></template>
                            </Column>
                            <Column field="payment_date" header="Date" style="width: 130px">
                                <template #body="{ data }"><span class="font-mono text-[11px] text-secondary">{{ data.payment_date }}</span></template>
                            </Column>
                            <Column header="Vendor">
                                <template #body="{ data }">
                                    <div class="flex flex-col"><span class="font-bold text-xs text-primary tracking-tight">{{ data.vendor?.name }}</span></div>
                                </template>
                            </Column>
                            <Column header="Total Paid" style="width: 150px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-amber-400">{{ formatCurrency(data.amount) }}</span></template>
                            </Column>
                            <Column header="Refunded" style="width: 130px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-rose-400" v-if="Number(data.refunded_amount) > 0">{{ formatCurrency(data.refunded_amount) }}</span><span v-else class="text-muted font-mono text-[11px]">-</span></template>
                            </Column>
                            <Column header="Remaining Balance" style="width: 160px">
                                <template #body="{ data }"><span class="font-mono font-bold tracking-tight text-[11px] text-sky-400">{{ formatCurrency(data.unallocated_amount) }}</span></template>
                            </Column>
                            <Column header="Method" style="width: 120px">
                                <template #body="{ data }"><span class="text-[9px] font-bold uppercase tracking-widest font-mono">{{ data.payment_method }}</span></template>
                            </Column>
                        </DataTable>
                    </div>
                </template>

                <template v-if="activeTab === 'statements'">
                    <div class="flex-1 min-h-0 flex flex-col relative z-10 p-6">
                        <div class="flex items-center gap-4 mb-6 relative z-30">
                            <div class="w-80">
                                <Select v-if="financeMode === 'RECEIVABLE'" v-model="selectedStatementCustomer" :options="customers" optionLabel="name" placeholder="Select Customer to View Statement" 
                                        class="w-full bg-deep/80 border-panel-border text-primary shadow-xl backdrop-blur-md" filter />
                                <Select v-else v-model="selectedStatementVendor" :options="vendors" optionLabel="name" placeholder="Select Vendor to View Statement" 
                                        class="w-full bg-deep/80 border-panel-border text-primary shadow-xl backdrop-blur-md" filter />
                            </div>
                        </div>

                        <div v-if="statementData" class="flex-1 min-h-0 flex flex-col">
                            <div class="grid grid-cols-4 gap-4 mb-6">
                                <div class="bg-deep/80 border border-panel-border rounded-xl p-4 flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-1">{{ financeMode === 'RECEIVABLE' ? 'Customer Exposure' : 'Vendor Liability' }}</span>
                                    <span class="text-xl font-bold font-mono text-primary">{{ formatCurrency(financeMode === 'RECEIVABLE' ? statementData.customer?.exposure || 0 : statementData.summary.closing_balance) }}</span>
                                </div>
                                <div class="bg-deep/80 border border-panel-border rounded-xl p-4 flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-1">Total Debits</span>
                                    <span class="text-xl font-bold font-mono text-primary/80">{{ formatCurrency(statementData.summary.total_debits) }}</span>
                                </div>
                                <div class="bg-deep/80 border border-panel-border rounded-xl p-4 flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-1">Total Credits</span>
                                    <span class="text-xl font-bold font-mono" :class="financeMode === 'RECEIVABLE' ? 'text-emerald-400' : 'text-amber-400'">{{ formatCurrency(statementData.summary.total_credits) }}</span>
                                </div>
                                <div class="border rounded-xl p-4 flex flex-col justify-center shadow-[0_0_20px_rgba(0,0,0,0.1)]"
                                     :class="financeMode === 'RECEIVABLE' ? 'bg-sky-500/10 border-sky-500/30' : 'bg-amber-500/10 border-amber-500/30'">
                                    <span class="text-[10px] font-bold uppercase tracking-widest mb-1" :class="financeMode === 'RECEIVABLE' ? 'text-sky-400' : 'text-amber-400'">Running Balance</span>
                                    <span class="text-2xl font-bold font-mono text-primary">{{ formatCurrency(statementData.summary.closing_balance) }}</span>
                                </div>
                            </div>

                            <DataTable :value="statementData.lines" :loading="loadingStatement" scrollable scrollHeight="flex" 
                                       class="gh-table border border-panel-border/80 rounded-xl overflow-hidden bg-deep/50" :pt="tablePt"
                                       @row-click="(e) => e.data.link ? router.visit(e.data.link) : null"
                            >
                                <Column field="date" header="Date" style="width: 120px">
                                    <template #body="{ data }"><span class="font-mono text-[11px] text-secondary">{{ data.date }}</span></template>
                                </Column>
                                <Column header="Type" style="width: 140px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[10px] px-2 py-0.5 rounded border tracking-widest"
                                              :class="{
                                                  'bg-sky-500/10 text-sky-400 border-sky-500/20': data.type === 'INVOICE' || data.type === 'BILL',
                                                  'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': data.type === 'PAYMENT' && financeMode==='RECEIVABLE',
                                                  'bg-amber-500/10 text-amber-400 border-amber-500/20': data.type === 'PAYMENT' && financeMode==='PAYABLE',
                                                  'bg-rose-500/10 text-rose-400 border-rose-500/20': data.type === 'CREDIT_NOTE' || data.type === 'DEBIT_NOTE' || data.type === 'REFUND',
                                                  'bg-panel-hover text-secondary border-panel-border': data.type === 'OPENING_BALANCE'
                                              }">
                                            {{ data.type }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="reference" header="Reference" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] font-bold tracking-widest transition-colors"
                                              :class="data.link ? (financeMode === 'RECEIVABLE' ? 'text-sky-400 hover:text-sky-300' : 'text-amber-500 hover:text-amber-400') : 'text-secondary'">
                                            {{ data.reference || '-' }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="description" header="Description">
                                    <template #body="{ data }"><span class="text-xs text-secondary">{{ data.description }}</span></template>
                                </Column>
                                <Column header="Debit (+)" style="width: 130px" class="text-right">
                                    <template #body="{ data }">
                                        <span v-if="Number(data.debit) > 0" class="font-mono text-xs text-primary/70 font-bold">{{ formatCurrency(data.debit) }}</span>
                                        <span v-else class="text-muted font-mono">-</span>
                                    </template>
                                </Column>
                                <Column header="Credit (-)" style="width: 130px" class="text-right">
                                    <template #body="{ data }">
                                        <span v-if="Number(data.credit) > 0" class="font-mono text-xs font-bold" :class="financeMode === 'RECEIVABLE' ? 'text-emerald-400' : 'text-amber-400'">{{ formatCurrency(data.credit) }}</span>
                                        <span v-else class="text-muted font-mono">-</span>
                                    </template>
                                </Column>
                                <Column header="Balance" style="width: 150px" class="text-right border-l border-panel-border/50">
                                    <template #body="{ data }"><span class="font-mono text-[13px] font-bold text-primary tracking-tight">{{ formatCurrency(data.balance) }}</span></template>
                                </Column>
                            </DataTable>
                        </div>
                        
                        <div v-else class="flex-1 flex items-center justify-center relative flex-col opacity-50 grayscale">
                            <i class="pi pi-book text-6xl" :class="financeMode === 'RECEIVABLE' ? 'text-sky-400' : 'text-amber-400'"></i>
                            <h2 class="text-xl font-bold tracking-tight text-primary mb-2">{{ financeMode === 'RECEIVABLE' ? 'Customer Statements' : 'Vendor Ledger Viewer' }}</h2>
                            <p class="text-secondary text-sm max-w-md text-center">Select an entity from the dropdown to load the chronological accounting statement.</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
</style>


