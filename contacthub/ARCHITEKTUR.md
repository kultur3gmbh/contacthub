# ContactHub – modulare Architektur 0.14.5

## Pakete

`ContactHub Basic` ist das kostenlose, eigenständig lauffähige Haupt-Plugin. Es enthält Personen, Firmen, Adressen, Tags, Einwilligungen, Registrierung, Aktivitäten, Groundhogg-Kompatibilität und die grundlegende WooCommerce-Kundenansicht. Bestellungen bleiben in WooCommerce; ContactHub liest sie über die öffentliche CRUD-API und legt keine Bestellkopien an.

`ContactHub Plus` benötigt Basic und ergänzt abgegrenzte Module. In 0.14.0 sind E-Mail-Vorlagen und Newsletter-Versand, die direkt in die Personenliste integrierte visuelle Kontaktsuche, Kampagnen, Formularadapter sowie die Integration für The Events Calendar/Event Tickets Plus enthalten. Künftige Module für Amelia, GiveWP, B2B, Berichte und Automatisierungen verwenden dieselben Erweiterungspunkte. Eine Deaktivierung von Plus entfernt weder Daten noch Einstellungen.

```text
ContactHub Basic
├── Personen und Firmen
├── Adressen, Tags und Einwilligungen
├── Registrierung und Mein Konto
├── WooCommerce-Basisintegration
├── Groundhogg-Kompatibilität und Migration
└── Erweiterungspunkte
    └── ContactHub Plus
        ├── E-Mail-Vorlagen und Newsletter-Versand
        ├── Events und Event Tickets
        ├── WooCommerce- und Veranstaltungssegmente
        ├── Amelia (geplant)
        ├── B2B (geplant)
        ├── Berichte (geplant)
        └── Automatisierungen (geplant)
```

Basic enthält keine direkte Abfrage von Event-Tickets-Daten. Plus liefert Verfügbarkeit, Teilnehmerzahlen und Veranstaltungslisten über `contacthub_events_available`, `contacthub_event_summaries` und `contacthub_contact_events`. Zusätzliche Einstellungsbereiche werden über `contacthub_settings_tabs` und `contacthub_render_settings_tab_{name}` registriert.

## E-Mail-Vorlagen

E-Mail-Vorlagen werden im ContactHub-Datenbank-Modus in `chb_newsletter_templates` gespeichert. Im Groundhogg-Frontend-Modus bleiben dieser Bereich und der ContactHub-Versand ausgeblendet. Inhalt und fertiges responsives HTML werden getrennt gespeichert. Der visuelle Editor besitzt eine Bausteinpalette, Drag-and-drop-Arbeitsfläche, direkte Text- und Bildwerkzeuge sowie WordPress-WYSIWYG und Mediathek. Kampagnen, Empfängerstatus und globale Sperren liegen getrennt in `chb_newsletter_campaigns`, `chb_newsletter_recipients` und `chb_newsletter_suppressions`. Der Versand erfolgt über eine gebündelte WP-Cron-Warteschlange und `wp_mail`; fehlende Zustimmung und globale Sperren werden vor Vorbereitung und erneut vor dem Versand geprüft.

Die Vorlagen enthalten automatisch einen Abmelde-Platzhalter. Personalisierungen werden erst bei Vorschau beziehungsweise später beim Versand aufgelöst. Ein Testversand verwendet den angemeldeten Administrator als Beispieldatensatz und wird ausschließlich an die ausdrücklich eingegebene Testadresse gesendet.

## Nächster Ausbau: Notizen und Aktivitäten

Die Tabelle `chb_activities` bleibt die zentrale, eigenständige Historie. Der nächste Ausbau soll Notizen, Telefonate, E-Mails und Aufgaben als unterscheidbare Aktivitätstypen ablegen. Jeder Eintrag erhält die Zuordnung zu Person und optional Firma, Bestellung oder Veranstaltung sowie Autor, Zeitstempel, Inhalt und Änderungsinformationen. Die Oberfläche wird in den bestehenden Tab „Aktivitäten“ integriert; Berechtigungen regeln Erstellen, Bearbeiten und Löschen. Für den Groundhogg-Übergang ist eine einmalige, wiederholbar protokollierte Migration vorhandener Notizen vorgesehen.

