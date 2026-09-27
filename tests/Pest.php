<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature testleri Laravel TestCase üzerinde, patek_perde_test veritabanında,
// her test kendi transaction'ında çalışır (phpunit.xml'deki DB ayarları geçerlidir).
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
