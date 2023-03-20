<?php

use Illuminate\Container\Container;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Contracts\Container\BindingResolutionException;

if (!function_exists('conmsg')) {
    /**
     * Output the given text to the console.
     *
     * @param string $output
     * @return void
     */
    function conmsg(string $output): void
    {
        output('<info>' . $output . '</info>');
    }
}

if (!function_exists('warn')) {
    /**
     * Output the given text to the console.
     *
     * @param string $output
     * @return void
     */
    function warn(string $output): void
    {
        output('<fg=red>' . $output . '</>');
    }
}

if (!function_exists('table')) {
    /**
     * Output a table to the console.
     *
     * @param array $headers
     * @param array $rows
     * @return void
     */
    function table(array $headers = [], array $rows = []): void
    {
        $table = new Table(new ConsoleOutput);

        $table->setHeaders($headers)->setRows($rows);

        $table->render();
    }
}

if (!function_exists('output')) {
    /**
     * Output the given text to the console.
     *
     * @param string $output
     * @return void
     */
    function output(string $output): void
    {
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') {
            return;
        }

        $stdout = new ConsoleOutput();

        $stdout->writeln($output);
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve the given class from the container.
     *
     * @param string $class
     * @return mixed
     * @throws BindingResolutionException
     */
    function resolve(string $class): mixed
    {
        return Container::getInstance()->make($class);
    }
}

if (!function_exists('swap')) {
    /**
     * Swap the given class implementation in the container.
     *
     * @param string $class
     * @param mixed $instance
     * @return void
     */
    function swap(string $class, mixed $instance): void
    {
        Container::getInstance()->instance($class, $instance);
    }
}

if (!function_exists('retry')) {
    /**
     * Retry the given function N times.
     *
     * @param int $retries
     * @param $fn
     * @param int $sleep
     * @return mixed
     */
    function retry(int $retries, $fn, int $sleep = 0): mixed
    {
        beginning:
        try {
            return $fn();
        } catch (Exception $e) {
            if (!$retries) {
                throw $e;
            }

            $retries--;

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }
}

if (!function_exists('ends_with')) {
    /**
     * Determine if a given string ends with a given substring.
     *
     * @param string $haystack
     * @param array|string $needles
     * @return bool
     */
    function ends_with(string $haystack, array|string $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if (substr($haystack, -strlen($needle)) === (string)$needle) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('starts_with')) {
    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string $haystack
     * @param string|string[] $needles
     * @return bool
     */
    function starts_with(string $haystack, array|string $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ((string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('user')) {
    /**
     * Get the user
     */
    function user()
    {
        if (!isset($_SERVER['SUDO_USER'])) {
            return $_SERVER['USER'];
        }

        return $_SERVER['SUDO_USER'];
    }
}

if (!function_exists('projectPath')) {
    function projectPath($path = null): string
    {
        if ($path === null) {
            $path = getcwd();
        }

        $vendor = sprintf('%s/vendor/autoload.php', $path);
        if (file_exists($vendor)) {
            require $vendor;
        }

        return $path;
    }
}

if (!function_exists('languagePath')) {
    function languagePath(string $path, string $language = null): string
    {
        $directories = ['resources/lang', 'lang'];

        foreach ($directories as $directory) {
            $segments = array_filter([$path, $directory, $language]);
            $langPath = implode('/', $segments);
            if (is_dir($langPath)) {
                return $langPath;
            }
        }

        warn('No language pack found in the current project directory.');
        exit(1);
    }
}
