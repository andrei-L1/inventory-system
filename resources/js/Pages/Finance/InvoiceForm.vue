<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();

const salesOrders = ref([]);
const selectedSo = ref(null);
const loadingSOs = ref(false);

const invoiceDate = ref(new Date().toISOString().split('T')[0]);
const dueDate = ref('');

const lines = ref([]);
const submitting = ref(false);

onMounted(async () => {
    loadSalesOrders();
});

const loadSalesOrders = async () => {
    loadingSOs.value = true;
    try {
        // Only fetch SOs that are somewhat active (confirmed to shipped)
        const res = await axios.get('/api/sales-orders?limit=100'); 
        // Filter those that have shipped lines greater than 0
        salesOrders.value = res.data.data.filter(so => 
            ['confirmed', 'partially_picked', 'picked', 'partially_packed', 'packed', 'partially_shipped', 'shipped'].includes(so.status?.name)
        );
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load Sales Orders', life: 3000 });
    } finally {
        loadingSOs.value = false;
    }
};

const onSoSelect = async () => {
    if (!selectedSo.value) {
        lines.value = [];
        return;
    }

    try {
        const res = await axios.get(`/api/sales-orders/${selectedSo.value.id}`);
        const soData = res.data.data;

        lines.value = soData.lines.map(line => ({
            so_line_id: line.id,
            product_name: line.product?.name,
            uom: line.uom?.abbreviation || line.product?.uom?.abbreviation,
            ordered_qty: Number(line.ordered_qty),
            shipped_qty: Number(line.shipped_qty),
            uninvoiced_qty: Number(line.uninvoiced_qty || 0),
            unit_price: Number(line.unit_price),
            // Default invoice qty to what's left
            invoice_qty: Number(line.uninvoiced_qty || 0), 
        })).filter(l => l.uninvoiced_qty > 0); // Only shippable, uninvoiced lines can be invoiced
        
        if (lines.value.length === 0) {
            toast.add({ severity: 'warn', summary: 'No Shippable Lines', detail: 'This Sales Order has no lines that have been shipped yet.', life: 4000 });
        }

    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load SO details', life: 3000 });
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const totalAmount = computed(() => {
    return lines.value.reduce((sum, line) => sum + (line.invoice_qty * line.unit_price), 0);
});

const submitInvoice = async () => {
    if (!selectedSo.value) return;

    const payloadLines = lines.value
        .filter(l => l.invoice_qty > 0)
        .map(l => ({
            so_line_id: l.so_line_id,
            quantity: l.invoice_qty
        }));

    if (payloadLines.length === 0) {
        toast.add({ severity: 'warn', summary: 'Empty', detail: 'Must invoice at least one item.', life: 3000 });
        return;
    }

    // Format dates to YYYY-MM-DD
    const formatDate = (date) => {
        if (!date) return null;
        const d = new Date(date);
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    };

    submitting.value = true;
    try {
        await axios.post(`/api/sales-orders/${selectedSo.value.id}/invoice`, {
            invoice_date: formatDate(invoiceDate.value),
            due_date: formatDate(dueDate.value),
            lines: payloadLines
        });

        toast.add({ severity: 'success', summary: 'Success', detail: 'Draft Invoice Created Successfully', life: 3000 });
        setTimeout(() => router.visit('/finance-center'), 1500);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to generate invoice', life: 4000 });
    } finally {
        submitting.value = false;
    }
};

</script>

<template>
    <AppLayout>
        <Head title="Create Invoice" />
        <Toast />

        <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <button @click="router.visit('/finance-center')" class="bg-transparent border-none text-sky-400 hover:text-sky-300 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest mb-6 transition-colors p-0 outline-none">
                        <i class="pi pi-arrow-left text-xs"></i> Back to Finance Center
                    </button>
                    <h1 class="text-2xl font-bold text-white tracking-tight mb-1">Generate Invoice</h1>
                    <p class="text-zinc-400 text-sm">Bill a customer from an active Sales Order based on shipped quantities.</p>
                </div>
            </div>

            <!-- Form Details -->
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 mb-6 shadow-xl">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Select Sales Order</label>
                        <Select v-model="selectedSo" :options="salesOrders" optionLabel="so_number" placeholder="Choose SO" 
                                class="w-full bg-zinc-950 border-zinc-800 text-white" 
                                :loading="loadingSOs" filter 
                                @change="onSoSelect" />
                    </div>

                    <div v-if="selectedSo">
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Invoice Date</label>
                        <input type="date" v-model="invoiceDate" class="w-full bg-zinc-950 border border-zinc-800 text-white p-2.5 rounded-lg text-sm focus:border-sky-500 outline-none" />
                    </div>

                    <div v-if="selectedSo">
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Due Date (Optional)</label>
                        <input type="date" v-model="dueDate" class="w-full bg-zinc-950 border border-zinc-800 text-white p-2.5 rounded-lg text-sm focus:border-sky-500 outline-none" />
                    </div>
                </div>

                <div v-if="selectedSo" class="mt-6 border-t border-zinc-800 pt-6">
                    <div class="flex items-center gap-3">
                        <span class="text-zinc-400 text-sm">Customer:</span>
                        <span class="text-white font-bold">{{ selectedSo.customer?.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Lines -->
            <div v-if="selectedSo && lines.length > 0" class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 mb-6 shadow-xl">
                <h3 class="text-white font-bold tracking-tight mb-4 flex items-center gap-2">
                    <i class="pi pi-box text-sky-400"></i>
                    Shipped Lines for Invoicing
                </h3>

                <DataTable :value="lines" class="p-datatable-sm bg-transparent">
                    <Column header="Product">
                        <template #body="{ data }">
                            <span class="text-sm text-zinc-200 font-bold">{{ data.product_name }}</span>
                        </template>
                    </Column>
                    <Column header="Shipped Qty" style="width: 15%">
                        <template #body="{ data }">
                            <span class="text-sm font-mono text-emerald-400">{{ data.shipped_qty }} {{ data.uom }}</span>
                        </template>
                    </Column>
                    <Column header="Unit Price" style="width: 15%">
                        <template #body="{ data }">
                            <span class="text-sm font-mono text-zinc-300">{{ formatCurrency(data.unit_price) }}</span>
                        </template>
                    </Column>
                    <Column header="Invoice Qty" style="width: 20%">
                        <template #body="{ data }">
                            <InputNumber v-model="data.invoice_qty" :min="0" :max="data.uninvoiced_qty" :minFractionDigits="0" :maxFractionDigits="4" 
                                         class="w-full max-w-[120px]" inputClass="bg-zinc-950 border-zinc-700 text-white text-right text-xs" />
                        </template>
                    </Column>
                    <Column header="Subtotal" style="width: 15%" class="text-right">
                        <template #body="{ data }">
                            <span class="text-sm font-mono font-bold text-sky-400">{{ formatCurrency(data.invoice_qty * data.unit_price) }}</span>
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-6 flex justify-end">
                    <div class="bg-zinc-950 border border-zinc-800 rounded-lg p-4 w-64">
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-500 text-xs font-bold uppercase tracking-widest">Total Invoice</span>
                            <span class="text-xl font-bold font-mono text-white">{{ formatCurrency(totalAmount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State / Generate Button -->
            <div class="flex justify-end gap-4" v-if="selectedSo">
                <button @click="router.visit('/finance-center')" class="px-6 py-3 rounded-lg font-bold text-xs uppercase tracking-widest text-zinc-400 hover:text-white transition-colors">
                    Cancel
                </button>
                <button @click="submitInvoice" :disabled="submitting || lines.length === 0" 
                        class="px-6 py-3 rounded-lg font-bold text-xs uppercase tracking-widest bg-sky-500 hover:bg-sky-400 text-zinc-950 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <i v-if="submitting" class="pi pi-spinner pi-spin"></i>
                    Generate Draft Invoice
                </button>
            </div>
        </div>
    </AppLayout>
</template>
