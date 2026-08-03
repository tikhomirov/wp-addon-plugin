<?php
/**
 *  Contact Form7 Date and time picker
 */
if (! defined('WPCF7_PLUGIN')) {
    return;
}

/**
 ** A base module for the following types of tags:
 ** 	[datetimepicker* datetimepicker-* id:datetimepicker]
 **/

/* form_tag handler */

add_action('wpcf7_init', 'wpcf7_add_form_tag_datetime');

function wpcf7_add_form_tag_datetime()
{
    wpcf7_add_form_tag(['datetimepicker', 'datetimepicker*'],
        'wpcf7_datetime_form_tag_handler', ['name-attr' => true]);
}

function wpcf7_datetime_form_tag_handler($tag)
{
    if (empty($tag->name)) {
        return '';
    }

    $validation_error = wpcf7_get_validation_error($tag->name);

    $class = wpcf7_form_controls_class($tag->type);

    $class .= ' wpcf7-validates-as-datetime';

    if ($validation_error) {
        $class .= ' wpcf7-not-valid';
    }

    $atts = [];

    $atts['class'] = $tag->get_class_option($class);
    $atts['id'] = $tag->get_id_option();
    $atts['tabindex'] = $tag->get_option('tabindex', 'signed_int', true);
    /* $atts['min'] = $tag->get_date_option( 'min' );
    $atts['max'] = $tag->get_date_option( 'max' );
    $atts['step'] = $tag->get_option( 'step', 'int', true ); */

    if ($tag->has_option('readonly')) {
        $atts['readonly'] = 'readonly';
    }

    if ($tag->is_required()) {
        $atts['aria-required'] = 'true';
    }

    $atts['aria-invalid'] = $validation_error ? 'true' : 'false';

    $value = (string) reset($tag->values);

    if ($tag->has_option('placeholder') || $tag->has_option('watermark')) {
        $atts['placeholder'] = $value;
        $value = '';
    }

    $value = $tag->get_default_option($value);

    $value = wpcf7_get_hangover($tag->name, $value);

    $atts['value'] = $value;

    if (wpcf7_support_html5()) {
        // $atts['type'] = $tag->basetype;
        $atts['type'] = 'text';
        // jQuery validate
        // $atts['data-validation'] = 'number';
        $atts['data-sanitize'] = 'numberFormat';
        $atts['data-sanitize-number-format'] = '00.00.0000 00:00';
        $atts['data-validation-help'] = __('Specify date and time in format: 00.00.0000 00:00 or use the calendar', 'wp-addon');

    } else {
        $atts['type'] = 'text';
    }

    $atts['name'] = $tag->name;

    $atts = wpcf7_format_atts($atts);

    $html = sprintf(
        /* <input id="datetimepicker" type="text" value="Дата"> */
        '<span class="wpcf7-form-control-wrap %1$s"><input %2$s />%3$s</span>',
        sanitize_html_class($tag->name), $atts, $validation_error);

    return $html;
}

/* Validation filter */

add_filter('wpcf7_validate_date', 'wpcf7_datetime_validation_filter', 10, 2);
add_filter('wpcf7_validate_date*', 'wpcf7_datetime_validation_filter', 10, 2);

function wpcf7_datetime_validation_filter($result, $tag)
{
    $name = $tag->name;

    /* $min = $tag->get_date_option( 'min' );
    $max = $tag->get_date_option( 'max' ); */

    $value = isset($_POST[$name])
        ? trim(strtr((string) $_POST[$name], "\n", ' '))
        : '';

    if ($tag->is_required() && $value == '') {
        $result->invalidate($tag, wpcf7_get_message('invalid_required'));
    } elseif ($value != '' && ! wpcf7_is_date($value)) {
        $result->invalidate($tag, wpcf7_get_message('invalid_date'));
    }
    /* elseif ( '' != $value && ! empty( $min ) && $value < $min ) {
        $result->invalidate( $tag, wpcf7_get_message( 'date_too_early' ) );
    } elseif ( '' != $value && ! empty( $max ) && $max < $value ) {
        $result->invalidate( $tag, wpcf7_get_message( 'date_too_late' ) );
    } */

    return $result;
}

/* Messages */

add_filter('wpcf7_messages', 'wpcf7_datetime_messages');

function wpcf7_datetime_messages($messages)
{
    return array_merge($messages, [
        'invalid_date' => [
            'description' => __('Date format that the sender entered is invalid', 'contact-form-7'),
            'default' => __('The date format is incorrect.', 'contact-form-7'),
        ],

        'date_too_early' => [
            'description' => __('Date is earlier than minimum limit', 'contact-form-7'),
            'default' => __('The date is before the earliest one allowed.', 'contact-form-7'),
        ],

        'date_too_late' => [
            'description' => __('Date is later than maximum limit', 'contact-form-7'),
            'default' => __('The date is after the latest one allowed.', 'contact-form-7'),
        ],
    ]);
}

/* Tag generator */

add_action('wpcf7_admin_init', 'wpcf7_add_tag_generator_datetime', 19);

function wpcf7_add_tag_generator_datetime()
{
    $tag_generator = WPCF7_TagGenerator::get_instance();
    $tag_generator->add('datetimepicker', __('date and timepicker', 'contact-form-7'), 'wpcf7_tag_generator_datetime');
}

