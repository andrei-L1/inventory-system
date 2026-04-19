<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import Toast from 'primevue/toast';
import { usePermissions } from '@/Composables/usePermissions';
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import axios from 'axios';

const { can } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const categories = ref([]);
const loading = ref(false);

const dialogVisible = ref(false);
const submitted = ref(false);
const form = ref({
    id: null,
    name: '',
    code: '',
    description: '',
    is_active: true
});

const loadCategories = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/categories');
        categories.value = response.data.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load categories', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(loadCategories);

const openNew = () => {
    form.value = { id: null, name: '', code: '', description: '', is_active: true };
    submitted.value = false;
    dialogVisible.value = true;
};

const editCategory = (cat) => {
    form.value = { ...cat };
    submitted.value = false;
    dialogVisible.value = true;
};

const saveCategory = async () => {
    submitted.value = true;
    if (!form.value.name) {
        toast.add({ severity: 'warn', summary: 'Validation Error', detail: 'Category name is required.', life: 3000 });
        return;
    }

    try {
        if (form.value.id) {
            await axios.put(`/api/categories/${form.value.id}`, form.value);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Category updated successfully.', life: 3000 });
        } else {
            await axios.post('/api/categories', form.value);
            toast.add({ severity: 'success', summary: 'Created', detail: 'New category established.', life: 3000 });
        }
        dialogVisible.value = false;
        loadCategories();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to save category.', life: 3000 });
    }
};

