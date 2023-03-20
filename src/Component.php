<?php

namespace Betterde\TranslatorCli;

use Illuminate\Support\Arr;
use Illuminate\Http\Client\RequestException;

/**
 * Component Synchronize handler.
 *
 * Date: 2022/5/30
 * @author George
 * @package Betterde\TranslatorCli
 */
class Component
{
    /**
     * @var string $name
     */
    public string $name;

    /**
     * @var string $filename
     */
    public string $filename;

    /**
     * @var array $keys
     */
    public array $keys = [];

    /**
     * @param string $name
     * @param string $filename
     */
    public function __construct(string $name, string $filename)
    {
        $this->name = $name;
        $this->filename = $filename;
    }

    /**
     * Fetch the keys and translations of component from remote.
     *
     * Date: 2023/3/19
     * @author George
     * @param array $languages
     * @param int $limit
     * @param int $page
     * @return array
     * @throws RequestException
     */
    public function fetchKeysFromRemote(array $languages = [], int $limit = 50, int $page = 1): array
    {
        $request = Certification::$request;
        $url = sprintf('%s/projects/%s/keys', Certification::$endpoint, Certification::$project);
        $response = $request->get($url, [
            'page' => $page,
            'search' => sprintf('%s.', $this->name),
            'per_page' => $limit,
        ])->throw()->json();

        $data = Arr::get($response, 'data');
        $included = Arr::get($response, 'included');
        foreach ($data as $item) {
            $id = Arr::get($item, 'id');
            $name = Arr::get($item, 'attributes.name');
            $translations = collect($included)->where('type', 'translation')->where('attributes.key_id', $id);
            $contents = [];
            $default = collect($included)
                ->where('type', 'translation')
                ->where('attributes.key_id', $id)
                ->where('attributes.language_id', Project::$language->id)
                ->first();
            foreach ($languages as $language) {
                $translation = $translations->where('attributes.language_id', $language->id)->first();
                $content = Arr::get($translation, 'attributes.content');
                if (!empty($content)) {
                    $contents[$language->name] = Arr::get($translation, 'attributes.content');
                }
            }

            $this->keys[$name] = [
                'id' => $id,
                'name' => $name,
                'default' => Arr::get($default, 'attributes.content'),
                'translations' => $contents
            ];
        }

        $total = Arr::get($response, 'meta.total', 0);

        if ($total > $limit && count($data) == $limit) {
            $page++;
            $batch = intval(ceil($total / $limit));
            if ($page <= $batch) {
                $this->fetchKeysFromRemote($languages, $limit, $page);
            }
        }

        return $this->keys;
    }

    /**
     * Date: 2022/5/30
     * @param string $path
     * @param string $locale
     * @return array
     * @author George
     */
    public function load(string $path, string $locale): array
    {
        $data = [];
        if (is_file($path)) {
            $__path = $path;
            $__data = $data;

            $data = (static function () use ($__path, $__data) {
                extract($__data, EXTR_SKIP);
                return require $__path;
            })();
        }

        return Arr::dot($data);
    }
}