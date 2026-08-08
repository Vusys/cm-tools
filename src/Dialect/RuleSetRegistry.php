<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect;

use Vusys\CmTools\Dialect\RuleSet\CommonMarkStrictRuleSet;
use Vusys\CmTools\Dialect\RuleSet\GitHubFlavoredMarkdownRuleSet;
use Vusys\CmTools\Dialect\RuleSet\JiraCloudMarkdownRuleSet;

/**
 * Looks up a {@see RuleSet} by the short name used on the command line (`--dialect=github`),
 * and lists every registered rule set for `cm-tools detect`.
 */
final readonly class RuleSetRegistry
{
    /** @var array<string, RuleSet> */
    private array $ruleSets;

    public function __construct()
    {
        $this->ruleSets = [
            'commonmark' => new CommonMarkStrictRuleSet,
            'github' => new GitHubFlavoredMarkdownRuleSet,
            'jira-cloud' => new JiraCloudMarkdownRuleSet,
        ];
    }

    public function get(string $key): ?RuleSet
    {
        return $this->ruleSets[$key] ?? null;
    }

    /**
     * @return array<string, RuleSet>
     */
    public function all(): array
    {
        return $this->ruleSets;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->ruleSets);
    }
}
