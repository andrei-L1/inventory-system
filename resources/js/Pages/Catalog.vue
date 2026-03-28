<script setup>
import { ref, onMounted, computed } from 'vue';
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

const openNew = () => {
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
    submitted.value = false;
    dialogVisible.value = true;
};

const editProduct = (p) => {
    product.value = { ...p, 
        category_id: p.category?.id, 
        uom_id: p.uom?.id,
        preferred_vendor_id: p.preferred_vendor?.id,
        // Find the costing method id from the label/name if needed, 
        // but the API resource should ideally return the ID. 
        // For now, let's assume p.costing_method is the name 'fifo' etc.
        costing_method_id: costingMethods.value.find(m => m.name === p.costing_method)?.id
    };
    imagePreview.value = p.main_image_url;
    dialogVisible.value = true;
};

const onImageSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        product.value.image = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const saveProduct = async () => {
    submitted.value = true;
    
    // Simple validation
    if (!product.value.name || !product.value.product_code || !product.value.sku || !product.value.category_id || !product.value.uom_id || !product.value.costing_method_id) {
        return;
    }

    const formData = new FormData();
    Object.keys(product.value).forEach(key => {
        if (product.value[key] !== null && key !== 'image') {
            formData.append(key, product.value[key]);
        }
    });
    
    if (product.value.image) {
        formData.append('image', product.value.image);
    }

    // Workaround for PUT with FormData in Laravel
    if (product.value.id) {
        formData.append('_method', 'PUT');
    }

    try {
        const url = product.value.id ? `/api/products/${product.value.id}` : '/api/products';
        await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        toast.add({ severity: 'success', summary: 'Successful', detail: 'Product Saved', life: 3000 });
        dialogVisible.value = false;
        loadProducts();
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save product';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 });
    }
};

