<?php

namespace App\Tests\Support;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;

/**
 * Fixe le comportement actuel d'API Platform (le noyau est toujours demarre)
 * explicitement, pour ne pas dependre du changement de defaut annonce en 5.0.
 */
abstract class ApiTestCase extends BaseApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;
}
