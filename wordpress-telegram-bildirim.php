<?php
/**
 * Plugin Name: WordPress Telegram Bildirim
 * Description: WordPress içerik ve yönetici hesap olayları için Telegram bildirimleri gönderir.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: WordPress Telegram Bildirim
 * License: GPL-2.0-or-later
 * Text Domain: wordpress-telegram-bildirim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WordPress_Telegram_Bildirim {
	const OPTION_NAME = 'wordpress_telegram_bildirim_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'transition_post_status', array( $this, 'post_published' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( $this, 'page_created' ), 10, 3 );
		add_action( 'trashed_post', array( $this, 'post_deleted' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'post_deleted' ), 10, 2 );

		add_action( 'comment_post', array( $this, 'comment_created' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'comment_edited' ), 10, 2 );
		add_action( 'transition_comment_status', array( $this, 'comment_status_changed' ), 10, 3 );

		add_action( 'wp_login', array( $this, 'user_logged_in' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'user_logged_out' ) );
		add_action( 'retrieve_password', array( $this, 'password_reset_requested' ) );
		add_action( 'wp_login_failed', array( $this, 'login_failed' ), 10, 2 );
	}

	public function add_settings_page() {
		add_options_page(
			'Telegram Bildirimleri',
			'Telegram Bildirimleri',
			'manage_options',
			'wordpress-telegram-bildirim',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wordpress_telegram_bildirim',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);
	}

	public function sanitize_settings( $settings ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->get_settings();
		}

		$settings = is_array( $settings ) ? $settings : array();
		$bot_token = isset( $settings['bot_token'] ) ? sanitize_text_field( $settings['bot_token'] ) : '';
		if ( '' === $bot_token ) {
			$current_settings = $this->get_settings();
			$bot_token = $current_settings['bot_token'];
		}

		return array(
			'bot_token' => $bot_token,
			'chat_id'   => isset( $settings['chat_id'] ) ? sanitize_text_field( $settings['chat_id'] ) : '',
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1>Telegram Bildirimleri</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wordpress_telegram_bildirim' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="telegram-bot-token">Bot tokenı</label></th>
						<td><input id="telegram-bot-token" type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[bot_token]" value="" placeholder="<?php echo '' === $settings['bot_token'] ? '' : esc_attr__( 'Kaydedilmiş tokenı değiştirmek için yeni token girin', 'wordpress-telegram-bildirim' ); ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="telegram-chat-id">Yönetici sohbet kimliği</label></th>
						<td><input id="telegram-chat-id" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[chat_id]" value="<?php echo esc_attr( $settings['chat_id'] ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p>Bu sohbet kimliği yalnızca yönetici bildirimlerinin gönderileceği Telegram hesabına ait olmalıdır.</p>
		</div>
		<?php
	}

	public function post_published( $new_status, $old_status, $post ) {
		if (
			'publish' === $new_status
			&& 'publish' !== $old_status
			&& 'post' === $post->post_type
			&& $this->is_supported_content( $post )
		) {
			$this->notify( 'Yayınlandı', $post );
		}
	}

	public function post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->is_supported_content( $post_after ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'page' === $post_after->post_type ) {
			if ( 'trash' === $post_after->post_status ) {
				return;
			}

			$event = 'auto-draft' === $post_before->post_status && 'auto-draft' !== $post_after->post_status
				? 'Oluşturuldu'
				: 'Düzenlendi';
			$this->notify( $event, $post_after );
		} elseif ( 'publish' === $post_after->post_status && 'publish' === $post_before->post_status ) {
			$this->notify( 'Düzenlendi', $post_after );
		}
	}

	public function page_created( $post, $update, $post_before ) {
		if (
			! $update
			&& 'page' === $post->post_type
			&& 'auto-draft' !== $post->post_status
			&& $this->is_supported_content( $post )
		) {
			$this->notify( 'Oluşturuldu', $post );
		}
	}

	public function post_deleted( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( $this->is_supported_content( $post ) ) {
			$this->notify( 'Silindi', $post );
		}
	}

	public function comment_created( $comment_id, $comment_approved, $comment_data ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$this->notify_comment( 'Yorum yapıldı', $comment );
		}
	}

	public function comment_edited( $comment_id, $data = null ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$this->notify_comment( 'Yorum düzenlendi', $comment );
		}
	}

	public function comment_status_changed( $new_status, $old_status, $comment ) {
		if ( $new_status === $old_status ) {
			return;
		}

		$labels = array(
			'approved'       => 'Yorum onaylandı',
			'unapproved'     => 'Yorum reddedildi',
			'spam'           => 'Yorum spam olarak işaretlendi',
			'trash'          => 'Yorum çöpe taşındı',
		);

		if ( isset( $labels[ $new_status ] ) ) {
			$this->notify_comment( $labels[ $new_status ], $comment );
		}
	}

	public function user_logged_in( $user_login, $user ) {
		$this->send_message( 'Kullanıcı girişi: ' . $user_login );
	}

	public function user_logged_out( $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		if ( $user && $user->exists() ) {
			$this->send_message( 'Kullanıcı çıkışı: ' . $user->user_login );
		}
	}

	public function password_reset_requested( $user_login ) {
		$this->send_message( 'Şifre sıfırlama isteği: ' . $user_login );
	}

	public function login_failed( $username, $error = null ) {
		$this->send_message( 'Başarısız giriş denemesi: ' . $username );
	}

	private function is_supported_content( $post ) {
		return $post instanceof WP_Post
			&& in_array( $post->post_type, array( 'post', 'page' ), true )
			&& ! wp_is_post_revision( $post->ID )
			&& ! wp_is_post_autosave( $post->ID );
	}

	private function notify( $event, $post ) {
		$type = 'page' === $post->post_type ? 'Sayfa' : 'Yazı';
		$this->send_message( $type . ' ' . $event . ': ' . $post->post_title . "\n" . get_permalink( $post ) );
	}

	private function notify_comment( $event, $comment ) {
		$post = get_post( $comment->comment_post_ID );
		$title = $post ? $post->post_title : '';
		$content = wp_html_excerpt( wp_strip_all_tags( $comment->comment_content ), 2500, '…' );
		$this->send_message(
			$event . ' (' . $title . ')'
			. "\nYazan: " . $comment->comment_author
			. "\n" . $content
		);
	}

	private function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'bot_token' => isset( $settings['bot_token'] ) ? $settings['bot_token'] : '',
			'chat_id'   => isset( $settings['chat_id'] ) ? $settings['chat_id'] : '',
		);
	}

	private function send_message( $message ) {
		$settings = $this->get_settings();
		if ( '' === $settings['bot_token'] || '' === $settings['chat_id'] ) {
			return;
		}

		wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $settings['bot_token'] ) . '/sendMessage',
			array(
				'timeout' => 5,
				'body'    => array(
					'chat_id' => $settings['chat_id'],
					'text'    => wp_strip_all_tags( $message ),
				),
			)
		);
	}
}

new WordPress_Telegram_Bildirim();
