<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

/**
 * Read-only access to listing data owned by Directorist.
 *
 * Bubba Hub does not create a second source of truth for these fields.
 */
final class DirectoristFields {
    private const PRICE_META = '_price';

    public static function get(int $post_id, string $field, mixed $default = null): mixed {
        if (!Listing::is_listing($post_id)) {
            return $default;
        }

        $definition = self::definition($field);
        if (!$definition) {
            return $default;
        }

        $meta_key = self::meta_key_from_definition($definition);
        if ($meta_key === '') {
            return $default;
        }

        $value = get_post_meta($post_id, $meta_key, true);

        return ($value === '' || $value === false || $value === null) ? $default : $value;
    }

    public static function meta_key(string $field): string {
        if ($field === 'price') {
            return self::PRICE_META;
        }

        $definition = self::definition($field);
        return $definition ? self::meta_key_from_definition($definition) : '';
    }

    /**
     * Return a configured Directorist field by its label.
     *
     * Directorist stores custom-field keys and options in the directory
     * builder. Bubba Hub reads that configuration rather than maintaining
     * duplicate field definitions.
     */
    public static function definition(string $field): array {
        $labels = [
            'age_range' => ['age range'],
            'term_time' => ['term time', 'term time only'],
            'free_activity' => ['free activity'],
        ];

        if (empty($labels[$field])) {
            return [];
        }

        $directory_id = self::default_directory_id();
        if ($directory_id <= 0 || !function_exists('directorist_get_listing_form_fields')) {
            return [];
        }

        $form_fields = directorist_get_listing_form_fields($directory_id);
        if (!is_array($form_fields)) {
            return [];
        }

        foreach ($form_fields as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            $label = sanitize_title((string) ($config['label'] ?? ''));
            foreach ($labels[$field] as $candidate) {
                if ($label === sanitize_title($candidate)) {
                    $config['field_key'] = !empty($config['field_key'])
                        ? sanitize_key((string) $config['field_key'])
                        : sanitize_key((string) $key);
                    return $config;
                }
            }
        }

        return [];
    }

    /**
     * @return array<string, array<string,string>>
     */
    public static function options(string $field): array {
        $definition = self::definition($field);
        if (!$definition || empty($definition['options']) || !is_array($definition['options'])) {
            return [];
        }

        $options = [];
        foreach ($definition['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }

            $value = isset($option['option_value']) ? (string) $option['option_value'] : '';
            $label = isset($option['option_label']) ? (string) $option['option_label'] : $value;

            if ($value === '') {
                continue;
            }

            $options[$value] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    public static function free_activity_option(): ?array {
        $options = self::options('free_activity');
        if (!$options) {
            return null;
        }

        return reset($options) ?: null;
    }

    public static function price_value(mixed $value): ?float {
        if ($value === '' || $value === null || is_array($value)) {
            return null;
        }

        $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function default_directory_id(): int {
        if (function_exists('directorist_get_default_directory')) {
            return (int) directorist_get_default_directory();
        }

        if (function_exists('get_directorist_option')) {
            return (int) get_directorist_option('default_directory', 0);
        }

        return 0;
    }

    private static function meta_key_from_definition(array $definition): string {
        $key = !empty($definition['field_key']) ? sanitize_key((string) $definition['field_key']) : '';
        return $key !== '' ? '_' . $key : '';
    }
}
