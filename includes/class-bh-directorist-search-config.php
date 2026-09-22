<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only bridge to Directorist's Search Form Builder configuration.
 *
 * This class never writes to Directorist settings. It reads the configured
 * search-form fields so Bubba Hub can stay visually/custom-query driven while
 * Directorist remains the source of truth for directory search configuration.
 */
final class DirectoristSearchConfig {
    /**
     * Return the configured Builder field keys for the default directory.
     *
     * If Builder data has not yet been saved, fall back to Directorist's
     * standard search-field option so the Bubba Hub search does not silently
     * lose Category/Location controls on a fresh installation.
     *
     * @return array<string>
     */
    public static function fields(): array {
        if (function_exists('directorist_get_default_directory')) {
            $directory_id = (int) directorist_get_default_directory();

            if ($directory_id > 0) {
                $search_form = get_term_meta($directory_id, 'search_form_fields', true);

                if (is_array($search_form) && array_key_exists('fields', $search_form) && is_array($search_form['fields'])) {
                    return self::normalise_fields($search_form['fields']);
                }
            }
        }

        $defaults = function_exists('get_directorist_option')
            ? get_directorist_option('search_tsc_fields', ['search_text', 'search_category', 'search_location'])
            : ['search_text', 'search_category', 'search_location'];

        return is_array($defaults) ? array_values(array_unique(array_map('sanitize_key', $defaults))) : [];
    }

    public static function has(string $field): bool {
        return in_array(sanitize_key($field), self::fields(), true);
    }

    /**
     * Resolve the common Directorist Builder fields without replacing
     * Bubba Hub's existing search UI or query.
     *
     * @return array{search: bool, category: bool, location: bool}
     */
    public static function basic_fields(): array {
        $fields = self::fields();

        return [
            'search' => self::contains_any($fields, ['search_text', 'text', 'keyword']),
            'category' => self::contains_any($fields, ['search_category', 'category']),
            'location' => self::contains_any($fields, ['search_location', 'location']),
        ];
    }

    /**
     * @param array<int, mixed> $fields
     * @return array<string>
     */
    private static function normalise_fields(array $fields): array {
        $keys = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            foreach (['original_widget_key', 'widget_key', 'field_key'] as $candidate) {
                if (!empty($field[$candidate]) && is_string($field[$candidate])) {
                    $keys[] = sanitize_key($field[$candidate]);
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @param array<string> $fields
     * @param array<string> $aliases
     */
    private static function contains_any(array $fields, array $aliases): bool {
        return (bool) array_intersect($fields, $aliases);
    }
}
