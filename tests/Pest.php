<?php

use Brickhouse\Http\Tests;

pest()
    ->extend(Tests\TestCase::class)
    ->in('Feature', 'Unit');
