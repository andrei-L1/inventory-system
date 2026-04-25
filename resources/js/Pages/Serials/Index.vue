<template>
    <Head title="Serial Registry" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 min-h-screen bg-deep">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black text-primary tracking-tight font-mono">Serial Registry</h1>
                    <p class="text-[11px] text-muted font-mono mt-1">Unit-level serial traceability — track every serialized item from receipt to sale.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-mono font-black text-violet-400 uppercase tracking-widest bg-violet-500/10 border border-violet-500/20 px-3 py-1.5 rounded-lg">Phase 6.3</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-4 gap-4">
                <InputText v-model="filters.serial_number" placeholder="Search serial #..." @input="debouncedLoad"
                    class="!bg-panel !border-panel-border !rounded-xl !h-10 !px-3 !text-xs text-primary !w-full focus:!border-violet-500/50 transition-all" />

                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="All Statuses" showClear @change="loadSerials"
                    class="!bg-panel !border-panel-border !rounded-xl !h-10 !w-full font-mono"
                    :pt="{ input: { class: '!text-xs !text-primary !px-3' } }" />

                <Select v-model="filters.product_id" :options="products" optionLabel="name" optionValue="id"
                    placeholder="All Products" showClear filter filterPlaceholder="Search products..." @change="loadSerials"
                    class="!bg-panel !border-panel-border !rounded-xl !h-10 !w-full font-mono"
                    :pt="{ input: { class: '!text-xs !text-primary !px-3' } }" />

                <Select v-model="filters.location_id" :options="locations" optionLabel="name" optionValue="id"
                    placeholder="All Locations" showClear filter @change="loadSerials"
                    class="!bg-panel !border-panel-border !rounded-xl !h-10 !w-full font-mono"
                    :pt="{ input: { class: '!text-xs !text-primary !px-3' } }" />
            </div>

            <!-- Table -->
            <div class="bg-panel/60 border border-panel-border rounded-2xl overflow-hidden shadow-xl">
                <DataTable
                    :value="serials"
                    :loading="loading"
                    dataKey="id"
                    class="p-datatable-sm"
                    :expandedRows="expandedRows"
                    @row-expand="onRowExpand"
                    @row-collapse="onRowCollapse"
                    scrollable
                    scrollHeight="60vh"
                    :pt="{ 
                        table: { class: 'w-full' },
                        headercell: { class: '!bg-panel-hover !border-panel-border !text-primary !text-[10px] !uppercase !font-bold !tracking-[0.15em] !py-4 !px-8' },
                        bodyrow: { class: 'hover:!bg-panel-hover !transition-all duration-200' }
                    }"
                >
                    <!-- Expand toggle -->
                    <Column expander style="width: 3rem" />

                    <Column field="serial_number" header="SERIAL #" style="min-width: 160px">
                        <template #body="{ data }">
                            <span class="font-mono text-xs font-bold text-violet-500 dark:text-violet-300">{{ data.serial_number }}</span>
                        </template>
                    </Column>

                    <Column header="PRODUCT" style="min-width: 200px">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-xs font-bold text-primary">{{ data.product?.name }}</span>
                                <span class="text-[9px] font-mono text-muted">{{ data.product?.sku }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="STATUS" style="width: 130px">
                        <template #body="{ data }">
                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded font-mono"
                                :class="{
                                    'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': data.status === 'in_stock',
                                    'bg-sky-500/10 text-sky-400 border border-sky-500/20': data.status === 'sold',
                                    'bg-amber-500/10 text-amber-400 border border-amber-500/20': data.status === 'returned',
                                    'bg-red-500/10 text-red-400 border border-red-500/20': data.status === 'damaged',
                                }">
                                {{ data.status.replace('_', ' ') }}
                            </span>
                        </template>
                    </Column>

                    <Column header="LOCATION" style="min-width: 150px">
                        <template #body="{ data }">
                            <span v-if="data.current_location" class="text-[11px] text-secondary font-mono">
                                {{ data.current_location.name }}
                            </span>
                            <span v-else class="text-[10px] text-muted italic font-mono">—</span>
                        </template>
                    </Column>

                    <Column header="REGISTERED" style="width: 140px">
                        <template #body="{ data }">
                            <span class="text-[10px] font-mono text-muted">{{ data.created_at?.split('T')[0] }}</span>
                        </template>
                    </Column>

                    <Column header="" style="width: 80px">
                        <template #body="{ data }">
                            <div class="flex gap-1.5">
                                <Button
                                    v-if="data.status === 'in_stock'"
                                    icon="pi pi-flag"
                                    size="small"
                                    severity="danger"
                                    text
                                    v-tooltip.top="'Mark as Damaged'"
                                    @click="markDamaged(data)"
                                    class="!w-7 !h-7"
                                />
                                <Button
                                    v-if="data.status === 'in_stock'"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="secondary"
                                    text
                                    v-tooltip.top="'Delete (no transactions only)'"
                                    @click="confirmDelete(data)"
                                    class="!w-7 !h-7"
                                />
                            </div>
                        </template>
                    </Column>

                    <!-- Row expansion: transaction history -->
                    <template #expansion="{ data }">
                        <div class="px-8 py-4 bg-deep/60">
                            <div v-if="!data.transaction_history || data.transaction_history.length === 0"
                                class="text-[11px] text-muted font-mono italic py-2">
                                No transaction history found for this serial.
                            </div>
                            <table v-else class="w-full text-[10px] font-mono">
                                <thead>
                                    <tr class="border-b border-panel-border/40">
                                        <th class="text-left text-muted pb-1 font-black uppercase tracking-widest pr-6">Reference</th>
                                        <th class="text-left text-muted pb-1 font-black uppercase tracking-widest pr-6">Type</th>
                                        <th class="text-left text-muted pb-1 font-black uppercase tracking-widest pr-6">Date</th>
                                        <th class="text-right text-muted pb-1 font-black uppercase tracking-widest">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="tx in data.transaction_history" :key="tx.transaction_line_id"
                                        class="border-b border-panel-border/20 hover:bg-panel/40 transition-colors">
                                        <td class="py-1.5 pr-6 text-violet-500 dark:text-violet-300 font-bold">{{ tx.reference_number }}</td>
                                        <td class="py-1.5 pr-6 text-secondary capitalize">{{ tx.transaction_type }}</td>
                                        <td class="py-1.5 pr-6 text-muted">{{ tx.transaction_date }}</td>
                                        <td class="py-1.5 text-right text-primary">{{ tx.formatted_quantity }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <template #empty>
                        <div class="flex flex-col items-center justify-center py-16 gap-3">
                            <i class="pi pi-barcode text-4xl text-muted/40"></i>
                            <p class="text-sm font-bold text-muted">No serial numbers found</p>
                            <p class="text-xs text-muted/60">Serials are registered when receiving POs with serial numbers.</p>
                        </div>
                    </template>
                </DataTable>

                <!-- Pagination -->
                <div v-if="pagination.total > 0" class="flex items-center justify-between px-4 py-3 border-t border-panel-border/50">
                    <span class="text-[10px] font-mono text-muted">
                        Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} serials
                    </span>
                    <div class="flex gap-1">
                        <Button label="Prev" size="small" text :disabled="pagination.current_page <= 1"
                            @click="goToPage(pagination.current_page - 1)"
                            class="!text-xs !px-3 !py-1" />
                        <Button label="Next" size="small" text :disabled="pagination.current_page >= pagination.last_page"
                            @click="goToPage(pagination.current_page + 1)"
                            class="!text-xs !px-3 !py-1" />
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog />
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const confirm = useConfirm();
const toast = useToast();

