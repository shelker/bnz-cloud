<script setup>
import { ref, computed } from 'vue'
import { packageCategories, comboDiscounts, autoRenewal, printDeadlines, isCategoryBookable } from './data/packages.js'
import PackageCard from './components/PackageCard.vue'
import CartSummary from './components/CartSummary.vue'
import CartMobileBar from './components/CartMobileBar.vue'
import CompanyForm from './components/CompanyForm.vue'

const companyFormRef = ref(null)
const submitting = ref(false)
const submitError = ref('')
const formValid = computed(() => 
  step.value === 'form' && companyFormRef.value?.isValid?.value && !submitting.value
)

const step = ref('config') // config | form | success
const selections = ref({}) // { categoryId: package }
const submittedData = ref(null)

const mailEndpoint = import.meta.env.VITE_API_BASE
  ? `${import.meta.env.VITE_API_BASE.replace(/\/$/, '')}/api/send-sponsor.php`
  : `${import.meta.env.BASE_URL}api/send-sponsor.php`
const zunftsitzungTicketsCount = ref(0)

// Auswahl setzen/ändern (pro Kategorie max. 1 Paket)
function selectPackage(categoryId, pkg) {
  if (!isCategoryBookable(categoryId)) return
  if (selections.value[categoryId]?.id === pkg.id) {
    delete selections.value[categoryId]
  } else {
    selections.value[categoryId] = { ...pkg }
  }
  selections.value = { ...selections.value }
}

function getSelectedPackageId(categoryId) {
  return selections.value[categoryId]?.id ?? null
}

// Berechnung
const selectionList = computed(() => {
  const list = []
  for (const cat of packageCategories) {
    const pkg = selections.value[cat.id]
    if (pkg) {
      list.push({ category: cat, categoryName: cat.name, pkg })
    }
  }
  return list
})

const subtotal = computed(() => 
  selectionList.value.reduce((sum, s) => sum + s.pkg.price, 0)
)

// Bestes Kombi-Rabatt (höchster Treffer)
const activeCombo = computed(() => {
  const n = selectionList.value.length
  return comboDiscounts
    .filter(c => n >= c.minPackages)
    .sort((a, b) => b.minPackages - a.minPackages)[0] ?? null
})

const discountPercent = computed(() => activeCombo.value?.discountPercent ?? 0)
const packagesTotal = computed(() =>
  subtotal.value * (1 - discountPercent.value / 100)
)
const zunftsitzungTicketsAmount = computed(() =>
  (zunftsitzungTicketsCount.value || 0) * zunftsitzungTicketPrice.value
)
const total = computed(() =>
  packagesTotal.value + zunftsitzungTicketsAmount.value
)

const hasZunftsitzungInSelection = computed(() =>
  selectionList.value.some(s => s.category.id === 'zunftsitzung')
)
const hasLiederheftInSelection = computed(() =>
  selectionList.value.some(s => s.category.id === 'liederheft')
)

const zunftsitzungCategory = packageCategories.find(c => c.id === 'zunftsitzung')
const zunftsitzungTicketPrice = computed(() =>
  activeCombo.value?.zunftsitzungTicketPrice ?? zunftsitzungCategory?.ticketPrice ?? 38
)

const bookableCategoryCount = computed(() =>
  packageCategories.filter((cat) => isCategoryBookable(cat.id)).length
)

const bestReachableCombo = computed(() =>
  comboDiscounts
    .filter((c) => bookableCategoryCount.value >= c.minPackages)
    .sort((a, b) => b.minPackages - a.minPackages)[0] ?? null
)

const nextCombo = computed(() => {
  const n = selectionList.value.length
  return comboDiscounts
    .filter((c) => c.minPackages > n && c.minPackages <= bookableCategoryCount.value)
    .sort((a, b) => a.minPackages - b.minPackages)[0] ?? null
})

const selectionHint = computed(() => {
  const next = nextCombo.value
  const n = selectionList.value.length
  if (next && next.minPackages === n + 1) {
    return `1 weiteres Paket = ${next.discountPercent}% Rabatt + Zunftsitzung-Tickets reduziert auf ${next.zunftsitzungTicketPrice} €`
  }
  const best = bestReachableCombo.value
  if (!best || n > 0) return ''
  return `${best.minPackages}+ Pakete = bis zu ${best.discountPercent}% Rabatt + vergünstigte Zunftsitzung-Tickets`
})

// Auto-Renewal Option (wird im Formular abgefragt)
const showAutoRenewalInfo = computed(() => 
  submittedData.value?.autoRenewal ?? false
)

