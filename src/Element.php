<?php

namespace Betterde\TranslatorCli;

use Illuminate\Support\Arr;
use Illuminate\Http\Client\RequestException;

/**
 * The element of the component
 *
 * Date: 2023/3/19
 * @author George
 * @package Betterde\TranslatorCli
 */
class Element
{
    /**
     * @var string $key
     */
    private string $key;

    /**
     * @var bool $exists
     */
    public bool $exists;

    /**
     * @var bool $updated
     */
    public bool $updated;

    /**
     * @var string $content
     */
    private string $content;

    /**
     * @param string $key
     * @param string $content
     */
    public function __construct(string $key, string $content)
    {
        $this->key = $key;
        $this->exists = false;
        $this->content = $content;
        $this->updated = false;
    }

    /**
     * Sync translation keys from local default language to remote.
     *
     * Date: 2022/6/6
     * @author George
     * @param string|null $languageID
     * @return Element
     * @throws RequestException
     */
    public function sync(string $languageID = null): self
    {
        if (empty($languageID)) {
            $languageID = Project::$language->id;
        }

        $response = $this->find();
        $data = Arr::get($response, 'data');
        $key = collect($data)->where('attributes.name', $this->key)->first();

        if ($key) {
            $this->exists = true;
            $included = Arr::get($response, 'included');
            $translation = collect($included)
                ->where('type', 'translation')
                ->where('attributes.key_id', Arr::get($key, 'id'))
                ->where('attributes.language_id', $languageID)->first();
            $content = Arr::get($translation, 'attributes.content');

            if (getenv('SHELL_VERBOSITY') == 3) {
                conmsg(sprintf("The key name '%s' is already exist.", $this->key));
            }

            if ($content != $this->content) {
                $this->createTranslation([
                    'key_id' => Arr::get($key, 'id'),
                    'flavor_id' => null,
                    'language_id' => Project::$language->id,
                    'translation' => [
                        'content' => $this->content
                    ]
                ]);

                $this->updated = true;

                if (getenv('SHELL_VERBOSITY') == 3) {
                    conmsg(sprintf("The translation of the key name '%s' was changed from '%s' to '%s'", $this->key, $content, $this->content));
                }
            }
        } else {
            $this->create();
        }

        return $this;
    }

    /**
     * Find the translation key by the name.
     *
     * Date: 2023/3/19
     * @author George
     * @return array
     * @throws RequestException
     */
    public function find(): array
    {
        $request = Certification::$request;
        $url = sprintf('%s/projects/%s/keys', Certification::$endpoint, Certification::$project);
        return $request->get($url, [
            'match' => 'exactly',
            'case_sensitive' => true,
            'search' => $this->key
        ])->throw()->json();
    }

    /**
     * Create the translation key.
     *
     * Date: 2023/3/19
     * @author George
     * @throws RequestException
     */
    public function create(): void
    {
        $request = Certification::$request;
        $url = sprintf('%s/projects/%s/keys', Certification::$endpoint, Certification::$project);
        $response = $request->post($url, [
            'name' => $this->key,
            'description' => '',
            'html_enabled' => false
        ]);
        $payload = $response->json();
        $data = Arr::get($payload, 'data');

        if (getenv('SHELL_VERBOSITY') == 3) {
            conmsg(sprintf("The key name '%s' has been successfully created.", $this->key));
        }

        $this->createTranslation([
            'key_id' => Arr::get($data, 'id'),
            'flavor_id' => null,
            'language_id' => Project::$language->id,
            'translation' => [
                'content' => $this->content
            ]
        ]);
    }

    /**
     * Create the translation.
     *
     * Date: 2023/3/19
     * @author George
     * @param array $attributes
     * @throws RequestException
     */
    public function createTranslation(array $attributes): void
    {
        $request = Certification::$request;
        $url = sprintf('%s/projects/%s/translations', Certification::$endpoint, Certification::$project);
        $request->post($url, $attributes)->throw()->json();
    }
}