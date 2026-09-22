<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle;

/**
 * Connection settings for one skautIS instance (test or production).
 */
final readonly class SkautisConfig
{
    public function __construct(
        private string $baseUrl,
        private string $appId,
    ) {
    }

    public function appId(): string
    {
        return $this->appId;
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function isConfigured(): bool
    {
        return '' !== $this->appId;
    }

    /**
     * Every skautIS web service is a separate .asmx endpoint with its own WSDL.
     */
    public function wsdlUrl(string $service): string
    {
        return sprintf('%s/JunakWebservice/%s.asmx?WSDL', $this->baseUrl(), $service);
    }

    /**
     * The hosted login form. skautIS POSTs skautIS_Token, skautIS_IDRole,
     * skautIS_IDUnit and skautIS_DateLogout back to the URL registered for this appid.
     */
    public function loginUrl(): string
    {
        return sprintf('%s/Login/?appid=%s', $this->baseUrl(), urlencode($this->appId));
    }

    /**
     * Ends the skautIS session itself, then redirects to the registered logout URL.
     */
    public function logoutUrl(string $token): string
    {
        return sprintf(
            '%s/Login/LogOut.aspx?AppId=%s&Token=%s',
            $this->baseUrl(),
            urlencode($this->appId),
            urlencode($token),
        );
    }
}
