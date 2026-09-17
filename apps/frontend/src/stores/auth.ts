import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from '@/services/api';
import type { User, LoginPayload, RegisterUserPayload } from '@duofinance/shared-types';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('df_token'));
  const user = ref<User | null>(JSON.parse(localStorage.getItem('df_user') ?? 'null'));
  const coupleId = ref<string | null>(localStorage.getItem('df_couple_id'));

  const isAuthenticated = computed(() => !!token.value && !!user.value);

  function persist(t: string, u: User, cId: string | null) {
    token.value = t;
    user.value = u;
    coupleId.value = cId;
    localStorage.setItem('df_token', t);
    localStorage.setItem('df_user', JSON.stringify(u));
    if (cId) localStorage.setItem('df_couple_id', cId);
    else localStorage.removeItem('df_couple_id');
  }

  async function login(payload: LoginPayload) {
    const { data } = await api.post<{ token: string; user: User; coupleId: string | null }>(
      '/auth/login',
      payload,
    );
    persist(data.token, data.user, data.coupleId);
  }

  async function register(payload: RegisterUserPayload) {
    const { data } = await api.post<{ user: User }>('/auth/register', payload);
    return data.user;
  }

  function logout() {
    token.value = null;
    user.value = null;
    coupleId.value = null;
    localStorage.removeItem('df_token');
    localStorage.removeItem('df_user');
    localStorage.removeItem('df_couple_id');
  }

  function updateCoupleId(id: string) {
    coupleId.value = id;
    localStorage.setItem('df_couple_id', id);
  }

  return { token, user, coupleId, isAuthenticated, login, register, logout, updateCoupleId };
});
