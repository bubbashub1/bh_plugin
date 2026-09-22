<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only bridge to Directorist's Search Form Builder configuration.
 *
 * This class never writes to Directorist settings. It only reads the
 * configured search-form fields so Bubba Hub can stay visually/custom-query
 * driven while Directorist remains the source of truth for directory search
 * field configuration.
 */
final class DirectoristSearchConfig {
    /**
     * Return the configured Builder field keys for the default directory.
     *
     * @return array<string>
     */
    public static function fields(): array {
        if (!function_exists('directorist_get_default_directory')) {
            return [];
        }

        $directory_id = (int) directorist_get_default_directory();
        if ($directory_id <= 0) {
            return [];
        }

        $search_form = get_term_meta($directory_id, 'search_form_fields', true);
        if (!is_array($search_form) || empty($search_form['fields']) || !is_array($search_form['fields'])) {
            return [];
        }

        $fields = [];

        foreach ($search_form['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = '';
            foreach (['original_widget_key', 'widget_key', 'field_key'] as $candidate) {
                if (!empty($field[$candidate]) && is_string($field[$candidate])) {
                    $key = sanitize_key($field[$candidate]);
                    break;
                }
            }

            if ($key !== '') {
                $fields[] = $key;
            }
        }

        return array_values(array_unique($fields));
    }

    public static function has(string $field): bool {
        return in_array(sanitize_key($field), self::fields(), true);
    }

    /**
     * Resolve the common Directorist Builder fields without assuming that
     * Bubba Hub's custom search UI must replace the Builder.
     *
     * @return array{search: bool, category: bool, location: bool}
     */
    public static function basic_fields(): array {
        $fields = self::fields();

        if (!$fields) {
            return [
                'search' => false,
                'category' => false,
                'location' => false,
            ];
        }

        return [
            'search' => self::contains_any($fields, ['search_text', 'text', 'keyword']),
            'category' => self::contains_any($fields, ['search_category', 'category']),
            'location' => self::contains_any($fields, ['search_location', 'location']),
        ];
    }

    /**
     * @param array<string> $fields
     * @param array<string> $aliases
     */
    private static function contains_any(array $fields, array $aliases): bool {
        return (bool) array_intersect($fields, $aliases);
    }
}
