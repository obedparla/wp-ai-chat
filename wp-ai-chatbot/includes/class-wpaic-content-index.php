<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TeamTNT\TNTSearch\TNTSearch;

class WPAIC_Content_Index {

	private ?TNTSearch $tnt = null;
	private string $index_path;
	private string $index_name = 'content.index';

	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->index_path = $upload_dir['basedir'] . '/wpaic/search/';
	}

	private function get_tnt(): TNTSearch {
		if ( null === $this->tnt ) {
			$this->tnt = new TNTSearch();
			$this->tnt->loadConfig(
				array(
					'driver'    => 'filesystem',
					'storage'   => $this->index_path,
					'fuzziness' => true,
				)
			);
		}
		return $this->tnt;
	}

	private function ensure_directory(): bool {
		if ( ! file_exists( $this->index_path ) ) {
			return wp_mkdir_p( $this->index_path );
		}
		return true;
	}

	public function get_selected_post_types(): array {
		$settings = get_option( 'wpaic_settings', array() );
		if ( is_array( $settings ) && array_key_exists( 'content_index_post_types', $settings ) && is_array( $settings['content_index_post_types'] ) ) {
			return $settings['content_index_post_types'];
		}
		return array( 'page', 'post' );
	}

	public function build_index(): bool {
		$post_types = $this->get_selected_post_types();
		if ( empty( $post_types ) ) {
			return $this->clear_index();
		}

		if ( ! $this->ensure_directory() ) {
			return false;
		}

		$index_file = $this->index_path . $this->index_name;
		if ( file_exists( $index_file ) ) {
			wp_delete_file( $index_file );
		}

		$tnt     = $this->get_tnt();
		$indexer = $tnt->createIndex( $this->index_name );
		$indexer->setLanguage( 'no' );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'has_password'   => false,
			)
		);

		foreach ( $posts as $post ) {
			$content  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$document = implode( ' ', array( $post->post_title, $content ) );

			$indexer->insert(
				array(
					'id'   => $post->ID,
					'text' => $document,
				)
			);
		}

		$this->update_index_meta( count( $posts ), $post_types );

		return true;
	}

	public function clear_index(): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( file_exists( $index_file ) ) {
			wp_delete_file( $index_file );
		}

		$this->clear_index_meta();

		return true;
	}

	public function search( string $query, int $limit = 5 ): array {
		$index_file = $this->index_path . $this->index_name;

		if ( ! file_exists( $index_file ) ) {
			return $this->fallback_search( $query, $limit );
		}

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$tnt->fuzziness = true;

		$results  = $tnt->search( $query, $limit * 2 );
		$post_ids = isset( $results['ids'] ) && is_array( $results['ids'] ) ? array_map( 'intval', $results['ids'] ) : array();

		if ( empty( $post_ids ) ) {
			return $this->fallback_search( $query, $limit );
		}

		$output = array();
		foreach ( array_slice( $post_ids, 0, $limit ) as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$snippet = $this->generate_snippet( $content, $query );

			$output[] = array(
				'post_id' => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
				'snippet' => $snippet,
			);
		}

		return $output;
	}

	public function get_page_content( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return null;
		}
		if ( ! in_array( $post->post_type, $this->get_selected_post_types(), true ) ) {
			return null;
		}

		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

		return array(
			'post_id' => $post->ID,
			'title'   => $post->post_title,
			'url'     => get_permalink( $post->ID ),
			'content' => $content,
		);
	}

	public function index_post( int $post_id ): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( ! file_exists( $index_file ) ) {
			return $this->build_index();
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return false;
		}

		$content  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$document = implode( ' ', array( $post->post_title, $content ) );

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$indexer = $tnt->getIndex();

		$indexer->update(
			$post_id,
			array(
				'id'   => $post_id,
				'text' => $document,
			)
		);

		return true;
	}

	public function remove_post( int $post_id ): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( ! file_exists( $index_file ) ) {
			return true;
		}

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$indexer = $tnt->getIndex();
		$indexer->delete( $post_id );

		return true;
	}

	public function get_index_status(): array {
		$index_file = $this->index_path . $this->index_name;
		$meta       = get_option( 'wpaic_content_index_meta', array() );

		return array(
			'exists'             => file_exists( $index_file ),
			'post_count'         => isset( $meta['post_count'] ) ? (int) $meta['post_count'] : 0,
			'last_updated'       => isset( $meta['last_updated'] ) ? $meta['last_updated'] : null,
			'indexed_post_types' => isset( $meta['post_types'] ) ? $meta['post_types'] : array(),
		);
	}

	private function update_index_meta( int $count, array $post_types ): void {
		update_option(
			'wpaic_content_index_meta',
			array(
				'post_count'   => $count,
				'last_updated' => current_time( 'mysql' ),
				'post_types'   => $post_types,
			)
		);
	}

	private function clear_index_meta(): void {
		update_option(
			'wpaic_content_index_meta',
			array(
				'post_count'   => 0,
				'last_updated' => null,
				'post_types'   => array(),
			)
		);
	}

	private function generate_snippet( string $content, string $query ): string {
		$snippet_length = 500;

		$terms    = preg_split( '/\s+/', mb_strtolower( trim( $query ) ) );
		$lower    = mb_strtolower( $content );
		$position = false;

		foreach ( $terms as $term ) {
			$position = mb_strpos( $lower, $term );
			if ( false !== $position ) {
				break;
			}
		}

		if ( false === $position ) {
			return mb_substr( $content, 0, $snippet_length );
		}

		$half  = (int) floor( $snippet_length / 2 );
		$start = max( 0, $position - $half );
		$end   = min( mb_strlen( $content ), $start + $snippet_length );

		if ( $end - $start < $snippet_length && $start > 0 ) {
			$start = max( 0, $end - $snippet_length );
		}

		return mb_substr( $content, $start, $end - $start );
	}

	private function fallback_search( string $query, int $limit ): array {
		$post_types = $this->get_selected_post_types();

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				's'              => $query,
				'posts_per_page' => $limit,
				'has_password'   => false,
			)
		);

		$output = array();
		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$snippet = $this->generate_snippet( $content, $query );

			$output[] = array(
				'post_id' => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
				'snippet' => $snippet,
			);
		}

		return $output;
	}
}
