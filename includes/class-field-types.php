<?php

namespace CFUI;

use Carbon_Fields\Field\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the simple field types shown in the builder UI onto the
 * Carbon Fields field classes that actually render them.
 */
class Field_Types {

	/** Plain, non-nesting field types. */
	public static function leaf_options() {
		return [
			'text'      => __( 'Text', 'carbon-fields-ui' ),
			'textarea'  => __( 'Textarea', 'carbon-fields-ui' ),
			'rich_text' => __( 'Rich Text (WYSIWYG)', 'carbon-fields-ui' ),
			'number'    => __( 'Number', 'carbon-fields-ui' ),
			'checkbox'  => __( 'Checkbox (yes/no)', 'carbon-fields-ui' ),
			'select'    => __( 'Select', 'carbon-fields-ui' ),
			'radio'     => __( 'Radio', 'carbon-fields-ui' ),
			'set'       => __( 'Checkbox Set (multiple choices)', 'carbon-fields-ui' ),
			'image'     => __( 'Image', 'carbon-fields-ui' ),
			'file'      => __( 'File', 'carbon-fields-ui' ),
			'date'      => __( 'Date', 'carbon-fields-ui' ),
			'color'     => __( 'Color Picker', 'carbon-fields-ui' ),
		];
	}

	/** Field types that hold their own set of sub-fields. */
	public static function nested_options() {
		return [
			'group'    => __( 'Group (fixed set of fields)', 'carbon-fields-ui' ),
			'repeater' => __( 'Repeater (repeatable set of fields)', 'carbon-fields-ui' ),
		];
	}

	/**
	 * All field types selectable at the top level. Nested types are left
	 * out one level down (a group/repeater can't contain another one) to
	 * keep the builder's recursion simple and bounded.
	 */
	public static function get_options( $include_nested = true ) {
		return $include_nested
			? array_merge( self::leaf_options(), self::nested_options() )
			: self::leaf_options();
	}

	public static function is_nested_type( $type ) {
		return array_key_exists( $type, self::nested_options() );
	}

	/** Field types that take a plain-text default value. */
	public static function simple_value_types() {
		return [ 'text', 'textarea', 'number', 'date' ];
	}

	/** Field types that need a "Choices" list. */
	public static function choice_types() {
		return [ 'select', 'radio', 'set' ];
	}

	/**
	 * Build a live Carbon Fields Field object for one leaf builder field
	 * type. Group/Repeater are handled separately since they need their
	 * own sub-fields, not just a type/name/label.
	 */
	public static function create( $type, $name, $label ) {
		if ( ! isset( self::leaf_options()[ $type ] ) ) {
			$type = 'text';
		}

		if ( 'number' === $type ) {
			$field = Field::make( 'text', $name, $label );
			$field->set_attribute( 'type', 'number' );
			$field->set_attribute( 'step', 'any' );

			return $field;
		}

		if ( 'date' === $type ) {
			// Plain HTML5 date input instead of Carbon Fields' bundled
			// Flatpickr-based date picker, to avoid pulling in a picker
			// library/UI for a single field type.
			$field = Field::make( 'text', $name, $label );
			$field->set_attribute( 'type', 'date' );

			return $field;
		}

		return Field::make( $type, $name, $label );
	}
}
