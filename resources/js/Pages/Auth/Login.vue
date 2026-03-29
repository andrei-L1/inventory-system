<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

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

<template>
    <Head title="Terminal Auth" />

    <div class="auth-container min-h-screen flex items-center justify-center bg-[#09090b] p-6 relative overflow-hidden">
        <!-- Technical Background Grid -->
        <div class="absolute inset-0 opacity-10 pointer-events-none" 
             style="background-image: radial-gradient(#27272a 1px, transparent 1px); background-size: 32px 32px;"></div>
             

        <div class="auth-panel w-full max-w-[420px] relative z-10 transition-all duration-700 animate-in fade-in slide-in-from-bottom-8">
            <div class="glass-surface border border-white/5 rounded-2xl shadow-2xl p-8 backdrop-blur-xl bg-zinc-900/50">
                <div class="text-center mb-10">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-zinc-800 border border-white/10 mb-4 shadow-inner group">
                        <i class="pi pi-server text-sky-400 text-2xl group-hover:scale-110 transition-transform duration-300"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-white tracking-tight mb-1">Stock Command</h1>
                    <p class="text-zinc-500 text-sm uppercase tracking-widest font-medium">Enterprise Inventory Terminal</p>
                </div>
                
                <form @submit.prevent="submit" class="space-y-5">
                    <Message v-if="form.errors.username" severity="error" :closable="false" class="mb-4">
                        {{ form.errors.username }}
                    </Message>
                    
                    <div class="space-y-2">
                        <label for="username" class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider ml-1">Operator ID</label>
                        <IconField>
                            <InputIcon class="pi pi-user text-zinc-500" />
                            <InputText 
                                id="username" 
                                v-model="form.username" 
                                required 
                                autofocus 
                                autocomplete="username" 
                                placeholder="SYS_ADMIN" 
                                class="w-full !bg-zinc-800/50 !border-white/5 !text-white h-12 focus:!border-sky-500/50 transition-colors"
                            />
                        </IconField>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="password" class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider ml-1">Secure Code</label>
                        <IconField>
                            <InputIcon class="pi pi-lock text-zinc-500" />
                            <Password 
                                id="password" 
                                v-model="form.password" 
                                :feedback="false" 
                                required 
                                autocomplete="current-password" 
                                placeholder="••••••••" 
                                toggleMask 
                                class="w-full" 
                                :pt="{
                                    pcInput: {
                                        root: { class: 'w-full !bg-zinc-800/50 !border-white/5 !text-white h-12 focus:!border-sky-500/50 transition-colors' }
                                    }
                                }"
                            />
                        </IconField>
                    </div>
                    
                    <div class="pt-2">
                        <Button 
                            type="submit" 
                            label="Authenticate" 
                            class="w-full !bg-sky-500 !border-none hover:!bg-sky-400 !text-white font-bold h-12 shadow-lg shadow-sky-500/10 transition-all active:scale-[0.98]" 
                            :loading="form.processing" 
                        />
                    </div>
                </form>

                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t border-white/5"></span>
                    </div>
                    <div class="relative flex justify-center text-[10px] uppercase tracking-widest font-bold">
                        <span class="bg-zinc-900 px-3 text-zinc-500">OR CONTINUE WITH</span>
                    </div>
                </div>

                <Button 
                    @click="loginWithGoogle"
                    icon="pi pi-google" 
                    label="Sign in with Google" 
                    class="w-full !bg-white !text-zinc-900 border-none h-12 font-bold hover:!bg-zinc-100 transition-colors active:scale-[0.98]"
                />
            </div>
            
            <div class="mt-8 text-center">
                <span class="text-[10px] text-zinc-600 uppercase tracking-[0.2em] font-medium">
                    Sector 7-G Clearance Level Required &copy; 2026
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-iconfield) {
    width: 100%;
}

:deep(.p-inputtext:focus) {
    box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2);
}

:deep(.p-password-input) {
    border-radius: 8px !important;
}

:deep(.p-message-error) {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.2);
    font-size: 0.8rem;
    padding: 0.75rem;
}
</style>
