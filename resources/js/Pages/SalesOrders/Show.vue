<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Popover from 'primevue/popover';
import Select from 'primevue/select';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ id: [String, Number] });
const { can } = usePermissions();
const confirm = useConfirm();
const toast = useToast();

const so = ref(null);
const loading = ref(true);
const uoms = ref([]);
const uomConversions = ref([]);
const locations = ref([]);
const carriers = ref([]);
const availableInventory = ref([]);
const loadingConversions = ref(false);

const pickLoading = ref(false);
const packLoading = ref(false);
const approveLoading = ref(false);
const fulfillLoading = ref(false);

const pickDialog = ref(false);
const pickForm = ref({ lines: [] });

const packDialog = ref(false);
const packForm = ref({ lines: [] });

const shipDialog = ref(false);
const shipForm = ref({ 
    lines: [],
    carrier_id: null,
    tracking_number: '',
    notes: ''
});

const returnDialog = ref(false);
const returnLoading = ref(false);
const returnForm = ref({ 
    location_id: null,
    lines: [],
    notes: ''
});

// UOM Config Helpers
const getFactorToBase = (uomId, productId = null) => {
    if (!uomId) return { factor: 1, baseId: null };
    let factor = 1.0;
    let current = Number(uomId);
    let processed = [current];
    while (true) {
        let rule = null;
        if (productId) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === current && c.product_id === productId);
        }
        if (!rule) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === current && c.product_id === null);
        }
        
        if (!rule || processed.includes(Number(rule.to_uom_id))) break;
        factor = Number(factor) * Number(rule.conversion_factor);
        current = Number(rule.to_uom_id);
        processed.push(current);
    }
    return { factor, baseId: current };
};

const getUomAbbr = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.abbreviation : '';
};

const getFilteredUoms = (line) => {
    let list = uoms.value;
    const currentUom = uoms.value.find(u => u.id === line.uom_id);
    if (currentUom && currentUom.category) {
        list = uoms.value.filter(u => u.category === currentUom.category);
    } else {
        const isCount = isUomIdDiscrete(line.uom_id);
        list = uoms.value.filter(u => u.category === (isCount ? 'count' : u.category));
    }

    return list.map(u => {
        let rule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(u.id) && c.product_id !== null && Number(c.product_id) === Number(line.product_id));
        let isCustom = !!rule;
        if (!rule) {
            rule = uomConversions.value.find(c => Number(c.from_uom_id) === Number(u.id) && c.product_id === null);
        }
        let conversion_text = '';
        if (rule) {
            const toUom = uoms.value.find(tu => tu.id === rule.to_uom_id);
            if (toUom) {
                conversion_text = `× ${Number(rule.conversion_factor)} ${toUom.abbreviation}`;
            }
        }
        return { ...u, conversion_text, is_custom: isCustom };
    });
};

const onReturnUomChange = (line) => {
    const soLine = so.value.lines.find(l => l.id === line.so_line_id);
    if (!soLine) return;
    const targetInfo = getFactorToBase(line.uom_id, line.product_id);
    const soBaseInfo = getFactorToBase(soLine.uom_id, soLine.product_id);
    if (Number(targetInfo.baseId) === Number(soBaseInfo.baseId)) {
        const effectiveFactor = Number(soBaseInfo.factor) / Number(targetInfo.factor);
        line.return_qty = Number(line.net_shipped_qty) * effectiveFactor;
        return;
    }
    toast.add({ severity: 'warn', summary: 'No Conversion', detail: 'No common base unit found for this UOM pairing.', life: 4000 });
};

const filteredReturnLocations = computed(() => {
    if (availableInventory.value.length === 0) return locations.value;
    const locIdsWithStock = new Set(availableInventory.value.map(inv => inv.location?.id).filter(Boolean));
    return locations.value.filter(loc => locIdsWithStock.has(loc.id));
});

const getStockInSelectedLocation = (productId) => {
    if (!returnForm.value.location_id) return 0;
    const inv = availableInventory.value.find(
        i => Number(i.product?.id) === Number(productId) && Number(i.location?.id) === Number(returnForm.value.location_id)
    );
    return inv ? Number(inv.quantity_on_hand) : 0;
};

// C-7: Calculate dynamic headroom for returns based on the selected UOM
const getMaxReturnable = (line) => {
    const soLine = so.value.lines.find(l => l.id === line.so_line_id);
    if (!soLine) return 0;
    
    const currentInfo = getFactorToBase(line.uom_id, line.product_id);
    const soInfo = getFactorToBase(soLine.uom_id, soLine.product_id);
    
    if (Number(currentInfo.baseId) !== Number(soInfo.baseId)) return 0;
    
    // (Shipped in SO Unit * SO Factor) / Current Factor = Max in Current Unit
    const multiplier = Number(soInfo.factor) / Number(currentInfo.factor);
    return Number(soLine.net_shipped_qty) * multiplier;
};

const isQuotation = computed(() => so.value?.status?.name === 'quotation' || so.value?.status?.name === 'quotation_sent');
const canCancel = computed(() => so.value && !['shipped', 'cancelled', 'closed'].includes(so.value.status.name));

const getTotalAvailable = (line) => {
    return Number(line.total_available_qty || 0);
};

const getFormattedTotalAvailable = (line) => {
    return line.formatted_total_available_qty || '0';
};

const getAvailabilityStatus = (line) => {
    const total = getTotalAvailable(line);
    const ordered = Number(line.ordered_qty);
    if (total >= ordered) return { severity: 'success', label: 'In Stock' };
    if (total > 0) return { severity: 'warn', label: 'Limited' };
    return { severity: 'danger', label: 'Out of Stock' };
};

const loadMasterData = async () => {
    try {
        const [uomRes, convRes, locRes, carrierRes] = await Promise.all([
            axios.get('/api/uom'),
            axios.get('/api/uom-conversions'),
            axios.get('/api/locations?limit=1000'),
            axios.get('/api/carriers?active=1&limit=500'),
        ]);
        uoms.value = uomRes.data.data;
        uomConversions.value = convRes.data.data;
        locations.value = locRes.data.data;
        carriers.value = carrierRes.data.data;
    } catch (e) {
        console.error("Failed to load metadata", e);
    }
};

const isUomIdDiscrete = (id) => {
    const uom = uoms.value.find(u => u.id === id);
    return uom ? uom.category === 'count' : true;
};

const loadSO = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/sales-orders/${props.id}`);
        so.value = res.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load Sales Order', life: 3000 });
        router.visit('/sales-orders');
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await Promise.all([loadSO(), loadMasterData()]);
});

const getStatusColor = (statusName) => {
    const map = {
        'quotation': 'warning',
        'quotation_sent': 'info',
        'confirmed': 'info',
        'partially_picked': 'help',
        'picked': 'help',
        'partially_packed': 'help',
        'packed': 'help',
        'partially_shipped': 'help',
        'shipped': 'success',
        'closed': 'success',
        'cancelled': 'danger'
    };
    return map[statusName] || 'info';
};

const approve = async () => {
    confirm.require({
        message: 'Confirm this order? This will reserve stock at the designated locations.',
        header: 'Confirm Sales Order',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                approveLoading.value = true;
                await axios.patch(`/api/sales-orders/${so.value.id}/approve`);
                toast.add({ severity: 'success', summary: 'Confirmed', detail: 'Stock has been reserved', life: 3000 });
                loadSO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Confirmation failed', life: 3000 });
            } finally {
                approveLoading.value = false;
            }
        }
    });
};

const cancelOrder = () => {
    confirm.require({
        message: 'Are you sure you want to cancel this order? All reserved stock will be released.',
        header: 'Cancel Sales Order',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.patch(`/api/sales-orders/${so.value.id}/cancel`);
                toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Order cancelled and stock released', life: 3000 });
                loadSO();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Cancellation failed', life: 3000 });
            }
        }
    });
};

const deleteOrder = () => {
    confirm.require({
        message: 'Permanently delete this quotation? This action cannot be undone.',
        header: 'Delete Quotation',
        icon: 'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/sales-orders/${so.value.id}`);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Quotation removed', life: 3000 });
                router.visit('/sales-orders');
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Delete failed', life: 3000 });
            }
        }
    });
};

