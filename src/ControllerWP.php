<?php

namespace WpAddon;

use WpAddon\Services\OptionService;

final class ControllerWP
{
    private OptionService $optionService;

    private array $options;

    /**
     * Constructor
     */
    public function __construct(OptionService $optionService)
    {
        $this->optionService = $optionService;
        $this->options = $this->optionService->getSettings();
    }

    /**
     * Fire! Run tweak options
     */
    public function options_loader()
    {
        if (! is_array($this->options)) {
            return;
        }

        /**
         * @var string $key Option name
         * @var int|array|false $value Option value
         */
        foreach ($this->options as $key => $value) {
            if ($value == 1) {
                $this->run($key); // 'plugins_loaded'
            } elseif (is_array($value)) { // block options
                foreach ($value as $name => $val) {
                    if ($val == '1') { // включаем все что не выключено
                        $this->run($name);
                    }
                }
            }
        }
    }

    /**
     * Запускаем если есть такая функция
     *
     * @param  $settings
     */
    protected function run($function)
    {
        $function = str_replace('-', '_', $function);
        if (function_exists($function)) {
            add_action('init', $function, 1);
        }
    }
}
