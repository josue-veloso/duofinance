<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="section-title">Gastos</h2>
      <div class="flex items-center gap-2" aria-label="Acoes de gastos">
        <MonthPicker v-model="selectedMonth" />
        <Button
          v-if="expenseStore.recurring.length"
          variant="outline"
          class="inline-flex items-center gap-1.5"
          @click="showRecurringDialog = true"
        >
          <Repeat class="h-3.5 w-3.5" />
          <span>Fixas ({{ expenseStore.recurring.length }})</span>
        </Button>
        <Button aria-label="Adicionar novo gasto" @click="showForm = true">+ Novo</Button>
      </div>
    </div>

    <div class="card space-y-3">
      <p class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Buscar e filtrar</p>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input
          v-model="filters.search"
          class="input"
          placeholder="Buscar descrição"
          aria-label="Buscar por descrição"
        >
        <select v-model="filters.categoryId" class="input" aria-label="Filtrar por categoria">
          <option value="">Todas as categorias</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
        <select v-model="filters.paidByUserId" class="input" aria-label="Filtrar por responsável">
          <option value="">Todos os responsáveis</option>
          <option v-for="u in payers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <select v-model="isSharedFilter" class="input" aria-label="Filtrar por tipo de gasto">
          <option value="">Compartilhado e pessoal</option>
          <option value="shared">Somente compartilhado</option>
          <option value="personal">Somente pessoal</option>
        </select>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button variant="secondary" size="sm" @click="reload">Aplicar filtros</Button>
        <Button variant="ghost" size="sm" @click="clearFilters">Limpar</Button>
      </div>
    </div>

    <!-- Manage Recurring Expenses Dialog -->
    <Dialog :open="showRecurringDialog" @update:open="showRecurringDialog = $event">
      <DialogContent class="max-w-md">
        <div class="space-y-1">
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Contas e Assinaturas Fixas</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Despesas recorrentes que são geradas automaticamente todo mês.
          </p>
        </div>

        <div v-if="!expenseStore.recurring.length" class="py-6 text-center text-sm text-slate-500">
          Nenhuma despesa fixa cadastrada. Ao criar um novo gasto, marque a opção "Repetir mensalmente".
        </div>

        <ul v-else class="space-y-2 max-h-80 overflow-y-auto pt-2">
          <li
            v-for="r in expenseStore.recurring"
            :key="r.id"
            class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-800 p-3 bg-slate-50/50 dark:bg-slate-900/40"
          >
            <div>
              <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ r.description }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Dia {{ r.dayOfMonth }} · {{ r.isActive ? 'Ativa' : 'Pausada' }}
              </p>
            </div>
            <Button
              size="sm"
              :variant="r.isActive ? 'outline' : 'secondary'"
              class="text-xs"
              @click="toggleRecurring(r.id, !r.isActive)"
            >
              {{ r.isActive ? 'Pausar' : 'Ativar' }}
            </Button>
          </li>
        </ul>
      </DialogContent>
    </Dialog>

    <!-- Add expense dialog -->
    <Dialog :open="showForm" @update:open="showForm = $event">
      <DialogContent class="max-w-md p-0">
        <ExpenseForm @created="onCreated" @cancel="showForm = false" />
      </DialogContent>
    </Dialog>

    <!-- List -->
    <div v-if="expenseStore.loading" class="space-y-2">
      <div v-for="n in 5" :key="n" class="card h-16 animate-pulse bg-white/85" />
    </div>

    <p v-else-if="!expenseStore.installments.length" class="text-sm text-slate-500">
      Nenhum lançamento em {{ monthLabel }}.
    </p>

    <ul v-else class="space-y-2">
      <InstallmentRow
        v-for="inst in expenseStore.installments"
        :key="inst.id"
        :installment="inst"
        show-edit
        show-undo
        show-delete
        @edit="openEditDialog(inst.expense)"
        @undo="handleUndo(inst.expense.id)"
        @delete="openDeleteDialog(inst.expense.id)"
        @approve="handleApprove(inst.expense.id)"
        @reject="handleReject(inst.expense.id)"
      />
    </ul>

    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Remover gasto?</AlertDialogTitle>
          <AlertDialogDescription>
            Esta ação remove o gasto e todas as parcelas relacionadas. Essa operação não pode ser desfeita.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction variant="destructive" @click="confirmDelete">Remover</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <Dialog :open="editDialogOpen" @update:open="editDialogOpen = $event">
      <DialogContent class="max-w-md">
        <h3 class="text-lg font-semibold">Editar gasto</h3>
        <form class="space-y-3" @submit.prevent="confirmEdit">
          <input v-model="editForm.description" class="input" placeholder="Descrição" required>
          <div class="grid grid-cols-2 gap-3">
            <input v-model.number="editForm.totalAmount" class="input" type="number" min="0.01" step="0.01" required>
            <input v-model.number="editForm.installmentsCount" class="input" type="number" min="1" max="96" required>
          </div>
          <input v-model="editForm.purchaseDate" class="input" type="date" required>
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="editForm.isShared" type="checkbox" class="rounded border-slate-300">
            Compartilhado
          </label>
          <div class="flex justify-end gap-2">
            <Button type="button" variant="ghost" @click="editDialogOpen = false">Cancelar</Button>
            <Button type="submit">Salvar alterações</Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog :open="auditDialogOpen" @update:open="auditDialogOpen = $event">
      <DialogContent class="max-w-xl">
        <h3 class="text-lg font-semibold">Histórico do gasto</h3>
        <ul class="max-h-80 space-y-2 overflow-auto">
          <li v-for="audit in expenseStore.audits" :key="audit.id" class="rounded-lg border border-slate-200 p-3">
            <p class="text-sm font-semibold text-slate-900">{{ audit.action }} · {{ audit.actorUser?.name || 'Usuário' }}</p>
            <p class="text-xs text-slate-500">{{ new Date(audit.createdAt).toLocaleString('pt-BR') }}</p>
          </li>
          <li v-if="!expenseStore.audits.length" class="text-sm text-slate-500">Sem histórico.</li>
        </ul>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { Repeat } from 'lucide-vue-next';
