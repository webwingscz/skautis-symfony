<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Client;

use Webwings\SkautisBundle\Exception\SkautisTransportException;
use Webwings\SkautisBundle\SkautisConfig;

/**
 * Builds and reuses one SoapClient per skautIS web service.
 *
 * Creating a client downloads and parses the WSDL, which is slow, so the
 * clients are kept for the lifetime of the request and the WSDL itself is
 * cached on disk (see soap.wsdl_cache_* in docker/php/conf.d/app.ini).
 */
final class SoapClientFactory
{
    /** @var array<string, \SoapClient> */
    private array $clients = [];

    public function __construct(
        private readonly SkautisConfig $config,
        private readonly int $timeout = 30,
    ) {
    }

    public function get(string $service): \SoapClient
    {
        return $this->clients[$service] ??= $this->create($service);
    }

    private function create(string $service): \SoapClient
    {
        try {
            return new \SoapClient($this->config->wsdlUrl($service), [
                'soap_version' => \SOAP_1_2,
                'encoding' => 'utf-8',
                'exceptions' => true,
                'cache_wsdl' => \WSDL_CACHE_DISK,
                // Without this, a result with exactly one row comes back as a bare
                // object instead of an array, so every caller would need to branch.
                'features' => \SOAP_SINGLE_ELEMENT_ARRAYS,
                'connection_timeout' => $this->timeout,
                'stream_context' => stream_context_create([
                    'http' => ['timeout' => $this->timeout],
                ]),
            ]);
        } catch (\SoapFault $e) {
            throw new SkautisTransportException(sprintf('Nepodařilo se načíst WSDL služby "%s": %s', $service, $e->getMessage()), previous: $e);
        }
    }
}
