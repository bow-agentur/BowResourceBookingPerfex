# BowResourceBookingPerfex — Planungstafel (Planning Board) v2.0 — Implementierungsplan

## Ausgangssituation

Das Modul existiert mit einer Float-ähnlichen Planungstafel (`views/planning_board.php`, `assets/js/planning-board.js`). Die Kernentitäten sind:

- **`rb_allocations`** — Staff auf Projekte/Tasks zuweisen (Datum von/bis, h/Tag)
- **`rb_work_patterns`** — Arbeitszeitmodelle pro Mitarbeiter
- **`rb_time_off`** — Abwesenheiten

Perfex-Kerntabellen die erweitert werden: `projects`, `tasks`, `project_members`, `task_assigned`.

---

## Ziele & Anforderungen

### 1. Planungstafel — Mehrzeilige Mitarbeiterzeilen (overlapping projects)

**Ist:** Jeder Mitarbeiter hat genau eine Zeile. Überlappende Projekte werden übereinander geschrieben (kein Platz).

**Soll:** Jeder Mitarbeiter hat eine dynamisch wachsende Zeilengruppe. Innerhalb dieser Gruppe erhält jede überlappende Zuweisung (Allocation) eine eigene Unterzeile. Nicht-überlappende Zuweisungen teilen sich eine Unterzeile.

```
┌─────────────┬───────────────────────────────────────────────┐
│ Max Mustermann                                              │
│  ├── [Projekt A: 23.05–30.05 · 4h/d]                       │
│  ├── [Projekt B: 27.05–03.06 · 3h/d] ← überlappt mit A    │
│  └── [Task: "Logo Design" 25.05–28.05 · 2h/d]              │
├─────────────┼───────────────────────────────────────────────┤
│ Anna Schmidt│                                               │
│  └── [Projekt A: 20.05–04.06 · 8h/d]                       │
└─────────────┴───────────────────────────────────────────────┘
```

**Implementierung:**
- Berechnung im Frontend (JS): Lane-Packing-Algorithmus (Intervall-Scheduling-Problem)
- Jeder Staff-Block bekommt so viele Lanes wie nötig für seine Allocations
- Mitarbeiter-Label zeigt Name + Gesamt-Auslastung (z.B. 80%)
- Kollaps/Expand des Staff-Blocks per Klick

---

### 2. Projekte liefern den Zeitrahmen — kein separater Datumsbereich bei Zuweisung

**Ist:** Beim Erstellen einer Allocation muss Datum von/bis manuell eingegeben werden.

**Soll:** Wird ein Mitarbeiter einem Projekt zugewiesen, werden Start- und Enddatum automatisch aus dem Projekt übernommen. Das Feld ist readonly, aber überschreibbar (bei Bedarf).

- Beim Auswählen eines Projekts im Allocation-Dialog: Felder `date_from`/`date_to` werden mit `project.start_date`/`project.deadline` befüllt.
- Beim Auswählen eines Tasks: Felder werden mit `task.startdate`/`task.duedate` befüllt.
- Die gespeicherte Allocation behält eigene Daten (für Teilzuweisungen), aber die UI kommuniziert den Projektkontext.

**DB:** Keine Schemaänderung nötig, nur UI-Logik.

---

### 3. Tasks in der Detailansicht der Planungstafel

**Ist:** Die Planungstafel zeigt Allocations als Balken (Projekt-Level). Tasks werden nicht separat visualisiert.

**Soll:** Jede Projektallocation auf der Planungstafel kann ausgeklappt werden, um die Tasks des Projekts zu sehen, die dem jeweiligen Mitarbeiter zugewiesen sind. Tasks erscheinen als kleinere Balken unterhalb der Projektallocation.

- Tooltip/Hover: Task-Name, Status, fällig am, geschätzte Stunden
- Tasks nur sichtbar wenn: `task_assigned.staffid = staff_id` AND `tasks.rel_id = project_id`
- API-Erweiterung: `api_board_data` liefert Tasks pro Allocation mit

