import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@/services/api';
import type { MonthSummary, MonthlyClose, DebtStatus, DebtPayment } from '@duofinance/shared-types';

export const useClearingStore = defineStore('clearing', () => {
  const summary = ref<MonthSummary | null>(null);
  const history = ref<MonthlyClose[]>([]);
  const debt = ref<DebtStatus | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchSummary(month: string) {
    loading.value = true;
    error.value = null;
    try {
      const { data } = await api.get<{ summary: MonthSummary }>(`/clearing/${month}`);
      summary.value = data.summary;
    } catch (e: unknown) {
      error.value = extractMessage(e);
    } finally {
      loading.value = false;
    }
  }

  async function closeMonth(month: string) {
    await api.post(`/clearing/${month}/close`);
    await fetchSummary(month);
    await fetchHistory();
    await fetchDebt();
  }

  async function fetchHistory() {
    const { data } = await api.get<{ history: MonthlyClose[] }>('/clearing/history');
    history.value = data.history;
  }

  async function fetchDebt() {
    const { data } = await api.get<{ debt: DebtStatus }>('/clearing/debts');
    debt.value = data.debt;
  }

  async function registerDebtPayment(payload: {
    payerId: string;
    receiverId: string;
    amount: number;
    note?: string;
    month?: string;
  }): Promise<DebtPayment> {
    const { data } = await api.post<{ payment: DebtPayment }>('/clearing/debts/payments', payload);
    await fetchDebt();
    return data.payment;
  }

  return {
    summary,
    history,
    debt,
    loading,
    error,
    fetchSummary,
    closeMonth,
    fetchHistory,
    fetchDebt,
    registerDebtPayment,
  };
});

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object' && 'response' in e) {
    const r = (e as { response?: { data?: { error?: string } } }).response;
    return r?.data?.error ?? 'Erro desconhecido';
  }
  return 'Erro desconhecido';
}