const editOrder = () => {
    router.visit(`/sales-orders/${so.value.id}/edit`);
};

const openPickDialog = () => {
    pickForm.value.lines = so.value.lines
        .filter(l => Number(l.picked_qty) < Number(l.ordered_qty))
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            ordered_qty: Number(l.ordered_qty),
            picked_qty: Number(l.picked_qty), // already picked
            to_pick: Number(l.remaining_pick_qty), // from accessor
            uom: l.uom?.abbreviation
        }));
    pickDialog.value = true;
};

const submitPick = async () => {
    try {
        pickLoading.value = true;
        const payload = {
            lines: pickForm.value.lines.filter(l => Number(l.to_pick) > 0).map(l => ({
                so_line_id: l.so_line_id,
                picked_qty: l.to_pick
            }))
        };
        if (payload.lines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Input Required', detail: 'Specify at least one item to pick', life: 3000 });
            return;
        }
        await axios.patch(`/api/sales-orders/${so.value.id}/pick`, payload);
        toast.add({ severity: 'success', summary: 'Pick Registered', detail: 'Items moved to staging', life: 3000 });
        pickDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Pick failed', life: 3000 });
    } finally {
        pickLoading.value = false;
    }
};

const openPackDialog = () => {
    packForm.value.lines = so.value.lines
        .filter(l => Number(l.packed_qty) < Number(l.picked_qty))
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            picked_qty: Number(l.picked_qty),
            packed_qty: Number(l.packed_qty),
            to_pack: Number(l.remaining_pack_qty),
            uom: l.uom?.abbreviation
        }));
    packDialog.value = true;
};

const submitPack = async () => {
    try {
        packLoading.value = true;
        const payload = {
            lines: packForm.value.lines.filter(l => Number(l.to_pack) > 0).map(l => ({
                so_line_id: l.so_line_id,
                packed_qty: l.to_pack
            }))
        };
        await axios.patch(`/api/sales-orders/${so.value.id}/pack`, payload);
        toast.add({ severity: 'success', summary: 'Pack Registered', detail: 'Items verified and boxed', life: 3000 });
        packDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Pack failed', life: 3000 });
    } finally {
        packLoading.value = false;
    }
};

const printVoucher = () => {
    window.open(window.location.href + '/print', '_blank');
};

const openShipDialog = async () => {
    shipForm.value.lines = so.value.lines
        .filter(l => Number(l.shipped_qty) < Number(l.packed_qty))
        .map(l => ({
            so_line_id: l.id,
            product_name: l.product_name,
            sku: l.sku,
            product_id: l.product_id,
            location_id: l.location_id,
            packed_qty: Number(l.packed_qty),
            shipped_qty: Number(l.shipped_qty),
            to_ship: Number(l.remaining_ship_qty),
            uom: l.uom?.abbreviation,
            // Phase 6.3: optional serial selection
            serial_ids: [],
            available_serials: [],
            loadingSerials: false,
        }));
    shipForm.value.carrier_id = null;
    shipForm.value.tracking_number = '';
    shipForm.value.notes = '';
    shipDialog.value = true;

    // Phase 6.3: eagerly load available in_stock serials per line
    for (const line of shipForm.value.lines) {
        if (line.product_id) {
            line.loadingSerials = true;
            try {
                const params = { status: 'in_stock', product_id: line.product_id, limit: 200 };
                if (line.location_id) params.location_id = line.location_id;
                const res = await axios.get('/api/serials', { params });
                line.available_serials = res.data.data;
            } catch { /* silent — no serials available */ }
            finally { line.loadingSerials = false; }
        }
    }
};

const fulfill = async () => {
    if (!shipForm.value.carrier_id) {
        toast.add({ severity: 'warn', summary: 'Required', detail: 'Please select a carrier from the list', life: 3000 });
        return;
    }

    try {
        fulfillLoading.value = true;
        const payload = {
            carrier_id: shipForm.value.carrier_id,
            tracking_number: shipForm.value.tracking_number,
            notes: shipForm.value.notes,
            lines: shipForm.value.lines.filter(l => Number(l.to_ship) > 0).map(l => ({
                so_line_id: l.so_line_id,
                shipped_qty: l.to_ship,
                // Phase 6.3: only send if serials were selected
                serial_ids: l.serial_ids?.length ? l.serial_ids : undefined,
            }))
        };
        await axios.post(`/api/sales-orders/${so.value.id}/ship`, payload);
        toast.add({ severity: 'success', summary: 'Shipped', detail: 'Order fulfilled and stock movements recorded', life: 3000 });
        shipDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Fulfillment failed', life: 3000 });
    } finally {
        fulfillLoading.value = false;
    }
};

const openReturnDialog = async () => {
    returnLoading.value = true;
    try {
        // Port logical parity: Fetch inventory distribution for all products on the SO
        const productIds = so.value.lines.map(l => l.product_id);
        const inventoryRes = await Promise.all(
            productIds.map(id => axios.get(`/api/inventory?product_id=${id}&limit=100`))
        );
        availableInventory.value = inventoryRes.flatMap(res => res.data.data);

        returnForm.value.location_id = null;
        returnForm.value.lines = so.value.lines
            .filter(l => Number(l.net_shipped_qty) > 0)
            .map(l => ({
                so_line_id: l.id,
                product_id: l.product_id,
                product_name: l.product_name,
                sku: l.sku,
                net_shipped_qty: Number(l.net_shipped_qty), // Original shipped in SO Unit
                uom: l.uom?.abbreviation,
                uom_id: l.uom_id,
                return_qty: 0,
                resolution: 'replacement',
                reason: ''
            }));
        returnForm.value.notes = '';
        returnDialog.value = true;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not fetch inventory position data.', life: 3000 });
    } finally {
        returnLoading.value = false;
    }
};

const submitReturn = async () => {
    if (!returnForm.value.location_id) {
        toast.add({ severity: 'warn', summary: 'Input Required', detail: 'Specify a destination location for the return', life: 3000 });
        return;
    }

    try {
        const payload = {
            location_id: returnForm.value.location_id,
            notes: returnForm.value.notes,
            lines: returnForm.value.lines.filter(l => Number(l.return_qty) > 0).map(l => ({
                so_line_id: l.so_line_id,
                returned_qty: l.return_qty,
                uom_id: l.uom_id,
                resolution: l.resolution,
                reason: l.reason
            }))
        };

        if (payload.lines.length === 0) {
            toast.add({ severity: 'warn', summary: 'Input Required', detail: 'Specify at least one item to return', life: 3000 });
            return;
        }

        returnLoading.value = true;
        await axios.post(`/api/sales-orders/${so.value.id}/return`, payload);
        toast.add({ severity: 'success', summary: 'Return Processed', detail: 'Stock updated and Credit Note generated', life: 3000 });
        returnDialog.value = false;
        loadSO();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Return failed', life: 3000 });
    } finally {
        returnLoading.value = false;
    }
};

