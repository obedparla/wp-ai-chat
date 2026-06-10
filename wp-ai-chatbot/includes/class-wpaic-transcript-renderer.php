<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds admin transcript payloads (messages + tool-event chips) and renders
 * assistant markdown to HTML. Extracted from WPAIC_Admin to keep it lean.
 */
class WPAIC_Transcript_Renderer {
	private WPAIC_Logs $logs;

	public function __construct( ?WPAIC_Logs $logs = null ) {
		$this->logs = $logs ?? new WPAIC_Logs();
	}

	/**
	 * Messages and tool-event chips for a conversation, merged chronologically
	 * and ready for JSON. Message items carry type=message/role/content; event
	 * items carry type=event/label.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_transcript_items( int $conversation_id ): array {
		$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$items = array();
		foreach ( $this->logs->get_conversation_messages( $conversation_id ) as $msg ) {
			$item = array(
				'type'       => 'message',
				'role'       => $msg->role,
				'content'    => $msg->content,
				'created_at' => wp_date( $datetime_format, strtotime( $msg->created_at ) ),
				'sort_time'  => $msg->created_at,
			);
			// Assistant replies are markdown; render server-side so the modal
			// shows formatting instead of raw asterisks. User text stays plain.
			if ( 'assistant' === $msg->role ) {
				$item['content_html'] = self::render_markdown_lite( (string) $msg->content );
			}
			$items[] = $item;
		}

		foreach ( WPAIC_Events::get_for_conversation( $conversation_id ) as $event ) {
			$items[] = array(
				'type'       => 'event',
				'label'      => WPAIC_Events::describe( $event->event_type, $event->event_data ),
				'created_at' => wp_date( $datetime_format, strtotime( $event->created_at ) ),
				'sort_time'  => $event->created_at,
			);
		}

		// Same-second ties follow the write order of a request cycle:
		// user message first, tool events during the stream, assistant reply last.
		$type_order = static function ( array $item ): int {
			if ( 'event' === $item['type'] ) {
				return 1;
			}
			return 'user' === ( $item['role'] ?? '' ) ? 0 : 2;
		};

		usort(
			$items,
			static function ( array $a, array $b ) use ( $type_order ): int {
				$time_comparison = strcmp( $a['sort_time'], $b['sort_time'] );
				if ( 0 !== $time_comparison ) {
					return $time_comparison;
				}
				return $type_order( $a ) <=> $type_order( $b );
			}
		);

		foreach ( $items as &$item ) {
			unset( $item['sort_time'] );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Minimal markdown-to-HTML for admin transcript display: bold, italic,
	 * inline code, links, bullet/numbered lists, and headings (rendered bold).
	 * All input is HTML-escaped first, so the output only contains the tags
	 * generated here.
	 */
	public static function render_markdown_lite( string $text ): string {
		$lines = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $text ) );

		// Tokenize each line, then emit grouping consecutive same-type segments.
		$segments = array();
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				$segments[] = array(
					'type' => 'break',
					'html' => '',
				);
			} elseif ( preg_match( '/^[-*]\s+(.*)$/', $trimmed, $matches ) ) {
				$segments[] = array(
					'type' => 'ul',
					'html' => '<li>' . self::render_inline_markdown( $matches[1] ) . '</li>',
				);
			} elseif ( preg_match( '/^\d+[.)]\s+(.*)$/', $trimmed, $matches ) ) {
				$segments[] = array(
					'type' => 'ol',
					'html' => '<li>' . self::render_inline_markdown( $matches[1] ) . '</li>',
				);
			} elseif ( preg_match( '/^#{1,6}\s+(.*)$/', $trimmed, $matches ) ) {
				$segments[] = array(
					'type' => 'heading',
					'html' => '<p><strong>' . self::render_inline_markdown( $matches[1] ) . '</strong></p>',
				);
			} else {
				$segments[] = array(
					'type' => 'text',
					'html' => self::render_inline_markdown( $trimmed ),
				);
			}
		}

		$html          = '';
		$segment_count = count( $segments );
		$index         = 0;
		while ( $index < $segment_count ) {
			$type = $segments[ $index ]['type'];

			if ( 'break' === $type ) {
				++$index;
				continue;
			}

			if ( 'heading' === $type ) {
				$html .= $segments[ $index ]['html'];
				++$index;
				continue;
			}

			$run = array();
			while ( $index < $segment_count && $segments[ $index ]['type'] === $type ) {
				$run[] = $segments[ $index ]['html'];
				++$index;
			}

			if ( 'text' === $type ) {
				$html .= '<p>' . implode( '<br>', $run ) . '</p>';
			} else {
				$html .= '<' . $type . '>' . implode( '', $run ) . '</' . $type . '>';
			}
		}

		return $html;
	}

	/**
	 * Inline markdown spans (code, bold, italic, links) on a single line.
	 * Escapes the line first; replacements only introduce tags built here.
	 */
	private static function render_inline_markdown( string $text ): string {
		$escaped = esc_html( $text );

		$escaped = (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', $escaped );
		$escaped = (string) preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped );
		$escaped = (string) preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped );
		$escaped = (string) preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			static function ( array $matches ): string {
				return '<a href="' . esc_url( $matches[2] ) . '" target="_blank" rel="noopener">' . $matches[1] . '</a>';
			},
			$escaped
		);

		return $escaped;
	}
}
