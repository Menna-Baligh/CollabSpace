<?php

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

uses(
    TestCase::class,
)->in('Unit');

uses(
    Tests\TestCase::class,
)->in('Unit');
