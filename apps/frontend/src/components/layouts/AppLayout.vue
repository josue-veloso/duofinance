<template>
  <div class="app-shell flex flex-col">
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/75 backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-900/80 transition-colors">
      <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-3.5">
        <RouterLink to="/dashboard" class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-violet-500 shadow-md shadow-blue-500/20">
            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 text-white" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="8.5" cy="12" r="4.5" />
              <circle cx="15.5" cy="12" r="4.5" />
              <path d="M12 9.5v5" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-extrabold leading-none tracking-tight text-slate-900 dark:text-white">DuoFinance</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Financeiro de casal</p>
          </div>
        </RouterLink>

        <nav class="hidden items-center gap-1 sm:flex" aria-label="Navegação principal">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"
            active-class="bg-slate-900 text-white hover:bg-slate-900 hover:text-white dark:bg-white dark:text-slate-900 dark:hover:bg-white dark:hover:text-slate-900"
          >
            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
              <path :d="item.path" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>{{ item.label }}</span>
          </RouterLink>
        </nav>

        <div class="flex items-center gap-2">
          <!-- Dark Mode Toggle Button -->
          <button
            aria-label="Alternar tema claro/escuro"
            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200/80 bg-slate-50 text-slate-600 transition hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-300 dark:hover:bg-slate-800"
            @click="theme.toggleTheme"
          >
            <!-- Sun icon when dark -->
            <svg v-if="theme.isDark.value" class="h-4 w-4 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="5" />
              <line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" />
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
              <line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" />
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
            </svg>
            <!-- Moon icon when light -->
            <svg v-else class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
          </button>

          <!-- User chip with avatar -->
          <div class="hidden items-center gap-2 rounded-full border border-slate-200/80 bg-slate-100/70 py-1 pl-1 pr-3 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-200 sm:flex">
            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-[11px] font-bold text-white shadow-sm">
              {{ auth.user?.name?.slice(0, 1).toUpperCase() }}
            </div>
            <span>{{ auth.user?.name }}</span>
          </div>

          <button aria-label="Encerrar sessão" @click="handleLogout" class="btn-ghost text-slate-500 dark:text-slate-400">Sair</button>
        </div>
      </div>
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-7">
      <RouterView />
    </main>

    <nav class="sticky bottom-0 border-t border-slate-200/80 bg-white/90 backdrop-blur-xl dark:border-slate-800/80 dark:bg-slate-900/90 sm:hidden transition-colors" aria-label="Navegação mobile">
      <div class="grid grid-cols-4">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex flex-col items-center gap-1 py-2 text-[11px] font-semibold text-slate-400 dark:text-slate-500 transition"
          active-class="text-blue-600 dark:text-blue-400"
        >
          <svg viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" aria-hidden="true">
            <path :d="item.path" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>{{ item.label }}</span>
        </RouterLink>
      </div>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/composables/useTheme';

const auth = useAuthStore();
const router = useRouter();
const theme = useTheme();

onMounted(() => {
  theme.initTheme();
});

const navItems = [
  {
    to: '/dashboard',
    label: 'Painel',
    path: 'M3 12.5L12 4l9 8.5M5 10.5V20h14v-9.5',
  },
  {
    to: '/expenses',
    label: 'Gastos',
    path: 'M4 7h16M7 4v6m10-6v6M5 10h14v10H5z',
  },
  {
    to: '/verdict',
    label: 'Acerto',
    path: 'M12 4v16M5 8h14M7 8l5 5 5-5',
  },
  {
    to: '/couple',
    label: 'Casal',
    path: 'M8.5 12a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 8.5 12zm7 0a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 15.5 12zM3.5 20a5 5 0 0 1 10 0M10.5 20a5 5 0 0 1 10 0',
  },
];

function handleLogout() {
  auth.logout();
  router.push('/login');
}
</script>
