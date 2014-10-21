<?php
/**
 * Odin_Metabox class.
 *
 * Built Metaboxs.
 *
 * @package  Odin
 * @category Metabox
 * @author   WPBrasil
 * @version  2.0.0
 */
class Odin_Metabox {

    /**
     * Metaboxs fields.
     *
     * @var array
     */
    protected $fields = array();

    /**
     * Metaboxs construct.
     *
     * @param string $id        HTML 'id' attribute of the edit screen section.
     * @param string $title     Title of the edit screen section, visible to user.
     * @param string $post_type The type of Write screen on which to show the edit screen section.
     * @param string $context   The part of the page where the edit screen section should be shown ('normal', 'advanced', or 'side').
     * @param string $priority  The priority within the context where the boxes should show ('high', 'core', 'default' or 'low').
     *
     * @return void
     */
    public function __construct( $id, $title, $post_type = 'post', $context = 'normal', $priority = 'high' ) {
        $this->id        = $id;
        $this->title     = $title;
        $this->post_type = $post_type;
        $this->context   = $context;
        $this->priority  = $priority;
        $this->nonce     = $id . '_nonce';

        // Add Metabox.
        add_action( 'add_meta_boxes', array( &$this, 'add' ) );

        // Save Metaboxs.
        add_action( 'save_post', array( &$this, 'save' ) );

        // Load scripts.
        add_action( 'admin_enqueue_scripts', array( &$this, 'scripts' ) );

        // Add post type columns
        add_filter( 'manage_edit-' . $post_type . '_columns', array($this, 'add_custom_columns_admin_post_list' ));
        add_action( 'manage_' . $post_type . '_posts_custom_column', array($this, 'set_values_custom_columns_admin_post_list'), 10,2);
    }

    /**
     * Load metabox scripts.
     *
     * @return void
     */
    public function scripts() {
        $screen = get_current_screen();

        if ( $this->post_type === $screen->id ) {
            // Color Picker.
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            // Media Upload.
            wp_enqueue_media();

            // jQuery UI.
            wp_enqueue_script( 'jquery-ui-sortable' );

            // Metabox.
            wp_enqueue_script( 'odin-admin', get_template_directory_uri() . '/core/assets/js/admin.js', array( 'jquery' ), null, true );
            wp_enqueue_style( 'odin-admin', get_template_directory_uri() . '/core/assets/css/admin.css', array(), null, 'all' );

            // Localize strings.
            wp_localize_script(
                'odin-admin',
                'odin_admin_params',
                array(
                    'gallery_title'  => __( 'Add images in gallery', 'odin' ),
                    'gallery_button' => __( 'Add in gallery', 'odin' ),
                    'gallery_remove' => __( 'Remove image', 'odin' ),
                    'upload_title'   => __( 'Choose a file', 'odin' ),
                    'upload_button'  => __( 'Add file', 'odin' ),
                )
            );
        }
    }

    /**
     * Add the metabox in edit screens.
     *
     * @return void
     */
    public function add() {
        add_meta_box(
            $this->id,
            $this->title,
            array( &$this, 'metabox' ),
            $this->post_type,
            $this->context,
            $this->priority
        );
    }

    /**
     * Set metabox fields.
     *
     * @param array $fields Metabox fields.
     *
     * @return void
     */
    public function set_fields( $fields = array() ) {
        $this->fields = $fields;
    }