const deleteProduct = (p) => {
    confirm.require({
        message: 'Are you sure you want to delete this product? This will soft-delete the record.',
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/products/${p.id}`);
                toast.add({ severity: 'success', summary: 'Successful', detail: 'Product Deleted', life: 3000 });
                loadProducts();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Deletion failed', life: 3000 });
            }
        }
    });
};

const formatCurrency = (value) => {
    return '$' + parseFloat(value).toFixed(2);
};
</script>

<template>
    <AppLayout>
        <Head title="Product Catalog" />
        
        <div class="sharp-panel" style="display: flex; flex-direction: column; height: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h2 class="brand-title" style="margin: 0; font-size: 1.5rem;">Master Data: Catalog</h2>
                    <div style="color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.25rem;">Live Database Overview</div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <InputText v-model="search" placeholder="Filter by Name, SKU, Code..." @input="loadProducts" style="width: 300px;" />
                    <Button v-if="can('manage-products')" label="New Product" icon="pi pi-plus" class="p-button-primary" @click="openNew" />
                </div>
            </div>

            <DataTable :value="products" :loading="loading" responsiveLayout="scroll" :paginator="true" :rows="10"
                       class="p-datatable-sm sharp-table">
                
                <template #empty>
                    <div style="text-align: center; padding: 3rem; color: var(--text-secondary); letter-spacing: 0.05em;">
                        NO CATALOG RECORDS FOUND
                    </div>
                </template>

                <Column field="main_image_url" header="IMAGE" style="width: 80px;">
                    <template #body="{ data }">
                        <img v-if="data.main_image_url" :src="data.main_image_url" class="product-thumb" />
                        <div v-else class="product-thumb-placeholder"><i class="pi pi-image text-muted"></i></div>
                    </template>
                </Column>

                <Column field="product_code" header="CODE" style="min-width: 120px">
                    <template #body="{ data }">
                        <span style="font-family: monospace; font-size: 0.85rem;">{{ data.product_code }}</span>
                    </template>
                </Column>

                <Column field="sku" header="SKU" style="min-width: 140px">
                    <template #body="{ data }">
                        <span style="font-family: monospace; color: var(--accent-primary); font-weight: 600;">{{ data.sku }}</span>
                    </template>
                </Column>

                <Column field="name" header="DESIGNATION" style="min-width: 200px;"></Column>
                
                <Column field="category" header="CATEGORY" style="min-width: 150px">
                    <template #body="{ data }">
                        <Tag :value="data.category?.name || 'Uncategorized'" severity="secondary" style="border-radius: 2px; text-transform: uppercase; font-size: 0.65rem;" />
                    </template>
                </Column>
                
                <Column field="selling_price" header="UNIT PRICE" style="min-width: 120px;">
                    <template #body="{ data }">
                        <span>{{ formatCurrency(data.selling_price) }}</span>
                    </template>
                </Column>

                <Column header="STATUS" style="min-width: 120px;">
                    <template #body="{ data }">
                        <Tag :severity="data.is_active ? 'success' : 'danger'" :value="data.is_active ? 'ACTIVE' : 'OFFLINE'" style="border-radius: 2px; font-size: 0.65rem;" />
                    </template>
                </Column>
                
                <Column header="ACTIONS" style="width: 120px;" v-if="can('manage-products')">
                    <template #body="{ data }">
                        <Button icon="pi pi-pencil" class="p-button-text p-button-sm p-button-secondary" @click="editProduct(data)" />
                        <Button icon="pi pi-trash" class="p-button-text p-button-sm p-button-danger" @click="deleteProduct(data)" />
                    </template>
                </Column>
            </DataTable>
            
            <!-- Product Form Dialog -->
            <Dialog v-model:visible="dialogVisible" :header="product.id ? 'UPDATE MASTER DATA' : 'NEW MASTER DATA ENTRY'" :modal="true" :style="{ width: '800px' }">
                <div class="grid formgrid p-fluid">
                    <!-- Identity Section -->
                    <div class="field col-12 md:col-6">
                        <label class="form-label">Product Name *</label>
                        <InputText v-model="product.name" required autofocus :class="{'p-invalid': submitted && !product.name}" />
                        <small class="p-error" v-if="submitted && !product.name">Name is required.</small>
                    </div>
                    <div class="field col-12 md:col-3">
                        <label class="form-label">Internal Code *</label>
                        <InputText v-model="product.product_code" required :class="{'p-invalid': submitted && !product.product_code}" />
                    </div>
                    <div class="field col-12 md:col-3">
                        <label class="form-label">SKU *</label>
                        <InputText v-model="product.sku" required :class="{'p-invalid': submitted && !product.sku}" />
                    </div>

                    <!-- Description -->
                    <div class="field col-12">
                        <label class="form-label">Technical Description</label>
                        <Textarea v-model="product.description" rows="2" />
                    </div>

                    <!-- Classifications -->
                    <div class="field col-12 md:col-4">
                        <label class="form-label">Category *</label>
                        <Select v-model="product.category_id" :options="categories" optionLabel="name" optionValue="id" placeholder="Select Category" :class="{'p-invalid': submitted && !product.category_id}" />
                    </div>
                    <div class="field col-12 md:col-4">
                        <label class="form-label">Unit of Measure *</label>
                        <Select v-model="product.uom_id" :options="uoms" optionLabel="name" optionValue="id" placeholder="Select UOM" :class="{'p-invalid': submitted && !product.uom_id}" />
                    </div>
                    <div class="field col-12 md:col-4">
                        <label class="form-label">Costing Method *</label>
                        <Select v-model="product.costing_method_id" :options="costingMethods" optionLabel="label" optionValue="id" placeholder="Select Method" :class="{'p-invalid': submitted && !product.costing_method_id}" />
                    </div>

                    <!-- Financials & Inventory -->
                    <div class="field col-12 md:col-4">
                        <label class="form-label">Selling Price</label>
                        <InputNumber v-model="product.selling_price" mode="currency" currency="USD" locale="en-US" />
                    </div>
                    <div class="field col-12 md:col-4">
                        <label class="form-label">Reorder Point</label>
                        <InputNumber v-model="product.reorder_point" />
                    </div>
                    <div class="field col-12 md:col-4">
                        <label class="form-label">Preferred Vendor</label>
                        <Select v-model="product.preferred_vendor_id" :options="vendors" optionLabel="name" optionValue="id" placeholder="Select Vendor" />
                    </div>

                    <!-- Attachments & Status -->
                    <div class="field col-12 md:col-8">
                        <label class="form-label">Product Image</label>
                        <div class="image-upload-wrapper">
                            <img v-if="imagePreview" :src="imagePreview" class="preview-img" />
                            <div class="upload-controls">
                                <input type="file" @change="onImageSelect" accept="image/*" id="file-upload" hidden />
                                <label for="file-upload" class="upload-btn">
                                    <i class="pi pi-upload"></i> {{ imagePreview ? 'Replace Image' : 'Upload Image' }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="field col-12 md:col-4 flex flex-column justify-content-center">
                        <label class="form-label">Registry Status</label>
                        <div class="flex align-items-center gap-3">
                            <ToggleSwitch v-model="product.is_active" />
                            <span :style="{ color: product.is_active ? 'var(--accent-primary)' : 'var(--text-secondary)' }">
                                {{ product.is_active ? 'ACTIVE_SKU' : 'OFFLINE' }}
                            </span>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <Button label="Abort Operation" icon="pi pi-times" @click="dialogVisible = false" class="p-button-text p-button-secondary" />
                    <Button label="Execute Commitment" icon="pi pi-check" @click="saveProduct" class="p-button-primary" />
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.form-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
}

.product-thumb {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 2px;
    border: 1px solid var(--bg-panel-border);
}

.product-thumb-placeholder {
    width: 48px;
    height: 48px;
    background: var(--bg-deep);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed var(--bg-panel-border);
    border-radius: 2px;
}

.image-upload-wrapper {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    padding: 1rem;
    background: var(--bg-deep);
    border: 1px solid var(--bg-panel-border);
    border-radius: 2px;
}

.preview-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 2px;
}

.upload-btn {
    background: var(--bg-panel-border);
    color: var(--text-primary);
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    border-radius: 2px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.upload-btn:hover {
    background: #3f3f46;
}

/* PrimeVue Overrides */
::v-deep(.p-dialog .p-dialog-header) {
    background: var(--bg-panel);
    border-bottom: 1px solid var(--bg-panel-border);
    color: var(--text-primary);
    padding: 1.5rem;
}

::v-deep(.p-dialog .p-dialog-content) {
    background: var(--bg-panel);
    color: var(--text-primary);
    padding: 2rem;
}

::v-deep(.p-dialog .p-dialog-footer) {
    background: var(--bg-panel);
    border-top: 1px solid var(--bg-panel-border);
    padding: 1.5rem;
}

::v-deep(.sharp-table.p-datatable .p-datatable-header) { background: transparent; border: none; }
::v-deep(.sharp-table.p-datatable .p-datatable-thead > tr > th) {
    background-color: transparent;
    color: var(--text-secondary);
    border-bottom: 2px solid var(--bg-panel-border);
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    font-weight: 700;
    text-transform: uppercase;
}
::v-deep(.sharp-table.p-datatable .p-datatable-tbody > tr) { background: transparent; color: var(--text-primary); }
::v-deep(.sharp-table.p-datatable .p-datatable-tbody > tr:hover) { background: rgba(255, 255, 255, 0.03); }
::v-deep(.sharp-table.p-datatable .p-datatable-tbody > tr > td) { border-bottom: 1px solid var(--bg-panel-border); padding: 1rem; }
</style>
