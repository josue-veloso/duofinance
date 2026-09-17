<template>
  <div
    class="card relative overflow-hidden transition"
    :class="[
      compact ? 'hover:shadow-md' : 'border-blue-200/80 bg-gradient-to-b from-blue-50/70 to-white dark:border-blue-900/40 dark:from-slate-900/90 dark:to-slate-900/50',
      summary.verdictAmount < 0.01 && !previousDebt ? 'border-emerald-200/70 bg-emerald-50/50 dark:border-emerald-900/30 dark:bg-emerald-950/20' : '',
      summary.verdictAmount < 0.01 && previousDebt ? 'border-amber-200/90 bg-gradient-to-b from-amber-50/60 to-white dark:border-amber-900/40 dark:from-amber-950/20 dark:to-slate-900/60' : ''
    ]"
  >
    <!-- Header with title and status badge -->
    <div class="mb-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span
          class="flex h-2 w-2 rounded-full"
          :class="[
            summary.verdictAmount >= 0.01 ? 'bg-blue-600 animate-pulse' : '',
            summary.verdictAmount < 0.01 && previousDebt ? 'bg-amber-500' : '',
            summary.verdictAmount < 0.01 && !previousDebt ? 'bg-emerald-500' : ''
          ]"
        />
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          {{ compact ? 'Resumo de Acerto' : 'Veredito Oficial' }}
        </h3>
      </div>

      <span
        v-if="summary.isClosed"
        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300"
      >
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
        </svg>
        Mês Fechado
      </span>
      <span
        v-else-if="summary.verdictAmount >= 0.01"
        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
      >
        Em aberto
      </span>
      <span
        v-else-if="previousDebt"
        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
      >
        Pendência anterior
      </span>
      <span
        v-else
        class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
      >
        Equilibrado
      </span>
    </div>

    <!-- Balanced state (No month verdict) -->
    <div v-if="summary.verdictAmount < 0.01">
      <!-- Sub-case A: No previous debt at all -> 100% Balanced -->
      <div v-if="!previousDebt" class="flex items-center gap-4 py-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-inner dark:bg-emerald-900/50 dark:text-emerald-300">
          <CheckCircle2 class="h-6 w-6" />
        </div>
        <div>
          <p class="text-base font-bold text-emerald-800 dark:text-emerald-300">Tudo equilibrado!</p>
          <p class="text-xs text-emerald-600 dark:text-emerald-400">
            Nenhuma transferência pendente. Cada um pagou exatamente sua cota proporcional.
          </p>
        </div>
      </div>

      <!-- Sub-case B: Month is balanced, but previous months have unsettled debt -->
      <div v-else class="space-y-3 py-1">
        <div class="flex items-start gap-3.5">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 shadow-inner dark:bg-amber-950/70 dark:text-amber-300 mt-0.5">
            <Clock class="h-5 w-5" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-slate-900 dark:text-white">Gastos deste mês equilibrados</p>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
              Porém, há um saldo de <strong class="font-bold text-amber-800 dark:text-amber-300">{{ fmt(previousDebt.amount) }}</strong> de meses anteriores em aberto ({{ previousDebtPayerName }} deve para {{ previousDebtReceiverName }}).
            </p>
          </div>
        </div>

        <div v-if="compact" class="pt-2 text-center border-t border-amber-100 dark:border-amber-950/40">
          <RouterLink to="/verdict" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 transition">
            <span>Ver acerto e quitar pendência</span>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
            </svg>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- Transfer Flow State (Month has an active verdict) -->
    <div v-else class="space-y-4">
      <div class="flex flex-col items-center justify-between gap-3 sm:flex-row sm:gap-4 py-2">
        <!-- Payer profile -->
        <div class="flex items-center gap-3 sm:flex-col sm:text-center w-full sm:w-1/3">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-rose-500 to-amber-500 text-base font-extrabold text-white shadow-md shadow-rose-500/20">
            {{ payerName.slice(0, 1).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Devedor</p>
            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ payerName }}</p>
          </div>
        </div>

        <!-- Transfer flow visual center -->
        <div class="my-2 sm:my-0 flex flex-1 flex-col items-center justify-center px-2 text-center w-full">
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            Deve transferir
          </span>

          <div class="my-2 flex w-full items-center justify-center gap-2">
            <div class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-rose-300 via-rose-400 to-blue-400 dark:from-rose-800 dark:via-blue-700" />
            <div class="shrink-0 rounded-full border border-blue-200/80 bg-white px-4 py-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
              <span class="text-base font-black tracking-tight text-blue-600 dark:text-blue-400 sm:text-xl whitespace-nowrap">
                {{ fmt(summary.verdictAmount) }}
              </span>
            </div>
            <div class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-blue-400 via-emerald-400 to-emerald-300 dark:from-blue-700 dark:to-emerald-700" />
          </div>

          <div class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
            <svg class="h-3.5 w-3.5 text-blue-500 dark:text-blue-400 rotate-90 sm:rotate-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
        </div>

        <!-- Receiver profile -->
        <div class="flex flex-row-reverse sm:flex-col items-center gap-3 sm:text-center w-full sm:w-1/3 justify-start">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-500 text-base font-extrabold text-white shadow-md shadow-emerald-500/20">
            {{ receiverName.slice(0, 1).toUpperCase() }}
          </div>
          <div class="min-w-0 text-right sm:text-center">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Recebedor</p>
            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ receiverName }}</p>
          </div>
        </div>
      </div>

      <!-- Previous debt reminder banner if there's also an unsettled balance from past months -->
      <div
        v-if="previousDebt"
        class="flex items-center gap-2 rounded-xl border border-amber-200/80 bg-amber-50/70 p-2.5 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300"
      >
        <AlertCircle class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
        <span>
          Lembrete: além deste mês, há <strong>{{ fmt(previousDebt.amount) }}</strong> pendente de meses anteriores em aberto ({{ previousDebtPayerName }} deve para {{ previousDebtReceiverName }}).
        </span>
      </div>

      <!-- Compact shortcut link -->
      <div v-if="compact" class="pt-2 text-center border-t border-slate-100 dark:border-slate-800/80">
        <RouterLink to="/verdict" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition">
          <span>Ver acerto completo e quitar</span>
          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
          </svg>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { CheckCircle2, Clock, AlertCircle } from 'lucide-vue-next';
import type { MonthSummary } from '@duofinance/shared-types';
import { formatCurrency } from '@/utils/format';

const props = defineProps<{
  summary: MonthSummary;
  user1Name: string;
  user2Name: string;
  compact?: boolean;
}>();

const payerName = computed(() => {
  if (!props.summary.verdictPayerId) return '—';
  return props.summary.verdictPayerId === props.summary.couple?.user1Id
    ? props.user1Name
    : props.user2Name;
});

const receiverName = computed(() => {
  if (!props.summary.verdictReceiverId) return '—';
  return props.summary.verdictReceiverId === props.summary.couple?.user1Id
    ? props.user1Name
    : props.user2Name;
});

const previousDebt = computed(() => {
  const list = props.summary.outstandingDebt || [];
  return list.find((d) => d.amount >= 0.01) || null;
});

const previousDebtPayerName = computed(() => {
  if (!previousDebt.value) return '';
  return previousDebt.value.payerId === props.summary.couple?.user1Id
    ? props.user1Name
    : props.user2Name;
});

const previousDebtReceiverName = computed(() => {
  if (!previousDebt.value) return '';
  return previousDebt.value.receiverId === props.summary.couple?.user1Id
    ? props.user1Name
    : props.user2Name;
});

function fmt(v: number) {
  return formatCurrency(v);
}
</script>
