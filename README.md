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
- venues
- classes/sessions
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

## Stability rules

1. No modification of Directorist core files.
2. No duplicate directory/listing post type in Bubba Hub.
3. Small modules with one responsibility.
4. No front-end JavaScript framework unless genuinely required.
5. Staging first.
6. One logical change per deployment.
7. Existing site functionality must not be removed as part of the foundation migration.
