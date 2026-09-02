<?php
/**
 * The list of people who said they are coming.
 *
 * @package SeedcastVisitorCard\Admin
 */

namespace SeedcastVisitorCard\Admin;

use Seedcast\Core\Install;
use SeedcastVisitorCard\Frontend\Card;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the shared submissions table, filtered to this plugin's rows.
 *
 * Grouped by contact rather than listing every row flat: somebody who filled
 * the card in twice is one person who came back, not two visitors, and a list
 * that says otherwise quietly overstates how the church is doing.
 */
class Submissions {

	/**
	 * Rows per page.
	 */
	private const PER_PAGE = 30;

	/**
	 * The states a submission can be in.
	 *
	 * Stored in the shared table's status column. Nothing is ever deleted by
	 * moving between them: archiving takes something off the working list
	 * while keeping it, which is what a church usually means when it says it
	 * is finished with a card.
	 *
	 * @return array<string, string>
	 */
	public static function states(): array {
		return [
			'pending'   => __( 'New', 'seedcast-visitor-card' ),
			'contacted' => __( 'Done', 'seedcast-visitor-card' ),
			'archived'  => __( 'Archived', 'seedcast-visitor-card' ),
		];
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_scvc_delete', [ $this, 'handle_delete' ] );
		add_action( 'admin_post_scvc_purge', [ $this, 'handle_purge' ] );
		add_action( 'admin_post_scvc_status', [ $this, 'handle_status' ] );
	}

