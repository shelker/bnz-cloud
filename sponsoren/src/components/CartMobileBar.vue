<script setup>
import { ref } from 'vue'

defineProps({
  selections: { type: Array, default: () => [] },
  selectionCount: Number,
  total: Number,
  hasSelections: Boolean,
  step: String,
  formValid: Boolean,
})
defineEmits(['click-cta', 'submit'])

const expanded = ref(false)
</script>

<template>
  <div
    class="fixed bottom-0 left-0 right-0 z-50 lg:hidden bg-white/95 backdrop-blur-sm border-t border-slate-200 shadow-[0_-2px_12px_rgba(0,0,0,0.06)]"
  >
    <!-- Ausklappbare Auswahl -->
    <div
      v-if="hasSelections && expanded"
      class="px-4 pt-3 pb-2 max-h-32 overflow-y-auto border-b border-slate-100"
    >
      <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Deine Auswahl</p>
      <ul class="space-y-1 text-sm text-slate-700">
        <li v-for="s in selections" :key="s.pkg.id">
          {{ s.categoryName }}: {{ s.pkg.name }} · <span class="whitespace-nowrap">{{ s.pkg.price }} €</span>
        </li>
      </ul>
    </div>

    <div
      class="safe-area-pb flex items-center justify-between gap-3 px-4 py-3 max-w-7xl mx-auto"
      @click="hasSelections && (expanded = !expanded)"
    >
      <div class="flex items-center gap-3 min-w-0 flex-1">
        <span class="text-sm text-slate-600">
          {{ hasSelections ? `${selectionCount} Paket${selectionCount === 1 ? '' : 'e'}` : 'Warenkorb' }}
        </span>
        <span class="font-bold" :class="hasSelections ? 'text-bnz-600' : 'text-slate-400'">
          <span class="whitespace-nowrap">{{ hasSelections ? `${total.toFixed(0)} €` : '—' }}</span>
        </span>
        <span v-if="hasSelections" class="text-slate-400 ml-auto">
          <svg
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-180': expanded }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
          </svg>
        </span>
      </div>
      <button
        v-if="step === 'config'"
        type="button"
        @click.stop="$emit('click-cta')"
        class="flex items-center gap-2 shrink-0 px-4 py-2.5 rounded-lg font-semibold text-sm transition-all active:scale-95"
        :class="hasSelections
          ? 'bg-bnz-600 text-white hover:bg-bnz-700'
          : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
        :disabled="!hasSelections"
      >
        Weiter
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
      <button
        v-else-if="step === 'form'"
        type="button"
        @click.stop="$emit('submit')"
        class="flex items-center gap-2 shrink-0 px-4 py-2.5 rounded-lg font-semibold text-sm transition-all active:scale-95"
        :class="formValid
          ? 'bg-bnz-600 text-white hover:bg-bnz-700'
          : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
        :disabled="!formValid"
      >
        Absenden
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </div>
</template>
