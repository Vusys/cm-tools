<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Dialect\RuleSetRegistry;

final class RuleSetRegistryTest extends TestCase
{
    public function test_it_resolves_known_keys(): void
    {
        $registry = new RuleSetRegistry;

        $this->assertNotNull($registry->get('github'));
        $this->assertNotNull($registry->get('jira-cloud'));
        $this->assertNotNull($registry->get('commonmark'));
    }

    public function test_it_returns_null_for_an_unknown_key(): void
    {
        $this->assertNull((new RuleSetRegistry)->get('nonexistent'));
    }

    public function test_keys_matches_the_registered_rule_sets(): void
    {
        $registry = new RuleSetRegistry;

        $this->assertSame(array_keys($registry->all()), $registry->keys());
    }
}
