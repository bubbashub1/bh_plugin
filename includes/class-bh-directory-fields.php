<?php
namespace BubbaHub;

defined('ABSPATH') || exit;

final class DirectoryFields {
    public static function definitions(): array {
        return [
            'age_range' => [
                'label' => 'Age Range',
                'source' => 'directorist',
                'aliases' => ['age_range'],
                'values' => ['0-3','3-6','6-9','9-12','1-3','2-4','3-5','5-plus','all'],
            ],
            'region' => ['label'=>'Region','source'=>'directorist','aliases'=>['region']],
            'town' => ['label'=>'Town','source'=>'directorist','aliases'=>['town','location']],
            'category' => ['label'=>'Category','source'=>'directorist','aliases'=>['category']],
            'day' => ['label'=>'Day','source'=>'acf_weekly_schedule','aliases'=>['day','day_name']],
            'term_time' => ['label'=>'Term Time','source'=>'directorist','aliases'=>['term_time']],
            'price' => ['label'=>'Price','source'=>'directorist','aliases'=>['_price']],
            'session_length' => ['label'=>'Session Length','source'=>'acf_weekly_schedule','aliases'=>['session_length']],
            'location' => ['label'=>'Location','source'=>'directorist','aliases'=>['location','address']],
        ];
    }

    public static function get(int $post_id, string $key, mixed $default = null): mixed {
        if (!Listing::is_listing($post_id) || !isset(self::definitions()[$key])) {
            return $default;
        }

        $source = self::definitions()[$key]['source'] ?? '';
        if ($source === 'directorist' && in_array($key, ['age_range', 'term_time', 'price'], true)) {
            return DirectoristFields::get($post_id, $key, $default);
        }

        foreach (self::definitions()[$key]['aliases'] as $field) {
            $value = self::get_meta_or_acf($post_id, $field);
            if ($value !== null && $value !== false && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private static function get_meta_or_acf(int $post_id, string $field): mixed {
        if (function_exists('get_field')) {
            $value = get_field($field, $post_id);
            if ($value !== null && $value !== false && $value !== '') return $value;
        }
        $value = get_post_meta($post_id, $field, true);
        return ($value === '' || $value === false) ? null : $value;
    }

    public static function searchable_keys(): array {
        return ['age_range','region','town','category','day','term_time','location','price'];
    }
}
