<template>
  <div class="fixed bottom-5 right-5 z-50 flex max-w-sm flex-col gap-2 pointer-events-none sm:bottom-6 sm:right-6">
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-4 opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-center gap-3 rounded-2xl border px-4 py-3 shadow-xl backdrop-blur-xl transition"
        :class="getToastClasses(toast.type)"
      >
        <div class="shrink-0">
          <svg v-if="toast.type === 'success'" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
          </svg>
          <svg v-else-if="toast.type === 'error'" class="h-5 w-5 text-rose-600 dark:text-rose-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
          </svg>
          <svg v-else class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
          </svg>
        </div>

        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 flex-1">{{ toast.message }}</p>

        <button
          class="shrink-0 rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition"
          @click="remove(toast.id)"
          aria-label="Fechar notificação"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();

function getToastClasses(type: string) {
  if (type === 'success') {
    return 'bg-white/95 border-emerald-200 dark:bg-slate-900/95 dark:border-emerald-900/50';
  }
  if (type === 'error') {
    return 'bg-white/95 border-rose-200 dark:bg-slate-900/95 dark:border-rose-900/50';
  }
  return 'bg-white/95 border-blue-200 dark:bg-slate-900/95 dark:border-blue-900/50';
}
</script>

