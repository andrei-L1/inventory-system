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

// Parity: Allocation interface
const showAllocateDialog = ref(false);
const openBills = ref([]);
const allocateItems = ref([]); // { bill_id, amount }
const loadingBills = ref(false);
const submitting = ref(false);

// Refund dialog state
const showRefundDialog = ref(false);
const submittingRefund = ref(false);
const refundMethods = ['Bank Transfer', 'Cash', 'Credit Card', 'Check', 'Other'];
const refundForm = ref({
    amount: 0,
    refund_date: new Date().toISOString().split('T')[0],
    refund_method: 'Bank Transfer',
    reference_number: '',
    notes: ''
});

const fetchPayment = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/vendor-payments/${props.id}`);
        // Handle both simple objects and wrapped Resource objects
        payment.value = response.data.data ? response.data.data : response.data;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not fetch disbursement details', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const openAllocateDialog = async () => {
    showAllocateDialog.value = true;
    loadingBills.value = true;
    try {
        const res = await axios.get('/api/bills', {
            params: { vendor_id: payment.value.vendor_id, status: 'POSTED' }
        });
        openBills.value = res.data.data.filter(b => Number(b.balance_due) > 0);
        allocateItems.value = openBills.value.map(b => ({
            bill_id: b.id,
            bill_number: b.bill_number,
            bill_date: b.bill_date,
            balance_due: Number(b.balance_due),
            amount: 0
        }));
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load outstanding bills.' });
    } finally {
        loadingBills.value = false;
    }
};

const totalAllocated = computed(() => {
    return allocateItems.value.reduce((sum, i) => sum + (Number(i.amount) || 0), 0);
});

const unallocatedRemaining = computed(() => {
    return Math.max(0, Number(payment.value.unallocated_amount) - totalAllocated.value);
});

const isOveralllocated = computed(() => {
    return totalAllocated.value > Number(payment.value.unallocated_amount);
});

const autoAllocate = () => {
    if (Number(payment.value.unallocated_amount) <= 0) return;

    let remainingToAllocate = Number(payment.value.unallocated_amount);
    // FIFO by bill_date
    const sorted = [...allocateItems.value].sort((a, b) => new Date(a.bill_date) - new Date(b.bill_date));

    for (const item of sorted) {
        item.amount = 0;
        if (remainingToAllocate <= 0) continue;

        const applyParams = Math.min(item.balance_due, remainingToAllocate);
        item.amount = applyParams;
        remainingToAllocate -= applyParams;
    }

    // Sync back
    for (const item of allocateItems.value) {
        const updated = sorted.find(s => s.bill_id === item.bill_id);
        if (updated) item.amount = updated.amount;
    }
};

const submitAllocation = async () => {
    if (isOveralllocated.value) {
        toast.add({ severity: 'error', summary: 'Invalid', detail: 'Cannot allocate more than available credit.' });
        return;
    }

    submitting.value = true;
    try {
        const items = allocateItems.value.filter(a => a.amount > 0).map(a => ({
            bill_id: a.bill_id,
            amount: a.amount
        }));

        if (items.length > 0) {
            await axios.post(`/api/vendor-payments/${props.id}/allocate`, { allocations: items });
            toast.add({ severity: 'success', summary: 'Success', detail: 'Funds allocated successfully.' });
            showAllocateDialog.value = false;
            fetchPayment();
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Allocation failed.' });
    } finally {
        submitting.value = false;
    }
}

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
    if (refundForm.value.amount > Number(payment.value.unallocated_amount)) {
        toast.add({ severity: 'error', summary: 'Invalid', detail: 'Refund exceeds unallocated credit.' });
        return;
    }

    submittingRefund.value = true;
    try {
        await axios.post(`/api/vendor-payments/${props.id}/refund`, { ...refundForm.value });
        toast.add({ severity: 'success', summary: 'Success', detail: 'Refund issued successfully.' });
        showRefundDialog.value = false;
        fetchPayment();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Refund failed.' });
    } finally {
        submittingRefund.value = false;
    }
};

const handleVoid = async () => {
    if (!confirm('Are you sure you want to VOID this disbursement? Linked bill balances will be restored.')) return;
    try {
        await axios.patch(`/api/vendor-payments/${props.id}/void`);
        toast.add({ severity: 'warn', summary: 'Voided', detail: 'Disbursement cancelled.', life: 3000 });
        fetchPayment();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not void disbursement.', life: 3000 });
    }
};

const handleUnallocate = async (allocationId) => {
    if (!confirm('Remove this allocation? This will restore the bill balance and increase unallocated credit.')) return;
    try {
        await axios.delete(`/api/vendor-payments/${props.id}/unallocate/${allocationId}`);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Allocation removed.', life: 3000 });
        fetchPayment();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not remove allocation.', life: 3000 });
    }
};

onMounted(() => {
    fetchPayment();
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
};

const printDocument = () => {
    window.print();
};

const getStatusColor = (status) => {
    switch (status) {
        case 'VOID': return 'text-rose-500 bg-rose-500/10 border-rose-500/20';
        default: return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="payment ? 'Disbursement ' + (payment.payment_number || payment.reference_number) : 'Disbursement Voucher'" />
        <Toast />

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 print:py-0 print:px-0">
            <!-- Headers / Actions (Hidden on Print) -->
            <div class="mb-6 flex justify-between items-end print:hidden">
                <div v-if="!loading">
                    <button @click="router.visit('/finance-center?mode=PAYABLE')" class="bg-transparent border-none text-amber-600 hover:text-amber-500 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest mb-6 transition-colors p-0 outline-none">
                        <i class="pi pi-arrow-left text-xs"></i> Back to Finance Center
                    </button>
                    <div class="flex items-center gap-4">
                        <h1 class="text-2xl font-bold text-primary tracking-tight mb-0">Record: {{ payment?.payment_number }}</h1>
                        <div class="inline-flex px-2 py-0.5 rounded border text-[10px] font-bold tracking-[0.1em] font-mono uppercase" :class="getStatusColor(payment?.status)">
                            {{ payment?.status || 'PAID' }}
                        </div>
                    </div>
                </div>
                
                <div v-if="!loading" class="flex items-center gap-3">
                    <button @click="printDocument" class="bg-panel-hover hover:bg-zinc-700 text-primary px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                        <i class="pi pi-print"></i> Print
                    </button>
                    
                    <button v-if="payment?.status !== 'VOID'" @click="handleVoid" class="bg-panel-hover hover:bg-rose-500 text-primary px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                        <i class="pi pi-ban" /> Void
                    </button>
                </div>
            </div>

            <!-- Loader -->
            <div v-if="loading" class="py-32 flex flex-col items-center justify-center space-y-4">
                <i class="pi pi-spinner pi-spin text-4xl text-amber-500"></i>
                <p class="font-mono text-[10px] uppercase tracking-[0.3em] text-muted animate-pulse">Retrieving Settlement Data...</p>
            </div>

            <!-- Disbursement Voucher (Classic White) -->
            <div v-else-if="payment" class="space-y-8">
                <div class="bg-white text-zinc-900 rounded-2xl shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] border-t-8 border-amber-500 overflow-hidden relative z-10 print:shadow-none print:border-none print:rounded-none">
                    <div class="p-12">
                        
                        <div class="flex justify-between items-start border-b-2 border-zinc-200 pb-8 mb-8">
                            <div>
                                <h2 class="text-4xl font-black tracking-tighter text-zinc-900 uppercase">Disbursement Voucher</h2>
                                <p class="text-secondary font-mono text-sm mt-2">{{ payment.payment_number }}</p>
                            </div>
                            
                            <div class="text-right">
                                <div class="font-bold text-xl text-zinc-900">Nexus Logistics Corp.</div>
                                <div class="text-sm text-secondary mt-1">123 Corporate Ave, Matrix City</div>
                                <div class="text-sm text-secondary uppercase font-mono tracking-tighter">TAX ID: N/A</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-12 mb-8">
                            <!-- Paid To -->
                            <div>
                                <div class="text-xs font-bold uppercase tracking-widest text-secondary mb-2">Paid To</div>
                                <div class="font-bold text-lg text-zinc-900">{{ payment.vendor?.name }}</div>
                                <div class="text-sm text-muted mt-1 uppercase font-mono">{{ payment.vendor?.vendor_code }}</div>
                                <div class="text-xs text-secondary leading-relaxed font-bold mt-4 max-w-sm">
                                    {{ payment.vendor?.address || 'Vendor Address not specified (N/A)' }}
                                </div>
                            </div>

                            <!-- Meta Info Grid -->
                            <div class="bg-zinc-50 rounded-lg p-4 border border-zinc-100 grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Date</div>
                                    <div class="font-mono text-sm font-bold text-zinc-800">{{ payment.payment_date }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Method</div>
                                    <div class="font-mono text-sm font-bold text-zinc-800">{{ payment.payment_method || 'N/A' }}</div>
                                </div>
                                <div class="col-span-2">
                                    <div class="text-[10px] font-bold uppercase tracking-widest text-secondary">Reference / Check #</div>
                                    <div class="font-mono text-sm font-bold text-zinc-800">{{ payment.reference_number || '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Ledger Activity Table -->
                        <div class="mb-8">
                            <div class="flex justify-between items-end mb-4 px-2">
                                <span class="text-xs font-bold text-secondary uppercase tracking-widest font-mono">Ledger Allocations</span>
                                <div class="flex gap-4 print:hidden" v-if="payment.status !== 'VOID'">
                                    <button v-if="Number(payment.unallocated_amount) > 0" @click="openAllocateDialog" class="px-3 py-1.5 bg-sky-500/10 border border-sky-500/20 text-sky-600 rounded-lg font-bold text-[10px] uppercase tracking-widest hover:bg-sky-500 hover:text-primary transition-all flex items-center gap-2 outline-none">
                                        <i class="pi pi-plus-circle" /> Allocate Credit
                                    </button>
                                    <button v-if="Number(payment.unallocated_amount) > 0" @click="openRefundDialog" class="px-3 py-1.5 bg-rose-500/10 border border-rose-500/20 text-rose-600 rounded-lg font-bold text-[10px] uppercase tracking-widest hover:bg-rose-500 hover:text-primary transition-all flex items-center gap-2 outline-none">
                                        <i class="pi pi-undo" /> Issue Refund
                                    </button>
                                </div>
                            </div>
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-y-2 border-zinc-200">
                                        <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-secondary">Allocation Reference</th>
                                        <th class="py-3 px-2 text-xs font-bold uppercase tracking-widest text-secondary text-right">Settled Amount</th>
                                        <th class="py-3 px-2 print:hidden" style="width: 50px"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 font-mono">
                                    <tr v-if="!payment.allocations?.length && !payment.refunds?.length">
                                        <td colspan="3" class="px-2 py-8 text-center text-secondary text-xs italic tracking-widest">No primary allocations recorded</td>
                                    </tr>
                                    <!-- Regular Allocations -->
                                    <tr v-for="alloc in payment.allocations" :key="'al-' + alloc.id" class="text-sm group hover:bg-zinc-50 transition-colors">
                                        <td class="py-3 px-2">
                                            <div class="flex items-center gap-3 cursor-pointer" @click="router.visit('/finance/bills/' + alloc.bill_id)">
                                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                                <span class="font-bold text-zinc-800 group-hover:text-amber-600 underline decoration-zinc-300">{{ alloc.bill?.bill_number }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2 text-right font-bold text-zinc-900 border-none">{{ formatCurrency(alloc.amount) }}</td>
                                        <td class="py-3 px-2 text-right print:hidden border-none text-zinc-300">
                                            <button v-if="payment.status !== 'VOID'" @click="handleUnallocate(alloc.id)" class="text-zinc-300 hover:text-rose-500 transition-colors bg-transparent border-none p-0" title="Unallocate">
                                                <i class="pi pi-times-circle" />
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Refunds (Parity) -->
                                    <tr v-for="rf in payment.refunds" :key="'rf-' + rf.id" class="text-sm">
                                        <td class="py-3 px-2">
                                            <div class="flex items-center gap-3">
                                                <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                                                <span class="font-bold text-rose-600 italic">Refund: {{ rf.refund_number }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2 text-right font-bold text-rose-600 font-mono">- {{ formatCurrency(rf.amount) }}</td>
                                        <td class="py-3 px-2 print:hidden"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals Section -->
                        <div class="flex justify-end">
                            <div class="w-72">
                                <div class="flex justify-between py-2 border-b border-zinc-100">
                                    <span class="text-xs font-bold uppercase tracking-widest text-secondary">Total Funds Sent</span>
                                    <span class="font-mono text-sm text-zinc-800 font-bold">{{ formatCurrency(payment.amount) }}</span>
                                </div>
                                <div class="flex justify-between py-3 bg-zinc-50 px-3 -mx-3 mt-2 border border-zinc-200 rounded-lg">
                                    <span class="text-xs font-black uppercase tracking-widest text-zinc-900">Total Disbursement</span>
                                    <span class="font-mono text-lg font-black text-amber-600">{{ formatCurrency(payment.amount) }}</span>
                                </div>

                                <!-- Remaining Balance -->
                                <div class="mt-6 flex justify-between py-3 border-t-2 border-zinc-900 mt-1">
                                    <span class="text-xs font-black uppercase tracking-widest text-zinc-900">Unallocated Credit</span>
                                    <span class="font-mono text-xl font-black text-zinc-900" :class="Number(payment.unallocated_amount) > 0 ? 'text-amber-600' : ''">
                                        {{ formatCurrency(payment.unallocated_amount) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Auditor Footer -->
                        <div class="mt-16 pt-8 border-t border-zinc-100 flex justify-between items-center text-secondary font-mono">
                             <div class="flex items-center gap-3">
                                <i class="pi pi-shield text-amber-600/30"></i>
                                <span class="text-[9px] font-bold uppercase tracking-[0.2em]">Official Disbursement Voucher • Audit ID: {{ payment.id }}</span>
                            </div>
                            <div class="text-[10px] font-bold uppercase tracking-widest">
                                Generated on {{ new Date().toLocaleString() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Allocate Remaining Credit Dialog -->
        <Dialog v-model:visible="showAllocateDialog" modal :style="{ width: '50rem' }" class="p-fluid">
            <template #header>
                <div class="flex flex-col">
                    <span class="text-xl font-bold text-primary tracking-tight">Allocate Remaining Credit</span>
                    <span class="text-xs text-secondary">Apply leftover funds to open bills for {{ payment?.vendor?.name }}</span>
                </div>
            </template>

            <div v-if="isOveralllocated" class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 font-mono text-sm mt-4">
                <i class="pi pi-exclamation-triangle"></i>
                <span>Allocation Exceeds Remaining Credit! Please review manual allocations.</span>
            </div>

            <div class="bg-panel border border-panel-border rounded-xl p-6 shadow-xl flex items-center justify-between mb-6 mt-4">
                <div class="flex gap-8">
                    <div class="flex flex-col">
                        <span class="text-secondary text-[10px] font-bold uppercase tracking-widest mb-1">To Allocate</span>
                        <span class="text-primary font-mono font-bold">{{ formatCurrency(totalAllocated) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-secondary text-[10px] font-bold uppercase tracking-widest mb-1">Unallocated Credit left</span>
                        <span class="font-mono font-bold" :class="unallocatedRemaining > 0 ? 'text-amber-400' : 'text-muted'">
                            {{ formatCurrency(unallocatedRemaining) }}
                        </span>
                    </div>
                </div>
                <button @click="autoAllocate" v-if="allocateItems.length > 0" class="px-4 py-2 bg-sky-500/10 border border-sky-500/20 text-sky-400 hover:bg-sky-500 hover:text-primary rounded-lg text-xs font-bold uppercase tracking-widest transition-colors">
                    Auto Allocate
                </button>
            </div>

            <div v-if="loadingBills" class="flex justify-center p-8 text-secondary">
                <i class="pi pi-spinner pi-spin text-3xl"></i>
            </div>
            <div v-else-if="allocateItems.length === 0" class="p-8 text-center text-secondary border border-dashed border-panel-border rounded-xl">
                This vendor has no other open bills to allocate funds to!
            </div>
            <div v-else class="border border-panel-border rounded-lg overflow-hidden h-[300px] overflow-y-auto">
                <DataTable :value="allocateItems" class="p-datatable-sm bg-transparent border-none">
                    <Column header="Bill">
                        <template #body="{ data }">
                            <div class="flex flex-col py-1">
                                <span class="text-sm text-primary font-bold bg-deep px-2 py-1 rounded border border-panel-border self-start mb-1">{{ data.bill_number }}</span>
                                <span class="text-[10px] text-secondary ml-1">{{ data.bill_date }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Balance Due" style="width: 25%">
                        <template #body="{ data }">
                            <span class="text-sm font-mono font-bold text-rose-400 font-mono">{{ formatCurrency(data.balance_due) }}</span>
                        </template>
                    </Column>
                    <Column header="Apply Amount" style="width: 35%" class="text-right">
                        <template #body="{ data }">
                            <InputNumber v-model="data.amount" mode="currency" currency="PHP" locale="en-PH"
                                         class="w-full" inputClass="bg-deep border-zinc-700 text-amber-500 font-bold text-right text-sm w-full focus:border-amber-500 outline-none" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <template #footer>
                <button @click="showAllocateDialog = false" class="px-4 py-2 text-secondary hover:text-primary font-bold text-xs uppercase tracking-widest transition-colors">Cancel</button>
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
                    <span class="text-xl font-bold text-primary tracking-tight">Issue Cash Refund</span>
                    <span class="text-xs text-secondary">Return unallocated credit from {{ payment?.vendor?.name }}</span>
                </div>
            </template>

            <div class="bg-panel border border-panel-border rounded-xl p-5 mt-4 mb-6">
                <div class="text-[10px] text-secondary font-bold uppercase tracking-widest mb-1">Available Unallocated Credit</div>
                <div class="text-2xl font-mono font-black text-amber-400 font-mono">{{ formatCurrency(payment?.unallocated_amount) }}</div>
            </div>

            <div class="space-y-4">
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary uppercase tracking-widest">Refund Amount *</label>
                    <InputNumber v-model="refundForm.amount" :min="0.01" :max="Number(payment?.unallocated_amount)" :minFractionDigits="2" :maxFractionDigits="2"
                                 inputClass="!bg-panel !border-zinc-700 !text-rose-400 !font-bold !text-lg outline-none" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-secondary uppercase tracking-widest">Refund Date *</label>
                        <input type="date" v-model="refundForm.refund_date"
                               class="h-11 bg-panel border border-zinc-700 text-primary rounded-lg px-3 text-sm focus:border-rose-500 outline-none" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Refund Method</label>
                        <Select v-model="refundForm.refund_method" :options="refundMethods" 
                                class="!bg-panel !border-zinc-700 !text-zinc-300 outline-none" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary uppercase tracking-widest">Reference / Check #</label>
                    <InputText v-model="refundForm.reference_number" placeholder="e.g. CHK-001234"
                               class="!bg-panel !border-zinc-700 !text-zinc-300 !h-11 outline-none" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[10px] font-bold text-secondary uppercase tracking-widest">Notes</label>
                    <InputText v-model="refundForm.notes" placeholder="Reason for refund..."
                               class="!bg-panel !border-zinc-700 !text-zinc-300 !h-11 outline-none" />
                </div>
            </div>

            <template #footer>
                <button @click="showRefundDialog = false" class="px-4 py-2 text-secondary hover:text-primary font-bold text-xs uppercase tracking-widest transition-colors">Cancel</button>
                <button @click="submitRefund" :disabled="submittingRefund || !refundForm.amount || refundForm.amount <= 0"
                        class="ml-4 px-6 py-2 bg-rose-500 text-primary hover:bg-rose-400 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors shadow-lg disabled:opacity-50 flex items-center gap-2">
                    <i v-if="submittingRefund" class="pi pi-spinner pi-spin"></i>
                    <i v-else class="pi pi-undo"></i>
                    Issue Refund
                </button>
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
@reference "../../../css/app.css";

:deep(.p-inputnumber-input) {
    @apply focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none transition-all;
}

@media print {
    body {
        @apply bg-white;
    }
}
</style>