function wpcf7_tag_generator_datetime($contact_form, $args = '')
{
    $args = wp_parse_args($args, []);
    $type = 'datetimepicker';

    $description = __('Generate a form-tag for a date and time input field. Documentation: %s.', 'contact-form-7');

    $desc_link = wpcf7_link(__('https://xdsoft.net/jqplugins/datetimepicker/', 'contact-form-7'), __('DateTimePicker jQuery plugin', 'contact-form-7'));

    ?>
<div class="control-box">
<fieldset>
<legend><?php echo sprintf(esc_html($description), $desc_link); ?></legend>

<table class="form-table">
<tbody>
	<tr>
	<th scope="row"><?php echo esc_html(__('Field type', 'contact-form-7')); ?></th>
	<td>
		<fieldset>
		<legend class="screen-reader-text"><?php echo esc_html(__('Field type', 'contact-form-7')); ?></legend>
		<label><input type="checkbox" name="required" /> <?php echo esc_html(__('Required field', 'contact-form-7')); ?></label>
		</fieldset>
	</td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'].'-name'); ?>"><?php echo esc_html(__('Name', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr($args['content'].'-name'); ?>" /></td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'].'-values'); ?>"><?php echo esc_html(__('Default value', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="values" class="oneline" id="<?php echo esc_attr($args['content'].'-values'); ?>" /><br />
	<label><input type="checkbox" name="placeholder" class="option" /> <?php echo esc_html(__('Use this text as the placeholder of the field', 'contact-form-7')); ?></label></td>
	</tr>


	
	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'].'-id'); ?>"><?php echo esc_html(__('Id attribute', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="id" class="idvalue oneline option disabled" id="<?php echo esc_attr($args['content'].'-id'); ?>" value="datetimepicker" disabled="disabled" /></td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'].'-class'); ?>"><?php echo esc_html(__('Class attribute', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr($args['content'].'-class'); ?>" /></td>
	</tr>
</tbody>
</table>
</fieldset>
</div>

<div class="insert-box">
	<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select()" />

	<div class="submitbox">
	<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>" />
	</div>

	<br class="clear" />

	<p class="description mail-tag"><label for="<?php echo esc_attr($args['content'].'-mailtag'); ?>"><?php echo sprintf(esc_html(__('To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.', 'contact-form-7')), '<strong><span class="mail-tag"></span></strong>'); ?><input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr($args['content'].'-mailtag'); ?>" /></label></p>
</div>
<?php
}

/* Add js frontend outpot */
function add_datetimepicker()
{

    $dir_js = (RW_PLUGIN_URL.'assets/js/jquery.datetimepicker.full.min.js');
    $dir_css = (RW_PLUGIN_URL.'assets/css/min/jquery.datetimepicker.min.css');
    $dir_validate_js = '//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js';
    $dir_validate_css = '//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/theme-default.min.css';

    wp_register_script('contact-form-7', $dir_validate_js, ['jquery', 'jquery-form'], '2.3.26', true); // inc jQuery validator
    wp_register_script('contact-form-7', $dir_js, ['jquery', 'jquery-form'], '1.3.4', true); // inc datetime

    wp_enqueue_style('contact-form-7', $dir_css, [], WPCF7_VERSION, 'all'); // inc css
    /*    wp_enqueue_script('jquery.datetimepicker');// inc js + inline
        wp_enqueue_script('jquery.form-validator');// inc js + inline
    */

    // jQuery datetimepicker settings
    wp_add_inline_script('jquery.datetimepicker',
        "
        jQuery.datetimepicker.setLocale('ru');
        jQuery('#datetimepicker').datetimepicker({
         timepicker:true,
         format:'d.m.Y H:i',
         minDate:0,
         defaultDate:new Date(),
         allowTimes:[
          '11:00',
          '12:00',
          '13:00',
          '14:00',
          '15:00',
          '16:00',
          '17:00',
          '18:00',
          '19:00',
          '20:00',
          '21:00',
          '22:00'
          ]
        });
        ");
    // Documentation: https://xdsoft.net/jqplugins/datetimepicker/

    // jQuery validate settings
    wp_add_inline_script('jquery.form-validator', '
jQuery.validate({
  form : ".wpcf7-form", // add support cf7
  lang : "ru", // set locale
  modules : "html5"
});
');
    // Documentation: http://www.formvalidator.net
}
add_action('wp_enqueue_scripts', 'add_datetimepicker');

/* Validation filter */
add_filter('wpcf7_validate_datetimepicker', 'wpcf7_datetimepicker_validation_filter', 10, 2);
add_filter('wpcf7_validate_datetimepicker*', 'wpcf7_datetimepicker_validation_filter', 10, 2);

function wpcf7_datetimepicker_validation_filter($result, $tag)
{
    $name = $tag->name;
    $value = isset($_POST[$name])
        ? trim(strtr((string) $_POST[$name], "\n", ' '))
        : '';
    if ($tag->is_required() && $value == '') {
        $result->invalidate($tag, wpcf7_get_message('invalid_required'));
    } elseif ($value != '' && ! rw_validate_datetime($value)) {
        $result->invalidate($tag, wpcf7_get_message('invalid_number'));
    }

    return $result;
}

/**
 * DateTime validate format
 *
 * @return bool
 */
/* pattern generate by https://uiregex.com/ru */
function rw_validate_phone($value)
{
    // mask: +0(000)000-00-00
    $pattern = '/^\+[0-9]{1}\([0-9]{3}\)[0-9]{3}\-[0-9]{2}\-[0-9]{2}$/';
    $result = preg_match($pattern, $value);
    if ($result === 1) {
        // echo 'Это успех, братан!';
        return true;
    } else {
        // echo 'Это фиаско, братан!';
        return false;
    }
}

function rw_validate_datetime($value)
{
    // mask 00.00.0000 00:00
    $pattern = '/^[0-9]{2}\.[0-9]{2}\.[0-9]{4} [0-9]{2}:[0-9]{2}$/';
    $result = preg_match($pattern, $value);
    if ($result === 1) {
        return true;
    } else {
        return false;
    }
}