## Datenquelle und Betriebsarten

Im **Groundhogg-Frontend-Modus** bleibt Groundhogg alleinige Datenquelle für Kontakte, Firmen, Tags, Zustimmungen, Broadcasts, Funnels und Automatisierungen. ContactHub stellt die Oberfläche bereit; der eigene Newsletter-Editor und -Versand bleiben in diesem Modus deaktiviert.

Im **ContactHub-Datenbank-Modus** werden neue und bearbeitete Personen, Firmen, Tags, Zustimmungen und Aktivitäten ausschließlich in ContactHub gespeichert; Groundhogg wird nicht parallel beschrieben. Mit ContactHub Plus übernimmt ContactHub in diesem Modus auch E-Mail-Vorlagen, Kampagnen und Versand. WooCommerce-, WordPress-, Rechnungs- und Veranstaltungsdaten bleiben über ihre jeweiligen Systeme verknüpft.

Das CRM-Schema verwendet die Tabellen `chb_contacts`, `chb_companies`, `chb_company_contacts`, `chb_tags`, `chb_contact_tags`, `chb_consents`, `chb_activities`, `chb_contactmeta` und `chb_companymeta`. Vor jedem Anlegen oder Aktualisieren wird der tatsächliche Tabellen- und Versionsstand geprüft. Ohne Groundhogg werden diese Tabellen bei einer frischen Aktivierung automatisch angelegt und ausgewählt. Ist Groundhogg aktiv, entstehen sie erst nach einer ausdrücklichen Einrichtung oder Migration.

Die Groundhogg-Migration liest Kontakte, Firmen, Firmenbeziehungen und Tags und protokolliert übernommene, übersprungene und fehlerhafte Datensätze. Groundhogg-IDs werden nur als Herkunftsverknüpfung in ContactHub gespeichert. ContactHub Plus kann zusätzlich E-Mail-Vorlagen und abgeschlossene Broadcasts samt aggregierten Versandwerten als schreibgeschützte, deduplizierte Archive übernehmen. Groundhogg wird dabei nie verändert. Importierte Originalvorlagen können nur angesehen oder in eine bearbeitbare ContactHub-Vorlage dupliziert werden; importierte Broadcasts lassen sich weder starten noch erneut versenden.

## Quellen

