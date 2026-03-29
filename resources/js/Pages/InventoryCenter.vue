<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();
const products = ref([]);
const selectedProduct = ref(null);
const history = ref([]);
const loadingProducts = ref(false);
const loadingHistory = ref(false);
const search = ref('');

const loadProducts = async () => {
    loadingProducts.value = true;
    try {
        const res = await axios.get('/api/products', { params: { query: search.value } });
        products.value = res.data.data;
        if (products.value.length > 0 && !selectedProduct.value) {
            selectedProduct.value = products.value[0];
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingProducts.value = false;
    }
};

const loadHistory = async () => {
    if (!selectedProduct.value) return;
    loadingHistory.value = true;
    try {
        const res = await axios.get(`/api/products/${selectedProduct.value.id}/transactions`);
        history.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

onMounted(loadProducts);

watch(selectedProduct, () => {
    loadHistory();
});

const handleLinkClick = (type, num) => {
    toast.add({ 
        severity: 'info', 
        summary: 'Navigating to Order', 
        detail: `Redirecting to ${type}: ${num}`, 
        life: 3000 
    });
};

const getTransactionSeverity = (type) => {
    switch (type.toLowerCase()) {
        case 'receipt': return 'success';
        case 'issue': return 'danger';
        case 'transfer': return 'info';
        case 'adjustment': return 'warning';
        default: return 'secondary';
    }
};

const formatCurrency = (val) => {
    return '$' + parseFloat(val).toFixed(2);
};

const getStockStatusClass = (p) => {
    if (p.total_qoh === 0) return 'status-danger';
    if (p.total_qoh < p.reorder_point) return 'status-warning';
    return 'status-success';
};

const getStockStatusLabel = (p) => {
    if (p.total_qoh === 0) return 'CRITICAL: ZERO STOCK';
    if (p.total_qoh < p.reorder_point) return 'LOW STOCK: REPLENISH';
    return 'STOCK BALANCED';
};
</script>

<template>
    <AppLayout>
        <Head title="Inventory Center" />
        <Toast />

        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] overflow-hidden flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-[0.2em] block mb-2 font-mono">Live Inventory Monitoring</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Inventory Center</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Track current stock levels, movement history, and valuation across all storage locations in the system.</p>
                </div>
            </div>

            <!-- Primary Workspace Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-8 flex-1 min-h-0">
                
                <!-- Left Sector: Asset Registry Sidebar -->
                <aside class="col-span-12 lg:col-span-3 flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-6 border-b border-zinc-800 bg-zinc-900/60">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                            <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Product_Search</span>
                        </div>
                        <div class="relative">
                            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 text-sm"></i>
                            <InputText 
                                v-model="search" 
                                placeholder="Search products..." 
                                @input="loadProducts" 
                                class="!w-full !pl-11 !pr-4 !bg-zinc-950 !border-zinc-800 !text-white !h-12 !text-xs !rounded-xl focus:!border-emerald-500/30 transition-all font-mono"
                            />
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar">
                        <Listbox 
                            v-model="selectedProduct" 
                            :options="products" 
                            optionLabel="name" 
                            class="!border-none !bg-transparent"
                            :pt="{
                                root: { class: '!p-2' },
                                item: ({ context }) => ({
                                    class: [
                                        '!p-4 !mb-1 !rounded-xl !transition-all !duration-300 !border',
                                        context.selected 
                                            ? '!bg-emerald-500/10 !border-emerald-500/20 !text-white shadow-[0_0_15px_rgba(16,185,129,0.05)]' 
                                            : '!bg-transparent !border-transparent !text-zinc-500 hover:!bg-zinc-800/40 hover:!text-zinc-200'
                                    ]
                                })
                            }"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col gap-2 w-full">
                                    <div class="flex justify-between items-center w-full">
                                        <span class="text-[9px] font-bold font-mono tracking-tighter" :class="selectedProduct?.id === option.id ? 'text-emerald-400' : 'text-zinc-600'">{{ option.sku }}</span>
                                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border leading-none" 
                                              :class="[
                                                  option.total_qoh === 0 ? 'bg-red-500/10 text-red-400 border-red-500/20' : 
                                                  option.total_qoh < option.reorder_point ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 
                                                  'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                              ]">
                                            {{ option.total_qoh }}
                                        </span>
                                    </div>
                                    <span class="text-xs font-bold truncate tracking-tight">{{ option.name }}</span>
                                </div>
                            </template>
                        </Listbox>
                    </div>
                </aside>

                <!-- Right Sector: Intelligence & Ledger Area -->
                <main class="col-span-12 lg:col-span-9 flex flex-col gap-8 min-h-0">
                    
                    <!-- Top Section: Technical Manifest -->
                    <section class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-8 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                        <!-- Background Accent -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 blur-[100px] -mr-32 -mt-32 rounded-full transition-opacity group-hover:opacity-100 opacity-50"></div>
                        
                        <template v-if="selectedProduct">
                            <div class="relative z-10 flex flex-col">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 pb-10 border-b border-zinc-800/60">
                                    <div class="flex flex-col flex-1">
                                        <div class="flex items-center gap-4 mb-3">
                                            <h1 class="text-3xl font-bold text-white tracking-tighter m-0">{{ selectedProduct.name }}</h1>
                                            <div class="flex gap-2">
                                                <span class="text-[9px] font-bold px-3 py-1 bg-zinc-800/80 border border-zinc-700 rounded-full text-zinc-400 uppercase tracking-widest font-mono">{{ selectedProduct.category?.name || 'PRODUCT' }}</span>
                                                <span class="text-[10px] font-bold px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 uppercase tracking-widest font-mono">STATUS // ACTIVE</span>
                                            </div>
                                        </div>
                                        <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed italic">{{ selectedProduct.description || 'No description provided for this catalog item.' }}</p>
                                    </div>

                                    <!-- Stock Status Summary -->
                                    <div class="p-6 bg-zinc-950/80 border border-zinc-800 rounded-2xl flex flex-col items-center justify-center min-w-[200px] shadow-lg"
                                         :class="[
                                             selectedProduct.total_qoh === 0 ? 'ring-1 ring-red-500/20' : 
                                             selectedProduct.total_qoh < selectedProduct.reorder_point ? 'ring-1 ring-amber-500/20' : 
                                             'ring-1 ring-emerald-500/20'
                                         ]">
                                        <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-[0.2em] mb-2 font-mono">Quantity_on_hand</span>
                                        <span class="text-4xl font-bold tracking-tighter font-mono" 
                                              :class="[
                                                  selectedProduct.total_qoh === 0 ? 'text-red-400' : 
                                                  selectedProduct.total_qoh < selectedProduct.reorder_point ? 'text-amber-400' : 
                                                  'text-emerald-400'
                                              ]">
                                            {{ selectedProduct.total_qoh }}
                                        </span>
                                        <span class="text-[10px] font-bold font-mono mt-2" 
                                              :class="[
                                                  selectedProduct.total_qoh === 0 ? 'text-red-400/80' : 
                                                  selectedProduct.total_qoh < selectedProduct.reorder_point ? 'text-amber-400/80' : 
                                                  'text-emerald-400/80'
                                              ]">
                                            {{ getStockStatusLabel(selectedProduct) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-12 gap-y-8">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">SKU / Code</label>
                                        <code class="text-sky-400 font-mono text-sm tracking-tighter bg-sky-500/5 px-2 py-0.5 rounded border border-sky-500/10 w-fit">{{ selectedProduct.sku }}</code>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Selling Price</label>
                                        <span class="text-white font-bold text-lg tracking-tight">{{ formatCurrency(selectedProduct.selling_price) }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Unit of Measure</label>
                                        <span class="text-zinc-300 font-bold uppercase text-xs">{{ selectedProduct.uom?.name || 'Standard Unit' }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Costing Method</label>
                                        <span class="text-emerald-400 font-mono font-bold text-[11px] bg-emerald-500/5 px-2 py-0.5 rounded border border-emerald-500/10 w-fit uppercase">{{ selectedProduct.costing_method }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="h-64 flex flex-col items-center justify-center opacity-20 grayscale">
                            <i class="pi pi-box text-6xl mb-6"></i>
                            <p class="font-mono text-xs tracking-[0.3em] uppercase">System Ready: Select Node for Intelligence Data</p>
                        </div>
                    </section>

                    <!-- Bottom Section: Transactional Ledger -->
                    <section class="flex-1 min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl flex flex-col backdrop-blur-sm">
                        <div class="px-8 py-5 border-b border-zinc-800/60 bg-zinc-900/80 flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></div>
                                <span class="text-[11px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono">Recent Transaction History</span>
                            </div>
                            <span class="bg-zinc-800/60 text-zinc-500 px-3 py-1 rounded text-[10px] font-bold border border-zinc-700 font-mono tracking-tighter">{{ history.length }} RECORDS FOUND</span>
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
                                        headercell: { class: '!bg-zinc-900/60 !border-zinc-800 !text-zinc-500 !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' },
                                        bodycell: { class: '!border-zinc-800/40 !py-4 !px-8 !text-[13px] !text-zinc-300' }
                                    },
                                    bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200' }
                                }"
                            >
                                <template #empty>
                                    <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-database text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No Transactional Artifacts Detected in Local Subnet</p>
                                    </div>
                                </template>
                                
                                <Column field="transaction_date" header="Date / Time" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-zinc-400">{{ data.transaction_date }}</span>
                                    </template>
                                </Column>
                                
                                <Column field="reference_number" header="Reference #" style="width: 180px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] bg-zinc-950 text-sky-400 px-2 py-0.5 border border-sky-500/10 rounded tracking-tighter">{{ data.reference_number }}</span>
                                    </template>
                                </Column>
                                
                                <Column field="type" header="Type" style="width: 150px">
                                    <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[9px] font-bold tracking-[0.1em] font-mono"
                                             :class="[
                                                 data.type.toLowerCase() === 'receipt' ? 'bg-emerald-500/5 text-emerald-400 border-emerald-500/20' : 
                                                 data.type.toLowerCase() === 'issue' ? 'bg-red-500/5 text-red-400 border-red-500/20' : 
                                                 'bg-sky-500/5 text-sky-400 border-sky-500/20'
                                             ]">
                                            {{ data.type.toUpperCase() }}
                                        </div>
                                    </template>
                                </Column>
                                
                                <Column field="quantity" header="Change Qty" style="width: 140px">
                                    <template #body="{ data }">
                                        <div class="font-mono font-bold text-sm tracking-tighter" :class="data.type.toLowerCase() === 'issue' || (data.type.toLowerCase() === 'adjustment' && data.quantity < 0) ? 'text-red-400' : 'text-emerald-400'">
                                            {{ data.type.toLowerCase() === 'issue' || (data.type.toLowerCase() === 'adjustment' && data.quantity < 0) ? '' : '+' }}{{ data.quantity }}
                                        </div>
                                    </template>
                                </Column>
                                
                                <Column header="Entity / Vendor">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-3">
                                            <div v-if="data.vendor_name" @click="handleLinkClick('Vendor', data.vendor_name)" class="text-sky-400 cursor-pointer hover:underline flex items-center gap-2 font-bold text-xs tracking-tight">
                                                <i class="pi pi-building text-[10px]"></i> {{ data.vendor_name }}
                                            </div>
                                            <div v-else-if="data.customer_name" @click="handleLinkClick('Customer', data.customer_name)" class="text-amber-400 cursor-pointer hover:underline flex items-center gap-2 font-bold text-xs tracking-tight">
                                                <i class="pi pi-user text-[10px]"></i> {{ data.customer_name }}
                                            </div>
                                            <span v-else class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">internal movement</span>
                                        </div>
                                    </template>
                                </Column>
                                
                                <Column header="Linked Document" style="width: 200px">
                                    <template #body="{ data }">
                                        <div v-if="data.po_number || (data.reference_doc && data.reference_doc.includes('PO'))" 
                                            @click="handleLinkClick('PO', data.po_number || data.reference_doc)" 
                                            class="text-emerald-400/80 hover:text-emerald-400 cursor-pointer flex items-center gap-2 font-mono text-[11px] transition-colors">
                                            <i class="pi pi-paperclip text-[10px]"></i> {{ data.po_number || data.reference_doc }}
                                        </div>
                                        <div v-else-if="data.so_number || (data.reference_doc && data.reference_doc.includes('SO'))" 
                                            @click="handleLinkClick('SO', data.so_number || data.reference_doc)" 
                                            class="text-amber-400/80 hover:text-amber-400 cursor-pointer flex items-center gap-2 font-mono text-[11px] transition-colors">
                                            <i class="pi pi-send text-[10px]"></i> {{ data.so_number || data.reference_doc }}
                                        </div>
                                        <span v-else-if="data.reference_doc" class="text-zinc-600 font-mono text-[10px] uppercase truncate max-w-[140px]">{{ data.reference_doc }}</span>
                                        <span v-else class="text-zinc-800 font-mono text-[11px]">NULL_REF</span>
                                    </template>
                                </Column>
                                
                                <Column field="status" header="Status" style="width: 140px">
                                     <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="data.status.toLowerCase() === 'posted' ? 'bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]' : 'bg-zinc-700'"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono" :class="data.status.toLowerCase() === 'posted' ? 'text-zinc-200' : 'text-zinc-600'">{{ data.status.toUpperCase() }}</span>
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes v4 */
</style>
