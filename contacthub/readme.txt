=== ContactHub Basic – User-Friendly CRM for WordPress ===
Developer: Kultur3 GmbH
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3

A user-friendly contact and company CRM for WordPress with a free WooCommerce customer overview, optional Groundhogg compatibility, and a stable extension API for ContactHub Plus.

== Changelog ==

= 1.0.0 =
* Establishes the first stable ContactHub Basic release while preserving standalone ContactHub and Groundhogg data-source modes.
* Refines the shared administration controls used by optional form integrations and improves active My Account navigation spacing.
* Retains non-destructive, repeatable migrations, protected deletion workflows, and the stable extension contracts used by ContactHub Plus.

= 0.16.1 =
* Keeps company-list edit, view, and trash controls on the same line as the company name.

= 0.16.0 =
* Automatically applies learned salutation assignments when known first names are entered, while preserving every explicit selection and extending the library from confirmed assignments.
* Groups the company-list edit, view, and trash controls into one compact and accessible action set.
* Adds responsive shared administration styles for the ContactHub Plus form-field mapper without making Basic depend on Plus.

= 0.15.5 =
* Synchronized ContactHub Basic with ContactHub Plus and ContactHub Checkout 0.15.5 while keeping cart and checkout presentation outside the Basic plugin.

= 0.15.4 =
* Synchronized ContactHub Basic with ContactHub Plus 0.15.4 while keeping all cart and checkout presentation exclusively in Plus.

= 0.15.3 =
* Synchronized ContactHub Basic with ContactHub Plus 0.15.3 while keeping cart and checkout presentation exclusively in Plus.

= 0.15.1 =
* Corrected the trash glyph alignment in the compact person-list action group.

= 0.15.0 =
* Groups the edit, view, and trash controls in the people list into one compact action set.

= 0.14.8 =
* Added stable extension contracts that let ContactHub Plus provide optional checkout consent while preserving standalone ContactHub and Groundhogg data modes.
* Kept the free WooCommerce integration focused on customers, addresses, orders, invoices, and customer totals; cart and checkout presentation remain Plus features.
* Replaced the visible Trash column label and row text links in people and company lists with compact accessible trash-icon buttons.

= 0.14.6 =
* Added a secure GitHub release updater for ContactHub Basic and the shared updater contract used by ContactHub Plus.
* Added release-manifest, checksum, package-root, version, WordPress, PHP, and Basic compatibility validation before installation.
* Added administrator-controlled manual checks and opt-in automatic updates without changing ContactHub customer data or settings.

= 0.14.5 =
* Added an administrator-only Trash for standalone ContactHub people and companies with individual and bulk actions.
* Added safe restoration while preserving ContactHub relationships and stable record identifiers.
* Added explicitly confirmed permanent deletion of local ContactHub records with transactional rollback; WordPress users, orders, invoices, events, Groundhogg records, and external source data remain untouched.
* Prevented repeat imports from silently recreating people or companies while their ContactHub records remain in Trash.

= 0.14.0 =
* Added separate registration setting areas for general customers, business customers, and newsletter signup.
* Added persistent back, previous, and next navigation from person details to the active people-list view.
* Replaced the destructive reset checkbox with an explicit warning dialog and kept migrations administrator-only and non-destructive toward source systems.
* The Newsletter Plugin migration is shown only when its installation is detected and now supports a preview before import.
* Generalized person communication history from Groundhogg broadcasts to source-labelled mailings supplied by optional modules.

= 0.13.2 =
* Added a dedicated migration center for Groundhogg, WooCommerce, and The Newsletter Plugin imports.
* Separated standard and business customer registrations and added a standalone newsletter signup with double opt-in.
* Added a safe duplicate-candidate preview without automatic data merging.

= 0.13.1 =
* Corrected Groundhogg contact values in filtered people lists and made double-opt-in emails richly editable.

= 0.13.0 =
* Corrected a PHP namespace syntax error in the complete Groundhogg contact loader that made Basic 0.12.4 unusable.
* Retains the full-data search and identity-mapping improvements prepared for 0.12.4.

= 0.12.4 =
* Advanced searches now load the complete Groundhogg contact set independently of list pagination.
* Prepared shared full-dataset identity mapping for reliable WooCommerce purchase and Event Tickets searches.

= 0.12.3 =
* Company lists now show their assigned people, while person master data and address presentation have been tightened and clarified.
* Improved German labels, alignment, and list presentation across the contact and company administration.
* Database upgrades now finish before the first ContactHub table query and verify required tables and fields before marking an upgrade complete.

