<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();
const salesOrders = ref([]);
const loading = ref(false);
const search = ref('');

const loadSalesOrders = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/sales-orders', { params: { query: search.value } });
        salesOrders.value = res.data.data;
    } catch (e) {
        console.error("Failed to load SOs:", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadSalesOrders();
});

const getStatusColor = (statusName) => {
    const map = {
        'quotation': 'warning',
        'quotation_sent': 'info',
        'confirmed': 'info',
        'picked': 'help',
        'packed': 'help',
        'shipped': 'success',
        'partially_shipped': 'help',
        'closed': 'success',
        'cancelled': 'danger'
    };
    return map[statusName] || 'info';
};
</script>

<template>
    <Head title="Sales - Sales Orders" />
    <AppLayout>
        <div class="flex flex-col gap-6 h-full">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-panel/40 p-6 rounded-2xl border border-panel-border/80 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 blur-[100px] pointer-events-none"></div>
                
                <div class="flex items-center gap-4 z-10">
                    <div class="w-12 h-12 rounded-xl bg-teal-500/10 flex items-center justify-center border border-teal-500/20 shadow-[0_0_15px_rgba(20,184,166,0.1)]">
                        <i class="pi pi-receipt text-xl text-teal-400"></i>
                    </div>
                    <div>
                        <h1 class="text-primary text-xl font-bold tracking-tight mb-1">Sales Orders</h1>
                        <p class="text-secondary text-[10px] font-bold tracking-[0.2em] uppercase font-mono">
                            Order-to-Fulfillment Ledger
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10">
                    <div class="relative w-full md:w-auto">
                        <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-secondary text-sm"></i>
                        <InputText 
                            v-model="search" 
                            @input="loadSalesOrders"
                            placeholder="Search orders..." 
                            class="!w-full md:!w-72 !pl-11 !pr-4 !bg-deep !border-panel-border !text-sm !text-primary focus:!border-teal-500/50 !h-11 !rounded-xl transition-all"
                        />
                    </div>
                    <Link href="/sales-orders/create" class="no-underline pb-1">
                        <Button 
                            v-if="can('manage-sales-orders')" 
                            icon="pi pi-plus" 
                            label="New Quotation" 
                            class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-primary font-bold shadow-[0_0_15px_rgba(20,184,166,0.3)] transition-all"
                        />
                    </Link>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 bg-panel/40 border border-panel-border/80 rounded-2xl flex flex-col overflow-hidden shadow-xl">
                <DataTable 
                    :value="salesOrders" 
                    :loading="loading" 
                    scrollable 
                    scrollHeight="flex"
                    class="p-datatable-sm w-full flex-1"
                    stripedRows
                    hoverableRows
                    @row-click="(e) => router.visit(`/sales-orders/${e.data.id}`)"
                >
                    <template #empty>
                        <div class="flex flex-col items-center justify-center p-12 opacity-50">
                            <i class="pi pi-inbox text-4xl text-muted mb-4"></i>
                            <span class="text-xs font-bold text-secondary uppercase tracking-widest font-mono">No sales orders found.</span>
                        </div>
                    </template>

                    <Column field="so_number" header="SO NUMBER">
                        <template #body="{ data }">
                            <span @click.stop="router.visit(`/sales-orders/${data.id}`)" class="text-teal-400 font-mono text-xs font-bold cursor-pointer hover:underline">{{ data.so_number }}</span>
                        </template>
                    </Column>

                    <Column field="customer_name" header="CUSTOMER">
                        <template #body="{ data }">
                            <span @click.stop="router.visit(`/customer-center?customer_id=${data.customer_id}`)" class="text-primary font-bold text-xs hover:text-teal-400 cursor-pointer transition-colors">{{ data.customer_name }}</span>
                        </template>
                    </Column>

                    <Column field="order_date" header="DATE">
                        <template #body="{ data }">
                            <span class="text-secondary text-xs font-mono">{{ data.order_date }}</span>
                        </template>
                    </Column>

                    <Column field="status" header="STATUS">
                        <template #body="{ data }">
                            <Tag 
                                :severity="getStatusColor(data.status.name)" 
                                :value="data.status.name.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-bold tracking-widest font-mono uppercase px-2 py-0.5 rounded"
                            />
                        </template>
                    </Column>

                    <Column field="total_amount" header="TOTAL VALUE">
                        <template #body="{ data }">
                            <span class="text-emerald-400 font-mono text-xs font-bold">₱{{ data.formatted_total_amount }}</span>
                        </template>
                    </Column>

                    <Column bodyStyle="text-align: right; width: 5rem;">
                        <template #body="{ data }">
                            <Button 
                                icon="pi pi-chevron-right" 
                                class="p-button-text p-button-rounded p-button-sm !text-secondary hover:!text-teal-400 transition-colors"
                                @click.stop="router.visit(`/sales-orders/${data.id}`)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ─── DataTable ────────────────────────────────────────────────── */
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: var(--bg-panel-hover);
    border-bottom: 1px solid var(--bg-panel-border);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 1rem;
}
:deep(.p-datatable .p-datatable-tbody > tr) {
    background: transparent;
    transition: background-color 0.2s;
    cursor: pointer;
}
:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background: var(--bg-panel-hover);
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid var(--bg-panel-border);
    color: var(--text-primary);
    padding: 1rem;
}
:deep(.p-tag) {
    background: var(--bg-panel-hover);
    color: var(--text-secondary);
    border: 1px solid var(--bg-panel-border);
}
:deep(.p-tag.p-tag-warning) {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706; /* Amber 600 - Darker for better visibility on light bg */
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.app-dark :deep(.p-tag.p-tag-warning) {
    color: #fbbf24; /* Amber 400 - Original for dark mode */
}
:deep(.p-tag.p-tag-info) {
    background: rgba(14, 165, 233, 0.1);
    color: #0284c7; /* Sky 600 */
    border: 1px solid rgba(14, 165, 233, 0.2);
}
.app-dark :deep(.p-tag.p-tag-info) {
    color: #38bdf8;
}
:deep(.p-tag.p-tag-success) {
    background: rgba(16, 185, 129, 0.1);
    color: #059669; /* Emerald 600 */
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.app-dark :deep(.p-tag.p-tag-success) {
    color: #34d399;
}
:deep(.p-tag.p-tag-danger) {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626; /* Red 600 */
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.app-dark :deep(.p-tag.p-tag-danger) {
    color: #f87171;
}
:deep(.p-tag.p-tag-help) {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed; /* Violet 600 */
    border: 1px solid rgba(139, 92, 246, 0.2);
}
.app-dark :deep(.p-tag.p-tag-help) {
    color: #a78bfa;
}
</style>


