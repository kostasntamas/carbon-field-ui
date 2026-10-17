# Carbon Fields UI

A simple admin interface for creating custom fields with [Carbon Fields](https://github.com/htmlburger/carbon-fields) — no code required. Carbon Fields itself is bundled in `vendor/` (no Composer needed to run the plugin).

## Usage

1. Activate the plugin.
2. Go to **Field Groups → Add New** in the WP admin.
3. Give the group a title (used internally, and shown as the meta box title on post/term/user screens).
4. **Locations** tab: add one row per place you want the fields to appear — Post Type (optionally limited to a page template), Taxonomy, User Profile, or a new/existing Options Page. Add several rows to show the same fields in more than one place.
5. **Fields** tab: add one row per field — label, auto-generated or custom meta key, field type, help text, default value, choices (for Select/Radio/Checkbox Set), and whether it's required.
6. Publish. The fields appear immediately wherever you targeted them.
7. Uncheck **Active** (under Settings) to hide a group without deleting it.

## Reading values in your theme

Use Carbon Fields' own helper functions with the meta key you set on the field:

```php
carbon_get_post_meta( get_the_ID(), 'subtitle' );
carbon_get_term_meta( get_queried_object_id(), 'tagline' );
carbon_get_user_meta( get_current_user_id(), 'phone' );
carbon_get_theme_option( 'company_address' );
```

## Supported field types

Text, Textarea, Rich Text, Number, Checkbox, Select, Radio, Checkbox Set, Image, File, Date, Color Picker, Group, Repeater.

- **Date** renders as a plain HTML5 `<input type="date">` — no picker library/styling attached.
- **Group** is a fixed set of sub-fields (always exactly one),
- **Repeater** is the same but repeatable. Both get their own "Sub Fields" tab once selected, with the same field options minus Group/Repeater — nesting is one level deep, by design.

`Field_Types::create()` in `includes/class-field-types.php` is the place to add more leaf types later.

## Notes

- Carbon Fields is bundled under `vendor/htmlburger/carbon-fields` (GPLv2) and booted directly — no Composer install step needed.
- Deleting a field group only removes its definition; any data already saved under its meta keys stays in the database.

# carbon-field-ui