const deleteCategory = (cat) => {
    confirm.require({
        message: `Delete the "${cat.name}" category? This will fail if products are already linked.`,
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await axios.delete(`/api/categories/${cat.id}`);
                toast.add({ severity: 'success', summary: 'Removed', detail: 'Category deleted.', life: 3000 });
                loadCategories();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Cannot delete category with linked products.', life: 4000 });
            }
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Category Management" />
        <Toast />

        <div class="p-8 bg-deep min-h-[calc(100vh-64px)] flex flex-col">
            <!-- Header Section -->
            <div class="max-w-[1600px] w-full mx-auto mb-10 pb-8 border-b border-zinc-900 flex justify-between items-end">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-orange-400 uppercase tracking-[0.2em] block mb-2 font-mono">Classification Registry</span>
                    <h1 class="text-3xl font-bold text-primary tracking-tight m-0 mb-2">Category Center</h1>
                    <p class="text-secondary text-sm max-w-2xl leading-relaxed">Define and manage product categories. Set unique codes used for automated SKU generation and reporting.</p>
                </div>
                <div v-if="can('manage-products')" class="flex gap-4">
                    <Button label="ESTABLISH CATEGORY" icon="pi pi-plus" 
                            class="!bg-orange-500 !border-none !text-zinc-950 !px-6 !h-12 !font-bold !text-[11px] uppercase tracking-widest shadow-[0_0_20px_rgba(249,115,22,0.2)] hover:!bg-orange-400 active:scale-95 transition-all" 
                            @click="openNew" />
                </div>
            </div>

            <!-- Category Grid -->
            <div class="max-w-[1600px] w-full mx-auto flex-1 overflow-y-auto custom-scrollbar pb-20">
                <div v-if="loading" class="flex justify-center items-center py-32">
                    <i class="pi pi-spin pi-spinner text-orange-400 text-4xl"></i>
                </div>
                
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div v-for="cat in categories" :key="cat.id" 
                         class="bg-panel/40 border rounded-2xl overflow-hidden flex flex-col transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 relative"
                         :class="cat.is_active ? 'border-panel-border/80 shadow-[0_5px_30px_rgba(0,0,0,0.5)]' : 'border-zinc-900 opacity-60 grayscale'">
                        
                        <!-- Glow Accent -->
                        <div class="absolute top-0 right-0 w-32 h-32 bg-orange-500/5 blur-[50px] -mr-16 -mt-16 rounded-full"></div>

                        <div class="p-6 border-b border-panel-border/60 flex justify-between items-start bg-panel/60 relative z-10">
                            <div class="flex flex-col gap-2">
                                <div class="w-14 h-14 rounded-xl bg-deep border border-orange-500/20 flex items-center justify-center shadow-inner relative group">
                                    <span v-if="cat.code" class="text-xs font-black text-orange-400 font-mono tracking-tighter">{{ cat.code }}</span>
                                    <i v-else class="pi pi-tags text-muted text-lg"></i>
                                    <div class="absolute -top-1 -right-1 w-3 h-3 rounded-full border-2 border-zinc-950" :class="cat.is_active ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-zinc-700'"></div>
                                </div>
                                <h3 class="text-lg font-bold text-primary tracking-tight m-0 mt-2">{{ cat.name }}</h3>
                                <p class="text-secondary text-[11px] leading-relaxed m-0 h-8 line-clamp-2 overflow-hidden">{{ cat.description || 'No description provided.' }}</p>
                            </div>
                            <div v-if="can('manage-products')" class="flex gap-1">
                                <button @click="editCategory(cat)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none outline-none text-secondary hover:text-primary hover:bg-panel-hover transition-colors cursor-pointer">
                                    <i class="pi pi-pencil text-xs"></i>
                                </button>
                                <button @click="deleteCategory(cat)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none outline-none text-secondary hover:text-red-400 hover:bg-red-500/10 transition-colors cursor-pointer">
                                    <i class="pi pi-trash text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-deep/30 flex justify-between items-center">
                            <span class="text-[9px] font-bold text-muted uppercase tracking-widest font-mono">System Code</span>
                            <span class="font-mono text-[10px] font-black" :class="cat.code ? 'text-orange-500' : 'text-muted italic'">{{ cat.code || 'NULL' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dialog -->
            <Dialog 
                v-model:visible="dialogVisible" 
                :modal="true" 
                class="!bg-transparent !border-none !shadow-none ring-0 outline-none"
                :pt="{
                    root: { class: 'p-0 sm:m-4 max-w-lg w-full' },
                    content: { class: 'p-0 !bg-transparent' }
                }"
                :showHeader="false"
            >
                <div class="bg-deep border border-panel-border rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-500 ring-1 ring-white/5">
                    <div class="px-8 py-6 border-b border-zinc-900 bg-panel/50 flex justify-between items-center">
                        <div class="flex flex-col">
                            <div class="text-[9px] font-bold text-orange-500 font-mono tracking-[0.2em] mb-1">CLASSIFICATION_SYNC</div>
                            <h2 class="text-primary text-xl font-bold tracking-tight m-0">{{ form.id ? 'Modify Category' : 'Establish New Category' }}</h2>
                        </div>
                        <Button icon="pi pi-times" class="!text-muted hover:!text-primary !bg-transparent !border-none !w-10 !h-10 hover:!bg-panel transition-colors" @click="dialogVisible = false" />
                    </div>

                    <div class="p-8 bg-[radial-gradient(circle_at_top_right,rgba(249,115,22,0.03),transparent_40%)]">
                        <div class="grid grid-cols-12 gap-x-6 gap-y-6">
                            <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Category Name *</label>
                                <InputText v-model="form.name" placeholder="E.g. Electronics" 
                                           class="!bg-panel/50 !border-panel-border !text-primary !h-12 !font-bold focus:!border-orange-500/40"
                                           :class="{'!border-red-500/50': submitted && !form.name}" />
                            </div>
                            <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">SKU Prefix (Code)</label>
                                <InputText v-model="form.code" placeholder="E.g. ELE" maxlength="10"
                                           class="!bg-panel/50 !border-panel-border !text-orange-400 !h-12 !font-mono font-black focus:!border-orange-500/30 uppercase" />
                                <span class="text-[8px] text-muted font-bold uppercase tracking-tight mt-1">Used for SKU prefixing</span>
                            </div>

                            <div class="col-span-12 flex flex-col gap-2">
                                <label class="text-[10px] font-bold text-secondary uppercase tracking-widest font-mono">Description</label>
                                <Textarea v-model="form.description" rows="3" placeholder="Define the purpose of this classification..."
                                          class="!bg-panel/50 !border-panel-border !text-zinc-300 focus:!border-orange-500/30 w-full !p-4" />
                            </div>

                            <div class="col-span-12 pt-2 flex items-center justify-between p-4 bg-panel/30 rounded-xl border border-panel-border/80">
                                <div class="flex flex-col">
                                    <span class="text-primary font-bold text-[11px] uppercase tracking-tight">Active Status</span>
                                    <span class="text-secondary text-[9px] font-mono uppercase mt-0.5">Turn off to hide from catalog forms</span>
                                </div>
                                <ToggleSwitch v-model="form.is_active" 
                                             :pt="{ slider: ({ props }) => ({ class: props.modelValue ? '!bg-orange-500' : '!bg-zinc-700' }) }" />
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-6 border-t border-zinc-900 bg-panel/50 flex justify-end gap-3">
                        <Button label="DISCARD" class="!bg-transparent !border-panel-border !text-secondary hover:!text-primary hover:!border-zinc-600 !px-6 !h-11 !font-bold !text-[10px] uppercase tracking-widest border transition-colors" @click="dialogVisible = false" />
                        <Button label="SAVE CATEGORY" class="!bg-orange-500 !border-none !text-zinc-950 !px-10 !h-11 !font-bold !text-[10px] uppercase tracking-widest shadow-lg shadow-orange-500/10 hover:!bg-orange-400 active:scale-95 transition-all" @click="saveCategory" />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>