const serials    = ref([]);
const loading    = ref(false);
const products   = ref([]);
const locations  = ref([]);
const expandedRows = ref([]);

const filters = ref({
    serial_number: '',
    status: null,
    product_id: null,
    location_id: null,
});

const pagination = ref({
    total: 0, from: 0, to: 0, current_page: 1, last_page: 1,
});

const statusOptions = [
    { label: 'In Stock',  value: 'in_stock' },
    { label: 'Sold',      value: 'sold' },
    { label: 'Returned',  value: 'returned' },
    { label: 'Damaged',   value: 'damaged' },
];

let debounceTimer = null;
const debouncedLoad = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadSerials(), 400);
};

const loadSerials = async (page = 1) => {
    loading.value = true;
    try {
        const params = { limit: 50, page };
        if (filters.value.serial_number) params.serial_number = filters.value.serial_number;
        if (filters.value.status)        params.status = filters.value.status;
        if (filters.value.product_id)    params.product_id = filters.value.product_id;
        if (filters.value.location_id)   params.location_id = filters.value.location_id;

        const res = await axios.get('/api/serials', { params });
        serials.value = res.data.data;
        const meta = res.data.meta;
        pagination.value = {
            total: meta.total,
            from: meta.from ?? 0,
            to: meta.to ?? 0,
            current_page: meta.current_page,
            last_page: meta.last_page,
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load serials.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const goToPage = (page) => loadSerials(page);

const onRowExpand = async (event) => {
    // Lazy-load full transaction history for the expanded row
    try {
        const res = await axios.get(`/api/serials/${event.data.id}`);
        const idx = serials.value.findIndex(s => s.id === event.data.id);
        if (idx !== -1) {
            serials.value[idx] = { ...serials.value[idx], transaction_history: res.data.data.transaction_history };
        }
    } catch {
        // silently fail — history will show empty
    }
};

const onRowCollapse = () => {};

const markDamaged = async (serial) => {
    confirm.require({
        message: `Mark serial "${serial.serial_number}" as DAMAGED? This removes it from active stock.`,
        header: 'Mark as Damaged',
        icon: 'pi pi-exclamation-triangle',
        rejectClass: 'p-button-secondary p-button-text',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Mark Damaged',
        accept: async () => {
            try {
                await axios.patch(`/api/serials/${serial.id}`, { status: 'damaged' });
                toast.add({ severity: 'warn', summary: 'Marked Damaged', detail: serial.serial_number, life: 3000 });
                loadSerials(pagination.value.current_page);
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Failed', detail: e.response?.data?.message, life: 3000 });
            }
        },
    });
};

const confirmDelete = (serial) => {
    confirm.require({
        message: `Delete serial "${serial.serial_number}"? This cannot be undone.`,
        header: 'Delete Serial',
        icon: 'pi pi-trash',
        rejectClass: 'p-button-secondary p-button-text',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Delete',
        accept: async () => {
            try {
                await axios.delete(`/api/serials/${serial.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: serial.serial_number, life: 3000 });
                loadSerials(pagination.value.current_page);
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Cannot Delete', detail: e.response?.data?.message, life: 3000 });
            }
        },
    });
};

const loadMasterData = async () => {
    try {
        const [prodRes, locRes] = await Promise.all([
            axios.get('/api/products?limit=1000&active=1'),
            axios.get('/api/locations?limit=1000'),
        ]);
        products.value  = prodRes.data.data;
        locations.value = locRes.data.data;
    } catch { /* silent */ }
};

onMounted(() => {
    loadMasterData();
    loadSerials();
});
</script>