= 0.12.2 =
* Reordered person master data, improved address display, and expanded the salutation library with safe bulk assignment.
* Improved compatibility of the salutation library in both ContactHub and Groundhogg data modes.

= 0.12.0 =
* Added salutations with a locally learned, editable and CSV-mergeable first-name dictionary; suggestions are never applied automatically.
* Added optional tag descriptions and reorganized person master data, tags, consent, menu order, address display, and people-list number alignment.
* Added a safe two-step WooCommerce customer import for standalone installations without Groundhogg; email is the unique merge key and orders remain untouched.
* Country selectors now show Austria, Germany, and Switzerland first, followed by a separator and the remaining countries alphabetically.
* ContactHub database upgrades now run automatically for existing standalone installations.

= 0.11.5 =
* Added company assignment while creating a person, data-source-aware company diagnostics, and direct company creation.
* Added selectable export fields, print-only company-name shortening, and a combined sortable orders heading.

= 0.11.4 =
* Added secure CSV export for selected people or every person matching the current search and filters.
* Added a print-list dialog with selected/all scope and individually selectable columns.
* Exposed customer and WordPress role editing clearly to administrators while retaining permission safeguards.
* Groundhogg role and system tags are hidden from ContactHub tag selectors without deleting source data.

= 0.11.3 =
* Added the ContactHub Plus advanced-filter state to the people list, sorting links, quick filters, and pagination without changing the selected data source.
* Kept the people list as the single working screen for advanced contact searches.

= 0.11.2 =
* Added a structured contact address that is stored in the selected ContactHub or Groundhogg data source independently of a WordPress/WooCommerce account.
* The address shortcut in person master data now opens the contact address editor; an explicit checkbox can copy it to the WooCommerce billing profile without changing existing orders or invoices.
* New ContactHub registrations and Groundhogg migrations retain the structured primary address in the ContactHub database.
* Vertically centered the people-list selection checkboxes.

= 0.11.1 =
* Renamed the former test database throughout the interface to ContactHub database.
* Fresh installations without Groundhogg now create and select the ContactHub database automatically.
* Added explicit Groundhogg frontend and ContactHub database modes; Groundhogg remains responsible for broadcasts, funnels, and automations in its mode.
* Groundhogg migration tools are hidden when Groundhogg is unavailable and remain read-only toward Groundhogg.
* Replaced the one-time theme-style action with a visible ContactHub/theme style switch that shows the active choice.
* Right-aligned monetary totals in order and invoice tables.
* Added a migration extension point used by ContactHub Plus for protected Groundhogg email and broadcast archives.
* CSV import and export are intentionally not included in this release.

= 0.11.0 =
* Added public, read-only extension APIs for safe segment evaluation and newsletter recipients.
* Added a protected unsubscribe bridge for both ContactHub standalone data and Groundhogg contacts.
* Added the saved-segment selector to the people list while keeping filtering, sorting, and pagination compatible.
* Protected hard-bounce, spam, complaint, and blocked statuses from the ordinary manual consent override.
* Kept Basic translations in the independent `contacthub` text domain and language catalog.

= 0.10.0 =
* Split ContactHub into the free ContactHub Basic plugin and the optional ContactHub Plus extension package.
* ContactHub Basic retains people, companies, addresses, tags, consent, registration, Groundhogg compatibility, and the WooCommerce customer and order overview.
* Newsletter templates and the visual composer moved to ContactHub Plus without changing their existing database table or settings.
* The Events Calendar and Event Tickets Plus adapter moved behind a ContactHub Plus interface; Basic no longer contains third-party attendee queries.
* Added extensible settings tabs and event data filters so future modules can be added without editing the Basic plugin.
* Disabling ContactHub Plus hides its modules but never deletes contacts, orders, event data, templates, or settings.

= 0.9.2 =
* Text blocks now provide a formatting toolbar directly above the selected block and can be edited immediately inside the email canvas.
* Image blocks expose alignment and width controls directly on the block and in the sidebar; widths can use pixels or percent.
* Added an optional WooCommerce product block for two or four products selected from latest, category, producer/brand or product attribute, featured, sale, or manual sources.
* Product blocks query current WooCommerce product images, names, prices, and links without duplicating product data in ContactHub.
* When WooCommerce is unavailable, the product block is hidden from the palette while existing product blocks remain intact and display a clear notice.
* The people list displays email addresses as plain text with a separate mailto icon and suppresses Groundhogg's global email detector throughout ContactHub.

