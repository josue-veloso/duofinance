<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm space-y-7">
      <div class="text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">DuoFinance</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">Bem-vindo de volta</h1>
        <p class="mt-2 text-sm text-slate-500">Acompanhe gastos compartilhados com clareza e contexto.</p>
      </div>

      <Card>
        <CardContent class="pt-6">
          <form class="space-y-4" @submit.prevent="handleLogin">
            <div class="space-y-1.5">
              <Label for="email">Email</Label>
              <Input id="email" v-model="form.email" type="email" placeholder="voce@email.com" required />
            </div>
            <div class="space-y-1.5">
              <Label for="password">Senha</Label>
              <Input id="password" v-model="form.password" type="password" placeholder="••••••••" required />
            </div>

            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

            <Button type="submit" class="w-full" :disabled="loading">
              <span v-if="loading">Entrando…</span>
              <span v-else>Entrar</span>
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="text-center text-sm text-slate-500">
        Não tem conta?
        <RouterLink to="/register" class="font-semibold text-slate-900 hover:underline">Criar conta</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const loading = ref(false);
const error = ref('');

async function handleLogin() {
  loading.value = true;
  error.value = '';
  try {
    await auth.login(form);
    router.push('/dashboard');
  } catch (e: unknown) {
    error.value = extractMessage(e);
  } finally {
    loading.value = false;
  }
}

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object' && 'response' in e) {
    const r = (e as { response?: { data?: { error?: string } } }).response;
    return r?.data?.error ?? 'Credenciais inválidas';
  }
  return 'Erro ao conectar';
}
</script>
