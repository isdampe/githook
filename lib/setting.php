<?php defined("GITHOOK_VERSION") || exit;

class GitHookSettings {
	public function __construct() {
		add_action("admin_init", [$this, "register_settings"]);
		add_action("admin_menu", [$this, "register_menus"]);
	}

	public function register_settings() {
		register_setting("githook", "githook_options");

		add_settings_section("githook_config",
			__("Repo configuration", "githook"), [$this, "render_settings_form"],
			"githook");

		add_settings_field("githook_field_repo_dir", __("Repo directory", "githook"),
			[$this, "githook_field_repo_dir_cb"], "githook", "githook_config", [
				"label_for" => "githook_field_repo_dir",
				"class" => "wporg_row"
			]
		);

		add_settings_field("githook_field_repo_url", __("Repo url (SSH)", "githook"),
			[$this, "githook_field_repo_url_cb"], "githook", "githook_config", [
				"label_for" => "githook_field_repo_url",
				"class" => "wporg_row"
			]
		);
	}

	public function githook_field_repo_dir_cb( $args ) {
		echo sprintf('<input class="regular-text code" id="%s" name="githook_options[%s]" value="%s" />',
			esc_attr($args["label_for"]), esc_attr($args["label_for"]),
			$this->get_repo_directory());
	}

	public function githook_field_repo_url_cb( $args ) {
		$options = get_option("githook_options");

		echo sprintf('<input class="regular-text code" id="%s" name="githook_options[%s]" value="%s" />',
			esc_attr($args["label_for"]), esc_attr($args["label_for"]),
			isset($options[$args["label_for"]]) ? $options[$args["label_for"]] : "");
	}

	public function get_repo_directory(): string {
		$options = get_option("githook_options");
		if (isset($options["githook_field_repo_dir"]) && $options["githook_field_repo_dir"] !== "")
			return $options["githook_field_repo_dir"];

		// Default theme.
		return get_template_directory();
	}

	public function get_all(): array {
		return [
			"git_dir" => $this->get_repo_directory(),
			"secret" => $this->get_secret(),
			"git_repo" => isset($options["githook_field_repo_url"]) ? $options["githook_field_repo_url"] : ""
		];
	}

	public function get_public_key(bool $auto_tried = false): string {
		$home = posix_getpwuid(posix_getuid());
		$key_path = sprintf("%s/.ssh/id_rsa.pub", $home["dir"]);

		if (! file_exists($key_path)) {
			if (! $auto_tried) {
				$this->generate_public_key();
				return $this->get_public_key(true);
			} else {
				return "Could not be automatically determined.";
			}
		}

		return file_get_contents($key_path);
	}

	private function generate_public_key() {
		$home = posix_getpwuid(posix_getuid());
		$output_fp = sprintf("%s/.ssh/id_rsa", $home["dir"]);
		$cmd = sprintf('ssh-keygen -f "%s" -N ""', $output_fp);
	}

	public function get_secret(): string {
		$githook_secret = get_option("githook_secret");
		if (! $githook_secret) {
			$githook_secret = wp_generate_password(24, true);
			update_option("githook_secret", $githook_secret);
		}

		return $githook_secret;
	}

	public function render_settings_form(array $args = []) {
		echo sprintf('<table class="form-table">
			<tbody>
				<tr>
					<th>Your payload URL is</th>
					<td><input class="regular-text code" readonly value="%s/githook/notify" /></td>
				</tr>
				<tr>
					<th>Your secret is</th>
					<td>
						<input class="regular-text code" readonly value="%s" />
					</td>
				</tr>
				<tr>
					<th>Your public key is</th>
					<td>
						<textarea readonly rows="5" class="regular-text code">%s</textarea>
					</td>
				</tr>
			</tbody>
		</table>', get_bloginfo("wpurl"), $this->get_secret(), $this->get_public_key());
	}

	public function register_menus() {
		add_menu_page("GitHook", "GitHook Options", "manage_options", "githook",
			[$this, "render_settings_html"]
		);
	}

	public function render_settings_html() {
		if ( ! current_user_can("manage_options"))
			return;

		if (isset($_GET["settings-updated"])) {
			add_settings_error("githook_messages", "githook_message",
				__("Settings Saved", "githook"), "updated");
		}

		settings_errors("githook_messages");
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields("githook");
				do_settings_sections("githook");
				submit_button("Save Settings");
				?>
			</form>
		</div>
		<?php
	}
}