= 0.9.1 =
* Rebuilt the newsletter template editor as a three-part visual composer with draggable content blocks, an email canvas, and a contextual settings sidebar.
* Rich-text blocks now use the WordPress WYSIWYG editor, while image blocks select files directly from the WordPress media library.
* Blocks can be inserted, reordered by drag and drop, duplicated, removed, aligned, and edited without leaving the visual email canvas.
* Added a dedicated Newsletter settings tab for sender details, company and legal data, logo, colors, typography, email width, and a fixed footer.
* The company footer is locked in the template editor and automatically includes configured legal links and the unsubscribe link.
* WooCommerce email colors, sender details, header image, and footer text can be copied into ContactHub without creating a permanent WooCommerce dependency.
* Existing 0.9.0 templates remain readable and receive the extended block properties when next saved.
* Hidden shipping-address fields are disabled and never remain required; copied billing values are cleared when a customer switches to a genuinely separate shipping address.

= 0.9.0 =
* Groundhogg tags are now read page by page without the former fixed limit; regular tags are no longer hidden merely because their names match WordPress roles.
* The repeatable migration maps every readable tag and assignment, omits only genuine internal Groundhogg status tags, and records source/target counts plus migration warnings.
* Tag management now follows the WordPress taxonomy layout with an add/edit panel, searchable table, row actions, bulk deletion, assignment counts, and pagination.
* Added an independent ContactHub newsletter-template data model using the `chb_newsletter_templates` table; it does not depend on the selected contact data source.
* Added newsletter template management with drafts, duplication, archiving, search, and pagination.
* Added a responsive block editor for headings, text, images, buttons, dividers, and spacing, including ordering and removal controls.
* Added subject, preview text, sender name, reply-to address, personalization placeholders, and an automatic unsubscribe placeholder.
* Added desktop/mobile preview with a real test-contact representation and test delivery through the configured WordPress mail system.
* WooCommerce address AJAX saving now re-reads and compares every persisted address field before showing a success message.
* The active My Account menu item now prioritizes the theme's primary/hover accent color instead of a potentially white palette variable.

= 0.8.6 =
* Korrektur: Rechnungs- und Lieferadressen werden zuverlässig über WooCommerce und dessen zugrunde liegende Benutzerfelder gespeichert; eine unveränderte, bereits bestätigte UID löst keine unnötige erneute VIES-Abfrage aus.
* Ein eigener Menüpunkt „Tags“ steht direkt vor „Einstellungen“ bereit und arbeitet mit der aktuell gewählten Datenquelle.
* Die Tag-Verwaltung bietet Suche, Neuanlage, Umbenennen, Zuordnungszahlen und geschütztes Löschen samt bestehender Zuordnungen.
* Die Adresse in den Personen-Stammdaten ist über ein Bearbeiten-Symbol zugänglich und öffnet die strukturierte WooCommerce-Rechnungsadresse, ohne Daten zu duplizieren.
* Beim Verknüpfen eines WordPress-Kontos wird je nach Datenquelle korrekt „ContactHub-Person“ oder „Groundhogg-Kontakt“ angezeigt.
* Im Kundenkonto steht die optionale Firma vor dem Namen der Rechnungsadresse und die UID-Prüfung vor der abweichenden Lieferadresse.
* Fremde doppelte UID-Felder werden im ContactHub-Adressbereich gezielt ausgeblendet.
* Der aktive Menüpunkt unter „Mein Konto“ übernimmt die allgemeine Akzentfarbe des aktiven Themes.
* Die bestehende chb_activities-Struktur bleibt die Grundlage für die als nächsten Ausbau geplanten Notizen und weiteren Aktivitäten.

= 0.8.5 =
* „Mein Profil“ und WooCommerce-Rechnungs-/Lieferadressen speichern per AJAX mit Rückmeldung im Formular.
* Die Rechnungsadresse enthält ein einziges ContactHub-UID-Feld; angegebene UID-Nummern werden vor dem Speichern verpflichtend über EU VIES geprüft und positiv markiert.
* Die Personendetailansicht zeigt die Adresse in den Stammdaten, eine reine E-Mail-Anzeige mit separatem Mail-Symbol und den Double-Opt-in-Status.
* Die Tabs lauten und folgen nun: Bestellungen, Rechnungs- und Lieferadresse, Events (wenn verfügbar), Broadcasts und Aktivitäten.
* Tag-Zuweisung, Entfernung und Neuanlage besitzen für Groundhogg eine direkte, kompatible API-Rückfallebene.
* Telefonnummern verwenden auch im Registrierungsformular getrennte europäische Ländervorwahl und einheitliche österreichische Abstände.
* Doppelte UID-Felder anderer Erweiterungen und der entbehrliche Dashboard-Text „ÜBERSICHTS-BEREICH“ werden nur in den ContactHub-Ansichten ausgeblendet.

