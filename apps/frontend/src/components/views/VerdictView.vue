<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="section-title">Acerto do Mês</h2>
      <MonthPicker v-model="selectedMonth" />
    </div>

    <div v-if="clearing.loading" class="card h-48 animate-pulse bg-white/80" />

    <template v-else-if="clearing.summary">
      <!-- Balance breakdown -->
      <div class="card space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Resumo financeiro</h3>
        <div class="grid grid-cols-3 text-center gap-4">
          <div>
            <p class="text-xs text-slate-500">{{ user1Name }}</p>
            <p class="text-lg font-bold text-slate-900">{{ fmt(clearing.summary.user1Paid) }}</p>
            <p class="text-xs" :class="clearing.summary.user1Balance >= 0 ? 'text-emerald-600' : 'text-rose-600'">
              {{ clearing.summary.user1Balance >= 0 ? '+' : '' }}{{ fmt(clearing.summary.user1Balance) }}
            </p>
          </div>
          <div class="flex items-center justify-center text-slate-300 text-xl">|</div>
          <div>
            <p class="text-xs text-slate-500">{{ user2Name }}</p>
            <p class="text-lg font-bold text-slate-900">{{ fmt(clearing.summary.user2Paid) }}</p>
            <p class="text-xs" :class="clearing.summary.user2Balance >= 0 ? 'text-emerald-600' : 'text-rose-600'">
              {{ clearing.summary.user2Balance >= 0 ? '+' : '' }}{{ fmt(clearing.summary.user2Balance) }}
            </p>
          </div>
        </div>

        <div class="border-t border-slate-200 pt-4">
          <p class="text-xs text-slate-500">Total compartilhado</p>
          <p class="text-xl font-bold text-slate-900">{{ fmt(clearing.summary.totalShared) }}</p>
        </div>
      </div>

      <!-- Verdict card -->
      <VerdictCard
        :summary="clearing.summary"
        :user1-name="user1Name"
        :user2-name="user2Name"
      />

      <!-- Close month -->
      <div v-if="!clearing.summary.isClosed" class="card border-blue-200 bg-blue-50/70 dark:border-blue-900/40 dark:bg-blue-950/20">
        <p class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Fechamento guiado</p>
        <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">
          Revise, confirme e finalize. Depois disso, o resultado fica registrado no histórico.
        </p>

        <div
          v-if="(clearing.summary.pendingApprovalCount || 0) > 0"
          class="mb-3 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300"
        >
          <AlertCircle class="h-4 w-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
          <span>Existem {{ clearing.summary.pendingApprovalCount }} {{ clearing.summary.pendingApprovalCount === 1 ? 'despesa pendente' : 'despesas pendentes' }} de validação. Aprove ou recuse todas antes de realizar o fechamento.</span>
        </div>

        <button
          class="btn-primary"
          :disabled="closing || (clearing.summary.pendingApprovalCount || 0) > 0"
          @click="closeDialogOpen = true"
        >
          <span v-if="closing">Fechando…</span>
          <span v-else>Iniciar fechamento</span>
        </button>
      </div>

      <div v-else class="card border-emerald-200 bg-emerald-50/70">
        <p class="text-sm font-semibold text-emerald-700">Mês arquivado em {{ closedAtFormatted }}</p>
      </div>

      <div class="card space-y-3">
        <p class="text-sm font-semibold text-slate-900">Histórico de fechamentos</p>
        <ul class="space-y-2">
          <li v-for="h in clearing.history" :key="h.id" class="flex items-center justify-between rounded-xl border border-slate-200 p-3">
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ fmtMonth(h.month) }}</p>
              <p class="text-xs text-slate-500">Total compartilhado: {{ fmt(Number(h.totalShared)) }}</p>
            </div>
            <p class="text-sm font-semibold text-slate-700">{{ fmt(Number(h.verdictAmount)) }}</p>
          </li>
          <li v-if="!clearing.history.length" class="text-sm text-slate-500">Sem meses fechados ainda.</li>
        </ul>
      </div>

      <div class="card space-y-3">
        <p class="text-sm font-semibold text-slate-900">Dívidas e pagamentos</p>
        <ul class="space-y-2">
          <li v-for="d in clearing.debt?.outstanding || []" :key="`${d.payerId}-${d.receiverId}`" class="rounded-lg border border-slate-200 p-3">
            <p class="text-sm text-slate-700">{{ userName(d.payerId) }} deve para {{ userName(d.receiverId) }}</p>
            <p class="text-base font-semibold text-rose-700">{{ fmt(d.amount) }}</p>
          </li>
          <li v-if="!(clearing.debt?.outstanding?.length)" class="text-sm text-slate-500">Sem dívidas em aberto.</li>
        </ul>

        <form class="grid gap-2 sm:grid-cols-2" @submit.prevent="registerPayment">
          <select v-model="paymentForm.payerId" class="input" required>
            <option value="">Quem pagou</option>
            <option v-for="u in coupleUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <select v-model="paymentForm.receiverId" class="input" required>
            <option value="">Quem recebeu</option>
            <option v-for="u in coupleUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <input v-model.number="paymentForm.amount" class="input" type="number" min="0.01" step="0.01" placeholder="Valor pago" required>
          <input v-model="paymentForm.note" class="input" type="text" placeholder="Observação (opcional)">
          <button class="btn-primary sm:col-span-2" type="submit">Registrar pagamento</button>
        </form>
      </div>
    </template>

    <AlertDialog :open="closeDialogOpen" @update:open="closeDialogOpen = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Confirmar fechamento de {{ selectedMonth }}</AlertDialogTitle>
          <AlertDialogDescription>
            Revise os valores: total {{ fmt(clearing.summary?.totalShared || 0) }} e veredito {{ fmt(clearing.summary?.verdictAmount || 0) }}.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Voltar</AlertDialogCancel>
          <AlertDialogAction @click="handleClose">Confirmar e fechar</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { AlertCircle } from 'lucide-vue-next';
