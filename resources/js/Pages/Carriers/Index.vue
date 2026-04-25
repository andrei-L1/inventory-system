<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ToggleButton from 'primevue/togglebutton';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const carriers = ref([]);
const loading = ref(true);
const search = ref('');

const dialogVisible = ref(false);
const dialogMode = ref('create'); // 'create' | 'edit'
const saving = ref(false);

const emptyForm = () => ({
    id: null,
    name: '',
    contact_person: '',
    phone: '',
    tracking_url_template: '',
    is_active: true,
});

const form = ref(emptyForm());

const filteredCarriers = computed(() => {
    if (!search.value) return carriers.value;
    const q = search.value.toLowerCase();
    return carriers.value.filter(c =>
        c.name.toLowerCase().includes(q) ||
        (c.contact_person || '').toLowerCase().includes(q)
    );
});

const loadCarriers = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/carriers?limit=500');
        carriers.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load carriers', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(loadCarriers);

const openCreate = () => {
    form.value = emptyForm();
    dialogMode.value = 'create';
    dialogVisible.value = true;
};

const openEdit = (carrier) => {
    form.value = { ...carrier };
    dialogMode.value = 'edit';
    dialogVisible.value = true;
};

const saveCarrier = async () => {
    if (!form.value.name.trim()) {
        toast.add({ severity: 'warn', summary: 'Required', detail: 'Carrier name is required', life: 3000 });
        return;
    }

    saving.value = true;
    try {
        const payload = {
            name: form.value.name.trim(),
            contact_person: form.value.contact_person || null,
            phone: form.value.phone || null,
            tracking_url_template: form.value.tracking_url_template || null,
            is_active: form.value.is_active,
        };

        if (dialogMode.value === 'create') {
            await axios.post('/api/carriers', payload);
            toast.add({ severity: 'success', summary: 'Created', detail: `Carrier "${payload.name}" added`, life: 3000 });
        } else {
            await axios.patch(`/api/carriers/${form.value.id}`, payload);
            toast.add({ severity: 'success', summary: 'Updated', detail: `Carrier "${payload.name}" saved`, life: 3000 });
        }

        dialogVisible.value = false;
        await loadCarriers();
    } catch (e) {
        const msg = e.response?.data?.message || (dialogMode.value === 'create' ? 'Create failed' : 'Update failed');
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 });
    } finally {
        saving.value = false;
    }
};

const toggleActive = async (carrier) => {
    try {
        await axios.patch(`/api/carriers/${carrier.id}`, { is_active: !carrier.is_active });
        carrier.is_active = !carrier.is_active;
        toast.add({
            severity: carrier.is_active ? 'success' : 'warn',
            summary: carrier.is_active ? 'Activated' : 'Deactivated',
            detail: `${carrier.name} is now ${carrier.is_active ? 'active' : 'inactive'}`,
            life: 2500,
        });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Status update failed', life: 3000 });
    }
};

const deleteCarrier = (carrier) => {
    confirm.require({
        message: `Delete carrier "${carrier.name}"? This cannot be undone.`,
        header: 'Delete Carrier',
        icon: 'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/carriers/${carrier.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: `${carrier.name} removed`, life: 3000 });
                await loadCarriers();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Delete failed', life: 4000 });
            }
        },
    });
};

const getTrackingPreview = (template) => {
    if (!template) return null;
    return template.replace('{tracking_number}', 'DEMO123456789');
};
</script>

