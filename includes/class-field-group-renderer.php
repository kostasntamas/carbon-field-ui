<?php

namespace CFUI;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Reads every published field group created in the builder and turns it
 * into real Carbon Fields containers/fields.
 */
class Field_Group_Renderer {

	public static function init() {
		add_action( 'carbon_fields_register_fields', [ __CLASS__, 'register_dynamic_fields' ] );
	}

	public static function register_dynamic_fields() {
		$groups = get_posts( [
			'post_type'      => Field_Group_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		] );

		foreach ( $groups as $group ) {
			if ( ! self::is_active( $group->ID ) ) {
				continue;
			}

			$locations  = array_filter( (array) carbon_get_post_meta( $group->ID, 'cfui_locations' ) );
			$field_defs = array_filter( (array) carbon_get_post_meta( $group->ID, 'cfui_fields' ) );

			if ( ! $locations || ! $field_defs ) {
				continue;
			}

			if ( ! self::build_fields( $field_defs ) ) {
				continue; // nothing valid to show, e.g. every row is missing a label
			}

			self::register_containers_for_group( $group, $locations, $field_defs );
		}
	}

	public static function is_active( $post_id ) {
		$value = carbon_get_post_meta( $post_id, 'cfui_active' );

		return '' === $value ? true : (bool) $value;
	}

	/**
	 * Turns the "cfui_fields" complex-field rows into live Carbon Fields
	 * Field objects, ready to be cloned into one or more containers.
	 */
	private static function build_fields( array $field_defs ) {
		$fields     = [];
		$used_names = [];

		foreach ( $field_defs as $def ) {
			$label = trim( (string) ( $def['label'] ?? '' ) );

			if ( '' === $label ) {
				continue;
			}

			$name = trim( (string) ( $def['name'] ?? '' ) );
			$name = sanitize_key( str_replace( '-', '_', sanitize_title( $name ?: $label ) ) );

			if ( '' === $name ) {
				continue;
			}

			$base_name = $name;
			$suffix    = 1;

			while ( in_array( $name, $used_names, true ) ) {
				$name = $base_name . '_' . ( ++$suffix );
			}

			$used_names[] = $name;

			$type = $def['type'] ?? 'text';

			if ( Field_Types::is_nested_type( $type ) ) {
				$field = self::build_nested_field( $type, $name, $label, $def );

				if ( $field ) {
					$fields[] = $field;
				}

				continue;
			}

			$field = Field_Types::create( $type, $name, $label );

			if ( ! empty( $def['help_text'] ) ) {
				$field->set_help_text( $def['help_text'] );
			}

			if ( ! empty( $def['required'] ) ) {
				$field->set_required( true );
			}

			if ( isset( $def['default_value'] ) && '' !== $def['default_value'] && in_array( $type, Field_Types::simple_value_types(), true ) ) {
				$field->set_default_value( $def['default_value'] );
			}

			if ( in_array( $type, Field_Types::choice_types(), true ) && ! empty( $def['choices'] ) ) {
				$field->set_options( self::parse_choices( $def['choices'] ) );
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Builds a Group (fixed, single set) or Repeater (repeatable set) as
	 * a Carbon Fields Complex field made up of its own sub-fields.
	 * Sub-fields can't themselves be Group/Repeater (see
	 * Field_Group_Post_Type::get_subfield_definition_fields()), so this
	 * recursion is always exactly one level deep.
	 */
	private static function build_nested_field( $type, $name, $label, array $def ) {
		$sub_defs   = array_filter( (array) ( $def['sub_fields'] ?? [] ) );
		$sub_fields = self::build_fields( $sub_defs );

		if ( ! $sub_fields ) {
			return null; // a group/repeater with nothing inside it isn't useful
		}

		$field = Field::make( 'complex', $name, $label )->add_fields( $sub_fields );

		if ( 'group' === $type ) {
			$field->set_layout( 'grid' )->set_min( 1 )->set_max( 1 );
		} else {
			$field->set_layout( 'tabbed-vertical' );
		}

		if ( ! empty( $def['help_text'] ) ) {
			$field->set_help_text( $def['help_text'] );
		}

		return $field;
	}

	private static function parse_choices( $raw ) {
		$options = [];

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( false !== strpos( $line, ':' ) ) {
				[ $value, $choice_label ] = array_map( 'trim', explode( ':', $line, 2 ) );
			} else {
				$value = $choice_label = $line;
			}

			$options[ $value ] = $choice_label;
		}

		return $options;
	}

	/**
	 * Each location row becomes its own container. Keeping a strict
	 * one-row-to-one-container mapping avoids fighting Carbon Fields'
	 * where()/or_where() boolean grouping for combinations it was never
	 * designed to express.
	 */
	private static function register_containers_for_group( $group, array $locations, array $field_defs ) {
		$title = '' !== $group->post_title ? $group->post_title : sprintf( __( 'Field Group #%d', 'carbon-fields-ui' ), $group->ID );

		foreach ( $locations as $index => $loc ) {
			$type        = $loc['location_type'] ?? '';
			$unique_name = 'cfui-' . $group->ID . '-' . $index;

			// Built fresh per container (rather than cloned) so nested
			// Group/Repeater fields never share a Field object graph
			// across containers — a plain PHP clone() is shallow and
			// would leave their sub-fields pointing at the same objects.
			$field_set = self::build_fields( $field_defs );

			switch ( $type ) {
				case 'post_type':
					if ( empty( $loc['post_type'] ) ) {
						break;
					}

					$container = Container::make( 'post_meta', $unique_name, $title )
						->where( 'post_type', '=', $loc['post_type'] );

					if ( ! empty( $loc['post_template'] ) ) {
						$container->where( 'post_template', '=', $loc['post_template'] );
					}

					$container->add_fields( $field_set );
					break;

				case 'taxonomy':
					if ( empty( $loc['taxonomy'] ) ) {
						break;
					}

					Container::make( 'term_meta', $unique_name, $title )
						->where( 'term_taxonomy', '=', $loc['taxonomy'] )
						->add_fields( $field_set );
					break;

				case 'user':
					Container::make( 'user_meta', $unique_name, $title )
						->add_fields( $field_set );
					break;

				case 'options_page':
					if ( empty( $loc['options_page_title'] ) ) {
						break;
					}

					$container = Container::make( 'theme_options', $unique_name, $loc['options_page_title'] )
						->set_page_file( 'cfui-' . sanitize_title( $loc['options_page_title'] ) . '-' . $group->ID );

					if ( ! empty( $loc['options_menu_title'] ) ) {
						$container->set_page_menu_title( $loc['options_menu_title'] );
					}

					if ( ! empty( $loc['options_parent_slug'] ) ) {
						$container->set_page_parent( $loc['options_parent_slug'] );
					}

					$container->add_fields( $field_set );
					break;
			}
		}
	}

	public static function describe_location( array $loc ) {
		switch ( $loc['location_type'] ?? '' ) {
			case 'post_type':
				return $loc['post_type'] ? sprintf( __( 'Post Type: %s', 'carbon-fields-ui' ), $loc['post_type'] ) : '';
			case 'taxonomy':
				return $loc['taxonomy'] ? sprintf( __( 'Taxonomy: %s', 'carbon-fields-ui' ), $loc['taxonomy'] ) : '';
			case 'user':
				return __( 'User Profile', 'carbon-fields-ui' );
			case 'options_page':
				return $loc['options_page_title'] ? sprintf( __( 'Options Page: %s', 'carbon-fields-ui' ), $loc['options_page_title'] ) : '';
		}

		return '';
	}
}
