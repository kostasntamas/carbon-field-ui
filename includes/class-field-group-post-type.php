<?php

namespace CFUI;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Field;

defined('ABSPATH') || exit;

/**
 * Registers the "Field Groups" admin screen and the Carbon Fields meta
 * boxes used to build a field group (its locations and its fields).
 */
class Field_Group_Post_Type
{

	const POST_TYPE = 'cfui_field_group';

	public static function init()
	{
		add_action('init', [__CLASS__, 'register_post_type']);
		add_action('carbon_fields_register_fields', [__CLASS__, 'register_builder_fields']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

		add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'columns']);
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
	}

	public static function enqueue_assets($hook)
	{
		if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
			return;
		}

		$screen = get_current_screen();
		if (! $screen || self::POST_TYPE !== $screen->post_type) {
			return;
		}

		wp_enqueue_script('cfui-admin', CFUI_URL . 'assets/admin.js', [], CFUI_VERSION, true);
	}

	public static function register_post_type()
	{
		register_post_type(self::POST_TYPE, [
			'labels'              => [
				'name'               => __('Field Groups', 'carbon-fields-ui'),
				'singular_name'      => __('Field Group', 'carbon-fields-ui'),
				'add_new'            => __('Add New', 'carbon-fields-ui'),
				'add_new_item'       => __('Add New Field Group', 'carbon-fields-ui'),
				'edit_item'          => __('Edit Field Group', 'carbon-fields-ui'),
				'all_items'          => __('Field Groups', 'carbon-fields-ui'),
				'not_found'          => __('No field groups yet. Add your first one.', 'carbon-fields-ui'),
				'menu_name'          => __('Field Groups', 'carbon-fields-ui'),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-screenoptions',
			'menu_position'       => 80,
			'supports'            => ['title'],
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_in_rest'        => false,
		]);
	}

	public static function columns($columns)
	{
		$new = [];
		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ('title' === $key) {
				$new['cfui_locations'] = __('Locations', 'carbon-fields-ui');
				$new['cfui_fields']    = __('Fields', 'carbon-fields-ui');
				$new['cfui_status']    = __('Status', 'carbon-fields-ui');
			}
		}

		return $new;
	}

	public static function column_content($column, $post_id)
	{
		if ('cfui_fields' === $column) {
			$fields = (array) carbon_get_post_meta($post_id, 'cfui_fields');
			echo esc_html(count($fields));
		}

		if ('cfui_locations' === $column) {
			$locations = (array) carbon_get_post_meta($post_id, 'cfui_locations');
			$labels    = array_filter(array_map([Field_Group_Renderer::class, 'describe_location'], $locations));
			echo esc_html($labels ? implode(', ', $labels) : '—');
		}

		if ('cfui_status' === $column) {
			if (Field_Group_Renderer::is_active($post_id)) {
				echo '<span style="color:#1a9c56;">●</span> ' . esc_html__('Active', 'carbon-fields-ui');
			} else {
				echo '<span style="color:#aaa;">●</span> ' . esc_html__('Inactive', 'carbon-fields-ui');
			}
		}
	}

	public static function register_builder_fields()
	{
		Container::make('post_meta', 'cfui-settings', __('Settings', 'carbon-fields-ui'))
			->where('post_type', '=', self::POST_TYPE)
			->add_fields([
				Field::make('checkbox', 'cfui_active', __('Active', 'carbon-fields-ui'))
					->set_default_value(true)
					->set_help_text(__('Inactive field groups are saved but not shown anywhere.', 'carbon-fields-ui')),
			]);

		Container::make('post_meta', 'cfui-locations', __('Locations: where to show these fields', 'carbon-fields-ui'))
			->where('post_type', '=', self::POST_TYPE)
			->add_fields([
				Field::make('complex', 'cfui_locations', __('Locations', 'carbon-fields-ui'))
					->set_layout('tabbed-vertical')
					->add_fields(self::get_location_fields())
					->set_header_template(self::get_location_header_template())
					->set_min(1),
			]);

		Container::make('post_meta', 'cfui-fields', __('Fields', 'carbon-fields-ui'))
			->where('post_type', '=', self::POST_TYPE)
			->add_fields([
				Field::make('complex', 'cfui_fields', __('Fields', 'carbon-fields-ui'))
					->set_layout('tabbed-vertical')
					->add_fields(self::get_field_definition_fields())
					->set_header_template(self::get_simple_label_header_template(__('New Field', 'carbon-fields-ui'))),
			]);
	}

	/**
	 * The complex field's row-tab label only has access to that row's raw
	 * values (e.g. a post type slug, not its label), so the slug => label
	 * lookups are embedded straight into the template as JS literals.
	 */
	private static function get_location_header_template()
	{
		$post_type_labels = wp_json_encode(self::get_post_type_options());
		$taxonomy_labels  = wp_json_encode(self::get_taxonomy_options());
		$template_labels  = wp_json_encode(self::get_template_options());
		$user_label       = esc_js(__('User Profile', 'carbon-fields-ui'));
		$new_label        = esc_js(__('New Location', 'carbon-fields-ui'));

		return "
			<% var postTypeLabels = {$post_type_labels}; %>
			<% var taxonomyLabels = {$taxonomy_labels}; %>
			<% var templateLabels = {$template_labels}; %>
			<%
				var label = '';
				if ( location_type === 'post_type' && post_type ) {
					label = postTypeLabels[ post_type ] || post_type;
					if ( post_template && templateLabels[ post_template ] ) {
						label += ' — ' + templateLabels[ post_template ];
					}
				} else if ( location_type === 'taxonomy' && taxonomy ) {
					label = taxonomyLabels[ taxonomy ] || taxonomy;
				} else if ( location_type === 'user' ) {
					label = '{$user_label}';
				} else if ( location_type === 'options_page' && options_page_title ) {
					label = options_page_title;
				}
			%>
			<% if (label) { %><%- label %><% } else { %>{$new_label}<% } %>
		";
	}

	private static function get_location_fields()
	{
		return [
			Field::make('select', 'location_type', __('Show on', 'carbon-fields-ui'))
				->set_options([
					'post_type'    => __('Post Type', 'carbon-fields-ui'),
					'taxonomy'     => __('Taxonomy', 'carbon-fields-ui'),
					'user'         => __('User Profile', 'carbon-fields-ui'),
					'options_page' => __('Options Page', 'carbon-fields-ui'),
				])
				->set_default_value('post_type')
				->set_required(true),

			Field::make('select', 'post_type', __('Post Type', 'carbon-fields-ui'))
				->set_options(self::get_post_type_options())
				->set_conditional_logic([['field' => 'location_type', 'value' => 'post_type']]),

			Field::make('select', 'post_template', __('Page Template (optional)', 'carbon-fields-ui'))
				->set_options(array_merge(['' => __('Any template', 'carbon-fields-ui')], self::get_template_options()))
				->set_required(false)
				->set_conditional_logic([['field' => 'location_type', 'value' => 'post_type']]),

			Field::make('select', 'taxonomy', __('Taxonomy', 'carbon-fields-ui'))
				->set_options(self::get_taxonomy_options())
				->set_conditional_logic([['field' => 'location_type', 'value' => 'taxonomy']]),

			Field::make('text', 'options_page_title', __('Page Title', 'carbon-fields-ui'))
				->set_help_text(__('Shown as the admin page heading, e.g. "Site Settings".', 'carbon-fields-ui'))
				->set_conditional_logic([['field' => 'location_type', 'value' => 'options_page']]),

			Field::make('text', 'options_menu_title', __('Menu Title (optional)', 'carbon-fields-ui'))
				->set_conditional_logic([['field' => 'location_type', 'value' => 'options_page']]),

			Field::make('text', 'options_parent_slug', __('Parent Menu Slug (optional)', 'carbon-fields-ui'))
				->set_help_text(__('e.g. "themes.php", "options-general.php". Leave empty for a new top-level menu.', 'carbon-fields-ui'))
				->set_conditional_logic([['field' => 'location_type', 'value' => 'options_page']]),
		];
	}

	/**
	 * Fields shared by both the top-level field row and the (one level
	 * deep) sub-field row inside a Group/Repeater.
	 */
	private static function get_common_field_definition_fields()
	{
		return [
			Field::make('text', 'label', __('Field Label', 'carbon-fields-ui'))
				->set_required(true)
				->set_width(50)
				->set_attribute('data-cfui-label-field', '1'),

			Field::make('text', 'name', __('Field Name (meta key)', 'carbon-fields-ui'))
				->set_help_text(__('Lowercase letters, numbers and underscores. Auto-filled from the label — edit it to override.', 'carbon-fields-ui'))
				->set_width(50)
				->set_attribute('data-cfui-name-field', '1'),

			Field::make('textarea', 'help_text', __('Help Text (optional)', 'carbon-fields-ui'))
				->set_rows(2)
				->set_width(50),

			Field::make('text', 'default_value', __('Default Value (optional)', 'carbon-fields-ui'))
				->set_width(50)
				->set_conditional_logic([['field' => 'type', 'value' => Field_Types::simple_value_types(), 'compare' => 'IN']]),

			Field::make('textarea', 'choices', __('Choices — one per line, "value : Label"', 'carbon-fields-ui'))
				->set_rows(4)
				->set_conditional_logic([['field' => 'type', 'value' => Field_Types::choice_types(), 'compare' => 'IN']]),
		];
	}

	private static function get_field_definition_fields()
	{
		$nested_types = array_keys(Field_Types::nested_options());

		return array_merge(
			[
				Field::make('select', 'type', __('Field Type', 'carbon-fields-ui'))
					->set_options(Field_Types::get_options())
					->set_default_value('text')
					->set_required(true),
			],
			self::get_common_field_definition_fields(),
			[
				Field::make('complex', 'sub_fields', __('Sub Fields', 'carbon-fields-ui'))
					->set_layout('tabbed-vertical')
					->add_fields(self::get_subfield_definition_fields())
					->set_header_template(self::get_simple_label_header_template(__('New Sub Field', 'carbon-fields-ui')))
					->set_conditional_logic([['field' => 'type', 'value' => $nested_types, 'compare' => 'IN']]),

				Field::make('checkbox', 'required', __('Required', 'carbon-fields-ui'))
					->set_conditional_logic([['field' => 'type', 'value' => $nested_types, 'compare' => 'NOT IN']]),
			]
		);
	}

	/**
	 * Same row, but one level down inside a Group/Repeater — no further
	 * nesting is offered here, which keeps the recursion bounded at 1.
	 */
	private static function get_subfield_definition_fields()
	{
		return array_merge(
			[
				Field::make('select', 'type', __('Field Type', 'carbon-fields-ui'))
					->set_options(Field_Types::get_options(false))
					->set_default_value('text')
					->set_required(true),
			],
			self::get_common_field_definition_fields(),
			[
				Field::make('checkbox', 'required', __('Required', 'carbon-fields-ui')),
			]
		);
	}

	private static function get_simple_label_header_template($fallback_label)
	{
		$fallback_label = esc_js($fallback_label);

		return "
			<% if (label) { %><%- label %><% } else { %>{$fallback_label}<% } %>
		";
	}

	private static function get_post_type_options()
	{
		$options = [];
		foreach (get_post_types(['public' => true], 'objects') as $post_type) {
			$options[$post_type->name] = $post_type->labels->singular_name;
		}

		return $options ?: ['post' => 'Post'];
	}

	private static function get_taxonomy_options()
	{
		$options = [];
		foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy) {
			$options[$taxonomy->name] = $taxonomy->labels->singular_name;
		}

		return $options ?: ['category' => 'Category'];
	}

	private static function get_template_options()
	{
		return wp_get_theme()->get_page_templates();
	}
}
