<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Echec d'authentification sur /api/login.
 *
 * Renvoie 429 quand login_throttling a coupe (trop de tentatives), et delegue
 * tous les autres cas au handler Lexik (401 "Invalid credentials.").
 */
final class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly AuthenticationFailureHandlerInterface $decorated,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JWTAuthenticationFailureResponse(
                'Trop de tentatives de connexion. Reessayez dans une minute.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return $this->decorated->onAuthenticationFailure($request, $exception);
    }
}
