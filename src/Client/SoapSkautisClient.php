<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Client;

use Psr\Log\LoggerInterface;
use Webwings\SkautisBundle\Exception\SkautisAuthenticationException;
use Webwings\SkautisBundle\Exception\SkautisPermissionException;
use Webwings\SkautisBundle\Exception\SkautisTransportException;
use Webwings\SkautisBundle\SkautisConfig;

/**
 * Speaks the skautIS SOAP dialect.
 *
 * A call to Service.Method sends one wrapper element named `methodInput`
 * (first letter lowercased) holding the arguments plus the credentials, and
 * gets back `MethodResult`, which for collections contains repeated
 * `MethodOutput` elements.
 */
final class SoapSkautisClient implements SkautisClientInterface
{
    public function __construct(
        private readonly SoapClientFactory $clientFactory,
        private readonly SkautisConfig $config,
        private readonly TokenHolder $tokenHolder,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function callAll(string $service, string $method, array $args = []): array
    {
        $result = $this->call($service, $method, $args);
        $output = null !== $result ? ($result->{$method.'Output'} ?? null) : null;

        if (null === $output) {
            return [];
        }

        return array_values(array_filter(
            \is_array($output) ? $output : [$output],
            static fn (mixed $row): bool => $row instanceof \stdClass,
        ));
    }

    public function callOne(string $service, string $method, array $args = []): ?\stdClass
    {
        $result = $this->call($service, $method, $args);

        if (null === $result) {
            return null;
        }

        // Some single-record methods still nest the payload in a *Output element.
        $output = $result->{$method.'Output'} ?? null;

        if (null !== $output) {
            $output = \is_array($output) ? ($output[0] ?? null) : $output;

            return $output instanceof \stdClass ? $output : null;
        }

        return $result;
    }

    /**
     * @param array<string, scalar|null> $args
     */
    private function call(string $service, string $method, array $args = []): ?\stdClass
    {
        $input = array_filter(
            array_merge($this->credentials(), $args),
            static fn (mixed $value): bool => null !== $value,
        );

        // SoapClient works from the WSDL, so arguments the method does not declare
        // (ID_Application on most read methods, for instance) are simply dropped.
        $payload = [lcfirst($method).'Input' => $input];

        try {
            $response = $this->clientFactory->get($service)->__soapCall($method, [$payload]);
        } catch (\SoapFault $e) {
            throw $this->translateFault($service, $method, $e);
        }

        $this->logger?->debug('skautIS {service}.{method}', [
            'service' => $service,
            'method' => $method,
            'args' => array_keys($args),
        ]);

        $result = $response->{$method.'Result'} ?? null;

        return $result instanceof \stdClass ? $result : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function credentials(): array
    {
        return [
            'ID_Login' => $this->tokenHolder->getToken(),
            'ID_Application' => $this->config->appId(),
        ];
    }

    private function translateFault(string $service, string $method, \SoapFault $fault): \Throwable
    {
        $message = $fault->getMessage();
        $context = sprintf('%s.%s: %s', $service, $method, $message);

        // skautIS reports business errors as SOAP faults with a Czech message.
        if (preg_match('~(byl odhlášen|Přihlášení (vypršelo|neexistuje)|není přihlášen)~ui', $message)) {
            return new SkautisAuthenticationException($context, previous: $fault);
        }

        if (preg_match('~(nemáte oprávnění|nedostatečná práva|není povoleno)~ui', $message)) {
            return new SkautisPermissionException($context, previous: $fault);
        }

        return new SkautisTransportException($context, previous: $fault);
    }
}