= 0.8.4 =
* Der leere Tag-Bereich in der Personendetailansicht wurde korrigiert; Hinweis und Eingabefeld überlagern sich nicht mehr.
* Die WooCommerce-Seite „Mein Konto“ zeigt Gästen zunächst nur die Anmeldung und öffnet das vollständige ContactHub-Registrierungsformular erst über „Jetzt registrieren“.
* Das Kundenmenü beginnt mit Dashboard und „Mein Profil“, gefolgt von Adressen, Zahlungsarten, zusätzlichen Erweiterungsbereichen und Abmelden.
* Das Dashboard zeigt Bestellanzahl, Bestellsumme, bis zu fünf letzte Bestellungen und – bei vorhandener Event-Tickets-Integration – die Zahl tatsächlich besuchter Veranstaltungen.
* „Mein Profil“ ergänzt vor- und nachgestellten Titel, Telefonnummer und eine optionale Firma; die WooCommerce-Passwortänderung bleibt erhalten.
* Unter „Allgemeines“ wird das aktive Theme angezeigt. Ein geschützter Button aktiviert die Übernahme kompatibler Typografie-, Farb-, Formular-, Button-, Rahmen- und Rundungsstile.
* Das Dashboard ist kompakter und enthält wieder einen erklärenden Text mit direkten Links zu Profil, Adressen, Zahlungsarten und Passwortverwaltung.
* „Mein Profil“ besitzt eine feste Überschrift und ordnet Vor-/Nachname, vor-/nachgestellte Titel, die europäische Telefonnummer sowie die persönliche Adresse in Zweierreihen an.
* Die Telefonnummer verwendet eine getrennte europäische Ländervorwahl, erkennt eingegebene internationale Vorwahlen und formatiert österreichische Mobil- und Ortsvorwahlen.
* Die Passwortänderung wurde aus dem Profil entfernt und als eigener Menüpunkt umgesetzt.
* Die Rechnungsadresse kann auf Wunsch aus der Profiladresse übernommen werden. Eine gesonderte Lieferadresse bleibt verborgen, bis sie ausdrücklich als abweichend markiert wird.

= 0.8.3 =
* Der Firmenbereich im Registrierungsformular erscheint zunächst als einfache Zeile „Firmendaten anlegen“ mit großem Plus-/Minus-Symbol und öffnet die Felder erst auf Klick.
* Die gesonderte Lieferadresse verwendet denselben Aufbau; ohne Öffnen des Bereichs gilt weiterhin die Rechnungsadresse.
* Die gewünschte Kundenrolle wird als Dropdown angezeigt und weiterhin erst nach Double-Opt-in und administrativer Freigabe zugewiesen.
* Administratoren können die vorbereiteten chb_-Tabellen nun ausdrücklich als ContactHub-Testdatenbank anlegen und aktivieren.
* Im Testdatenbankmodus werden Personen, Firmen, Tags, Zustimmungen und Aktivitäten in ContactHub gelesen und gespeichert; Groundhogg bleibt unverändert.
* Kontakte, Firmen, Firmenbeziehungen und Tags können zu Testzwecken aus Groundhogg übernommen werden. Wiederholte Importe aktualisieren zugeordnete Kontakte und Firmen, statt sie anhand ihrer Groundhogg-ID erneut anzulegen.
* Die Einstellungsseite zeigt Tabellenstände und ein Protokoll des letzten Imports.
* Eine zweifach bestätigte Rücksetzfunktion leert ausschließlich die ContactHub-eigenen chb_-Tabellen. Danach kann die Groundhogg-Testmigration erneut ausgeführt werden.