---

### 4. Tasks in Perfex erweitern — Stundenfeld

**Ist:** Perfex-Tasks haben `duedate` aber kein Feld für geschätzte Gesamtstunden.

**Soll:** Tasks erhalten ein neues Feld **`estimated_hours`** (DECIMAL 6,2).

**DB-Migration:**
```sql
ALTER TABLE `tblitems_tasks`   -- tatsächlich: tbltasks
  ADD COLUMN `estimated_hours` DECIMAL(6,2) NULL DEFAULT NULL 
  AFTER `duedate`;
```
*(Tabellenname prüfen: `db_prefix() . 'tasks'`)*

**UI-Integration (Perfex Task-Modal):**
- Neues Eingabefeld "Geschätzte Stunden" im Task-Formular
- Hook: `before_add_task` / `after_update_task` → Wert speichern
- Wird über die Planungstafel lesend und schreibend zugänglich

---

### 5. Stundendarstellung in der Planungstafel (h/d Durchschnitt)

**Formel:** `daily_avg = estimated_hours / working_days(start, end)`

`working_days` = Anzahl Werktage im Zeitraum (optional: Wochenenden einschließen wenn `include_weekends = 1`).

**Beispiel:** 10h Aufgabe, 7 Tage (Mo–So, keine Wochenenden = 5 Werktage) → **2h/d**

**Anzeige auf dem Balken:**
- Primär: `[Task-Name · 2h/d]`
- Bei sehr schmalen Balken: nur Icon oder abgekürzte Stunden

**Berechnung:**
- PHP-Hilfsfunktion `rb_calc_daily_avg($estimated_hours, $date_from, $date_to, $include_weekends)` in `helpers/rb_capacity_helper.php`
- Wird in `api_board_data` für jede Allocation und jeden Task mitgeliefert

---

### 6. Datenarchitektur — Live View statt redundanter Speicherung

**Entscheidung:** Die Planungstafel ist eine **Live View über Perfex-Daten**. Es werden KEINE redundanten Allocations in `rb_allocations` für alle Projekt-/Task-Mitgliedschaften angelegt. Stattdessen:

- `api_board_data` liest **live** aus `project_members`, `task_assigned`, `tasks`, `projects`
- `rb_allocations` speichert ausschließlich **Planungs-Overrides** die Perfex nicht kennt: spezifische h/Tag, Farbe, Notiz, und Task-Stunden-Schätzungen
- Beim Laden des Boards: jeder `project_members`-Eintrag erscheint automatisch als Balken, auch ohne passende Allocation
- Planungs-Daten (h/d, Farbe) werden per `(staff_id, project_id, task_id)` aus `rb_allocations` geladen und über den Perfex-Datensatz gelegt

**Schreibrichtung Board → Perfex:**

| Aktion in Planungstafel | Schreibt nach Perfex |
|---|---|
| Mitarbeiter via Board einem Projekt zugewiesen | `project_members` INSERT |
| Mitarbeiter via Board einem Task zugewiesen | `task_assigned` INSERT |
| Mitarbeiter aus Projekt entfernt | `project_members` DELETE |

| Mitarbeiter aus Task entfernt | `task_assigned` DELETE |
| Task-Stunden bearbeitet | `tasks.estimated_hours` UPDATE |
| h/Tag oder Farbe auf Balken geändert | `rb_allocations` UPSERT (nur Planungs-Override) |
| Mitarbeiter als Follower gesetzt | Follower-Tabelle UPDATE |

**Automatismus:** Wenn in Perfex ein Projekt/Task-Mitglied hinzugefügt wird, erscheint es beim nächsten Board-Load automatisch ohne zusätzliche Aktion.

---

### 7. Berechtigungen & Mitarbeiter-Ansicht

| Rolle | Planungstafel | Reports |
|---|---|---|
| Admin | Vollzugriff (lesen, erstellen, bearbeiten, löschen, alle Mitarbeiter) | Vollzugriff |
| Mitarbeiter | Lesen (gesamtes Board), eigene Zeile + eigene Ziele sichtbar | Kein Zugriff |

