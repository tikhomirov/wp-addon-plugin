<?php

/**
 * Custom default user avatar.
 *
 * @author: Aleksey Tikhomirov
 *
 * @year: 2019-02-25
 */

namespace functions\users;

if (! class_exists('functions\users\UserAvatar')) {
    class UserAvatar
    {
        public string $url;

        public function __construct(string $url = '')
        {
            add_filter('avatar_defaults', [$this, 'new_default_avatar'], 10);
            add_filter('get_avatar', [$this, 'media_get_avatar'], 10, 6);

            $this->url = ! empty($url) ? $url : RW_PLUGIN_URL . 'assets/images/user_gray.svg';
        }

        /**
         * Custom default avatar by default.
         *
         *
         * @return mixed
         */
        public function new_default_avatar($avatar_defaults)
        {
            $avatar_defaults[$this->url] = __('Custom Default Avatar', 'wp-addon');

            return $avatar_defaults;
        }

        /**
         * Replace the default avatar image in the avatar picker list.
         *
         *
         * @return string Avatar HTML markup
         */
        public function media_get_avatar($avatar, $id_or_email, $size, $default, $alt, $args): string
        {
            if ($default === $this->url) { // default avatar URL
                $avatar = '<img src="' . $args['default'] . '" width="32px" height="32px" class="avatar avatar-32 photo avatar-default" alt="' . __(
                    'User avatar',
                    'wp-addon',
                ) . '">';
                // $args['default']
            }

            return $avatar;
        }
    }

    return new UserAvatar;
}
