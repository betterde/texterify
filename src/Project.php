<?php

namespace Betterde\TranslatorCli;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Factory;
use Symfony\Component\Console\Helper\Table;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Project language package handler.
 *
 * Date: 2022/6/6
 * @author George
 * @package Betterde\TranslatorCli
 */
class Project
{
    /**
     * Default language fo this project.
     *
     * @var Language $language
     */
    public static Language $language;

    /**
     * @var string $id
     */
    private string $id;

    /**
     * @var string $name
     */
    private string $name;

    /**
     * @var string $description
     */
    private string $description;

    /**
     * Absolute path of the project
     *
     * @var string
     */
    private string $path;

    /**
     * Multiple language files under the specified language.
     *
     * @var array
     */
    private array $files = [];

    /**
     * @var array|Language[] $languages
     */
    public array $languages;

    /**
     * Component files table.
     *
     * @var Table
     */
    private Table $table;

    /**
     * @var Collection|Component[]
     */
    private Collection $components;

    /**
     * Console stdout.
     *
     * @var ConsoleOutput
     */
    private ConsoleOutput $stdout;

    /**
     * Project construct
     *
     * @param string $id
     * @param string $path
     */
    public function __construct(string $id, string $path)
    {
        $this->id = $id;
        $this->path = $path;
        $stdout = new ConsoleOutput();
        $this->table = new Table($stdout);
        $this->stdout = $stdout;
    }

    /**
     * Date: 2023/3/18
     * @author George
     * @param bool $recursion
     * @param string|null $language
     * @return $this
     * @throws RequestException
     */
    public function loadComponents(bool $recursion = true, string $language = null): static
    {
        if (empty($language)) {
            $language = $this->getDefaultLanguage();
        }

        $languagePath = languagePath($this->path, $language);
        $this->scanDir($languagePath, $this->files, $recursion);

        $components = collect([]);

        foreach ($this->files as $file) {
            $slashFilename =  trim(Str::after($file, $languagePath), '/');
            $dotFilename = Str::replace('/', '.', $slashFilename);
            $componentName = Str::before($dotFilename, '.php');
            $component = new Component($componentName, $file);
            $components->push($component);
        }

        $this->components = $components;

        return $this;
    }

    /**
     * Render a list of components on the console.
     *
     * Date: 2023/3/18
     * @author George
     * @throws RequestException
     */
    public function renderComponentsToTable(): void
    {
        $language = $this->getDefaultLanguage();
        $this->table->setHeaders(['Name', 'Language', 'File']);
        foreach ($this->components as $component) {
            $this->table->addRow([$component->name, $language, $component->filename]);
        }

        $this->table->render();
    }

    /**
     * Fetch languages from remote.
     *
     * Date: 2023/3/18
     * @author George
     * @return $this
     * @throws RequestException
     */
    public function fetchLanguages(): self
    {
        if (empty($this->languages)) {
            $request = Certification::$request;
            $url = sprintf('%s/projects/%s/languages', Certification::$endpoint, Certification::$project);
            $response = $request->get($url)->throw()->json();
            $languages = Arr::get($response, 'data');
            $included = Arr::get($response, 'included');

            foreach ($languages as $language) {
                $attributes = Arr::get($language, 'attributes', []);
                $countryID = Arr::get($language, 'relationships.country_code.data.id');
                $country = collect($included)->where('type', 'country_code')->where('id', $countryID)->first();
                $countryName = Arr::get($country, 'attributes.name');
                $this->languages[] = new Language(Arr::get($attributes, 'id'), Arr::get($attributes, 'name'), Arr::get($attributes, 'is_default'), $countryName);
            }
        }

        return $this;
    }

    /**
     * Render a list of languages on the console.
     *
     * Date: 2023/3/18
     * @author George
     */
    public function renderLanguagesToTable(): void
    {
        $this->table->setHeaders(['ID', 'Country', 'Language', 'Default']);

        foreach ($this->languages as $language) {
            $this->table->addRow([
                $language->id,
                $language->country,
                $language->name,
                $language->default ? 'true' : 'false'
            ]);
        }

        $this->table->render();
    }