    /**
     * Metabox view.
     *
     * @param  object $post Post object.
     *
     * @return string       Metabox HTML fields.
     */
    public function metabox( $post ) {
        // Use nonce for verification.
        wp_nonce_field( basename( __FILE__ ), $this->nonce );

        $post_id = $post->ID;

        do_action( 'odin_metabox_table_before_' . $this->id, $post_id );

        echo '<table class="form-table">';

        foreach ( $this->fields as $field ) {
            echo '<tr valign="top">';

            if ( 'title' == $field['type'] ) {
                echo sprintf( '<th colspan="2"><strong>%s</strong></th>', $field['label'] );
            } else {
                echo sprintf( '<th><label for="%s">%s</label></th>', $field['id'], $field['label'] );

                echo '<td>';
                $this->process_fields( $field, $post_id );

                if ( isset( $field['description'] ) )
                    echo sprintf( '<br /><span class="description">%s</span>', $field['description'] );

                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</table>';

        do_action( 'odin_metabox_table_after_' . $this->id, $post_id );

    }

    /**
     * Process the metabox fields.
     *
     * @param  array $args    Field arguments
     * @param  int   $post_id ID of the current post type.
     *
     * @return string          HTML of the field.
     */
    protected function process_fields( $args, $post_id ) {
        $id      = $args['id'];
        $type    = $args['type'];
        $options = isset( $args['options'] ) ? $args['options'] : '';
        $attrs   = isset( $args['attributes'] ) ? $args['attributes'] : array();

        // Gets current value or default.
        $current = get_post_meta( $post_id, $id, true );
        if ( ! $current )
            $current = isset( $args['default'] ) ? $args['default'] : '';

        switch ( $type ) {
            case 'text':
                $this->field_input( $id, $current, array_merge( array( 'class' => 'regular-text' ), $attrs ) );
                break;
            case 'input':
                $this->field_input( $id, $current, $attrs );
                break;
            case 'textarea':
                $this->field_textarea( $id, $current, $attrs );
                break;
            case 'checkbox':
                $this->field_checkbox( $id, $current, $attrs );
                break;
            case 'select':
                $this->field_select( $id, $current, $options, $attrs );
                break;
            case 'radio':
                $this->field_radio( $id, $current, $options, $attrs );
                break;
            case 'editor':
                $this->field_editor( $id, $current, $options );
                break;
            case 'color':
                $this->field_input( $id, $current, array_merge( array( 'class' => 'odin-color-field' ), $attrs ) );
                break;
            case 'upload':
                $this->field_upload( $id, $current, $attrs );
                break;
            case 'image':
                $this->field_image( $id, $current );
                break;
            case 'image_plupload':
                $this->field_image_plupload( $id, $current );
                break;

            default:
                do_action( 'odin_metabox_field_' . $this->id, $type, $id, $current, $options, $attrs );
                break;
        }
    }

    /**
     * Build field attributes.
     *
     * @param  array $attrs Attributes as array.
     *
     * @return string       Attributes as string.
     */
    protected function build_field_attributes( $attrs ) {
        $attributes = '';

        if ( ! empty( $attrs ) ) {
            foreach ( $attrs as $key => $attr )
                $attributes .= ' ' . $key . '="' . $attr . '"';
        }

        return $attributes;
    }

    /**
     * Input field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $attrs   Array with field attributes.
     *
     * @return string          HTML of the field.
     */
    protected function field_input( $id, $current, $attrs ) {
        if ( ! isset( $attrs['type'] ) )
            $attrs['type'] = 'text';

        echo sprintf( '<input id="%1$s" name="%1$s" value="%2$s"%3$s />', $id, esc_attr( $current ), $this->build_field_attributes( $attrs ) );
    }

    /**
     * Textarea field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $attrs   Array with field attributes.
     *
     * @return string          HTML of the field.
     */
    protected function field_textarea( $id, $current, $attrs ) {
        echo sprintf( '<textarea id="%1$s" name="%1$s" cols="60" rows="4"%3$s>%2$s</textarea>', $id, esc_attr( $current ), $this->build_field_attributes( $attrs ) );
    }

    /**
     * Checkbox field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $attrs   Array with field attributes.
     *
     * @return string          HTML of the field.
     */
    protected function field_checkbox( $id, $current, $attrs ) {
        echo sprintf( '<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s%3$s />', $id, checked( 1, $current, false ), $this->build_field_attributes( $attrs ) );
    }

    /**
     * Select field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $options Array with select options.
     * @param  array  $attrs   Array with field attributes.
     *
     * @return string          HTML of the field.
     */
    protected function field_select( $id, $current, $options, $attrs ) {
        $html = sprintf( '<select id="%1$s" name="%1$s"%2$s>', $id, $this->build_field_attributes( $attrs ) );

        foreach ( $options as $key => $label )
            $html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected( $current, $key, false ), $label );

        $html .= '</select>';

        echo $html;
    }

    /**
     * Radio field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $options Array with input options.
     * @param  array  $attrs   Array with field attributes.
     *
     * @return string          HTML of the field.
     */
    protected function field_radio( $id, $current, $options, $attrs ) {
        $html = '';

        foreach ( $options as $key => $label )
            $html .= sprintf( '<input type="radio" id="%1$s_%2$s" name="%1$s" value="%2$s"%3$s%5$s /><label for="%1$s_%2$s"> %4$s</label><br />', $id, $key, checked( $current, $key, false ), $label, $this->build_field_attributes( $attrs ) );

        echo $html;
    }

    /**
     * Editor field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $options Array with wp_editor options.
     *
     * @return string          HTML of the field.
     */
    protected function field_editor( $id, $current, $options ) {
        // Set default options.
        if ( empty( $options ) )
            $options = array( 'textarea_rows' => 10 );

        echo '<div style="max-width: 600px;">';
            wp_editor( wpautop( $current ), $id, $options );
        echo '</div>';
    }

    /**
     * Upload field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     * @param  array  $attrs   Array with field attributes.
     *
     * @return string          HTML of the field.
     */
    protected function field_upload( $id, $current, $attrs ) {
        echo sprintf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text"%4$s /> <input class="button odin-upload-button" type="button" value="%3$s" />', $id, esc_url( $current ), __( 'Select file', 'odin' ), $this->build_field_attributes( $attrs ) );
    }

    /**
     * Image field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     *
     * @return string          HTML of the field.
     */
    protected function field_image( $id, $current ) {

        // Gets placeholder image.
        $image = get_template_directory_uri() . '/core/assets/images/placeholder.png';
        $html  = '<span class="odin_default_image" style="display: none;">' . $image . '</span>';

        if ( $current ) {
            $image = wp_get_attachment_image_src( $current, 'thumbnail' );
            $image = $image[0];
        }

        $html .= sprintf( '<input id="%1$s" name="%1$s" type="hidden" class="odin-upload-image" value="%2$s" /><img src="%3$s" class="odin-preview-image" style="height: 150px; width: 150px;" alt="" /><br /><input id="%1$s-button" class="odin-upload-image-button button" type="button" value="%4$s" /><small> <a href="#" class="odin-clear-image-button">%5$s</a></small>', $id, $current, $image, __( 'Select image', 'odin' ), __( 'Remove image', 'odin' ) );

        echo $html;
    }

    /**
     * Image plupload field.
     *
     * @param  string $id      Field id.
     * @param  string $current Field current value.
     *
     * @return string          HTML of the field.
     */
    protected function field_image_plupload( $id, $current ) {
        $html = '<div class="odin-gallery-container">';
            $html .= '<ul class="odin-gallery-images">';
                if ( ! empty( $current ) ) {
                    // Gets the current images.
                    $attachments = array_filter( explode( ',', $current ) );

                    if ( $attachments ) {
                        foreach ( $attachments as $attachment_id ) {
                            $html .= sprintf( '<li class="image" data-attachment_id="%1$s">%2$s<ul class="actions"><li><a href="#" class="delete" title="%3$s">X</a></li></ul></li>',
                                $attachment_id,
                                wp_get_attachment_image( $attachment_id, 'thumbnail' ),
                                __( 'Remove image', 'odin' )
                            );
                        }
                    }
                }
            $html .= '</ul><div class="clear"></div>';

            // Adds the hidden input.
            $html .= sprintf( '<input type="hidden" class="odin-gallery-field" name="%s" value="%s" />', $id, $current );

            // Adds "adds images in gallery" url.
            $html .= sprintf( '<p class="odin-gallery-add hide-if-no-js"><a href="#">%s</a></p>', __( 'Add images in gallery', 'odin' ) );
        $html .= '</div>';

        echo $html;
    }

    /**
     * Save metabox data.
     *
     * @param  int $post_id Current post type ID.
     *
     * @return void
     */
    public function save( $post_id ) {
        // Verify nonce.
        if ( ! isset( $_POST[ $this->nonce ] ) || ! wp_verify_nonce( $_POST[ $this->nonce ], basename( __FILE__ ) ) )
            return $post_id;

        // Verify if this is an auto save routine.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        // Check permissions.
        if ( $this->post_type == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;

        } elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        foreach ( $this->fields as $field ) {
            $name = $field['id'];
            $old = get_post_meta( $post_id, $name, true );

            $new = apply_filters( 'odin_save_metabox_' . $this->id, $_POST[ $name ] );

            if ( $new && $new != $old )
                update_post_meta( $post_id, $name, $new );
            elseif ( '' == $new && $old )
                delete_post_meta( $post_id, $name, $old );
        }
    }

    public function add_custom_columns_admin_post_list( $columns ){
        $new_columns = array();
        foreach( $this->fields as $key => $field ){
            if( isset( $field[ 'is_column' ] ) && $field[ 'is_column' ] == true ){
                $new_columns[ $field[ 'id' ] ] = $field[ 'label' ];
            }
        }

        return array_merge( $columns, $new_columns );
    }

    public function set_values_custom_columns_admin_post_list( $column , $post_id ){
        $type = $this->get_field_type_by_id( $column );
        $is_column = $this->check_field_is_column_by_id( $column );

        if( !$is_column ){
            return;
        }

        switch ( $type ) {
            case 'image':
            case 'image_plupload':
                $value = wp_get_attachment_image( get_post_meta( $post_id, $column, true ) , array( 50 , 50) );
                break;
            default:
                $value = apply_filters( 'admin_post_column_value_' . $this->post_type . '_' . $column , get_post_meta( $post_id, $column , true ) );
                break;
        }
        echo $value;
    }

    public function get_field_type_by_id( $field_id ){
        foreach( $this->fields as $field ){
            if( $field[ 'id' ] == $field_id ){
                return $field[ 'type' ];
            }
        }
        return '';
    }

    public function check_field_is_column_by_id( $field_id ){
        foreach( $this->fields as $field ){
            if( @$field[ 'is_column' ] == true ){
                return true;
            }
        }
        return false;
    }
}