import { useExpenseStore } from '@/stores/expenses';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import ExpenseForm from '@/components/ui/ExpenseForm.vue';
import InstallmentRow from '@/components/ui/InstallmentRow.vue';
import { currentMonthStr, monthLabel as getMonthLabel } from '@/utils/format';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
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
import type { Expense } from '@duofinance/shared-types';
import { useToast } from '@/composables/useToast';

const expenseStore = useExpenseStore();
const toast = useToast();
const selectedMonth = ref(currentMonthStr());

const showForm = ref(false);
const showRecurringDialog = ref(false);
const deleteDialogOpen = ref(false);
const editDialogOpen = ref(false);
const auditDialogOpen = ref(false);
const pendingDeleteExpenseId = ref<string | null>(null);
const pendingEditExpenseId = ref<string | null>(null);

const filters = ref({ search: '', categoryId: '', paidByUserId: '', isShared: undefined as boolean | undefined });
const isSharedFilter = ref('');

const editForm = ref({
  description: '',
  totalAmount: 0,
  installmentsCount: 1,
  isShared: true,
  purchaseDate: new Date().toISOString().slice(0, 10),
});

const monthLabel = computed(() => getMonthLabel(selectedMonth.value));
const categories = computed(() => {
  const map = new Map<string, { id: string; name: string }>();
  for (const inst of expenseStore.installments) {
    const c = inst.expense.category;
    if (c) map.set(c.id, { id: c.id, name: c.name });
  }
  return Array.from(map.values());
});
const payers = computed(() => {
  const map = new Map<string, { id: string; name: string }>();
  for (const inst of expenseStore.installments) {
    const p = inst.expense.paidByUser;
    if (p) map.set(p.id, { id: p.id, name: p.name });
  }
  return Array.from(map.values());
});

watch(selectedMonth, () => reload(), { immediate: false });

onMounted(async () => {
  await Promise.all([reload(), expenseStore.fetchRecurring()]);
});

async function reload() {
  filters.value.isShared = isSharedFilter.value === '' ? undefined : isSharedFilter.value === 'shared';
  await expenseStore.fetchByMonth(selectedMonth.value, filters.value);
}

async function clearFilters() {
  filters.value = { search: '', categoryId: '', paidByUserId: '', isShared: undefined };
  isSharedFilter.value = '';
  await reload();
}

async function onCreated() {
  showForm.value = false;
  await reload();
}

function openDeleteDialog(expenseId: string) {
  pendingDeleteExpenseId.value = expenseId;
  deleteDialogOpen.value = true;
}

async function confirmDelete() {
  if (!pendingDeleteExpenseId.value) return;
  await expenseStore.deleteExpense(pendingDeleteExpenseId.value);
  toast.info('Gasto removido');
  await reload();
  pendingDeleteExpenseId.value = null;
  deleteDialogOpen.value = false;
}

function openEditDialog(expense: Expense) {
  pendingEditExpenseId.value = expense.id;
  editForm.value = {
    description: expense.description,
    totalAmount: Number(expense.totalAmount),
    installmentsCount: expense.installmentsCount,
    isShared: expense.isShared,
    purchaseDate: String(expense.purchaseDate).slice(0, 10),
  };
  editDialogOpen.value = true;
}

async function confirmEdit() {
  if (!pendingEditExpenseId.value) return;
  await expenseStore.updateExpense(pendingEditExpenseId.value, editForm.value);
  toast.success('Gasto atualizado!');
  editDialogOpen.value = false;
  await reload();
}

async function handleApprove(expenseId: string) {
  try {
    await expenseStore.approveExpense(expenseId);
    toast.success('Gasto aprovado!');
    await reload();
  } catch {
    toast.error('Erro ao aprovar gasto.');
  }
}

async function handleReject(expenseId: string) {
  try {
    await expenseStore.rejectExpense(expenseId);
    toast.info('Gasto recusado.');
    await reload();
  } catch {
    toast.error('Erro ao recusar gasto.');
  }
}

async function handleUndo(expenseId: string) {
  await expenseStore.undoExpense(expenseId);
  await expenseStore.fetchAudits(expenseId);
  toast.info('Alteração desfeita com sucesso!');
  auditDialogOpen.value = true;
  await reload();
}

async function toggleRecurring(id: string, active: boolean) {
  await expenseStore.setRecurringActive(id, active);
  toast.info(active ? 'Recorrência ativada' : 'Recorrência pausada');
  await expenseStore.fetchRecurring();
}

</script>

