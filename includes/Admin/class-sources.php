<?php
/**
 * Where the cards are coming from.
 *
 * @package SeedcastVisitorCard\Admin
 */

namespace SeedcastVisitorCard\Admin;

use Seedcast\Core\Install;
use SeedcastVisitorCard\Frontend\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts submissions per page.
 *
 * Built entirely from what a submission already records: the page it was sent
 * from. Nothing watches anyone, nothing is stored about people who did not
 * fill the card in, and no page views are counted. That means this cannot say
 * how many people saw a card, only how many sent one, which is a smaller claim
 * and an honest one.
 *
 * The reason it is worth having: it answers whether sermons produce visits. A
 * church that can see four cards came from one message is looking at evidence
 * that putting its preaching online is doing something, which is the whole
 * argument for the rest of the suite.
 */
class Sources {

	/**
	 * How many rows to show.
	 */
	private const LIMIT = 20;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 13 );
	}

	/**
	 * Add the submenu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'seedcast-visitor-card',
			__( 'Where cards come from', 'seedcast-visitor-card' ),
			__( 'Where from', 'seedcast-visitor-card' ),
			'manage_options',
			'seedcast-visitor-card-sources',
			[ $this, 'render' ]
		);
	}

	/**
	 * Counts grouped by the page a card was sent from.
	 *
	 * Grouped on the post id where there is one, because a URL changes when a
	 * permalink does and picks up query strings from campaign links, which
	 * would split one sermon into several rows. Cards sent from somewhere with
	 * no post behind it fall back to the URL.
	 *
	 * @param string $from Optional start date.
	 * @param string $to   Optional end date.
	 * @return array<int, array{label:string,url:string,count:int,post_id:int}>
	 */
	public static function counts( string $from = '', string $to = '' ): array {
		global $wpdb;

		if ( ! Submissions::table_exists() ) {
			return [];
		}

		$sql    = 'SELECT linked_post, source_url, COUNT(*) AS total
			FROM %i
			WHERE source = %s AND type = %s';
		$params = [ Install::submissions_table(), Card::SOURCE, Card::TYPE ];

		if ( '' !== $from ) {
			$sql     .= ' AND submitted_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( '' !== $to ) {
			$sql     .= ' AND submitted_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		$sql .= ' GROUP BY linked_post, source_url ORDER BY total DESC LIMIT 200';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql holds only literal fragments; every value is bound through $params by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];

		foreach ( $rows as $row ) {
			$post_id = (int) ( $row['linked_post'] ?? 0 );
			$url     = (string) ( $row['source_url'] ?? '' );
			$count   = (int) $row['total'];

			// One key per piece of content, so the same sermon reached by two
			// different URLs is one row rather than two.
			$key = $post_id > 0 ? 'p:' . $post_id : 'u:' . $url;

			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = [
					'label'   => self::label_for( $post_id, $url ),
					'url'     => $post_id > 0 ? (string) get_permalink( $post_id ) : $url,
					'count'   => 0,
					'post_id' => $post_id,
				];
			}

			$out[ $key ]['count'] += $count;
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		return $out;
	}

	/**
	 * A readable name for a page.
	 *
	 * @param int    $post_id Post id, or 0.
	 * @param string $url     Source URL.
	 * @return string
	 */
	private static function label_for( int $post_id, string $url ): string {
		if ( $post_id > 0 ) {
			$title = get_the_title( $post_id );
			if ( '' !== trim( (string) $title ) ) {
				$type   = get_post_type_object( (string) get_post_type( $post_id ) );
				$prefix = $type && isset( $type->labels->singular_name )
					? $type->labels->singular_name . ': '
					: '';
				return $prefix . $title;
			}
		}

		if ( '' === $url ) {
			return __( 'Somewhere unrecorded', 'seedcast-visitor-card' );
		}

		// The path is more use than the whole URL in a narrow column, and the
		// domain is the same on every row anyway.
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return '' !== $path && '/' !== $path ? trim( $path, '/' ) : __( 'Home page', 'seedcast-visitor-card' );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only date filters.
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

		$rows  = self::counts( $from, $to );
		$total = array_sum( wp_list_pluck( $rows, 'count' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Where cards come from', 'seedcast-visitor-card' ); ?></h1>

			<p class="description scvc-sources-intro">
				<?php esc_html_e( 'Which pages people were reading when they told you they were coming. Counted from the cards themselves, so nobody is tracked and nothing is recorded about people who did not fill one in.', 'seedcast-visitor-card' ); ?>
			</p>

			<form method="get" class="scvc-filters">
				<input type="hidden" name="page" value="seedcast-visitor-card-sources" />
				<label for="scvc-src-from"><?php esc_html_e( 'From', 'seedcast-visitor-card' ); ?></label>
				<input type="date" id="scvc-src-from" name="from" value="<?php echo esc_attr( $from ); ?>" />
				<label for="scvc-src-to"><?php esc_html_e( 'To', 'seedcast-visitor-card' ); ?></label>
				<input type="date" id="scvc-src-to" name="to" value="<?php echo esc_attr( $to ); ?>" />
				<?php submit_button( __( 'Filter', 'seedcast-visitor-card' ), 'secondary', '', false ); ?>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=seedcast-visitor-card-sources' ) ); ?>">
					<?php esc_html_e( 'Reset', 'seedcast-visitor-card' ); ?>
				</a>
			</form>

			<?php if ( ! $rows ) : ?>
				<p class="description">
					<?php esc_html_e( 'Nothing yet. Once people start filling in the card, this shows which pages brought them.', 'seedcast-visitor-card' ); ?>
				</p>
				</div>
				<?php
				return;
			endif;
			?>

			<table class="widefat striped scvc-sources">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Page', 'seedcast-visitor-card' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Cards', 'seedcast-visitor-card' ); ?></th>
						<th style="width:40%;"><?php esc_html_e( 'Share', 'seedcast-visitor-card' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $rows, 0, self::LIMIT ) as $row ) : ?>
						<tr>
							<td>
								<?php if ( '' !== $row['url'] ) : ?>
									<a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['label'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['label'] ); ?>
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( (string) $row['count'] ); ?></strong></td>
							<td>
								<?php
								$share = $total > 0 ? round( ( $row['count'] / $total ) * 100 ) : 0;
								?>
								<span class="scvc-bar" aria-hidden="true">
									<span class="scvc-bar__fill" style="width:<?php echo esc_attr( (string) $share ); ?>%"></span>
								</span>
								<span class="scvc-bar__label">
									<?php
									printf(
										/* translators: %d: percentage of all cards. */
										esc_html__( '%d%%', 'seedcast-visitor-card' ),
										absint( $share )
									);
									?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( count( $rows ) > self::LIMIT ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of pages not shown. */
						esc_html( _n( 'And %d other page.', 'And %d other pages.', count( $rows ) - self::LIMIT, 'seedcast-visitor-card' ) ),
						absint( count( $rows ) - self::LIMIT )
					);
					?>
				</p>
			<?php endif; ?>

			<p class="description scvc-sources-note">
				<?php esc_html_e( 'This counts cards, not visits to the page. It cannot tell you how many people saw the card and did not fill it in.', 'seedcast-visitor-card' ); ?>
			</p>
		</div>
		<?php
	}
}
