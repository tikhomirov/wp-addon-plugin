# Settings Guide

This comprehensive guide covers all configuration options available in the WP Addon Plugin settings panel.

## Accessing Settings

Navigate to **WordPress Admin > Settings > WP Addon** to access the plugin configuration.

## Settings Sections

### General Settings

#### Maintenance Mode
- **Setting**: `enable_maintenance`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Enables maintenance mode, showing a maintenance page to all users except administrators.

#### Show Custom Fields in Admin
- **Setting**: `show_all_custom_fields`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Displays custom fields meta box on post edit pages for categories and posts.

#### Disable Auto Updates
- **Setting**: `disable_auto_update`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Completely disables all WordPress automatic updates (core, plugins, themes).

### Posts and Pages Settings

#### Show Post IDs
- **Setting**: `show_id`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds an ID column to the posts and pages admin tables.

#### Show Thumbnails
- **Setting**: `show_thumbnail`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds a thumbnail column to the posts admin table.

#### Enable Post Duplication
- **Setting**: `duplicate_post`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds "Duplicate" action to posts and pages for easy content duplication.

#### Remove Category URL
- **Setting**: `remove_category_url`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Removes category base from URLs (requires permalink flush).

#### Change Excerpt Length
- **Setting**: `change_excerpt`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Limits post excerpt to maximum 200 characters.

#### Exclude Categories from Frontend
- **Setting**: `exclude_cat`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Hides specific categories from frontend display.

#### Excluded Categories List
- **Setting**: `exclude_cat_val`
- **Type**: Textarea
- **Default**: Pre-populated with example IDs
- **Description**: Comma-separated list of category IDs to exclude (use minus sign: -123).
- **Dependency**: Requires `exclude_cat` to be enabled.

### Comment Settings

#### Disable All Comments
- **Setting**: `disable_comments`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Globally disables comments on all posts and pages.

#### Disable Comment Links Nofollow
- **Setting**: `disable_comment_nofollow`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Removes rel="nofollow" from comment author links.

#### Remove Website Field
- **Setting**: `remove_site_field_in_comment`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Removes the website/URL field from comment forms.

### Comment Spam Settings

Replaces the Kama SpamBlock plugin. A blocked comment is never stored in the
database, no notification email is sent, and the page cache is not purged.

#### Enable Antispam
- **Setting**: `antispam_enabled`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Turns the whole comment spam filter on. When disabled no hook is registered at all.

#### Score Threshold
- **Setting**: `antispam_threshold`
- **Type**: Number
- **Default**: `3`
- **Description**: Minimum total score that blocks a comment. Higher means fewer false positives, more spam gets through.

#### Honeypot Field
- **Setting**: `antispam_honeypot`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Renders a hidden `antispam_website` field inside the comment form. Any value means a bot.

#### Minimum Fill Time
- **Setting**: `antispam_min_seconds`
- **Type**: Number
- **Default**: `3`
- **Description**: Seconds a visitor must spend on the page before the form may be submitted. `0` disables the check. The timestamp is written by JavaScript, so a page served from cache is not affected.

#### Hourly Comment Limit
- **Setting**: `antispam_rate_hour`
- **Type**: Number
- **Default**: `0` (disabled)
- **Description**: Maximum comments per IP per hour. `0` turns the limit off.

#### Daily Comment Limit
- **Setting**: `antispam_rate_day`
- **Type**: Number
- **Default**: `0` (disabled)
- **Description**: Maximum comments per IP per day. `0` turns the limit off.

#### Verify Pingback Backlinks
- **Setting**: `antispam_check_backlinks`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Fetches the source page of a pingback or trackback and requires a link back to the site.

#### Trust Moderators
- **Setting**: `antispam_trust_moderators`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Skips every check for users who can moderate comments.

#### Blocked Message
- **Setting**: `antispam_blocked_message`
- **Type**: Textarea
- **Default**: `Your comment looks like spam and was rejected. Please review it and try again.`
- **Description**: Message shown to the author when a comment is rejected. Returned with HTTP 403.

#### How the score is counted

| Signal | Weight |
| --- | --- |
| Body is a single random token (ASCII letters only, 16+ chars, mixed case) | 3 |
| Author name is a random token by the same rule | 2 |
| No Cyrillic and no whitespace in the body | 1 |
| Three or more dots in the email local part | 1 |
| First visit (no `comment_author_*` cookies) | 1 |

The weights are additive, and the body token alone already reaches the default
threshold. Device names, Git hashes and plain English words are not treated as
random tokens.

#### Blocked Attempts Statistics

The section renders the number of blocked attempts for the last 30 days, a
breakdown by reason, the most active IPs, and the last attempts table. Data
lives in the `wp_addon_antispam_log` table and is pruned monthly by the
`wp_addon_antispam_rotate` cron event, plus opportunistically every 50 writes.

## TinyMCE Editor Settings

#### Disable Gutenberg
- **Setting**: `disable_guttenberg`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Forces classic editor for all users.

#### Custom Color Palette
- **Setting**: `tiny_custom_colors`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds custom color palette to TinyMCE editor.

