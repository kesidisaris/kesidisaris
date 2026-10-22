<?php
/**
 * Plugin Name: Eldico Sync
 * Description: Συγχρονισμός καταλόγου προϊόντων Eldico (XML) με το WooCommerce. Variable products από το ParentCode, mapping κατηγοριών 3 επιπέδων, τιμές, απόθεμα με ποσότητες, εικόνες, brands, χαρακτηριστικά. Προϊόντα με tag GAS24 δεν ενημερώνονται.
 * Version: 2.2.0
 * Author: Eldico
 * Text Domain: eldico-sync
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ELDS_VERSION', '2.2.0' );
define( 'ELDS_OPT_SETTINGS', 'elds_settings' );
define( 'ELDS_OPT_MAPPING',  'elds_cat_mapping' );
define( 'ELDS_OPT_QUEUE',    'elds_import_queue' );
define( 'ELDS_XML_FILE',     'eldico-feed.xml' ); // παλιά θέση (uploads) — μεταφέρεται αυτόματα σε προστατευμένο φάκελο
define( 'ELDS_VAT_RATE',     1.24 );              // διαιρέτης όταν οι τιμές του XML είναι με ΦΠΑ 24%
define( 'ELDS_OPT_LOCK',     'elds_run_lock' );

class Eldico_Sync {

	private static $instance = null;

	/** Προτιμώμενα spec keys ως χαρακτηριστικό παραλλαγής (με σειρά). */
	private $variation_pref = [
		'Χρώμα', 'Μέγεθος', 'Μέγεθος (cm)', 'Διαστάσεις (cm)', 'Διάσταση (cm)',
		'Χωρητικότητα (L)', 'Χωρητικότητα (ml)', 'Τύπος',
	];

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',  [ $this, 'admin_menu' ] );
		add_action( 'admin_init',  [ $this, 'handle_posts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

		add_action( 'wp_ajax_elds_import_batch', [ $this, 'ajax_import_batch' ] );

		// Αυτόματος ωριαίος συγχρονισμός (WP-Cron).
		add_action( 'init',               [ $this, 'maybe_schedule_cron' ] );
		add_action( 'elds_hourly_sync',   [ $this, 'cron_hourly_sync' ] );
		add_action( 'elds_continue_sync', [ $this, 'cron_continue_sync' ] );
	}

	/* ---------------------------------------------------------------------
	 * Ρυθμίσεις
	 * ------------------------------------------------------------------- */

	public function settings() {
		return wp_parse_args( get_option( ELDS_OPT_SETTINGS, [] ), [
			'feed_url'        => '',
			'price_field'     => 'RetailPrice',   // RetailPrice | WholesalePrice
			'zero_price'      => 'wholesale_x',   // skip_product | wholesale_x | no_price
			'zero_multiplier' => '1.9',           // συντελεστής επί της χονδρικής όταν λιανική = 0
			'vat_included'    => 1,               // οι τιμές πώλησης του XML είναι με ΦΠΑ 24% -> αποθήκευση χωρίς ΦΠΑ
			'variable_products' => 1,             // ομάδες ParentCode -> variable products
			'manage_stock'    => 1,               // διαχείριση αποθέματος με ποσότητα (Availability)
			'import_images'   => 1,
			'import_specs'    => 1,               // Specifications -> attributes
			'append_warning'  => 1,               // WARNGR στο τέλος της περιγραφής
			'append_addinfo'  => 0,               // AdditionalInfos στο τέλος της περιγραφής
			'append_manual'   => 1,               // link εγχειριδίου (ManualLink)
			'skip_tag'        => 'GAS24',         // προϊόντα με αυτό το tag δεν ενημερώνονται
			'content_tag'     => 'GAS',           // μόνο περιεχόμενο/εικόνες — όχι τιμή & απόθεμα
			'update_existing' => 1,
			'create_new'      => 1,
			'auto_sync'       => 0,               // ωριαίος αυτόματος συγχρονισμός
			'batch_size'      => 10,
		] );
	}

	/* ---------------------------------------------------------------------
	 * Admin UI
	 * ------------------------------------------------------------------- */

	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Eldico Sync',
			'Eldico Sync',
			'manage_woocommerce',
			'eldico-sync',
			[ $this, 'render_page' ]
		);
	}

	public function admin_assets( $hook ) {
		if ( strpos( $hook, 'eldico-sync' ) === false ) return;
		wp_enqueue_script( 'jquery' );
	}

	private function tab() {
		$t = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'source';
		return in_array( $t, [ 'source', 'categories', 'import', 'settings' ], true ) ? $t : 'source';
	}

	public function render_page() {
		$tab = $this->tab();
		echo '<div class="wrap"><h1>Eldico Sync</h1>';
		$tabs = [
			'source'     => '1. Πηγή XML',
			'categories' => '2. Κατηγορίες',
			'import'     => '3. Εισαγωγή',
			'settings'   => 'Ρυθμίσεις',
		];
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( admin_url( 'admin.php?page=eldico-sync&tab=' . $key ) ),
				$tab === $key ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-error"><p>Απαιτείται ενεργό WooCommerce.</p></div></div>';
			return;
		}

		switch ( $tab ) {
			case 'categories': $this->render_categories(); break;
			case 'import':     $this->render_import();     break;
			case 'settings':   $this->render_settings();   break;
			default:           $this->render_source();     break;
		}
		echo '</div>';
	}

	/* ---------------- Tab: Πηγή ---------------- */

	private function render_source() {
		$s    = $this->settings();
		$file = $this->xml_path();
		$has  = file_exists( $file );
		?>
		<h2>Πηγή XML καταλόγου Eldico</h2>
		<?php if ( $has ) :
			$info = $this->feed_summary();
			?>
			<div class="notice notice-success inline"><p>
				✔ Υπάρχει αποθηκευμένο XML (<?php echo esc_html( size_format( filesize( $file ) ) ); ?>,
				<?php echo esc_html( date_i18n( 'd/m/Y H:i', filemtime( $file ) ) ); ?>)
				— <strong><?php echo (int) $info['products']; ?></strong> προϊόντα
				(<strong><?php echo (int) $info['groups']; ?></strong> ομάδες παραλλαγών,
				<strong><?php echo (int) $info['simples']; ?></strong> απλά),
				<strong><?php echo (int) $info['categories']; ?></strong> μονοπάτια κατηγοριών.
			</p></div>
		<?php endif; ?>

		<form method="post" style="max-width:720px">
			<?php wp_nonce_field( 'elds_source' ); ?>
			<table class="form-table">
				<tr>
					<th>URL του feed</th>
					<td>
						<input type="url" name="elds_feed_url" class="regular-text" style="width:100%"
							value="<?php echo esc_attr( $s['feed_url'] ); ?>"
							placeholder="https://eldico-b2b.gr/.../seasonal.xml">
						<p class="description">Θα κατεβαίνει από εδώ και στον αυτόματο συγχρονισμό.</p>
					</td>
				</tr>
			</table>
			<p><button class="button button-primary" name="elds_action" value="fetch_url">Λήψη από URL</button></p>
		</form>

		<hr>
		<form method="post" enctype="multipart/form-data" style="max-width:720px">
			<?php wp_nonce_field( 'elds_source' ); ?>
			<table class="form-table">
				<tr>
					<th>ή Μεταφόρτωση αρχείου</th>
					<td><input type="file" name="elds_xml_file" accept=".xml"></td>
				</tr>
			</table>
			<p><button class="button" name="elds_action" value="upload_file">Μεταφόρτωση XML</button></p>
		</form>
		<?php
		if ( $has ) {
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=eldico-sync&tab=categories' ) ) . '">Συνέχεια στις Κατηγορίες →</a></p>';
		}
	}

	/* ---------------- Tab: Κατηγορίες ---------------- */

	private function render_categories() {
		$file = $this->xml_path();
		if ( ! file_exists( $file ) ) {
			echo '<div class="notice notice-warning inline"><p>Δεν υπάρχει XML. Πήγαινε πρώτα στην <a href="' . esc_url( admin_url( 'admin.php?page=eldico-sync&tab=source' ) ) . '">Πηγή XML</a>.</p></div>';
			return;
		}

		$tree    = $this->category_tree();
		$mapping = get_option( ELDS_OPT_MAPPING, [] );
		$wc_cats = $this->wc_category_options();
		?>
		<h2>Αντιστοίχιση κατηγοριών</h2>
		<p>Το feed της Eldico έχει κατηγορίες 3 επιπέδων (π.χ. <em>Είδη Παραλίας → Κολύμβηση → Βατραχοπέδιλα</em>).
			Για κάθε μονοπάτι επίλεξε υπάρχουσα κατηγορία WooCommerce, «➕ Δημιουργία νέας» (φτιάχνεται ολόκληρη η ιεραρχία) ή «Παράλειψη».
			<strong>Προϊόντα σε μονοπάτια με «Παράλειψη» δεν εισάγονται καθόλου.</strong></p>

		<form method="post">
			<?php wp_nonce_field( 'elds_mapping' ); ?>
			<p>
				<button class="button" type="button" onclick="jQuery('.elds-map').val('__create__')">Όλα → Δημιουργία νέας</button>
				<button class="button" type="button" onclick="jQuery('.elds-map').val('')">Όλα → Παράλειψη</button>
			</p>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr>
					<th>Κατηγορία Eldico</th>
					<th style="width:90px">Προϊόντα</th>
					<th style="width:360px">Κατηγορία WooCommerce</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $tree as $path => $count ) :
					$key = md5( $path );
					$cur = $mapping[ $key ] ?? '__create__';
					$parts = array_map( 'trim', explode( '->', $path ) );
					?>
					<tr>
						<td><?php echo esc_html( implode( ' → ', $parts ) ); ?></td>
						<td><?php echo (int) $count; ?></td>
						<td>
							<select name="elds_map[<?php echo esc_attr( $key ); ?>]" class="elds-map" style="width:100%">
								<option value="" <?php selected( $cur, '' ); ?>>— Παράλειψη —</option>
								<option value="__create__" <?php selected( $cur, '__create__' ); ?>>➕ Δημιουργία νέας (<?php echo esc_html( implode( ' → ', $parts ) ); ?>)</option>
								<?php foreach ( $wc_cats as $id => $label ) : ?>
									<option value="<?php echo (int) $id; ?>" <?php selected( (string) $cur, (string) $id ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" name="elds_map_path[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $path ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary" name="elds_action" value="save_mapping">Αποθήκευση αντιστοίχισης</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eldico-sync&tab=import' ) ); ?>">Συνέχεια στην Εισαγωγή →</a></p>
		</form>
		<?php
	}

	/* ---------------- Tab: Εισαγωγή ---------------- */

	private function render_import() {
		$file = $this->xml_path();
		if ( ! file_exists( $file ) ) {
			echo '<div class="notice notice-warning inline"><p>Δεν υπάρχει XML. Πήγαινε πρώτα στην Πηγή XML.</p></div>';
			return;
		}
		$s       = $this->settings();
		$info    = $this->feed_summary();
		$mapping = get_option( ELDS_OPT_MAPPING, [] );
		$bt      = $this->brand_taxonomy();
		?>
		<h2>Εισαγωγή / Συγχρονισμός</h2>
		<ul style="max-width:820px">
			<li>📦 Προϊόντα στο feed: <strong><?php echo (int) $info['products']; ?></strong>
				— <?php echo $s['variable_products']
					? '<strong>' . (int) $info['groups'] . '</strong> variable products (' . (int) $info['variations'] . ' παραλλαγές) + <strong>' . (int) $info['simples'] . '</strong> απλά'
					: 'όλα ως απλά προϊόντα'; ?>
				— mapping για <?php echo count( $mapping ); ?> μονοπάτια κατηγοριών</li>
			<li>💶 Τιμή: <code><?php echo esc_html( $s['price_field'] ); ?></code>
				<?php if ( 'RetailPrice' === $s['price_field'] ) : ?>
					<?php echo $s['vat_included'] ? '— αποθήκευση χωρίς ΦΠΑ (τιμή XML / 1.24)' : '— αποθήκευση όπως στο XML'; ?>
					— όταν λιανική = 0:
					<?php
					echo esc_html( [
						'skip_product' => 'παράλειψη',
						'wholesale_x'  => 'χονδρική × ' . $s['zero_multiplier'],
						'no_price'     => 'χωρίς τιμή',
					][ $s['zero_price'] ] ?? '' );
					?>
				<?php endif; ?>
			</li>
			<li>📊 Απόθεμα: <?php echo $s['manage_stock'] ? 'διαχείριση με ποσότητα (πεδίο Availability, ανά παραλλαγή)' : 'μόνο κατάσταση (σε απόθεμα / εξαντλημένο)'; ?></li>
			<li>🏷 Brands (Manufacturer): <?php echo $bt ? 'taxonomy <code>' . esc_html( $bt ) . '</code>' : '<span style="color:#b32d2e">δεν βρέθηκε brand taxonomy — θα μπει ως χαρακτηριστικό</span>'; ?></li>
			<li>🖼 Εικόνες: <?php echo $s['import_images'] ? 'ναι (μόνο νέες — δεν ξανακατεβαίνουν)' : 'όχι'; ?></li>
			<li>🔒 Προϊόντα με tag <code><?php echo esc_html( $s['skip_tag'] ); ?></code> δεν ενημερώνονται καθόλου.
				Με tag <code><?php echo esc_html( $s['content_tag'] ); ?></code>: μόνο περιεχόμενο & εικόνες — τιμή/απόθεμα παγωμένα.</li>
		</ul>

		<p>
			<button id="elds-start" class="button button-primary button-hero">▶ Έναρξη συγχρονισμού</button>
			<button id="elds-stop" class="button button-hero" style="display:none">⏹ Διακοπή</button>
		</p>

		<div id="elds-progress" style="display:none;max-width:820px">
			<div style="background:#e5e5e5;border-radius:4px;overflow:hidden;height:26px">
				<div id="elds-bar" style="background:#2271b1;height:26px;width:0%;color:#fff;text-align:center;line-height:26px;transition:width .3s">0%</div>
			</div>
			<p id="elds-status"></p>
			<div id="elds-log" style="background:#fff;border:1px solid #ccd0d4;max-height:340px;overflow:auto;padding:8px 12px;font-family:monospace;font-size:12px"></div>
		</div>

		<script>
		jQuery(function($){
			var stopped = false, total = 0, done = 0;

			function log(line){
				$('#elds-log').prepend('<div>'+line+'</div>');
			}
			function batch(offset){
				if (stopped) { $('#elds-status').text('Διακόπηκε στο ' + done + '/' + total); toggle(false); return; }
				$.post(ajaxurl, {
					action: 'elds_import_batch',
					_wpnonce: '<?php echo esc_js( wp_create_nonce( 'elds_import' ) ); ?>',
					offset: offset
				}).done(function(r){
					if (!r || !r.success) { log('Σφάλμα: ' + (r && r.data ? r.data : 'άγνωστο')); toggle(false); return; }
					var d = r.data;
					total = d.total; done = d.done;
					var pct = total ? Math.round(done*100/total) : 100;
					$('#elds-bar').css('width', pct+'%').text(pct+'%');
					$('#elds-status').text(done + ' / ' + total + ' στοιχεία (απλά + ομάδες)');
					(d.log || []).forEach(function(l){ log(l); });
					if (d.finished) { $('#elds-status').text('✔ Ολοκληρώθηκε: ' + done + ' / ' + total); toggle(false); }
					else { batch(d.next); }
				}).fail(function(){
					log('Σφάλμα δικτύου — ξαναπροσπάθησε με Έναρξη (συνεχίζει από εκεί που έμεινε).');
					toggle(false);
				});
			}
			function toggle(running){
				$('#elds-start').toggle(!running);
				$('#elds-stop').toggle(running);
			}
			$('#elds-start').on('click', function(){
				stopped = false; toggle(true);
				$('#elds-progress').show();
				batch(-1); // -1 = ξεκίνα/συνέχισε ουρά
			});
			$('#elds-stop').on('click', function(){ stopped = true; });
		});
		</script>
		<?php
	}

	/* ---------------- Tab: Ρυθμίσεις ---------------- */

	private function render_settings() {
		$s = $this->settings();
		?>
		<h2>Ρυθμίσεις</h2>
		<form method="post" style="max-width:820px">
			<?php wp_nonce_field( 'elds_settings' ); ?>
			<table class="form-table">
				<tr>
					<th>Παραλλαγές</th>
					<td>
						<label><input type="checkbox" name="elds[variable_products]" value="1" <?php checked( $s['variable_products'] ); ?>>
							Ομάδες <code>ParentCode</code> → variable products με παραλλαγές</label>
						<p class="description">Το χαρακτηριστικό παραλλαγής βρίσκεται αυτόματα (Χρώμα, Μέγεθος κ.λπ. — αλλιώς «Έκδοση» από τη διαφορά των τίτλων). Αν απενεργοποιηθεί, όλα εισάγονται ως απλά προϊόντα.</p>
					</td>
				</tr>
				<tr>
					<th>Πεδίο τιμής</th>
					<td>
						<select name="elds[price_field]">
							<option value="RetailPrice"    <?php selected( $s['price_field'], 'RetailPrice' ); ?>>RetailPrice (προτεινόμενη λιανική)</option>
							<option value="WholesalePrice" <?php selected( $s['price_field'], 'WholesalePrice' ); ?>>WholesalePrice (χονδρική)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>ΦΠΑ</th>
					<td>
						<label><input type="checkbox" name="elds[vat_included]" value="1" <?php checked( $s['vat_included'] ); ?>>
							Οι τιμές πώλησης του XML είναι με ΦΠΑ 24%</label>
						<p class="description">Όταν είναι ενεργό, η τιμή που καταχωρείται στο WooCommerce είναι <code>RetailPrice / 1.24</code> (αποφορολογημένη, με 4 δεκαδικά ώστε μετά το ΦΠΑ να επανέρχεται η ακριβής τιμή του XML). Ισχύει μόνο για το πεδίο RetailPrice· η χονδρική και ο υπολογισμός «χονδρική × συντελεστή» δεν διαιρούνται. Βεβαιώσου ότι στο WooCommerce → Ρυθμίσεις → Φόροι οι τιμές καταχωρούνται <em>χωρίς</em> φόρο.</p>
					</td>
				</tr>
				<tr>
					<th>Όταν η λιανική είναι 0</th>
					<td>
						<select name="elds[zero_price]">
							<option value="wholesale_x"  <?php selected( $s['zero_price'], 'wholesale_x' ); ?>>Χονδρική × συντελεστή</option>
							<option value="skip_product" <?php selected( $s['zero_price'], 'skip_product' ); ?>>Παράλειψη προϊόντος / παραλλαγής</option>
							<option value="no_price"     <?php selected( $s['zero_price'], 'no_price' ); ?>>Εισαγωγή χωρίς τιμή</option>
						</select>
						Συντελεστής: <input type="number" step="0.01" min="1" name="elds[zero_multiplier]" value="<?php echo esc_attr( $s['zero_multiplier'] ); ?>" style="width:90px">
						<p class="description">Στο feed υπάρχουν ~340 προϊόντα με RetailPrice = 0 (όλα έχουν χονδρική). Το αποτέλεσμα στρογγυλοποιείται στα 2 δεκαδικά.</p>
					</td>
				</tr>
				<tr>
					<th>Απόθεμα</th>
					<td>
						<label><input type="checkbox" name="elds[manage_stock]" value="1" <?php checked( $s['manage_stock'] ); ?>>
							Διαχείριση αποθέματος με ποσότητα (πεδίο <code>Availability</code>)</label>
						<p class="description">Αν απενεργοποιηθεί, ορίζεται μόνο «σε απόθεμα / εξαντλημένο» (Availability &gt; 0 / = 0).</p>
					</td>
				</tr>
				<tr>
					<th>Εικόνες</th>
					<td><label><input type="checkbox" name="elds[import_images]" value="1" <?php checked( $s['import_images'] ); ?>>
						Λήψη εικόνων (featured + gallery + εικόνα ανά παραλλαγή, χωρίς διπλή λήψη σε επόμενα sync)</label></td>
				</tr>
				<tr>
					<th>Χαρακτηριστικά</th>
					<td><label><input type="checkbox" name="elds[import_specs]" value="1" <?php checked( $s['import_specs'] ); ?>>
						Specifications → ορατά χαρακτηριστικά προϊόντος (Χρώμα, Υλικό, Διαστάσεις κ.λπ.)</label></td>
				</tr>
				<tr>
					<th>Περιγραφή</th>
					<td>
						<label><input type="checkbox" name="elds[append_warning]" value="1" <?php checked( $s['append_warning'] ); ?>>
							Προσθήκη προειδοποιήσεων ασφαλείας (<code>WARNGR</code>) στο τέλος</label><br>
						<label><input type="checkbox" name="elds[append_addinfo]" value="1" <?php checked( $s['append_addinfo'] ); ?>>
							Προσθήκη <code>AdditionalInfos</code> (λοιπά χαρακτηριστικά) στο τέλος</label><br>
						<label><input type="checkbox" name="elds[append_manual]" value="1" <?php checked( $s['append_manual'] ); ?>>
							Προσθήκη συνδέσμου εγχειριδίου (<code>ManualLink</code>) όπου υπάρχει</label>
					</td>
				</tr>
				<tr>
					<th>Tag κλειδώματος</th>
					<td>
						<input type="text" name="elds[skip_tag]" value="<?php echo esc_attr( $s['skip_tag'] ); ?>" style="width:140px">
						<p class="description">Προϊόντα (ή variable parents) που φέρουν αυτό το product tag στο WooCommerce δεν ενημερώνονται ποτέ. Αν εμφανιστεί μέσα στα δεδομένα ενός προϊόντος στο XML, το προϊόν επίσης παραλείπεται.</p>
					</td>
				</tr>
				<tr>
					<th>Tag μερικού κλειδώματος</th>
					<td>
						<input type="text" name="elds[content_tag]" value="<?php echo esc_attr( $s['content_tag'] ); ?>" style="width:140px">
						<p class="description">Προϊόντα (ή variable parents) με αυτό το product tag ενημερώνονται <strong>μόνο</strong> ως προς περιεχόμενο και εικόνες (τίτλος, περιγραφές, χαρακτηριστικά, φωτογραφίες) — <strong>τιμή και απόθεμα δεν αλλάζουν</strong>. Σε variable parent, ισχύει για όλες τις παραλλαγές του.</p>
					</td>
				</tr>
				<tr>
					<th>Συμπεριφορά</th>
					<td>
						<label><input type="checkbox" name="elds[create_new]" value="1" <?php checked( $s['create_new'] ); ?>> Δημιουργία νέων προϊόντων</label><br>
						<label><input type="checkbox" name="elds[update_existing]" value="1" <?php checked( $s['update_existing'] ); ?>> Ενημέρωση υπαρχόντων (κλειδί: SKU = <code>Code</code>)</label>
					</td>
				</tr>
				<tr>
					<th>Αυτόματος συγχρονισμός</th>
					<td>
						<label><input type="checkbox" name="elds[auto_sync]" value="1" <?php checked( $s['auto_sync'] ); ?>>
							Ωριαίος αυτόματος συγχρονισμός από το αποθηκευμένο URL (WP-Cron)</label>
						<p class="description">Ο κανόνας <?php echo esc_html( $s['skip_tag'] ); ?> ισχύει και στο αυτόματο sync. Νέες εικόνες κατεβαίνουν μόνο για νέα προϊόντα.</p>
					</td>
				</tr>
				<tr>
					<th>Μέγεθος παρτίδας</th>
					<td><input type="number" min="1" max="50" name="elds[batch_size]" value="<?php echo (int) $s['batch_size']; ?>" style="width:90px">
						<p class="description">Στοιχεία ανά παρτίδα (μια ομάδα παραλλαγών μετράει ως 1 στοιχείο).</p></td>
				</tr>
			</table>
			<p><button class="button button-primary" name="elds_action" value="save_settings">Αποθήκευση</button></p>
		</form>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * POST handlers
	 * ------------------------------------------------------------------- */

	public function handle_posts() {
		if ( empty( $_POST['elds_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) return;
		$action = sanitize_key( $_POST['elds_action'] );

		if ( 'fetch_url' === $action ) {
			check_admin_referer( 'elds_source' );
			$url = esc_url_raw( $_POST['elds_feed_url'] ?? '' );
			if ( ! $url ) { $this->notice( 'Δώσε URL.', 'error' ); return; }
			$s = $this->settings(); $s['feed_url'] = $url;
			update_option( ELDS_OPT_SETTINGS, $s );
			$res = $this->download_feed( $url );
			$this->notice( is_wp_error( $res ) ? 'Αποτυχία λήψης: ' . $res->get_error_message() : 'Το XML κατέβηκε.', is_wp_error( $res ) ? 'error' : 'success' );
		}

		if ( 'upload_file' === $action ) {
			check_admin_referer( 'elds_source' );
			if ( empty( $_FILES['elds_xml_file']['tmp_name'] ) ) { $this->notice( 'Δεν επιλέχθηκε αρχείο.', 'error' ); return; }
			$raw = file_get_contents( $_FILES['elds_xml_file']['tmp_name'] );
			$ok  = $this->save_feed( $raw );
			$this->notice( $ok ? 'Το XML αποθηκεύτηκε.' : 'Μη έγκυρο XML — δεν αποθηκεύτηκε.', $ok ? 'success' : 'error' );
		}

		if ( 'save_mapping' === $action ) {
			check_admin_referer( 'elds_mapping' );
			$map   = [];
			$paths = [];
			foreach ( (array) ( $_POST['elds_map'] ?? [] ) as $key => $val ) {
				$key = sanitize_key( $key );
				$map[ $key ]   = ( '__create__' === $val ) ? '__create__' : ( '' === $val ? '' : (int) $val );
				$paths[ $key ] = sanitize_text_field( wp_unslash( $_POST['elds_map_path'][ $key ] ?? '' ) );
			}
			update_option( ELDS_OPT_MAPPING, $map );
			update_option( ELDS_OPT_MAPPING . '_paths', $paths );
			$this->notice( 'Η αντιστοίχιση αποθηκεύτηκε.' );
		}

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'elds_settings' );
			$in = (array) ( $_POST['elds'] ?? [] );
			$s  = $this->settings();
			$s['price_field']       = in_array( $in['price_field'] ?? '', [ 'RetailPrice', 'WholesalePrice' ], true ) ? $in['price_field'] : 'RetailPrice';
			$s['zero_price']        = in_array( $in['zero_price'] ?? '', [ 'skip_product', 'wholesale_x', 'no_price' ], true ) ? $in['zero_price'] : 'wholesale_x';
			$s['zero_multiplier']   = max( 1, (float) ( $in['zero_multiplier'] ?? 1.9 ) );
			$s['vat_included']      = empty( $in['vat_included'] )      ? 0 : 1;
			$s['variable_products'] = empty( $in['variable_products'] ) ? 0 : 1;
			$s['manage_stock']      = empty( $in['manage_stock'] )      ? 0 : 1;
			$s['import_images']     = empty( $in['import_images'] )     ? 0 : 1;
			$s['import_specs']      = empty( $in['import_specs'] )      ? 0 : 1;
			$s['append_warning']    = empty( $in['append_warning'] )    ? 0 : 1;
			$s['append_addinfo']    = empty( $in['append_addinfo'] )    ? 0 : 1;
			$s['append_manual']     = empty( $in['append_manual'] )     ? 0 : 1;
			$s['create_new']        = empty( $in['create_new'] )        ? 0 : 1;
			$s['update_existing']   = empty( $in['update_existing'] )   ? 0 : 1;
			$s['auto_sync']         = empty( $in['auto_sync'] )         ? 0 : 1;
			$s['skip_tag']          = sanitize_text_field( $in['skip_tag'] ?? 'GAS24' );
			$s['content_tag']       = sanitize_text_field( $in['content_tag'] ?? 'GAS' );
			$s['batch_size']        = min( 50, max( 1, (int) ( $in['batch_size'] ?? 10 ) ) );
			update_option( ELDS_OPT_SETTINGS, $s );
			$this->notice( 'Οι ρυθμίσεις αποθηκεύτηκαν.' );
		}
	}

	private function notice( $msg, $type = 'success' ) {
		add_action( 'admin_notices', function () use ( $msg, $type ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $msg ) );
		} );
	}

	/* ---------------------------------------------------------------------
	 * Feed: λήψη / αποθήκευση / parsing
	 * ------------------------------------------------------------------- */

	/**
	 * Προστατευμένος φάκελος για το feed (περιέχει χονδρικές τιμές).
	 * .htaccess + index.php + απρόβλεπτο όνομα αρχείου (καλύπτει και nginx).
	 */
	private function data_dir() {
		static $dir = null;
		if ( null !== $dir ) return $dir;

		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'eldico-sync';
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		// Μεταφορά από την παλιά, δημόσια θέση.
		$old = trailingslashit( $up['basedir'] ) . ELDS_XML_FILE;
		if ( file_exists( $old ) ) {
			if ( ! file_exists( $dir . '/' . $this->feed_basename( 'feed' ) ) ) {
				@rename( $old, $dir . '/' . $this->feed_basename( 'feed' ) );
			} else {
				@unlink( $old );
			}
		}
		return $dir;
	}

	private function feed_basename( $prefix ) {
		return $prefix . '-' . substr( wp_hash( 'elds_feed_file' ), 0, 20 ) . '.xml';
	}

	/** Το «ζωντανό» feed (τελευταία λήψη/μεταφόρτωση). */
	private function xml_path() {
		return $this->data_dir() . '/' . $this->feed_basename( 'feed' );
	}

	/** Αντίγραφο του feed για το τρέχον run, ώστε οι θέσεις της ουράς να μη μετατοπίζονται. */
	private function run_xml_path() {
		return $this->data_dir() . '/' . $this->feed_basename( 'run' );
	}

	private function active_xml_path() {
		$snap = $this->run_xml_path();
		return file_exists( $snap ) ? $snap : $this->xml_path();
	}

	private function start_run() {
		$live = $this->xml_path();
		if ( file_exists( $live ) ) copy( $live, $this->run_xml_path() );
		update_option( ELDS_OPT_QUEUE, [
			'pos'       => 0,
			'finished'  => 0,
			'feed_time' => file_exists( $live ) ? filemtime( $live ) : 0,
		], false );
	}

	/* ---- Lock (αποτρέπει ταυτόχρονα batches από AJAX / cron) ---- */

	private function acquire_lock() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
			ELDS_OPT_LOCK, (string) time()
		) );
		if ( $wpdb->rows_affected ) return true;

		// Ληγμένο lock (π.χ. fatal στη μέση) -> αφαίρεση και νέα προσπάθεια.
		if ( ! $this->is_locked() ) {
			$wpdb->delete( $wpdb->options, [ 'option_name' => ELDS_OPT_LOCK ] );
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
				ELDS_OPT_LOCK, (string) time()
			) );
			return (bool) $wpdb->rows_affected;
		}
		return false;
	}

	private function release_lock() {
		global $wpdb;
		$wpdb->delete( $wpdb->options, [ 'option_name' => ELDS_OPT_LOCK ] );
	}

	private function is_locked() {
		global $wpdb;
		$t = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", ELDS_OPT_LOCK ) );
		return $t && ( time() - $t ) <= 300;
	}

	/** Προϊόν που προστατεύεται από tag (πλήρες ή μερικό κλείδωμα) δεν διαγράφεται/μετατρέπεται. */
	private function is_tag_locked( $post_id, array $s ) {
		foreach ( [ $s['skip_tag'], $s['content_tag'] ] as $tag ) {
			if ( $tag && has_term( $tag, 'product_tag', $post_id ) ) return $tag;
		}
		return '';
	}

	private function download_feed( $url ) {
		$resp = wp_remote_get( $url, [ 'timeout' => 120 ] );
		if ( is_wp_error( $resp ) ) return $resp;
		$code = wp_remote_retrieve_response_code( $resp );
		if ( 200 !== $code ) return new WP_Error( 'elds_http', 'HTTP ' . $code );
		$body = wp_remote_retrieve_body( $resp );
		if ( ! $this->save_feed( $body ) ) return new WP_Error( 'elds_xml', 'Μη έγκυρο XML.' );
		return true;
	}

	private function save_feed( $raw ) {
		if ( ! $raw || false === strpos( $raw, '<Product' ) ) return false;
		if ( false === $this->parse_xml( $raw ) ) return false;
		file_put_contents( $this->xml_path(), $raw );
		delete_transient( 'elds_feed_summary' );
		return true;
	}

	/**
	 * Ελαφρύ sanitization για ανεσκάπευτους χαρακτήρες (προληπτικά) + parsing.
	 */
	private function parse_xml( $raw ) {
		// Escape «γυμνών» & μόνο ΕΚΤΟΣ CDATA (μέσα σε CDATA θα εμφανίζονταν κυριολεκτικά ως &amp;).
		$parts = preg_split( '/(<!\[CDATA\[.*?\]\]>)/s', $raw, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i % 2 ) {
				$parts[ $i ] = preg_replace( '/&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $part );
			}
		}
		$raw = implode( '', $parts );
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_PARSEHUGE );
		return $xml;
	}

	private function load_products( $file = null ) {
		$file = $file ?: $this->xml_path();
		if ( ! file_exists( $file ) ) return [];
		$xml = $this->parse_xml( file_get_contents( $file ) );
		if ( false === $xml ) return [];
		$nodes = $xml->xpath( '//Product' );
		$items = [];
		foreach ( $nodes as $p ) {
			$item = $this->node_to_item( $p );
			if ( $item['sku'] ) $items[] = $item;
		}
		return $items;
	}

	/**
	 * Μονάδες εισαγωγής: απλά προϊόντα + ομάδες ParentCode (>=2 μέλη).
	 */
	private function load_units( $file = null ) {
		$items = $this->load_products( $file );
		$s     = $this->settings();

		if ( ! $s['variable_products'] ) {
			return array_map( function ( $it ) { return [ 'type' => 'simple', 'item' => $it ]; }, $items );
		}

		$groups  = [];
		$singles = [];
		foreach ( $items as $it ) {
			if ( $it['parent_code'] ) $groups[ $it['parent_code'] ][] = $it;
			else $singles[] = $it;
		}

		$units = [];
		foreach ( $groups as $pc => $members ) {
			if ( count( $members ) > 1 ) {
				$units[] = [ 'type' => 'group', 'parent_code' => $pc, 'members' => $members ];
			} else {
				$singles[] = $members[0];
			}
		}
		foreach ( $singles as $it ) $units[] = [ 'type' => 'simple', 'item' => $it ];
		return $units;
	}

	private function node_to_item( SimpleXMLElement $p ) {
		$g = function ( $tag ) use ( $p ) { return trim( (string) $p->{$tag} ); };

		$images = [];
		if ( $g( 'ProductImage' ) ) $images[] = $g( 'ProductImage' );
		for ( $i = 1; $i <= 6; $i++ ) {
			$e = $g( 'ProductExtraImage_' . $i );
			if ( $e ) $images[] = $e;
		}

		// Specifications: "Κλειδί: Τιμή | Κλειδί: Τιμή"
		$specs = [];
		foreach ( explode( '|', $g( 'Specifications' ) ) as $part ) {
			if ( false === strpos( $part, ':' ) ) continue;
			list( $k, $v ) = array_map( 'trim', explode( ':', $part, 2 ) );
			if ( '' !== $k && '' !== $v && '-' !== $v ) $specs[ $k ] = $v;
		}

		// Διαστάσεις "15x15x96" (cm)
		$dims = [ '', '', '' ];
		if ( preg_match( '/([\d.,]+)\s*[xχ×]\s*([\d.,]+)\s*[xχ×]\s*([\d.,]+)/iu', $g( 'ProductDimensions' ), $m ) ) {
			$dims = [ $this->num( $m[1] ), $this->num( $m[2] ), $this->num( $m[3] ) ];
		}

		// Βάρος "3.02 Kg" ή "250 gr"
		$weight = '';
		if ( preg_match( '/([\d.,]+)\s*(kg|gr|g)?/iu', $g( 'ProductWeight' ), $m ) && '' !== $m[1] ) {
			$w = (float) $this->num( $m[1] );
			if ( isset( $m[2] ) && in_array( strtolower( $m[2] ), [ 'gr', 'g' ], true ) ) $w = $w / 1000;
			if ( $w > 0 ) $weight = (string) $w;
		}

		return [
			'sku'         => $g( 'Code' ),
			'barcode'     => '-' === $g( 'BarCode' ) ? '' : $g( 'BarCode' ),
			'title'       => $g( 'ProductTitle' ),
			'short'       => $g( 'ProductSmallDescription' ),
			'description' => $g( 'ProductLargeDescription' ),
			'category'    => $g( 'Category' ),
			'brand'       => $g( 'Manufacturer' ),
			'retail'      => $this->num( $g( 'RetailPrice' ) ),
			'wholesale'   => $this->num( $g( 'WholesalePrice' ) ),
			'stock'       => max( 0, (int) $g( 'Availability' ) ),
			'weight'      => $weight,
			'dims'        => $dims,
			'images'      => $images,
			'specs'       => $specs,
			'warn'        => $g( 'WARNGR' ),
			'addinfo'     => '-' === $g( 'AdditionalInfos' ) ? '' : $g( 'AdditionalInfos' ),
			'manual_name' => $g( 'ManualName' ),
			'manual_link' => $g( 'ManualLink' ),
			'parent_code' => $g( 'ParentCode' ),
			'country'     => '-' === $g( 'Country' ) ? '' : $g( 'Country' ),
			'warranty'    => '-' === $g( 'Warranty' ) ? '' : $g( 'Warranty' ),
			'min_qty'     => max( 1, (int) $g( 'MinimumQuantity' ) ),
			'raw_haystack'=> $p->asXML(),
		];
	}

	private function num( $v ) {
		$v = str_replace( ',', '.', trim( (string) $v ) );
		return is_numeric( $v ) ? $v : '';
	}

	private function feed_summary() {
		$sum = get_transient( 'elds_feed_summary' );
		if ( $sum ) return $sum;
		$units = $this->load_units();
		$cats  = []; $groups = 0; $simples = 0; $variations = 0; $products = 0;
		foreach ( $units as $u ) {
			if ( 'group' === $u['type'] ) {
				$groups++;
				$variations += count( $u['members'] );
				$products   += count( $u['members'] );
				foreach ( $u['members'] as $m ) if ( $m['category'] ) $cats[ $m['category'] ] = 1;
			} else {
				$simples++;
				$products++;
				if ( $u['item']['category'] ) $cats[ $u['item']['category'] ] = 1;
			}
		}
		$sum = [
			'products'   => $products,
			'groups'     => $groups,
			'variations' => $variations,
			'simples'    => $simples,
			'categories' => count( $cats ),
		];
		set_transient( 'elds_feed_summary', $sum, HOUR_IN_SECONDS );
		return $sum;
	}

	private function category_tree() {
		$items = $this->load_products();
		$tree  = [];
		foreach ( $items as $it ) {
			if ( ! $it['category'] ) continue;
			$tree[ $it['category'] ] = ( $tree[ $it['category'] ] ?? 0 ) + 1;
		}
		uksort( $tree, 'strcasecmp' );
		return $tree;
	}

	private function wc_category_options() {
		$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		$opts  = [];
		if ( is_wp_error( $terms ) ) return $opts;
		$by_id = [];
		foreach ( $terms as $t ) $by_id[ $t->term_id ] = $t;
		foreach ( $terms as $t ) {
			$label = $t->name; $p = $t->parent;
			while ( $p && isset( $by_id[ $p ] ) ) { $label = $by_id[ $p ]->name . ' → ' . $label; $p = $by_id[ $p ]->parent; }
			$opts[ $t->term_id ] = $label;
		}
		asort( $opts, SORT_FLAG_CASE | SORT_STRING );
		return $opts;
	}

	/* ---------------------------------------------------------------------
	 * Brand taxonomy detection (WooCommerce Brands 9.4+, Perfect Brands, YITH)
	 * ------------------------------------------------------------------- */

	private function brand_taxonomy() {
		foreach ( [ 'product_brand', 'pwb-brand', 'yith_product_brand' ] as $tax ) {
			if ( taxonomy_exists( $tax ) ) return $tax;
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Εισαγωγή — ουρά & παρτίδες
	 * ------------------------------------------------------------------- */

	public function ajax_import_batch() {
		check_ajax_referer( 'elds_import' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Δεν επιτρέπεται.' );

		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : -1;
		$queue  = get_option( ELDS_OPT_QUEUE, [] );

		if ( $offset < 0 ) {
			$live  = $this->xml_path();
			$newer = file_exists( $live ) && filemtime( $live ) > (int) ( $queue['feed_time'] ?? 0 );
			if ( ! isset( $queue['pos'] ) || ! empty( $queue['finished'] ) || $newer || ! file_exists( $this->run_xml_path() ) ) {
				$this->start_run();
				$queue = get_option( ELDS_OPT_QUEUE, [] );
			}
			$offset = (int) $queue['pos'];
		}

		$result = $this->run_batch( $offset );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
		wp_send_json_success( $result );
	}

	private function run_batch( $offset ) {
		if ( ! $this->acquire_lock() ) {
			return new WP_Error( 'elds_locked', 'Τρέχει ήδη άλλος συγχρονισμός (cron ή άλλη καρτέλα) — δοκίμασε ξανά σε λίγο.' );
		}
		try {
			return $this->do_batch( $offset );
		} finally {
			$this->release_lock();
		}
	}

	private function do_batch( $offset ) {
		$s     = $this->settings();
		$units = $this->load_units( $this->active_xml_path() );
		$total = count( $units );
		$batch = array_slice( $units, $offset, $s['batch_size'] );
		$log   = [];

		foreach ( $batch as $unit ) {
			if ( 'group' === $unit['type'] ) {
				$results = $this->import_group( $unit, $s );
			} else {
				$results = [ $this->import_item( $unit['item'], $s ) ];
			}
			foreach ( $results as $r ) {
				$icon = [ 'created' => '✚', 'updated' => '↻', 'skipped' => '⏭', 'locked' => '🔒', 'error' => '✖' ][ $r['status'] ] ?? '·';
				$log[] = $icon . ' ' . $r['sku'] . ' — ' . $r['msg'];
			}
		}

		$done     = min( $offset + count( $batch ), $total );
		$finished = $done >= $total;

		$queue = get_option( ELDS_OPT_QUEUE, [] );
		update_option( ELDS_OPT_QUEUE, [
			'pos'       => $done,
			'finished'  => $finished ? 1 : 0,
			'feed_time' => (int) ( $queue['feed_time'] ?? 0 ),
		], false );
		if ( $finished ) @unlink( $this->run_xml_path() );

		return [
			'total'    => $total,
			'done'     => $done,
			'next'     => $done,
			'finished' => $finished,
			'log'      => $log,
		];
	}

	/* ---------------------------------------------------------------------
	 * Χαρακτηριστικό παραλλαγής ανά ομάδα
	 * ------------------------------------------------------------------- */

	/**
	 * Επιστρέφει [ attr_name, [ sku => value ] ].
	 * 1) Spec key παρόν σε όλα τα μέλη με διακριτές τιμές (προτίμηση σε Χρώμα, Μέγεθος...).
	 * 2) Αλλιώς «Έκδοση» από τη διαφορά των τίτλων (κοινό πρόθεμα/κατάληξη αφαιρούνται).
	 * 3) Τελικό fallback για μοναδικότητα: προσάρτηση κωδικού.
	 */
	private function pick_variation_attr( array $members ) {
		$specsets = array_map( function ( $m ) { return $m['specs']; }, $members );

		// Κοινά keys σε ΟΛΑ τα μέλη
		$common = array_keys( $specsets[0] );
		foreach ( $specsets as $sp ) $common = array_intersect( $common, array_keys( $sp ) );

		$candidates = [];
		foreach ( $common as $k ) {
			$vals = array_map( function ( $sp ) use ( $k ) { return $sp[ $k ]; }, $specsets );
			if ( count( array_unique( $vals ) ) === count( $vals ) ) $candidates[] = $k;
		}

		$attr = '';
		foreach ( $this->variation_pref as $pref ) {
			if ( in_array( $pref, $candidates, true ) ) { $attr = $pref; break; }
		}
		if ( ! $attr && $candidates ) $attr = $candidates[0];

		$values = [];
		if ( $attr ) {
			foreach ( $members as $m ) $values[ $m['sku'] ] = $m['specs'][ $attr ];
			return [ $attr, $values ];
		}

		// Fallback: «Έκδοση» από τους τίτλους
		$titles = array_map( function ( $m ) { return $m['title']; }, $members );
		$prefix = $this->common_prefix( $titles );
		$suffix = $this->common_suffix( $titles );

		foreach ( $members as $i => $m ) {
			$t = $titles[ $i ];
			$mid = mb_substr( $t, mb_strlen( $prefix ) );
			if ( $suffix && mb_strlen( $mid ) > mb_strlen( $suffix ) ) {
				$mid = mb_substr( $mid, 0, mb_strlen( $mid ) - mb_strlen( $suffix ) );
			}
			$mid = trim( $mid, " \t-|,·/()" );
			$values[ $m['sku'] ] = ( '' !== $mid ) ? $mid : $t;
		}

		// Μοναδικότητα: αν δύο παραλλαγές βγάζουν ίδια τιμή, προσάρτησε τον κωδικό.
		$seen = [];
		foreach ( $values as $sku => $v ) {
			if ( isset( $seen[ $v ] ) ) $values[ $sku ] = $v . ' (Κωδ. ' . $sku . ')';
			$seen[ $values[ $sku ] ] = 1;
		}

		return [ 'Έκδοση', $values ];
	}

	private function common_prefix( array $strings ) {
		$first = mb_str_split( array_shift( $strings ) );
		foreach ( $strings as $s ) {
			$chars = mb_str_split( $s );
			$len   = min( count( $first ), count( $chars ) );
			$i     = 0;
			while ( $i < $len && $first[ $i ] === $chars[ $i ] ) $i++;
			$first = array_slice( $first, 0, $i );
		}
		return implode( '', $first );
	}

	private function common_suffix( array $strings ) {
		$rev = array_map( function ( $s ) { return implode( '', array_reverse( mb_str_split( $s ) ) ); }, $strings );
		$suf = $this->common_prefix( $rev );
		return implode( '', array_reverse( mb_str_split( $suf ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Εισαγωγή ομάδας -> Variable product + παραλλαγές
	 * ------------------------------------------------------------------- */

	private function import_group( array $unit, array $s ) {
		$pc      = $unit['parent_code'];
		$members = $unit['members'];
		$psku    = $pc . 'P'; // SKU του variable parent (τα Code είναι αριθμητικά, δεν συγκρούεται)

		// Μέλος-οδηγός: αυτό με Code == ParentCode, αλλιώς το πρώτο.
		$primary = $members[0];
		foreach ( $members as $m ) if ( $m['sku'] === $pc ) { $primary = $m; break; }

		// Κατηγορία από το μέλος-οδηγό (οι ομάδες μοιράζονται πρακτικά την ίδια).
		$cat_ids = $this->resolve_categories( $primary['category'] );
		if ( empty( $cat_ids ) ) {
			return [ [ 'sku' => $psku, 'status' => 'skipped', 'msg' => 'Ομάδα ' . $pc . ': χωρίς κατηγορία (mapping: Παράλειψη)' ] ];
		}

		// Κλείδωμα από XML: μέλη με το skip tag στα δεδομένα τους παραλείπονται.
		$results = [];
		if ( $s['skip_tag'] ) {
			$kept = [];
			foreach ( $members as $m ) {
				if ( false !== stripos( $m['raw_haystack'] ?? '', $s['skip_tag'] ) ) {
					$results[] = [ 'sku' => $m['sku'], 'status' => 'skipped', 'msg' => 'Περιέχει tag ' . $s['skip_tag'] . ' στο XML' ];
				} else {
					$kept[] = $m;
				}
			}
			$members = $kept;
			if ( ! $members ) return $results;
		}

		// Μερικό κλείδωμα (tag GAS στον parent): μόνο περιεχόμενο & εικόνες για όλη την ομάδα.
		$pre_parent_id = wc_get_product_id_by_sku( $psku );
		$content_only  = ( $s['content_tag'] && $pre_parent_id && has_term( $s['content_tag'], 'product_tag', $pre_parent_id ) );

		// Τιμές: υπολογισμός ανά μέλος + πολιτική μηδενικής λιανικής.
		// Σε content_only η τιμή δεν θα γραφτεί έτσι κι αλλιώς — τα μέλη κρατιούνται.
		$kept = [];
		foreach ( $members as $m ) {
			$price = $this->resolve_price( $m, $s );
			if ( 'SKIP' === $price && ! $content_only ) {
				$results[] = [ 'sku' => $m['sku'], 'status' => 'skipped', 'msg' => 'Λιανική = 0 (πολιτική: παράλειψη)' ];
				continue;
			}
			$m['price'] = $price;
			$kept[] = $m;
		}
		$members = $kept;
		if ( ! $members ) {
			$results[] = [ 'sku' => $psku, 'status' => 'skipped', 'msg' => 'Ομάδα ' . $pc . ': καμία παραλλαγή προς εισαγωγή' ];
			return $results;
		}

		// Χαρακτηριστικό παραλλαγής
		list( $attr_name, $attr_values ) = $this->pick_variation_attr( $members );
		$attr_slug = sanitize_title( $attr_name );

		// ---- Parent ----
		$parent_id = wc_get_product_id_by_sku( $psku );
		$is_new    = false;

		if ( $parent_id ) {
			if ( ! $s['update_existing'] ) {
				return array_merge( $results, [ [ 'sku' => $psku, 'status' => 'skipped', 'msg' => 'Υπάρχει ήδη (η ενημέρωση είναι κλειστή)' ] ] );
			}
			if ( $s['skip_tag'] && has_term( $s['skip_tag'], 'product_tag', $parent_id ) ) {
				return array_merge( $results, [ [ 'sku' => $psku, 'status' => 'locked', 'msg' => 'Κλειδωμένο (tag ' . $s['skip_tag'] . ') — ολόκληρη η ομάδα' ] ] );
			}
			$parent = wc_get_product( $parent_id );
			if ( $parent && ! $parent->is_type( 'variable' ) ) {
				if ( $lock_tag = $this->is_tag_locked( $parent_id, $s ) ) {
					return array_merge( $results, [ [ 'sku' => $psku, 'status' => 'locked', 'msg' => 'Υπάρχει ως άλλο είδος με tag ' . $lock_tag . ' — δεν μετατρέπεται' ] ] );
				}
				// Υπήρχε ως άλλο είδος — καθάρισμα και αναδημιουργία.
				update_post_meta( $parent_id, '_sku', '' );
				wp_trash_post( $parent_id );
				$parent = null;
			}
		} else {
			$parent = null;
		}

		if ( ! $parent ) {
			if ( ! $s['create_new'] ) {
				return array_merge( $results, [ [ 'sku' => $psku, 'status' => 'skipped', 'msg' => 'Νέο προϊόν (η δημιουργία είναι κλειστή)' ] ] );
			}
			$parent = new WC_Product_Variable();
			$parent->set_sku( $psku );
			$is_new = true;
		}

		try {
			// Όνομα parent: κοινό πρόθεμα των τίτλων, αλλιώς τίτλος μέλους-οδηγού.
			$titles = array_map( function ( $m ) { return $m['title']; }, $members );
			$pname  = trim( $this->common_prefix( $titles ), " \t-|,·/(" );
			if ( mb_strlen( $pname ) < 10 ) $pname = $primary['title'];

			$parent->set_name( $pname ?: $psku );
			$parent->set_short_description( $primary['short'] );
			$parent->set_description( $this->build_description( $primary, $s ) );
			$parent->set_category_ids( $cat_ids );
			$parent->set_manage_stock( false );

			// Attributes: κοινά specs (ίδια τιμή σε όλα τα μέλη) ως ορατά + το variation attribute.
			$attrs = [];
			$pos   = 0;

			if ( $s['import_specs'] ) {
				$shared = $primary['specs'];
				foreach ( $members as $m ) {
					foreach ( $shared as $k => $v ) {
						if ( ! isset( $m['specs'][ $k ] ) || $m['specs'][ $k ] !== $v ) unset( $shared[ $k ] );
					}
				}
				unset( $shared[ $attr_name ] );
				foreach ( $shared as $k => $v ) {
					$a = new WC_Product_Attribute();
					$a->set_name( $k );
					$a->set_options( array_map( 'trim', explode( '|', $v ) ) );
					$a->set_position( $pos++ );
					$a->set_visible( true );
					$a->set_variation( false );
					$attrs[] = $a;
				}
				if ( $primary['country'] ) {
					$a = new WC_Product_Attribute();
					$a->set_name( 'Χώρα προέλευσης' ); $a->set_options( [ $primary['country'] ] );
					$a->set_position( $pos++ ); $a->set_visible( true ); $a->set_variation( false );
					$attrs[] = $a;
				}
			}

			$va = new WC_Product_Attribute();
			$va->set_name( $attr_name );
			$va->set_options( array_values( array_unique( $attr_values ) ) );
			$va->set_position( $pos++ );
			$va->set_visible( true );
			$va->set_variation( true );
			$attrs[] = $va;

			$parent->set_attributes( $attrs );
			$parent->save();
			$parent_id = $parent->get_id();

			update_post_meta( $parent_id, '_elds_parent_code', $pc );
			update_post_meta( $parent_id, '_elds_last_sync', current_time( 'mysql' ) );

			if ( $primary['brand'] ) $this->assign_brand( $parent_id, $primary['brand'] );

			// Εικόνες parent (featured + gallery από το μέλος-οδηγό).
			if ( $s['import_images'] && $primary['images'] ) {
				$this->import_images( $parent, $primary['images'] );
				$parent->save();
			}

			$results[] = [
				'sku'    => $psku,
				'status' => $is_new ? 'created' : 'updated',
				'msg'    => 'Variable «' . $pname . '» (' . $attr_name . ', ' . count( $members ) . ' παραλλαγές)'
					. ( $content_only ? ' — μόνο περιεχόμενο (tag ' . $s['content_tag'] . '), τιμές/απόθεμα παγωμένα' : '' ),
			];
		} catch ( Exception $e ) {
			$results[] = [ 'sku' => $psku, 'status' => 'error', 'msg' => $e->getMessage() ];
			return $results;
		}

		// ---- Παραλλαγές ----
		foreach ( $members as $m ) {
			$results[] = $this->import_variation( $m, $parent_id, $attr_slug, $attr_values[ $m['sku'] ] ?? '', $s, $content_only );
		}

		// Sync τιμών/αποθέματος του parent από τις παραλλαγές.
		WC_Product_Variable::sync( $parent_id );

		return $results;
	}

	private function import_variation( array $item, $parent_id, $attr_slug, $attr_value, array $s, $content_only = false ) {
		$sku = $item['sku'];

		$existing_id = wc_get_product_id_by_sku( $sku );
		$variation   = null;
		$is_new      = true;
		$note        = '';

		if ( $existing_id ) {
			$existing = wc_get_product( $existing_id );
			if ( $existing && $existing->is_type( 'variation' ) ) {
				if ( ! $s['update_existing'] ) {
					return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Υπάρχει ήδη (η ενημέρωση είναι κλειστή)' ];
				}
				$variation = $existing;
				$is_new    = false;
			} elseif ( $existing ) {
				if ( $lock_tag = $this->is_tag_locked( $existing_id, $s ) ) {
					return [ 'sku' => $sku, 'status' => 'locked', 'msg' => 'Υπάρχει ως απλό προϊόν με tag ' . $lock_tag . ' — δεν μετατρέπεται σε παραλλαγή' ];
				}
				// Ήταν απλό προϊόν (π.χ. από v1) — μετατροπή σε παραλλαγή.
				update_post_meta( $existing_id, '_sku', '' );
				wp_trash_post( $existing_id );
				$note = 'μετατράπηκε από απλό σε παραλλαγή';
			}
		}

		if ( ! $variation ) {
			if ( ! $s['create_new'] && ! $note ) {
				return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Νέα παραλλαγή (η δημιουργία είναι κλειστή)' ];
			}
			$variation = new WC_Product_Variation();
			$variation->set_sku( $sku );
		}

		// Το πάγωμα τιμής/αποθέματος αφορά υπάρχουσες παραλλαγές — οι νέες παίρνουν πλήρη δεδομένα.
		$freeze = $content_only && ! $is_new;
		if ( $is_new && 'SKIP' === $item['price'] ) {
			return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Νέα παραλλαγή με λιανική = 0 (πολιτική: παράλειψη)' ];
		}

		try {
			$variation->set_parent_id( $parent_id );
			$variation->set_attributes( [ $attr_slug => $attr_value ] );
			$variation->set_status( 'publish' );

			if ( ! $freeze ) {
				if ( '' !== $item['price'] && 'SKIP' !== $item['price'] ) {
					$variation->set_regular_price( $item['price'] );
				}

				if ( $s['manage_stock'] ) {
					$variation->set_manage_stock( true );
					$variation->set_stock_quantity( $item['stock'] );
				} else {
					$variation->set_manage_stock( false );
				}
				$variation->set_stock_status( $item['stock'] > 0 ? 'instock' : 'outofstock' );
			}

			if ( $item['weight'] ) $variation->set_weight( $item['weight'] );
			if ( $item['dims'][0] ) $variation->set_length( $item['dims'][0] );
			if ( $item['dims'][1] ) $variation->set_width( $item['dims'][1] );
			if ( $item['dims'][2] ) $variation->set_height( $item['dims'][2] );

			if ( $item['barcode'] && method_exists( $variation, 'set_global_unique_id' ) ) {
				try { $variation->set_global_unique_id( $item['barcode'] ); } catch ( Exception $e ) {}
			}

			// Εικόνα παραλλαγής: η πρώτη εικόνα του μέλους.
			$img_msg = '';
			if ( $s['import_images'] && ! empty( $item['images'] ) ) {
				$att = $this->sideload_one( $item['images'][0], $parent_id, $item['title'] );
				if ( $att ) { $variation->set_image_id( $att ); $img_msg = 'εικόνα ΟΚ'; }
			}

			$variation->save();
			$vid = $variation->get_id();

			update_post_meta( $vid, '_elds_barcode', $item['barcode'] );
			update_post_meta( $vid, '_elds_parent_code', $item['parent_code'] );
			update_post_meta( $vid, '_elds_wholesale_price', $item['wholesale'] );
			update_post_meta( $vid, '_elds_min_quantity', $item['min_qty'] );
			update_post_meta( $vid, '_elds_last_sync', current_time( 'mysql' ) );

			$parts = array_filter( [
				$is_new ? 'Παραλλαγή δημιουργήθηκε' : 'Παραλλαγή ενημερώθηκε',
				'«' . $attr_value . '»',
				$freeze ? 'τιμή/απόθεμα παγωμένα (tag ' . $s['content_tag'] . ')' : '',
				$note,
				$img_msg,
			] );
			return [ 'sku' => $sku, 'status' => $is_new ? 'created' : 'updated', 'msg' => implode( ' | ', $parts ) ];
		} catch ( Exception $e ) {
			return [ 'sku' => $sku, 'status' => 'error', 'msg' => $e->getMessage() ];
		}
	}

	/* ---------------------------------------------------------------------
	 * Εισαγωγή απλού προϊόντος
	 * ------------------------------------------------------------------- */

	private function resolve_price( array $item, array $s ) {
		if ( 'WholesalePrice' === $s['price_field'] ) return $item['wholesale'];

		$price = $item['retail'];
		if ( '' === $price || (float) $price <= 0 ) {
			switch ( $s['zero_price'] ) {
				case 'skip_product':
					return 'SKIP';
				case 'wholesale_x':
					return ( '' !== $item['wholesale'] ) ? (string) round( (float) $item['wholesale'] * (float) $s['zero_multiplier'], 2 ) : '';
				default:
					return '';
			}
		}

		// Τιμή XML με ΦΠΑ 24% -> αποφορολογημένη (4 δεκαδικά ώστε ×1.24 να επιστρέφει την τιμή του XML).
		if ( ! empty( $s['vat_included'] ) ) {
			return (string) round( (float) $price / ELDS_VAT_RATE, 4 );
		}
		return $price;
	}

	private function import_item( $item, $s ) {
		$sku = $item['sku'];

		// Παράλειψη αν το ίδιο το XML περιέχει το skip tag (π.χ. GAS24).
		if ( $s['skip_tag'] && false !== stripos( $item['raw_haystack'] ?? '', $s['skip_tag'] ) ) {
			return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Περιέχει tag ' . $s['skip_tag'] . ' στο XML' ];
		}

		// Κατηγορίες: αν το mapping δίνει «Παράλειψη», το προϊόν ΔΕΝ εισάγεται.
		$cat_ids = $this->resolve_categories( $item['category'] );
		if ( empty( $cat_ids ) ) {
			return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Χωρίς κατηγορία (mapping: Παράλειψη)' ];
		}

		$price        = $this->resolve_price( $item, $s );
		$content_only = false;

		$existing_id = wc_get_product_id_by_sku( $sku );

		if ( $existing_id ) {
			if ( ! $s['update_existing'] ) {
				return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Υπάρχει ήδη (η ενημέρωση είναι κλειστή)' ];
			}
			if ( $s['skip_tag'] && has_term( $s['skip_tag'], 'product_tag', $existing_id ) ) {
				return [ 'sku' => $sku, 'status' => 'locked', 'msg' => 'Κλειδωμένο (tag ' . $s['skip_tag'] . ')' ];
			}
			// Μερικό κλείδωμα: μόνο περιεχόμενο & εικόνες — όχι τιμή/απόθεμα.
			$content_only = ( $s['content_tag'] && has_term( $s['content_tag'], 'product_tag', $existing_id ) );
			$product = wc_get_product( $existing_id );
			if ( $product && $product->is_type( 'variation' ) ) {
				// Ήταν παραλλαγή, τώρα είναι μόνο του στο feed — μετατροπή σε απλό.
				update_post_meta( $existing_id, '_sku', '' );
				wp_trash_post( $existing_id );
				$product = new WC_Product_Simple();
				$product->set_sku( $sku );
				$is_new = true;
			} else {
				$is_new = false;
			}
		} else {
			if ( ! $s['create_new'] ) {
				return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Νέο προϊόν (η δημιουργία είναι κλειστή)' ];
			}
			$product = new WC_Product_Simple();
			$product->set_sku( $sku );
			$is_new = true;
		}

		if ( 'SKIP' === $price && ! $content_only ) {
			return [ 'sku' => $sku, 'status' => 'skipped', 'msg' => 'Λιανική = 0 (πολιτική: παράλειψη)' ];
		}

		try {
			$product->set_name( $item['title'] ?: $sku );
			$product->set_short_description( $item['short'] );
			$product->set_description( $this->build_description( $item, $s ) );
			$product->set_category_ids( $cat_ids );

			if ( ! $content_only ) {
				if ( '' !== $price && 'SKIP' !== $price ) {
					$product->set_regular_price( $price );
				}

				// Απόθεμα
				if ( $s['manage_stock'] ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( $item['stock'] );
				} else {
					$product->set_manage_stock( false );
				}
				$product->set_stock_status( $item['stock'] > 0 ? 'instock' : 'outofstock' );
			}

			// Βάρος & διαστάσεις
			if ( $item['weight'] ) $product->set_weight( $item['weight'] );
			if ( $item['dims'][0] ) $product->set_length( $item['dims'][0] );
			if ( $item['dims'][1] ) $product->set_width( $item['dims'][1] );
			if ( $item['dims'][2] ) $product->set_height( $item['dims'][2] );

			// Barcode → GTIN (WooCommerce 9.2+)
			if ( $item['barcode'] && method_exists( $product, 'set_global_unique_id' ) ) {
				try { $product->set_global_unique_id( $item['barcode'] ); } catch ( Exception $e ) {}
			}

			// Χαρακτηριστικά
			if ( $s['import_specs'] ) {
				$this->set_attributes( $product, $item );
			}

			$product->save();
			$pid = $product->get_id();

			// Meta
			update_post_meta( $pid, '_elds_barcode', $item['barcode'] );
			update_post_meta( $pid, '_elds_parent_code', $item['parent_code'] );
			update_post_meta( $pid, '_elds_wholesale_price', $item['wholesale'] );
			update_post_meta( $pid, '_elds_min_quantity', $item['min_qty'] );
			update_post_meta( $pid, '_elds_last_sync', current_time( 'mysql' ) );

			// Brand
			if ( $item['brand'] ) $this->assign_brand( $pid, $item['brand'] );

			// Εικόνες
			$img_msg = '';
			if ( $s['import_images'] && $item['images'] ) {
				$img_msg = $this->import_images( $product, $item['images'] );
				$product->save();
			}

			$msg = $is_new ? 'Δημιουργήθηκε' : 'Ενημερώθηκε';
			if ( $content_only ) $msg .= ' — μόνο περιεχόμενο (tag ' . $s['content_tag'] . '), τιμή/απόθεμα παγωμένα';
			if ( $img_msg ) $msg .= ' | ' . $img_msg;

			return [ 'sku' => $sku, 'status' => $is_new ? 'created' : 'updated', 'msg' => $msg ];
		} catch ( Exception $e ) {
			return [ 'sku' => $sku, 'status' => 'error', 'msg' => $e->getMessage() ];
		}
	}

	private function build_description( $item, $s ) {
		$desc = $item['description'];

		if ( $s['append_addinfo'] && $item['addinfo'] ) {
			$desc .= "\n\n<h4>Λοιπά χαρακτηριστικά</h4>\n<p>" . esc_html( preg_replace( '/^Λοιπά Χαρακτηριστικά:\s*>?\s*/u', '', $item['addinfo'] ) ) . '</p>';
		}
		if ( $s['append_manual'] && $item['manual_link'] ) {
			$name  = $item['manual_name'] ?: 'Οδηγίες χρήσης (PDF)';
			$desc .= "\n\n<p><a href=\"" . esc_url( $item['manual_link'] ) . '" target="_blank" rel="noopener">📄 ' . esc_html( $name ) . '</a></p>';
		}
		if ( $s['append_warning'] && $item['warn'] && '-' !== $item['warn'] ) {
			$desc .= "\n\n<h4>Προειδοποιήσεις ασφαλείας</h4>\n" . wp_kses_post( $item['warn'] );
		}
		return $desc;
	}

	private function set_attributes( WC_Product $product, $item ) {
		$attrs = [];
		$pos   = 0;

		$add = function ( $name, $value ) use ( &$attrs, &$pos ) {
			if ( '' === trim( (string) $value ) ) return;
			$a = new WC_Product_Attribute();
			$a->set_name( $name );
			$a->set_options( array_map( 'trim', explode( '|', $value ) ) );
			$a->set_position( $pos++ );
			$a->set_visible( true );
			$a->set_variation( false );
			$attrs[] = $a;
		};

		foreach ( $item['specs'] as $k => $v ) $add( $k, $v );
		if ( $item['country'] )  $add( 'Χώρα προέλευσης', $item['country'] );
		if ( $item['warranty'] ) $add( 'Εγγύηση', $item['warranty'] );

		if ( $attrs ) $product->set_attributes( $attrs );
	}

	private function assign_brand( $pid, $brand ) {
		$tax = $this->brand_taxonomy();
		if ( $tax ) {
			$term = term_exists( $brand, $tax );
			if ( ! $term ) $term = wp_insert_term( $brand, $tax );
			if ( ! is_wp_error( $term ) ) {
				wp_set_object_terms( $pid, (int) ( $term['term_id'] ?? $term ), $tax, false );
			}
			return;
		}
		// Fallback: ως attribute «Brand».
		$product = wc_get_product( $pid );
		if ( ! $product ) return;
		$attrs = $product->get_attributes();
		$a = new WC_Product_Attribute();
		$a->set_name( 'Brand' );
		$a->set_options( [ $brand ] );
		$a->set_visible( true );
		$attrs['brand'] = $a;
		$product->set_attributes( $attrs );
		$product->save();
	}

	/* ---------------------------------------------------------------------
	 * Κατηγορίες
	 * ------------------------------------------------------------------- */

	/**
	 * Βρίσκει/δημιουργεί κατηγορία ΜΕ συγκεκριμένο parent.
	 * (Το term_exists με parent = 0 δεν φιλτράρει καθόλου ως προς parent.)
	 */
	private function find_or_create_cat( $name, $parent ) {
		$ids = get_terms( [
			'taxonomy'   => 'product_cat',
			'name'       => $name,
			'parent'     => (int) $parent,
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $ids ) && $ids ) return (int) $ids[0];

		$term = wp_insert_term( $name, 'product_cat', [ 'parent' => (int) $parent ] );
		if ( is_wp_error( $term ) ) {
			$existing = $term->get_error_data( 'term_exists' );
			return $existing ? (int) $existing : 0;
		}
		return (int) $term['term_id'];
	}

	private function resolve_categories( $path ) {
		if ( ! $path ) return [];
		$mapping = get_option( ELDS_OPT_MAPPING, [] );
		$key     = md5( $path );
		$val     = array_key_exists( $key, $mapping ) ? $mapping[ $key ] : '__create__';

		if ( '' === $val ) return [];                       // Παράλειψη
		if ( '__create__' !== $val ) {
			$term = get_term( (int) $val, 'product_cat' );
			return ( $term && ! is_wp_error( $term ) ) ? [ (int) $val ] : [];
		}

		// Δημιουργία ιεραρχίας από το μονοπάτι "A -> B -> C".
		$parts  = array_filter( array_map( 'trim', explode( '->', $path ) ) );
		$parent = 0;
		$last   = 0;
		foreach ( $parts as $name ) {
			$id = $this->find_or_create_cat( $name, $parent );
			if ( ! $id ) return $last ? [ $last ] : [];
			$last   = $id;
			$parent = $id;
		}
		if ( $last ) {
			// Cache: το μονοπάτι πλέον δείχνει στο term για να μην ξαναδημιουργείται.
			$mapping[ $key ] = $last;
			update_option( ELDS_OPT_MAPPING, $mapping );
			return [ $last ];
		}
		return [];
	}

	/* ---------------------------------------------------------------------
	 * Εικόνες (χωρίς διπλή λήψη — hash στο URL)
	 * ------------------------------------------------------------------- */

	private function sideload_one( $url, $post_id, $desc ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$hash  = md5( $url );
		$found = get_posts( [
			'post_type'   => 'attachment',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_elds_img_hash',
			'meta_value'  => $hash,
		] );
		if ( $found ) return (int) $found[0];

		$att = media_sideload_image( $url, $post_id, $desc, 'id' );
		if ( is_wp_error( $att ) ) return 0;
		update_post_meta( $att, '_elds_img_hash', $hash );
		return (int) $att;
	}

	private function import_images( WC_Product $product, array $urls ) {
		$ids = [];
		$new = 0;
		foreach ( array_slice( $urls, 0, 8 ) as $url ) {
			$hash_before = md5( $url );
			$existed = get_posts( [
				'post_type' => 'attachment', 'numberposts' => 1, 'fields' => 'ids',
				'meta_key' => '_elds_img_hash', 'meta_value' => $hash_before,
			] );
			$att = $this->sideload_one( $url, $product->get_id(), $product->get_name() );
			if ( ! $att ) continue;
			if ( ! $existed ) $new++;
			$ids[] = $att;
		}

		if ( ! $ids ) return '';
		$product->set_image_id( array_shift( $ids ) );
		$product->set_gallery_image_ids( $ids );
		return $new ? $new . ' νέες εικόνες' : 'εικόνες ΟΚ';
	}

	/* ---------------------------------------------------------------------
	 * WP-Cron: ωριαίος αυτόματος συγχρονισμός
	 * ------------------------------------------------------------------- */

	public function maybe_schedule_cron() {
		$s = $this->settings();
		$scheduled = wp_next_scheduled( 'elds_hourly_sync' );
		if ( $s['auto_sync'] && ! $scheduled ) {
			wp_schedule_event( time() + 300, 'hourly', 'elds_hourly_sync' );
		}
		if ( ! $s['auto_sync'] && $scheduled ) {
			wp_unschedule_event( $scheduled, 'elds_hourly_sync' );
			wp_clear_scheduled_hook( 'elds_continue_sync' );
		}
	}

	public function cron_hourly_sync() {
		$s = $this->settings();
		if ( ! $s['auto_sync'] ) return;
		if ( $this->is_locked() ) return; // τρέχει ήδη κάτι (χειροκίνητη εισαγωγή ή προηγούμενο cron)
		if ( $s['feed_url'] ) {
			$this->download_feed( $s['feed_url'] ); // αν αποτύχει, χρησιμοποιείται το ήδη αποθηκευμένο
		}
		$this->start_run();
		$this->cron_continue_sync();
	}

	public function cron_continue_sync() {
		$queue = get_option( ELDS_OPT_QUEUE, [] );
		if ( ! empty( $queue['finished'] ) ) return;
		$offset = (int) ( $queue['pos'] ?? 0 );

		$deadline = time() + 20; // ~20s δουλειάς ανά cron hit
		do {
			$result = $this->run_batch( $offset );
			if ( is_wp_error( $result ) ) { // κλειδωμένο από άλλη διεργασία -> ξανά σε 1'
				wp_schedule_single_event( time() + 60, 'elds_continue_sync' );
				return;
			}
			$offset = $result['next'];
		} while ( ! $result['finished'] && time() < $deadline );

		if ( ! $result['finished'] ) {
			wp_schedule_single_event( time() + 60, 'elds_continue_sync' );
		}
	}
}

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'elds_hourly_sync' );
	wp_clear_scheduled_hook( 'elds_continue_sync' );
} );

add_action( 'plugins_loaded', [ 'Eldico_Sync', 'instance' ] );
