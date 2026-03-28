<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
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

// Validation errors
const errors = ref({});

// Debounced search for better performance
const debouncedSearch = useDebounceFn(() => {
    loadProducts();
}, 300);

watch(search, () => {
    debouncedSearch();
});

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
    } catch (e) {
        console.error("Metadata load error", e);
    }
};

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
        is_active: true,
        image: null
    };
    imagePreview.value = null;
    imageFile.value = null;
    errors.value = {};
    submitted.value = false;
    isEditing.value = false;
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
        category_id: p.category?.id, 
        uom_id: p.uom?.id,
        preferred_vendor_id: p.preferred_vendor?.id,
        costing_method_id: costingMethods.value.find(m => m.name === p.costing_method)?.id || p.costing_method_id
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
    
    if (!product.value.name?.trim()) newErrors.name = 'Product name is required';
    if (!product.value.product_code?.trim()) newErrors.product_code = 'Product code is required';
    if (!product.value.sku?.trim()) newErrors.sku = 'SKU is required';
    if (!product.value.category_id) newErrors.category_id = 'Category is required';
    if (!product.value.uom_id) newErrors.uom_id = 'Unit of measure is required';
    if (!product.value.costing_method_id) newErrors.costing_method_id = 'Costing method is required';
    if (product.value.selling_price < 0) newErrors.selling_price = 'Price cannot be negative';
    if (product.value.reorder_point < 0) newErrors.reorder_point = 'Reorder point cannot be negative';
    
    errors.value = newErrors;
    return Object.keys(newErrors).length === 0;
};

