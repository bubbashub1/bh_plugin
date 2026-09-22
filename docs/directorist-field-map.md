# Directorist / Bubba Hub migration map

This is the controlled mapping used by the `bh_plugin` foundation.

| Original directory data | Owner | Migration treatment |
|---|---|---|
| title, content | Directorist | Native listing title/description |
| category, tags | Directorist | Native taxonomy fields |
| address, postcode, map, lat, long | Directorist | Native location/map data |
| price | Directorist | Native listing field |
| email, phone, website | Directorist | Native contact fields |
| facebook, instagram | Directorist | Native/social fields where configured |
| image_url / image | Directorist | Native listing image/featured image |
| age_range | Directorist | Preserve existing values |
| region / town | Directorist | Use existing Directorist location/taxonomy configuration; do not create a duplicate BH location engine |
| day / term_time / session_length | Directorist where available; ACF for structured timetable | Preserve searchable listing values; keep detailed repeating timetable in ACF |
| weekly_schedule | ACF attached to Directorist listing | Read through the BH Listing bridge |
| group_business_hours_repeater | ACF legacy/compatibility field | Read during migration; do not duplicate data automatically |
| booking records | Bubba Hub | Later integration with GetPaid/Stripe/Wallet |
| child/bump/family profiles | Bubba Hub | Separate family data |
| planner/saved items | Bubba Hub | Separate family data |

## Existing age values

Preserve the existing values, including:
- 0-3
- 3-6
- 6-9
- 9-12
- 1-3
- 2-4
- 3-5
- 5-plus
- all

Pregnancy/Postnatal should not be silently converted into an age value. If a separate family-stage field is introduced, that is a later controlled migration.

## Timetable structure

The preferred ACF structure remains:

- day
- closed/open
- session name (optional)
- start time
- end time
- frequency
- term-time-only
- optional notes

The timetable remains attached to the Directorist listing and must not become a separate post type.

## Step 2 safety boundary

Step 2 is a read-only compatibility layer. It does not:
- edit Directorist core;
- create a second listing post type;
- create a second location system;
- bulk rewrite existing listings;
- change Directorist settings;
- require Directorist Pro.

Any data migration is a later, separately tested step.
