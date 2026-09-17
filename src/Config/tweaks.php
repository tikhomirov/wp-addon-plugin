<?php

if (! function_exists('wptweaker_field')) {
    /**
     * @return array{id: string, type: string, title: string, default: bool, desc: string}
     */
    function wptweaker_field(string $id, string $title, bool $default, string $desc): array
    {
        return [
            'id' => $id,
            'type' => 'switcher',
            'title' => $title,
            'default' => $default,
            'desc' => $desc,
        ];
    }
}

return [
    wptweaker_field(
        'wptweaker_setting_1',
        __('Remove WP-Version in Header', 'wp-addon'),
        true,
        __('Removes the WordPress version number from page HTML and RSS. A small security measure: attackers have a harder time targeting a specific WP version.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_2',
        __('WP-Emojis deacivation', 'wp-addon'),
        true,
        __('Disables WordPress emoji scripts and styles. Pages load faster, especially if you do not use emoji in content.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_3',
        __('Remove Windows Live Writer', 'wp-addon'),
        true,
        __('Removes the wlwmanifest link from the head. It was only needed for the old Windows Live Writer app and is not required on modern sites.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_4',
        __('Remove RSD-Link', 'wp-addon'),
        true,
        __('Removes the RSD link for external editors. It is an extra technical tag in the head that is usually unused.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_5',
        __('⚠️ Remove RSS links', 'wp-addon'),
        false,
        __('Removes RSS feed links from the head. Enable only if you are sure RSS is not needed. For a blog it is usually better to leave this off so readers and aggregators can find your feed.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_6',
        __('Remove shortlink in the header', 'wp-addon'),
        true,
        __('Removes the shortlink — a short service URL for the page. It is not needed for SEO or visitors.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_7',
        __('Remove adjacent links to posts in the header', 'wp-addon'),
        true,
        __('Removes link rel="prev/next" from the head. If the theme already outputs previous/next post navigation, this tag is redundant.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_8',
        __('Set limit post revisions to 5', 'wp-addon'),
        true,
        __('Limits the number of saved post revisions to 5. This reduces database size without hurting day-to-day editing.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_9',
        __('⚠️ Block WordPress.org update HTTP requests', 'wp-addon'),
        false,
        __('Blocks background requests to api.wordpress.org and downloads.wordpress.org. Useful on closed servers, but WordPress will stop checking core, plugin, and theme updates. Skipped automatically when General Settings → Disable all updates is enabled.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_10',
        __('⚠️ Disable heartbeat on frontend (slow down in admin)', 'wp-addon'),
        true,
        __('Disables Heartbeat on the public site and slows it down in the admin. This reduces extra AJAX requests. Post autosave remains, but runs less often.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_11',
        __('⚠️ Remove jQuery Migrate', 'wp-addon'),
        false,
        __('Removes jquery-migrate on the frontend. It may speed up the site but can break older themes and plugins. Enable only after checking forms, sliders, and menus.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_12',
        __('Disable new themes on major WP updates', 'wp-addon'),
        true,
        __('Prevents WordPress from installing a new default theme during a major core update. Convenient if you use only your own theme.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_13',
        __('Disable XML-RPC', 'wp-addon'),
        true,
        __('Disables XML-RPC — an old interface for remote publishing and some attacks. If you do not publish via external XML-RPC apps, it is safer to keep this enabled.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_14',
        __('Remove post by email function', 'wp-addon'),
        true,
        __('Disables publishing posts by email. Almost nobody needs this feature, and it is better to close an unused entry point.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_15',
        __('⚠️ Disable agressive update', 'wp-addon'),
        false,
        __('Disables automatic update checks on every admin visit. Reduces load, but even administrators will not see update notices until they open Updates manually. Skipped automatically when General Settings → Disable all updates is enabled.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_16',
        __('Disable URL auto-linking in comments', 'wp-addon'),
        true,
        __('Stops URLs in comments from being turned into clickable links automatically. Useful against spam and unwanted outbound links in comments. Different from General Settings → Disable link Nofollow in comment, which only removes rel="nofollow".', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_17',
        __('Remove login-shake on errors', 'wp-addon'),
        true,
        __('Removes the login form shake on wrong username or password. It does not affect site behavior — only the login page animation.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_18',
        __('Empty WP-Trash every 14 days', 'wp-addon'),
        true,
        __('Automatically empties the WordPress trash after 14 days. Helps keep deleted posts and comments from bloating the database.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_19',
        __('⚠️ Allow download types file: SVG, DOC, djv ..', 'wp-addon'),
        true,
        __('Allows uploading additional file types: SVG, DOC, WebP, and others. SVG is handy for icons but may contain malicious code — upload only trusted files. Also adds WebP and SVG MIME types; use settings 34 and 35 below for media library preview support.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_20',
        __('Disable pingback from this site to this site', 'wp-addon'),
        true,
        __('Prevents the site from sending pingbacks to itself. This is useless load and sometimes a source of spam.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_21',
        __('Hide adminbar in frontend for contributors, authors and subscribers', 'wp-addon'),
        true,
        __('Hides the admin bar on the frontend for authors, contributors, and subscribers. Editors and administrators still see it.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_22',
        __('Add VK and OK to user profile', 'wp-addon'),
        true,
        __('Adds VK and Odnoklassniki fields to the user profile and removes outdated AIM/Jabber/Yahoo IM fields.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_23',
        __('Showing usages memory and time generate site page', 'wp-addon'),
        false,
        __('Shows page generation time and memory usage in an HTML comment at the bottom of the page. Visible only to administrators. Better to leave off on production.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_24',
        __('⚠️ Remove Standard WP widget', 'wp-addon'),
        false,
        __('Removes all default WordPress widgets from the available list. Dangerous on unfamiliar sites: you can lose already configured sidebar blocks.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_25',
        __('AutoRemove readme.html and license.txt files', 'wp-addon'),
        true,
        __('Deletes readme.html and license.txt from the site root when an administrator opens the admin. These files can reveal the WordPress version to outsiders.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_26',
        __('Add search filter for taxonomy checklists in post editor', 'wp-addon'),
        true,
        __('Adds a search field above category and tag lists in the post editor. Very helpful when you have many terms.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_27',
        __('Keep selected taxonomy terms in place (disable checked on top)', 'wp-addon'),
        true,
        __('Does not move selected categories and tags to the top of the list. Makes long category trees easier to scan.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_28',
        __('If posts have status "pending" show numbers it in menu', 'wp-addon'),
        true,
        __('Shows a Pending count next to the Posts menu item in the admin.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_29',
        __('Showing message "Update wordpress" only admin', 'wp-addon'),
        true,
        __('Hides WordPress update notices from editors, authors, and other users without administrator rights.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_30',
        __('Repalse [...] to "Read more ..." for posts', 'wp-addon'),
        true,
        __('Replaces the default [...] excerpt marker with a Read more... link.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_31',
        __('Allow shortcode in "Text" widget', 'wp-addon'),
        true,
        __('Allows shortcodes to run in text and block widgets. Needed if you insert [shortcode] directly into a widget.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_32',
        __('⚠️ Get jquery from google cloud', 'wp-addon'),
        false,
        __('Loads jQuery from Google CDN instead of the WordPress bundled version. It may speed up loading but often breaks theme and plugin compatibility. Better to leave off by default.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_33',
        __('⚠️ Disable shortcode_unautop (keep paragraph tags around shortcodes)', 'wp-addon'),
        false,
        __('Disables automatic <p> wrapping around shortcodes. Needed only if layout around shortcodes breaks. Not required on most sites.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_34',
        __('Allow WEBP support for media', 'wp-addon'),
        true,
        __('Allows uploading and previewing WebP images in the media library. WebP is also allowed by setting 19 above; this option adds preview support in the library.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_35',
        __('Allow SVG support for media', 'wp-addon'),
        true,
        __('Helps SVG files display correctly in the media library. Works together with setting 19 above, which allows SVG uploads.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_36',
        __('Disable browser checking in dashboard', 'wp-addon'),
        true,
        __('Disables outdated browser checks in the WordPress dashboard. Removes an extra request to WordPress.org and annoying warnings.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_37',
        __('Change login error message', 'wp-addon'),
        true,
        __('Shows a generic Incorrect username or password message instead of hints like that user does not exist. This improves login page security.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_38',
        __('⚠️ Extend login session to 1 year when "Remember Me" is checked', 'wp-addon'),
        true,
        __('Extends the login cookie to 1 year when Remember Me is checked. Without the checkbox the default session length stays unchanged. Do not enable on shared computers.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_39',
        __('Disable wp-embed script on frontend', 'wp-addon'),
        true,
        __('Disables the wp-embed.js script WordPress loads for post embeds. On a regular site it is rarely needed and saves a few kilobytes.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_40',
        __('Disable dashicons CSS for guests', 'wp-addon'),
        true,
        __('Does not load dashicons CSS for logged-out visitors. Saves about 30 KB if the frontend has no admin UI elements.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_41',
        __('⚠️ Disable REST API for unauthorized users', 'wp-addon'),
        false,
        __('Closes the REST API for guests. Improves security but may break some forms, frontend Gutenberg blocks, and integrations. Enable only after testing.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_42',
        __('Noindex for search, attachment and date archives', 'wp-addon'),
        true,
        __('Adds noindex for search results, attachment pages, and date archives. These pages are rarely useful in Google and often create duplicates. Different from General Settings → Disable site indexing, which blocks the entire site.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_43',
        __('⚠️ Redirect author archives to home page', 'wp-addon'),
        true,
        __('Redirects author archive pages /author/name/ to the home page. Useful for a single-author blog. On a multi-author site it is better to leave this off.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_44',
        __('Disable theme and plugin file editor in admin', 'wp-addon'),
        true,
        __('Prevents editing theme and plugin PHP files from the admin. Recommended for security: changes should go through git or FTP deliberately.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_45',
        __('Disable application passwords', 'wp-addon'),
        true,
        __('Disables Application Passwords for REST API access. Rarely needed; if you do not use external apps with WP access, it is safer to keep this enabled.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_46',
        __('⚠️ Remove global theme styles on frontend', 'wp-addon'),
        false,
        __('Removes global-styles and classic-theme-styles on the frontend. It may speed up classic themes but can break blocks and typography. Check the site appearance after enabling.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_47',
        __('Remove REST and oEmbed discovery links from head', 'wp-addon'),
        true,
        __('Removes REST API and oEmbed discovery links from the head. Visitors do not need them, and they reveal extra site structure.', 'wp-addon'),
    ),
    wptweaker_field(
        'wptweaker_setting_48',
        __('Block WordPress and Automattic tracking pixel', 'wp-addon'),
        true,
        __('Blocks requests to pixel.wp.com, stats.wp.com, and WordPress Events in the admin. Also disables WooCommerce/Jetpack tracking when those plugins are installed. Does not block core updates.', 'wp-addon'),
    ),
];
