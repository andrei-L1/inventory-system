<script setup>
import { ref, onMounted, watch } from 'vue';
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
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import axios from 'axios';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const customers = ref([]);
const selectedCustomer = ref(null);
const history = ref([]);
const loadingCustomers = ref(false);
const loadingHistory = ref(false);
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

const loadCustomers = async () => {
    loadingCustomers.value = true;
    try {
        const res = await axios.get('/api/customers', { params: { query: search.value } });
        customers.value = res.data.data;
        if (customers.value.length > 0 && !selectedCustomer.value) {
            const urlParams = new URLSearchParams(window.location.search);
            const customerId = urlParams.get('customer_id');
            if (customerId) {
                selectedCustomer.value = customers.value.find(c => c.id == customerId) || customers.value[0];
            } else {
                selectedCustomer.value = customers.value[0];
            }
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
        history.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

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
    loadHistory();
});

const handleLinkClick = (type, name, id) => {
    if (type === 'SO' && id) {
        // Future route for Sales Orders
        // router.visit(`/sales-orders/${id}`);
        toast.add({ severity: 'info', summary: 'Navigating to SO', detail: `SO #${name} details coming in Step 4.`, life: 3000 });
        return;
    }

    toast.add({ 
        severity: id ? 'info' : 'warn', 
        summary: id ? `Navigating to ${type}` : 'Relation Missing', 
        detail: id ? `Feature coming soon for ${type}` : `Data missing for ${type}`, 
        life: 4000 
    });
};

const getStatusSeverity = (status) => {
    switch (status.toLowerCase()) {
        case 'fulfilled':
        case 'posted': return 'success';
        case 'confirmed': return 'info';
        case 'pending': return 'warning';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
};

const openNew = () => {
    customerForm.value = { 
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
    };
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

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
};
</script>

<template>
    <AppLayout>
        <Head title="Customer Center" />
        <Toast />

        <div class="min-h-full flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-cyan-400 uppercase tracking-[0.2em] block mb-2 font-mono">Client Relations & Accounts</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Customer Center</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Manage your clients, view order history, and monitor credit limits in one high-performance interface.</p>
                </div>
                <div v-if="can('manage-customers')">
                    <Button label="REGISTER CUSTOMER" icon="pi pi-user-plus" 
                            class="!bg-cyan-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-cyan-500/10 hover:!bg-cyan-400 active:scale-95 transition-all" 
                            @click="openNew" />
                </div>
            </div>

            <!-- Primary Workspace Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-8 flex-1 min-h-0">
                
                <!-- Left Sector: Customer List Sidebar -->
                <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-transparent border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-6 border-b border-zinc-800/50 bg-transparent">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-1.5 h-1.5 rounded-full bg-cyan-500"></div>
                            <span class="text-[10px] font-bold text-zinc-500 tracking-[0.2em] uppercase font-mono leading-none">Customer_Registry</span>
                        </div>
                        <div class="relative">
                            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 text-sm"></i>
                            <InputText 
                                v-model="search" 
                                placeholder="Search clients..." 
                                @input="loadCustomers" 
                                class="!w-full !pl-11 !pr-4 !bg-zinc-950 !border-zinc-800 !text-white !h-12 !text-xs !rounded-xl focus:!border-cyan-500/30 transition-all font-mono"
                            />
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar">
                        <Listbox 
                            v-model="selectedCustomer" 
                            :options="customers" 
                            optionLabel="name" 
                            class="!border-none !bg-transparent"
                            :pt="{
                                root: { class: '!p-2' },
                                item: ({ context }) => ({
                                    class: [
                                        '!p-4 !mb-1 !rounded-xl !transition-all !duration-300 !border',
                                        context.selected 
                                            ? '!bg-cyan-500/10 !border-cyan-500/20 !text-white shadow-[0_0_15px_rgba(6,182,212,0.05)]' 
                                            : '!bg-transparent !border-transparent !text-zinc-500 hover:!bg-zinc-800/40 hover:!text-zinc-200'
                                    ]
                                })
                            }"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col gap-2 w-full">
                                    <span class="text-[9px] font-bold font-mono tracking-tighter" :class="selectedCustomer?.id === option.id ? 'text-cyan-400' : 'text-zinc-600'">{{ option.customer_code }}</span>
                                    <span class="text-xs font-bold truncate tracking-tight">{{ option.name }}</span>
                                </div>
                            </template>
                        </Listbox>
                    </div>
                </aside>

                <!-- Right Sector: Activity & Details Area -->
                <main class="col-span-12 lg:col-span-9 flex flex-col gap-8 min-h-0">
                    
                    <!-- Top Section: Customer Information -->
                    <section class="bg-transparent border border-zinc-800/80 rounded-2xl p-8 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                        <!-- Background Accent -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-500/5 blur-[100px] -mr-32 -mt-32 rounded-full transition-opacity group-hover:opacity-100 opacity-50"></div>
                        
                        <template v-if="selectedCustomer">
                            <div class="relative z-10 flex flex-col">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 pb-10 border-b border-zinc-800/60">
                                    <div class="flex flex-col flex-1">
                                        <div class="flex items-center gap-4 mb-3">
                                            <h1 class="text-3xl font-bold text-white tracking-tighter m-0">{{ selectedCustomer.name }}</h1>
                                            <div class="flex gap-2">
                                                <span class="text-[9px] font-bold px-3 py-1 bg-cyan-500/5 border border-cyan-500/20 rounded-full text-cyan-400 uppercase tracking-widest font-mono">ACTIVE CLIENT</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 text-zinc-500 text-xs font-mono">
                                            <i class="pi pi-envelope text-[10px] text-cyan-400"></i>
                                            <span>{{ selectedCustomer.email || 'No email provided' }}</span>
                                            <span class="mx-2 text-zinc-800">|</span>
                                            <i class="pi pi-phone text-[10px] text-cyan-400"></i>
                                            <span>{{ selectedCustomer.phone || 'No phone' }}</span>
                                        </div>
                                    </div>

                                    <div v-if="can('manage-customers')" class="flex gap-3">
                                        <Button icon="pi pi-pencil" 
                                                class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white hover:!bg-zinc-800 !w-12 !h-12 !rounded-xl transition-all" 
                                                @click="editCustomer" />
                                        <Button icon="pi pi-trash" 
                                                class="!bg-zinc-900 !border-zinc-800 !text-red-400 hover:!text-red-300 hover:!bg-red-500/10 !w-12 !h-12 !rounded-xl transition-all" 
                                                @click="deleteCustomer" />
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-12">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Client ID code</label>
                                        <code class="text-cyan-400 font-mono text-sm tracking-tighter bg-transparent px-0 py-0.5 rounded w-fit">{{ selectedCustomer.customer_code }}</code>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Credit Limit</label>
                                        <span class="text-white font-bold text-sm tracking-tight flex items-center gap-2">
                                            <i class="pi pi-wallet text-cyan-500/50 text-[10px]"></i>
                                            {{ formatCurrency(selectedCustomer.credit_limit || 0) }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 lg:col-span-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Tax / VAT ID</label>
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-transparent flex items-center justify-center border border-zinc-800">
                                                <i class="pi pi-id-card text-zinc-500 text-xs"></i>
                                            </div>
                                            <span class="text-zinc-300 font-bold text-xs uppercase tracking-widest font-mono">{{ selectedCustomer.tax_number || 'NOT SPECIFIED' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-8 bg-zinc-900/10 p-6 border border-zinc-800/40 rounded-xl">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Billing Address</label>
                                        <p class="text-zinc-400 text-xs leading-relaxed italic">{{ selectedCustomer.billing_address || 'Same as shipping or not specified' }}</p>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Shipping Address</label>
                                        <p class="text-zinc-400 text-xs leading-relaxed italic">{{ selectedCustomer.shipping_address || 'Not specified' }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="h-64 flex flex-col items-center justify-center opacity-20 grayscale">
                            <i class="pi pi-id-card text-6xl mb-6"></i>
                            <p class="font-mono text-xs tracking-[0.3em] uppercase">Select a client to view details...</p>
                        </div>
                    </section>

                    <!-- Bottom Section: Transactions -->
                    <section class="flex-1 min-h-0 bg-transparent border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl flex flex-col backdrop-blur-sm">
                        <!-- Navigation Tabs -->
                        <div class="px-8 border-b border-zinc-800/60 bg-transparent flex items-center gap-8 h-16">
                            <button @click="activeTab = 'orders'" 
                                    class="h-full px-2 text-[11px] font-bold uppercase tracking-[0.2em] transition-all relative group bg-transparent border-none cursor-pointer"
                                    :class="activeTab === 'orders' ? 'text-cyan-400' : 'text-zinc-500 hover:text-zinc-300'">
                                <i class="pi pi-shopping-cart mr-2"></i> Recent Sales Orders
                                <div v-if="activeTab === 'orders'" class="absolute bottom-0 left-0 w-full h-0.5 bg-cyan-500 shadow-[0_0_10px_rgba(6,182,212,0.5)]"></div>
                            </button>
                            
                            <div class="ml-auto">
                                <span class="bg-transparent text-zinc-600 px-3 py-1 rounded text-[10px] font-bold border border-zinc-800/50 font-mono tracking-tighter uppercase">
                                    {{ history.length }} Orders Found
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-hidden">
                            <DataTable 
                                :value="history" 
                                :loading="loadingHistory" 
                                scrollable 
                                scrollHeight="flex" 
                                class="gh-table border-none"
                                :pt="{
                                    root: { class: '!bg-transparent' },
                                    column: {
                                    headercell: { class: '!bg-transparent !border-zinc-800 !text-zinc-600 !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' },
                                        bodycell: { class: '!border-zinc-800/40 !py-4 !px-8 !text-[13px] !text-zinc-300' }
                                    },
                                    bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200' }
                                }"
                            >
                                <template #empty>
                                    <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-shopping-cart text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No order history found for this client</p>
                                    </div>
                                </template>
                                
                                <Column field="order_date" header="Date" style="width: 140px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-zinc-400">{{ data.order_date }}</span>
                                    </template>
                                </Column>
                                
                                <Column field="so_number" header="Order ID" style="width: 180px">
                                    <template #body="{ data }">
                                        <span @click="handleLinkClick('SO', data.so_number, data.id)" 
                                              class="font-mono text-[11px] bg-cyan-500/5 text-cyan-400 px-2 py-0.5 border border-cyan-500/10 rounded tracking-tighter cursor-pointer hover:bg-cyan-500/20">
                                            {{ data.so_number }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="total_amount" header="Bill Value" style="width: 180px">
                                    <template #body="{ data }">
                                        <span class="text-white font-bold">{{ data.currency }} {{ data.total_amount }}</span>
                                    </template>
                                </Column>
                                
                                <Column field="status" header="Dispatch Status">
                                    <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="[
                                                data.status?.name.toLowerCase() === 'fulfilled' ? 'bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]' : 
                                                data.status?.name.toLowerCase() === 'confirmed' ? 'bg-cyan-500 shadow-[0_0_5px_rgba(6,182,212,0.5)]' :
                                                'bg-zinc-700'
                                            ]"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono uppercase" :class="[
                                                data.status?.name.toLowerCase() === 'fulfilled' ? 'text-zinc-200' : 
                                                data.status?.name.toLowerCase() === 'confirmed' ? 'text-zinc-200' :
                                                'text-zinc-600'
                                            ]">{{ data.status?.name || 'DRAFT' }}</span>
                                        </div>
                                    </template>
                                </Column>
                                
                                <Column header="Logistics Notes">
                                    <template #body="{ data }">
                                        <span class="text-zinc-600 text-[11px] italic truncate block max-w-[200px]">{{ data.notes || '---' }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </section>
                </main>
            </div>

            <!-- Management Dialog -->
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
                <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    <!-- Header -->
                    <div class="px-8 py-6 border-b border-zinc-900 bg-zinc-900/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <div class="text-[9px] font-bold text-cyan-500 font-mono tracking-[0.2em] mb-1">CLIENT_REGISTRY</div>
                            <h2 class="text-white text-xl font-bold tracking-tight m-0">{{ customerForm.id ? 'Modify Client Instance' : 'New Client Protocol' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-zinc-600 hover:!text-white !bg-transparent !border-none !w-10 !h-10 hover:!bg-zinc-900 transition-colors" @click="dialogVisible = false" />
                    </div>

                    <!-- Body -->
                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(6,182,212,0.03),transparent_40%)]">
                        <div class="grid grid-cols-12 gap-x-6 gap-y-6">
                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Full Company Name *</label>
                                <InputText v-model="customerForm.name" placeholder="E.g. Global Tech Solutions" 
                                           class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !font-bold focus:!border-cyan-500/40"
                                           :class="{'!border-red-500/50': submitted && !customerForm.name}" />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Client Code *</label>
                                <InputText v-model="customerForm.customer_code" placeholder="CST-000" class="!bg-zinc-900/50 !border-zinc-800 !text-cyan-400 !h-12 !font-mono focus:!border-cyan-500/30"
                                           :class="{'!border-red-500/50': submitted && !customerForm.customer_code}" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Liaison Email</label>
                                <InputText v-model="customerForm.email" placeholder="client@nexus.com" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-cyan-500/30" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Phone Reference</label>
                                <InputText v-model="customerForm.phone" placeholder="+1 (000) 000-0000" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-cyan-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Tax / Reg Number</label>
                                <InputText v-model="customerForm.tax_number" placeholder="VAT-000000" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-cyan-500/30" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Approved Credit Limit</label>
                                <InputNumber v-model="customerForm.credit_limit" mode="currency" currency="PHP" locale="en-PH"
                                             class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-cyan-500/30"
                                             :pt="{ input: { class: '!bg-zinc-900/50 !border-zinc-800 !text-white !h-12' } } " />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Billing Information</label>
                                <Textarea v-model="customerForm.billing_address" rows="2" placeholder="Full billing address..." class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 focus:!border-cyan-500/30" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Dispatch / Shipping Target</label>
                                <Textarea v-model="customerForm.shipping_address" rows="2" placeholder="Primary delivery destination..." class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 focus:!border-cyan-500/30" />
                            </div>

                            <div class="col-span-12 p-5 bg-zinc-900/30 border border-zinc-800/60 rounded-xl flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-[11px] uppercase tracking-tight">Account System Status</span>
                                    <span class="text-zinc-500 text-[9px] font-mono uppercase mt-0.5">Network // {{ customerForm.is_active ? 'active' : 'restricted' }}</span>
                                </div>
                                <ToggleSwitch v-model="customerForm.is_active" 
                                             :pt="{
                                                 slider: ({ props }) => ({
                                                     class: props.modelValue ? '!bg-cyan-500' : '!bg-zinc-700'
                                                 })
                                             }" />
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-6 border-t border-zinc-900 bg-zinc-900/50 flex justify-end gap-3">
                        <Button label="ABORT" class="!bg-transparent !border-zinc-800 !text-zinc-500 hover:!text-white hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="dialogVisible = false" />
                        <Button label="COMMIT REGISTRY" 
                                class="!bg-cyan-500 !border-none !text-white !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-cyan-500/10 hover:!bg-cyan-400 active:scale-95 transition-all" 
                                @click="saveCustomer" />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #18181b;
    border-radius: 10px;
}
</style>
