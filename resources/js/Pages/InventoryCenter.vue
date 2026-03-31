<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Listbox from 'primevue/listbox';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Menu from 'primevue/menu';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();
const products = ref([]);
const selectedProduct = ref(null);
const history = ref([]);
const layers = ref([]);
const locationBreakdown = ref([]);
const loadingProducts = ref(false);
const loadingHistory = ref(false);
const loadingIntelligence = ref(false);
const search = ref('');

const rulesDialogVisible = ref(false);
const reorderRules = ref([]);
const loadingRules = ref(false);
const ruleForm = ref({
    id: null,
    location_id: null,
    min_stock: 0,
    max_stock: null,
    reorder_qty: 0,
    is_active: true
});
const savingRule = ref(false);

const loadProducts = async () => {
    loadingProducts.value = true;
    try {
        const res = await axios.get('/api/products', { params: { query: search.value } });
        products.value = res.data.data;
        if (products.value.length > 0 && !selectedProduct.value) {
            const urlParams = new URLSearchParams(window.location.search);
            const productId = urlParams.get('product_id');
            if (productId) {
                selectedProduct.value = products.value.find(p => p.id == productId) || products.value[0];
            } else {
                selectedProduct.value = products.value[0];
            }
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

const loadIntelligenceData = async () => {
    if (!selectedProduct.value) return;
    loadingIntelligence.value = true;
    try {
        const [locRes, layerRes] = await Promise.all([
            axios.get(`/api/inventory/${selectedProduct.value.id}/locations`),
            axios.get(`/api/inventory/${selectedProduct.value.id}/cost-layers`)
        ]);
        locationBreakdown.value = locRes.data.data;
        layers.value = layerRes.data.data;
    } catch (e) {
        console.error("Intelligence load error", e);
    } finally {
        loadingIntelligence.value = false;
    }
};

const loadReorderRules = async () => {
    if (!selectedProduct.value) return;
    loadingRules.value = true;
    try {
        const res = await axios.get(`/api/reorder-rules?product_id=${selectedProduct.value.id}`);
        reorderRules.value = res.data;
    } catch (e) {
        console.error("Rules load error", e);
    } finally {
        loadingRules.value = false;
    }
};

const openReorderRulesDialog = () => {
    loadReorderRules();
    rulesDialogVisible.value = true;
};

const editRule = (rule) => {
    ruleForm.value = { ...rule };
};

const prepareNewRule = () => {
    ruleForm.value = {
        id: null,
        location_id: null,
        min_stock: selectedProduct.value?.reorder_point || 0,
        max_stock: null,
        reorder_qty: selectedProduct.value?.reorder_quantity || 0,
        is_active: true
    };
};

const saveRule = async () => {
    savingRule.value = true;
    try {
        const payload = { ...ruleForm.value, product_id: selectedProduct.value.id };
        if (ruleForm.value.id) {
            await axios.put(`/api/reorder-rules/${ruleForm.value.id}`, payload);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Reorder rule updated', life: 3000 });
        } else {
            await axios.post('/api/reorder-rules', payload);
            toast.add({ severity: 'success', summary: 'Created', detail: 'New reorder rule active', life: 3000 });
        }
        loadReorderRules();
        ruleForm.value = { id: null, location_id: null, min_stock: 0, max_stock: null, reorder_qty: 0, is_active: true };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to save rule', life: 4000 });
    } finally {
        savingRule.value = false;
    }
};

const deleteRule = async (id) => {
    if(!confirm("Are you sure you want to delete this rule?")) return;
    try {
        await axios.delete(`/api/reorder-rules/${id}`);
        toast.add({ severity: 'success', summary: 'Deleted', detail: 'Rule deleted successfully', life: 3000 });
        loadReorderRules();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed', life: 3000 });
    }
};


const menu = ref(null);
const toggleMenu = (event) => {
    menu.value.toggle(event);
};

const movementOptions = [
    { 
        label: 'Stock Receipt', 
        icon: 'pi pi-plus-circle', 
        command: () => router.visit('/movements/receipt') 
    },
    { 
        label: 'Stock Issuance', 
        icon: 'pi pi-minus-circle', 
        command: () => router.visit('/movements/issue') 
    },
    { 
        label: 'Location Transfer', 
        icon: 'pi pi-arrow-right-arrow-left', 
        command: () => router.visit('/movements/transfer') 
    },
    { 
        label: 'Stock Adjustment', 
        icon: 'pi pi-sliders-h', 
        command: () => router.visit('/movements/adjustment') 
    }
];

onMounted(loadProducts);

const page = usePage();
watch(() => page.url, () => {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('product_id');
    if (productId && products.value.length > 0) {
        selectedProduct.value = products.value.find(p => p.id == productId) || selectedProduct.value;
    }
});

watch(selectedProduct, () => {
    loadHistory();
    loadIntelligenceData();
});

const handleLinkClick = (type, name, id) => {
    console.log(`Navigating to ${type} [ID: ${id}]`);
    
    if (type === 'Vendor' && id) {
        router.visit(`/vendor-center?vendor_id=${id}`);
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

    if (type === 'Product' && id) {
        router.visit(`/inventory-center?product_id=${id}`);
        return;
    }
    
    // Fallback or feedback if ID is missing or type is unhandled
    const detailMsg = id 
        ? `Pending feature for ${type}: ${name}` 
        : `Cannot link this ${type}: Data reference (ID) is missing in the system ledger.`;
        
    toast.add({ 
        severity: id ? 'info' : 'warn', 
        summary: id ? `Navigating to ${type}` : 'Relation Missing', 
        detail: detailMsg, 
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

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val);
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

const listboxPt = {
    root: { class: '!p-2 h-full flex flex-col' },
    listContainer: { class: '!max-h-none flex-1 overflow-y-auto custom-scrollbar' },
    item: (options) => ({
        class: [
            '!p-4 !mb-1 !rounded-xl !transition-all !duration-300 !border',
            options.context.selected 
                ? '!bg-emerald-500/10 !border-emerald-500/20 !text-white shadow-[0_0_15px_rgba(16,185,129,0.05)]' 
                : '!bg-transparent !border-transparent !text-zinc-500 hover:!bg-zinc-800/40 hover:!text-zinc-200'
        ]
    })
};

const tablePt = {
    root: { class: '!bg-transparent' },
    bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200' },
    header: { class: '!bg-zinc-900/60 !border-zinc-800 !text-zinc-500 !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' }
};
</script>

<template>
    <AppLayout>
        <Head title="Inventory Center" />
        <Toast />

        <div class="p-8 bg-zinc-950 min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-[0.2em] block mb-2 font-mono">Stock Monitoring</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Inventory Center</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Track current stock levels, movement history, and valuation across all storage locations in the system.</p>
                </div>
                
                <div class="flex gap-4 items-center">
                    <button @click="toggleMenu" 
                            class="bg-emerald-500 hover:bg-emerald-400 text-zinc-950 px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 shadow-[0_0_20px_rgba(16,185,129,0.2)] flex items-center gap-3">
                        <i class="pi pi-plus text-sm"></i>
                        <span>New Movement</span>
                    </button>
                    <Menu ref="menu" :model="movementOptions" :popup="true" class="!bg-zinc-900 !border-zinc-800 !p-2 !rounded-xl !min-w-[200px]" :pt="{
                        itemlink: { class: 'hover:!bg-zinc-800 !rounded-lg !p-3 transition-all' },
                        itemlabel: { class: '!text-[10px] !font-bold !text-zinc-400 !uppercase !tracking-widest' },
                        itemicon: { class: '!text-zinc-500 !text-sm' }
                    }" />
                </div>
            </div>

            <!-- Primary Workspace Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-8 items-start flex-1 min-h-0">
                
                <aside class="col-span-12 lg:col-span-3 lg:sticky lg:top-[140px] lg:h-[calc(100vh-160px)] flex flex-col min-h-0 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-6 border-b border-zinc-800 bg-zinc-900/60">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                            <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Product List</span>
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
                    
                    <div class="flex-1 min-h-0 flex flex-col">
                        <Listbox 
                            v-model="selectedProduct" 
                            :options="products" 
                            optionLabel="name" 
                            class="!border-none !bg-transparent flex-1"
                            :pt="listboxPt"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col gap-2 w-full">
                                    <div class="flex justify-between items-center w-full">
                                        <span class="text-[9px] font-bold font-mono tracking-widest uppercase" :class="selectedProduct?.id === option.id ? 'text-emerald-400' : 'text-zinc-600'">{{ option.sku }}</span>
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

                <!-- Right Sector: Product History & Insights -->
                <main class="col-span-12 lg:col-span-9 flex flex-col gap-8 min-h-0">
                    
                    <!-- Top Section: Product Details -->
                    <section class="bg-zinc-900/40 border border-zinc-800/80 rounded-2xl p-8 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                        <!-- Background Accent -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 blur-[100px] -mr-32 -mt-32 rounded-full transition-opacity group-hover:opacity-100 opacity-50"></div>
                        
                        <template v-if="selectedProduct">
                            <div class="relative z-10 flex flex-col">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 pb-10 border-b border-zinc-800/60">
                                    <div class="flex flex-col flex-1">
                                        <div class="flex items-center gap-4 mb-3">
                                            <h1 class="text-3xl font-bold text-white tracking-tighter m-0">{{ selectedProduct.name }}</h1>
                                            <div class="flex flex-wrap gap-2">
                                                <span class="text-[9px] font-bold px-3 py-1 bg-zinc-800/80 border border-zinc-700 rounded-full text-zinc-400 uppercase tracking-widest font-mono">{{ selectedProduct.category?.name || 'PRODUCT' }}</span>
                                                <span v-if="selectedProduct.preferred_vendor" class="text-[9px] font-bold px-3 py-1 bg-sky-500/10 border border-sky-500/20 rounded-full text-sky-400 uppercase tracking-widest font-mono">OWNED BY: {{ selectedProduct.preferred_vendor.name }}</span>
                                                <span v-else class="text-[9px] font-bold px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full text-amber-400 uppercase tracking-widest font-mono">STOCK: INTERNAL</span>
                                                <span class="text-[10px] font-bold px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 uppercase tracking-widest font-mono">STATUS: ACTIVE</span>
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
                                        <span class="text-[9px] font-bold text-zinc-500 uppercase tracking-[0.2em] mb-2 font-mono">Current Stock</span>
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
                                
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-x-12 gap-y-8">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Internal ID</label>
                                        <code class="text-sky-400 font-mono text-sm tracking-widest bg-sky-500/5 px-2 py-0.5 rounded border border-sky-500/10 w-fit">{{ selectedProduct.sku }}</code>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Selling Price</label>
                                        <span class="text-white font-bold text-lg tracking-tight">{{ formatCurrency(selectedProduct.selling_price) }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Average Unit Cost</label>
                                        <span class="text-sky-400 font-bold text-lg tracking-tight">{{ formatCurrency(selectedProduct.average_cost) }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Unit of Measure</label>
                                        <span class="text-zinc-300 font-bold uppercase text-xs">{{ selectedProduct.uom?.name || 'Unit' }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Valuation Method</label>
                                        <span class="text-emerald-400 font-mono font-bold text-[11px] bg-emerald-500/5 px-2 py-0.5 rounded border border-emerald-500/10 w-fit uppercase">{{ selectedProduct.valuation_method || selectedProduct.costing_method }}</span>
                                    </div>
                                </div>

                                <!-- Quick Actions Toolbar -->
                                <div class="mt-10 pt-8 border-t border-zinc-900 flex items-center gap-8 animate-in fade-in slide-in-from-left-4 duration-1000">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-bold text-zinc-700 uppercase tracking-[0.3em] font-mono leading-none mb-1">Actions</span>
                                        <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-tight">Post Movement</span>
                                    </div>
                                    <div class="flex gap-4">
                                        <button @click="router.visit('/movements/receipt?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400 text-[10px] font-bold uppercase tracking-widest hover:bg-sky-500 hover:text-white transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-plus-circle" /> Receipt
                                        </button>
                                        <button @click="router.visit('/movements/issue?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[10px] font-bold uppercase tracking-widest hover:bg-rose-500 hover:text-white transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-minus-circle" /> Issue
                                        </button>
                                        <button @click="router.visit('/movements/transfer?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400 text-[10px] font-bold uppercase tracking-widest hover:bg-violet-500 hover:text-white transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-arrow-right-arrow-left" /> Transfer
                                        </button>
                                        <button @click="router.visit('/movements/adjustment?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[10px] font-bold uppercase tracking-widest hover:bg-amber-500 hover:text-zinc-950 transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-sliders-h" /> Adjust
                                        </button>
                                        <button @click="openReorderRulesDialog" 
                                                class="px-6 h-11 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-bold uppercase tracking-widest hover:bg-indigo-500 hover:text-white transition-all active:scale-95 flex items-center gap-2 ml-4 relative">
                                            <i class="pi pi-cog" /> Reorder Rules
                                            <span class="absolute -top-2 -right-2 w-3 h-3 bg-indigo-500 rounded-full animate-pulse border-2 border-zinc-900"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="h-64 flex flex-col items-center justify-center opacity-20 grayscale">
                            <i class="pi pi-box text-6xl mb-6"></i>
                            <p class="font-mono text-xs tracking-[0.3em] uppercase">Select a product to view details</p>
                        </div>
                    </section>

                    <!-- Intelligence Grid: Location Breakdown & Cost Layers -->
                    <div v-if="selectedProduct" class="grid grid-cols-12 gap-8 animate-in fade-in slide-in-from-bottom-6 duration-700">
                        <!-- Location Distribution Sector -->
                        <aside class="col-span-12 lg:col-span-5 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm flex flex-col min-h-0">
                            <div class="px-8 py-4 border-b border-zinc-800 bg-zinc-900/60 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <i class="pi pi-map-marker text-emerald-400 text-xs"></i>
                                    <span class="text-[10px] font-bold text-zinc-300 tracking-[0.25em] uppercase font-mono">Storage Breakdown</span>
                                </div>
                                <span class="text-[9px] font-bold text-emerald-500 font-mono tracking-tighter uppercase">Available in these areas</span>
                            </div>
                            <div class="p-6 flex-1">
                                <template v-if="locationBreakdown.length > 0">
                                    <div class="space-y-3">
                                        <div v-for="loc in locationBreakdown" :key="loc.id" class="flex items-center justify-between p-4 bg-zinc-950/50 border border-zinc-800/60 rounded-xl group hover:border-emerald-500/20 transition-all duration-300">
                                            <div class="flex flex-col">
                                                <span class="text-white font-bold text-xs tracking-tight uppercase">{{ loc.location_name }}</span>
                                                <span class="text-[9px] font-bold text-zinc-600 font-mono tracking-widest">{{ loc.location_code }}</span>
                                            </div>
                                            <div class="flex flex-col items-end">
                                                <span class="text-emerald-400 font-mono font-bold text-sm tracking-tighter">{{ loc.quantity_on_hand }}</span>
                                                <span class="text-[9px] font-bold text-zinc-700 font-mono tracking-[0.2em] uppercase">Items at this area</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="h-32 flex flex-col items-center justify-center opacity-30 grayscale border-2 border-dashed border-zinc-800 rounded-2xl">
                                    <i class="pi pi-map-marker text-3xl mb-3"></i>
                                    <span class="text-[9px] font-bold font-mono tracking-widest uppercase text-zinc-600">No Physical Presence Detected</span>
                                </div>
                            </div>
                        </aside>

                        <!-- Cost Layer Persistence Sector -->
                        <aside class="col-span-12 lg:col-span-7 bg-zinc-900/40 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm flex flex-col min-h-0">
                            <div class="px-8 py-4 border-b border-zinc-800 bg-zinc-900/60 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <i class="pi pi-database text-sky-400 text-xs"></i>
                                    <span class="text-[10px] font-bold text-zinc-300 tracking-[0.25em] uppercase font-mono">Inventory Costing ({{ selectedProduct?.valuation_method || selectedProduct?.costing_method || 'FIFO' }})</span>
                                </div>
                                <span class="text-[9px] font-bold text-sky-400 font-mono tracking-tighter uppercase">In Stock</span>
                            </div>
                            <div class="p-0 flex-1 flex flex-col">
                                <DataTable 
                                    :value="layers" 
                                    :loading="loadingIntelligence"
                                    scrollable 
                                    scrollHeight="320px"
                                    class="gh-table border-none !bg-transparent"
                                    :pt="tablePt"
                                >
                                    <template #empty>
                                        <div class="py-20 text-center opacity-20 flex flex-col items-center grayscale">
                                            <i class="pi pi-history text-4xl mb-4"></i>
                                            <p class="font-mono text-[10px] tracking-[0.2em] uppercase">No Financial Cost Footprint</p>
                                        </div>
                                    </template>
                                    <Column field="receipt_date" header="Date Received" class="!bg-zinc-900/60 !text-zinc-500 !text-[10px] !uppercase !font-bold" style="width: 140px">
                                        <template #body="{ data }">
                                            <span class="font-mono text-[10px] text-zinc-500">{{ data.receipt_date }}</span>
                                        </template>
                                    </Column>
                                    <Column field="remaining_qty" header="Remaining" style="width: 110px">
                                        <template #body="{ data }">
                                            <span class="font-mono font-bold text-zinc-200">{{ data.remaining_qty }}</span>
                                        </template>
                                    </Column>
                                    <Column field="unit_cost" header="Unit Cost" style="width: 120px">
                                        <template #body="{ data }">
                                            <span class="font-mono font-bold text-sky-400 tracking-tighter text-[11px]">{{ formatCurrency(data.unit_cost) }}</span>
                                        </template>
                                    </Column>
                                    <Column header="Status" class="!text-right">
                                        <template #body="{ data }">
                                            <div class="inline-flex items-center gap-2 group-hover:px-2 transition-all">
                                                <span class="w-1 h-1 rounded-full bg-sky-500 shadow-[0_0_8px_rgba(14,165,233,0.8)]"></span>
                                                <span class="text-[9px] font-bold text-sky-500 uppercase tracking-widest font-mono">{{ data.remaining_qty > 0 ? 'ACTIVE' : 'DEPLETED' }}</span>
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </aside>
                    </div>

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
                                :pt="tablePt"
                            >
                                <template #empty>
                                    <div class="py-32 text-center opacity-20 flex flex-col items-center grayscale">
                                        <i class="pi pi-database text-5xl mb-6"></i>
                                        <p class="font-mono text-xs tracking-[0.2em] uppercase">No transaction history found for this product</p>
                                    </div>
                                </template>
                                
                                <Column field="transaction_date" header="Date / Time" style="width: 160px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-zinc-400">{{ data.transaction_date }}</span>
                                    </template>
                                </Column>
                                
                                <Column field="reference_number" header="Reference #" style="width: 180px">
                                    <template #body="{ data }">
                                        <span @click.stop="handleLinkClick('Movement', data.reference_number, data.id)" 
                                              class="font-mono text-[11px] bg-zinc-950 text-sky-400 px-2 py-0.5 border border-sky-500/10 rounded tracking-widest cursor-pointer hover:bg-sky-500/10 hover:border-sky-500/30 transition-all shadow-[0_0_15px_rgba(56,189,248,0.05)] uppercase">
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
                                
                                <Column field="quantity" header="Change Qty" style="width: 140px">
                                    <template #body="{ data }">
                                        <div class="font-mono font-bold text-sm tracking-tighter" :class="data.type.name.toLowerCase() === 'issue' || (data.type.name.toLowerCase() === 'adjustment' && data.quantity < 0) ? 'text-red-400' : 'text-emerald-400'">
                                            {{ data.type.name.toLowerCase() === 'issue' || (data.type.name.toLowerCase() === 'adjustment' && data.quantity < 0) ? '' : '+' }}{{ data.quantity }}
                                        </div>
                                    </template>
                                </Column>
                                
                                <Column header="Entity / Vendor">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-3">
                                            <div v-if="data.vendor_name" @click.stop="handleLinkClick('Vendor', data.vendor_name, data.vendor_id)" class="text-sky-400 cursor-pointer hover:underline flex items-center gap-2 font-bold text-xs tracking-tight">
                                                <i class="pi pi-building text-[10px]"></i> {{ data.vendor_name }}
                                            </div>
                                            <div v-else-if="data.customer_name" @click.stop="handleLinkClick('Customer', data.customer_name, data.customer_id)" class="text-amber-400 cursor-pointer hover:underline flex items-center gap-2 font-bold text-xs tracking-tight">
                                                <i class="pi pi-user text-[10px]"></i> {{ data.customer_name }}
                                            </div>
                                            <span v-else class="text-[10px] font-bold text-zinc-600 uppercase tracking-widest font-mono">internal movement</span>
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
                                        <div v-else-if="data.so_number || (data.reference_doc && data.reference_doc.includes('SO'))" 
                                            @click.stop="handleLinkClick('SO', data.so_number || data.reference_doc, data.so_id)" 
                                            class="text-amber-400/80 hover:text-amber-400 cursor-pointer flex items-center gap-2 font-mono text-[11px] transition-colors">
                                            <i class="pi pi-send text-[10px]"></i> {{ data.so_number || data.reference_doc }}
                                        </div>
                                        <span v-else-if="data.reference_doc" class="text-zinc-600 font-mono text-[10px] uppercase truncate max-w-[140px]">{{ data.reference_doc }}</span>
                                        <span v-else class="text-zinc-800 font-mono text-[11px]">Internal</span>
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
                        </div>
                    </section>
                </main>
            </div>
        </div>
        
        <Dialog 
            v-model:visible="rulesDialogVisible" 
            modal 
            header="Reorder & Replenishment Rules"
            class="max-w-4xl w-full mx-4"
            :pt="{
                root: { class: '!bg-zinc-950 !border !border-zinc-800' },
                header: { class: '!bg-zinc-900 !text-white !border-b !border-zinc-800 !p-6' },
                content: { class: '!p-0 !bg-zinc-950' }
            }"
            @show="prepareNewRule"
        >
            <div class="flex flex-col md:flex-row shadow-inner min-h-[500px]">
                <!-- Rules List -->
                <div class="w-full md:w-1/2 border-r border-zinc-800 bg-zinc-900/30 flex flex-col p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-bold text-white uppercase tracking-widest font-mono m-0">Active Rules</h3>
                        <button @click="prepareNewRule" class="text-[10px] font-bold text-emerald-400 hover:text-emerald-300 uppercase tracking-widest"><i class="pi pi-plus" /> New</button>
                    </div>
                    
                    <div v-if="loadingRules" class="flex-1 flex justify-center items-center">
                        <i class="pi pi-spin pi-spinner text-zinc-500 text-2xl"></i>
                    </div>
                    
                    <div v-else-if="reorderRules.length === 0" class="flex-1 flex flex-col items-center justify-center opacity-50 grayscale">
                        <i class="pi pi-sitemap text-3xl mb-4"></i>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] font-mono text-zinc-500 text-center">No rules configured.<br/>Engine will ignore this product.</span>
                    </div>

                    <div v-else class="flex-1 space-y-3 overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="rule in reorderRules" :key="rule.id" 
                             @click="editRule(rule)"
                             class="p-4 rounded-xl border cursor-pointer transition-all group"
                             :class="ruleForm.id === rule.id ? 'bg-indigo-500/10 border-indigo-500/30' : 'bg-zinc-950 border-zinc-800 hover:border-zinc-700'">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-white truncate max-w-[150px]">{{ rule.location_name }}</span>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded text-white" :class="rule.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-zinc-800 text-zinc-500'">{{ rule.is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-zinc-600 font-bold uppercase font-mono">Min Stock</span>
                                    <span class="text-amber-400 font-bold font-mono text-xs">{{ rule.min_stock }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-zinc-600 font-bold uppercase font-mono">Restock</span>
                                    <span class="text-emerald-400 font-bold font-mono text-xs">{{ rule.reorder_qty }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="w-full md:w-1/2 p-8 flex flex-col bg-zinc-950">
                    <h3 class="text-sm font-bold text-white uppercase tracking-widest font-mono mb-8 m-0">{{ ruleForm.id ? 'Edit Rule' : 'Create Rule' }}</h3>
                    
                    <div class="space-y-6 flex-1">
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Location <span class="text-zinc-600 italic lowercase">(leave blank for global root rule)</span></label>
                            <Select v-model="ruleForm.location_id" :options="locationBreakdown" optionLabel="location_name" optionValue="location_id" 
                                    placeholder="Global Defaults (All Locations)" showClear 
                                    class="!bg-zinc-900 !border-zinc-800 text-white w-full" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Min Stock (Trigger)</label>
                                <InputNumber v-model="ruleForm.min_stock" inputClass="!bg-zinc-900 !border-zinc-800 !text-white !w-full !font-mono" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Restock Amount</label>
                                <InputNumber v-model="ruleForm.reorder_qty" inputClass="!bg-zinc-900 !border-zinc-800 !text-white !w-full !font-mono" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Max Stock Limit (Optional)</label>
                            <InputNumber v-model="ruleForm.max_stock" inputClass="!bg-zinc-900 !border-zinc-800 !text-white !w-full !font-mono" placeholder="No limit" />
                        </div>

                        <div class="flex items-center justify-between p-4 border border-zinc-800 bg-zinc-900/50 rounded-lg">
                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest font-mono">Status</span>
                            <ToggleSwitch v-model="ruleForm.is_active" />
                        </div>
                    </div>

                    <div class="pt-8 mt-8 border-t border-zinc-800 flex justify-between items-center gap-4">
                        <button v-if="ruleForm.id" @click="deleteRule(ruleForm.id)" class="px-5 h-12 rounded-lg text-[11px] font-bold uppercase tracking-widest text-red-500 hover:bg-red-500/10 transition-colors border border-transparent hover:border-red-500/20">Delete</button>
                        <div class="flex-1"></div>
                        <button @click="prepareNewRule" class="bg-transparent border border-zinc-800 text-zinc-500 hover:text-white hover:border-zinc-600 px-8 h-12 rounded-lg font-bold text-[11px] uppercase tracking-widest transition-colors flex items-center justify-center">Clear</button>
                        <button @click="saveRule" :disabled="savingRule" class="bg-emerald-500 border-none text-zinc-950 px-10 h-12 rounded-lg font-bold text-[11px] uppercase tracking-widest hover:bg-emerald-400 active:scale-95 shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center min-w-[140px]">
                            <i v-if="savingRule" class="pi pi-spin pi-spinner mr-2"></i>
                            {{ savingRule ? 'SAVING...' : 'SAVE RULE' }}
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes v4 */
</style>
