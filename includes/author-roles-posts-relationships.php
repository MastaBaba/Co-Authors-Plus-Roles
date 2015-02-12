<?php
/**
 * Functions that create or esatblish a relationship between a co-author and a post.
 *
 */

namespace CoAuthorsPlusRoles;


/**
 * Sets a guest author as a contributor on a post, with a specified role.
 *
 * This should be called on all additional contributors, not on primary
 * authors/bylines, who will use the existing functionality from Co-Authors Plus.
 *
 * @param int|object $post_id Post to set author as "coauthor" on
 * @param object|string $author WP_User object, or nicename/user_login/slug
 * @param object|string $author_role Term or slug of contributor role to set. Defaults to "byline" if empty
 * @return bool True on success, false on failure (if any of the inputs are not acceptable).
 */
function set_author_on_post( $post_id, $author, $author_role = false ) {
	global $coauthors_plus, $wpdb;

	if ( is_object( $post_id ) && isset( $post_id->ID ) )
		$post_id = $post_id->ID;

	$post_id = intval( $post_id );

	if ( is_string( $author ) )
		$author = $coauthors_plus->get_coauthor_by( 'user_nicename', $author );

	if ( is_int( $author ) )
		$author = $coauthors_plus->get_coauthor_by( 'id', $author );

	// Only create the byline term if the contributor role is:
	//  - one of the byline roles, as set in register_author_role(), or
	//  - unset, meaning they should default to primary author role.
	if ( ! $author_role || in_array( $author_role, byline_roles() ) )
		$coauthors_plus->add_coauthors( $post_id, array( $author->user_nicename ), true );

	if ( ! $post_id || ! $author )
		return false;

	$drop_existing_role = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key LIKE 'cap-%%' AND meta_value=%d",
			array( $post_id, $author->ID )
		)
	);

	if ( ! $author_role )
		return true;

	if ( is_string( $author_role ) )
		$author_role = get_author_role( $author_role );

	update_post_meta( $post_id, 'cap-' . $author_role->slug, $author->ID );
}


/**
 * Removes a guest author from a post.
 *
 * @param int|object $post_id Post to set author as "coauthor" on
 * @param object|string $author WP_User object, or nicename/user_login/slug
 * @return bool True on success, false on failure (if any of the inputs are not acceptable).
 */
function remove_author_from_post( $post_id, $author ) {
	global $coauthors_plus, $wpdb;

	if ( is_object( $post_id ) && isset( $post_id->ID ) )
		$post_id = $post_id->ID;

	$post_id = intval( $post_id );

	if ( is_string( $author ) )
		$author = $coauthors_plus->get_coauthor_by( 'user_nicename', $author );

	if ( is_int( $author ) )
		$author = $coauthors_plus->get_coauthor_by( 'id', $author );

	// Remove byline term from post: Start by getting the author terms on the post.
	$existing_authors = wp_get_object_terms( $post_id, $coauthors_plus->coauthor_taxonomy, array( 'fields' => 'slugs' ) );
	$new_authors = array_diff( $existing_authors, array( 'cap-' . $author->user_nicename ) );
	wp_set_object_terms( $post_id, $new_authors, $coauthors_plus->coauthor_taxonomy, true );

	// Delete meta value setting contributor on post
	$drop_existing_role = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key LIKE 'cap-%%' AND meta_value=%d",
			array( $post_id, $author->ID )
		)
	);
}