= 0.8.2 =
* Die Einstellungen sind in die Tabs „Allgemeines“ und „Kunden-Registrierung“ gegliedert; weitere Bereiche können später ergänzt werden.
* Unter „Allgemeines“ wird Groundhogg als führende Datenquelle angezeigt, sofern es aktiv ist. Eine Deaktivierung führt niemals zu einem automatischen Wechsel der Datenquelle.
* Die künftige Standalone-Datenstruktur mit dem Präfix chb_ ist vorbereitet, bleibt jedoch bis zur Fertigstellung der Datenzugriffsschicht und des Migrationsassistenten gesperrt.
* ContactHub legt in dieser Version keine Standalone-Kundentabellen automatisch an. Bestehende Tabellen werden vor einer späteren expliziten Einrichtung oder Aktualisierung geprüft.
* Erkannte Integrationen werden samt Status „aktiv“ beziehungsweise „installiert, inaktiv“ angezeigt; ihre Daten werden bei einer ContactHub-Deinstallation ausdrücklich nicht verändert.
* Kundendaten bleiben bei einer Deinstallation standardmäßig erhalten. Die Löschung ausschließlich ContactHub-eigener Daten muss vorab ausdrücklich aktiviert und bestätigt werden.
* Firmen- und Lieferadressbereiche verwenden Plus und Minus statt eines Dreiecks; die Lieferadresse bleibt geschlossen, bis eine abweichende Adresse gewählt wird.

= 0.8.1 =
* Customers can choose their own unique WordPress username during registration.
* Company details are optional and shown in a collapsed section; form section headings now use the active theme's h3 styling inside each panel.
* Administrators can select which non-privileged WordPress roles customers may request. A requested role is never assigned automatically and requires both email confirmation and administrator approval.
* Pending registrations show the requested role and safely prevent approval if the role is no longer allowed or the email address is unconfirmed.
* Every entered VAT ID is validated through EU VIES on the form and again securely on submission; successful verification is stored on the new account.
* The optional shipping address remains collapsed until the customer explicitly indicates that it differs from the billing address.
* Administrators can edit the submission message, double-opt-in subject and message, confirmation message, redirect page and redirect delay.
* Double-opt-in messages support the placeholders {first_name}, {site_name} and {confirmation_link}; a missing confirmation link is appended automatically.

= 0.8.0 =
* Added a dedicated Settings page for the configurable business-customer registration workflow.
* Added the public [contacthub_registration] shortcode with account, contact, company, billing, shipping, VAT ID and consent fields.
* Added separate email confirmation, optional marketing double opt-in and administrator approval states.
* Added a protected pending-registration list with approve, reject and resend-confirmation actions.
* Approved registrations receive only the administrator-configured non-administrator WordPress role; visitors can never choose roles, B2B groups, discounts or tax privileges.
* Registration data is mapped to the WordPress account and the existing WooCommerce, German Market and B2B Market profile fields without changing historical orders or invoices.
* Added configurable privacy, terms, success-page and notification settings plus integration diagnostics.
* The selected ContactHub registration page can optionally replace the public standard WordPress registration while preserving the administrative login.

= 0.7.2 =
* List controls use a light grey default appearance and a dark grey hover state; green and red status indicators keep their semantic colours.
* All table cells are vertically centred.
* Email confirmation and marketing status are grouped closely in one column.
* Tag suggestions are restricted to Groundhogg tags and a selected tag is saved immediately without an additional Add button.
* WordPress roles use a separate field and an independent suggestion list containing roles only.
* Permanent tag deletion was removed from the person detail view; removing a tag from an individual contact remains available.
* Tags now have their own unbranded contact section; Groundhogg's automatically mirrored WordPress-role and opt-in-status tags are hidden there so roles and user-managed tags remain visibly separate.

= 0.7.1 =
* Stift und Auge stehen bei Personen in einer eigenen Aktionsspalte direkt nach Vor- und Nachname; beide besitzen einen mittelgrauen Hintergrund und weiße Symbole.
* Eine zusätzliche mittelgraue Statusanzeige vor dem Marketable-Symbol zeigt den aktuellen Groundhogg-Bestätigungsstatus der E-Mail-Adresse als Häkchen oder Querstrich.
* Das PDF-Symbol der Bestellliste erscheint rahmenlos direkt neben dem Auge in derselben Aktionsspalte.
* Die Broadcast-Liste ist kompakter und wird mit höchstens 20 Einträgen pro Seite sowie Vor-/Zurück-Navigation angezeigt.
* Groundhogg-Tags lassen sich in einem gemeinsamen Suchfeld mit Vorschlägen finden, neu erstellen, zuweisen und direkt als Tag-Chip von der Person entfernen.
* Das dauerhafte Löschen eines Groundhogg-Tags bleibt als getrennte, ausdrücklich zu bestätigende Aktion erhalten.
* WordPress-Rollen werden für Administratoren ebenfalls als durchsuchbare Mehrfachauswahl mit direkt entfernbaren Rollen-Chips verwaltet; die bestehenden Schutzregeln bleiben aktiv.

