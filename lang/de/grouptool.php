<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// If not, see <http://www.gnu.org/licenses/>.

/**
 * lang/de/grouptool.php
 * Strings for component 'mod_grouptool', language 'de'
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Aktiv';
$string['add_member'] = 'Füge {$a->username} zur Gruppe {$a->groupname} hinzu';
$string['added_member'] = '{$a->username} zur Gruppe {$a->groupname} hinzugefügt';
$string['administration'] = 'Administration';
$string['administration_alt'] = 'Gruppen und Gruppierungen erstellen, sowie Einstellungen, für die in dieser Instanz aktiven Gruppen, ändern';
$string['agroups'] = 'Aktive Gruppen';
$string['all_groups_full'] = 'Nutzer/in mit der ID {$a} kann nicht in Gruppe eingetragen werden, da alle Gruppen voll sind!';
$string['allowed'] = 'Erlaubt';
$string['allow_multiple'] = 'Multiple Anmeldungen zulassen';
$string['allow_multiple_help'] = 'Ermöglicht es Studenten, in mehr als 1 Gruppe zur gleichen Zeit angemeldet zu sein. Sie müssen spezifizieren wie viele Gruppen zumindest und maximal gewählt werden müssen.';
$string['allow_reg'] = 'Selbstanmeldung zulassen';
$string['allow_reg_help'] = 'Ermöglicht es Studenten sich selbst zu einer (oder mehrerer) der unten aktivierten Gruppen anzumelden.';
$string['allow_unreg'] = 'Abmeldung zulassen';
$string['allow_unreg_help'] = 'Ermöglicht Studenten sich von Gruppen ab- bzw. zu anderen Gruppen umzumelden, solange sie sich innerhalb eines (optionalen) Zeitlimits befinden.';
$string['already_marked'] = 'Diese Gruppe wurde bereits zur Anmeldung markiert!';
$string['already_member'] = '{$a->username} ist bereits Mitglied von Gruppe {$a->groupname}';
$string['already_occupied'] = 'Der Gruppenplatz in Gruppe {$a->grpname} wurde bereits an einen Benutzer vergeben, der die Anmeldung schneller durchführte. Bitte wählen Sie eine andere Gruppe aus!';
$string['already_queued'] = '{$a->username} ist bereits in der Warteliste von Gruppe {$a->groupname}!';
$string['already_registered'] = '{$a->username} ist bereits in Gruppe {$a->groupname} registriert!';
$string['alwaysshowdescription'] = 'Beschreibung immer anzeigen';
$string['alwaysshowdescription_help'] = 'Wenn deaktiviert, wird die Beschreibung nur während des Anmeldezeitraums angezeigt.';
$string['you_are_already_queued'] = 'Sie sind bereits in der Warteliste von Gruppe {$a->groupname}!';
$string['you_are_already_registered'] = 'Sie sind bereits in der Gruppe {$a->groupname} registriert!';
$string['asterisk_marks_moodle_registrations'] = 'Benutzer mit führendem Asterisk/Stern sind bereits in den entsprechenden Moodle-Gruppen angemeldet.';
$string['availabledate'] = 'Anmeldebeginn';
$string['availabledate_help'] = 'Beginn des Anmeldezeitraums. Nach diesem Datum ist es Studenten möglich sich selbst zu den ausgewählten Gruppen anzumelden (falls dies zugelassen ist).';
$string['availabledateno'] = 'Immer verfügbar';
$string['cant_enrol'] = 'Kann Nutzer nicht automatisch in Kurs einschreiben.';
$string['cfg_addinstanceset_head'] = 'Weitere Instanzeinstellungen';
$string['cfg_addinstanceset_head_info'] = 'Weitere Instanzeinstellungen für die Gruppenverwaltung.';
$string['cfg_admin_head'] = 'Standardeinstellungen für die Administration';
$string['cfg_admin_head_info'] = 'Standardeinstellungen für den Administrationstab der Gruppenverwaltung.';
$string['cfg_instance_head'] = 'Standard-Instanz-Einstellungen';
$string['cfg_instance_head_info'] = 'Standardeinstellungen für neue Gruppenverwaltungsinstanzen.';
$string['cfg_allow_multiple'] = 'Mehrfache Anmeldungen';
$string['cfg_allow_multiple_desc'] = 'Ermöglicht Studenten sich standardmäßig in mehr als 1 Gruppe zur gleichen Zeit zu befinden.';
$string['cfg_allow_reg'] = 'Selbstanmeldung zulassen';
$string['cfg_allow_reg_desc'] = 'Ermöglicht es Studenten standardmäßig sich selbst anzumelden.';
$string['cfg_allow_unreg'] = 'Abmeldung zulassen';
$string['cfg_allow_unreg_desc'] = 'Erlaubt es Studenten standardmäßig sich selbst, innerhalb der Deadline, von Gruppen ab- und zu anderen Gruppen umzumelden.';
$string['cfg_choose_max'] = 'Maximalanzahl zu wählender Gruppen';
$string['cfg_choose_max_desc'] = 'In wie viele Gruppen sollen Studierende standardmäßig maximal zeitgleich angemeldet sein dürfen?';
$string['cfg_choose_min'] = 'Mindestanzahl zu wählender Gruppen';
$string['cfg_choose_min_desc'] = 'In wie vielen Gruppen sollen Studierende standardmäßig minimal angemeldet sein?';
$string['cfg_force_importreg'] = 'Erzwinge Anmeldung in der Gruppenverwaltung';
$string['cfg_force_importreg_desc'] = 'Erzwing die Anmeldung innerhalb der Gruppenverwaltungsinstanz für Benutzer, die via Gruppenverwaltung in Moodle-Gruppen importiert wurden.';
$string['cfg_grpsize'] = 'Allgemeine Standardgruppengröße';
$string['cfg_grpsize_desc'] = 'Standardgruppengröße, die überall in der Gruppenverwaltung verwendet wird';
$string['cfg_ifgroupdeleted'] = 'Bei Gruppenlöschung';
$string['cfg_ifgroupdeleted_desc'] = 'Sollen in Moodle gelöschte Gruppen standardmäßig für die jeweiligen Instanzen wiederhergestellt oder aus den jeweiligen Instanzen (inkl. aller Anmeldungen & Wartelisten in der Gruppenverwaltung, etc.) entfernt werden? Man beachte: Wenn „Gruppe erneut erstellen“ ausgewählt wurde, werden die Gruppen unmittelbar nach dem Löschvorgang unter „Kurs-Administration / NutzerInnen / Gruppe“ wiederhergestellt.';
$string['cfg_ifmemberadded'] = 'Bei hinzugefügtem Gruppenmitglied';
$string['cfg_ifmemberadded_desc'] = 'Sollen neue Gruppenmitglieder standardmäßig auch in der Gruppenverwaltung hinzugefügt oder ignoriert werden?';
$string['cfg_ifmemberremoved'] = 'Bei gelöschtem Gruppenmitglied';
$string['cfg_ifmemberremoved_desc'] = 'Sollen Anmeldungen entfernter Gruppenmitglieder innerhalb der Gruppenverwaltungen standardmäßig gelöscht oder ignoriert werden?';
$string['cfg_immediate_reg'] = 'Sofortige Anmeldung';
$string['cfg_immediate_reg_desc'] = 'Soll jede Anmeldung automatisch zu den Moodle-Gruppen durchgereicht werden?';
$string['cfg_importfields'] = 'Vergleichsfelder für Import';
$string['cfg_importfields_desc'] = 'Gibt an mit welchen Feldern der Benutzer-Tabelle beim Import verglichen werden soll. Die Felder werden sequenziell durchsucht, bis möglichst genau ein einziger Treffer gefunden wurde. Mögliche/Sinnvolle Werte sind z.B.: username, idnumber, email. ACHTUNG: es erfolgt keine Kontrolle auf richtige Schreibweise. Erlaubte Zeichen: a-z, A-Z, \',\'';
$string['cfg_max_queues'] = 'Maximale gleichzeitige Wartelistenplätze';
$string['cfg_max_queues_desc'] = 'Gibt an in wie vielen Gruppen ein Nutzer standardmäßig zeitgleich in der Warteliste gereiht sein darf.';
$string['cfg_moodlesync_head'] = 'Synchronisationsverhalten';
$string['cfg_moodlesync_head_info'] = 'Wie sich die Gruppenverwaltungsinstanz bei hinzugefügten/gelöschten Mitgliedern/Gruppen in Moodle verhalten soll';
$string['cfg_name_scheme'] = 'Standard-Namensschema';
$string['cfg_name_scheme_desc'] = 'Standard-Namensschema für Gruppenerzeugung';
$string['cfg_show_members'] = 'Zeige Gruppenmitglieder';
$string['cfg_show_members_desc'] = 'Gibt an ob Gruppenmitglieder standardmäßig angezeigt werden sollen';
$string['cfg_use_individual'] = 'Unterschiedliche Gruppengrößen festlegen';
$string['cfg_use_individual_desc'] = 'Gibt an, ob standardmäßig eine individuelle Größe pro Gruppe definiert werden soll.';
$string['cfg_use_queue'] = 'Wartelisten verwenden';
$string['cfg_use_queue_desc'] = 'Gibt an, ob standardmäßig Wartelisten bei überfüllten Gruppen verwendet werden sollen';
$string['cfg_use_size'] = 'Begrenze Gruppengrößen';
$string['cfg_use_size_desc'] = 'Gibt an, ob standardmäßig nur begrenzte Plätze pro Gruppe verfügbar sein sollen.';
$string['change_group'] = 'Gruppe wechseln';
$string['change_group_to'] = 'Soll mit Gruppenwechsel zu {$a->groupname} fortgefahren werden?';
$string['change_group_to_success'] = 'Gruppenwechsel erfolgreich! {$a->username} ist nun in der Gruppe {$a->groupname} registriert!';
$string['you_change_group_to_success'] = 'Ihr Gruppenwechsel war erfolgreich! Sie sind nun in der Gruppe {$a->groupname} registriert!';
$string['checkbox_control_header'] = 'Gruppen/Gruppierungen auswählen';
$string['checkbox_control_header_help'] = '<p>Mit dieser Funktion können Sie gezielt Gruppen aus einer/ mehreren Gruppierung(en) in Ihrer Gruppenverwaltung aktivieren oder deaktivieren:
<ol>
    <li>Wählen Sie im Auswahlfeld zwischen den Varianten "Alle Gruppen" (d.h. es werden alle enthaltenen Gruppen aktiviert/deaktiviert ) bzw. eine Gruppierung oder wahlweise mehrere Gruppierungen (mit Strg + Klick).</li>
    <li>Nutzen Sie Sie eine der drei Optionen "Selektieren/ Deselektieren/ Invertieren":
        <ul>
            <li><b>Selektieren:</b> Die Gruppe(n) der ausgewählten Gruppierung(en) aus dem Auswahlfeld wird/ werden aktiviert.</li>
            <li><b>Deselektieren:</b> Die Gruppe(n) der ausgewählten Gruppierung(en) aus dem Auswahlfeld wird/ werden deaktiviert. </li>
            <li><b>Invertieren:</b> Es werden alle Gruppen der nicht ausgewählten Gruppierung(en) aus dem Auswahlfeld markiert. </li>
        </ul>
    </li>
    <li>Übernehmen Sie Ihre Auswahl über Klick auf die Schaltfläche "Start".</li>
    <li>Speichern Sie über "Änderungen speichern".</li>
</ol></p>';
$string['choose_group'] = 'Sie müssen eine Zielgruppe auswählen!';
$string['choose_max'] = 'Maximalanzahl zu wählender Gruppen';
$string['choose_min'] = 'Mindestanzahl zu wählender Gruppen';
$string['choose_minmax_title'] = 'Gruppenanzahl';
$string['choose_min_text'] = 'Sie müssen mindestens <span style="font-weight:bold;">{$a}</span> Gruppe(n) auswählen!';
$string['choose_max_text'] = 'Sie dürfen nicht mehr als <span style="font-weight:bold;">{$a}</span> Gruppe(n) auswählen!';
$string['choose_min_max_text'] = 'Sie müssen zwischen <span style="font-weight:bold;">{$a->min}</span> und <span style="font-weight:bold;">{$a->max}</span> Gruppen auswählen!';
$string['choose_targetgroup'] = 'Import in Gruppe';
$string['chooseactivity'] = 'Sie müssen eine Aktivität auswählen, bevor Daten angezeigt werden können!';
$string['create_1_person_groups'] = '1-Personen-Gruppen erstellen';
$string['create_fromto_groups'] = 'Gruppen von X bis Y erstellen (z.B. von 34 bis 89).';
$string['createGroups'] = 'Gruppen erstellen';
$string['createGroupings'] = 'Gruppierungen erstellen/zuweisen';
$string['create_assign_groupings'] = 'Gruppierungen erstellen/zuweisen';
$string['create_groups_confirm'] = 'Gruppen erstellen, wie in der Vorschau gezeigt?';
$string['create_groups_confirm_problem'] = 'Beim Versuch die neuen Gruppen anhand des vorgegebenen Namenschemas anzulegen sind Konflikte aufgetreten - siehe Vorschau - Moodle Gruppen müssen eineindeutige Namen haben. Der Konflikt kann an bereits bestehenden Gruppen mit gleichem Namen oder einem Syntaxfehler im Namensschema (z.B. leer, fehlendes #-Symbol, ...) liegen.';
$string['create_groupings_confirm'] = 'Gruppierungen erstellen, wie in der Vorschau gezeigt?';
$string['create_groupings_confirm_problem'] = 'Zumindest 1 Fehler ist aufgetreten (siehe Vorschau)!';
$string['condition_prevent_access'] = 'Die derzeitigen Umstände erlauben Ihnen keinen Zugriff auf die Kreuzerlübung!';
$string['copied_grade_feedback'] = 'Gruppenbenotung<br />
+Abgabe von: {$a->student}<br />
+Note von: {$a->teacher}<br />
+Original Datum/Zeit: {$a->date}<br />
+Feedback: {$a->feedback}';
$string['copy'] = 'Übertragen';
$string['copy_chosen'] = 'Übertrage Gewählte';
$string['copygrade'] = 'Bewertung kopieren';
$string['copy_refgrades_feedback'] = 'Übertrage Referenzbewertungen und Feedback der gewählten Gruppen auf andere Gruppenmitglieder';
$string['copy_grade_confirm'] = 'Sind Sie sich wirklich SICHER?';
$string['copy_grade_overwrite_confirm'] = 'Sind Sie sich wirklich SICHER? Existierende Bewertungen werden überschrieben!';
$string['copy_grades_confirm'] = 'Sind Sie sich wirklich SICHER?';
$string['copy_grades_overwrite_confirm'] = 'Sind Sie sich wirklich SICHER? Existierende Bewertungen werden überschrieben!';
$string['copy_grades_success'] = 'Die folgenden Bewertungen wurden erfolgreich aktualisiert:';
$string['copy_grades_errors'] = 'Zumindest 1 Fehler trat während des Übertragens der Bewertungen auf:';
$string['could_not_add'] = 'Konnte {$a->username} nicht zu Gruppe {$a->groupname} hinzufügen!';
$string['define_amount_groups'] = 'Gruppenanzahl festlegen';
$string['define_amount_members'] = 'Mitgliederanzahl festlegen';
$string['delete_reference'] = 'Lösche aus der Gruppenverwaltung';
$string['description'] = 'Beschreibung';
$string['deselect'] = 'Deselektieren';
$string['determinismerror'] = 'Das Anmeldeende, darf nicht vor dem Anmeldebeginn oder in der Vergangenheit liegen!';
$string['digits'] = 'Mindestanzahl Stellen';
$string['disabled'] = 'Deaktiviert';
$string['drag'] = 'Verschiebe';
$string['due'] = 'Gruppenverwaltung Deadline';
$string['duedate'] = 'Anmeldeende';
$string['duedate_help'] = 'Ende des Anmeldezeitraums. Nach diesem Datum können sich Studierende nicht mehr selbstständig anmelden und Lehrende erhalten u.a. Zugriff auf die Warteschlangenauflösung.';
$string['duedateno'] = 'Keine Deadline';
$string['early'] = '{$a} rechtzeitig';
$string['error_at'] = 'Fehler bei';
$string['error_getting_data'] = 'Fehler beim Auslesen der Gruppendaten! Entweder keine oder mehrere Gruppen wurden zurückgegeben!';
$string['eventagrpcreated'] = 'Aktive Gruppe erstellt';
$string['eventagrpdeleted'] = 'Aktive Gruppe gelöscht';
$string['eventagrpsupdated'] = 'Aktive Gruppen aktualisiert';
$string['eventdequeuingstarted'] = 'Warteschlangenauflösung gestartet';
$string['eventgroupcreationstarted'] = 'Gruppenerstellung gestartet';
$string['eventgroupgraded'] = 'Gruppe benotet';
$string['eventgrouprecreated'] = 'Gruppe wiedererstellt';
$string['eventgroupingscreated'] = 'Gruppierungen erstellt';
$string['eventoverviewexported'] = 'Exportierte Überblick';
$string['eventqueueentrycreated'] = 'Warteschlangeneintrag erstellt';
$string['eventqueueentrydeleted'] = 'Warteschlangeneintrag gelöscht';
$string['eventregistrationcreated'] = 'Anmeldung erstellt';
$string['eventregistrationdeleted'] = 'Anmeldung gelöscht';
$string['eventregistrationpushstarted'] = 'Registierungsübertragung gestartet';
$string['eventuserimported'] = 'Nutzer importiert';
$string['eventusermoved'] = 'Nutzer verschoben';
$string['eventuserlistexported'] = 'Exportierte Teilnehmer/innenliste';
$string['userlist'] = 'Teilnehmer/innenliste';
$string['userlist_alt'] = 'Zeige Liste aller Teilnehmer/innen und deren Anmeldungen. Exportiere Daten über Nutzer und deren Gruppen in verschiedene Formate (PDF, plain text, Excel, etc.).';
$string['feedbackplural'] = 'Rückmeldungen';
$string['filters_legend'] = 'Filtere Daten';
$string['followchanges'] = 'Folge Änderungen';
$string['forceregistration'] = 'Anmeldung in Gruppenverwaltung erzwingen';
$string['forceregistration_help'] = 'Beachten Sie, dass die Gruppen der Gruppenverwaltung sich grundlegend von den Moodle Standardgruppen des Kurses unterscheiden. Aktivieren Sie das Häckchen, wenn Sie TeilnehmerInnen sowohl in die Moodle Standardgruppe als auch in die ausgewählte Gruppe der Gruppenverwaltung importieren möchten.';
$string['found_multiple'] = 'Kann Nutzer/in nicht einwandfrei identifizieren, mehrere Treffer:';
$string['free'] = 'Frei';
$string['fromgttoerror'] = 'Bis-Wert muss größer oder gleich dem Von-Wert sein';
$string['fullgroup'] = 'Gruppe ist voll';
$string['general_information'] = 'Allgemeine Informationen';
$string['global_userstats'] = '{$a->reg_users} von {$a->users} Nutzern sind angemeldet. {$a->notreg_users} (noch) ohne Anmeldung.';
$string['grading'] = 'Bewertung';
$string['grading_activity_title'] = 'Aktivität';
$string['grading_alt'] = 'Werkzeuge zum Kopieren von Bewertungen von 1 Gruppenmitglied zu (allen) anderen Gruppenmitgliedern entweder für 1 oder mehrere Gruppen.';
$string['grading_filter_select_title'] = 'Gruppe(n)';
$string['grading_filter_select_title_help'] = 'Auswählen welche Gruppe bzw. Gruppen bearbeitet werden:<ul><li>konfliktfrei - Alle Gruppen, bei denen nur 1 Gruppenmitglied eine Bewertung für die ausgewählte Aktivität erhalten hat.</li><li>Alle - Alle Gruppen</li><li>"Gruppenname" - nur die jeweils ausgewählte Gruppe</li></ul>';
$string['grading_grouping_select_title'] = 'Nach Gruppierung filtern';
$string['group_administration'] = 'Gruppen verwalten';
$string['group_administration_alt'] = '(aktive) Gruppen und Gruppierungen verwalten';
$string['group_assign_error_prev'] = 'Kann nicht zu Gruppe hinzufügen!';
$string['group_assign_error'] = 'Kann nicht zu Gruppe hinzufügen!';
$string['group_creation'] = 'Gruppen erstellen';
$string['group_creation_alt'] = 'Gruppen erstellen';
$string['group_creation_failed'] = 'Gruppenerstellung fehlgeschlagen!';
$string['groupcreation'] = 'Gruppen erstellen';
$string['groupcreationmode'] = 'Modus';
$string['groupcreationmode_help'] = 'Auswahl, wie Gruppen erstellt werden sollen:<br />
<ul>
<li>Definiere Gruppenanzahl - Es wird ausgewählt aus welcher Rolle Benutzer/innen für die Gruppenerzeugung benutzt werden sollen und die gewünschte Anzahl an Gruppen im Textfeld angegeben. Im Namenschema wird das Schema für die Gruppennamen angegeben. Dabei können folgende Tags verwendet werden:
<ul>
<li># (wird durch die Gruppennummer ersetzt)</li>
<li>@ (wird durch eine Buchstabenrepräsentation der Gruppennummer ersetzt)</li>
</ul>
Die betroffenen Benutzer/innen werden automatisch auf die angegebene Anzahl an Gruppen verteilt.</li>
<li>
Definiere Anzahl an Gruppenmitgliedern - Hier werden die ideale Gruppenmitgliedsanzahl sowie Namensschema und Rolle für die Nutzerauswahl angegeben. Die benötigte Gruppenanzahl wird automatisch errechnet. Wahlweise können kleine Gruppen (entspricht weniger als 70 % Füllung) auf die anderen Gruppen aufgeteilt werden.
</li>
<li>
1-Personen-Gruppen erzeugen - Hier wird für jeden ausgewählten Benutzer eine Gruppe erzeugt. Zusätzlich zu # und @ können Sie hier folgende Tags für das Namensschema benutzen:
<ul>
<li>[username] - der Benutzername</li>
<li>[firstname] - der Vorname</li>
<li>[lastname] - der Familienname</li>
<li>[idnumber] - die ID-Nummer</li>
</ul>
Wenn Daten für einen Benutzer nicht vorhanden sind, wird der Tag durch TagnameXX ersetzt (wobei XX für die laufende Gruppennummer steht).
</li>
<li>
Erzeuge Gruppen in einem bestimmten Intervall (z.B. von 34 bis 89) - Mit diesem Modus ist es möglich (fehlende) Gruppen nachträglich zu erzeugen (z.B. Gruppe 4, Gruppe 5, Gruppe 6). Hierfür müssen einfach die Grenzen (von-bis) eingetragen werden und angegeben werden wie viele Stellen für kleine Zahlen genutzt werden sollen (werden mit führenden Nullen aufgefüllt, z.B. 1, 01, 001 oder 0001...).
</li>
</ul>';
$string['groupfromtodigits'] = 'Von, Bis &amp; Stellen im Namen:';
$string['groupinfo'] = 'Gruppeninformationen';
$string['grouping_assign_success'] = 'Wurden erfolgreich hinzugefügt:';
$string['grouping_assign_success_prev'] = 'Können erfolgreich hinzugefügt werden:';
$string['grouping_assign_error'] = 'Konnten nicht zur Gruppierung hinzugefügt werden:';
$string['grouping_assign_error_prev'] = 'Können nicht zur Gruppierung hinzugefügt werden:';
$string['grouping_exists_error_prev'] = 'Kann Gruppierung nicht anlegen, da bereits eine Gruppierung mit diesem Namen existiert!';
$string['grouping_exists_error'] = 'Konnte Gruppierung nicht anlegen, da bereits eine Gruppierung mit diesem Namen existiert!';
$string['grouping_creation_success'] = 'Gruppierung erfolgreich angelegt und Gruppe {$a} hinzugefügt!';
$string['grouping_creation_success_prev'] = 'Gruppierung kann erfolgreich angelegt und Gruppe {$a} hinzugefügt werden!';
$string['grouping_creation_error_prev'] = 'Kann Gruppierung nicht anlegen!';
$string['grouping_creation_error'] = 'Konnte Gruppierung nicht anlegen!';
$string['grouping_creation_only_success'] = 'Gruppierung erfolgreich angelegt!';
$string['grouping_creation_only_success_prev'] = 'Gruppierung kann erfolgreich angelegt werden!';
$string['groupingscreation'] = 'Gruppierung(en) erstellen/zuweisen';
$string['groupingselect'] = 'Gruppierung für ausgewählte Gruppen';
$string['groupingselect_help'] = 'Gruppierungen für ausgewählte Gruppen erstellen:<ul>
<li>für ausgewählte Gruppen EINE neue gemeinsame Gruppierung anlegen. Name der Gruppierung kann selbst gewählt werden.</li>
<li>für jede ausgewählte Gruppe eine Gruppierung PRO Gruppe anlegen. Name der Gruppierung ist gleich der Name der Gruppe.</li>
<li>ausgewählte Gruppen in eine bestehende Gruppierung hinzufügen.</li></ul>';
$string['group_places'] = 'Gruppenplätze';
$string['group_places_help'] = 'Das Feld "Gruppenplätze" informiert (durch Schrägstrich getrennt) erstens über die Anzahl der gesamt verfügbaren Plätze, zweitens über die Anzahl der freien Gruppenplätze sowie drittens über die Anzahl jener Plätze, die bereits zum Zeitpunkt des Seitenaufrufs belegt waren.';
$string['groupoverview'] = 'Gruppenübersicht';
$string['groupselection'] = 'Gruppenauswahl';
$string['groupselection_help'] = 'Wählen Sie durch Selektieren der jeweiligen Kontrollkästchen jene Gruppen aus, für welche Sie die Übertragung der Referenzbewertungen sowie der Feedbacks durchführen möchten. Im Falle, dass nur 1 Gruppe angezeigt wird, bestimmen Sie die Quelle für den Kopiervorgang durch Auswahl eines der rechts angezeigten Buttons.';
$string['groupsize'] = 'Gruppengröße';
$string['groups_created'] = 'Gruppen erfolgreich erstellt!';
$string['grouptool'] = 'Gruppenverwaltung';
$string['grouptoolfieldset'] = 'Instanzeinstellungen';
$string['grouptoolname'] = 'Name der Gruppenverwaltung';
$string['grouptoolname_help'] = 'Der Name der zu erstellenden/bearbeitenden Gruppenverwaltungs-Instanz';
$string['grouptool:addinstance'] = 'Erstelle eine Gruppenverwaltungsinstanz im Kurs';
$string['grouptool:administrate_groups'] = 'Verwalte (aktive) Gruppen und Gruppierungen';
$string['grouptool:create_groupings'] = 'Erstelle Gruppierungen mit Hilfe der Gruppenverwaltung.';
$string['grouptool:create_groups'] = 'Erstelle Gruppen mit Hilfe der Gruppenverwaltung';
$string['grouptool:export'] = 'Exportiere Gruppen und Anmeldungen zu verschiedenen Formaten';
$string['grouptool:grade'] = 'Kopiere Bewertungen von einem Gruppenmitglied auf andere';
$string['grouptool:grade_own_group'] = 'Kopiere Bewertungen von einem Gruppenmitglied auf andere, sofern die ursprüngliche Bewertung von mir stammt.';
$string['grouptool:register'] = 'Selbstanmeldung in aktiver Gruppe mit Hilfe der Gruppenverwaltung';
$string['grouptool:register_students'] = 'Melde andere Benutzer in aktiven Gruppen unter Zuhilfenahme der Gruppenverwaltung an. (Wird auch zum Auflösen von Wartelisten benötigt)';
$string['grouptool:move_students'] = 'Verschiebe Studenten in andere Gruppen.';
$string['grouptool:view_description'] = 'Zeige Gruppenverwaltungsbeschreibung';
$string['grouptool:view_groups'] = 'Zeige aktive Gruppen';
$string['grouptool:view_regs_group_overview'] = 'Zeige eine nach Gruppen gegliederte Liste, wer in welcher aktiven Gruppe angemeldet/in der Warteliste gereiht ist.';
$string['grouptool:view_regs_course_overview'] = 'Zeige eine Nutzerliste mit der Information, wer in welcher aktiven Gruppe angemeldet/in der Warteliste gereiht ist.';
$string['grouptool:view_own_registration'] = 'Zeige eigene Registrierung(en) an';
$string['groupuser_import'] = 'Importiere Gruppenmitglieder';
$string['group_not_in_grouping'] = 'Gewählte Gruppe ist nicht in gewählter Gruppierung!';
$string['group_or_member_count'] = 'Gruppen- bzw. Mitgliederanzahl';
$string['grp_marked'] = 'Zur Anmeldung markiert';
$string['grpsizezeroerror'] = 'Gruppengröße muss größer gleich 1 sein (positive Integerzahl)';
$string['ifgroupdeleted'] = 'Wenn Kursgruppen gelöscht werden';
$string['ifgroupdeleted_help'] = 'Sollen gelöschte Kursgruppen für die Gruppenverwaltung wiederhergestellt oder alle Referenzen (aktive Gruppe/Anmeldungen/Warteliste) gelöscht werden? Man beachte: Wenn „Gruppe erneut erstellen“ ausgewählt wurde, werden die Gruppen unmittelbar nach dem Löschvorgang unter „Kurs-Administration / NutzerInnen / Gruppe“ wiederhergestellt.';
$string['ifmemberadded'] = 'Wenn Mitglieder hinzugefügt werden';
$string['ifmemberadded_help'] = 'Sollen neue Gruppenmitglieder in die aktive Gruppe der Gruppenverwaltung übernommen oder ignoriert werden?';
$string['ifmemberremoved'] = 'Wenn Mitglieder gelöscht werden';
$string['ifmemberremoved_help'] = 'Sollen die Anmeldungen in den aktiven Gruppen gelöscht oder die Änderung ignoriert werden?';
$string['ignorechanges'] = 'Änderungen ignorieren';
$string['ignored_not_found_users'] = 'Zumindest 1 Benutzer konnte nicht zur Gruppe hinzugefügt werden!';
$string['ignoring_not_found_users'] = 'Zumindest 1 Benutzer kann nicht gefunden werden! Alle nicht gefundenen Benutzer werden ignoriert!';
$string['immediate_reg'] = 'Sofortige Anmeldungen';
$string['immediate_reg_help'] = 'Wenn aktiviert, werden An-/Abmeldungen sofort in die Moodle-Gruppen übernommen. Wenn nicht aktiviert, können die Anmeldungen per Knopfdruck in die Moodle-Gruppen übernommen werden!';
$string['import'] = 'Import';
$string['importbutton'] = 'Benutzer/innen hinzufügen';
$string['import_desc'] = 'Importiere Benutzer per Liste von ID-Nummern in bestimmte Gruppe.';
$string['import_in_inactive_group_warning'] = 'Achtung: Die Gruppe "{$a}" ist derzeit in der Gruppenverwaltung inaktiv und wird deshalb nicht angezeigt. Der Import erfolgt nur in die Moodle-Gruppe. In dieser Grouptool-Instanz erfolgt keine Anmeldung!';
$string['import_in_inactive_group_rejected'] = 'Die Anmeldung im Grouptool wurde für die inaktive Gruppe "{$a}" zurückgewiesen. Aktivieren Sie die Gruppe im Grouptool um die Anmeldung zu ermöglichen.';
$string['import_user'] = 'Importieren von {$a->fullname} ({$a->idnumber}) in Gruppe {$a->groupname} erfolgreich.';
$string['import_user_prev'] = 'Importiere {$a->fullname} ({$a->idnumber}) in Gruppe {$a->groupname}.';
$string['import_user_problem'] = 'Fehler beim Importieren von {$a->fullname} (ID-Nummer: {$a->idnumber}) in Gruppe {$a->groupname}.';
$string['includedeleted'] = 'Include deleted users';
$string['includedeleted_help'] = 'If checked, deleted users won\'t get filtered out of the list. Deleted user-accounts can\'t be enroled in the course during import process.';
$string['incomplete_only_label'] = 'Zeige nur Gruppen mit fehlenden Bewertungen';
$string['intro'] = 'Beschreibung';
$string['invert'] = 'Invertieren';
$string['landscape'] = 'Querformat';
$string['late'] = '{$a} zu spät';
$string['loading'] = 'Lade...';
$string['maxmembers'] = 'Globale Gruppengröße';
$string['max_queues_reached'] = 'Maximale Wartelistenplätze erreicht!';
$string['max_regs_reached'] = 'Maximale Anmeldungen erreicht!';
$string['messageprovider:grouptool_moveupreg'] = 'Anmeldung durch Nachrücken in der Warteschlange';
$string['missing_source_selection'] = 'Keine Quelle ausgewählt!';
$string['modulename'] = 'Gruppenverwaltung';
$string['modulenameplural'] = 'Gruppenverwaltungen';
$string['modulename_help'] = 'Die Gruppenverwaltung umfasst mehrere Aufgabenbereiche in Verbindung mit Gruppen:<ul><li>Sie erlaubt es Gruppen auf verschiedene Art und Weise (Angabe von Anzahl an Gruppen/Gruppenmitgliedern, 1-Personen-Gruppen) sowie Gruppierungen für jede Kursgruppe zu erzeugen.</li><li>Weiters kann sie benutzt werden um es Studierenden zu ermöglichen sich selbst innerhalb eines gewissen Zeitraumes zu Gruppen anzumelden.</li><li>Mit ihrere Hilfe lassen sich Gruppenbenotungen durchführen, d.h. eine Aktivitätsbenotung von einem Studierenden auf andere Gruppenmitglieder zu übertragen.</li><li>Es ist auch möglich Gruppen schnell zu befüllen, indem Nutzer/innen mittels Liste mit Matrikelnummern in eine bestimmmte Gruppe importiert werden.</li><li>Überblick über alle Gruppen sowie deren Anmeldungen/Wartelisten/etc in verschiedene Formate (PDF/XLSX/ODS/TXT) exportierbar.</li><li>Exportierbare Liste aller im Kurs eingeschriebener Benutzer/innen mit ihren Gruppenanmeldungen, Wartelistenplätzen, etc (ebenfalls exportierbar).</li></ul><p>(!) Beachten Sie, dass die Gruppen der Gruppenverwaltung sich grundlegend von den Moodle Standardgruppen des Kurses unterscheiden. Um Konsistenz zwischen den Standardgruppen und den Gruppenverwaltungsgruppen zu bewahren, stellen Sie alle Parameter unter dem Abschnitt „Verhalten bei Änderungen in Moodle-Gruppen“ mit Hilfe des Drop Down Menüs auf „Folge Änderungen“ ein.</p>';
$string['moodlesync'] = 'Verhalten bei Änderungen in Moodle-Gruppen';
$string['moodlesync_help'] = 'Wie sich die Gruppenverwaltung verhalten soll, wenn Gruppenmitglieder/Gruppen in Moodle hinzugefügt/entfernt werden';
$string['movedown'] = 'Nach unten verschieben';
$string['moveup'] = 'Nach oben verschieben';
$string['must_specify_groupingname'] = 'Sie müssen einen Namen für die Gruppierung angeben!';
$string['mustbegt0'] = 'Muss ganzzahlig und größer oder gleich 0 sein (>= 0)';
$string['mustbegtoeqmin'] = 'Muss größer als das oder gleich dem Minimum sein!';
$string['mustbeposint'] = 'Muss eine positive ganze Zahl sein (>= 1)';
$string['mygroups_only_label'] = 'Zeige nur Quellen, die ich bewertet habe';
$string['name_scheme_tags'] = '<span class="tag firstname">[firstname]</span>
<span class="tag lastname">[lastname]</span>
<span class="tag idnumber">[idnumber]</span>
<span class="tag username">[username]</span>
<span class="tag alpha">@</span>
<span class="tag number">#</span>';
$string['nameschemenotunique'] = 'Gruppennamen, die aus diesem Namensschema erzeugt werden, sind nicht einzigartig ({$a}). Bitte wählen sie ein anderes Namensschema oder benutzen sie # (laufende Nummer) oder @ (alphabetische Repräsentation) um eindeutige Gruppennamen zu erzeugen.';
$string['namingscheme'] = 'Namensschema';
$string['namingscheme_help'] = '<p>Das Namensschema definiert, wie Gruppen beim Erstellen automatisch benannt werden. </p>
<p>Hierbei ist folgendes zu beachten:<br />
<ol><li>Der Gruppenname muss immer einzigartig in Ihrem Kurs sein (d.h. es können nicht mehrere Gruppen idente Namen tragen).</li>
<li>Sollen mehrere Gruppen hinzugefügt werden, müssen zwingend "Tags" verwendet werden, die die Gruppen eindeutig bezeichnen.</li></ol></p>
<p>Jeder "Tag" wird für die Gruppennamen durch (Benutzer-)Informationen ersetzt. Die Tags in [] sind mit Benutzerdaten verknüpft und die # und @ werden durch eine laufende Nummer bzw. alphabetische Repräsentation dieser ersetzt. Wenn JavaScript aktiviert ist, können Sie durch Klicken auf die Tags, diese dem Namensschema anhängen. Bitte beachten Sie, dass jeder Gruppenname innerhalb des Kurses einzigartig sein muss und ändern Sie bei entsprechenden Problemen das Namensschema!</p>';
$string['no_conflictfree_to_display'] = 'Keine konfliktfreien Gruppen anzuzeigen. Stattdessen alle angezeigt!';
$string['no_data_to_display'] = 'Keine Gruppendaten anzuzeigen!';
$string['no_grades_present'] = 'Keine Bewertungen anzuzeigen';
$string['no_groups_to_display'] = 'Keine Gruppe(n) anzuzeigen!';
$string['no_groupmembers_to_display'] = 'Keine Gruppenmitglieder zum Anzeigen vorhanden, stattdessen werden alle Gruppen angezeigt!';
$string['no_queues_to_resolve'] = 'Keine Warteliste aufzulösen!';
$string['no_registrations'] = 'Keine Anmeldungen';
$string['no_target_selected'] = 'Es wurde kein Ziel für den Kopiervorgang gewählt! Es muss zumindest 1 Ziel gewählt werden!';
$string['no_users_to_display'] = 'Keine Nutzer anzuzeigen!';
$string['noaccess'] = 'Sie haben keinen Zugriff auf dieses Modul! Es ist möglich, dass Sie nicht zur richtigen Gruppe gehören.';
$string['nobody_queued'] = 'Keine Wartelisteneinträge';
$string['nogrouptools'] = 'Es gibt keine Gruppenverwaltungen!';
$string['nogroupingselected'] = 'Es wurde(n) keine Gruppierung(en) ausgewählt!';
$string['nonconflicting'] = 'Konfliktfrei';
$string['nosmallgroups'] = 'Verhindere kleine Gruppen';
$string['nosmallgroups_help'] = 'Wenn aktiviert, wird sichergestellt, dass jede Gruppe zumindest zu 70% der angegebenen Größe gefüllt ist! Die Benutzer/innen der unter Umständen vorhandenen letzten kleineren Gruppe, werden auf die übrigen Gruppen aufgeteilt. Es kann daher vorkommen, dass diese Gruppen mehr Mitglieder als spezifiziert haben!';
$string['noregistrationdue'] = 'unbeschränkt';
$string['not_allowed_to_show_members'] = 'Sie haben keine Berechtigung diese Information anzuzeigen!';
$string['not_graded_by_me'] = 'Wurde von jemand anders benotet';
$string['not_in_queue_or_registered'] = '{$a->username} ist weder in der Gruppe {$a->groupname} angemeldet noch in deren Warteliste gereiht.';
$string['not_permitted'] = 'Nicht erlaubt';
$string['not_registered'] = 'Sie sind noch nirgends angemeldet!';
$string['you_are_not_in_queue_or_registered'] = 'Sie sind weder in der Gruppe {$a->groupname} angemeldet noch in deren Warteliste gereiht.';
$string['nothing_to_push'] = 'Nichts zu übernehmen!';
$string['nowhere_queued'] = 'Keine Wartelisteneinträge';
$string['number_of_students'] = 'Anzahl an Benutzer/innen';
$string['occupied'] = 'Belegt';
$string['onenewgrouping'] = 'Ja, in EINER neuen Gruppierung';
$string['onenewgroupingpergroup'] = 'Ja, eine Gruppierung PRO Gruppe';
$string['orientation'] = 'PDF-Ausrichtung';
$string['overflowwarning'] = 'Wenn Sie fortfahren wird die Gruppengröße in der Instanz {$a->instancename} überschritten!';
$string['overview'] = 'Überblick';
$string['overview_alt'] = 'Überblick über Gruppen und deren Anmeldungen';
$string['overview_tab'] = 'Gruppenübersicht';
$string['overview_tab_alt'] = 'Öffne Gruppenübersicht';
$string['overwrite_label'] = 'Überschreibe vorhandene Bewertungen';
$string['place_allocated_in_group_success'] = 'Gruppe {$a->groupname} wurde erfolgreich zur Anmeldung markiert';
$string['pluginadministration'] = 'Gruppenverwaltungs Administration';
$string['pluginname'] = 'Gruppenverwaltung';
$string['portrait'] = 'Hochformat';
$string['preview'] = 'Vorschau';
$string['queue'] = 'Warteliste';
$string['queuesgrp'] = 'Warteschlangen und maximale Anzahl an Warteschlangenplätze';
$string['queuesgrp_help'] = 'Wenn Wartelisten aktiviert sind, werden Benutzer, die sich in einer vollen Gruppe anmelden wollen, auf deren Warteliste gesetzt bis sie durch die Abmeldung eines anderen in die Gruppe nachrücken. Nach der Deadline, kann der Lehrende die Wartelisten auflösen, wobei die Gruppen in der Reihenfolge der Sortierung in der Gruppenliste mit den restlichen Wartelisteneinträgen aufgefüllt werden. Man kann die gleichzeitigen Wartelistenplätze für Benutzer begrenzen.<br />Begrenzt die gleichzeitig einnehmbaren Plätze in Wartelisten pro Benutzer in dieser Gruppenverwaltung.';
$string['queuespresenterror'] = 'Es sind bereits Benutzer in Warteschlangen eingetragen. Sie können diese nicht deaktivieren, bis die Wartelisten aufgelöst wurden.';
$string['queue_and_multiple_reg_title'] = 'Warteschlangen und Mehrfachanmeldungen';
$string['queue_in_group'] = 'Trage {$a->username} in Warteliste der Gruppe {$a->groupname} ein?';
$string['queue_in_group_success'] = '{$a->username} erfolgreich in Warteliste der Gruppe {$a->groupname} eingetragen!';
$string['queue_you_in_group'] = 'Wollen Sie in die Warteliste der Gruppe {$a->groupname} eingetragen werden?';
$string['queue_you_in_group_success'] = 'Sie wurden erfolgreich in die Warteliste der Gruppe {$a->groupname} eingetragen!';
$string['queued'] = 'In Warteliste';
$string['queued_in_group_info'] = '{$a->username} in Warteliste von {$a->groupname}';
$string['queued_on_rank'] = 'In Warteliste auf Platz #{$a}';
$string['queues'] = 'Wartelisten';
$string['queuespresent'] = 'Es sind bereits Nutzer in Warteschlangen eingetragen! Diese werden gelöscht, wenn sie fortfahren. Speichern sie erneut um fortzufahren!';
$string['queuesizeerror'] = 'Maximale Warteplätze müssen ganzzahlig und positive sein (>= 1)';
$string['queues_max'] = 'Max Plätze in Wartelisten';
$string['queueing_is'] = 'Wartelisten sind';
$string['rank'] = 'Rang';
$string['recreate_group'] = 'Gruppe erneut erstellen';
$string['reference_grade_feedback'] = 'Referenzbewertung / Feedback';
$string['refresh_table_button'] = 'Vorschau aktualisieren';
$string['reg_in_full_group'] = 'Anmeldung von {$a->username} in Gruppe {$a->groupname} nicht möglich, da diese bereits voll ist!';
$string['reg_you_in_full_group'] = 'Anmeldung in Gruppe {$a->groupname} nicht möglich, da diese bereits voll ist!';
$string['reg_not_open'] = 'Die Anmeldung ist derzeit nicht möglich. Vielleicht ist der Anmeldezeitraum vorbei oder es war nie erlaubt.';
$string['register'] = 'Anmelden';
$string['registered'] = 'Angemeldet';
$string['registered_on_rank'] = 'Angemeldet auf Platz #{$a}';
$string['registered_in_group_info'] = '{$a->username} in Gruppe {$a->groupname} angemeldet';
$string['register_in_group'] = 'Sind Sie sicher, dass Sie {$a->username} in Gruppe {$a->groupname} anmelden möchten?';
$string['register_in_group_success'] = 'Anmeldung von {$a->username} in Gruppe {$a->groupname} war erfolgreich!';
$string['register_you_in_group'] = 'Sind Sie sicher, dass Sie sich zu Gruppe {$a->groupname} anmelden möchten?';
$string['register_you_in_group_success'] = 'Sie wurden erfolgreich in Gruppe {$a->groupname} angemeldet!';
$string['register_you_in_group_successmail'] = 'Sie wurden erfolgreich in Gruppe {$a->groupname} angemeldet!';
$string['register_you_in_group_successmailhtml'] = 'Sie wurden erfolgreich in Gruppe {$a->groupname} angemeldet!';
$string['registrationdue'] = 'Anmeldung bis';
$string['registrations'] = 'Anmeldungen';
$string['registrations_missing'] = '{$a} Anmeldungen fehlen';
$string['registration_missing'] = '1 Anmeldung fehlt';
$string['registration_period_end'] = 'Ende der Anmeldung für';
$string['registration_period_start'] = 'Beginn der Anmeldung für';
$string['reset_agrps'] = 'Setze aktive Gruppen zurück';
$string['reset_agrps_help'] = 'Setzt alle Kursgruppen inaktiv für die Gruppenverwaltungen und löscht jede Registrierung und jeden Wartelisteneintrag in den Gruppenverwaltungen des Kurses!';
$string['reset_registrations'] = 'Setze Anmeldungen zurück';
$string['reset_registrations_help'] = 'Anmeldungen werden automatisch gelöscht, wenn aktive Gruppen zurückgesetzt werden.';
$string['reset_queues'] = 'Setze Wartelisten zurück';
$string['reset_queues_help'] = 'Wartelisten werden automatisch gelöscht, wenn aktive Gruppen zurückgesetzt werden.';
$string['reset_transparent_unreg'] = 'Melde Moodle-Gruppen-Mitglieder ab';
$string['reset_transparent_unreg_help'] = 'Melde alle Moodle-Gruppen-Mitglieder ab, wenn Sie in entsprechenden aktiven Gruppen angemeldet sind.';
$string['resolve_queue_legend'] = 'Löse Wartelisten auf';
$string['resolve_queue_title'] = 'Löse Wartelisten auf';
$string['resolve_queue'] = 'Wartelisten auflösen';
$string['selected'] = 'Ausgewählt';
$string['select'] = 'Selektieren';
$string['selectfromcohort'] = 'Mitglieder aus globaler Gruppe wählen';
$string['selfregistration'] = 'Anmeldung';
$string['selfregistration_alt'] = 'Selbstanmeldung zu einer oder mehreren Gruppen';
$string['show_members'] = 'Gruppenmitglieder anzeigen';
$string['show_members_help'] = 'Wenn aktiviert ist sichtbar, wer sich schon zur Gruppe angemeldet hat.';
$string['size'] = 'Gruppengröße';
$string['size_grp'] = 'Globale Gruppengrößeneinstellung';
$string['size_grp_help'] = 'Wenn Gruppengröße verwendet wird, wird die maximale Anzahl an Gruppenanmeldungen für jede Gruppe begrenzt (für diese Instanz). Wenn zusätzlich die "individuelle Größe" aktiviert ist, wird die Gruppengröße für jede Gruppe gesondert festgelegt.';
$string['skipped'] = 'Übersprungen';
$string['source'] = 'Quelle';
$string['source_missing'] = 'Es gibt keine Quellen, von denen kopiert werden kann!';
$string['sources_missing'] = 'Es existiert zumindest 1 Gruppe, bei der keine Quelle ausgewählt wurde!';
$string['sortlist_no_data'] = 'In diesem Kurs sind aktuell keine Gruppen vorhanden.';
$string['status'] = 'Status';
$string['status_help'] = '<ul><li><span style="font-weight:bold">✔</span> angemeldet in Moodle-Gruppe und Gruppenverwaltung</li><li><span style="font-weight:bold">?</span> angemeldet in Moodle-Gruppe, nicht aber in der Gruppenverwaltung</li><li><span style="font-weight:bold">+</span> angemeldet in der Gruppenverwaltung, nicht aber in Moodle-Gruppe</li><li><span style="font-weight:bold">1, 2, 3...</span> auf Warteliste in der Gruppenverwaltung</li></ul>';
$string['switched_to_all_groups'] = 'Ändere Gruppenfilter zu "Alle"!';
$string['target'] = 'Ziel';
$string['too_many_queue_places'] = 'Kann {$a->username} nicht in Warteliste der Gruppe {$a->groupname} eintragen, weil {$a->username} bereits in zu vielen Wartelisten eingetragen ist!';
$string['too_many_regs'] = 'Nutzer ist bereits in zu vielen Gruppen angemeldet!';
$string['toomanyregs'] = 'Achtung! In mindestens einer der Gruppen wird die eingetragene Gruppengröße bereits überschritten.<br />Bevor Sie die neuen Einstellungen der Gruppengröße speichern können, reduzieren Sie die Anzahl der angemeldeten Teilnehmer/innen in den Gruppen.';
$string['toomanyregspresent'] = 'Zumindest 1 Nutzer ist in zu vielen Gruppen angemeldet, daher kann die maximale Anzahl zu wählender Gruppen nicht geringer als {$a} eingestellt werden.';
$string['toolessregspresent'] = 'Zumindest 1 Nutzer ist in zu wenigen Gruppen angemeldet, daher kann die minimale Anzahl zu wählender Gruppen nicht größer als {$a} eingestellt werden.';
$string['you_have_too_many_queue_places'] = 'Kann Sie nicht in Warteliste der Gruppe {$a->groupname} eintragen, weil Sie bereits in zu vielen Wartelisten eingetragen sind!';
$string['total'] = 'Gesamt';
$string['unqueue'] = 'Aus Warteliste austragen';
$string['unqueue_from_group'] = 'Wirklich mit Austragen von {$a->username} aus Warteliste der Gruppe {$a->groupname} fortfahren?';
$string['unqueue_from_group_success'] = 'Austragen von {$a->username} aus Warteliste der Gruppe {$a->groupname} erfolgreich!';
$string['unqueue_you_from_group'] = 'Wollen Sie sich wirklich aus der Warteliste der Gruppe {$a->groupname} austragen?';
$string['unqueue_you_from_group_success'] = 'Sie wurden erfolgreich aus der Warteliste der Gruppe {$a->groupname} ausgetragen!';
$string['unreg'] = 'Abmelden';
$string['unreg_from_group'] = 'Mit Abmeldung von {$a->username} aus Gruppe {$a->groupname} fortfahren?';
$string['unreg_from_group_success'] = 'Abmelden von {$a->username} aus Gruppe {$a->groupname} erfolgreich!';
$string['unreg_not_alowed'] = 'Abmeldung ist nicht gestattet!';
$string['unreg_you_from_group'] = 'Mit Ihrer Abmeldung aus Gruppe {$a->groupname} fortfahren?';
$string['unreg_you_from_group_success'] = 'Ihre Abmeldung aus Gruppe {$a->groupname} war erfolgreich!';
$string['unreg_is'] = 'Abmeldung';
$string['updatemdlgrps'] = 'Anmeldungen in Moodle-Gruppen übertragen';
$string['update_grouplist_success'] = 'Aktive Gruppen erfolgreich aktualisiert!';
$string['userlist'] = 'Teilnehmer/innenliste';
$string['userlist_tab'] = 'Kursübersicht';
$string['userlist_tab_alt'] = 'Öffne Kursübersicht';
$string['user_has_too_less_regs'] = 'Abmeldung ist nicht möglich, da Nutzer {$a->username} in zu wenigen Gruppen angemeldet ist!';
$string['user_is_deleted'] = 'Der gefundene Benutzer-Account (ID {$a->id}, Name {$a->fullname}) ist bereits gelöscht. Eine Einschreibung ist deshalb nicht möglich.';
$string['user_move_prev'] = '"Nutzer/in mit ID {$a->userid} wird von Gruppe {$a->agrpid} nach Gruppe {$a->current_grp} verschoben ({$a->current_text})';
$string['user_moved'] = 'Nutzer/in mit ID {$a->userid} wurde von Gruppe {$a->agrpid} nach Gruppe {$a->current_grp} verschoben ({$a->current_text})';
$string['userlist_help'] = 'Liste von ID-Nummern durch eines oder mehrere der folgenden Zeichen getrennt<ul><li>[,] Beistrich</li><li>[;] Strichpunkt</li><li>[ ] Leerzeichen</li><li>[\n] Zeilensprung</li><li>[\r] Wagenrücklauf</li><li>[\t] Tabulator</li></ul>';
$string['users_tab'] = 'Teilnehmer/innen';
$string['users_tab_alt'] = 'Öffne Teilnehmer/innen';
$string['user_not_found'] = 'Nutzer {$a} wurde nicht gefunden!';
$string['use_all_or_chosen'] = 'Alle/Ausgewählte';
$string['use_all_or_chosen_help'] = 'Wenn alle ausgewählt, wird eine Gruppierung für jede Kursgruppe erstellt. Bei "Ausgewählte" wird eine Gruppierung nur für in der Liste ausgewählte Gruppen erstellt.';
$string['use_individual'] = 'Benutze individuelle Gruppengrößen';
$string['use_individual_help'] = 'Überschreibe globale Gruppengröße mit individuellen Werten für jede Gruppe. Diese werden per Gruppenliste weiter unten gesetzt.';
$string['use_size'] = 'Aktivieren';
$string['use_queue'] = 'Benutze Wartelisten';
$string['viewmoodlegroups'] = 'Zu den Moodle Gruppen';
$string['you_are_already_marked'] = 'Sie haben die Gruppe {$a->groupname} bereits erfolgreich zur Anmeldung markiert!';
$string['you_have_too_less_regs'] = 'Eine Abmeldung ist nicht möglich, weil Sie in zu wenigen Gruppen angemeldet sind';
$string['your_place_allocated_in_group_success'] = 'Sie haben die Gruppe {$a->groupname} erfolgreich zur Anmeldung markiert.';

// Deprecated since Moodle 2.8
$string['grouptool:view_registrations'] = 'Zeige wer in welcher aktiven Gruppe angemeldet/in der Warteliste gereiht ist.';