- Firmen, Ansprechpartner und Firmennotizen: offizielle WordPress-Abilities von Groundhogg Companies 3.5.0
- Personen, Tags, Kontaktnotizen und Marketingstatus: offizielle Groundhogg-Contact-Abilities
- Bestellungen und Rechnungsadresse: WooCommerce CRUD-API
- Rechnungs-PDF: vorhandene German-Market-Adminaktion aus den WooCommerce-Bestellaktionen; keine eigene PDF-Erzeugung
- Rechnungsnummer: vorhandene German-Market-Bestellmetadaten mit filterbarem Kompatibilitäts-Fallback; ohne separates Rechnungsnummern-Add-on wird wie bei German Market die WooCommerce-Bestellnummer verwendet; keine Kopie
- UID: `billing_vat` (German Market) und `b2b_uid` (B2B Market) des zugeordneten WordPress-Benutzers
- Installationshinweis für die spätere Benutzerdokumentation: Die eigene Option zur Anzeige des UID-Feldes in German Market beziehungsweise B2B Market muss deaktiviert werden, damit ContactHub das zentrale UID-Feld ohne doppelte Ausgabe darstellen kann.
- Aktivitäten: Groundhogg-Firmen- und Kontaktnotizen; keine zweite Ereignisdatenbank
- Marketingzustimmung: Groundhogg `optin_status`; Zusatzinformationen als Kontakt-Metadaten und nachvollziehbare Groundhogg-Notiz
- Telefonnummern: bestehende Groundhogg- und WooCommerce-Felder; die Trennung von Ländervorwahl und nationaler Nummer ist ausschließlich eine Darstellung der Oberfläche
- Rechnungs- und Lieferadressen: WooCommerce `WC_Customer` und dessen öffentliche CRUD-Methoden
- Bestellanzahl und Bestellsumme in der Personenliste: gemeinsame, seitenweise WooCommerce-Bestellabfrage; Zuordnung über die verknüpfte Kundennummer sowie Groundhogg-, Benutzer- und Rechnungs-E-Mail
- Doppelte Treffer werden anhand der WooCommerce-Bestell-ID zusammengeführt; stornierte, fehlgeschlagene und Checkout-Entwürfe sind ausgeschlossen, Rückerstattungen werden von der Summe abgezogen
- Veranstaltungsbuchungen und Check-ins: offizielle Attendee-Repository-Schnittstelle von Event Tickets Plus; Zuordnung zu Personen ausschließlich über normalisierte Teilnehmer-E-Mail-Adressen
- Veranstaltungs-Pop-up: strukturierte Termin- und Ortsfunktionen von The Events Calendar sowie Ticketbestand und Verkäufe aus den vorhandenen Event-Tickets-Objekten; keine Kopie
- Broadcasts: Groundhogg-Ereignisverlauf (`events`) und vorhandene Öffnungs-/Klickaktivitäten (`activity`); E-Mail-Vorschau über das zugeordnete Groundhogg-E-Mail-Objekt
- Tags: Groundhogg-Abilities zum Auflisten, Erstellen und Ändern der Kontaktzuordnung; dauerhaftes Löschen über Groundhoggs eigene Tags-Datenbankschnittstelle und deren Aufräum-Hooks
- Benutzerkonten und Rollen: WordPress-Benutzer-API; nur für Administratoren sichtbar und zusätzlich über Nonce, Rollen- und Benutzerberechtigungen geschützt

## Optionale Firmenverwaltung

Im Groundhogg-Frontend-Modus ist Groundhogg Companies ein optionales Modul. Ist die Erweiterung dort nicht aktiv, startet ContactHub direkt mit der Personenübersicht. Firmenmenü, Firmenspalte, Firmensortierung und Firmenblock werden nicht angezeigt; entsprechende Schreibzugriffe werden serverseitig abgewiesen. Im ContactHub-Datenbank-Modus steht die eigene Firmenverwaltung ohne Groundhogg bereit. Die Personenverwaltung mit Marketingstatus, WooCommerce-Adressen, Bestellungen, Rechnungslinks und Aktivitäten bleibt in beiden Betriebsarten aktiv.

## Optionale Veranstaltungsintegration

Sind The Events Calendar, Event Tickets und Event Tickets Plus aktiv, erscheint in der Personenliste die Zahl der eindeutig zugeordneten Veranstaltungen. Die Zuordnung erfolgt über die normalisierte E-Mail-Adresse der Event-Tickets-Teilnehmerliste; Buchung und Check-in führen beide zu einer Zuordnung, mehrere Tickets derselben E-Mail-Adresse für dieselbe Veranstaltung zählen einmal. Die Detailansicht zeigt alle gefundenen Veranstaltungsbuchungen und kann „Gebucht“ und „Besucht“ weiterhin unterscheiden. Fehlt eine Voraussetzung, bleiben Spalte und Tab ausgeblendet. Es werden keine Buchungen, Tickets oder Check-ins verändert und keine Veranstaltungsdaten dupliziert.

## Schutzregeln

Der Prototyp besitzt eng begrenzte AJAX-Schreibendpunkte für freigegebene Groundhogg-Firmen- und Kontaktfelder, WooCommerce-Kundenadressen sowie die beiden bestehenden UID-Benutzerfelder. Sie sind durch WooCommerce-Verwaltungsrecht und Nonce geschützt. Firmen- und Kontaktwerte werden ausschließlich über die offiziellen Abilities gespeichert; die UID wird synchron in `billing_vat` und `b2b_uid` geschrieben. Die Massenbearbeitung ist auf höchstens 100 ausdrücklich ausgewählte Kontakte begrenzt, verlangt einen Zustimmungsnachweis und schützt Abmeldungen, unzustellbare Adressen, Spam-, Beschwerde- und Sperrstatus vor einer Reaktivierung. Ohne Groundhogg legt ContactHub seine CRM-Tabellen bei der Aktivierung automatisch an; mit Groundhogg geschieht dies nur nach ausdrücklicher Einrichtung oder Migration. ContactHub verändert keine Automatisierungen und generiert keine Rechnungen neu. Historische Rechnungs- und Bestelldaten bleiben schreibgeschützt.

