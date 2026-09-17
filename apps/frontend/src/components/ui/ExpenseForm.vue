<template>
  <div class="w-full max-w-md space-y-4 p-6">
    <div class="flex items-center justify-between">
      <h3 class="font-bold text-slate-900 dark:text-white">Novo gasto</h3>
    </div>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="space-y-1.5">
        <Label for="ef-description">Descrição</Label>
        <Input id="ef-description" v-model="form.description" type="text" placeholder="Ex: Mercado, Aluguel, Farmácia" required />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1.5">
          <Label for="ef-amount">Valor total (R$)</Label>
          <Input
            id="ef-amount"
            v-model="amountMasked"
            type="text"
            inputmode="numeric"
            placeholder="R$ 0,00"
            required
          />
        </div>
        <div class="space-y-1.5">
          <Label for="ef-installments">Parcelas</Label>
          <Input
            id="ef-installments"
            v-model.number="form.installmentsCount"
            type="number"
            min="1"
            max="96"
            :disabled="form.isRecurring"
            required
          />
        </div>
      </div>

      <div class="space-y-1.5">
        <Label for="ef-date">Data da compra</Label>
        <Input id="ef-date" v-model="form.purchaseDate" type="date" required />
      </div>

      <div class="space-y-1.5">
        <Label>Categoria</Label>
        <select
          v-model="form.categoryId"
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-900"
        >
          <option value="">Sem categoria</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.name }}
          </option>
        </select>
      </div>

      <!-- Shared & Split Options -->
      <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5 space-y-3 dark:border-slate-800 dark:bg-slate-900/40">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <input id="is-shared" v-model="form.isShared" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
            <label for="is-shared" class="text-sm font-semibold text-slate-800 dark:text-slate-200">Gasto compartilhado</label>
          </div>
          <span v-if="form.isShared" class="text-[11px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/60 px-2 py-0.5 rounded-full">
            Requer aprovação
          </span>
        </div>

        <div v-if="form.isShared" class="space-y-2 pt-1 border-t border-slate-200/60 dark:border-slate-800/80">
          <Label class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Divisão do valor</Label>
          <div class="grid grid-cols-3 gap-1.5">
            <button
              type="button"
              class="rounded-lg border px-2 py-1.5 text-xs font-bold transition text-center"
              :class="form.splitMode === 'DEFAULT' ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
              @click="form.splitMode = 'DEFAULT'"
            >
              Padrão Casal
            </button>
            <button
              type="button"
              class="rounded-lg border px-2 py-1.5 text-xs font-bold transition text-center"
              :class="form.splitMode === 'EQUAL_50_50' ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
              @click="form.splitMode = 'EQUAL_50_50'"
            >
              Meio a meio (50%)
            </button>
            <button
              type="button"
              class="rounded-lg border px-2 py-1.5 text-xs font-bold transition text-center"
              :class="form.splitMode === 'CUSTOM' ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
              @click="form.splitMode = 'CUSTOM'"
            >
              Personalizado
            </button>
          </div>

          <div v-if="form.splitMode === 'CUSTOM'" class="mt-2 flex items-center gap-2 rounded-lg bg-white p-2 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <span class="text-xs text-slate-500 font-medium">Minha cota:</span>
            <input
              v-model.number="customPercent"
              type="number"
              min="1"
              max="99"
              class="h-7 w-16 rounded border border-slate-300 px-1 text-center text-xs font-bold dark:bg-slate-900 dark:border-slate-600 dark:text-white"
            />
            <span class="text-xs text-slate-600 dark:text-slate-300 font-semibold">% (Parceiro(a): {{ 100 - customPercent }}%)</span>
          </div>
        </div>
      </div>

      <!-- Recurring Option -->
      <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5 space-y-2 dark:border-slate-800 dark:bg-slate-900/40">
        <div class="flex items-center gap-2">
          <input id="is-recurring" v-model="form.isRecurring" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
          <label for="is-recurring" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-slate-200 cursor-pointer">
            <Repeat class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
            <span>Repetir mensalmente (Despesa fixa)</span>
          </label>
        </div>

        <div v-if="form.isRecurring" class="pt-2 grid grid-cols-2 gap-3 border-t border-slate-200/60 dark:border-slate-800/80">
          <div class="space-y-1">
            <Label for="rec-day" class="text-xs">Dia do vencimento</Label>
            <Input id="rec-day" v-model.number="form.dayOfMonth" type="number" min="1" max="28" required />
          </div>
          <div class="space-y-1">
            <Label for="rec-start" class="text-xs">Mês inicial</Label>
            <Input id="rec-start" v-model="form.startMonth" type="month" required />
          </div>
        </div>
      </div>

      <p v-if="form.installmentsCount > 1 && form.totalAmount > 0 && !form.isRecurring" class="text-xs text-slate-500">
        ≈ {{ fmt(form.totalAmount / form.installmentsCount) }} / parcela por {{ form.installmentsCount }} meses
      </p>

      <p v-if="error" class="text-sm font-semibold text-rose-600 dark:text-rose-400">{{ error }}</p>

      <div class="flex gap-3 pt-1">
        <Button type="button" variant="outline" class="flex-1" @click="emit('cancel')">Cancelar</Button>
        <Button type="submit" class="flex-1" :disabled="loading || form.totalAmount <= 0">
          <span v-if="loading">Salvando…</span>
          <span v-else>Salvar</span>
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Repeat } from 'lucide-vue-next';
import { useExpenseStore } from '@/stores/expenses';
import { api } from '@/services/api';
import type { Category, CreateExpensePayload } from '@duofinance/shared-types';
import { formatCurrency } from '@/utils/format';
import { useToast } from '@/composables/useToast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const emit = defineEmits<{ created: []; cancel: [] }>();
const store = useExpenseStore();
const toast = useToast();

