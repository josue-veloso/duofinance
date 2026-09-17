<template>
  <div
    class="card relative overflow-hidden transition"
    :class="[
      compact ? 'hover:shadow-md' : 'border-blue-200/80 bg-gradient-to-b from-blue-50/70 to-white dark:border-blue-900/40 dark:from-slate-900/90 dark:to-slate-900/50',
      summary.verdictAmount < 0.01 ? 'border-emerald-200/70 bg-emerald-50/50 dark:border-emerald-900/30 dark:bg-emerald-950/20' : ''
    ]"
  >
    <!-- Header with title and status badge -->
    <div class="mb-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="flex h-2 w-2 rounded-full" :class="summary.verdictAmount < 0.01 ? 'bg-emerald-500' : 'bg-blue-600 animate-pulse'" />
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
        Mês Quitado
      </span>
      <span
        v-else-if="summary.verdictAmount >= 0.01"
        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
      >
        Em aberto
      </span>
    </div>

    <!-- Balanced state (No debts) -->
    <div v-if="summary.verdictAmount < 0.01" class="flex items-center gap-4 py-3">
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

    <!-- Transfer Flow State -->
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
import { CheckCircle2 } from 'lucide-vue-next';
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

function fmt(v: number) {
  return formatCurrency(v);
}
</script>
