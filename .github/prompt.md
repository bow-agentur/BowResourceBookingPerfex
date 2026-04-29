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

### 6. Bidirektionale Synchronisation — Planungstafel ↔ Perfex

**Soll:** Änderungen in der Planungstafel schreiben zurück nach Perfex:

| Aktion in Planungstafel | Aktion in Perfex |
|---|---|
| Neue Allocation für Projekt erstellt | Staff zu `project_members` hinzufügen (wenn nicht vorhanden) |
| Neue Allocation für Task erstellt | Staff zu `task_assigned` hinzufügen (wenn nicht vorhanden) |
| Allocation gelöscht | Staff aus `project_members` / `task_assigned` entfernen |
| Task-Stunden auf Balken bearbeitet | `tasks.estimated_hours` aktualisieren |
| Mitarbeiter als Follower gesetzt | `project_activity` / Follower-Tabelle aktualisieren |
| Mitarbeiter direkt über Board einem Projekt hinzugefügt | `project_members` INSERT |

**Umgekehrt (Perfex → Planungstafel):**
- `api_board_data` liest IMMER frisch aus Perfex-Tabellen: `project_members`, `task_assigned`
- Existing Allocations die keinen passenden `project_member`-Eintrag mehr haben → visuelle Warnung ("nicht mehr Mitglied")

**PHP in Model:**
```php
// Bei add_allocation:
$this->sync_to_perfex_on_create($allocation);

// Bei delete_allocation:
$this->sync_to_perfex_on_delete($id);
```

---

### 7. Berechtigungen

| Rolle | Planungstafel | Reports |
|---|---|---|
| Admin | Vollzugriff (lesen, erstellen, bearbeiten, löschen) | Vollzugriff |
| Mitarbeiter | Nur lesen (eigene Zeile sehen) | Kein Zugriff |

**Implementierung:**
- `has_permission('resourcebooking', '', 'create')` → Nur Admins
- Mitarbeiter-Ansicht: gefiltert auf `staffid = get_staff_user_id()`
- JS: `config.canEdit = false` für Mitarbeiter → Drag&Drop, Resize, Buttons deaktiviert
- Toolbar "Neue Zuweisung" Button nicht rendern wenn kein Create-Recht

---

## Implementierungsschritte

### Phase 1 — DB & Datenmodell

**Schritt 1.1 — Tasks: `estimated_hours` Feld**
- `install.php` erweitern: `ALTER TABLE tbltasks ADD estimated_hours ...`
- Auto-Migration in Model: prüfen ob Feld existiert, sonst hinzufügen
- Hooks registrieren: `add_action('before_add_task', ...)` und `add_action('before_update_task', ...)`
- Hook-Handler in `resourcebooking.php` oder separater `includes/task_hooks.php`

**Schritt 1.2 — Allocations: source_type & source_sync Flags**
- `rb_allocations` erweitern:
  ```sql
  ADD COLUMN `source_type` ENUM('manual','project_sync','task_sync') DEFAULT 'manual',
  ADD COLUMN `is_synced_member` TINYINT(1) DEFAULT 0
  ```

**Schritt 1.3 — API: Tasks in board_data**
- `get_board_data()` in Model: für jede Allocation Tasks des Projekts laden (gefiltert auf Staff)
- Task-Daten: `id, name, startdate, duedate, estimated_hours, status, daily_avg`

---

### Phase 2 — Backend-Logik

**Schritt 2.1 — Bidirektionale Sync-Methoden in `Rb_planning_model`**

```php
private function sync_to_perfex_on_create($allocation)
{
    if ($allocation['project_id']) {
        // project_members: INSERT IGNORE
        $exists = $this->db->where(['project_id' => $pid, 'staff_id' => $sid])
                           ->get(db_prefix() . 'project_members')->num_rows();
        if (!$exists) {
            $this->db->insert(db_prefix() . 'project_members', [
                'project_id' => $allocation['project_id'],
                'staff_id'   => $allocation['staff_id']
            ]);
        }
    }
    if ($allocation['task_id']) {
        // task_assigned: analog
    }
}

private function sync_to_perfex_on_delete($id)
{
    $allocation = $this->get_allocation($id);
    // Prüfen ob andere Allocations für gleichen Staff+Projekt noch existieren
    // Wenn nein: aus project_members/task_assigned entfernen
}
```

