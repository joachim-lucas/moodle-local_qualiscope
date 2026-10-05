## QualiScope — ChangeLog

### v1.0.5 (2026100502)

- Evidence: uploading several files at once keeps every file instead of one, and a check result can be evidenced by a link alone. A file uploaded today is attached to its evidence record rather than to the result row, and an upgrade step moves the files attached by earlier versions.
- Evidence: the privacy export and the indicator report read the file from the same place, so an uploaded file is no longer listed as missing.
- Actions: the due date is shown in the timezone of the user. The edit form prefilled the date input with `date()`, which formats in the timezone of the server, while the value was stored at midnight in the timezone of the user: a user east of the server opened the form on the day before, and saving it again moved the due date one day earlier on every save.
- Campaigns: the coverage of each campaign is aggregated in SQL instead of loading every result row into memory. The course picker is now the standard Moodle course element, so it searches instead of listing every course, and the front page is left out.
- Campaigns: a campaign whose scope is a list of categories or of courses is no longer accepted empty. The two pickers also write into separate parameters, they used to share one `scopeids[]`, where a category id and a course id could not be told apart.
- Campaigns: the scope switch reveals the picker of the chosen scope. It bound only the `change` event and its `forEach` callback had no `this`, so the two pickers were never revealed and the form could not be filled.
- Privacy: deleting a campaign creator anonymises their campaigns (`userid = 0`) instead of deleting them, which destroyed the audit trail of everyone else involved. The results, actions and evidence of the other users are kept.
- Pages: the page context is set before the page title on every page of the plugin, and a course or section summary left empty is no longer passed to `strlen()`.
- Translations: the hardcoded labels of the analysis reports, the archive and the accessibility checks are now language strings (437 in English and in French). The names of the entries inside the generated archives are left untranslated on purpose.
- Documentation: the Requirements section states the Moodle and PHP versions `version.php` actually asks for. It asked for Moodle 5.2 while the plugin supports 4.5, and for PHP 8.2 while Moodle 4.5 runs on 8.1. A test now compares the section with `$plugin->requires`.

### v1.0.4 (2026100500)

- Web services: all four functions extend `\core_external\external_api`. The global `\external_api` alias, removed from core, made every AJAX call of the campaign analysis fail fatally, so the campaign was closed empty, with no result recorded.
- Progress: a refused or failing course is no longer silently counted as analysed. The bar turns red, the title and the detail report how many courses failed and how many were skipped, and the cause is written to the browser console.
- Progress: the bar drops its waiting colour before the final colour is applied.

### v1.0.3 (2026093002)

- Rendering: course, campaign and section names are displayed and exported as plain text, in the current language. A name stored with the multilang filter no longer shows its `<span lang="…">` tags in the Excel workbooks, the PDFs, the ZIP archive or the interface.
- Export: the name of the downloaded file is built from the resolved course name, it no longer contains any HTML tag.

### v1.0.2 (2026093000)

Fixes coming out of the code review before the Marketplace submission.

- Security: the capability and the owning course of the record are checked before saving a corrective action and before attaching an evidence.
- Security: evidences are uploaded through a Moodle form with restricted file types and size; the files are no longer served inline.
- Security: a session key is now required on every write (campaign analyses, exports).
- Security: the user evidence file export no longer includes other users' files.
- Privacy: the provider no longer deletes site-wide data when a context is purged; it acts only on the context it receives, handles context paths and implements the userlist interface.
- Privacy: the data of a deleted course is removed (results, actions, evidences and quota).
- Web services: argument order of `create_action` fixed, the function was unusable.
- Web services: the campaign progress page uses an external service and `core/ajax` instead of a hand-rolled XHR call.
- Rendering: the remaining pages go through Mustache templates and the output API.
- Rendering: the printable report is rendered inside the theme, its rules living in `styles.css`.
- Internationalisation: no more hardcoded user-facing text, French included.
- Headers: the hook files now use the standard Moodle file header.
- Input parameters: the evidence owner, due date and annotation now use precise types instead of `PARAM_RAW`.
- Excel export: built with Moodle's Excel library, no temporary file outside `moodledata`.
- Settings: the admin page is no longer built for users who cannot reach it.
- Permissions: the `local/qualiscope:managechecks` capability, which gated no operation, is removed.

### v1.0.1 (2026092500)

- Audit campaign management: advanced filters, instant search, real-time progress bar.
- Campaign comparison and time tracking of the results.
- Consolidated corrective action plan (CAPA) with bulk creation of actions.
- Audit report export to Excel, CSV, PDF and HTML print; auditor evidence pack (PDF + ZIP).
- Multi-course macro analysis by criteria and indicators, highlighting the priority weak points.
- Referentials: Qualiopi (refined indicators), IACET, ISO 21001; a help page per standard.
- Indicators 1 and 26 strengthened (WCAG accessibility), non-binary compliance scoring with constructive alignment and engagement analysis.
- Built-in offline-signed licence (licence key, site hash, expiry, free course quota).
- Internationalisation: full English translation of the criteria, indicators and checks; localisation helpers loaded automatically.
- Moodle 4.5 compatibility: HTML rendering of the course summary, Bootstrap 4 card backgrounds and widgets, selection chevron.
- CI: code checking (codechecker, stylelint) on the Moodle 5.2 and 4.5 CI matrices, first release.