**Mitarbeiter sehen das Board — aber:**
- Alle Mitarbeiter-Zeilen werden angezeigt (nicht nur die eigene), damit Kollegen-Auslastung sichtbar ist
- Eigene Zeile ist visuell hervorgehoben
- **Ziele:** Im Staff-Header wird angezeigt, welche Projekte/Tasks dem Mitarbeiter zugewiesen sind + ihre geplanten Stunden — das sind die persönlichen Arbeitsziele
- Drag&Drop, Resize, Edit-Buttons, "Neue Zuweisung" komplett deaktiviert für Mitarbeiter
- `config.isEmployee = true` wird serverseitig gesetzt wenn `get_staff_user_id()` kein Admin ist

**Implementierung:**
- `has_permission('resourcebooking', '', 'create')` → Nur Admins
- `config.canEdit = false` für Mitarbeiter → interact.js nicht initialisiert
- Mitarbeiter-eigene Zeile: CSS-Klasse `rb-own-row` für Highlighting

---

### 8. Integration bowhumanressources — Urlaub, Krank, Feiertage

**Entscheidung:** Die `rb_time_off`-Tabelle wird **nicht** für Urlaub/Krank/Feiertage genutzt. Diese Daten werden live aus dem `bowhumanressources`-Modul gelesen.

**Quell-Tabellen (bowhumanressources):**

| Datentyp | Tabelle | Bedingung |
|---|---|---|
| Genehmigte Abwesenheiten (Urlaub, Krank, HO) | `tblhr_absences` | `status = 'approved'` |
| Gesetzliche Feiertage | `tblhr_public_holidays` | aktives Jahr, passendes Bundesland |

**`rb_time_off` bleibt nur für:** manuell eingetragene Sperrzeiten die nicht aus HR stammen (z.B. interne Events).

**Kapazitätsberechnung:** `available_hours` = Work-Pattern-Stunden − HR-Abwesenheits-Stunden − Feiertags-Stunden

**Abhängigkeit absichern:**
```php
// In Rb_planning_model::get_board_data()
if ($this->db->table_exists(db_prefix() . 'hr_absences')) {
    // HR-Abwesenheiten laden
} else {
    // Fallback auf rb_time_off
}
```

**Überstunden-Warnung:**
- Wenn `geplante_stunden_pro_tag > verfügbare_stunden_pro_tag`: Balken wird **rot** dargestellt
- Zusätzlich: Perfex-System-Notification für den betroffenen Mitarbeiter und den Admin
- Notification-Text: "[Mitarbeitername] ist am [Datum] mit [X]h überlastet ([Projekt])"
- Notification wird über `add_notification()` Perfex-Hook angelegt, nur bei Speichern/Aktualisieren einer Allocation

---

## Implementierungsschritte

### Phase 1 — DB & Datenmodell

**Schritt 1.1 — Tasks: `estimated_hours` Feld**
- `install.php` erweitern: `ALTER TABLE tbltasks ADD estimated_hours ...`
- Auto-Migration in Model: prüfen ob Feld existiert, sonst hinzufügen
- Hooks registrieren: `add_action('before_add_task', ...)` und `add_action('before_update_task', ...)`
- Hook-Handler in `resourcebooking.php` oder separater `includes/task_hooks.php`

**Schritt 1.2 — Allocations: nur Planungs-Overrides speichern**
- `rb_allocations` speichert NUR was Perfex nicht hat: `hours_per_day`, `color`, `note` pro `(staff_id, project_id, task_id)`
- Eindeutiger Index: `UNIQUE KEY (staff_id, project_id, task_id)` → UPSERT möglich
- `source_type`/`is_synced_member` entfallen — nicht nötig da Board live aus Perfex liest
- Migration: bestehende `rb_allocations` Einträge behalten, nur redundante Datums-Felder werden ignoriert wenn Projekt-Daten verfügbar