**Schritt 2.2 — Controller: Task-Stunden updaten via API**

```php
public function api_update_task_hours()
{
    // POST: task_id, estimated_hours
    // Schreibt zurück nach tbltasks
}
```

**Schritt 2.3 — Controller: Direkt Member hinzufügen**

```php
public function api_add_member()
{
    // POST: staff_id, project_id | task_id
    // Fügt zu project_members/task_assigned hinzu
    // Erstellt optional direkt eine Allocation
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
-- 1. Tasks: geschätzte Stunden
ALTER TABLE `tbltasks`
  ADD COLUMN `estimated_hours` DECIMAL(6,2) NULL DEFAULT NULL AFTER `duedate`;

-- 2. Allocations: Sync-Flags
ALTER TABLE `tblrb_allocations`
  ADD COLUMN `source_type` ENUM('manual','project_sync','task_sync') NOT NULL DEFAULT 'manual' AFTER `updated_at`,
  ADD COLUMN `is_synced_member` TINYINT(1) NOT NULL DEFAULT 0 AFTER `source_type`;
```

---

## API-Endpunkte — Übersicht

| Methode | Endpoint | Beschreibung |
|---|---|---|
| GET | `api_board_data` | Board-Daten inkl. Tasks und daily_avg |
| GET/POST | `api_allocations` | CRUD Allocations |
| PUT/DELETE | `api_allocations/$id` | Update/Delete + Perfex-Sync |
| POST | `api_add_member` | Mitarbeiter direkt zu Projekt/Task hinzufügen |
| POST | `api_update_task_hours` | `estimated_hours` in tbltasks schreiben |
| GET | `api_get_project` | Projektdaten (für Auto-Datum im Dialog) |
| GET | `api_get_tasks` | Tasks eines Projekts (für Task-Dropdown) |

---

## Arbeitsreihenfolge (Empfehlung)

1. **DB-Migration** — `estimated_hours` + Allocation-Flags (`install.php` + Auto-Migration)
2. **Hook für Task-Stunden** — Perfex-Integration, sodass Stunden im normalen Task-Formular gesetzt werden können
3. **Model erweitern** — `get_board_data` liefert Tasks; Sync-Methoden implementieren
4. **Controller-Endpunkte** — `api_add_member`, `api_update_task_hours`, `api_get_project`, `api_get_tasks`
5. **JS: Lane-Packing** — Algorithmus + Rendering der Staff-Gruppen mit Lanes
6. **JS: Allocation-Dialog** — Auto-Datum, Task-Dropdown, Follower-Checkbox
7. **JS: Task-Balken** — Rendern, Hover-Tooltip, Inline-Edit Stunden
8. **Berechtigungs-Enforcement** — Admin/Mitarbeiter-Unterscheidung im JS + PHP
9. **Reports** — Admin-only Gate + Highcharts Auslastungs-Chart
10. **Cleanup** — Sprach-Strings DE/EN vervollständigen, QA

---

## Offene Fragen / Entscheidungen

- [ ] Sollen Allocations die durch Sync entstehen (Mitglied schon im Projekt) **automatisch** angelegt werden beim Laden des Boards oder **nur** wenn der Nutzer explizit hinzufügt?
- [ ] Soll die Planungstafel für Mitarbeiter ihre eigene Zeile zeigen oder gar nicht zugänglich sein?
- [ ] Überstunden-Warnung (>8h/d): Nur visuell (Rot) oder auch als Notification?
- [ ] Feiertage: Aus `bowhumanressources`-Modul ziehen oder eigenständig im `rb_time_off` verwalten?
