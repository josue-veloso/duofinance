<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm space-y-7">
      <div class="text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">DuoFinance</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">Criar conta</h1>
        <p class="mt-2 text-sm text-slate-500">Configure seu espaço e convide seu parceiro para começar.</p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="handleRegister">
            <div class="space-y-1.5">
              <Label for="name">Nome</Label>
              <Input id="name" v-model="form.name" type="text" placeholder="Seu nome" required />
            </div>
            <div class="space-y-1.5">
              <Label for="email">Email</Label>
              <Input id="email" v-model="form.email" type="email" placeholder="voce@email.com" required />
            </div>
            <div class="space-y-1.5">
              <Label for="password">Senha</Label>
              <Input id="password" v-model="form.password" type="password" placeholder="mínimo 8 caracteres" minlength="8" required />
            </div>

            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
            <p v-if="success" class="text-sm text-emerald-600">{{ success }}</p>

            <Button type="submit" class="w-full" :disabled="loading">
              <span v-if="loading">Criando…</span>
              <span v-else>Criar conta</span>
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="text-center text-sm text-slate-500">
        Já tem conta?
        <RouterLink to="/login" class="font-semibold text-slate-900 hover:underline">Entrar</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ name: '', email: '', password: '' });
const loading = ref(false);
const error = ref('');
const success = ref('');

async function handleRegister() {
  loading.value = true;
  error.value = '';
  success.value = '';
  try {
    await auth.register(form);
    success.value = 'Conta criada! Redirecionando para login…';
    setTimeout(() => router.push('/login'), 1500);
  } catch (e: unknown) {
    error.value = extractMessage(e);
  } finally {
    loading.value = false;
  }
}

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object' && 'response' in e) {
    const r = (e as { response?: { data?: { error?: string } } }).response;
    return r?.data?.error ?? 'Erro ao criar conta';
  }
  return 'Erro ao conectar';
}
</script>
