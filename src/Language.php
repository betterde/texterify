<?php

namespace Betterde\TranslatorCli;

class Language
{
    /**
     * @var string $id
     */
    public string $id;

    /**
     * @var string $name
     */
    public string $name;

    /**
     * @var bool $default
     */
    public bool $default;

    /**
     * @var string $country
     */
    public string $country;

    /**
     * @param string $id
     * @param string $name
     * @param bool $default
     * @param string $country
     */
    public function __construct(string $id, string $name, bool $default, string $country)
    {
        $this->id = $id;
        $this->name = $name;
        $this->default = $default;
        $this->country = $country;
    }
}