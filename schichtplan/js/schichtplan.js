/**
 * JavaScript für Schichtplan-System
 * Bedburger Narrenzunft
 */

let currentSelectedDate = null;
let currentSelectedSchichtId = null;
let currentView = localStorage.getItem('schichtplan_view') || 'calendar';

/** Ungefährliche Ausgabe in HTML-Template-Strings */
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
}

/**
 * Bestimmt die Farbe basierend auf der Verfügbarkeit von Helfern
 * @param {number} anzahlBenoetigt Anzahl der benötigten Helfer (0 = unbegrenzt)
 * @param {number} angemeldet Anzahl der bereits angemeldeten Helfer
 * @returns {string} Hex-Farbcode
 */
function getAvailabilityColor(anzahlBenoetigt, angemeldet) {
    if (anzahlBenoetigt == 0) {
        // Unbegrenzt - grün
        return '#22c55e'; // green-500
    }
    
    const frei = anzahlBenoetigt - angemeldet;
    
    if (frei <= 0) {
        // Voll - rot
        return '#ef4444'; // red-500
    }
    
    const prozentFrei = (frei / anzahlBenoetigt) * 100;
    
    if (prozentFrei >= 50) {
        // Viele Plätze frei - grün
        return '#22c55e'; // green-500
    } else {
        // Wenige Plätze frei - gelb
        return '#eab308'; // yellow-500
    }
}

// Ansicht wechseln
function switchView(view) {
    currentView = view;
    localStorage.setItem('schichtplan_view', view);
    
    const calendarBtn = document.getElementById('viewCalendarBtn');
    const listBtn = document.getElementById('viewListBtn');
    const calendarView = document.getElementById('calendarView');
    const listView = document.getElementById('listView');
    const monthNav = document.getElementById('monthNavigation');
    
    if (!calendarBtn || !listBtn || !calendarView || !listView || !monthNav) {
        console.error('View switch elements not found');
        return;
    }
    
    // Buttons aktualisieren
    if (view === 'calendar') {
        calendarBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        calendarBtn.classList.remove('active');
        listBtn.classList.add('active');
    }
    
    // Ansichten zeigen/verstecken
    if (view === 'calendar') {
        calendarView.classList.add('active');
        listView.classList.remove('active');
        monthNav.style.display = 'flex';
    } else {
        calendarView.classList.remove('active');
        listView.classList.add('active');
        monthNav.style.display = 'flex'; // Monatsnavigation auch in Listen-Ansicht anzeigen
    }
}

// Funktion global verfügbar machen (muss vor DOMContentLoaded stehen)
window.switchView = switchView;

// Event-Handler-Funktionen
function handleCalendarClick(e) {
    e.preventDefault();
    e.stopPropagation();
    switchView('calendar');
}

function handleListClick(e) {
    e.preventDefault();
    e.stopPropagation();
    switchView('list');
}

// Funktion zum Initialisieren der Event-Listener
function initViewSwitch() {
    // Event-Listener für View-Switch-Buttons hinzufügen
    const calendarBtn = document.getElementById('viewCalendarBtn');
    const listBtn = document.getElementById('viewListBtn');
    
    if (calendarBtn && !calendarBtn.dataset.listenerAttached) {
        calendarBtn.addEventListener('click', handleCalendarClick);
        calendarBtn.dataset.listenerAttached = 'true';
    }
    
    if (listBtn && !listBtn.dataset.listenerAttached) {
        listBtn.addEventListener('click', handleListClick);
        listBtn.dataset.listenerAttached = 'true';
    }
    
    // Initialisiere die Ansicht basierend auf gespeichertem Wert oder Standard
    setTimeout(function() {
        switchView(currentView);
    }, 50);
}

// Beim Laden die gespeicherte Ansicht wiederherstellen
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initViewSwitch);
} else {
    // DOM ist bereits geladen
    initViewSwitch();
}

/**
 * Tagesdetails anzeigen
 */
