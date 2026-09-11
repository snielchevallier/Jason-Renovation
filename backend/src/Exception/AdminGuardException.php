<?php

namespace App\Exception;

/**
 * Erreur metier destinee a etre affichee a l'utilisateur du back-office
 * (message flash + redirection), jamais une page d'erreur 500.
 *
 * A lever depuis les controleurs EasyAdmin (garde-fous, regles metier) plutot
 * qu'une \RuntimeException brute : voir AdminGuardExceptionListener.
 */
class AdminGuardException extends \RuntimeException
{
}
