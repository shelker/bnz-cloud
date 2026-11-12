<script setup>
import { computed } from 'vue'
import { getPrintDeadlineInfo } from '../data/packages.js'

const props = defineProps({
  category: Object,
  selectedPackageId: [String, null],
  printDeadline: { type: String, default: null },
})

const emit = defineEmits(['select'])

const minPrice = computed(() =>
  Math.min(...props.category.packages.map(p => p.price))
)

const selectedPackage = computed(() =>
  props.category.packages.find(p => p.id === props.selectedPackageId)
)

const deadlineInfo = computed(() => getPrintDeadlineInfo(props.printDeadline))
const deadlinePassed = computed(() => deadlineInfo.value?.passed === true)

function handleSelect(pkg) {
  if (deadlinePassed.value) return
  emit('select', pkg)
}
</script>

<template>
  <article
    class="rounded-2xl bg-white border-2 border-slate-200 shadow-md overflow-hidden transition-all duration-200"
    :class="deadlinePassed
      ? 'opacity-70'
      : selectedPackageId
        ? 'border-bnz-500 shadow-lg ring-2 ring-bnz-200'
        : 'hover:shadow-lg hover:border-bnz-300'"
  >
    <!-- Header mit Dummy-Bild + BNZ-Overlay -->
    <div
      class="h-28 flex items-end justify-between p-4 text-white bg-cover bg-center bg-no-repeat bg-slate-300"
      :style="category.image
        ? { backgroundImage: `linear-gradient(to bottom right, rgba(196,30,58,0.88), rgba(155,22,48,0.88)), url(${category.image})` }
        : { background: 'linear-gradient(to bottom right, #C41E3A, #9B1630)' }"
    >
      <h3 class="text-xl font-sans font-bold drop-shadow">
        {{ category.name }}
      </h3>
      <span class="shrink-0 px-2.5 py-1 rounded-lg bg-white/25 text-sm font-bold whitespace-nowrap">
        ab {{ minPrice }} €
      </span>
    </div>

    <div class="p-4">
      <p class="text-slate-600 text-sm mb-4 leading-relaxed">
        {{ category.description }}
      </p>

      <!-- Kategorie-weite Benefits (z. B. Summerjeck) -->
      <div v-if="category.benefitsIncluded?.length" class="mb-4">
        <p class="text-sm font-semibold text-bnz-700 uppercase tracking-wide mb-2">In allen Paketen enthalten</p>
        <ul class="space-y-2 mb-3">
          <li
            v-for="benefit in category.benefitsIncluded"
            :key="benefit"
            class="text-sm text-slate-600 flex items-start gap-2"
          >
            <span class="text-bnz-500 shrink-0">–</span>
            {{ benefit }}
          </li>
        </ul>
      </div>

      <!-- Paket-Buttons – groß, klar, gut klickbar -->
      <div class="space-y-2">
        <button
          v-for="pkg in category.packages"
          :key="pkg.id"
          type="button"
          :disabled="deadlinePassed"
          @click="handleSelect(pkg)"
          class="w-full flex items-start gap-3 px-4 py-3 rounded-xl border-2 text-left transition-all duration-200"
          :class="deadlinePassed
            ? 'border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed'
            : selectedPackageId === pkg.id
              ? 'border-bnz-500 bg-bnz-50 text-bnz-900 active:scale-[0.98]'
              : 'border-slate-200 bg-slate-50 hover:border-bnz-300 hover:bg-bnz-50/50 active:scale-[0.98]'"
        >
          <span
            v-if="selectedPackageId === pkg.id"
            class="shrink-0 w-6 h-6 rounded-full bg-bnz-500 flex items-center justify-center text-white mt-0.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </span>
          <span v-else class="shrink-0 w-6" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-base">{{ pkg.name }}</span>
              <span
                class="font-bold shrink-0 text-sm whitespace-nowrap"
                :class="deadlinePassed ? 'text-slate-400' : 'text-bnz-600'"
              >
                {{ pkg.priceNote ? pkg.priceNote + ' ' : '' }}{{ pkg.price }} €
              </span>
            </div>
            <p
              v-if="pkg.description"
              class="text-sm mt-0.5"
              :class="deadlinePassed ? 'text-slate-400' : 'text-slate-600'"
            >
              {{ pkg.description }}
            </p>
          </div>
        </button>
      </div>

      <!-- Ausgewähltes Paket – konkrete Details (nur wenn Tickets oder Extras) -->
      <div
        v-if="selectedPackage && (selectedPackage.tickets || selectedPackage.extras?.length)"
        class="mt-3 pt-3 border-t border-slate-200 space-y-2"
      >
        <div v-if="selectedPackage?.tickets" class="text-sm font-medium text-bnz-700">
          {{ selectedPackage.tickets }} Festival-Tickets inklusive
        </div>
        <ul v-if="selectedPackage?.extras?.length" class="text-sm text-slate-600 space-y-1">
          <li v-for="extra in selectedPackage.extras" :key="extra" class="flex items-start gap-2">
            <span class="text-bnz-500 shrink-0">✓</span> {{ extra }}
          </li>
        </ul>
      </div>
    </div>
  </article>
</template>
