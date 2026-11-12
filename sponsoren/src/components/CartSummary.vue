<script setup>
import { computed } from 'vue'

const props = defineProps({
  selections: Array,
  subtotal: Number,
  discountPercent: Number,
  packagesTotal: Number,
  zunftsitzungTicketsCount: { type: Number, default: 0 },
  zunftsitzungTicketPrice: { type: Number, default: 38 },
  showZunftsitzungTickets: { type: Boolean, default: false },
  total: Number,
  comboInfo: Object,
  upsellHint: { type: String, default: '' },
  autoRenewal: Boolean,
})

const discountAmount = computed(() => 
  props.discountPercent ? (props.subtotal * props.discountPercent / 100) : 0
)

const VAT_RATE = 0.19
const vatAmount = computed(() => 
  props.total * VAT_RATE / (1 + VAT_RATE)
)
</script>

<template>
  <div>
    <div class="rounded-2xl bg-white border-2 border-slate-200 shadow-md p-5">
      <h3 class="text-base font-sans font-semibold text-slate-800 mb-3">
        Deine Auswahl
      </h3>

      <div v-if="selections.length === 0" class="py-6 text-center text-slate-500">
        <p class="text-sm">Deine Auswahl erscheint hier</p>
        <p class="text-xs mt-1">Klicke auf ein Paket</p>
        <p v-if="upsellHint" class="text-xs mt-2 text-slate-400">{{ upsellHint }}</p>
      </div>

      <template v-else>
        <ul class="space-y-2 mb-4 max-h-40 overflow-y-auto">
          <li
            v-for="s in selections"
            :key="s.pkg.id"
            class="text-sm py-2 border-b border-slate-100 last:border-0"
          >
            <div class="flex justify-between gap-2">
              <span class="text-slate-700 min-w-0 truncate">{{ s.categoryName }}: {{ s.pkg.name }}</span>
              <span class="font-semibold text-bnz-600 shrink-0 whitespace-nowrap">{{ s.pkg.priceNote ? s.pkg.priceNote + ' ' : '' }}{{ s.pkg.price }} €</span>
            </div>
            <p v-if="s.pkg.priceNote === 'ab'" class="text-xs text-slate-500 mt-0.5">
              Ab-Preis – genauer Betrag nach Absprache (Druckdetails etc.)
            </p>
          </li>
        </ul>

        <!-- Kombi-Bonus -->
        <div v-if="comboInfo" class="mb-4 p-3 rounded-lg bg-bnz-50 border border-bnz-200">
          <p class="text-bnz-700 text-sm font-medium">
            {{ comboInfo.minPackages }} Pakete = {{ comboInfo.discountPercent }}% Rabatt!
          </p>
          <p class="text-slate-600 text-xs mt-0.5">
            Zunftsitzung-Tickets reduziert auf {{ comboInfo.zunftsitzungTicketPrice }} €
          </p>
        </div>

        <!-- Automatische Verlängerung -->
        <div v-if="autoRenewal" class="mb-4 p-3 rounded-lg bg-bnz-50 border border-bnz-200">
          <p class="text-bnz-700 text-sm font-medium">
            Preisgarantie bei 3+ Jahren
          </p>
        </div>

        <div class="space-y-1.5 pt-4 border-t border-slate-200">
          <div v-if="discountPercent" class="flex justify-between gap-2 text-sm text-slate-600">
            <span>Zwischensumme</span>
            <span class="shrink-0 whitespace-nowrap">{{ subtotal.toFixed(2) }} €</span>
          </div>
          <div v-if="discountPercent" class="flex justify-between gap-2 text-sm text-bnz-600">
            <span>Rabatt ({{ discountPercent }}%)</span>
            <span class="shrink-0 whitespace-nowrap">-{{ discountAmount.toFixed(2) }} €</span>
          </div>
          <div v-if="showZunftsitzungTickets && zunftsitzungTicketsCount > 0" class="flex justify-between gap-2 text-sm text-slate-600">
            <span>Zunftsitzung-Tickets ({{ zunftsitzungTicketsCount }} × {{ zunftsitzungTicketPrice }} €)</span>
            <span class="shrink-0 whitespace-nowrap">{{ (zunftsitzungTicketsCount * zunftsitzungTicketPrice).toFixed(2) }} €</span>
          </div>
          <div class="flex justify-between gap-2 text-base font-bold pt-2 text-slate-800">
            <span>Gesamt (brutto)</span>
            <span class="text-bnz-600 shrink-0 whitespace-nowrap">{{ total.toFixed(2) }} €</span>
          </div>
          <div class="flex justify-between gap-2 text-xs text-slate-500 pt-1">
            <span>davon enthaltene USt. (19%)</span>
            <span class="shrink-0 whitespace-nowrap">{{ vatAmount.toFixed(2) }} €</span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