function goToForm() {
  if (selectionList.value.length > 0) {
    step.value = 'form'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function goBack() {
  step.value = 'config'
}

function handleMobileBarSubmit() {
  companyFormRef.value?.submitForm()
}
async function handleSubmit(data) {
  submitError.value = ''
  const logoFile = data.logoFile instanceof File ? data.logoFile : null
  const { logoFile: _drop, ...formFields } = data
  const payload = {
    ...formFields,
    selections: selectionList.value,
    subtotal: subtotal.value,
    discountPercent: discountPercent.value,
    total: total.value,
    comboInfo: activeCombo.value,
  }
  submitting.value = true
  try {
    let res
    if (logoFile) {
      const fd = new FormData()
      fd.append('data', JSON.stringify(payload))
      fd.append('logo', logoFile, logoFile.name)
      res = await fetch(mailEndpoint, { method: 'POST', body: fd })
    } else {
      res = await fetch(mailEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
    }
    if (!res.ok) {
      let detail = 'send_failed'
      let body = null
      try {
        body = await res.json()
        if (body?.error) detail = body.error
      } catch {
        /* ignore */
      }
      if (body?.detail && typeof body.detail === 'string') {
        submitError.value = body.detail
      } else {
        const errMap = {
          invalid_file_type: 'Dateityp nicht erlaubt (nur Bilder oder PDF).',
          file_too_large: 'Die Datei ist zu groß (max. 10 MB).',
          upload_failed: 'Der Datei-Upload ist fehlgeschlagen.',
          upload_invalid: 'Die Datei konnte nicht verarbeitet werden.',
          invalid_json: 'Ungültige Daten. Bitte Seite neu laden und erneut versuchen.',
          invalid_payload: 'Bitte Pflichtfelder prüfen.',
          mail_not_configured: 'Der Mail-Server ist nicht konfiguriert.',
        }
        const hint = errMap[detail]
        if (hint) submitError.value = hint
      }
      throw new Error(detail)
    }
    submittedData.value = { ...payload, logoFileName: logoFile?.name ?? null }
    step.value = 'success'
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    console.error('Sponsor-Anfrage:', e)
    if (!submitError.value) {
      submitError.value =
        'Der Versand ist fehlgeschlagen. Bitte versuche es später erneut oder kontaktiere uns telefonisch.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Header – BNZ Ruut-Wieß -->
    <header class="header-bnz bg-bnz-600 text-white sticky top-0 z-40 shadow-lg">
      <div class="max-w-7xl mx-auto px-4 py-4 md:py-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="flex items-start gap-4">
            <img
              src="/apple-touch-icon.png"
              alt="BNZ Logo"
              class="h-12 md:h-14 shrink-0"
            />
            <div>
            <h1 class="text-2xl md:text-3xl font-sans font-bold">
              Werde BNZ Sponsor
            </h1>
            <p v-if="step === 'form'" class="text-white/90 text-sm mt-1">
              Fast geschafft – nur noch deine Kontaktdaten.
            </p>
            <p v-if="step !== 'success'" class="text-white/70 text-xs mt-2">
              {{ step === 'config' ? 'Schritt 1 von 2' : 'Schritt 2 von 2' }}
            </p>
            <p v-if="step === 'config' && selectionHint" class="text-white/60 text-xs mt-1">
              Tipp: {{ selectionHint }}
            </p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span
              v-if="selectionList.length > 0"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/20 text-white text-sm font-medium"
            >
              <span class="w-2 h-2 rounded-full bg-white animate-pulse" />
              {{ selectionList.length }} Paket(e) ausgewählt
            </span>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 md:py-12 pb-24 lg:pb-12">
      <!-- Step: Konfigurator -->
      <div v-if="step === 'config'" class="animate-fade-in">
        <div class="grid lg:grid-cols-3 gap-8">
          <!-- Pakete -->
          <div class="lg:col-span-2 space-y-8">
            <section>
              <h2 class="text-xl font-sans font-semibold text-slate-800 mb-6">
                Wähle deine Sponsoring-Pakete
              </h2>
              <div class="grid md:grid-cols-2 gap-6">
                <PackageCard
                  v-for="cat in packageCategories"
                  :key="cat.id"
                  :category="cat"
                  :selected-package-id="getSelectedPackageId(cat.id)"
                  :print-deadline="printDeadlines[cat.id]"
                  @select="(pkg) => selectPackage(cat.id, pkg)"
                />
              </div>
            </section>

            <!-- Preisgarantie – kompakt -->
            <p class="text-slate-600 text-sm">
              {{ autoRenewal.benefit }}
            </p>
          </div>

          <!-- Warenkorb – sticky auf Desktop, damit CartSummary + Button sichtbar bleiben -->
          <div class="lg:col-span-1 lg:sticky lg:top-24 lg:self-start">
            <CartSummary
              :selections="selectionList"
              :subtotal="subtotal"
              :discount-percent="discountPercent"
              :packages-total="packagesTotal"
              :zunftsitzung-tickets-count="zunftsitzungTicketsCount"
              :zunftsitzung-ticket-price="zunftsitzungTicketPrice"
              :show-zunftsitzung-tickets="hasZunftsitzungInSelection || !!activeCombo"
              :total="total"
              :combo-info="activeCombo"
              :upsell-hint="selectionHint"
              :auto-renewal="false"
            />
            <button
              v-if="selectionList.length > 0"
              type="button"
              @click="goToForm"
              class="mt-4 w-full py-4 rounded-xl font-bold text-white bg-bnz-600 hover:bg-bnz-700 shadow-lg hover:shadow-xl transition-all duration-200 min-h-[52px] active:scale-[0.98]"
            >
              Weiter →
            </button>
          </div>
        </div>
      </div>

      <!-- Step: Firmendaten -->
      <div v-else-if="step === 'form'" class="animate-fade-in">
        <button
          type="button"
          @click="goBack"
          class="flex items-center gap-2 text-slate-700 hover:text-bnz-600 mb-4 transition py-3 px-4 rounded-xl border-2 border-slate-200 hover:border-bnz-300 font-medium -ml-1"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Zurück zur Auswahl
        </button>
        <div class="mb-6 p-4 rounded-xl bg-white border border-bnz-200 shadow-sm">
          <p class="text-slate-700 font-medium">
            Du hast {{ selectionList.length }} Paket(e) für {{ total.toFixed(2) }} € gewählt
          </p>
          <ul class="text-sm text-slate-600 mt-1 space-y-0.5">
            <li v-for="s in selectionList" :key="s.pkg.id">{{ s.categoryName }}: {{ s.pkg.name }}</li>
          </ul>
        </div>
        <div class="flex gap-8 flex-col xl:flex-row max-w-6xl">
          <div class="flex-1 min-w-0">
            <CompanyForm
              ref="companyFormRef"
              v-model:zunftsitzung-tickets="zunftsitzungTicketsCount"
              :combo-info="activeCombo"
              :show-zunftsitzung-tickets="hasZunftsitzungInSelection || !!activeCombo"
              :zunftsitzung-ticket-price="zunftsitzungTicketPrice"
              :has-liederheft="hasLiederheftInSelection"
              :submitting="submitting"
              :submit-error="submitError"
              @submit="handleSubmit"
            />
          </div>
          <div class="xl:w-80 shrink-0">
            <CartSummary
              :selections="selectionList"
              :subtotal="subtotal"
              :discount-percent="discountPercent"
              :packages-total="packagesTotal"
              :zunftsitzung-tickets-count="zunftsitzungTicketsCount"
              :zunftsitzung-ticket-price="zunftsitzungTicketPrice"
              :show-zunftsitzung-tickets="hasZunftsitzungInSelection || !!activeCombo"
              :total="total"
              :combo-info="activeCombo"
              :auto-renewal="false"
            />
          </div>
        </div>
      </div>

      <!-- Step: Erfolg -->
      <div v-else-if="step === 'success'" class="animate-fade-in max-w-xl mx-auto text-center py-16">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-bnz-100 text-bnz-600 mb-6">
          <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="text-2xl font-sans font-bold text-slate-800 mb-2">
          Anfrage erfolgreich gesendet
        </h2>
        <p class="text-slate-600 mb-6">
          Wir melden uns innerhalb von 2–3 Werktagen bei dir unter {{ submittedData?.email }}.
        </p>
        <div class="text-left bg-white border border-bnz-200 rounded-xl p-5 shadow-sm mb-8">
          <h3 class="font-semibold text-slate-800 mb-3">Was passiert als Nächstes?</h3>
          <ul class="space-y-2 text-slate-600 text-sm">
            <li class="flex items-start gap-2">
              <span class="text-bnz-500 font-bold">1.</span>
              Wir bestätigen deine Anfrage per E-Mail.
            </li>
            <li class="flex items-start gap-2">
              <span class="text-bnz-500 font-bold">2.</span>
              Unser Team nimmt Kontakt auf und bespricht die Details mit dir.
            </li>
            <li class="flex items-start gap-2">
              <span class="text-bnz-500 font-bold">3.</span>
              Nach Absprache schicken wir dir die Unterlagen.
            </li>
          </ul>
        </div>
      </div>
    </main>

    <!-- Mobile: Sticky Warenkorb (immer sichtbar) -->
    <CartMobileBar
      v-if="step !== 'success'"
      class="lg:hidden"
      :selections="selectionList"
      :selection-count="selectionList.length"
      :total="total"
      :has-selections="selectionList.length > 0"
      :step="step"
      :form-valid="formValid"
      @click-cta="goToForm"
      @submit="handleMobileBarSubmit"
    />

  </div>
</template>
