import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@/services/api';
import type {
  Expense,
  CreateExpensePayload,
  InstallmentWithExpense,
  ExpenseAudit,
  RecurringExpense,
  CreateRecurringExpensePayload,
} from '@duofinance/shared-types';

export type ExpenseFilters = {
  search?: string;
  categoryId?: string;
  paidByUserId?: string;
  isShared?: boolean;
};

export const useExpenseStore = defineStore('expense', () => {
  const installments = ref<InstallmentWithExpense[]>([]);
  const recurring = ref<RecurringExpense[]>([]);
  const audits = ref<ExpenseAudit[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchByMonth(month: string, filters: ExpenseFilters = {}) {
    loading.value = true;
    error.value = null;
    try {
      const { data } = await api.get<{ installments: InstallmentWithExpense[] }>(`/expenses/${month}`, {
        params: {
          search: filters.search || undefined,
          categoryId: filters.categoryId || undefined,
          paidByUserId: filters.paidByUserId || undefined,
          isShared: typeof filters.isShared === 'boolean' ? String(filters.isShared) : undefined,
        },
      });
      installments.value = data.installments;
    } catch (e: unknown) {
      error.value = extractMessage(e);
    } finally {
      loading.value = false;
    }
  }

  async function createExpense(payload: CreateExpensePayload): Promise<Expense> {
    const { data } = await api.post<{ expense: Expense }>('/expenses', payload);
    return data.expense;
  }

  async function updateExpense(id: string, payload: Partial<CreateExpensePayload>): Promise<Expense> {
    const { data } = await api.put<{ expense: Expense }>(`/expenses/${id}`, payload);
    return data.expense;
  }

  async function undoExpense(id: string): Promise<Expense> {
    const { data } = await api.post<{ expense: Expense }>(`/expenses/${id}/undo`);
    return data.expense;
  }

  async function fetchAudits(id: string) {
    const { data } = await api.get<{ audits: ExpenseAudit[] }>(`/expenses/${id}/audits`);
    audits.value = data.audits;
  }

  async function deleteExpense(id: string) {
    await api.delete(`/expenses/${id}`);
  }

  async function fetchRecurring() {
    const { data } = await api.get<{ recurring: RecurringExpense[] }>('/recurring-expenses');
    recurring.value = data.recurring;
  }

  async function createRecurring(payload: CreateRecurringExpensePayload): Promise<RecurringExpense> {
    const { data } = await api.post<{ recurring: RecurringExpense }>('/recurring-expenses', payload);
    return data.recurring;
  }

  async function setRecurringActive(id: string, isActive: boolean): Promise<RecurringExpense> {
    const { data } = await api.patch<{ recurring: RecurringExpense }>(`/recurring-expenses/${id}`, { isActive });
    return data.recurring;
  }

  async function approveExpense(id: string): Promise<Expense> {
    const { data } = await api.post<{ expense: Expense }>(`/expenses/${id}/approve`);
    return data.expense;
  }

  async function rejectExpense(id: string): Promise<Expense> {
    const { data } = await api.post<{ expense: Expense }>(`/expenses/${id}/reject`);
    return data.expense;
  }

  return {
    installments,
    recurring,
    audits,
    loading,
    error,
    fetchByMonth,
    createExpense,
    updateExpense,
    undoExpense,
    approveExpense,
    rejectExpense,
    fetchAudits,
    deleteExpense,
    fetchRecurring,
    createRecurring,
    setRecurringActive,
  };
});

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object' && 'response' in e) {
    const r = (e as { response?: { data?: { error?: string } } }).response;
    return r?.data?.error ?? 'Erro desconhecido';
  }
  return 'Erro desconhecido';
}
