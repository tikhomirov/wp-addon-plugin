<?php

require_once dirname(__DIR__, 2).'/functions/wp-functions.php';

describe('Tweaks config', function () {
    it('registers 48 tweak options', function () {
        $tweaks = require dirname(__DIR__, 2).'/src/Config/tweaks.php';

        expect($tweaks)->toHaveCount(48);
    });

    it('provides beginner description for every tweak', function () {
        $tweaks = require dirname(__DIR__, 2).'/src/Config/tweaks.php';

        foreach ($tweaks as $tweak) {
            expect($tweak['desc'] ?? '')->not->toBe('');
        }
    });

    it('uses optimized defaults for this project', function () {
        $tweaks = require dirname(__DIR__, 2).'/src/Config/tweaks.php';
        $defaults = [];

        foreach ($tweaks as $tweak) {
            $defaults[$tweak['id']] = $tweak['default'];
        }

        expect($defaults['wptweaker_setting_5'])->toBeFalse();
        expect($defaults['wptweaker_setting_10'])->toBeTrue();
        expect($defaults['wptweaker_setting_15'])->toBeFalse();
        expect($defaults['wptweaker_setting_23'])->toBeFalse();
        expect($defaults['wptweaker_setting_25'])->toBeTrue();
        expect($defaults['wptweaker_setting_43'])->toBeTrue();
        expect($defaults['wptweaker_setting_48'])->toBeTrue();
    });

    it('marks cautious tweaks with warning emoji', function () {
        $tweaks = require dirname(__DIR__, 2).'/src/Config/tweaks.php';
        $cautiousIds = [
            'wptweaker_setting_5',
            'wptweaker_setting_9',
            'wptweaker_setting_10',
            'wptweaker_setting_11',
            'wptweaker_setting_15',
            'wptweaker_setting_19',
            'wptweaker_setting_24',
            'wptweaker_setting_32',
            'wptweaker_setting_33',
            'wptweaker_setting_38',
            'wptweaker_setting_41',
            'wptweaker_setting_43',
            'wptweaker_setting_46',
        ];

        $titlesById = [];
        foreach ($tweaks as $tweak) {
            $titlesById[$tweak['id']] = $tweak['title'];
        }

        foreach ($cautiousIds as $id) {
            expect($titlesById[$id])->toContain('⚠️');
        }
    });

    it('has callback function for every tweak option', function () {
        $tweaks = require dirname(__DIR__, 2).'/src/Config/tweaks.php';

        foreach ($tweaks as $tweak) {
            expect(function_exists($tweak['id']))->toBeTrue("Missing function {$tweak['id']}");
        }
    });
});

describe('update tweak guards', function () {
    beforeEach(function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn ($key, $default = []) => $default;
        require_once dirname(__DIR__, 2).'/functions/main-settings-helpers.php';
    });

    it('detects disable all updates at root level for tweak guards', function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn () => ['disable_auto_update' => '1'];

        expect(wp_addon_is_root_setting_enabled('disable_auto_update'))->toBeTrue();
        expect(wp_addon_is_root_setting_enabled('wptweaker_setting_9'))->toBeFalse();
    });
});

describe('wptweaker_block_wordpress_org_requests', function () {
    it('blocks api.wordpress.org requests', function () {
        $result = wptweaker_block_wordpress_org_requests(
            false,
            [],
            'https://api.wordpress.org/core/version-check/1.7/'
        );

        expect(is_wp_error($result))->toBeTrue();
        expect($result->get_error_code())->toBe('wptweaker_http_blocked');
    });

    it('blocks downloads.wordpress.org requests', function () {
        $result = wptweaker_block_wordpress_org_requests(
            false,
            [],
            'https://downloads.wordpress.org/plugin/hello-dolly.zip'
        );

        expect(is_wp_error($result))->toBeTrue();
    });

    it('allows unrelated external requests', function () {
        $result = wptweaker_block_wordpress_org_requests(
            false,
            [],
            'https://example.com/webhook'
        );

        expect($result)->toBeFalse();
    });
});

describe('wptweaker_extend_login_session', function () {
    it('extends session only when remember me is checked', function () {
        expect(wptweaker_extend_login_session(3600, 1, false))->toBe(3600);
        expect(wptweaker_extend_login_session(3600, 1, true))->toBe(YEAR_IN_SECONDS);
    });
});

describe('wptweaker_should_hide_admin_bar', function () {
    afterEach(function () {
        global $mock_user_capabilities;
        $mock_user_capabilities = null;
    });

    it('hides admin bar for authors and subscribers', function () {
        global $mock_user_capabilities;
        $mock_user_capabilities = ['edit_posts'];

        expect(wptweaker_should_hide_admin_bar())->toBeTrue();
    });

    it('shows admin bar for editors and administrators', function () {
        global $mock_user_capabilities;

        $mock_user_capabilities = ['edit_posts', 'edit_others_posts'];
        expect(wptweaker_should_hide_admin_bar())->toBeFalse();

        $mock_user_capabilities = ['manage_options'];
        expect(wptweaker_should_hide_admin_bar())->toBeFalse();
    });
});

