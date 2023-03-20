<?php

namespace Betterde\TranslatorCli;

class Translation
{
    public string $id;
    public string $key;
    public string $content;
    public string $language;

    /**
     * @param string $id
     * @param string $key
     * @param string $content
     * @param string $language
     */
    public function __construct(string $id, string $key, string $content, string $language)
    {
        $this->id = $id;
        $this->key = $key;
        $this->content = $content;
        $this->language = $language;
    }
}