<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Follower Class
 *
 * This Object represents a single Follower.
 * There is no direct reference to a WordPress User here.
 *
 * @author Matt Wiebe
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#follow-activity-inbox
 */
class Follower extends Actor {
	/**
	 * The complete Remote-Profile of the Follower
	 *
	 * @var array
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Get the errors.
	 *
	 * @return mixed
	 */
	public function get_errors() {
		return get_post_meta( $this->_id, 'activitypub_errors' );
	}

	/**
	 * Reset (delete) all errors.
	 *
	 * @return void
	 */
	public function reset_errors() {
		delete_post_meta( $this->_id, 'activitypub_errors' );
	}

	/**
	 * Count the errors.
	 *
	 * @return int The number of errors.
	 */
	public function count_errors() {
		$errors = $this->get_errors();

		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return count( $errors );
		}

		return 0;
	}

	/**
	 * Return the latest error message.
	 *
	 * @return string The error message.
	 */
	public function get_latest_error_message() {
		$errors = $this->get_errors();

		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return reset( $errors );
		}

		return '';
	}

	/**
	 * Update the current Follower-Object.
	 *
	 * @return void
	 */
	public function update() {
		$this->save();
	}

	/**
	 * Save the current Follower-Object.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! $this->get__id() ) {
			global $wpdb;

			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE guid=%s",
					esc_sql( $this->get_id() )
				)
			);

			if ( $post_id ) {
				$post = get_post( $post_id );
				$this->set__id( $post->ID );
			}
		}

		$args = array(
			'ID'           => $this->get__id(),
			'guid'         => esc_url_raw( $this->get_id() ),
			'post_title'   => esc_html( $this->get_name() ),
			'post_author'  => 0,
			'post_type'    => Followers::POST_TYPE,
			'post_name'    => esc_url_raw( $this->get_id() ),
			'post_excerpt' => esc_html( $this->get_summary() ) ? $this->get_summary() : '',
			'post_status'  => 'publish',
			'meta_input'   => $this->get_post_meta_input(),
		);

		$post_id = wp_insert_post( $args );
		$this->_id = $post_id;
	}

	/**
	 * Upsert the current Follower-Object.
	 *
	 * @return void
	 */
	public function upsert() {
		$this->save();
	}

	/**
	 * Delete the current Follower-Object.
	 *
	 * Beware that this os deleting a Follower for ALL users!!!
	 *
	 * To delete only the User connection (unfollow)
	 * @see \Activitypub\Rest\Followers::remove_follower()
	 *
	 * @return void
	 */
	public function delete() {
		wp_delete_post( $this->_id );
	}

	/**
	 * Update the post meta.
	 *
	 * @return void
	 */
	protected function get_post_meta_input() {
		$meta_input = array();
		$meta_input['activitypub_inbox'] = $this->get_shared_inbox();
		$meta_input['activitypub_actor_json'] = $this->to_json();

		return $meta_input;
	}

	/**
	 * Get the Icon URL (Avatar)
	 *
	 * @return string The URL to the Avatar.
	 */
	public function get_icon_url() {
		$icon = $this->get_icon();

		if ( ! $icon ) {
			return '';
		}

		if ( is_array( $icon ) ) {
			return $icon['url'];
		}

		return $icon;
	}

	/**
	 * Get the shared inbox, with a fallback to the inbox.
	 *
	 * @return string|null The URL to the shared inbox, the inbox or null.
	 */
	public function get_shared_inbox() {
		if ( ! empty( $this->get_endpoints()['sharedInbox'] ) ) {
			return $this->get_endpoints()['sharedInbox'];
		} elseif ( ! empty( $this->get_inbox() ) ) {
			return $this->get_inbox();
		}

		return null;
	}

	/**
	 * Convert a Custom-Post-Type input to an Activitypub\Model\Follower.
	 *
	 * @return string The JSON string.
	 *
	 * @return array Activitypub\Model\Follower
	 */
	public static function init_from_cpt( $post ) {
		$actor_json = get_post_meta( $post->ID, 'activitypub_actor_json', true );
		$object = self::init_from_json( $actor_json );
		$object->set__id( $post->ID );
		$object->set_id( $post->guid );
		$object->set_published( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_published ) ) );
		$object->set_updated( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_modified ) ) );

		return $object;
	}
}
