<?php

/**
 * Свой аватар для пользователей по-умолчанию.
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

            $this->url = ! empty($url) ? $url : RW_PLUGIN_URL.'assets/images/user_gray.svg';
        }

        /**
         * Свой вариант аватарки по-умолчанию.
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
         * Свой вариант аватарки по-умолчанию. Заменяем картинку при отображении списка аватаров.
         *
         *
         * @return string - html код аватарки
         */
        public function media_get_avatar($avatar, $id_or_email, $size, $default, $alt, $args): string
        {
            if ($default === $this->url) { // путь к файлу
                $avatar = '<img src="'.$args['default'].'" width="32px" height="32px" class="avatar avatar-32 photo avatar-default" alt="'.__('User avatar',
                    'wp-addon').'">';
                // $args['default']
            }

            return $avatar;
        }
    }

    return new UserAvatar;
}
