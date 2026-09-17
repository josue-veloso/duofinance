import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/components/views/LoginView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/components/views/RegisterView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/',
      component: () => import('@/components/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: '/dashboard',
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/components/views/DashboardView.vue'),
        },
        {
          path: 'expenses',
          name: 'expenses',
          component: () => import('@/components/views/ExpensesView.vue'),
        },
        {
          path: 'verdict',
          name: 'verdict',
          component: () => import('@/components/views/VerdictView.vue'),
        },
        {
          path: 'couple',
          name: 'couple',
          component: () => import('@/components/views/CoupleView.vue'),
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' };
  }
  if (!to.meta.requiresAuth && auth.isAuthenticated && (to.name === 'login' || to.name === 'register')) {
    return { name: 'dashboard' };
  }
});
