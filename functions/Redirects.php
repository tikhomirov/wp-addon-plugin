<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\HookTrait;

class Redirects implements ModuleInterface
{
    use HookTrait;

    public function init(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->addHook('init', [$this, 'processRedirects'], 1);
    }

    public function processRedirects(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $options = get_option('wp-addon', []);

        // Check if redirects are configured
        if (empty($options['redirects_rules'])) {
            return;
        }

        $redirects = [];
        foreach ($options['redirects_rules'] as $rule) {
            if (! empty($rule['request']) && ! empty($rule['destination'])) {
                $redirects[trim($rule['request'])] = trim($rule['destination']);
            }
        }

        if (empty($redirects)) {
            return;
        }

        // Get the current request
        $userrequest = str_ireplace(get_option('home'), '', $this->getAddress());
        $userrequest = rtrim($userrequest, '/');

        $wildcard = ! empty($options['redirects_wildcard']);
        $do_redirect = '';

        // Check each redirect rule
        foreach ($redirects as $storedrequest => $destination) {
            // Check if we should use regex search
            if ($wildcard && strpos($storedrequest, '*') !== false) {
                // Wildcard redirect
                // Don't allow people to accidentally lock themselves out of admin
                if (strpos($userrequest, '/wp-login') !== 0 && strpos($userrequest, '/wp-admin') !== 0) {
                    // Make sure it gets all the proper decoding and rtrim action
                    $storedrequest = str_replace('*', '(.*)', $storedrequest);
                    $pattern = '/^'.str_replace('/', '\/', rtrim($storedrequest, '/')).'/';
                    $destination = str_replace('*', '$1', $destination);
                    $output = preg_replace($pattern, $destination, $userrequest);
                    if ($output !== $userrequest) {
                        // Pattern matched, perform redirect
                        $do_redirect = $output;
                    }
                }
            } elseif (urldecode($userrequest) == rtrim($storedrequest, '/')) {
                // Simple comparison redirect
                $do_redirect = $destination;
            }

            // Redirect. The second condition here prevents redirect loops as a result of wildcards.
            if ($do_redirect !== '' && trim($do_redirect, '/') !== trim($userrequest, '/')) {
                // Check if destination needs the domain prepended
                if (strpos($do_redirect, '/') === 0) {
                    $do_redirect = home_url().$do_redirect;
                }
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: '.$do_redirect);
                exit();
            } else {
                unset($redirects[$storedrequest]);
            }
        }
    }

    private function isEnabled(): bool
    {
        $options = get_option('wp-addon', []);

        return ! array_key_exists('redirect_enable', $options) || $this->isTruthy($options['redirect_enable']);
    }

    private function isTruthy($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * Get the full address of the current request
     * Credit: http://www.phpro.org/examples/Get-Full-URL.html
     */
    private function getAddress(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        return $this->getProtocol().'://'.$host.$requestUri;
    }

    private function getProtocol(): string
    {
        // Set the base protocol to http
        $protocol = 'http';
        // Check for https
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
            $protocol .= 's';
        }

        return $protocol;
    }
}
