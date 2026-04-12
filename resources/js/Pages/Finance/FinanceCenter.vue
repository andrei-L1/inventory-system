<script setup>
import { ref, onMounted, computed } from 'vue';
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

const activeTab = ref('invoices'); // invoices, payments, statements

// Data State
const invoices = ref([]);
const payments = ref([]);
const customers = ref([]);

const loadingInvoices = ref(false);
const loadingPayments = ref(false);

const invoiceFilters = ref({ status: null, type: null, search: '' });

// Statements State
const selectedStatementCustomer = ref(null);
const statementData = ref(null);
const loadingStatement = ref(false);

onMounted(async () => {
    await loadInitialData();
});

const loadInitialData = async () => {
    try {
        loadInvoices();
        loadPayments();
        loadCustomers();
    } catch (e) {
        console.error(e);
    }
};

const loadCustomers = async () => {
    try {
        const res = await axios.get('/api/customers');
        customers.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load customers', life: 3000 });
    }
};

import { watch } from 'vue';

watch(selectedStatementCustomer, async (newVal) => {
    if (!newVal) {
        statementData.value = null;
        return;
    }

    loadingStatement.value = true;
    try {
        // Optional: date range filtering can be added later
        const res = await axios.get(`/api/customers/${newVal.id}/statement`);
        statementData.value = res.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load customer statement', life: 3000 });
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

const postInvoice = async (invoice) => {
    if (!confirm(`Are you sure you want to officially post invoice ${invoice.invoice_number}? This cannot be undone.`)) return;
    
    try {
        const res = await axios.patch(`/api/invoices/${invoice.id}/post`);
        toast.add({ severity: 'success', summary: 'Posted', detail: 'Invoice officially posted to ledger.', life: 3000 });
        loadInvoices();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to post invoice', life: 3000 });
    }
};

const voidInvoice = async (invoice) => {
    if (!confirm(`Are you sure you want to VOID invoice ${invoice.invoice_number}?`)) return;
    
    try {
        const res = await axios.patch(`/api/invoices/${invoice.id}/void`);
        toast.add({ severity: 'success', summary: 'Voided', detail: 'Invoice has been voided.', life: 3000 });
        loadInvoices();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to void invoice', life: 3000 });
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const getStatusColor = (status) => {
    switch (status) {
        case 'DRAFT': return 'bg-zinc-800 text-zinc-400 border-zinc-700';
        case 'OPEN': return 'bg-sky-500/10 text-sky-400 border-sky-500/20';
        case 'PAID': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'VOID': return 'bg-red-500/10 text-red-400 border-red-500/20';
        default: return 'bg-zinc-800 text-zinc-400 border-zinc-700';
    }
};

const tablePt = {
    root: { class: '!bg-transparent' },
    bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200 cursor-pointer' },
    header: { class: '!bg-zinc-900/60 !border-zinc-800 !text-zinc-500 !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' }
};

</script>

<template>
    <AppLayout>
        <Head title="Finance Center" />
        <Toast />

        <div class="p-4 bg-zinc-950 min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="w-full mb-6 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Accounts Receivable</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Finance Center</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Manage customer invoices, record incoming payments, and generate unified running-balance statements.</p>
                </div>
                
                <div class="flex gap-4 items-center">
                    <button @click="router.visit('/finance/payments/create')" 
                            class="bg-zinc-900 border border-zinc-800 hover:border-sky-500/50 hover:bg-zinc-800 text-sky-400 px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 flex items-center gap-3">
                        <i class="pi pi-credit-card text-sm"></i>
                        <span>Record Payment</span>
                    </button>
                    <!-- Invoices are generated from SOs normally, so we might just direct them to SO list but let's provide a shortcut -->
                    <button @click="router.visit('/finance/invoices/create')" 
                            class="bg-sky-500 hover:bg-sky-400 text-zinc-950 px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 shadow-[0_0_20px_rgba(14,165,233,0.2)] flex items-center gap-3">
                        <i class="pi pi-file-invoice text-sm"></i>
                        <span>New Invoice</span>
                    </button>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="w-full mb-6 flex items-center gap-6 border-b border-zinc-900 pb-0">
                <div @click="activeTab = 'invoices'" 
                     class="pb-4 px-2 cursor-pointer font-bold text-[11px] uppercase tracking-widest transition-all border-b-2"
                     :class="activeTab === 'invoices' ? 'text-sky-400 border-sky-400' : 'text-zinc-500 border-transparent hover:text-zinc-300'">
                    Invoices & Credit Notes
                </div>
                <div @click="activeTab = 'payments'" 
                     class="pb-4 px-2 cursor-pointer font-bold text-[11px] uppercase tracking-widest transition-all border-b-2"
                     :class="activeTab === 'payments' ? 'text-sky-400 border-sky-400' : 'text-zinc-500 border-transparent hover:text-zinc-300'">
                    Payments
                </div>
                <div @click="activeTab = 'statements'" 
                     class="pb-4 px-2 cursor-pointer font-bold text-[11px] uppercase tracking-widest transition-all border-b-2"
                     :class="activeTab === 'statements' ? 'text-sky-400 border-sky-400' : 'text-zinc-500 border-transparent hover:text-zinc-300'">
                    Customer Statements
                </div>
            </div>

            <!-- Workspace -->
            <div class="w-full flex-1 min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm flex flex-col relative group">
                <!-- Background Accent -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-sky-500/5 blur-[120px] -mr-48 -mt-48 rounded-full pointer-events-none opacity-50"></div>

                <template v-if="activeTab === 'invoices'">
                    <div class="flex-1 min-h-0 flex flex-col relative z-10 p-0">
                        <DataTable 
                            :value="invoices" 
                            :loading="loadingInvoices"
                            scrollable 
                            scrollHeight="flex"
                            class="gh-table border-none"
                            :pt="tablePt"
                            @row-click="(e) => router.visit('/finance/invoices/' + e.data.id)"
                            :rowClass="() => 'cursor-pointer hover:bg-zinc-800/30 transition-colors'"
                        >
                            <template #empty>
                                <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                    <i class="pi pi-file-invoice text-5xl mb-6"></i>
                                    <p class="font-mono text-xs tracking-[0.2em] uppercase">No invoices found in ledger</p>
                                </div>
                            </template>

                            <Column field="invoice_number" header="Invoice #" style="width: 200px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] bg-zinc-950 text-white px-2 py-0.5 border border-zinc-800 rounded tracking-widest">{{ data.invoice_number }}</span>
                                    <span v-if="data.type === 'CREDIT_NOTE'" class="ml-2 text-[8px] bg-rose-500/10 text-rose-400 px-1 py-0.5 rounded border border-rose-500/20 font-bold uppercase tracking-widest">CREDIT NOTE</span>
                                </template>
                            </Column>

                            <Column field="invoice_date" header="Date" style="width: 130px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] text-zinc-400">{{ data.invoice_date }}</span>
                                </template>
                            </Column>

                            <Column header="Customer">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-xs text-zinc-200 tracking-tight">{{ data.customer?.name }}</span>
                                        <span class="font-mono text-[9px] text-zinc-600 tracking-widest">{{ data.customer?.customer_code }}</span>
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
                                    <div class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold tracking-[0.1em] font-mono" :class="getStatusColor(data.status)">
                                        {{ data.status }}
                                    </div>
                                </template>
                            </Column>

                            <Column header="Total Amount" style="width: 150px">
                                <template #body="{ data }">
                                    <span class="font-mono font-bold tracking-tight text-[11px] text-zinc-200">{{ formatCurrency(data.total_amount) }}</span>
                                </template>
                            </Column>

                            <Column header="Balance Due" style="width: 150px">
                                <template #body="{ data }">
                                    <span class="font-mono font-bold tracking-tight text-[11px]" :class="Number(data.balance) > 0 ? 'text-rose-400' : 'text-emerald-400'">
                                        {{ formatCurrency(data.balance) }}
                                    </span>
                                </template>
                            </Column>

                            <Column header="Actions" style="width: 140px">
                                <template #body="{ data }">
                                    <div class="flex gap-2">
                                        <button v-if="data.status === 'DRAFT'" @click.stop="postInvoice(data)" class="px-3 h-7 bg-sky-500/10 border border-sky-500/20 text-sky-400 hover:bg-sky-500 hover:text-white rounded text-[9px] font-bold uppercase tracking-widest transition-colors font-mono">
                                            Post
                                        </button>
                                        <button v-if="data.status === 'OPEN'" @click.stop="voidInvoice(data)" class="px-3 h-7 bg-zinc-800 border border-zinc-700 text-zinc-500 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/30 rounded text-[9px] font-bold uppercase tracking-widest transition-colors font-mono">
                                            Void
                                        </button>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>

                <template v-if="activeTab === 'payments'">
                    <div class="flex-1 min-h-0 flex flex-col relative z-10">
                        <DataTable 
                            :value="payments" 
                            :loading="loadingPayments"
                            scrollable 
                            scrollHeight="flex"
                            class="gh-table border-none"
                            :pt="tablePt"
                            @row-click="(e) => router.visit('/finance/payments/' + e.data.id)"
                            :rowClass="() => 'cursor-pointer hover:bg-zinc-800/30 transition-colors'"
                        >
                            <template #empty>
                                <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                    <i class="pi pi-credit-card text-5xl mb-6"></i>
                                    <p class="font-mono text-xs tracking-[0.2em] uppercase">No payments recorded</p>
                                </div>
                            </template>

                            <Column field="payment_number" header="Payment #" style="width: 200px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] bg-zinc-950 text-white px-2 py-0.5 border border-zinc-800 rounded tracking-widest">{{ data.payment_number }}</span>
                                </template>
                            </Column>

                            <Column field="payment_date" header="Date" style="width: 130px">
                                <template #body="{ data }">
                                    <span class="font-mono text-[11px] text-zinc-400">{{ data.payment_date }}</span>
                                </template>
                            </Column>

                            <Column header="Customer">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-xs text-zinc-200 tracking-tight">{{ data.customer?.name }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Total Received" style="width: 150px">
                                <template #body="{ data }">
                                    <span class="font-mono font-bold tracking-tight text-[11px] text-emerald-400">{{ formatCurrency(data.amount) }}</span>
                                </template>
                            </Column>

                            <Column header="Unallocated Credit" style="width: 180px">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold tracking-tight text-[11px]" :class="Number(data.unallocated_amount) > 0 ? 'text-amber-400' : 'text-zinc-600'">
                                            {{ formatCurrency(data.unallocated_amount) }}
                                        </span>
                                        <span v-if="Number(data.unallocated_amount) > 0" class="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.8)]"></span>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>

                <template v-if="activeTab === 'statements'">
                    <div class="flex-1 min-h-0 flex flex-col relative z-10 p-6">
                        <!-- Controls -->
                        <div class="flex items-center gap-4 mb-6 relative z-30">
                            <div class="w-80">
                                <Select v-model="selectedStatementCustomer" :options="customers" optionLabel="name" placeholder="Select Customer to View Statement" 
                                        class="w-full bg-zinc-950/80 border-zinc-700 text-white shadow-xl backdrop-blur-md" 
                                        filter />
                            </div>
                        </div>

                        <!-- Statement Result -->
                        <div v-if="statementData" class="flex-1 min-h-0 flex flex-col">
                            <!-- Statement Summary Header -->
                            <div class="grid grid-cols-4 gap-4 mb-6">
                                <div class="bg-zinc-950/80 border border-zinc-800 rounded-xl p-4 flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1">Customer Exposure</span>
                                    <span class="text-xl font-bold font-mono text-white">{{ formatCurrency(statementData.customer.exposure) }}</span>
                                </div>
                                <div class="bg-zinc-950/80 border border-zinc-800 rounded-xl p-4 flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1">Total Debits</span>
                                    <span class="text-xl font-bold font-mono text-zinc-300">{{ formatCurrency(statementData.summary.total_debits) }}</span>
                                </div>
                                <div class="bg-zinc-950/80 border border-zinc-800 rounded-xl p-4 flex flex-col justify-center">
                                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1">Total Credits</span>
                                    <span class="text-xl font-bold font-mono text-emerald-400">{{ formatCurrency(statementData.summary.total_credits) }}</span>
                                </div>
                                <div class="bg-sky-500/10 border border-sky-500/30 rounded-xl p-4 flex flex-col justify-center shadow-[0_0_20px_rgba(14,165,233,0.1)]">
                                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-widest mb-1">Closing Balance</span>
                                    <span class="text-2xl font-bold font-mono text-white">{{ formatCurrency(statementData.summary.closing_balance) }}</span>
                                </div>
                            </div>

                            <!-- Ledger Table -->
                            <DataTable 
                                :value="statementData.lines" 
                                :loading="loadingStatement"
                                scrollable 
                                scrollHeight="flex"
                                class="gh-table border border-zinc-800/80 rounded-xl overflow-hidden bg-zinc-950/50"
                                :pt="tablePt"
                                @row-click="(e) => {
                                    const row = e.data;
                                    if (row.type === 'INVOICE' || row.type === 'CREDIT_NOTE') {
                                        router.visit('/finance/invoices/' + row.id);
                                    } else if (row.type === 'PAYMENT' || row.type === 'REFUND') {
                                        router.visit('/finance/payments/' + row.id);
                                    }
                                }"
                                :rowClass="() => 'cursor-pointer hover:bg-zinc-800/30 transition-colors'"
                            >
                                <Column field="date" header="Date" style="width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-zinc-400">{{ data.date }}</span>
                                    </template>
                                </Column>

                                <Column header="Type" style="width: 140px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[10px] px-2 py-0.5 rounded border tracking-widest"
                                              :class="{
                                                  'bg-sky-500/10 text-sky-400 border-sky-500/20': data.type === 'INVOICE',
                                                  'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': data.type === 'PAYMENT',
                                                  'bg-rose-500/10 text-rose-400 border-rose-500/20': data.type === 'CREDIT_NOTE',
                                                  'bg-rose-500/10 text-rose-400 border-rose-500/20': data.type === 'REFUND',
                                                  'bg-zinc-800 text-zinc-400 border-zinc-700': data.type === 'OPENING_BALANCE'
                                              }">
                                            {{ data.type }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="reference" header="Reference" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] font-bold text-white tracking-widest">{{ data.reference || '-' }}</span>
                                    </template>
                                </Column>

                                <Column field="description" header="Description">
                                    <template #body="{ data }">
                                        <span class="text-xs text-zinc-400">{{ data.description }}</span>
                                    </template>
                                </Column>

                                <Column header="Debit (Charge)" style="width: 130px" class="text-right">
                                    <template #body="{ data }">
                                        <span v-if="Number(data.debit) > 0" class="font-mono text-xs text-zinc-300 font-bold">{{ formatCurrency(data.debit) }}</span>
                                        <span v-else class="text-zinc-700 font-mono">-</span>
                                    </template>
                                </Column>

                                <Column header="Credit (Payment)" style="width: 130px" class="text-right">
                                    <template #body="{ data }">
                                        <span v-if="Number(data.credit) > 0" class="font-mono text-xs text-emerald-400 font-bold">{{ formatCurrency(data.credit) }}</span>
                                        <span v-else class="text-zinc-700 font-mono">-</span>
                                    </template>
                                </Column>

                                <Column header="Running Balance" style="width: 150px" class="text-right border-l border-zinc-800/50">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[13px] font-bold tracking-tight" :class="Number(data.balance_raw) > 0 ? 'text-white' : 'text-emerald-400'">
                                            {{ formatCurrency(data.balance) }}
                                        </span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        
                        <div v-else class="flex-1 flex items-center justify-center relative flex-col opacity-50 grayscale">
                            <i class="pi pi-book text-6xl text-sky-400 mb-6"></i>
                            <h2 class="text-xl font-bold tracking-tight text-white mb-2">Customer Statements Viewer</h2>
                            <p class="text-zinc-500 text-sm max-w-md text-center">Select a customer from the dropdown above to load their chronological accounting ledger.</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
</style>
