<?php
/**
 * Admin partial: Overview dashboard (the sidebar landing page).
 *
 * Variables in scope (set by CEAFSN_NP_Admin::page_overview()):
 *   @var array $items            All policy rows (up to 200)
 *   @var int   $published        Count of rows with status = published
 *   @var array $missing_pdf      Rows with no PDF attached
 *   @var array $shared_documents One entry per attachment used by >1 record,
 *                                each with attachment_id, filename, records
 *   @var array $topics           Distinct topics in use
 *
 * Every number below is counted from stored rows. Nothing is estimated or
 * filled in with placeholder data.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

$np_total   = count( $items );
$np_drafts  = max( 0, $np_total - $published );
$np_shared  = count( $shared_documents );

$np_attention = array();
foreach ( $shared_documents as $np_doc ) {
	foreach ( $np_doc['records'] as $np_record ) {
		$np_attention[] = array(
			'policy_id' => (int) $np_record->policy_id,
			'title'     => (string) $np_record->title,
			'reason'    => 'shared',
			'filename'  => (string) $np_doc['filename'],
		);
	}
}
foreach ( $missing_pdf as $np_record ) {
	$np_attention[] = array(
		'policy_id' => (int) $np_record->policy_id,
		'title'     => (string) $np_record->title,
		'reason'    => 'missing',
		'filename'  => '',
	);
}

$np_attention_count = count( $np_attention );

$np_url = static function ( string $page, string $extra = '' ): string {
	return admin_url( 'admin.php?page=' . $page . $extra );
};
?>

<div class="wrap ceafsn-np-wrap">

	<?php if ( ! empty( $_GET['ceafsn_np_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_np_error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<a class="ceafsn-sr" href="#ceafsn-np-main"><?php esc_html_e( 'Skip to dashboard content', 'ceafsn-np' ); ?></a>

	<header class="ceafsn-header">
		<p class="ceafsn-header__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-np' ); ?></p>
		<h1 class="ceafsn-header__title"><?php esc_html_e( 'Nutrition Policy', 'ceafsn-np' ); ?></h1>
		<p class="ceafsn-header__subtitle">
			<?php esc_html_e( 'A record stays invisible on the public page until its PDF passes validation. Nothing is listed here without a real, readable document.', 'ceafsn-np' ); ?>
		</p>
		<div class="ceafsn-header__actions">
			<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $np_url( CEAFSN_NP_Admin::PAGE_POLICIES, '&action=add' ) ); ?>">
				<?php esc_html_e( '+ Add policy record', 'ceafsn-np' ); ?>
			</a>
			<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $np_url( CEAFSN_NP_Admin::PAGE_POLICIES ) ); ?>">
				<?php esc_html_e( 'All records', 'ceafsn-np' ); ?>
			</a>
		</div>
	</header>

	<div id="ceafsn-np-main">

		<?php if ( 0 === $np_total ) : ?>

			<div class="ceafsn-card">
				<div class="ceafsn-empty">
					<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
					<h2 class="ceafsn-empty__title"><?php esc_html_e( 'No policy records yet', 'ceafsn-np' ); ?></h2>
					<p class="ceafsn-empty__text">
						<?php esc_html_e( 'This repository is empty on purpose. Add a record with its own PDF and it will appear on the public page once you publish it.', 'ceafsn-np' ); ?>
					</p>
					<div class="ceafsn-empty__action">
						<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $np_url( CEAFSN_NP_Admin::PAGE_POLICIES, '&action=add' ) ); ?>">
							<?php esc_html_e( 'Add the first record', 'ceafsn-np' ); ?>
						</a>
					</div>
				</div>
			</div>

		<?php else : ?>

			<div class="ceafsn-kpi-row">
				<div class="ceafsn-kpi ceafsn-kpi--accent">
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Records', 'ceafsn-np' ); ?></span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $np_total ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: 1: published count, 2: draft count. */
							esc_html__( '%1$s published · %2$s draft', 'ceafsn-np' ),
							esc_html( number_format_i18n( $published ) ),
							esc_html( number_format_i18n( $np_drafts ) )
						);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Published', 'ceafsn-np' ); ?></span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $published ) ); ?></span>
					<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Visible on the public page', 'ceafsn-np' ); ?></span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Topics covered', 'ceafsn-np' ); ?></span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( count( $topics ) ) ); ?></span>
					<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Distinct domains in use', 'ceafsn-np' ); ?></span>
				</div>

				<div class="ceafsn-kpi<?php echo $np_attention_count > 0 ? ' ceafsn-kpi--alert' : ''; ?>">
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Needs a document', 'ceafsn-np' ); ?></span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $np_attention_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: 1: records with no PDF, 2: records sharing a PDF. */
							esc_html__( '%1$s without a PDF · %2$s sharing a document', 'ceafsn-np' ),
							esc_html( number_format_i18n( count( $missing_pdf ) ) ),
							esc_html( number_format_i18n( $np_shared ) )
						);
						?>
					</span>
				</div>
			</div>

			<div class="ceafsn-split">

				<div>
					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<div>
								<h2 class="ceafsn-card__title"><?php esc_html_e( 'Needs your attention', 'ceafsn-np' ); ?></h2>
								<p class="ceafsn-card__hint">
									<?php esc_html_e( 'Records with no PDF, or sharing a document with another record.', 'ceafsn-np' ); ?>
								</p>
							</div>
							<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $np_url( CEAFSN_NP_Admin::PAGE_POLICIES ) ); ?>">
								<?php esc_html_e( 'All records', 'ceafsn-np' ); ?>
							</a>
						</div>

						<?php if ( empty( $np_attention ) ) : ?>
							<div class="ceafsn-empty">
								<span class="ceafsn-empty__mark" aria-hidden="true">&#10003;</span>
								<h3 class="ceafsn-empty__title"><?php esc_html_e( 'Every record has its own document', 'ceafsn-np' ); ?></h3>
								<p class="ceafsn-empty__text">
									<?php esc_html_e( 'No record is missing a PDF or sharing one with another record.', 'ceafsn-np' ); ?>
								</p>
							</div>
						<?php else : ?>
							<ul class="ceafsn-list">
								<?php foreach ( array_slice( $np_attention, 0, 8 ) as $np_item ) : ?>
									<li class="ceafsn-list__item">
										<div class="ceafsn-list__main">
											<p class="ceafsn-list__title"><?php echo esc_html( $np_item['title'] ); ?></p>
											<p class="ceafsn-list__meta">
												<?php
												if ( 'missing' === $np_item['reason'] ) {
													esc_html_e( 'No PDF attached — this record cannot be published.', 'ceafsn-np' );
												} else {
													printf(
														/* translators: %s: shared PDF file name. */
														esc_html__( 'Shares the PDF "%s" with another record.', 'ceafsn-np' ),
														esc_html( '' !== $np_item['filename'] ? $np_item['filename'] : __( 'unnamed', 'ceafsn-np' ) )
													);
												}
												?>
											</p>
										</div>
										<div class="ceafsn-list__actions">
											<?php if ( 'missing' === $np_item['reason'] ) : ?>
												<span class="ceafsn-badge ceafsn-badge--unverified"><?php esc_html_e( 'No PDF', 'ceafsn-np' ); ?></span>
											<?php else : ?>
												<span class="ceafsn-badge ceafsn-badge--pending"><?php esc_html_e( 'Shared PDF', 'ceafsn-np' ); ?></span>
											<?php endif; ?>
											<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $np_url( CEAFSN_NP_Admin::PAGE_POLICIES, '&action=edit&id=' . absint( $np_item['policy_id'] ) ) ); ?>">
												<?php esc_html_e( 'Review', 'ceafsn-np' ); ?>
											</a>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $np_attention_count > 8 ) : ?>
								<div class="ceafsn-card__body">
									<p class="ceafsn-card__hint">
										<?php
										printf(
											/* translators: %s: number of remaining records. */
											esc_html__( 'and %s more — open All records to review the rest.', 'ceafsn-np' ),
											esc_html( number_format_i18n( $np_attention_count - 8 ) )
										);
										?>
									</p>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</section>
				</div>

				<div>
					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Topics in use', 'ceafsn-np' ); ?></h2>
						</div>
						<div class="ceafsn-card__body">
							<?php if ( empty( $topics ) ) : ?>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'No topic recorded yet.', 'ceafsn-np' ); ?></p>
							<?php else : ?>
								<ul class="ceafsn-list">
									<?php foreach ( $topics as $np_topic ) : ?>
										<li class="ceafsn-list__item">
											<div class="ceafsn-list__main">
												<p class="ceafsn-list__title"><?php echo esc_html( $np_topic ); ?></p>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>

					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Before you publish', 'ceafsn-np' ); ?></h2>
						</div>
						<div class="ceafsn-card__body">
							<ul class="ceafsn-list">
								<li class="ceafsn-list__item">
									<div class="ceafsn-list__main">
										<p class="ceafsn-list__meta">
											<?php esc_html_e( 'A record is saved as a draft automatically if its PDF fails validation. Publish only once the document is verified.', 'ceafsn-np' ); ?>
										</p>
									</div>
								</li>
								<li class="ceafsn-list__item">
									<div class="ceafsn-list__main">
										<p class="ceafsn-list__meta">
											<?php esc_html_e( 'Give each policy its own PDF. The same file attached to several records is flagged above.', 'ceafsn-np' ); ?>
										</p>
									</div>
								</li>
							</ul>
							<div class="ceafsn-card__body ceafsn-card__body--tight">
								<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $np_url( CEAFSN_NP_Admin::PAGE_POLICIES ) ); ?>">
									<?php esc_html_e( 'Review all records', 'ceafsn-np' ); ?>
								</a>
							</div>
						</div>
					</section>
				</div>

			</div>

		<?php endif; ?>

	</div>
</div><!-- .ceafsn-np-wrap -->