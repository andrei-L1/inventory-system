<script setup>
import { ref, onMounted, watch, computed } from 'vue';
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
import Popover from 'primevue/popover';

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

// Transaction History Filters & Pagination
const historyFilters = ref({
    date_from: null,
    date_to: null,
    type: 'all',
});
const historyMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
const historyCurrentPage = ref(1);

const historyTypeOptions = [
    { label: 'All Types', value: 'all' },
    { label: 'Receipt', value: 'receipt' },
    { label: 'Issue', value: 'issue' },
    { label: 'Transfer', value: 'transfer' },
    { label: 'Adjustment', value: 'adjustment' },
];

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

const uoms = ref([]);
const uomConversions = ref([]);
const selectedViewUomId = ref(null);
const stockOp = ref(null);
const selectedLineForStock = ref(null);
const showHighPrecision = ref(false);

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id == id);
    return uom ? uom.category === 'count' : true;
};

const filteredUoms = computed(() => {
    if (!selectedProduct.value) return uoms.value;
    
    const cat = selectedProduct.value.uom?.category;
    return uoms.value.filter(u => {
        // Must be in same category to even be a candidate
        if (u.category !== cat) return false;

        // Base units are always allowed
        if (u.is_base) return true;
        
        // Product-specific conversions
        const hasProdRule = uomConversions.value.some(c => 
            c.product_id === selectedProduct.value.id && 
            (c.from_uom_id === u.id || c.to_uom_id === u.id)
        );
        if (hasProdRule) return true;
        
        // Global conversions
        const hasGlobalRule = uomConversions.value.some(c => 
            !c.product_id && 
            (c.from_uom_id === u.id || c.to_uom_id === u.id)
        );
        if (hasGlobalRule) return true;
        
        return false;
    });
});

const getFactorToBase = (uomId, productId = null) => {
    const uom = uoms.value.find(u => u.id == uomId);
    if (!uom) return { factor: 1, baseId: uomId };
    if (uom.is_base) return { factor: 1, baseId: uom.id };
    
    // Check product-specific rules first
    if (productId) {
        const prodRule = uomConversions.value.find(c => c.product_id === productId && c.from_uom_id === uomId);
        if (prodRule) return { factor: Number(prodRule.conversion_factor), baseId: prodRule.to_uom_id };
    }
    
    // Check global rules
    const globalRule = uomConversions.value.find(c => !c.product_id && c.from_uom_id === uomId);
    if (globalRule) return { factor: Number(globalRule.conversion_factor), baseId: globalRule.to_uom_id };
    
    return { factor: 1, baseId: uom.id };
};

const getScaledQty = (uomId, rawPieces, productId = null) => {
    if (rawPieces === undefined || rawPieces === null) return '0';
    const factor = getFactorToBase(uomId, productId).factor;
    const scaled = (Number(rawPieces) / factor);
    const uom = uoms.value.find(u => u.id === uomId);
    return (uom?.category === 'count')
        ? Math.floor(scaled + 0.0001).toString()
        : scaled.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: uom?.decimals ?? 8 });
};

const toggleStockInfo = async (event, product) => {
    selectedLineForStock.value = product;
    // We need to trigger loadIntelligenceData if it's not the currently selected product
    if (selectedProduct.value?.id !== product.id) {
        selectedProduct.value = product;
        await loadIntelligenceData();
    } else if (!locationBreakdown.value.length) {
        await loadIntelligenceData();
    }
    stockOp.value.toggle(event);
};

const loadMasterData = async () => {
    try {
        const [uomRes, convRes] = await Promise.all([
            axios.get('/api/uom'),
            axios.get('/api/uom-conversions')
        ]);
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
    } catch (e) {
        console.error("Failed to load UOM data", e);
    }
};

