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
import DatePicker from 'primevue/datepicker';
import axios from 'axios';

const toast = useToast();
const confirm = useConfirm();
const { can } = usePermissions();

// ── State ──────────────────────────────────────────────────────────────────────
const discounts = ref([]);
const loading = ref(false);
const search = ref('');

const dialog = ref(false);
const form = ref({
    id: null, name: '', type: 'percentage', value: null,
    start_date: null, end_date: null,
    product_id: null, category_id: null, customer_id: null,
    is_active: true
});
const formLoading = ref(false);

const products = ref([]);
const categories = ref([]);
const customers = ref([]);

// ── Load ───────────────────────────────────────────────────────────────────────
const load = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/discounts?limit=100');
        discounts.value = res.data.data;
    } catch { /* silent */ } finally {
        loading.value = false;
    }
};

const loadLookups = async () => {
    try {
        const [pRes, cRes, cuRes] = await Promise.all([
            axios.get('/api/products?limit=1000'),
            axios.get('/api/categories?limit=500'),
            axios.get('/api/customers?limit=1000'),
        ]);
        products.value = pRes.data.data;
        categories.value = cRes.data.data;
        customers.value = cuRes.data.data;
    } catch { /* silent */ }
};

onMounted(() => { load(); loadLookups(); });

// ── Computed ───────────────────────────────────────────────────────────────────
const filtered = computed(() => {
    if (!search.value) return discounts.value;
    const q = search.value.toLowerCase();
    return discounts.value.filter(d =>
        d.name.toLowerCase().includes(q) ||
        d.product?.name?.toLowerCase().includes(q) ||
        d.category?.name?.toLowerCase().includes(q) ||
        d.customer?.name?.toLowerCase().includes(q)
    );
});

const scopeLabel = (d) => {
    if (d.customer_id && d.product_id) return `${d.customer?.name} · ${d.product?.name}`;
    if (d.customer_id) return `Customer: ${d.customer?.name}`;
    if (d.product_id) return `Product: ${d.product?.name}`;
    if (d.category_id) return `Category: ${d.category?.name}`;
    return 'Global';
};

const isActive = (d) => {
    if (!d.is_active) return false;
    const now = new Date();
    if (d.start_date && new Date(d.start_date) > now) return false;
    if (d.end_date && new Date(d.end_date) < now) return false;
    return true;
};

// ── CRUD ───────────────────────────────────────────────────────────────────────
const openCreate = () => {
    form.value = { id: null, name: '', type: 'percentage', value: null, start_date: null, end_date: null, product_id: null, category_id: null, customer_id: null, is_active: true };
    dialog.value = true;
};

const openEdit = (d) => {
    form.value = {
        id: d.id, name: d.name, type: d.type, value: parseFloat(d.value),
        start_date: d.start_date ? new Date(d.start_date) : null,
        end_date: d.end_date ? new Date(d.end_date) : null,
        product_id: d.product_id, category_id: d.category_id, customer_id: d.customer_id,
        is_active: d.is_active
    };
    dialog.value = true;
};

const save = async () => {
    if (!form.value.name || form.value.value === null) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'Name and Value are required.', life: 3000 });
        return;
    }
    formLoading.value = true;
    const payload = {
        ...form.value,
        start_date: form.value.start_date ? form.value.start_date.toISOString().split('T')[0] : null,
        end_date: form.value.end_date ? form.value.end_date.toISOString().split('T')[0] : null,
    };
    try {
        if (form.value.id) {
            await axios.put(`/api/discounts/${form.value.id}`, payload);
            toast.add({ severity: 'success', summary: 'Updated', life: 2000 });
        } else {
            await axios.post('/api/discounts', payload);
            toast.add({ severity: 'success', summary: 'Created', life: 2000 });
        }
        dialog.value = false;
        load();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed.', life: 3000 });
    } finally {
        formLoading.value = false;
    }
};