**Schritt 1.3 — API: Live-Daten aus Perfex + HR**
- `get_board_data()` im Model: JOIN über `project_members` + `projects` + `tasks` + `task_assigned`
- Für jeden Staff + Projekt: Allocation-Override aus `rb_allocations` per LEFT JOIN laden (falls vorhanden)
- Task-Daten: `id, name, startdate, duedate, estimated_hours, status, daily_avg`
- HR-Daten: `hr_absences` (approved) + `hr_public_holidays` per LEFT JOIN, Fallback auf `rb_time_off`

**Schritt 1.4 — bowhumanressources Integration**
- Prüfen ob HR-Tabellen existieren: `tblhr_absences`, `tblhr_public_holidays`
- `rb_capacity_helper.php`: Funktion `rb_get_available_hours($staff_id, $date)` — liest aus HR falls verfügbar
- Kapazitätsfarben: Grün (≤80%), Orange (81–100%), Rot (>100% = Überlastung + Notification)

---

### Phase 2 — Backend-Logik

**Schritt 2.1 — Live Board-Daten aus Perfex lesen**

```php
public function get_board_data($date_from, $date_to, $filters = [])
{
    // 1. Projekt-Mitgliedschaften direkt aus project_members + projects
    $this->db->select('pm.staff_id, p.id as project_id, p.name as project_name,
        p.start_date, p.deadline,
        a.hours_per_day, a.color, a.note,  -- Planungs-Overrides (nullable)
        s.firstname, s.lastname');
    $this->db->from(db_prefix() . 'project_members pm');
    $this->db->join(db_prefix() . 'projects p', 'p.id = pm.project_id');
    $this->db->join(db_prefix() . 'staff s', 's.staffid = pm.staff_id');
    $this->db->join($this->table_allocations . ' a',
        'a.staff_id = pm.staff_id AND a.project_id = pm.project_id AND a.task_id IS NULL',
        'left');
    // ... Filter + Task-Zuweisungen analog

    // 2. HR-Abwesenheiten und Feiertage
    $time_off = $this->get_hr_time_off($date_from, $date_to);

    // 3. Kapazität berechnen (work_patterns vs. time_off)
    // 4. Überlastungen ermitteln → Notification auslösen falls nötig
}
```

**Schritt 2.2 — Board schreibt direkt nach Perfex (kein separater Sync)**

```php
public function assign_staff_to_project($staff_id, $project_id)
{
    // INSERT IGNORE in project_members
    // UPSERT in rb_allocations für Planungs-Override
    // Perfex-Hook: log_activity() + add_notification() wenn Überlastung
}

public function assign_staff_to_task($staff_id, $task_id)
{
    // INSERT IGNORE in task_assigned
    // UPSERT in rb_allocations
}

public function remove_staff_from_project($staff_id, $project_id)
{
    // DELETE aus project_members
    // DELETE aus rb_allocations (Planungs-Override entfernen)
}
```

**Schritt 2.3 — Controller: Direkte Board-Aktionen**

```php
public function api_assign_member()   { /* POST: staff_id + project_id|task_id → schreibt nach Perfex */ }
public function api_remove_member()   { /* POST: staff_id + project_id|task_id → entfernt aus Perfex */ }
public function api_update_task_hours() { /* POST: task_id, estimated_hours → tbltasks */ }
public function api_upsert_override() { /* POST: staff_id, project_id|task_id, hours_per_day, color, note → rb_allocations */ }
```

**Schritt 2.4 — HR-Integration im Model**

```php
private function get_hr_time_off($date_from, $date_to)
{
    if ($this->db->table_exists(db_prefix() . 'hr_absences')) {
        // Approved Abwesenheiten aus HR-Modul
        return $this->db->select('staff_id, start_date, end_date, type')
            ->where('status', 'approved')
            ->where('start_date <=', $date_to)
            ->where('end_date >=', $date_from)
            ->get(db_prefix() . 'hr_absences')->result_array();
    }
    // Fallback: rb_time_off
    return $this->get_time_off(['date_from' => $date_from, 'date_to' => $date_to]);
}

private function get_hr_holidays($date_from, $date_to)
{
    if ($this->db->table_exists(db_prefix() . 'hr_public_holidays')) {
        return $this->db->where('date >=', $date_from)
            ->where('date <=', $date_to)
            ->get(db_prefix() . 'hr_public_holidays')->result_array();
    }
    return [];
}
```

