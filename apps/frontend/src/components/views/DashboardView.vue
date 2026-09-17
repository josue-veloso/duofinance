<template>
  <div class="space-y-6">
    <!-- Month selector -->
    <div class="flex items-center justify-between">
      <h2 class="section-title">Painel</h2>
      <MonthPicker v-model="selectedMonth" />
    </div>

    <!-- Summary cards -->
    <div v-if="clearing.loading" class="grid grid-cols-2 gap-3">
      <div v-for="n in 4" :key="n" class="card h-24 animate-pulse bg-white/80 dark:bg-slate-800/60" />
    </div>

    <template v-else-if="clearing.summary">
      <!-- Pending mutual approval alert banner -->
      <div
        v-if="(clearing.summary.pendingApprovalCount || 0) > 0"
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 to-orange-50/60 p-4 dark:border-amber-900/40 dark:from-amber-950/40 dark:to-orange-950/20 shadow-sm"
      >
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
            <Clock class="h-5 w-5" />
          </span>
          <div>
            <p class="text-sm font-bold text-amber-900 dark:text-amber-200">
              {{ clearing.summary.pendingApprovalCount }} {{ clearing.summary.pendingApprovalCount === 1 ? 'gasto pendente de validação' : 'gastos pendentes de validação' }}
            </p>
            <p class="text-xs text-amber-700 dark:text-amber-400">
              Há despesas compartilhadas aguardando a aprovação do casal neste mês.
            </p>
          </div>
        </div>
        <RouterLink to="/expenses" class="btn-primary text-xs !py-2 !px-3.5 shrink-0 self-start sm:self-center">
          Revisar e aprovar
        </RouterLink>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="card">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total compartilhado</p>
          <p class="mt-1 text-2xl font-black tracking-tight text-slate-900 dark:text-white">{{ fmt(clearing.summary.totalShared) }}</p>
        </div>
        <div class="card">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Variação vs mês anterior</p>
          <p class="mt-1 text-2xl font-black tracking-tight" :class="(clearing.summary.deltaVsPreviousMonth || 0) >= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
            {{ (clearing.summary.deltaVsPreviousMonth || 0) >= 0 ? '+' : '' }}{{ fmt(clearing.summary.deltaVsPreviousMonth || 0) }}
          </p>
        </div>
        <div class="card">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ user1Name }} pagou</p>
          <p class="mt-1 text-xl font-extrabold text-slate-900 dark:text-white">{{ fmt(clearing.summary.user1Paid) }}</p>
        </div>
        <div class="card">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ user2Name }} pagou</p>
          <p class="mt-1 text-xl font-extrabold text-slate-900 dark:text-white">{{ fmt(clearing.summary.user2Paid) }}</p>
        </div>
      </div>

      <!-- Top Categorias with Visual Data Viz Progress Bar -->
      <div class="card space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Distribuição por Categorias</p>
          <span v-if="(clearing.summary.topCategories || []).length" class="text-xs font-semibold text-slate-400 dark:text-slate-500">
            Top {{ (clearing.summary.topCategories || []).length }}
          </span>
        </div>

        <!-- Segmented Colored Distribution Bar -->
        <div v-if="(clearing.summary.topCategories || []).length" class="space-y-1">
          <div class="flex h-3.5 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 shadow-inner dark:bg-slate-800">
            <div
              v-for="(cat, idx) in clearing.summary.topCategories"
              :key="cat.categoryId || cat.name"
              class="h-full transition-all duration-500 first:rounded-l-full last:rounded-r-full"
              :style="{
                width: `${cat.percent}%`,
                backgroundColor: getCategoryColor(idx)
              }"
              :title="`${cat.name}: ${cat.percent}%`"
            />
          </div>
        </div>

        <!-- Category Items List -->
        <ul class="space-y-2">
          <li
            v-for="(cat, idx) in clearing.summary.topCategories || []"
            :key="cat.categoryId || cat.name"
            class="group relative flex items-center justify-between overflow-hidden rounded-xl border border-slate-100 bg-slate-50/70 px-3.5 py-2.5 transition hover:border-slate-200 dark:border-slate-800/80 dark:bg-slate-800/40 dark:hover:border-slate-700"
          >
            <div class="z-10 flex items-center gap-3">
              <span class="h-3 w-3 shrink-0 rounded-full shadow-sm" :style="{ backgroundColor: getCategoryColor(idx) }" />
              <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ cat.name }}</span>
            </div>
            <div class="z-10 flex items-center gap-2">
              <span class="text-sm font-bold text-slate-900 dark:text-white">{{ fmt(cat.total) }}</span>
              <span class="rounded-full border border-slate-200/60 bg-white/80 px-2 py-0.5 text-xs font-bold text-slate-600 dark:border-slate-700/60 dark:bg-slate-900/80 dark:text-slate-300">
                {{ cat.percent }}%
              </span>
            </div>
            <!-- Background micro progress bar -->
            <div
              class="absolute bottom-0 left-0 top-0 rounded-xl opacity-10 transition-all duration-500 dark:opacity-15"
              :style="{
                width: `${cat.percent}%`,
                backgroundColor: getCategoryColor(idx)
              }"
            />
          </li>
          <li v-if="!(clearing.summary.topCategories || []).length" class="text-sm text-slate-500 dark:text-slate-400">
            Sem dados de categoria no mês.
          </li>
        </ul>
      </div>

      <!-- Mini verdict teaser -->
      <VerdictCard
        :summary="clearing.summary"
        :user1-name="user1Name"
        :user2-name="user2Name"
        compact
      />
    </template>

    <!-- Recent installments -->
    <section>
      <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        Lançamentos em {{ monthLabel }}
      </h3>

      <p v-if="!clearing.loading && !clearing.summary?.installments?.length" class="text-sm text-slate-500 dark:text-slate-400">
        Nenhum gasto compartilhado neste mês.
      </p>

      <ul class="space-y-2">
        <InstallmentRow
          v-for="inst in clearing.summary?.installments"
          :key="inst.id"
          :installment="inst"
        />
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { Clock } from 'lucide-vue-next';
import { useClearingStore } from '@/stores/clearing';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import VerdictCard from '@/components/ui/VerdictCard.vue';
import InstallmentRow from '@/components/ui/InstallmentRow.vue';
import { formatCurrency, currentMonthStr, monthLabel as getMonthLabel } from '@/utils/format';

const clearing = useClearingStore();

const selectedMonth = ref(currentMonthStr());

const user1Name = computed(() => clearing.summary?.couple?.user1?.name ?? 'Usuário 1');
const user2Name = computed(() => clearing.summary?.couple?.user2?.name ?? 'Usuário 2');
const monthLabel = computed(() => getMonthLabel(selectedMonth.value));

const palette = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
function getCategoryColor(idx: number) {
  return palette[idx % palette.length];
}

function fmt(v: number) {
  return formatCurrency(v);
}

watch(selectedMonth, (m) => clearing.fetchSummary(m), { immediate: false });

onMounted(() => clearing.fetchSummary(selectedMonth.value));
</script>
