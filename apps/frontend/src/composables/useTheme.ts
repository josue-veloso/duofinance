import { ref } from 'vue';

const isDark = ref(false);
let initialized = false;

export function useTheme() {
  function initTheme() {
    if (initialized) return;
    initialized = true;

    const saved = localStorage.getItem('df_theme');
    if (saved) {
      isDark.value = saved === 'dark';
    } else {
      isDark.value = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    applyTheme();
  }

  function toggleTheme() {
    isDark.value = !isDark.value;
    localStorage.setItem('df_theme', isDark.value ? 'dark' : 'light');
    applyTheme();
  }

  function applyTheme() {
    if (isDark.value) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }

  return {
    isDark,
    initTheme,
    toggleTheme,
  };
}

