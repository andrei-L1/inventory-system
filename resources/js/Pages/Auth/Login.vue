<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';

const form = useForm({
    username: '',
    password: '',
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Terminal Auth" />

    <div class="layout-center">
        <div class="sharp-panel" style="max-width: 440px; width: 100%;">
            <div class="text-center mb-6">
                <!-- Sharp Technical Icon -->
                <i class="pi pi-server brand-accent" style="font-size: 2rem; margin-bottom: 1rem; display: inline-block;"></i>
                
                <h1 class="brand-title">Stock Command</h1>
                <p class="text-muted">ENTERPRISE INVENTORY TERMINAL.</p>
            </div>
            
            <form @submit.prevent="submit" class="flex-col gap-4">
                
                <Message v-if="form.errors.username" severity="error" :closable="false" style="padding: 0.5rem; width: 100%; margin-bottom: 1rem; text-align: center; border-radius: 2px;">
                    {{ form.errors.username }}
                </Message>
                
                <div class="flex-col gap-2">
                    <label for="username" style="font-size: 0.75rem; font-weight: 600; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.1em;">Operator ID</label>
                    <InputText id="username" v-model="form.username" required autofocus autocomplete="username" placeholder="SYS_ADMIN" class="w-full p-inputtext-lg" />
                </div>
                
                <div class="flex-col gap-2 mt-4">
                    <label for="password" style="font-size: 0.75rem; font-weight: 600; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.1em;">Secure Code</label>
                    <Password id="password" v-model="form.password" :feedback="false" required autocomplete="current-password" placeholder="••••••••" toggleMask class="w-full" inputClass="w-full p-inputtext-lg" />
                </div>
                
                <div style="margin-top: 2rem;">
                    <Button type="submit" label="Authenticate" class="w-full p-button-primary" style="height: 3.2rem; font-size: 1rem;" :loading="form.processing" />
                </div>
            </form>
        </div>
        
        <div style="margin-top: 2rem; text-align: center; color: #52525b; font-size: 0.75rem; letter-spacing: 0.15em;">
            PHASE 1.2 CORE ENGINE &copy; 2026
        </div>
    </div>
</template>
