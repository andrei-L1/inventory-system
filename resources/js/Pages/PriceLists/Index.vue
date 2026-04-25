<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { usePermissions } from '@/Composables/usePermissions';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import axios from 'axios';

const toast = useToast();
const confirm = useConfirm();
const { can } = usePermissions();

// ── State ──────────────────────────────────────────────────────────────────────
const priceLists = ref([]);
const loading = ref(false);
const search = ref('');

const listDialog = ref(false);
const listForm = ref({ id: null, name: '', currency: 'PHP', is_active: true });
const listLoading = ref(false);

const itemDialog = ref(false);
const selectedList = ref(null);
const products = ref([]);
const itemForm = ref({ product_id: null, price: null, min_quantity: 0 });
const itemLoading = ref(false);

// ── Load ───────────────────────────────────────────────────────────────────────
const load = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/price-lists?limit=100');
        priceLists.value = res.data.data;
    } catch { /* silent */ } finally {
        loading.value = false;
    }
};

const loadProducts = async () => {
    const res = await axios.get('/api/products?limit=1000');
    products.value = res.data.data;
};

onMounted(() => { load(); loadProducts(); });

// ── Computed ───────────────────────────────────────────────────────────────────
const filtered = computed(() => {
    if (!search.value) return priceLists.value;
    const q = search.value.toLowerCase();
    return priceLists.value.filter(l => l.name.toLowerCase().includes(q));
});

// ── List CRUD ──────────────────────────────────────────────────────────────────
const openCreateList = () => {
    listForm.value = { id: null, name: '', currency: 'PHP', is_active: true };
    listDialog.value = true;
};

const openEditList = (list) => {
    listForm.value = { id: list.id, name: list.name, currency: list.currency, is_active: list.is_active };
    listDialog.value = true;
};

const saveList = async () => {
    if (!listForm.value.name) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Name is required.', life: 3000 });
        return;
    }
    listLoading.value = true;
    try {
        if (listForm.value.id) {
            await axios.put(`/api/price-lists/${listForm.value.id}`, listForm.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Price list updated.', life: 3000 });
        } else {
            await axios.post('/api/price-lists', listForm.value);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Price list created.', life: 3000 });
        }
        listDialog.value = false;
        load();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed.', life: 3000 });
    } finally {
        listLoading.value = false;
    }
};

const deleteList = (list) => {
    confirm.require({
        message: `Delete price list "${list.name}"? This cannot be undone.`,
        header: 'Delete Price List',
        icon: 'pi pi-trash',
        rejectClass: 'p-button-secondary p-button-text',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Delete',
        accept: async () => {
            try {
                await axios.delete(`/api/price-lists/${list.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Cannot Delete', detail: e.response?.data?.message, life: 4000 });
            }
        }
    });
};

// ── Items (Prices) ─────────────────────────────────────────────────────────────
const openItems = async (list) => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/price-lists/${list.id}`);
        selectedList.value = res.data.data;
        itemForm.value = { product_id: null, price: null, min_quantity: 0 };
        itemDialog.value = true;
    } catch { /* silent */ } finally {
        loading.value = false;
    }
};

const upsertItem = async () => {
    if (!itemForm.value.product_id || itemForm.value.price === null) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Product and Price are required.', life: 3000 });
        return;
    }
    itemLoading.value = true;
    try {
        await axios.post(`/api/price-lists/${selectedList.value.id}/items`, {
            product_id: itemForm.value.product_id,
            price: itemForm.value.price,
            min_quantity: itemForm.value.min_quantity || 0,
        });
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Price entry saved.', life: 3000 });
        itemForm.value = { product_id: null, price: null, min_quantity: 0 };
        // Refresh list items
        const res = await axios.get(`/api/price-lists/${selectedList.value.id}`);
        selectedList.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed.', life: 3000 });
    } finally {
        itemLoading.value = false;
    }
};

const removeItem = async (item) => {
    try {
        await axios.delete(`/api/price-lists/${selectedList.value.id}/items/${item.id}`);
        toast.add({ severity: 'success', summary: 'Removed', life: 2000 });
        const res = await axios.get(`/api/price-lists/${selectedList.value.id}`);
        selectedList.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message, life: 3000 });
    }
};

