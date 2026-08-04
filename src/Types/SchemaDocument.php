<?php

namespace Flyo\Yii\Types;

use Flyo\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The openapi document with the schemas of your flyo blocks and entities.
 *
 * The document is provided by the nitro api and describes every block component and entity type of your
 * project. It is the input of the [[TypeGenerator]], see [[\Flyo\Yii\Controllers\TypesController]].
 */
class SchemaDocument
{
    /**
     * @var string The path of the schemas endpoint, appended to the host of the [[Configuration]].
     */
    public const SCHEMAS_PATH = '/openapi/schemas';

    /**
     * @var array<string, array<string, mixed>> The schemas of the document, keyed by their name.
     */
    private array $schemas;

    /**
     * @param array<string, mixed> $document The decoded openapi document, either the full document or only
     * its schemas, see [[extractSchemas()]].
     */
    public function __construct(array $document)
    {
        $this->schemas = self::extractSchemas($document);
    }

    /**
     * Creates the document from a json string.
     */
    public static function fromJson(string $json): self
    {
        $document = json_decode($json, true);

        if (!is_array($document)) {
            throw new InvalidArgumentException('The openapi document is not a valid json object: ' . json_last_error_msg());
        }

        return new self($document);
    }

    /**
     * Creates the document from a local json file, useful to commit the document or to work offline.
     */
    public static function fromFile(string $file): self
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new InvalidArgumentException("The openapi document '{$file}' does not exist or is not readable.");
        }

        return self::fromJson((string) file_get_contents($file));
    }

    /**
     * Downloads the document from the given url.
     *
     * @param string $url The absolute url of the schemas endpoint.
     * @param string|null $token The flyo api token, send as `token` query param.
     * @param ClientInterface|null $client Mainly used for testing.
     */
    public static function fromUrl(string $url, ?string $token = null, ?ClientInterface $client = null): self
    {
        $client = $client ?: new Client();

        try {
            $response = $client->request('GET', $url, [
                'query' => $token === null ? [] : ['token' => $token],
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => true,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Unable to download the openapi document from '{$url}': " . $e->getMessage(), 0, $e);
        }

        return self::fromJson((string) $response->getBody());
    }

    /**
     * Downloads the document with the host and the token of the given (or the default) sdk configuration,
     * which is set up by [[\Flyo\Yii\Module::bootstrap()]].
     */
    public static function fromConfiguration(?Configuration $configuration = null, ?ClientInterface $client = null): self
    {
        $configuration = $configuration ?: Configuration::getDefaultConfiguration();

        return self::fromUrl(self::urlFromConfiguration($configuration), $configuration->getApiKey('token'), $client);
    }

    /**
     * The url of the schemas endpoint for the given configuration.
     */
    public static function urlFromConfiguration(Configuration $configuration): string
    {
        return rtrim((string) $configuration->getHost(), '/') . self::SCHEMAS_PATH;
    }

    /**
     * All schemas of the document, keyed by their name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * A single schema or null when the document does not contain it.
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(string $name): ?array
    {
        return $this->schemas[$name] ?? null;
    }

    /**
     * Normalizes the different shapes a schema document can have: the schemas of an openapi 3 document
     * (`components.schemas`), of a swagger 2 document (`definitions`), a `schemas` envelope or a plain map
     * of schema name to schema.
     *
     * @param array<string, mixed> $document
     * @return array<string, array<string, mixed>>
     */
    private static function extractSchemas(array $document): array
    {
        $candidates = [
            $document['components']['schemas'] ?? null,
            $document['schemas'] ?? null,
            $document['definitions'] ?? null,
            $document,
        ];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $schemas = array_filter($candidate, static fn (mixed $schema, mixed $name): bool => is_string($name) && self::isSchema($schema), ARRAY_FILTER_USE_BOTH);

            if ($schemas !== []) {
                /** @var array<string, array<string, mixed>> $schemas */
                return $schemas;
            }
        }

        throw new InvalidArgumentException('The openapi document does not contain any schema, expected them in `components.schemas`, `definitions` or as top level map.');
    }

    /**
     * Whether the given value looks like a json schema or not.
     */
    private static function isSchema(mixed $schema): bool
    {
        if (!is_array($schema)) {
            return false;
        }

        foreach (['type', 'properties', '$ref', 'allOf', 'oneOf', 'anyOf', 'enum', 'items', 'additionalProperties'] as $key) {
            if (array_key_exists($key, $schema)) {
                return true;
            }
        }

        return false;
    }
}