= 0.7.0 =
* Pluginname, Hauptmenü, technischer Plugin-Ordner, Hauptdatei, Seitenschlüssel, Textdomain und interne Präfixe wurden vollständig auf ContactHub umgestellt.
* ContactHub wird im ersten Block des WordPress-Adminmenüs unmittelbar nach Avada angezeigt.
* Ist Avada nicht aktiv, erscheint ContactHub ersatzweise am Ende des ersten Adminmenüblocks.
* In der Personenliste ersetzen ein Stift- und ein Auge-Symbol die bisherigen Textlinks „Bearbeiten“ und „Ansehen“; zugängliche Beschriftungen und Hinweise bleiben erhalten.

= 0.6.6 =
* Das Veranstaltungs-Pop-up besitzt nun zwei Spalten: links kompakte Termins-, Orts- und Ticketdaten, rechts Bild, Titel, Kurz- und Langinfo.
* Beginn und Ende werden getrennt angezeigt; Veranstaltungsadressen werden aus strukturierten Feldern aufgebaut, sodass kein HTML-Code mehr als Text erscheint.
* Verkaufte und verfügbare Tickets werden gemeinsam mit der prozentuellen Auslastung dargestellt; unbegrenzte Kontingente werden eindeutig bezeichnet.
* Augen-Symbole erscheinen ohne Button-Rahmen und stehen in der Veranstaltungsliste direkt nach dem Titel; ihre Spaltenüberschrift ist nur für Screenreader vorhanden.
* Bestelltabellen verwenden die Reihenfolge Bestellnummer, Datum, Rechnungsnummer, Aktionen, Status, Zahlart und Summe.
* Unter der Summenspalte wird die Gesamtsumme der angezeigten Bestellungen ausgegeben; unterschiedliche Währungen werden getrennt summiert.
* In der Personenliste stehen „Bearbeiten“ und „Anzeigen“ nun unmittelbar unter dem Nachnamen.

= 0.6.5 =
* ContactHub und erster Unterpunkt öffnen jetzt standardmäßig die Personenverwaltung; Firmen stehen – sofern Groundhogg Companies aktiv ist – an zweiter Stelle.
* Bestellungen zeigen Rechnungsnummer und Zahlart sowie kompakte Aktionssymbole.
* Das Auge öffnet Bestellpositionen, Adressen, Status, Summen und Zahlungsart in einem WooCommerce-orientierten Pop-up.
* Das Dokumentsymbol übernimmt die vorhandene German-Market-Adminaktion und öffnet das echte Rechnungs-PDF im Pop-up; der fehleranfällige Kundenkonto-Link wurde entfernt.
* Veranstaltungszeilen besitzen eine Vorschau mit Datum, Ort, Kurzinfo, Bild sowie gebuchten und verfügbaren Tickets.
* WordPress-Rollen werden ausschließlich Administratoren angezeigt und können als Mehrfachzuordnung sicher bearbeitet werden.
* Administratoren können ein WordPress-Konto mit sicherem Zufallspasswort und optionaler Einladung anlegen oder ein bereits unter derselben E-Mail vorhandenes Konto kontrolliert verknüpfen.
* Rollen- und Kontoverknüpfungsänderungen werden am Groundhogg-Kontakt protokolliert; eine Marketingzustimmung wird niemals automatisch gesetzt.

= 0.6.0 =
* Die Zahl der besuchten Veranstaltungen kann in der Personenliste auf- und absteigend sortiert werden.
* Vorname und Nachname werden wieder in getrennten Spalten angezeigt; „Bearbeiten“ und „Anzeigen“ stehen gemeinsam darunter über beide Namensspalten.
* German-Market-Rechnungen werden in einem Pop-up mit zusätzlicher Ausweichmöglichkeit für ein neues Browserfenster geöffnet.
* Neue Personen können direkt aus der Übersicht als unbestätigte Groundhogg-Kontakte angelegt werden; vorhandene E-Mail-Adressen werden nicht überschrieben.
* Groundhogg-Tags können bei einer Person zugewiesen, entfernt sowie neu erstellt werden.
* Tags können nach ausdrücklicher Warnung dauerhaft gelöscht werden; Groundhogg-Status-Tags sind vor dem Löschen geschützt.
* Neuer Tab „Broadcasts“ mit Versandzeitpunkt, Öffnungs- und Klickstatus sowie kontaktbezogener E-Mail-Vorschau im Pop-up.
* Die Feldabstände im linken Stammdatenblock wurden verkleinert.