const remove = (d) => {
    confirm.require({
        message: `Delete discount "${d.name}"?`,
        header: 'Delete Discount',
        icon: 'pi pi-trash',
        rejectClass: 'p-button-secondary p-button-text',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Delete',
        accept: async () => {
            try {
                await axios.delete(`/api/discounts/${d.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', life: 2000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message, life: 3000 });
            }
        }
    });
};

const TYPES = [
    { label: '% Percentage', value: 'percentage' },
    { label: '₱ Fixed Amount', value: 'fixed' },
];
</script>

<template>
    <Head title="Discounts" />
    <AppLayout>
        <div class="h-full flex flex-col gap-6 max-w-7xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between p-6 bg-panel/40 border border-panel-border/80 rounded-2xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-72 h-72 bg-rose-500/5 blur-[120px] pointer-events-none"></div>
                <div class="z-10">
                    <h1 class="text-primary text-xl font-bold tracking-tight">Discounts</h1>
                    <p class="text-secondary text-[10px] font-bold tracking-[0.2em] uppercase font-mono mt-1">Promotional rules · Product, category & customer overrides</p>
                </div>
                <Button v-if="can('manage-sales-orders')" icon="pi pi-plus" label="New Discount"
                    class="z-10 !bg-rose-500 hover:!bg-rose-400 !border-none !text-white font-bold uppercase tracking-widest text-xs shadow-[0_0_20px_rgba(244,63,94,0.3)] transition-all"
                    @click="openCreate" />
            </div>

            <!-- Table -->
            <div class="bg-panel/40 border border-panel-border/80 rounded-2xl shadow-xl overflow-hidden flex flex-col flex-1">
                <div class="flex items-center gap-4 px-5 py-4 border-b border-panel-border/50">
                    <i class="pi pi-search text-muted text-sm"></i>
                    <input v-model="search" placeholder="Search by name, product, category, customer..." class="bg-transparent text-primary text-sm outline-none flex-1 placeholder:text-muted/60 font-mono" />
                    <span class="text-[10px] font-mono text-muted font-bold">{{ filtered.length }} rules</span>
                </div>

                <DataTable :value="filtered" :loading="loading" rowHover stripedRows :pt="{ table: { class: 'w-full' } }">
                    <Column field="name" header="Name">
                        <template #body="{ data }">
                            <span class="text-primary font-bold text-sm">{{ data.name }}</span>
                        </template>
                    </Column>
                    <Column header="Value" style="width:130px">
                        <template #body="{ data }">
                            <span class="font-black font-mono text-sm" :class="data.type === 'percentage' ? 'text-rose-400' : 'text-amber-400'">
                                {{ data.label }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Scope">
                        <template #body="{ data }">
                            <span class="text-secondary text-xs font-mono">{{ scopeLabel(data) }}</span>
                        </template>
                    </Column>
                    <Column header="Validity" style="width:160px">
                        <template #body="{ data }">
                            <span class="text-muted text-[10px] font-mono">
                                {{ data.start_date || '∞' }} → {{ data.end_date || '∞' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Status" style="width:90px">
                        <template #body="{ data }">
                            <Tag :value="isActive(data) ? 'Active' : 'Inactive'"
                                :severity="isActive(data) ? 'success' : 'secondary'" class="!text-[10px] font-mono font-black uppercase" />
                        </template>
                    </Column>
                    <Column style="width:100px">
                        <template #body="{ data }">
                            <div class="flex gap-1 justify-end">
                                <Button v-if="can('manage-sales-orders')" icon="pi pi-pencil" size="small" text class="!w-7 !h-7 !text-secondary" @click="openEdit(data)" />
                                <Button v-if="can('manage-sales-orders')" icon="pi pi-trash" size="small" text severity="danger" class="!w-7 !h-7" @click="remove(data)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-16 text-muted font-mono text-sm italic">No discounts defined yet.</div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Discount Form Dialog -->
        <Dialog v-model:visible="dialog" modal :header="form.id ? 'Edit Discount' : 'New Discount'" :style="{ width: '36rem' }">
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Name</label>
                    <InputText v-model="form.name" placeholder="e.g. Summer Sale, VIP 20% off" class="!bg-deep !border-zinc-700 !text-secondary w-full" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Type</label>
                        <Select v-model="form.type" :options="TYPES" optionLabel="label" optionValue="value" class="!bg-deep !border-zinc-700 w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">
                            Value {{ form.type === 'percentage' ? '(%)' : '(₱)' }}
                        </label>
                        <InputNumber v-model="form.value" :min="0" :minFractionDigits="2" placeholder="0.00"
                            :inputStyle="{ background: '#09090b', border: '1px solid #27272a', color: 'white', padding: '0.5rem 0.75rem', borderRadius: '0.5rem', width: '100%' }" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">Start Date</label>
                        <DatePicker v-model="form.start_date" dateFormat="yy-mm-dd" placeholder="No start limit" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-secondary tracking-widest font-mono uppercase">End Date</label>
                        <DatePicker v-model="form.end_date" dateFormat="yy-mm-dd" placeholder="No end limit" class="w-full" />
                    </div>
                </div>

                <div class="bg-deep/60 border border-panel-border/50 rounded-xl p-4 flex flex-col gap-3">
                    <span class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Scope — leave blank to apply globally</span>
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Product (optional)</label>
                        <Select v-model="form.product_id" :options="products" optionLabel="name" optionValue="id"
                            placeholder="All products" filter showClear class="!bg-panel !border-panel-border w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Category (optional)</label>
                        <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id"
                            placeholder="All categories" filter showClear class="!bg-panel !border-panel-border w-full" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">Customer (optional)</label>
                        <Select v-model="form.customer_id" :options="customers" optionLabel="name" optionValue="id"
                            placeholder="All customers" filter showClear class="!bg-panel !border-panel-border w-full" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <ToggleSwitch v-model="form.is_active" />
                    <span class="text-sm text-secondary font-mono">Active</span>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="dialog = false" class="p-button-text !text-secondary" />
                <Button :label="form.id ? 'Save Changes' : 'Create'" icon="pi pi-check" @click="save" :loading="formLoading"
                    class="!bg-rose-500 hover:!bg-rose-400 !border-none !text-white font-bold" />
            </template>
        </Dialog>
    </AppLayout>
</template>
