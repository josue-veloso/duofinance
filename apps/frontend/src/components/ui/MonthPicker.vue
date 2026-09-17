<template>
  <div class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white p-1 shadow-sm">
    <button class="btn-ghost h-8 w-8 rounded-full px-0 py-0 text-base" @click="shift(-1)">‹</button>
    <span class="w-28 text-center text-sm font-semibold text-slate-700">{{ label }}</span>
    <button class="btn-ghost h-8 w-8 rounded-full px-0 py-0 text-base" @click="shift(1)">›</button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { monthLabel } from '@/utils/format';

const props = defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const label = computed(() => monthLabel(props.modelValue));

function shift(delta: number) {
  const [y, m] = props.modelValue.split('-').map(Number);
  const d = new Date(Date.UTC(y, m - 1 + delta, 1));
  const newMonth = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
  emit('update:modelValue', newMonth);
}
</script>