= 0.5.0 =
* Auswahlkästchen und eine Massenbearbeitung für die sichtbaren Personen ergänzt.
* „Marketable aktivieren“ kann für bis zu 100 ausgewählte Kontakte gemeinsam ausgeführt werden.
* Vor der Änderung müssen Herkunft, Datum und die ausdrückliche Zustimmung für jede ausgewählte Person bestätigt werden.
* Bereits marketable Kontakte bleiben unverändert; Abmeldungen, unzustellbare Adressen, Spam-, Beschwerde- und Sperrstatus werden automatisch geschützt und ausgelassen.
* Jede erfolgreiche Änderung wird mit Datum, Herkunft, Bearbeiter und optionaler Notiz am Groundhogg-Kontakt protokolliert.

= 0.4.9 =
* Die Spaltenüberschriften „Bestellungen“, „Bestellsumme“ und „Besuchte Veranstaltungen“ wurden zu „Best.“, „Summe“ und „Events“ verkürzt.
* Vor- und Nachname erscheinen gemeinsam und ohne Zeilenumbruch; die Aktionen „Bearbeiten“ und „Anzeigen“ stehen zusammen darunter.
* Der zusätzliche Filter „Event-Besucher“ zeigt ausschließlich Personen mit mindestens einer tatsächlich eingecheckten Veranstaltung.

= 0.4.8 =
* Optionale Integration von The Events Calendar und Event Tickets Plus ergänzt.
* Die Personenliste zeigt die Anzahl eindeutig besuchter Veranstaltungen anhand tatsächlich erfasster Check-ins.
* Mehrere Tickets derselben Person für dieselbe Veranstaltung werden nur einmal gezählt.
* Die Personendetailansicht erhält einen Tab „Veranstaltungen“ mit Termin, Buchungsstatus und Verknüpfung zur WooCommerce-Bestellung.
* Gebuchte und tatsächlich besuchte Veranstaltungen werden klar unterschieden.
* Fehlen die Veranstaltungs-Plugins, werden Spalte und Tab automatisch ausgeblendet.

= 0.4.7 =
* Groundhogg Companies ist für die Personenverwaltung keine zwingende Voraussetzung mehr.
* Fehlt die Companies-Erweiterung, werden Firmenmenü, Firmenspalte, Firmensortierung und Firmenblock automatisch deaktiviert.
* Personen, Marketingzustimmungen, WooCommerce-Adressen, Bestellungen, Umsätze, Rechnungslinks und Aktivitäten bleiben weiterhin verfügbar.
* Firmen-Schreibzugriffe werden bei fehlender Companies-Erweiterung sicher abgewiesen.

= 0.4.6 =
* Filter „Alle“ und „Besteller“ sowie die Sortierung der Personenliste repariert.
* Groundhogg-Kontakte werden für Filter und Sortierung verlässlich in unterstützten Teilmengen geladen.
* Bestellungen werden gemeinsam und seitenweise über die offizielle WooCommerce-Abfrage ausgewertet, statt eine vollständige Einzelabfrage je Person auszuführen.
* Zuordnung zuerst über die verknüpfte WooCommerce-Kundennummer und zusätzlich über Groundhogg-, Benutzer- und Rechnungs-E-Mail.
* Mehrfach gefundene Bestellungen werden anhand ihrer Bestell-ID entdoppelt; Rückerstattungen werden von der Bestellsumme abgezogen.
* Die sichtbare Überschrift der Marketingstatus-Spalte wurde entfernt; eine Bezeichnung für Screenreader bleibt erhalten.

= 0.4.5 =
* Personen können neben der Namenssuche mit „Alle“ oder „Besteller“ gefiltert werden.
* Vorname, Nachname, Firma und Bestellsumme sind auf- und absteigend sortierbar.
* Der Marketingstatus erscheint in der Personenliste kompakt als grüner Daumen hoch beziehungsweise roter Daumen runter; der genaue Status bleibt als barrierearme Beschriftung erhalten.
* Die Firmenübersicht besitzt eine Schnellsuche nach Firmennamen.
* Auch bei Firmen ersetzen „Bearbeiten“ und „Anzeigen“ den bisherigen Öffnen-Button.
* „Anzeigen“ öffnet eine schreibgeschützte Firmenansicht.

