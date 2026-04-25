<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import Toast from 'primevue/toast';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import axios from 'axios';
import Menu from 'primevue/menu';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();
const menu = ref(null);

const toggleMenu = (event) => {
    menu.value.toggle(event);
};

const actionOptions = [
    { 
        label: 'New Sales Order', 
        icon: 'pi pi-shopping-cart', 
        command: () => router.visit('/sales-orders/create') 
    },
    { 
        label: 'New Invoice', 
        icon: 'pi pi-file-invoice', 
        command: () => router.visit('/finance/invoices/create') 
    },
    { 
        label: 'Receive Payment', 
        icon: 'pi pi-credit-card', 
        command: () => router.visit('/finance/payments/create') 
    }
];

const customers = ref([]);
const selectedCustomer = ref(null);
const history = ref([]);
const invoices = ref([]);
const payments = ref([]);
const statement = ref([]);
const loadingCustomers = ref(false);
const loadingHistory = ref(false);
const loadingStatement = ref(false);
const search = ref('');
const activeTab = ref('orders');

const dialogVisible = ref(false);
const submitted = ref(false);

const customerForm = ref({
    id: null,
    customer_code: '',
    name: '',
    email: '',
    phone: '',
    billing_address: '',
    shipping_address: '',
    tax_number: '',
    credit_limit: 0,
    is_active: true
});

// Credit utilization percentage
const creditUtilization = computed(() => {
    if (!selectedCustomer.value) return 0;
    const limit = Number(selectedCustomer.value.credit_limit || 0);
    const exposure = Number(selectedCustomer.value.exposure || 0);
    if (limit <= 0) return 0;
    return Math.min(100, Math.round((exposure / limit) * 100));
});

const creditUtilizationColor = computed(() => {
    const pct = creditUtilization.value;
    if (pct >= 90) return 'bg-rose-500';
    if (pct >= 70) return 'bg-amber-400';
    return 'bg-cyan-500';
});

const availableCredit = computed(() => {
    if (!selectedCustomer.value) return 0;
    const limit = Number(selectedCustomer.value.credit_limit || 0);
    const exposure = Number(selectedCustomer.value.exposure || 0);
    return Math.max(0, limit - exposure);
});

const listboxPt = {
    root: { class: '!p-2 h-full flex flex-col' },
    listContainer: { class: '!max-h-none flex-1 overflow-y-auto custom-scrollbar' },
    item: (options) => ({
        class: [
            '!p-2.5 !mb-1 !rounded-xl !transition-all !duration-300 !border',
            options.context.selected 
                ? '!bg-sky-500/10 !border-sky-500/20 !text-primary shadow-[0_0_15px_rgba(14,165,233,0.05)]' 
                : '!bg-transparent !border-transparent !text-secondary hover:!bg-panel-hover/40 hover:!text-primary'
        ]
    })
};

const tablePt = {
    root: { class: '!bg-transparent' },
    bodyrow: { class: 'hover:!bg-panel-hover !transition-all duration-200' },
    header: { class: '!bg-panel-hover !border-panel-border !text-primary !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' }
};

