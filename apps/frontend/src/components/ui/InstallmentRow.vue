<template>
  <li class="card flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3 transition hover:border-slate-300 dark:hover:border-slate-700">
    <div class="flex items-center gap-3 flex-1 min-w-0">
      <span class="h-2.5 w-2.5 shrink-0 rounded-full shadow-sm" :style="{ background: installment.expense.category?.color || '#64748b' }" />

      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1.5 flex-wrap">
          <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ installment.expense.description }}</p>

          <!-- Recurring badge -->
          <span
            v-if="installment.expense.recurringExpenseId"
            class="inline-flex items-center gap-1 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950/80 dark:text-blue-300"
            title="Despesa recorrente"
          >
            <Repeat class="h-2.5 w-2.5" />
            Fixa
          </span>

          <!-- Split Mode badge -->
          <span
            v-if="installment.expense.isShared && installment.expense.splitMode === 'EQUAL_50_50'"
            class="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 dark:bg-purple-950/80 dark:text-purple-300"
          >
            50/50
          </span>
          <span
            v-else-if="installment.expense.isShared && installment.expense.splitMode === 'CUSTOM'"
            class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300"
          >
            {{ Math.round((installment.expense.customUser1Quota || 0.5) * 100) }}% / {{ 100 - Math.round((installment.expense.customUser1Quota || 0.5) * 100) }}%
          </span>

          <!-- Status badge -->
          <span
            v-if="installment.expense.status === 'PENDING_APPROVAL'"
            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950/80 dark:text-amber-300"
          >
            <Clock class="h-3 w-3" />
            Pendente
          </span>
          <span
            v-else-if="installment.expense.status === 'REJECTED'"
            class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800 dark:bg-rose-950/80 dark:text-rose-300"
          >
            <XCircle class="h-3 w-3" />
            Recusado
          </span>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          {{ installment.expense.paidByUser.name }} ·
          {{ installment.expense.category?.name || 'Sem categoria' }} ·
          Parcela {{ installment.installmentNumber }}/{{ installment.expense.installmentsCount }}
        </p>
      </div>
    </div>

    <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
      <div class="text-left sm:text-right">
        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ fmt(installment.amount) }}</p>
        <p v-if="installment.expense.isShared" class="text-xs font-semibold text-blue-600 dark:text-blue-400">Compartilhado</p>
        <p v-else class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pessoal</p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-1">
        <!-- Partner Approval Actions -->
        <template v-if="installment.expense.status === 'PENDING_APPROVAL'">
          <template v-if="canApprove">
            <button
              class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300"
              title="Aprovar despesa compartilhada"
              @click="emit('approve')"
            >
              <Check class="h-3.5 w-3.5" />
              Aprovar
            </button>
            <button
              class="inline-flex items-center justify-center rounded-lg bg-rose-50 p-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300"
              title="Recusar despesa"
              aria-label="Recusar despesa"
              @click="emit('reject')"
            >
              <X class="h-3.5 w-3.5" />
            </button>
          </template>
          <span v-else class="text-[11px] font-medium text-slate-400 dark:text-slate-500 italic pr-1">
            Aguardando parceiro(a)
          </span>
        </template>

        <!-- Standard Actions -->
        <button
          v-if="showEdit"
          class="rounded-lg px-2 py-1 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50"
          @click="emit('edit')"
        >
          Editar
        </button>
        <button
          v-if="showUndo"
          class="rounded-lg px-2 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/50"
          @click="emit('undo')"
        >
          Desfazer
        </button>
        <button
          v-if="showDelete"
          class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50"
          @click="emit('delete')"
        >
          Remover
        </button>
      </div>
    </div>
  </li>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Repeat, Clock, XCircle, Check, X } from 'lucide-vue-next';
import type { InstallmentWithExpense } from '@duofinance/shared-types';
import { formatCurrency } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';

const props = defineProps<{
  installment: InstallmentWithExpense;
  showDelete?: boolean;
  showEdit?: boolean;
  showUndo?: boolean;
}>();

const emit = defineEmits<{
  delete: [];
  edit: [];
  undo: [];
  approve: [];
  reject: [];
}>();

const authStore = useAuthStore();

const canApprove = computed(() => {
  const currentUserId = authStore.user?.id;
  if (!currentUserId) return false;
  return String(currentUserId) !== String(props.installment.expense.paidByUserId);
});

function fmt(v: number | string) { return formatCurrency(Number(v)); }
</script>
