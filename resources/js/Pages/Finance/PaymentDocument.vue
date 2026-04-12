<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toast from 'primevue/toast';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    id: { type: [String, Number], required: true }
});

const toast = useToast();
const payment = ref(null);
const loading = ref(true);

const showAllocateDialog = ref(false);
const openInvoices = ref([]);
const allocations = ref([]);
const loadingInvoices = ref(false);
const submitting = ref(false);

// Refund dialog state
const showRefundDialog = ref(false);
const submittingRefund = ref(false);
const refundMethods = ['Bank Transfer', 'Cash', 'Credit Card', 'Check', 'Other'];
const refundForm = ref({
    amount: 0,
    refund_date: new Date().toISOString().split('T')[0],
    method: 'Bank Transfer',
    reference_number: '',
    notes: ''
});

const loadPayment = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/payments/${props.id}`);
        payment.value = res.data.data ? res.data.data : res.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load Payment Record', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadPayment();
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const printDocument = () => {
    window.open(window.location.href + '/print', '_blank');
};

const openAllocateDialog = async () => {
    showAllocateDialog.value = true;
    loadingInvoices.value = true;
    try {
        const res = await axios.get('/api/invoices', {
            params: { customer_id: payment.value.customer_id, status: 'OPEN' }
        });
        
        openInvoices.value = res.data.data;
        allocations.value = openInvoices.value.map(inv => ({
            invoice_id: inv.id,
            invoice_number: inv.invoice_number,
            invoice_date: inv.invoice_date,
            total_amount: Number(inv.total_amount),
            balance: Number(inv.balance_due),
            amountToApply: 0
        }));
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load open invoices', life: 3000 });
    } finally {
        loadingInvoices.value = false;
    }
};

const totalAllocated = computed(() => {
    return allocations.value.reduce((sum, item) => sum + (Number(item.amountToApply) || 0), 0);
});

const unallocatedRemaining = computed(() => {
    return Math.max(0, Number(payment.value.unallocated_amount) - totalAllocated.value);
});

const isOveralllocated = computed(() => {
    return totalAllocated.value > Number(payment.value.unallocated_amount);
});

const autoAllocate = () => {
    if (payment.value.unallocated_amount <= 0) return;
    
    let remainingToAllocate = Number(payment.value.unallocated_amount);
    
    const sorted = [...allocations.value].sort((a,b) => new Date(a.invoice_date) - new Date(b.invoice_date));
    
    for (const item of sorted) {
        item.amountToApply = 0; // reset
        if (remainingToAllocate <= 0) continue;
        
        const applyParams = Math.min(item.balance, remainingToAllocate);
        item.amountToApply = applyParams;
        remainingToAllocate -= applyParams;
    }
    
    for (const item of allocations.value) {
        const updated = sorted.find(s => s.invoice_id === item.invoice_id);
        if (updated) item.amountToApply = updated.amountToApply;
    }
};

const submitAllocation = async () => {
    if (isOveralllocated.value) {
        toast.add({ severity: 'error', summary: 'Invalid Allocation', detail: 'You cannot allocate more than the remaining unallocated credit.', life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        const itemsToAllocate = allocations.value.filter(a => a.amountToApply > 0).map(a => ({
            invoice_id: a.invoice_id,
            amount: a.amountToApply
        }));

        if (itemsToAllocate.length > 0) {
            await axios.post(`/api/payments/${payment.value.id}/allocate`, {
                allocations: itemsToAllocate
            });
            toast.add({ severity: 'success', summary: 'Success', detail: 'Remaining credit allocated successfully.', life: 3000 });
            showAllocateDialog.value = false;
            loadPayment();
        } else {
            toast.add({ severity: 'warn', summary: 'No Action', detail: 'No amounts were allocated.', life: 3000 });
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Allocation failed', life: 4000 });
    } finally {
        submitting.value = false;
    }
};

const openRefundDialog = () => {
    refundForm.value = {
        amount: Number(payment.value.unallocated_amount),
        refund_date: new Date().toISOString().split('T')[0],
        refund_method: 'Bank Transfer',
        reference_number: '',
        notes: ''
    };
    showRefundDialog.value = true;
};

const submitRefund = async () => {
    if (!refundForm.value.amount || refundForm.value.amount <= 0) {
        toast.add({ severity: 'warn', summary: 'Invalid', detail: 'Refund amount must be greater than 0.', life: 3000 });
        return;
    }
    if (refundForm.value.amount > Number(payment.value.unallocated_amount)) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Refund amount exceeds available unallocated credit.', life: 4000 });
        return;
    }

    submittingRefund.value = true;
    try {
        await axios.post(`/api/payments/${payment.value.id}/refund`, {
            amount: refundForm.value.amount,
            refund_date: refundForm.value.refund_date,
            refund_method: refundForm.value.refund_method,
            reference_number: refundForm.value.reference_number,
            notes: refundForm.value.notes,
        });
        toast.add({ severity: 'success', summary: 'Refund Issued', detail: 'Cash refund has been recorded successfully.', life: 3000 });
        showRefundDialog.value = false;
        loadPayment();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Refund failed.', life: 4000 });
    } finally {
        submittingRefund.value = false;
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="payment ? payment.payment_number : 'Payment Record'" />
        <Toast />

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header / Actions -->
            <div class="mb-6 flex justify-between items-end">
                <div v-if="!loading">
                    <button @click="router.visit('/finance-center')" class="bg-transparent border-none text-sky-400 hover:text-sky-300 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest mb-6 transition-colors p-0 outline-none">
                        <i class="pi pi-arrow-left text-xs"></i> Back to Finance Center
                    </button>
                    <div class="flex items-center gap-4">
                        <h1 class="text-2xl font-bold text-white tracking-tight mb-0">Payment: {{ payment?.payment_number }}</h1>
                        <div class="inline-flex px-2 py-0.5 rounded border text-[10px] font-bold tracking-[0.1em] font-mono border-emerald-500/30 text-emerald-400 bg-emerald-500/10">
                            RECEIVED
                        </div>
                    </div>
                </div>
                <div v-if="!loading" class="flex items-center gap-3">
                    <button @click="printDocument" class="bg-zinc-800 hover:bg-zinc-700 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                        <i class="pi pi-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- Loader -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-24 text-zinc-500">
                <i class="pi pi-spinner pi-spin text-4xl mb-4 text-sky-500"></i>
                <p class="font-mono text-xs tracking-widest uppercase animate-pulse">Loading Payment Record...</p>
            </div>

            <!-- Voucher -->
            <div v-else-if="payment" class="bg-white text-zinc-900 rounded-xl shadow-2xl overflow-hidden">

                <!-- Header Band -->
                <div class="flex justify-between items-start p-10 border-b-2 border-zinc-200">
                    <div>
                        <h2 class="text-4xl font-black tracking-tighter text-zinc-900 uppercase">Payment Receipt</h2>
                        <p class="text-zinc-500 font-mono text-sm mt-2">{{ payment.payment_number }}</p>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-xl text-zinc-900">Nexus Logistics Corp.</div>
                        <div class="text-sm text-zinc-500 mt-1">123 Corporate Ave, Matrix City</div>
                        <div class="text-sm text-zinc-500 uppercase font-mono tracking-tighter">TAX ID: {{ payment?.company_tax_id || 'N/A' }}</div>
                    </div>
                </div>

                <div class="p-10">
                    <!-- Meta Grid -->
                    <div class="grid grid-cols-2 gap-12 mb-8">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-2">Received From</div>
                            <div class="font-bold text-lg text-zinc-900">{{ payment.customer?.name }}</div>
                            <div class="text-sm text-zinc-600 mt-1 uppercase font-mono">{{ payment.customer?.customer_code }}</div>
                            <div class="text-xs text-zinc-500 mt-2">{{ payment.customer?.billing_address || 'Billing Address not specified (N/A)' }}</div>
                            <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1">TIN: {{ payment.customer?.tax_number || 'N/A' }}</div>
                        </div>
                        <div class="bg-zinc-50 rounded-lg p-4 border border-zinc-100 grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Payment Date</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">{{ payment.payment_date }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Method</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">{{ payment.payment_method || 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Reference #</div>
                                <div class="font-mono text-sm font-bold text-zinc-800">{{ payment.reference_number || '—' }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Total Amount</div>
                                <div class="font-mono text-sm font-bold text-emerald-700">{{ formatCurrency(payment.amount) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Allocations & Refunds Table -->
                    <div class="mb-8">
                        <div class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-3">Ledger Activity</div>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-y-2 border-zinc-200">
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-zinc-500">Type</th>
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-zinc-500">Reference</th>
                                    <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-zinc-500 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr v-if="!payment.allocations?.length && !payment.refunds?.length">
                                    <td colspan="3" class="py-8 text-center text-zinc-400 text-sm italic">No activity — payment is fully unallocated</td>
                                </tr>
                                <!-- Allocation rows -->
                                <tr v-for="alloc in payment.allocations" :key="'alloc-' + alloc.id"
                                    class="text-sm cursor-pointer hover:bg-zinc-50 transition-colors"
                                    @click="router.visit('/finance/invoices/' + alloc.invoice_id)">
                                    <td class="py-3 px-2">
                                        <span class="text-[9px] font-bold uppercase tracking-widest bg-sky-50 text-sky-600 border border-sky-200 px-2 py-0.5 rounded">Allocation</span>
                                    </td>
                                    <td class="py-3 px-2 font-mono font-bold text-sky-700">{{ alloc.invoice?.invoice_number }}</td>
                                    <td class="py-3 px-2 font-mono font-bold text-zinc-900 text-right">{{ formatCurrency(alloc.amount) }}</td>
                                </tr>
                                <!-- Refund rows -->
                                <tr v-for="refund in payment.refunds" :key="'ref-' + refund.id" class="text-sm">
                                    <td class="py-3 px-2">
                                        <span class="text-[9px] font-bold uppercase tracking-widest bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 rounded">Refund</span>
                                    </td>
                                    <td class="py-3 px-2">
                                        <div class="font-mono font-bold text-rose-700">{{ refund.refund_number }}</div>
                                        <div class="text-[10px] text-zinc-400">{{ refund.refund_date }} · {{ refund.method }}</div>
                                    </td>
                                    <td class="py-3 px-2 font-mono font-bold text-rose-700 text-right">- {{ formatCurrency(refund.amount) }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="payment.allocations?.length || payment.refunds?.length || Number(payment.unallocated_amount) > 0">
                                <tr class="border-t-2 border-zinc-200">
                                    <td colspan="2" class="py-3 px-2 text-xs font-black uppercase tracking-widest text-zinc-900">
                                        <div class="flex items-center gap-2">
                                            Unallocated Credit
                                            <button v-if="Number(payment.unallocated_amount) > 0" @click="openAllocateDialog" class="px-2 py-0.5 bg-sky-500 text-white rounded font-bold text-[9px] hover:bg-sky-400 print:hidden transition-colors">
                                                ALLOCATE
                                            </button>
                                            <button v-if="Number(payment.unallocated_amount) > 0" @click="openRefundDialog" class="px-2 py-0.5 bg-rose-500 text-white rounded font-bold text-[9px] hover:bg-rose-400 print:hidden transition-colors">
                                                ISSUE REFUND
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 font-mono font-black text-right" :class="Number(payment.unallocated_amount) > 0 ? 'text-amber-600' : 'text-zinc-400'">
                                        {{ formatCurrency(payment.unallocated_amount) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="py-3 px-2 text-xs font-black uppercase tracking-widest text-zinc-900">Total Received</td>
                                    <td class="py-3 px-2 font-mono font-black text-lg text-emerald-700 text-right">{{ formatCurrency(payment.amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Notes -->
                    <div v-if="payment.notes" class="bg-zinc-50 border border-zinc-200 rounded-lg p-4">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">Notes</div>
                        <p class="text-sm text-zinc-600 italic m-0">{{ payment.notes }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Allocate Remaining Credit Dialog -->
        <Dialog v-model:visible="showAllocateDialog" modal :style="{ width: '50rem' }" class="p-fluid">
            <template #header>
                <div class="flex flex-col">
                    <span class="text-xl font-bold text-white tracking-tight">Allocate Remaining Credit</span>
                    <span class="text-xs text-zinc-400">Apply leftover funds to open invoices for {{ payment?.customer?.name }}</span>
                </div>
            </template>

            <div v-if="isOveralllocated" class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 font-mono text-sm mt-4">
                <i class="pi pi-exclamation-triangle"></i>
                <span>Allocation Exceeds Remaining Credit! Please review manual allocations.</span>
            </div>

            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 shadow-xl flex items-center justify-between mb-6 mt-4">
                <div class="flex gap-8">
                    <div class="flex flex-col">
                        <span class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1">To Allocate</span>
                        <span class="text-white font-mono font-bold">{{ formatCurrency(totalAllocated) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1">Unallocated Credit left</span>
                        <span class="font-mono font-bold" :class="unallocatedRemaining > 0 ? 'text-amber-400' : 'text-zinc-600'">
                            {{ formatCurrency(unallocatedRemaining) }}
                        </span>
                    </div>
                </div>
                <button @click="autoAllocate" v-if="allocations.length > 0" class="px-4 py-2 bg-sky-500/10 border border-sky-500/20 text-sky-400 hover:bg-sky-500 hover:text-white rounded-lg text-xs font-bold uppercase tracking-widest transition-colors">
                    Auto Allocate
                </button>
            </div>

            <div v-if="loadingInvoices" class="flex justify-center p-8 text-zinc-500">
                <i class="pi pi-spinner pi-spin text-3xl"></i>
            </div>
            <div v-else-if="allocations.length === 0" class="p-8 text-center text-zinc-500 border border-dashed border-zinc-800 rounded-xl">
                This customer has no other open invoices to allocate funds to!
            </div>
            <div v-else class="border border-zinc-800 rounded-lg overflow-hidden h-[300px] overflow-y-auto">
                <DataTable :value="allocations" class="p-datatable-sm bg-transparent border-none">
                    <Column header="Invoice">
                        <template #body="{ data }">
                            <div class="flex flex-col py-1">
                                <span class="text-sm text-zinc-200 font-bold bg-zinc-950 px-2 py-1 rounded border border-zinc-800 self-start mb-1">{{ data.invoice_number }}</span>
                                <span class="text-[10px] text-zinc-500 ml-1">{{ data.invoice_date }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Balance Due" style="width: 25%">
                        <template #body="{ data }">
                            <span class="text-sm font-mono font-bold text-rose-400">{{ formatCurrency(data.balance) }}</span>
                        </template>
                    </Column>
                    <Column header="Apply Amount" style="width: 35%" class="text-right">
                        <template #body="{ data }">
                            <InputNumber v-model="data.amountToApply" :min="0" :max="data.balance" :minFractionDigits="2" :maxFractionDigits="2"
                                         class="w-full" inputClass="bg-zinc-950 border-zinc-700 text-sky-400 font-bold text-right text-sm w-full focus:border-sky-500" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <template #footer>
                <button @click="showAllocateDialog = false" class="px-4 py-2 text-zinc-400 hover:text-white font-bold text-xs uppercase tracking-widest transition-colors">Cancel</button>
                <button @click="submitAllocation" :disabled="submitting || totalAllocated <= 0 || isOveralllocated" class="ml-4 px-6 py-2 bg-emerald-500 text-zinc-950 hover:bg-emerald-400 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors shadow-lg disabled:opacity-50 flex items-center gap-2">
                    <i v-if="submitting" class="pi pi-spinner pi-spin"></i>
                    Confirm Allocation
                </button>
            </template>
        </Dialog>

        <!-- Issue Refund Dialog -->
        <Dialog v-model:visible="showRefundDialog" modal :style="{ width: '32rem' }" class="p-fluid">
            <template #header>
                <div class="flex flex-col">
                    <span class="text-xl font-bold text-white tracking-tight">Issue Cash Refund</span>
                    <span class="text-xs text-zinc-400">Return unallocated credit to {{ payment?.customer?.name }}</span>
                </div>
            </template>

            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mt-4 mb-6">
                <div class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">Available Unallocated Credit</div>
                <div class="text-2xl font-mono font-black text-amber-400">{{ formatCurrency(payment?.unallocated_amount) }}</div>
            </div>

            <div class="space-y-4">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Refund Amount *</label>
                    <InputNumber v-model="refundForm.amount" :min="0.01" :max="Number(payment?.unallocated_amount)" :minFractionDigits="2" :maxFractionDigits="2"
                                 inputClass="!bg-zinc-900 !border-zinc-700 !text-rose-400 !font-bold !text-lg" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Refund Date *</label>
                        <input type="date" v-model="refundForm.refund_date"
                               class="h-11 bg-zinc-900 border border-zinc-700 text-white rounded-lg px-3 text-sm focus:border-rose-500 outline-none" />
                    </div>
                    <div class="col-span-12 flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest font-mono">Refund Method</label>
                        <Select v-model="refundForm.refund_method" :options="['Bank Transfer', 'Cash', 'Check', 'Credit Card']" 
                                class="!bg-zinc-950 !border-zinc-800 !text-zinc-300" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Reference / Check #</label>
                    <InputText v-model="refundForm.reference_number" placeholder="e.g. CHK-001234"
                               class="!bg-zinc-900 !border-zinc-700 !text-zinc-300 !h-11" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Notes</label>
                    <InputText v-model="refundForm.notes" placeholder="Reason for refund..."
                               class="!bg-zinc-900 !border-zinc-700 !text-zinc-300 !h-11" />
                </div>
            </div>

            <template #footer>
                <button @click="showRefundDialog = false" class="px-4 py-2 text-zinc-400 hover:text-white font-bold text-xs uppercase tracking-widest transition-colors">Cancel</button>
                <button @click="submitRefund" :disabled="submittingRefund || !refundForm.amount || refundForm.amount <= 0"
                        class="ml-4 px-6 py-2 bg-rose-500 text-white hover:bg-rose-400 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors shadow-lg disabled:opacity-50 flex items-center gap-2">
                    <i v-if="submittingRefund" class="pi pi-spinner pi-spin"></i>
                    <i v-else class="pi pi-undo"></i>
                    Issue Refund
                </button>
            </template>
        </Dialog>
    </AppLayout>
</template>


