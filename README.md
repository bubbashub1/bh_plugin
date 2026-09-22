# Bubba Hub

Bubba Hub is a family platform built on top of WordPress.

## Architecture

### Free Directorist = directory foundation
Directorist remains responsible for:
- listings
- directory categories and directory fields
- search and filtering
- locations/maps
- listing submission and editing
- directory archive and single-listing presentation

### Bubba Hub plugin = family platform layer
This plugin is responsible for Bubba Hub-specific functionality:
- My Bubba Hub
- family and child profiles
- favourites and saved activities
- family planner
- booking workflow
- payment integration
- Leader Space
- Bubba Hub notifications and workflows

## Compatibility rule

The Bubba Hub core must not require Directorist Pro or other paid Directorist extensions.

Where Directorist provides a feature in its free plugin, use Directorist rather than recreating it.

Where a Bubba Hub feature is not provided by free Directorist, implement it in this plugin or integrate with an already-installed WordPress plugin.

## Installed integrations

The target site currently uses:
- Advanced Custom Fields PRO
- Ultimate Member
- GetPaid
- GetPaid Stripe Payments
- GetPaid Wallet
- Ninja Forms
- ACF OpenStreetMap Field
- Add to Home Screen & Progressive Web App

## Directory migration baseline

The existing Directorist JSON is the source baseline for the directory field and search configuration.

The ownership map is documented in `docs/directorist-field-map.md`.

Key rules:
- Directorist remains the single directory/listing engine.
- ACF may provide structured timetable data attached to Directorist listings.
- Bubba Hub must not create a duplicate listing/location engine.
- Directorist booking/payment is not a dependency.
- Target timezone is Europe/London.
- Existing age values should be preserved during migration.

## Stability rules

1. No modification of Directorist core files.
2. No duplicate directory/listing post type in Bubba Hub.
3. Small modules with one responsibility.
4. No front-end JavaScript framework unless genuinely required.
5. Staging first.
6. One logical change per deployment.
7. Existing site functionality must not be removed as part of the foundation migration.
8. Configuration/data migrations must be backed up and tested before application.

## Stability Step 2

The `Listing` bridge is deliberately read-only. It:
- recognises Directorist listings through the native `at_biz_dir` post type;
- reads optional ACF values without making ACF a hard dependency;
- supports both `weekly_schedule` and the existing `group_business_hours_repeater` timetable field names;
- provides field aliases for the original directory data model.

No listings, taxonomies, fields, or Directorist settings are created or rewritten by this step.