#### Enable Open Sans Font
- **Setting**: `tiny_enable_opensans`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Loads Open Sans Google font for editor.

#### Advanced TinyMCE
- **Setting**: `tiny_advanced`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds third column with advanced formatting options (fonts, sizes, backgrounds).

#### Bootstrap 3 Support
- **Setting**: `add_bootstrap_3`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds Bootstrap 3 CSS classes to TinyMCE.

#### Table Plugin
- **Setting**: `tiny_table_plugin`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Enables table creation and editing in TinyMCE.

## SEO Settings

#### Auto Alt Text for Images
- **Setting**: `img_alt_in_upload`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Automatically sets alt text from image title during upload.

#### Enable Transliteration
- **Setting**: `transliteration_enable`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Converts Cyrillic characters to Latin in URLs and filenames.

#### Disable Site Indexing
- **Setting**: `index_disable`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Adds noindex meta tag to prevent search engine indexing.

#### Enable 301 Redirects
- **Setting**: `redirect_enable`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Enables custom redirect functionality.

## Database Optimization

#### Fix GUID on New Posts
- **Setting**: `write_right_guid`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Ensures correct GUID generation for new posts.

#### GUID Repair Interface
- **Setting**: `fix_guid`
- **Type**: Switcher
- **Default**: Disabled
- **Description**: Provides admin interface to repair GUIDs containing %category%, %tag%, etc.

## Dashboard Widgets

#### Show All Post Types
- **Setting**: `change_glance_widget`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Displays all post types in "At a Glance" dashboard widget.

#### Plugin List Widget
- **Setting**: `dashboard_plugin_list`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Adds plugin list widget to dashboard.

#### Server Info Widget
- **Setting**: `dashboard_server_info`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Shows server information (PHP version, memory, etc.) on dashboard.

#### User Roles Widget
- **Setting**: `dashboard_role_list`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Displays user role statistics on dashboard.

## Lazy Loading Settings

### Enable Lazy Loading
- **Setting**: `enable_lazy_loading`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Main toggle for the lazy loading module. When enabled, lazy loading is activated for selected media types. Recommended for all sites to improve performance.

### Media Types for Lazy Loading
- **Setting**: `lazy_types`
- **Type**: Checkbox
- **Default**: img, iframe, video
- **Description**: Select element types to apply lazy loading to. Images are most effective for optimization.
- **Options**: img (Images), iframe (YouTube, Vimeo), video (Video elements)

### Blur Effect Intensity
- **Setting**: `blur_intensity`
- **Type**: Number (1-10)
- **Default**: 5
- **Description**: Blur intensity for placeholder images. 1 = light blur, 10 = heavy blur. Recommended 3-7 for optimal quality/performance balance.

### Viewport Margin (rootMargin)
- **Setting**: `root_margin`
- **Type**: Text
- **Default**: 50px
- **Description**: Distance from viewport edge to start loading. Example: 50px = load 50px before element appears. 10% = 10% of viewport height.

### Visibility Threshold
- **Setting**: `threshold`
- **Type**: Number (0-1)
- **Default**: 0.1
- **Description**: Portion of element that must be visible to start loading. 0.1 = 10% of element visible. 1.0 = entire element visible.

### Enable Fallback for Old Browsers
- **Setting**: `enable_fallback`
- **Type**: Switcher
- **Default**: Enabled
- **Description**: Use scroll event listeners instead of Intersection Observer in browsers without IO support. Slower performance but ensures compatibility.

## Performance Tweaks

### Header Cleanup
- **Remove WP-Version**: `wptweaker_setting_1` - Removes WordPress version from meta tags
- **Disable Emojis**: `wptweaker_setting_2` - Removes emoji scripts and styles
- **Remove Windows Live Writer**: `wptweaker_setting_3` - Removes WLW manifest link
- **Remove RSD Link**: `wptweaker_setting_4` - Removes RSD API link
- **Remove RSS Links**: `wptweaker_setting_5` - Removes RSS feed links from head
- **Remove Shortlink**: `wptweaker_setting_6` - Removes shortlink from header
- **Remove Adjacent Links**: `wptweaker_setting_7` - Removes next/previous post links

### Content Optimization
- **Limit Post Revisions**: `wptweaker_setting_8` - Limits revisions to 5
- **Block HTTP Requests**: `wptweaker_setting_9` - Blocks plugin/theme update checks
- **Disable Heartbeat**: `wptweaker_setting_10` - Disables admin-ajax heartbeat
- **Remove jQuery Migrate**: `wptweaker_setting_11` - Removes jQuery migrate script

