<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  comboInfo: { type: Object, default: null },
  showZunftsitzungTickets: { type: Boolean, default: false },
  zunftsitzungTicketPrice: { type: Number, default: 38 },
  hasLiederheft: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  submitError: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'update:zunftsitzungTickets'])

const zunftsitzungTickets = defineModel('zunftsitzungTickets', { type: Number, default: 0 })

const form = ref({
  firmName: '',
  contactPerson: '',
  email: '',
  phone: '',
  street: '',
  postalCode: '',
  city: '',
  logoFile: null,
  notes: '',
  autoRenewal: false,
})



const isValid = computed(() => 
  form.value.firmName?.trim() && 
  form.value.contactPerson?.trim() && 
  form.value.email?.trim() &&
  form.value.street?.trim() &&
  form.value.postalCode?.trim() &&
  form.value.city?.trim()
)

function handleSubmit() {
  if (!isValid.value || props.submitting) return
  const payload = { ...form.value, zunftsitzungTickets: zunftsitzungTickets.value }
  emit('submit', payload)
}
function submitForm() {
  if (isValid.value) handleSubmit()
}
defineExpose({ submitForm, isValid })
</script>

<template>
  <div class="rounded-2xl bg-white border-2 border-slate-200 p-6 md:p-8 shadow-lg">
    <h3 class="text-xl font-sans font-semibold mb-2">
      Firmendaten
    </h3>
    <p class="text-slate-600 text-sm mb-6">
      So können wir uns bei dir melden und alles Weitere besprechen.
    </p>

    <p
      v-if="submitError"
      role="alert"
      class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm"
    >
      {{ submitError }}
    </p>
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Gruppe: Kontakt -->
      <div class="space-y-4">
        <h4 class="text-sm font-semibold text-slate-800 border-b border-slate-200 pb-2">Kontakt</h4>
        <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Firmenname *</label>
          <input
            v-model="form.firmName"
            type="text"
            required
            class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
            placeholder="z.B. Muster GmbH"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Ansprechpartner *</label>
          <input
            v-model="form.contactPerson"
            type="text"
            required
            class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
            placeholder="Name"
          />
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">E-Mail *</label>
          <input
            v-model="form.email"
            type="email"
            required
            class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
            placeholder="kontakt@firma.de"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Telefon</label>
          <input
            v-model="form.phone"
            type="tel"
            class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
            placeholder="+49 ..."
          />
        </div>
        </div>
      </div>

      <!-- Gruppe: Adresse -->
      <div class="space-y-4">
        <h4 class="text-sm font-semibold text-slate-800 border-b border-slate-200 pb-2">Adresse</h4>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Straße & Hausnummer *</label>
        <input
          v-model="form.street"
          type="text"
          required
          class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
          placeholder="Musterstraße 42"
        />
      </div>

      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">PLZ *</label>
          <input
            v-model="form.postalCode"
            type="text"
            required
            class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
            placeholder="50181"
          />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Ort *</label>
          <input
            v-model="form.city"
            type="text"
            required
            class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
            placeholder="Bedburg"
          />
        </div>
      </div>
      </div>

      <div
        v-if="showZunftsitzungTickets"
        class="flex flex-col gap-2 p-4 rounded-xl bg-bnz-50 border border-bnz-200"
      >
        <label class="text-sm font-medium text-slate-700">
          Wie viele Zunftsitzung-Tickets ({{ zunftsitzungTicketPrice }} €
          <template v-if="comboInfo?.zunftsitzungTicketPrice">, reduzierter Preis</template>
          ) möchtest du?
        </label>
        <input
          v-model.number="zunftsitzungTickets"
          type="number"
          min="0"
          class="w-24 px-4 py-2 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition"
          placeholder="0"
        />
      </div>

      <div class="flex flex-col gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200">
        <label class="text-sm font-medium text-slate-700">Logo (optional)</label>
        <input
          type="file"
          accept="image/*,.pdf"
          class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-bnz-100 file:text-bnz-700 file:font-medium hover:file:bg-bnz-200"
          @change="form.logoFile = $event.target.files?.[0] ?? null"
        />
        <p class="text-xs text-slate-500">
          <template v-if="hasLiederheft">Du kannst Logo oder Werbeanzeige auch im Nachgang mitschicken.</template>
          <template v-else>Du kannst das Logo auch im Nachgang mitschicken.</template>
        </p>
      </div>

      <div class="flex items-center gap-3 p-4 rounded-xl bg-bnz-50 border border-bnz-200">
        <input
          id="autoRenewal"
          v-model="form.autoRenewal"
          type="checkbox"
          class="w-5 h-5 rounded border-2 border-bnz-400 text-bnz-600 focus:ring-bnz-300"
        />
        <label for="autoRenewal" class="text-sm text-slate-700 cursor-pointer">
          <strong>Automatische Verlängerung</strong> – Mind. 3 Jahre gleiche Pakete = Garantie gleichbleibende Preise
        </label>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Anmerkungen</label>
        <textarea
          v-model="form.notes"
          rows="3"
          class="w-full px-4 py-3 rounded-xl border-2 border-slate-200 focus:border-bnz-400 focus:ring-2 focus:ring-bnz-100 outline-none transition resize-none"
          placeholder="Fragen, Wünsche, Sonderanfragen..."
        />
      </div>

      <button
        type="submit"
        :disabled="!isValid || submitting"
        class="w-full py-4 rounded-xl font-bold text-lg min-h-[52px] transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        :class="isValid && !submitting
          ? 'bg-bnz-600 text-white hover:bg-bnz-700 shadow-lg hover:shadow-xl active:scale-[0.98]' 
          : 'bg-slate-200 text-slate-500'"
      >
        {{ submitting ? 'Wird gesendet…' : 'Anfrage absenden' }}
      </button>
    </form>
  </div>
</template>
