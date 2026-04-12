<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const toast = useToast();

const customers = ref([]);
const selectedCustomer = ref(null);
const loadingCustomers = ref(false);

const paymentForm = ref({
    payment_date: new Date().toISOString().split('T')[0],
    amount: 0,
    payment_method: 'Bank Transfer',
    reference_number: '',
    notes: ''
});

const invoices = ref([]);
const allocations = ref([]); // array of { invoice_id, amountToApply }
const loadingInvoices = ref(false);
const submitting = ref(false);

const paymentMethods = ['Bank Transfer', 'Cash', 'Credit Card', 'Check', 'Other'];

onMounted(() => {
    loadCustomers();
});

const loadCustomers = async () => {
    loadingCustomers.value = true;
    try {
        const res = await axios.get('/api/customers');
        customers.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load customers', life: 3000 });
    } finally {
        loadingCustomers.value = false;
    }
};

watch(selectedCustomer, async (newVal) => {
    if (!newVal) {
        invoices.value = [];
        allocations.value = [];
        return;
    }

    loadingInvoices.value = true;
    try {
        // Fetch open invoices for allocation
        const res = await axios.get('/api/invoices', {
            params: { customer_id: newVal.id, status: 'OPEN' }
        });
        
        invoices.value = res.data.data;
        allocations.value = invoices.value.map(inv => ({
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
});

const totalAllocated = computed(() => {
    return allocations.value.reduce((sum, item) => sum + (Number(item.amountToApply) || 0), 0);
});

const unallocatedAmount = computed(() => {
    return Math.max(0, Number(paymentForm.value.amount) - totalAllocated.value);
});

const isOveralllocated = computed(() => {
    return totalAllocated.value > Number(paymentForm.value.amount);
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(val || 0));
};

const autoAllocate = () => {
    if (paymentForm.value.amount <= 0) return;
    
    let remainingToAllocate = Number(paymentForm.value.amount);
    
    // Sort oldest first
    const sorted = [...allocations.value].sort((a,b) => new Date(a.invoice_date) - new Date(b.invoice_date));
    
    for (const item of sorted) {
        item.amountToApply = 0; // reset
        if (remainingToAllocate <= 0) continue;
        
        const applyParams = Math.min(item.balance, remainingToAllocate);
        item.amountToApply = applyParams;
        remainingToAllocate -= applyParams;
    }
    
    // update original array based on sorted updates
    for (const item of allocations.value) {
        const updated = sorted.find(s => s.invoice_id === item.invoice_id);
        if (updated) item.amountToApply = updated.amountToApply;
    }
};

const submitPayment = async () => {
    if (!selectedCustomer.value) return;
    if (paymentForm.value.amount <= 0) {
        toast.add({ severity: 'warn', summary: 'Invalid', detail: 'Payment amount must be greater than 0', life: 3000 });
        return;
    }
    if (isOveralllocated.value) {
        toast.add({ severity: 'error', summary: 'Invalid Allocation', detail: 'You cannot allocate more than the received payment amount.', life: 3000 });
        return;
    }

    submitting.value = true;
    try {
        // Step 1: Create Payment
        const payRes = await axios.post('/api/payments', {
            customer_id: selectedCustomer.value.id,
            payment_date: paymentForm.value.payment_date,
            amount: paymentForm.value.amount,
            payment_method: paymentForm.value.payment_method,
            reference_number: paymentForm.value.reference_number,
            notes: paymentForm.value.notes
        });

        const paymentId = payRes.data.payment.id;

        // Step 2: Allocate if any > 0
        const itemsToAllocate = allocations.value.filter(a => a.amountToApply > 0).map(a => ({
            invoice_id: a.invoice_id,
            amount: a.amountToApply
        }));

        if (itemsToAllocate.length > 0) {
            await axios.post(`/api/payments/${paymentId}/allocate`, {
                allocations: itemsToAllocate
            });
        }

        toast.add({ severity: 'success', summary: 'Success', detail: 'Payment recorded and allocated successfully.', life: 3000 });
        setTimeout(() => router.visit('/finance-center'), 1500);

    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Transaction failed', life: 4000 });
    } finally {
        submitting.value = false;
    }
};

</script>

<template>
    <AppLayout>
        <Head title="Record Payment" />
        <Toast />

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <button @click="router.visit('/finance-center')" class="bg-transparent border-none text-sky-400 hover:text-sky-300 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest mb-6 transition-colors p-0 outline-none">
                        <i class="pi pi-arrow-left text-xs"></i> Back to Finance Center
                    </button>
                    <h1 class="text-2xl font-bold text-white tracking-tight mb-1">Record Payment</h1>
                    <p class="text-zinc-400 text-sm">Register incoming funds and allocate them to open invoices.</p>
                </div>
            </div>

            <!-- Global Form Error -->
            <div v-if="isOveralllocated" class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 font-mono text-sm shadow-[0_0_15px_rgba(239,68,68,0.1)]">
                <i class="pi pi-exclamation-triangle"></i>
                <span>Allocation Exceeds Payment Amount! Please review allocations.</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Col: Payment Details -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 shadow-xl sticky top-24">
                        <h3 class="text-white font-bold tracking-tight mb-6 flex items-center gap-2 text-lg border-b border-zinc-800 pb-4">
                            <i class="pi pi-wallet text-emerald-400"></i>
                            Receipt Details
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Customer</label>
                                <Select v-model="selectedCustomer" :options="customers" optionLabel="name" placeholder="Select Customer" 
                                        class="w-full bg-zinc-950 border-zinc-800 text-white" 
                                        :loading="loadingCustomers" filter />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Total Amount Received</label>
                                <InputNumber v-model="paymentForm.amount" mode="currency" currency="PHP" locale="en-PH" 
                                             class="w-full" inputClass="bg-zinc-950 border-zinc-800 text-emerald-400 font-bold text-xl text-right font-mono focus:border-emerald-500 transition-colors w-full" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Date</label>
                                <input type="date" v-model="paymentForm.payment_date" class="w-full bg-zinc-950 border border-zinc-800 text-white p-2.5 rounded-lg text-sm focus:border-sky-500 outline-none" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Method</label>
                                <Select v-model="paymentForm.payment_method" :options="paymentMethods"
                                        class="w-full bg-zinc-950 border-zinc-800 text-white" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2">Ref / Check No.</label>
                                <InputText v-model="paymentForm.reference_number" class="w-full bg-zinc-950 border-zinc-800 text-white" />
                            </div>

                            <div class="pt-4 border-t border-zinc-800 mt-4">
                                <button @click="submitPayment" :disabled="submitting || !selectedCustomer || paymentForm.amount <= 0 || isOveralllocated" 
                                        class="w-full px-6 py-4 rounded-xl font-bold text-[11px] uppercase tracking-widest transition-all shadow-[0_0_20px_rgba(16,185,129,0.15)] flex justify-center items-center gap-2"
                                        :class="isOveralllocated ? 'bg-red-500/20 text-red-400 border border-red-500/50 cursor-not-allowed' : 'bg-emerald-500 hover:bg-emerald-400 text-zinc-950 hover:shadow-[0_0_25px_rgba(16,185,129,0.3)]'">
                                    <i v-if="submitting" class="pi pi-spinner pi-spin"></i>
                                    Confirm Receipt
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Col: Allocations -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Allocation Summary Card -->
                    <div v-if="selectedCustomer" class="bg-zinc-900 border border-zinc-800 rounded-xl p-6 shadow-xl flex items-center justify-between">
                        <div class="flex gap-8">
                            <div class="flex flex-col">
                                <span class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1">To Allocate</span>
                                <span class="text-white font-mono font-bold">{{ formatCurrency(totalAllocated) }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-zinc-500 text-[10px] font-bold uppercase tracking-widest mb-1">Unallocated Credit</span>
                                <span class="font-mono font-bold" :class="unallocatedAmount > 0 ? 'text-amber-400' : 'text-zinc-600'">
                                    {{ formatCurrency(unallocatedAmount) }}
                                </span>
                            </div>
                        </div>
                        <button @click="autoAllocate" v-if="allocations.length > 0 && paymentForm.amount > 0" class="px-4 py-2 bg-sky-500/10 border border-sky-500/20 text-sky-400 hover:bg-sky-500 hover:text-white rounded-lg text-xs font-bold uppercase tracking-widest transition-colors">
                            Auto Allocate
                        </button>
                    </div>

                    <!-- Invoices List -->
                    <div v-if="selectedCustomer && allocations.length > 0" class="bg-zinc-900 border border-zinc-800 rounded-xl p-0 shadow-xl overflow-hidden">
                        <DataTable :value="allocations" class="p-datatable-sm bg-transparent border-none">
                            <Column header="Invoice">
                                <template #body="{ data }">
                                    <div class="flex flex-col py-2">
                                        <span class="text-sm text-zinc-200 font-bold bg-zinc-950 px-2 py-1 rounded border border-zinc-800 self-start mb-1">{{ data.invoice_number }}</span>
                                        <span class="text-[10px] text-zinc-500 ml-1">{{ data.invoice_date }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Total" style="width: 15%">
                                <template #body="{ data }">
                                    <span class="text-xs font-mono text-zinc-400">{{ formatCurrency(data.total_amount) }}</span>
                                </template>
                            </Column>
                            <Column header="Balance Due" style="width: 20%">
                                <template #body="{ data }">
                                    <span class="text-sm font-mono font-bold text-rose-400">{{ formatCurrency(data.balance) }}</span>
                                </template>
                            </Column>
                            <Column header="Apply Amount" style="width: 30%" class="text-right">
                                <template #body="{ data }">
                                    <InputNumber v-model="data.amountToApply" :min="0" :max="data.balance" :minFractionDigits="2" :maxFractionDigits="2" 
                                                 class="w-full" inputClass="bg-zinc-950 border-zinc-700 text-sky-400 font-bold text-right text-sm w-full focus:border-sky-500" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <div v-else-if="selectedCustomer" class="bg-zinc-900 border border-zinc-800 rounded-xl p-12 text-center opacity-50 relative overflow-hidden flex flex-col items-center">
                        <i class="pi pi-check-circle text-5xl text-emerald-400 mb-4 opacity-50"></i>
                        <h3 class="text-white font-bold text-lg mb-2">Account is clean!</h3>
                        <p class="text-zinc-500 text-sm">This customer has no open invoices. Any payments made will be stored as unallocated credit.</p>
                    </div>

                    <div v-else class="h-full bg-zinc-900/40 border border-zinc-800/50 rounded-xl p-12 text-center flex flex-col items-center justify-center border-dashed opacity-70">
                        <i class="pi pi-user text-4xl text-zinc-600 mb-4"></i>
                        <h3 class="text-zinc-400 font-bold mb-1">No Customer Selected</h3>
                        <p class="text-zinc-600 text-[11px] uppercase tracking-widest font-mono">Select a customer to view and allocate open invoices</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