### System Tweaks
- **Disable Theme Updates**: `wptweaker_setting_12` - Blocks theme update notifications
- **Disable XML-RPC**: `wptweaker_setting_13` - Completely disables XML-RPC
- **Remove Post by Email**: `wptweaker_setting_14` - Disables post-by-email feature
- **Disable Aggressive Updates**: `wptweaker_setting_15` - Prevents forced updates
- **Disable Comment URL Auto-Linking**: `wptweaker_setting_16` - Prevents URL auto-linking in comments
- **Remove Login Shake**: `wptweaker_setting_17` - Disables login form shake on errors
- **Auto Empty Trash**: `wptweaker_setting_18` - Empties trash every 14 days
- **Allow Additional File Types**: `wptweaker_setting_19` - Adds SVG, DOC, etc. to allowed uploads
- **Disable Self-Pingbacks**: `wptweaker_setting_20` - Prevents pingbacks to self
- **Hide Admin Bar**: `wptweaker_setting_21` - Hides admin bar for contributors/authors/subscribers
- **Add Social Fields**: `wptweaker_setting_22` - Adds VK/OK fields to user profiles
- **Show Performance Info**: `wptweaker_setting_23` - Displays memory/time usage in footer
- **Remove Default Widgets**: `wptweaker_setting_24` - Removes standard WordPress widgets
- **Auto Remove Files**: `wptweaker_setting_25` - Removes readme.html and license.txt
- **Pending Posts Notice**: `wptweaker_setting_26` - Shows notice for pending posts
- **Disable Taxonomy Dropdown**: `wptweaker_setting_27` - Removes taxonomy dropdown in post editor
- **Show Pending Count**: `wptweaker_setting_28` - Shows pending posts count in menu
- **Admin Update Notices**: `wptweaker_setting_29` - Shows update notices only to admins
- **Replace Excerpt More**: `wptweaker_setting_30` - Changes [...] to "Read more..."
- **Shortcodes in Widgets**: `wptweaker_setting_31` - Enables shortcodes in text widgets
- **jQuery from Google**: `wptweaker_setting_32` - Loads jQuery from Google CDN
- **Remove Auto P**: `wptweaker_setting_33` - Removes automatic paragraph tags
- **Allow WebP**: `wptweaker_setting_34` - Enables WebP upload support
- **Allow SVG**: `wptweaker_setting_35` - Enables SVG upload support
- **Disable Browser Check**: `wptweaker_setting_36` - Removes browser compatibility warnings

## Shortcodes and Widgets

### Gutenberg Settings
- **Disable Gutenberg Widgets**: `disable_guttenberg_widget` - Forces classic widgets
- **Enable Widget Duplication**: `add_clone_widget` - Allows duplicating widgets

### Shortcodes
- **FAQ Shortcode**: `faq_shortcode` - Enables FAQ accordion shortcode
- **Table of Contents**: `table_of_contents` - Enables TOC shortcode

### Widgets
- **Yearly Archive Widget**: `archive_widget` - Adds yearly archive widget

## Contact Form 7 Integration

*Available only if Contact Form 7 is installed*

- **Show Shortcode**: `cf7_show_shortcode` - Displays additional CF7 shortcodes

## Polylang Integration

*Available only if Polylang is installed*

- **Hide Languages**: `pll_hide_lang` - Space-separated list of language slugs to hide on frontend

## Custom Code

### Header CSS
- **Setting**: `rw_header_css`
- **Type**: Code Editor (CSS)
- **Description**: Custom CSS injected in page header

### Header HTML
- **Setting**: `rw_header_html`
- **Type**: Code Editor (HTML)
- **Description**: Custom HTML/JavaScript/Analytics code in header

### Footer HTML
- **Setting**: `rw_footer_html`
- **Type**: Code Editor (Mixed)
- **Description**: Custom HTML/JavaScript code in footer

## Media Cleanup Settings

*Located in separate Media Cleanup section*

- **Cleanup Tool**: Interactive tool to remove unused image sizes
- **Supported Formats**: JPG, JPEG, PNG, GIF
- **Preserved Sizes**: Original images and scaled versions
- **AJAX Endpoints**: `wp_addon_cleanup_images_dry_run`, `wp_addon_cleanup_images`

## Configuration Tips

### Performance Optimization
1. Enable maintenance mode during development
2. Use performance tweaks (disable emojis, heartbeat, etc.)
3. Configure media cleanup regularly
4. Enable caching-friendly settings

### Security Best Practices
1. Disable XML-RPC if not needed
2. Remove version information from headers
3. Enable comment restrictions
4. Use maintenance mode for updates

### SEO Configuration
1. Enable transliteration for clean URLs
2. Set up proper meta descriptions
3. Configure redirects
4. Use custom excerpt lengths

### Development Workflow
1. Use custom code sections for temporary changes
2. Leverage module system for permanent features
3. Test settings in staging environment
4. Backup before major changes

## Troubleshooting

### Settings Not Saving
- Check file permissions on wp-content/uploads/
- Verify Codestar Framework is installed
- Clear browser cache and try again

### Features Not Working
- Ensure required plugins are installed (CF7, Polylang)
- Check for plugin conflicts
- Review error logs

### Performance Issues
- Disable unnecessary tweaks
- Check server resource usage
- Optimize database regularly

## Related Documentation

- [README.md](README.md) - Plugin overview and installation
- [MODULES_GUIDE.md](MODULES_GUIDE.md) - Module development guide
- [WordPress Codex](https://codex.wordpress.org/) - WordPress documentation
- [Codestar Framework](https://github.com/Codestar/codestar-framework) - Settings framework

## Support

For additional help:
- Check WordPress forums
- Review plugin changelog
- Contact plugin author
- Submit GitHub issues
