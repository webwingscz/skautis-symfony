<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Exception;

/**
 * The skautIS token is missing, expired or was invalidated by a logout.
 */
final class SkautisAuthenticationException extends SkautisException
{
}