const loadCustomers = async () => {
    loadingCustomers.value = true;
    try {
        const res = await axios.get('/api/customers', { params: { query: search.value } });
        customers.value = res.data.data;
        if (customers.value.length > 0 && !selectedCustomer.value) {
            const urlParams = new URLSearchParams(window.location.search);
            const customerId = urlParams.get('customer_id');
            selectedCustomer.value = customerId
                ? (customers.value.find(c => c.id == customerId) || customers.value[0])
                : customers.value[0];
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingCustomers.value = false;
    }
};

const loadHistory = async () => {
    if (!selectedCustomer.value) return;
    loadingHistory.value = true;
    try {
        const res = await axios.get(`/api/customers/${selectedCustomer.value.id}/transactions`);
        history.value = res.data.orders;
        invoices.value = res.data.invoices;
        payments.value = res.data.payments || [];
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

const loadStatement = async () => {
    if (!selectedCustomer.value) return;
    loadingStatement.value = true;
    try {
        const res = await axios.get(`/api/customers/${selectedCustomer.value.id}/statement`);
        statement.value = res.data.lines || [];
    } catch (e) {
        console.error(e);
    } finally {
        loadingStatement.value = false;
    }
};

watch(activeTab, (newTab) => {
    if (newTab === 'statement' && statement.value.length === 0) {
        loadStatement();
    }
});

onMounted(loadCustomers);

const page = usePage();
watch(() => page.url, () => {
    const urlParams = new URLSearchParams(window.location.search);
    const customerId = urlParams.get('customer_id');
    if (customerId && customers.value.length > 0) {
        selectedCustomer.value = customers.value.find(c => c.id == customerId) || selectedCustomer.value;
    }
});

watch(selectedCustomer, () => { 
    statement.value = []; // Reset statement when customer changes
    loadHistory(); 
    if (activeTab.value === 'statement') {
        loadStatement();
    }
});

const openNew = () => {
    customerForm.value = { id: null, customer_code: '', name: '', email: '', phone: '', billing_address: '', shipping_address: '', tax_number: '', credit_limit: 0, is_active: true };
    submitted.value = false;
    dialogVisible.value = true;
};

const editCustomer = () => {
    customerForm.value = { ...selectedCustomer.value };
    submitted.value = false;
    dialogVisible.value = true;
};

const saveCustomer = async () => {
    submitted.value = true;
    if (!customerForm.value.name || !customerForm.value.customer_code) {
        toast.add({ severity: 'warn', summary: 'Missing Information', detail: 'Customer Name and Code are required.', life: 4000 });
        return;
    }
    try {
        if (customerForm.value.id) {
            await axios.put(`/api/customers/${customerForm.value.id}`, customerForm.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Customer updated successfully.', life: 3000 });
        } else {
            await axios.post('/api/customers', customerForm.value);
            toast.add({ severity: 'success', summary: 'Registered', detail: 'New customer added.', life: 3000 });
        }
        dialogVisible.value = false;
        loadCustomers();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save customer.', life: 3000 });
    }
};

const deleteCustomer = () => {
    confirm.require({
        message: 'Are you sure you want to remove this customer permanently?',
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/customers/${selectedCustomer.value.id}`);
                selectedCustomer.value = null;
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Customer removed.', life: 3000 });
                loadCustomers();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to delete.', life: 4000 });
            }
        }
    });
};

const getStatusClasses = (statusName) => {
    const s = (statusName || '').toLowerCase();
    if (['closed', 'fulfilled', 'shipped', 'paid'].includes(s)) return { dot: 'bg-emerald-500', text: 'text-emerald-400' };
    if (['confirmed', 'processing', 'open'].includes(s)) return { dot: 'bg-cyan-400', text: 'text-cyan-400' };
    if (['quotation', 'draft'].includes(s)) return { dot: 'bg-amber-400', text: 'text-amber-400' };
    if (['cancelled', 'void'].includes(s)) return { dot: 'bg-rose-500', text: 'text-rose-400' };
    return { dot: 'bg-zinc-600', text: 'text-secondary' };
};

const formatCurrency = (val) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
</script>

<template>
    <AppLayout>
        <Head title="Customer Center" />
        <Toast />

        <div class="p-4 bg-deep min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-6 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Customer Relations</span>
                    <h1 class="text-3xl font-bold text-primary tracking-tight m-0 mb-2">Customer Center</h1>
                    <p class="text-secondary text-sm max-w-2xl leading-relaxed">Manage clients, track transaction history, and monitor accounts receivable exposure in real-time.</p>
                </div>
                
                <div class="flex gap-4 items-center">
                    <button v-if="can('manage-customers')" @click="openNew" 
                            class="bg-sky-500 hover:bg-sky-400 text-primary px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 shadow-[0_0_20px_rgba(14,165,233,0.2)] flex items-center gap-3">
                        <i class="pi pi-user-plus text-sm"></i>
                        <span>New Customer</span>
                    </button>
                    <button @click="toggleMenu" 
                            class="bg-panel border border-panel-border hover:border-sky-500/50 hover:bg-panel-hover text-sky-400 px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 flex items-center gap-3">
                        <i class="pi pi-plus text-sm"></i>
                        <span>Quick Actions</span>
                    </button>
                    <Menu ref="menu" :model="actionOptions" :popup="true" class="!bg-panel !border-panel-border !p-2 !rounded-xl !min-w-[200px]" :pt="{
                        itemlink: { class: 'hover:!bg-panel-hover !rounded-lg !p-3 transition-all text-primary' },
                        itemlabel: { class: '!text-[10px] !font-bold !uppercase !tracking-widest !text-primary' },
                        itemicon: { class: '!text-primary !text-sm' }
                    }" />
                </div>
            </div>

            <!-- Main Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-4 items-start flex-1 min-h-0">

                <!-- LEFT: Customer List Sidebar -->
                <aside class="col-span-12 lg:col-span-3 lg:sticky lg:top-[100px] lg:h-[calc(100vh-120px)] flex flex-col min-h-0 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-4 border-b border-panel-border bg-panel/60">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-2 h-2 rounded-full bg-sky-500 shadow-[0_0_10px_rgba(14,165,233,0.5)]"></div>
                            <span class="text-[10px] font-bold text-secondary tracking-[0.2em] uppercase font-mono leading-none">Customer Registry</span>
                        </div>
                        <div class="relative">
                            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-secondary text-sm"></i>
                            <InputText 
                                v-model="search" 
                                placeholder="Search customers..." 
                                @input="loadCustomers" 
                                class="!w-full !pl-11 !pr-4 !bg-deep !border-panel-border !text-primary !h-12 !text-xs !rounded-xl focus:!border-sky-500/30 transition-all font-mono"
                            />
                        </div>
                    </div>
                    
                    <div class="flex-1 min-h-0 flex flex-col">
                        <Listbox 
                            v-model="selectedCustomer" 
                            :options="customers" 
                            optionLabel="name" 
                            class="!border-none !bg-transparent flex-1"
                            :pt="listboxPt"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col gap-2 w-full">
                                    <div class="flex justify-between items-center w-full">
                                        <span class="text-[9px] font-bold font-mono tracking-widest uppercase" :class="selectedCustomer?.id === option.id ? 'text-sky-400' : 'text-muted'">{{ option.customer_code }}</span>
                                          <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border leading-none" 
                                                :class="[
                                                    Number(option.exposure) > Number(option.credit_limit) * 0.9 ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 
                                                    'bg-sky-500/10 text-sky-400 border-sky-500/20'
                                                ]">
                                              {{ option.is_active ? 'ACTIVE' : 'INACTIVE' }}
                                          </span>
                                    </div>
                                    <span class="text-xs font-bold truncate tracking-tight">{{ option.name }}</span>
                                </div>
                            </template>
                        </Listbox>
                    </div>
                </aside>

                <!-- RIGHT Sector: Detail + Activity -->
                <main v-if="selectedCustomer" class="col-span-12 lg:col-span-9 flex flex-col gap-4 min-h-0">
                        
                        <!-- Top Section: Customer Details -->
                        <section class="bg-panel/40 border border-panel-border/80 rounded-2xl p-5 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                            <!-- Background Accent -->
                            <div class="absolute top-0 right-0 w-64 h-64 bg-sky-500/5 blur-[100px] -mr-32 -mt-32 rounded-full transition-opacity group-hover:opacity-100 opacity-50"></div>
                            
                            <div class="relative z-10 flex flex-col">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-6 pb-6 border-b border-panel-border/60">
                                    <div class="flex flex-col flex-1">
                                        <div class="flex items-center gap-4 mb-3">
                                            <h1 class="text-3xl font-bold text-primary tracking-tighter m-0">{{ selectedCustomer.name }}</h1>
                                            <div class="flex flex-wrap gap-2">
                                                <span class="text-[9px] font-bold px-3 py-1 bg-panel-hover/80 border border-panel-border rounded-full text-secondary uppercase tracking-widest font-mono">CUSTOMER</span>
                                                <span class="text-[9px] font-bold px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 rounded-full text-indigo-400 uppercase tracking-widest font-mono">TIN: {{ selectedCustomer.tax_number || 'N/A' }}</span>
                                                <span class="text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest font-mono border"
                                                      :class="selectedCustomer.is_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'"
                                                >STATUS: {{ selectedCustomer.is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-6 text-secondary text-xs font-mono">
                                            <span v-if="selectedCustomer.email" class="flex items-center gap-2"><i class="pi pi-envelope text-sky-500/50"></i>{{ selectedCustomer.email }}</span>
                                            <span v-if="selectedCustomer.phone" class="flex items-center gap-2"><i class="pi pi-phone text-sky-500/50"></i>{{ selectedCustomer.phone }}</span>
                                            <span class="flex items-center gap-2 font-black text-sky-400 tracking-widest">{{ selectedCustomer.customer_code }}</span>
                                        </div>
                                    </div>

                                    <div v-if="can('manage-customers')" class="flex gap-3">
                                        <button @click="editCustomer" class="w-12 h-12 rounded-xl bg-panel border border-panel-border text-secondary hover:text-primary hover:bg-panel-hover transition-all flex items-center justify-center">
                                            <i class="pi pi-pencil"></i>
                                        </button>
                                        <button @click="deleteCustomer" class="w-12 h-12 rounded-xl bg-panel border border-panel-border text-rose-500 hover:text-rose-400 hover:bg-rose-500/5 transition-all flex items-center justify-center">
                                            <i class="pi pi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="p-4 bg-deep/80 border border-panel-border rounded-2xl flex flex-col shadow-lg ring-1 ring-white/5">
                                        <span class="text-[9px] font-bold text-secondary uppercase tracking-[0.2em] mb-2 font-mono">Credit Limit</span>
                                        <span class="text-xl font-bold text-primary font-mono">{{ formatCurrency(selectedCustomer.credit_limit || 0) }}</span>
                                    </div>
                                    <div class="p-4 bg-deep/80 border border-panel-border rounded-2xl flex flex-col shadow-lg ring-1 ring-white/5"
                                         :class="creditUtilization >= 90 ? 'ring-rose-500/20' : 'ring-white/5'">
                                        <span class="text-[9px] font-bold text-secondary uppercase tracking-[0.2em] mb-2 font-mono">Outstanding Balance</span>
                                        <span class="text-xl font-bold font-mono" :class="Number(selectedCustomer.exposure) > 0 ? 'text-amber-400' : 'text-secondary'">{{ formatCurrency(selectedCustomer.exposure || 0) }}</span>
                                    </div>
                                    <div class="p-4 bg-deep/80 border border-panel-border rounded-2xl flex flex-col justify-between shadow-lg ring-1 ring-white/5 lg:col-span-2">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[9px] font-bold text-secondary uppercase tracking-[0.2em] font-mono">Credit Exposure</span>
                                            <span class="text-[10px] font-bold font-mono" :class="creditUtilization >= 90 ? 'text-rose-400' : 'text-sky-400'">{{ creditUtilization }}%</span>
                                        </div>
                                        <div class="h-2 bg-panel rounded-full overflow-hidden mb-1">
                                            <div class="h-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(14,165,233,0.3)]"
                                                 :class="creditUtilization >= 90 ? 'bg-rose-500' : creditUtilization >= 70 ? 'bg-amber-400' : 'bg-sky-500'"
                                                 :style="{ width: creditUtilization + '%' }"
                                            ></div>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-[8px] text-muted font-bold uppercase tracking-widest">Available Credit</span>
                                            <span class="text-[11px] font-bold text-emerald-400 font-mono">{{ formatCurrency(availableCredit) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Activity Tabs & Ledgers -->
                        <section class="bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl flex flex-col backdrop-blur-sm min-h-[500px]">
                            <!-- Tab Bar -->
                            <div class="px-8 border-b border-panel-border/60 bg-panel/60 flex items-center gap-8 h-16 divide-x divide-panel-border/40">
                                <button @click="activeTab = 'orders'"
                                    class="h-full px-2 text-[10px] font-bold uppercase tracking-[0.2em] transition-all relative border-none bg-transparent cursor-pointer"
                                    :class="activeTab === 'orders' ? 'text-sky-400' : 'text-secondary hover:text-zinc-300'">
                                    Recent Orders
                                    <div v-if="activeTab === 'orders'" class="absolute bottom-0 left-0 w-full h-0.5 bg-sky-500 shadow-[0_0_10px_rgba(14,165,233,0.5)]"></div>
                                </button>
                                <button @click="activeTab = 'invoices'"
                                    class="h-full px-8 text-[10px] font-bold uppercase tracking-[0.2em] transition-all relative border-none bg-transparent cursor-pointer"
                                    :class="activeTab === 'invoices' ? 'text-emerald-400' : 'text-secondary hover:text-zinc-300'">
                                    Invoices
                                    <div v-if="activeTab === 'invoices'" class="absolute bottom-0 left-0 w-full h-0.5 bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                                </button>
                                <button @click="activeTab = 'payments'"
                                    class="h-full px-8 text-[10px] font-bold uppercase tracking-[0.2em] transition-all relative border-none bg-transparent cursor-pointer"
                                    :class="activeTab === 'payments' ? 'text-indigo-400' : 'text-secondary hover:text-zinc-300'">
                                    Payments
                                    <div v-if="activeTab === 'payments'" class="absolute bottom-0 left-0 w-full h-0.5 bg-indigo-500 shadow-[0_0_10px_rgba(99,102,241,0.5)]"></div>
                                </button>
                                <button @click="activeTab = 'statement'"
                                    class="h-full px-8 text-[10px] font-bold uppercase tracking-[0.2em] transition-all relative border-none bg-transparent cursor-pointer"
                                    :class="activeTab === 'statement' ? 'text-amber-400' : 'text-secondary hover:text-zinc-300'">
                                    Full Statement
                                    <div v-if="activeTab === 'statement'" class="absolute bottom-0 left-0 w-full h-0.5 bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]"></div>
                                </button>
                                
                                <div class="ml-auto pl-4">
                                    <span class="bg-deep text-muted px-3 py-1 rounded text-[10px] font-bold border border-panel-border/50 font-mono tracking-tighter uppercase whitespace-nowrap">
                                        {{ activeTab === 'orders' ? history.length : activeTab === 'invoices' ? invoices.length : activeTab === 'payments' ? payments.length : statement.length }} Records Found
                                    </span>
                                </div>
                            </div>

                        <!-- Orders Tab -->
                        <div v-if="activeTab === 'orders'" class="flex-1 min-h-0">
                            <DataTable
                                :value="history"
                                :loading="loadingHistory"
                                scrollable
                                scrollHeight="flex"
                                class="gh-table border-none"
                                :pt="tablePt"
                                @row-click="(e) => router.visit('/sales-orders/' + e.data.id)"
                                :rowClass="() => 'cursor-pointer hover:bg-panel-hover/30 transition-colors'"
                            >
                                <template #empty>
                                    <div class="py-24 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-shopping-cart text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No sales orders found</p>
                                    </div>
                                </template>

                                <Column field="order_date" header="Date" style="width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-secondary">{{ data.order_date }}</span>
                                    </template>
                                </Column>

                                <Column field="so_number" header="SO Number" style="width: 200px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-xs bg-cyan-500/5 text-cyan-400 px-2 py-0.5 border border-cyan-500/10 rounded tracking-tighter">
                                            {{ data.so_number }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="total_amount" header="Order Value" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="text-primary font-bold font-mono text-sm">{{ formatCurrency(data.total_amount) }}</span>
                                    </template>
                                </Column>

                                <Column field="status" header="Status">
                                    <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="getStatusClasses(data.status?.name).dot"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono uppercase" :class="getStatusClasses(data.status?.name).text">
                                                {{ data.status?.name || 'DRAFT' }}
                                            </span>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Notes">
                                    <template #body="{ data }">
                                        <span class="text-muted text-xs italic truncate block max-w-[200px]">{{ data.notes || '—' }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Invoices Tab -->
                        <div v-if="activeTab === 'invoices'" class="flex-1 min-h-0">
                            <DataTable
                                :value="invoices"
                                :loading="loadingHistory"
                                scrollable
                                scrollHeight="flex"
                                class="gh-table border-none"
                                :pt="tablePt"
                                @row-click="(e) => router.visit('/finance/invoices/' + e.data.id)"
                                :rowClass="() => 'cursor-pointer hover:bg-panel-hover/30 transition-colors'"
                            >
                                <template #empty>
                                    <div class="py-24 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-file-invoice text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No invoices for this customer</p>
                                    </div>
                                </template>

                                <Column field="invoice_date" header="Date" style="width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-secondary">{{ data.invoice_date }}</span>
                                    </template>
                                </Column>

                                <Column field="invoice_number" header="Invoice #" style="width: 200px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-xs bg-emerald-500/5 text-emerald-400 px-2 py-0.5 border border-emerald-500/10 rounded tracking-tighter">
                                            {{ data.invoice_number }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="total_amount" header="Total Charged" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="text-primary font-bold font-mono text-sm">{{ formatCurrency(data.total_amount) }}</span>
                                    </template>
                                </Column>

                                <Column field="balance_due" header="Balance Due" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-bold font-mono text-sm" :class="Number(data.balance_due) > 0 ? 'text-rose-400' : 'text-muted'">
                                            {{ formatCurrency(data.balance_due) }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="status" header="Status">
                                    <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="getStatusClasses(data.status).dot"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono uppercase" :class="getStatusClasses(data.status).text">
                                                {{ data.status }}
                                            </span>
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <!-- Payments Tab -->
                        <div v-if="activeTab === 'payments'" class="flex-1 min-h-0">
                            <DataTable
                                :value="payments"
                                :loading="loadingHistory"
                                scrollable
                                scrollHeight="flex"
                                class="gh-table border-none"
                                :pt="tablePt"
                                @row-click="(e) => router.visit('/finance/payments/' + e.data.id)"
                                :rowClass="() => 'cursor-pointer hover:bg-panel-hover/30 transition-colors'"
                            >
                                <template #empty>
                                    <div class="py-24 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-credit-card text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No payment history found</p>
                                    </div>
                                </template>
                                <Column field="payment_date" header="Date" style="width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-secondary">{{ data.payment_date }}</span>
                                    </template>
                                </Column>
                                <Column field="payment_number" header="Payment #" style="width: 200px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-xs bg-indigo-500/5 text-indigo-400 px-2 py-0.5 border border-indigo-500/10 rounded tracking-tighter">
                                            {{ data.payment_number }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="amount" header="Total Amount" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="text-primary font-bold font-mono text-sm">{{ formatCurrency(data.amount) }}</span>
                                    </template>
                                </Column>
                                <Column field="unallocated_amount" header="Unallocated" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-bold font-mono text-sm" :class="Number(data.unallocated_amount) > 0 ? 'text-amber-400' : 'text-muted'">
                                            {{ formatCurrency(data.unallocated_amount) }}
                                        </span>
                                    </template>
                                </Column>
                                <Column header="Ref / Method">
                                    <template #body="{ data }">
                                        <span class="text-secondary text-xs font-mono">{{ data.method }} / {{ data.reference || '—' }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Statement Tab -->
                        <div v-if="activeTab === 'statement'" class="flex-1 min-h-0">
                            <DataTable
                                :value="statement"
                                :loading="loadingStatement"
                                scrollable
                                scrollHeight="flex"
                                class="gh-table border-none"
                                :pt="tablePt"
                            >
                                <template #empty>
                                    <div class="py-24 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-book text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No statement activity</p>
                                    </div>
                                </template>
                                <Column field="date" header="Date" style="width: 110px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-secondary">{{ data.date }}</span>
                                    </template>
                                </Column>
                                <Column header="Type" style="width: 140px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[10px] px-2 py-0.5 rounded border tracking-widest"
                                              :class="{
                                                  'bg-indigo-500/10 text-indigo-400 border-indigo-500/20': data.type === 'INVOICE',
                                                  'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': data.type === 'PAYMENT',
                                                  'bg-rose-500/10 text-rose-400 border-rose-500/20': data.type === 'CREDIT_NOTE',
                                                  'bg-rose-500/10 text-rose-400 border-rose-500/20': data.type === 'REFUND'
                                              }">
                                            {{ data.type }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="reference" header="Reference" style="width: 150px">
                                    <template #body="{ data }">
                                        <span v-if="data.link" 
                                              @click="router.visit(data.link)"
                                              class="font-mono text-xs text-cyan-400 hover:text-cyan-300 cursor-pointer underline underline-offset-4 decoration-cyan-500/30">
                                            {{ data.reference }}
                                        </span>
                                        <span v-else class="font-mono text-xs text-secondary">{{ data.reference }}</span>
                                    </template>
                                </Column>
                                <Column field="description" header="Description">
                                    <template #body="{ data }">
                                        <span class="text-secondary text-xs">{{ data.description }}</span>
                                    </template>
                                </Column>
                                <Column header="Debit" style="width: 130px" class="text-right">
                                    <template #body="{ data }">
                                        <span v-if="Number(data.debit) > 0" class="font-mono text-xs text-secondary font-bold">{{ formatCurrency(data.debit) }}</span>
                                        <span v-else class="text-muted font-mono">-</span>
                                    </template>
                                </Column>
                                <Column header="Credit" style="width: 130px" class="text-right">
                                    <template #body="{ data }">
                                        <span v-if="Number(data.credit) > 0" class="font-mono text-xs text-emerald-400 font-bold">{{ formatCurrency(data.credit) }}</span>
                                        <span v-else class="text-muted font-mono">-</span>
                                    </template>
                                </Column>
                                <Column field="running_balance" header="Running Balance" style="width: 150px; text-align: right">
                                    <template #body="{ data }">
                                        <span class="font-bold font-mono text-sm" :class="Number(data.running_balance) > 0 ? 'text-primary' : 'text-secondary'">
                                            {{ formatCurrency(data.running_balance) }}
                                        </span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </section>
                    </main>

                    <!-- Empty Selection State -->
                    <main v-else class="col-span-12 lg:col-span-9 flex flex-col items-center justify-center bg-panel/40 border border-panel-border/80 rounded-2xl p-32 backdrop-blur-sm shadow-2xl">
                        <div class="relative mb-12 group">
                            <!-- Background Glow -->
                            <div class="absolute inset-0 bg-sky-500/20 blur-[60px] rounded-full group-hover:bg-sky-500/30 transition-all duration-700"></div>
                            <div class="w-32 h-32 rounded-[2rem] bg-deep border border-panel-border flex items-center justify-center relative z-10 shadow-2xl transition-transform duration-500 group-hover:scale-110">
                                <i class="pi pi-users text-5xl text-sky-500/40"></i>
                            </div>
                        </div>
                        <div class="text-center relative z-10">
                            <h2 class="text-2xl font-bold text-primary tracking-tight mb-3">No Customer Selected</h2>
                            <p class="text-secondary max-w-sm mx-auto leading-relaxed text-sm">Select a client from the registry on the left to view their detailed transaction history and financial exposure.</p>
                            <div class="mt-8 flex items-center justify-center gap-4 text-[9px] font-bold text-muted font-mono tracking-widest uppercase">
                                <span>Registry Size: {{ customers.length }}</span>
                                <span class="w-1 h-1 rounded-full bg-panel-hover"></span>
                                <span>Real-time Sync</span>
                            </div>
                        </div>
                    </main>
                </div>

            <!-- Customer Dialog -->
            <Dialog
                v-model:visible="dialogVisible"
                :modal="true"
                class="!bg-transparent !border-none !shadow-none ring-0 outline-none"
                :pt="{
                    root: { class: 'p-0 sm:m-4 max-w-2xl w-full' },
                    content: { class: 'p-0 !bg-transparent' }
                }"
                :showHeader="false"
            >
                <div class="bg-deep border border-panel-border rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden ring-1 ring-white/5">
                    <!-- Dialog Header -->
                    <div class="px-8 py-6 border-b border-panel-border bg-panel/50 flex justify-between items-center relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-sky-500 shadow-[0_0_15px_rgba(14,165,233,0.5)]"></div>
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                                <i class="pi pi-user-plus text-sky-400"></i>
                            </div>
                            <div>
                                <div class="text-[9px] font-bold text-sky-500 font-mono tracking-[0.3em] mb-1">CRM_REGISTRY_V2</div>
                                <h2 class="text-primary text-xl font-bold tracking-tight m-0">{{ customerForm.id ? 'Modify Record' : 'Create New Client' }}</h2>
                            </div>
                        </div>
                        <button @click="dialogVisible = false" class="w-10 h-10 rounded-xl text-secondary hover:text-primary hover:bg-panel-hover transition-all flex items-center justify-center">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>

                    <!-- Dialog Body -->
                    <div class="p-8 space-y-5">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Full Company Name *</label>
                                <InputText v-model="customerForm.name" placeholder="E.g. Global Tech Solutions"
                                           class="!bg-panel/50 !border-panel-border !text-primary !h-12 !font-bold focus:!border-sky-500/40"
                                           :class="{'!border-rose-500/50': submitted && !customerForm.name}" />
                            </div>
                            <div class="col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Registry ID *</label>
                                <InputText v-model="customerForm.customer_code" placeholder="CST-000"
                                           class="!bg-panel/50 !border-panel-border !text-sky-400 !h-12 !font-mono focus:!border-sky-500/30"
                                           :class="{'!border-rose-500/50': submitted && !customerForm.customer_code}" />
                            </div>

                            <div class="col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Email</label>
                                <InputText v-model="customerForm.email" placeholder="client@company.com" class="!bg-panel/50 !border-panel-border !text-secondary !h-12 focus:!border-sky-500/30 font-mono text-xs" />
                            </div>
                            <div class="col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Phone</label>
                                <InputText v-model="customerForm.phone" placeholder="+63 000 000 0000" class="!bg-panel/50 !border-panel-border !text-secondary !h-12 focus:!border-sky-500/30 font-mono text-xs" />
                            </div>

                            <div class="col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Tax / VAT Number</label>
                                <InputText v-model="customerForm.tax_number" placeholder="VAT-000000" class="!bg-panel/50 !border-panel-border !text-secondary !h-12 focus:!border-sky-500/30 font-mono text-xs" />
                            </div>
                            <div class="col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Credit Limit</label>
                                <InputNumber v-model="customerForm.credit_limit" mode="currency" currency="PHP" locale="en-PH"
                                             :pt="{ input: { class: '!bg-panel/50 !border-panel-border !text-primary !h-12 !font-mono !text-sm' } }" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Billing Address</label>
                                <Textarea v-model="customerForm.billing_address" rows="2" placeholder="Full billing address..." class="!bg-panel/50 !border-panel-border !text-secondary focus:!border-sky-500/30 text-xs" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Shipping Address</label>
                                <Textarea v-model="customerForm.shipping_address" rows="2" placeholder="Primary delivery address..." class="!bg-panel/50 !border-panel-border !text-secondary focus:!border-sky-500/30 text-xs" />
                            </div>

                            <div class="col-span-12 p-4 bg-panel/40 border border-panel-border/60 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="text-primary font-bold text-sm tracking-tight m-0">Account Visibility</span>
                                    <p class="text-secondary text-[10px] font-mono m-0 mt-0.5 uppercase tracking-tighter">{{ customerForm.is_active ? 'ENABLED — Full System Access' : 'DISABLED — Read-Only Archive' }}</p>
                                </div>
                                <ToggleSwitch v-model="customerForm.is_active"
                                              :pt="{ slider: ({ props }) => ({ class: props.modelValue ? '!bg-sky-500' : '!bg-panel-border' }) }" />
                            </div>
                        </div>
                    </div>

                    <!-- Dialog Footer -->
                    <div class="px-8 py-5 border-t border-panel-border bg-panel/50 flex justify-end gap-3">
                        <button @click="dialogVisible = false" class="px-5 py-2.5 rounded-xl bg-transparent border border-panel-border text-secondary hover:text-primary hover:border-panel-border font-bold text-[10px] uppercase tracking-widest transition-colors">
                            Cancel
                        </button>
                        <button @click="saveCustomer" class="px-8 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-primary font-bold text-[10px] uppercase tracking-widest transition-all shadow-lg shadow-sky-500/10 active:scale-95">
                            {{ customerForm.id ? 'Push Updates' : 'Commit Registry' }}
                        </button>
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.gh-table :deep(.p-datatable-wrapper) {
    min-height: 400px;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(161, 161, 170, 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(14, 165, 233, 0.3);
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>