---

### Phase 3 — Frontend

**Schritt 3.1 — Lane-Packing-Algorithmus (JS)**

```javascript
function packAllocationsIntoLanes(allocations) {
    var lanes = [];
    allocations.forEach(function(alloc) {
        var placed = false;
        for (var i = 0; i < lanes.length; i++) {
            var lastInLane = lanes[i][lanes[i].length - 1];
            if (new Date(alloc.start_date) > new Date(lastInLane.end_date)) {
                lanes[i].push(alloc);
                placed = true;
                break;
            }
        }
        if (!placed) lanes.push([alloc]);
    });
    return lanes;
}
```

**Schritt 3.2 — Rendering: Dynamische Staff-Zeilengruppen**

- Jeder Staff bekommt einen Container `<div class="rb-staff-group">` mit:
  - Header-Zeile: Name + Auslastungsprozent + Toggle-Button
  - N Lanes (je Überlappungsebene eine `<div class="rb-lane">`)
- Task-Balken: kleinere Höhe (`height: 18px` vs. `24px`), andere Farbe/Stil
- Projektbalken: klickbar → klappt Tasks darunter auf

**Schritt 3.3 — Allocation-Dialog erweitern**

- Projekt auswählen → `date_from`/`date_to` auto-befüllen via AJAX (GET project)
- Task auswählen → analog, Tasks dropdown gefiltert nach Projekt
- `hours_per_day` bleibt manuell; bei Task-Auswahl mit `estimated_hours / working_days` vorschlagen
- "Als Follower hinzufügen" Checkbox

**Schritt 3.4 — Inline-Editing (nur Admin)**

- Doppelklick auf Stunden-Label → Inline-Input → speichert über `api_update_task_hours`
- Drag & Drop bleibt wie bisher, zusätzlich: Resize ändert Datumsbereich im Projekt wenn Allocation = Projektallocation

**Schritt 3.5 — Berechtigungs-Enforcement im JS**

```javascript
if (!config.canEdit) {
    // Interact.js nicht initialisieren
    // Edit-Buttons nicht rendern
    // Dialog nur lesend öffnen
}
```

---

### Phase 4 — Reports (Admin only)

- Reports-View (`views/reports.php`) mit `has_permission('resourcebooking', '', 'view')` + Admin-Check
- Auslastungs-Chart (Highcharts, bereits eingebunden): Staff × Zeitraum
- Export: CSV der Allocations
- Kapazitäts-Übersicht: Geplant h vs. Verfügbar h vs. Estimated Task-h

---

## Datenbankschema — Zusammenfassung der Änderungen

```sql
-- 1. Tasks: geschätzte Stunden (einzige Änderung an Perfex-Kerntabellen)
ALTER TABLE `tbltasks`
  ADD COLUMN `estimated_hours` DECIMAL(6,2) NULL DEFAULT NULL AFTER `duedate`;

-- 2. rb_allocations: Unique Key für UPSERT-Fähigkeit (Planungs-Overrides)
--    source_type und is_synced_member entfallen (nicht mehr nötig)
ALTER TABLE `tblrb_allocations`
  ADD UNIQUE KEY `uq_staff_project_task` (`staff_id`, `project_id`, `task_id`);

-- 3. rb_time_off bleibt für manuelle Sperrzeiten; Urlaub/Feiertage
--    werden NICHT hierhin geschrieben, sondern live aus bowhumanressources gelesen.
```