Bei einer Deinstallation bleiben Daten standardmäßig vollständig erhalten. Nur wenn ein Administrator die Löschoption zuvor ausdrücklich aktiviert und bestätigt hat, werden ContactHub-eigene `chb_`-Tabellen, ContactHub-Optionen und ContactHub-Benutzermetadaten entfernt. Groundhogg-, WooCommerce-, German-Market-, B2B-Market-, Event- und Rechnungsdaten sind davon ausgeschlossen.

Eine VIES-Prüfung findet nur nach einem ausdrücklichen Klick statt. Für das grüne Häkchen werden ausschließlich ein SHA-256-Prüfwert und der Prüfzeitpunkt gespeichert, keine dritte UID-Kopie. Bei einer Änderung der UID verfällt die Kennzeichnung automatisch.

Das manuelle Erteilen oder Widerrufen der E-Mail-Marketingzustimmung erfordert eine ausdrückliche Bestätigung, Herkunft und Datum. Der echte Groundhogg-Opt-in-Status wird geändert und der Vorgang zusätzlich als Groundhogg-Kontaktnotiz mit Bearbeiter protokolliert.

Neue Personen werden ausschließlich in der ausgewählten Datenquelle mit dem Status „Unbestätigt“ angelegt. Existiert die E-Mail-Adresse bereits, wird kein bestehender Kontakt überschrieben. Das Plugin legt dabei nicht automatisch ein WordPress- oder WooCommerce-Benutzerkonto an.

Beim dauerhaften Löschen eines Tags werden die bestehenden Groundhogg-Berechtigungen und Lösch-Hooks verwendet. Tags, die Groundhogg zur Abbildung von Opt-in- und Marketable-Status verwendet, sind in dieser Oberfläche vor dem Löschen geschützt.

WooCommerce-Adressen werden nur am verknüpften Kundenkonto gespeichert. Der Schreibendpunkt akzeptiert ausschließlich freigegebene Rechnungs- und Lieferadressfelder und verändert keine `WC_Order`-Objekte. Ohne verknüpftes WordPress-Benutzerkonto bleibt die Funktion deaktiviert.

Die Bestellvorschau liest ausschließlich das vorhandene WooCommerce-Bestellobjekt. Für das Rechnungs-PDF wird genau die Adminaktion verwendet, die German Market in der WooCommerce-Bestellliste bereitstellt. Das Plugin erzeugt weder eine neue Rechnung noch eine zweite Downloadberechtigung.

WordPress-Rollen und Kontoverknüpfungen sind nur für Administratoren sichtbar. Mehrfachrollen werden gegen die in WordPress tatsächlich bearbeitbaren Rollen geprüft; die eigene Administratorrolle und die Rolle des letzten Administrators sind vor versehentlichem Entfernen geschützt. Neue Konten erhalten ein starkes Zufallspasswort und optional die WordPress-Einladung. Ein bestehendes Konto wird nur bei identischer E-Mail-Adresse und nach ausdrücklicher Bestätigung verknüpft. Diese Vorgänge ändern keinen Groundhogg-Marketingstatus.

## Noch zu verifizieren

Der verfügbare Website-Zugang liefert Plugin-Versionen und die Companies-Abilities, aber keinen Datei- oder Datenbankzugriff. Deshalb muss auf Staging geprüft werden:

1. Im Shop aktivierte German-Market-Rechnungsstatus, Rechnungsnummern-Metadaten und die von German Market gelieferte Adminaktion.
2. Zuordnung mehrerer Firmen zu einem Kontakt und Bestellungen ohne Groundhogg-Kontakt.
3. Tatsächliche Versionen und Buchungsdaten von The Events Calendar und Event Tickets Plus auf Wunderweiber Business.

