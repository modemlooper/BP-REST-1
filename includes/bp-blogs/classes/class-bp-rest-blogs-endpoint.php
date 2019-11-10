<?php
/**
 * BP REST: BP_REST_Blogs_Endpoint class
 *
 * @package BuddyPress
 * @since 6.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blogs endpoints.
 *
 * Use /blogs/
 * Use /blogs/{id}
 *
 * @since 6.0.0
 */
class BP_REST_Blogs_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->blogs->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 6.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'A unique numeric ID for the Blog.', 'buddypress' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve Blogs.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'type'             => $request['type'],
			'include_blog_ids' => $request['include'],
			'user_id'          => $request['user_id'],
			'search_terms'     => $request['search'],
			'page'             => $request['page'],
			'per_page'         => $request['per_page'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 6.0.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_blogs_get_items_query_args', $args, $request );

		// false is the default value for some args.
		foreach ( $args as $key => $value ) {
			if ( empty( $value ) ) {
				$args[ $key ] = false;
			}
		}

		// Check if user is valid.
		if ( 0 !== $request['user_id'] ) {
			$user = get_user_by( 'id', $request['user_id'] );
			if ( ! $user instanceof WP_User ) {
				return new WP_Error(
					'bp_rest_blogs_get_items_user_failed',
					__( 'There was a problem confirming if user ID provided is a valid one.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		// Actually, query it.
		$blogs = bp_blogs_get_blogs( $args );

		$retval = array();
		foreach ( (array) $blogs['blogs'] as $blog ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $blog, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $blogs['total'], $args['per_page'] );

		/**
		 * Fires after blogs are fetched via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param array            $blogs     Fetched blogs.
		 * @param WP_REST_Response $response  The response data.
		 * @param WP_REST_Request  $request   The request sent to the API.
		 */
		do_action( 'bp_rest_blogs_get_items', $blogs, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to blog items.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filter the blogs `get_items` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_blogs_get_items_permissions_check', true, $request );
	}

	/**
	 * Retrieve a blog.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$blog = $this->get_blog_object( $request['id'] );

		if ( ! $blog instanceof BP_Blogs_Blog ) {
			return new WP_Error(
				'bp_rest_blog_invalid_id',
				__( 'Invalid blog ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $blog, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a blog is fetched via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Blogs_Blog    $blog     Fetched blog.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_blogs_get_item', $blog, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific blog.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {

		/**
		 * Filter the blog `get_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_blogs_get_item_permissions_check', true, $request );
	}

	/**
	 * Prepares blogs data for return as an object.
	 *
	 * @since 6.0.0
	 *
	 * @param BP_Blogs_Blog   $blog    Blog object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $blog, $request ) {
		$data = array(
			'id'            => $blog->blog_id,
			'user_id'       => $blog->admin_user_id,
			'name'          => $blog->name,
			'domain'        => $blog->domain,
			'path'          => $blog->path,
			'permalink'     => $this->get_blog_domain( $blog ),
			'description'   => $blog->description,
			'last_activity' => bp_rest_prepare_date_response( $blog->last_activity ),
		);

		// Get item schema.
		$schema = $this->get_item_schema();

		// Blog Avatars.
		if ( ! empty( $schema['properties']['avatar_urls'] ) ) {
			$data['avatar_urls'] = array(
				'thumb' => bp_get_blog_avatar(
					array(
						'type'          => 'thumb',
						'blog_id'       => $blog->blog_id,
						'admin_user_id' => $blog->admin_user_id,
					)
				),
				'full'  => bp_get_blog_avatar(
					array(
						'type'          => 'full',
						'blog_id'       => $blog->blog_id,
						'admin_user_id' => $blog->admin_user_id,
					)
				),
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $blog ) );

		/**
		 * Filter a blog returned from the API.
		 *
		 * @since 6.0.0
		 *
		 * @param WP_REST_Response  $response Response generated by the request.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param BP_Blogs_Blog     $blog     The blog object.
		 */
		return apply_filters( 'bp_rest_blogs_prepare_value', $response, $request, $blog );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 6.0.0
	 *
	 * @param BP_Blogs_Blog $blog Blog object.
	 * @return array
	 */
	protected function prepare_links( $blog ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $blog->blog_id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $blog->admin_user_id ) ),
				'embeddable' => true,
			),
		);

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @since 0.1.0
		 *
		 * @param array         $links The prepared links of the REST response.
		 * @param BP_Blogs_Blog $blog  Blog object.
		 */
		return apply_filters( 'bp_rest_blogs_prepare_links', $links, $blog );
	}

	/**
	 * Get blog permalink.
	 *
	 * @param BP_Blogs_Blog $blog Blog object.
	 * @return string
	 */
	protected function get_blog_domain( $blog ) {

		// Bail early.
		if ( empty( $blog->domain ) && empty( $blog->path ) ) {
			return '';
		}

		if ( empty( $blog->domain ) && ! empty( $blog->path ) ) {
			return bp_get_root_domain() . $blog->path;
		}

		$protocol  = is_ssl() ? 'https://' : 'http://';
		$permalink = $protocol . $blog->domain . $blog->path;

		return apply_filters( 'bp_get_blog_permalink', $permalink );
	}

	/**
	 * Get BP_Blogs_Blog object from a blog_id.
	 *
	 * @since 6.0.0
	 *
	 * @param int $blog_id Blog ID.
	 * @return BP_Blogs_Blog|bool
	 */
	protected function get_blog_object( $blog_id ) {
		$blogs = bp_blogs_get_blogs(
			array(
				'include_blog_ids'  => array( $blog_id ),
				'per_page'          => 1,
				'update_meta_cache' => false,
			)
		);

		if ( empty( $blogs['blogs'][0] ) ) {
			return false;
		}

		return $blogs['blogs'][0];
	}

	/**
	 * Get the blogs schema, conforming to JSON Schema.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_blogs',
			'type'       => 'object',
			'properties' => array(
				'id'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the blog.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_id'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for blog admin.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'name'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the blog.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'permalink'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink of the blog.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
					'format'      => 'uri',
				),
				'description'   => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The Description of the blog.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'path'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The path of the blog.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'domain'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'the domain of the blog.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'last_activity' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The last activity date from the blog, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);

		// Blog Avatars.
		if ( ! buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: Full image size for the group Avatar */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: Thumb imaze size for the group Avatar */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'view', 'edit' ),
			);

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the blog.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		/**
		 * Filter the blogs schema.
		 *
		 * @since 6.0.0
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_blogs_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for blogs collections.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['user_id'] = array(
			'description'       => __( 'ID of the user whose blogs user can post to.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Limit result set to items with a specific type.', 'buddypress' ),
			'default'           => 'active',
			'type'              => 'string',
			'enum'              => array( 'active', 'alphabetical', 'newest', 'random' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_blogs_collection_params', $params );
	}
}