<template>
    <Head title="Carrier Management" />
    <AppLayout>
        <div class="h-full flex flex-col gap-6">

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-deep border border-sky-900/20 rounded-3xl shadow-2xl relative overflow-hidden ring-1 ring-white/5">
                <!-- Sky Ambient Glow -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[60%] h-24 bg-sky-500/8 blur-[80px] pointer-events-none"></div>

                <div class="flex items-center gap-5 z-10">
                    <div class="w-14 h-14 rounded-2xl bg-sky-500/15 border border-sky-500/30 flex items-center justify-center shadow-[0_0_20px_rgba(14,165,233,0.15)]">
                        <i class="pi pi-truck text-sky-400 text-xl"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-primary text-2xl font-black tracking-tighter">Carrier Management</h1>
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono text-secondary">
                            Logistics · Phase 6.1 · {{ carriers.length }} Carriers Registered
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10">
                    <div class="relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted text-xs"></i>
                        <input
                            v-model="search"
                            placeholder="Search carriers..."
                            class="pl-9 pr-4 py-2.5 bg-panel border border-panel-border rounded-xl text-xs text-primary placeholder:text-muted focus:outline-none focus:border-sky-500/50 focus:ring-1 focus:ring-sky-500/20 transition-all font-mono w-52"
                        />
                    </div>
                    <Button
                        v-if="can('manage-products')"
                        id="create-carrier-btn"
                        label="Add Carrier"
                        icon="pi pi-plus"
                        class="!bg-sky-500/20 hover:!bg-sky-500/30 !text-sky-400 !border-sky-500/40 font-bold tracking-widest uppercase font-mono text-xs transition-all"
                        @click="openCreate"
                    />
                </div>
            </div>

            <!-- Carriers Table -->
            <div class="bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-xl">
                <DataTable
                    :value="filteredCarriers"
                    :loading="loading"
                    class="p-datatable-sm"
                    stripedRows
                    :rowHover="true"
                >
                    <!-- Empty State -->
                    <template #empty>
                        <div class="flex flex-col items-center justify-center py-16 gap-3">
                            <div class="w-16 h-16 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                                <i class="pi pi-truck text-sky-400 text-2xl"></i>
                            </div>
                            <p class="text-secondary text-sm font-bold">No carriers registered</p>
                            <p class="text-muted text-xs">Add a carrier to enable shipment tracking</p>
                        </div>
                    </template>

                    <!-- Carrier Name + Contact -->
                    <Column header="CARRIER" style="min-width: 200px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3 py-1">
                                <div class="w-9 h-9 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center flex-shrink-0">
                                    <i class="pi pi-truck text-sky-400 text-xs"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-sm">{{ data.name }}</span>
                                    <span v-if="data.contact_person" class="text-[10px] font-mono text-secondary">{{ data.contact_person }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Phone -->
                    <Column header="PHONE" style="min-width: 140px">
                        <template #body="{ data }">
                            <span class="text-xs font-mono text-secondary">{{ data.phone || '—' }}</span>
                        </template>
                    </Column>

                    <!-- Tracking URL Template -->
                    <Column header="TRACKING URL TEMPLATE" style="min-width: 280px">
                        <template #body="{ data }">
                            <div v-if="data.tracking_url_template" class="flex flex-col gap-1">
                                <span class="text-[10px] font-mono text-sky-400/80 break-all">{{ data.tracking_url_template }}</span>
                                <a
                                    :href="getTrackingPreview(data.tracking_url_template)"
                                    target="_blank"
                                    class="text-[9px] font-bold text-muted hover:text-sky-400 uppercase tracking-widest transition-colors flex items-center gap-1"
                                >
                                    <i class="pi pi-external-link text-[8px]"></i> Preview Link
                                </a>
                            </div>
                            <span v-else class="text-[10px] text-muted italic">No template configured</span>
                        </template>
                    </Column>

                    <!-- Status -->
                    <Column header="STATUS" style="width: 120px">
                        <template #body="{ data }">
                            <button
                                v-if="can('manage-products')"
                                @click="toggleActive(data)"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all cursor-pointer"
                                :class="data.is_active
                                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/20'
                                    : 'bg-zinc-800/50 border-zinc-700/50 text-muted hover:bg-zinc-700/40'"
                            >
                                <div class="w-1.5 h-1.5 rounded-full"
                                    :class="data.is_active ? 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.6)]' : 'bg-zinc-600'"
                                ></div>
                                <span class="text-[9px] font-black uppercase tracking-widest font-mono">
                                    {{ data.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </button>
                            <Tag
                                v-else
                                :severity="data.is_active ? 'success' : 'secondary'"
                                :value="data.is_active ? 'Active' : 'Inactive'"
                                class="text-[9px] font-black"
                            />
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="ACTIONS" style="width: 120px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2" v-if="can('manage-products')">
                                <button
                                    @click="openEdit(data)"
                                    class="w-8 h-8 rounded-lg bg-panel-hover border border-panel-border flex items-center justify-center text-secondary hover:text-sky-400 hover:border-sky-500/30 transition-all"
                                    title="Edit carrier"
                                >
                                    <i class="pi pi-pencil text-xs"></i>
                                </button>
                                <button
                                    @click="deleteCarrier(data)"
                                    class="w-8 h-8 rounded-lg bg-panel-hover border border-panel-border flex items-center justify-center text-secondary hover:text-red-400 hover:border-red-500/30 transition-all"
                                    title="Delete carrier"
                                >
                                    <i class="pi pi-trash text-xs"></i>
                                </button>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Create/Edit Dialog -->
            <Dialog
                v-model:visible="dialogVisible"
                :header="dialogMode === 'create' ? 'Add Carrier' : 'Edit Carrier'"
                :modal="true"
                :closable="true"
                :style="{ width: '520px' }"
                class="!bg-deep !border-panel-border"
            >
                <div class="flex flex-col gap-5 pt-2">

                    <!-- Name -->
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-black text-secondary uppercase tracking-widest font-mono">
                            Carrier Name <span class="text-red-400">*</span>
                        </label>
                        <InputText
                            id="carrier-name-input"
                            v-model="form.name"
                            placeholder="e.g. FedEx, J&T Express, LBC"
                            class="!bg-panel !border-panel-border !text-primary text-sm"
                        />
                    </div>

                    <!-- Contact Person -->
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-black text-secondary uppercase tracking-widest font-mono">Contact Person</label>
                        <InputText
                            id="carrier-contact-input"
                            v-model="form.contact_person"
                            placeholder="e.g. John Santos"
                            class="!bg-panel !border-panel-border !text-primary text-sm"
                        />
                    </div>

                    <!-- Phone -->
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-black text-secondary uppercase tracking-widest font-mono">Phone</label>
                        <InputText
                            id="carrier-phone-input"
                            v-model="form.phone"
                            placeholder="+63 912 345 6789"
                            class="!bg-panel !border-panel-border !text-primary text-sm"
                        />
                    </div>

                    <!-- Tracking URL Template -->
                    <div class="flex flex-col gap-2">
                        <label class="text-[10px] font-black text-secondary uppercase tracking-widest font-mono">
                            Tracking URL Template
                        </label>
                        <InputText
                            id="carrier-tracking-url-input"
                            v-model="form.tracking_url_template"
                            placeholder="https://track.carrier.com/{tracking_number}"
                            class="!bg-panel !border-panel-border !text-primary text-sm font-mono"
                        />
                        <p class="text-[9px] text-muted font-mono">
                            Use <code class="text-sky-400 bg-sky-500/10 px-1 rounded">{tracking_number}</code> as the placeholder. It will be replaced automatically.
                        </p>
                        <!-- Live preview -->
                        <div v-if="form.tracking_url_template" class="mt-1 p-2.5 bg-sky-500/5 border border-sky-500/20 rounded-lg">
                            <p class="text-[9px] text-muted uppercase tracking-widest font-mono mb-1">Preview URL:</p>
                            <a
                                :href="getTrackingPreview(form.tracking_url_template)"
                                target="_blank"
                                class="text-[10px] text-sky-400 hover:text-sky-300 font-mono break-all transition-colors"
                            >
                                {{ getTrackingPreview(form.tracking_url_template) }}
                            </a>
                        </div>
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center justify-between p-3 bg-panel-hover/50 rounded-xl border border-panel-border/50">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-primary">Active Status</span>
                            <span class="text-[10px] text-muted">Inactive carriers won't appear in ship dialogs</span>
                        </div>
                        <button
                            @click="form.is_active = !form.is_active"
                            class="flex items-center gap-2 px-4 py-2 rounded-lg border transition-all font-mono text-[10px] font-black uppercase tracking-widest"
                            :class="form.is_active
                                ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-400'
                                : 'bg-panel border-panel-border text-muted'"
                        >
                            <div class="w-2 h-2 rounded-full transition-all"
                                :class="form.is_active ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)]' : 'bg-zinc-600'"
                            ></div>
                            {{ form.is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Button
                            label="Cancel"
                            icon="pi pi-times"
                            class="!bg-panel-hover hover:!bg-zinc-700 !text-secondary !border-panel-border font-bold tracking-widest uppercase font-mono text-xs"
                            @click="dialogVisible = false"
                        />
                        <Button
                            :label="dialogMode === 'create' ? 'Add Carrier' : 'Save Changes'"
                            icon="pi pi-check"
                            :loading="saving"
                            class="!bg-sky-500/20 hover:!bg-sky-500/30 !text-sky-400 !border-sky-500/40 font-bold tracking-widest uppercase font-mono text-xs"
                            @click="saveCarrier"
                        />
                    </div>
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>
