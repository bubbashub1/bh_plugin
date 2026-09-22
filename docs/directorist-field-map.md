# Bubba Hub Directorist Field Map

This document records the ownership boundary for the existing Bubba Hub directory configuration.

## Core Directorist fields

Keep these in Directorist because they describe and locate a directory listing:

- Listing title
- Description/content
- Category
- Tags
- Location
- Address
- Postcode
- Map / coordinates
- Price
- Image / featured image
- Email
- Phone
- Website
- Facebook
- Instagram
- Age Range
- Days
- Session Length
- Term Time Only
- SEN Friendly
- Search/filter configuration
- Radius/location search
- Listing archive and single-listing presentation
- Listing submission/editing
- Reviews
- Favourites/bookmarks
- Claim listing

## ACF / structured data

Use ACF only where structured data is needed that should not become a second directory engine.

Primary candidate:

### Weekly schedule / timetable

Recommended structure:

- Day
- Closed/open
- Session name (optional)
- Start time
- End time
- Frequency
- Term-time-only
- Optional notes

The schedule should remain attached to the Directorist listing. It must not create a second listing post type.

## Bubba Hub-owned data

The following belongs to the family platform rather than the directory listing itself:

- Child profiles
- Bump profiles
- Family preferences
- Saved family planner items
- Personal calendar selections
- Booking records/workflow
- Booking consent
- Payment state
- Leader Space workflows
- Notifications
- Family-specific recommendations/preferences

## Location rule

Directorist remains the single source of truth for directory location, address, map and radius search.

Do not create a second Bubba Hub location taxonomy for the same directory listings.

## Booking rule

Do not depend on Directorist Booking or paid Directorist booking/payment extensions.

Bubba Hub booking/payment will integrate with the existing GetPaid, Stripe and Wallet stack.

## Timezone

The supplied baseline contains America/New_York. Bubba Hub is UK-based, so the target configuration should be Europe/London.

This is a configuration change only; it is not being applied by this plugin yet.

## Age/family-stage note

The existing age values should be preserved during migration.

Pregnancy/Postnatal is conceptually different from an age range. If this is split later, it should be done as a controlled data migration rather than silently changing existing listings.

## Migration rule

Do not bulk rewrite or import the supplied configuration until the live/staging Directorist configuration has been backed up and verified.

One configuration change or migration step should be tested at a time.
