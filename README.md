# QualiScope

QualiScope is a quality audit plugin for Moodle (local plugin). It helps training organisations
assess whether their Moodle platform matches quality referentials such as **Qualiopi**, **ISO 21001**
or **IACET**.

The plugin guides auditors through **referentials, criteria, indicators and checks**, tracks
corrective **actions**, collects **evidence**, and exports audit dossiers.

## Features

- Structured audit engine: referentials → criteria → indicators → checks.
- Automatic checks against Moodle course data (activities, resources, completion, gradebook,
  feedback, course fields, WCAG accessibility).
- Campaign management: scope courses/categories/organisations, run and re-run analyses.
- Corrective action plan with responsible, priority and due dates.
- Evidence collection: Moodle traces and external annotations/URLs.
- Exports: printable HTML audit report, PDF, XLSX, CSV and a complete ZIP evidence dossier.
- Dashboard with cross-course and cross-campaign comparisons.
- Multi-language interface (English and French) and outreach to language packs on AMOS.

## Requirements

- Moodle 5.2 or later (see [version.php](version.php)).
- PHP 8.2+.
- No third-party library required.

## Installation

1. Copy the `qualiscope` folder into `local/` (`/local/qualiscope`) of your Moodle installation.
   The plugin folder must be named `qualiscope`.
2. Log in as an administrator and go to *Site administration → Notifications* to complete the
   installation and upgrade.
3. The default referentials (`referentials/*.json`) are automatically seeded on first access.

## Usage

- **Referentials**: manage quality referentials, criteria and indicators.
- **Campaigns**: create an audit campaign, choose its scope, run the analysis per course and
  review the results per criterion and indicator.
- **Actions**: plan corrective actions and track their completion.
- **Evidence**: attach Moodle-based or external evidence to any check result.
- **Reports**: generate a full audit dossier (PDF, XLSX, ZIP) from a course or campaign.

## Privacy

QualiScope is compliant with the Moodle Privacy API. It only stores the `userid` of campaign
creators, evidence uploaders and action creators, plus the free text entered in the audit
(annotations, descriptions). Users can export or delete their personal data through
*Site administration → Users → Privacy and policies*.

## Development

This plugin follows the Moodle coding guidelines. Run the standard checks with a checkout of
[`moodle-plugin-ci`](https://moodle.org/plugins/plugin.php?plugin=local_moodleplugin) or the
GitHub Actions workflow provided in `.github/workflows/moodle-ci.yml`.

To rebuild the AMD JavaScript modules:

```sh
cd /path/to/moodle
npm install
grunt amd --root=local/qualiscope
```

The generated files `amd/build/*.min.js` are committed; sourcemaps (`*.min.js.map`) are ignored.

## Testing

PHPUnit tests live in `tests/` and cover the analysers, the referral seeder, the XLSX writer and
the privacy provider. Run them with:

```sh
vendor/bin/phpunit --testsuite local_qualiscope_testsuite
```

## License

QualiScope is licensed under the GNU GPL v3 or later (see [LICENSE.txt](LICENSE.txt)).