const categories = ref<Category[]>([]);
const loading = ref(false);
const error = ref('');

const today = new Date().toISOString().split('T')[0];
const currentMonth = today.slice(0, 7);

const customPercent = ref(50);

const form = ref({
  description: '',
  totalAmount: 0,
  installmentsCount: 1,
  isShared: true,
  splitMode: 'DEFAULT' as 'DEFAULT' | 'EQUAL_50_50' | 'CUSTOM' | 'FULL_USER1' | 'FULL_USER2',
  categoryId: '',
  purchaseDate: today,
  isRecurring: false,
  dayOfMonth: new Date().getDate() > 28 ? 28 : new Date().getDate(),
  startMonth: currentMonth,
});

// Currency mask — stores cents internally, displays formatted
const amountCents = ref(0);
const amountMasked = computed({
  get: () =>
    amountCents.value === 0
      ? ''
      : (amountCents.value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }),
  set: (value: string | number) => {
    const raw = String(value ?? '').replace(/\D/g, '');
    amountCents.value = raw === '' ? 0 : parseInt(raw, 10);
    form.value.totalAmount = amountCents.value / 100;
  },
});

onMounted(async () => {
  try {
    const { data } = await api.get<{ categories: Category[] }>('/categories');
    categories.value = data.categories;
  } catch { /* no categories */ }
});

async function handleSubmit() {
  loading.value = true;
  error.value = '';
  try {
    const payload: CreateExpensePayload = {
      description: form.value.description,
      totalAmount: form.value.totalAmount,
      installmentsCount: form.value.isRecurring ? 1 : form.value.installmentsCount,
      isShared: form.value.isShared,
      splitMode: form.value.isShared ? form.value.splitMode : 'DEFAULT',
      customUser1Quota: form.value.splitMode === 'CUSTOM' ? customPercent.value / 100 : null,
      status: form.value.isShared ? 'PENDING_APPROVAL' : 'CONFIRMED',
      categoryId: form.value.categoryId || undefined,
      purchaseDate: form.value.purchaseDate,
      isRecurring: form.value.isRecurring,
      dayOfMonth: form.value.isRecurring ? form.value.dayOfMonth : undefined,
      startMonth: form.value.isRecurring ? form.value.startMonth : undefined,
    };

    await store.createExpense(payload);
    if (form.value.isShared) {
      toast.success('Gasto adicionado e enviado para validação!');
    } else {
      toast.success('Gasto pessoal adicionado com sucesso!');
    }
    emit('created');
  } catch (e: unknown) {
    error.value = extractMessage(e);
  } finally {
    loading.value = false;
  }
}

function fmt(v: number) { return formatCurrency(v); }

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object' && 'response' in e) {
    const r = (e as { response?: { data?: { error?: string; message?: string } } }).response;
    return r?.data?.error ?? r?.data?.message ?? 'Erro ao salvar';
  }
  return 'Erro ao conectar';
}
</script>