describe('wptweaker_restrict_rest_api', function () {
    afterEach(function () {
        global $mock_is_user_logged_in;
        $mock_is_user_logged_in = null;
    });

    it('blocks REST access for guests', function () {
        global $mock_is_user_logged_in;
        $mock_is_user_logged_in = false;

        $result = wptweaker_restrict_rest_api(null);

        expect(is_wp_error($result))->toBeTrue();
        expect($result->get_error_code())->toBe('rest_not_logged_in');
        expect($result->get_error_data()['status'])->toBe(401);
    });

    it('allows REST access for logged in users', function () {
        global $mock_is_user_logged_in;
        $mock_is_user_logged_in = true;

        expect(wptweaker_restrict_rest_api(null))->toBeNull();
    });

    it('preserves existing authentication errors', function () {
        $error = new WP_Error('existing', 'Already blocked');

        expect(wptweaker_restrict_rest_api($error))->toBe($error);
        expect(wptweaker_restrict_rest_api(true))->toBeTrue();
    });
});

describe('wptweaker_should_noindex_context', function () {
    it('matches low value archive contexts', function () {
        expect(wptweaker_should_noindex_context('search'))->toBeTrue();
        expect(wptweaker_should_noindex_context('attachment'))->toBeTrue();
        expect(wptweaker_should_noindex_context('date'))->toBeTrue();
        expect(wptweaker_should_noindex_context('single'))->toBeFalse();
    });
});

describe('wptweaker_add_noindex_robots', function () {
    afterEach(function () {
        global $mock_is_search, $mock_is_attachment, $mock_is_date;
        $mock_is_search = false;
        $mock_is_attachment = false;
        $mock_is_date = false;
    });

    it('adds noindex and follow flags for archive contexts', function () {
        global $mock_is_search;
        $mock_is_search = true;

        $robots = wptweaker_add_noindex_robots(['max-image-preview' => 'large']);

        expect($robots)->toMatchArray([
            'max-image-preview' => 'large',
            'noindex' => true,
            'follow' => true,
        ]);
    });

    it('leaves robots unchanged on regular pages', function () {
        $robots = wptweaker_add_noindex_robots(['index' => true]);

        expect($robots)->toBe(['index' => true]);
    });
});

describe('wptweaker_generic_login_error_message', function () {
    it('returns generic login error without username hints', function () {
        $message = wptweaker_generic_login_error_message();

        expect($message)->toContain('Incorrect username or password.');
        expect($message)->not->toContain('admin');
    });
});

describe('wptweaker_remove_pingback_header', function () {
    it('removes X-Pingback response header', function () {
        $headers = wptweaker_remove_pingback_header([
            'X-Pingback' => 'https://example.com/xmlrpc.php',
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        expect($headers)->not->toHaveKey('X-Pingback');
        expect($headers['Content-Type'])->toBe('text/html; charset=UTF-8');
    });
});

describe('wptweaker_is_wp_tracking_url', function () {
    it('detects Automattic tracking and WordPress events endpoints', function () {
        expect(wptweaker_is_wp_tracking_url('https://pixel.wp.com/g.gif?v=1'))->toBeTrue();
        expect(wptweaker_is_wp_tracking_url('https://stats.wp.com/e.gif'))->toBeTrue();
        expect(wptweaker_is_wp_tracking_url('http://api.wordpress.org/events/1.0/'))->toBeTrue();
        expect(wptweaker_is_wp_tracking_url('https://example.com/track'))->toBeFalse();
    });
});

describe('wptweaker_block_wp_tracking_requests', function () {
    it('blocks tracking requests but allows regular traffic', function () {
        $blocked = wptweaker_block_wp_tracking_requests(
            false,
            [],
            'https://pixel.wp.com/g.gif'
        );

        expect(is_wp_error($blocked))->toBeTrue();
        expect($blocked->get_error_code())->toBe('wptweaker_tracking_blocked');

        expect(wptweaker_block_wp_tracking_requests(false, [], 'https://example.com/'))->toBeFalse();
    });
});

describe('wptweaker_disable_jetpack_stats_module', function () {
    it('removes stats module from jetpack module list', function () {
        $modules = wptweaker_disable_jetpack_stats_module([
            'stats' => 'Stats',
            'contact-form' => 'Contact Form',
        ]);

        expect($modules)->toBe(['contact-form' => 'Contact Form']);
    });
});

describe('wptweaker_setting_44', function () {
    it('defines DISALLOW_FILE_EDIT when not already set', function () {
        if (defined('DISALLOW_FILE_EDIT')) {
            expect(DISALLOW_FILE_EDIT)->toBeTrue();

            return;
        }

        wptweaker_setting_44();

        expect(defined('DISALLOW_FILE_EDIT'))->toBeTrue();
        expect(DISALLOW_FILE_EDIT)->toBeTrue();
    });
});
