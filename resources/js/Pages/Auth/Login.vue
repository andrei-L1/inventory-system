<template>
    <Head title="Inventory Login" />

    <div class="min-h-screen flex items-center justify-center bg-[#09090b] p-6 relative font-sans overflow-hidden">
        <!-- Technical Grid Underlay -->
        <div class="absolute inset-0 opacity-[0.05] pointer-events-none" 
             style="background-image: radial-gradient(#ffffff 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>

        <div class="w-full max-w-[400px] relative z-10">
            <!-- Header Group -->
            <div class="mb-8 border-l-2 border-sky-500 pl-6">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-[10px] font-bold text-sky-500 tracking-[0.3em] font-mono">System Online // v2.2.0</span>
                    <div class="h-[1px] flex-1 bg-zinc-800"></div>
                </div>
                <h1 class="text-white text-3xl font-bold tracking-tight uppercase leading-none mb-2" style="text-shadow: none !important;">Inventory System</h1>
                <p class="text-zinc-500 text-[11px] font-bold uppercase tracking-widest font-mono">Secure Access</p>
            </div>

            <!-- Login Manifest Card -->
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-2xl transition-all">
                <!-- Status Bar -->
                <div class="bg-zinc-800/50 px-6 py-2 border-b border-zinc-800 flex justify-between items-center">
                    <span class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest font-mono flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        System: Online
                    </span>
                    <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">
                        SSL: Secured
                    </span>
                </div>

                <div class="p-8">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Error Alert -->
                        <div v-if="form.errors.username" 
                             class="bg-zinc-950 border-l-2 border-red-500 p-4 mb-2">
                            <div class="flex items-center gap-3">
                                <i class="pi pi-shield text-red-500 text-xs"></i>
                                <span class="text-red-500 text-[10px] font-bold uppercase font-mono tracking-wider">Access Error: {{ form.errors.username }}</span>
                            </div>
                        </div>

                        <!-- Username Input -->
                        <div class="space-y-2">
                            <label for="username" class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest font-mono ml-0.5">Username / ID</label>
                            <div class="relative w-full">
                                <i class="pi pi-user absolute left-4 top-1/2 -translate-y-1/2 text-zinc-600 text-[12px] z-10 pointer-events-none"></i>
                                <input 
                                    id="username" 
                                    type="text"
                                    v-model="form.username" 
                                    required 
                                    autofocus 
                                    autocomplete="username" 
                                    placeholder="Enter username..." 
                                    class="w-full pl-11 bg-zinc-950 border border-zinc-800 text-white h-12 text-[13px] font-bold rounded-lg focus:border-zinc-500 outline-none transition-all font-mono placeholder:text-zinc-800"
                                />
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="space-y-2">
                            <label for="password" class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest font-mono ml-0.5">Password / Code</label>
                            <div class="relative w-full">
                                <i class="pi pi-lock absolute left-4 top-1/2 -translate-y-1/2 text-zinc-600 text-[12px] z-10 pointer-events-none"></i>
                                <input 
                                    id="password" 
                                    type="password"
                                    v-model="form.password" 
                                    required 
                                    autocomplete="current-password" 
                                    placeholder="••••••••" 
                                    class="w-full pl-11 bg-zinc-950 border border-zinc-800 text-white h-12 text-[13px] font-bold rounded-lg focus:border-zinc-500 outline-none transition-all font-mono placeholder:text-zinc-800"
                                />
                            </div>
                        </div>

                        <!-- Submit Action -->
                        <div class="pt-2">
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="w-full bg-zinc-100 hover:bg-white text-zinc-900 font-bold h-12 rounded-lg transition-all active:scale-[0.98] disabled:opacity-50 uppercase tracking-[0.2em] text-[11px] flex items-center justify-center gap-3"
                            >
                                <span v-if="!form.processing">Login to Dashboard</span>
                                <i v-else class="pi pi-spin pi-spinner"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Secondary Options -->
                    <div class="relative my-10 flex items-center opacity-40">
                        <div class="flex-1 border-t border-zinc-800"></div>
                        <span class="px-3 text-[9px] font-bold text-zinc-600 uppercase tracking-[0.2em] font-mono whitespace-nowrap">Other Options</span>
                        <div class="flex-1 border-t border-zinc-800"></div>
                    </div>

                    <button 
                        @click="loginWithGoogle"
                        class="w-full bg-zinc-950 border border-zinc-800 text-zinc-300 h-12 rounded-lg font-bold hover:bg-zinc-900 transition-all active:scale-[0.98] flex items-center justify-center gap-3 text-[10px] uppercase tracking-wider"
                    >
                        <i class="pi pi-google opacity-50"></i>
                        Sign in with Google
                    </button>
                </div>
            </div>

            <!-- Global Footer -->
            <div class="mt-8 flex justify-between items-center opacity-30 px-2">
                <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">&copy; 2026 Inventory System</span>
                <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-widest font-mono">Status: Healthy</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({
    username: '',
    password: '',
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

const loginWithGoogle = () => {
    window.location.href = '/auth/google';
};
</script>

<style scoped>
/* Ensure perfect spacing for standard inputs */
input::placeholder {
    opacity: 0.3;
}
</style>