async function showDayDetails(date) {
    currentSelectedDate = date;
    const modal = document.getElementById('dayModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('modalContent');
    
    // Datum formatieren
    const dateObj = new Date(date + 'T00:00:00');
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = dateObj.toLocaleDateString('de-DE', options);
    
    modalTitle.textContent = `Schichten am ${formattedDate}`;
    modalContent.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-gray-400"></i></div>';
    modal.classList.remove('hidden');
    
    try {
        const response = await fetch(`api.php?action=get_day_shifts&date=${date}`);
        const data = await response.json();
        
        if (data.success && data.data.schichten.length > 0) {
            let html = `<div class="mb-4 pb-3 border-b-2 border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">
                        ${data.data.schichten.length} Schicht${data.data.schichten.length !== 1 ? 'en' : ''} für diesen Tag
                    </span>
                </div>
            </div>
            <div class="space-y-3 sm:space-y-4">`;
            
            data.data.schichten.forEach(schicht => {
                const anzahlBenoetigt = parseInt(schicht.required_count) || 0;
                const angemeldet = parseInt(schicht.registered_count) || 0;
                const isFull = anzahlBenoetigt > 0 && angemeldet >= anzahlBenoetigt;
                const frei = anzahlBenoetigt > 0 ? anzahlBenoetigt - angemeldet : null;
                const availabilityColor = getAvailabilityColor(anzahlBenoetigt, angemeldet);
                
                html += `
                    <div class="bg-white border-2 ${isFull ? 'border-gray-300' : 'border-gray-200 hover:border-narrenzunft'} rounded-xl p-4 sm:p-5 hover:shadow-lg transition-all group">
                        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                            <!-- Linke Seite: Hauptinfos -->
                            <div class="flex-1 min-w-0">
                                <!-- Aufgabenbereich und Zeit in einer Zeile -->
                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                    <span class="px-4 py-2 rounded-lg bg-blue-600 text-white text-base sm:text-lg font-bold whitespace-nowrap shadow-md">
                                        <i class="fas fa-tasks mr-2"></i>${schicht.task_area_name}
                                    </span>
                                    ${schicht.start_time ? `
                                        <div class="flex items-center gap-1 text-gray-700 font-medium">
                                            <i class="fas fa-clock text-sm"></i>
                                            <span class="text-sm sm:text-base">
                                                ${schicht.start_time.substring(0, 5)}
                                                ${schicht.end_time ? `<span class="text-gray-400 mx-1">-</span>${schicht.end_time.substring(0, 5)}` : ''}
                                            </span>
                                        </div>
                                    ` : ''}
                                </div>
                                
                                ${schicht.team_slots && schicht.team_slots.length > 0 ? `
                                <div class="mb-3 text-sm text-gray-700 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
                                    <p class="font-semibold text-gray-800 mb-1.5 flex items-center gap-2">
                                        <i class="fas fa-users text-narrenzunft"></i> Team-Zeitfenster
                                    </p>
                                    <ul class="space-y-1">
                                        ${schicht.team_slots.map(sl => `
                                            <li>
                                                <span class="font-medium tabular-nums">${escapeHtml(sl.start)}–${escapeHtml(sl.end)}</span>
                                                ${sl.team ? `<span class="text-gray-600"> · ${escapeHtml(sl.team)}</span>` : ''}
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>
                                ` : ''}
                                
                                <!-- Verfügbarkeits-Status dezenter -->
                                <div class="mb-3">
                                    ${isFull ? `
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-red-50 border border-red-200 rounded text-xs">
                                            <i class="fas fa-times-circle text-red-500 text-xs"></i>
                                            <span class="text-red-600">Voll</span>
                                            <span class="text-red-500">(${angemeldet}/${anzahlBenoetigt})</span>
                                        </div>
                                    ` : `
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-green-50 border border-green-200 rounded text-xs">
                                            <i class="fas fa-check-circle text-green-500 text-xs"></i>
                                            ${frei !== null ? `<span class="text-green-600">${frei} frei</span>` : '<span class="text-green-600">Frei</span>'}
                                            ${anzahlBenoetigt > 0 ? `
                                                <span class="text-green-500">(${angemeldet}/${anzahlBenoetigt})</span>
                                            ` : `
                                                <span class="text-green-500">(${angemeldet})</span>
                                            `}
                                        </div>
                                    `}
                                </div>
                                
                                <!-- Zusätzliche Details -->
                                <div class="space-y-2 text-sm text-gray-600">
                                    ${schicht.responsible_person ? `
                                        <div class="flex items-start gap-2">
                                            <i class="fas fa-user-tie text-narrenzunft mt-0.5 flex-shrink-0"></i>
                                            <span><strong class="text-gray-700">Verantwortung:</strong> ${schicht.responsible_person}</span>
                                        </div>
                                    ` : ''}
                                    ${schicht.location ? `
                                        <div class="flex items-start gap-2">
                                            <i class="fas fa-map-marker-alt text-narrenzunft mt-0.5 flex-shrink-0"></i>
                                            <span>${schicht.location}</span>
                                        </div>
                                    ` : ''}
                                    ${schicht.description ? `
                                        <div class="flex items-start gap-2 pt-1 border-t border-gray-100">
                                            <i class="fas fa-info-circle text-narrenzunft mt-0.5 flex-shrink-0"></i>
                                            <p class="text-gray-700 leading-relaxed">${schicht.description}</p>
                                        </div>
                                    ` : ''}
                                </div>
                                
                                <!-- Eingetragene Personen -->
                                ${schicht.employees && schicht.employees.length > 0 ? `
                                    <div class="mt-4 pt-3 border-t border-gray-200">
                                        <p class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                            <i class="fas fa-users text-narrenzunft"></i>
                                            Eingetragene Personen (${schicht.employees.length})
                                        </p>
                                        <ul class="space-y-2">
                                            ${schicht.employees.map(m => {
                                                const name = m.is_group == 1 ? m.group_name : `${m.first_name || ''} ${m.last_name || ''}`.trim();
                                                const abteilung = m.department_name || m.group_name || '';
                                                return `
                                                    <li class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-3">
                                                        <div class="flex-1">
                                                            <div class="font-semibold text-gray-800">${name}</div>
                                                            ${abteilung && m.is_group != 1 ? `<div class="text-xs text-gray-500 mt-1">Abteilung: ${abteilung}</div>` : ''}
                                                            ${m.note ? `<div class="text-xs text-gray-600 mt-1 italic">${m.note}</div>` : ''}
                                                        </div>
                                                    </li>
                                                `;
                                            }).join('')}
                                        </ul>
                                    </div>
                                ` : `
                                    <div class="mt-4 pt-3 border-t border-gray-200">
                                        <p class="text-sm text-gray-500 italic">
                                            <i class="fas fa-info-circle mr-1"></i>Noch keine Eintragungen
                                        </p>
                                    </div>
                                `}
                            </div>
                            
                            <!-- Rechte Seite: Action Button -->
                            <div class="flex-shrink-0 sm:ml-4">
                                ${!isFull ? `
                                    <button onclick="openSignupModal(${schicht.id})" 
                                            class="w-full sm:w-auto bg-narrenzunft text-white px-5 py-3 rounded-lg hover:bg-red-800 active:bg-red-900 transition-colors text-sm sm:text-base font-semibold touch-button shadow-md hover:shadow-lg group-hover:scale-105 transition-transform whitespace-nowrap">
                                        <i class="fas fa-plus mr-2"></i>Eintragen
                                    </button>
                                ` : `
                                    <div class="w-full sm:w-auto px-5 py-3 rounded-lg bg-gray-200 text-gray-600 text-sm sm:text-base font-semibold text-center whitespace-nowrap cursor-not-allowed">
                                        <i class="fas fa-lock mr-2"></i>Voll
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            modalContent.innerHTML = html;
        } else {
            modalContent.innerHTML = '<div class="text-center py-8 text-gray-500">Keine Schichten für diesen Tag vorhanden.</div>';
        }
    } catch (error) {
        console.error('Fehler beim Laden der Schichten:', error);
        modalContent.innerHTML = '<div class="text-center py-8 text-red-500">Fehler beim Laden der Schichten. Bitte versuchen Sie es erneut.</div>';
    }
}

/**
 * Modal schließen
 */
function closeDayModal() {
    document.getElementById('dayModal').classList.add('hidden');
    currentSelectedDate = null;
}

/**
 * Button-Status zurücksetzen (Hilfsfunktion)
 */
function resetSignupButton() {
    const form = document.getElementById('signupForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return;
    
    // Button komplett zurücksetzen
    submitBtn.disabled = false;
    submitBtn.removeAttribute('disabled');
    submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Eintragen';
    submitBtn.className = 'w-full bg-narrenzunft text-white py-3 rounded-lg hover:bg-red-800 transition-colors font-medium';
    submitBtn.style.pointerEvents = 'auto';
    submitBtn.style.opacity = '1';
    submitBtn.style.cursor = 'pointer';
    submitBtn.style.removeProperty('pointer-events');
    submitBtn.style.removeProperty('opacity');
    submitBtn.style.removeProperty('cursor');
}

/**
 * Eintragungs-Modal öffnen
 */
function openSignupModal(schichtId) {
    currentSelectedSchichtId = schichtId;
    
    // Formular komplett zurücksetzen
    const form = document.getElementById('signupForm');
    if (!form) {
        console.error('Formular nicht gefunden!');
        return;
    }
    
    form.reset();
    
    // Schicht-ID setzen (nach reset)
    const schichtIdInput = document.getElementById('signupSchichtId');
    if (schichtIdInput) {
        schichtIdInput.value = schichtId;
    }
    
    // Button-Status zurücksetzen (mehrfach für Sicherheit)
    resetSignupButton();
    
    // Alle Input-Felder aktivieren
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.disabled = false;
        input.removeAttribute('readonly');
        input.style.removeProperty('opacity');
        input.style.removeProperty('pointer-events');
    });
    
    // Modal anzeigen
    const modal = document.getElementById('signupModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
    
    // Button nochmal zurücksetzen nach kurzer Verzögerung (falls DOM noch nicht bereit)
    setTimeout(() => {
        resetSignupButton();
        
        // Fokus auf erstes Feld setzen
        const firstInput = form.querySelector('input[type="text"]');
        if (firstInput) {
            firstInput.focus();
        }
    }, 50);
}

/**
 * Eintragungs-Modal schließen
 */
function closeSignupModal() {
    const form = document.getElementById('signupForm');
    if (form) {
        form.reset();
    }
    
    // Button-Status zurücksetzen
    resetSignupButton();
    
    // Alle Input-Felder aktivieren
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.disabled = false;
            input.removeAttribute('readonly');
            input.style.removeProperty('opacity');
            input.style.removeProperty('pointer-events');
        });
    }
    
    // Modal verstecken
    const modal = document.getElementById('signupModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    
    currentSelectedSchichtId = null;
    
    // Button nochmal zurücksetzen nach kurzer Verzögerung
    setTimeout(() => {
        resetSignupButton();
    }, 100);
}

/**
 * Eintragung absenden
 */
const signupForm = document.getElementById('signupForm');
if (signupForm) {
    signupForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Prüfe ob Button bereits deaktiviert ist (verhindert Doppel-Submit)
        if (submitBtn.disabled) {
            return false;
        }
        
        // Validierung
        const vorname = form.querySelector('[name="vorname"]').value.trim();
        const nachname = form.querySelector('[name="nachname"]').value.trim();
        const abteilung = form.querySelector('[name="abteilung_id"]').value;
        
        if (!vorname || !nachname || !abteilung) {
            alert('Bitte füllen Sie alle Pflichtfelder aus.');
            return false;
        }
        
        const formData = new FormData(form);
        formData.append('action', 'signup');
        
        const originalText = submitBtn.innerHTML;
        const originalClasses = submitBtn.className;
        
        // Button deaktivieren
        submitBtn.disabled = true;
        submitBtn.classList.remove('hover:bg-red-800');
        submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Wird eingetragen...';
        
        // Alle Input-Felder deaktivieren während des Submits
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.disabled = true;
        });
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Erfolgsmeldung anzeigen
                submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Erfolgreich!';
                submitBtn.classList.remove('bg-gray-400');
                submitBtn.classList.add('bg-green-600');
                
                // Nach kurzer Anzeige Modal schließen und zurücksetzen
                setTimeout(() => {
                    // Button komplett zurücksetzen BEVOR Modal geschlossen wird
                    resetSignupButton();
                    
                    // Alle Input-Felder wieder aktivieren
                    inputs.forEach(input => {
                        input.disabled = false;
                        input.style.removeProperty('opacity');
                        input.style.removeProperty('pointer-events');
                    });
                    
                    // Formular zurücksetzen
                    form.reset();
                    const schichtIdInput = document.getElementById('signupSchichtId');
                    if (schichtIdInput) {
                        schichtIdInput.value = '';
                    }
                    
                    // Modal schließen
                    const modal = document.getElementById('signupModal');
                    if (modal) {
                        modal.classList.add('hidden');
                    }
                    currentSelectedSchichtId = null;
                    
                    // Button nochmal zurücksetzen nach Modal-Schließung
                    setTimeout(() => {
                        resetSignupButton();
                    }, 100);
                    
                    // Tagesdetails neu laden
                    if (currentSelectedDate) {
                        showDayDetails(currentSelectedDate);
                    } else {
                        location.reload();
                    }
                }, 1500);
            } else {
                // Fehler: Button wieder aktivieren
                alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                submitBtn.className = originalClasses;
                submitBtn.removeAttribute('disabled');
                
                // Alle Input-Felder wieder aktivieren
                inputs.forEach(input => {
                    input.disabled = false;
                });
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Eintragen. Bitte versuchen Sie es erneut.');
            
            // Button wieder aktivieren
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            submitBtn.className = originalClasses;
            submitBtn.removeAttribute('disabled');
            
            // Alle Input-Felder wieder aktivieren
            inputs.forEach(input => {
                input.disabled = false;
            });
        }
        
        return false;
    });
}

// Löschen-Funktion entfernt - nur Admins können Eintragungen löschen
// Siehe admin_eintragungen.php für Admin-Löschfunktion

// Modal schließen bei Klick außerhalb
document.getElementById('dayModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDayModal();
    }
});

// Event-Listener für Modal-Öffnung hinzufügen (MutationObserver als Fallback)
const signupModal = document.getElementById('signupModal');
if (signupModal) {
    // Beim Klick außerhalb schließen
    signupModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeSignupModal();
        }
    });
    
    // Beim Anzeigen des Modals Button zurücksetzen
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                if (!signupModal.classList.contains('hidden')) {
                    // Modal wurde geöffnet - Button zurücksetzen
                    setTimeout(() => {
                        resetSignupButton();
                    }, 10);
                }
            }
        });
    });
    
    observer.observe(signupModal, {
        attributes: true,
        attributeFilter: ['class']
    });
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDayModal();
        closeSignupModal();
    }
});