const canApprove = computed(() => so.value?.status?.name === 'quotation' || so.value?.status?.name === 'quotation_sent');
const canPick = computed(() => {
    if (!so.value || ['quotation', 'quotation_sent', 'shipped', 'cancelled', 'closed'].includes(so.value.status.name)) return false;
    return so.value.lines?.some(l => Number(l.picked_qty) < Number(l.ordered_qty));
});

const canPack = computed(() => {
    if (!so.value || ['quotation', 'quotation_sent', 'shipped', 'cancelled', 'closed'].includes(so.value.status.name)) return false;
    return so.value.lines?.some(l => Number(l.packed_qty) < Number(l.picked_qty));
});

const canShip = computed(() => {
    if (!so.value || ['quotation', 'quotation_sent', 'shipped', 'cancelled', 'closed'].includes(so.value.status.name)) return false;
    return so.value.lines?.some(l => Number(l.shipped_qty) < Number(l.packed_qty));
});

const canReturn = computed(() => so.value?.lines?.some(l => Number(l.shipped_qty) > 0));

const subtotal = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + (Number(line.ordered_qty) * Number(line.unit_price)), 0) || 0;
});

const totalTax = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + Number(line.tax_amount || 0), 0) || 0;
});

const totalDiscount = computed(() => {
    return so.value?.lines?.reduce((sum, line) => sum + Number(line.discount_amount || 0), 0) || 0;
});




</script>

