<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import Popover from 'primevue/popover';
import axios from 'axios';
import { useDebounceFn } from '@vueuse/core';

const { can } = usePermissions();
const page = usePage();
const toast = useToast();
const confirm = useConfirm();

const products = ref([]);
const categories = ref([]);
const uoms = ref([]);
const costingMethods = ref([]);
const vendors = ref([]);
const allConversions = ref([]); // Global conversions cache

const loading = ref(true);
const search = ref('');
const dialogVisible = ref(false);
const submitted = ref(false);
const isEditing = ref(false);
const saving = ref(false);

// Form tabs for better organization
const activeTab = ref('basic');

const product = ref({
    id: null,
    product_code: '',
    name: '',
    description: '',
    sku: '',
    barcode: '',
    brand: '',
    category_id: null,
    uom_id: null,
    costing_method_id: null,
    preferred_vendor_id: null,
    selling_price: 0,
    reorder_point: 0,
    reorder_quantity: 0,
    is_active: true,
    image: null
});

const imagePreview = ref(null);
const imageFile = ref(null);
const imageUploading = ref(false);

const productConversions = ref([]);
const loadingConversions = ref(false);
const newRule = ref({ from_uom_id: null, to_uom_id: null, conversion_factor: null });
const addingRule = ref(false);

// Validation errors
const errors = ref({});

// Debounced search for better performance
const debouncedSearch = useDebounceFn(() => {
    loadProducts();
}, 300);

watch(search, () => {
    debouncedSearch();
});

watch(() => product.value.uom_id, (newVal) => {
    if (newVal) {
        const uom = uoms.value.find(u => u.id === newVal);
        if (uom && !uom.is_base) {
            const base = uoms.value.find(b => b.category === uom.category && b.is_base);
            product.value.initial_to_uom_id = base ? base.id : null;
            product.value.initial_conversion_factor = null;
        } else {
            product.value.initial_to_uom_id = null;
            product.value.initial_conversion_factor = null;
        }
    }
});

watch(activeTab, (val) => {
    if (val === 'packaging' && product.value.id) {
        loadProductConversions();
    }
});

const loadProductConversions = async () => {
    if (!product.value.id) return;
    loadingConversions.value = true;
    try {
        const res = await axios.get('/api/uom-conversions', { params: { product_id: product.value.id } });
        productConversions.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load packaging rules.', life: 3000 });
    } finally {
        loadingConversions.value = false;
    }
};

const saveProductConversion = async () => {
    if (!newRule.value.from_uom_id || !newRule.value.to_uom_id || !newRule.value.conversion_factor) {
        toast.add({ severity: 'warn', summary: 'Missing Info', detail: 'All fields are required.', life: 3000 });
        return;
    }
    try {
        await axios.post('/api/uom-conversions', {
            ...newRule.value,
            product_id: product.value.id
        });
        toast.add({ severity: 'success', summary: 'Added', detail: 'Packaging rule created.', life: 3000 });
        newRule.value = { from_uom_id: null, to_uom_id: null, conversion_factor: null };
        addingRule.value = false;
        
        // Refresh both local and global lists to sync the UI badges
        loadProductConversions();
        loadMetadata(); 
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to add rule', life: 4000 });
    }
};

const deleteProductConversion = async (id) => {
    try {
        await axios.delete(`/api/uom-conversions/${id}`);
        toast.add({ severity: 'success', summary: 'Deleted', detail: 'Packaging rule removed.', life: 3000 });
        loadProductConversions();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete rule', life: 3000 });
    }
};

const loadProducts = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/products', {
            params: { query: search.value }
        });
        products.value = response.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load products', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const loadMetadata = async () => {
    try {
        const [catRes, uomRes, costRes, venRes] = await Promise.all([
            axios.get('/api/categories'),
            axios.get('/api/uom'),
            axios.get('/api/costing-methods'),
            axios.get('/api/vendors')
        ]);
        categories.value = catRes.data.data;
        uoms.value = uomRes.data.data;
        costingMethods.value = costRes.data.data;
        vendors.value = venRes.data.data;
        
        // Also load global conversions to check for unit scalability
        const convRes = await axios.get('/api/uom-conversions');
        allConversions.value = convRes.data.data;
    } catch (e) {
        console.error("Metadata load error", e);
    }
};

const stockOp = ref(null);
const selectedProductForStock = ref(null);

const toggleStock = (event, product) => {
    selectedProductForStock.value = product;
    stockOp.value.toggle(event);
};

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id == id);
    return uom ? uom.category === 'count' : true;
};

const getFactorToBase = (uomId, productId = null) => {
    const uom = uoms.value.find(u => u.id == uomId);
    if (!uom) return { factor: 1, baseId: uomId };
    if (uom.is_base) return { factor: 1, baseId: uom.id };
    
    // Check product-specific rules first
    if (productId) {
        const prodRule = allConversions.value.find(c => c.product_id === productId && c.from_uom_id === uomId);
        if (prodRule) return { factor: parseFloat(prodRule.conversion_factor), baseId: prodRule.to_uom_id };
    }
    
    // Check global rules
    const globalRule = allConversions.value.find(c => !c.product_id && c.from_uom_id === uomId);
    if (globalRule) return { factor: parseFloat(globalRule.conversion_factor), baseId: globalRule.to_uom_id };
    
    return { factor: 1, baseId: uom.id };
};

const getScaledQty = (productObj, rawPieces) => {
    if (!productObj || rawPieces === undefined || rawPieces === null) return '0';
    const factor = getFactorToBase(productObj.uom_id, productObj.id).factor;
    const scaled = (parseFloat(rawPieces) / factor);
    return isUomIdDiscrete(productObj.uom_id) 
        ? Math.floor(scaled + 0.0001).toString() 
        : scaled.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 8 });
};

// Final stock quantities used for reorder logic in the grid

onMounted(() => {
    loadProducts();
    loadMetadata();
});

const resetForm = () => {
    product.value = {
        id: null,
        product_code: '',
        name: '',
        description: '',
        sku: '',
        barcode: '',
        brand: '',
        category_id: null,
        uom_id: null,
        costing_method_id: costingMethods.value.find(m => m.name === 'average')?.id || null,
        preferred_vendor_id: null,
        selling_price: 0,
        reorder_point: 0,
        reorder_quantity: 0,
        average_cost: 0,
        is_active: true,
        image: null,
        initial_conversion_factor: null,
        initial_to_uom_id: null
    };
    imagePreview.value = null;
    imageFile.value = null;
    errors.value = {};
    submitted.value = false;
    isEditing.value = false;
    addingRule.value = false;
    productConversions.value = [];
    activeTab.value = 'basic';
};

