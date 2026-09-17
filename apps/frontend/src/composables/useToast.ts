import { ref } from 'vue';

export interface ToastItem {
  id: string;
  type: 'success' | 'error' | 'info';
  message: string;
  duration?: number;
}

const toasts = ref<ToastItem[]>([]);

export function useToast() {
  function add(type: ToastItem['type'], message: string, duration = 3500) {
    const id = Math.random().toString(36).slice(2, 9);
    const item: ToastItem = { id, type, message, duration };
    toasts.value.push(item);

    if (duration > 0) {
      setTimeout(() => {
        remove(id);
      }, duration);
    }
  }

  function remove(id: string) {
    const idx = toasts.value.findIndex((t) => t.id === id);
    if (idx !== -1) {
      toasts.value.splice(idx, 1);
    }
  }

  return {
    toasts,
    success: (msg: string, duration?: number) => add('success', msg, duration),
    error: (msg: string, duration?: number) => add('error', msg, duration),
    info: (msg: string, duration?: number) => add('info', msg, duration),
    remove,
  };
}

