<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('package classes are not final')
    ->expect('BBSLab\LaravelMjml')
    ->classes()
    ->not->toBeFinal();

arch('package classes do not use private methods')
    ->expect('BBSLab\LaravelMjml')
    ->classes()
    ->not->toHavePrivateMethods();