const openNew = () => {
    resetForm();
    dialogVisible.value = true;
};

const editProduct = (p) => {
    resetForm();
    isEditing.value = true;
    product.value = { 
        ...p, 
        category_id: p.category_id, 
        uom_id: p.uom_id,
        costing_method_id: p.costing_method_id,
        preferred_vendor_id: p.preferred_vendor_id
    };
    imagePreview.value = p.main_image_url;
    dialogVisible.value = true;
};

const onImageSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate image type and size
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            toast.add({ severity: 'error', summary: 'Invalid Format', detail: 'Please upload JPEG, PNG, or WebP images only', life: 3000 });
            return;
        }
        
        if (file.size > maxSize) {
            toast.add({ severity: 'error', summary: 'File Too Large', detail: 'Maximum file size is 5MB', life: 3000 });
            return;
        }
        
        imageFile.value = file;
        product.value.image = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeImage = () => {
    imagePreview.value = null;
    imageFile.value = null;
    product.value.image = null;
};

const validateForm = () => {
    const newErrors = {};
    
    if (!product.value.name?.trim()) newErrors.name = 'Product name is mandatory.';
    
    // Only require these if not in a "new product" context where they auto-generate
    // But for simplicity, we allow them to be blank in frontend if user wants auto-gen
    // and let the backend handle the final validation or generation.
    // However, if the user explicitly enters something invalid, we catch it here.
    
    if (!product.value.category_id) newErrors.category_id = 'A product category must be selected.';
    if (!product.value.uom_id) newErrors.uom_id = 'Unit of measure is required.';
    
    // Strict Packaging Validation: If non-base UOM is selected and no rule exists in the system, block save.
    if (isNonBaseUOMMissingRule.value) {
        const uomName = uoms.value.find(u => u.id === product.value.uom_id)?.name || 'this unit';
        if (!product.value.id) {
            // New logic: If the user hasn't provided an initial factor, block it.
            if (!product.value.initial_conversion_factor) {
                newErrors.packaging = `No global rule found for ${uomName}. Please define the conversion factor (1 ${uomName} = ? pieces) in the Packaging tab.`;
            }
        } else {
            newErrors.packaging = `A conversion rule (e.g. 1 ${uomName} = X base units) must be defined in the Packaging tab.`;
        }
    }

    if (!product.value.costing_method_id) newErrors.costing_method_id = 'Costing method must be defined.';
    if (product.value.selling_price < 0) newErrors.selling_price = 'Price cannot be negative.';
    if (product.value.reorder_point < 0) newErrors.reorder_point = 'Reorder point cannot be negative.';
    
    errors.value = newErrors;
    return Object.keys(newErrors).length === 0;
};

const saveProduct = async () => {
    submitted.value = true;
    
    if (!validateForm()) {
        const firstError = Object.keys(errors.value)[0];
        
        // Proper notification for frontend blocking
        if (errors.value.packaging) {
            toast.add({ 
                severity: 'warn', 
                summary: 'Packaging Required', 
                detail: errors.value.packaging, 
                life: 6000 
            });
            activeTab.value = 'packaging';
        } else {
            toast.add({ 
                severity: 'warn', 
                summary: 'Wait!', 
                detail: 'Please fix the highlighted errors before saving.', 
                life: 3000 
            });
            
            // Auto-switch tabs based on where the first error is
            const inventoryFields = ['selling_price', 'reorder_point', 'reorder_quantity', 'costing_method_id'];
            if (inventoryFields.includes(firstError)) {
                activeTab.value = 'inventory';
            } else if (firstError === 'image') {
                activeTab.value = 'media';
            } else {
                activeTab.value = 'basic';
            }
        }

        // Scroll to first error
        if (firstError) {
            const element = document.querySelector(`[data-field="${firstError}"]`);
            if (element) element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    saving.value = true;

    const formData = new FormData();
    Object.keys(product.value).forEach(key => {
        if (key === 'image' || key === 'main_image_url') return;
        
        let val = product.value[key];
        // Handle boolean values for Laravel's boolean validation
        if (typeof val === 'boolean') {
            val = val ? '1' : '0';
        }
        formData.append(key, val === null || val === undefined ? '' : val);
    });
    
    if (imageFile.value) {
        formData.append('image', imageFile.value);
    }

    if (product.value.id) {
        formData.append('_method', 'PUT');
    }

    try {
        const url = product.value.id ? `/api/products/${product.value.id}` : '/api/products';
        await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        toast.add({ 
            severity: 'success', 
            summary: 'Success', 
            detail: product.value.id ? 'Product updated successfully' : 'Product created successfully', 
            life: 3000,
            icon: 'pi pi-check-circle'
        });
        dialogVisible.value = false;
        loadProducts();
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save product';
        const fieldErrors = e.response?.data?.errors;
        
        if (fieldErrors) {
            errors.value = fieldErrors;
            const firstErrField = Object.keys(fieldErrors)[0];
            const firstErrMsg = fieldErrors[firstErrField][0];
            const firstErrMsgLower = firstErrMsg.toLowerCase();
            toast.add({ severity: 'error', summary: 'Validation Error', detail: `${firstErrMsg}`, life: 5000 });
            
            // Auto-switch tab if error is in another one
            const inventoryFields = ['selling_price', 'reorder_point', 'reorder_quantity', 'costing_method_id'];
            
            if (inventoryFields.includes(firstErrField)) {
                activeTab.value = 'inventory';
            } else if (firstErrField === 'image') {
                activeTab.value = 'media';
            } else if (firstErrField === 'packaging' || firstErrField === 'initial_conversion_factor' || firstErrMsgLower.includes('conversion rule')) {
                activeTab.value = 'packaging';
            } else {
                activeTab.value = 'basic';
            }
        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 });
        }
    } finally {
        saving.value = false;
    }
};

