<template>
  <div class="space-y-6">
    <h2 class="section-title">Casal</h2>

    <!-- Already in couple -->
    <template v-if="coupleData">
      <div class="card space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Parceiros</h3>
        <div class="flex gap-4">
          <div class="flex-1 text-center">
            <div class="mx-auto mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-700">
              {{ coupleData.user1?.name?.slice(0, 1).toUpperCase() }}
            </div>
            <p class="font-semibold text-slate-900">{{ coupleData.user1?.name }}</p>
            <p class="text-xs text-slate-500">{{ coupleData.user1?.email }}</p>
            <p class="mt-1 badge-positive">{{ (Number(coupleData.user1Quota) * 100).toFixed(0) }}%</p>
          </div>
          <div class="flex items-center text-slate-300">|</div>
          <div class="flex-1 text-center">
            <div class="mx-auto mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-700">
              {{ coupleData.user2?.name?.slice(0, 1).toUpperCase() }}
            </div>
            <p class="font-semibold text-slate-900">{{ coupleData.user2?.name }}</p>
            <p class="text-xs text-slate-500">{{ coupleData.user2?.email }}</p>
            <p class="mt-1 badge-positive">{{ (Number(coupleData.user2Quota) * 100).toFixed(0) }}%</p>
          </div>
        </div>
        <p class="text-center text-xs text-slate-500">A porcentagem define a cota de responsabilidade sobre os gastos compartilhados.</p>
      </div>
    </template>

    <!-- Create couple form -->
    <template v-else>
      <div class="card space-y-4">
        <h3 class="text-sm font-semibold text-slate-900">Vincular parceiro(a)</h3>
        <p class="text-sm text-slate-500">Informe o email do parceiro(a) que já possui conta no DuoFinance.</p>

        <form class="space-y-4" @submit.prevent="handleCreate">
          <div>
            <label class="label">Email do parceiro(a)</label>
            <input v-model="form.partnerEmail" type="email" class="input" placeholder="parceiro@email.com" required />
          </div>
          <div>
            <label class="label">Minha cota (%)</label>
            <input v-model.number="form.user1QuotaPercent" type="number" class="input" min="1" max="99" step="1" required />
            <p class="mt-1 text-xs text-slate-500">A cota do parceiro será {{ 100 - form.user1QuotaPercent }}%</p>
          </div>

          <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

          <button type="submit" class="btn-primary" :disabled="loading">
            <span v-if="loading">Vinculando…</span>
            <span v-else>Vincular</span>
          </button>
        </form>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import type { Couple } from '@duofinance/shared-types';

const auth = useAuthStore();

const coupleData = ref<Couple | null>(null);
const loading = ref(false);
const error = ref('');
const form = ref({ partnerEmail: '', user1QuotaPercent: 50 });

onMounted(async () => {
  try {
    const { data } = await api.get<{ couple: Couple }>('/couple');
    coupleData.value = data.couple;
  } catch {
    // no couple yet
  }
});

async function handleCreate() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.post<{ couple: Couple }>('/couple', {
      partnerEmail: form.value.partnerEmail,
      user1Quota: form.value.user1QuotaPercent / 100,
    });
    coupleData.value = data.couple;
    auth.updateCoupleId(data.couple.id);
  } catch (e: unknown) {
    error.value = extractMessage(e);
  } finally {
    loading.value = false;
  }
}

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object' && 'response' in e) {
    const r = (e as { response?: { data?: { error?: string } } }).response;
    return r?.data?.error ?? 'Erro';
  }
  return 'Erro ao conectar';
}
</script>