    /**
     * Synchronize all language packages under the specified language of the current project.
     *
     * Date: 2022/5/30
     * @author George
     * @throws RequestException
     */
    public function pushKeyAndTranslationToRemote(string $name = null, string $language = null): void
    {
        if (empty($language)) {
            $language = $this->getDefaultLanguage();
        }

        $this->table->setHeaders(['Path', 'Language', 'Component', 'Extension', 'Total', 'Successful', 'Existed', 'Updated', 'Skipped', 'Failed']);

        /**
         * @var Component $component
         */
        foreach ($this->components as $component) {
            if (!empty($name) && $name !== $component->name) {
                continue;
            }

            if (getenv('SHELL_VERBOSITY') >= 1) {
                $this->stdout->writeln(sprintf("Now, ready to read %s file.", $component->filename));
            }
            $file = pathinfo($component->filename);

            // Only files with the PHP extension are synchronized.
            if ($file['extension'] == 'php') {
                $slug = Str::replace('.' . $file['extension'], '', Str::after($component->filename, $language . '/'));
                $keys = $component->load($component->filename, $language);

                $failed = 0;
                $exists = 0;
                $skipped = 0;
                $updated = 0;
                $successful = 0;

                foreach ($keys as $key => $text) {
                    if (empty($key) || empty($text)) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $fullKey = sprintf('%s.%s', $slug, $key);
                        $element = new Element($fullKey, $text);

                        $element->sync();

                        if ($element->exists === false) {
                            $successful++;
                        } elseif ($element->exists === true) {
                            $exists++;
                        }

                        if ($element->updated) {
                            $updated++;
                        }

                    } catch (Exception $e) {
                        $this->stdout->writeln(sprintf("<fg=red> %s </>", $e->getMessage()));
                        $failed++;
                    }
                }

                $this->table->addRow([
                    Str::before($component->filename, $language),
                    $language,
                    Str::replace('.' . $file['extension'], '', Str::after($component->filename, $language . '/')),
                    $file['extension'],
                    count($keys),
                    $successful,
                    $exists,
                    $updated,
                    $skipped,
                    $failed
                ]);

                if (getenv('SHELL_VERBOSITY') == 3) {
                    $this->stdout->writeln(sprintf("Path: %s Language: %s File: %s Total: %d Successful: %d Existed: %d Updated: %d Skipped: %d Failed: %d",
                        Str::before($component->filename, $language),
                        $language,
                        Str::after($component->filename, $language),
                        count($keys),
                        $successful,
                        $exists,
                        $updated,
                        $skipped,
                        $failed
                    ));
                }
            }

            if (getenv('SHELL_VERBOSITY') >= 1) {
                $message = sprintf("Now, The %s file has been synchronized", $component->filename);
                $this->stdout->writeln($message);
                $this->stdout->writeln(str_repeat('-', strlen($message)) . "\n");
            }
        }

        $this->stdout->writeln(sprintf("This project has the following files under the %s language package:", $language));
        $this->table->render();
    }

    /**
     * Pull translations from remote.
     *
     * Date: 2023/3/19
     * @author George
     * @param string|null $componentName
     * @param string|null $languageCode
     * @throws RequestException
     */
    public function pullTranslations(string $componentName = null, string $languageCode = null, bool $fallback = false): void
    {
        $languages = [];
        $this->fetchLanguages();
        if (empty($languageCode)) {
            $languages = collect($this->languages)->where('default', false)->all();
        } else {
            $result = collect($this->languages)->where('name', $languageCode)->first();
            if (empty($result)) {
                warn('The specified language does not exist, please create a new language in Texterify and complete the translation!');
                exit(1);
            }
            $languages[] = $result;
        }

        foreach ($this->components as $component) {
            if (!empty($componentName) && $component->name != $componentName) {
                continue;
            }

            $response = $component->fetchKeysFromRemote($languages);

            foreach ($languages as $language) {
                $translations = [];
                foreach ($response as $item) {
                    $content = Arr::get($item, "translations.$language->name");
                    $index = Str::after($item['name'], $component->name . '.');
                    if (!empty($content)) {
                        $translations[$index] = $content;
                    } elseif ($fallback) {
                        $translations[$index] = Arr::get($item, 'default');
                    }
                }

                $package = Arr::undot($translations);
                $filename = sprintf('%s/%s/%s.php', languagePath($this->path), $language->name, str_replace('.', '/', $component->name));

                if (!file_exists(dirname($filename))) {
                    mkdir(dirname($filename), 0755, true);
                }

                $content = sprintf("<?php\n\nreturn %s;", var_export($package, true));

                file_put_contents($filename, $content);
            }
        }
    }

    /**
     * Scan the given directory path.
     *
     * Date: 2022/6/5
     * @param string $path
     * @param array $files
     * @param bool $recursion
     * @author George
     */
    private function scanDir(string $path, array &$files, bool $recursion = false): void
    {
        $handler = opendir($path);

        while ($item = readdir($handler)) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }

            $itemPath = sprintf('%s/%s', $path, $item);

            if (is_file($itemPath)) {
                $files[] = $itemPath;
                continue;
            }

            if ($recursion && is_dir($itemPath)) {
                $this->scanDir($itemPath, $files, $recursion);
            }
        }
    }

    /**
     * Get project detail from translator service
     *
     * Date: 2022/6/7
     * @param string $project
     * @return array
     * @throws RequestException
     * @throws Exception
     * @author George
     */
    public static function getDetailFromTranslator(string $project): array
    {
        $client = new Factory();
        $url = sprintf('%s/project/%s', Certification::$endpoint, $project);
        $response = $client->withToken(Certification::$token)->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
            ->get($url)
            ->throw()
            ->json();

        $code = Arr::get($response, 'code');
        if ($code != 200) {
            throw new Exception(Arr::get($response, 'message'));
        }

        return Arr::get($response, 'data.item');
    }

    /**
     * Get the default language for the project.
     *
     * Date: 2023/3/17
     * @author George
     * @return string
     * @throws RequestException
     */
    public function getDefaultLanguage(): string
    {
        $this->fetchLanguages();

        $language = collect($this->languages)->where('default', true)->first();
        self::$language = $language;

        return $language->name;
    }

    /**
     * Fetch project info from remote.
     *
     * Date: 2023/3/18
     * @author George
     * @throws RequestException
     */
    public function fetchDetail(): void
    {
        $request = Certification::$request;

        $url = sprintf('%s/projects/%s', Certification::$endpoint, $this->id);
        $payload = $request->get($url)->throw()->json();

        $attributes = Arr::get($payload, 'data.attributes');

        conmsg(sprintf('Project ID: %s', Arr::get($attributes, 'id')));
        conmsg(sprintf('Project Name: %s', Arr::get($attributes, 'name')));
        conmsg(sprintf('Project Description: %s', Arr::get($attributes, 'description')));
    }
}