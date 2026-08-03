<?php

add_action('admin_head', function () {
    if (get_current_screen()->id === 'plugins' && ! class_exists('CSF')) {
        ?>
		<div id="message" class="updated notice is-dismissible"><p>
				To work <b>#1 RW WordPress AddOn</b> plugin, please download and install <a href="https://github.com/Codestar/codestar-framework" target="_blank">Codestar Framework</a>.
			</p></div>
		<?php
    }
});

/**
 *  Include all php file in subfolder
 */
foreach (glob(RW_PLUGIN_DIR.'functions/*.php') as $file) {
    $file = basename($file);
    require RW_PLUGIN_DIR.'functions/'.$file;
}

/**
 *  Include all php file in subfolder
 */
foreach (glob(RW_PLUGIN_DIR.'functions/*/*.php') as $file) {
    include_once $file;
}

/*foreach ( glob( RW_PLUGIN_DIR . 'ext/*.php' ) as $file ){
    add_action('plugins_loaded', function () use ($file) {
        include_once $file;
    });
}*/