const saveProduct = async () => {
    submitted.value = true;
    
    if (!validateForm()) {
        // Scroll to first error
        const firstError = Object.keys(errors.value)[0];
        if (firstError) {
            const element = document.querySelector(`[data-field="${firstError}"]`);
            if (element) element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    saving.value = true;

    const formData = new FormData();
    Object.keys(product.value).forEach(key => {
        if (product.value[key] !== null && key !== 'image') {
            formData.append(key, product.value[key]);
        }
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
            toast.add({ severity: 'error', summary: 'Validation Error', detail: 'Please check the form for errors', life: 3000 });
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
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};

const getStatusSeverity = (isActive) => isActive ? 'success' : 'secondary';
const getStatusLabel = (isActive) => isActive ? 'Active' : 'Inactive';

// Quick stats
const stats = computed(() => ({
    total: products.value.length,
    active: products.value.filter(p => p.is_active).length,
    totalValue: products.value.reduce((sum, p) => sum + (p.selling_price || 0), 0)
}));
</script>

<template>
    <AppLayout>
        <Head title="Product Catalog" />
        
        <div class="catalog-container">
            <!-- Header Section -->
            <div class="catalog-header">
                <div class="header-left">
                    <span class="page-badge">Master Documentation</span>
                    <h1 class="page-title">Product Catalog</h1>
                    <p class="page-description">Maintain systematic registry of all inventory assets and classifications.</p>
                </div>
                
                <div class="header-right">
                    <div class="search-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <InputText 
                            v-model="search" 
                            placeholder="Filter registry..." 
                            class="search-input"
                        />
                        <i v-if="search" class="pi pi-times clear-search" @click="search = ''" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 10px; color: var(--text-secondary);"></i>
                    </div>
                    <Button 
                        v-if="can('manage-products')" 
                        label="Add Asset" 
                        icon="pi pi-plus" 
                        class="p-button-primary"
                        @click="openNew" 
                    />
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="pi pi-box"></i></div>
                    <div class="stat-info">
                        <span class="stat-value">{{ stats.total }}</span>
                        <span class="stat-label">Total Registry</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="pi pi-check-circle"></i></div>
                    <div class="stat-info">
                        <span class="stat-value">{{ stats.active }}</span>
                        <span class="stat-label">Active Nodes</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="pi pi-dollar"></i></div>
                    <div class="stat-info">
                        <span class="stat-value">{{ formatCurrency(stats.totalValue) }}</span>
                        <span class="stat-label">Aggregate Valuation</span>
                    </div>
                </div>
            </div>

            <!-- Central Registry Table -->
            <div class="table-container">
                <div class="table-header">
                    <span class="table-title">ASSET REGISTRY</span>
                    <span class="gh-count">{{ products.length }} items</span>
                </div>
                <DataTable 
                    :value="products" 
                    :loading="loading" 
                    responsiveLayout="scroll" 
                    :paginator="true" 
                    :rows="12"
                    class="gh-table"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                    currentPageReportTemplate="{first} to {last} of {totalRecords}"
                >
                    <template #empty>
                        <div class="empty-state">
                            <i class="pi pi-search" style="font-size: 2rem; opacity: 0.2; margin-bottom: 1rem;"></i>
                            <p>NO MATCHING RECORDS FOUND IN REGISTRY</p>
                        </div>
                    </template>

                    <Column field="main_image_url" header="Media" style="width: 80px">
                        <template #body="{ data }">
                            <div class="product-image-container">
                                <img v-if="data.main_image_url" :src="data.main_image_url" class="product-image" />
                                <i v-else class="pi pi-image" style="opacity: 0.3"></i>
                            </div>
                        </template>
                    </Column>

                    <Column field="product_code" header="System ID" style="width: 140px">
                        <template #body="{ data }">
                            <span class="product-code">{{ data.product_code }}</span>
                        </template>
                    </Column>

                    <Column field="sku" header="Registry SKU" style="width: 160px">
                        <template #body="{ data }">
                            <span class="product-sku">{{ data.sku }}</span>
                        </template>
                    </Column>

                    <Column field="name" header="Asset Designation">
                        <template #body="{ data }">
                            <div>
                                <span class="product-name">{{ data.name }}</span>
                                <span v-if="data.brand" class="product-brand">{{ data.brand }}</span>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="category" header="Classification" style="width: 150px">
                        <template #body="{ data }">
                            <Tag :value="data.category?.name || 'Unclassified'" class="gh-tag-secondary" />
                        </template>
                    </Column>
                    
                    <Column field="selling_price" header="Value" style="width: 120px">
                        <template #body="{ data }">
                            <span class="product-price">{{ formatCurrency(data.selling_price) }}</span>
                        </template>
                    </Column>

                    <Column header="Status" style="width: 120px">
                        <template #body="{ data }">
                            <div class="status-pill" :class="data.is_active ? 'status-active' : 'status-inactive'">
                                <span class="status-dot"></span>
                                {{ data.is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </div>
                        </template>
                    </Column>
                    
                    <Column header="Ops" style="width: 100px" v-if="can('manage-products')">
                        <template #body="{ data }">
                            <div class="action-buttons">
                                <Button icon="pi pi-pencil" class="p-button-text action-btn" @click="editProduct(data)" />
                                <Button icon="pi pi-trash" class="p-button-text action-btn delete-btn" @click="deleteProduct(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
            
            <!-- Premium Asset Configuration Modal (Monochrome Slate) -->
            <Dialog 
                v-model:visible="dialogVisible" 
                :modal="true" 
                :style="{ width: '800px', margin: '0 1rem' }" 
                class="slate-modal"
                :closable="!saving"
                :showHeader="false"
            >
                <div class="slate-modal-inner">
                    <!-- Header -->
                    <div class="slate-modal-header">
                        <div class="header-left">
                            <div class="slate-badge">{{ isEditing ? 'SYSTEM.UPDATE' : 'SYSTEM.INIT' }}</div>
                            <h2>{{ isEditing ? 'Asset Modification' : 'New Asset Protocol' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="p-button-text close-trigger" @click="dialogVisible = false" :disabled="saving" />
                    </div>

                    <!-- Horizontal Nav -->
                    <nav class="slate-tabs">
                        <button @click="activeTab = 'basic'" :class="{ active: activeTab === 'basic' }">
                            01 // Core Specs
                        </button>
                        <button @click="activeTab = 'inventory'" :class="{ active: activeTab === 'inventory' }">
                            02 // Logistics
                        </button>
                        <button @click="activeTab = 'media'" :class="{ active: activeTab === 'media' }">
                            03 // Artifacts
                        </button>
                    </nav>

                    <!-- Scrollable Form Area -->
                    <div class="slate-modal-body">
                        <div v-show="activeTab === 'basic'" class="form-section animate-in">
                            <div class="slate-form-grid">
                                <div class="p-field col-span-2">
                                    <label>Asset Designation</label>
                                    <InputText v-model="product.name" placeholder="E.g. High-Density Processing Unit" :class="{'p-invalid': errors.name}" />
                                </div>
                                <div class="p-field">
                                    <label>System Code</label>
                                    <InputText v-model="product.product_code" placeholder="PRD-XXXX" />
                                </div>
                                <div class="p-field">
                                    <label>Registry SKU</label>
                                    <InputText v-model="product.sku" placeholder="SKU-XXXX" />
                                </div>
                                <div class="p-field">
                                    <label>Manufacturer Brand</label>
                                    <InputText v-model="product.brand" placeholder="N/A" />
                                </div>
                                <div class="p-field">
                                    <label>Barcode ID</label>
                                    <InputText v-model="product.barcode" placeholder="Optional" />
                                </div>
                                <div class="p-field">
                                    <label>Classification Node</label>
                                    <Select v-model="product.category_id" :options="categories" optionLabel="name" optionValue="id" />
                                </div>
                                <div class="p-field">
                                    <label>Quantum Unit (UOM)</label>
                                    <Select v-model="product.uom_id" :options="uoms" optionLabel="name" optionValue="id" />
                                </div>
                                <div class="p-field col-span-2">
                                    <label>Technical Description</label>
                                    <Textarea v-model="product.description" rows="3" placeholder="Provide detailed operational parameters..." />
                                </div>
                            </div>
                        </div>

                        <div v-show="activeTab === 'inventory'" class="form-section animate-in">
                            <div class="slate-form-grid">
                                <div class="p-field">
                                    <label>Base Valuation (USD)</label>
                                    <InputNumber v-model="product.selling_price" mode="currency" currency="USD" locale="en-US" />
                                </div>
                                <div class="p-field">
                                    <label>Reorder Threshold</label>
                                    <InputNumber v-model="product.reorder_point" />
                                </div>
                                <div class="p-field">
                                    <label>Target Buffer Qty</label>
                                    <InputNumber v-model="product.reorder_quantity" />
                                </div>
                                <div class="p-field">
                                    <label>Valuation Model</label>
                                    <Select v-model="product.costing_method_id" :options="costingMethods" optionLabel="label" optionValue="id" />
                                </div>
                                <div class="p-field col-span-2 status-control">
                                    <div class="control-info">
                                        <h4>Operational Status</h4>
                                        <p>Toggle system availability for this asset.</p>
                                    </div>
                                    <ToggleSwitch v-model="product.is_active" />
                                </div>
                            </div>
                        </div>

                        <div v-show="activeTab === 'media'" class="form-section animate-in">
                            <div class="upload-stage">
                                <div class="upload-dropzone" @click="$refs.fileInput.click()">
                                    <input type="file" ref="fileInput" @change="onImageSelect" hidden />
                                    <div v-if="imagePreview" class="preview-wrapper">
                                        <img :src="imagePreview" class="asset-preview-img" />
                                        <div class="overlay-actions">
                                            <Button icon="pi pi-trash" class="p-button-danger p-button-rounded" @click.stop="removeImage" />
                                        </div>
                                    </div>
                                    <div v-else class="upload-prompt">
                                        <i class="pi pi-cloud-upload"></i>
                                        <h5>Drop System Artifact</h5>
                                        <span>Max: 5MB (JPEG, PNG, WEBP)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="slate-modal-footer">
                        <div class="sys-id">UID // {{ isEditing ? product.id : 'PENDING' }}</div>
                        <div class="action-buttons-group">
                            <Button label="Abort" class="p-button-secondary" @click="dialogVisible = false" />
                            <Button :label="isEditing ? 'COMMIT UPDATE' : 'EXECUTE INIT'" class="p-button-primary" @click="saveProduct" :loading="saving" />
                        </div>
                    </div>
                </div>
            </Dialog>

        </div>
    </AppLayout>
</template>

<style scoped>
/* Main Catalog UI (GitHub Inspired) */
.catalog-container {
    padding: 1.5rem;
    background: var(--bg-deep);
    min-height: 100vh;
}

.catalog-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 2rem;
    gap: 1.5rem;
}

.page-badge {
    font-size: 11px;
    font-weight: 600;
    color: var(--accent-primary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 4px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.page-description {
    color: var(--text-secondary);
    font-size: 13px;
    margin: 0;
}

.header-right {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.search-wrapper {
    position: relative;
    width: 300px;
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: var(--text-secondary);
    z-index: 1;
}

.search-input {
    width: 100%;
    padding-left: 30px !important;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 6px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-primary);
    font-size: 18px;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.2;
}

.stat-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
}

.table-container {
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 6px;
    overflow: hidden;
}

.table-header {
    padding: 10px 16px;
    background: var(--bg-panel);
    border-bottom: 1px solid var(--bg-panel-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
}

.gh-count {
    background: var(--bg-panel-hover);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    color: var(--text-primary);
}

.gh-table {
    font-size: 13px;
}

::v-deep(.p-datatable-thead > tr > th) {
    background: var(--bg-panel-hover) !important;
    border-bottom: 1px solid var(--bg-panel-border) !important;
    padding: 10px 16px !important;
    color: var(--text-secondary) !important;
    font-weight: 600 !important;
}

::v-deep(.p-datatable-tbody > tr) {
    background: transparent !important;
    border-bottom: 1px solid var(--bg-panel-border) !important;
}

::v-deep(.p-datatable-tbody > tr:hover) {
    background: var(--bg-panel) !important;
}

.product-image-container {
    width: 40px;
    height: 40px;
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-code {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 12px;
    background: var(--bg-panel);
    color: var(--text-primary);
    padding: 2px 6px;
    border: 1px solid var(--bg-panel-border);
    border-radius: 4px;
}

.product-sku {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 12px;
    color: var(--accent-primary);
}

.product-name {
    font-weight: 600;
    color: var(--text-primary);
    display: block;
}

.product-brand {
    font-size: 11px;
    color: var(--text-secondary);
}

.product-price {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-weight: 600;
}

.status-pill {
    font-size: 10px;
    font-weight: 600;
    padding: 1px 8px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-active {
    background: rgba(87, 171, 90, 0.1);
    color: var(--accent-primary);
    border: 1px solid rgba(87, 171, 90, 0.2);
}

.status-inactive {
    background: var(--bg-panel);
    color: var(--text-secondary);
    border: 1px solid var(--bg-panel-border);
}

.status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
}

.status-active .status-dot { background: var(--accent-primary); }
.status-inactive .status-dot { background: var(--text-secondary); }

.gh-tag-secondary {
    background: var(--bg-panel) !important;
    color: var(--text-secondary) !important;
    font-size: 10px;
    border: 1px solid var(--bg-panel-border);
    padding: 2px 8px;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.action-btn {
    color: var(--text-secondary) !important;
    padding: 0.5rem !important;
    min-width: 0 !important;
}

.action-btn:hover {
    color: var(--accent-primary) !important;
    background: rgba(87, 171, 90, 0.1) !important;
}

.delete-btn:hover {
    color: #f47067 !important;
    background: rgba(244, 112, 103, 0.1) !important;
}

/* --- Slate Modal Redesign --- */
::v-deep(.slate-modal) {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
}

::v-deep(.p-dialog-content) {
    padding: 0 !important;
    background: transparent !important;
    outline: none !important;
    border: none !important;
}

.slate-modal-inner {
    background: var(--bg-deep); /* Absolute black */
    border: 1px solid var(--bg-panel-border);
    border-radius: 8px; /* Slightly softer for a modern look */
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    max-height: 85vh;
    overflow: hidden;
}

/* Header */
.slate-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2rem 2.5rem 1.5rem;
    border-bottom: 1px solid var(--bg-panel-border);
    background: var(--bg-panel);
}

.header-left .slate-badge {
    font-size: 10px;
    font-weight: 700;
    color: var(--accent-subtle);
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
    font-family: 'JetBrains Mono', monospace;
}

.header-left h2 {
    font-size: 22px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    letter-spacing: -0.02em;
}

.close-trigger {
    color: var(--text-secondary) !important;
    width: 32px !important;
    height: 32px !important;
}

/* Horizontal Nav */
.slate-tabs {
    display: flex;
    padding: 0 2.5rem;
    border-bottom: 1px solid var(--bg-panel-border);
    background: var(--bg-panel);
}

.slate-tabs button {
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 1rem 1.5rem;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none;
}

.slate-tabs button:hover {
    color: var(--text-primary);
}

.slate-tabs button.active {
    color: var(--accent-primary);
    border-bottom-color: var(--accent-primary);
}

/* Modal Body */
.slate-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2.5rem;
    background: var(--bg-deep);
}

.slate-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem 2rem;
}

.col-span-2 {
    grid-column: span 2;
}

.p-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.p-field label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Clean Form Inputs */
::v-deep(.p-inputtext), ::v-deep(.p-select), ::v-deep(.p-inputnumber-input), ::v-deep(.p-textarea) {
    background: var(--bg-panel) !important;
    border: 1px solid var(--bg-panel-border) !important;
    color: var(--text-primary) !important;
    border-radius: 4px !important;
    padding: 10px 14px !important;
    font-size: 14px !important;
    box-shadow: none !important;
    transition: all 0.2s ease !important;
}

::v-deep(.p-inputtext:focus), ::v-deep(.p-select:focus), ::v-deep(.p-inputnumber-input:focus), ::v-deep(.p-textarea:focus) {
    border-color: var(--accent-primary) !important;
    outline: 0 !important;
}

/* Status Control Box */
.status-control {
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 4px;
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.control-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.control-info p {
    margin: 0;
    font-size: 12px;
    color: var(--text-secondary);
}

/* Upload Interface */
.upload-stage {
    height: 300px;
    border: 1px dashed var(--bg-panel-border);
    border-radius: 6px;
    background: var(--bg-panel);
    transition: all 0.3s ease;
}

.upload-stage:hover {
    border-color: var(--text-secondary);
}

.upload-dropzone {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
}

.upload-prompt {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.upload-prompt i {
    font-size: 2rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.upload-prompt h5 {
    margin: 0;
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
}

.upload-prompt span {
    font-size: 12px;
    color: var(--text-muted);
}

.preview-wrapper {
    width: 100%;
    height: 100%;
    padding: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.asset-preview-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.overlay-actions {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.preview-wrapper:hover .overlay-actions {
    opacity: 1;
}

/* Footer */
.slate-modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2.5rem;
    background: var(--bg-panel);
    border-top: 1px solid var(--bg-panel-border);
}

.sys-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: var(--text-secondary);
}

.action-buttons-group {
    display: flex;
    gap: 1rem;
}

.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .catalog-header { flex-direction: column; align-items: flex-start; }
    .header-right { width: 100%; flex-direction: column; }
    .search-wrapper { width: 100%; }
    
    ::v-deep(.slate-modal) { width: 95vw !important; margin: 0 !important; }
    .slate-form-grid { grid-template-columns: 1fr; }
    .col-span-2 { grid-column: span 1; }
    .slate-modal-header, .slate-tabs, .slate-modal-body, .slate-modal-footer { padding: 1.5rem; }
    .slate-tabs { overflow-x: auto; white-space: nowrap; }
}
</style>