const CURRENCIES = ['PHP', 'USD', 'EUR', 'GBP', 'JPY', 'CNY'];
</script>

<template>
    <Head title="Price Lists" />
    <AppLayout>
        <div class="h-full flex flex-col gap-6 max-w-7xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-panel/40 border border-panel-border/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-72 h-72 bg-violet-500/5 blur-[120px] pointer-events-none"></div>
                <div class="z-10">
                    <h1 class="text-primary text-xl font-bold tracking-tight">Price Lists</h1>
                    <p class="text-secondary text-[10px] font-bold tracking-[0.2em] uppercase font-mono mt-1">Customer-specific pricing tiers · Quantity breaks</p>
                </div>
                <Button v-if="can('manage-sales-orders')" icon="pi pi-plus" label="New Price List"
                    class="z-10 !bg-violet-500 hover:!bg-violet-400 !border-none !text-white font-bold uppercase tracking-widest text-xs shadow-[0_0_20px_rgba(139,92,246,0.3)] transition-all"
                    @click="openCreateList" />
            </div>

            <!-- Table card -->
            <div class="bg-panel/40 border border-panel-border/80 rounded-2xl shadow-xl overflow-hidden flex flex-col flex-1">
                <div class="flex items-center gap-4 px-5 py-4 border-b border-panel-border/50">
                    <i class="pi pi-search text-muted text-sm"></i>
                    <input v-model="search" placeholder="Search price lists..." class="bg-transparent text-primary text-sm outline-none flex-1 placeholder:text-muted/60 font-mono" />
                    <span class="text-[10px] font-mono text-muted font-bold">{{ filtered.length }} lists</span>
                </div>

                <DataTable :value="filtered" :loading="loading" rowHover stripedRows
                    class="flex-1" :pt="{ table: { class: 'w-full' } }">
                    <Column field="name" header="Name" class="!font-mono">
                        <template #body="{ data }">
                            <span class="text-primary font-bold text-sm">{{ data.name }}</span>
                        </template>
                    </Column>
                    <Column field="currency" header="Currency" style="width:100px">
                        <template #body="{ data }">
                            <span class="text-[10px] font-mono font-black text-violet-400 bg-violet-500/10 px-2 py-0.5 rounded border border-violet-500/20">{{ data.currency }}</span>
                        </template>
                    </Column>
                    <Column header="Items" style="width:80px">
                        <template #body="{ data }">
                            <span class="text-secondary text-xs font-mono">{{ (data.items || []).length }}</span>
                        </template>
                    </Column>
                    <Column header="Customers" style="width:100px">
                        <template #body="{ data }">
                            <span class="text-secondary text-xs font-mono">{{ data.customers_count ?? 0 }}</span>
                        </template>
                    </Column>
                    <Column header="Status" style="width:90px">
                        <template #body="{ data }">
                            <Tag :value="data.is_active ? 'Active' : 'Inactive'"
                                :severity="data.is_active ? 'success' : 'secondary'" class="!text-[10px] font-mono font-black uppercase" />
                        </template>
                    </Column>
                    <Column header="" style="width:140px">
                        <template #body="{ data }">
                            <div class="flex gap-1 justify-end">
                                <Button icon="pi pi-list" size="small" text v-tooltip.top="'Manage Prices'"
                                    class="!w-7 !h-7 !text-violet-400" @click="openItems(data)" />
                                <Button v-if="can('manage-sales-orders')" icon="pi pi-pencil" size="small" text
                                    class="!w-7 !h-7 !text-secondary" @click="openEditList(data)" />
                                <Button v-if="can('manage-sales-orders')" icon="pi pi-trash" size="small" text severity="danger"
                                    class="!w-7 !h-7" @click="deleteList(data)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-16 text-muted font-mono text-sm italic">No price lists yet. Create one to assign custom pricing to customers.</div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Create / Edit List Dialog -->
        <Dialog v-model:visible="listDialog" modal :header="listForm.id ? 'Edit Price List' : 'New Price List'" :style="{ width: '26rem' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Name</label>
                    <InputText v-model="listForm.name" placeholder="e.g. Wholesale, VIP, Export" class="!bg-deep !border-zinc-700 !text-secondary w-full" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Currency</label>
                    <Select v-model="listForm.currency" :options="CURRENCIES" class="!bg-deep !border-zinc-700 w-full" />
                </div>
                <div class="flex items-center gap-3">
                    <ToggleSwitch v-model="listForm.is_active" />
                    <span class="text-sm text-secondary font-mono">Active</span>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="listDialog = false" class="p-button-text !text-secondary" />
                <Button :label="listForm.id ? 'Save Changes' : 'Create'" icon="pi pi-check" @click="saveList" :loading="listLoading"
                    class="!bg-violet-500 hover:!bg-violet-400 !border-none !text-white font-bold" />
            </template>
        </Dialog>

        <!-- Manage Items Dialog -->
        <Dialog v-model:visible="itemDialog" modal header="Manage Price List Items" :style="{ width: '52rem' }">
            <div v-if="selectedList" class="flex flex-col gap-5">

                <!-- Add Item Form -->
                <div v-if="can('manage-sales-orders')" class="bg-deep/60 border border-panel-border/50 rounded-xl p-4 flex flex-col gap-3">
                    <span class="text-[10px] font-bold text-violet-400 uppercase tracking-widest font-mono">Add / Update Price</span>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Product</label>
                            <Select v-model="itemForm.product_id" :options="products" optionLabel="name" optionValue="id"
                                placeholder="Select product" filter filterPlaceholder="Search..."
                                class="!bg-panel !border-panel-border w-full" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Custom Price</label>
                            <InputNumber v-model="itemForm.price" :minFractionDigits="2" :maxFractionDigits="6" placeholder="0.00"
                                :inputStyle="{ background: 'var(--p-panel)', border: '1px solid var(--panel-border)', color: 'white', padding: '0.5rem 0.75rem', borderRadius: '0.5rem', width: '100%' }" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Min Qty (Break)</label>
                            <InputNumber v-model="itemForm.min_quantity" :min="0" :minFractionDigits="0" placeholder="0"
                                :inputStyle="{ background: 'var(--p-panel)', border: '1px solid var(--panel-border)', color: 'white', padding: '0.5rem 0.75rem', borderRadius: '0.5rem', width: '100%' }" />
                        </div>
                    </div>
                    <Button label="Save Price" icon="pi pi-check" size="small" :loading="itemLoading"
                        class="self-end !bg-violet-500 hover:!bg-violet-400 !border-none !text-white font-bold uppercase tracking-widest text-xs"
                        @click="upsertItem" />
                </div>

                <!-- Items Table -->
                <DataTable :value="selectedList.items || []" stripedRows size="small"
                    :pt="{ table: { class: 'w-full' } }">
                    <Column header="Product" class="!font-mono">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                <span class="text-[9px] text-violet-400 font-mono font-black">{{ data.sku }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Selling Price" style="width:130px">
                        <template #body="{ data }">
                            <span class="text-muted text-xs font-mono line-through">₱{{ data.selling_price ? parseFloat(data.selling_price).toFixed(2) : 'N/A' }}</span>
                        </template>
                    </Column>
                    <Column header="Custom Price" style="width:130px">
                        <template #body="{ data }">
                            <span class="text-emerald-400 font-black font-mono text-xs">₱{{ parseFloat(data.price).toFixed(2) }}</span>
                        </template>
                    </Column>
                    <Column header="Min Qty" style="width:90px">
                        <template #body="{ data }">
                            <span class="text-secondary text-xs font-mono">{{ parseFloat(data.min_quantity) > 0 ? data.min_quantity : '—' }}</span>
                        </template>
                    </Column>
                    <Column style="width:50px">
                        <template #body="{ data }">
                            <Button v-if="can('manage-sales-orders')" icon="pi pi-trash" size="small" text severity="danger"
                                class="!w-6 !h-6" @click="removeItem(data)" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-muted font-mono text-xs italic">No items yet. Add product prices above.</div>
                    </template>
                </DataTable>
            </div>
            <template #footer>
                <Button label="Done" icon="pi pi-check" @click="itemDialog = false"
                    class="!bg-violet-500 hover:!bg-violet-400 !border-none !text-white font-bold" />
            </template>
        </Dialog>
    </AppLayout>
</template>
