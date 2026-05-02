# BOW Ressourcenplanung (bowresourceplanning)

**Version:** 1.0.0  
**Kompatibilität:** Perfex CRM 3.x  
**Autor:** BOW Digital

---

## Übersicht

Das Modul **BOW Ressourcenplanung** erweitert Perfex CRM um ein visuelles Ressourcenplanungs-Board (ähnlich Float/Teamweek):

- 📅 **Planungsboard** – Drag-and-drop Timeline für Mitarbeiterzuweisungen
- 🗂️ **Projektzuweisungen** – Mitarbeiter Projekten und Aufgaben zuordnen
- ⏱️ **Stunden- und Kapazitätsplanung** – Stunden pro Tag und Auslastungsstatus
- 📊 **Berichte** – Auslastungsübersicht pro Mitarbeiter und Projekt
- 🏖️ **Abwesenheitsanzeige** – Integration von HR-Modul-Abwesenheiten (optional)
- 🔄 **Arbeitsmuster** – Wochenstunden pro Mitarbeiter konfigurierbar

---

## Installation

1. Modul-Ordner nach `modules/bowresourceplanning/` kopieren
2. Im Admin unter **Setup → Module** das Modul aktivieren
3. Die Datenbanktabellen werden automatisch erstellt
4. Standard-Arbeitsmuster (40h/Woche) wird angelegt

---

## Berechtigungen einrichten

### Verfügbare Berechtigungen

| Berechtigung | Beschreibung |
|--------------|--------------|
| **Ansehen** | Planungsboard und Berichte lesen |
| **Erstellen** | Neue Zuweisungen anlegen |
| **Bearbeiten** | Bestehende Zuweisungen ändern |
| **Löschen** | Zuweisungen entfernen |

### Empfohlene Konfiguration

#### Mitarbeiter (read-only)
- ✅ Ansehen
- ❌ Erstellen / Bearbeiten / Löschen

#### Projektmanager
- ✅ Ansehen
- ✅ Erstellen
- ✅ Bearbeiten
- ❌ Löschen

#### Admin / Ressourcenmanager
- ✅ Alle Berechtigungen

---

## Planungsboard

### Verwendung

1. **BOW Ressourcenplanung → Planungsboard** öffnen
2. Zeitraum über Pfeilnavigation oder Datumsauswahl wählen
3. Über den **+**-Button eine neue Zuweisung anlegen:
   - Mitarbeiter wählen
   - Projekt oder Aufgabe zuordnen
   - Datumsbereich festlegen
   - Stunden pro Tag eingeben
4. Bestehende Zuweisungen per Klick bearbeiten oder löschen

### Ansichten

- **Woche** – 7 Tage
- **2 Wochen** – 14 Tage
- **Monat** – ~30 Tage
- **2 Monate** – ~60 Tage

### Kapazitätsstatus

| Farbe | Status |
|-------|--------|
| 🟢 Grün | Auslastung ≤ 80 % |
| 🟡 Gelb | Auslastung 80–100 % |
| 🔴 Rot | Überbucht (> 100 %) |
| ⚫ Grau | Kein Arbeitstag / Urlaub |

---

## Berichte

Unter **BOW Ressourcenplanung → Berichte** stehen folgende Auswertungen zur Verfügung:

- **Auslastungsübersicht** – Stunden und Prozent pro Mitarbeiter
- **Projektverteilung** – Stunden je Projekt als Pie-Chart
- **Aufgabendetails** – Geplante vs. geschätzte Stunden pro Aufgabe

### Filter

- Datumsbereich (Von / Bis)
- Mitarbeiter (einzeln oder alle)
- Projekt

---

## Arbeitsmuster

Unter **BOW Ressourcenplanung → Arbeitsmuster** können Wochenstunden je Mitarbeiter konfiguriert werden:

- **System-Standard (40h):** Mo–Fr je 8h — wird bei der Erstinstallation angelegt
- **Mitarbeiter-spezifisch:** Individuelle Stundenzahl pro Wochentag
- **Gültigkeitszeitraum:** Pattern kann ab einem bestimmten Datum gelten

---

## Abwesenheiten

Wenn das Modul **BOW Personal (bowhumanresources)** ebenfalls aktiv ist, werden genehmigte Abwesenheiten automatisch im Planungsboard angezeigt (Urlaub, Krankheit etc.) und bei der Kapazitätsberechnung berücksichtigt.

---

## Tests

Das Modul enthält eine PHPUnit-Test-Suite (137 Tests).

### Voraussetzungen

- PHP 8.1+
- Composer

### Ausführen

```bash
cd modules/bowresourceplanning
composer install
./vendor/bin/phpunit
```

Die Tests prüfen:
- Helper-Funktionen (Kapazitätsberechnung, Farben, Datum-Utilities)
- Vollständigkeit beider Sprachdateien (DE/EN)
- Schlüssel-Parität zwischen Deutsch und Englisch
- Keine doppelten Sprachschlüssel

---

## Changelog

### Version 1.0.0 (Mai 2025)
- Initiale Version
- Visuelles Planungsboard mit Kapazitätsanzeige
- Projekt- und Aufgabenzuweisungen
- Auslastungsberichte mit Highcharts
- Arbeitsmuster-Verwaltung
- Integration mit BOW Personal (Abwesenheiten)
- PHPUnit-Test-Suite

---

## Support

Bei Fragen oder Problemen wenden Sie sich an:

**BOW Digital**  
E-Mail: info@bow-agentur.de