<template>
    <Head :title="so ? so.so_number : 'Loading...'" />
    <AppLayout v-if="so">
        <div class="h-full flex flex-col gap-4">

            <!-- Header Panel -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-deep border border-teal-900/30 rounded-3xl shadow-2xl relative overflow-hidden ring-1 ring-white/5">
                <!-- Teal Ambient Glow -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-teal-500/10 blur-[100px] pointer-events-none"></div>

                <div class="flex items-center gap-5 z-10">
                    <button @click="router.visit('/sales-orders')" class="w-12 h-12 rounded-2xl bg-panel border border-panel-border flex items-center justify-center text-secondary hover:text-primary transition-all hover:scale-105 hover:border-teal-500/30 active:scale-95 group">
                        <i class="pi pi-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                    </button>
                    <div>
                        <div class="flex items-center gap-4 mb-1.5">
                            <h1 class="text-primary text-3xl font-black tracking-tighter font-mono">{{ so.so_number }}</h1>
                            <Tag 
                                :severity="getStatusColor(so.status.name)" 
                                :value="so.status.name.replace('_', ' ').toUpperCase()" 
                                class="text-[9px] font-black tracking-[0.2em] font-mono uppercase px-3 py-1 rounded-lg shadow-inner bg-panel-hover/50 border border-zinc-700/50"
                            />
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.2em] uppercase font-mono flex items-center gap-3">
                            <span @click="router.visit(`/customer-center?customer_id=${so.customer_id}`)" class="text-teal-400 hover:text-teal-300 cursor-pointer transition-colors">{{ so.customer_name }}</span>
                            <span class="w-1 h-1 rounded-full bg-zinc-700"></span>
                            <span class="text-secondary">₱{{ so.formatted_total_amount }} Revenue</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 z-10 no-print">
                    <!-- Return Dialog (RMA High-Density Upgrade) -->
                    <Button 
                        v-if="canApprove && can('manage-sales-orders')" 
                        label="Confirm Order" 
                        icon="pi pi-check-circle" 
                        :loading="approveLoading"
                        class="p-button-sm !bg-teal-500/20 hover:!bg-teal-500/30 !text-teal-400 !border-teal-500/50 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="approve"
                    />

                    <Button 
                        v-if="isQuotation && can('manage-sales-orders')" 
                        label="Edit" 
                        icon="pi pi-pencil" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="editOrder"
                    />

                    <Button 
                        v-if="canCancel && can('manage-sales-orders')" 
                        label="Cancel Order" 
                        icon="pi pi-times-circle" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-red-400 !border-red-900/30 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="cancelOrder"
                    />

                    <Button 
                        v-if="isQuotation && can('manage-sales-orders')" 
                        label="Delete" 
                        icon="pi pi-trash" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-secondary !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="deleteOrder"
                    />

                    <Button 
                        v-if="so.status?.name !== 'quotation' && so.status?.name !== 'cancelled'"
                        label="Print" 
                        icon="pi pi-print" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-zinc-300 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="printVoucher"
                    />

                    <Button 
                        v-if="canPick && can('manage-sales-orders')" 
                        label="Pick Lines" 
                        icon="pi pi-box" 
                        :loading="pickLoading"
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-help-400 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openPickDialog"
                    />

                    <Button 
                        v-if="canPack && can('manage-sales-orders')" 
                        label="Pack Lines" 
                        icon="pi pi-gift" 
                        :loading="packLoading"
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-help-400 !border-zinc-700 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openPackDialog"
                    />

                    <Button 
                        v-if="canShip && can('manage-sales-orders')" 
                        label="Ship / Fulfill" 
                        icon="pi pi-truck" 
                        class="p-button-sm !bg-teal-500 hover:!bg-teal-600 !border-none !text-primary font-bold shadow-[0_0_15px_rgba(20,184,166,0.3)] tracking-widest uppercase font-mono transition-all" 
                        @click="openShipDialog"
                    />

                    <Button 
                        v-if="canReturn && can('manage-sales-orders')" 
                        label="Return Items" 
                        icon="pi pi-backward" 
                        class="p-button-sm !bg-panel-hover hover:!bg-zinc-700 !text-amber-400 !border-amber-900/30 font-bold tracking-widest uppercase font-mono transition-all" 
                        @click="openReturnDialog"
                    />
                </div>
            </div>

            <!-- Details Board -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Meta Info Side -->
                <div class="col-span-12 lg:col-span-3 flex flex-col gap-4">
                    <div class="bg-panel/40 border border-panel-border/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono border-b border-panel-border/50 pb-3">Sales Metadata</span>
                        
                        <div class="flex justify-between items-center py-2 border-b border-panel-border/30">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Order Date</span>
                            <span class="text-xs font-bold text-primary">{{ so.order_date }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-panel-border/30">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Customer Code</span>
                            <span class="text-xs font-bold text-teal-400">{{ so.customer_code }}</span>
                        </div>
                        <div v-if="so.shipped_at" class="flex flex-col gap-2 py-2 border-b border-panel-border/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Shipped On</span>
                                <span class="text-xs font-bold text-teal-400">{{ so.shipped_at }}</span>
                            </div>
                            <div class="mt-1 p-2 bg-deep rounded border border-panel-border">
                                <div class="flex justify-between text-[10px] mb-1">
                                    <span class="text-muted font-bold uppercase tracking-tighter">Carrier:</span>
                                    <span class="text-zinc-300 font-bold">{{ so.carrier }}</span>
                                </div>
                                <div class="flex justify-between text-[10px]">
                                    <span class="text-muted font-bold uppercase tracking-tighter">Tracking:</span>
                                    <span class="text-sky-500 font-mono">{{ so.tracking_number }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="so.notes" class="flex flex-col gap-2 pt-2">
                            <span class="text-[10px] font-bold text-secondary font-mono tracking-widest uppercase">Notes</span>
                            <p class="text-xs text-secondary leading-relaxed bg-deep/50 p-3 rounded-lg border border-panel-border/50">{{ so.notes }}</p>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="flex flex-col gap-5 p-6 bg-deep/50 rounded-2xl border border-panel-border/50 shadow-inner">
                        <div class="flex justify-between items-center text-secondary">
                            <span class="text-[10px] font-bold tracking-widest uppercase font-mono">Subtotal</span>
                            <span class="text-xs font-bold text-primary">₱{{ so.formatted_subtotal }}</span>
                        </div>
                        <div class="flex justify-between items-center text-secondary">
                            <span class="text-[10px] font-bold tracking-widest uppercase font-mono">Tax Amount</span>
                            <span class="text-xs font-bold text-primary">₱{{ so.formatted_total_tax }}</span>
                        </div>
                        <div class="flex justify-between items-center text-secondary">
                            <span class="text-[10px] font-bold tracking-widest uppercase font-mono">Discounts</span>
                            <span class="text-xs font-bold text-primary">₱{{ so.formatted_total_discount }}</span>
                        </div>
                        <div class="h-px bg-panel-hover/50 my-1"></div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-[11px] font-black text-primary tracking-widest uppercase font-mono">Grand Total</span>
                            <span class="text-lg font-black text-teal-400 font-mono shadow-teal-500/20 drop-shadow-md">₱{{ so.formatted_total_amount }}</span>
                        </div>
                    </div>

                    <!-- Fulfillment History -->
                    <div v-if="so.transactions && so.transactions.length > 0" class="bg-panel/40 border border-panel-border/80 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <span class="text-[10px] font-bold text-teal-500 uppercase tracking-widest font-mono border-b border-panel-border/50 pb-2 text-center">Fulfillment History</span>
                        
                        <div v-for="tx in so.transactions" :key="tx.id" class="flex flex-col gap-1.5 p-3 bg-deep/50 rounded-xl border border-panel-border/50 group transition-all hover:border-teal-500/30">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-mono text-teal-400 font-bold uppercase tracking-tight">{{ tx.reference_number }}</span>
                                <span class="text-[9px] font-mono text-muted">{{ tx.transaction_date }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[9px] font-black text-muted uppercase tracking-widest">Status</span>
                                <Tag :severity="tx.status.name === 'posted' ? 'success' : 'warning'" :value="tx.status.name.toUpperCase()" class="text-[8px] font-black px-1.5 py-0.5 rounded-md" />
                            </div>
                        </div>
                    </div>

                    <!-- Shipments Panel (Phase 6.1) -->
                    <div v-if="so.shipments && so.shipments.length > 0" class="bg-panel/40 border border-sky-900/30 rounded-2xl p-4 shadow-xl flex flex-col gap-3">
                        <div class="flex items-center justify-between border-b border-panel-border/50 pb-2">
                            <span class="text-[10px] font-bold text-sky-400 uppercase tracking-widest font-mono">Shipment Records</span>
                            <span class="text-[9px] font-black text-muted font-mono">{{ so.shipments.length }} {{ so.shipments.length === 1 ? 'Entry' : 'Entries' }}</span>
                        </div>

                        <div v-for="shp in so.shipments" :key="shp.id" class="flex flex-col gap-2 p-3 bg-deep/50 rounded-xl border border-panel-border/50 hover:border-sky-500/30 transition-all group">
                            <!-- Header row -->
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-mono text-sky-400 font-bold uppercase tracking-tight">{{ shp.shipment_number }}</span>
                                <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded font-mono"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': shp.status === 'delivered',
                                        'bg-sky-500/10 text-sky-400 border border-sky-500/20': shp.status === 'in_transit',
                                        'bg-teal-500/10 text-teal-400 border border-teal-500/20': shp.status === 'shipped',
                                        'bg-zinc-700/50 text-muted border border-zinc-700/50': shp.status === 'pending',
                                        'bg-red-500/10 text-red-400 border border-red-500/20': shp.status === 'failed',
                                    }"
                                >{{ shp.status.replace('_', ' ') }}</span>
                            </div>
                            <!-- Carrier + Date row -->
                            <div class="flex items-center gap-2">
                                <i class="pi pi-truck text-[9px] text-muted"></i>
                                <span class="text-[10px] font-bold text-zinc-300">{{ shp.carrier?.name || '—' }}</span>
                                <span class="text-[9px] text-muted font-mono ml-auto">{{ shp.shipped_at ? shp.shipped_at.split(' ')[0] : '—' }}</span>
                            </div>
                            <!-- Tracking number with link -->
                            <div v-if="shp.tracking_number" class="flex items-center gap-2">
                                <a
                                    v-if="shp.tracking_url"
                                    :href="shp.tracking_url"
                                    target="_blank"
                                    class="text-[10px] font-mono text-sky-400 hover:text-sky-300 underline decoration-sky-500/30 transition-colors flex items-center gap-1"
                                >
                                    <i class="pi pi-external-link text-[8px]"></i>
                                    {{ shp.tracking_number }}
                                </a>
                                <span v-else class="text-[10px] font-mono text-zinc-400">{{ shp.tracking_number }}</span>
                            </div>
                            <div v-else class="text-[9px] text-muted italic font-mono">No tracking number</div>
                        </div>
                    </div>
                </div>

                <!-- Lines Data -->
                <div class="col-span-12 lg:col-span-9 flex flex-col gap-4">
                    <div class="flex-1 bg-panel/40 border border-panel-border/80 rounded-2xl flex flex-col overflow-hidden shadow-xl p-4">
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono border-b border-panel-border/50 pb-2 mb-3 block">Fulfillment Lines</span>
                        
                        <DataTable :value="so.lines" class="p-datatable-sm w-full" stripedRows>
                            <Column field="product_name" header="PRODUCT/ID">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[9px] font-bold text-teal-500/70 font-mono tracking-widest uppercase">{{ data.sku }}</span>
                                            <span class="text-[8px] text-muted font-mono">/ {{ data.uom?.abbreviation }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column field="location_name" header="SOURCE">
                                <template #body="{ data }">
                                    <span class="text-secondary text-[10px] font-bold uppercase font-mono tracking-tighter">{{ data.location?.name || 'N/A' }}</span>
                                </template>
                            </Column>

                            <!-- Stock Visibility for Quotations -->
                            <Column v-if="isQuotation" header="AVAILABILITY">
                                <template #body="{ data }">
                                    <div class="flex flex-col gap-1.5 py-1 min-w-[150px]">
                                        <div v-if="data.availability && data.availability.some(l => l.available_qty > 0 || l.reserved_qty > 0)" class="bg-deep/50 rounded-lg p-2 border border-panel-border/50 flex flex-col gap-1">
                                            <div v-for="loc in data.availability.filter(l => l.available_qty > 0 || l.reserved_qty > 0)" :key="loc.location_name" 
                                                 class="flex items-center justify-between px-0.5 border-b border-panel-border/30 last:border-0 pb-0.5 mb-0.5 last:pb-0 last:mb-0">
                                                <span class="text-[9px] font-bold text-secondary uppercase tracking-tighter">{{ loc.location_name }}</span>
                                                <div class="flex items-center gap-2">
                                                    <span :class="Number(loc.available_qty) > 0 ? 'text-teal-400' : 'text-muted'" class="text-[10px] font-mono font-bold">{{ loc.formatted_available_qty }}</span>
                                                    <span v-if="Number(loc.reserved_qty) > 0" class="text-[8px] text-amber-500/60 font-mono">({{ loc.formatted_reserved_qty }} RSV)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-2 bg-rose-500/5 border border-rose-500/10 rounded-lg">
                                            <span class="text-[8px] font-bold text-rose-400 uppercase tracking-widest">OUT OF STOCK</span>
                                        </div>
                                        
                                        <div class="flex justify-between px-1">
                                            <span class="text-[9px] font-bold text-muted uppercase font-mono tracking-widest">Total</span>
                                            <span class="text-[10px] font-black" :class="getTotalAvailable(data) > 0 ? 'text-primary' : 'text-red-500/50'">{{ getFormattedTotalAvailable(data) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column header="LIFECYCLE STATUS">
                                <template #body="{ data }">
                                    <div class="flex flex-col gap-2 w-36">
                                        <div class="h-2 bg-deep rounded-full overflow-hidden relative border border-panel-border/80 p-[1px] shadow-inner">
                                            <!-- Picked Bar (Background layer) -->
                                            <div :style="{ width: (Number(data.picked_qty) / Number(data.ordered_qty) * 100) + '%' }" class="absolute top-0 left-0 h-full bg-teal-500/30 transition-all duration-500 rounded-full"></div>
                                            <!-- Shipped Bar (Foreground layer) -->
                                            <div :style="{ width: (Number(data.shipped_qty) / Number(data.ordered_qty) * 100) + '%' }" class="absolute top-0 left-0 h-full bg-emerald-500 transition-all duration-700 shadow-[0_0_10px_rgba(16,185,129,0.5)] rounded-full"></div>
                                        </div>
                                        <div class="flex justify-between items-center px-1">
                                            <span class="text-[8px] font-black text-muted font-mono uppercase tracking-widest">Fulfillment</span>
                                            <span class="text-[10px] font-black text-emerald-400 font-mono">{{ Math.round(Number(data.shipped_qty) / Number(data.ordered_qty) * 100) }}%</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                            <Column header="GOAL / REQ">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-mono font-black text-primary">{{ data.formatted_ordered_qty }}</span>
                                        </div>
                                        <span class="text-[9px] font-black text-muted uppercase tracking-widest">Target</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="PICK/PACK">
                                <template #body="{ data }">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-mono font-bold text-zinc-300">{{ data.formatted_picked_qty }} Picked</span>
                                        <span class="text-[10px] font-mono font-bold text-zinc-300">{{ data.formatted_packed_qty }} Packed</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="RETURNED">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-mono font-black text-amber-500/80">{{ Number(data.returned_qty) > 0 ? data.formatted_returned_qty : '-' }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="SHIPPED">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-mono font-black text-emerald-400">{{ data.formatted_shipped_qty }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column header="PRICE">
                                <template #body="{ data }">
                                    <span class="text-secondary font-mono text-[10px] font-bold">{{ data.formatted_unit_price }}</span>
                                </template>
                            </Column>
                            <Column field="subtotal" header="LINE TOTAL">
                                <template #body="{ data }">
                                    <span class="text-primary font-mono text-xs font-bold">₱{{ Number(data.subtotal || 0).toFixed(2) }}</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>

            <!-- Linked Transactions (COGS) -->
            <div v-if="so.transactions && so.transactions.length > 0" class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-panel-hover"></div>
                    <span class="text-[10px] font-bold text-secondary uppercase tracking-[0.3em] font-mono whitespace-nowrap">Audit Ledger & Stock Movements</span>
                    <div class="h-px flex-1 bg-panel-hover"></div>
                </div>

                <div class="bg-panel/40 border border-panel-border/80 rounded-2xl overflow-hidden shadow-xl">
                    <DataTable :value="so.transactions" class="p-datatable-sm w-full audit-ledger-grid" :pt="{
                        thead: { class: 'bg-deep' },
                        bodyrow: { class: 'hover:bg-teal-500/[0.02] transition-colors border-b border-zinc-900/50' }
                    }">
                        <Column field="reference_number" header="MOVEMENT REF">
                            <template #body="{ data }">
                                <span class="text-teal-500 font-mono text-[10px] font-bold uppercase tracking-tight">{{ data.reference_number }}</span>
                            </template>
                        </Column>
                        <Column field="transaction_date" header="DATE">
                             <template #body="{ data }">
                                <span class="text-[10px] font-mono text-secondary">{{ data.transaction_date }}</span>
                            </template>
                        </Column>
                        <Column field="display_type" header="TYPE">
                            <template #body="{ data }">
                                <span class="text-[9px] font-black text-secondary uppercase tracking-widest">{{ data.display_type }}</span>
                            </template>
                        </Column>
                        <Column field="from_location_name" header="EXPORT FROM">
                            <template #body="{ data }">
                                <span class="text-[10px] font-bold text-secondary uppercase font-mono tracking-tight">{{ data.from_location_name }}</span>
                            </template>
                        </Column>
                        <Column field="to_location_name" header="DESTINATION">
                            <template #body="{ data }">
                                <span class="text-[10px] font-bold text-secondary uppercase font-mono tracking-tight">{{ data.to_location_name }}</span>
                            </template>
                        </Column>
                        <Column field="formatted_quantity" header="QUANTITY">
                             <template #body="{ data }">
                                <span class="text-[11px] font-mono font-black text-primary" :class="{ 'text-amber-500': Number(data.quantity) < 0 }">{{ data.formatted_quantity }}</span>
                            </template>
                        </Column>
                        <Column header="COGS AUDIT">
                            <template #body>
                                <span class="text-[10px] text-muted font-mono font-bold tracking-tighter uppercase px-2 py-0.5 bg-deep rounded border border-panel-border/50">Ledger Recorded</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Pick Dialog -->
        <Dialog v-model:visible="pickDialog" modal :header="null" :closable="false" :style="{ width: '55rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-deep border border-teal-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-teal-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-teal-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center">
                            <i class="pi pi-box text-teal-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-primary tracking-tight font-mono uppercase">Inventory Picking Control</h2>
                            <p class="text-[9px] text-secondary font-black uppercase tracking-widest mt-0.5">Stage items for order fulfillment</p>
                        </div>
                    </div>
                    <button @click="pickDialog = false" class="w-8 h-8 rounded-full bg-panel border border-panel-border flex items-center justify-center text-secondary hover:text-primary hover:bg-panel-hover transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-teal-500/5 border border-teal-500/10 p-4 rounded-2xl flex items-start gap-3">
                        <i class="pi pi-info-circle text-teal-500 mt-0.5"></i>
                        <p class="text-[11px] text-secondary font-medium leading-relaxed font-mono uppercase tracking-tight">
                            Protocol: Move reserved stock from storage bins to the staging area. Registering picks decreases available inventory and increments the staging ledger.
                        </p>
                    </div>
                
                    <DataTable :value="pickForm.lines" class="p-datatable-sm" scrollable scrollHeight="300px">
                        <Column field="product_name" header="PRODUCT/SKU">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-teal-500/70 font-bold uppercase tracking-widest">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="PROGRESS">
                            <template #body="{ data }">
                                <div class="flex flex-col gap-1 items-center">
                                    <span class="text-[10px] font-mono font-bold text-secondary bg-panel px-3 py-1 rounded-lg border border-panel-border shadow-inner">
                                        {{ Number(data.picked_qty) }} / {{ Number(data.ordered_qty) }}
                                    </span>
                                    <span class="text-[8px] font-black text-muted uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QUANTITY TO STAGE" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-deep border border-panel-border rounded-xl focus-within:border-teal-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_pick" 
                                        :min="0" 
                                        :max="Number(data.ordered_qty) - Number(data.picked_qty)" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-teal-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#14b8a6', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                    <div class="px-3 border-l border-panel-border bg-panel/50 h-full flex items-center">
                                        <span class="text-[9px] font-black text-secondary uppercase">{{ data.uom }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-teal-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="pickDialog = false" class="p-button-text !text-secondary hover:!text-primary uppercase font-mono font-black tracking-widest text-[11px]" />
                    <Button 
                        label="Complete Pick Selection" 
                        :loading="pickLoading" 
                        @click="submitPick"
                        class="!px-8 !h-11 !bg-teal-500 hover:!bg-teal-400 !text-zinc-950 font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_20px_rgba(20,184,166,0.3)]"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Pack Dialog -->
        <Dialog v-model:visible="packDialog" modal :header="null" :closable="false" :style="{ width: '55rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-deep border border-indigo-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5 shadow-[0_0_50px_rgba(79,70,229,0.1)]">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-indigo-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-indigo-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                            <i class="pi pi-gift text-indigo-400 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-primary tracking-tight font-mono uppercase">Packing & Quality Assurance</h2>
                            <p class="text-[9px] text-secondary font-black uppercase tracking-widest mt-0.5">Verify and box items for dispatch</p>
                        </div>
                    </div>
                    <button @click="packDialog = false" class="w-8 h-8 rounded-full bg-panel border border-panel-border flex items-center justify-center text-secondary hover:text-primary hover:bg-panel-hover transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-indigo-500/5 border border-indigo-500/10 p-4 rounded-2xl flex items-start gap-3">
                        <i class="pi pi-verified text-indigo-400 mt-0.5"></i>
                        <p class="text-[11px] text-secondary font-medium leading-relaxed font-mono uppercase tracking-tight">
                            Protocol: Audit staged items against packing manifest. Once "Packed", stock is considered ready for fulfillment and cannot be modified without a reversal.
                        </p>
                    </div>
                
                    <DataTable :value="packForm.lines" class="p-datatable-sm" scrollable scrollHeight="300px">
                        <Column field="product_name" header="PRODUCT">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-indigo-400/70 font-bold uppercase tracking-widest font-mono uppercase">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="STAGED (PICKED)">
                            <template #body="{ data }">
                                <div class="flex flex-col items-center">
                                    <span class="text-[10px] font-mono font-black text-zinc-300">{{ data.picked_qty }}</span>
                                    <span class="text-[8px] font-black text-muted uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QUANTITY TO PACK" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-deep border border-panel-border rounded-xl focus-within:border-indigo-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_pack" 
                                        :min="0" 
                                        :max="data.picked_qty - data.packed_qty" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-indigo-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#818cf8', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                    <div class="px-3 border-l border-panel-border bg-panel/50 h-full flex items-center">
                                        <span class="text-[9px] font-black text-secondary uppercase">{{ data.uom }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-indigo-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="packDialog = false" class="p-button-text !text-secondary hover:!text-primary uppercase font-mono font-black tracking-widest text-[11px]" />
                    <div class="flex items-center gap-4">
                        <span v-if="packForm.lines.some(l => l.to_pack > 0)" class="text-[10px] font-mono text-secondary font-bold uppercase">{{ packForm.lines.filter(l => l.to_pack > 0).length }} Items Prepared</span>
                        <Button 
                            label="Verify & Pack" 
                            :loading="packLoading" 
                            @click="submitPack"
                            class="!px-8 !h-11 !bg-indigo-600 hover:!bg-indigo-500 !text-primary font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_20px_rgba(79,70,229,0.3)]"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Ship Dialog -->
        <Dialog v-model:visible="shipDialog" modal :header="null" :closable="false" :style="{ width: '60rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-deep border border-emerald-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5 shadow-[0_0_50px_rgba(16,185,129,0.1)]">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[80%] h-32 bg-emerald-500/10 blur-[100px] pointer-events-none"></div>

                <div class="px-6 py-5 border-b border-emerald-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                            <i class="pi pi-truck text-emerald-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-primary tracking-tight font-mono uppercase">Logistics & Dispatch Control</h2>
                            <p class="text-[9px] text-secondary font-black uppercase tracking-widest mt-0.5">Final fulfillment and courier handover</p>
                        </div>
                    </div>
                    <button @click="shipDialog = false" class="w-8 h-8 rounded-full bg-panel border border-panel-border flex items-center justify-center text-secondary hover:text-primary hover:bg-panel-hover transition-colors">
                        <i class="pi pi-times text-[10px]"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 relative z-10 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="bg-emerald-500/5 border border-emerald-500/10 p-4 rounded-2xl flex items-start gap-4">
                        <i class="pi pi-map-marker text-emerald-500 mt-0.5"></i>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest font-mono mb-1">Carrier Manifest Protocol</span>
                            <p class="text-[11px] text-secondary font-medium leading-relaxed font-mono uppercase tracking-tight">
                                Executing dispatch finalizes the stock issue ledger. Please verify tracking ID accuracy for customer-side traceability. Handover recorded units to <span class="text-primary font-bold bg-panel px-2 py-0.5 rounded">SHIPMENT-EXPORT</span>.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 bg-panel/40 p-4 rounded-2xl border border-panel-border/50">
                        <!-- Carrier Select (Option A — linked to Carrier entity) -->
                        <div class="flex flex-col gap-2">
                            <label class="text-[9px] font-black text-muted tracking-[0.2em] uppercase font-mono">
                                Carrier <span class="text-red-400">*</span>
                            </label>
                            <Select
                                id="ship-carrier-select"
                                v-model="shipForm.carrier_id"
                                :options="carriers"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="SELECT CARRIER..."
                                filter
                                filterPlaceholder="Search carriers..."
                                class="!w-full !bg-deep !border-panel-border !rounded-xl !h-11 font-mono focus-within:!border-emerald-500/50 transition-all shadow-inner"
                                :pt="{ input: { class: '!text-xs !font-bold !text-primary !px-3' }, filterInput: { class: '!text-xs' } }"
                            >
                                <template #option="slotProps">
                                    <div class="flex items-center justify-between w-full gap-3">
                                        <div class="flex items-center gap-2">
                                            <i class="pi pi-truck text-emerald-400 text-xs"></i>
                                            <span class="text-xs font-bold text-primary">{{ slotProps.option.name }}</span>
                                        </div>
                                        <span v-if="slotProps.option.phone" class="text-[9px] font-mono text-muted">{{ slotProps.option.phone }}</span>
                                    </div>
                                </template>
                                <template #empty>
                                    <div class="text-center py-4">
                                        <p class="text-xs text-muted">No carriers found.</p>
                                        <a href="/carriers" target="_blank" class="text-[10px] text-sky-400 hover:text-sky-300 underline">Manage Carriers →</a>
                                    </div>
                                </template>
                            </Select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[9px] font-black text-muted tracking-[0.2em] uppercase font-mono">Tracking Reference / AWB</label>
                            <InputText v-model="shipForm.tracking_number" placeholder="Enter Ref #" class="!w-full !bg-deep !border-panel-border !rounded-xl !h-11 !px-4 !text-xs !font-bold text-primary focus:!border-emerald-500/50 transition-all shadow-inner" />
                        </div>
                    </div>
                    <!-- Notes field -->
                    <div class="flex flex-col gap-2 bg-panel/40 p-3 rounded-2xl border border-panel-border/50">
                        <label class="text-[9px] font-black text-muted tracking-[0.2em] uppercase font-mono">Shipment Notes (Optional)</label>
                        <InputText v-model="shipForm.notes" placeholder="e.g. Handle with care, requires signature..." class="!w-full !bg-deep !border-panel-border !rounded-xl !h-11 !px-4 !text-xs text-primary focus:!border-emerald-500/50 transition-all shadow-inner" />
                    </div>
                
                    <DataTable :value="shipForm.lines" class="p-datatable-sm" scrollable scrollHeight="250px">
                        <Column field="product_name" header="PRODUCT/SKU">
                             <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-xs">{{ data.product_name }}</span>
                                    <span class="text-[9px] font-mono text-emerald-500/70 font-bold uppercase tracking-widest">{{ data.sku }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="BOXED (PACKED)">
                            <template #body="{ data }">
                                <div class="flex flex-col items-center">
                                    <span class="text-[10px] font-mono font-black text-zinc-300">{{ data.packed_qty }}</span>
                                    <span class="text-[8px] font-black text-muted uppercase">{{ data.uom }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="QUANTITY TO SHIP" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center bg-deep border border-panel-border rounded-xl focus-within:border-emerald-500/50 transition-all shadow-inner h-10 group overflow-hidden">
                                     <InputNumber 
                                        v-model="data.to_ship" 
                                        :min="0" 
                                        :max="data.packed_qty - data.shipped_qty" 
                                        :maxFractionDigits="isUomIdDiscrete(data.so_line_id ? so.lines.find(l => l.id === data.so_line_id)?.uom_id : null) ? 0 : 8"
                                        class="p-inputtext-sm text-center font-mono font-black text-emerald-400 border-0 bg-transparent flex-1 focus:ring-0 w-full"
                                        :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#10b981', width: '100%', boxShadow: 'none', height: '2.5rem', fontSize: '0.85rem' }"
                                        placeholder="0"
                                    />
                                    <div class="px-3 border-l border-panel-border bg-panel/50 h-full flex items-center">
                                        <span class="text-[9px] font-black text-secondary uppercase">{{ data.uom }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <!-- Phase 6.3: Optional Serial Selection -->
                        <Column header="ASSIGN SERIALS (OPT.)" style="width: 200px">
                            <template #body="{ data }">
                                <div class="flex flex-col gap-1">
                                    <span v-if="data.loadingSerials" class="text-[9px] text-muted font-mono italic">Loading serials...</span>
                                    <span v-else-if="data.available_serials.length === 0" class="text-[9px] text-muted font-mono italic">No serials tracked</span>
                                    <template v-else>
                                        <div class="flex flex-wrap gap-1 max-h-20 overflow-y-auto p-1 bg-deep/50 rounded-lg border border-panel-border/50">
                                            <label v-for="serial in data.available_serials" :key="serial.id"
                                                class="flex items-center gap-1 cursor-pointer hover:bg-panel/50 px-1.5 py-0.5 rounded transition-colors">
                                                <input type="checkbox"
                                                    :value="serial.id"
                                                    v-model="data.serial_ids"
                                                    class="accent-violet-500 w-3 h-3" />
                                                <span class="text-[9px] font-mono text-violet-300">{{ serial.serial_number }}</span>
                                            </label>
                                        </div>
                                        <span v-if="data.serial_ids.length > 0 && data.serial_ids.length !== Math.round(data.to_ship)"
                                            class="text-[8px] text-amber-400 font-mono">
                                            ⚠ {{ data.serial_ids.length }} selected ≠ qty {{ Math.round(data.to_ship) }}
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="px-6 py-5 border-t border-emerald-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center z-20 relative">
                    <Button label="Cancel" @click="shipDialog = false" class="p-button-text !text-secondary hover:!text-primary uppercase font-mono font-black tracking-widest text-[11px]" />
                    <Button 
                        label="Dispatch Shipment" 
                        :loading="fulfillLoading" 
                        @click="fulfill"
                        class="!px-10 !h-12 !bg-white hover:!bg-zinc-200 !text-zinc-950 font-black uppercase font-mono tracking-widest text-[11px] !rounded-xl !border-none shadow-[0_0_30px_rgba(255,255,255,0.1)] transition-all"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Return Dialog (RMA High-Density Matrix) -->
        <Dialog v-model:visible="returnDialog" modal :header="null" :closable="false" :style="{ width: '65rem' }" pt:root:class="!border-0 !bg-transparent !shadow-2xl" pt:content:class="!p-0 !bg-transparent">
            <div class="flex flex-col bg-deep border border-amber-900/30 rounded-3xl overflow-hidden relative ring-1 ring-white/5 shadow-[0_0_50px_rgba(245,158,11,0.1)]">
                <div class="absolute top-0 right-0 w-[50%] h-32 bg-amber-500/5 blur-[100px] pointer-events-none"></div>
                
                <div class="px-6 py-5 border-b border-amber-900/10 bg-deep/80 backdrop-blur-xl flex justify-between items-center relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                            <i class="pi pi-backward text-amber-500 text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-lg font-black text-primary tracking-tight font-mono uppercase">Sales Return (RMA) Core</h2>
                            <p class="text-[9px] text-secondary font-black uppercase tracking-widest mt-0.5">Reverse fulfilled orders and issue credits</p>
                        </div>
                    </div>
                    <button @click="returnDialog = false" class="w-8 h-8 rounded-full bg-panel border border-panel-border flex items-center justify-center text-secondary hover:text-primary hover:bg-panel-hover transition-colors">
                        <i class="pi pi-times text-xs"></i>
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6 max-h-[65vh] overflow-y-auto custom-scrollbar relative z-10">
                    
                    <!-- Protocol Alert -->
                    <div class="bg-amber-500/5 border-l-2 border-l-amber-500/50 p-4 rounded-r-xl flex items-center gap-4">
                        <i class="pi pi-shield text-amber-500/60 shadow-[0_0_10px_rgba(245,158,11,0.2)]"></i>
                        <p class="text-[11px] text-secondary font-medium leading-relaxed">
                            <span class="text-primary font-mono font-black mr-2 tracking-tighter uppercase opacity-80">RMA Protocol Enforcement</span>
                            Processing an RMA generates an <span class="text-primary font-mono font-bold">SRET</span> movement. 
                            <span class="text-emerald-400/80 font-black text-[10px] tracking-wide uppercase mx-1">Replacement</span> resets fulfillment. 
                            <span class="text-sky-400/80 font-black text-[10px] tracking-wide uppercase mx-1">Refund</span> calculates <span class="text-amber-400 underline decoration-amber-500/30">Credit Note</span> liabilities.
                        </p>
                    </div>

                    <!-- Destination Configuration -->
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-6 flex flex-col gap-2">
                            <label class="text-[9px] font-black text-muted tracking-widest uppercase font-mono pl-1">Target Bin (Destination)</label>
                            <Select 
                                v-model="returnForm.location_id" 
                                :options="filteredReturnLocations" 
                                optionLabel="name" 
                                optionValue="id" 
                                placeholder="SELECT RECEIPT BIN..." 
                                filter
                                class="!w-full !bg-panel/30 !border-panel-border/80 !rounded-xl !h-12 !flex !items-center font-mono focus-within:!border-amber-500/30 shadow-none transition-all"
                            >
                                <template #option="slotProps">
                                    <div class="flex items-center justify-between w-full">
                                        <span class="text-[11px] font-bold text-zinc-300 uppercase tracking-tight">{{ slotProps.option.name }}</span>
                                        <span class="text-[10px] font-mono font-black text-amber-500/60">{{ slotProps.option.code }}</span>
                                    </div>
                                </template>
                            </Select>
                        </div>
                        <div class="col-span-6 flex flex-col gap-2">
                            <label class="text-[9px] font-black text-muted tracking-widest uppercase font-mono pl-1">Global Return Memo</label>
                            <InputText v-model="returnForm.notes" placeholder="Detailed reason for global return..." class="!w-full !bg-panel/30 !border-panel-border/80 !rounded-xl !h-12 !px-4 !text-xs !font-bold text-primary focus:!border-amber-500/30 transition-all shadow-none" />
                        </div>
                    </div>

                    <!-- Line Matrix -->
                    <div class="flex flex-col gap-2 text-center">
                        <div class="grid grid-cols-12 gap-4 px-4 text-[8px] font-black text-muted uppercase tracking-[0.2em] font-mono mb-1">
                            <div class="col-span-3 text-left">Product/ID</div>
                            <div class="col-span-2">System Status</div>
                            <div class="col-span-7">RMA Entry Selection</div>
                        </div>
                        
                        <div class="flex flex-col gap-3">
                            <div v-for="(line, index) in returnForm.lines" :key="line.so_line_id" 
                                 class="grid grid-cols-1 xl:grid-cols-12 gap-4 p-4 items-center bg-panel/20 border border-panel-border/40 rounded-2xl hover:bg-panel/40 transition-all hover:border-amber-500/20 group/row"
                            >
                                <!-- Product Identity -->
                                <div class="xl:col-span-3 flex flex-col gap-1 text-left">
                                    <span class="text-xs font-black text-primary group-hover/row:text-amber-400 transition-colors">{{ line.product_name }}</span>
                                    <span class="text-[9px] font-mono font-black text-sky-500/60 uppercase tracking-tighter">{{ line.sku }}</span>
                                </div>

                                <!-- Status Indicators -->
                                <div class="xl:col-span-2 flex items-center justify-center gap-3">
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black text-muted uppercase tracking-widest">SO UNIT</span>
                                        <span class="text-[11px] font-mono font-bold text-zinc-300">{{ line.net_shipped_qty }}</span>
                                    </div>
                                    <div class="w-px h-6 bg-panel-hover/50"></div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black uppercase tracking-widest text-emerald-500/60">In Bin</span>
                                        <span class="text-[11px] font-mono font-bold text-emerald-400">
                                            {{ getStockInSelectedLocation(line.product_id) ?? '0' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Entries (Unified Action Bar) -->
                                <div class="xl:col-span-7 grid grid-cols-12 gap-2 h-10">
                                    <!-- Qty + UOM -->
                                    <div class="col-span-5 flex items-center bg-deep border border-panel-border rounded-xl focus-within:border-amber-500/50 transition-all overflow-hidden h-full">
                                        <div class="flex-1 relative group/input">
                                            <InputNumber 
                                                v-model="line.return_qty" 
                                                class="w-full h-full"
                                                :max="getMaxReturnable(line)"
                                                :maxFractionDigits="isUomIdDiscrete(line.uom_id) ? 0 : 8"
                                                :inputStyle="{ background: 'transparent', border: '0', textAlign: 'center', color: '#f59e0b', width: '100%', fontWeight: '900', fontSize: '14px', fontFamily: 'monospace' }"
                                                placeholder="0"
                                            />
                                            <span class="absolute -top-1 left-1/2 -translate-x-1/2 text-[7px] font-black text-muted uppercase tracking-tighter opacity-0 group-focus-within/input:opacity-100 transition-opacity">
                                                MAX: {{ getMaxReturnable(line).toFixed(2) }}
                                            </span>
                                        </div>
                                        <div class="w-px h-4 bg-panel-hover/50"></div>
                                        <Select 
                                            v-model="line.uom_id" 
                                            :options="getFilteredUoms(line)" 
                                            optionLabel="abbreviation" 
                                            optionValue="id" 
                                            @change="onReturnUomChange(line)"
                                            class="!bg-panel/10 !border-0 !shadow-none !h-full w-24"
                                            pt:label:class="!text-amber-500 !font-black !p-0 !flex !items-center !justify-center !uppercase !h-full !text-[11px]"
                                            pt:dropdown:class="!text-muted !w-4"
                                        >
                                            <template #value="slotProps">
                                                <span v-if="slotProps.value" class="text-amber-500 font-black">
                                                    {{ uoms.find(u => u.id === slotProps.value)?.abbreviation }}
                                                </span>
                                            </template>
                                        </Select>
                                    </div>

                                    <!-- Resolution -->
                                    <div class="col-span-3">
                                        <Select 
                                            v-model="line.resolution" 
                                            :options="[{label: 'REPLACE', value: 'replacement'}, {label: 'REFUND', value: 'refund'}]" 
                                            optionLabel="label" 
                                            optionValue="value" 
                                            class="!w-full !bg-deep !border-panel-border !rounded-xl !h-full !flex !items-center !text-[9px] !font-black tracking-widest" 
                                        >
                                            <template #value="slotProps">
                                                <span v-if="slotProps.value" class="text-[9px] font-black uppercase" :class="slotProps.value === 'replacement' ? 'text-emerald-500/60' : 'text-sky-500/60'">
                                                    {{ slotProps.value === 'replacement' ? 'REPLACE' : 'REFUND' }}
                                                </span>
                                            </template>
                                        </Select>
                                    </div>

                                    <!-- Reason -->
                                    <div class="col-span-4">
                                        <InputText 
                                            v-model="line.reason" 
                                            placeholder="Condition..." 
                                            class="!w-full !bg-deep !border-panel-border !rounded-xl !text-[11px] !font-bold !h-full !px-4 focus:!border-amber-500/30 text-zinc-300" 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="px-6 py-5 border-t border-amber-900/30 bg-deep/80 backdrop-blur-xl flex justify-between items-center z-10 relative">
                    <Button 
                        label="Abort Operation" 
                        @click="returnDialog = false" 
                        class="p-button-text !text-secondary hover:!text-primary !font-bold !text-[11px] !tracking-widest uppercase font-mono" 
                    />
                    
                    <div class="flex items-center gap-6">
                        <span v-if="returnForm.lines.some(l => Number(l.return_qty) > 0)" class="text-[10px] font-mono text-amber-500 font-bold uppercase tracking-widest flex items-center gap-2">
                            <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></div>
                            {{ returnForm.lines.filter(l => Number(l.return_qty) > 0).length }} Marked
                        </span>
                        <Button 
                            label="EXECUTE RETURN" 
                            @click="submitReturn" 
                            :loading="returnLoading" 
                            class="!min-h-[2.5rem] !px-10 !bg-amber-500 hover:!bg-amber-400 !text-primary !font-black !text-[11px] !tracking-widest !rounded-xl !border-none transition-all uppercase shadow-[0_0_20px_rgba(245,158,11,0.3)]" 
                        />
                    </div>
                </div>
            </div>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: var(--bg-panel-hover);
    border-bottom: 1px solid var(--bg-panel-border);
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.25em;
    padding: 1rem 0.75rem;
}
:deep(.p-datatable .p-datatable-tbody > tr > td) {
    border-bottom: 1px solid var(--bg-panel-border);
    padding: 1rem 0.75rem;
}
:deep(.p-dialog) {
    background: var(--bg-panel);
    border: 1px solid var(--bg-panel-border);
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
}
:deep(.p-dialog-header) {
    background: var(--bg-panel);
    color: var(--text-primary);
    padding: 1.5rem 1.5rem 1rem 1.5rem;
}
:deep(.p-dialog-content) {
    background: var(--bg-panel);
    padding: 0 1.5rem 1.5rem 1.5rem;
}
.no-print {
    @media print {
        display: none !important;
    }
}
</style>