const loadProducts = async () => {
    loadingProducts.value = true;
    try {
        const params = { query: search.value };
        if (selectedViewUomId.value) params.target_uom_id = selectedViewUomId.value;
        const res = await axios.get('/api/products', { params });
        products.value = res.data.data;

        // Selection Sync: Ensure the detail view uses the fresh, scaled data from the API
        if (selectedProduct.value) {
            const freshProduct = products.value.find(p => p.id === selectedProduct.value.id);
            if (freshProduct) {
                selectedProduct.value = freshProduct;
                // No need to explicitly reload intelligence here if we have a watch on selectedProduct
            }
        } else if (products.value.length > 0) {
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

const loadHistory = async (page = 1) => {
    if (!selectedProduct.value) return;
    loadingHistory.value = true;
    historyCurrentPage.value = page;
    try {
        const params = {
            page,
            per_page: historyMeta.value.per_page,
        };
        if (historyFilters.value.date_from) params.date_from = historyFilters.value.date_from;
        if (historyFilters.value.date_to) params.date_to = historyFilters.value.date_to;
        if (historyFilters.value.type && historyFilters.value.type !== 'all') params.type = historyFilters.value.type;
        if (selectedViewUomId.value) params.target_uom_id = selectedViewUomId.value;

        const res = await axios.get(`/api/products/${selectedProduct.value.id}/transactions`, { params });
        history.value = res.data.data;
        historyMeta.value = res.data.meta ?? { current_page: 1, last_page: 1, total: res.data.data.length, per_page: 25 };
    } catch (e) {
        console.error(e);
    } finally {
        loadingHistory.value = false;
    }
};

const applyHistoryFilters = () => {
    loadHistory(1);
};

const resetHistoryFilters = () => {
    historyFilters.value = { date_from: null, date_to: null, type: 'all' };
    loadHistory(1);
};

const loadIntelligenceData = async () => {
    if (!selectedProduct.value) return;
    loadingIntelligence.value = true;
    try {
        const params = selectedViewUomId.value ? { target_uom_id: selectedViewUomId.value } : {};
        const [locRes, layerRes] = await Promise.all([
            axios.get(`/api/inventory/${selectedProduct.value.id}/locations`, { params }),
            axios.get(`/api/inventory/${selectedProduct.value.id}/cost-layers`, { params })
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

onMounted(() => {
    loadProducts();
    loadMasterData();
});

const page = usePage();
watch(() => page.url, () => {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('product_id');
    if (productId && products.value.length > 0) {
        selectedProduct.value = products.value.find(p => p.id == productId) || selectedProduct.value;
    }
});

watch(selectedProduct, () => {
    historyFilters.value = { date_from: null, date_to: null, type: 'all' };
    historyCurrentPage.value = 1;
    loadHistory(1);
    loadIntelligenceData();
});

// Refresh all intelligence/history data when the View Unit changes
watch(selectedViewUomId, () => {
    if (selectedProduct.value) {
        loadHistory(1);
        loadIntelligenceData();
    }
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

    if (type === 'SO' && id) {
        router.visit(`/sales-orders/${id}`);
        return;
    }

    if (type === 'Movement' && id) {
        router.visit(`/movements/${id}`);
        return;
    }

    if (type === 'Customer' && id) {
        router.visit(`/customer-center?customer_id=${id}`);
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
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val));
};

const getStockStatusClass = (p) => {
    if (Number(p.total_qoh) === 0) return 'status-danger';
    if (Number(p.total_qoh) < Number(p.reorder_point)) return 'status-warning';
    return 'status-success';
};

const getStockStatusLabel = (p) => {
    if (Number(p.total_qoh) === 0) return 'CRITICAL: ZERO STOCK';
    if (Number(p.total_qoh) < Number(p.reorder_point)) return 'LOW STOCK: REPLENISH';
    return 'STOCK BALANCED';
};

const listboxPt = {
    root: { class: '!p-2 h-full flex flex-col' },
    listContainer: { class: '!max-h-none flex-1 overflow-y-auto custom-scrollbar' },
    item: (options) => ({
        class: [
            '!p-2.5 !mb-1 !rounded-xl !transition-all !duration-300 !border',
            options.context.selected 
                ? '!bg-emerald-500/10 !border-emerald-500/20 !text-primary shadow-[0_0_15px_rgba(16,185,129,0.05)]' 
                : '!bg-transparent !border-transparent !text-secondary hover:!bg-panel-hover/40 hover:!text-primary'
        ]
    })
};

const tablePt = {
    root: { class: '!bg-transparent' },
    bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200' },
    header: { class: '!bg-panel/60 !border-panel-border !text-secondary !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' }
};
</script>

<template>
    <AppLayout>
        <Head title="Inventory Center" />
        <Toast />

        <div class="p-4 bg-deep min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-6 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-[0.2em] block mb-2 font-mono">Stock Monitoring</span>
                    <h1 class="text-3xl font-bold text-primary tracking-tight m-0 mb-2">Inventory Center</h1>
                    <p class="text-secondary text-sm max-w-2xl leading-relaxed">Track current stock levels, movement history, and valuation across all storage locations in the system.</p>
                </div>
                
                <div class="flex gap-4 items-center">
                    <div class="flex items-center gap-3 border border-panel-border/50 bg-panel/40 rounded-xl px-4 h-12">
                        <span class="text-[9px] font-bold text-secondary uppercase tracking-widest font-mono">View Unit</span>
                        <Select 
                            v-model="selectedViewUomId" 
                            :options="filteredUoms" 
                            optionLabel="abbreviation" 
                            optionValue="id" 
                            placeholder="Native"
                            showClear
                            @change="loadProducts"
                            class="!bg-transparent !border-none !h-full !text-[10px] font-mono font-black !w-32 !shadow-none !flex !items-center"
                            pt:label:class="!text-emerald-500 !p-0 !text-left !uppercase font-black !flex !items-center !h-full"
                            pt:dropdown:class="!text-muted !w-4 !flex !items-center"
                        >
                            <template #value="slotProps">
                                <span v-if="slotProps.value" class="text-emerald-500 uppercase font-black">
                                    {{ uoms.find(u => u.id === slotProps.value)?.abbreviation }}
                                </span>
                                <span v-else class="text-emerald-500/50 uppercase font-black">Native</span>
                            </template>
                            <template #option="slotProps">
                                <div class="flex flex-col gap-1 w-full py-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-bold text-zinc-100 uppercase tracking-tight">{{ slotProps.option.abbreviation }}</span>
                                        <span v-if="uomConversions.find(c => c.product_id === selectedProduct?.id && c.from_uom_id === slotProps.option.id)" 
                                              class="text-[8px] px-1.5 py-0.5 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded font-black tracking-widest leading-none">
                                            CUSTOM
                                        </span>
                                    </div>
                                    <div v-if="!slotProps.option.is_base" class="text-[9px] text-secondary font-mono italic">
                                        = {{ getFactorToBase(slotProps.option.id, selectedProduct?.id).factor }} {{ uoms.find(u => u.is_base && u.category === slotProps.option.category)?.abbreviation ?? '???' }}
                                    </div>
                                </div>
                            </template>
                        </Select>
                    </div>
                    <button @click="toggleMenu" 
                            class="bg-emerald-500 hover:bg-emerald-400 text-zinc-950 px-6 h-12 font-bold text-[10px] uppercase tracking-[0.2em] transition-all rounded-xl active:scale-95 shadow-[0_0_20px_rgba(16,185,129,0.2)] flex items-center gap-3">
                        <i class="pi pi-plus text-sm"></i>
                        <span>New Movement</span>
                    </button>
                    <Menu ref="menu" :model="movementOptions" :popup="true" class="!bg-panel !border-panel-border !p-2 !rounded-xl !min-w-[200px]" :pt="{
                        itemlink: { class: 'hover:!bg-panel-hover !rounded-lg !p-3 transition-all' },
                        itemlabel: { class: '!text-[10px] !font-bold !text-secondary !uppercase !tracking-widest' },
                        itemicon: { class: '!text-secondary !text-sm' }
                    }" />
                </div>
            </div>

            <!-- Primary Workspace Grid -->
            <div class="max-w-[1600px] w-full mx-auto grid grid-cols-12 gap-4 items-start flex-1 min-h-0">
                
                <aside class="col-span-12 lg:col-span-3 lg:sticky lg:top-[100px] lg:h-[calc(100vh-120px)] flex flex-col min-h-0 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="p-4 border-b border-panel-border bg-panel/60">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                            <span class="text-[10px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono leading-none">Product List</span>
                        </div>
                        <div class="relative">
                            <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-secondary text-sm"></i>
                            <InputText 
                                v-model="search" 
                                placeholder="Search products..." 
                                @input="loadProducts" 
                                class="!w-full !pl-11 !pr-4 !bg-deep !border-panel-border !text-primary !h-12 !text-xs !rounded-xl focus:!border-emerald-500/30 transition-all font-mono"
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
                                        <span class="text-[9px] font-bold font-mono tracking-widest uppercase" :class="selectedProduct?.id === option.id ? 'text-emerald-400' : 'text-muted'">{{ option.sku }}</span>
                                          <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border leading-none cursor-help hover:border-emerald-500/50 transition-colors" 
                                                @click.stop="toggleStockInfo($event, option)"
                                                :class="[
                                                    Number(option.total_qoh) === 0 ? 'bg-red-500/10 text-red-400 border-red-500/20' : 
                                                    Number(option.total_qoh) < Number(option.reorder_point) ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 
                                                    'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                ]">
                                              {{ option.formatted_total_qoh }}
                                          </span>
                                    </div>
                                    <span class="text-xs font-bold truncate tracking-tight">{{ option.name }}</span>
                                </div>
                            </template>
                        </Listbox>
                    </div>
                </aside>

                <!-- Right Sector: Product History & Insights -->
                <main class="col-span-12 lg:col-span-9 flex flex-col gap-4 min-h-0">
                    
                    <!-- Top Section: Product Details -->
                    <section class="bg-panel/40 border border-panel-border/80 rounded-2xl p-5 backdrop-blur-sm shadow-2xl transition-all duration-500 group overflow-hidden relative">
                        <!-- Background Accent -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 blur-[100px] -mr-32 -mt-32 rounded-full transition-opacity group-hover:opacity-100 opacity-50"></div>
                        
                        <template v-if="selectedProduct">
                            <div class="relative z-10 flex flex-col">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-6 pb-6 border-b border-panel-border/60">
                                    <div class="flex flex-col flex-1">
                                        <div class="flex items-center gap-4 mb-3">
                                            <h1 class="text-3xl font-bold text-primary tracking-tighter m-0">{{ selectedProduct.name }}</h1>
                                            <div class="flex flex-wrap gap-2">
                                                <span class="text-[9px] font-bold px-3 py-1 bg-panel-hover/80 border border-zinc-700 rounded-full text-secondary uppercase tracking-widest font-mono">{{ selectedProduct.category?.name || 'PRODUCT' }}</span>
                                                <span v-if="selectedProduct.preferred_vendor" class="text-[9px] font-bold px-3 py-1 bg-sky-500/10 border border-sky-500/20 rounded-full text-sky-400 uppercase tracking-widest font-mono">OWNED BY: {{ selectedProduct.preferred_vendor.name }}</span>
                                                <span v-else class="text-[9px] font-bold px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full text-amber-400 uppercase tracking-widest font-mono">STOCK: INTERNAL</span>
                                                <span class="text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest font-mono border"
                                                      :class="selectedProduct.is_active !== false ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-panel-hover/80 border-zinc-700 text-secondary'"
                                                >STATUS: {{ selectedProduct.is_active !== false ? 'ACTIVE' : 'INACTIVE' }}</span>
                                            </div>
                                        </div>
                                        <p class="text-secondary text-sm max-w-2xl leading-relaxed italic">{{ selectedProduct.description || 'No description provided for this catalog item.' }}</p>
                                    </div>

                                    <!-- Stock Status Summary -->
                                    <div class="p-4 bg-deep/80 border border-panel-border rounded-2xl flex flex-col items-center justify-center min-w-[180px] shadow-lg"
                                         :class="[
                                             Number(selectedProduct.total_qoh) === 0 ? 'ring-1 ring-red-500/20' : 
                                             Number(selectedProduct.total_qoh) < Number(selectedProduct.reorder_point) ? 'ring-1 ring-amber-500/20' : 
                                             'ring-1 ring-emerald-500/20'
                                         ]">
                                        <span class="text-[9px] font-bold text-secondary uppercase tracking-[0.2em] mb-2 font-mono">Current Stock</span>
                                        <span class="text-4xl font-bold tracking-tighter font-mono cursor-help hover:opacity-80 transition-opacity" 
                                              @click="toggleStockInfo($event, selectedProduct)"
                                              :class="[
                                                  Number(selectedProduct.total_qoh) === 0 ? 'text-red-400' : 
                                                  Number(selectedProduct.total_qoh) < Number(selectedProduct.reorder_point) ? 'text-amber-400' : 
                                                  'text-emerald-400'
                                              ]">
                                            {{ selectedProduct.formatted_total_qoh }}
                                        </span>
                                        <span class="text-[10px] font-bold font-mono mt-2" 
                                              :class="[
                                                  Number(selectedProduct.total_qoh) === 0 ? 'text-red-400/80' : 
                                                  Number(selectedProduct.total_qoh) < Number(selectedProduct.reorder_point) ? 'text-amber-400/80' : 
                                                  'text-emerald-400/80'
                                              ]">
                                            {{ getStockStatusLabel(selectedProduct) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-x-4 gap-y-4">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">SKU</label>
                                        <div class="h-10 bg-deep border border-zinc-900 rounded-lg flex items-center px-4">
                                            <span class="text-secondary font-mono text-xs font-bold">{{ selectedProduct.sku }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Selling Price</label>
                                        <span class="text-primary font-bold text-lg tracking-tight">~ {{ selectedProduct.formatted_selling_price }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2 group/cost cursor-help">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono flex items-center gap-2">
                                            Weighted Avg Cost
                                            <i v-if="showHighPrecision" class="pi pi-shield text-[10px] text-sky-400/50"></i>
                                        </label>
                                        <span v-if="!showHighPrecision" class="text-sky-400 font-bold text-lg tracking-tight">~ {{ selectedProduct.formatted_average_cost }}</span>
                                        <span v-else class="text-sky-300 font-mono font-bold text-lg tracking-tight animate-in fade-in duration-300">{{ selectedProduct.formatted_average_cost_8dp }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Total Stock Value</label>
                                        <span v-if="!showHighPrecision" class="text-amber-400 font-bold text-lg tracking-tight">{{ formatCurrency(selectedProduct.total_stock_value ?? 0) }}</span>
                                        <span v-else class="text-amber-300 font-mono font-bold text-lg tracking-tight animate-in fade-in duration-300">
                                            {{ selectedProduct.formatted_total_stock_value_8dp }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Reorder Point</label>
                                        <span class="text-zinc-300 font-mono font-bold text-lg tracking-tight">{{ getScaledQty(selectedViewUomId || selectedProduct.uom_id, selectedProduct.reorder_point, selectedProduct.id) }}</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Unit of Measure</label>
                                        <span class="text-zinc-300 font-bold uppercase text-xs">
                                            {{ selectedViewUomId ? uoms.find(u => u.id === selectedViewUomId)?.name : selectedProduct.uom?.name }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">Valuation Method</label>
                                        <span class="text-emerald-400 font-mono font-bold text-[11px] bg-emerald-500/5 px-2 py-0.5 rounded border border-emerald-500/10 w-fit uppercase">{{ selectedProduct.costing_method }}</span>
                                    </div>
                                </div>

                                <!-- Quick Actions Toolbar -->
                                <div class="mt-6 pt-6 border-t border-zinc-900 flex items-center gap-6 animate-in fade-in slide-in-from-left-4 duration-1000">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-bold text-muted uppercase tracking-[0.3em] font-mono leading-none mb-1">Actions</span>
                                        <span class="text-[11px] font-bold text-secondary uppercase tracking-tight">Post Movement</span>
                                    </div>
                                    <div class="flex gap-4">
                                        <button @click="router.visit('/movements/receipt?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400 text-[10px] font-bold uppercase tracking-widest hover:bg-sky-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-plus-circle" /> Receipt
                                        </button>
                                        <button @click="router.visit('/movements/issue?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[10px] font-bold uppercase tracking-widest hover:bg-rose-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-minus-circle" /> Issue
                                        </button>
                                        <button @click="router.visit('/movements/transfer?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400 text-[10px] font-bold uppercase tracking-widest hover:bg-violet-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-arrow-right-arrow-left" /> Transfer
                                        </button>
                                        <button @click="router.visit('/movements/adjustment?product_id=' + selectedProduct.id)" 
                                                class="px-6 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[10px] font-bold uppercase tracking-widest hover:bg-amber-500 hover:text-zinc-950 transition-all active:scale-95 flex items-center gap-2">
                                            <i class="pi pi-sliders-h" /> Adjust
                                        </button>
                                        <button @click="openReorderRulesDialog" 
                                                class="px-6 h-11 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-bold uppercase tracking-widest hover:bg-indigo-500 hover:text-primary transition-all active:scale-95 flex items-center gap-2 ml-4 relative">
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
                    <div v-if="selectedProduct" class="grid grid-cols-12 gap-4 animate-in fade-in slide-in-from-bottom-6 duration-700">
                        <!-- Location Distribution Sector -->
                        <aside class="col-span-12 lg:col-span-5 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm flex flex-col min-h-0">
                            <div class="px-8 py-4 border-b border-panel-border bg-panel/60 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <i class="pi pi-map-marker text-emerald-400 text-xs"></i>
                                    <span class="text-[10px] font-bold text-zinc-300 tracking-[0.25em] uppercase font-mono">Storage Breakdown</span>
                                </div>
                                <span class="text-[9px] font-bold text-emerald-500 font-mono tracking-tighter uppercase">Available in these areas</span>
                            </div>
                            <div class="p-4 flex-1">
                                <template v-if="locationBreakdown.length > 0 && locationBreakdown.some(l => Number(l.quantity_on_hand) > 0)">
                                    <div class="space-y-2">
                                        <div v-for="loc in locationBreakdown.filter(l => Number(l.quantity_on_hand) > 0)" :key="loc.id" class="flex items-center justify-between p-3 bg-deep/50 border border-panel-border/60 rounded-xl group hover:border-emerald-500/20 transition-all duration-300">
                                            <div class="flex flex-col">
                                                <span class="text-primary font-bold text-[11px] tracking-tight uppercase">{{ loc.location_name }}</span>
                                                <span class="text-[9px] font-bold text-muted font-mono tracking-widest">{{ loc.location_code }}</span>
                                            </div>
                                            <div class="flex items-end gap-3">
                                                <span class="text-emerald-400 font-mono font-bold text-xs tracking-tighter">{{ loc.formatted_quantity_on_hand }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="h-32 flex flex-col items-center justify-center opacity-30 grayscale border-2 border-dashed border-panel-border rounded-2xl">
                                    <i class="pi pi-map-marker text-3xl mb-3"></i>
                                    <span class="text-[9px] font-bold font-mono tracking-widest uppercase text-muted">No Physical Presence Detected</span>
                                </div>
                            </div>
                        </aside>

                        <!-- Cost Layer Persistence Sector -->
                        <aside class="col-span-12 lg:col-span-7 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm flex flex-col min-h-0">
                            <div class="px-8 py-4 border-b border-panel-border bg-panel/60 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <i class="pi pi-database text-sky-400 text-xs"></i>
                                    <span class="text-[10px] font-bold text-zinc-300 tracking-[0.25em] uppercase font-mono">Inventory Costing ({{ selectedProduct?.costing_method || 'FIFO' }})</span>
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
                                    <Column field="receipt_date" header="Date Received" class="!bg-panel/60 !text-secondary !text-[10px] !uppercase !font-bold" style="width: 140px">
                                        <template #body="{ data }">
                                            <span class="font-mono text-[10px] text-secondary">{{ data.receipt_date }}</span>
                                        </template>
                                    </Column>
                                    <Column header="Location" style="width: 160px">
                                        <template #body="{ data }">
                                            <span class="font-mono text-[10px] text-secondary uppercase tracking-wide">{{ data.location_name }}</span>
                                        </template>
                                    </Column>
                                    <Column field="remaining_qty" header="Remaining" style="width: 140px">
                                        <template #body="{ data }">
                                            <span class="font-mono font-bold text-primary">{{ data.formatted_remaining_qty }}</span>
                                        </template>
                                    </Column>
                                    <Column field="unit_cost" header="Unit Cost" style="width: 130px">
                                        <template #body="{ data }">
                                            <span v-if="!showHighPrecision" class="font-mono font-bold text-sky-400 tracking-tighter text-[11px]">~ {{ data.formatted_unit_cost }}</span>
                                            <span v-else class="font-mono font-bold text-sky-300 tracking-tighter text-[11px] animate-in fade-in duration-300">{{ data.formatted_unit_cost_8dp }}</span>
                                        </template>
                                    </Column>
                                    <Column header="Layer Value" style="width: 150px">
                                        <template #body="{ data }">
                                            <span v-if="!showHighPrecision" class="font-mono font-bold text-amber-400 tracking-tighter text-[11px]">{{ formatCurrency(data.total_value) }}</span>
                                            <span v-else class="font-mono font-bold text-amber-300 tracking-tighter text-[11px] animate-in fade-in duration-300">₱{{ data.total_value_8dp }}</span>
                                        </template>
                                    </Column>
                                    <Column header="Source PO" style="width: 160px">
                                        <template #body="{ data }">
                                            <span v-if="data.po_number"
                                                  @click="handleLinkClick('PO', data.po_number, data.po_id)"
                                                  class="font-mono text-[10px] text-emerald-400 cursor-pointer hover:underline tracking-wider">
                                                {{ data.po_number }}
                                            </span>
                                            <span v-else class="text-muted font-mono text-[10px]">Manual Entry</span>
                                        </template>
                                    </Column>
                                    <Column header="Status" style="width: 100px">
                                        <template #body="{ data }">
                                            <div class="inline-flex items-center gap-2">
                                                <span class="w-1 h-1 rounded-full bg-sky-500 shadow-[0_0_8px_rgba(14,165,233,0.8)]"></span>
                                                <span class="text-[9px] font-bold text-sky-500 uppercase tracking-widest font-mono">{{ Number(data.remaining_qty) > 0 ? 'ACTIVE' : 'DEPLETED' }}</span>
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </aside>
                    </div>

                    <!-- Bottom Section: Transactional Ledger -->
                    <section class="flex-1 min-h-0 bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-2xl flex flex-col backdrop-blur-sm">
                        <div class="px-5 py-4 border-b border-panel-border/60 bg-panel/80 flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></div>
                                <span class="text-[11px] font-bold text-zinc-300 tracking-[0.2em] uppercase font-mono">Transaction History</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-deep border border-panel-border rounded-lg group hover:border-sky-500/30 transition-all cursor-pointer" @click="showHighPrecision = !showHighPrecision">
                                    <div class="w-6 h-3 rounded-full bg-panel-hover relative transition-colors duration-300" :class="showHighPrecision ? 'bg-sky-500/20' : ''">
                                        <div class="absolute top-0.5 left-0.5 w-2 h-2 rounded-full bg-zinc-500 transition-all duration-300 shadow-lg" :class="showHighPrecision ? 'translate-x-3 !bg-sky-400' : ''"></div>
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-widest font-mono transition-colors" :class="showHighPrecision ? 'text-sky-400' : 'text-muted'">Audit Mode</span>
                                </div>
                                <span class="bg-panel-hover/60 text-secondary px-3 py-1 rounded text-[10px] font-bold border border-zinc-700 font-mono tracking-tighter">{{ historyMeta.total }} RECORDS</span>
                            </div>
                        </div>

                        <!-- Filter Bar -->
                        <div class="px-5 py-3 border-b border-panel-border/40 bg-panel/40 flex flex-wrap items-end gap-3">
                            <div class="flex flex-col gap-1">
                                <span class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">From</span>
                                <input
                                    type="date"
                                    v-model="historyFilters.date_from"
                                    class="h-9 px-3 bg-deep border border-panel-border rounded-lg text-zinc-300 text-xs font-mono focus:border-emerald-500/40 focus:outline-none transition-colors"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">To</span>
                                <input
                                    type="date"
                                    v-model="historyFilters.date_to"
                                    class="h-9 px-3 bg-deep border border-panel-border rounded-lg text-zinc-300 text-xs font-mono focus:border-emerald-500/40 focus:outline-none transition-colors"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Type</span>
                                <Select
                                    v-model="historyFilters.type"
                                    :options="historyTypeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="!bg-deep !border-panel-border !h-9 !rounded-lg !text-[11px] font-mono !w-40"
                                    pt:label:class="!text-zinc-300 !p-2 !text-[11px]"
                                    pt:dropdown:class="!text-muted !w-6"
                                />
                            </div>
                            <div class="flex gap-2 pb-0.5">
                                <button @click="applyHistoryFilters"
                                        class="h-9 px-5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-bold uppercase tracking-widest hover:bg-emerald-500 hover:text-zinc-950 transition-all active:scale-95">
                                    Apply
                                </button>
                                <button @click="resetHistoryFilters"
                                        class="h-9 px-4 rounded-lg bg-panel-hover/60 border border-zinc-700 text-secondary text-[10px] font-bold uppercase tracking-widest hover:text-primary hover:border-zinc-500 transition-all">
                                    Reset
                                </button>
                            </div>
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

                                <Column field="transaction_date" header="Date" style="width: 130px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-[11px] text-secondary">{{ data.transaction_date }}</span>
                                    </template>
                                </Column>

                                <Column field="reference_number" header="Reference #" style="width: 180px">
                                    <template #body="{ data }">
                                        <span @click.stop="handleLinkClick('Movement', data.reference_number, data.id)"
                                              class="font-mono text-[11px] bg-deep text-sky-400 px-2 py-0.5 border border-sky-500/10 rounded tracking-widest cursor-pointer hover:bg-sky-500/10 hover:border-sky-500/30 transition-all shadow-[0_0_15px_rgba(56,189,248,0.05)] uppercase">
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

                                <Column field="quantity" header="Change Qty" style="width: 130px">
                                    <template #body="{ data }">
                                        <div class="flex flex-col">
                                            <div class="font-mono font-bold text-xs tracking-tighter"
                                                 :class="data.quantity < 0 ? 'text-rose-400' : 'text-emerald-400'">
                                                {{ data.formatted_quantity }}
                                            </div>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Unit" style="width: 80px">
                                    <template #body="{ data }">
                                        <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border border-panel-border bg-deep text-secondary uppercase tracking-widest">
                                            {{ data.uom_abbreviation }}
                                        </span>
                                    </template>
                                </Column>

                                <Column header="Unit Cost" style="width: 140px">
                                    <template #body="{ data }">
                                        <span v-if="!showHighPrecision" class="font-mono text-[11px] font-bold text-sky-400">
                                            {{ data.formatted_unit_cost }}
                                        </span>
                                        <span v-else class="font-mono text-[11px] font-bold text-sky-300 animate-in fade-in duration-300">
                                            {{ data.formatted_unit_cost_8dp }}
                                        </span>
                                    </template>
                                </Column>

                                <Column header="Total Value" style="width: 150px">
                                    <template #body="{ data }">
                                        <span v-if="!showHighPrecision" class="font-mono text-[11px] font-bold"
                                              :class="data.quantity < 0 ? 'text-rose-400' : 'text-amber-400'">
                                            {{ data.total_cost > 0 ? formatCurrency(data.total_cost) : '—' }}
                                        </span>
                                        <span v-else class="font-mono text-[11px] font-bold animate-in fade-in duration-300"
                                              :class="data.quantity < 0 ? 'text-rose-300' : 'text-amber-300'">
                                            {{ data.total_cost > 0 ? '₱' + data.total_cost_8dp : '—' }}
                                        </span>
                                    </template>
                                </Column>

                                <Column header="Entity">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-3">
                                            <div v-if="data.vendor_name" @click.stop="handleLinkClick('Vendor', data.vendor_name, data.vendor_id)" class="text-sky-400 cursor-pointer hover:underline flex items-center gap-2 font-bold text-xs tracking-tight">
                                                <i class="pi pi-building text-[10px]"></i> {{ data.vendor_name }}
                                            </div>
                                            <div v-else-if="data.customer_name" @click.stop="handleLinkClick('Customer', data.customer_name, data.customer_id)" class="text-amber-400 cursor-pointer hover:underline flex items-center gap-2 font-bold text-xs tracking-tight">
                                                <i class="pi pi-user text-[10px]"></i> {{ data.customer_name }}
                                            </div>
                                            <span v-else class="text-[10px] font-bold text-muted uppercase tracking-widest font-mono">internal</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Linked Doc" style="width: 180px">
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
                                        <span v-else-if="data.reference_doc" class="text-muted font-mono text-[10px] uppercase truncate max-w-[140px]">{{ data.reference_doc }}</span>
                                        <span v-else class="text-zinc-800 font-mono text-[11px]">Internal</span>
                                    </template>
                                </Column>

                                <Column field="status" header="Status" style="width: 110px">
                                     <template #body="{ data }">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="data.status.name.toLowerCase() === 'posted' ? 'bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]' : 'bg-zinc-700'"></span>
                                            <span class="text-[10px] font-bold tracking-widest font-mono" :class="data.status.name.toLowerCase() === 'posted' ? 'text-primary' : 'text-muted'">{{ data.status.name.toUpperCase() }}</span>
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Paginator -->
                        <div v-if="historyMeta.last_page > 1" class="px-8 py-4 border-t border-panel-border/40 bg-panel/60 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-muted font-mono uppercase tracking-widest">
                                Page {{ historyMeta.current_page }} of {{ historyMeta.last_page }} &nbsp;·&nbsp; {{ historyMeta.total }} records
                            </span>
                            <div class="flex gap-2">
                                <button
                                    @click="loadHistory(historyMeta.current_page - 1)"
                                    :disabled="historyMeta.current_page <= 1"
                                    class="h-8 px-4 rounded-lg bg-panel-hover border border-zinc-700 text-secondary text-[10px] font-bold uppercase tracking-widest hover:border-zinc-500 hover:text-primary transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                                ><i class="pi pi-chevron-left text-[9px]"></i> Prev</button>
                                <button
                                    @click="loadHistory(historyMeta.current_page + 1)"
                                    :disabled="historyMeta.current_page >= historyMeta.last_page"
                                    class="h-8 px-4 rounded-lg bg-panel-hover border border-zinc-700 text-secondary text-[10px] font-bold uppercase tracking-widest hover:border-zinc-500 hover:text-primary transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                                >Next <i class="pi pi-chevron-right text-[9px]"></i></button>
                            </div>
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
                root: { class: '!bg-deep !border !border-panel-border' },
                header: { class: '!bg-panel !text-primary !border-b !border-panel-border !p-6' },
                content: { class: '!p-0 !bg-deep' }
            }"
            @show="prepareNewRule"
        >
            <div class="flex flex-col md:flex-row shadow-inner min-h-[500px]">
                <!-- Rules List -->
                <div class="w-full md:w-1/2 border-r border-panel-border bg-panel/30 flex flex-col p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-bold text-primary uppercase tracking-widest font-mono m-0">Active Rules</h3>
                        <button @click="prepareNewRule" class="text-[10px] font-bold text-emerald-400 hover:text-emerald-300 uppercase tracking-widest"><i class="pi pi-plus" /> New</button>
                    </div>
                    
                    <div v-if="loadingRules" class="flex-1 flex justify-center items-center">
                        <i class="pi pi-spin pi-spinner text-secondary text-2xl"></i>
                    </div>
                    
                    <div v-else-if="reorderRules.length === 0" class="flex-1 flex flex-col items-center justify-center opacity-50 grayscale">
                        <i class="pi pi-sitemap text-3xl mb-4"></i>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] font-mono text-secondary text-center">No rules configured.<br/>Engine will ignore this product.</span>
                    </div>

                    <div v-else class="flex-1 space-y-3 overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="rule in reorderRules" :key="rule.id" 
                             @click="editRule(rule)"
                             class="p-4 rounded-xl border cursor-pointer transition-all group"
                             :class="ruleForm.id === rule.id ? 'bg-indigo-500/10 border-indigo-500/30' : 'bg-deep border-panel-border hover:border-zinc-700'">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-primary truncate max-w-[150px]">{{ rule.location_name }}</span>
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded text-primary" :class="rule.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-panel-hover text-secondary'">{{ rule.is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-muted font-bold uppercase font-mono">Min Stock</span>
                                    <span class="text-amber-400 font-bold font-mono text-xs">{{ rule.min_stock }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-muted font-bold uppercase font-mono">Restock</span>
                                    <span class="text-emerald-400 font-bold font-mono text-xs">{{ rule.reorder_qty }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="w-full md:w-1/2 p-8 flex flex-col bg-deep">
                    <h3 class="text-sm font-bold text-primary uppercase tracking-widest font-mono mb-8 m-0">{{ ruleForm.id ? 'Edit Rule' : 'Create Rule' }}</h3>
                    
                    <div class="space-y-6 flex-1">
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Location <span class="text-muted italic lowercase">(leave blank for global root rule)</span></label>
                            <Select v-model="ruleForm.location_id" :options="locationBreakdown" optionLabel="location_name" optionValue="location_id" 
                                    placeholder="Global Defaults (All Locations)" showClear 
                                    class="!bg-panel !border-panel-border text-primary w-full" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Min Stock (Trigger)</label>
                                <InputNumber v-model="ruleForm.min_stock" :minFractionDigits="0" :maxFractionDigits="selectedProduct ? (isUomIdDiscrete(selectedProduct.uom_id) ? 0 : 8) : 0" inputClass="!bg-panel !border-panel-border !text-primary !w-full !font-mono" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Restock Amount</label>
                                <InputNumber v-model="ruleForm.reorder_qty" :minFractionDigits="0" :maxFractionDigits="selectedProduct ? (isUomIdDiscrete(selectedProduct.uom_id) ? 0 : 8) : 0" inputClass="!bg-panel !border-panel-border !text-primary !w-full !font-mono" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Max Stock Limit (Optional)</label>
                            <InputNumber v-model="ruleForm.max_stock" :minFractionDigits="0" :maxFractionDigits="selectedProduct ? (isUomIdDiscrete(selectedProduct.uom_id) ? 0 : 8) : 0" inputClass="!bg-panel !border-panel-border !text-primary !w-full !font-mono" placeholder="No limit" />
                        </div>

                        <div class="flex items-center justify-between p-4 border border-panel-border bg-panel/50 rounded-lg">
                            <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Status</span>
                            <ToggleSwitch v-model="ruleForm.is_active" />
                        </div>
                    </div>

                    <div class="pt-8 mt-8 border-t border-panel-border flex justify-between items-center gap-4">
                        <button v-if="ruleForm.id" @click="deleteRule(ruleForm.id)" class="px-5 h-12 rounded-lg text-[11px] font-bold uppercase tracking-widest text-red-500 hover:bg-red-500/10 transition-colors border border-transparent hover:border-red-500/20">Delete</button>
                        <div class="flex-1"></div>
                        <button @click="prepareNewRule" class="bg-transparent border border-panel-border text-secondary hover:text-primary hover:border-zinc-600 px-8 h-12 rounded-lg font-bold text-[11px] uppercase tracking-widest transition-colors flex items-center justify-center">Clear</button>
                        <button @click="saveRule" :disabled="savingRule" class="bg-emerald-500 border-none text-zinc-950 px-10 h-12 rounded-lg font-bold text-[11px] uppercase tracking-widest hover:bg-emerald-400 active:scale-95 shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center min-w-[140px]">
                            <i v-if="savingRule" class="pi pi-spin pi-spinner mr-2"></i>
                            {{ savingRule ? 'SAVING...' : 'SAVE RULE' }}
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Scattered Stock Breakdown Popover -->
        <Popover ref="stockOp" class="!bg-panel !border-panel-border !shadow-2xl">
            <div v-if="selectedLineForStock" class="w-72 p-4 text-primary text-left">
                <div class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mb-3 border-b border-panel-border pb-2 flex justify-between">
                    <span>Location Breakdown</span>
                    <span>{{ uoms.find(u => u.id == (selectedViewUomId || selectedLineForStock.uom_id))?.abbreviation }}</span>
                </div>
                <div class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar">
                    <div v-for="loc in locationBreakdown" :key="loc.location_id" class="flex justify-between items-center text-[10px]">
                        <span class="text-secondary truncate pr-2 uppercase font-bold">
                            {{ loc.location_name }}
                        </span>
                        <span class="font-mono text-primary">
                            {{ getScaledQty(selectedLineForStock.uom_id, loc.quantity_on_hand, selectedLineForStock.id) }}
                        </span>
                    </div>
                    <div v-if="!locationBreakdown.length" class="text-center py-2 text-muted text-[10px] italic">
                        No stock available in any location
                    </div>
                </div>
                <div class="mt-3 pt-2 border-t border-panel-border flex justify-between items-center font-mono text-[9px]">
                    <span class="font-bold text-muted uppercase italic">Total Global Stock</span>
                    <span class="font-black text-primary px-2 py-0.5 bg-panel rounded">
                        {{ getScaledQty(selectedLineForStock.uom_id, selectedLineForStock.total_qoh, selectedLineForStock.id) }}
                    </span>
                </div>
            </div>
        </Popover>
    </AppLayout>
</template>

<style scoped>
/* Scoped styles migrated to Tailwind Utility Classes v4 */
</style>


