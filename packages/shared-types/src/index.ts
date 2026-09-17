// ─── Domain entities ─────────────────────────────────────────────────────────

export interface User {
  id: string;
  name: string;
  email: string;
  createdAt: string;
}

export interface Couple {
  id: string;
  user1Id: string;
  user2Id: string;
  /** Fraction of shared costs owed by user1 (e.g. 0.5 = 50 %) */
  user1Quota: number;
  /** Fraction of shared costs owed by user2 (e.g. 0.5 = 50 %) */
  user2Quota: number;
  user1?: User;
  user2?: User;
  createdAt: string;
}

export interface Category {
  id: string;
  coupleId: string;
  name: string;
  color: string;
  icon: string;
}

export interface Expense {
  id: string;
  coupleId: string;
  paidByUserId: string;
  description: string;
  totalAmount: number;
  installmentsCount: number;
  isShared: boolean;
  splitMode?: 'DEFAULT' | 'EQUAL_50_50' | 'CUSTOM' | 'FULL_USER1' | 'FULL_USER2';
  customUser1Quota?: number | null;
  status?: 'CONFIRMED' | 'PENDING_APPROVAL' | 'REJECTED';
  approvedByUserId?: string | null;
  approvedAt?: string | null;
  recurringExpenseId?: string | null;
  categoryId: string | null;
  purchaseDate: string;
  createdAt: string;
  paidByUser?: User;
  approvedByUser?: User;
  category?: Category;
  installments?: Installment[];
}

export interface Installment {
  id: string;
  expenseId: string;
  installmentNumber: number;
  /** Amount for this specific installment (may differ from totalAmount / count due to rounding) */
  amount: number;
  /** ISO month string: YYYY-MM */
  dueMonth: string;
  expense?: Expense;
}

export interface MonthlyClose {
  id: string;
  coupleId: string;
  /** ISO month string: YYYY-MM */
  month: string;
  totalShared: number;
  user1Paid: number;
  user2Paid: number;
  /**
   * Positive → user1 is owed by user2.
   * Negative → user2 is owed by user1.
   * Formula: user1Paid - (totalShared × user1Quota)
   */
  verdictAmount: number;
  verdictPayerId: string | null;
  verdictReceiverId: string | null;
  closedAt: string | null;
}

// ─── API payload types ────────────────────────────────────────────────────────

export interface RegisterUserPayload {
  name: string;
  email: string;
  password: string;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface CreateCouplePayload {
  partnerEmail: string;
  user1Quota: number;
}

export interface CreateExpensePayload {
  description: string;
  totalAmount: number;
  installmentsCount: number;
  isShared: boolean;
  splitMode?: 'DEFAULT' | 'EQUAL_50_50' | 'CUSTOM' | 'FULL_USER1' | 'FULL_USER2';
  customUser1Quota?: number | null;
  status?: 'CONFIRMED' | 'PENDING_APPROVAL';
  categoryId?: string;
  purchaseDate: string;
  isRecurring?: boolean;
  dayOfMonth?: number;
  startMonth?: string;
  endMonth?: string | null;
}

export interface CreateCategoryPayload {
  name: string;
  color: string;
  icon: string;
}

// ─── Dashboard / Clearing types ───────────────────────────────────────────────

export interface MonthSummary {
  month: string;
  couple?: Couple;
  totalShared: number;
  user1Paid: number;
  user2Paid: number;
  user1Balance: number;
  user2Balance: number;
  /** Positive → user1 receives; negative → user2 receives */
  verdictAmount: number;
  verdictPayerId: string | null;
  verdictReceiverId: string | null;
  isClosed: boolean;
  previousTotalShared?: number;
  deltaVsPreviousMonth?: number;
  sharedCount?: number;
  pendingApprovalCount?: number;
  topCategories?: Array<{
    categoryId: string | null;
    name: string;
    total: number;
    percent: number;
  }>;
  installments: InstallmentWithExpense[];
}

export interface InstallmentWithExpense extends Installment {
  expense: Expense & { paidByUser: User; category: Category | null };
}

export interface ExpenseAudit {
  id: string;
  expenseId: string;
  coupleId: string;
  actorUserId: string;
  action: 'CREATED' | 'UPDATED' | 'APPROVED' | 'REJECTED' | 'DELETED' | 'RESTORED' | string;
  before?: Record<string, unknown> | null;
  after?: Record<string, unknown> | null;
  createdAt: string;
  actorUser?: User;
}

export interface RecurringExpense {
  id: string;
  coupleId: string;
  paidByUserId: string;
  description: string;
  totalAmount: number;
  installmentsCount: number;
  isShared: boolean;
  categoryId: string | null;
  dayOfMonth: number;
  startMonth: string;
  endMonth: string | null;
  frequency: string;
  isActive: boolean;
  lastGeneratedMonth: string | null;
  createdAt: string;
  updatedAt: string;
  paidByUser?: User;
  category?: Category | null;
}

export interface CreateRecurringExpensePayload {
  description: string;
  totalAmount: number;
  installmentsCount: number;
  isShared: boolean;
  categoryId?: string;
  dayOfMonth: number;
  startMonth: string;
  endMonth?: string;
}

export interface DebtPayment {
  id: string;
  coupleId: string;
  month: string | null;
  payerId: string;
  receiverId: string;
  amount: number;
  note?: string | null;
  createdAt: string;
  payer?: User;
  receiver?: User;
}

export interface DebtStatus {
  outstanding: Array<{
    payerId: string;
    receiverId: string;
    amount: number;
  }>;
  payments: DebtPayment[];
}

// ─── Utility ──────────────────────────────────────────────────────────────────

/** Compute the net balance for a user in a given month.
 *  Positive → user is owed money; negative → user owes money.
 */
export function computeBalance(
  userPaid: number,
  totalShared: number,
  userQuota: number,
): number {
  return userPaid - totalShared * userQuota;
}