	/**
	 * Mark somebody as contacted, or put them back.
	 *
	 * Uses the status column the shared table already carries. The point is
	 * not record keeping: it is that a card sitting unanswered looks exactly
	 * like one that has been answered, and a pastor working down the list on a
	 * Monday needs to see which is which.
	 *
	 * @return void
	 */
	public function handle_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot do that.', 'seedcast-visitor-card' ) );
		}
		check_admin_referer( 'scvc_status' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified immediately above.
		$raw = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : 'contacted';

		$to  = array_key_exists( $to, self::states() ) ? $to : 'contacted';
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		if ( $ids ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- prepare() takes a single array of replacements; the sniff counts arguments statically and cannot see inside it, so it reads one where there are several.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from a count; values passed separately.
					"UPDATE %i SET status = %s WHERE source = %s AND type = %s AND id IN ( {$placeholders} )",
					array_merge( [ Install::submissions_table(), $to, Card::SOURCE, Card::TYPE ], $ids )
				)
			);
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=seedcast-visitor-card' ) );
		exit;
	}

	/**
	 * Whether the shared submissions table exists.
	 *
	 * Checked rather than assumed, because it belongs to the shared library
	 * and is created on demand. A screen that queries a missing table shows
	 * nothing and gives no reason, which reads as "nobody has filled it in"
	 * rather than as a fault.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table = Install::submissions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return (string) $found === $table;
	}

	/**
	 * Every card, newest first.
	 *
	 * @param array $args from, to, search.
	 * @return array<int, array>
	 */
	public static function all( array $args = [] ): array {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return [];
		}

		$args = wp_parse_args( $args, [
			'from'    => '',
			'to'      => '',
			'search'  => '',
			'state'   => '',
			'orderby' => 'date',
			'order'   => 'desc',
		] );

		/*
		 * The query is assembled from literal fragments only. Every value the
		 * caller supplied goes into $params and is bound by prepare(), so
		 * nothing from a request is ever concatenated into the string. Static
		 * analysis cannot follow that across the appends below, which is what
		 * the suppression on the query is for rather than a claim that
		 * building SQL in a variable is fine in general.
		 */
		$sql    = 'SELECT * FROM %i WHERE source = %s AND type = %s';
		$params = [ Install::submissions_table(), Card::SOURCE, Card::TYPE ];

		if ( '' !== $args['from'] ) {
			$sql     .= ' AND submitted_at >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}
		if ( '' !== $args['to'] ) {
			$sql     .= ' AND submitted_at <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sql     .= ' AND ( author_name LIKE %s OR author_email LIKE %s OR payload LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $args['state'] ) {
			if ( 'pending' === $args['state'] ) {
				// Rows written before there were states have no status of
				// their own, and they are new until somebody says otherwise.
				$sql     .= " AND ( status = %s OR status = '' OR status IS NULL )";
				$params[] = 'pending';
			} else {
				$sql     .= ' AND status = %s';
				$params[] = $args['state'];
			}
		}

		/*
		 * Ordering is built from a fixed map rather than from what was asked
		 * for, so nothing from the request reaches the query. Sorting by name
		 * puts the unnamed last either way: a column of blanks at the top is
		 * not what anyone means by sorting.
		 */
		$columns = [
			'date' => 'submitted_at',
			'who'  => "NULLIF(author_name, '')",
		];

		$column    = $columns[ $args['orderby'] ] ?? $columns['date'];
		$direction = 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- both parts come from the fixed maps above, never from the request.
		$sql .= " ORDER BY {$column} IS NULL, {$column} {$direction} LIMIT 2000";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql holds only literal fragments; every value is bound through $params by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$decoded        = json_decode( (string) $row['payload'], true );
			$row['payload'] = is_array( $decoded ) ? $decoded : [];
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Group rows by the person who sent them.
	 *
	 * Keyed on email where there is one and phone otherwise, so the same
	 * person filling the card in twice reads as one contact who came back
	 * rather than as two visitors.
	 *
	 * @param array $rows Rows from all().
	 * @return array<string, array{rows:array,name:string,email:string,phone:string,latest:string,count:int}>
	 */
	public static function group( array $rows ): array {
		$groups = [];

		foreach ( $rows as $row ) {
			$email = strtolower( trim( (string) $row['author_email'] ) );
			$phone = preg_replace( '/[^0-9]/', '', (string) ( $row['payload']['phone'] ?? '' ) );

			if ( '' !== $email ) {
				$key = 'e:' . $email;
			} elseif ( '' !== $phone ) {
				$key = 'p:' . $phone;
			} else {
				// Nothing to match on. Its own group, so it is never silently
				// folded in with somebody else.
				$key = 'r:' . $row['id'];
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [
					'rows'   => [],
					'name'   => (string) $row['author_name'],
					'email'  => (string) $row['author_email'],
					'phone'  => (string) ( $row['payload']['phone'] ?? '' ),
					'latest' => (string) $row['submitted_at'],
					'status' => (string) ( $row['status'] ?? 'pending' ),
					'count'  => 0,
				];
			}

			$groups[ $key ]['rows'][] = $row;
			$groups[ $key ]['count']++;

			if ( '' === $groups[ $key ]['name'] && '' !== (string) $row['author_name'] ) {
				$groups[ $key ]['name'] = (string) $row['author_name'];
			}
		}

		/*
		 * Cards within a person are always newest first, whatever order the
		 * list itself is in. The list sort arranges people; sorting by name or
		 * by oldest first would otherwise leave the most recent card buried
		 * among the earlier ones and label the wrong one as current.
		 */
		foreach ( $groups as &$group ) {
			usort(
				$group['rows'],
				static function ( $a, $b ) {
					return strcmp( (string) $b['submitted_at'], (string) $a['submitted_at'] );
				}
			);

			$group['latest'] = (string) $group['rows'][0]['submitted_at'];

			// Contact details from the most recent card that carried them, so
			// somebody who changed address is reachable at the new one.
			foreach ( $group['rows'] as $row ) {
				if ( '' === $group['email'] && '' !== (string) $row['author_email'] ) {
					$group['email'] = (string) $row['author_email'];
				}
				if ( '' === $group['phone'] && ! empty( $row['payload']['phone'] ) ) {
					$group['phone'] = (string) $row['payload']['phone'];
				}
			}
		}
		unset( $group );

		return $groups;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters; nothing is written from them.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

		if ( ! self::table_exists() ) {
			$this->render_missing_table();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : 'pending';
		if ( ! array_key_exists( $state, self::states() ) ) {
			$state = 'pending';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';

		$rows   = self::all( [
			'search'  => $search,
			'from'    => $from,
			'to'      => $to,
			'state'   => $state,
			'orderby' => $orderby,
			'order'   => $order,
		] );
		$groups = self::group( $rows );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$pages = max( 1, (int) ceil( count( $groups ) / self::PER_PAGE ) );
		$paged = min( $paged, $pages );
		$page  = array_slice( $groups, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );

		$this->render_header( count( $rows ), count( $groups ) );
		$this->render_tabs( $state, $search, $from, $to );
		$this->render_filters( $search, $from, $to, $state );

		if ( ! $page ) {
			$this->render_empty( '' !== $search || '' !== $from || '' !== $to );
			return;
		}

		$this->render_table( $page, $orderby, $order, $state, $search, $from, $to );
		$this->render_pagination( $paged, $pages, $search, $from, $to );
		$this->render_tools();
	}

	/**
	 * The storage is not there yet.
	 *
	 * @return void
	 */
	private function render_missing_table(): void {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Cards are not being stored yet.', 'seedcast-visitor-card' ); ?></strong>
				<?php esc_html_e( 'The shared Seedcast table has not been created on this site.', 'seedcast-visitor-card' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Deactivating and reactivating Visitor Card creates it. If that does not work, your database user may not be allowed to create tables.', 'seedcast-visitor-card' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Counts at the top.
	 *
	 * @param int $rows   Card count.
	 * @param int $people Distinct people.
	 * @return void
	 */
	private function render_header( int $rows, int $people ): void {
		?>
		<div class="scvc-stats">
			<div class="scvc-stat">
				<span class="scvc-stat__value"><?php echo esc_html( (string) $people ); ?></span>
				<span class="scvc-stat__label"><?php esc_html_e( 'People', 'seedcast-visitor-card' ); ?></span>
			</div>
			<div class="scvc-stat">
				<span class="scvc-stat__value"><?php echo esc_html( (string) $rows ); ?></span>
				<span class="scvc-stat__label"><?php esc_html_e( 'Submissions', 'seedcast-visitor-card' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Search and date filters.
	 *
	 * @param string $search Search term.
	 * @param string $from   From date.
	 * @param string $to     To date.
	 * @return void
	 */
	private function render_filters( string $search, string $from, string $to, string $state = 'pending' ): void {
		?>
		<form method="get" class="scvc-filters">
			<input type="hidden" name="page" value="seedcast-visitor-card" />
			<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>" />
			<label class="screen-reader-text" for="scvc-s"><?php esc_html_e( 'Search', 'seedcast-visitor-card' ); ?></label>
			<input type="search" id="scvc-s" name="s" value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Name, email or message', 'seedcast-visitor-card' ); ?>" />

			<label for="scvc-from"><?php esc_html_e( 'From', 'seedcast-visitor-card' ); ?></label>
			<input type="date" id="scvc-from" name="from" value="<?php echo esc_attr( $from ); ?>" />

			<label for="scvc-to"><?php esc_html_e( 'To', 'seedcast-visitor-card' ); ?></label>
			<input type="date" id="scvc-to" name="to" value="<?php echo esc_attr( $to ); ?>" />

			<?php submit_button( __( 'Filter', 'seedcast-visitor-card' ), 'secondary', '', false ); ?>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=seedcast-visitor-card' ) ); ?>">
				<?php esc_html_e( 'Reset', 'seedcast-visitor-card' ); ?>
			</a>
		</form>
		<?php
	}

	/**
	 * Counts per state, as tabs.
	 *
	 * @param string $current Current state.
	 * @param string $search  Search term.
	 * @param string $from    From date.
	 * @param string $to      To date.
	 * @return void
	 */
	private function render_tabs( string $current, string $search, string $from, string $to ): void {
		echo '<ul class="subsubsub scvc-tabs">';

		$last = array_key_last( self::states() );

		foreach ( self::states() as $state => $label ) {
			$count = count( self::group( self::all( [
				'search' => $search,
				'from'   => $from,
				'to'     => $to,
				'state'  => $state,
			] ) ) );

			$url = add_query_arg(
				[
					'page'  => 'seedcast-visitor-card',
					'state' => $state,
					's'     => $search ?: false,
					'from'  => $from ?: false,
					'to'    => $to ?: false,
				],
				admin_url( 'admin.php' )
			);

			printf(
				'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>%5$s</li>',
				esc_url( $url ),
				$state === $current ? ' class="current"' : '',
				esc_html( $label ),
				(int) $count,
				$state === $last ? '' : ' | '
			);
		}

		echo '</ul>';
	}

	/**
	 * Nothing to show.
	 *
	 * @param bool $filtered Whether a filter is applied.
	 * @return void
	 */
	private function render_empty( bool $filtered ): void {
		echo '<p class="description">';
		if ( $filtered ) {
			esc_html_e( 'No cards match that.', 'seedcast-visitor-card' );
		} else {
			esc_html_e( 'Nobody has filled in the card yet. It appears on your sermons and services automatically, and you can place it anywhere with the shortcode.', 'seedcast-visitor-card' );
		}
		echo '</p>';
	}

	/**
	 * The list.
	 *
	 * @param array $groups Grouped rows.
	 * @return void
	 */
	private function render_table( array $groups, string $orderby = 'date', string $order = 'desc', string $state = 'pending', string $search = '', string $from = '', string $to = '' ): void {
		$context = [
			'state'  => $state,
			'search' => $search,
			'from'   => $from,
			'to'     => $to,
		];
		?>
		<table class="widefat striped scvc-table">
			<thead>
				<tr>
					<?php $this->sortable_header( 'who', __( 'Who', 'seedcast-visitor-card' ), $orderby, $order, $context ); ?>
					<th style="width:200px;"><?php esc_html_e( 'Contact', 'seedcast-visitor-card' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Coming', 'seedcast-visitor-card' ); ?></th>
					<?php $this->sortable_header( 'date', __( 'When they said', 'seedcast-visitor-card' ), $orderby, $order, $context, 'width:150px;' ); ?>
					<th style="width:150px;"></th>
					<th style="width:80px;"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $groups as $group ) : ?>
					<?php $latest = $group['rows'][0]; ?>
					<tr>
						<td>
							<strong>
								<?php
								echo '' !== $group['name']
									? esc_html( $group['name'] )
									: '<em>' . esc_html__( 'No name given', 'seedcast-visitor-card' ) . '</em>';
								?>
							</strong>
							<?php if ( $group['count'] > 1 ) : ?>
								<span class="scvc-badge">
									<?php
									printf(
										/* translators: %d: number of cards from the same person. */
										esc_html( _n( '%d card', '%d cards', $group['count'], 'seedcast-visitor-card' ) ),
										(int) $group['count']
									);
									?>
								</span>
							<?php endif; ?>

							<?php
							/*
							 * Every card, not only the most recent.
							 *
							 * Somebody who fills the card in twice has usually
							 * written something different the second time, and
							 * showing only the newest quietly threw the rest
							 * away. They are still one person, so the grouping
							 * stays; the cards sit inside it.
							 */
							$this->render_card_content( $latest );
							?>

							<?php if ( $group['count'] > 1 ) : ?>
								<details class="scvc-earlier">
									<summary>
										<?php
										printf(
											/* translators: %d: number of earlier cards from the same person. */
											esc_html( _n( '%d earlier card', '%d earlier cards', $group['count'] - 1, 'seedcast-visitor-card' ) ),
											(int) $group['count'] - 1
										);
										?>
									</summary>

									<?php foreach ( array_slice( $group['rows'], 1 ) as $earlier ) : ?>
										<div class="scvc-earlier__card">
											<p class="scvc-earlier__when">
												<?php
												echo esc_html(
													wp_date(
														(string) get_option( 'date_format', 'M j, Y' ),
														(int) strtotime( (string) $earlier['submitted_at'] )
													)
												);
												?>
												<?php $earlier_party = absint( $earlier['payload']['party'] ?? 0 ); ?>
												<?php if ( $earlier_party > 0 ) : ?>
													<span class="scvc-earlier__party">
														<?php
														printf(
															/* translators: %d: how many were coming. */
															esc_html( _n( '%d coming', '%d coming', $earlier_party, 'seedcast-visitor-card' ) ),
															$earlier_party
														);
														?>
													</span>
												<?php endif; ?>
											</p>
											<?php $this->render_card_content( $earlier ); ?>
										</div>
									<?php endforeach; ?>
								</details>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( '' !== $group['email'] ) : ?>
								<a href="<?php echo esc_url( 'mailto:' . $group['email'] ); ?>">
									<?php echo esc_html( $group['email'] ); ?>
								</a><br />
							<?php endif; ?>
							<?php if ( '' !== $group['phone'] ) : ?>
								<?php echo esc_html( $group['phone'] ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$party = (int) ( $latest['payload']['party'] ?? 0 );
							echo $party > 0 ? esc_html( (string) $party ) : '&mdash;';
							?>
						</td>
						<td>
							<?php
							echo esc_html(
								wp_date(
									(string) get_option( 'date_format', 'M j, Y' ),
									(int) strtotime( (string) $latest['submitted_at'] )
								)
							);
							?>
							<?php if ( ! empty( $latest['source_url'] ) ) : ?>
								<br />
								<a class="scvc-from" href="<?php echo esc_url( (string) $latest['source_url'] ); ?>">
									<?php esc_html_e( 'from this page', 'seedcast-visitor-card' ); ?>
								</a>
							<?php endif; ?>
						</td>
						<td>
							<?php $this->status_control( $group ); ?>
						</td>
						<td>
							<?php $this->delete_link( array_column( $group['rows'], 'id' ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * What one card said.
	 *
	 * Used for the most recent card and for each earlier one, so nothing a
	 * visitor wrote is shown in one place and dropped in the other.
	 *
	 * @param array $row One stored row, payload already decoded.
	 * @return void
	 */
	private function render_card_content( array $row ): void {
		$payload = is_array( $row['payload'] ?? null ) ? $row['payload'] : [];

		$status_label = Settings::choice_label(
			Settings::statuses(),
			(string) ( $payload['status'] ?? '' )
		);

		if ( '' !== $status_label ) {
			?>
			<p class="scvc-choice-line">
				<span class="scvc-choice-line__label"><?php echo esc_html( Settings::status_legend() ); ?></span>
				<span class="scvc-pill"><?php echo esc_html( $status_label ); ?></span>
			</p>
			<?php
		}

		$chosen  = [];
		$offered = Settings::interests();
		foreach ( (array) ( $payload['interests'] ?? [] ) as $interest_key ) {
			$label = Settings::choice_label( $offered, (string) $interest_key );
			if ( '' !== $label ) {
				$chosen[] = $label;
			}
		}

		if ( $chosen ) {
			?>
			<p class="scvc-choice-line">
				<span class="scvc-choice-line__label"><?php echo esc_html( Settings::interests_legend() ); ?></span>
				<?php foreach ( $chosen as $label ) : ?>
					<span class="scvc-pill"><?php echo esc_html( $label ); ?></span>
				<?php endforeach; ?>
			</p>
			<?php
		}

		if ( ! empty( $payload['message'] ) ) {
			echo '<p class="scvc-message">' . esc_html( (string) $payload['message'] ) . '</p>';
		}

		if ( ! empty( $payload['heard'] ) ) {
			echo '<p class="scvc-heard">';
			printf(
				/* translators: %s: how they heard about the church. */
				esc_html__( 'Heard through: %s', 'seedcast-visitor-card' ),
				esc_html( (string) $payload['heard'] )
			);
			echo '</p>';
		}
	}

	/**
	 * A column heading that can be sorted by.
	 *
	 * @param string $key     Sort key.
	 * @param string $label   Heading.
	 * @param string $orderby Current sort key.
	 * @param string $order   Current direction.
	 * @param array  $context Filters to keep when sorting.
	 * @param string $style   Optional inline width.
	 * @return void
	 */
	private function sortable_header( string $key, string $label, string $orderby, string $order, array $context, string $style = '' ): void {
		$active = $key === $orderby;

		// Clicking the column you are already sorted by turns it round.
		$next = ( $active && 'asc' === $order ) ? 'desc' : 'asc';

		// Except for dates, where newest first is what anyone wants to see
		// first, so the first click on that column gives them that.
		if ( ! $active && 'date' === $key ) {
			$next = 'desc';
		}

		$url = add_query_arg(
			[
				'page'    => 'seedcast-visitor-card',
				'state'   => $context['state'],
				's'       => $context['search'] ?: false,
				'from'    => $context['from'] ?: false,
				'to'      => $context['to'] ?: false,
				'orderby' => $key,
				'order'   => $next,
			],
			admin_url( 'admin.php' )
		);

		$classes = 'manage-column sortable ' . ( $active ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' );
		if ( $active ) {
			$classes = 'manage-column sorted ' . ( 'asc' === $order ? 'asc' : 'desc' );
		}

		printf(
			'<th scope="col" class="%1$s"%2$s><a href="%3$s"><span>%4$s</span><span class="sorting-indicator"></span></a></th>',
			esc_attr( $classes ),
			$style ? ' style="' . esc_attr( $style ) . '"' : '',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * The followed-up control for one person.
	 *
	 * @param array $group Grouped rows.
	 * @return void
	 */
	private function status_control( array $group ): void {
		$done = 'contacted' === ( $group['status'] ?? 'pending' );
		$ids  = array_column( $group['rows'], 'id' );

		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'scvc_status',
					'ids'    => implode( ',', array_map( 'absint', $ids ) ),
					'to'     => $done ? 'pending' : 'contacted',
				],
				admin_url( 'admin-post.php' )
			),
			'scvc_status'
		);

		if ( $done ) {
			?>
			<a class="scvc-status scvc-status--done" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'Done', 'seedcast-visitor-card' ); ?>
			</a>
			<?php
			return;
		}
		?>
		<a class="scvc-status" href="<?php echo esc_url( $url ); ?>">
			<?php esc_html_e( 'Mark done', 'seedcast-visitor-card' ); ?>
		</a>
		<?php
	}

	/**
	 * A delete control for one person's cards.
	 *
	 * @param array $ids Row IDs.
	 * @return void
	 */
	private function delete_link( array $ids ): void {
		$this->archive_link( $ids );

		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'scvc_delete',
					'ids'    => implode( ',', array_map( 'absint', $ids ) ),
				],
				admin_url( 'admin-post.php' )
			),
			'scvc_delete'
		);
		?>
		<a class="scvc-delete" href="<?php echo esc_url( $url ); ?>"
			onclick="return confirm('<?php echo esc_js( __( 'Delete this person and every card they filled in? This cannot be undone.', 'seedcast-visitor-card' ) ); ?>');">
			<?php esc_html_e( 'Delete', 'seedcast-visitor-card' ); ?>
		</a>
		<?php
	}

	/**
	 * Archive, or put back.
	 *
	 * Offered next to delete because it is what most people actually want:
	 * the card is finished with, but the person is still someone who came.
	 *
	 * @param array $ids Row IDs.
	 * @return void
	 */
	private function archive_link( array $ids ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the current tab to decide which way the control points.
		$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : 'pending';

		$archived = 'archived' === $state;

		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'scvc_status',
					'ids'    => implode( ',', array_map( 'absint', $ids ) ),
					'to'     => $archived ? 'pending' : 'archived',
				],
				admin_url( 'admin-post.php' )
			),
			'scvc_status'
		);
		?>
		<a class="scvc-archive" href="<?php echo esc_url( $url ); ?>">
			<?php
			echo $archived
				? esc_html__( 'Restore', 'seedcast-visitor-card' )
				: esc_html__( 'Archive', 'seedcast-visitor-card' );
			?>
		</a>
		<?php
	}

	/**
	 * Pagination.
	 *
	 * @param int    $paged  Current page.
	 * @param int    $pages  Total pages.
	 * @param string $search Search term.
	 * @param string $from   From date.
	 * @param string $to     To date.
	 * @return void
	 */
	private function render_pagination( int $paged, int $pages, string $search, string $from, string $to ): void {
		if ( $pages < 2 ) {
			return;
		}
		echo '<p class="tablenav-pages">';
		echo wp_kses_post(
			(string) paginate_links( [
				'base'    => add_query_arg( [
					'page'  => 'seedcast-visitor-card',
					's'     => $search,
					'from'  => $from,
					'to'    => $to,
					'paged' => '%#%',
				], admin_url( 'admin.php' ) ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			] )
		);
		echo '</p>';
	}

	/**
	 * Export and purge.
	 *
	 * @return void
	 */
	private function render_tools(): void {
		$export = wp_nonce_url(
			add_query_arg( [ 'action' => 'scvc_export' ], admin_url( 'admin-post.php' ) ),
			'scvc_export'
		);
		?>
		<div class="scvc-tools">
			<a class="button" href="<?php echo esc_url( $export ); ?>">
				<?php esc_html_e( 'Export everything as CSV', 'seedcast-visitor-card' ); ?>
			</a>

			<?php
			/*
			 * Archive rather than delete. Tidying up a list is not the same as
			 * wanting the people on it gone, and this control is reached by
			 * somebody doing housekeeping rather than answering a request to be
			 * removed. Deleting for good is per person, where it is deliberate.
			 */
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvc-purge">
				<?php wp_nonce_field( 'scvc_purge' ); ?>
				<input type="hidden" name="action" value="scvc_purge" />
				<label for="scvc-before"><?php esc_html_e( 'Archive cards from before', 'seedcast-visitor-card' ); ?></label>
				<input type="date" id="scvc-before" name="before" required />
				<button type="submit" class="button">
					<?php esc_html_e( 'Archive', 'seedcast-visitor-card' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Delete specific rows.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot do that.', 'seedcast-visitor-card' ) );
		}
		check_admin_referer( 'scvc_delete' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified immediately above.
		$raw = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '';
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		if ( $ids ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- prepare() takes a single array of replacements; the sniff counts arguments statically and cannot see inside it, so it reads one where there are several.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from a count, values passed separately.
					"DELETE FROM %i WHERE source = %s AND type = %s AND id IN ( {$placeholders} )",
					array_merge( [ Install::submissions_table(), Card::SOURCE, Card::TYPE ], $ids )
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'scvc_deleted', count( $ids ), admin_url( 'admin.php?page=seedcast-visitor-card' ) ) );
		exit;
	}

	/**
	 * Archive everything before a date.
	 *
	 * @return void
	 */
	public function handle_purge(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot do that.', 'seedcast-visitor-card' ) );
		}
		check_admin_referer( 'scvc_purge' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$before = isset( $_POST['before'] ) ? sanitize_text_field( wp_unslash( $_POST['before'] ) ) : '';

		if ( '' !== $before ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET status = %s WHERE source = %s AND type = %s AND submitted_at < %s',
					Install::submissions_table(),
					'archived',
					Card::SOURCE,
					Card::TYPE,
					$before . ' 00:00:00'
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=seedcast-visitor-card' ) );
		exit;
	}
}