= 0.4.2 =
* Marketing und Zustimmung in der Personenansicht in die linke Spalte vor den Firmenblock verschoben.
* WooCommerce-Adressen, Bestellungen und Rechnungen sowie Aktivitäten in einem gemeinsamen, barrierearm bedienbaren Tab-Block zusammengefasst.
* Personenübersicht um Bestellanzahl und Bestellsumme ergänzt; stornierte, fehlgeschlagene und Checkout-Entwürfe werden nicht mitgezählt.
* Der bisherige Öffnen-Button wurde durch die WordPress-typischen Zeilenaktionen „Bearbeiten“ und „Anzeigen“ ersetzt.
* „Anzeigen“ öffnet eine schreibgeschützte Personenansicht; über „Bearbeiten“ kann in den Bearbeitungsmodus gewechselt werden.

= 0.4.1 =
* Telefonnummern bei Firmen, Personen und WooCommerce-Rechnungsadressen in europäische Ländervorwahl und nationale Nummer aufgeteilt.
* Österreich ist vorausgewählt; eingegebene internationale Vorwahlen werden automatisch erkannt.
* Österreichische Mobilfunk- und Ortsvorwahlen werden zur Anzeige lesbar abgesetzt, während ein einheitlicher Wert gespeichert bleibt.
* WooCommerce-Rechnungs- und Lieferadressen können im Personenkonto angelegt und bearbeitet werden.
* Adressänderungen betreffen ausschließlich das WooCommerce-Kundenprofil und niemals bestehende Bestellungen oder Rechnungen.

= 0.4.0 =
* Personenübersicht mit Suche, Seitennavigation, Firmenzuordnung und Groundhogg-Marketingstatus ergänzt.
* Personen-Detailansicht mit bearbeitbaren Stammdaten, Firmenlink, UID, Bestellungen, Rechnungen, Adressen, Tags und Kontaktnotizen ergänzt.
* Marketingzustimmung kann nach ausdrücklicher Bestätigung manuell erteilt oder widerrufen werden.
* Die Änderung setzt den bestehenden Groundhogg-Opt-in-Status und wird mit Datum, Herkunft, Bearbeiter und optionaler Notiz protokolliert.
* Erfolgreich über EU-VIES geprüfte UID-Nummern erhalten ein dauerhaftes grünes Häkchen.

= 0.3.0 =
* UID-Feld mit den vorhandenen Benutzerfeldern von German Market (`billing_vat`) und B2B Market (`b2b_uid`) verknüpft.
* Beide vorhandenen Felder werden beim Speichern synchron gehalten; es wird keine dritte UID-Kopie angelegt.
* Manuelle UID-Prüfung über den offiziellen EU-VIES-Dienst ergänzt.

= 0.2.0 =
* Firmenname, Website, Telefon, Branche, Firmenadresse und Beschreibung können direkt bearbeitet werden.
* Sichere Speicherung über die offizielle Groundhogg-Companies-Ability mit Berechtigungs- und Nonce-Prüfung.
* Rechnungsdaten bleiben zum Schutz historischer Belege schreibgeschützt.

= 0.1.1 =
* Firmen, Metadaten und Ansprechpartner werden über die offizielle WordPress-Abilities-Schnittstelle von Groundhogg Companies 3.5.0 gelesen.
* Bestellungen mehrerer Ansprechpartner werden zusammengeführt.
* Firmennotizen erscheinen im Aktivitätenbereich.

== Sicherheit und Datenhaltung ==

* Im Groundhogg-Modus werden keine eigenen Kundentabellen angelegt; die vorbereiteten chb_-Tabellen entstehen erst durch eine spätere ausdrücklich gestartete Standalone-Einrichtung oder Migration.
* Keine Änderung an Groundhogg-Flows.
* Schreibzugriff ausschließlich auf freigegebene Firmen- und Personenfelder, den Groundhogg-Opt-in-Status sowie vorhandene UID-Felder.
* Bestellungen werden über die WooCommerce-API gelesen.
* Rechnungen werden über die bestehende German-Market-Adminaktion geöffnet.
* Historische Rechnungen werden weder neu erzeugt noch verändert.
* Veranstaltungsdaten und Check-ins werden ausschließlich gelesen und nicht kopiert oder verändert.

== Installation ==

Die ZIP-Datei über Plugins > Installieren > Plugin hochladen einspielen und zunächst in einer kontrollierten Umgebung prüfen.

