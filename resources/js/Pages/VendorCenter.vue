<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import Tag from 'primevue/tag';
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

const vendors = ref([]);
const selectedVendor = ref(null);
const history = ref([]);
const loadingVendors = ref(false);
const loadingHistory = ref(false);
const search = ref('');
const activeTab = ref('transactions');
const suppliedProducts = ref([]);
const loadingProducts = ref(false);

const dialogVisible = ref(false);
const submitted = ref(false);

const vendorForm = ref({
    id: null,
    vendor_code: '',
    name: '',
    email: '',
    phone: '',
    address: '',
    contact_person: '',
    is_active: true
});

const loadVendors = async () => {
    loadingVendors.value = true;
    try {
        const res = await axios.get('/api/vendors', { params: { query: search.value } });
        vendors.value = res.data.data;
        if (vendors.value.length > 0 && !selectedVendor.value) {
            const urlParams = new URLSearchParams(window.location.search);
            const vendorId = urlParams.get('vendor_id');
            if (vendorId) {
                selectedVendor.value = vendors.value.find(v => v.id == vendorId) || vendors.value[0];
            } else {
                selectedVendor.value = vendors.value[0];
            }
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingVendors.value = false;
    }
};

const loadHistory = async () => {
    if (!selectedVendor.value) return;
    loadingHistory.value = true;
    try {
        const res = await axios.get(`/api/vendors/${selectedVendor.value.id}/transactions`);
        history.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

const loadSuppliedProducts = async () => {
    if (!selectedVendor.value) return;
    loadingProducts.value = true;
    try {
        const res = await axios.get('/api/products', { 
            params: { vendor_id: selectedVendor.value.id, per_page: 50 } 
        });
        suppliedProducts.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingProducts.value = false;
    }
};

onMounted(loadVendors);

const page = usePage();
watch(() => page.url, () => {
    const urlParams = new URLSearchParams(window.location.search);
    const vendorId = urlParams.get('vendor_id');
    if (vendorId && vendors.value.length > 0) {
        selectedVendor.value = vendors.value.find(v => v.id == vendorId) || selectedVendor.value;
    }
});

watch(selectedVendor, () => {
    loadHistory();
    loadSuppliedProducts();
    activeTab.value = 'transactions';
});

const handleLinkClick = (type, name, id) => {
    console.log(`Navigating to ${type} [ID: ${id}]`);

    if (type === 'Product' && id) {
        router.visit(`/inventory-center?product_id=${id}`);
        return;
    }

    if (type === 'PO' && id) {
        router.visit(`/purchase-orders/${id}`);
        return;
    }

    if (type === 'Movement' && id) {
        router.visit(`/movements/${id}`);
        return;
    }

    toast.add({ 
        severity: id ? 'info' : 'warn', 
        summary: id ? `Navigating to ${type}` : 'Relation Missing', 
        detail: id ? `Pending feature for ${type}` : `Data missing for ${type}`, 
        life: 4000 
    });
};

const getTransactionSeverity = (type) => {
    switch (type.toLowerCase()) {
        case 'receipt': 
        case 'good_receipt': return 'success';
        case 'issue': return 'danger';
        case 'transfer': return 'info';
        case 'adjustment': return 'warning';
        default: return 'secondary';
    }
};

const openNew = () => {
    vendorForm.value = { id: null, vendor_code: '', name: '', email: '', phone: '', address: '', contact_person: '', is_active: true };
    submitted.value = false;
    dialogVisible.value = true;
};

const editVendor = () => {
    vendorForm.value = { ...selectedVendor.value };
    submitted.value = false;
    dialogVisible.value = true;
};

const saveVendor = async () => {
    submitted.value = true;
    if (!vendorForm.value.name || !vendorForm.value.vendor_code) {
        toast.add({ severity: 'warn', summary: 'Missing Information', detail: 'Vendor Name and Code are required.', life: 4000 });
        return;
    }

    try {
        if (vendorForm.value.id) {
            await axios.put(`/api/vendors/${vendorForm.value.id}`, vendorForm.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Vendor updated successfully.', life: 3000 });
        } else {
            await axios.post('/api/vendors', vendorForm.value);
            toast.add({ severity: 'success', summary: 'Registered', detail: 'New vendor added.', life: 3000 });
        }
        dialogVisible.value = false;
        loadVendors();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save vendor.', life: 3000 });
    }
};

const deleteVendor = () => {
    confirm.require({
        message: 'Are you sure you want to remove this vendor permanently?',
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/vendors/${selectedVendor.value.id}`);
                selectedVendor.value = null;
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Vendor removed.', life: 3000 });
                loadVendors();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Cannot remove vendor with active transactions.', life: 4000 });
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
        <Head title="Vendor Center" />
        <Toast />

        <div class="min-h-full flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Manage Suppliers & Vendors</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Vendor Center</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Manage your suppliers, procurement history, and contact information in one central hub.</p>
                </div>
                <div v-if="can('manage-products')">
                    <Button label="ADD VENDOR" icon="pi pi-plus-circle" 
                            class="!bg-sky-500 !border-none !text-white !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" 
                            @click="openNew" />
                </div>
            </div>

            <!-- Primary Workspace Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-8 flex-1 min-h-0">
                
                <!-- Left Sector: Vendor List Sidebar -->
                <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-transparent border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-6 border-b border-zinc-800/50 bg-transparent">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-1.5 h-1.5 rounded-full bg-sky-500"></div>
                            <span class="text-[10px] font-bold text-zinc-500 tracking-[0.2em] uppercase font-mono leading-none">Vendor_List</span>
                        </div>
                        <div class="relative">
                            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 text-sm"></i>
                            <InputText 
                                v-model="search" 
                                placeholder="Search vendors..." 
                                @input="loadVendors" 
                                class="!w-full !pl-11 !pr-4 !bg-zinc-950 !border-zinc-800 !text-white !h-12 !text-xs !rounded-xl focus:!border-sky-500/30 transition-all font-mono"
                            />
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar">
                        <Listbox 
                            v-model="selectedVendor" 
                            :options="vendors" 
                            optionLabel="name" 
                            class="!border-none !bg-transparent"
                            :pt="{
                                root: { class: '!p-2' },
                                item: ({ context }) => ({
                                    class: [
                                        '!p-4 !mb-1 !rounded-xl !transition-all !duration-300 !border',
                                        context.selected 
                                            ? '!bg-sky-500/10 !border-sky-500/20 !text-white shadow-[0_0_15px_rgba(14,165,233,0.05)]' 
                                            : '!bg-transparent !border-transparent !text-zinc-500 hover:!bg-zinc-800/40 hover:!text-zinc-200'
                                    ]
                                })
                            }"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col gap-2 w-full">
                                    <span class="text-[9px] font-bold font-mono tracking-tighter" :class="selectedVendor?.id === option.id ? 'text-sky-400' : 'text-zinc-600'">{{ option.vendor_code }}</span>
                                    <span class="text-xs font-bold truncate tracking-tight">{{ option.name }}</span>
                                </div>
                            </template>
                        </Listbox>
                    </div>
                </aside>

                <!-- Right Sector: Activity & Details Area -->
                <main class="col-span-12 lg:col-span-9 flex flex-col gap-8 min-h-0">
                    
                    <!-- Top Section: Vendor Information -->
                    <section class="bg-transparent border border-zinc-800/80 rounded-2xl p-8 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                        <!-- Background Accent -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-sky-500/5 blur-[100px] -mr-32 -mt-32 rounded-full transition-opacity group-hover:opacity-100 opacity-50"></div>
                        
                        <template v-if="selectedVendor">
                            <div class="relative z-10 flex flex-col">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 pb-10 border-b border-zinc-800/60">
                                    <div class="flex flex-col flex-1">
                                        <div class="flex items-center gap-4 mb-3">
                                            <h1 class="text-3xl font-bold text-white tracking-tighter m-0">{{ selectedVendor.name }}</h1>
                                            <div class="flex gap-2">
                                                <span class="text-[9px] font-bold px-3 py-1 bg-emerald-500/5 border border-emerald-500/20 rounded-full text-emerald-400 uppercase tracking-widest font-mono">APPROVED</span>
                                                <span class="text-[9px] font-bold px-3 py-1 bg-transparent border border-zinc-800 rounded-full text-zinc-500 uppercase tracking-widest font-mono">VENDOR</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 text-zinc-500 text-xs font-mono">
                                            <i class="pi pi-at text-[10px] text-sky-400"></i>
                                            <span>{{ selectedVendor.email || 'No email provided' }}</span>
                                        </div>
                                    </div>

                                    <div v-if="can('manage-products')" class="flex gap-3">
                                        <Button icon="pi pi-pencil" 
                                                class="!bg-zinc-900 !border-zinc-800 !text-zinc-400 hover:!text-white hover:!bg-zinc-800 !w-12 !h-12 !rounded-xl transition-all" 
                                                @click="editVendor" />
                                        <Button icon="pi pi-trash" 
                                                class="!bg-zinc-900 !border-zinc-800 !text-red-400 hover:!text-red-300 hover:!bg-red-500/10 !w-12 !h-12 !rounded-xl transition-all" 
                                                @click="deleteVendor" />
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-12">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Vendor Code</label>
                                        <code class="text-sky-400 font-mono text-sm tracking-tighter bg-transparent px-0 py-0.5 rounded w-fit">{{ selectedVendor.vendor_code }}</code>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Phone Number</label>
                                        <span class="text-white font-bold text-sm tracking-tight flex items-center gap-2">
                                            <i class="pi pi-phone text-sky-500/50 text-[10px]"></i>
                                            {{ selectedVendor.phone || 'N/A' }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2 lg:col-span-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Primary Contact Person</label>
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-transparent flex items-center justify-center border border-zinc-800">
                                                <i class="pi pi-user text-zinc-500 text-xs"></i>
                                            </div>
                                            <span class="text-zinc-300 font-bold text-xs uppercase">{{ selectedVendor.contact_person || 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="h-64 flex flex-col items-center justify-center opacity-20 grayscale">
                            <i class="pi pi-users text-6xl mb-6"></i>
                            <p class="font-mono text-xs tracking-[0.3em] uppercase">Select a vendor to view details...</p>
                        </div>
                    </section>

                    <!-- Bottom Section: Transactions & Products -->
                    <section class="flex-1 min-h-0 bg-transparent border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl flex flex-col backdrop-blur-sm">
                        <!-- Navigation Tabs -->
                        <div class="px-8 border-b border-zinc-800/60 bg-transparent flex items-center gap-8 h-16">
                            <button @click="activeTab = 'transactions'" 
                                    class="h-full px-2 text-[11px] font-bold uppercase tracking-[0.2em] transition-all relative group bg-transparent border-none cursor-pointer"
                                    :class="activeTab === 'transactions' ? 'text-sky-400' : 'text-zinc-500 hover:text-zinc-300'">
                                <i class="pi pi-history mr-2"></i> Recent Transactions
                                <div v-if="activeTab === 'transactions'" class="absolute bottom-0 left-0 w-full h-0.5 bg-sky-500 shadow-[0_0_10px_rgba(14,165,233,0.5)]"></div>
                            </button>
                            <button @click="activeTab = 'products'" 
                                    class="h-full px-2 text-[11px] font-bold uppercase tracking-[0.2em] transition-all relative group bg-transparent border-none cursor-pointer"
                                    :class="activeTab === 'products' ? 'text-emerald-400' : 'text-zinc-500 hover:text-zinc-300'">
                                <i class="pi pi-box mr-2"></i> Master Supplied Products
                                <div v-if="activeTab === 'products'" class="absolute bottom-0 left-0 w-full h-0.5 bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                            </button>
                            
                            <div class="ml-auto">
                                <span class="bg-transparent text-zinc-600 px-3 py-1 rounded text-[10px] font-bold border border-zinc-800/50 font-mono tracking-tighter uppercase">
                                    {{ activeTab === 'transactions' ? `${history.length} Records` : `${suppliedProducts.length} Products` }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-hidden">
                            <!-- Transactions View -->
                            <DataTable 
                                v-if="activeTab === 'transactions'"
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
                                        <i class="pi pi-history text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No history found for this vendor</p>
                                    </div>
                                </template>
                                
                                <Column field="transaction_date" header="Date / Time" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-zinc-400">{{ data.transaction_date }}</span>
                                    </template>
                                </Column>
                                
                                <Column field="reference_number" header="Reference #" style="width: 200px">
                                    <template #body="{ data }">
                                        <span @click.stop="handleLinkClick('Movement', data.reference_number, data.id)"
                                              class="font-mono text-[11px] bg-sky-500/5 text-sky-400 px-2 py-0.5 border border-sky-500/10 rounded tracking-tighter cursor-pointer hover:bg-sky-500/10 hover:border-sky-500/30 transition-all">
                                            {{ data.reference_number }}
                                        </span>
                                    </template>
                                </Column>
                                
                                <Column field="type" header="Type" style="width: 150px">
                                    <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[9px] font-bold tracking-[0.1em] font-mono"
                                             :class="[
                                                 data.type.name.toLowerCase() === 'receipt' || data.type.name.toLowerCase() === 'good_receipt' ? 'bg-emerald-500/5 text-emerald-400 border-emerald-500/20' : 
                                                 data.type.name.toLowerCase() === 'issue' ? 'bg-red-500/5 text-red-400 border-red-500/20' : 
                                                 'bg-sky-500/5 text-sky-400 border-sky-500/20'
                                             ]">
                                            {{ data.display_type || data.type.name.toUpperCase() }}
                                        </div>
                                    </template>
                                </Column>
                                
                                <Column header="Product" style="width: 200px">
                                    <template #body="{ data }">
                                        <div @click.stop="handleLinkClick('Product', data.product_name, data.product_id)" class="text-white hover:text-sky-400 cursor-pointer font-bold text-xs transition-colors">
                                            {{ data.product_name || 'N/A' }}
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Change Qty" style="width: 130px">
                                    <template #body="{ data }">
                                        <div class="flex flex-col">
                                            <span class="font-mono font-bold text-xs tracking-tighter"
                                                  :class="data.quantity < 0 ? 'text-rose-400' : 'text-emerald-400'">
                                                {{ data.quantity < 0 ? '' : '+' }}{{ data.quantity }}
                                            </span>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Unit" style="width: 90px">
                                    <template #body="{ data }">
                                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border border-zinc-800 bg-zinc-950 text-zinc-400 uppercase tracking-widest">
                                            {{ data.uom_abbreviation || 'PCS' }}
                                        </span>
                                    </template>
                                </Column>
 
                                <Column field="to_location" header="Location Tracking">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-2 text-zinc-400 font-bold text-xs">
                                            <i class="pi pi-map-marker text-sky-500/50 text-[10px]"></i>
                                            {{ data.to_location || 'Internal' }}
                                        </div>
                                    </template>
                                </Column>
 
                                <Column header="Linked Document" style="width: 200px">
                                    <template #body="{ data }">
                                        <div v-if="data.po_number || (data.reference_doc && data.reference_doc.includes('PO'))" 
                                            @click.stop="handleLinkClick('PO', data.po_number || data.reference_doc, data.po_id)" 
                                            class="text-emerald-400/80 hover:text-emerald-400 cursor-pointer flex items-center gap-2 font-mono text-[11px] transition-colors">
                                            <i class="pi pi-paperclip text-[10px]"></i> {{ data.po_number || data.reference_doc }}
                                        </div>
                                        <span v-else class="text-zinc-600 font-mono text-[10px]">Manual Entry</span>
                                    </template>
                                </Column>
                                
                                <Column field="status" header="Status" style="width: 140px">
                                     <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="data.status.name.toLowerCase() === 'posted' ? 'bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]' : 'bg-zinc-700'"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono" :class="data.status.name.toLowerCase() === 'posted' ? 'text-zinc-200' : 'text-zinc-600'">{{ data.status.name.toUpperCase() }}</span>
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>

                            <!-- Products View -->
                            <DataTable 
                                v-else-if="activeTab === 'products'"
                                :value="suppliedProducts" 
                                :loading="loadingProducts" 
                                scrollable 
                                scrollHeight="flex" 
                                class="gh-table border-none"
                                :pt="{
                                    root: { class: '!bg-transparent' },
                                    column: {
                                    headercell: { class: '!bg-transparent !border-zinc-800 !text-zinc-600 !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' },
                                        bodycell: { class: '!border-zinc-800/40 !py-4 !px-8 !text-[13px] !text-zinc-300' }
                                    },
                                    bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200 cursor-pointer' }
                                }"
                                @row-click="(e) => handleLinkClick('Product', e.data.name, e.data.id)"
                            >
                                <template #empty>
                                    <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-box text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">This vendor is not currently linked to any catalog items</p>
                                    </div>
                                </template>
                                
                                <Column field="sku" header="SKU / Code" style="width: 180px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] bg-emerald-500/5 text-emerald-400 px-2 py-0.5 border border-emerald-500/10 rounded tracking-tighter">{{ data.sku }}</span>
                                    </template>
                                </Column>

                                <Column field="name" header="Catalog Item Name">
                                    <template #body="{ data }">
                                        <div class="flex flex-col">
                                            <span class="text-white font-bold text-sm">{{ data.name }}</span>
                                            <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">{{ data.category?.name || 'Uncategorized' }}</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="total_qoh" header="Total On Hand" style="width: 150px">
                                    <template #body="{ data }">
                                        <span class="font-mono font-bold text-sm" :class="data.total_qoh > 0 ? 'text-zinc-200' : 'text-zinc-600'">{{ data.total_qoh }} {{ data.uom?.name || 'pcs' }}</span>
                                    </template>
                                </Column>

                                <Column field="selling_price" header="Current Market Price" style="width: 180px">
                                    <template #body="{ data }">
                                        <span class="text-white font-bold">{{ formatCurrency(data.selling_price) }}</span>
                                    </template>
                                </Column>

                                <Column header="Status" style="width: 140px">
                                     <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="data.is_active ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono" :class="data.is_active ? 'text-zinc-200' : 'text-red-400'">{{ data.is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </section>
                </main>
            </div>
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
                            <div class="text-[9px] font-bold text-sky-500 font-mono tracking-[0.2em] mb-1">VENDOR_DETAILS</div>
                            <h2 class="text-white text-xl font-bold tracking-tight m-0">{{ vendorForm.id ? 'Edit Vendor Details' : 'New Vendor Registration' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-zinc-600 hover:!text-white !bg-transparent !border-none !w-10 !h-10 hover:!bg-zinc-900 transition-colors" @click="dialogVisible = false" />
                    </div>

                    <!-- Body -->
                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.03),transparent_40%)]">
                        <div class="grid grid-cols-12 gap-x-6 gap-y-6">
                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Vendor Name *</label>
                                <InputText v-model="vendorForm.name" placeholder="E.g. Apex Logistics Corp" 
                                           class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !font-bold focus:!border-sky-500/40"
                                           :class="{'!border-red-500/50': submitted && !vendorForm.name}" />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Vendor Code</label>
                                <InputText v-model="vendorForm.vendor_code" placeholder="VND-000" class="!bg-zinc-900/50 !border-zinc-800 !text-sky-400 !h-12 !font-mono focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Email Address</label>
                                <InputText v-model="vendorForm.email" placeholder="contact@nexus.com" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" />
                            </div>
                            <div class="col-span-12 md:col-span-6 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Phone Number</label>
                                <InputText v-model="vendorForm.phone" placeholder="+1 (000) 000-0000" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Physical Address</label>
                                <InputText v-model="vendorForm.address" placeholder="Main Facility Sector 7" class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 focus:!border-sky-500/30" />
                            </div>

                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Contact Person</label>
                                <InputText v-model="vendorForm.contact_person" placeholder="Command Officer Name" class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 focus:!border-sky-500/30 font-bold" />
                            </div>

                            <div class="col-span-12 p-5 bg-zinc-900/30 border border-zinc-800/60 rounded-xl flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-white font-bold text-[11px] uppercase tracking-tight">Vendor Active Status</span>
                                    <span class="text-zinc-500 text-[9px] font-mono uppercase mt-0.5">Status // {{ vendorForm.is_active ? 'active' : 'inactive' }}</span>
                                </div>
                                <ToggleSwitch v-model="vendorForm.is_active" 
                                             :pt="{
                                                 slider: ({ props }) => ({
                                                     class: props.modelValue ? '!bg-sky-500' : '!bg-zinc-700'
                                                 })
                                             }" />
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-6 border-t border-zinc-900 bg-zinc-900/50 flex justify-end gap-3">
                        <Button label="CANCEL" class="!bg-transparent !border-zinc-800 !text-zinc-500 hover:!text-white hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="dialogVisible = false" />
                        <Button label="SAVE VENDOR" 
                                class="!bg-sky-500 !border-none !text-white !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-sky-500/10 hover:!bg-sky-400 active:scale-95 transition-all" 
                                @click="saveVendor" />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes v4 */
</style>
