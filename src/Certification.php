<?php

namespace Betterde\TranslatorCli;

use Illuminate\Support\Arr;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

/**
 * Date: 2022/6/5
 * @author George
 * @package Betterde\TranslatorCli
 */
class Certification
{
    static public string $endpoint;

    static public string $project;

    static public string $email;

    static public string $token;

    /**
     * @var PendingRequest $request
     */
    static public $request;

    /**
     * Load certification config from local json file.
     *
     * Date: 2022/6/5
     * @param string $path
     * @author George
     */
    public static function load(string $path): void
    {
        $certFile = sprintf('%s/%s', $path, '.texterify.json');

        if (file_exists($certFile)) {
            $certification = json_decode(file_get_contents($certFile), JSON_UNESCAPED_UNICODE);
            self::$endpoint = Arr::get($certification, 'endpoint');
            self::$project = Arr::get($certification, 'project');
            self::$email = Arr::get($certification, 'email');
            self::$token = Arr::get($certification, 'token');
            self::request();
        } else {
            warn('Please use the auth command to generate the authentication information first.');
            conmsg('e.g. translator auth <domain> <project> <token>');
            exit(1);
        }
    }

    /**
     * Verify user credentials are correct.
     *
     * Date: 2023/3/17
     * @author George
     * @param string $endpoint
     * @param string $project
     * @param string $email
     * @param string $token
     * @param bool $force
     * @throws RequestException
     */
    public static function verify(string $endpoint, string $project, string $email, string $token, bool $force): void
    {
        $client = new Factory();
        $url = sprintf('%s/projects/%s', $endpoint, $project);

        $headers = [
            'Accept' => 'application/json',
            'Auth-Email' => $email,
            'Auth-Secret' => $token,
            'Content-Type' => 'application/json',
        ];


        $response = $client->withHeaders($headers)
            ->get($url)
            ->throw();

        if ($response->successful()) {
            self::write($endpoint, $project, $email, $token, $force);
        }
    }

    /**
     * Write certification config into local json file.
     *
     * Date: 2022/6/5
     * @author George
     * @param string $project
     * @param string $email
     * @param string $token
     * @param bool $force
     * @param string $endpoint
     */
    public static function write(string $endpoint, string $project, string $email, string $token, bool $force): void
    {
        $identity = [
            'endpoint' => $endpoint,
            'project' => $project,
            'email' => $email,
            'token' => $token
        ];

        $filename = '.texterify.json';

        $certification = json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (empty($force) && file_exists($filename)) {
            warn('The certification file for the translator service already exists!');
        } else {
            file_put_contents($filename, $certification);
            conmsg("Successful!");
        }
    }

    /**
     * Date: 2023/3/18
     * @author George
     * @return PendingRequest
     */
    public static function request(): PendingRequest
    {
        if (!isset(self::$request)) {
            $request = new Factory();
            $headers = [
                'Accept' => 'application/json',
                'Auth-Email' => self::$email,
                'Auth-Secret' => self::$token,
                'Content-Type' => 'application/json',
            ];

            self::$request = $request->withHeaders($headers);
        }

        return self::$request;
    }
}