<?php
/**
 * Full Site Editing (FSE) theme / template / global-styles abilities.
 *
 * Exposes the WordPress block-theme surface: read the active theme and the installed theme
 * list, list and read block templates, update a database block template's markup, and read the
 * resolved global styles and settings (theme.json). Every ability gates on edit_theme_options —
 * the capability WordPress puts on the Appearance screens — reusing aafm_perm_edit_theme_options()
 * from menus.php (loaded first); this file never redefines it, which would be a fatal redeclare.
 *
 * The permission is object-INDEPENDENT (there is no per-template/per-theme capability), so the
 * discovery layer falls through to that callback with no per-object case in server.php for the
 * whole FSE family, reads and the single write alike.
 *
 * No filesystem path is ever returned: the reads use get_stylesheet()/get_template() and the
 * theme header fields, never get_stylesheet_directory(). update-template refuses theme-FILE
 * templates (which have no backing post) and only ever edits a wp_template post's content, which
 * is kses-hardened first — block delimiters survive, scripts are stripped.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_filter( 'aafm_abilities_registry', 'aafm_register_themes_definitions' );

/**
 * Contribute the FSE theme/template/global-styles definitions to the registry.
 *
 * @param array<string,array<string,mixed>> $registry Registry.
 * @return array<string,array<string,mixed>>
 */
function aafm_register_themes_definitions( array $registry ): array {
	$registry['aafm/get-active-theme']  = array(
		'label'        => __( 'Get active theme', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Reads the active theme: name, version, stylesheet, parent, and whether it is a block theme. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_get_active_theme',
	);
	$registry['aafm/list-themes']       = array(
		'label'        => __( 'List themes', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Lists installed themes by name, version, stylesheet, and active state. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_list_themes',
	);
	$registry['aafm/list-templates']    = array(
		'label'        => __( 'List templates', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Lists block templates (or template parts) by id, slug, title, type, and source. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_list_templates',
	);
	$registry['aafm/get-template']      = array(
		'label'        => __( 'Get template', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Reads one block template by id, including its markup. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_get_template',
	);
	$registry['aafm/get-global-styles'] = array(
		'label'        => __( 'Get global styles', 'agent-abilities-for-mcp' ),
		'description'  => __( "Reads the active theme's resolved global styles and settings (theme.json). Requires the edit-theme-options capability.", 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_get_global_styles',
	);
	$registry['aafm/update-template']   = array(
		'label'        => __( 'Update template', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Updates a database block template by id. Its markup is sanitized, and theme-file templates cannot be edited. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'group'        => 'writes',
		'risk'         => 'write',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_update_template',
	);
	return $registry;
}

/**
 * Args for aafm/get-active-theme.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_active_theme(): array {
	return array(
		'label'               => __( 'Get active theme', 'agent-abilities-for-mcp' ),
		'description'         => __( 'Reads the active theme: name, version, stylesheet, parent, and whether it is a block theme. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'name'           => array( 'type' => 'string' ),
				'version'        => array( 'type' => 'string' ),
				'stylesheet'     => array( 'type' => 'string' ),
				'template'       => array( 'type' => 'string' ),
				'is_block_theme' => array( 'type' => 'boolean' ),
			),
		),
		'execute_callback'    => 'aafm_exec_get_active_theme',
		'permission_callback' => 'aafm_perm_edit_theme_options',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Execute aafm/get-active-theme.
 *
 * Returns the active theme's header fields and slugs only — never a filesystem directory.
 * stylesheet is the active (child) slug; template is the parent slug (same as stylesheet for a
 * non-child theme).
 *
 * @return array<string,mixed>
 */
function aafm_exec_get_active_theme(): array {
	$theme = wp_get_theme();
	return array(
		'name'           => (string) $theme->get( 'Name' ),
		'version'        => (string) $theme->get( 'Version' ),
		'stylesheet'     => $theme->get_stylesheet(),
		'template'       => $theme->get_template(),
		'is_block_theme' => wp_is_block_theme(),
	);
}

/**
 * Args for aafm/list-themes.
 *
 * @return array<string,mixed>
 */
function aafm_args_list_themes(): array {
	return array(
		'label'               => __( 'List themes', 'agent-abilities-for-mcp' ),
		'description'         => __( 'Lists installed themes by name, version, stylesheet, and active state. Requires the edit-theme-options capability.', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'themes' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'       => array( 'type' => 'string' ),
							'version'    => array( 'type' => 'string' ),
							'stylesheet' => array( 'type' => 'string' ),
							'status'     => array( 'type' => 'string' ),
						),
					),
				),
			),
		),
		'execute_callback'    => 'aafm_exec_list_themes',
		'permission_callback' => 'aafm_perm_edit_theme_options',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Execute aafm/list-themes.
 *
 * Lists every installed theme with its header fields and stylesheet slug, marking the active one.
 * No filesystem path is returned (the slug is the stylesheet directory NAME, not its full path).
 *
 * @return array<string,mixed>
 */
function aafm_exec_list_themes(): array {
	$active = wp_get_theme()->get_stylesheet();
	$themes = array();
	foreach ( wp_get_themes() as $theme ) {
		$stylesheet = $theme->get_stylesheet();
		$themes[]   = array(
			'name'       => (string) $theme->get( 'Name' ),
			'version'    => (string) $theme->get( 'Version' ),
			'stylesheet' => $stylesheet,
			'status'     => $stylesheet === $active ? 'active' : 'inactive',
		);
	}
	return array( 'themes' => $themes );
}
