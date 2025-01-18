<?php
class HeadlessWoo_Admin
{
    private $options;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'headlesswoo_admin_menu'));
        add_action('admin_init', array($this, 'headlesswoo_settings_init'));
    }

    public function headlesswoo_admin_menu()
    {
        add_menu_page(
            'Headless Woo Settings',
            'Headless Woo',
            'manage_options',
            'headlesswoo_admin',
            array($this, 'headlesswoo_admin_page'),
            'dashicons-admin-generic',
            55
        );
    }

    public function headlesswoo_settings_init()
    {
        register_setting('headlesswoo', 'headlesswoo_options');

        add_settings_section(
            'headlesswoo_section_developers',
            __('Headless Woo Settings', 'headlesswoo'),
            array($this, 'headlesswoo_section_developers_cb'),
            'headlesswoo'
        );

        add_settings_field(
            'headlesswoo_field_jwt_secret',
            __('JWT Secret', 'headlesswoo'),
            array($this, 'headlesswoo_field_jwt_secret_cb'),
            'headlesswoo',
            'headlesswoo_section_developers',
            [
                'label_for' => 'headlesswoo_field_jwt_secret',
                'class' => 'headlesswoo_row',
                'headlesswoo_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'headlesswoo_field_jwt_expiration',
            __('JWT Expiration (in seconds)', 'headlesswoo'),
            array($this, 'headlesswoo_field_jwt_expiration_cb'),
            'headlesswoo',
            'headlesswoo_section_developers',
            [
                'label_for' => 'headlesswoo_field_jwt_expiration',
                'class' => 'headlesswoo_row',
                'headlesswoo_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'headlesswoo_field_api_key',
            __('API Key', 'headlesswoo'),
            array($this, 'headlesswoo_field_api_key_cb'),
            'headlesswoo',
            'headlesswoo_section_developers',
            [
                'label_for' => 'headlesswoo_field_api_key',
                'class' => 'headlesswoo_row',
                'headlesswoo_custom_data' => 'custom',
            ]
        );

        add_settings_field(
            'headlesswoo_field_api_key_secret',
            __('API Secret', 'headlesswoo'),
            array($this, 'headlesswoo_field_api_key_secret_cb'),
            'headlesswoo',
            'headlesswoo_section_developers',
            [
                'label_for' => 'headlesswoo_field_api_key_secret',
                'class' => 'headlesswoo_row',
                'headlesswoo_custom_data' => 'custom',
            ]
        );
    }

    public function headlesswoo_section_developers_cb($args)
    {
        ?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure your Headless Woo settings here.', 'headlesswoo'); ?></p>
        <?php
    }

    public function headlesswoo_field_jwt_secret_cb($args)
    {
        $options = get_option('headlesswoo_options');
        ?>
        <input type="text"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="headlesswoo_options[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : ''; ?>"
        >
        <p class="description">
            <?php esc_html_e('Enter a secure secret key for JWT token generation.', 'headlesswoo'); ?>
        </p>
        <?php
    }

    public function headlesswoo_field_jwt_expiration_cb($args)
    {
        $options = get_option('headlesswoo_options');
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="headlesswoo_options[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '3600'; ?>"
        >
        <p class="description">
            <?php esc_html_e('Enter the JWT token expiration time in seconds. Default is 3600 (1 hour).', 'headlesswoo'); ?>
        </p>
        <?php
    }
    public function headlesswoo_field_api_key_cb($args){
        $options = get_option('headlesswoo_options');
        ?>
            <input type="text"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="headlesswoo_options[<?php echo esc_attr($args['label_for']); ?>]"
            value="<?php echo isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : ''; ?>"
            >
        <p class="description">
            <?php esc_html_e('Enter the API key for Woo Commerce.', 'headlesswoo'); ?>
        </p>
        <?php
    }
    public function headlesswoo_field_api_key_secret_cb($args){
        $options = get_option('headlesswoo_options');
        ?>
        <input type="text"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="headlesswoo_options[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : ''; ?>"
        >
        <p class="description">
            <?php esc_html_e('Enter the API key Seceret for Woo Commerce.','headlesswoo'); ?>
        </p>
        <?php
    }


    public function headlesswoo_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('headlesswoo_messages', 'headlesswoo_message', __('Settings Saved', 'headlesswoo'), 'updated');
        }

        settings_errors('headlesswoo_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('headlesswoo');
                do_settings_sections('headlesswoo');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
}

new HeadlessWoo_Admin();