**Kein Zugriff auf Perfex-Kern außer:**
- `tbltasks.estimated_hours` — neues Feld
- `project_members` — lesen + schreiben (Board-Aktionen)
- `task_assigned` — lesen + schreiben (Board-Aktionen)
- `projects`, `tasks` — nur lesen
- `tblhr_absences`, `tblhr_public_holidays` — nur lesen (bowhumanressources)

---

## API-Endpunkte — Übersicht

| Methode | Endpoint | Beschreibung |
|---|---|---|
| GET | `api_board_data` | Live-Daten: Projekte, Tasks, HR-Abwesenheiten, Kapazität |
| GET/POST | `api_allocations` | CRUD Planungs-Overrides (h/d, Farbe, Notiz) |
| DELETE | `api_allocations/$id` | Override löschen (entfernt NICHT aus Perfex) |
| POST | `api_assign_member` | Mitarbeiter zu Projekt/Task hinzufügen → schreibt nach Perfex |
| POST | `api_remove_member` | Mitarbeiter aus Projekt/Task entfernen → schreibt nach Perfex |
| POST | `api_update_task_hours` | `estimated_hours` in `tbltasks` schreiben |
| POST | `api_upsert_override` | h/Tag, Farbe, Notiz in `rb_allocations` UPSERT |
| GET | `api_get_project` | Projektdaten inkl. `start_date`, `deadline` (für Auto-Datum) |
| GET | `api_get_tasks` | Tasks eines Projekts gefiltert nach Staff |

---

## Arbeitsreihenfolge (Empfehlung)

1. **DB-Migration** — `tbltasks.estimated_hours` + Unique Key auf `rb_allocations` (`install.php` + Auto-Migration)
2. **Hook für Task-Stunden** — `before_add_task` / `before_update_task` Hooks in `resourcebooking.php` registrieren
3. **HR-Integration im Model** — `get_hr_time_off()` + `get_hr_holidays()` mit Fallback implementieren
4. **Model: `get_board_data` umschreiben** — Live-Join über Perfex-Tabellen, Planungs-Overrides per LEFT JOIN
5. **Model: Write-Methoden** — `assign_staff_to_project/task`, `remove_staff_from_project/task`, `upsert_override`
6. **Controller-Endpunkte** — alle neuen API-Methoden implementieren, Perfex-Notification bei Überlastung
7. **JS: Lane-Packing** — Algorithmus + Rendering der Staff-Gruppen mit Lanes
8. **JS: Mitarbeiter-Ziele** — eigene Zeile highlighten, Ziel-Anzeige im Staff-Header
9. **JS: Allocation-Dialog** — Auto-Datum aus Projekt/Task, Task-Dropdown, Follower-Checkbox
10. **JS: Task-Balken** — Rendern, Hover-Tooltip, Inline-Edit Stunden, Überlastungsfarben
11. **Berechtigungs-Enforcement** — Admin/Mitarbeiter-Unterscheidung in JS + PHP, Mitarbeiter read-only
12. **Reports** — Admin-only Gate + Highcharts Auslastungs-Chart (Geplant h vs. Verfügbar h)
13. **Cleanup** — Sprach-Strings DE/EN vervollständigen, QA

---

## Entscheidungen (beschlossen)

- [x] **Allocation-Strategie:** Board liest live aus Perfex (`project_members`, `task_assigned`). `rb_allocations` speichert nur Planungs-Overrides (h/Tag, Farbe, Notiz). Keine redundante Datenhaltung.
- [x] **Mitarbeiter-Ansicht:** Mitarbeiter sehen das gesamte Board read-only. Eigene Zeile ist hervorgehoben. Persönliche Ziele (Projekte + Tasks + geplante h) werden im Staff-Header angezeigt.
- [x] **Überlastungs-Warnung:** Visuell (roter Balken) + Perfex-Notification für Mitarbeiter und Admin beim Speichern/Aktualisieren.
- [x] **Feiertage & Urlaub:** Werden live aus `bowhumanressources`-Modul gelesen (`tblhr_absences`, `tblhr_public_holidays`). Fallback auf `rb_time_off` wenn HR-Modul nicht installiert.