import { useClearingStore } from '@/stores/clearing';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import VerdictCard from '@/components/ui/VerdictCard.vue';
import { formatCurrency, currentMonthStr } from '@/utils/format';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useToast } from '@/composables/useToast';

const clearing = useClearingStore();
const toast = useToast();
const selectedMonth = ref(currentMonthStr());
const closing = ref(false);
const closeDialogOpen = ref(false);

const paymentForm = ref({ payerId: '', receiverId: '', amount: 0, note: '' });

const user1Name = computed(() => clearing.summary?.couple?.user1?.name ?? 'Usuário 1');
const user2Name = computed(() => clearing.summary?.couple?.user2?.name ?? 'Usuário 2');
const coupleUsers = computed(() => {
  const u1 = clearing.summary?.couple?.user1;
  const u2 = clearing.summary?.couple?.user2;
  return [u1, u2].filter(Boolean) as Array<{ id: string; name: string }>;
});
const closedAtFormatted = computed(() => {
  const closedAt = clearing.history.find((h) => h.month.startsWith(selectedMonth.value))?.closedAt;
  if (!closedAt) return '';
  return new Date(closedAt).toLocaleDateString('pt-BR');
});

function fmt(v: number) { return formatCurrency(v); }

function fmtMonth(month: string) {
  const [y, m] = month.slice(0, 7).split('-').map(Number);
  return new Date(y, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}

function userName(userId: string) {
  return coupleUsers.value.find((u) => u.id === userId)?.name ?? 'Parceiro';
}

watch(selectedMonth, async (m) => {
  await clearing.fetchSummary(m);
  await clearing.fetchDebt();
}, { immediate: false });

onMounted(async () => {
  await Promise.all([
    clearing.fetchSummary(selectedMonth.value),
    clearing.fetchHistory(),
    clearing.fetchDebt(),
  ]);
});

async function handleClose() {
  closeDialogOpen.value = false;
  closing.value = true;
  try {
    await clearing.closeMonth(selectedMonth.value);
    toast.success('Mês fechado e arquivado com sucesso!');
  } finally {
    closing.value = false;
  }
}

async function registerPayment() {
  await clearing.registerDebtPayment({
    payerId: paymentForm.value.payerId,
    receiverId: paymentForm.value.receiverId,
    amount: paymentForm.value.amount,
    note: paymentForm.value.note || undefined,
    month: selectedMonth.value,
  });
  toast.success('Pagamento registrado com sucesso!');
  paymentForm.value = { payerId: '', receiverId: '', amount: 0, note: '' };
}
</script>