const deleteProduct = (p) => {
    confirm.require({
        message: `Are you sure you want to delete "${p.name}"? This will soft-delete the record.`,
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/products/${p.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Product has been removed', life: 3000 });
                loadProducts();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Deletion failed', life: 3000 });
            }
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value || 0);
};

const getStatusSeverity = (isActive) => isActive ? 'success' : 'secondary';
const getStatusLabel = (isActive) => isActive ? 'Active' : 'Inactive';

// Navigate to Inventory Center pre-selecting this product
const goToInventory = (product) => {
    router.visit(`/inventory-center?product_id=${product.id}`);
};

// Quick stats
const stats = computed(() => ({
    total: products.value.length,
    active: products.value.filter(p => p.is_active).length,
    totalValue: products.value.reduce((sum, p) => sum + (p.selling_price || 0), 0)
}));

const isNonBaseUOMMissingRule = computed(() => {
    if (!product.value.uom_id) return false;
    const uom = uoms.value.find(u => u.id === product.value.uom_id);
    if (!uom || uom.is_base) return false;

    // A rule is missing if there is no global rule FOR THIS UOM 
    // AND no product-specific rule for THIS product.
    // AND no initial factor provided in the current form.
    const hasRule = allConversions.value.some(c => 
        c.from_uom_id === uom.id && 
        (!c.product_id || c.product_id === product.value.id)
    );

    return !hasRule && !product.value.initial_conversion_factor;
});

const getBaseUomForSelected = computed(() => {
    if (!product.value.uom_id) return null;
    const uom = uoms.value.find(u => u.id === product.value.uom_id);
    if (!uom) return null;
    return uoms.value.find(b => b.category === uom.category && b.is_base);
});
</script>

<template>
    <AppLayout>
        <Head title="Product Catalog" />
        
        <div class="p-8 bg-zinc-950 min-h-screen">
            <!-- Header Section -->
            <div class="max-w-[1600px] mx-auto flex flex-col lg:flex-row justify-between items-start lg:items-end mb-10 gap-8">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-sky-400 uppercase tracking-[0.2em] block mb-2 font-mono">Product Inventory Management</span>
                    <h1 class="text-3xl font-bold text-white tracking-tight m-0 mb-2">Product Catalog</h1>
                    <p class="text-zinc-500 text-sm max-w-2xl leading-relaxed">Manage and organize all products in your system catalog. Track classifications, pricing, and inventory parameters across the organization.</p>
                </div>
                
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full lg:w-auto">
                    <div class="relative flex-1 sm:w-80">
                        <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 text-sm"></i>
                        <InputText 
                            v-model="search" 
                            placeholder="Search by name, SKU or code..." 
                            class="w-full !pl-11 !pr-10 !bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !text-sm focus:!border-sky-500/30 !rounded-lg transition-all"
                        />
                        <i v-if="search" class="pi pi-times absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer text-xs text-zinc-600 hover:text-zinc-400 transition-colors" @click="search = ''"></i>
                    </div>
                    <Button 
                        v-if="can('manage-products')" 
                        label="ADD PRODUCT" 
                        icon="pi pi-plus" 
                        class="!bg-sky-500 !text-white !border-none !h-12 !font-bold !px-6 !rounded-lg hover:!bg-sky-400 active:scale-95 transition-all shadow-lg shadow-sky-500/10"
                        @click="openNew" 
                    />
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="max-w-[1600px] mx-auto grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-zinc-900/40 border border-zinc-800/60 rounded-xl p-5 flex items-center gap-5 group hover:border-sky-500/20 transition-all duration-500 shadow-sm hover:shadow-sky-500/5">
                    <div class="w-14 h-14 rounded-lg bg-zinc-950 border border-zinc-800 flex items-center justify-center text-sky-400 group-hover:scale-105 transition-transform duration-500">
                        <i class="pi pi-box text-xl"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold text-white tracking-tight leading-none">{{ stats.total }}</span>
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-2 font-mono">Total Products</span>
                    </div>
                </div>
                <div class="bg-zinc-900/40 border border-zinc-800/60 rounded-xl p-5 flex items-center gap-5 group hover:border-emerald-500/20 transition-all duration-500 shadow-sm hover:shadow-emerald-500/5">
                    <div class="w-14 h-14 rounded-lg bg-zinc-950 border border-zinc-800 flex items-center justify-center text-emerald-400 group-hover:scale-105 transition-transform duration-500">
                        <i class="pi pi-check-circle text-xl"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold text-white tracking-tight leading-none">{{ stats.active }}</span>
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-2 font-mono">Available Items</span>
                    </div>
                </div>
                <div class="bg-zinc-900/40 border border-zinc-800/60 rounded-xl p-5 flex items-center gap-5 group hover:border-amber-500/20 transition-all duration-500 shadow-sm hover:shadow-amber-500/5">
                    <div class="w-14 h-14 rounded-lg bg-zinc-950 border border-zinc-800 flex items-center justify-center text-amber-400 group-hover:scale-105 transition-transform duration-500">
                        <i class="pi pi-dollar text-xl"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold text-white tracking-tight leading-none">{{ formatCurrency(stats.totalValue) }}</span>
                        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-2 font-mono">Total Catalog Value</span>
                    </div>
                </div>
            </div>

            <!-- Central Registry Table -->
            <div class="max-w-[1600px] mx-auto bg-zinc-900/50 border border-zinc-800/80 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
                <div class="px-6 py-4 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/80">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-sky-500"></div>
                        <span class="text-[11px] font-bold text-zinc-300 tracking-[0.15em] uppercase">All Registered Items</span>
                    </div>
                    <span class="bg-zinc-800/50 text-zinc-400 px-3 py-1 rounded-md text-[10px] font-bold border border-zinc-700 font-mono tracking-tighter">{{ products.length }} PRODUCTS</span>
                </div>
                
                <DataTable 
                    :value="products" 
                    :loading="loading" 
                    responsiveLayout="scroll" 
                    :paginator="true" 
                    :rows="10"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageSelect"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}"
                    class="gh-table"
                    @row-click="(e) => goToInventory(e.data)"
                    :pt="{
                        column: {
                            headercell: { class: '!bg-zinc-900/90 !border-zinc-800 !text-zinc-300 !text-[11px] !uppercase !font-bold !tracking-[0.1em] !py-4 !px-6' },
                            bodycell: { class: '!border-zinc-800/40 !py-4 !px-6 !text-[13px] !text-zinc-300' }
                        },
                        bodyrow: { class: 'hover:!bg-white/[0.02] !transition-all duration-200 cursor-pointer' },
                        paginator: {
                            root: { class: '!bg-zinc-900/80 !border-t !border-zinc-800 !py-3' },
                            pagelink: ({ props, state, context }) => ({
                                class: context.active ? '!bg-sky-500 !text-white !rounded-md' : '!text-zinc-500 hover:!text-zinc-200'
                            })
                        }
                    }"
                >
                    <template #empty>
                        <div class="py-24 text-center flex flex-col items-center justify-center opacity-30">
                            <i class="pi pi-database text-5xl mb-6"></i>
                            <p class="font-mono text-xs tracking-[0.2em] uppercase">No product records found in the system</p>
                        </div>
                    </template>

                    <Column field="main_image_url" header="Media" style="width: 80px">
                        <template #body="{ data }">
                            <div class="w-12 h-12 bg-zinc-950 border border-zinc-800 rounded-lg flex items-center justify-center overflow-hidden group/img transition-all hover:border-sky-500/40 cursor-zoom-in">
                                <img v-if="data.main_image_url" :src="data.main_image_url" class="w-full h-full object-cover group-hover/img:scale-110 transition-transform duration-500" />
                                <i v-else class="pi pi-image text-zinc-800 text-xl"></i>
                            </div>
                        </template>
                    </Column>

                    <Column field="product_code" header="Part Number (MPN)" style="width: 140px">
                        <template #body="{ data }">
                            <span class="font-mono text-[10px] bg-zinc-950 text-sky-400 px-3 py-1 rounded border border-sky-500/20 shadow-[0_0_15px_rgba(56,189,248,0.05)]">{{ data.product_code || '---' }}</span>
                        </template>
                    </Column>

                    <Column field="sku" header="SKU" style="width: 160px">
                        <template #body="{ data }">
                            <span class="font-mono text-[11px] text-zinc-500 tracking-widest uppercase">{{ data.sku }}</span>
                        </template>
                    </Column>

                    <Column field="name" header="Product Name">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-bold text-zinc-100 truncate max-w-[280px] tracking-tight">{{ data.name }}</span>
                                <span v-if="data.brand" class="text-[9px] font-bold text-zinc-600 uppercase tracking-[0.1em] font-mono leading-none">{{ data.brand }}</span>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="category" header="Category" style="width: 140px">
                        <template #body="{ data }">
                            <span class="text-[9px] font-bold px-3 py-1 bg-sky-500/5 border border-sky-500/20 rounded-full text-sky-400/80 uppercase tracking-widest font-mono">
                                {{ data.category?.name || 'Unclassified' }}
                            </span>
                        </template>
                    </Column>

                    <Column field="owner" header="Ownership" style="width: 180px">
                        <template #body="{ data }">
                            <div v-if="data.preferred_vendor" class="flex flex-col">
                                <span class="text-[10px] font-bold text-zinc-200 tracking-tight">{{ data.preferred_vendor.name }}</span>
                                <span class="text-[8px] font-bold text-zinc-600 uppercase tracking-widest font-mono">{{ data.preferred_vendor.vendor_code }}</span>
                            </div>
                            <span v-else class="text-[9px] font-bold px-3 py-1 bg-zinc-800/50 border border-zinc-700/50 rounded text-zinc-500 uppercase tracking-widest font-mono">
                                INTERNAL
                            </span>
                        </template>
                    </Column>
                    
                    <Column field="selling_price" header="Price" style="width: 130px">
                        <template #body="{ data }">
                            <span class="font-mono font-bold text-zinc-200 text-sm">{{ data.formatted_selling_price }}</span>
                        </template>
                    </Column>

                    <Column header="Stock Level" style="width: 15rem">
                        <template #body="{ data }">
                            <div class="flex flex-col items-start gap-1" @click.stop>
                                <div class="flex items-center gap-2 cursor-help group/stock" @click="toggleStock($event, data)">
                                    <div class="px-2 py-0.5 rounded bg-zinc-950 border border-zinc-800 flex items-center gap-1.5 transition-all group-hover/stock:border-sky-500/30">
                                        <div class="w-1.5 h-1.5 rounded-full animate-pulse" :class="(data.total_qoh || 0) > (data.reorder_point || 0) ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.4)]'"></div>
                                        <span class="text-[11px] font-mono font-bold tracking-tight" :class="(data.total_qoh || 0) > (data.reorder_point || 0) ? 'text-zinc-100' : 'text-amber-400'">
                                            {{ data.formatted_total_qoh }}
                                        </span>
                                    </div>
                                    <i class="pi pi-info-circle text-[10px] text-zinc-700 group-hover/stock:text-sky-500/50 transition-colors"></i>
                                </div>
                                <div v-if="(data.total_qoh || 0) <= (data.reorder_point || 0)" class="flex items-center gap-1">
                                    <i class="pi pi-exclamation-triangle text-[8px] text-amber-600"></i>
                                    <span class="text-[8px] font-bold text-amber-600/80 uppercase tracking-tighter">Below Reorder Point</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Status" style="width: 150px">
                        <template #body="{ data }">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[9px] font-bold tracking-widest transition-all font-mono"
                                 :class="data.is_active ? 'bg-emerald-500/5 text-emerald-400 border-emerald-500/20 shadow-[0_0_10px_rgba(52,211,153,0.05)]' : 'bg-zinc-800/50 text-zinc-600 border-zinc-700/50'">
                                <span class="w-1.5 h-1.5 rounded-full" :class="data.is_active ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]' : 'bg-zinc-700'"></span>
                                {{ data.is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </div>
                        </template>
                    </Column>
                    
                    <Column header="Actions" style="width: 100px" v-if="can('manage-products')">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2" @click.stop>
                                <Button icon="pi pi-pencil" class="!text-zinc-500 hover:!text-sky-400 hover:!bg-sky-500/10 !w-9 !h-9 !p-0 !rounded-lg !border-none transition-all" @click="editProduct(data)" />
                                <Button icon="pi pi-trash" class="!text-zinc-500 hover:!text-red-400 hover:!bg-red-500/10 !w-9 !h-9 !p-0 !rounded-lg !border-none transition-all" @click="deleteProduct(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
            
            <Dialog 
                v-model:visible="dialogVisible" 
                :modal="true" 
                class="!bg-transparent !border-none !shadow-none ring-0 outline-none"
                :pt="{
                    root: { class: 'p-0 sm:m-4 max-w-5xl w-full' },
                    content: { class: 'p-0 !bg-transparent' }
                }"
                :closable="!saving"
                :showHeader="false"
            >
                <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col md:flex-row h-[85vh] overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    
                    <!-- Sidebar Navigation -->
                    <aside class="w-full md:w-64 bg-zinc-900/50 border-r border-zinc-800 flex flex-col pt-10 px-6 gap-8">
                        <div class="flex flex-col gap-1 px-2">
                            <div class="text-[9px] font-bold text-sky-500 tracking-[0.3em] font-mono leading-none mb-1">RECORD_DETAILS</div>
                            <h3 class="text-white text-lg font-bold tracking-tighter m-0 whitespace-nowrap">Product Details</h3>
                        </div>

                        <nav class="flex flex-col gap-2">
                            <button @click="activeTab = 'basic'" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all text-left border"
                                    :class="activeTab === 'basic' ? 'bg-sky-500/10 text-sky-400 border-sky-500/30 shadow-[0_0_15px_rgba(56,189,248,0.1)]' : 'bg-zinc-800/20 text-zinc-400 border-zinc-800 shadow-sm hover:text-zinc-100 hover:bg-zinc-800/80 hover:border-zinc-700'">
                                <span class="font-mono text-[9px] w-6 h-6 flex items-center justify-center rounded border transition-colors" 
                                      :class="activeTab === 'basic' ? 'border-sky-500/40 bg-sky-500/20 text-sky-400' : 'border-zinc-700 bg-zinc-950 text-zinc-500 group-hover:text-zinc-300'">01</span>
                                01. BASIC INFO
                            </button>
                            <button @click="activeTab = 'inventory'" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all text-left border"
                                    :class="activeTab === 'inventory' ? 'bg-sky-500/10 text-sky-400 border-sky-500/30 shadow-[0_0_15px_rgba(56,189,248,0.1)]' : 'bg-zinc-800/20 text-zinc-400 border-zinc-800 shadow-sm hover:text-zinc-100 hover:bg-zinc-800/80 hover:border-zinc-700'">
                                <span class="font-mono text-[9px] w-6 h-6 flex items-center justify-center rounded border transition-colors"
                                      :class="activeTab === 'inventory' ? 'border-sky-500/40 bg-sky-500/20 text-sky-400' : 'border-zinc-700 bg-zinc-950 text-zinc-500 group-hover:text-zinc-300'">02</span>
                                02. PRICING & LEVELS
                            </button>
                            <button @click="activeTab = 'media'" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all text-left border"
                                    :class="activeTab === 'media' ? 'bg-sky-500/10 text-sky-400 border-sky-500/30 shadow-[0_0_15px_rgba(56,189,248,0.1)]' : 'bg-zinc-800/20 text-zinc-400 border-zinc-800 shadow-sm hover:text-zinc-100 hover:bg-zinc-800/80 hover:border-zinc-700'">
                                <span class="font-mono text-[9px] w-6 h-6 flex items-center justify-center rounded border transition-colors"
                                      :class="activeTab === 'media' ? 'border-sky-500/40 bg-sky-500/20 text-sky-400' : 'border-zinc-700 bg-zinc-950 text-zinc-500 group-hover:text-zinc-300'">03</span>
                                03. PRODUCT PHOTO
                            </button>
                            <button @click="activeTab = 'packaging'" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all text-left border"
                                    :class="activeTab === 'packaging' ? 'bg-fuchsia-500/10 text-fuchsia-400 border-fuchsia-500/30 shadow-[0_0_15px_rgba(217,70,239,0.1)]' : 'bg-zinc-800/20 text-zinc-400 border-zinc-800 shadow-sm hover:text-zinc-100 hover:bg-zinc-800/80 hover:border-zinc-700'">
                                <span class="font-mono text-[9px] w-6 h-6 flex items-center justify-center rounded border transition-colors"
                                      :class="activeTab === 'packaging' ? 'border-fuchsia-500/40 bg-fuchsia-500/20 text-fuchsia-400' : 'border-zinc-700 bg-zinc-950 text-zinc-500 group-hover:text-zinc-300'">04</span>
                                04. PACKAGING (UOM)
                                <span v-if="isNonBaseUOMMissingRule" class="ml-auto w-2 h-2 rounded-full bg-fuchsia-500 animate-pulse shadow-[0_0_8px_rgba(217,70,239,0.5)]"></span>
                            </button>
                        </nav>

                        <div class="mt-auto pb-10 px-2">
                            <div class="text-[9px] font-bold text-zinc-600 font-mono tracking-tighter uppercase mb-1">Access Level</div>
                            <div class="text-[10px] text-zinc-400 font-mono flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                AUTHORIZED ADMIN
                            </div>
                        </div>
                    </aside>

                    <!-- Main Portal -->
                    <section class="flex-1 flex flex-col min-w-0">
                        <!-- Content Scrollable -->
                        <div class="flex-1 overflow-y-auto p-10 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.03),transparent_40%)]">
                            <!-- System Status Banner -->
                            <div class="flex justify-between items-center mb-10 pb-6 border-b border-zinc-900">
                                <div class="flex flex-col">
                                    <div class="text-[9px] font-bold text-zinc-600 font-mono uppercase tracking-[0.2em] mb-1">Product Information</div>
                                    <h2 class="text-white text-2xl font-bold tracking-tight m-0">{{ isEditing ? 'Edit Product' : 'New Product' }}</h2>
                                </div>
                                <Button icon="pi pi-times" class="!text-zinc-600 hover:!text-white !bg-transparent !border-none !w-10 !h-10 hover:!bg-zinc-900 transition-colors" @click="dialogVisible = false" :disabled="saving" />
                            </div>

                            <!-- Tab Sections -->
                            <div v-show="activeTab === 'basic'" class="animate-in slide-in-from-right-4 duration-500">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-8">
                                    <div class="flex flex-col gap-3 md:col-span-2">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Product Name</label>
                                            <span v-if="errors.name" class="text-red-400 text-[10px] font-bold uppercase tracking-widest font-mono">!! {{ errors.name }}</span>
                                        </div>
                                        <InputText v-model="product.name" placeholder="E.g. Wireless Mouse X10" 
                                                   class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-14 !text-lg !font-bold focus:!border-sky-500/40 transition-all !px-5"
                                                   :class="{'!border-red-500/50': errors.name}" />
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Part Number (MPN)</label>
                                            <span v-if="errors.product_code" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">{{ errors.product_code }}</span>
                                        </div>
                                        <InputText v-model="product.product_code" placeholder="Manufacturer's unique identifier..." 
                                                  :disabled="isEditing && product.has_history"
                                                  class="!bg-zinc-900/50 !border-zinc-800 !text-sky-400 !h-12 !font-mono !px-4 focus:!border-sky-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
                                                  :class="{'!border-red-500/50': errors.product_code}" />
                                        <p v-if="isEditing && product.has_history" class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest font-mono mt-1 italic">Identifier locked due to audit history</p>
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Product SKU</label>
                                            <span v-if="errors.sku" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">{{ errors.sku }}</span>
                                            <span v-else-if="!isEditing && !product.sku" class="text-red-500 text-[9px] font-bold uppercase tracking-widest font-mono italic">Auto-generates if blank</span>
                                        </div>
                                        <InputText v-model="product.sku" placeholder="CAT-ABBR-0001" 
                                                  :disabled="isEditing && product.has_history"
                                                  class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 !h-12 !font-mono !px-4 focus:!border-sky-500/30 disabled:opacity-50 disabled:cursor-not-allowed"
                                                  :class="{'!border-red-500/50': errors.sku}" />
                                        <p v-if="isEditing && product.has_history" class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest font-mono mt-1 italic">Identifier locked due to audit history</p>
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Category</label>
                                            <span v-if="errors.category_id" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">Required</span>
                                        </div>
                                        <Select v-model="product.category_id" :options="categories" optionLabel="name" optionValue="id" 
                                                class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12"
                                                :class="{'!border-red-500/50': errors.category_id}" />
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Preferred Vendor (Owner)</label>
                                            <span class="text-zinc-600 text-[9px] font-bold uppercase tracking-widest font-mono italic">Optional</span>
                                        </div>
                                        <Select v-model="product.preferred_vendor_id" :options="vendors" optionLabel="name" optionValue="id" 
                                                placeholder="Link to a supplier..."
                                                showClear
                                                class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12" />
                                    </div>

                                    <div class="flex flex-col gap-3 md:col-span-2">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Unit of Measure</label>
                                            <span v-if="errors.uom_id" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">Required</span>
                                        </div>
                                        <Select v-model="product.uom_id" :options="uoms" optionLabel="name" optionValue="id" 
                                                :disabled="isEditing && product.has_history"
                                                class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 disabled:opacity-50"
                                                :class="{'!border-red-500/50': errors.uom_id}" />
                                        
                                        <!-- Inline Quick Conversion for New Products -->
                                        <div v-if="!product.id && isNonBaseUOMMissingRule" class="mt-2 p-3 bg-[radial-gradient(ellipse_at_top,rgba(56,189,248,0.05),transparent)] border border-sky-500/20 rounded-lg animate-in fade-in slide-in-from-top-2 duration-300">
                                            <div class="flex items-center justify-between mb-3 border-b border-sky-500/10 pb-2">
                                                <div class="flex items-center gap-2">
                                                    <i class="pi pi-bolt text-sky-400 text-[10px]"></i>
                                                    <span class="text-[8px] font-black text-sky-400 uppercase tracking-widest font-mono italic">Quick Conversion Define</span>
                                                </div>
                                                <span class="text-[8px] font-bold text-zinc-500 font-mono tracking-tighter">1 {{ uoms.find(u => u.id === product.uom_id)?.abbreviation }} =</span>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <div class="relative flex-1">
                                                    <InputNumber v-model="product.initial_conversion_factor" placeholder="Enter quantity..." 
                                                                inputClass="!bg-zinc-950 !border-sky-500/30 !text-white !h-11 !w-full !px-3 !font-black !font-mono !text-lg focus:!border-sky-500 shadow-inner" />
                                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-zinc-500 uppercase font-mono tracking-tighter">{{ getBaseUomForSelected?.abbreviation || 'pcs' }}</div>
                                                </div>
                                                <div class="flex flex-col gap-0.5 max-w-[120px]">
                                                    <span class="text-[7px] text-zinc-500 font-bold uppercase tracking-[0.1em] font-mono leading-none">Atomic Rule</span>
                                                    <p class="text-[7px] text-sky-400/60 font-mono italic m-0 tracking-tighter leading-tight">Scale factors are applied at creation.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <p v-if="isEditing && product.has_history" class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest font-mono mt-1 italic">UOM locked due to audit history</p>
                                    </div>
                                    <div class="flex flex-col gap-3 md:col-span-2">
                                        <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Description</label>
                                        <Textarea v-model="product.description" rows="4" placeholder="Enter product description..." 
                                                  class="!bg-zinc-900/50 !border-zinc-800 !text-zinc-300 focus:!border-sky-500/30 transition-all !p-5 leading-relaxed" />
                                    </div>
                                </div>
                            </div>

                            <div v-show="activeTab === 'inventory'" class="animate-in slide-in-from-right-4 duration-500">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-8">
                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Selling Price (PHP)</label>
                                            <span v-if="errors.selling_price" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">{{ errors.selling_price }}</span>
                                        </div>
                                        <InputNumber v-model="product.selling_price" mode="currency" currency="PHP" locale="en-PH" 
                                                    inputClass="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !w-full !px-4 !font-mono font-bold"
                                                    :class="{'!border-red-500/50': errors.selling_price}" />
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Minimum Stock Level</label>
                                            <span v-if="errors.reorder_point" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">{{ errors.reorder_point }}</span>
                                        </div>
                                        <InputNumber v-model="product.reorder_point" :minFractionDigits="0" :maxFractionDigits="isUomIdDiscrete(product.uom_id) ? 0 : 8"
                                                    inputClass="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !w-full !px-4 !font-mono font-bold"
                                                    :class="{'!border-red-500/50': errors.reorder_point}" />
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Restock Amount</label>
                                        <InputNumber v-model="product.reorder_quantity" :minFractionDigits="0" :maxFractionDigits="isUomIdDiscrete(product.uom_id) ? 0 : 8"
                                                    inputClass="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 !w-full !px-4 !font-mono font-bold" />
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Valuation Method</label>
                                            <span v-if="errors.costing_method_id" class="text-red-400 text-[9px] font-bold uppercase tracking-widest font-mono">Required</span>
                                        </div>
                                        <Select v-model="product.costing_method_id" :options="costingMethods" optionLabel="label" optionValue="id" 
                                                :disabled="isEditing && product.has_history"
                                                class="!bg-zinc-900/50 !border-zinc-800 !text-white !h-12 disabled:opacity-50"
                                                :class="{'!border-red-500/50': errors.costing_method_id}" />
                                        <p v-if="isEditing && product.has_history" class="text-[8px] font-bold text-zinc-700 uppercase tracking-widest font-mono mt-1 italic">Costing method locked due to audit history</p>
                                    </div>
                                    <div class="col-span-12 md:col-span-2 p-8 bg-zinc-900/30 border border-zinc-800/60 rounded-xl relative overflow-hidden group">
                                        <div class="flex justify-between items-center relative z-10">
                                            <div class="flex flex-col gap-1">
                                                <h4 class="text-white font-bold text-sm m-0 uppercase tracking-tight">Active Availability</h4>
                                                <p class="text-zinc-500 text-[11px] font-mono tracking-tighter uppercase leading-none mt-1">Status: {{ product.is_active ? 'Active' : 'Inactive' }}</p>
                                            </div>
                                            <ToggleSwitch v-model="product.is_active" 
                                                         :pt="{
                                                             slider: ({ props }) => ({
                                                                 class: props.modelValue ? '!bg-emerald-500' : '!bg-zinc-700'
                                                             })
                                                         }" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-show="activeTab === 'media'" class="animate-in slide-in-from-right-4 duration-500 h-full">
                                <div class="h-96 border-2 border-dashed border-zinc-900 bg-zinc-950/50 rounded-2xl flex items-center justify-center relative group hover:border-sky-500/20 transition-all cursor-pointer overflow-hidden shadow-inner" 
                                     @click="$refs.fileInput.click()">
                                    <input type="file" ref="fileInput" @change="onImageSelect" hidden />
                                    
                                    <div v-if="imagePreview" class="absolute inset-0 w-full h-full p-10 flex items-center justify-center">
                                        <img :src="imagePreview" class="w-full h-full object-contain drop-shadow-2xl" />
                                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-[2px]">
                                            <Button label="REPLACE IMAGE" icon="pi pi-refresh" class="!bg-sky-500 !border-none !rounded-full !px-8 !font-bold !h-12" />
                                        </div>
                                    </div>
                                    <div v-else class="flex flex-col items-center gap-4 text-center opacity-30 group-hover:opacity-60 transition-opacity">
                                        <div class="w-20 h-20 rounded-full bg-zinc-900 flex items-center justify-center border border-zinc-800">
                                            <i class="pi pi-cloud-upload text-3xl"></i>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <h5 class="text-white font-bold uppercase text-xs tracking-[0.2em] m-0">Upload Product Image</h5>
                                            <span class="text-[10px] text-zinc-500 font-mono">WEBP // PNG // JPEG (MAX: 5MB)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-show="activeTab === 'packaging'" class="animate-in slide-in-from-right-4 duration-500 h-full flex flex-col">
                                <div v-if="product.uom_id" class="flex flex-col gap-6">
                                    <div class="flex justify-between items-center bg-zinc-900/30 p-4 border border-zinc-800/80 rounded-xl">
                                        <div class="flex flex-col">
                                            <span class="text-white font-bold text-sm tracking-tight uppercase">Product Packaging Equivalency</span>
                                            <span class="text-[10px] text-zinc-500 font-mono mt-1 leading-relaxed">Define how cases, pallets, or boxes break down accurately to the atomic base unit for true scale calculations.</span>
                                        </div>
                                        <Button v-if="!addingRule" 
                                                label="ADD PACKAGING" icon="pi pi-plus" 
                                                :disabled="!product.id"
                                                class="!bg-fuchsia-500 hover:!bg-fuchsia-400 !border-none !text-[10px] !font-bold !h-10 !px-4 disabled:!opacity-30 disabled:!bg-zinc-800 disabled:!text-zinc-500 transition-all font-mono" 
                                                @click="addingRule = true" 
                                                :title="!product.id ? 'Save product first to unlock custom packaging' : ''" />
                                    </div>

                                    <!-- Atomic Onboarding for New Products -->
                                    <div v-if="!product.id && isNonBaseUOMMissingRule" class="bg-[radial-gradient(ellipse_at_top,rgba(56,189,248,0.05),transparent)] border border-sky-500/20 p-8 rounded-xl flex flex-col gap-6 shadow-inner animate-in fade-in zoom-in duration-500">
                                        <div class="flex items-start gap-4 pb-4 border-b border-sky-500/10">
                                            <div class="w-10 h-10 rounded bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400">
                                                <i class="pi pi-bolt"></i>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <span class="text-[10px] font-bold text-sky-400 uppercase tracking-widest font-mono">Atomic Onboarding</span>
                                                <p class="text-[10px] text-zinc-400 font-mono leading-relaxed m-0 italic">No global rule found for <span class="text-white">{{ uoms.find(u => u.id === product.uom_id)?.abbreviation }}</span>. Define it now to save this product.</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-2">
                                            <div class="flex flex-col gap-3">
                                                <div class="flex justify-between items-center px-1">
                                                    <label class="text-[9px] font-black text-zinc-500 uppercase tracking-widest font-mono italic">Configuration Rule</label>
                                                    <span class="text-[8px] font-bold text-sky-500 uppercase font-mono">1 {{ uoms.find(u => u.id === product.uom_id)?.abbreviation }} =</span>
                                                </div>
                                                <div class="relative">
                                                     <InputNumber v-model="product.initial_conversion_factor" placeholder="E.g. 12" 
                                                                 class="!w-full"
                                                                 inputClass="!bg-zinc-900/80 !border-sky-500/30 !text-white !h-12 !px-4 !font-black !text-lg !font-mono focus:!border-sky-500 shadow-[0_0_15px_rgba(56,189,248,0.1)]" />
                                                     <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold text-zinc-500 tracking-tighter uppercase font-mono">{{ getBaseUomForSelected?.abbreviation || 'pcs' }}</div>
                                                </div>
                                            </div>

                                            <div class="bg-zinc-900/40 border border-zinc-800 p-5 rounded-lg flex items-center gap-4">
                                                <i class="pi pi-lock-open text-zinc-700 text-lg"></i>
                                                <p class="text-[9px] text-zinc-500 font-mono leading-relaxed m-0">This rule will be saved <span class="text-sky-400 font-bold italic">Atopically</span> with the product. No global settings will be affected.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="addingRule" class="bg-[radial-gradient(ellipse_at_top,rgba(217,70,239,0.05),transparent)] border border-fuchsia-500/20 p-6 rounded-xl flex flex-col gap-5 shadow-inner">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold text-fuchsia-400/80 uppercase tracking-widest font-mono">1. Packaging Unit</label>
                                                <Select v-model="newRule.from_uom_id" :options="uoms.filter(u => !u.is_base)" optionLabel="name" optionValue="id" placeholder="E.g. Box" class="!bg-zinc-900 !border-zinc-800 !text-white" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold text-fuchsia-400/80 uppercase tracking-widest font-mono">2. Translates To</label>
                                                <Select v-model="newRule.to_uom_id" :options="uoms.filter(u => u.is_base)" optionLabel="name" optionValue="id" placeholder="E.g. Pieces" class="!bg-zinc-900 !border-zinc-800 !text-sky-400 !font-bold" />
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <label class="text-[10px] font-bold text-fuchsia-400/80 uppercase tracking-widest font-mono">3. Qty in Base Unit</label>
                                                <InputNumber v-model="newRule.conversion_factor" placeholder="E.g. 12" inputClass="!bg-zinc-900 !border-zinc-800 !text-fuchsia-400 !font-bold !font-mono" />
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-3 mt-2 pt-4 border-t border-fuchsia-500/10">
                                            <Button label="CANCEL CONFIG" class="!bg-transparent !border-zinc-700 !text-zinc-400 hover:!text-white !px-4 !h-8 !border !text-[9px] font-bold tracking-widest transition-colors" @click="addingRule = false" />
                                            <Button label="COMMIT RULE" class="!bg-fuchsia-500 hover:!bg-fuchsia-400 !text-white !border-none !px-6 !h-8 !text-[9px] !font-bold tracking-widest" @click="saveProductConversion" />
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3" v-if="!loadingConversions">
                                        <div v-for="rule in productConversions" :key="rule.id" class="flex justify-between items-center p-4 bg-zinc-900/50 border border-zinc-800 rounded-lg group hover:border-zinc-700 transition-colors">
                                            <div class="flex items-center gap-4">
                                                <div class="px-3 py-1.5 bg-zinc-950 rounded text-[12px] font-mono font-black text-white border border-zinc-800 shadow-inner">1 {{ rule.from_uom?.abbreviation || '?' }}</div>
                                                <i class="pi pi-arrow-right text-zinc-600 text-[10px]"></i>
                                                <div class="px-3 py-1.5 bg-sky-950/20 rounded text-[12px] font-mono font-black text-fuchsia-400 border border-fuchsia-500/20 shadow-[0_0_15px_rgba(217,70,239,0.08)]">{{ rule.conversion_factor }} {{ rule.to_uom?.abbreviation || '?' }}</div>
                                            </div>
                                            <Button icon="pi pi-trash" class="!w-8 !h-8 !p-0 !bg-transparent !border-none !text-zinc-600 hover:!text-red-400 opacity-0 group-hover:opacity-100 transition-all cursor-pointer" @click="deleteProductConversion(rule.id)" />
                                        </div>
                                        <div v-if="productConversions.length === 0 && !addingRule" class="text-center py-12 px-6 opacity-40 grayscale flex flex-col items-center">
                                            <i class="pi pi-box text-3xl mb-4 text-zinc-500 font-light"></i>
                                            <p class="text-[10px] font-mono uppercase tracking-[0.2em] leading-relaxed m-0 text-zinc-400">No custom packaging defined.<br/><span class="opacity-70 text-[9px]">Product will use standard universal metrics from global settings.</span></p>
                                        </div>
                                    </div>
                                    <div v-if="loadingConversions" class="flex justify-center py-16">
                                        <i class="pi pi-spin pi-spinner text-fuchsia-400 text-3xl opacity-50"></i>
                                    </div>
                                </div>
                                <div v-else class="p-8 border-2 border-dashed border-zinc-900 bg-zinc-950/50 rounded-2xl flex flex-col items-center justify-center text-center opacity-60">
                                    <i class="pi pi-info-circle text-3xl text-zinc-600 mb-4 font-light"></i>
                                    <p class="text-[10px] font-mono uppercase tracking-[0.2em] leading-relaxed m-0 text-zinc-400">Select a Unit of Measure in the Basic Info tab<br/><span class="opacity-70 text-[8px]">Then you can configure packaging here.</span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Global Footer -->
                        <footer class="px-10 py-8 border-t border-zinc-900 bg-zinc-900/50 flex flex-col sm:flex-row justify-between items-center gap-8">
                            <div class="flex flex-col gap-1">
                                <div class="text-[9px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Product ID</div>
                                <div class="text-[10px] text-sky-400 font-mono">{{ isEditing ? `ID-${product.id}` : 'PENDING' }}</div>
                            </div>
                            <div class="flex items-center gap-4 w-full sm:w-auto">
                                <Button label="CANCEL" class="!bg-transparent !border-zinc-800 !text-zinc-500 hover:!text-white hover:!border-zinc-600 !px-8 !h-12 !font-bold !text-[11px] uppercase tracking-widest flex-1 sm:flex-none border transition-colors" @click="dialogVisible = false" />
                                <Button :label="isEditing ? 'SAVE CHANGES' : 'SAVE PRODUCT'" 
                                        class="!bg-emerald-500 !border-none !text-white !px-12 !h-12 !font-bold !text-[11px] uppercase tracking-widest flex-1 sm:flex-none shadow-lg shadow-emerald-500/10 hover:!bg-emerald-400 active:scale-95 transition-all" 
                                        @click="saveProduct" :loading="saving" />
                            </div>
                        </footer>
                    </section>
                </div>
            </Dialog>

            <!-- Scattered Stock Popover -->
            <Popover ref="stockOp" class="!bg-zinc-950 !border-zinc-800 !shadow-2xl !p-0 overflow-hidden">
                <div v-if="selectedProductForStock" class="flex flex-col w-72">
                    <div class="px-4 py-3 border-b border-zinc-900 bg-zinc-900/30 flex justify-between items-center">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-sky-500 uppercase tracking-widest font-mono">{{ selectedProductForStock.sku }}</span>
                            <span class="text-[10px] font-bold text-white truncate max-w-[180px]">{{ selectedProductForStock.name }}</span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black text-white font-mono">{{ getScaledQty(selectedProductForStock, selectedProductForStock.total_qoh) }}</span>
                            <span class="text-[8px] font-bold text-zinc-600 uppercase tracking-tighter">Total On Hand</span>
                        </div>
                    </div>
                    <div class="max-h-60 overflow-y-auto custom-scrollbar">
                        <div v-if="selectedProductForStock.inventories?.length > 0" class="flex flex-col">
                            <div v-for="inv in selectedProductForStock.inventories" :key="inv.id" 
                                 class="px-4 py-2.5 border-b border-zinc-900/50 flex justify-between items-center hover:bg-white/[0.02] transition-colors">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-zinc-300 leading-none">{{ inv.location?.name }}</span>
                                    <span class="text-[8px] font-mono font-bold text-zinc-600 uppercase tracking-tighter mt-1">{{ inv.location?.code }}</span>
                                </div>
                                <span class="text-[10px] font-mono font-bold text-emerald-400">
                                    {{ getScaledQty(selectedProductForStock, inv.quantity_on_hand) }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="p-8 text-center flex flex-col items-center gap-2 opacity-30">
                            <i class="pi pi-exclamation-circle text-xl"></i>
                            <span class="text-[9px] font-bold uppercase tracking-widest font-mono">No physical stock found</span>
                        </div>
                    </div>
                    <div class="px-4 py-2 bg-zinc-900/10 flex justify-center border-t border-zinc-900">
                        <button @click="goToInventory(selectedProductForStock)" class="text-[9px] font-bold text-zinc-500 hover:text-sky-400 transition-colors uppercase tracking-[0.2em] font-mono">View Inventory Ledger</button>
                    </div>
                </div>
            </Popover>

        </div>
    </AppLayout>
</template>

<style scoped>
/* Redefined via Tailwind v4 Utilities */
</style>